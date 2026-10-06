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
        static::saving(function ($barang) {
            if ($barang->is_barang_jadi) {
                $barang->is_bahan_baku = false;
                $barang->is_bahan_setengah_jadi = false;
                $barang->is_operational = false;
            } elseif ($barang->is_operational) {
                $barang->is_bahan_baku = false;
                $barang->is_bahan_setengah_jadi = false;
                $barang->is_barang_jadi = false;
            } elseif ($barang->is_bahan_setengah_jadi) {
                $barang->is_bahan_baku = false;
                $barang->is_barang_jadi = false;
                $barang->is_operational = false;
            } elseif ($barang->is_bahan_baku) {
                $barang->is_bahan_setengah_jadi = false;
                $barang->is_barang_jadi = false;
                $barang->is_operational = false;
            }
        });

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

        // 2. Ambil detail pembelian untuk divalidasi dan disinkronkan kuantitas serta harga batch-nya
        $query = \Illuminate\Support\Facades\DB::table('pembelian_detail')
            ->join('master_barang', 'pembelian_detail.barang_id', '=', 'master_barang.id')
            ->select(
                'pembelian_detail.*',
                'master_barang.satuan as master_satuan',
                'master_barang.satuan_pembelian as master_satuan_pembelian',
                'master_barang.konversi_pembelian as master_konversi'
            );

        if ($targetBarangId) {
            $query->where('pembelian_detail.barang_id', $targetBarangId);
            $pDetails = $query->get();
        } else {
            $pDetails = $query->get();
        }

        foreach ($pDetails as $pd) {
            $pQty = (float) ($pd->qty ?? 0);
            $pHarga = (float) ($pd->harga ?? 0);
            $pHargaPerQty = (float) ($pd->harga_per_qty ?? 0);
            if ($pQty <= 0) {
                continue;
            }

            if ($pHarga <= 0 && $pHargaPerQty > 0) {
                $pHarga = round($pQty * $pHargaPerQty, 2);
            }

            $detailKonv = (float) ($pd->konversi_pembelian ?? 1);
            $satBeli = strtolower(trim($pd->satuan_pembelian ?? ''));
            $satDasar = strtolower(trim($pd->master_satuan ?? ''));
            $masterSatBeli = strtolower(trim($pd->master_satuan_pembelian ?? ''));
            $masterKonv = (float) ($pd->master_konversi ?? 1);

            $isSatuanBeli = false;
            $konversi = 1.0;

            if ($detailKonv > 1) {
                $isSatuanBeli = true;
                $konversi = $detailKonv;
            } elseif (!empty($satBeli) && !empty($satDasar) && $satBeli !== $satDasar && $masterKonv > 1) {
                $isSatuanBeli = true;
                $konversi = $masterKonv;
            }

            $correctBaseQty = $isSatuanBeli ? round($pQty * $konversi, 4) : $pQty;
            $correctHargaPerQty = $correctBaseQty > 0 ? round($pHarga / $correctBaseQty, 4) : ($pHargaPerQty > 0 ? ($isSatuanBeli ? round($pHargaPerQty / $konversi, 4) : $pHargaPerQty) : 0);

            // Perbaiki transaksi_stok jika tidak sesuai dengan correctBaseQty atau pHarga
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

                // Konversi kuantitas jika masih dalam format qty pembelian
                if ($isSatuanBeli && abs((float)$batch->qty_masuk - $pQty) < 0.01 && abs((float)$batch->qty_masuk - $correctBaseQty) > 0.01) {
                    $newMasuk = $correctBaseQty;
                    $qtyKeluar = (float)$batch->qty_keluar;
                    $newSisa = max(0, $newMasuk - $qtyKeluar);
                    $updateData['qty_masuk'] = $newMasuk;
                    $updateData['qty_sisa']  = $newSisa;
                    $updateData['is_habis']  = ($newSisa <= 0);
                    $needsUpdate = true;
                }

                // Konversi harga jika tidak sesuai dengan correctHargaPerQty
                if ($correctHargaPerQty > 0 && abs((float)$batch->harga_per_qty - $correctHargaPerQty) > 0.001) {
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

        // Heal corrupted rogue prices (233 / 25888.89 / dummy fallback) in batches and master_barang
        self::autoHealCorruptedDummyBatches($targetBarangId);

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
     * Bersihkan batch atau master barang yang terkontaminasi harga corrupted dummy (seperti 233 atau 25888.89)
     */
    public static function autoHealCorruptedDummyBatches($targetBarangId = null): void
    {
        $barangsQuery = \Illuminate\Support\Facades\DB::table('master_barang');
        if ($targetBarangId) {
            $barangsQuery->where('id', $targetBarangId);
        } else {
            $barangsQuery->where(function($q) {
                $q->whereBetween('hpp_referensi', [232, 234])
                  ->orWhereBetween('hpp_referensi', [25888, 25889])
                  ->orWhereExists(function($sub) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('stok_gudang_batch')
                          ->whereColumn('stok_gudang_batch.barang_id', 'master_barang.id')
                          ->where(function($bSub) {
                              $bSub->whereBetween('stok_gudang_batch.harga_per_qty', [232, 234])
                                   ->orWhereBetween('stok_gudang_batch.harga_per_qty', [25888, 25889]);
                          });
                  });
            });
        }

        $items = $barangsQuery->get();
        foreach ($items as $item) {
            // Dapatkan harga acuan sebenarnya: dari pembelian_detail terbaru atau persediaan_awal_detail
            $realPrice = null;
            $satDasar = strtolower(trim($item->satuan ?? ''));
            $masterKonv = (float)($item->konversi_pembelian ?? 1);

            $latestPd = \Illuminate\Support\Facades\DB::table('pembelian_detail')
                ->join('pembelian', 'pembelian.id', '=', 'pembelian_detail.pembelian_id')
                ->where('pembelian_detail.barang_id', $item->id)
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
                ->where(function($q) {
                    $q->where('pembelian_detail.harga_per_qty', '>', 0)
                      ->orWhere('pembelian_detail.harga', '>', 0);
                })
                ->orderBy('pembelian.tanggal', 'desc')
                ->orderBy('pembelian.id', 'desc')
                ->orderBy('pembelian_detail.id', 'desc')
                ->first();

            if ($latestPd) {
                $pQty = (float)($latestPd->qty ?? 0);
                $pHarga = (float)($latestPd->harga ?? 0);
                $pHargaPerQty = (float)($latestPd->harga_per_qty ?? 0);
                $detailKonv = (float)($latestPd->konversi_pembelian ?? 1);
                $satBeli = strtolower(trim($latestPd->satuan_pembelian ?? ''));
                $effectiveKonv = $detailKonv > 1 ? $detailKonv : ($masterKonv > 1 ? $masterKonv : 1.0);

                $isSatuanBeli = false;
                if (!empty($satBeli) && !empty($satDasar) && $satBeli !== $satDasar) {
                    $isSatuanBeli = true;
                } elseif ($effectiveKonv > 1 && $pQty < ($effectiveKonv * 0.5) && $pHarga > 0) {
                    $isSatuanBeli = true;
                }

                $totalBaseQty = $isSatuanBeli ? ($pQty * $effectiveKonv) : $pQty;
                if ($totalBaseQty > 0 && $pHarga > 0) {
                    $realPrice = round($pHarga / $totalBaseQty, 4);
                } elseif ($pHargaPerQty > 0) {
                    $realPrice = round($isSatuanBeli ? ($pHargaPerQty / $effectiveKonv) : $pHargaPerQty, 4);
                }
            }

            if (!$realPrice || $realPrice <= 0) {
                $sad = \Illuminate\Support\Facades\DB::table('persediaan_awal_detail')
                    ->where('barang_id', $item->id)
                    ->where('harga_satuan', '>', 0)
                    ->orderBy('id', 'desc')
                    ->value('harga_satuan');
                if ($sad && (float)$sad > 0) {
                    $realPrice = (float)$sad;
                }
            }

            if (!$realPrice || $realPrice <= 0) {
                $realPrice = (float)($item->harga_beli ?? 0);
                if ($masterKonv > 1 && $realPrice > 0) {
                    $realPrice = round($realPrice / $masterKonv, 4);
                }
            }

            if ($realPrice && $realPrice > 0) {
                // Update batch yang korup
                \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                    ->where('barang_id', $item->id)
                    ->where(function($q) {
                        $q->whereBetween('harga_per_qty', [232, 234])
                          ->orWhereBetween('harga_per_qty', [25888, 25889]);
                    })
                    ->update([
                        'harga_per_qty' => $realPrice,
                        'updated_at'    => now(),
                    ]);

                // Update master_barang hpp_referensi jika korup
                if ((abs((float)$item->hpp_referensi - 233) <= 1) || (abs((float)$item->hpp_referensi - 25888.89) <= 1)) {
                    \Illuminate\Support\Facades\DB::table('master_barang')
                        ->where('id', $item->id)
                        ->update([
                            'hpp_referensi' => $realPrice,
                            'updated_at'    => now(),
                        ]);
                }
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
                    $calcPrice = round((float)$tx->total_harga / (float)$tx->qty, 4);
                    if ($calcPrice > 0 && abs($calcPrice - 233) > 1 && abs($calcPrice - 25888.89) > 1) {
                        $origPrice = $calcPrice;
                    }
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

    public static function healConflictingJenisFlags(): void
    {
        // 1. Barang jadi yang masih berstatus bahan baku / operational
        \Illuminate\Support\Facades\DB::table('master_barang')
            ->where('is_barang_jadi', 1)
            ->where(function ($q) {
                $q->where('is_bahan_baku', 1)
                  ->orWhere('is_bahan_setengah_jadi', 1)
                  ->orWhere('is_operational', 1);
            })
            ->update([
                'is_bahan_baku'          => 0,
                'is_bahan_setengah_jadi' => 0,
                'is_operational'         => 0,
            ]);

        // 2. Operational yang masih berstatus bahan baku / BSJ / barang jadi
        \Illuminate\Support\Facades\DB::table('master_barang')
            ->where('is_operational', 1)
            ->where(function ($q) {
                $q->where('is_bahan_baku', 1)
                  ->orWhere('is_bahan_setengah_jadi', 1)
                  ->orWhere('is_barang_jadi', 1);
            })
            ->update([
                'is_bahan_baku'          => 0,
                'is_bahan_setengah_jadi' => 0,
                'is_barang_jadi'         => 0,
            ]);

        // 3. Bahan setengah jadi yang masih berstatus bahan baku
        \Illuminate\Support\Facades\DB::table('master_barang')
            ->where('is_bahan_setengah_jadi', 1)
            ->where('is_bahan_baku', 1)
            ->update([
                'is_bahan_baku' => 0,
            ]);
    }
}
