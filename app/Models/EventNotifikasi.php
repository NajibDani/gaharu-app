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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
                  ->orWhere('menu_target', 'semua');
            });
    }
}

