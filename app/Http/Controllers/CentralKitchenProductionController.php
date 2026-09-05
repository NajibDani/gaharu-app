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
use Illuminate\Support\Facades\DB;

class CentralKitchenProductionController extends Controller
{
    /**
     * Dashboard & Riwayat Produksi Central Kitchen
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        // Filter WO yang berasal dari pesanan Central Kitchen
        $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
        $gudangCkId = $gudangCk ? $gudangCk->id : 5;

        $queryWo = WorkOrder::with(['details.pesanan.customer', 'details.produk.resep.bahan'])
            ->whereHas('details.pesanan', function($q) {
                $q->where('tipe_pesanan', 'central_kitchen');
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

                // Cek kebutuhan bahan untuk sisa target produksi CK
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

            // Validasi kecukupan bahan baku di Gudang Central Kitchen
            $isBahanSufficient = true;
            $defisitBahan = [];

            foreach ($agregatKebutuhan as $bahanId => $dataBahan) {
                $stokGudang = floatval(StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $bahanId)->value('jumlah') ?? 0);
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

        // Pesanan CK yang pending/siap dibuatkan WO
        $pesananCkPending = Pesanan::centralKitchen()
            ->with(['details.produk.resepBtklBop', 'customer'])
            ->whereIn('status_pesanan', ['pending', 'Draft'])
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
            ->whereHas('pesanan', function($q) {
                $q->where('tipe_pesanan', 'central_kitchen');
            })
            ->orWhereNull('pesanan_id'); // produksi mandiri tanpa pesanan outlet

        if ($search) {
            $queryProduksi->where('kode_produksi', 'like', '%' . $search . '%');
        }

        $riwayatProduksi = $queryProduksi->orderBy('id', 'desc')->paginate(10, ['*'], 'prod_page')->withQueryString();

        // Hitung ketersediaan bahan baku untuk setiap draft riwayat produksi CK
        $riwayatProduksi->getCollection()->transform(function($prod) use ($gudangCkId) {
            $isBahanSufficient = true;
            $defisitBahan = [];
            $fifoService = app(\App\Services\FifoService::class);
            if (strtolower($prod->status_produksi) === 'draft') {
                foreach ($prod->details as $detail) {
                    $produk = MasterBarang::with('resep.bahan')->find($detail->produk_id);
                    if ($produk && $produk->resep_id) {
                        $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
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
            }
            $prod->is_bahan_sufficient = $isBahanSufficient;
            $prod->defisit_bahan = $defisitBahan;
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

        return view('central_kitchen.produksi.index', compact('woList', 'pesananCkPending', 'riwayatProduksi', 'stokBsjPerDivisi'));
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

        $pesanan = Pesanan::centralKitchen()->findOrFail($request->pesanan_id);

        DB::beginTransaction();
        try {
            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 5;

            // Check if there is any quantity to produce
            $hasQtyToProduce = false;
            foreach ($request->produk_id as $key => $produk_id) {
                $qty = floatval($request->qty_rencana[$key] ?? 0);
                if ($qty > 0) {
                    $hasQtyToProduce = true;
                }
            }

            if (!$hasQtyToProduce) {
                $custNama = strtolower($pesanan->customer_nama ?? $pesanan->customer->nama ?? '');
                $targetStatus = str_contains($custNama, 'central kitchen') ? 'Selesai' : 'Siap kirim';
                
                $pesanan->update(['status_pesanan' => $targetStatus]);
                
                DB::commit();
                return redirect()->route('ck-produksi.index')->with('success', 'Stok sudah mencukupi di Gudang CK. Pesanan otomatis dialokasikan dari stok dan siap dikirim tanpa perlu WO baru!');
            }

            // Cek ketersediaan bahan baku di Gudang Central Kitchen
            $isBahanCukup = true;
            foreach ($request->produk_id as $key => $produk_id) {
                $qty = floatval($request->qty_rencana[$key] ?? 0);
                if ($qty <= 0) continue;

                $produk = MasterBarang::with('resep.bahan')->find($produk_id);
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

            foreach ($request->produk_id as $key => $produk_id) {
                if (floatval($request->qty_rencana[$key]) <= 0) continue;

                WorkOrderDetail::create([
                    'work_order_id' => $wo->id,
                    'pesanan_id'    => $pesanan->id,
                    'produk_id'     => $produk_id,
                    'qty_rencana'   => $request->qty_rencana[$key],
                ]);
            }

            $pesanan->update(['status_pesanan' => 'Diproses']);

            DB::commit();
            $msg = $isBahanCukup 
                ? 'Work Order Central Kitchen berhasil dibuat! Bahan baku mencukupi di Gudang CK, siap langsung diproduksi.' 
                : 'Work Order Central Kitchen berhasil dibuat! Bahan baku belum mencukupi, silakan buat permintaan bahan jika diperlukan.';
            return redirect()->route('ck-produksi.index')->with('success', $msg);
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

            // Cek ketersediaan bahan
            foreach ($woDetails as $wod) {
                if ($wod->produk && $wod->produk->resep) {
                    $sudah = DB::table('alokasi_produksi_pesanan')
                        ->where('pesanan_id', $wod->pesanan_id)
                        ->where('produk_id', $wod->produk_id)
                        ->sum('qty_alokasi') ?? 0;
                    $sisa = max(0, floatval($wod->qty_rencana) - floatval($sudah));

                    if ($sisa > 0) {
                        foreach ($wod->produk->resep as $resep) {
                            $kebutuhan = floatval($resep->qty_bahan) * $sisa;
                            $stok = floatval(StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $resep->bahan_id)->value('jumlah') ?? 0);
                            if ($stok < $kebutuhan) {
                                $isBahanSufficient = false;
                                $defisitBahan[] = [
                                    'nama'   => $resep->bahan->nama ?? 'Bahan',
                                    'butuh'  => $kebutuhan,
                                    'stok'   => $stok,
                                    'kurang' => $kebutuhan - $stok,
                                    'satuan' => $resep->bahan->satuan ?? 'pcs',
                                ];
                            }
                        }
                    }
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

        return view('central_kitchen.produksi.create', compact('workOrders', 'selectedWoId', 'items', 'isBahanSufficient', 'defisitBahan', 'divisiCk', 'selectedDivisiId'));
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
        $request->validate([
            'work_order_id'    => 'required',
            'tanggal_produksi' => 'required|date',
            'produk_id'        => 'required|array',
            'qty_hasil'        => 'required|array',
            'divisi_id'        => 'nullable|exists:gudang_divisi,id',
        ]);

        DB::beginTransaction();
        try {
            $wo = WorkOrder::with('details.produk')->findOrFail($request->work_order_id);
            $pesananIdUtama = $wo->details->pluck('pesanan_id')->first();
            $pesanan = Pesanan::find($pesananIdUtama);

            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 5;

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

            // Validasi ketersediaan bahan baku di Gudang CK sebelum eksekusi
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($request->produk_id as $key => $produkId) {
                $qtyHasil = floatval($request->qty_hasil[$key] ?? 0);
                if ($qtyHasil <= 0) continue;

                $produk = MasterBarang::with('resep.bahan')->find($produkId);
                if ($produk && $produk->resep_id) {
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                        
                        $avail = $fifoService->checkBahanAvailability($item, $qtyButuh, $gudangCkId);
                        if (!$avail['sufficient']) {
                            $namaBahan = $avail['nama'];
                            $stokBahan = $avail['stok'];
                            throw new \Exception("Stok {$namaBahan} di Gudang Central Kitchen belum mencukupi (Tersedia: {$stokBahan}, Dibutuhkan: {$qtyButuh}). Silakan minta bahan terlebih dahulu.");
                        }
                    }
                }
            }

            // Kode Produksi
            $kodeProduksi = 'PRD-CK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

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
                $qtyHasil = floatval($request->qty_hasil[$key] ?? 0);
                if ($qtyHasil <= 0) continue;

                $produk = MasterBarang::find($produkId);

                // Validasi agar tidak melebihi sisa target
                $wod = $wo->details->where('produk_id', $produkId)->first();
                $targetRencana = $wod ? floatval($wod->qty_rencana) : $qtyHasil;
                $sudahAlokasi = DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $pesananIdUtama)
                    ->where('produk_id', $produkId)
                    ->sum('qty_alokasi') ?? 0;
                $sisaTarget = max(0, $targetRencana - $sudahAlokasi);

                if ($qtyHasil > $sisaTarget && $sisaTarget > 0) {
                    $namaProd = $produk ? $produk->nama : 'Produk';
                    throw new \Exception("Qty input untuk {$namaProd} ({$qtyHasil}) melebihi sisa kekurangan ({$sisaTarget}).");
                }

                $totalBbbProduk = 0;
                if ($produk && $produk->resep_id) {
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
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

                $isInternalCk = false;
                if ($pesanan) {
                    $custNama = strtolower($pesanan->customer_nama ?? $pesanan->customer->nama ?? '');
                    if (str_contains($custNama, 'central kitchen')) {
                        $isInternalCk = true;
                    }
                }

                // Alokasi pesanan CK
                ProduksiPesanan::create([
                    'produksi_id'       => $produksiId,
                    'pesanan_id'        => $pesananIdUtama,
                    'produk_id'         => $produkId,
                    'qty_alokasi'       => $qtyHasil,
                    'qty_terkirim'      => $isInternalCk ? $qtyHasil : 0,
                    'hpp_per_unit'      => $hppPerUnit,
                    'total_hpp_alokasi' => $hppKeseluruhan,
                ]);
            }

            // Cek apakah seluruh item WO sudah 100% selesai
            $woAllDone = true;
            foreach ($wo->details as $wod) {
                $totalSelesai = DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $pesananIdUtama)
                    ->where('produk_id', $wod->produk_id)
                    ->sum('qty_alokasi') ?? 0;
                if (floatval($totalSelesai) < floatval($wod->qty_rencana)) {
                    $woAllDone = false;
                    break;
                }
            }

            if ($woAllDone) {
                $wo->update(['status_wo' => 'Selesai']);
                if ($pesanan) {
                    $isInternalCk = false;
                    $custNama = strtolower($pesanan->customer_nama ?? $pesanan->customer->nama ?? '');
                    if (str_contains($custNama, 'central kitchen')) {
                        $isInternalCk = true;
                    }
                    $pesanan->update(['status_pesanan' => $isInternalCk ? 'Selesai' : 'Siap kirim']);
                }
            } else {
                $wo->update(['status_wo' => 'Diproses']);
                if ($pesanan && $pesanan->status_pesanan === 'pending') {
                    $pesanan->update(['status_pesanan' => 'Diproses']);
                }
            }

            DB::commit();
            $pesanSukses = $woAllDone 
                ? 'Produksi Central Kitchen berhasil di-approve! Seluruh target WO selesai dan siap dikirim ke outlet.'
                : 'Produksi Central Kitchen berhasil di-approve! Sisa kekurangan target masih dapat diproduksi kembali.';

            return redirect()->route('ck-produksi.index')->with('success', $pesanSukses);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses produksi CK: ' . $e->getMessage());
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

            // Validasi ketersediaan bahan baku sebelum approve
            foreach ($produksi->details as $detail) {
                $produk = MasterBarang::with('resep.bahan')->find($detail->produk_id);
                if ($produk && $produk->resep_id) {
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * floatval($detail->qty);
                        
                        $avail = $fifoService->checkBahanAvailability($item, $qtyButuh, $gudangBahanId);
                        if (!$avail['sufficient']) {
                            $namaBahan = $avail['nama'];
                            $stokBahan = $avail['stok'];
                            throw new \Exception("Stok {$namaBahan} di Gudang Central Kitchen belum mencukupi (Tersedia: {$stokBahan}, Dibutuhkan: {$qtyButuh}).");
                        }
                    }
                }
            }

            foreach ($produksi->details as $detail) {
                $produkId = $detail->produk_id;
                $qtyHasil = floatval($detail->qty);
                $produk   = MasterBarang::find($produkId);

                $totalBbbProduk = 0;
                if ($produk && $produk->resep_id) {
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
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
            $resepItems = ResepBahanBaku::where('resep_id', $p->resep_id)->with(['bahan', 'alternatif.bahan'])->get();
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
                if ($produk && $produk->resep_id) {
                    foreach (ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get() as $r) {
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
                if ($produk && $produk->resep_id) {
                    foreach (ResepBahanBaku::where('resep_id', $produk->resep_id)->with(['bahan', 'alternatif.bahan'])->get() as $r) {
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
