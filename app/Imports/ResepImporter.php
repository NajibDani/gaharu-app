<?php

namespace App\Imports;

use App\Models\MasterBarang;
use App\Models\ResepBahanBaku;
use App\Models\ResepBtklBop;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import Resep (BTKL/BOP + Bahan Baku) dari file Excel.
 *
 * Format: 1 baris = 1 bahan baku, dikelompokkan berdasarkan kode_produk.
 * Semua baris dengan kode_produk yang sama akan digabung jadi 1 resep dengan
 * banyak bahan baku (persis seperti perilaku ResepBtklBopController::store()).
 *
 * Aturan:
 * - Kolom wajib: kode_produk, output_qty, kode_bahan, qty_bahan
 * - kode_produk harus barang dengan is_barang_jadi = 1, jika tidak ditemukan -> seluruh
 *   baris untuk kode_produk itu dilewati (skip, dicatat sebagai error informasi).
 * - Jika produk SUDAH PUNYA resep -> seluruh baris untuk kode_produk itu dilewati (skip).
 * - kode_bahan harus barang dengan is_bahan_baku = 1, jika tidak ditemukan -> hanya baris
 *   bahan itu yang dilewati (resep tetap dibuat dengan bahan lain yang valid).
 * - Role user TIDAK diperiksa saat import (sesuai permintaan).
 */
class ResepImporter
{
    protected int $createdRecipes = 0;
    protected int $createdIngredients = 0;
    protected int $skippedRecipes = 0;
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
        $colIndex  = array_flip($headerRow);

        foreach (['kode_produk', 'output_qty', 'kode_bahan', 'qty_bahan'] as $required) {
            if (!isset($colIndex[$required])) {
                $this->errors[] = "Kolom wajib '{$required}' tidak ditemukan di header baris pertama. Gunakan template yang disediakan.";
            }
        }
        if (!empty($this->errors)) {
            return $this->summary();
        }

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

        // Kelompokkan baris berdasarkan kode_produk, urut sesuai kemunculan pertama
        $groups = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $kodeProduk = $get($row, 'kode_produk');
            if ($kodeProduk === '') {
                $this->errors[] = 'Baris ' . ($i + 1) . ': kode_produk kosong, dilewati.';
                continue;
            }

            $groups[$kodeProduk][] = ['data' => $row, 'excel_row' => $i + 1];
        }

        foreach ($groups as $kodeProduk => $groupRows) {
            $firstRow = $groupRows[0]['data'];

            $produk = MasterBarang::withoutGlobalScopes()
                ->where('kode_barang', $kodeProduk)
                ->where('is_barang_jadi', 1)
                ->first();

            if (!$produk) {
                $this->errors[] = "Produk kode '{$kodeProduk}' tidak ditemukan atau bukan Barang Jadi. Semua baris resep untuk kode ini dilewati.";
                continue;
            }

            $sudahAdaResep = ResepBtklBop::where('produk_id', $produk->id)->exists();
            if ($sudahAdaResep) {
                $this->skippedRecipes++;
                $this->skippedRows[] = "Produk '{$kodeProduk}' ({$produk->nama}) sudah punya resep, dilewati.";
                continue;
            }

            $outputQty = $numeric($get($firstRow, 'output_qty'), 0);
            if ($outputQty <= 0) {
                $this->errors[] = "Produk '{$kodeProduk}': output_qty tidak valid (harus > 0), resep dilewati.";
                continue;
            }
            $satuanOutput = $get($firstRow, 'satuan_output') ?: 'Batch';
            $btkl         = $numeric($get($firstRow, 'btkl_per_batch'), 0);
            $bop          = $numeric($get($firstRow, 'bop_per_batch'), 0);

            $bahanGrouped = [];
            $groupErrors  = [];
            foreach ($groupRows as $r) {
                $rowData     = $r['data'];
                $kodeBahan   = $get($rowData, 'kode_bahan');
                $qtyBahan    = $numeric($get($rowData, 'qty_bahan'), 0);
                $satuanBahan = $get($rowData, 'satuan_bahan');

                if ($kodeBahan === '' || $qtyBahan <= 0) {
                    $groupErrors[] = "Baris {$r['excel_row']}: kode_bahan/qty_bahan kosong atau tidak valid, dilewati.";
                    continue;
                }

                $bahan = MasterBarang::withoutGlobalScopes()
                    ->where('kode_barang', $kodeBahan)
                    ->where('is_bahan_baku', 1)
                    ->first();

                if (!$bahan) {
                    $groupErrors[] = "Baris {$r['excel_row']}: bahan baku kode '{$kodeBahan}' tidak ditemukan / bukan Bahan Baku, dilewati.";
                    continue;
                }

                if (isset($bahanGrouped[$bahan->id])) {
                    $bahanGrouped[$bahan->id]['qty'] += $qtyBahan;
                } else {
                    $bahanGrouped[$bahan->id] = [
                        'qty'    => $qtyBahan,
                        'satuan' => $satuanBahan !== '' ? $satuanBahan : ($bahan->satuan ?? '-'),
                    ];
                }
            }

            if (empty($bahanGrouped)) {
                $this->errors[] = "Produk '{$kodeProduk}': tidak ada bahan baku valid, resep dilewati.";
                $this->errors   = array_merge($this->errors, $groupErrors);
                continue;
            }

            try {
                DB::transaction(function () use ($produk, $outputQty, $satuanOutput, $btkl, $bop, $bahanGrouped) {
                    $resep = ResepBtklBop::create([
                        'produk_id'      => $produk->id,
                        'output_qty'     => $outputQty,
                        'satuan_output'  => $satuanOutput,
                        'btkl_per_batch' => $btkl,
                        'bop_per_batch'  => $bop,
                    ]);

                    MasterBarang::where('id', $produk->id)->update(['resep_id' => $resep->id]);

                    foreach ($bahanGrouped as $bahanId => $val) {
                        ResepBahanBaku::create([
                            'resep_id'  => $resep->id,
                            'bahan_id'  => $bahanId,
                            'qty_bahan' => $val['qty'],
                            'satuan'    => $val['satuan'],
                        ]);
                        $this->createdIngredients++;
                    }
                });
                $this->createdRecipes++;
                $this->errors = array_merge($this->errors, $groupErrors);
            } catch (\Throwable $e) {
                $this->errors[] = "Produk '{$kodeProduk}': gagal disimpan ({$e->getMessage()}).";
            }
        }

        return $this->summary();
    }

    protected function summary(): array
    {
        return [
            'createdRecipes'     => $this->createdRecipes,
            'createdIngredients' => $this->createdIngredients,
            'skippedRecipes'     => $this->skippedRecipes,
            'skippedRows'        => $this->skippedRows,
            'errors'             => $this->errors,
        ];
    }
}
