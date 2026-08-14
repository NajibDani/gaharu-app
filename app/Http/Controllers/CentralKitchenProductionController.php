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
            ->with(['details.produk', 'customer'])
            ->whereIn('status_pesanan', ['pending', 'Draft'])
            ->orderBy('estimasi_kirim', 'asc')
            ->paginate(10, ['*'], 'pesanan_page')
            ->withQueryString();

        // Riwayat Produksi CK dengan detail produk & pesanan
        $queryProduksi = Produksi::with(['details.produk', 'pesanan.customer'])
            ->whereHas('pesanan', function($q) {
                $q->where('tipe_pesanan', 'central_kitchen');
            });

        if ($search) {
            $queryProduksi->where('kode_produksi', 'like', '%' . $search . '%');
        }

        $riwayatProduksi = $queryProduksi->orderBy('id', 'desc')->paginate(10, ['*'], 'prod_page')->withQueryString();

        return view('central_kitchen.produksi.index', compact('woList', 'pesananCkPending', 'riwayatProduksi'));
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
     * Kirim/Minta Bahan Baku dari Gudang Utama ke Gudang Central Kitchen
     */
    public function kirimBahanBaku($woId)
    {
        $wo = WorkOrder::with('details.produk.resep.bahan')->findOrFail($woId);

        DB::beginTransaction();
        try {
            $gudangCk = MasterGudang::where('nama', 'Gudang Central Kitchen')->first()
                ?? MasterGudang::where('kategori', 'Produksi')->first();

            if (!$gudangCk) {
                throw new \Exception('Gudang Central Kitchen belum tersedia di Master Gudang.');
            }

            $pengeluaran = \App\Models\PengeluaranBahanBaku::create([
                'kode_pengeluaran' => 'REQ-CK-' . date('Ymd') . '-' . strtoupper(\Str::random(4)),
                'tanggal'          => now(),
                'gudang_id'        => $gudangCk->id,
                'status'           => 'Draft',
                'keterangan'       => 'Permintaan bahan baku CK untuk ' . $wo->kode_wo,
                'created_by'       => auth()->id(),
            ]);

            $agregatBahan = [];
            foreach ($wo->details as $detail) {
                if (!$detail->produk || !$detail->produk->resep) continue;

                foreach ($detail->produk->resep as $resep) {
                    $qtyKebutuhan = $resep->qty_bahan * $detail->qty_rencana;
                    if (!isset($agregatBahan[$resep->bahan_id])) {
                        $agregatBahan[$resep->bahan_id] = [
                            'qty'    => 0,
                            'satuan' => $resep->bahan->satuan ?? '-',
                        ];
                    }
                    $agregatBahan[$resep->bahan_id]['qty'] += $qtyKebutuhan;
                }
            }

            foreach ($agregatBahan as $bahanId => $data) {
                \App\Models\PengeluaranBahanBakuDetail::create([
                    'pengeluaran_id' => $pengeluaran->id,
                    'barang_id'      => $bahanId,
                    'qty'            => $data['qty'],
                    'satuan'         => $data['satuan'],
                ]);
            }

            $wo->update(['status_wo' => 'Diproses']);

            DB::commit();
            return redirect()->back()->with('success', 'Permintaan bahan baku untuk Central Kitchen berhasil dibuat.');
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

        $items = collect();
        if ($selectedWoId) {
            $woDetails = WorkOrderDetail::where('work_order_id', $selectedWoId)->with('produk')->get();
            $items = $woDetails->map(function($wod) {
                return (object) [
                    'produk_id'    => $wod->produk_id,
                    'produk'       => $wod->produk,
                    'total_target' => $wod->qty_rencana,
                    'sisa_target'  => $wod->qty_rencana,
                ];
            });
        }

        return view('central_kitchen.produksi.create', compact('workOrders', 'selectedWoId', 'items'));
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
        ]);

        DB::beginTransaction();
        try {
            $wo = WorkOrder::with('details.produk')->findOrFail($request->work_order_id);
            $pesananIdUtama = $wo->details->pluck('pesanan_id')->first();
            $pesanan = Pesanan::find($pesananIdUtama);

            $gudangCk = MasterGudang::where('nama', 'Gudang Central Kitchen')->first();
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
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                        $fifoResult = $fifoService->consumeFIFO($item->bahan_id, $qtyButuh, $gudangCkId);

                        foreach ($fifoResult as $layer) {
                            $totalBbbProduk += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                        }

                        $stokBahanGlobal = StokGudang::where('gudang_id', $gudangCkId)->where('barang_id', $item->bahan_id)->first();
                        if ($stokBahanGlobal) {
                            $stokBahanGlobal->decrement('jumlah', $qtyButuh);
                        }
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

                // Alokasi pesanan CK
                ProduksiPesanan::create([
                    'produksi_id'       => $produksiId,
                    'pesanan_id'        => $pesananIdUtama,
                    'produk_id'         => $produkId,
                    'qty_alokasi'       => $qtyHasil,
                    'qty_terkirim'      => 0,
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
                    $pesanan->update(['status_pesanan' => 'Siap kirim']);
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

            foreach ($produksi->details as $detail) {
                $produkId = $detail->produk_id;
                $qtyHasil = floatval($detail->qty);
                $produk   = MasterBarang::find($produkId);

                $totalBbbProduk = 0;
                if ($produk && $produk->resep_id) {
                    $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->get();
                    foreach ($resepItems as $item) {
                        $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;
                        $fifoResult = $fifoService->consumeFIFO($item->bahan_id, $qtyButuh, $gudangBahanId);

                        foreach ($fifoResult as $layer) {
                            $totalBbbProduk += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                        }

                        $stokBahanGlobal = StokGudang::where('gudang_id', $gudangBahanId)->where('barang_id', $item->bahan_id)->first();
                        if ($stokBahanGlobal) {
                            $stokBahanGlobal->decrement('jumlah', $qtyButuh);
                        }
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

                // Alokasi pesanan CK
                ProduksiPesanan::create([
                    'produksi_id'       => $produksi->id,
                    'pesanan_id'        => $produksi->pesanan_id,
                    'produk_id'         => $produkId,
                    'qty_alokasi'       => $qtyHasil,
                    'qty_terkirim'      => 0,
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
                        DB::table('pesanan')->where('id', $produksi->pesanan_id)->update(['status_pesanan' => 'Siap kirim']);
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
}
