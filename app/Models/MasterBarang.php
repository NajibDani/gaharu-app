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
     * Mencegah dan memperbaiki kelipatan konversi ganda (misal 144 PCS terkalikan 24x menjadi 3456 PCS).
     */
    public static function autoHealUnconvertedPembelianBatches($targetBarangId = null): void
    {
        $query = \Illuminate\Support\Facades\DB::table('master_barang')
            ->where('konversi_pembelian', '>', 1);

        if ($targetBarangId) {
            $query->where('id', $targetBarangId);
        }

        $barangsWithKonversi = $query->get();

        foreach ($barangsWithKonversi as $b) {
            $konversi = (float) $b->konversi_pembelian;
            if ($konversi <= 1) {
                continue;
            }

            $pDetails = \Illuminate\Support\Facades\DB::table('pembelian_detail')
                ->where('barang_id', $b->id)
                ->get();

            foreach ($pDetails as $pd) {
                $pQty = (float) ($pd->qty ?? 0);
                $pHarga = (float) ($pd->harga ?? 0);
                if ($pQty <= 0) {
                    continue;
                }

                $hargaPerItem = $pHarga / $pQty;
                $refPrice = (float) ($b->hpp_referensi ?: 0);

                // Tentukan apakah pQty ini diinput dalam satuan dasar (PCS) atau satuan pembelian (KARTON)
                $isInputSatuanDasar = false;
                if ($refPrice > 0) {
                    if ($hargaPerItem < ($refPrice * ($konversi * 0.4))) {
                        $isInputSatuanDasar = true;
                    }
                } elseif (strtolower($pd->satuan_pembelian ?? '') === strtolower($b->satuan ?? '')) {
                    $isInputSatuanDasar = true;
                }

                $correctBaseQty = $isInputSatuanDasar ? $pQty : round($pQty * $konversi, 2);
                $correctHargaPerQty = $correctBaseQty > 0 ? round($pHarga / $correctBaseQty, 4) : 0;

                // Perbaiki transaksi_stok jika overinflated (> correctBaseQty * 1.01)
                $txList = \Illuminate\Support\Facades\DB::table('transaksi_stok')
                    ->where('barang_id', $b->id)
                    ->where('source_id', $pd->pembelian_id)
                    ->whereIn('source_type', ['pembelian', 'penerimaan_pembelian'])
                    ->get();

                foreach ($txList as $tx) {
                    if ((float)$tx->qty > ($correctBaseQty * 1.01)) {
                        \Illuminate\Support\Facades\DB::table('transaksi_stok')
                            ->where('id', $tx->id)
                            ->update(['qty' => $correctBaseQty]);
                    }
                }

                // Perbaiki stok_gudang_batch jika overinflated (> correctBaseQty * 1.01)
                $batches = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                    ->where('pembelian_detail_id', $pd->id)
                    ->get();

                foreach ($batches as $batch) {
                    if ((float)$batch->qty_masuk > ($correctBaseQty * 1.01)) {
                        $newSisa = max(0, $correctBaseQty - (float)$batch->qty_keluar);
                        \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                            ->where('id', $batch->id)
                            ->update([
                                'qty_masuk'     => $correctBaseQty,
                                'qty_sisa'      => $newSisa,
                                'harga_per_qty' => $correctHargaPerQty,
                                'is_habis'      => ($newSisa <= 0),
                                'updated_at'    => now(),
                            ]);
                    }
                }
            }
        }
    }
}
