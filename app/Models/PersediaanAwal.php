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

    public function isTerpakaiPengeluaranBahanBaku(): bool
    {
        $batchNumbers = $this->details->pluck('batch_number')->filter()->toArray();

        if (empty($batchNumbers)) {
            $barangIds = $this->details->pluck('barang_id')->filter()->toArray();
            if (empty($barangIds)) {
                return false;
            }
            $batchNumbers = StokGudangBatch::where('gudang_id', $this->gudang_id)
                ->whereIn('barang_id', $barangIds)
                ->where('batch_number', 'like', 'SA-%')
                ->pluck('batch_number')
                ->toArray();
        }

        if (empty($batchNumbers)) {
            return false;
        }

        // Cek 1: PengeluaranBahanBakuFifo
        $usedInFifo = PengeluaranBahanBakuFifo::whereIn('batch_number', $batchNumbers)->exists();
        if ($usedInFifo) {
            return true;
        }

        // Cek 2: StokGudangBatch (qty_keluar > 0)
        return StokGudangBatch::whereIn('batch_number', $batchNumbers)
            ->where('qty_keluar', '>', 0)
            ->exists();
    }
}

