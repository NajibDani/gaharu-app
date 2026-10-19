<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreparationInput extends Model
{
    use HasFactory;

    protected $table = 'preparation_inputs';

    protected $fillable = [
        'preparation_id',
        'barang_id',
        'qty',
        'satuan',
        'konversi',
        'qty_base',
        'hpp_satuan',
        'total_biaya',
        'fifo_breakdown',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'konversi' => 'decimal:4',
        'qty_base' => 'decimal:4',
        'hpp_satuan' => 'decimal:4',
        'total_biaya' => 'decimal:4',
        'fifo_breakdown' => 'array',
    ];

    public function preparation()
    {
        return $this->belongsTo(Preparation::class, 'preparation_id');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }
}
