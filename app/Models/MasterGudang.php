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
        return strtolower($this->kategori) === 'operasional';
    }

    public function isOutlet(): bool
    {
        if (!$this->isOperasional()) {
            return false;
        }
        $namaLower = strtolower($this->nama);
        if (str_contains($namaLower, 'utama') || 
            str_contains($namaLower, 'central kitchen') || 
            str_contains($namaLower, 'cold kitchen') || 
            str_contains($namaLower, 'smoke')) {
            return false;
        }
        return true;
    }

    public static function getOutletGudangIds(): array
    {
        $gaharu = static::where('kategori', 'Operasional')->where('nama', 'like', '%Gaharu%')->first()
            ?? static::where('nama', 'like', '%Gaharu%')->first();
        $kejingga = static::where('kategori', 'Operasional')->where('nama', 'like', '%KeJingga%')->first()
            ?? static::where('nama', 'like', '%KeJingga%')->first();
        
        $ids = [];
        if ($gaharu) $ids[] = $gaharu->id;
        if ($kejingga) $ids[] = $kejingga->id;
        return !empty($ids) ? array_unique($ids) : [3, 5];
    }

    public static function resolveOutletId(?string $hint = null): int
    {
        $hintLower = strtolower($hint ?? '');
        $kejingga = static::where('kategori', 'Operasional')->where('nama', 'like', '%KeJingga%')->first()
            ?? static::where('nama', 'like', '%KeJingga%')->first();
        $gaharu = static::where('kategori', 'Operasional')->where('nama', 'like', '%Gaharu%')->first()
            ?? static::where('nama', 'like', '%Gaharu%')->first();

        if (str_contains($hintLower, 'kj') || str_contains($hintLower, 'kejingga')) {
            return $kejingga ? $kejingga->id : 5;
        }
        return $gaharu ? $gaharu->id : 3;
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

        // 1. Tagging spesifik divisi pada master barang / barang_minimum_stock
        $minStockDivisi = \Illuminate\Support\Facades\DB::table('barang_minimum_stock')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->where('is_active', true)
            ->whereNotNull('divisi_id')
            ->value('divisi_id');

        if ($minStockDivisi) {
            return $minStockDivisi;
        }

        // 2. Tagging riwayat persediaan awal / PBK ke divisi di gudang ini
        $recentDivisi = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('gudang_tujuan_id', $gudangId)
            ->whereNotNull('divisi_tujuan_id')
            ->whereIn('source_type', ['persediaan_awal', 'saldo_awal', 'pengeluaran_bahan_baku'])
            ->latest('tanggal')
            ->value('divisi_tujuan_id');

        if ($recentDivisi) {
            return $recentDivisi;
        }

        // 3. Ketentuan tagging divisi berdasarkan Kategori Master Barang:
        //    - Kategori Makanan -> Divisi Kitchen
        //    - Kategori Minuman -> Divisi Bar / Barista
        //    - Kategori Server / Operasional -> Divisi Server
        $barang = \Illuminate\Support\Facades\DB::table('master_barang')->where('id', $barangId)->first();
        if ($barang && $barang->kategori_id) {
            $kategori = \Illuminate\Support\Facades\DB::table('kategori')->where('id', $barang->kategori_id)->first();
            if ($kategori) {
                $katNama = strtolower($kategori->nama);
                $divisis = \Illuminate\Support\Facades\DB::table('gudang_divisi')->where('gudang_id', $gudangId)->get();

                if (str_contains($katNama, 'makanan') && !str_contains($katNama, 'minuman')) {
                    $kitchen = $divisis->first(fn($d) => stripos($d->nama, 'kitchen') !== false || stripos($d->nama, 'dapur') !== false);
                    if ($kitchen) return $kitchen->id;
                }

                if (str_contains($katNama, 'minuman')) {
                    $bar = $divisis->first(fn($d) => stripos($d->nama, 'bar') !== false || stripos($d->nama, 'barista') !== false);
                    if ($bar) return $bar->id;
                }

                if (str_contains($katNama, 'server') || str_contains($katNama, 'service') || str_contains($katNama, 'operasional')) {
                    $server = $divisis->first(fn($d) => stripos($d->nama, 'server') !== false || stripos($d->nama, 'service') !== false);
                    if ($server) return $server->id;
                }
            }
        }

        return null;
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