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
            $minStockCk        = $get($row, 'min_stock_ck') ?: $get($row, 'minimum_stock_ck');
            $minStockUtama     = $get($row, 'min_stock_gudang_utama');
            $minStockKejingga  = $get($row, 'minimum_stock_kejingga');
            $minStockGaharu    = $get($row, 'minimum_stock_gaharu');
            $minOrder          = $get($row, 'minimum_order');

            // Outlet & Divisi columns
            $minKejinggaKitchen = $get($row, 'min_stock_kejingga_kitchen');
            $minKejinggaBarista = $get($row, 'min_stock_kejingga_barista');
            $minKejinggaServer  = $get($row, 'min_stock_kejingga_server');
            $minGaharuKitchen   = $get($row, 'min_stock_gaharu_kitchen');
            $minGaharuBarista   = $get($row, 'min_stock_gaharu_barista');
            $minGaharuServer    = $get($row, 'min_stock_gaharu_server');
            $minB2b             = $get($row, 'min_stock_b2b');

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
                    DB::transaction(function () use ($exists, $minStock, $minStockCk, $minStockUtama, $minStockKejingga, $minStockGaharu, $minKejinggaKitchen, $minKejinggaBarista, $minKejinggaServer, $minGaharuKitchen, $minGaharuBarista, $minGaharuServer, $minB2b, $numeric) {
                        $updateData = [];
                        if ($minStock !== '') $updateData['minimum_stock'] = $numeric($minStock, 0);
                        if ($minStockCk !== '') $updateData['minimum_stock_ck'] = $numeric($minStockCk, 0);
                        if ($minStockKejingga !== '') $updateData['minimum_stock_kejingga'] = $numeric($minStockKejingga, 0);
                        if ($minStockGaharu !== '') $updateData['minimum_stock_gaharu'] = $numeric($minStockGaharu, 0);
                        
                        if (!empty($updateData)) {
                            $exists->update($updateData);
                        }

                        // Simpan / update minimum stock per outlet & divisi
                        $allGudangs = \App\Models\MasterGudang::with('divisi')->get();
                        $ckGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'central kitchen'));
                        $gudangUtama = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'gudang utama') || str_contains(strtolower($g->nama), 'utama'));
                        $b2bGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'b2b'));
                        $gaharuGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'gaharu'));
                        $kejinggaGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'kejingga'));

                        $saveMin = function ($gudangId, $divisiId, $val) use ($exists, $numeric) {
                            if ($gudangId && $val !== '' && $val !== null) {
                                \App\Models\BarangMinimumStock::updateOrCreate(
                                    [
                                        'barang_id' => $exists->id,
                                        'gudang_id' => $gudangId,
                                        'divisi_id' => $divisiId,
                                    ],
                                    [
                                        'minimum_stock' => $numeric($val, 0),
                                        'is_active'     => true,
                                    ]
                                );
                            }
                        };

                        // Central Kitchen
                        if ($ckGudang && $minStockCk !== '') {
                            $saveMin($ckGudang->id, null, $minStockCk);
                        }

                        // Gudang Utama
                        if ($gudangUtama && $minStockUtama !== '') {
                            $saveMin($gudangUtama->id, null, $minStockUtama);
                        }

                        // B2B
                        if ($b2bGudang && $minB2b !== '') {
                            $saveMin($b2bGudang->id, null, $minB2b);
                        }

                        // KeJingga
                        if ($kejinggaGudang) {
                            $divKitchen = $kejinggaGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'kitchen'));
                            $divBarista = $kejinggaGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'barista'));
                            $divServer  = $kejinggaGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'server'));

                            if ($minKejinggaKitchen !== '') $saveMin($kejinggaGudang->id, $divKitchen?->id, $minKejinggaKitchen);
                            if ($minKejinggaBarista !== '') $saveMin($kejinggaGudang->id, $divBarista?->id, $minKejinggaBarista);
                            if ($minKejinggaServer !== '')  $saveMin($kejinggaGudang->id, $divServer?->id, $minKejinggaServer);
                            if ($minStockKejingga !== '' && $minKejinggaKitchen === '' && $minKejinggaBarista === '' && $minKejinggaServer === '') {
                                $saveMin($kejinggaGudang->id, null, $minStockKejingga);
                            }
                        }

                        // Gaharu
                        if ($gaharuGudang) {
                            $divKitchen = $gaharuGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'kitchen'));
                            $divBarista = $gaharuGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'barista'));
                            $divServer  = $gaharuGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'server'));

                            if ($minGaharuKitchen !== '') $saveMin($gaharuGudang->id, $divKitchen?->id, $minGaharuKitchen);
                            if ($minGaharuBarista !== '') $saveMin($gaharuGudang->id, $divBarista?->id, $minGaharuBarista);
                            if ($minGaharuServer !== '')  $saveMin($gaharuGudang->id, $divServer?->id, $minGaharuServer);
                            if ($minStockGaharu !== '' && $minGaharuKitchen === '' && $minGaharuBarista === '' && $minGaharuServer === '') {
                                $saveMin($gaharuGudang->id, null, $minStockGaharu);
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
                    $hargaB2bVal, $hargaPosVal, $hpp, $minStock, $minStockCk, $minStockUtama, $minStockKejingga, $minStockGaharu, $minOrder,
                    $minKejinggaKitchen, $minKejinggaBarista, $minKejinggaServer, $minGaharuKitchen, $minGaharuBarista, $minGaharuServer, $minB2b,
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
                        'minimum_stock_ck'      => $minStockCk !== '' ? $numeric($minStockCk, 0) : null,
                        'minimum_stock_kejingga' => $minStockKejingga !== '' ? $numeric($minStockKejingga, 0) : null,
                        'minimum_stock_gaharu'  => $minStockGaharu !== '' ? $numeric($minStockGaharu, 0) : null,
                        'minimum_order'         => $numeric($minOrder, 1),
                        'tipe_penjualan'        => $tipePenjualan,
                    ]);

                    // Simpan minimum stock per outlet & divisi jika jenis BAHAN_BAKU
                    if ($jenisUtama === 'BAHAN_BAKU') {
                        $allGudangs = \App\Models\MasterGudang::with('divisi')->get();
                        $ckGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'central kitchen'));
                        $gudangUtama = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'gudang utama') || str_contains(strtolower($g->nama), 'utama'));
                        $b2bGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'b2b'));
                        $gaharuGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'gaharu'));
                        $kejinggaGudang = $allGudangs->first(fn($g) => str_contains(strtolower($g->nama), 'kejingga'));

                        $saveMin = function ($gudangId, $divisiId, $val) use ($barang, $numeric) {
                            if ($gudangId && $val !== '' && $val !== null) {
                                \App\Models\BarangMinimumStock::create([
                                    'barang_id'     => $barang->id,
                                    'gudang_id'     => $gudangId,
                                    'divisi_id'     => $divisiId,
                                    'minimum_stock' => $numeric($val, 0),
                                    'is_active'     => true,
                                ]);
                            }
                        };

                        // Central Kitchen
                        if ($ckGudang && $minStockCk !== '') {
                            $saveMin($ckGudang->id, null, $minStockCk);
                        }

                        // Gudang Utama
                        if ($gudangUtama && $minStockUtama !== '') {
                            $saveMin($gudangUtama->id, null, $minStockUtama);
                        }

                        // B2B
                        if ($b2bGudang && $minB2b !== '') {
                            $saveMin($b2bGudang->id, null, $minB2b);
                        }

                        // KeJingga
                        if ($kejinggaGudang) {
                            $divKitchen = $kejinggaGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'kitchen'));
                            $divBarista = $kejinggaGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'barista'));
                            $divServer  = $kejinggaGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'server'));

                            if ($minKejinggaKitchen !== '') $saveMin($kejinggaGudang->id, $divKitchen?->id, $minKejinggaKitchen);
                            if ($minKejinggaBarista !== '') $saveMin($kejinggaGudang->id, $divBarista?->id, $minKejinggaBarista);
                            if ($minKejinggaServer !== '')  $saveMin($kejinggaGudang->id, $divServer?->id, $minKejinggaServer);
                            if ($minStockKejingga !== '' && $minKejinggaKitchen === '' && $minKejinggaBarista === '' && $minKejinggaServer === '') {
                                $saveMin($kejinggaGudang->id, null, $minStockKejingga);
                            }
                        }

                        // Gaharu
                        if ($gaharuGudang) {
                            $divKitchen = $gaharuGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'kitchen'));
                            $divBarista = $gaharuGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'barista'));
                            $divServer  = $gaharuGudang->divisi->first(fn($d) => str_contains(strtolower($d->nama), 'server'));

                            if ($minGaharuKitchen !== '') $saveMin($gaharuGudang->id, $divKitchen?->id, $minGaharuKitchen);
                            if ($minGaharuBarista !== '') $saveMin($gaharuGudang->id, $divBarista?->id, $minGaharuBarista);
                            if ($minGaharuServer !== '')  $saveMin($gaharuGudang->id, $divServer?->id, $minGaharuServer);
                            if ($minStockGaharu !== '' && $minGaharuKitchen === '' && $minGaharuBarista === '' && $minGaharuServer === '') {
                                $saveMin($gaharuGudang->id, null, $minStockGaharu);
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
