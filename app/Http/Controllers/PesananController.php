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
        $query = Pesanan::b2b()->with(['customer', 'pembayaran', 'details.produk']);

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
        }

        $customers = Customer::orderBy('nama')->get();

        $totalPesanan = Pesanan::count();
        $totalProses = Pesanan::whereIn('status_pesanan', ['Draft', 'Proses', 'Siap kirim', 'pending', 'ready'])->count();
        $totalSelesai = Pesanan::where('status_pesanan', 'Selesai')->count();

        return view('pesanan.index', compact('pesanan', 'totalPesanan', 'totalProses', 'totalSelesai', 'customers', 'customerId'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::all();
        $produk = MasterBarang::where('is_barang_jadi', 1)->where('is_active', true)->get();

        return view('pesanan.create', compact('customers', 'produk'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            'tanggal' => 'required',
            'estimasi_kirim' => 'nullable',
            'produk_id' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'harga' => 'nullable|array',
            'subtotal' => 'nullable|array',
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
            $qty = $request->qty[$key] ?? 0;
            if ($qty < $barang->minimum_order) {
                return redirect()->back()->withErrors([
                    'qty' => "Jumlah order untuk {$barang->nama} kurang dari batas minimum order (" . number_format($barang->minimum_order) . " {$barang->satuan})."
                ])->withInput();
            }
        }

        $taxPercentage = floatval($request->tax_percentage ?? 0);
        $subtotalDpp = 0;
        if (is_array($request->subtotal)) {
            foreach ($request->subtotal as $sub) {
                $subtotalDpp += floatval($sub);
            }
        }
        $taxAmount = round($subtotalDpp * ($taxPercentage / 100), 2);
        $totalPesanan = $subtotalDpp + $taxAmount;

        $pesanan = Pesanan::create([
            'kode_pesanan' => $request->kode_pesanan,
            'customer_id' => $request->customer_id,
            'tanggal' => $request->tanggal,
            'estimasi_kirim' => $estimasiKirim,
            'estimasi_produksi' => $request->estimasi_produksi ?? null,
            'total_pesanan' => $totalPesanan,
            'tax_percentage' => $taxPercentage,
            'tax_service' => $taxAmount,
            'status_pesanan' => 'pending',
            'status_pembayaran' => 'Belum Bayar',
            'created_by' => auth()->id(),
        ]);
    
        foreach ($request->produk_id as $key => $produk) {
            if (!$produk) continue;

            $qtyVal = floatval($request->qty[$key] ?? 0);
            $hargaVal = floatval($request->harga[$key] ?? 0);
            $subtotalVal = isset($request->subtotal[$key]) ? floatval($request->subtotal[$key]) : ($qtyVal * $hargaVal);

            PesananDetail::create([
                'pesanan_id' => $pesanan->id,
                'produk_id' => $produk,
                'qty' => $qtyVal,
                'harga' => $hargaVal,
                'subtotal' => $subtotalVal,
            ]);
        }

        return redirect()->route('pesanan.index')->with('success', 'Permintaan Cold Kitchen baru berhasil diajukan!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pesanan = Pesanan::with(['customer', 'details.produk'])->findOrFail($id);
        return view('pesanan.show', compact('pesanan'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $pesanan = Pesanan::with('details.produk')->findOrFail($id);

        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Gagal mengedit: Permintaan #' . $pesanan->kode_pesanan . ' sudah diproses dalam Work Order Produksi.');
        }

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Gagal mengedit: Permintaan #' . $pesanan->kode_pesanan . ' berada pada periode akuntansi yang sudah ditutup buku.');
        }

        $customers = Customer::all();
        $produk = MasterBarang::where('is_barang_jadi', 1)->where('is_active', true)->get();

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
            'customer_id' => 'required',
            'tanggal' => 'required',
            'estimasi_kirim' => 'nullable',
            'produk_id' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'harga' => 'nullable|array',
            'subtotal' => 'nullable|array',
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
            $qty = $request->qty[$key] ?? 0;
            if ($qty < $barang->minimum_order) {
                return redirect()->back()->withErrors([
                    'qty' => "Jumlah order untuk {$barang->nama} kurang dari batas minimum order (" . number_format($barang->minimum_order) . " {$barang->satuan})."
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $subtotalDpp = 0;
            $itemsData = [];

            // Ambil harga lama jika ada per produk_id
            $oldPrices = $pesanan->details->pluck('harga', 'produk_id')->toArray();

            foreach ($request->produk_id as $key => $produkId) {
                if (!$produkId) continue;
                $qtyVal = floatval($request->qty[$key] ?? 0);
                if ($qtyVal <= 0) continue;

                $hargaVal = isset($request->harga[$key]) ? floatval($request->harga[$key]) : ($oldPrices[$produkId] ?? 0);
                $subtotalVal = isset($request->subtotal[$key]) && floatval($request->subtotal[$key]) > 0
                    ? floatval($request->subtotal[$key])
                    : ($qtyVal * $hargaVal);

                $subtotalDpp += $subtotalVal;
                $itemsData[] = [
                    'produk_id' => $produkId,
                    'qty'       => $qtyVal,
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
                $pesanan->update(['status_pembayaran' => 'Belum Bayar']);
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
        $pesanan = Pesanan::findOrFail($id);
        
        $request->validate([
            'tanggal_bayar' => 'required|date',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_bayar)) {
            return redirect()->back()->with('error', 'Gagal menyimpan: Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal_bayar)) . ' sudah ditutup buku.')->withInput();
        }

        $totalBayarSebelumnya = $pesanan->pembayaran()->sum('jumlah_bayar');
        $sisaTagihan = max(0, $pesanan->total_pesanan - $totalBayarSebelumnya);
    
        $isTerminOrCod = in_array($request->metode_pembayaran, ['Termin', 'COD']);
        $minBayar = $isTerminOrCod ? 0 : 1;
        $jumlahBayarInput = floatval($request->jumlah_bayar ?? 0);

        $request->validate([
            'jumlah_bayar' => 'required|numeric|min:' . $minBayar . '|max:' . max(0.01, $sisaTagihan),
            'metode_pembayaran' => 'required|string',
            'bukti_file'        => 'nullable|array',
            'bukti_file.*'      => 'file|image|max:2048'
        ]);

        $buktiFiles = [];
        if ($request->hasFile('bukti_file')) {
            foreach ($request->file('bukti_file') as $file) {
                $path = $file->store('pembayaran_bukti', 'public');
                $buktiFiles[] = $path;
            }
        }
    
        if ($jumlahBayarInput > 0) {
            $pembayaran = Pembayaran::create([
                'pesanan_id' => $pesanan->id,
                'kategori_pembayaran' => 'penjualan',
                'tanggal_bayar' => $request->tanggal_bayar,
                'jumlah_bayar' => $jumlahBayarInput,
                'metode_pembayaran' => $request->metode_pembayaran,
                'catatan' => $request->catatan,
                'bukti_pembayaran' => $buktiFiles,
                'created_by' => auth()->id()
            ]);

            // Auto post B2B payment journal
            \App\Http\Controllers\JurnalController::autoPostPenjualanB2b($pembayaran->id, 'pembayaran');
        }

        $totalBayarBaru = $totalBayarSebelumnya + $jumlahBayarInput;
    
        if ($totalBayarBaru >= $pesanan->total_pesanan) {
            $pesanan->update(['status_pembayaran' => 'Lunas']);
        } elseif ($totalBayarBaru > 0) {
            $pesanan->update(['status_pembayaran' => 'DP']);
        }
    
        return back()->with('success', 'Catatan pembayaran / termin berhasil disimpan!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $pesanan = Pesanan::findOrFail($id);

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->route('pesanan.index')->with('error', 'Gagal menghapus: Permintaan berada pada periode akuntansi yang sudah ditutup buku.');
        }

        // PROTEKSI NYATA: Jika sudah masuk WO, tidak boleh dihapus sama sekali
        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Data tidak bisa dihapus karena relasi logistik Work Order (WO) sudah terbentuk.');
        }

        $sudahKirim = \App\Models\Pengiriman::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahKirim) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Data tidak bisa dihapus karena sudah ada riwayat pengiriman logistik.');
        }

        DB::beginTransaction();
        try {
            Pembayaran::where('pesanan_id', $pesanan->id)->delete();
            PesananDetail::where('pesanan_id', $pesanan->id)->delete();
            $pesanan->delete();

            DB::commit();
            return redirect()->route('pesanan.index')->with('success', 'Permintaan Cold Kitchen #' . $pesanan->kode_pesanan . ' berhasil dihapus permanen.');
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
        $pesanan = Pesanan::with(['customer', 'gudang', 'creator', 'details.produk', 'pembayaran'])->findOrFail($id);
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
                $sisaTagihan = max(0, $pesanan->total_pesanan - $totalBayarSebelumnya);

                if ($sisaTagihan > 0) {
                    $pembayaran = Pembayaran::create([
                        'pesanan_id'          => $pesanan->id,
                        'kategori_pembayaran' => 'penjualan',
                        'tanggal_bayar'       => $request->tanggal_bayar,
                        'jumlah_bayar'        => $sisaTagihan,
                        'metode_pembayaran'   => $request->metode_pembayaran,
                        'catatan'             => $request->catatan ? ($request->catatan . ' (Pelunasan Massal)') : 'Pelunasan Massal Termin/Periode',
                        'bukti_pembayaran'    => $buktiFiles,
                        'created_by'          => auth()->id(),
                    ]);

                    \App\Http\Controllers\JurnalController::autoPostPenjualanB2b($pembayaran->id, 'pembayaran');

                    $pesanan->update(['status_pembayaran' => 'Lunas']);
                    $totalBayarSemua += $sisaTagihan;
                    $jumlahNota++;
                }
            }

            DB::commit();
            return back()->with('success', "Pembayaran berhasil! Sebanyak {$jumlahNota} nota telah dilunasi dengan total Rp " . number_format($totalBayarSemua, 0, ',', '.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses pembayaran massal: ' . $e->getMessage());
        }
    }
}