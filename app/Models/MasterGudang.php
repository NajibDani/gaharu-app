<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterGudang extends Model
{
    protected $table = 'master_gudang';
    protected $fillable = ['nama', 'kategori'];
    public $timestamps = false;

    public function stok()
    {
        return $this->hasMany(StokGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->hasMany(GudangDivisi::class, 'gudang_id');
    }

    public function isOperasional(): bool
    {
        $namaLower = strtolower($this->nama ?? '');
        if (str_contains($namaLower, 'gaharu') || str_contains($namaLower, 'kejingga')) {
            return true;
        }
        return strtolower($this->kategori ?? '') === 'operasional';
    }

    public function isOutlet(): bool
    {
        $namaLower = strtolower($this->nama ?? '');
        if (str_contains($namaLower, 'utama') || 
            str_contains($namaLower, 'central kitchen') || 
            str_contains($namaLower, 'cold kitchen') || 
            str_contains($namaLower, 'smoke') ||
            str_contains($namaLower, 'produksi')) {
            return false;
        }
        if (str_contains($namaLower, 'gaharu') || str_contains($namaLower, 'kejingga') || str_contains($namaLower, 'outlet')) {
            return true;
        }
        return $this->isOperasional();
    }

    public static function getOutletGudangIds(): array
    {
        $gaharu = static::where('nama', 'like', '%Gaharu%')->first();
        $kejingga = static::where(function($q) {
            $q->where('nama', 'like', '%KeJingga%')->orWhere('nama', 'like', '%Kejingga%');
        })->first();
        
        $ids = [];
        if ($gaharu) $ids[] = $gaharu->id;
        if ($kejingga) $ids[] = $kejingga->id;
        $fallback = [3, 5];
        return !empty($ids) ? array_values(array_unique(array_merge($ids, $fallback))) : $fallback;
    }

    public static function resolveOutletId(?string $hint = null): int
    {
        $hintLower = strtolower($hint ?? '');
        $kejingga = static::where(function($q) {
            $q->where('nama', 'like', '%KeJingga%')->orWhere('nama', 'like', '%Kejingga%');
        })->first();
        $gaharu = static::where('nama', 'like', '%Gaharu%')->first();

        $kejinggaId = $kejingga ? $kejingga->id : 5;
        $gaharuId = $gaharu ? $gaharu->id : 3;

        if (str_contains($hintLower, 'kj') || str_contains($hintLower, 'kejingga') || str_contains($hintLower, 'jingga')) {
            return $kejinggaId;
        }
        return $gaharuId;
    }

    public static function getGudangUtama(): ?self
    {
        return static::where('kategori', 'Utama')
            ->orWhere('nama', 'like', '%Gudang Utama%')
            ->orWhere('nama', 'like', '%Utama%')
            ->first();
    }

    public static function getGudangUtamaId(): int
    {
        return static::getGudangUtama()?->id ?? 2;
    }

    public static function resolveDivisiIdForBarang($gudangId, $barangId)
    {
        if (!$gudangId || !$barangId) return null;

        // 1. Tagging spesifik divisi pada barang_minimum_stock (hanya yang memiliki threshold positif)
        $minStockDivisi = \Illuminate\Support\Facades\DB::table('barang_minimum_stock')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->where('is_active', true)
            ->whereNotNull('divisi_id')
            ->where('minimum_stock', '>', 0)
            ->value('divisi_id');

        if ($minStockDivisi) {
            return $minStockDivisi;
        }

        // 2. Tagging riwayat transaksi masuk (persediaan awal / saldo awal / PBK masuk / stock opname) ke divisi di gudang ini
        $recentDivisi = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('gudang_tujuan_id', $gudangId)
            ->whereNotNull('divisi_tujuan_id')
            ->whereIn('source_type', ['persediaan_awal', 'saldo_awal', 'pengeluaran_bahan_baku', 'stock_opname'])
            ->latest('tanggal')
            ->value('divisi_tujuan_id');

        if ($recentDivisi) {
            return $recentDivisi;
        }

        // 3. Cek batch aktif di stok_gudang_batch yang memiliki sisa stok
        $batchDivisi = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->whereNotNull('divisi_id')
            ->where('qty_sisa', '>', 0)
            ->groupBy('divisi_id')
            ->orderByRaw('SUM(qty_sisa) DESC')
            ->value('divisi_id');

        if ($batchDivisi) {
            return $batchDivisi;
        }

        // 4. Cek stok ringkasan di stok_gudang yang memiliki jumlah positif
        $stokGudangDivisi = \Illuminate\Support\Facades\DB::table('stok_gudang')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->whereNotNull('divisi_id')
            ->where('jumlah', '>', 0)
            ->orderByDesc('jumlah')
            ->value('divisi_id');

        if ($stokGudangDivisi) {
            return $stokGudangDivisi;
        }

        // 5. Cek dari riwayat divisi asal di transaksi stok (misal pernah dipotong dari divisi tertentu)
        $recentAsalDivisi = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('gudang_asal_id', $gudangId)
            ->whereNotNull('divisi_asal_id')
            ->latest('tanggal')
            ->value('divisi_asal_id');

        if ($recentAsalDivisi) {
            return $recentAsalDivisi;
        }

        // 5. Ketentuan tagging divisi berdasarkan Kategori Master Barang & Nama Barang
        $barang = \Illuminate\Support\Facades\DB::table('master_barang')->where('id', $barangId)->first();
        $divisis = \Illuminate\Support\Facades\DB::table('gudang_divisi')->where('gudang_id', $gudangId)->get();
        $baristaDiv = $divisis->first(fn($d) => stripos($d->nama, 'barista') !== false || stripos($d->nama, 'bar') !== false);
        $kitchenDiv = $divisis->first(fn($d) => stripos($d->nama, 'kitchen') !== false || stripos($d->nama, 'dapur') !== false);
        $serverDiv  = $divisis->first(fn($d) => stripos($d->nama, 'server') !== false || stripos($d->nama, 'service') !== false || stripos($d->nama, 'operasional') !== false);

        if ($barang && $barang->kategori_id) {
            $kategori = \Illuminate\Support\Facades\DB::table('kategori')->where('id', $barang->kategori_id)->first();
            if ($kategori) {
                $katNama = strtolower($kategori->nama);

                if ((str_contains($katNama, 'minuman') || str_contains($katNama, 'kopi') || str_contains($katNama, 'coffee') || str_contains($katNama, 'bar') || str_contains($katNama, 'drink') || str_contains($katNama, 'beverage') || str_contains($katNama, 'tea')) && $baristaDiv) {
                    return $baristaDiv->id;
                }

                if (str_contains($katNama, 'makanan') && !str_contains($katNama, 'minuman') && $kitchenDiv) {
                    return $kitchenDiv->id;
                }

                if ((str_contains($katNama, 'server') || str_contains($katNama, 'service') || str_contains($katNama, 'operasional') || str_contains($katNama, 'packaging')) && $serverDiv) {
                    return $serverDiv->id;
                }
            }
        }

        // 6. Deteksi berdasarkan kata kunci nama barang
        if ($barang && !empty($barang->nama)) {
            $namaLower = strtolower($barang->nama);
            $baristaKeywords = ['kopi', 'coffee', 'arabica', 'robusta', 'espresso', 'milk', 'susu', 'fresh milk', 'syrup', 'sirup', 'tea', 'teh', 'powder', 'ice', 'es batu', 'galon', 'cleo', 'air', 'cup', 'straw', 'sedotan', 'creamer', 'matcha', 'taro', 'hazelnut', 'vanilla', 'caramel', 'gula', 'sugar', 'madu', 'bean'];
            foreach ($baristaKeywords as $kw) {
                if (str_contains($namaLower, $kw) && $baristaDiv) {
                    return $baristaDiv->id;
                }
            }

            $kitchenKeywords = ['ayam', 'chicken', 'daging', 'beef', 'ikan', 'fish', 'sauce', 'saus', 'sambal', 'rice', 'nasi', 'noodle', 'mie', 'flour', 'terigu', 'oil', 'minyak', 'egg', 'telur', 'bawang', 'sayur', 'pastry', 'roti', 'bread', 'keju', 'cheese', 'butter', 'mentega'];
            foreach ($kitchenKeywords as $kw) {
                if (str_contains($namaLower, $kw) && $kitchenDiv) {
                    return $kitchenDiv->id;
                }
            }
        }

        return $baristaDiv?->id ?: ($kitchenDiv?->id ?: null);
    }

    public function permintaanBahanBaku()
    {
        return $this->hasMany(
            PermintaanBahanBaku::class,
            'gudang_id'
        );
    }

    public function stockOpname()
    {
        return $this->hasMany(
            StockOpname::class,
            'gudang_id'
        );
    }
}