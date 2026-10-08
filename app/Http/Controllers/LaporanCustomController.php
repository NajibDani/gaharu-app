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

        // 1. Produksi dari Central Kitchen yang dialokasikan ke Outlet Kejingga
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
        });

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $query->where(function($q) use ($startDate, $endDate) {
                $q->whereHas('pesanan', function($pq) use ($startDate, $endDate) {
                    $pq->whereBetween('tanggal', [$startDate, $endDate]);
                })
                ->orWhereHas('alokasiPesanan.pesanan', function($pq) use ($startDate, $endDate) {
                    $pq->whereBetween('tanggal', [$startDate, $endDate]);
                })
                ->orWhere(function($sq) use ($startDate, $endDate) {
                    $sq->whereDoesntHave('pesanan', function($pq) {
                        $pq->whereNotNull('tanggal');
                    })
                    ->whereDoesntHave('alokasiPesanan.pesanan', function($pq) {
                        $pq->whereNotNull('tanggal');
                    })
                    ->where(function($dq) use ($startDate, $endDate) {
                        $dq->whereBetween('tanggal_mulai', [$startDate, $endDate])
                          ->orWhere(function($cq) use ($startDate, $endDate) {
                              $cq->whereNull('tanggal_mulai')
                                 ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                          });
                    });
                });
            });
        } elseif ($request->filled('start_date')) {
            $startDate = $request->start_date;
            $query->where(function($q) use ($startDate) {
                $q->whereHas('pesanan', function($pq) use ($startDate) {
                    $pq->whereDate('tanggal', '>=', $startDate);
                })
                ->orWhereHas('alokasiPesanan.pesanan', function($pq) use ($startDate) {
                    $pq->whereDate('tanggal', '>=', $startDate);
                })
                ->orWhere(function($sq) use ($startDate) {
                    $sq->whereDoesntHave('pesanan', function($pq) {
                        $pq->whereNotNull('tanggal');
                    })
                    ->whereDoesntHave('alokasiPesanan.pesanan', function($pq) {
                        $pq->whereNotNull('tanggal');
                    })
                    ->where(function($dq) use ($startDate) {
                        $dq->whereDate('tanggal_mulai', '>=', $startDate)
                          ->orWhere(function($cq) use ($startDate) {
                              $cq->whereNull('tanggal_mulai')
                                 ->whereDate('created_at', '>=', $startDate);
                          });
                    });
                });
            });
        } elseif ($request->filled('end_date')) {
            $endDate = $request->end_date;
            $query->where(function($q) use ($endDate) {
                $q->whereHas('pesanan', function($pq) use ($endDate) {
                    $pq->whereDate('tanggal', '<=', $endDate);
                })
                ->orWhereHas('alokasiPesanan.pesanan', function($pq) use ($endDate) {
                    $pq->whereDate('tanggal', '<=', $endDate);
                })
                ->orWhere(function($sq) use ($endDate) {
                    $sq->whereDoesntHave('pesanan', function($pq) {
                        $pq->whereNotNull('tanggal');
                    })
                    ->whereDoesntHave('alokasiPesanan.pesanan', function($pq) {
                        $pq->whereNotNull('tanggal');
                    })
                    ->where(function($dq) use ($endDate) {
                        $dq->whereDate('tanggal_mulai', '<=', $endDate)
                          ->orWhere(function($cq) use ($endDate) {
                              $cq->whereNull('tanggal_mulai')
                                 ->whereDate('created_at', '<=', $endDate);
                          });
                    });
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
                if (!$hasCond) {
                    $q->where(function($sq) {
                        $sq->whereHas('pesanan', function($pq) {
                            $pq->where('status_pembayaran', '!=', 'Lunas')
                               ->where('status_pembayaran', '!=', 'lunas')
                               ->orWhereNull('status_pembayaran');
                        })->orWhereHas('alokasiPesanan.pesanan', function($pq) {
                            $pq->where('status_pembayaran', '!=', 'Lunas')
                               ->where('status_pembayaran', '!=', 'lunas')
                               ->orWhereNull('status_pembayaran');
                        });
                    });
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
                if (!$hasCond) {
                    $q->where(function($sq) {
                        $sq->whereHas('pesanan', function($pq) {
                            $pq->where('status_pembayaran', 'Lunas')
                               ->orWhere('status_pembayaran', 'lunas');
                        })->orWhereHas('alokasiPesanan.pesanan', function($pq) {
                            $pq->where('status_pembayaran', 'Lunas')
                               ->orWhere('status_pembayaran', 'lunas');
                        });
                    });
                }
            });
        }

        $prodList = $query->latest('tanggal_mulai')->latest('id')->get();

        // 2. Query Pesanan Central Kitchen Kejingga (Termasuk Permintaan Stok BSJ tanpa Batch Produksi Baru)
        $fifoService = app(\App\Services\FifoService::class);
        $pesananQuery = \App\Models\Pesanan::centralKitchen()
            ->with([
                'customer',
                'gudang',
                'divisi',
                'details.produk',
                'creator',
                'workOrderDetails.workOrder'
            ])
            ->where(function($q) {
                $q->whereHas('customer', function($cq) { $cq->where('nama', 'like', '%kejingga%'); })
                  ->orWhereHas('gudang', function($gq) { $gq->where('nama', 'like', '%kejingga%'); });
            })
            ->whereNotIn('id', function($q) {
                $q->select('pesanan_id')->from('alokasi_produksi_pesanan')->whereNotNull('pesanan_id');
            })
            ->whereNotIn('id', function($q) {
                $q->select('pesanan_id')->from('produksi')->whereNotNull('pesanan_id');
            });

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $pesananQuery->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $pesananQuery->whereDate('tanggal', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $pesananQuery->whereDate('tanggal', '<=', $request->end_date);
        }

        if ($statusPembayaran === 'belum_dibayar') {
            $pesananQuery->where(function($q) {
                $q->where('status_pembayaran', '!=', 'Lunas')
                  ->where('status_pembayaran', '!=', 'lunas')
                  ->orWhereNull('status_pembayaran');
            });
        } elseif ($statusPembayaran === 'lunas') {
            $pesananQuery->where(function($q) {
                $q->where('status_pembayaran', 'Lunas')
                  ->orWhere('status_pembayaran', 'lunas');
            });
        }

        $pesananList = $pesananQuery->latest('tanggal')->latest('id')->get();

        $data = collect();

        foreach ($prodList as $p) {
            $p->row_type = 'produksi';
            $p->row_date = $p->pesanan->tanggal ?? ($p->alokasiPesanan->first()->pesanan->tanggal ?? $p->tanggal_mulai);
            $p->details = $p->details->filter(fn($d) => floatval($d->qty ?? ($d->jumlah ?? 0)) > 0)->values();
            $data->push($p);
        }

        foreach ($pesananList as $pes) {
            $woDetail = $pes->workOrderDetails->first();
            $wo = $woDetail ? $woDetail->workOrder : null;
            $kodeWo = $wo ? $wo->kode_wo : $pes->kode_pesanan;
            $validProductIds = ($wo && $wo->details) ? $wo->details->pluck('produk_id')->toArray() : null;

            $activeDetails = $pes->details->filter(function($d) use ($validProductIds) {
                if (floatval($d->qty ?? 0) <= 0) return false;
                if ($validProductIds !== null && !in_array($d->produk_id, $validProductIds)) return false;
                return true;
            })->values();

            foreach ($activeDetails as $d) {
                $needsHppCalc = (!$d->hpp_total || $d->hpp_total <= 0);
                if (!$needsHppCalc && floatval($d->qty ?? 0) > 0 && (floatval($d->hpp_total) / floatval($d->qty)) < 1.0) {
                    $needsHppCalc = true;
                }

                if ($needsHppCalc) {
                    $harga = 0;
                    if ($d->subtotal && $d->subtotal > 0 && $d->qty > 0 && ($d->subtotal / $d->qty) >= 1.0) {
                        $harga = $d->subtotal / $d->qty;
                    } elseif ($d->produk_id) {
                        $harga = $fifoService->getHppResepBsj($d->produk_id);
                        if ($harga <= 0 && $d->produk) {
                            $harga = (float)($d->produk->hpp_referensi ?? 0);
                        }
                    }
                    $d->hpp_total = ($d->subtotal && $d->subtotal > 0 && ($d->subtotal / $d->qty) >= 1.0) ? $d->subtotal : ($d->qty * $harga);
                }
                $d->barang = $d->produk;
            }

            $virtualObj = new \stdClass();
            $virtualObj->row_type = 'pesanan';
            $virtualObj->id = 'pes_' . $pes->id;
            $virtualObj->pesanan_real_id = $pes->id;
            $virtualObj->kode_produksi = $kodeWo;
            $virtualObj->tanggal_mulai = $pes->tanggal;
            $virtualObj->tanggal_selesai = $pes->estimasi_kirim ?? $pes->tanggal;
            $virtualObj->gudangBahan = (object)['nama' => 'Gudang Central Kitchen'];
            $virtualObj->gudangHasil = $pes->customer ?? $pes->gudang ?? (object)['nama' => 'Outlet KeJingga'];
            $virtualObj->divisi = $pes->divisi;
            $virtualObj->status_produksi = 'Stok BSJ (' . ucfirst($pes->status_pesanan ?? 'Diproses') . ')';
            $virtualObj->status_pembayaran = strtolower($pes->status_pembayaran ?? '') === 'lunas' ? 'lunas' : 'belum_dibayar';
            $virtualObj->details = $activeDetails;
            $virtualObj->pesanan = $pes;
            $virtualObj->alokasiPesanan = collect();
            $virtualObj->creator = $pes->creator;
            $virtualObj->dibayarByUser = null;
            $virtualObj->row_date = $pes->tanggal;

            $data->push($virtualObj);
        }

        $data = $data->sortByDesc(function($item) {
            return $item->row_date;
        })->values();

        return view('laporan_custom.pengeluaran_produksi_ck_kejingga', compact('data', 'statusPembayaran'));
    }

    public function prosesBayarProduksiCkKejingga(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'tanggal_pembayaran' => 'required|date',
            'metode_pembayaran' => 'required|string',
            'catatan_pembayaran' => 'nullable|string|max:500',
        ]);

        $ids = $request->ids;
        $noInvoice = 'INV-CK-KEJINGGA-' . date('Ymd-His');
        $hasColumnStatus = \Illuminate\Support\Facades\Schema::hasColumn('produksi', 'status_pembayaran');

        $prodIds = [];
        $pesIds = [];

        foreach ($ids as $idItem) {
            if (str_starts_with($idItem, 'pes_')) {
                $pesIds[] = (int) str_replace('pes_', '', $idItem);
            } elseif (str_starts_with($idItem, 'prod_')) {
                $prodIds[] = (int) str_replace('prod_', '', $idItem);
            } elseif (is_numeric($idItem)) {
                if (Produksi::where('id', $idItem)->exists()) {
                    $prodIds[] = (int) $idItem;
                } else {
                    $pesIds[] = (int) $idItem;
                }
            }
        }

        $updatedCount = 0;

        if (!empty($prodIds)) {
            $transactions = Produksi::with(['pesanan', 'alokasiPesanan.pesanan'])->whereIn('id', $prodIds)->get();

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
        }

        if (!empty($pesIds)) {
            $fifoService = app(\App\Services\FifoService::class);
            $pesanans = \App\Models\Pesanan::with('details.produk')->whereIn('id', $pesIds)->get();

            foreach ($pesanans as $p) {
                $p->update(['status_pembayaran' => 'Lunas']);
                $hasPayment = \App\Models\Pembayaran::where('pesanan_id', $p->id)->exists();
                if (!$hasPayment) {
                    $calcNominal = 0;
                    foreach ($p->details as $d) {
                        if ($d->subtotal && $d->subtotal > 0) {
                            $calcNominal += (float)$d->subtotal;
                        } else {
                            $h = $fifoService->getHppResepBsj($d->produk_id);
                            if ($h <= 0 && $d->produk) $h = (float)($d->produk->hpp_referensi ?? 0);
                            $calcNominal += ($d->qty * $h);
                        }
                    }
                    $nominal = $p->total_pesanan > 0 ? (float)$p->total_pesanan : $calcNominal;

                    \App\Models\Pembayaran::create([
                        'pesanan_id'          => $p->id,
                        'kategori_pembayaran' => 'penjualan',
                        'tanggal_bayar'       => $request->tanggal_pembayaran,
                        'jumlah_bayar'        => $nominal > 0 ? $nominal : 0,
                        'metode_pembayaran'   => $request->metode_pembayaran,
                        'catatan'             => $request->catatan_pembayaran ? ($request->catatan_pembayaran . ' (Pelunasan via Laporan Produksi CK)') : 'Pelunasan via Laporan Produksi CK',
                        'created_by'          => auth()->id() ?? 1,
                    ]);
                }
                $updatedCount++;
            }
        }

        return redirect()->back()->with('success', "Pembayaran untuk {$updatedCount} produksi & pesanan terkait berhasil diproses (Status: Lunas). Invoice dapat langsung dicetak.");
    }

    public function cetakInvoiceProduksiCkKejingga(Request $request)
    {
        $idsParam = $request->input('ids');
        $noInvoice = $request->input('no_invoice');

        $rawIds = [];
        if (is_array($idsParam)) {
            $rawIds = $idsParam;
        } elseif (is_string($idsParam) && !empty($idsParam)) {
            $rawIds = explode(',', $idsParam);
        }

        $prodIds = [];
        $pesIds = [];

        foreach ($rawIds as $idItem) {
            $idItem = trim($idItem);
            if (str_starts_with($idItem, 'pes_')) {
                $pesIds[] = (int) str_replace('pes_', '', $idItem);
            } elseif (str_starts_with($idItem, 'prod_')) {
                $prodIds[] = (int) str_replace('prod_', '', $idItem);
            } elseif (is_numeric($idItem)) {
                if (Produksi::where('id', $idItem)->exists()) {
                    $prodIds[] = (int) $idItem;
                } else {
                    $pesIds[] = (int) $idItem;
                }
            }
        }

        $transactions = collect();
        $fifoService = app(\App\Services\FifoService::class);
        $hppRecalculated = false;

        if ($request->has('refresh_hpp') || $request->has('recalculate')) {
            \App\Services\FifoService::clearHargaCache();
            $hppRecalculated = true;

            if (!empty($prodIds)) {
                $prodController = app(\App\Http\Controllers\CentralKitchenProductionController::class);
                foreach ($prodIds as $pId) {
                    $pObj = Produksi::with(['pesanan', 'alokasiPesanan.pesanan'])->find($pId);
                    if ($pObj && !$pObj->isLunas()) {
                        $prodController->recalculateHpp($pId);
                    }
                }
            }
        }

        if (!empty($prodIds)) {
            $prodTx = Produksi::with([
                'details.barang',
                'details.produk',
                'gudangBahan',
                'gudangHasil',
                'divisi',
                'pesanan.customer',
                'alokasiPesanan.pesanan.customer',
                'creator',
                'dibayarByUser'
            ])->whereIn('id', $prodIds)->get();

            foreach ($prodTx as $pt) {
                $pt->details = $pt->details->filter(fn($d) => floatval($d->qty ?? ($d->jumlah ?? 0)) > 0)->values();
                $transactions->push($pt);
            }
        }

        if (!empty($pesIds)) {
            $pesTx = \App\Models\Pesanan::with([
                'customer',
                'gudang',
                'divisi',
                'details.produk',
                'creator',
                'workOrderDetails.workOrder'
            ])->whereIn('id', $pesIds)->get();

            foreach ($pesTx as $pes) {
                $woDetail = $pes->workOrderDetails->first();
                $wo = $woDetail ? $woDetail->workOrder : null;
                $kodeWo = $wo ? $wo->kode_wo : $pes->kode_pesanan;
                $validProductIds = ($wo && $wo->details) ? $wo->details->pluck('produk_id')->toArray() : null;

                $activeDetails = $pes->details->filter(function($d) use ($validProductIds) {
                    if (floatval($d->qty ?? 0) <= 0) return false;
                    if ($validProductIds !== null && !in_array($d->produk_id, $validProductIds)) return false;
                    return true;
                })->values();

                foreach ($activeDetails as $d) {
                    $needsHppCalc = $hppRecalculated || (!$d->hpp_total || $d->hpp_total <= 0);
                    if (!$needsHppCalc && floatval($d->qty ?? 0) > 0 && (floatval($d->hpp_total) / floatval($d->qty)) < 1.0) {
                        $needsHppCalc = true;
                    }

                    if ($needsHppCalc) {
                        $harga = 0;
                        if (!$hppRecalculated && $d->subtotal && $d->subtotal > 0 && $d->qty > 0 && ($d->subtotal / $d->qty) >= 1.0) {
                            $harga = $d->subtotal / $d->qty;
                        } elseif ($d->produk_id) {
                            $harga = $fifoService->getHppResepBsj($d->produk_id);
                            if ($harga <= 0 && $d->produk) {
                                $harga = (float)($d->produk->hpp_referensi ?? 0);
                            }
                        }
                        $d->hpp_total = ($d->qty * $harga);
                    }
                    $d->barang = $d->produk;
                }

                $virtualObj = new \stdClass();
                $virtualObj->id = 'pes_' . $pes->id;
                $virtualObj->kode_produksi = $kodeWo;
                $virtualObj->tanggal_mulai = $pes->tanggal;
                $virtualObj->tanggal_selesai = $pes->estimasi_kirim ?? $pes->tanggal;
                $virtualObj->gudangBahan = (object)['nama' => 'Gudang Central Kitchen'];
                $virtualObj->gudangHasil = $pes->customer ?? $pes->gudang ?? (object)['nama' => 'Outlet KeJingga'];
                $virtualObj->divisi = $pes->divisi;
                $virtualObj->status_produksi = 'Stok BSJ (' . ucfirst($pes->status_pesanan ?? 'Diproses') . ')';
                $virtualObj->status_pembayaran = strtolower($pes->status_pembayaran ?? '') === 'lunas' ? 'lunas' : 'belum_dibayar';
                $virtualObj->details = $activeDetails;
                $virtualObj->pesanan = $pes;
                $virtualObj->alokasiPesanan = collect();
                $virtualObj->creator = $pes->creator;
                $virtualObj->dibayarByUser = null;
                $virtualObj->no_invoice = 'INV-CK-KEJINGGA-' . date('YmdHis');
                $virtualObj->tanggal_pembayaran = null;
                $virtualObj->metode_pembayaran = null;
                $virtualObj->catatan_pembayaran = null;

                $transactions->push($virtualObj);
            }
        }

        if ($transactions->isEmpty()) {
            return redirect()->back()->with('error', 'Data produksi/permintaan tidak ditemukan.');
        }

        $isPdf = $request->has('pdf');

        if ($isPdf) {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
            $pdf->loadView('laporan_custom.invoice_produksi_ck_kejingga', compact('transactions', 'isPdf', 'hppRecalculated'));
            return $pdf->stream('Invoice-Produksi-CK-Kejingga-' . date('YmdHis') . '.pdf');
        }

        return view('laporan_custom.invoice_produksi_ck_kejingga', compact('transactions', 'isPdf', 'hppRecalculated'));
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
