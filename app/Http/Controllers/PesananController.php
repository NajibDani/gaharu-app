<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\MasterBarang;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use App\Models\Pembayaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PesananController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Pesanan::b2b()->with(['customer', 'pembayaran', 'details.produk.resepBtklBop', 'gudang', 'creator']);

        $customerId = $request->query('customer_id');
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_pesanan', 'like', '%' . $search . '%')
                  ->orWhere('no_pesanan', 'like', '%' . $search . '%')
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('nama', 'like', '%' . $search . '%');
                  });
            });
        }

        $pesanan = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        // Ambil status WO secara realtime untuk dilempar ke sistem UI Blade
        foreach ($pesanan as $p) {
            $woDetail = WorkOrderDetail::where('pesanan_id', $p->id)->first();
            if ($woDetail) {
                $wo = WorkOrder::find($woDetail->work_order_id);
                $p->wo_status = $wo ? strtolower($wo->status_wo) : null;
            } else {
                $p->wo_status = null;
            }
            $p->is_sent = \App\Models\Pengiriman::where('pesanan_id', $p->id)
                ->where('status_pengiriman', 'Selesai')
                ->exists() || ($p->total_qty_terkirim ?? 0) > 0;
        }

        $customers = Customer::orderBy('nama')->get();

        $totalPesanan = Pesanan::b2b()->count();
        $totalProses = Pesanan::b2b()->whereIn('status_pesanan', ['Draft', 'Proses', 'Siap kirim', 'pending', 'ready', 'Diproses'])->count();
        $totalSelesai = Pesanan::b2b()->where('status_pesanan', 'Selesai')->count();

        // Barang yang bisa dipilih: Bahan Setengah Jadi (BSJ) DAN Bahan Jadi
        $produk = MasterBarang::with('resepBtklBop')
            ->where('is_active', true)
            ->where(function($q) {
                $q->where('is_bahan_setengah_jadi', true)
                  ->orWhere('is_barang_jadi', true);
            })
            ->orderBy('nama', 'asc')
            ->get();

        return view('pesanan.index', compact('pesanan', 'totalPesanan', 'totalProses', 'totalSelesai', 'customers', 'customerId', 'produk'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::orderBy('nama')->get();
        
        // Ambil barang aktif khusus Bahan Setengah Jadi (BSJ) DAN Bahan Jadi
        $produk = MasterBarang::with('resepBtklBop')
            ->where('is_active', true)
            ->where(function($q) {
                $q->where('is_bahan_setengah_jadi', true)
                  ->orWhere('is_barang_jadi', true);
            })
            ->orderBy('nama', 'asc')
            ->get();

        return view('pesanan.create', compact('customers', 'produk'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => 'required',
            'tanggal'        => 'required',
            'estimasi_kirim' => 'nullable',
            'produk_id'      => 'required|array|min:1',
            'qty'            => 'required|array|min:1',
            'harga'          => 'nullable|array',
            'subtotal'       => 'nullable|array',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return redirect()->back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat membuat Permintaan Cold Kitchen pada periode yang sudah ditutup.'])->withInput();
        }

        if (date('Y-m-d', strtotime($request->tanggal)) < date('Y-m-d')) {
            return redirect()->back()->withErrors(['tanggal' => 'Tanggal transaksi tidak boleh sebelum hari ini.'])->withInput();
        }

        $estimasiKirim = $request->estimasi_kirim ? $request->estimasi_kirim : $request->tanggal;

        foreach ($request->produk_id as $key => $produkId) {
            if (!$produkId) continue;
            $barang = MasterBarang::find($produkId);
            if (!$barang || !$barang->is_active) {
                return redirect()->back()->withErrors([
                    'produk_id' => "Barang " . ($barang->nama ?? 'pilihan') . " sedang tidak aktif dan tidak dapat dipilih dalam transaksi."
                ])->withInput();
            }
            $qty = floatval($request->qty[$key] ?? 0);
            if ($barang->minimum_order && $qty < $barang->minimum_order) {
                return redirect()->back()->withErrors([
                    'qty' => "Jumlah order untuk {$barang->nama} kurang dari batas minimum order (" . number_format($barang->minimum_order) . " {$barang->satuan})."
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($request->customer_id);
            $custNama = strtolower($customer->nama);
            
            // Tentukan gudang outlet tujuan jika ada
            $gudangOutlet = null;
            if (str_contains($custNama, 'kejingga')) {
                $gudangOutlet = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')
                    ->orWhere('nama', 'like', '%Kejingga%')->first();
            } elseif (str_contains($custNama, 'gaharu')) {
                $gudangOutlet = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')
                    ->where('kategori', 'Operasional')->first();
            } else {
                $gudangOutlet = \App\Models\MasterGudang::where('kategori', 'Operasional')->first();
            }
            $gudangId = $gudangOutlet ? $gudangOutlet->id : null;

            // Aturan Pembayaran: Outlet Gaharu internal default 'Lunas', Outlet KeJingga & Konsumen Luar/Cold Kitchen default 'Belum Bayar'
            $isInternalGaharu = str_contains($custNama, 'gaharu') && !str_contains($custNama, 'luar');
            $statusBayar = $isInternalGaharu ? 'Lunas' : 'Belum Bayar';

            $orderModes = $request->input('order_mode', []);
            $subtotalDpp = 0;
            $itemsData = [];
            $fifoService = app(\App\Services\FifoService::class);

            foreach ($request->produk_id as $key => $produkId) {
                if (!$produkId || floatval($request->qty[$key] ?? 0) <= 0) continue;

                $rawQty = floatval($request->qty[$key]);
                $mode = $orderModes[$key] ?? 'satuan';
                $finalQty = $rawQty;

                $itemObj = MasterBarang::with('resepBtklBop')->find($produkId);

                if ($mode === 'konversi') {
                    if ($itemObj && floatval($itemObj->konversi_pembelian) > 1) {
                        $finalQty = $rawQty * floatval($itemObj->konversi_pembelian);
                    }
                } elseif ($mode === 'resep') {
                    if ($itemObj && $itemObj->resepBtklBop && floatval($itemObj->resepBtklBop->output_qty) > 0) {
                        $finalQty = $rawQty * floatval($itemObj->resepBtklBop->output_qty);
                    }
                }

                $hargaVal = floatval($request->harga[$key] ?? 0);
                if ($hargaVal <= 0) {
                    $hargaVal = $fifoService->getHargaTerakhirBahan($produkId, 4); // Gudang Cold Kitchen
                    if ($hargaVal <= 0) {
                        $hargaVal = $fifoService->getHargaTerakhirBahan($produkId, 1); // Fallback Gudang Utama
                    }
                    if ($hargaVal <= 0 && $itemObj) {
                        $hargaVal = floatval($itemObj->hpp_referensi ?? $itemObj->harga_satuan_terkecil ?? 0);
                    }
                }

                $subtotalVal = isset($request->subtotal[$key]) && floatval($request->subtotal[$key]) > 0
                    ? floatval($request->subtotal[$key])
                    : ($finalQty * $hargaVal);

                $subtotalDpp += $subtotalVal;
                $itemsData[] = [
                    'produk_id' => $produkId,
                    'qty'       => $finalQty,
                    'harga'     => $hargaVal,
                    'subtotal'  => $subtotalVal,
                ];
            }

            $taxPercentage = floatval($request->tax_percentage ?? 0);
            $taxAmount = round($subtotalDpp * ($taxPercentage / 100), 2);
            $totalPesanan = $subtotalDpp + $taxAmount;

            $kodePesanan = $request->kode_pesanan;
            if (!$kodePesanan) {
                $kodePesanan = 'CK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }

            $pesanan = Pesanan::create([
                'kode_pesanan'      => $kodePesanan,
                'tipe_pesanan'      => 'b2b',
                'customer_id'       => $request->customer_id,
                'tanggal'           => $request->tanggal,
                'estimasi_kirim'    => $estimasiKirim,
                'estimasi_produksi' => $request->estimasi_produksi ?? null,
                'total_pesanan'     => $totalPesanan,
                'tax_percentage'    => $taxPercentage,
                'tax_service'       => $taxAmount,
                'status_pesanan'    => 'pending',
                'status_pembayaran' => $statusBayar,
                'created_by'        => auth()->id(),
                'gudang_id'         => $gudangId,
            ]);

            foreach ($itemsData as $item) {
                PesananDetail::create([
                    'pesanan_id' => $pesanan->id,
                    'produk_id'  => $item['produk_id'],
                    'qty'        => $item['qty'],
                    'harga'      => $item['harga'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }

            DB::commit();
            return redirect()->route('pesanan.index')->with('success', 'Permintaan Cold Kitchen baru #' . $pesanan->kode_pesanan . ' berhasil diajukan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan pesanan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pesanan = Pesanan::with(['customer', 'details.produk.resepBtklBop', 'gudang', 'creator'])->findOrFail($id);
        
        $woDetail = WorkOrderDetail::where('pesanan_id', $pesanan->id)->first();
        $workOrder = $woDetail ? WorkOrder::find($woDetail->work_order_id) : null;

        return view('pesanan.show', compact('pesanan', 'workOrder'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $pesanan = Pesanan::with(['details.produk.resepBtklBop', 'customer'])->findOrFail($id);

        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Gagal mengedit: Permintaan #' . $pesanan->kode_pesanan . ' sudah diproses dalam Work Order Produksi.');
        }

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Gagal mengedit: Permintaan #' . $pesanan->kode_pesanan . ' berada pada periode akuntansi yang sudah ditutup buku.');
        }

        $customers = Customer::orderBy('nama')->get();

        // Ambil barang aktif khusus Bahan Setengah Jadi (BSJ) DAN Bahan Jadi
        $produk = MasterBarang::with('resepBtklBop')
            ->where('is_active', true)
            ->where(function($q) {
                $q->where('is_bahan_setengah_jadi', true)
                  ->orWhere('is_barang_jadi', true);
            })
            ->orderBy('nama', 'asc')
            ->get();

        return view('pesanan.edit', compact('pesanan', 'customers', 'produk'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pesanan = Pesanan::findOrFail($id);

        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Gagal memperbarui: Permintaan #' . $pesanan->kode_pesanan . ' sudah diproses dalam Work Order Produksi.');
        }

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->back()->withErrors(['tanggal' => 'Pesanan ini berada pada periode akuntansi yang sudah ditutup buku. Tidak dapat mengubah pesanan.'])->withInput();
        }

        $request->validate([
            'customer_id'    => 'required',
            'tanggal'        => 'required',
            'estimasi_kirim' => 'nullable',
            'produk_id'      => 'required|array|min:1',
            'qty'            => 'required|array|min:1',
            'harga'          => 'nullable|array',
            'subtotal'       => 'nullable|array',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return redirect()->back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat mengubah tanggal transaksi ke periode yang ditutup.'])->withInput();
        }

        if ($request->estimasi_kirim && date('Y-m-d', strtotime($request->estimasi_kirim)) < date('Y-m-d', strtotime($request->tanggal))) {
            return redirect()->back()->withErrors(['estimasi_kirim' => 'Estimasi kirim tidak boleh sebelum tanggal transaksi.'])->withInput();
        }

        foreach ($request->produk_id as $key => $produkId) {
            if (!$produkId) continue;
            $barang = MasterBarang::find($produkId);
            if (!$barang || !$barang->is_active) {
                return redirect()->back()->withErrors([
                    'produk_id' => "Barang " . ($barang->nama ?? 'pilihan') . " sedang tidak aktif dan tidak dapat dipilih dalam transaksi."
                ])->withInput();
            }
            $qty = floatval($request->qty[$key] ?? 0);
            if ($barang->minimum_order && $qty < $barang->minimum_order) {
                return redirect()->back()->withErrors([
                    'qty' => "Jumlah order untuk {$barang->nama} kurang dari batas minimum order (" . number_format($barang->minimum_order) . " {$barang->satuan})."
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($request->customer_id);
            $custNama = strtolower($customer->nama);
            
            $gudangOutlet = null;
            if (str_contains($custNama, 'kejingga')) {
                $gudangOutlet = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')
                    ->orWhere('nama', 'like', '%Kejingga%')->first();
            } elseif (str_contains($custNama, 'gaharu')) {
                $gudangOutlet = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')
                    ->where('kategori', 'Operasional')->first();
            } else {
                $gudangOutlet = \App\Models\MasterGudang::where('kategori', 'Operasional')->first();
            }
            $gudangId = $gudangOutlet ? $gudangOutlet->id : $pesanan->gudang_id;

            $orderModes = $request->input('order_mode', []);
            $subtotalDpp = 0;
            $itemsData = [];

            // Ambil harga lama jika ada per produk_id
            $oldPrices = $pesanan->details->pluck('harga', 'produk_id')->toArray();

            foreach ($request->produk_id as $key => $produkId) {
                if (!$produkId || floatval($request->qty[$key] ?? 0) <= 0) continue;

                $rawQty = floatval($request->qty[$key]);
                $mode = $orderModes[$key] ?? 'satuan';
                $finalQty = $rawQty;

                if ($mode === 'konversi') {
                    $itemObj = MasterBarang::find($produkId);
                    if ($itemObj && floatval($itemObj->konversi_pembelian) > 1) {
                        $finalQty = $rawQty * floatval($itemObj->konversi_pembelian);
                    }
                } elseif ($mode === 'resep') {
                    $itemObj = MasterBarang::with('resepBtklBop')->find($produkId);
                    if ($itemObj && $itemObj->resepBtklBop && floatval($itemObj->resepBtklBop->output_qty) > 0) {
                        $finalQty = $rawQty * floatval($itemObj->resepBtklBop->output_qty);
                    }
                }

                $hargaVal = isset($request->harga[$key]) ? floatval($request->harga[$key]) : ($oldPrices[$produkId] ?? 0);
                $subtotalVal = isset($request->subtotal[$key]) && floatval($request->subtotal[$key]) > 0
                    ? floatval($request->subtotal[$key])
                    : ($finalQty * $hargaVal);

                $subtotalDpp += $subtotalVal;
                $itemsData[] = [
                    'produk_id' => $produkId,
                    'qty'       => $finalQty,
                    'harga'     => $hargaVal,
                    'subtotal'  => $subtotalVal,
                ];
            }

            $taxPercentage = floatval($request->tax_percentage ?? $pesanan->tax_percentage ?? 0);
            $taxAmount = round($subtotalDpp * ($taxPercentage / 100), 2);
            $totalPesanan = $subtotalDpp + $taxAmount;

            $pesanan->update([
                'customer_id'       => $request->customer_id,
                'tanggal'           => $request->tanggal,
                'estimasi_kirim'    => $request->estimasi_kirim ?: $request->tanggal,
                'estimasi_produksi' => $request->estimasi_produksi ?? $pesanan->estimasi_produksi,
                'total_pesanan'     => $totalPesanan,
                'tax_percentage'    => $taxPercentage,
                'tax_service'       => $taxAmount,
                'gudang_id'         => $gudangId,
            ]);

            // Hapus detail lama dan ganti detail baru
            PesananDetail::where('pesanan_id', $pesanan->id)->delete();
            foreach ($itemsData as $item) {
                PesananDetail::create([
                    'pesanan_id' => $pesanan->id,
                    'produk_id'  => $item['produk_id'],
                    'qty'        => $item['qty'],
                    'harga'      => $item['harga'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }

            // Hitung ulang status pembayaran
            $totalBayarSelesai = $pesanan->pembayaran()->sum('jumlah_bayar');
            if ($totalPesanan > 0 && $totalBayarSelesai >= $totalPesanan) {
                $pesanan->update(['status_pembayaran' => 'Lunas']);
            } elseif ($totalBayarSelesai > 0) {
                $pesanan->update(['status_pembayaran' => 'DP']);
            } else {
                $pesanan->update(['status_pembayaran' => str_contains($custNama, 'kejingga') ? 'Belum Bayar' : 'Lunas']);
            }

            DB::commit();
            return redirect()->route('pesanan.index')->with('success', 'Permintaan Cold Kitchen #' . $pesanan->kode_pesanan . ' berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui permintaan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Simpan Pembayaran Modal (DP / Lunas)
     */
    public function simpanPembayaran(Request $request, $id)
    {
        $pesanan = Pesanan::with(['pembayaran', 'details'])->findOrFail($id);
        
        $request->validate([
            'tanggal_bayar'     => 'required|date',
            'jumlah_bayar'      => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|string',
            'bukti_file'        => 'nullable|array',
            'bukti_file.*'      => 'file|image|max:2048'
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_bayar)) {
            return redirect()->back()->with('error', 'Gagal menyimpan: Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal_bayar)) . ' sudah ditutup buku.')->withInput();
        }

        $totalBayarSebelumnya = $pesanan->pembayaran()->sum('jumlah_bayar');
        $totalTagihan = $pesanan->total_pesanan > 0 ? (float)$pesanan->total_pesanan : (float)$pesanan->details->sum('subtotal');
        $sisaTagihan = max(0, $totalTagihan - $totalBayarSebelumnya);
        $jumlahBayarInput = floatval($request->jumlah_bayar ?? 0);

        $buktiFiles = [];
        if ($request->hasFile('bukti_file')) {
            foreach ($request->file('bukti_file') as $file) {
                $path = $file->store('pembayaran_bukti', 'public');
                $buktiFiles[] = $path;
            }
        }
    
        if ($jumlahBayarInput > 0) {
            $pembayaran = Pembayaran::create([
                'pesanan_id'          => $pesanan->id,
                'kategori_pembayaran' => 'penjualan',
                'tanggal_bayar'       => $request->tanggal_bayar,
                'jumlah_bayar'        => $jumlahBayarInput,
                'metode_pembayaran'   => $request->metode_pembayaran,
                'catatan'             => $request->catatan,
                'bukti_pembayaran'    => $buktiFiles,
                'created_by'          => auth()->id()
            ]);

            // Auto post B2B payment journal
            \App\Http\Controllers\JurnalController::autoPostPenjualanB2b($pembayaran->id, 'pembayaran');
        }

        $totalBayarBaru = $totalBayarSebelumnya + $jumlahBayarInput;
    
        if ($totalTagihan > 0) {
            if ($totalBayarBaru >= $totalTagihan) {
                $pesanan->update(['status_pembayaran' => 'Lunas']);
            } elseif ($totalBayarBaru > 0) {
                $pesanan->update(['status_pembayaran' => 'DP']);
            } else {
                $pesanan->update(['status_pembayaran' => 'Belum Bayar']);
            }
        } else {
            $pesanan->update(['status_pembayaran' => 'Lunas']);
        }
    
        return back()->with('success', 'Catatan pembayaran / termin berhasil disimpan!');
    }

    /**
     * Remove the specified resource from storage.
     * Superadmin dapat menghapus PO/Permintaan yang belum terkirim (masa uji coba)
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        $pesanan = Pesanan::findOrFail($id);

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->route('pesanan.index')->with('error', 'Gagal menghapus: Permintaan berada pada periode akuntansi yang sudah ditutup buku.');
        }

        // Cek apakah sudah ada pengiriman yang statusnya Selesai (sudah terkirim)
        $sudahKirim = \App\Models\Pengiriman::where('pesanan_id', $pesanan->id)
            ->where('status_pengiriman', 'Selesai')
            ->exists();

        if ($sudahKirim || ($pesanan->total_qty_terkirim ?? 0) > 0) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Data tidak bisa dihapus karena sudah ada riwayat pengiriman logistik yang selesai (sudah terkirim).');
        }

        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();

        // Jika sudah masuk WO dan BUKAN Super Admin, tolak
        if ($sudahWO && !$isSuperAdmin) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Data tidak bisa dihapus karena relasi logistik Work Order (WO) sudah terbentuk. Hanya Superadmin yang dapat menghapus Purchase Order yang belum terkirim.');
        }

        DB::beginTransaction();
        try {
            // 1. Bersihkan Jurnal Penjualan B2B jika ada
            $pembayaranIds = Pembayaran::where('pesanan_id', $pesanan->id)->pluck('id')->toArray();
            $pengirimanDraftIds = \App\Models\Pengiriman::where('pesanan_id', $pesanan->id)->pluck('id')->toArray();

            $jurnals = DB::table('jurnal_penjualan_b2b')
                ->where(function($q) use ($pembayaranIds, $pengirimanDraftIds) {
                    if (!empty($pembayaranIds)) {
                        $q->where(function($sub) use ($pembayaranIds) {
                            $sub->where('source_type', 'pembayaran')->whereIn('source_id', $pembayaranIds);
                        });
                    }
                    if (!empty($pengirimanDraftIds)) {
                        $q->orWhere(function($sub) use ($pengirimanDraftIds) {
                            $sub->where('source_type', 'pengiriman')->whereIn('source_id', $pengirimanDraftIds);
                        });
                    }
                })->get();

            foreach ($jurnals as $j) {
                DB::table('journal_items')->where('journal_id', $j->id)->whereIn('journal_type', ['penjualan_b2b', 'jurnal_penjualan_b2b'])->delete();
                DB::table('jurnal_penjualan_b2b')->where('id', $j->id)->delete();
            }

            // 2. Hapus Pembayaran
            Pembayaran::where('pesanan_id', $pesanan->id)->delete();

            // 3. Hapus Pengiriman draft beserta detail
            if (!empty($pengirimanDraftIds)) {
                DB::table('pengiriman_detail')->whereIn('pengiriman_id', $pengirimanDraftIds)->delete();
                DB::table('pengiriman')->whereIn('id', $pengirimanDraftIds)->delete();
            }

            // 4. Hapus Alokasi Produksi Pesanan
            DB::table('alokasi_produksi_pesanan')->where('pesanan_id', $pesanan->id)->delete();

            // 5. Tangani Relasi Produksi jika ada
            $produksis = \App\Models\Produksi::where('pesanan_id', $pesanan->id)->get();
            foreach ($produksis as $prod) {
                if ($prod->status_produksi === 'Selesai') {
                    $prodDetails = DB::table('produksi_detail')->where('produksi_id', $prod->id)->get();
                    foreach ($prodDetails as $pDet) {
                        $stok = \App\Models\StokGudang::where('barang_id', $pDet->produk_id)
                            ->where('gudang_id', $prod->gudang_hasil_id)
                            ->first();
                        if ($stok) {
                            $stok->decrement('jumlah', (float) $pDet->qty);
                        }
                    }
                    DB::table('stok_gudang_batch')->where('produksi_id', $prod->id)->delete();
                    DB::table('transaksi_stok')->where('source_id', $prod->id)->where('source_type', 'produksi')->delete();
                }

                DB::table('produksi_detail')->where('produksi_id', $prod->id)->delete();
                $prod->delete();
            }

            // 6. Hapus Work Order Detail & Work Order jika kosong
            $woDetails = WorkOrderDetail::where('pesanan_id', $pesanan->id)->get();
            $woIds = $woDetails->pluck('work_order_id')->unique()->toArray();
            WorkOrderDetail::where('pesanan_id', $pesanan->id)->delete();

            foreach ($woIds as $woId) {
                $sisaDetail = WorkOrderDetail::where('work_order_id', $woId)->count();
                if ($sisaDetail === 0) {
                    WorkOrder::where('id', $woId)->delete();
                }
            }

            // 7. Hapus Pesanan Detail & Pesanan
            PesananDetail::where('pesanan_id', $pesanan->id)->delete();
            $pesanan->delete();

            DB::commit();
            return redirect()->route('pesanan.index')->with('success', 'Permintaan Cold Kitchen #' . $pesanan->kode_pesanan . ' dan seluruh data terkait berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('pesanan.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
    
    /**
     * Batalkan Pesanan Kontrak
     */
    public function batal($id)
    {
        $pesanan = Pesanan::findOrFail($id);
    
        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->route('pesanan.index')->with('error', 'Gagal membatalkan: Kontrak pesanan berada pada periode akuntansi yang sudah ditutup buku.');
        }
    
        $woDetail = WorkOrderDetail::where('pesanan_id', $pesanan->id)->first();
        if ($woDetail) {
            $workOrder = WorkOrder::find($woDetail->work_order_id);
    
            // PROTEKSI NYATA: Jika WO berstatus selain draft (misal 'diproses'), gagalkan pembatalan
            if ($workOrder && strtolower($workOrder->status_wo) !== 'draft') {
                return redirect()->route('pesanan.index')
                    ->with('error', 'Pembatalan ditolak! Dapur utama telah memproses bahan baku untuk pesanan ini.');
            }
        }
    
        $pesanan->update(['status_pesanan' => 'dibatalkan']);
    
        return redirect()->route('pesanan.index')->with('success', 'Status kontrak pesanan resmi dibatalkan.');
    }

    /**
     * Kwitansi Cetak
     */
    public function kwitansi($id)
    {
        $pesanan = Pesanan::with(['customer', 'pembayaran'])->findOrFail($id);
        return view('pesanan.kwitansi', compact('pesanan'));
    }

    public function cetakSoPdf($id)
    {
        $pesanan = Pesanan::with(['customer', 'gudang', 'creator', 'details.produk.resepBtklBop', 'pembayaran'])->findOrFail($id);
        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
        $pdf->loadView('pesanan.so-pdf', compact('pesanan'));
        return $pdf->stream('Sales-Order-' . $pesanan->kode_pesanan . '.pdf');
    }

    /**
     * Input / Update Harga Jual per pcs oleh Admin untuk Permintaan Cold Kitchen
     */
    public function updateHargaJual(Request $request, $id)
    {
        $pesanan = Pesanan::with('details')->findOrFail($id);

        $request->validate([
            'detail_id' => 'required|array',
            'harga'     => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $subtotalDpp = 0;
            foreach ($request->detail_id as $key => $dId) {
                $detail = PesananDetail::where('pesanan_id', $pesanan->id)->where('id', $dId)->first();
                if ($detail) {
                    $harga = floatval($request->harga[$key] ?? 0);
                    $subtotal = $detail->qty * $harga;
                    $detail->update([
                        'harga'    => $harga,
                        'subtotal' => $subtotal,
                    ]);
                    $subtotalDpp += $subtotal;
                }
            }

            $taxRate = floatval($pesanan->tax_percentage ?? 0);
            $taxAmount = round($subtotalDpp * ($taxRate / 100), 2);
            $totalBaru = $subtotalDpp + $taxAmount;

            $pesanan->update([
                'total_pesanan' => $totalBaru,
                'tax_service'   => $taxAmount,
            ]);

            // Hitung ulang status pembayaran
            $totalBayar = $pesanan->pembayaran()->sum('jumlah_bayar');
            if ($totalBayar >= $totalBaru && $totalBaru > 0) {
                $pesanan->update(['status_pembayaran' => 'Lunas']);
            } elseif ($totalBayar > 0) {
                $pesanan->update(['status_pembayaran' => 'DP']);
            }

            DB::commit();
            return back()->with('success', 'Harga jual per pcs berhasil diperbarui! Total tagihan: Rp ' . number_format($totalBaru, 0, ',', '.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui harga jual: ' . $e->getMessage());
        }
    }

    /**
     * Pembayaran Massal / Multi-Nota untuk Cold Kitchen
     */
    public function pembayaranMassal(Request $request)
    {
        $request->validate([
            'pesanan_ids'       => 'required|array|min:1',
            'tanggal_bayar'     => 'required|date',
            'metode_pembayaran' => 'required|string',
            'bukti_file'        => 'nullable|array',
            'bukti_file.*'      => 'file|image|max:2048',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_bayar)) {
            return redirect()->back()->with('error', 'Gagal memproses pembayaran: Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal_bayar)) . ' sudah ditutup buku.')->withInput();
        }

        $buktiFiles = [];
        if ($request->hasFile('bukti_file')) {
            foreach ($request->file('bukti_file') as $file) {
                $path = $file->store('pembayaran_bukti', 'public');
                $buktiFiles[] = $path;
            }
        }

        DB::beginTransaction();
        try {
            $pesanans = Pesanan::whereIn('id', $request->pesanan_ids)->get();
            $totalBayarSemua = 0;
            $jumlahNota = 0;

            foreach ($pesanans as $pesanan) {
                $totalBayarSebelumnya = $pesanan->pembayaran()->sum('jumlah_bayar');
                $totalTagihan = $pesanan->total_pesanan > 0 ? (float)$pesanan->total_pesanan : (float)$pesanan->details->sum('subtotal');
                $sisaTagihan = max(0, $totalTagihan - $totalBayarSebelumnya);

                if ($sisaTagihan > 0) {
                    $pembayaran = Pembayaran::create([
                        'pesanan_id'          => $pesanan->id,
                        'kategori_pembayaran' => 'penjualan',
                        'tanggal_bayar'       => $request->tanggal_bayar,
                        'jumlah_bayar'        => $sisaTagihan,
                        'metode_pembayaran'   => $request->metode_pembayaran,
                        'catatan'             => $request->catatan ? ($request->catatan . ' (Pelunasan Massal Cold Kitchen)') : 'Pelunasan Massal Cold Kitchen Termin/Periode',
                        'bukti_pembayaran'    => $buktiFiles,
                        'created_by'          => auth()->id(),
                    ]);

                    \App\Http\Controllers\JurnalController::autoPostPenjualanB2b($pembayaran->id, 'pembayaran');

                    $pesanan->update(['status_pembayaran' => 'Lunas']);
                    $totalBayarSemua += $sisaTagihan;
                    $jumlahNota++;
                } else {
                    $pesanan->update(['status_pembayaran' => 'Lunas']);
                }
            }

            DB::commit();
            return back()->with('success', "Pembayaran berhasil! Sebanyak {$jumlahNota} nota Cold Kitchen telah dilunasi dengan total Rp " . number_format($totalBayarSemua, 0, ',', '.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses pembayaran massal: ' . $e->getMessage());
        }
    }
}