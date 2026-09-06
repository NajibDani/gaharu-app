<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\ResepBahanBaku;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;
use App\Models\Produksi;
use App\Models\ProduksiDetail;
use App\Models\ProduksiPesanan;
use Illuminate\Support\Facades\DB;

class ProduksiController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1. HALAMAN UNIFIED B2B PRODUKSI (Order Masuk, Work Orders, & Riwayat)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $customerId = $request->query('customer_id');

        // 1. Pesanan Cold Kitchen yang Pending / Siap dibuatkan WO
        $pesananQuery = \App\Models\Pesanan::where(function($q) {
                $q->where('tipe_pesanan', 'b2b')->orWhereNull('tipe_pesanan');
            })
            ->with(['details.produk', 'customer'])
            ->whereIn('status_pesanan', ['pending', 'Draft', 'Siap diproduksi']);

        if ($customerId) {
            $pesananQuery->where('customer_id', $customerId);
        }

        $pesananB2BPending = $pesananQuery
            ->orderBy('estimasi_kirim', 'asc')
            ->paginate(10, ['*'], 'pesanan_page')
            ->withQueryString();

        // Hitung sisa kebutuhan WO per pesanan dan per detail
        $pesananB2BPending->getCollection()->transform(function($p) {
            $totalPesananQty = 0;
            $totalSudahWoQty = 0;

            foreach ($p->details as $detail) {
                $sudahWo = WorkOrderDetail::where('pesanan_id', $p->id)
                    ->where('produk_id', $detail->produk_id)
                    ->sum('qty_rencana');
                
                $detail->qty_sudah_wo = floatval($sudahWo);
                $detail->sisa_wo_qty = max(0, floatval($detail->qty) - floatval($sudahWo));

                $totalPesananQty += floatval($detail->qty);
                $totalSudahWoQty += floatval($sudahWo);
            }

            $p->total_sisa_wo = max(0, $totalPesananQty - $totalSudahWoQty);
            $p->is_fully_wo = ($p->total_sisa_wo <= 0 && $totalPesananQty > 0);
            $p->is_partial_wo = ($totalSudahWoQty > 0 && $p->total_sisa_wo > 0);

            return $p;
        });

        // 2. Filter Work Order Cold Kitchen
        $queryWo = WorkOrder::with(['details.pesanan.customer', 'details.produk.resep.bahan'])
            ->where(function($q) {
                $q->whereHas('details.pesanan', function($pq) {
                    $pq->where('tipe_pesanan', 'b2b')->orWhereNull('tipe_pesanan');
                })->orWhereDoesntHave('details.pesanan');
            });

        if ($customerId) {
            $queryWo->whereHas('details.pesanan', function($pq) use ($customerId) {
                $pq->where('customer_id', $customerId);
            });
        }

        if ($search) {
            $queryWo->where('kode_wo', 'like', '%' . $search . '%');
        }

        $woList = $queryWo->latest()->paginate(10, ['*'], 'wo_page')->withQueryString();

        $gudangB2BId = 3; // Gudang B2B

        // Hitung progress produksi & ketersediaan bahan baku untuk setiap WO
        $woList->getCollection()->transform(function($wo) use ($gudangB2BId) {
            $firstDetail = $wo->details->first();
            $customer = $firstDetail && $firstDetail->pesanan ? $firstDetail->pesanan->customer : null;
            $wo->customer_nama = $customer ? ($customer->nama ?? $customer->name ?? 'Customer B2B') : 'Customer B2B';
            $wo->pesanan_kode  = $firstDetail && $firstDetail->pesanan ? $firstDetail->pesanan->kode_pesanan : '-';

            $totalTarget = 0;
            $totalSelesai = 0;
            $totalSisa = 0;

            $itemsProgress = [];
            $agregatKebutuhan = [];

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

                $itemsProgress[] = [
                    'produk_id'    => $wod->produk_id,
                    'kode_barang'  => $wod->produk->kode_barang ?? 'N/A',
                    'nama_produk'  => $wod->produk->nama ?? 'N/A',
                    'satuan'       => $wod->produk->satuan ?? 'pcs',
                    'target'       => $target,
                    'sudah'        => floatval($sudah),
                    'sisa'         => $sisa,
                ];

                // Cek kebutuhan bahan untuk sisa target produksi
                if ($wod->produk && $wod->produk->resep && $sisa > 0) {
                    foreach ($wod->produk->resep as $resep) {
                        $qtyButuh = floatval($resep->qty_bahan) * $sisa;
                        if (!isset($agregatKebutuhan[$resep->bahan_id])) {
                            $agregatKebutuhan[$resep->bahan_id] = [
                                'nama'   => $resep->bahan->nama ?? 'Bahan',
                                'butuh'  => 0,
                                'satuan' => $resep->bahan->satuan ?? 'pcs',
                            ];
                        }
                        $agregatKebutuhan[$resep->bahan_id]['butuh'] += $qtyButuh;
                    }
                }
            }

            // Validasi kecukupan bahan baku di Gudang B2B
            $isBahanSufficient = true;
            $defisitBahan = [];

            foreach ($agregatKebutuhan as $bahanId => $dataBahan) {
                $stokGudang = floatval(StokGudang::where('gudang_id', $gudangB2BId)->where('barang_id', $bahanId)->value('jumlah') ?? 0);
                if ($stokGudang < $dataBahan['butuh']) {
                    $isBahanSufficient = false;
                    $defisitBahan[] = [
                        'nama'   => $dataBahan['nama'],
                        'butuh'  => $dataBahan['butuh'],
                        'stok'   => $stokGudang,
                        'kurang' => $dataBahan['butuh'] - $stokGudang,
                        'satuan' => $dataBahan['satuan'],
                    ];
                }
            }

            $wo->total_target = $totalTarget;
            $wo->total_selesai = $totalSelesai;
            $wo->total_sisa = $totalSisa;
            $wo->items_progress = $itemsProgress;
            $wo->is_all_completed = ($totalSisa <= 0 && $totalTarget > 0);
            $wo->is_bahan_sufficient = $isBahanSufficient;
            $wo->defisit_bahan = $defisitBahan;

            return $wo;
        });

        // 3. Riwayat Produksi Cold Kitchen
        $queryProduksi = Produksi::with(['details.produk', 'pesanan.customer'])
            ->where(function($q) {
                $q->whereHas('pesanan', function($pq) {
                    $pq->where('tipe_pesanan', 'b2b')->orWhereNull('tipe_pesanan');
                })->orWhereDoesntHave('pesanan');
            });

        if ($customerId) {
            $queryProduksi->whereHas('pesanan', function($pq) use ($customerId) {
                $pq->where('customer_id', $customerId);
            });
        }

        if ($search) {
            $queryProduksi->where('kode_produksi', 'like', '%' . $search . '%');
        }

        $riwayatProduksi = $queryProduksi->orderBy('id', 'desc')->paginate(10, ['*'], 'prod_page')->withQueryString();

        $totalData = (clone $queryProduksi)->count();
        $totalDraft = (clone $queryProduksi)->where('status_produksi', 'Draft')->count();
        $totalApproved = (clone $queryProduksi)->where('status_produksi', 'Selesai')->count();

        $customers = \App\Models\Customer::orderBy('nama')->get();

        return view('produksi.index', compact('pesananB2BPending', 'woList', 'riwayatProduksi', 'totalData', 'totalDraft', 'totalApproved', 'customers', 'customerId'));
    }

    /**
     * Buat WO B2B Cepat dari Order Masuk
     */
    public function storeWo(Request $request)
    {
        $request->validate([
            'pesanan_id'  => 'required',
            'produk_id'   => 'required|array',
            'qty_rencana' => 'required|array',
        ]);

        $pesanan = \App\Models\Pesanan::findOrFail($request->pesanan_id);

        DB::beginTransaction();
        try {
            $gudangB2BId = 3;

            // Cek ketersediaan bahan baku di Gudang B2B
            $isBahanCukup = true;
            foreach ($request->produk_id as $key => $produkId) {
                $qty = floatval($request->qty_rencana[$key] ?? 0);
                if ($qty <= 0) continue;

                $produk = MasterBarang::with('resep.bahan')->find($produkId);
                if ($produk && $produk->resep) {
                    foreach ($produk->resep as $resep) {
                        $kebutuhan = floatval($resep->qty_bahan) * $qty;
                        $stok = floatval(StokGudang::where('gudang_id', $gudangB2BId)->where('barang_id', $resep->bahan_id)->value('jumlah') ?? 0);
                        if ($stok < $kebutuhan) {
                            $isBahanCukup = false;
                            break 2;
                        }
                    }
                }
            }

            $wo = WorkOrder::create([
                'kode_wo'    => 'WO-B2B-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'tanggal_wo' => now(),
                'status_wo'  => $isBahanCukup ? 'Diproses' : 'Draft',
                'catatan'    => $request->catatan ?? ($isBahanCukup ? 'Bahan baku mencukupi di Gudang B2B' : 'Bahan baku kurang, menunggu permintaan'),
                'created_by' => auth()->id(),
            ]);

            foreach ($request->produk_id as $key => $produkId) {
                $qty = floatval($request->qty_rencana[$key] ?? 0);
                if ($qty <= 0) continue;

                WorkOrderDetail::create([
                    'work_order_id' => $wo->id,
                    'pesanan_id'    => $pesanan->id,
                    'produk_id'     => $produkId,
                    'qty_rencana'   => $qty,
                ]);
            }

            DB::commit();
            $msg = $isBahanCukup 
                ? 'Work Order B2B (' . $wo->kode_wo . ') berhasil dibuat! Bahan baku mencukupi di Gudang B2B, siap langsung diproduksi.' 
                : 'Work Order B2B (' . $wo->kode_wo . ') berhasil dibuat! Bahan baku belum mencukupi, silakan buat permintaan bahan jika diperlukan.';
            return redirect()->route('produksi.index', ['tab' => 'wo'])->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membuat Work Order: ' . $e->getMessage());
        }
    }

    /**
     * Simpan & Approve Hasil Produksi B2B Sekaligus (Mendukung Parsial & Sisa Target)
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
            return back()->with('error', 'Gagal: Tidak ada Work Order valid yang dipilih.')->withInput();
        }

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_produksi)) {
            return back()->with('error', 'Gagal: Periode akuntansi untuk tanggal ' . date('d/m/Y', strtotime($request->tanggal_produksi)) . ' sudah ditutup buku.')->withInput();
        }

        DB::beginTransaction();
        try {
            $workOrders = WorkOrder::with('details.produk')->whereIn('id', $woIds)->get();
            if ($workOrders->isEmpty()) {
                throw new \Exception('Work Order yang dipilih tidak ditemukan.');
            }

            $pesananIdUtama = $workOrders->pluck('details')->flatten()->pluck('pesanan_id')->filter()->first();
            $pesanan = \App\Models\Pesanan::find($pesananIdUtama);

            $gudangBahan = MasterGudang::where('kategori', 'Produksi')->first() 
                ?? MasterGudang::where('nama', 'like', '%Produksi%')->first()
                ?? MasterGudang::first();
            $gudangBahanId = $gudangBahan ? $gudangBahan->id : 3;

            $gudangHasil = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first()
                ?? MasterGudang::where('kategori', 'Produksi')->first()
                ?? $gudangBahan;
            $gudangHasilId = $gudangHasil ? $gudangHasil->id : 3;

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

            $kodeProduksi = 'PRD-BATCH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $produksiId = DB::table('produksi')->insertGetId([
                'kode_produksi'   => $kodeProduksi,
                'pesanan_id'      => $pesananIdUtama,
                'tanggal_mulai'   => $request->tanggal_produksi,
                'tanggal_selesai' => now(),
                'status_produksi' => 'Selesai',
                'gudang_bahan_id' => $gudangBahanId,
                'gudang_hasil_id' => $gudangHasilId,
                'created_by'      => auth()->id() ?? 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $fifoService = app(\App\Services\FifoService::class);

            foreach ($request->produk_id as $key => $produkId) {
                $qtyHasil = floatval($request->qty_hasil[$key] ?? 0);
                if ($qtyHasil <= 0) continue;

                $produk = MasterBarang::find($produkId);
                if (!$produk) {
                    throw new \Exception("ID Produk {$produkId} tidak valid.");
                }

                $totalBbbProduk = 0;
                if ($produk->resep_id) {
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                        
                        // Tentukan bahan yang dipakai (utama atau alternatif)
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
                            'tanggal'        => $request->tanggal_produksi,
                            'tipe'           => 'keluar',
                            'source_type'    => 'produksi',
                            'source_id'      => $produksiId,
                            'gudang_asal_id' => $gudangBahanId,
                            'barang_id'      => $resolvedBahanId,
                            'qty'            => $qtyButuh,
                            'total_harga'    => $hppBahan,
                            'created_by'     => auth()->id() ?? 1,
                        ]);
                    }
                } else {
                    $totalBbbProduk = floatval($produk->hpp_referensi ?? 0) * $qtyHasil;
                }

                // BTKL & BOP
                $biayaTambahan = DB::table('resep_btkl_bop')->where('produk_id', $produkId)->first();
                if ($biayaTambahan) {
                    $outputQty = floatval($biayaTambahan->output_qty ?? 1) > 0 ? floatval($biayaTambahan->output_qty) : 1;
                    $btklNominal = floatval($biayaTambahan->btkl_per_batch ?? ($biayaTambahan->btkl ?? 0));
                    $bopNominal  = floatval($biayaTambahan->bop_per_batch ?? ($biayaTambahan->bop ?? 0));
                    $btklPerUnit = $btklNominal / $outputQty;
                    $bopPerUnit  = $bopNominal / $outputQty;
                    $totalBtklBop = ($btklPerUnit + $bopPerUnit) * $qtyHasil;
                } else {
                    $totalBtklBop = $totalBbbProduk * 0.30;
                }

                $totalHppProduk = $totalBbbProduk + $totalBtklBop;
                $hppPerUnit = $qtyHasil > 0 ? ($totalHppProduk / $qtyHasil) : 0;

                DB::table('produksi_detail')->insert([
                    'produksi_id' => $produksiId,
                    'produk_id'   => $produkId,
                    'qty'         => $qtyHasil,
                    'hpp_total'   => $totalHppProduk,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // Tambah stok hasil jadi
                $stokJadi = StokGudang::where('gudang_id', $gudangHasilId)->where('barang_id', $produkId)->first();
                if ($stokJadi) {
                    $stokJadi->increment('jumlah', $qtyHasil);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangHasilId,
                        'barang_id' => $produkId,
                        'jumlah'    => $qtyHasil,
                    ]);
                }

                // Buat Batch Stok Jadi
                $supplierId  = DB::table('suppliers')->value('id') ?? 1;
                $pembelianId = DB::table('pembelian')->value('id') ?? 1;
                $pemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                StokGudangBatch::create([
                    'gudang_id'           => $gudangHasilId,
                    'supplier_id'         => $supplierId,
                    'barang_id'           => $produkId,
                    'pembelian_id'        => $pembelianId,
                    'pembelian_detail_id' => $pemDetailId,
                    'batch_number'        => 'PROD-' . $kodeProduksi,
                    'qty_masuk'           => $qtyHasil,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyHasil,
                    'harga_per_qty'       => $hppPerUnit,
                    'is_habis'            => false,
                ]);

                // Transaksi stok masuk
                TransaksiStok::create([
                    'tanggal'          => $request->tanggal_produksi,
                    'tipe'             => 'masuk',
                    'source_type'      => 'produksi',
                    'source_id'        => $produksiId,
                    'gudang_tujuan_id' => $gudangHasilId,
                    'barang_id'        => $produkId,
                    'qty'              => $qtyHasil,
                    'total_harga'      => $totalHppProduk,
                    'created_by'       => auth()->id() ?? 1,
                ]);

                // Alokasi pesanan secara fleksibel ke seluruh WO yang dipilih dalam batch ini
                $qtySisaAlokasi = $qtyHasil;
                $wodList = DB::table('work_order_detail')
                    ->whereIn('work_order_id', $woIds)
                    ->where('produk_id', $produkId)
                    ->get();

                foreach ($wodList as $wod) {
                    if ($qtySisaAlokasi <= 0) break;

                    $sudah = DB::table('alokasi_produksi_pesanan')
                        ->where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $wod->produk_id)
                        ->sum('qty_alokasi') ?? 0;

                    $kurangWod = max(0, floatval($wod->qty_rencana) - floatval($sudah));
                    if ($kurangWod <= 0) continue;

                    $porsi = min($qtySisaAlokasi, $kurangWod);

                    ProduksiPesanan::create([
                        'produksi_id'       => $produksiId,
                        'pesanan_id'        => $wod->pesanan_id,
                        'produk_id'         => $produkId,
                        'qty_alokasi'       => $porsi,
                        'qty_terkirim'      => 0,
                        'hpp_per_unit'      => $hppPerUnit,
                        'total_hpp_alokasi' => $hppPerUnit * $porsi,
                    ]);

                    $qtySisaAlokasi -= $porsi;
                }
            }

            // Update status seluruh Work Order yang diproses dalam batch ini
            foreach ($workOrders as $wo) {
                $allDone = true;
                foreach ($wo->details as $wod) {
                    $totalTarget = floatval($wod->qty_rencana);
                    $sudahAlokasi = DB::table('alokasi_produksi_pesanan')
                        ->where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $wod->produk_id)
                        ->sum('qty_alokasi') ?? 0;
                    if ($sudahAlokasi < $totalTarget) {
                        $allDone = false;
                        break;
                    }
                }

                $wo->update(['status_wo' => $allDone ? 'Selesai' : 'Diproses']);
                
                $pId = $wo->details->pluck('pesanan_id')->first();
                if ($pId && $allDone) {
                    $pObj = \App\Models\Pesanan::find($pId);
                    if ($pObj) {
                        $pObj->update(['status_pesanan' => 'Siap kirim']);
                    }
                }
            }

            DB::commit();
            return redirect()->route('produksi.index', ['tab' => 'prod'])->with('success', 'Hasil Produksi Batch B2B (' . count($workOrders) . ' WO) berhasil disimpan dan di-Approve! HPP dan stok telah diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses hasil produksi batch: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2. HALAMAN FORM INPUT DRAFT PRODUKSI (Mendukung Parsial & Sisa Target)
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $workOrders = WorkOrder::where('status_wo', 'Diproses')->get();
        $gudangs = DB::table('master_gudang')->get();

        $selectedWoId = $request->get('work_order_id');
        $items = collect();

        if ($selectedWoId) {
            $woDetails = WorkOrderDetail::where('work_order_id', $selectedWoId)
                ->with('produk')
                ->get();

            $groupedItems = [];

            // Petakan grup produk & hitung total rencana targetnya
            foreach ($woDetails as $wod) {
                $pid = $wod->produk_id;
                
                if (!isset($groupedItems[$pid])) {
                    $groupedItems[$pid] = [
                        'produk_id'        => $pid,
                        'produk'           => $wod->produk,
                        'total_target'     => 0,
                        'sudah_diproduksi' => 0,
                    ];
                }
                
                $groupedItems[$pid]['total_target'] += $wod->qty_rencana;
            }

            // Hitung akumulasi produksi riil yang sudah tersimpan untuk WO ini
            foreach ($groupedItems as $pid => $data) {
                $currentPesananIds = $woDetails->where('produk_id', $pid)
                    ->pluck('pesanan_id')
                    ->filter()
                    ->toArray();

                $terproduksi = 0;

                if (!empty($currentPesananIds)) {
                    $terproduksi = DB::table('alokasi_produksi_pesanan')
                        ->whereIn('pesanan_id', $currentPesananIds)
                        ->where('produk_id', $pid)
                        ->sum('qty_alokasi');
                } else {
                    $pesananIdsAll = $woDetails->pluck('pesanan_id')->filter()->toArray();
                    $pesananIdUtama = !empty($pesananIdsAll) ? $pesananIdsAll[0] : null;

                    if ($pesananIdUtama) {
                        $terproduksi = DB::table('alokasi_produksi_pesanan')
                            ->join('produksi', 'alokasi_produksi_pesanan.produksi_id', '=', 'produksi.id')
                            ->where('produksi.pesanan_id', $pesananIdUtama)
                            ->where('alokasi_produksi_pesanan.produk_id', $pid)
                            ->whereNull('alokasi_produksi_pesanan.pesanan_id')
                            ->sum('alokasi_produksi_pesanan.qty_alokasi');
                    }
                }

                $groupedItems[$pid]['sudah_diproduksi'] = $terproduksi;
            }

            // Format data sisa target untuk dikirim ke blade view
            $items = collect($groupedItems)->map(function ($item) {
                $sisa = $item['total_target'] - $item['sudah_diproduksi'];
                return (object) [
                    'produk_id'        => $item['produk_id'],
                    'produk'           => $item['produk'],
                    'total_target'     => $item['total_target'],
                    'sudah_diproduksi' => $item['sudah_diproduksi'],
                    'sisa_target'      => $sisa > 0 ? $sisa : 0,
                ];
            })->filter(function($item) {
                return $item->sisa_target > 0; 
            })->values();
        }

        return view('produksi.create', compact('workOrders', 'gudangs', 'selectedWoId', 'items'));
    }

    /*
    |--------------------------------------------------------------------------
    | 3. SIMPAN DRAFT PRODUKSI (Tanpa kolom work_order_id di DB)
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'work_order_id'    => 'required',
            'tanggal_produksi' => 'required|date',
            'produk_id'        => 'required|array',
            'qty_hasil'        => 'required|array',
        ]);

        // =========================================================================
        // BLOK VALIDASI CEK SISA TARGET (Mencegah input melebihi sisa WO)
        // =========================================================================
        $woDetails = DB::table('work_order_detail')->where('work_order_id', $request->work_order_id)->get();
        
        foreach ($request->produk_id as $key => $produkId) {
            $qtyInput = floatval($request->qty_hasil[$key]);
            if ($qtyInput <= 0) continue;

            $totalTarget = $woDetails->where('produk_id', $produkId)->sum('qty_rencana');

            $currentPesananIds = $woDetails->where('produk_id', $produkId)
                ->pluck('pesanan_id')->filter()->toArray();

            $terproduksi = 0;
            if (!empty($currentPesananIds)) {
                $terproduksi = DB::table('alokasi_produksi_pesanan')
                    ->whereIn('pesanan_id', $currentPesananIds)
                    ->where('produk_id', $produkId)
                    ->sum('qty_alokasi');
            } else {
                $pesananIdsAll = $woDetails->pluck('pesanan_id')->filter()->toArray();
                $pesananIdUtama = !empty($pesananIdsAll) ? $pesananIdsAll[0] : null;

                if ($pesananIdUtama) {
                    $terproduksi = DB::table('alokasi_produksi_pesanan')
                        ->join('produksi', 'alokasi_produksi_pesanan.produksi_id', '=', 'produksi.id')
                        ->where('produksi.pesanan_id', $pesananIdUtama)
                        ->where('alokasi_produksi_pesanan.produk_id', $produkId)
                        ->whereNull('alokasi_produksi_pesanan.pesanan_id')
                        ->sum('alokasi_produksi_pesanan.qty_alokasi');
                }
            }

            $sisaTarget = $totalTarget - $terproduksi;

            if ($qtyInput > $sisaTarget) {
                $namaProduk = DB::table('master_barang')->where('id', $produkId)->value('nama');
                return redirect()->back()->with('error', "Gagal Simpan! Jumlah produk '{$namaProduk}' ({$qtyInput} unit) melebihi sisa target. Maksimal yang bisa diinput adalah {$sisaTarget} unit.");
            }
        }
        // =========================================================================

        DB::beginTransaction();

        try {
            // Ambil referensi pesanan dari Work Order
            $pesananIds = DB::table('work_order_detail')
                ->where('work_order_id', $request->work_order_id)
                ->pluck('pesanan_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $pesananIdUtama = !empty($pesananIds) ? $pesananIds[0] : null;

            if (!$pesananIdUtama) {
                return redirect()->back()->with('error', 'Tidak dapat membuat Draft: Work Order ini tidak memiliki referensi Pesanan.');
            }

            // Simpan header sebagai DRAFT (HPP = 0, Stok belum dipotong)
            $produksiId = DB::table('produksi')->insertGetId([
                'kode_produksi'   => 'PRD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'pesanan_id'      => $pesananIdUtama,
                'tanggal_mulai'   => $request->tanggal_produksi,
                'tanggal_selesai' => null, 
                'status_produksi' => 'Draft', 
                'gudang_bahan_id' => 3,
                'gudang_hasil_id' => 3,
                'created_by'      => auth()->id() ?? 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            foreach ($request->produk_id as $key => $produkId) {
                $qtyHasil = floatval($request->qty_hasil[$key]);
                if ($qtyHasil <= 0) continue;

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
            return redirect()->route('produksi.index')->with('success', 'Draft Produksi berhasil disimpan. Data masih bisa diedit sebelum di-Approve.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Simpan Draft! Pesan: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4. HALAMAN EDIT DRAFT
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $produksi = Produksi::with('details.produk')->findOrFail($id);
        return view('produksi.edit', compact('produksi'));
    }

    /*
    |--------------------------------------------------------------------------
    | 5. UPDATE DRAFT PRODUKSI (Edit qty hasil fisik sebelum approve)
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $produksi = Produksi::findOrFail($id);

        if ($produksi->status_produksi !== 'Draft') {
            return redirect()->back()->with('error', 'Data sudah di-Approve dan tidak dapat diedit.');
        }

        // =========================================================================
        // BLOK VALIDASI CEK SISA TARGET SAAT UPDATE
        // =========================================================================
        $wodUtama = DB::table('work_order_detail')
            ->where('pesanan_id', $produksi->pesanan_id)
            ->orderBy('id', 'desc')
            ->first();

        if ($wodUtama) {
            $workOrderId = $wodUtama->work_order_id;
            $woDetails = DB::table('work_order_detail')->where('work_order_id', $workOrderId)->get();

            foreach ($request->produk_id as $key => $produkId) {
                $qtyInput = floatval($request->qty_hasil[$key]);
                if ($qtyInput <= 0) continue;

                $totalTarget = $woDetails->where('produk_id', $produkId)->sum('qty_rencana');

                $currentPesananIds = $woDetails->where('produk_id', $produkId)
                    ->pluck('pesanan_id')->filter()->toArray();

                $terproduksi = 0;
                if (!empty($currentPesananIds)) {
                    $terproduksi = DB::table('alokasi_produksi_pesanan')
                        ->whereIn('pesanan_id', $currentPesananIds)
                        ->where('produk_id', $produkId)
                        ->sum('qty_alokasi');
                } else {
                    $pesananIdsAll = $woDetails->pluck('pesanan_id')->filter()->toArray();
                    $pesananIdUtamaCek = !empty($pesananIdsAll) ? $pesananIdsAll[0] : null;

                    if ($pesananIdUtamaCek) {
                        $terproduksi = DB::table('alokasi_produksi_pesanan')
                            ->join('produksi', 'alokasi_produksi_pesanan.produksi_id', '=', 'produksi.id')
                            ->where('produksi.pesanan_id', $pesananIdUtamaCek)
                            ->where('alokasi_produksi_pesanan.produk_id', $produkId)
                            ->whereNull('alokasi_produksi_pesanan.pesanan_id')
                            ->sum('alokasi_produksi_pesanan.qty_alokasi');
                    }
                }

                $sisaTarget = $totalTarget - $terproduksi;

                if ($qtyInput > $sisaTarget) {
                    $namaProduk = DB::table('master_barang')->where('id', $produkId)->value('nama');
                    return redirect()->back()->with('error', "Gagal Edit! Jumlah produk '{$namaProduk}' ({$qtyInput} unit) melebihi sisa target. Maksimal yang bisa diinput adalah {$sisaTarget} unit.");
                }
            }
        }
        // =========================================================================

        DB::beginTransaction();
        try {
            DB::table('produksi_detail')->where('produksi_id', $id)->delete();

            foreach ($request->produk_id as $key => $produkId) {
                $qtyHasil = floatval($request->qty_hasil[$key]);
                if ($qtyHasil <= 0) continue;

                DB::table('produksi_detail')->insert([
                    'produksi_id' => $id,
                    'produk_id'   => $produkId,
                    'qty'         => $qtyHasil,
                    'hpp_total'   => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            $produksi->update(['tanggal_mulai' => $request->tanggal_produksi]);

            DB::commit();
            return redirect()->route('produksi.index')->with('success', 'Draft Produksi berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update draft: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6. HAPUS DRAFT PRODUKSI
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $produksi = Produksi::findOrFail($id);

        if ($produksi->status_produksi !== 'Draft') {
            return redirect()->back()->with('error', 'Tidak dapat menghapus produksi yang sudah di-Approve (Terkunci).');
        }

        DB::table('produksi_detail')->where('produksi_id', $id)->delete();
        $produksi->delete();

        return redirect()->route('produksi.index')->with('success', 'Draft Produksi berhasil dihapus secara permanen.');
    }

    /*
    |--------------------------------------------------------------------------
    | 7. APPROVE PRODUKSI (Tahap Final - Hitung FIFO HPP, Potong Stok, Jembatan WO)
    |--------------------------------------------------------------------------
    */
    public function approve(Request $request, $id)
    {
        // Ambil data draft produksi beserta item detail fisiknya
        $produksi = Produksi::with('details')->findOrFail($id);

        if ($produksi->status_produksi !== 'Draft') {
            return redirect()->back()->with('error', 'Data ini sudah disetujui sebelumnya dan terkunci.');
        }

        DB::beginTransaction();

        try {
            $gudangBahanId = $produksi->gudang_bahan_id;
            $gudangHasilId = $produksi->gudang_hasil_id;
            $fifoService   = app(\App\Services\FifoService::class);

            // --- LOGIKA JEMBATAN PELACAK WORK ORDER ID ---
            $wodUtama = DB::table('work_order_detail')
                ->where('pesanan_id', $produksi->pesanan_id)
                ->orderBy('id', 'desc')
                ->first();
                
            if (!$wodUtama) {
                throw new \Exception('Tidak dapat menemukan Work Order yang terkait dengan pesanan ini.');
            }
            $workOrderId = $wodUtama->work_order_id;
            // ----------------------------------------------

            // Ambil semua pesanan yang tergabung dalam Work Order tersebut
            $pesananIds = DB::table('work_order_detail')
                ->where('work_order_id', $workOrderId)
                ->pluck('pesanan_id')
                ->unique()
                ->values()
                ->toArray();

            // Eksekusi perhitungan per item produk hasil produksi
            foreach ($produksi->details as $detail) {
                $produkId = $detail->produk_id;
                $qtyHasil = floatval($detail->qty);
                $produk   = MasterBarang::find($produkId);

                if (!$produk) {
                    throw new \Exception("ID Produk {$produkId} tidak valid.");
                }

                if (is_null($produk->resep_id)) {
                    throw new \Exception("Produk '{$produk->nama}' belum memiliki resep.");
                }

                $totalBbbProduk = 0;

                $biayaTambahan = DB::table('resep_btkl_bop')->where('produk_id', $produkId)->first();
                $outputQty = ($biayaTambahan && floatval($biayaTambahan->output_qty) > 0) ? floatval($biayaTambahan->output_qty) : 1;

                // A. FIFO BAHAN BAKU
                $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();

                foreach ($resepItems as $item) {
                    $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;

                    // Tentukan bahan yang dipakai (utama atau alternatif)
                    $resolved = $fifoService->resolveAlternativeBahan($item, $qtyButuh, $gudangBahanId);
                    $resolvedBahanId = $resolved['bahan_id'];

                    $fifoResult = $fifoService->consumeFIFO(
                        $resolvedBahanId,
                        $qtyButuh,
                        $gudangBahanId
                    );

                    $hppBahan = 0;
                    foreach ($fifoResult as $layer) {
                        $hppBahan += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                    }
                    $totalBbbProduk += $hppBahan;

                    $stokBahanGlobal = StokGudang::where('gudang_id', $gudangBahanId)
                        ->where('barang_id', $resolvedBahanId)
                        ->first();

                    if ($stokBahanGlobal) {
                        $stokBahanGlobal->decrement('jumlah', $qtyButuh);
                    } else {
                        StokGudang::create([
                            'gudang_id' => $gudangBahanId,
                            'barang_id' => $resolvedBahanId,
                            'jumlah'    => 0 - $qtyButuh,
                        ]);
                    }

                    // Catat transaksi keluar bahan baku untuk Buku Pembantu Persediaan
                    TransaksiStok::create([
                        'tanggal'        => now(),
                        'tipe'           => 'keluar',
                        'source_type'    => 'produksi',
                        'source_id'      => $produksi->id,
                        'gudang_asal_id' => $gudangBahanId,
                        'barang_id'      => $resolvedBahanId,
                        'qty'            => $qtyButuh,
                        'total_harga'    => $hppBahan,
                        'created_by'     => auth()->id() ?? 1,
                    ]);
                }

                // B. HITUNG BTKL & BOP (30% dari Total BBB)
                $totalBtklBop = $totalBbbProduk * 0.30;

                // C. HITUNG TOTAL HPP & UPDATE KE DETAIL PRODUKSI
                $hppKeseluruhan = $totalBbbProduk + $totalBtklBop;
                $hppPerUnit     = $qtyHasil > 0 ? ($hppKeseluruhan / $qtyHasil) : 0;

                DB::table('produksi_detail')
                    ->where('id', $detail->id)
                    ->update([
                        'hpp_total'  => $hppKeseluruhan,
                        'updated_at' => now()
                    ]);

                // D. TAMBAH STOK BARANG JADI KE GUDANG HASIL (STOK, BATCH & LOG TRANSAKSI)
                $stokBarangJadi = StokGudang::where('gudang_id', $gudangHasilId)
                    ->where('barang_id', $produkId)
                    ->first();

                if ($stokBarangJadi) {
                    $stokBarangJadi->increment('jumlah', $qtyHasil);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangHasilId,
                        'barang_id' => $produkId,
                        'jumlah'    => $qtyHasil,
                    ]);
                }

                $defaultSupplierId  = DB::table('suppliers')->value('id') ?? 1;
                $defaultPembelianId = DB::table('pembelian')->value('id') ?? 1;
                $defaultPemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                StokGudangBatch::create([
                    'gudang_id'           => $gudangHasilId,
                    'supplier_id'         => $defaultSupplierId,
                    'barang_id'           => $produkId,
                    'pembelian_id'        => $defaultPembelianId,
                    'pembelian_detail_id' => $defaultPemDetailId,
                    'batch_number'        => 'PROD-' . $produksi->kode_produksi,
                    'qty_masuk'           => $qtyHasil,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyHasil,
                    'harga_per_qty'       => $hppPerUnit,
                    'is_habis'            => false,
                ]);

                TransaksiStok::create([
                    'tanggal'          => now(),
                    'tipe'             => 'masuk',
                    'source_type'      => 'produksi',
                    'source_id'        => $produksi->id,
                    'gudang_tujuan_id' => $gudangHasilId,
                    'barang_id'        => $produkId,
                    'qty'              => $qtyHasil,
                    'total_harga'      => $hppKeseluruhan,
                    'created_by'       => auth()->id() ?? 1,
                ]);

                // E. DISTRIBUSI ALOKASI PESANAN DALAM WO
                // Prioritas: pesanan dengan estimasi_kirim PALING DEKAT/sudah lewat
                // dialokasikan lebih dulu. Detail tanpa relasi pesanan (mis. WO
                // gabungan tanpa referensi pesanan spesifik) ditaruh paling akhir.
                //
                // Sisa kebutuhan dihitung dari (qty_rencana - qty yang SUDAH
                // teralokasi dari batch produksi sebelumnya), bukan qty_rencana
                // mentah. Ini penting untuk input produksi parsial/bertahap:
                // batch baru tidak boleh menumpuk alokasi ke pesanan yang qty
                // rencananya sudah terpenuhi di batch-batch sebelumnya.
                $sisaBarangSiapBagi = $qtyHasil;

                $detailPesananWO = WorkOrderDetail::with('pesanan')
                    ->where('work_order_id', $workOrderId)
                    ->where('produk_id', $produkId)
                    ->get()
                    ->sort(function ($a, $b) {
                        $tglA = optional($a->pesanan)->estimasi_kirim;
                        $tglB = optional($b->pesanan)->estimasi_kirim;

                        if (is_null($tglA) && is_null($tglB)) {
                            return $a->id <=> $b->id;
                        }
                        if (is_null($tglA)) return 1;  // tanpa pesanan -> ke belakang
                        if (is_null($tglB)) return -1;

                        return $tglA <=> $tglB ?: $a->id <=> $b->id;
                    })
                    ->values();

                foreach ($detailPesananWO as $detailWO) {
                    if ($sisaBarangSiapBagi <= 0) break;

                    $qtyRencanaWO = floatval($detailWO->qty_rencana);

                    // Qty yang sudah teralokasi ke pesanan+produk ini dari
                    // batch-batch produksi sebelumnya (jika ada).
                    $sudahTeralokasi = DB::table('alokasi_produksi_pesanan')
                        ->where('pesanan_id', $detailWO->pesanan_id)
                        ->where('produk_id', $produkId)
                        ->sum('qty_alokasi');

                    $sisaKebutuhanDetail = $qtyRencanaWO - $sudahTeralokasi;
                    if ($sisaKebutuhanDetail <= 0) continue;

                    $qtyAlokasi = min($sisaKebutuhanDetail, $sisaBarangSiapBagi);

                    if ($qtyAlokasi > 0) {
                        ProduksiPesanan::create([
                            'produksi_id'       => $produksi->id,
                            'pesanan_id'        => $detailWO->pesanan_id,
                            'produk_id'         => $produkId,
                            'qty_alokasi'       => $qtyAlokasi,
                            'qty_terkirim'      => 0,
                            'hpp_per_unit'      => $hppPerUnit,
                            'total_hpp_alokasi' => $qtyAlokasi * $hppPerUnit,
                        ]);

                        $sisaBarangSiapBagi -= $qtyAlokasi;
                    }
                }
            }

            // F. VALIDASI STATUS AKUMULASI KETERPENUHAN (WO & PESANAN)
            $semuaWODetail = DB::table('work_order_detail')->where('work_order_id', $workOrderId)->get();
            $woSelesaiSempurna = true;

            foreach ($semuaWODetail as $wod) {
                $totalAlokasiTercatat = DB::table('alokasi_produksi_pesanan')
                    ->where('produk_id', $wod->produk_id)
                    ->where('pesanan_id', $wod->pesanan_id)
                    ->sum('qty_alokasi');

                if ($totalAlokasiTercatat < $wod->qty_rencana) {
                    $woSelesaiSempurna = false;
                }
            }

            DB::table('work_order')
                ->where('id', $workOrderId)
                ->update([
                    'status_wo'  => $woSelesaiSempurna ? 'Selesai' : 'Diproses',
                    'updated_at' => now(),
                ]);

            if (!empty($pesananIds)) {
                foreach ($pesananIds as $pesananId) {
                    $detailPesanan = DB::table('pesanan_detail')->where('pesanan_id', $pesananId)->get();
                    $pesananSelesaiSempurna = true;

                    foreach ($detailPesanan as $dp) {
                        $totalAlokasiPesanan = DB::table('alokasi_produksi_pesanan')
                            ->where('pesanan_id', $pesananId)
                            ->where('produk_id', $dp->produk_id)
                            ->sum('qty_alokasi');

                        if ($totalAlokasiPesanan < $dp->qty) {
                            $pesananSelesaiSempurna = false;
                            break; 
                        }
                    }

                    DB::table('pesanan')
                        ->where('id', $pesananId)
                        ->update([
                            'status_pesanan' => $pesananSelesaiSempurna ? 'Siap kirim' : 'Diproses',
                            'updated_at'     => now(),
                        ]);
                }
            }

            // G. UPDATE DATA UTAMA PRODUKSI DARI DRAFT MENJADI SELESAI
            DB::table('produksi')
                ->where('id', $produksi->id)
                ->update([
                    'status_produksi' => 'Selesai',
                    'tanggal_selesai' => now(),
                    'updated_at'      => now(),
                ]);

            DB::commit();
            return redirect()->route('produksi.index')->with('success', 'Produksi berhasil disetujui! Seluruh stok gudang, FIFO, HPP, dan status pesanan telah diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Approve! Pesan: ' . $e->getMessage());
        }
    }

/*
    |--------------------------------------------------------------------------
    | HALAMAN DETAIL PRODUKSI
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        // Pastikan relasi ke detail, produk, pesanan, dan customer dimuat
        $produksi = Produksi::with(['details.produk', 'pesanan.customer'])->findOrFail($id);
        
        // Ambil nama gudang hasil secara manual agar tidak perlu repot mengubah Model
        $gudangHasil = DB::table('master_gudang')->where('id', $produksi->gudang_hasil_id)->first();
        $namaGudang = $gudangHasil ? $gudangHasil->nama : 'Gudang Tidak Diketahui';
        
        return view('produksi.show', compact('produksi', 'namaGudang'));
    }

    public function cetakPdf($id)
    {
        $produksi = Produksi::with(['details.produk', 'pesanan.customer'])->findOrFail($id);
        $gudangHasil = DB::table('master_gudang')->where('id', $produksi->gudang_hasil_id)->first();
        $namaGudang = $gudangHasil ? $gudangHasil->nama : 'Gudang Tidak Diketahui';

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('produksi.show-pdf', compact('produksi', 'namaGudang'));
        return $pdf->download('produksi-' . $produksi->kode_produksi . '.pdf');
    }
}