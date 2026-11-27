<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventNotifikasi extends Model
{
    protected $table = 'event_notifikasis';

    protected $fillable = [
        'judul',
        'pesan',
        'menu_target',
        'tanggal_mulai',
        'tanggal_selesai',
        'tipe_icon',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'is_active'       => 'boolean',
    ];

    public const MENU_OPTIONS = [
        'pembelian'     => 'Pembelian Bahan Baku',
        'permintaan'    => 'Permintaan / Transfer Bahan',
        'produksi'      => 'Produksi Central Kitchen',
        'penjualan_pos' => 'Penjualan POS',
        'semua'         => 'Semua Menu',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mengembalikan daftar menu target sebagai array
     */
    public function getMenuTargetsAttribute(): array
    {
        if (empty($this->menu_target)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->menu_target))));
    }

    /**
     * Scope untuk mengambil event yang aktif untuk tanggal hari ini dan menu target tertentu
     */
    public function scopeAktifUntukMenu($query, string $menu = 'pembelian')
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->where(function ($q) use ($menu) {
                $q->where('menu_target', $menu)
                  ->orWhere('menu_target', 'semua')
                  ->orWhere('menu_target', 'like', "{$menu},%")
                  ->orWhere('menu_target', 'like', "%,{$menu},%")
                  ->orWhere('menu_target', 'like', "%,{$menu}")
                  ->orWhere('menu_target', 'like', 'semua,%')
                  ->orWhere('menu_target', 'like', '%,semua,%')
                  ->orWhere('menu_target', 'like', '%,semua');
            });
    }
}

