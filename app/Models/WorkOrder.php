<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory;

    protected $table = 'work_order';
    protected $fillable = ['kode_wo', 'tanggal_wo', 'status_wo', 'catatan', 'created_by'];

    public function details()
    {
        return $this->hasMany(WorkOrderDetail::class, 'work_order_id');
    }

    public function isLunas(): bool
    {
        return strtolower($this->status_pembayaran ?? '') === 'lunas';
    }

    public function getStatusPembayaranAttribute(): string
    {
        if (isset($this->attributes['status_pembayaran']) && $this->attributes['status_pembayaran']) {
            return $this->attributes['status_pembayaran'];
        }
        foreach ($this->details as $detail) {
            if ($detail->pesanan && strtolower($detail->pesanan->status_pembayaran ?? '') === 'lunas') {
                return 'lunas';
            }
        }
        $pesananIds = $this->details->pluck('pesanan_id')->filter()->unique();
        if ($pesananIds->isNotEmpty()) {
            $pesananLunas = Pesanan::whereIn('id', $pesananIds)
                ->whereIn('status_pembayaran', ['lunas', 'Lunas'])
                ->exists();
            if ($pesananLunas) {
                return 'lunas';
            }

            $hasColumnStatus = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'status_pembayaran');
            $hasCatatan = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'catatan');
            $hasKeterangan = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'keterangan');

            if ($hasColumnStatus || $hasCatatan || $hasKeterangan) {
                $lunasExists = Produksi::whereIn('pesanan_id', $pesananIds)
                    ->where(function($q) use ($hasColumnStatus, $hasCatatan, $hasKeterangan) {
                        $hasCond = false;
                        if ($hasColumnStatus) {
                            $q->whereIn('status_pembayaran', ['lunas', 'Lunas']);
                            $hasCond = true;
                        }
                        if ($hasCatatan) {
                            if ($hasCond) {
                                $q->orWhere('catatan', 'like', '%"status_pembayaran":"lunas"%');
                            } else {
                                $q->where('catatan', 'like', '%"status_pembayaran":"lunas"%');
                                $hasCond = true;
                            }
                        }
                        if ($hasKeterangan) {
                            if ($hasCond) {
                                $q->orWhere('keterangan', 'like', '%"status_pembayaran":"lunas"%');
                            } else {
                                $q->where('keterangan', 'like', '%"status_pembayaran":"lunas"%');
                                $hasCond = true;
                            }
                        }
                    })->exists();
                if ($lunasExists) {
                    return 'lunas';
                }
            }
        }
        return 'belum_dibayar';
    }
}
