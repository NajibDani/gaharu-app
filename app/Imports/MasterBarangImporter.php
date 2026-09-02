<?php

namespace App\Imports;

use App\Models\Kategori;
use App\Models\MasterBarang;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import Master Barang dari file Excel.
 *
 * Aturan:
 * - Kolom wajib: kode_barang, nama, kategori, jenis_utama, satuan
 * - Jika kode_barang SUDAH ADA di database -> baris dilewati (skip), tidak dianggap error.
 * - Kategori dicocokkan berdasarkan nama (case-insensitive) ke tabel kategori.
 * - jenis_utama harus salah satu: BAHAN_BAKU, BARANG_JADI, OPERATIONAL
 * - tipe_penjualan wajib diisi jika jenis_utama = BARANG_JADI, harus salah satu:
 *   POS Kejingga, POS Gaharu, B2B
 * - Role user TIDAK diperiksa saat import (sesuai permintaan): semua baris valid akan
 *   langsung dibuat tanpa filter tipe_penjualan berdasarkan role.
 */
class MasterBarangImporter
{
    protected array $jenisAllowed = ['BAHAN_BAKU', 'BAHAN_SETENGAH_JADI', 'BARANG_JADI', 'OPERATIONAL'];
    protected array $tipeAllowed  = ['POS Kejingga', 'POS Gaharu', 'B2B'];

    protected int $created = 0;
    protected int $skipped = 0;
    protected array $skippedRows = [];
    protected array $errors = [];

    public function import(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            $this->errors[] = 'File kosong / tidak ada data.';
            return $this->summary();
        }

        $headerRow = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $colIndex  = array_flip($headerRow); // nama_kolom => index array

        foreach (['kode_barang', 'nama', 'kategori', 'jenis_utama', 'satuan'] as $required) {
            if (!isset($colIndex[$required])) {
                $this->errors[] = "Kolom wajib '{$required}' tidak ditemukan di header baris pertama. Gunakan template yang disediakan.";
            }
        }
        if (!empty($this->errors)) {
            return $this->summary();
        }

        $kategoriMap = Kategori::all()->keyBy(fn ($k) => strtolower(trim($k->nama)));

        $get = function (array $row, string $key) use ($colIndex) {
            return isset($colIndex[$key], $row[$colIndex[$key]]) ? trim((string) $row[$colIndex[$key]]) : '';
        };

        $numeric = function ($val, $default = 0) {
            if ($val === '' || $val === null) {
                return $default;
            }
            $clean = str_replace([',', ' '], ['.', ''], (string) $val);
            return is_numeric($clean) ? (float) $clean : $default;
        };

        for ($i = 1; $i < count($rows); $i++) {
            $row         = $rows[$i];
            $excelRowNum = $i + 1;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // baris kosong total, lewati diam-diam
            }

            $kodeBarang        = $get($row, 'kode_barang');
            $nama              = $get($row, 'nama');
            $kategoriNama      = $get($row, 'kategori');
            $jenisUtama        = strtoupper($get($row, 'jenis_utama'));
            $satuan            = $get($row, 'satuan');
            $satuanPembelian   = $get($row, 'satuan_pembelian');
            $konversiPembelian = $get($row, 'konversi_pembelian');
            $tipePenjualan     = $get($row, 'tipe_penjualan');
            $hargaB2b          = $get($row, 'harga_jual_b2b');
            $hargaPos          = $get($row, 'harga_jual_pos');
            $hpp               = $get($row, 'hpp_referensi');
            $minStock          = $get($row, 'minimum_stock') ?: $get($row, 'minimum_stock_umum');
            $minOrder          = $get($row, 'minimum_order');

            // Load semua master gudang & divisi untuk pencocokan kolom dinamis
            $allGudangs = \App\Models\MasterGudang::with('divisi')->get();

            // Kumpulkan nilai minimum stock dari seluruh kolom (baik dinamis maupun legacy)
            // Format: [ ['gudang_id' => ..., 'divisi_id' => ..., 'val' => ...], ... ]
            $minStockEntries = [];

            foreach ($allGudangs as $g) {
                $slugG = \Illuminate\Support\Str::slug($g->nama, '_');
                if ($g->divisi->count() > 0) {
                    foreach ($g->divisi as $d) {
                        $slugD = \Illuminate\Support\Str::slug($d->nama, '_');
                        // Cek beberapa kemungkinan nama kolom
                        $val = $get($row, "min_stock_{$slugG}_{$slugD}");
                        if ($val === '') {
                            // Cek format legacy jika ada (e.g. min_stock_kejingga_kitchen, min_stock_gaharu_kitchen)
                            if (str_contains(strtolower($g->nama), 'kejingga')) {
                                if (str_contains(strtolower($d->nama), 'kitchen')) $val = $get($row, 'min_stock_kejingga_kitchen');
                                elseif (str_contains(strtolower($d->nama), 'barista')) $val = $get($row, 'min_stock_kejingga_barista');
                                elseif (str_contains(strtolower($d->nama), 'server')) $val = $get($row, 'min_stock_kejingga_server');
                            } elseif (str_contains(strtolower($g->nama), 'gaharu')) {
                                if (str_contains(strtolower($d->nama), 'kitchen')) $val = $get($row, 'min_stock_gaharu_kitchen');
                                elseif (str_contains(strtolower($d->nama), 'barista')) $val = $get($row, 'min_stock_gaharu_barista');
                                elseif (str_contains(strtolower($d->nama), 'server')) $val = $get($row, 'min_stock_gaharu_server');
                            }
                        }

                        if ($val !== '') {
                            $minStockEntries[] = [
                                'gudang_id' => $g->id,
                                'divisi_id' => $d->id,
                                'val'       => $val,
                            ];
                        }
                    }
                } else {
                    $val = $get($row, "min_stock_{$slugG}");
                    if ($val === '') {
                        if (str_contains(strtolower($g->nama), 'central kitchen')) {
                            $val = $get($row, 'min_stock_ck') ?: $get($row, 'minimum_stock_ck');
                        } elseif (str_contains(strtolower($g->nama), 'gudang utama') || str_contains(strtolower($g->nama), 'utama')) {
                            $val = $get($row, 'min_stock_gudang_utama');
                        } elseif (str_contains(strtolower($g->nama), 'b2b')) {
                            $val = $get($row, 'min_stock_b2b');
                        }
                    }

                    if ($val !== '') {
                        $minStockEntries[] = [
                            'gudang_id' => $g->id,
                            'divisi_id' => null,
                            'val'       => $val,
                        ];
                    }
                }
            }

            if ($kodeBarang === '' || $nama === '') {
                $this->errors[] = "Baris {$excelRowNum}: kode_barang atau nama kosong, dilewati.";
                continue;
            }

            // ATURAN UTAMA: skip jika kode_barang sudah ada (cek tanpa scope role)
            $exists = MasterBarang::withoutGlobalScopes()
                ->where('kode_barang', $kodeBarang)
                ->first();
            if ($exists) {
                // UPDATE MINIMUM STOCK untuk barang yang sudah ada
                try {
                    DB::transaction(function () use ($exists, $minStock, $minStockEntries, $numeric) {
                        if ($minStock !== '') {
                            $exists->update(['minimum_stock' => $numeric($minStock, 0)]);
                        }

                        // Simpan / update minimum stock per outlet & divisi dinamis
                        foreach ($minStockEntries as $entry) {
                            if ($entry['gudang_id'] && $entry['val'] !== '' && $entry['val'] !== null) {
                                \App\Models\BarangMinimumStock::updateOrCreate(
                                    [
                                        'barang_id' => $exists->id,
                                        'gudang_id' => $entry['gudang_id'],
                                        'divisi_id' => $entry['divisi_id'],
                                    ],
                                    [
                                        'minimum_stock' => $numeric($entry['val'], 0),
                                        'is_active'     => true,
                                    ]
                                );
                            }
                        }
                    });
                    $this->skipped++; // Tetap dikelompokkan ke "skipped" atau "updated" agar user tahu
                    $this->skippedRows[] = "Baris {$excelRowNum}: kode_barang '{$kodeBarang}' sudah ada, minimum stock diperbarui.";
                } catch (\Throwable $e) {
                    $this->errors[] = "Baris {$excelRowNum}: gagal memperbarui minimum stock ({$e->getMessage()}).";
                }
                continue;
            }

            $kategori = $kategoriMap->get(strtolower($kategoriNama));
            if (!$kategori) {
                $this->errors[] = "Baris {$excelRowNum}: kategori '{$kategoriNama}' tidak ditemukan, dilewati.";
                continue;
            }

            if (!in_array($jenisUtama, $this->jenisAllowed, true)) {
                $this->errors[] = "Baris {$excelRowNum}: jenis_utama '{$jenisUtama}' tidak valid (harus BAHAN_BAKU / BARANG_JADI / OPERATIONAL), dilewati.";
                continue;
            }

            if ($jenisUtama === 'BARANG_JADI') {
                if (!in_array($tipePenjualan, $this->tipeAllowed, true)) {
                    $this->errors[] = "Baris {$excelRowNum}: tipe_penjualan '{$tipePenjualan}' tidak valid untuk Barang Jadi (harus POS Kejingga / POS Gaharu / B2B), dilewati.";
                    continue;
                }
            } else {
                $tipePenjualan = null;
            }

            if ($jenisUtama === 'BAHAN_SETENGAH_JADI') {
                $satuanClean = strtoupper(trim($satuan));
                if (!in_array($satuanClean, ['GR', 'ML', 'GRAM', 'MILILITER'])) {
                    $this->errors[] = "Baris {$excelRowNum}: Untuk Bahan Setengah Jadi, satuan '{$satuan}' tidak valid (harus gram/gr atau mililiter/ml), dilewati.";
                    continue;
                }
                if ($satuanClean === 'GRAM') {
                    $satuan = 'GR';
                } elseif ($satuanClean === 'MILILITER') {
                    $satuan = 'ML';
                } else {
                    $satuan = $satuanClean;
                }
            }

            $hargaB2bVal = $jenisUtama === 'BARANG_JADI' ? $numeric($hargaB2b) : 0;
            $hargaPosVal = $jenisUtama === 'BARANG_JADI' ? $numeric($hargaPos) : 0;

            try {
                DB::transaction(function () use (
                    $kategori, $kodeBarang, $nama, $satuan, $satuanPembelian,
                    $konversiPembelian, $jenisUtama, $tipePenjualan,
                    $hargaB2bVal, $hargaPosVal, $hpp, $minStock, $minOrder,
                    $minStockEntries,
                    $numeric
                ) {
                    $barang = MasterBarang::create([
                        'kategori_id'           => $kategori->id,
                        'resep_id'              => null,
                        'kode_barang'           => $kodeBarang,
                        'nama'                  => $nama,
                        'satuan'                => $satuan,
                        'satuan_pembelian'      => $satuanPembelian !== '' ? $satuanPembelian : null,
                        'konversi_pembelian'    => $numeric($konversiPembelian, 1),
                        'is_bahan_baku'         => $jenisUtama === 'BAHAN_BAKU',
                        'is_bahan_setengah_jadi' => $jenisUtama === 'BAHAN_SETENGAH_JADI',
                        'is_barang_jadi'        => $jenisUtama === 'BARANG_JADI',
                        'is_operational'        => $jenisUtama === 'OPERATIONAL',
                        'is_direct_consumption' => false,
                        'hpp_referensi'         => $numeric($hpp, 0),
                        'harga_jual_b2b'        => $hargaB2bVal,
                        'harga_jual_pos'        => $hargaPosVal,
                        'is_active'             => true,
                        'minimum_stock'         => $minStock !== '' ? $numeric($minStock, 0) : null,
                        'minimum_order'         => $numeric($minOrder, 1),
                        'tipe_penjualan'        => $tipePenjualan,
                    ]);

                    // Simpan minimum stock per outlet & divisi dinamis jika jenis BAHAN_BAKU
                    if ($jenisUtama === 'BAHAN_BAKU') {
                        foreach ($minStockEntries as $entry) {
                            if ($entry['gudang_id'] && $entry['val'] !== '' && $entry['val'] !== null) {
                                \App\Models\BarangMinimumStock::create([
                                    'barang_id'     => $barang->id,
                                    'gudang_id'     => $entry['gudang_id'],
                                    'divisi_id'     => $entry['divisi_id'],
                                    'minimum_stock' => $numeric($entry['val'], 0),
                                    'is_active'     => true,
                                ]);
                            }
                        }
                    }
                });
                $this->created++;
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$excelRowNum}: gagal disimpan ({$e->getMessage()}).";
            }
        }

        return $this->summary();
    }

    protected function summary(): array
    {
        return [
            'created'     => $this->created,
            'skipped'     => $this->skipped,
            'skippedRows' => $this->skippedRows,
            'errors'      => $this->errors,
        ];
    }
}
