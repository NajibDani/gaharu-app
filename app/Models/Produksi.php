<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produksi extends Model
{
    protected $table = 'produksi';

    protected $fillable = [
        'kode_produksi', 
        'pesanan_id', 
        'tanggal_mulai', 
        'tanggal_selesai', 
        'status_produksi', 
        'gudang_bahan_id', 
        'gudang_hasil_id', 
        'created_by',
        'divisi_id',
        'catatan',
        'keterangan',
        'status_pembayaran',
        'tanggal_pembayaran',
        'metode_pembayaran',
        'catatan_pembayaran',
        'no_invoice',
        'dibayar_by'
    ];

    /**
     * Relasi ke Pesanan (Sangat Penting untuk memperbaiki Error)
     */
    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }

    public function alokasiPesanan(): HasMany
    {
        return $this->hasMany(ProduksiPesanan::class, 'produksi_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    /**
     * Relasi ke Detail Produksi
     */
    public function details(): HasMany
    {
        return $this->hasMany(ProduksiDetail::class, 'produksi_id');
    }

    /**
     * Relasi ke Permintaan Bahan Baku
     */
    public function permintaanBahanBaku(): HasMany
    {
        return $this->hasMany(PermintaanBahanBaku::class, 'produksi_id');
    }

    /**
     * Relasi ke User pembuat data
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Gudang Bahan (Gudang B2B - ID 3)
     */
    public function gudangBahan(): BelongsTo
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_bahan_id');
    }

    /**
     * Relasi ke Gudang Hasil (Gudang B2B - ID 3)
     */
    public function gudangHasil(): BelongsTo
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_hasil_id');
    }

    // Accessors & JSON Payment Metadata (Tanpa Migrasi)
    public function getPaymentMeta(): array
    {
        $raw = $this->catatan ?? $this->keterangan ?? '';
        if ($raw && str_contains($raw, '__PAYMENT_META__:')) {
            $jsonStr = substr($raw, strpos($raw, '__PAYMENT_META__:') + strlen('__PAYMENT_META__:'));
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    public function getStatusPembayaranAttribute()
    {
        if (isset($this->attributes['status_pembayaran']) && $this->attributes['status_pembayaran']) {
            return $this->attributes['status_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        if (isset($meta['status_pembayaran'])) {
            return $meta['status_pembayaran'];
        }
        if ($this->pesanan && strtolower($this->pesanan->status_pembayaran ?? '') === 'lunas') {
            return 'lunas';
        }
        if ($this->alokasiPesanan && $this->alokasiPesanan->isNotEmpty()) {
            foreach ($this->alokasiPesanan as $alo) {
                if ($alo->pesanan && strtolower($alo->pesanan->status_pembayaran ?? '') === 'lunas') {
                    return 'lunas';
                }
            }
        }
        return 'belum_dibayar';
    }

    public function getTanggalPembayaranAttribute()
    {
        if (isset($this->attributes['tanggal_pembayaran']) && $this->attributes['tanggal_pembayaran']) {
            return \Carbon\Carbon::parse($this->attributes['tanggal_pembayaran']);
        }
        $meta = $this->getPaymentMeta();
        if (isset($meta['tanggal_pembayaran'])) {
            return \Carbon\Carbon::parse($meta['tanggal_pembayaran']);
        }
        $pes = $this->pesanan ?? ($this->alokasiPesanan->first()->pesanan ?? null);
        if ($pes) {
            $pembayaran = \App\Models\Pembayaran::where('pesanan_id', $pes->id)->latest('id')->first();
            if ($pembayaran && $pembayaran->tanggal_bayar) {
                return \Carbon\Carbon::parse($pembayaran->tanggal_bayar);
            }
        }
        return null;
    }

    public function getMetodePembayaranAttribute()
    {
        if (isset($this->attributes['metode_pembayaran']) && $this->attributes['metode_pembayaran']) {
            return $this->attributes['metode_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        if (isset($meta['metode_pembayaran'])) {
            return $meta['metode_pembayaran'];
        }
        $pes = $this->pesanan ?? ($this->alokasiPesanan->first()->pesanan ?? null);
        if ($pes) {
            $pembayaran = \App\Models\Pembayaran::where('pesanan_id', $pes->id)->latest('id')->first();
            if ($pembayaran) {
                return $pembayaran->metode_pembayaran;
            }
        }
        return null;
    }

    public function getCatatanPembayaranAttribute()
    {
        if (isset($this->attributes['catatan_pembayaran']) && $this->attributes['catatan_pembayaran']) {
            return $this->attributes['catatan_pembayaran'];
        }
        $meta = $this->getPaymentMeta();
        if (isset($meta['catatan_pembayaran'])) {
            return $meta['catatan_pembayaran'];
        }
        $pes = $this->pesanan ?? ($this->alokasiPesanan->first()->pesanan ?? null);
        if ($pes) {
            $pembayaran = \App\Models\Pembayaran::where('pesanan_id', $pes->id)->latest('id')->first();
            if ($pembayaran) {
                return $pembayaran->catatan;
            }
        }
        return null;
    }

    public function getNoInvoiceAttribute()
    {
        if (isset($this->attributes['no_invoice']) && $this->attributes['no_invoice']) {
            return $this->attributes['no_invoice'];
        }
        $meta = $this->getPaymentMeta();
        if (isset($meta['no_invoice'])) {
            return $meta['no_invoice'];
        }
        $pes = $this->pesanan ?? ($this->alokasiPesanan->first()->pesanan ?? null);
        if ($pes) {
            $pembayaran = \App\Models\Pembayaran::where('pesanan_id', $pes->id)->latest('id')->first();
            if ($pembayaran && $pembayaran->no_invoice) {
                return $pembayaran->no_invoice;
            }
        }
        return null;
    }

    public function getDibayarByAttribute()
    {
        if (isset($this->attributes['dibayar_by']) && $this->attributes['dibayar_by']) {
            return $this->attributes['dibayar_by'];
        }
        $meta = $this->getPaymentMeta();
        if (isset($meta['dibayar_by'])) {
            return $meta['dibayar_by'];
        }
        $pes = $this->pesanan ?? ($this->alokasiPesanan->first()->pesanan ?? null);
        if ($pes) {
            $pembayaran = \App\Models\Pembayaran::where('pesanan_id', $pes->id)->latest('id')->first();
            if ($pembayaran) {
                return $pembayaran->created_by;
            }
        }
        return null;
    }

    public function dibayarByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibayar_by');
    }

    public function updatePaymentMeta(array $data)
    {
        // 1. Direct update to columns if they exist in schema
        $directUpdates = [];
        foreach (['status_pembayaran', 'tanggal_pembayaran', 'metode_pembayaran', 'catatan_pembayaran', 'no_invoice', 'dibayar_by'] as $col) {
            if (isset($data[$col]) && \Illuminate\Support\Facades\Schema::hasColumn('produksi', $col)) {
                $directUpdates[$col] = $data[$col];
            }
        }
        if (!empty($directUpdates)) {
            $this->update($directUpdates);
        }

        // 2. Also keep payment metadata in catatan / keterangan ONLY IF column exists
        if (\Illuminate\Support\Facades\Schema::hasColumn('produksi', 'catatan') || \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'keterangan')) {
            $existing = $this->getPaymentMeta();
            $merged = array_merge($existing, $data);
            
            $baseText = $this->catatan ?? $this->keterangan ?? '';
            if (str_contains($baseText, '__PAYMENT_META__:')) {
                $baseText = trim(substr($baseText, 0, strpos($baseText, '__PAYMENT_META__:')));
            }

            $newRaw = ($baseText ? $baseText . "\n" : '') . '__PAYMENT_META__:' . json_encode($merged);

            if (\Illuminate\Support\Facades\Schema::hasColumn('produksi', 'catatan')) {
                $this->update(['catatan' => $newRaw]);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('produksi', 'keterangan')) {
                $this->update(['keterangan' => $newRaw]);
            }
        }
    }
}