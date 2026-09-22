<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokGudang extends Model
{
    protected $table = 'stok_gudang';

    public $timestamps = false;

    protected $fillable = [
        'gudang_id',
        'divisi_id',
        'barang_id',
        'jumlah',
    ];

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    /**
     * Hitung stok real-time barang berdasarkan buku pembantu persediaan (transaksi_stok).
     */
    public static function getStokBukuPembantu($barangId, $gudangId = null, $divisiId = null, $date = null): float
    {
        $date = $date ?: date('Y-m-d');
        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        $konversiBarang = $barang ? (float)($barang->konversi_pembelian ?: 1.0) : 1.0;
        if ($konversiBarang <= 0) $konversiBarang = 1.0;

        $rawTxs = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<=', $date . ' 23:59:59');

        if ($gudangId && $divisiId) {
            $rawTxs->where(function ($q) use ($gudangId, $divisiId) {
                $q->where(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                });
            });
        } elseif ($gudangId) {
            $rawTxs->where(function ($q) use ($gudangId) {
                $q->where('gudang_asal_id', $gudangId)
                  ->orWhere('gudang_tujuan_id', $gudangId);
            });
        } elseif ($divisiId) {
            $rawTxs->where(function ($q) use ($divisiId) {
                $q->where('divisi_asal_id', $divisiId)
                  ->orWhere('divisi_tujuan_id', $divisiId);
            });
        }

        $items = $rawTxs->orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();
        $runningQty = 0;

        foreach ($items as $row) {
            $rawQty = floatval($row->qty);
            $qty = $rawQty;

            if (in_array(strtolower($row->source_type ?? ''), ['pembelian', 'penerimaan_pembelian', 'pembelian_batal'])) {
                $pDetail = null;
                $sourceType = strtolower($row->source_type ?? '');
                if ($sourceType === 'pembelian') {
                    $pDetail = \Illuminate\Support\Facades\DB::table('pembelian_detail')
                        ->where('pembelian_id', $row->source_id)
                        ->where('barang_id', $barangId)
                        ->first();
                } elseif ($sourceType === 'penerimaan_pembelian') {
                    $rcv = \Illuminate\Support\Facades\DB::table('penerimaan_pembelian')->where('id', $row->source_id)->first();
                    if ($rcv) {
                        $pDetail = \Illuminate\Support\Facades\DB::table('pembelian_detail')
                            ->where('pembelian_id', $rcv->pembelian_id)
                            ->where('barang_id', $barangId)
                            ->first();
                    }
                }

                $detailKonv = $pDetail ? (float)($pDetail->konversi_pembelian ?? 1) : 1.0;
                $konvRow = $detailKonv > 1 ? $detailKonv : $konversiBarang;
                $qtyInput = $pDetail ? (float)($pDetail->qty ?? 0) : 0.0;

                if ($konvRow > 1 && $qtyInput > 0 && abs($rawQty - $qtyInput) < 0.01) {
                    $qty = $rawQty * $konvRow;
                } elseif ($konversiBarang > 1 && $rawQty > 0 && floatval($row->total_harga) > 0) {
                    $unitPrice = floatval($row->total_harga) / $rawQty;
                    $refPrice = $barang ? (float)($barang->hpp_referensi ?: 0) : 0;
                    if ($refPrice > 0 && $unitPrice > ($refPrice * ($konversiBarang * 0.4))) {
                        $qty = $rawQty * $konversiBarang;
                    }
                }
            }

            $isMasuk = false;
            $isKeluar = false;

            if ($gudangId || $divisiId) {
                $matchTujuan = true;
                $matchAsal   = true;

                if ($gudangId) {
                    $matchTujuan = $matchTujuan && ($row->gudang_tujuan_id == $gudangId);
                    $matchAsal   = $matchAsal   && ($row->gudang_asal_id   == $gudangId);
                }
                if ($divisiId) {
                    $matchTujuan = $matchTujuan && ($row->divisi_tujuan_id == $divisiId);
                    $matchAsal   = $matchAsal   && ($row->divisi_asal_id   == $divisiId);
                } elseif ($gudangId) {
                    $matchTujuan = $matchTujuan && is_null($row->divisi_tujuan_id);
                    $matchAsal   = $matchAsal   && is_null($row->divisi_asal_id);
                }

                if ($matchTujuan && !$matchAsal) {
                    $isMasuk = true;
                } elseif ($matchAsal && !$matchTujuan) {
                    $isKeluar = true;
                }
            } else {
                if ($row->tipe === 'masuk') {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar') {
                    $isKeluar = true;
                }
            }

            if ($isMasuk) {
                $runningQty += $qty;
            } elseif ($isKeluar) {
                $runningQty -= $qty;
            }
        }

        return max(0, $runningQty);
    }

    public static function reconcileStockSummary($barangId = null, $gudangId = null, $divisiId = null)
    {
        $q1 = \Illuminate\Support\Facades\DB::table('stok_gudang')->select('gudang_id', 'divisi_id', 'barang_id');
        $q2 = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')->select('gudang_id', 'divisi_id', 'barang_id');
        $q3 = \Illuminate\Support\Facades\DB::table('transaksi_stok')->whereNotNull('gudang_tujuan_id')->select('gudang_tujuan_id as gudang_id', 'divisi_tujuan_id as divisi_id', 'barang_id');
        $q4 = \Illuminate\Support\Facades\DB::table('transaksi_stok')->whereNotNull('gudang_asal_id')->select('gudang_asal_id as gudang_id', 'divisi_asal_id as divisi_id', 'barang_id');

        if ($barangId) {
            $q1->where('barang_id', $barangId);
            $q2->where('barang_id', $barangId);
            $q3->where('barang_id', $barangId);
            $q4->where('barang_id', $barangId);
        }
        if ($gudangId) {
            $q1->where('gudang_id', $gudangId);
            $q2->where('gudang_id', $gudangId);
            $q3->where('gudang_tujuan_id', $gudangId);
            $q4->where('gudang_asal_id', $gudangId);
        }
        if ($divisiId) {
            $q1->where('divisi_id', $divisiId);
            $q2->where('divisi_id', $divisiId);
            $q3->where('divisi_tujuan_id', $divisiId);
            $q4->where('divisi_asal_id', $divisiId);
        }

        $combos = $q1->union($q2)->union($q3)->union($q4)->get();

        foreach ($combos as $c) {
            $gId = $c->gudang_id;
            $dId = $c->divisi_id;
            $bId = $c->barang_id;

            if (!$gId || !$bId) continue;

            $targetJumlah = self::getStokBukuPembantu($bId, $gId, $dId);

            $sgQuery = \Illuminate\Support\Facades\DB::table('stok_gudang')->where('gudang_id', $gId)->where('barang_id', $bId);
            if ($dId) {
                $sgQuery->where('divisi_id', $dId);
            } else {
                $sgQuery->whereNull('divisi_id');
            }
            $existing = $sgQuery->first();

            if ($existing) {
                if (abs((float)$existing->jumlah - $targetJumlah) > 0.0001) {
                    \Illuminate\Support\Facades\DB::table('stok_gudang')->where('id', $existing->id)->update(['jumlah' => $targetJumlah]);
                }
            } else {
                if ($targetJumlah > 0) {
                    \Illuminate\Support\Facades\DB::table('stok_gudang')->insert([
                        'gudang_id' => $gId,
                        'divisi_id' => $dId,
                        'barang_id' => $bId,
                        'jumlah'    => $targetJumlah,
                    ]);
                }
            }
        }
    }
}