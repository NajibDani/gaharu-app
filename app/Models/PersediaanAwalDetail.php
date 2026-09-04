<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersediaanAwalDetail extends Model
{
    use HasFactory;

    protected $table = 'persediaan_awal_detail';

    protected $fillable = [
        'persediaan_awal_id',
        'barang_id',
        'qty',
        'satuan',
        'satuan_pembelian',
        'konversi_pembelian',
        'qty_pembelian',
        'harga_pembelian',
        'harga_satuan',
        'total_nilai',
        'batch_number',
    ];

    protected $casts = [
        'qty'                => 'decimal:2',
        'konversi_pembelian' => 'decimal:2',
        'qty_pembelian'      => 'decimal:2',
        'harga_pembelian'    => 'decimal:2',
        'harga_satuan'       => 'decimal:2',
        'total_nilai'        => 'decimal:2',
    ];

    public function persediaanAwal()
    {
        return $this->belongsTo(PersediaanAwal::class, 'persediaan_awal_id');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }
}
