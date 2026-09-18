<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengeluaranBahanBaku extends Model
{
    protected $table = 'pengeluaran_bahan_baku';

    protected $fillable = [
        'kode_pengeluaran',
        'tanggal',
        'gudang_id',
        'divisi_id',
        'jenis_pengeluaran',
        'status',
        'keterangan',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(
            PengeluaranBahanBakuDetail::class,
            'pengeluaran_id'
        );
    }

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Cari objek StockOpname terkait dari dokumen PBK ini.
     */
    public function findAssociatedStockOpname(): ?StockOpname
    {
        $kodePengeluaran = $this->kode_pengeluaran ?? '';
        $keterangan = $this->keterangan ?? '';

        // 1. Langsung bersihkan prefix PBK
        $candidateKode = $kodePengeluaran;
        if (str_starts_with($candidateKode, 'PBK-SO-SO-')) {
            $candidateKode = substr($candidateKode, 7);
        } elseif (str_starts_with($candidateKode, 'PBK-SO-')) {
            $candidateKode = substr($candidateKode, 4);
        } elseif (str_starts_with($candidateKode, 'PBK-')) {
            $candidateKode = substr($candidateKode, 4);
        }

        if (!empty($candidateKode)) {
            $so = StockOpname::where('kode_opname', $candidateKode)->first();
            if ($so) {
                return $so;
            }
        }

        // 2. Regex matching SO-[A-Za-z0-9-]+ pada kode_pengeluaran & keterangan
        $textToSearch = $kodePengeluaran . ' ' . $keterangan;
        if (preg_match_all('/SO-[A-Za-z0-9-]+/', $textToSearch, $matches)) {
            foreach ($matches[0] as $matchKode) {
                $matchKode = rtrim($matchKode, '-.,');
                $so = StockOpname::where('kode_opname', $matchKode)->first();
                if ($so) {
                    return $so;
                }

                if (str_starts_with($matchKode, 'SO-SO-')) {
                    $subKode = substr($matchKode, 3);
                    $so = StockOpname::where('kode_opname', $subKode)->first();
                    if ($so) {
                        return $so;
                    }
                }
            }
        }

        return null;
    }
}