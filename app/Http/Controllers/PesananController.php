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

class PesananController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Auto-delete pesanan lebih dari 2 hari jika belum bayar DP (status_pembayaran == 'Belum Bayar')
        Pesanan::where('status_pembayaran', 'Belum Bayar')
            ->where('tanggal', '<', now()->subDays(2)->format('Y-m-d H:i:s'))
            ->delete();

        $search = $request->query('search');
        $query = Pesanan::with(['customer', 'pembayaran']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_pesanan', 'like', '%' . $search . '%')
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

        $totalPesanan = Pesanan::count();
        $totalProses = Pesanan::whereIn('status_pesanan', ['Draft', 'Proses', 'Siap kirim', 'pending', 'ready'])->count();
        $totalSelesai = Pesanan::where('status_pesanan', 'Selesai')->count();

        return view('pesanan.index', compact('pesanan', 'totalPesanan', 'totalProses', 'totalSelesai'));
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
            'estimasi_kirim' => 'required',
            'produk_id' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'harga' => 'required|array|min:1',
            'subtotal' => 'required|array|min:1',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return redirect()->back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat membuat Pesanan B2B pada periode yang sudah ditutup.'])->withInput();
        }

        if (date('Y-m-d', strtotime($request->tanggal)) < date('Y-m-d')) {
            return redirect()->back()->withErrors(['tanggal' => 'Tanggal transaksi tidak boleh sebelum hari ini.'])->withInput();
        }

        if (date('Y-m-d', strtotime($request->estimasi_kirim)) < date('Y-m-d', strtotime($request->tanggal))) {
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

        $taxPercentage = floatval($request->tax_percentage ?? 0);
        $subtotalDpp = 0;
        foreach ($request->subtotal as $sub) {
            $subtotalDpp += floatval($sub);
        }
        $taxAmount = round($subtotalDpp * ($taxPercentage / 100), 2);
        $totalPesanan = $subtotalDpp + $taxAmount;

        $pesanan = Pesanan::create([
            'kode_pesanan' => $request->kode_pesanan,
            'customer_id' => $request->customer_id,
            'tanggal' => $request->tanggal,
            'estimasi_kirim' => $request->estimasi_kirim,
            'estimasi_produksi' => $request->estimasi_produksi,
            'total_pesanan' => $totalPesanan,
            'tax_percentage' => $taxPercentage,
            'tax_service' => $taxAmount,
            'status_pesanan' => 'pending',
            'status_pembayaran' => 'Belum Bayar',
            'created_by' => auth()->id(),
        ]);
    
        foreach ($request->produk_id as $key => $produk) {
            if (!$produk) continue;

            PesananDetail::create([
                'pesanan_id' => $pesanan->id,
                'produk_id' => $produk,
                'qty' => $request->qty[$key],
                'harga' => $request->harga[$key],
                'subtotal' => $request->subtotal[$key],
            ]);
        }

        return redirect()->route('pesanan.index')->with('success', 'Pesanan B2B baru berhasil ditambahkan');
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

        // PROTEKSI NYATA: Jika sudah terdaftar di WO (baik draft/proses), blokir akses edit via URL
        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Gagal membuka form! Kontrak pesanan ini sudah diproses ke dalam antrean Work Order.');
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

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->back()->withErrors(['tanggal' => 'Pesanan ini berada pada periode akuntansi yang sudah ditutup buku. Tidak dapat mengubah pesanan.'])->withInput();
        }

        $request->validate([
            'customer_id' => 'required',
            'tanggal' => 'required',
            'estimasi_kirim' => 'required',
            'produk_id' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'harga' => 'required|array|min:1',
            'subtotal' => 'required|array|min:1',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return redirect()->back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat mengubah tanggal transaksi ke periode yang ditutup.'])->withInput();
        }

        if (date('Y-m-d', strtotime($request->tanggal)) < date('Y-m-d')) {
            return redirect()->back()->withErrors(['tanggal' => 'Tanggal transaksi tidak boleh sebelum hari ini.'])->withInput();
        }

        if (date('Y-m-d', strtotime($request->estimasi_kirim)) < date('Y-m-d', strtotime($request->tanggal))) {
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

        $pesanan = Pesanan::findOrFail($id);

        $taxPercentage = floatval($request->tax_percentage ?? 0);
        $subtotalDpp = 0;
        foreach ($request->subtotal as $sub) {
            $subtotalDpp += floatval($sub);
        }
        $taxAmount = round($subtotalDpp * ($taxPercentage / 100), 2);
        $totalPesanan = $subtotalDpp + $taxAmount;
    
        // update header pesanan
        $pesanan->update([
            'customer_id' => $request->customer_id,
            'tanggal' => $request->tanggal,
            'estimasi_kirim' => $request->estimasi_kirim,
            'estimasi_produksi' => $request->estimasi_produksi,
            'total_pesanan' => $totalPesanan,
            'tax_percentage' => $taxPercentage,
            'tax_service' => $taxAmount,
        ]);
    
        // hapus detail lama
        PesananDetail::where(
            'pesanan_id',
            $pesanan->id
        )->delete();
    
        // simpan ulang detail baru
        foreach ($request->produk_id as $key => $produk) {
    
            if (!$produk) {
                continue;
            }
    
            PesananDetail::create([
                'pesanan_id' => $pesanan->id,
                'produk_id' => $produk,
                'qty' => $request->qty[$key],
                'harga' => $request->harga[$key],
                'subtotal' => $request->subtotal[$key],
            ]);
        }
    
        // ===================================================================
        // TAMBAHKAN LOGIKA OTOMATIS HITUNG ULANG STATUS PEMBAYARAN DI SINI
        // ===================================================================
        // 1. Hitung total uang yang sudah pernah dibayarkan sebelumnya
        $totalBayarSelesai = $pesanan->pembayaran()->sum('jumlah_bayar');
    
        // 2. Bandingkan dengan total_pesanan yang baru setelah di-edit
        if ($totalBayarSelesai >= $pesanan->total_pesanan) {
            // Jika uang yang masuk pas atau lebih, status jadi Lunas
            $pesanan->update(['status_pembayaran' => 'Lunas']);
        } elseif ($totalBayarSelesai > 0) {
            // Jika uang masuk kurang dari total baru tapi sudah pernah bayar, status turun ke DP
            $pesanan->update(['status_pembayaran' => 'DP']);
        } else {
            // Jika memang belum pernah ada pembayaran sama sekali
            $pesanan->update(['status_pembayaran' => 'Belum Bayar']);
        }
        // ===================================================================
    
        return redirect()
            ->route('pesanan.index')
            ->with(
                'success',
                'Pesanan berhasil diupdate dan status keuangan disesuaikan'
            );
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
        $sisaTagihan = $pesanan->total_pesanan - $totalBayarSebelumnya;
    
        $minBayar = 1;
        if ($totalBayarSebelumnya == 0) {
            $minBayar = $pesanan->total_pesanan * 0.30;
        }
    
        $request->validate([
            'jumlah_bayar' => 'required|numeric|min:' . $minBayar . '|max:' . $sisaTagihan,
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
    
        Pembayaran::create([
            'pesanan_id' => $pesanan->id,
            'kategori_pembayaran' => 'penjualan',
            'tanggal_bayar' => $request->tanggal_bayar,
            'jumlah_bayar' => $request->jumlah_bayar,
            'metode_pembayaran' => $request->metode_pembayaran,
            'catatan' => $request->catatan,
            'bukti_pembayaran' => $buktiFiles,
            'created_by' => auth()->id()
        ]);
    
        $totalBayarBaru = $totalBayarSebelumnya + $request->jumlah_bayar;
    
        if ($totalBayarBaru >= $pesanan->total_pesanan) {
            $pesanan->update(['status_pembayaran' => 'Lunas']);
        } elseif ($totalBayarBaru > 0) {
            $pesanan->update(['status_pembayaran' => 'DP']);
        }
    
        return back()->with('success', 'Catatan kas masuk berhasil divalidasi!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $pesanan = Pesanan::findOrFail($id);

        if (\App\Models\Journal::isPeriodClosed($pesanan->tanggal)) {
            return redirect()->route('pesanan.index')->with('error', 'Gagal menghapus: Kontrak pesanan berada pada periode akuntansi yang sudah ditutup buku.');
        }

        // PROTEKSI NYATA: Jika sudah masuk WO, tidak boleh dihapus sama sekali
        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('pesanan.index')
                ->with('error', 'Data tidak bisa dihapus karena relasi logistik Work Order (WO) sudah terbentuk.');
        }

        PesananDetail::where('pesanan_id', $pesanan->id)->delete();
        $pesanan->delete();

        return redirect()->route('pesanan.index')->with('success', 'Kontrak pesanan berhasil dihapus permanen');
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
}