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
        'status_pembayaran',
        'tanggal_pembayaran',
        'metode_pembayaran',
        'catatan_pembayaran',
        'no_invoice',
        'dibayar_by',
        'keterangan',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'tanggal_pembayaran' => 'datetime',
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

    public function dibayarByUser()
    {
        if (isset($this->attributes['dibayar_by'])) {
            return $this->belongsTo(User::class, 'dibayar_by');
        }
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPaymentMeta(): array
    {
        if (empty($this->keterangan)) {
            return [];
        }
        $decoded = json_decode($this->keterangan, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return ['note' => $this->keterangan];
    }

    public function updatePaymentMeta(array $data): void
    {
        $meta = $this->getPaymentMeta();
        foreach ($data as $key => $val) {
            $meta[$key] = $val;
        }
        $this->keterangan = json_encode($meta);
        $this->save();
    }

    public function isLunas(): bool
    {
        return strtolower($this->status_pembayaran ?? '') === 'lunas';
    }

    public function getStatusPembayaranAttribute(): string
    {
        if (isset($this->attributes['status_pembayaran']) && !empty($this->attributes['status_pembayaran'])) {
            return $this->attributes['status_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        return $meta['status_pembayaran'] ?? 'belum_dibayar';
    }

    public function getTanggalPembayaranAttribute()
    {
        if (isset($this->attributes['tanggal_pembayaran']) && !empty($this->attributes['tanggal_pembayaran'])) {
            return $this->attributes['tanggal_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        return !empty($meta['tanggal_pembayaran']) ? \Carbon\Carbon::parse($meta['tanggal_pembayaran']) : null;
    }

    public function getMetodePembayaranAttribute(): ?string
    {
        if (isset($this->attributes['metode_pembayaran']) && !empty($this->attributes['metode_pembayaran'])) {
            return $this->attributes['metode_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        return $meta['metode_pembayaran'] ?? null;
    }

    public function getCatatanPembayaranAttribute(): ?string
    {
        if (isset($this->attributes['catatan_pembayaran']) && !empty($this->attributes['catatan_pembayaran'])) {
            return $this->attributes['catatan_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        return $meta['catatan_pembayaran'] ?? null;
    }

    public function getNoInvoiceAttribute(): ?string
    {
        if (isset($this->attributes['no_invoice']) && !empty($this->attributes['no_invoice'])) {
            return $this->attributes['no_invoice'];
        }
        $meta = $this->getPaymentMeta();
        return $meta['no_invoice'] ?? null;
    }

    public function getDibayarByAttribute(): ?int
    {
        if (isset($this->attributes['dibayar_by']) && !empty($this->attributes['dibayar_by'])) {
            return (int)$this->attributes['dibayar_by'];
        }
        $meta = $this->getPaymentMeta();
        return isset($meta['dibayar_by']) ? (int)$meta['dibayar_by'] : null;
    }

    public function getDibayarUserAttribute()
    {
        $id = $this->dibayar_by;
        return $id ? User::find($id) : null;
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