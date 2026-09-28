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