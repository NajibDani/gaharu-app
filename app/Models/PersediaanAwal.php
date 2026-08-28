<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersediaanAwal extends Model
{
    use HasFactory;

    protected $table = 'persediaan_awal';

    protected $fillable = [
        'kode_transaksi',
        'tanggal',
        'gudang_id',
        'divisi_id',
        'total_item',
        'total_qty',
        'total_nilai',
        'keterangan',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'total_qty'   => 'decimal:2',
        'total_nilai' => 'decimal:2',
    ];

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(PersediaanAwalDetail::class, 'persediaan_awal_id');
    }
}
