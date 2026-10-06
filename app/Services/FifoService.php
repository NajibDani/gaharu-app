<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\ResepBahanBaku;
use App\Models\MasterBarang;
use Illuminate\Support\Facades\DB;

class FifoService
{
    /*
    |--------------------------------------------------------------------------
    | RESOLVE ALTERNATIVE BAHAN (PRIORITAS)
    |--------------------------------------------------------------------------
    |
    | Menerima satu item ResepBahanBaku, cek stok bahan utama (prioritas 1).
    | Jika stok utama tidak cukup, cek alternatif berdasarkan prioritas.
    | Return: ['bahan_id' => int, 'nama' => string]
    |
    | Jika tidak ada satupun yang cukup, return bahan utama (biar FIFO yg handle error).
    |
    */

    public function resolveAlternativeBahan(ResepBahanBaku $item, float $qtyButuh, int $gudangId, ?int $divisiId = null): array
    {
        // Bangun daftar kandidat: bahan utama (prioritas 1) + alternatif (prioritas 2, 3, ...)
        $candidates = collect();

        // Bahan utama (prioritas 1)
        $candidates->push([
            'bahan_id' => $item->bahan_id,
            'nama'     => $item->bahan->nama ?? 'Bahan',
            'prioritas' => 1,
        ]);

        // Alternatif (sudah di-sort by prioritas di model)
        if ($item->relationLoaded('alternatif')) {
            foreach ($item->alternatif as $alt) {
                $candidates->push([
                    'bahan_id' => $alt->bahan_id,
                    'nama'     => $alt->bahan->nama ?? 'Bahan Alternatif',
                    'prioritas' => $alt->prioritas,
                ]);
            }
        } else {
            foreach ($item->alternatif()->with('bahan')->orderBy('prioritas')->get() as $alt) {
                $candidates->push([
                    'bahan_id' => $alt->bahan_id,
                    'nama'     => $alt->bahan->nama ?? 'Bahan Alternatif',
                    'prioritas' => $alt->prioritas,
                ]);
            }
        }

        // Jika hanya 1 kandidat (tidak ada alternatif), langsung return
        if ($candidates->count() <= 1) {
            return $candidates->first();
        }

        // Cek stok per kandidat, pilih yang cukup dengan prioritas tertinggi
        foreach ($candidates as $candidate) {
            $query = StokGudang::where('gudang_id', $gudangId)
                ->where('barang_id', $candidate['bahan_id']);

            if ($divisiId) {
                $query->where('divisi_id', $divisiId);
            }

            $stok = (float) ($query->value('jumlah') ?? 0);

            if ($stok >= $qtyButuh) {
                return $candidate;
            }
        }

        // Tidak ada yang cukup — return bahan utama (prioritas 1)
        return $candidates->first();
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK BAHAN AVAILABILITY (VALIDASI SAJA, TANPA KONSUMSI)
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk validasi sebelum produksi.
    | Cek apakah bahan utama ATAU salah satu alternatif memiliki stok cukup.
    | Return: ['sufficient' => bool, 'bahan_id' => int, 'nama' => string, 'stok' => float, 'butuh' => float]
    |
    */

    public function checkBahanAvailability(ResepBahanBaku $item, float $qtyButuh, int $gudangId, ?int $divisiId = null): array
    {
        $candidates = collect();

        $candidates->push([
            'bahan_id' => $item->bahan_id,
            'nama'     => $item->bahan->nama ?? 'Bahan',
        ]);

        $alts = $item->relationLoaded('alternatif')
            ? $item->alternatif
            : $item->alternatif()->with('bahan')->orderBy('prioritas')->get();

        foreach ($alts as $alt) {
            $candidates->push([
                'bahan_id' => $alt->bahan_id,
                'nama'     => $alt->bahan->nama ?? 'Bahan Alternatif',
            ]);
        }

        foreach ($candidates as $candidate) {
            $query = StokGudang::where('gudang_id', $gudangId)
                ->where('barang_id', $candidate['bahan_id']);

            if ($divisiId) {
                $query->where('divisi_id', $divisiId);
            }

            $stok = (float) ($query->value('jumlah') ?? 0);

            if ($stok >= $qtyButuh) {
                return [
                    'sufficient' => true,
                    'bahan_id'   => $candidate['bahan_id'],
                    'nama'       => $candidate['nama'],
                    'stok'       => $stok,
                    'butuh'      => $qtyButuh,
                ];
            }
        }

        // Tidak ada yang cukup, return info bahan utama
        $first = $candidates->first();
        $stokUtama = (float) (StokGudang::where('gudang_id', $gudangId)->where('barang_id', $first['bahan_id'])->value('jumlah') ?? 0);

        return [
            'sufficient' => false,
            'bahan_id'   => $first['bahan_id'],
            'nama'       => $first['nama'],
            'stok'       => $stokUtama,
            'butuh'      => $qtyButuh,
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | CREATE BATCH SAAT PEMBELIAN
    |--------------------------------------------------------------------------
    |
    | Setiap pembelian akan membuat batch FIFO baru.
    |
    */

    public function createBatchStock(
        Pembelian $pembelian,
        PembelianDetail $detail
    ): void {

        StokGudangBatch::create([
            'gudang_id'           => $pembelian->gudang_id,
            'divisi_id'           => null,
            'supplier_id'         => $pembelian->supplier_id,
            'barang_id'           => $detail->barang_id,
            'pembelian_id'        => $pembelian->id,
            'pembelian_detail_id' => $detail->id,
            'batch_number'        => $detail->batch_number,
            'qty_masuk'           => $detail->qty_diterima ?? $detail->qty,
            'qty_keluar'          => 0,
            'qty_sisa'            => $detail->qty_diterima ?? $detail->qty,
            'harga_per_qty'       => $detail->harga_per_qty,
            'is_habis'            => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FIFO CONSUME
    |--------------------------------------------------------------------------
    |
    | Mengurangi stok berdasarkan batch tertua.
    |
    | Parameter $allowNegative:
    | - false (default) : throw Exception jika stok tidak cukup
    | - true            : lanjutkan meski stok kurang (untuk Stock Opname),
    |                     sisa qty yang tidak ada batch-nya akan menggunakan
    |                     harga fallback (avg historis / hpp_referensi)
    |
    | Parameter $divisiId (optional):
    | - null            : konsumsi dari batch umum / tanpa filter divisi
    | - int             : konsumsi dari batch spesifik divisi terkait
    |
    */

    protected static array $hargaTerakhirCache = [];

    public static function clearHargaCache(): void
    {
        self::$hargaTerakhirCache = [];
    }

    public function consumeFIFO(
        int $barangId,
        float $qtyKeluar,
        int $gudangId,
        bool $allowNegative = false,
        ?int $divisiId = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | AMBIL BATCH FIFO (DENGAN PESSIMISTIC LOCK)
        |--------------------------------------------------------------------------
        */

        $query = StokGudangBatch::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->where('qty_sisa', '>', 0)
            ->where('is_habis', false);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        } else {
            $query->whereNull('divisi_id');
        }

        $batches = $query->orderBy('id')->lockForUpdate()->get();

        /*
        |--------------------------------------------------------------------------
        | VALIDASI STOK FIFO
        |--------------------------------------------------------------------------
        */

        $totalSisa = $batches->sum('qty_sisa');

        if ($totalSisa < $qtyKeluar && !$allowNegative) {
            throw new \Exception(
                'Stok FIFO tidak mencukupi. Tersedia: ' . $totalSisa . ', Dibutuhkan: ' . $qtyKeluar . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FIFO LOOP
        |--------------------------------------------------------------------------
        */

        $sisaPermintaan = $qtyKeluar;
        $result = [];

        foreach ($batches as $batch) {
            if ($sisaPermintaan <= 0) {
                break;
            }

            $ambilQty = min(
                $batch->qty_sisa,
                $sisaPermintaan
            );

            $batch->qty_keluar += $ambilQty;
            $batch->qty_sisa -= $ambilQty;

            if ($batch->qty_sisa <= 0) {
                $batch->qty_sisa = 0;
                $batch->is_habis = true;
            }

            $batch->save();

            $batchHarga = (float) $batch->harga_per_qty;
            if ($batchHarga <= 0 || abs($batchHarga - 233) <= 1 || abs($batchHarga - 25888.89) <= 1) {
                $batchHarga = $this->getHargaTerakhirBahan($barangId, $gudangId);
                if ($batchHarga > 0) {
                    $batch->harga_per_qty = $batchHarga;
                    $batch->save();
                }
            }

            $result[] = [
                'batch_id'      => $batch->id,
                'batch_number'  => $batch->batch_number,
                'qty_keluar'    => $ambilQty,
                'harga_per_qty' => $batchHarga,
            ];

            $sisaPermintaan -= $ambilQty;
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK: SISA QTY TIDAK ADA BATCH-NYA (allowNegative = true)
        |--------------------------------------------------------------------------
        */

        if ($sisaPermintaan > 0 && $allowNegative) {
            $hargaFallback = $this->getHargaTerakhirBahan($barangId, $gudangId);

            if (!$hargaFallback || $hargaFallback <= 0 || abs($hargaFallback - 233) <= 1 || abs($hargaFallback - 25888.89) <= 1) {
                $refHpp = (float)(DB::table('master_barang')->where('id', $barangId)->value('hpp_referensi') ?? 0);
                if ($refHpp > 0 && abs($refHpp - 233) > 1 && abs($refHpp - 25888.89) > 1) {
                    $hargaFallback = $refHpp;
                } else {
                    $hargaFallback = (float)(DB::table('master_barang')->where('id', $barangId)->value('harga_beli') ?? 0);
                }
            }

            $result[] = [
                'batch_id'      => null,
                'batch_number'  => 'OVERRIDE-HARGA-TERAKHIR',
                'qty_keluar'    => $sisaPermintaan,
                'harga_per_qty' => (float) $hargaFallback,
            ];
        }

        return $result;
    }

    /**
     * Ambil harga terakhir bahan dengan prioritas:
     * 1. Harga per qty batch terakhir di gudang spesifik yang memiliki harga > 0
     * 2. Harga per qty batch terakhir di Gudang Utama (sebagai referensi harga pusat)
     * 3. Harga per qty detail pembelian terakhir (pembelian_detail ke Gudang Utama)
     * 4. Harga per qty batch terakhir secara global (semua gudang)
     * 5. Formulasi resep jika barang merupakan Bahan Setengah Jadi / memiliki resep
     * 6. HPP referensi / harga beli master barang
     */
    public function getHargaTerakhirBahan(int $barangId, ?int $gudangId = null, array $visited = []): float
    {
        // Cegah rekursi tak hingga (circular reference)
        if (in_array($barangId, $visited)) {
            return 0.0;
        }

        $cacheKey = "{$barangId}_{$gudangId}";
        if (isset(self::$hargaTerakhirCache[$cacheKey])) {
            return self::$hargaTerakhirCache[$cacheKey];
        }

        $visited[] = $barangId;

        $master = DB::table('master_barang')->where('id', $barangId)->first();
        $satDasar = strtolower(trim($master->satuan ?? ''));
        $masterSatBeli = strtolower(trim($master->satuan_pembelian ?? ''));
        $masterKonv = (float)($master->konversi_pembelian ?? 1);
        $masterHppRef = (float)($master->hpp_referensi ?? 0);
        $masterHargaBeli = (float)($master->harga_beli ?? 0);
        $expectedMinPrice = 0.0;
        if ($masterKonv > 1) {
            if ($masterHppRef > 0) {
                $expectedMinPrice = $masterHppRef * 0.1;
            } elseif ($masterHargaBeli > 0) {
                $expectedMinPrice = ($masterHargaBeli / $masterKonv) * 0.1;
            }
        }

        $isValidBatchPrice = function($rawPrice) use ($expectedMinPrice, $masterKonv) {
            $p = floatval($rawPrice);
            if ($p <= 0) return false;
            // Cegah harga rogue dummy yang sering mengontaminasi sistem
            if (abs($p - 233) <= 1 || abs($p - 25888.89) <= 1) {
                return false;
            }
            // Jika barang memiliki konversi besar (> 1) dan harga batch sangat kecil (< 1 atau < 10% estimasi harga dasar),
            // kemungkinan besar terkena bug double division oleh konversi.
            if ($masterKonv > 1) {
                if ($expectedMinPrice > 0 && $p < $expectedMinPrice) {
                    return false;
                }
                if ($p < 0.5 && ($expectedMinPrice > 0 || $masterHargaBeli > 100)) {
                    return false;
                }
            }
            return true;
        };

        // 1. Cek batch aktif di gudang spesifik yang memiliki sisa stok (qty_sisa > 0)
        if ($gudangId) {
            $activeGudangBatch = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId)
                ->where('qty_sisa', '>', 0)
                ->where('is_habis', false)
                ->where('harga_per_qty', '>', 0)
                ->orderBy('id', 'asc')
                ->value('harga_per_qty');

            if ($activeGudangBatch && $isValidBatchPrice($activeGudangBatch)) {
                $res = (float) $activeGudangBatch;
                self::$hargaTerakhirCache[$cacheKey] = $res;
                return $res;
            }
        }

        // 2. Cek batch aktif di GUDANG UTAMA sebagai harga referensi pusat
        $gudangUtama = DB::table('master_gudang')
            ->where(function($q) {
                $q->where('nama', 'like', '%Gudang Utama%')
                  ->orWhere('kategori', 'Utama');
            })
            ->first();

        if ($gudangUtama && (!$gudangId || $gudangId != $gudangUtama->id)) {
            $activeUtamaBatch = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangUtama->id)
                ->where('barang_id', $barangId)
                ->where('qty_sisa', '>', 0)
                ->where('is_habis', false)
                ->where('harga_per_qty', '>', 0)
                ->orderBy('id', 'asc')
                ->value('harga_per_qty');

            if ($activeUtamaBatch && $isValidBatchPrice($activeUtamaBatch)) {
                $res = (float) $activeUtamaBatch;
                self::$hargaTerakhirCache[$cacheKey] = $res;
                return $res;
            }
        }

        // 3. Cek batch aktif di gudang mana saja secara global
        $activeAnyBatch = DB::table('stok_gudang_batch')
            ->where('barang_id', $barangId)
            ->where('qty_sisa', '>', 0)
            ->where('is_habis', false)
            ->where('harga_per_qty', '>', 0)
            ->orderBy('id', 'asc')
            ->value('harga_per_qty');

        if ($activeAnyBatch && $isValidBatchPrice($activeAnyBatch)) {
            $res = (float) $activeAnyBatch;
            self::$hargaTerakhirCache[$cacheKey] = $res;
            return $res;
        }

        // 4. Cek detail pembelian terakhir pada tabel pembelian_detail yang valid (tidak dihapus / batal)
        $latestPembelian = DB::table('pembelian_detail')
            ->join('pembelian', 'pembelian.id', '=', 'pembelian_detail.pembelian_id')
            ->where('pembelian_detail.barang_id', $barangId)
            ->where(function($q) {
                $q->where('pembelian_detail.harga_per_qty', '>', 0)
                  ->orWhere('pembelian_detail.harga', '>', 0);
            })
            ->where(function($q) {
                $q->whereNull('pembelian.catatan_pembayaran')
                  ->orWhere(function($sub) {
                      $sub->where('pembelian.catatan_pembayaran', 'not like', '[DELETED]%')
                          ->where('pembelian.catatan_pembayaran', 'not like', '[BATAL]%');
                  });
            })
            ->where(function($q) {
                $q->whereNull('pembelian.keterangan')
                  ->orWhere('pembelian.keterangan', 'not like', '[BATAL]%');
            })
            ->select('pembelian_detail.*', 'pembelian.tanggal')
            ->orderBy('pembelian.tanggal', 'desc')
            ->orderBy('pembelian.id', 'desc')
            ->orderBy('pembelian_detail.id', 'desc')
            ->first();

        if ($latestPembelian) {
            $pQty = (float)($latestPembelian->qty ?? 0);
            $pHarga = (float)($latestPembelian->harga ?? 0);
            $pHargaPerQty = (float)($latestPembelian->harga_per_qty ?? 0);
            $detailKonv = (float)($latestPembelian->konversi_pembelian ?? 1);
            $satBeli = strtolower(trim($latestPembelian->satuan_pembelian ?? ''));

            $effectiveKonv = $detailKonv > 1 ? $detailKonv : ($masterKonv > 1 ? $masterKonv : 1.0);

            // Deteksi apakah pQty dinyatakan dalam satuan pembelian (misal LOAFT) atau satuan dasar (misal GR)
            $isSatuanBeli = false;
            if (!empty($satBeli) && !empty($satDasar) && $satBeli !== $satDasar) {
                $isSatuanBeli = true;
            } elseif (!empty($masterSatBeli) && !empty($satDasar) && $masterSatBeli !== $satDasar && $satBeli === $masterSatBeli) {
                $isSatuanBeli = true;
            } elseif ($effectiveKonv > 1 && $pQty < ($effectiveKonv * 0.5) && $pHarga > 0) {
                // Heuristik: jika qty sangat kecil dibanding konversi (misal 4.65 vs konversi 5000), ini adalah satuan pembelian
                $isSatuanBeli = true;
            }

            $totalBaseQty = $isSatuanBeli ? ($pQty * $effectiveKonv) : $pQty;
            $unitPriceDasar = 0.0;
            if ($totalBaseQty > 0 && $pHarga > 0) {
                $unitPriceDasar = $pHarga / $totalBaseQty;
            } elseif ($pHargaPerQty > 0) {
                $unitPriceDasar = $isSatuanBeli ? ($pHargaPerQty / $effectiveKonv) : $pHargaPerQty;
            }

            if ($unitPriceDasar > 0) {
                $res = (float) $unitPriceDasar;
                self::$hargaTerakhirCache[$cacheKey] = $res;
                return $res;
            }
        }

        // 5. Cek batch historis terakhir di gudang spesifik / gudang utama / global
        if ($gudangId) {
            $latestGudangBatch = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId)
                ->where('harga_per_qty', '>', 0)
                ->orderBy('id', 'desc')
                ->value('harga_per_qty');

            if ($latestGudangBatch && $isValidBatchPrice($latestGudangBatch)) {
                $res = (float) $latestGudangBatch;
                self::$hargaTerakhirCache[$cacheKey] = $res;
                return $res;
            }
        }

        if ($gudangUtama && (!$gudangId || $gudangId != $gudangUtama->id)) {
            $latestUtamaBatch = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangUtama->id)
                ->where('barang_id', $barangId)
                ->where('harga_per_qty', '>', 0)
                ->orderBy('id', 'desc')
                ->value('harga_per_qty');

            if ($latestUtamaBatch && $isValidBatchPrice($latestUtamaBatch)) {
                $res = (float) $latestUtamaBatch;
                self::$hargaTerakhirCache[$cacheKey] = $res;
                return $res;
            }
        }

        $latestBatchGlobal = DB::table('stok_gudang_batch')
            ->where('barang_id', $barangId)
            ->where('harga_per_qty', '>', 0)
            ->orderBy('id', 'desc')
            ->value('harga_per_qty');

        if ($latestBatchGlobal && $isValidBatchPrice($latestBatchGlobal)) {
            $res = (float) $latestBatchGlobal;
            self::$hargaTerakhirCache[$cacheKey] = $res;
            return $res;
        }

        // 6. Fallback ke persediaan awal
        $sa = DB::table('persediaan_awal_detail')
            ->where('barang_id', $barangId)
            ->where('harga_satuan', '>', 0)
            ->orderBy('id', 'desc')
            ->value('harga_satuan');

        if ($sa && floatval($sa) > 0) {
            $res = round((float) $sa, 2);
            self::$hargaTerakhirCache[$cacheKey] = $res;
            return $res;
        }

        // 7. Formulasi resep jika barang memiliki resep (terutama Bahan Setengah Jadi)
        $barang = DB::table('master_barang')->where('id', $barangId)->first();
        if ($barang) {
            $resep = null;
            if (!empty($barang->resep_id)) {
                $resep = DB::table('resep_btkl_bop')->where('id', $barang->resep_id)->first();
            }
            if (!$resep) {
                $resep = DB::table('resep_btkl_bop')->where('produk_id', $barangId)->first();
            }

            if ($resep) {
                $subBahanList = DB::table('resep_bahanbaku')->where('resep_id', $resep->id)->get();
                if ($subBahanList->count() > 0) {
                    $outputQty = floatval($resep->output_qty) > 0 ? floatval($resep->output_qty) : 1.0;
                    $totalBiayaResep = 0.0;
                    foreach ($subBahanList as $subBahan) {
                        $subHarga = $this->getHargaTerakhirBahan($subBahan->bahan_id, $gudangId, $visited);
                        $totalBiayaResep += (floatval($subBahan->qty_bahan) * $subHarga);
                    }
                    if ($totalBiayaResep > 0) {
                        $multiplier = $this->isProducedInCkOrColdKitchen($barangId) ? 1.30 : 1.0;
                        $totalBiayaResep = $totalBiayaResep * $multiplier;
                        $res = (float) ($totalBiayaResep / $outputQty);
                        self::$hargaTerakhirCache[$cacheKey] = $res;
                        return $res;
                    }
                }
            }

            // 8. Fallback ke HPP referensi master barang / harga beli
            if (isset($barang->hpp_referensi) && floatval($barang->hpp_referensi) > 0) {
                $res = (float) $barang->hpp_referensi;
                if (true) {
                    self::$hargaTerakhirCache[$cacheKey] = $res;
                }
                return $res;
            }
            if (isset($barang->harga_beli) && floatval($barang->harga_beli) > 0) {
                $res = (float) $barang->harga_beli;
                $konversi = isset($barang->konversi_pembelian) ? (float)$barang->konversi_pembelian : 1;
                if ($konversi > 1) {
                    $res = $res / $konversi;
                }
                if (true) {
                    self::$hargaTerakhirCache[$cacheKey] = $res;
                }
                return $res;
            }
            if (isset($barang->harga_satuan_terkecil) && floatval($barang->harga_satuan_terkecil) > 0) {
                $res = (float) $barang->harga_satuan_terkecil;
                if (true) {
                    self::$hargaTerakhirCache[$cacheKey] = $res;
                }
                return $res;
            }
        }

        if (true) {
            self::$hargaTerakhirCache[$cacheKey] = 0.0;
        }
        return 0.0;
    }

    public function getEstimatedHargaFIFO(int $barangId, float $qtyKeluar, int $gudangId, ?int $divisiId = null): array
    {
        $query = StokGudangBatch::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->where('qty_sisa', '>', 0)
            ->where('is_habis', false);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        } else {
            $query->whereNull('divisi_id');
        }

        $batches = $query->orderBy('id')->get();

        $sisaPermintaan = $qtyKeluar;
        $totalHpp = 0;

        foreach ($batches as $batch) {
            if ($sisaPermintaan <= 0) {
                break;
            }
            $batchHarga = (float) $batch->harga_per_qty;
            if ($batchHarga <= 0 || abs($batchHarga - 233) <= 1 || abs($batchHarga - 25888.89) <= 1) {
                $batchHarga = $this->getHargaTerakhirBahan($barangId, $gudangId);
            }
            $ambilQty = min($batch->qty_sisa, $sisaPermintaan);
            $totalHpp += $ambilQty * $batchHarga;
            $sisaPermintaan -= $ambilQty;
        }

        if ($sisaPermintaan > 0) {
            $hargaFallback = $this->getHargaTerakhirBahan($barangId, $gudangId);

            if (!$hargaFallback || $hargaFallback <= 0 || abs($hargaFallback - 233) <= 1 || abs($hargaFallback - 25888.89) <= 1) {
                $refHpp = (float)(DB::table('master_barang')->where('id', $barangId)->value('hpp_referensi') ?? 0);
                if ($refHpp > 0 && abs($refHpp - 233) > 1 && abs($refHpp - 25888.89) > 1) {
                    $hargaFallback = $refHpp;
                } else {
                    $hargaFallback = (float)(DB::table('master_barang')->where('id', $barangId)->value('harga_beli') ?? 0);
                }
            }

            $totalHpp += $sisaPermintaan * (float) $hargaFallback;
        }

        $hargaSatuan = $qtyKeluar > 0 ? ($totalHpp / $qtyKeluar) : 0;

        return [
            'harga_satuan' => $hargaSatuan,
            'total_harga'  => $totalHpp
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SINKRONISASI HPP BARANG SESUAI FIFO
    |--------------------------------------------------------------------------
    |
    | Menghitung HPP aktif untuk barang berdasarkan aturan FIFO:
    | 1. Batch aktif tertua yang masih memiliki sisa stok (qty_sisa > 0, is_habis = false).
    | 2. Jika seluruh batch habis, ambil dari detail pembelian terakhir yang valid.
    | 3. Jika tidak ada pembelian, ambil dari persediaan awal.
    | 4. Fallback ke hpp_referensi yang tersimpan saat ini.
    |
    | Hasil kalkulasi langsung disimpan ke master_barang.hpp_referensi.
    |
    */
    /**
     * Hitung HPP Resep untuk Bahan Setengah Jadi (BSJ) berdasarkan harga bahan baku
     * terbaru di Gudang Central Kitchen (kategori Produksi).
     * Formula: (sum(qty_bahan * harga_CK) * 1.30 BOP/BTKL) / output_qty
     * Fallback ke hpp_referensi jika resep tidak ditemukan.
     */
    public function getHppResepBsj(int $barangId, array $visited = []): float
    {
        if (in_array($barangId, $visited) || count($visited) > 6) {
            return 0.0;
        }
        $visited[] = $barangId;

        // Cari gudang Central Kitchen (kategori Produksi)
        $gudangCk = DB::table('master_gudang')
            ->where(function ($q) {
                $q->where('kategori', 'Produksi')
                  ->orWhere('nama', 'like', '%Central Kitchen%');
            })
            ->orderByRaw("CASE WHEN kategori = 'Produksi' THEN 0 ELSE 1 END")
            ->first();
        $gudangCkId = $gudangCk ? (int) $gudangCk->id : 1;

        $barang = DB::table('master_barang')->where('id', $barangId)->first();
        if (!$barang) {
            return 0.0;
        }

        // Cari resep: via resep_id, produk_id, atau barang lain dengan nama sama
        $resep = null;
        if (!empty($barang->resep_id)) {
            $resep = DB::table('resep_btkl_bop')->where('id', $barang->resep_id)->first();
        }
        if (!$resep) {
            $resep = DB::table('resep_btkl_bop')->where('produk_id', $barangId)->first();
        }
        if (!$resep && !empty($barang->nama)) {
            $sameNameBarangIds = DB::table('master_barang')
                ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim($barang->nama))])
                ->pluck('id');
            if ($sameNameBarangIds->isNotEmpty()) {
                $resep = DB::table('resep_btkl_bop')
                    ->whereIn('produk_id', $sameNameBarangIds)
                    ->first();
            }
        }

        if ($resep) {
            $subBahanList = DB::table('resep_bahanbaku')->where('resep_id', $resep->id)->get();
            if ($subBahanList->count() > 0) {
                $outputQty = floatval($resep->output_qty) > 0 ? floatval($resep->output_qty) : 1.0;
                $totalBbb  = 0.0;
                foreach ($subBahanList as $subBahan) {
                    $subBhn = DB::table('master_barang')->where('id', $subBahan->bahan_id)->first();
                    $isSubBsj = $subBhn ? ($subBhn->is_bahan_setengah_jadi || !empty($subBhn->resep_id) || DB::table('resep_btkl_bop')->where('produk_id', $subBahan->bahan_id)->exists()) : false;
                    
                    $hargaBahan = 0.0;
                    if ($isSubBsj) {
                        $hargaBahan = $this->getHppResepBsj((int) $subBahan->bahan_id, $visited);
                    }
                    if ($hargaBahan <= 0) {
                        $hargaBahan = $this->getHargaTerakhirBahan((int) $subBahan->bahan_id, $gudangCkId);
                    }
                    if ($hargaBahan <= 0 && $subBhn) {
                        $hargaBahan = (float) ($subBhn->hpp_referensi ?: ($subBhn->harga_beli ?: 0));
                    }

                    $totalBbb += floatval($subBahan->qty_bahan) * $hargaBahan;
                }
                if ($totalBbb > 0) {
                    $multiplier = $this->isProducedInCkOrColdKitchen($barangId) ? 1.30 : 1.0;
                    return round(($totalBbb * $multiplier) / $outputQty, 4);
                }
            }
        }

        // Cek riwayat harga produksi / batch FIFO di Gudang Central Kitchen untuk barang ini / nama sama
        $sameNameIds = !empty($barang->nama) ? DB::table('master_barang')
            ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim($barang->nama))])
            ->pluck('id')
            ->toArray() : [$barangId];
        if (!in_array($barangId, $sameNameIds)) {
            $sameNameIds[] = $barangId;
        }

        foreach ($sameNameIds as $idToTest) {
            $hargaCk = $this->getHargaTerakhirBahan((int) $idToTest, $gudangCkId);
            if ($hargaCk > 0) {
                return round($hargaCk, 4);
            }
        }

        // Fallback: hpp_referensi master barang atau barang lain dengan nama sama
        $hppRef = (float) ($barang->hpp_referensi ?: ($barang->harga_beli ?: 0));
        if ($hppRef <= 0 && !empty($barang->nama)) {
            $hppRef = (float) (DB::table('master_barang')
                ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim($barang->nama))])
                ->where('hpp_referensi', '>', 0)
                ->value('hpp_referensi') ?: 0);
        }

        return $hppRef;
    }

    /**
     * Cek apakah Bahan Setengah Jadi (BSJ) dipesan / diproduksi melalui Central Kitchen atau Cold Kitchen.
     * Jika dibuat sendiri di outlet (tidak via CK/Cold Kitchen), pembebanan 30% BTKL/BOP tidak ditambahkan pada resep BSJ,
     * melainkan hanya dibebankan sekali saat transaksi Penjualan POS.
     */
    public function isProducedInCkOrColdKitchen(int $barangId): bool
    {
        $hasProduksi = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->whereIn('source_type', ['produksi', 'produksi_ck'])
            ->exists();

        if ($hasProduksi) {
            return true;
        }

        $hasProduksiDetail = DB::table('produksi_detail')
            ->where('produk_id', $barangId)
            ->exists();

        if ($hasProduksiDetail) {
            return true;
        }

        $hasPesananCk = DB::table('pesanan_detail')
            ->join('pesanan', 'pesanan.id', '=', 'pesanan_detail.pesanan_id')
            ->where('pesanan_detail.produk_id', $barangId)
            ->where('pesanan.tipe_pesanan', 'central_kitchen')
            ->exists();

        if ($hasPesananCk) {
            return true;
        }

        $gudangCkIds = DB::table('master_gudang')
            ->where(function ($q) {
                $q->where('kategori', 'Produksi')
                  ->orWhere('nama', 'like', '%Central Kitchen%')
                  ->orWhere('nama', 'like', '%Cold Kitchen%');
            })
            ->pluck('id');

        if ($gudangCkIds->isNotEmpty()) {
            $hasCkBatch = DB::table('stok_gudang_batch')
                ->whereIn('gudang_id', $gudangCkIds)
                ->where('barang_id', $barangId)
                ->where('harga_per_qty', '>', 0)
                ->exists();
            if ($hasCkBatch) {
                return true;
            }
        }

        return false;
    }

    public function syncBarangHpp(int $barangId): float
    {
        self::clearHargaCache();
        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        if (!$barang) return 0.0;

        // Jika barang merupakan Bahan Setengah Jadi atau memiliki formulasi resep, utamakan kalkulasi HPP Resep BSJ
        if ($barang->is_bahan_setengah_jadi || $barang->hasResep()) {
            $newHpp = $this->getHppResepBsj($barangId);
        } else {
            $gudangUtamaId = \App\Models\MasterGudang::getGudangUtamaId();
            $newHpp = $this->getFifoHpp($barangId, $gudangUtamaId);
        }

        if ($newHpp > 0) {
            MasterBarang::withoutGlobalScopes()
                ->where('id', $barangId)
                ->update(['hpp_referensi' => $newHpp]);
        }

        return $newHpp;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ESTIMASI HARGA FIFO AKTIF (TANPA UPDATE DB)
    |--------------------------------------------------------------------------
    */
    public function getFifoHpp(int $barangId, ?int $gudangId = null): float
    {
        return $this->getHargaTerakhirBahan($barangId, $gudangId);
    }

    /*
    |--------------------------------------------------------------------------
    | SINKRONISASI HPP UNTUK SEMUA BARANG
    |--------------------------------------------------------------------------
    */
    public function syncAllBarangHpp(): array
    {
        $barangs = MasterBarang::withoutGlobalScopes()->get();
        $synced = [];

        foreach ($barangs as $b) {
            $oldHpp = (float) $b->hpp_referensi;
            $newHpp = $this->syncBarangHpp($b->id);
            if (abs($oldHpp - $newHpp) > 0.001) {
                $synced[$b->id] = [
                    'kode'    => $b->kode_barang,
                    'nama'    => $b->nama,
                    'old_hpp' => $oldHpp,
                    'new_hpp' => $newHpp,
                ];
            }
        }

        return $synced;
    }
}
