<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use App\Models\StokGudang;
use App\Models\MasterBarang;
use App\Models\ResepBahanBaku;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = WorkOrder::with([
            'details.pesanan.customer',
            'details.produk'
        ]);

        if ($search) {
            $query->where('no_wo', 'like', '%' . $search . '%');
        }

        // Page param dibedakan (wo_page) supaya tidak bentrok dengan pagination tabel pesanan
        $wo = $query->latest()->paginate(10, ['*'], 'wo_page')->withQueryString();

        // Tampilkan pesanan yang status bayarnya DP atau Lunas untuk dibuatkan WO
        // Diurutkan dari estimasi_kirim PALING MEPET (tanggal paling dekat/sudah lewat) di paling atas
        $pesanan = Pesanan::with(['details.produk', 'customer'])
                    ->where('status_pesanan', 'pending')
                    ->whereIn('status_pembayaran', ['DP', 'Lunas']) 
                    ->orderBy('estimasi_kirim', 'asc')
                    ->paginate(10, ['*'], 'pesanan_page')
                    ->withQueryString();

        return view('work_order.index', compact('wo', 'pesanan'));
    }

    public function create($id)
    {
        $pesanan = Pesanan::with(['details.produk', 'customer'])->findOrFail($id);
        return view('work_order.create', compact('pesanan'));
    }

    public function store(Request $request)
    {
        $pesanan = Pesanan::findOrFail($request->pesanan_id);

        if ($pesanan->status_pembayaran == 'Belum Bayar') {
            return back()->with('error', 'Gagal! Pesanan ini belum membayar DP.');
        }

        DB::beginTransaction();
        try {
            $wo = WorkOrder::create([
                'kode_wo'    => $request->kode_wo,
                'tanggal_wo' => $request->tanggal_wo,
                'status_wo'  => 'Draft',
                'catatan'    => $request->catatan,
                'created_by' => auth()->id(),
            ]);

            foreach ($request->produk_id as $key => $produk_id) {
                if ($request->qty_rencana[$key] <= 0) continue;

                WorkOrderDetail::create([
                    'work_order_id' => $wo->id,
                    'pesanan_id'    => $request->pesanan_id,
                    'produk_id'     => $produk_id,
                    'qty_rencana'   => $request->qty_rencana[$key],
                ]);
            }

            DB::commit();
            return redirect()->route('wo.index')->with('success', 'Work Order berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    public function reviewMassal(Request $request)
    {
        $request->validate(['detail_ids' => 'required|array|min:1']);

        $details = PesananDetail::with(['pesanan.customer', 'produk'])
                    ->whereIn('id', $request->detail_ids)
                    ->get();

        foreach ($details as $detail) {
            $qtySudahWO = WorkOrderDetail::where('pesanan_id', $detail->pesanan_id)
                            ->where('produk_id', $detail->produk_id)
                            ->sum('qty_rencana');
            $detail->sisa_qty = $detail->qty - $qtySudahWO;
        }

        return view('work_order.review_massal', compact('details'));
    }

    public function storeMassal(Request $request)
    {
        $cekBayar = Pesanan::whereIn('id', $request->pesanan_id)
                            ->where('status_pembayaran', 'Belum Bayar')
                            ->exists();
    
        if ($cekBayar) {
            return redirect()->route('wo.index')->with('error', 'Salah satu pesanan belum membayar DP!');
        }

        DB::beginTransaction();
        try {
            $wo = WorkOrder::create([
                'kode_wo'    => 'WO-BATCH-' . strtoupper(bin2hex(random_bytes(3))),
                'tanggal_wo' => now(),
                'status_wo'  => 'Draft',
                'created_by' => auth()->id(),
                'catatan'    => 'Dibuat secara massal/gabungan',
            ]);

            foreach ($request->pesanan_id as $index => $p_id) {
                if ($request->qty_rencana[$index] > 0) {
                    WorkOrderDetail::create([
                        'work_order_id' => $wo->id,
                        'pesanan_id'    => $p_id,
                        'produk_id'     => $request->produk_id[$index],
                        'qty_rencana'   => $request->qty_rencana[$index],
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('wo.index')->with('success', 'Work Order Gabungan berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('wo.index')->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

public function show($id)
{
    $wo = WorkOrder::with([
        'details.pesanan.customer', 
        'details.produk.resep.bahan' // Harus memuat urutan ini
    ])->findOrFail($id);

    // Kelompokkan detail berdasarkan produk, supaya produk yang sama
    // (walau berasal dari pesanan/customer berbeda) tampil sebagai satu
    // baris dengan qty gabungan & satu kalkulasi resep, bukan per pesanan.
    $groupedDetails = $wo->details->groupBy('produk_id');

    return view('work_order.show', compact('wo', 'groupedDetails'));
}

public function cetakPdf($id)
{
    $wo = WorkOrder::with([
        'details.pesanan.customer', 
        'details.produk.resep.bahan'
    ])->findOrFail($id);

    $pdf = app('dompdf.wrapper');
    $pdf->loadView('work_order.show-pdf', compact('wo'));
    return $pdf->download('work-order-' . $wo->kode_wo . '.pdf');
}

    /**
     * FUNGSI KRUSIAL: Kirim Produksi & Transfer Stok Bahan Baku
     * Dari Gudang Utama (ID: 1) ke Gudang Produksi (ID: 2)
     */
    public function kirimKeProduksi($id)
{
    $wo = \App\Models\WorkOrder::with(
        'details.produk.resep.bahan'
    )->findOrFail($id);

    \DB::beginTransaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | CARI GUDANG PRODUKSI
        |--------------------------------------------------------------------------
        */

        $gudangProduksi = \App\Models\MasterGudang::where(
            'kategori',
            'Produksi'
        )->first();

        if (!$gudangProduksi) {
            throw new \Exception(
                'Gudang Produksi belum dibuat pada Master Gudang.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | BUAT DOKUMEN PENGELUARAN
        |--------------------------------------------------------------------------
        */

        $pengeluaran = \App\Models\PengeluaranBahanBaku::create([
            'kode_pengeluaran' => 'REQ-' . date('Ymd') . '-' . strtoupper(\Str::random(4)),
            'tanggal'          => now(),

            // tujuan transfer
            'gudang_id'        => $gudangProduksi->id,

            'status'           => 'Draft',
            'keterangan'       => 'Permintaan bahan baku untuk ' . $wo->kode_wo,
            'created_by'       => auth()->id(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | DETAIL BAHAN BAKU DARI RESEP
        |--------------------------------------------------------------------------
        | Digabung (agregasi) per barang_id dulu, supaya kalau ada beberapa
        | pesanan yang minta produk yang sama, kebutuhan bahannya dijumlah
        | menjadi satu baris permintaan per bahan, bukan baris terpisah
        | per pesanan. Ini hanya penggabungan angka kebutuhan, TIDAK
        | mengubah logika pengambilan stok (FIFO) yang terjadi di proses lain.
        */

        $agregatBahan = [];

        foreach ($wo->details as $detail) {

            if (!$detail->produk) {
                continue;
            }

            if (!$detail->produk->resep) {
                continue;
            }

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

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS WO
        |--------------------------------------------------------------------------
        */

        $wo->update([
            'status_wo' => 'Diproses'
        ]);

        \DB::commit();

        return redirect()->back()->with(
            'success',
            'Permintaan bahan berhasil dibuat.'
        );

    } catch (\Exception $e) {

        \DB::rollBack();

        return redirect()->back()->with(
            'error',
            'Gagal: ' . $e->getMessage()
        );
    }
}
}