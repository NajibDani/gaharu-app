<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Kategori;

class MasterBarang extends Model
{
    protected $table = 'master_barang';
    protected $fillable = [
        'kategori_id',
        'resep_id',
        'kode_barang',
        'nama',
        'satuan',
        'satuan_pembelian',
        'konversi_pembelian',
        'is_bahan_baku',
        'is_bahan_setengah_jadi',
        'is_barang_jadi',
        'is_operational',
        'is_direct_consumption',
        'harga_jual_b2b',
        'harga_jual_pos',
        'hpp_referensi',
        'is_active',
        'minimum_stock',
        'minimum_stock_ck',
        'minimum_stock_kejingga',
        'minimum_stock_gaharu',
        'minimum_order',
        'tipe_penjualan'
    ];

    protected static function booted()
    {
        static::addGlobalScope('role_barang_filter', function (\Illuminate\Database\Eloquent\Builder $builder) {
            // Bypass filter di luar konteks request (CLI, seeder, migrate)
            if (app()->runningInConsole()) {
                if (!app()->runningUnitTests() && !defined('TEST_RUNNING')) {
                    return;
                }
            }

            $user = auth()->user();
            if ($user && $user->role) {
                $roleName = $user->role->nama;
                if ($roleName === 'Super Admin' || $roleName === 'Administrator' || $roleName === 'HRD' || $roleName === 'Direktur Keuangan' || $roleName === 'Bagian Produksi') {
                    return;
                }

                if ($roleName === 'Kepala Outlet Gaharu') {
                    $builder->where(function ($q) {
                        $q->where('is_bahan_baku', 1)
                          ->orWhere('is_bahan_setengah_jadi', 1)
                          ->orWhere(function ($q2) {
                              $q2->where('is_barang_jadi', 1)
                                 ->whereIn('tipe_penjualan', ['POS Gaharu', 'B2B']);
                          });
                    });
                } elseif ($roleName === 'Kepala Outlet Kejingga') {
                    $builder->where(function ($q) {
                        $q->where('is_bahan_baku', 1)
                          ->orWhere('is_bahan_setengah_jadi', 1)
                          ->orWhere(function ($q2) {
                              $q2->where('is_barang_jadi', 1)
                                 ->where('tipe_penjualan', 'POS Kejingga');
                          });
                    });
                } elseif ($roleName === 'Kepala Gudang') {
                    $builder->where(function ($q) {
                        $q->where('is_bahan_baku', 1)
                          ->orWhere('is_bahan_setengah_jadi', 1)
                          ->orWhere('is_operational', 1)
                          ->orWhere(function ($q2) {
                              $q2->where('is_barang_jadi', 1)
                                 ->where('tipe_penjualan', 'B2B');
                          });
                    });
                }
            }
        });
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }
    public function permintaanDetails()
    {
        return $this->hasMany(
            PermintaanBahanBakuDetail::class,
            'barang_id'
        );
    }

// Di dalam class MasterBarang
public function hargaPosAktif()
{
    // Laravel akan otomatis mencari 'barang_id' di tabel harga_barang_pos 
    // dan mencocokkannya dengan 'id' di tabel ini
    return $this->hasOne(HargaPeriode::class, 'barang_id')
                ->whereDate('tgl_mulai', '<=', now())
                ->whereDate('tgl_selesai', '>=', now())
                ->latest();
}

public function firstFifoLayer()
{
    return $this->hasOne(FifoLayer::class, 'barang_id')->orderBy('tanggal_masuk', 'asc');
}
    public function getJenisUtamaAttribute()
    {
        if ($this->is_bahan_baku) return 'BAHAN_BAKU';
        if ($this->is_bahan_setengah_jadi) return 'BAHAN_SETENGAH_JADI';
        if ($this->is_barang_jadi) return 'BARANG_JADI';
        if ($this->is_operational) return 'OPERATIONAL';
        return 'UMUM';
    }

    public function getResepIdAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }
        if ($this->relationLoaded('resepBtklBop') && $this->resepBtklBop) {
            return $this->resepBtklBop->id;
        }
        return $this->resepBtklBop()->value('id');
    }

    public function resep()
    {
        // Hubungkan langsung ke resep_bahanbaku melalui tabel header resep_btkl_bop
        return $this->hasManyThrough(
            ResepBahanBaku::class,
            ResepBtklBop::class,
            'produk_id', // Foreign key on resep_btkl_bop
            'resep_id',  // Foreign key on resep_bahanbaku
            'id',        // Local key on master_barang
            'id'         // Local key on resep_btkl_bop
        );
    }

    public function hasResep(): bool
    {
        if ($this->relationLoaded('resepBtklBop') && $this->resepBtklBop) {
            if ($this->resepBtklBop->relationLoaded('bahanbaku')) {
                return $this->resepBtklBop->bahanbaku->count() > 0;
            }
            return $this->resepBtklBop->bahanbaku()->exists();
        }
        
        $resepBop = $this->resepBtklBop()->with('bahanbaku')->first();
        if ($resepBop && $resepBop->bahanbaku->count() > 0) {
            return true;
        }

        $rawResepId = $this->attributes['resep_id'] ?? null;
        if (!empty($rawResepId)) {
            return ResepBahanBaku::where('resep_id', $rawResepId)->exists();
        }

        return false;
    }
public function stockOpnameDetails()
{
    return $this->hasMany(
        StockOpnameDetail::class,
        'barang_id'
    );
}

public function minimumStocks()
{
    return $this->hasMany(BarangMinimumStock::class, 'barang_id');
}

public function resepBtklBop()
{
    return $this->hasOne(ResepBtklBop::class, 'produk_id');
}

public function resepBahanBakuUtama()
{
    return $this->hasMany(ResepBahanBaku::class, 'bahan_id');
}

public function resepBahanBakuAlternatif()
{
    return $this->hasMany(ResepBahanBakuAlternatif::class, 'bahan_id');
}

    /**
     * Auto-heal & sinkronkan kolom resep_id di tabel master_barang dengan resep_btkl_bop
     */
    public static function syncAllResepIds(): void
    {
        \Illuminate\Support\Facades\DB::table('master_barang')
            ->join('resep_btkl_bop', 'master_barang.id', '=', 'resep_btkl_bop.produk_id')
            ->where(function ($q) {
                $q->whereNull('master_barang.resep_id')
                  ->orWhereColumn('master_barang.resep_id', '!=', 'resep_btkl_bop.id');
            })
            ->update(['master_barang.resep_id' => \Illuminate\Support\Facades\DB::raw('resep_btkl_bop.id')]);
    }

    /**
     * Auto-heal & sinkronkan satuan resep dan bahan baku dengan satuan di master_barang
     */
    public static function syncAllResepSatuan(): void
    {
        \Illuminate\Support\Facades\DB::table('resep_btkl_bop')
            ->join('master_barang', 'resep_btkl_bop.produk_id', '=', 'master_barang.id')
            ->whereNotNull('master_barang.satuan')
            ->where('master_barang.satuan', '!=', '')
            ->whereColumn('resep_btkl_bop.satuan_output', '!=', 'master_barang.satuan')
            ->update(['resep_btkl_bop.satuan_output' => \Illuminate\Support\Facades\DB::raw('master_barang.satuan')]);

        \Illuminate\Support\Facades\DB::table('resep_bahanbaku')
            ->join('master_barang', 'resep_bahanbaku.bahan_id', '=', 'master_barang.id')
            ->whereNotNull('master_barang.satuan')
            ->where('master_barang.satuan', '!=', '')
            ->whereColumn('resep_bahanbaku.satuan', '!=', 'master_barang.satuan')
            ->update(['resep_bahanbaku.satuan' => \Illuminate\Support\Facades\DB::raw('master_barang.satuan')]);
    }

    /**
     * Normalisasi dan sinkronisasi kuantitas stok pembelian agar sesuai dengan satuan dasar.
     * Memastikan kuantitas transaksi_stok dan stok_gudang_batch tersimpan dalam satuan dasar (misal 18 GALON * 19.000 ML = 342.000 ML).
     */
    public static function autoHealUnconvertedPembelianBatches($targetBarangId = null): void
    {
        // 1. Sinkronisasi master_barang jika satuan_pembelian / konversi_pembelian belum terisi di master namun ada di pembelian_detail
        $unfilledMasters = \Illuminate\Support\Facades\DB::table('master_barang')
            ->where(function($q) {
                $q->whereNull('konversi_pembelian')
                  ->orWhere('konversi_pembelian', '<=', 1)
                  ->orWhereNull('satuan_pembelian')
                  ->orWhere('satuan_pembelian', '');
            });
        if ($targetBarangId) {
            $unfilledMasters->where('id', $targetBarangId);
        }
        $unfilledIds = $unfilledMasters->pluck('id')->toArray();

        if (!empty($unfilledIds)) {
            $latestPds = \Illuminate\Support\Facades\DB::table('pembelian_detail')
                ->whereIn('barang_id', $unfilledIds)
                ->whereNotNull('satuan_pembelian')
                ->where('konversi_pembelian', '>', 1)
                ->orderBy('id', 'desc')
                ->get()
                ->unique('barang_id');

            foreach ($latestPds as $lpd) {
                \Illuminate\Support\Facades\DB::table('master_barang')
                    ->where('id', $lpd->barang_id)
                    ->update([
                        'satuan_pembelian'   => $lpd->satuan_pembelian,
                        'konversi_pembelian' => $lpd->konversi_pembelian,
                    ]);
            }
        }

        // 2. Ambil detail pembelian yang memiliki konversi pembelian > 1
        $query = \Illuminate\Support\Facades\DB::table('pembelian_detail')
            ->join('master_barang', 'pembelian_detail.barang_id', '=', 'master_barang.id')
            ->select(
                'pembelian_detail.*',
                'master_barang.satuan as master_satuan',
                'master_barang.satuan_pembelian as master_satuan_pembelian',
                'master_barang.konversi_pembelian as master_konversi'
            )
            ->where(function ($q) {
                $q->where('pembelian_detail.konversi_pembelian', '>', 1)
                  ->orWhere('master_barang.konversi_pembelian', '>', 1);
            });

        if ($targetBarangId) {
            $query->where('pembelian_detail.barang_id', $targetBarangId);
            $pDetails = $query->get();
        } else {
            $hasUnconverted = \Illuminate\Support\Facades\DB::table('stok_gudang_batch as b')
                ->join('pembelian_detail as pd', 'b.pembelian_detail_id', '=', 'pd.id')
                ->where('pd.konversi_pembelian', '>', 1)
                ->whereRaw('ABS(b.qty_masuk - pd.qty) < 0.01')
                ->exists();

            $pDetails = $hasUnconverted ? $query->get() : collect();
        }

        foreach ($pDetails as $pd) {
            $pQty = (float) ($pd->qty ?? 0);
            $pHarga = (float) ($pd->harga ?? 0);
            $pHargaPerQty = (float) ($pd->harga_per_qty ?? 0);
            if ($pQty <= 0) {
                continue;
            }

            $unitPriceBeli = $pHargaPerQty > 0 ? $pHargaPerQty : ($pQty > 0 ? ($pHarga / $pQty) : 0);
            if ($pHarga <= 0 && $unitPriceBeli > 0) {
                $pHarga = round($pQty * $unitPriceBeli, 2);
            }

            $detailKonv = (float) ($pd->konversi_pembelian ?? 1);
            $masterKonv = (float) ($pd->master_konversi ?? 1);
            $konversi = $detailKonv > 1 ? $detailKonv : ($masterKonv > 1 ? $masterKonv : 1.0);

            $satBeli = strtolower(trim($pd->satuan_pembelian ?: ($pd->master_satuan_pembelian ?: '')));
            $satDasar = strtolower(trim($pd->master_satuan ?: ''));

            if ($satBeli === $satDasar || $konversi <= 1) {
                continue;
            }

            // Kuantitas dasar yang benar dalam satuan stok dasar (misal 18 GALON * 19.000 ML = 342.000 ML)
            $correctBaseQty = round($pQty * $konversi, 4);
            $correctHargaPerQty = round($unitPriceBeli / $konversi, 4);

            // Perbaiki transaksi_stok jika tidak sesuai dengan correctBaseQty
            $txList = \Illuminate\Support\Facades\DB::table('transaksi_stok')
                ->where('barang_id', $pd->barang_id)
                ->where('source_id', $pd->pembelian_id)
                ->whereIn('source_type', ['pembelian', 'penerimaan_pembelian'])
                ->get();

            foreach ($txList as $tx) {
                if (abs((float)$tx->qty - $correctBaseQty) > 0.01 || abs((float)$tx->total_harga - $pHarga) > 0.01) {
                    \Illuminate\Support\Facades\DB::table('transaksi_stok')
                        ->where('id', $tx->id)
                        ->update([
                            'qty' => $correctBaseQty,
                            'total_harga' => $pHarga,
                        ]);
                }
            }

            // CRITICAL: ONLY target REAL purchase batches for this specific barang and purchase detail!
            // NEVER match Saldo Awal (SA-%), Mutasi (%-MUT), Stock Opname (SO-%), or batches of other items!
            $batches = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                ->where('barang_id', $pd->barang_id)
                ->where('pembelian_detail_id', $pd->id)
                ->where('batch_number', 'not like', 'SA-%')
                ->where('batch_number', 'not like', '%-MUT')
                ->where('batch_number', 'not like', 'SO-%')
                ->where('batch_number', 'not like', 'CK-%')
                ->get();

            foreach ($batches as $batch) {
                $needsUpdate = false;
                $updateData = [];

                // Konversi kuantitas jika masih dalam satuan pembelian
                if (abs((float)$batch->qty_masuk - $pQty) < 0.01 && $konversi > 1) {
                    $newMasuk = round((float)$batch->qty_masuk * $konversi, 4);
                    $qtyKeluar = (float)$batch->qty_keluar;
                    $newSisa = max(0, $newMasuk - $qtyKeluar);
                    $updateData['qty_masuk'] = $newMasuk;
                    $updateData['qty_sisa']  = $newSisa;
                    $updateData['is_habis']  = ($newSisa <= 0);
                    $needsUpdate = true;
                }

                // Konversi harga jika masih menggunakan harga satuan pembelian
                if ($correctHargaPerQty > 0 && abs((float)$batch->harga_per_qty - $unitPriceBeli) < 0.01) {
                    $updateData['harga_per_qty'] = $correctHargaPerQty;
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $updateData['updated_at'] = now();
                    \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                        ->where('id', $batch->id)
                        ->update($updateData);
                }
            }
        }

        // Jalankan juga healing untuk saldo awal dan mutasi agar data selalu sinkron
        self::autoHealSaldoAwalBatches($targetBarangId);
        self::autoHealMutasiBatches($targetBarangId);

        if ($targetBarangId) {
            try {
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$targetBarangId);
            } catch (\Throwable $e) {
                // Ignore if service fails during migration/testing
            }
        }
    }

    /**
     * Auto-heal & sinkronkan batch saldo awal (SA-*) dari persediaan_awal_detail
     */
    public static function autoHealSaldoAwalBatches($targetBarangId = null): void
    {
        $mismatched = \Illuminate\Support\Facades\DB::table('stok_gudang_batch as b')
            ->join('persediaan_awal_detail as sad', 'b.barang_id', '=', 'sad.barang_id')
            ->join('persediaan_awal as pa', function($j) {
                $j->on('pa.id', '=', 'sad.persediaan_awal_id')
                  ->on('pa.gudang_id', '=', 'b.gudang_id');
            })
            ->where('b.batch_number', 'like', 'SA-%')
            ->where(function($q) {
                $q->whereRaw('ABS(b.harga_per_qty - sad.harga_satuan) > 0.001')
                  ->orWhereRaw('ABS(b.qty_masuk - sad.qty) > 0.01')
                  ->orWhereNotNull('b.pembelian_detail_id');
            })
            ->select('b.id', 'b.qty_keluar', 'sad.qty as real_qty', 'sad.harga_satuan as real_price');

        if ($targetBarangId) {
            $mismatched->where('b.barang_id', $targetBarangId);
        }

        $rows = $mismatched->get();
        foreach ($rows as $r) {
            $realQty = (float) $r->real_qty;
            $realPrice = (float) $r->real_price;
            $used = (float) $r->qty_keluar;
            $newSisa = max(0, $realQty - $used);
            \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                ->where('id', $r->id)
                ->update([
                    'qty_masuk'           => $realQty,
                    'qty_sisa'            => $newSisa,
                    'is_habis'            => ($newSisa <= 0),
                    'harga_per_qty'       => $realPrice,
                    'pembelian_id'        => null,
                    'pembelian_detail_id' => null,
                    'updated_at'          => now(),
                ]);
        }
    }

    /**
     * Auto-heal & sinkronkan batch mutasi (*-MUT) yang terdistorsi harga modal atau kuantitasnya
     */
    public static function autoHealMutasiBatches($targetBarangId = null): void
    {
        $query = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
            ->where('batch_number', 'like', '%-MUT');

        if ($targetBarangId) {
            $query->where('barang_id', $targetBarangId);
        } else {
            // Hanya scan mutasi yang belum memiliki harga valid atau harganya 0
            $query->where(function($q) {
                $q->whereNull('harga_per_qty')
                  ->orWhere('harga_per_qty', '<=', 0);
            });
        }

        $mutBatches = $query->get();

        foreach ($mutBatches as $mb) {
            $origBatchNum = preg_replace('/-MUT$/', '', $mb->batch_number);
            $origBatch = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                ->where('batch_number', $origBatchNum)
                ->where('barang_id', $mb->barang_id)
                ->first();

            $origPrice = null;
            if ($origBatch) {
                if (str_starts_with($origBatch->batch_number, 'SA-')) {
                    $sad = \Illuminate\Support\Facades\DB::table('persediaan_awal_detail')
                        ->where('barang_id', $mb->barang_id)
                        ->first();
                    if ($sad && (float)$sad->harga_satuan > 0) {
                        $origPrice = (float) $sad->harga_satuan;
                    }
                }
                if (!$origPrice && (float)$origBatch->harga_per_qty > 0 && abs((float)$origBatch->harga_per_qty - 25888.89) > 0.01 && abs((float)$origBatch->harga_per_qty - 233) > 0.01) {
                    $origPrice = (float) $origBatch->harga_per_qty;
                }
            }

            if (!$origPrice) {
                $tx = \Illuminate\Support\Facades\DB::table('transaksi_stok')
                    ->where('barang_id', $mb->barang_id)
                    ->where('gudang_tujuan_id', $mb->gudang_id)
                    ->where('tipe', 'masuk')
                    ->where('source_type', 'pengeluaran_bahan_baku')
                    ->where('tanggal', $mb->created_at)
                    ->first();
                if ($tx && (float)$tx->qty > 0) {
                    $origPrice = round((float)$tx->total_harga / (float)$tx->qty, 4);
                }
            }

            if (!$origPrice) {
                $sad = \Illuminate\Support\Facades\DB::table('persediaan_awal_detail')
                    ->where('barang_id', $mb->barang_id)
                    ->first();
                if ($sad && (float)$sad->harga_satuan > 0) {
                    $origPrice = (float) $sad->harga_satuan;
                }
            }

            $realQty = null;
            $txQty = \Illuminate\Support\Facades\DB::table('transaksi_stok')
                ->where('barang_id', $mb->barang_id)
                ->where('gudang_tujuan_id', $mb->gudang_id)
                ->where('tipe', 'masuk')
                ->where('source_type', 'pengeluaran_bahan_baku')
                ->where('tanggal', $mb->created_at)
                ->value('qty');
            if ($txQty && (float)$txQty > 0) {
                $realQty = (float) $txQty;
            }

            if ($origPrice || ($realQty && abs((float)$mb->qty_masuk - $realQty) > 0.01)) {
                $diffPrice = $origPrice && abs((float)$mb->harga_per_qty - $origPrice) > 0.001;
                $diffQty = $realQty && abs((float)$mb->qty_masuk - $realQty) > 0.01;

                if ($diffPrice || $diffQty) {
                    $update = ['updated_at' => now()];
                    if ($origPrice) {
                        $update['harga_per_qty'] = $origPrice;
                    }
                    if ($realQty && abs((float)$mb->qty_masuk - $realQty) > 0.01) {
                        $used = (float)$mb->qty_keluar;
                        $newSisa = max(0, $realQty - $used);
                        $update['qty_masuk'] = $realQty;
                        $update['qty_sisa']  = $newSisa;
                        $update['is_habis']  = ($newSisa <= 0);
                    }
                    \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                        ->where('id', $mb->id)
                        ->update($update);
                }
            }
        }
    }
}
