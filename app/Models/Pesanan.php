<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MasterGudang;

class Pesanan extends Model
{
    protected $table = 'pesanan';

    public $pendingCatatan = null;

    protected $fillable = [
        'kode_pesanan',
        'tipe_pesanan',
        'customer_id',
        'tanggal',
        'estimasi_kirim',
        'estimasi_produksi',
        'total_pesanan',
        'tax_percentage',
        'tax_service',
        'status_pesanan',
        'status_pembayaran',
        'created_by',
        'gudang_id',
        'divisi_id',
        'catatan',
    ];

    public function getCatatanAttribute()
    {
        if (array_key_exists('catatan', $this->attributes)) {
            return $this->attributes['catatan'];
        }
        return \Illuminate\Support\Facades\Cache::get('pesanan_catatan_' . $this->id);
    }

    public function setCatatanAttribute($value)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('pesanan', 'catatan')) {
            $this->attributes['catatan'] = $value;
        } else {
            $this->pendingCatatan = $value;
        }
    }

    protected static function booted()
    {
        static::saving(function ($pesanan) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('pesanan', 'catatan')) {
                if (isset($pesanan->attributes['catatan'])) {
                    $pesanan->pendingCatatan = $pesanan->attributes['catatan'];
                    unset($pesanan->attributes['catatan']);
                }
            }
        });

        static::saved(function ($pesanan) {
            if (isset($pesanan->pendingCatatan)) {
                if (!empty($pesanan->pendingCatatan)) {
                    \Illuminate\Support\Facades\Cache::put('pesanan_catatan_' . $pesanan->id, $pesanan->pendingCatatan, now()->addYears(5));
                } else {
                    \Illuminate\Support\Facades\Cache::forget('pesanan_catatan_' . $pesanan->id);
                }
                unset($pesanan->pendingCatatan);
            }
        });

        static::deleted(function ($pesanan) {
            \Illuminate\Support\Facades\Cache::forget('pesanan_catatan_' . $pesanan->id);
        });
    }

    public function scopeB2b($query)
    {
        return $query->where(function($q) {
            $q->where('tipe_pesanan', 'b2b')
              ->orWhereNull('tipe_pesanan');
        });
    }

    public function scopeCentralKitchen($query)
    {
        return $query->where('tipe_pesanan', 'central_kitchen');
    }

    protected $casts = [
        'total_pesanan'  => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_service'    => 'decimal:2',
    ];

    // Hubungkan ke tabel pembayaran yang akan kita buat nanti
    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'pesanan_id');
    }

    // Fungsi bantu untuk cek sisa tagihan
    public function getSisaTagihanAttribute()
    {
        $totalBayar = $this->pembayaran()->sum('jumlah_bayar');
        return $this->total_pesanan - $totalBayar;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function details()
    {
        return $this->hasMany(PesananDetail::class, 'pesanan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    //untuk membatasi pengeditan setelah WO dibuat
    public function workOrderDetails()
    {
        return $this->hasMany(WorkOrderDetail::class, 'pesanan_id');
    }

    public function workOrder()
    {
        return $this->hasManyThrough(WorkOrder::class, WorkOrderDetail::class, 'pesanan_id', 'id', 'id', 'work_order_id');
    }

    public function pengirimans()
    {
        return $this->hasMany(Pengiriman::class, 'pesanan_id');
    }

    public function getIsFullyShippedAttribute()
    {
        if ($this->details->isEmpty()) return false;
        foreach ($this->details as $det) {
            if ($det->qty_sisa > 0) {
                return false;
            }
        }
        return true;
    }

    public function getIsPartiallyShippedAttribute()
    {
        $hasShipped = false;
        $hasRemaining = false;
        foreach ($this->details as $det) {
            if ($det->qty_terkirim > 0) {
                $hasShipped = true;
            }
            if ($det->qty_sisa > 0) {
                $hasRemaining = true;
            }
        }
        return $hasShipped && $hasRemaining;
    }

    public function getTotalQtyPesanAttribute()
    {
        return $this->details->sum('qty');
    }

    public function getTotalQtyTerkirimAttribute()
    {
        return $this->details->sum('qty_terkirim');
    }
}
