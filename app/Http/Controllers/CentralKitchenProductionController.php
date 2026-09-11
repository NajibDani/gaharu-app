<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use App\Models\Produksi;
use App\Models\ProduksiDetail;
use App\Models\ProduksiPesanan;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\ResepBahanBaku;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;
use App\Models\Pengiriman;
use Illuminate\Support\Facades\DB;

class CentralKitchenProductionController extends Controller
{
    /**
     * Dashboard & Riwayat Produksi Central Kitchen
     */
    public function index(Request $request)
    {
        MasterBarang::syncAllResepIds();

        $search = $request->query('search');
        $customerId = $request->query('customer_id');

        $isSuperAdmin = auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->username === 'superadmin');

        // Filter WO yang berasal dari pesanan Central Kitchen
        $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
        $gudangCkId = $gudangCk ? $gudangCk->id : 5;

        $queryWo = WorkOrder::with(['details.pesanan.customer', 'details.produk.resep.bahan'])
            ->whereHas('details.pesanan', function($q) use ($customerId) {
                $q->where('tipe_pesanan', 'central_kitchen');
                if ($customerId) {
                    $q->where('customer_id', $customerId);
                }
            });

        if ($search) {
            $queryWo->where('kode_wo', 'like', '%' . $search . '%');
        }

        $woList = $queryWo->latest()->paginate(10, ['*'], 'wo_page')->withQueryString();

        // Hitung progress produksi, sisa kekurangan, dan ketersediaan bahan baku di Gudang CK
        $woList->getCollection()->transform(function($wo) use ($gudangCkId) {
            $firstDetail = $wo->details->first();
            $customer = $firstDetail && $firstDetail->pesanan ? $firstDetail->pesanan->customer : null;
            $wo->customer_nama = $customer ? $customer->nama : 'Outlet Internal';
            $wo->pesanan_kode  = $firstDetail && $firstDetail->pesanan ? $firstDetail->pesanan->kode_pesanan : '-';

            $totalTarget = 0;
            $totalSelesai = 0;
            $totalSisa = 0;

            $itemsProgress = [];
            $agregatKebutuhan = [];
            $produkTanpaResep = [];
            $hasMissingResep = false;
            $rekapBahanMap = [];

            foreach ($wo->details as $wod) {
                $target = floatval($wod->qty_rencana);
                $sudah = DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $wod->pesanan_id)
                    ->where('produk_id', $wod->produk_id)
                    ->sum('qty_alokasi') ?? 0;
                $sisa = max(0, $target - floatval($sudah));

                $totalTarget += $target;
                $totalSelesai += floatval($sudah);
                $totalSisa += $sisa;

                // Cek apakah produk memiliki resep dengan bahan baku
                $hasResep = ($wod->produk && $wod->produk->resep && $wod->produk->resep->count() > 0);
                if (!$hasResep && $sisa > 0) {
                    $hasMissingResep = true;
                    $produkTanpaResep[] = [
                        'produk_id'   => $wod->produk_id,
                        'nama_produk' => $wod->produk->nama ?? 'Produk #' . $wod->produk_id,
                        'kode_barang' => $wod->produk->kode_barang ?? '-',
                    ];
                }

                $satuanKonversi = $wod->produk && $wod->produk->satuan_pembelian ? strtoupper($wod->produk->satuan_pembelian) : '';
                $konversiVal = floatval($wod->produk->konversi_pembelian ?? 1);

                $itemsProgress[] = [
                    'produk_id'        => $wod->produk_id,
                    'kode_barang'      => $wod->produk->kode_barang ?? 'N/A',
                    'nama_produk'      => $wod->produk->nama ?? 'N/A',
                    'satuan'           => $wod->produk->satuan ?? 'pcs',
                    'satuan_pembelian' => $satuanKonversi,
                    'konversi'         => $konversiVal,
                    'target'           => $target,
                    'sudah'            => floatval($sudah),
                    'sisa'             => $sisa,
                    'has_resep'        => $hasResep,
                ];

                // Hitung kebutuhan bahan baku (untuk total target WO dan sisa target)
                if ($hasResep) {
                    foreach ($wod->produk->resep as $resep) {
                        $bahanId = $resep->bahan_id;
                        $qtyPerUnit = floatval($resep->qty_bahan);
                        $butuhTarget = $qtyPerUnit * $target;
                        $butuhSisa = $qtyPerUnit * $sisa;

                        if (!isset($rekapBahanMap[$bahanId])) {
                            $rekapBahanMap[$bahanId] = [
                                'bahan_id'    => $bahanId,
                                'kode_barang' => $resep->bahan->kode_barang ?? '-',
                                'nama_bahan'  => $resep->bahan->nama ?? ('Bahan #' . $bahanId),
                                'satuan'      => $resep->bahan->satuan ?? ($resep->satuan ?? 'pcs'),
                                'total_butuh' => 0,
                                'sisa_butuh'  => 0,
                                'breakdown'   => [],
                            ];
                        }

                        $rekapBahanMap[$bahanId]['total_butuh'] += $butuhTarget;
                        $rekapBahanMap[$bahanId]['sisa_butuh'] += $butuhSisa;

                        $rekapBahanMap[$bahanId]['breakdown'][] = [
                            'nama_produk'    => $wod->produk->nama ?? ('Produk #' . $wod->produk_id),
                            'kode_produk'    => $wod->produk->kode_barang ?? '-',
                            'target_produk'  => $target,
                            'sisa_produk'    => $sisa,
                            'satuan_produk'  => $wod->produk->satuan ?? 'pcs',
                            'qty_per_unit'   => $qtyPerUnit,
                            'subtotal_total' => $butuhTarget,
                            'subtotal_sisa'  => $butuhSisa,
                        ];

                        if ($sisa > 0) {
                            if (!isset($agregatKebutuhan[$bahanId])) {
                                $agregatKebutuhan[$bahanId] = [
                                    'nama'   => $resep->bahan->nama ?? 'Bahan',
                                    'butuh'  => 0,
                                    'satuan' => $resep->bahan->satuan ?? 'pcs',
                                ];
                            }
                            $agregatKebutuhan[$bahanId]['butuh'] += $butuhSisa;
                        }
                    }
                }
            }

            // Ambil stok terkini di Gudang Central Kitchen untuk seluruh bahan yang direkap
            $bahanIds = array_keys($rekapBahanMap);
            $stokGudangCk = !empty($bahanIds)
                ? StokGudang::where('gudang_id', $gudangCkId)
                    ->whereIn('barang_id', $bahanIds)
                    ->pluck('jumlah', 'barang_id')
                    ->toArray()
                : [];

            $isBahanSufficient = true;
            $defisitBahan = [];
            $rekapBahanList = [];
            $totalBahanKurang = 0;
            $totalBahanCukup = 0;

            foreach ($rekapBahanMap as $bahanId => $dataBahan) {
                $stok = floatval($stokGudangCk[$bahanId] ?? 0);
                // Jika WO masih ada sisa produksi, gunakan sisa_butuh; jika sudah komplit, gunakan total_butuh
                $refButuh = ($totalSisa > 0) ? $dataBahan['sisa_butuh'] : $dataBahan['total_butuh'];
                $isCukup = ($stok >= $refButuh);
                $kurang = max(0, $refButuh - $stok);
                $selisih = $stok - $refButuh;

                if (!$isCukup) {
                    $isBahanSufficient = false;
                    $totalBahanKurang++;
                    if ($totalSisa > 0) {
                        $defisitBahan[] = [
                            'nama'   => $dataBahan['nama_bahan'],
                            'butuh'  => $dataBahan['sisa_butuh'],
                            'stok'   => $stok,
                            'kurang' => $kurang,
                            'satuan' => $dataBahan['satuan'],
                        ];
                    }
                } else {
                    $totalBahanCukup++;
                }

                $dataBahan['stok_ck'] = $stok;
                $dataBahan['is_cukup'] = $isCukup;
                $dataBahan['kurang'] = $kurang;
                $dataBahan['selisih'] = $selisih;
                $dataBahan['ref_butuh'] = $refButuh;
                $dataBahan['persen_stok'] = ($refButuh > 0) ? min(100, round(($stok / $refButuh) * 100)) : 100;

                $rekapBahanList[] = $dataBahan;
            }

            // Urutkan bahan baku: yang kurang ditaruh paling atas, lalu urut abjad nama
            usort($rekapBahanList, function($a, $b) {
                if ($a['is_cukup'] === $b['is_cukup']) {
                    return strcmp($a['nama_bahan'], $b['nama_bahan']);
                }
                return $a['is_cukup'] ? 1 : -1;
            });

            $wo->total_target = $totalTarget;
            $wo->total_selesai = $totalSelesai;
            $wo->total_sisa = $totalSisa;
            $wo->items_progress = $itemsProgress;
            $wo->is_all_completed = ($totalSisa <= 0 && $totalTarget > 0);
            $wo->has_missing_resep = $hasMissingResep;
            $wo->produk_tanpa_resep = $produkTanpaResep;
            $wo->is_bahan_sufficient = $isBahanSufficient;
            $wo->defisit_bahan = $defisitBahan;
            $wo->rekap_bahan = $rekapBahanList;
            $wo->total_jenis_bahan = count($rekapBahanList);
            $wo->total_bahan_kurang = $totalBahanKurang;
            $wo->total_bahan_cukup = $totalBahanCukup;
            // Approval hanya bisa dilakukan jika seluruh item punya resep dan bahan cukup
            $wo->can_approve = !$hasMissingResep && $isBahanSufficient;

            // Cek status pengiriman pesanan terkait Work Order ini
            $pesananIds = $wo->details->pluck('pesanan_id')->filter()->unique();
            $isTerkirim = Pengiriman::whereIn('pesanan_id', $pesananIds)
                ->where('status_pengiriman', 'Selesai')
                ->exists();
            $wo->is_terkirim = $isTerkirim;
            $wo->is_belum_terkirim = !$isTerkirim;

            return $wo;
        });

        // Pesanan CK yang pending/siap dibuatkan WO
        $pesananCkQuery = Pesanan::centralKitchen()
            ->with(['details.produk.resepBtklBop', 'customer'])
            ->whereIn('status_pesanan', ['pending', 'Draft']);

        if ($customerId) {
            $pesananCkQuery->where('customer_id', $customerId);
        }

        $pesananCkPending = $pesananCkQuery
            ->orderBy('estimasi_kirim', 'asc')
            ->paginate(10, ['*'], 'pesanan_page')
            ->withQueryString();

        $pesananCkPending->getCollection()->transform(function($p) use ($gudangCkId) {
            foreach ($p->details as $d) {
                $stok = floatval(StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $d->produk_id)->value('jumlah') ?? 0);
                $d->stok_tersedia = $stok;
                $d->qty_kurang = max(0, floatval($d->qty) - $stok);
            }
            return $p;
        });

        // Riwayat Produksi CK dengan detail produk & pesanan
        $queryProduksi = Produksi::with(['details.produk', 'pesanan.customer', 'divisi'])
            ->where(function($q) use ($customerId) {
                $q->whereHas('pesanan', function($pq) use ($customerId) {
                    $pq->where('tipe_pesanan', 'central_kitchen');
                    if ($customerId) {
                        $pq->where('customer_id', $customerId);
                    }
                });
                if (!$customerId) {
                    $q->orWhereNull('pesanan_id'); // produksi mandiri tanpa pesanan outlet
                }
            });

        if ($search) {
            $queryProduksi->where('kode_produksi', 'like', '%' . $search . '%');
        }

        $riwayatProduksi = $queryProduksi->orderBy('id', 'desc')->paginate(10, ['*'], 'prod_page')->withQueryString();

        // Hitung ketersediaan bahan baku & resep untuk setiap draft riwayat produksi CK
        $riwayatProduksi->getCollection()->transform(function($prod) use ($gudangCkId) {
            $isBahanSufficient = true;
            $hasMissingResep = false;
            $defisitBahan = [];
            $produkTanpaResep = [];
            $fifoService = app(\App\Services\FifoService::class);

            if (strtolower($prod->status_produksi) === 'draft') {
                foreach ($prod->details as $detail) {
                    $produk = MasterBarang::with('resep.bahan')->find($detail->produk_id);
                    $hasResep = ($produk && $produk->resep && $produk->resep->count() > 0);
                    
                    if (!$hasResep) {
                        $hasMissingResep = true;
                        $produkTanpaResep[] = [
                            'produk_id'   => $detail->produk_id,
                            'nama_produk' => $produk->nama ?? 'Produk #' . $detail->produk_id,
                            'kode_barang' => $produk->kode_barang ?? '-',
                        ];
                        continue;
                    }

                    $resepId = $produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null);
                    $resepItems = $resepId ? ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get() : collect();
                    foreach ($resepItems as $resep) {
                        $kebutuhan = floatval($resep->qty_bahan) * floatval($detail->qty);
                        
                        $avail = $fifoService->checkBahanAvailability($resep, $kebutuhan, $gudangCkId);
                        if (!$avail['sufficient']) {
                            $isBahanSufficient = false;
                            $defisitBahan[] = [
                                'nama'   => $avail['nama'],
                                'butuh'  => $kebutuhan,
                                'stok'   => $avail['stok'],
                                'kurang' => $kebutuhan - $avail['stok'],
                                'satuan' => $resep->bahan->satuan ?? 'pcs',
                            ];
                        }
                    }
                }
            }

            $prod->has_missing_resep = $hasMissingResep;
            $prod->produk_tanpa_resep = $produkTanpaResep;
            $prod->is_bahan_sufficient = $isBahanSufficient;
            $prod->defisit_bahan = $defisitBahan;
            $prod->can_approve = !$hasMissingResep && $isBahanSufficient;
            return $prod;
        });

        // Stok BSJ per Divisi Gudang Central Kitchen
        $stokBsjPerDivisi = [];
        $divisiList = \App\Models\GudangDivisi::where('gudang_id', $gudangCkId)->get();
        foreach ($divisiList as $divisi) {
            $stokItems = StokGudang::with('barang')
                ->where('gudang_id', $gudangCkId)
                ->where('divisi_id', $divisi->id)
                ->whereHas('barang', fn($q) => $q->where('is_bahan_setengah_jadi', true)->where('is_active', true))
                ->get();
            if ($stokItems->isNotEmpty()) {
                $stokBsjPerDivisi[$divisi->nama] = $stokItems->map(fn($s) => [
                    'nama'   => $s->barang->nama ?? '-',
                    'jumlah' => (float) $s->jumlah,
                    'satuan' => $s->barang->satuan ?? '-',
                ])->toArray();
            }
        }

        $customers = \App\Models\Customer::orderBy('nama')->get();

        $allProdukCk = MasterBarang::where('is_active', true)
            ->where('is_bahan_setengah_jadi', true)
            ->orderBy('nama', 'asc')
            ->get();

        return view('central_kitchen.produksi.index', compact('woList', 'pesananCkPending', 'riwayatProduksi', 'stokBsjPerDivisi', 'customers', 'customerId', 'allProdukCk', 'isSuperAdmin'));
    }

    /**
     * Buat WO Central Kitchen dari Order
     */
    public function storeWo(Request $request)
    {
        $request->validate([
            'pesanan_id'  => 'required',
            'produk_id'   => 'required|array',
            'qty_rencana' => 'required|array',
        ]);

        $pesanan = Pesanan::centralKitchen()->with('details')->findOrFail($request->pesanan_id);

        DB::beginTransaction();
        try {
            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 5;

            // Map kuantitas rencana yang dikirimkan dari form
            $qtyMap = [];
            foreach ($request->produk_id as $key => $produk_id) {
                $qtyMap[$produk_id] = floatval($request->qty_rencana[$key] ?? 0);
            }

            // Cek ketersediaan bahan baku di Gudang Central Kitchen untuk seluruh item pesanan
            $isBahanCukup = true;
            foreach ($pesanan->details as $pd) {
                $qty = isset($qtyMap[$pd->produk_id]) && $qtyMap[$pd->produk_id] > 0 
                    ? $qtyMap[$pd->produk_id] 
                    : floatval($pd->qty);

                if ($qty <= 0) continue;

                $produk = MasterBarang::with('resep.bahan')->find($pd->produk_id);
                if ($produk && $produk->resep) {
                    foreach ($produk->resep as $resep) {
                        $kebutuhan = floatval($resep->qty_bahan) * $qty;
                        $stok = floatval(StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $resep->bahan_id)->value('jumlah') ?? 0);
                        if ($stok < $kebutuhan) {
                            $isBahanCukup = false;
                            break 2;
                        }
                    }
                }
            }

            $wo = WorkOrder::create([
                'kode_wo'    => 'WO-CK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'tanggal_wo' => now(),
                'status_wo'  => $isBahanCukup ? 'Diproses' : 'Draft',
                'catatan'    => $request->catatan ?? ($isBahanCukup ? 'Bahan baku mencukupi di Gudang CK' : 'Bahan baku kurang, menunggu permintaan'),
                'created_by' => auth()->id(),
            ]);

            // Masukkan SELURUH item pesanan ke dalam Work Order Detail agar tidak ada item yang hilang
            foreach ($pesanan->details as $pd) {
                $qty = isset($qtyMap[$pd->produk_id]) && $qtyMap[$pd->produk_id] > 0 
                    ? $qtyMap[$pd->produk_id] 
                    : floatval($pd->qty);

                WorkOrderDetail::create([
                    'work_order_id' => $wo->id,
                    'pesanan_id'    => $pesanan->id,
                    'produk_id'     => $pd->produk_id,
                    'qty_rencana'   => $qty,
                ]);
            }

            $pesanan->update(['status_pesanan' => 'Diproses']);

            DB::commit();
            $msg = $isBahanCukup 
                ? 'Work Order Central Kitchen (' . $wo->kode_wo . ') berhasil dibuat! Bahan baku mencukupi di Gudang CK, siap langsung diproduksi.' 
                : 'Work Order Central Kitchen (' . $wo->kode_wo . ') berhasil dibuat! Bahan baku belum mencukupi, silakan buat permintaan bahan jika diperlukan.';
            return redirect()->route('ck-produksi.index', ['tab' => 'wo'])->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal membuat WO CK: ' . $e->getMessage());
        }
    }

    /**
     * Kirim/Minta Bahan Baku dari Gudang Utama ke Gudang Central Kitchen Sesuai Kekurangan Saja
     */
    public function kirimBahanBaku($woId)
    {
        $wo = WorkOrder::with('details.produk.resep.bahan')->findOrFail($woId);

        DB::beginTransaction();
        try {
            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first()
                ?? MasterGudang::where('kategori', 'Produksi')->first();

            if (!$gudangCk) {
                throw new \Exception('Gudang Central Kitchen belum tersedia di Master Gudang.');
            }

            $agregatBahan = [];
            foreach ($wo->details as $detail) {
                if (!$detail->produk || !$detail->produk->resep) continue;

                // Hitung sisa kebutuhan produksi untuk item ini
                $sudah = DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $detail->pesanan_id)
                    ->where('produk_id', $detail->produk_id)
                    ->sum('qty_alokasi') ?? 0;
                $sisaQty = max(0, floatval($detail->qty_rencana) - floatval($sudah));
                if ($sisaQty <= 0) $sisaQty = floatval($detail->qty_rencana);

                foreach ($detail->produk->resep as $resep) {
                    $qtyKebutuhan = floatval($resep->qty_bahan) * $sisaQty;
                    if (!isset($agregatBahan[$resep->bahan_id])) {
                        $agregatBahan[$resep->bahan_id] = [
                            'nama'   => $resep->bahan->nama ?? 'Bahan',
                            'butuh'  => 0,
                            'satuan' => $resep->bahan->satuan ?? '-',
                        ];
                    }
                    $agregatBahan[$resep->bahan_id]['butuh'] += $qtyKebutuhan;
                }
            }

            // Hitung hanya kekurangan bahannya saja (kebutuhan resep - stok tersedia di Gudang CK)
            $bahanKurang = [];
            foreach ($agregatBahan as $bahanId => $data) {
                $stokDiCk = floatval(StokGudang::where('gudang_id', $gudangCk->id)->where('barang_id', $bahanId)->value('jumlah') ?? 0);
                $kurang = max(0, $data['butuh'] - $stokDiCk);
                if ($kurang > 0) {
                    $bahanKurang[$bahanId] = [
                        'qty'    => $kurang,
                        'satuan' => $data['satuan'],
                    ];
                }
            }

            if (empty($bahanKurang)) {
                return redirect()->back()->with('success', 'Stok bahan baku di Gudang Central Kitchen sudah mencukupi seluruh target WO. Tidak perlu meminta bahan tambahan.');
            }

            $pengeluaran = \App\Models\PengeluaranBahanBaku::create([
                'kode_pengeluaran' => 'REQ-CK-' . date('Ymd') . '-' . strtoupper(\Str::random(4)),
                'tanggal'          => now(),
                'gudang_id'        => $gudangCk->id,
                'status'           => 'Draft',
                'keterangan'       => 'Permintaan kekurangan bahan baku CK untuk ' . $wo->kode_wo,
                'created_by'       => auth()->id(),
            ]);

            foreach ($bahanKurang as $bahanId => $data) {
                \App\Models\PengeluaranBahanBakuDetail::create([
                    'pengeluaran_id' => $pengeluaran->id,
                    'barang_id'      => $bahanId,
                    'qty'            => $data['qty'],
                    'satuan'         => $data['satuan'],
                ]);
            }

            $wo->update(['status_wo' => 'Diproses']);

            DB::commit();
            return redirect()->back()->with('success', 'Permintaan kekurangan bahan baku untuk Central Kitchen berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Form Input Hasil Produksi CK
     */
    public function createProduksi(Request $request)
    {
        $selectedWoId = $request->get('work_order_id');
        $workOrders = WorkOrder::where('status_wo', 'Diproses')
            ->whereHas('details.pesanan', function($q) {
                $q->where('tipe_pesanan', 'central_kitchen');
            })
            ->get();

        $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
        $gudangCkId = $gudangCk ? $gudangCk->id : 5;

        $items = collect();
        $isBahanSufficient = true;
        $defisitBahan = [];

        if ($selectedWoId) {
            $woDetails = WorkOrderDetail::where('work_order_id', $selectedWoId)->with('produk.resep.bahan')->get();
            $items = $woDetails->map(function($wod) {
                $sudah = DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $wod->pesanan_id)
                    ->where('produk_id', $wod->produk_id)
                    ->sum('qty_alokasi') ?? 0;
                $sisa = max(0, floatval($wod->qty_rencana) - floatval($sudah));

                return (object) [
                    'produk_id'    => $wod->produk_id,
                    'produk'       => $wod->produk,
                    'total_target' => $wod->qty_rencana,
                    'sisa_target'  => $sisa,
                ];
            });

            // Cek ketersediaan resep & bahan
            $hasMissingResep = false;
            $produkTanpaResep = [];
            $agregatKebutuhan = [];

            foreach ($woDetails as $wod) {
                $hasResep = $wod->produk && $wod->produk->resep && $wod->produk->resep->count() > 0;
                if (!$hasResep) {
                    $hasMissingResep = true;
                    $produkTanpaResep[] = [
                        'produk_id'   => $wod->produk_id,
                        'nama_produk' => $wod->produk->nama ?? 'Produk #' . $wod->produk_id,
                        'kode_barang' => $wod->produk->kode_barang ?? '-',
                    ];
                }

                $sudah = DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $wod->pesanan_id)
                    ->where('produk_id', $wod->produk_id)
                    ->sum('qty_alokasi') ?? 0;
                $sisa = max(0, floatval($wod->qty_rencana) - floatval($sudah));

                if ($hasResep && $sisa > 0) {
                    foreach ($wod->produk->resep as $resep) {
                        $kebutuhan = floatval($resep->qty_bahan) * $sisa;
                        $bahanId = $resep->bahan_id;
                        if (!isset($agregatKebutuhan[$bahanId])) {
                            $agregatKebutuhan[$bahanId] = [
                                'nama'   => $resep->bahan->nama ?? 'Bahan',
                                'satuan' => $resep->bahan->satuan ?? 'pcs',
                                'total_butuh' => 0,
                            ];
                        }
                        $agregatKebutuhan[$bahanId]['total_butuh'] += $kebutuhan;
                    }
                }
            }

            foreach ($agregatKebutuhan as $bahanId => $agg) {
                $stok = floatval(StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $bahanId)->value('jumlah') ?? 0);
                if ($stok < $agg['total_butuh']) {
                    $isBahanSufficient = false;
                    $defisitBahan[] = [
                        'nama'   => $agg['nama'],
                        'butuh'  => $agg['total_butuh'],
                        'stok'   => $stok,
                        'kurang' => $agg['total_butuh'] - $stok,
                        'satuan' => $agg['satuan'],
                    ];
                }
            }
        }

        // Ambil divisi Gudang Central Kitchen untuk dropdown
        $gudangCkForDivisi = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
        $gudangCkIdForDivisi = $gudangCkForDivisi ? $gudangCkForDivisi->id : 1;
        $divisiCk = \App\Models\GudangDivisi::where('gudang_id', $gudangCkIdForDivisi)->get();

        // Attach divisi_id dari pesanan CK terkait WO untuk auto-fill
        $workOrders->each(function($wo) {
            $pesananId = optional($wo->details->first())->pesanan_id;
            $wo->divisi_id = $pesananId ? \App\Models\Pesanan::find($pesananId)?->divisi_id : null;
        });

        $selectedDivisiId = $request->get('divisi_id');
        if (!$selectedDivisiId && $selectedWoId) {
            $selectedWo = $workOrders->firstWhere('id', $selectedWoId);
            $selectedDivisiId = $selectedWo?->divisi_id;
        }

        return view('central_kitchen.produksi.create', compact('workOrders', 'selectedWoId', 'items', 'isBahanSufficient', 'defisitBahan', 'divisiCk', 'selectedDivisiId', 'hasMissingResep', 'produkTanpaResep'));
    }

    /**
     * Simpan Draft Produksi Central Kitchen
     */
    public function storeProduksi(Request $request)
    {
        $request->validate([
            'work_order_id'    => 'required',
            'tanggal_produksi' => 'required|date',
            'produk_id'        => 'required|array',
            'qty_hasil'        => 'required|array',
            'divisi_id'        => 'nullable|exists:gudang_divisi,id',
        ]);

        DB::beginTransaction();
        try {
            $woDetails = DB::table('work_order_detail')->where('work_order_id', $request->work_order_id)->get();
            $pesananIdUtama = $woDetails->pluck('pesanan_id')->first();

            $gudangCk = MasterGudang::where('nama', 'Gudang Central Kitchen')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 3;

            $produksiId = DB::table('produksi')->insertGetId([
                'kode_produksi'   => 'PRD-CK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'pesanan_id'      => $pesananIdUtama,
                'tanggal_mulai'   => $request->tanggal_produksi,
                'tanggal_selesai' => null,
                'status_produksi' => 'Draft',
                'gudang_bahan_id' => $gudangCkId,
                'gudang_hasil_id' => $gudangCkId,
                'divisi_id'       => $request->divisi_id,
                'created_by'      => auth()->id() ?? 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $inputSatuanList = $request->input('satuan_input', []);

            foreach ($request->produk_id as $key => $produkId) {
                $rawQty = floatval($request->qty_hasil[$key] ?? 0);
                if ($rawQty <= 0) continue;

                $unitChoice = $inputSatuanList[$key] ?? 'dasar';
                $qtyHasil = $rawQty;

                if ($unitChoice === 'konversi') {
                    $prodItem = MasterBarang::find($produkId);
                    if ($prodItem && floatval($prodItem->konversi_pembelian) > 1) {
                        $qtyHasil = $rawQty * floatval($prodItem->konversi_pembelian);
                    }
                }

                DB::table('produksi_detail')->insert([
                    'produksi_id' => $produksiId,
                    'produk_id'   => $produkId,
                    'qty'         => $qtyHasil,
                    'hpp_total'   => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            DB::commit();
            return redirect()->route('ck-produksi.index')->with('success', 'Draft Produksi Central Kitchen berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Simpan Draft: ' . $e->getMessage());
        }
    }

    /**
     * Simpan & Approve Hasil Produksi Central Kitchen Sekaligus (Mendukung Parsial / Sisa Kekurangan)
     */
    public function storeAndApprove(Request $request)
    {
        $woIdsInput = $request->input('work_order_ids') ?? $request->input('work_order_id');
        if (is_array($woIdsInput)) {
            $woIds = array_filter($woIdsInput);
        } else {
            $woIds = array_filter(explode(',', strval($woIdsInput)));
        }

        if (empty($woIds)) {
            return back()->with('error', 'Gagal: Tidak ada Work Order Central Kitchen valid yang dipilih.')->withInput();
        }

        DB::beginTransaction();
        try {
            $workOrders = WorkOrder::with('details.produk')->whereIn('id', $woIds)->get();
            if ($workOrders->isEmpty()) {
                throw new \Exception('Work Order Central Kitchen yang dipilih tidak ditemukan.');
            }

            $pesananIdUtama = $workOrders->pluck('details')->flatten()->pluck('pesanan_id')->filter()->first();
            $pesanan = Pesanan::find($pesananIdUtama);

            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 5;

            $inputSatuanList = $request->input('satuan_input', []);

            // Validasi minimal 1 produk memiliki qty hasil > 0
            $hasValidQty = false;
            foreach ($request->produk_id as $key => $pid) {
                if (floatval($request->qty_hasil[$key] ?? 0) > 0) {
                    $hasValidQty = true;
                    break;
                }
            }

            if (!$hasValidQty) {
                throw new \Exception('Harap masukkan minimal 1 produk dengan Qty hasil lebih dari 0.');
            }

            $isDraft = ($request->input('action') === 'draft');

            if ($isDraft) {
                // SIMPAN DRAFT PERUBAHAN QTY (Dapat disimpan tanpa formulasi resep / bahan baku)
                $pesananIdsToRecount = [];
                foreach ($request->produk_id as $key => $produkId) {
                    $rawQty = floatval($request->qty_hasil[$key] ?? 0);
                    $unitChoice = $inputSatuanList[$key] ?? 'dasar';
                    $qtyHasil = $rawQty;

                    $produk = MasterBarang::find($produkId);
                    if ($unitChoice === 'konversi' && $produk && floatval($produk->konversi_pembelian) > 1) {
                        $qtyHasil = $rawQty * floatval($produk->konversi_pembelian);
                    }

                    // Update qty_rencana pada work_order_detail untuk seluruh WO yang dipilih
                    $wodList = DB::table('work_order_detail')
                        ->whereIn('work_order_id', $woIds)
                        ->where('produk_id', $produkId)
                        ->get();

                    foreach ($wodList as $wod) {
                        DB::table('work_order_detail')
                            ->where('id', $wod->id)
                            ->update([
                                'qty_rencana' => $qtyHasil,
                                'updated_at'  => now(),
                            ]);

                        $pesDetail = PesananDetail::where('pesanan_id', $wod->pesanan_id)
                            ->where('produk_id', $produkId)
                            ->first();
                        if ($pesDetail) {
                            $harga = floatval($pesDetail->harga);
                            $pesDetail->update([
                                'qty'      => $qtyHasil,
                                'subtotal' => $qtyHasil * $harga,
                            ]);
                            $pesananIdsToRecount[$wod->pesanan_id] = $wod->pesanan_id;
                        }
                    }
                }

                // Hitung ulang total pesanan
                foreach ($pesananIdsToRecount as $pId) {
                    $newTotal = PesananDetail::where('pesanan_id', $pId)->sum('subtotal');
                    Pesanan::where('id', $pId)->update(['total_pesanan' => $newTotal]);
                }

                // Pastikan status WO tetap Draft
                WorkOrder::whereIn('id', $woIds)->update(['status_wo' => 'Draft', 'updated_at' => now()]);

                // Buat atau perbarui draft Produksi di tabel produksi
                $existingDraftProd = Produksi::where('pesanan_id', $pesananIdUtama)
                    ->where('status_produksi', 'Draft')
                    ->first();

                if ($existingDraftProd) {
                    $existingDraftProd->update([
                        'tanggal_mulai' => $request->tanggal_produksi ?? now(),
                        'divisi_id'     => $request->divisi_id,
                        'updated_at'    => now(),
                    ]);
                    DB::table('produksi_detail')->where('produksi_id', $existingDraftProd->id)->delete();
                    $draftProdId = $existingDraftProd->id;
                } else {
                    $kodeProduksiDraft = 'PRD-CK-DRAFT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $draftProdId = DB::table('produksi')->insertGetId([
                        'kode_produksi'   => $kodeProduksiDraft,
                        'pesanan_id'      => $pesananIdUtama,
                        'tanggal_mulai'   => $request->tanggal_produksi ?? now(),
                        'tanggal_selesai' => null,
                        'status_produksi' => 'Draft',
                        'gudang_bahan_id' => $gudangCkId,
                        'gudang_hasil_id' => $gudangCkId,
                        'divisi_id'       => $request->divisi_id,
                        'created_by'      => auth()->id() ?? 1,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }

                foreach ($request->produk_id as $key => $produkId) {
                    $rawQty = floatval($request->qty_hasil[$key] ?? 0);
                    if ($rawQty <= 0) continue;
                    $unitChoice = $inputSatuanList[$key] ?? 'dasar';
                    $qtyHasil = $rawQty;
                    $produk = MasterBarang::find($produkId);
                    if ($unitChoice === 'konversi' && $produk && floatval($produk->konversi_pembelian) > 1) {
                        $qtyHasil = $rawQty * floatval($produk->konversi_pembelian);
                    }

                    DB::table('produksi_detail')->insert([
                        'produksi_id' => $draftProdId,
                        'produk_id'   => $produkId,
                        'qty'         => $qtyHasil,
                        'hpp_total'   => 0,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                DB::commit();
                return redirect()->back()->with('success', 'Draft perubahan kuantitas berhasil disimpan! Anda dapat melakukan approval produksi setelah formulasi resep dan bahan baku dilengkapi.');
            }

            // Validasi formulasi resep & ketersediaan bahan baku di Gudang CK sebelum eksekusi (Khusus Approve)
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($request->produk_id as $key => $produkId) {
                $rawQty = floatval($request->qty_hasil[$key] ?? 0);
                if ($rawQty <= 0) continue;

                $unitChoice = $inputSatuanList[$key] ?? 'dasar';
                $qtyHasil = $rawQty;

                $produk = MasterBarang::with('resep.bahan')->find($produkId);
                if (!$produk) {
                    throw new \Exception("ID Produk {$produkId} tidak valid.");
                }

                if ($unitChoice === 'konversi' && floatval($produk->konversi_pembelian) > 1) {
                    $qtyHasil = $rawQty * floatval($produk->konversi_pembelian);
                }

                $hasResep = ($produk->resep && $produk->resep->count() > 0);
                if (!$hasResep) {
                    throw new \Exception("Approval belum dapat dilakukan: Menu '{$produk->nama}' belum memiliki formulasi resep. Silakan isi resep terlebih dahulu di menu Resep.");
                }

                $resepId = $produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null);
                $resepItems = $resepId ? ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get() : collect();
                foreach ($resepItems as $item) {
                    $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                    
                    $avail = $fifoService->checkBahanAvailability($item, $qtyButuh, $gudangCkId);
                    if (!$avail['sufficient']) {
                        $namaBahan = $avail['nama'];
                        $stokBahan = $avail['stok'];
                        throw new \Exception("Approval belum dapat dilakukan: Stok bahan baku {$namaBahan} di Gudang Central Kitchen belum mencukupi (Tersedia: {$stokBahan}, Dibutuhkan: {$qtyButuh}). Silakan lakukan permintaan bahan terlebih dahulu.");
                    }
                }
            }

            // Bersihkan draft produksi sebelumnya jika ada untuk pesanan ini agar tidak ada duplikasi draft setelah approve
            $oldDraft = Produksi::where('pesanan_id', $pesananIdUtama)->where('status_produksi', 'Draft')->first();
            if ($oldDraft) {
                DB::table('produksi_detail')->where('produksi_id', $oldDraft->id)->delete();
                $oldDraft->delete();
            }

            // Kode Produksi Batch
            $kodeProduksi = 'PRD-CK-BATCH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $produksiId = DB::table('produksi')->insertGetId([
                'kode_produksi'   => $kodeProduksi,
                'pesanan_id'      => $pesananIdUtama,
                'tanggal_mulai'   => $request->tanggal_produksi,
                'tanggal_selesai' => now(),
                'status_produksi' => 'Selesai',
                'gudang_bahan_id' => $gudangCkId,
                'gudang_hasil_id' => $gudangCkId,
                'divisi_id'       => $request->divisi_id,
                'created_by'      => auth()->id() ?? 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $fifoService = app(\App\Services\FifoService::class);

            foreach ($request->produk_id as $key => $produkId) {
                $rawQty = floatval($request->qty_hasil[$key] ?? 0);
                if ($rawQty <= 0) continue;

                $produk = MasterBarang::find($produkId);
                $unitChoice = $inputSatuanList[$key] ?? 'dasar';
                $qtyHasil = $rawQty;

                if ($unitChoice === 'konversi' && $produk && floatval($produk->konversi_pembelian) > 1) {
                    $qtyHasil = $rawQty * floatval($produk->konversi_pembelian);
                }

                $totalBbbProduk = 0;
                $resepId = $produk ? ($produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null)) : null;
                if ($resepId) {
                    $resepItems = ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                        
                        $resolved = $fifoService->resolveAlternativeBahan($item, $qtyButuh, $gudangCkId);
                        $resolvedBahanId = $resolved['bahan_id'];

                        $fifoResult = $fifoService->consumeFIFO($resolvedBahanId, $qtyButuh, $gudangCkId);

                        $hppBahan = 0;
                        foreach ($fifoResult as $layer) {
                            $hppBahan += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                        }
                        $totalBbbProduk += $hppBahan;

                        $stokBahanGlobal = StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $resolvedBahanId)->first();
                        if ($stokBahanGlobal) {
                            $stokBahanGlobal->decrement('jumlah', $qtyButuh);
                        }

                        // Catat transaksi keluar bahan baku untuk Buku Pembantu Persediaan
                        TransaksiStok::create([
                            'tanggal'        => now(),
                            'tipe'           => 'keluar',
                            'source_type'    => 'produksi_ck',
                            'source_id'      => $produksiId,
                            'gudang_asal_id' => $gudangCkId,
                            'barang_id'      => $resolvedBahanId,
                            'qty'            => $qtyButuh,
                            'total_harga'    => $hppBahan,
                            'created_by'     => auth()->id() ?? 1,
                        ]);
                    }
                } else {
                    $totalBbbProduk = floatval($produk->hpp_referensi ?? 0) * $qtyHasil;
                }

                // BTKL & BOP (30% dari BBB)
                $totalBtklBop   = $totalBbbProduk * 0.30;
                $hppKeseluruhan = $totalBbbProduk + $totalBtklBop;
                $hppPerUnit     = $qtyHasil > 0 ? ($hppKeseluruhan / $qtyHasil) : 0;

                DB::table('produksi_detail')->insert([
                    'produksi_id' => $produksiId,
                    'produk_id'   => $produkId,
                    'qty'         => $qtyHasil,
                    'hpp_total'   => $hppKeseluruhan,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // Tambah stok ke Gudang Central Kitchen
                $stokBarangJadi = StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $produkId)->first();
                if ($stokBarangJadi) {
                    $stokBarangJadi->increment('jumlah', $qtyHasil);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangCkId,
                        'barang_id' => $produkId,
                        'jumlah'    => $qtyHasil,
                    ]);
                }

                $supplierId  = DB::table('suppliers')->value('id') ?? 1;
                $pembelianId = DB::table('pembelian')->value('id') ?? 1;
                $pemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                StokGudangBatch::create([
                    'gudang_id'           => $gudangCkId,
                    'supplier_id'         => $supplierId,
                    'barang_id'           => $produkId,
                    'pembelian_id'        => $pembelianId,
                    'pembelian_detail_id' => $pemDetailId,
                    'batch_number'        => 'CK-' . $kodeProduksi,
                    'qty_masuk'           => $qtyHasil,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyHasil,
                    'harga_per_qty'       => $hppPerUnit,
                    'is_habis'            => false,
                ]);

                TransaksiStok::create([
                    'tanggal'          => now(),
                    'tipe'             => 'masuk',
                    'source_type'      => 'produksi_ck',
                    'source_id'        => $produksiId,
                    'gudang_tujuan_id' => $gudangCkId,
                    'barang_id'        => $produkId,
                    'qty'              => $qtyHasil,
                    'total_harga'      => $hppKeseluruhan,
                    'created_by'       => auth()->id() ?? 1,
                ]);

                // Alokasi pesanan CK ke seluruh WO yang dipilih dalam proses produksi selesai ini
                $qtySisaAlokasi = $qtyHasil;
                $wodList = DB::table('work_order_detail')
                    ->whereIn('work_order_id', $woIds)
                    ->where('produk_id', $produkId)
                    ->get();

                $totalWodCount = $wodList->count();
                foreach ($wodList as $wIdx => $wod) {
                    if ($qtySisaAlokasi <= 0) break;

                    // Alokasikan seluruh hasil produksi rill ke WO ini
                    $porsi = ($wIdx === $totalWodCount - 1) ? $qtySisaAlokasi : min($qtySisaAlokasi, max(floatval($wod->qty_rencana), $qtySisaAlokasi));
                    $porsi = min($porsi, $qtySisaAlokasi);

                    $pesananItem = Pesanan::find($wod->pesanan_id);
                    $isInternalCk = false;
                    if ($pesananItem) {
                        $custNama = strtolower($pesananItem->customer_nama ?? $pesananItem->customer->nama ?? '');
                        if (str_contains($custNama, 'central kitchen')) {
                            $isInternalCk = true;
                        }
                    }

                    ProduksiPesanan::create([
                        'produksi_id'       => $produksiId,
                        'pesanan_id'        => $wod->pesanan_id,
                        'produk_id'         => $produkId,
                        'qty_alokasi'       => $porsi,
                        'qty_terkirim'      => $isInternalCk ? $porsi : 0,
                        'hpp_per_unit'      => $hppPerUnit,
                        'total_hpp_alokasi' => $hppPerUnit * $porsi,
                    ]);

                    $totalRealisasi = DB::table('alokasi_produksi_pesanan')
                        ->where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $produkId)
                        ->sum('qty_alokasi') ?? 0;

                    // Update target rencana WO sesuai total rill yang diselesaikan staff
                    DB::table('work_order_detail')
                        ->where('id', $wod->id)
                        ->update(['qty_rencana' => $totalRealisasi]);

                    // Update PesananDetail sesuai total produksi rill & HPP
                    PesananDetail::where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $produkId)
                        ->update([
                            'qty'      => $totalRealisasi,
                            'harga'    => $hppPerUnit,
                            'subtotal' => $totalRealisasi * $hppPerUnit,
                        ]);

                    $qtySisaAlokasi -= $porsi;
                }
            }

            // Periksa item-item yang tidak diproduksi (qty_hasil == 0) pada WO ini agar total qty dan HPP akurat
            foreach ($workOrders as $wo) {
                foreach ($wo->details as $wod) {
                    $hasAlokasi = DB::table('alokasi_produksi_pesanan')
                        ->where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $wod->produk_id)
                        ->exists();

                    $inputIdx = array_search($wod->produk_id, $request->produk_id);
                    if ($inputIdx !== false && floatval($request->qty_hasil[$inputIdx] ?? 0) == 0 && !$hasAlokasi) {
                        $wod->update(['qty_rencana' => 0]);
                        PesananDetail::where('pesanan_id', $wod->pesanan_id)
                            ->where('produk_id', $wod->produk_id)
                            ->update([
                                'qty'      => 0,
                                'subtotal' => 0,
                            ]);
                    }
                }

                // Tandai WO sebagai Selesai karena staff sudah menyelesaikan pesanan sesuai produksi rill
                $wo->update(['status_wo' => 'Selesai']);
                
                $pId = $wo->details->pluck('pesanan_id')->first();
                if ($pId) {
                    $pObj = Pesanan::find($pId);
                    if ($pObj) {
                        $custNama = strtolower($pObj->customer_nama ?? $pObj->customer->nama ?? '');
                        $isInternalCk = str_contains($custNama, 'central kitchen');
                        
                        // Hitung ulang total pesanan berdasarkan HPP & Qty rill yang sudah selesai
                        $newTotalHpp = PesananDetail::where('pesanan_id', $pObj->id)->sum('subtotal');
                        $pObj->update([
                            'total_pesanan'  => $newTotalHpp,
                            'tax_service'    => 0,
                            'status_pesanan' => $isInternalCk ? 'Selesai' : 'Siap kirim',
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('ck-produksi.index')->with('success', 'Hasil produksi Central Kitchen (' . count($workOrders) . ' WO) berhasil di-approve! Total kuantitas dan HPP telah diperbarui sesuai hasil rill selesai.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses produksi CK batch: ' . $e->getMessage());
        }
    }

    /**
     * Edit Qty Work Order (Superadmin & User Gaharu, Hanya untuk WO yang Belum Terkirim)
     */
    public function editQtyWo(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->canEditWoQty()) {
            abort(403, 'Akses ditolak: Fitur edit kuantitas Work Order hanya dapat diakses oleh Superadmin dan user Gaharu.');
        }

        $request->validate([
            'detail_id'          => 'nullable|array',
            'qty_baru'           => 'nullable|array',
            'satuan_input_edit'  => 'nullable|array',
            'deleted_detail_ids' => 'nullable|array',
            'new_produk_id'      => 'nullable|array',
            'new_qty'            => 'nullable|array',
            'new_satuan_input'   => 'nullable|array',
            'alasan_edit'        => 'nullable|string|max:255',
        ]);

        $wo = WorkOrder::with(['details.produk', 'details.pesanan'])->findOrFail($id);

        $pesananIds = $wo->details->pluck('pesanan_id')->filter()->unique();
        $isTerkirim = Pengiriman::whereIn('pesanan_id', $pesananIds)
            ->where('status_pengiriman', 'Selesai')
            ->exists();

        if ($isTerkirim) {
            return back()->with('error', 'Gagal: Work Order ' . $wo->kode_wo . ' sudah memiliki pengiriman berstatus Selesai dan tidak dapat diedit lagi.');
        }

        // Tentukan pesanan ID default dari detail yang ada
        $defaultPesananId = $pesananIds->first() ?? null;

        // Validasi: Pastikan setelah hapus & tambah, WO tidak kosong
        $deletedIds = collect($request->deleted_detail_ids ?? [])->map(fn($v) => intval($v))->filter()->toArray();
        $existingDetailIds = $wo->details->pluck('id')->toArray();
        $remainingCount = count(array_diff($existingDetailIds, $deletedIds));
        $newValidCount = 0;
        if (!empty($request->new_produk_id)) {
            foreach ($request->new_produk_id as $nKey => $nPid) {
                if (!empty($nPid) && floatval($request->new_qty[$nKey] ?? 0) > 0) {
                    $newValidCount++;
                }
            }
        }
        if (($remainingCount + $newValidCount) <= 0) {
            return back()->with('error', 'Gagal: Work Order harus memiliki minimal 1 item produk. Tidak dapat menghapus seluruh item.');
        }

        DB::beginTransaction();
        try {
            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 5;
            $isSelesai = (strtolower($wo->status_wo) === 'selesai');

            // 1. PROSES HAPUS ITEM (DELETED DETAILS)
            if (!empty($deletedIds)) {
                foreach ($deletedIds as $delId) {
                    $delWod = WorkOrderDetail::where('work_order_id', $wo->id)->where('id', $delId)->first();
                    if (!$delWod) continue;

                    $delQty = floatval($delWod->qty_rencana);
                    $delProdukId = $delWod->produk_id;
                    $delPesananId = $delWod->pesanan_id;

                    // Hapus dari PesananDetail
                    if ($delPesananId) {
                        PesananDetail::where('pesanan_id', $delPesananId)
                            ->where('produk_id', $delProdukId)
                            ->delete();
                    }

                    // Jika WO sudah Selesai, kurangi/batalkan alokasi & stok
                    if ($isSelesai && $delQty > 0) {
                        $alokasiList = ProduksiPesanan::where('pesanan_id', $delPesananId)
                            ->where('produk_id', $delProdukId)
                            ->get();

                        if ($alokasiList->isNotEmpty()) {
                            $lastAlokasi = $alokasiList->last();
                            $hppPerUnit  = floatval($lastAlokasi->hpp_per_unit);
                            $newAlokasiQty = max(0, floatval($lastAlokasi->qty_alokasi) - $delQty);
                            if ($newAlokasiQty <= 0) {
                                $lastAlokasi->delete();
                            } else {
                                $lastAlokasi->update([
                                    'qty_alokasi'       => $newAlokasiQty,
                                    'total_hpp_alokasi' => $newAlokasiQty * $hppPerUnit,
                                ]);
                            }

                            $prodDetail = DB::table('produksi_detail')
                                ->where('produksi_id', $lastAlokasi->produksi_id)
                                ->where('produk_id', $delProdukId)
                                ->first();
                            if ($prodDetail) {
                                $newProdQty = max(0, floatval($prodDetail->qty) - $delQty);
                                if ($newProdQty <= 0) {
                                    DB::table('produksi_detail')->where('id', $prodDetail->id)->delete();
                                } else {
                                    DB::table('produksi_detail')
                                        ->where('id', $prodDetail->id)
                                        ->update([
                                            'qty'       => $newProdQty,
                                            'hpp_total' => $newProdQty * $hppPerUnit,
                                        ]);
                                }
                            }
                        }

                        // Kurangi stok Gudang CK
                        $stokGudang = StokGudang::where('gudang_id', $gudangCkId)
                            ->where('barang_id', $delProdukId)
                            ->first();
                        if ($stokGudang) {
                            $stokGudang->decrement('jumlah', min(floatval($stokGudang->jumlah), $delQty));
                        }

                        $batch = StokGudangBatch::where('gudang_id', $gudangCkId)
                            ->where('barang_id', $delProdukId)
                            ->where('batch_number', 'like', '%CK-%')
                            ->latest()
                            ->first();
                        if ($batch) {
                            $batch->decrement('qty_masuk', min(floatval($batch->qty_masuk), $delQty));
                            $batch->decrement('qty_sisa', min(floatval($batch->qty_sisa), $delQty));
                        }
                    }

                    // Hapus record WorkOrderDetail
                    $delWod->delete();
                }
            }

            // 2. PROSES UPDATE QTY ITEM EKSISTING
            if (!empty($request->detail_id) && is_array($request->detail_id)) {
                foreach ($request->detail_id as $key => $detailId) {
                    if (in_array(intval($detailId), $deletedIds)) {
                        continue; // Lewati yang sudah dihapus
                    }

                    $wod = WorkOrderDetail::where('work_order_id', $wo->id)->where('id', $detailId)->first();
                    if (!$wod) continue;

                    $oldQty = floatval($wod->qty_rencana);
                    $rawQty = floatval($request->qty_baru[$key] ?? 0);
                    $unitChoice = $request->satuan_input_edit[$key] ?? 'dasar';
                    $newQty = $rawQty;

                    $produk = $wod->produk ?? MasterBarang::find($wod->produk_id);
                    if ($unitChoice === 'konversi' && $produk && floatval($produk->konversi_pembelian) > 1) {
                        $newQty = $rawQty * floatval($produk->konversi_pembelian);
                    }

                    if ($newQty <= 0) {
                        throw new \Exception("Kuantitas produk '{$produk->nama}' harus lebih dari 0. Jika ingin menghapus, gunakan tombol hapus baris.");
                    }

                    $deltaQty = $newQty - $oldQty;

                    // Update WorkOrderDetail
                    $wod->update(['qty_rencana' => $newQty]);

                    // Update PesananDetail
                    $pesDetail = PesananDetail::where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $wod->produk_id)
                        ->first();
                    if ($pesDetail) {
                        $hargaUnit = floatval($pesDetail->harga);
                        $pesDetail->update([
                            'qty'      => $newQty,
                            'subtotal' => $newQty * $hargaUnit,
                        ]);
                    }

                    // Jika WO sudah Selesai (sudah dialokasikan hasil produksinya):
                    if ($isSelesai) {
                        $alokasiList = ProduksiPesanan::where('pesanan_id', $wod->pesanan_id)
                            ->where('produk_id', $wod->produk_id)
                            ->get();

                        if ($alokasiList->isNotEmpty()) {
                            $lastAlokasi = $alokasiList->last();
                            $hppPerUnit  = floatval($lastAlokasi->hpp_per_unit);
                            $newAlokasiQty = max(0, floatval($lastAlokasi->qty_alokasi) + $deltaQty);
                            $lastAlokasi->update([
                                'qty_alokasi'       => $newAlokasiQty,
                                'total_hpp_alokasi' => $newAlokasiQty * $hppPerUnit,
                            ]);

                            // Update produksi_detail terkait
                            $prodDetail = DB::table('produksi_detail')
                                ->where('produksi_id', $lastAlokasi->produksi_id)
                                ->where('produk_id', $wod->produk_id)
                                ->first();
                            if ($prodDetail) {
                                $newProdQty = max(0, floatval($prodDetail->qty) + $deltaQty);
                                DB::table('produksi_detail')
                                    ->where('id', $prodDetail->id)
                                    ->update([
                                        'qty'       => $newProdQty,
                                        'hpp_total' => $newProdQty * $hppPerUnit,
                                    ]);
                            }
                        }

                        // Sesuaikan stok jadi di Gudang CK jika ada selisih
                        if ($deltaQty != 0) {
                            $stokGudang = StokGudang::where('gudang_id', $gudangCkId)
                                ->where('barang_id', $wod->produk_id)
                                ->first();
                            if ($stokGudang) {
                                $stokGudang->increment('jumlah', $deltaQty);
                            }

                            $batch = StokGudangBatch::where('gudang_id', $gudangCkId)
                                ->where('barang_id', $wod->produk_id)
                                ->where('batch_number', 'like', '%CK-%')
                                ->latest()
                                ->first();
                            if ($batch) {
                                $batch->increment('qty_masuk', $deltaQty);
                                $batch->increment('qty_sisa', $deltaQty);
                            }
                        }
                    }
                }
            }

            // 3. PROSES TAMBAH ITEM BARU
            if (!empty($request->new_produk_id) && is_array($request->new_produk_id)) {
                foreach ($request->new_produk_id as $nIdx => $newPid) {
                    if (empty($newPid)) continue;
                    $rawNewQty = floatval($request->new_qty[$nIdx] ?? 0);
                    if ($rawNewQty <= 0) continue;

                    $produkNew = MasterBarang::find($newPid);
                    if (!$produkNew) continue;

                    $unitChoiceNew = $request->new_satuan_input[$nIdx] ?? 'dasar';
                    $finalNewQty = $rawNewQty;
                    if ($unitChoiceNew === 'konversi' && floatval($produkNew->konversi_pembelian) > 1) {
                        $finalNewQty = $rawNewQty * floatval($produkNew->konversi_pembelian);
                    }

                    // Tentukan harga satuan produk
                    $hargaSatuan = floatval($produkNew->harga_jual_b2b ?? 0);
                    if ($hargaSatuan <= 0) {
                        $hargaSatuan = floatval($produkNew->hpp_referensi ?? 0);
                    }

                    // Buat / Update PesananDetail
                    if ($defaultPesananId) {
                        $pesDetail = PesananDetail::where('pesanan_id', $defaultPesananId)
                            ->where('produk_id', $newPid)
                            ->first();

                        if ($pesDetail) {
                            $updatedQty = floatval($pesDetail->qty) + $finalNewQty;
                            $pesDetail->update([
                                'qty'      => $updatedQty,
                                'subtotal' => $updatedQty * floatval($pesDetail->harga ?: $hargaSatuan),
                            ]);
                        } else {
                            PesananDetail::create([
                                'pesanan_id' => $defaultPesananId,
                                'produk_id'  => $newPid,
                                'qty'        => $finalNewQty,
                                'harga'      => $hargaSatuan,
                                'subtotal'   => $finalNewQty * $hargaSatuan,
                            ]);
                        }
                    }

                    // Buat WorkOrderDetail
                    WorkOrderDetail::create([
                        'work_order_id' => $wo->id,
                        'pesanan_id'    => $defaultPesananId,
                        'produk_id'     => $newPid,
                        'qty_rencana'   => $finalNewQty,
                    ]);

                    // Jika WO sudah Selesai, tambahkan stok & alokasi untuk produk baru ini
                    if ($isSelesai) {
                        $lastProduksi = Produksi::where('pesanan_id', $defaultPesananId)
                            ->where('status_produksi', 'Selesai')
                            ->latest()
                            ->first();

                        $hppUnitBaru = floatval($produkNew->hpp_referensi ?? 0);

                        if ($lastProduksi) {
                            // Alokasi produksi pesanan
                            ProduksiPesanan::create([
                                'produksi_id'       => $lastProduksi->id,
                                'pesanan_id'        => $defaultPesananId,
                                'produk_id'         => $newPid,
                                'qty_alokasi'       => $finalNewQty,
                                'hpp_per_unit'      => $hppUnitBaru,
                                'total_hpp_alokasi' => $finalNewQty * $hppUnitBaru,
                            ]);

                            // Detail produksi
                            DB::table('produksi_detail')->insert([
                                'produksi_id' => $lastProduksi->id,
                                'produk_id'   => $newPid,
                                'qty'         => $finalNewQty,
                                'hpp_total'   => $finalNewQty * $hppUnitBaru,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]);
                        }

                        // Tambah ke StokGudang CK
                        $stokGudang = StokGudang::where('gudang_id', $gudangCkId)
                            ->where('barang_id', $newPid)
                            ->first();
                        if ($stokGudang) {
                            $stokGudang->increment('jumlah', $finalNewQty);
                        } else {
                            StokGudang::create([
                                'gudang_id' => $gudangCkId,
                                'barang_id' => $newPid,
                                'jumlah'    => $finalNewQty,
                            ]);
                        }

                        $batch = StokGudangBatch::where('gudang_id', $gudangCkId)
                            ->where('barang_id', $newPid)
                            ->where('batch_number', 'like', '%CK-%')
                            ->latest()
                            ->first();
                        if ($batch) {
                            $batch->increment('qty_masuk', $finalNewQty);
                            $batch->increment('qty_sisa', $finalNewQty);
                        }
                    }
                }
            }

            // Hitung ulang total pesanan untuk seluruh pesanan terkait
            foreach ($pesananIds as $pId) {
                $pes = Pesanan::find($pId);
                if ($pes) {
                    $newTotal = PesananDetail::where('pesanan_id', $pId)->sum('subtotal');
                    $pes->update(['total_pesanan' => $newTotal]);
                }
            }

            if (!empty($request->alasan_edit)) {
                $wo->update([
                    'catatan' => trim(($wo->catatan ? $wo->catatan . ' | ' : '') . '[Edit Qty Superadmin: ' . $request->alasan_edit . ']')
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', "Detail Work Order {$wo->kode_wo} berhasil diperbarui oleh Superadmin!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui WO: ' . $e->getMessage());
        }
    }

    /**
     * Approve Produksi Central Kitchen (Hitung FIFO HPP & Masukkan ke Stok Gudang CK)
     */
    public function approveProduksi($id)
    {
        $produksi = Produksi::with('details')->findOrFail($id);
        if ($produksi->status_produksi !== 'Draft') {
            return redirect()->back()->with('error', 'Produksi ini sudah disetujui sebelumnya.');
        }

        DB::beginTransaction();
        try {
            $gudangBahanId = $produksi->gudang_bahan_id;
            $gudangHasilId = $produksi->gudang_hasil_id;
            $fifoService   = app(\App\Services\FifoService::class);

            $wodUtama = DB::table('work_order_detail')->where('pesanan_id', $produksi->pesanan_id)->first();
            $workOrderId = $wodUtama ? $wodUtama->work_order_id : null;

            // Validasi formulasi resep & ketersediaan bahan baku sebelum approve
            foreach ($produksi->details as $detail) {
                $produk = MasterBarang::with('resep.bahan')->find($detail->produk_id);
                if (!$produk) {
                    throw new \Exception("ID Produk {$detail->produk_id} tidak valid.");
                }

                $hasResep = ($produk->resep && $produk->resep->count() > 0);
                if (!$hasResep) {
                    throw new \Exception("Approval belum dapat dilakukan: Menu '{$produk->nama}' belum memiliki formulasi resep. Silakan isi resep terlebih dahulu di menu Resep.");
                }

                $resepId = $produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null);
                $resepItems = $resepId ? ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get() : collect();
                foreach ($resepItems as $item) {
                    $qtyButuh = floatval($item->qty_bahan) * floatval($detail->qty);
                    
                    $avail = $fifoService->checkBahanAvailability($item, $qtyButuh, $gudangBahanId);
                    if (!$avail['sufficient']) {
                        $namaBahan = $avail['nama'];
                        $stokBahan = $avail['stok'];
                        throw new \Exception("Approval belum dapat dilakukan: Stok {$namaBahan} di Gudang Central Kitchen belum mencukupi (Tersedia: {$stokBahan}, Dibutuhkan: {$qtyButuh}). Silakan lakukan permintaan bahan terlebih dahulu.");
                    }
                }
            }

            foreach ($produksi->details as $detail) {
                $produkId = $detail->produk_id;
                $qtyHasil = floatval($detail->qty);
                $produk   = MasterBarang::find($produkId);

                $totalBbbProduk = 0;
                $resepId = $produk ? ($produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null)) : null;
                if ($resepId) {
                    $resepItems = ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                        
                        $resolved = $fifoService->resolveAlternativeBahan($item, $qtyButuh, $gudangBahanId);
                        $resolvedBahanId = $resolved['bahan_id'];

                        $fifoResult = $fifoService->consumeFIFO($resolvedBahanId, $qtyButuh, $gudangBahanId);

                        $hppBahan = 0;
                        foreach ($fifoResult as $layer) {
                            $hppBahan += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                        }
                        $totalBbbProduk += $hppBahan;

                        $stokBahanGlobal = StokGudang::where('gudang_id', $gudangBahanId)->where('barang_id', $resolvedBahanId)->first();
                        if ($stokBahanGlobal) {
                            $stokBahanGlobal->decrement('jumlah', $qtyButuh);
                        }

                        // Catat transaksi keluar bahan baku untuk Buku Pembantu Persediaan
                        TransaksiStok::create([
                            'tanggal'        => now(),
                            'tipe'           => 'keluar',
                            'source_type'    => 'produksi_ck',
                            'source_id'      => $produksi->id,
                            'gudang_asal_id' => $gudangBahanId,
                            'barang_id'      => $resolvedBahanId,
                            'qty'            => $qtyButuh,
                            'total_harga'    => $hppBahan,
                            'created_by'     => auth()->id() ?? 1,
                        ]);
                    }
                } else {
                    // Jika belum ada resep, gunakan HPP referensi barang
                    $totalBbbProduk = floatval($produk->hpp_referensi ?? 0) * $qtyHasil;
                }

                // BTKL & BOP (30% dari BBB)
                $totalBtklBop = $totalBbbProduk * 0.30;
                $hppKeseluruhan = $totalBbbProduk + $totalBtklBop;
                $hppPerUnit     = $qtyHasil > 0 ? ($hppKeseluruhan / $qtyHasil) : 0;

                DB::table('produksi_detail')->where('id', $detail->id)->update([
                    'hpp_total'  => $hppKeseluruhan,
                    'updated_at' => now(),
                ]);

                // Tambah stok ke Gudang Central Kitchen
                $stokBarangJadi = StokGudang::where('gudang_id', $gudangHasilId)->where('barang_id', $produkId)->first();
                if ($stokBarangJadi) {
                    $stokBarangJadi->increment('jumlah', $qtyHasil);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangHasilId,
                        'barang_id' => $produkId,
                        'jumlah'    => $qtyHasil,
                    ]);
                }

                $supplierId  = DB::table('suppliers')->value('id') ?? 1;
                $pembelianId = DB::table('pembelian')->value('id') ?? 1;
                $pemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                StokGudangBatch::create([
                    'gudang_id'           => $gudangHasilId,
                    'supplier_id'         => $supplierId,
                    'barang_id'           => $produkId,
                    'pembelian_id'        => $pembelianId,
                    'pembelian_detail_id' => $pemDetailId,
                    'batch_number'        => 'CK-' . $produksi->kode_produksi,
                    'qty_masuk'           => $qtyHasil,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyHasil,
                    'harga_per_qty'       => $hppPerUnit,
                    'is_habis'            => false,
                ]);

                TransaksiStok::create([
                    'tanggal'          => now(),
                    'tipe'             => 'masuk',
                    'source_type'      => 'produksi_ck',
                    'source_id'        => $produksi->id,
                    'gudang_tujuan_id' => $gudangHasilId,
                    'barang_id'        => $produkId,
                    'qty'              => $qtyHasil,
                    'total_harga'      => $hppKeseluruhan,
                    'created_by'       => auth()->id() ?? 1,
                ]);

                $isInternalCk = false;
                $pesanan = DB::table('pesanan')->where('id', $produksi->pesanan_id)->first();
                if ($pesanan) {
                    $custNama = strtolower($pesanan->customer_nama ?? '');
                    if (!$custNama) {
                        $customer = DB::table('customers')->where('id', $pesanan->customer_id)->first();
                        $custNama = strtolower($customer->nama ?? '');
                    }
                    if (str_contains($custNama, 'central kitchen')) {
                        $isInternalCk = true;
                    }
                }

                // Alokasi pesanan CK
                ProduksiPesanan::create([
                    'produksi_id'       => $produksi->id,
                    'pesanan_id'        => $produksi->pesanan_id,
                    'produk_id'         => $produkId,
                    'qty_alokasi'       => $qtyHasil,
                    'qty_terkirim'      => $isInternalCk ? $qtyHasil : 0,
                    'hpp_per_unit'      => $hppPerUnit,
                    'total_hpp_alokasi' => $hppKeseluruhan,
                ]);

                // Update harga di PesananDetail sesuai HPP murni (tanpa keuntungan)
                PesananDetail::where('pesanan_id', $produksi->pesanan_id)
                    ->where('produk_id', $produkId)
                    ->update([
                        'harga'    => $hppPerUnit,
                        'subtotal' => DB::raw('qty * ' . $hppPerUnit),
                    ]);
            }

            // Hitung ulang total pesanan CK berdasarkan HPP
            if ($produksi->pesanan_id) {
                $newTotalHpp = PesananDetail::where('pesanan_id', $produksi->pesanan_id)->sum('subtotal');
                DB::table('pesanan')->where('id', $produksi->pesanan_id)->update([
                    'total_pesanan' => $newTotalHpp,
                    'tax_service'   => 0,
                ]);
            }

            // Cek apakah seluruh target WO selesai
            if ($workOrderId) {
                $wo = WorkOrder::with('details')->find($workOrderId);
                if ($wo) {
                    $woAllDone = true;
                    foreach ($wo->details as $wod) {
                        $totalSelesai = DB::table('alokasi_produksi_pesanan')
                            ->where('pesanan_id', $wo->details->first()->pesanan_id)
                            ->where('produk_id', $wod->produk_id)
                            ->sum('qty_alokasi') ?? 0;
                        if (floatval($totalSelesai) < floatval($wod->qty_rencana)) {
                            $woAllDone = false;
                            break;
                        }
                    }
                    $wo->update(['status_wo' => $woAllDone ? 'Selesai' : 'Diproses']);
                    if ($woAllDone) {
                        $targetStatus = $isInternalCk ? 'Selesai' : 'Siap kirim';
                        DB::table('pesanan')->where('id', $produksi->pesanan_id)->update(['status_pesanan' => $targetStatus]);
                    }
                }
            }

            DB::table('produksi')->where('id', $produksi->id)->update([
                'status_produksi' => 'Selesai',
                'tanggal_selesai' => now(),
            ]);

            DB::commit();
            return redirect()->route('ck-produksi.index')->with('success', 'Produksi Central Kitchen berhasil di-approve. HPP per unit berhasil dihitung dan barang siap dikirim ke outlet!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Approve Produksi CK: ' . $e->getMessage());
        }
    }

    /**
     * Form Produksi Stok Internal Central Kitchen (tanpa order outlet)
     */
    public function createStokInternal()
    {
        $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
        $gudangCkId = $gudangCk ? $gudangCk->id : 1;

        $divisiCk = \App\Models\GudangDivisi::where('gudang_id', $gudangCkId)->get();

        $produkBsj = MasterBarang::where('is_active', true)
            ->where('is_bahan_setengah_jadi', true)
            ->orderBy('nama')
            ->get();

        // Cek ketersediaan bahan baku untuk setiap BSJ
        $fifoService = app(\App\Services\FifoService::class);
        $produkWithBahan = $produkBsj->map(function($p) use ($gudangCkId, $fifoService) {
            $resepId = $p->resep_id ?: ($p->resepBtklBop ? $p->resepBtklBop->id : null);
            $resepItems = $resepId ? ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get() : collect();
            $bahanList = $resepItems->map(function($r) use ($gudangCkId, $fifoService) {
                $avail = $fifoService->checkBahanAvailability($r, (float)$r->qty_bahan, $gudangCkId);
                return [
                    'nama'        => $avail['nama'],
                    'qty_per_unit'=> (float) $r->qty_bahan,
                    'satuan'      => $r->bahan->satuan ?? '',
                    'stok'        => $avail['stok'],
                ];
            });
            return (object)[
                'id'     => $p->id,
                'kode'   => $p->kode_barang,
                'nama'   => $p->nama,
                'satuan' => $p->satuan,
                'bahan'  => $bahanList,
            ];
        });

        return view('central_kitchen.produksi.stok_internal_create', compact('divisiCk', 'produkWithBahan', 'gudangCkId'));
    }

    /**
     * Simpan Produksi Stok Internal Central Kitchen (langsung selesai)
     */
    public function storeStokInternal(Request $request)
    {
        $request->validate([
            'divisi_id'        => 'nullable|exists:gudang_divisi,id',
            'tanggal_produksi' => 'required|date',
            'produk_id'        => 'required|array|min:1',
            'qty_hasil'        => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 1;
            $divisiId   = $request->divisi_id;
            $kodeProduksi = 'PRD-INT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // Validasi: minimal 1 produk dengan qty > 0
            $hasValid = false;
            foreach ($request->produk_id as $k => $pid) {
                if (floatval($request->qty_hasil[$k] ?? 0) > 0) { $hasValid = true; break; }
            }
            if (!$hasValid) throw new \Exception('Harap isi minimal 1 produk dengan qty lebih dari 0.');

            // Cek kecukupan bahan
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($request->produk_id as $k => $produkId) {
                $qty = floatval($request->qty_hasil[$k] ?? 0);
                if ($qty <= 0) continue;
                $produk = MasterBarang::find($produkId);
                $resepId = $produk ? ($produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null)) : null;
                if ($resepId) {
                    foreach (ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get() as $r) {
                        $butuh = floatval($r->qty_bahan) * $qty;
                        $avail = $fifoService->checkBahanAvailability($r, $butuh, $gudangCkId);
                        if (!$avail['sufficient']) {
                            $namaBahan = $avail['nama'];
                            $stok = $avail['stok'];
                            throw new \Exception("Stok {$namaBahan} tidak mencukupi (Tersedia: {$stok}, Butuh: {$butuh}).");
                        }
                    }
                }
            }

            // Simpan record Produksi
            $produksiId = DB::table('produksi')->insertGetId([
                'kode_produksi'   => $kodeProduksi,
                'pesanan_id'      => null,
                'tanggal_mulai'   => $request->tanggal_produksi,
                'tanggal_selesai' => $request->tanggal_produksi,
                'status_produksi' => 'Selesai',
                'gudang_bahan_id' => $gudangCkId,
                'gudang_hasil_id' => $gudangCkId,
                'divisi_id'       => $divisiId,
                'created_by'      => auth()->id() ?? 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $fifoService = app(\App\Services\FifoService::class);

            foreach ($request->produk_id as $k => $produkId) {
                $qty = floatval($request->qty_hasil[$k] ?? 0);
                if ($qty <= 0) continue;

                $produk = MasterBarang::find($produkId);
                $totalBbb = 0;

                // Konsumsi bahan baku via FIFO
                $resepId = $produk ? ($produk->resep_id ?: ($produk->resepBtklBop ? $produk->resepBtklBop->id : null)) : null;
                if ($resepId) {
                    foreach (ResepBahanBaku::where('resep_id', $resepId)->with(['bahan', 'alternatif.bahan'])->get() as $r) {
                        $butuh     = floatval($r->qty_bahan) * $qty;
                        
                        $resolved = $fifoService->resolveAlternativeBahan($r, $butuh, $gudangCkId);
                        $resolvedBahanId = $resolved['bahan_id'];

                        $fifoResult = $fifoService->consumeFIFO($resolvedBahanId, $butuh, $gudangCkId);
                        $hppBahan  = 0;
                        foreach ($fifoResult as $layer) {
                            $hppBahan += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                        }
                        $totalBbb += $hppBahan;

                        // Kurangi stok bahan
                        $stokBahan = StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $resolvedBahanId)->first();
                        if ($stokBahan) $stokBahan->decrement('jumlah', $butuh);

                        // Catat TransaksiStok keluar bahan
                        TransaksiStok::create([
                            'tanggal'        => now(),
                            'tipe'           => 'keluar',
                            'source_type'    => 'produksi_internal_ck',
                            'source_id'      => $produksiId,
                            'gudang_asal_id' => $gudangCkId,
                            'barang_id'      => $resolvedBahanId,
                            'qty'            => $butuh,
                            'total_harga'    => $hppBahan,
                            'created_by'     => auth()->id() ?? 1,
                        ]);
                    }
                } else {
                    $totalBbb = floatval($produk->hpp_referensi ?? 0) * $qty;
                }

                $totalBtkl = $totalBbb * 0.30;
                $hppTotal  = $totalBbb + $totalBtkl;
                $hppUnit   = $qty > 0 ? ($hppTotal / $qty) : 0;

                DB::table('produksi_detail')->insert([
                    'produksi_id' => $produksiId,
                    'produk_id'   => $produkId,
                    'qty'         => $qty,
                    'hpp_total'   => $hppTotal,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // Tambah stok BSJ di divisi yang dipilih
                $stokBsj = StokGudang::where('gudang_id', $gudangCkId)
                    ->where('barang_id', $produkId)
                    ->where(function($q) use ($divisiId) {
                        if ($divisiId) {
                            $q->where('divisi_id', $divisiId);
                        } else {
                            $q->whereNull('divisi_id');
                        }
                    })
                    ->first();
                if ($stokBsj) {
                    $stokBsj->increment('jumlah', $qty);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangCkId,
                        'barang_id' => $produkId,
                        'divisi_id' => $divisiId,
                        'jumlah'    => $qty,
                    ]);
                }

                // Batch FIFO masuk
                $supplierId  = DB::table('suppliers')->value('id') ?? 1;
                $pembelianId = DB::table('pembelian')->value('id') ?? 1;
                $pemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;
                StokGudangBatch::create([
                    'gudang_id'           => $gudangCkId,
                    'divisi_id'           => $divisiId,
                    'supplier_id'         => $supplierId,
                    'barang_id'           => $produkId,
                    'pembelian_id'        => $pembelianId,
                    'pembelian_detail_id' => $pemDetailId,
                    'batch_number'        => 'INT-' . $kodeProduksi,
                    'qty_masuk'           => $qty,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qty,
                    'harga_per_qty'       => $hppUnit,
                    'is_habis'            => false,
                ]);

                // Catat TransaksiStok masuk BSJ
                TransaksiStok::create([
                    'tanggal'          => now(),
                    'tipe'             => 'masuk',
                    'source_type'      => 'produksi_internal_ck',
                    'source_id'        => $produksiId,
                    'gudang_tujuan_id' => $gudangCkId,
                    'barang_id'        => $produkId,
                    'qty'              => $qty,
                    'total_harga'      => $hppTotal,
                    'created_by'       => auth()->id() ?? 1,
                ]);
            }

            DB::commit();
            return redirect()->route('ck-produksi.index')->with('success', "Produksi Stok Internal ({$kodeProduksi}) berhasil disimpan. Stok BSJ bertambah di Divisi yang dipilih.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Simpan Produksi Internal: ' . $e->getMessage())->withInput();
        }
    }
}
