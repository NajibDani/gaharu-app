<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PengeluaranBahanBaku;
use App\Models\Produksi;
use App\Models\Pembelian;
use App\Models\MasterGudang;
use App\Models\Supplier;
use App\Models\GudangDivisi;
use Illuminate\Support\Facades\DB;

class LaporanCustomController extends Controller
{
    // 1. Pengeluaran bahan baku dari gudang utama ke kejingga
    public function pengeluaranBahanBakuGudangUtamaKejingga(Request $request)
    {
        // Pengeluaran/Permintaan transfer bahan baku dari Gudang Utama ke Kejingga
        // Pada tabel pengeluaran_bahan_baku, kolom gudang_id dan divisi_id menyimpan GUDANG & DIVISI TUJUAN transfer
        // Permintaan bahan baku antar gudang menggunakan prefix PBK- (transfer), bukan otomatisasi penjualan kasir/POS (OUT-MOKA/AUTO_POS)
        $query = PengeluaranBahanBaku::with(['details.barang', 'gudang', 'divisi', 'dibayarByUser'])
            ->where(function ($q) {
                $q->whereHas('gudang', function ($gq) {
                    $gq->where('nama', 'like', '%kejingga%');
                })
                ->orWhereHas('divisi', function ($dq) {
                    $dq->where('nama', 'like', '%kejingga%')
                       ->orWhereHas('gudang', function ($dgq) {
                           $dgq->where('nama', 'like', '%kejingga%');
                       });
                })
                ->orWhere('keterangan', 'like', '%kejingga%')
                ->orWhere('jenis_pengeluaran', 'like', '%kejingga%');
            })
            ->where(function($q) {
                $q->whereNull('keterangan')->orWhere(function($k) {
                    $k->where('keterangan', 'not like', '%opname%')
                      ->where('keterangan', 'not like', '%AUTO_POS%')
                      ->where('keterangan', 'not like', '%MOKA%');
                });
            })
            ->where('kode_pengeluaran', 'like', 'PBK-%')
            ->where('kode_pengeluaran', 'not like', '%SO%')
            ->where('kode_pengeluaran', 'not like', '%WST%')
            ->where(function($q) {
                $q->whereNull('jenis_pengeluaran')
                  ->orWhere('jenis_pengeluaran', 'transfer')
                  ->orWhere('jenis_pengeluaran', 'like', '%kejingga%');
            });

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('tanggal', '>=', $request->start_date)
                  ->whereDate('tanggal', '<=', $request->end_date);
        }

        $statusPembayaran = $request->query('status_pembayaran', 'semua');
        $hasColumnStatus = \Illuminate\Support\Facades\Schema::hasColumn('pengeluaran_bahan_baku', 'status_pembayaran');

        if ($statusPembayaran === 'belum_dibayar') {
            $query->where(function($q) use ($hasColumnStatus) {
                if ($hasColumnStatus) {
                    $q->where('status_pembayaran', 'belum_dibayar')
                      ->orWhereNull('status_pembayaran');
                }
                $q->orWhereNull('keterangan')
                  ->orWhere('keterangan', 'not like', '%"status_pembayaran":"lunas"%');
            });
        } elseif ($statusPembayaran === 'lunas') {
            $query->where(function($q) use ($hasColumnStatus) {
                if ($hasColumnStatus) {
                    $q->where('status_pembayaran', 'lunas');
                }
                $q->orWhere('keterangan', 'like', '%"status_pembayaran":"lunas"%');
            });
        }

        $data = $query->latest('tanggal')->latest('id')->get();

        return view('laporan_custom.pengeluaran_bahan_baku_kejingga', compact('data', 'statusPembayaran'));
    }

    // 1b. Proses Pembayaran PBK Kejingga
    public function prosesBayarPbkKejingga(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:pengeluaran_bahan_baku,id',
            'tanggal_pembayaran' => 'required|date',
            'metode_pembayaran' => 'required|string',
            'catatan_pembayaran' => 'nullable|string|max:500',
        ]);

        $ids = $request->ids;
        $noInvoice = 'INV-KEJINGGA-' . date('Ymd-His');
        $hasColumnStatus = \Illuminate\Support\Facades\Schema::hasColumn('pengeluaran_bahan_baku', 'status_pembayaran');

        $transactions = PengeluaranBahanBaku::whereIn('id', $ids)->get();
        $updatedCount = 0;

        foreach ($transactions as $pbk) {
            if ($hasColumnStatus) {
                $pbk->update([
                    'status_pembayaran'  => 'lunas',
                    'tanggal_pembayaran' => $request->tanggal_pembayaran . ' ' . date('H:i:s'),
                    'metode_pembayaran'  => $request->metode_pembayaran,
                    'catatan_pembayaran' => $request->catatan_pembayaran,
                    'no_invoice'         => DB::raw("COALESCE(no_invoice, '{$noInvoice}')"),
                    'dibayar_by'         => auth()->id() ?? 1,
                ]);
            } else {
                $existingInv = $pbk->no_invoice;
                $pbk->updatePaymentMeta([
                    'status_pembayaran'  => 'lunas',
                    'tanggal_pembayaran' => $request->tanggal_pembayaran . ' ' . date('H:i:s'),
                    'metode_pembayaran'  => $request->metode_pembayaran,
                    'catatan_pembayaran' => $request->catatan_pembayaran,
                    'no_invoice'         => $existingInv ?: $noInvoice,
                    'dibayar_by'         => auth()->id() ?? 1,
                ]);
            }
            $updatedCount++;
        }

        return redirect()->back()->with('success', "Pembayaran untuk {$updatedCount} transaksi pengeluaran berhasil diproses (Status: Lunas). Invoice dapat langsung dicetak.");
    }

    // 1c. Cetak Invoice PBK Kejingga
    public function cetakInvoicePbkKejingga(Request $request)
    {
        $idsParam = $request->input('ids');
        $noInvoice = $request->input('no_invoice');

        $ids = [];
        if (is_array($idsParam)) {
            $ids = array_map('intval', $idsParam);
        } elseif (is_string($idsParam) && !empty($idsParam)) {
            $ids = array_map('intval', explode(',', $idsParam));
        }

        $query = PengeluaranBahanBaku::with(['details.barang', 'gudang', 'divisi', 'creator', 'dibayarByUser']);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (!empty($noInvoice)) {
            $hasColNoInvoice = \Illuminate\Support\Facades\Schema::hasColumn('pengeluaran_bahan_baku', 'no_invoice');
            $query->where(function($q) use ($noInvoice, $hasColNoInvoice) {
                if ($hasColNoInvoice) {
                    $q->where('no_invoice', $noInvoice);
                }
                $q->orWhere('keterangan', 'like', '%"no_invoice":"' . $noInvoice . '"%');
            });
        } else {
            return redirect()->back()->with('error', 'Pilih minimal 1 transaksi pengeluaran untuk mencetak invoice.');
        }

        $transactions = $query->orderBy('tanggal', 'asc')->get();

        if ($transactions->isEmpty()) {
            return redirect()->back()->with('error', 'Data pengeluaran tidak ditemukan.');
        }

        $isPdf = $request->has('pdf');

        if ($isPdf) {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
            $pdf->loadView('laporan_custom.invoice_pbk_kejingga', compact('transactions', 'isPdf'));
            return $pdf->stream('Invoice-Pembayaran-Kejingga-' . date('YmdHis') . '.pdf');
        }

        return view('laporan_custom.invoice_pbk_kejingga', compact('transactions', 'isPdf'));
    }

    // 2. Pengeluaran produksi central kitchen ke jingga
    public function pengeluaranProduksiCentralKitchenKejingga(Request $request)
    {
        $hasColKeterangan = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'keterangan');
        $hasColCatatan = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'catatan');
        $hasColCatatanBayar = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'catatan_pembayaran');

        // Produksi dari Central Kitchen yang dialokasikan ke Outlet Kejingga
        $query = Produksi::with([
            'details.barang',
            'details.produk',
            'gudangBahan',
            'gudangHasil',
            'divisi',
            'pesanan.customer',
            'alokasiPesanan.pesanan.customer',
            'creator',
            'dibayarByUser'
        ])
        ->where(function($q) use ($hasColKeterangan, $hasColCatatan, $hasColCatatanBayar) {
            $q->whereHas('pesanan.customer', function($cq) {
                $cq->where('nama', 'like', '%kejingga%');
            })
            ->orWhereHas('alokasiPesanan.pesanan.customer', function($cq) {
                $cq->where('nama', 'like', '%kejingga%');
            })
            ->orWhereHas('gudangHasil', function($gq) {
                $gq->where('nama', 'like', '%kejingga%');
            })
            ->orWhereHas('divisi', function($dq) {
                $dq->where('nama', 'like', '%kejingga%');
            })
            ->orWhereHas('pesanan.gudang', function($gq) {
                $gq->where('nama', 'like', '%kejingga%');
            })
            ->orWhereHas('alokasiPesanan.pesanan.gudang', function($gq) {
                $gq->where('nama', 'like', '%kejingga%');
            })
            ->orWhere('kode_produksi', 'like', '%kejingga%');

            if ($hasColKeterangan) {
                $q->orWhere('keterangan', 'like', '%kejingga%');
            }
            if ($hasColCatatan) {
                $q->orWhere('catatan', 'like', '%kejingga%');
            }
            if ($hasColCatatanBayar) {
                $q->orWhere('catatan_pembayaran', 'like', '%kejingga%');
            }
        })
        ->where('kode_produksi', 'not like', '%SO%');

        if ($request->filled('start_date')) {
            $query->where(function($q) use ($request) {
                $q->whereDate('tanggal_mulai', '>=', $request->start_date)
                  ->orWhereDate('tanggal_selesai', '>=', $request->start_date)
                  ->orWhereDate('created_at', '>=', $request->start_date)
                  ->orWhereHas('pesanan', function($pq) use ($request) {
                      $pq->whereDate('tanggal', '>=', $request->start_date)
                         ->orWhereDate('estimasi_kirim', '>=', $request->start_date);
                  })
                  ->orWhereHas('alokasiPesanan.pesanan', function($pq) use ($request) {
                      $pq->whereDate('tanggal', '>=', $request->start_date)
                         ->orWhereDate('estimasi_kirim', '>=', $request->start_date);
                  });
            });
        }

        if ($request->filled('end_date')) {
            $query->where(function($q) use ($request) {
                $q->whereDate('tanggal_mulai', '<=', $request->end_date)
                  ->orWhereDate('tanggal_selesai', '<=', $request->end_date)
                  ->orWhereDate('created_at', '<=', $request->end_date)
                  ->orWhereHas('pesanan', function($pq) use ($request) {
                      $pq->whereDate('tanggal', '<=', $request->end_date)
                         ->orWhereDate('estimasi_kirim', '<=', $request->end_date);
                  })
                  ->orWhereHas('alokasiPesanan.pesanan', function($pq) use ($request) {
                      $pq->whereDate('tanggal', '<=', $request->end_date)
                         ->orWhereDate('estimasi_kirim', '<=', $request->end_date);
                  });
            });
        }

        $statusPembayaran = $request->query('status_pembayaran', 'semua');
        $hasColumnStatus = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'status_pembayaran');

        if ($statusPembayaran === 'belum_dibayar') {
            $query->where(function($q) use ($hasColumnStatus, $hasColKeterangan, $hasColCatatan) {
                $hasCond = false;
                if ($hasColumnStatus) {
                    $q->where(function($sq) {
                        $sq->where('status_pembayaran', 'belum_dibayar')
                          ->orWhere('status_pembayaran', 'Belum Bayar')
                          ->orWhereNull('status_pembayaran');
                    });
                    $hasCond = true;
                }
                if ($hasColKeterangan) {
                    if ($hasCond) {
                        $q->orWhereNull('keterangan')->orWhere('keterangan', 'not like', '%"status_pembayaran":"lunas"%');
                    } else {
                        $q->where(function($sq) {
                            $sq->whereNull('keterangan')->orWhere('keterangan', 'not like', '%"status_pembayaran":"lunas"%');
                        });
                        $hasCond = true;
                    }
                }
                if ($hasColCatatan) {
                    if ($hasCond) {
                        $q->orWhereNull('catatan')->orWhere('catatan', 'not like', '%"status_pembayaran":"lunas"%');
                    } else {
                        $q->where(function($sq) {
                            $sq->whereNull('catatan')->orWhere('catatan', 'not like', '%"status_pembayaran":"lunas"%');
                        });
                        $hasCond = true;
                    }
                }
            });
        } elseif ($statusPembayaran === 'lunas') {
            $query->where(function($q) use ($hasColumnStatus, $hasColKeterangan, $hasColCatatan) {
                $hasCond = false;
                if ($hasColumnStatus) {
                    $q->where(function($sq) {
                        $sq->where('status_pembayaran', 'lunas')
                          ->orWhere('status_pembayaran', 'Lunas');
                    });
                    $hasCond = true;
                }
                if ($hasColKeterangan) {
                    if ($hasCond) {
                        $q->orWhere('keterangan', 'like', '%"status_pembayaran":"lunas"%');
                    } else {
                        $q->where('keterangan', 'like', '%"status_pembayaran":"lunas"%');
                        $hasCond = true;
                    }
                }
                if ($hasColCatatan) {
                    if ($hasCond) {
                        $q->orWhere('catatan', 'like', '%"status_pembayaran":"lunas"%');
                    } else {
                        $q->where('catatan', 'like', '%"status_pembayaran":"lunas"%');
                        $hasCond = true;
                    }
                }
            });
        }

        $data = $query->latest('tanggal_mulai')->latest('id')->get();

        return view('laporan_custom.pengeluaran_produksi_ck_kejingga', compact('data', 'statusPembayaran'));
    }

    public function prosesBayarProduksiCkKejingga(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:produksi,id',
            'tanggal_pembayaran' => 'required|date',
            'metode_pembayaran' => 'required|string',
            'catatan_pembayaran' => 'nullable|string|max:500',
        ]);

        $ids = $request->ids;
        $noInvoice = 'INV-CK-KEJINGGA-' . date('Ymd-His');
        $hasColumnStatus = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'status_pembayaran');

        $transactions = Produksi::with(['pesanan', 'alokasiPesanan.pesanan'])->whereIn('id', $ids)->get();
        $updatedCount = 0;

        foreach ($transactions as $prod) {
            $tgl = $request->tanggal_pembayaran . ' ' . date('H:i:s');
            $metode = $request->metode_pembayaran;
            $catatan = $request->catatan_pembayaran;

            if ($hasColumnStatus) {
                $prod->update([
                    'status_pembayaran'  => 'lunas',
                    'tanggal_pembayaran' => $tgl,
                    'metode_pembayaran'  => $metode,
                    'catatan_pembayaran' => $catatan,
                    'no_invoice'         => DB::raw("COALESCE(no_invoice, '{$noInvoice}')"),
                    'dibayar_by'         => auth()->id() ?? 1,
                ]);
            } else {
                $existingInv = $prod->no_invoice;
                $prod->updatePaymentMeta([
                    'status_pembayaran'  => 'lunas',
                    'tanggal_pembayaran' => $tgl,
                    'metode_pembayaran'  => $metode,
                    'catatan_pembayaran' => $catatan,
                    'no_invoice'         => $existingInv ?: $noInvoice,
                    'dibayar_by'         => auth()->id() ?? 1,
                ]);
            }

            // Synchronize Lunas status to associated Pesanan records to prevent double payment
            $pesananIds = collect([$prod->pesanan_id]);
            if ($prod->alokasiPesanan) {
                $pesananIds = $pesananIds->merge($prod->alokasiPesanan->pluck('pesanan_id'));
            }
            $pesananIds = $pesananIds->filter()->unique();

            foreach ($pesananIds as $pId) {
                $p = \App\Models\Pesanan::with('details')->find($pId);
                if ($p) {
                    $p->update(['status_pembayaran' => 'Lunas']);
                    $hasPayment = \App\Models\Pembayaran::where('pesanan_id', $p->id)->exists();
                    if (!$hasPayment) {
                        $nominal = $p->total_pesanan > 0 ? (float)$p->total_pesanan : (float)$p->details->sum('subtotal');
                        \App\Models\Pembayaran::create([
                            'pesanan_id'          => $p->id,
                            'kategori_pembayaran' => 'penjualan',
                            'tanggal_bayar'       => $request->tanggal_pembayaran,
                            'jumlah_bayar'        => $nominal > 0 ? $nominal : 0,
                            'metode_pembayaran'   => $metode,
                            'catatan'             => $catatan ? ($catatan . ' (Pelunasan via Laporan Produksi CK)') : 'Pelunasan via Laporan Produksi CK',
                            'created_by'          => auth()->id() ?? 1,
                        ]);
                    }
                }
            }

            $updatedCount++;
        }

        return redirect()->back()->with('success', "Pembayaran untuk {$updatedCount} produksi & pesanan terkait berhasil diproses (Status: Lunas). Invoice dapat langsung dicetak.");
    }

    public function cetakInvoiceProduksiCkKejingga(Request $request)
    {
        $idsParam = $request->input('ids');
        $noInvoice = $request->input('no_invoice');

        $ids = [];
        if (is_array($idsParam)) {
            $ids = array_map('intval', $idsParam);
        } elseif (is_string($idsParam) && !empty($idsParam)) {
            $ids = array_map('intval', explode(',', $idsParam));
        }

        $query = Produksi::with([
            'details.barang',
            'details.produk',
            'gudangBahan',
            'gudangHasil',
            'divisi',
            'pesanan.customer',
            'alokasiPesanan.pesanan.customer',
            'creator',
            'dibayarByUser'
        ]);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (!empty($noInvoice)) {
            $hasColNoInvoice = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'no_invoice');
            $hasColKeterangan = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'keterangan');
            $hasColCatatan = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'catatan');

            $query->where(function($q) use ($noInvoice, $hasColNoInvoice, $hasColKeterangan, $hasColCatatan) {
                if ($hasColNoInvoice) {
                    $q->where('no_invoice', $noInvoice);
                }
                if ($hasColKeterangan) {
                    $q->orWhere('keterangan', 'like', '%"no_invoice":"' . $noInvoice . '"%');
                }
                if ($hasColCatatan) {
                    $q->orWhere('catatan', 'like', '%"no_invoice":"' . $noInvoice . '"%');
                }
            });
        } else {
            return redirect()->back()->with('error', 'Pilih minimal 1 transaksi produksi untuk mencetak invoice.');
        }

        $transactions = $query->orderBy('tanggal_mulai', 'asc')->get();

        if ($transactions->isEmpty()) {
            return redirect()->back()->with('error', 'Data produksi tidak ditemukan.');
        }

        $isPdf = $request->has('pdf');

        if ($isPdf) {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
            $pdf->loadView('laporan_custom.invoice_produksi_ck_kejingga', compact('transactions', 'isPdf'));
            return $pdf->stream('Invoice-Produksi-CK-Kejingga-' . date('YmdHis') . '.pdf');
        }

        return view('laporan_custom.invoice_produksi_ck_kejingga', compact('transactions', 'isPdf'));
    }

    // 3. Pengeluaran produksi cold kitchen kejingga
    public function pengeluaranProduksiColdKitchenKejingga(Request $request)
    {
        // Asumsi: Produksi dari gudang/divisi Cold Kitchen ke Kejingga
        $query = Produksi::with(['details.barang', 'gudangBahan', 'gudangHasil', 'divisi'])
            ->whereHas('gudangBahan', function ($q) {
                $q->where('nama', 'like', '%Cold Kitchen%');
            })
            ->where(function ($q) {
                $q->whereHas('gudangHasil', function ($q2) {
                    $q2->where('nama', 'like', '%Kejingga%');
                })->orWhereHas('divisi', function ($q3) {
                    $q3->where('nama', 'like', '%Kejingga%');
                });
            })
            ->where('kode_produksi', 'not like', '%SO%');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal_mulai', [$request->start_date, $request->end_date]);
        }

        $data = $query->latest('tanggal_mulai')->get();

        return view('laporan_custom.pengeluaran_produksi_cold_kejingga', compact('data'));
    }

    // 4. Pembelian bahan baku gudang utama dikurangi permintaan ke central kitchen dan kejingga
    public function pembelianGudangUtamaDikurangiPermintaan(Request $request)
    {
        $jenisSupplier = $request->input('jenis_supplier', 'nota_pasar'); // nota_pasar atau selain_nota_pasar
        
        $pembelianQuery = Pembelian::with(['details.barang', 'supplier'])
            ->whereHas('gudang', function ($q) {
                $q->where('nama', 'like', '%Gudang Utama%');
            })
            ->where(function($q) {
                $q->whereNull('keterangan')->orWhere('keterangan', 'not like', '%opname%');
            })
            ->where('kode_pembelian', 'not like', '%SO%');

        if ($jenisSupplier == 'nota_pasar') {
            $pembelianQuery->whereHas('supplier', function ($q) {
                $q->where('nama', 'like', '%Nota Pasar%');
            });
        } else {
            $pembelianQuery->whereHas('supplier', function ($q) {
                $q->where('nama', 'not like', '%Nota Pasar%');
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $pembelianQuery->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        }

        $pembelianData = $pembelianQuery->get();

        // Calculate totals logic here if needed, passing to view
        return view('laporan_custom.pembelian_gudang_utama_minus_permintaan', compact('pembelianData', 'jenisSupplier'));
    }

    // 5. Total permintaan bahan baku dengan filter tiap divisi
    public function totalPermintaanBahanBakuDivisi(Request $request)
    {
        $divisi_id = $request->input('divisi_id');
        
        $query = PengeluaranBahanBaku::with(['details.barang', 'divisi'])
            ->where(function($q) {
                $q->whereNull('keterangan')->orWhere('keterangan', 'not like', '%opname%');
            })
            ->where('kode_pengeluaran', 'not like', '%SO%');
        
        if ($divisi_id) {
            $query->where('divisi_id', $divisi_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        }

        $data = $query->latest('tanggal')->get();
        $divisis = GudangDivisi::all();

        return view('laporan_custom.total_permintaan_divisi', compact('data', 'divisis', 'divisi_id'));
    }
}
