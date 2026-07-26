<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pesanan;
use App\Models\Pengiriman;
use App\Models\PengirimanDetail;
use App\Models\MasterGudang;
use App\Models\ProduksiPesanan;
use Illuminate\Support\Facades\DB;

class PengirimanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Pengiriman::with('pesanan.customer');

        if ($search) {
            $query->where('no_pengiriman', 'like', '%' . $search . '%');
        }

        $pengirimans = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        $totalData = Pengiriman::count();
        $totalDraft = Pengiriman::where('status_pengiriman', 'Draft')->count();
        $totalApproved = Pengiriman::where('status_pengiriman', 'Selesai')->count();

        return view('pengiriman.index', compact('pengirimans', 'totalData', 'totalDraft', 'totalApproved'));
    }

    public function create()
    {
        $pesanans = Pesanan::with('customer')
            ->where('status_pesanan', 'Siap kirim')
            ->where('status_pembayaran', 'Lunas')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pengiriman.create', compact('pesanans'));
    }

    public function getPesananDetail($id)
    {
        $pesanan = DB::table('pesanan')
            ->leftJoin('customers', 'pesanan.customer_id', '=', 'customers.id')
            ->where('pesanan.id', $id)
            ->select('pesanan.*', 'customers.nama as customer_nama')
            ->first();

        if (!$pesanan) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $details = DB::table('pesanan_detail')
            ->leftJoin('master_barang', 'pesanan_detail.produk_id', '=', 'master_barang.id')
            ->where('pesanan_detail.pesanan_id', $id)
            ->select('pesanan_detail.*', 'master_barang.nama as barang_nama', 'master_barang.satuan as barang_satuan')
            ->get();

        $formattedDetails = $details->map(function ($item) {
            return [
                'barang_id' => $item->produk_id,
                'qty' => $item->qty ?? 0,
                'barang' => [
                    'nama' => $item->barang_nama ?? 'Produk Tanpa Nama',
                    'satuan' => $item->barang_satuan ?? 'Unit'
                ]
            ];
        });

        return response()->json([
            'id' => $pesanan->id,
            'kode_pesanan' => $pesanan->kode_pesanan,
            'customer' => [
                'nama' => $pesanan->customer_nama
            ],
            'details' => $formattedDetails
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pesanan_id' => 'required',
            'tanggal_pengiriman' => 'required|date',
            'kurir' => 'required|string',
            'details' => 'required|array',
            'details.*.barang_id' => 'required',
            'details.*.qty_kirim' => 'required|numeric|min:1',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_pengiriman)) {
            return back()->with('error', 'Gagal menyimpan: Periode akuntansi untuk tanggal pengiriman ' . date('d/m/Y', strtotime($request->tanggal_pengiriman)) . ' sudah ditutup buku (closing).')->withInput();
        }

        $pesanan = DB::table('pesanan')->where('id', $request->pesanan_id)->first();
        if (!$pesanan) {
            return back()->with('error', 'Data pesanan tidak ditemukan.');
        }

        if ($pesanan->status_pembayaran !== 'Lunas') {
            return back()->with('error', 'Gagal membuat pengiriman: Pesanan B2B ini belum lunas.');
        }

        DB::beginTransaction();
        try {
            // Simpan data sebagai DRAFT
            $pengiriman = Pengiriman::create([
                'no_pengiriman' => 'SJ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'pesanan_id' => $request->pesanan_id,
                'tanggal_pengiriman' => $request->tanggal_pengiriman,
                'kurir' => $request->kurir,
                'status_pengiriman' => 'Draft',
            ]);

            foreach ($request->details as $detail) {
                PengirimanDetail::create([
                    'pengiriman_id' => $pengiriman->id,
                    'barang_id' => $detail['barang_id'],
                    'qty_kirim' => $detail['qty_kirim'],
                ]);
            }

            DB::commit();
            return redirect()->route('pengiriman.index')->with('success', 'Draft surat jalan berhasil dibuat. Silakan lakukan approval untuk memotong stok.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membuat draft pengiriman: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $pengiriman = Pengiriman::with(['details.barang', 'pesanan.customer'])->findOrFail($id);
        return view('pengiriman.show', compact('pengiriman'));
    }

    public function edit($id)
    {
        $pengiriman = Pengiriman::with('details.barang')->findOrFail($id);
        
        if ($pengiriman->status_pengiriman !== 'Draft') {
            return redirect()->route('pengiriman.index')->with('error', 'Pengiriman yang sudah disetujui tidak dapat diedit.');
        }

        $pesanans = Pesanan::with('customer')
            ->where('status_pembayaran', 'Lunas')
            ->get(); // Mengambil pesanan lunas untuk keperluan edit

        return view('pengiriman.edit', compact('pengiriman', 'pesanans'));
    }

    public function update(Request $request, $id)
    {
        $pengiriman = Pengiriman::findOrFail($id);

        if ($pengiriman->status_pengiriman !== 'Draft') {
            return redirect()->route('pengiriman.index')->with('error', 'Pengiriman yang sudah disetujui tidak dapat diubah.');
        }

        $pesanan = DB::table('pesanan')->where('id', $pengiriman->pesanan_id)->first();
        if (!$pesanan || $pesanan->status_pembayaran !== 'Lunas') {
            return back()->with('error', 'Gagal memperbarui pengiriman: Pesanan B2B ini belum lunas.');
        }

        $request->validate([
            'tanggal_pengiriman' => 'required|date',
            'kurir' => 'required|string',
            'details' => 'required|array',
            'details.*.id' => 'required',
            'details.*.qty_kirim' => 'required|numeric|min:1',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_pengiriman)) {
            return back()->with('error', 'Gagal memperbarui: Periode akuntansi untuk tanggal pengiriman ' . date('d/m/Y', strtotime($request->tanggal_pengiriman)) . ' sudah ditutup buku (closing).')->withInput();
        }

        DB::beginTransaction();
        try {
            $pengiriman->update([
                'tanggal_pengiriman' => $request->tanggal_pengiriman,
                'kurir' => $request->kurir,
            ]);

            foreach ($request->details as $detailData) {
                $detail = PengirimanDetail::findOrFail($detailData['id']);
                $detail->update([
                    'qty_kirim' => $detailData['qty_kirim']
                ]);
            }

            DB::commit();
            return redirect()->route('pengiriman.index')->with('success', 'Draft pengiriman berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui draft: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $pengiriman = Pengiriman::findOrFail($id);
        
        if ($pengiriman->status_pengiriman !== 'Draft') {
            return back()->with('error', 'Tidak dapat menghapus pengiriman yang sudah Selesai.');
        }

        DB::beginTransaction();
        try {
            PengirimanDetail::where('pengiriman_id', $id)->delete();
            $pengiriman->delete();

            DB::commit();
            return redirect()->route('pengiriman.index')->with('success', 'Draft pengiriman berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus draft: ' . $e->getMessage());
        }
    }

    public function approve($id)
    {
        $pengiriman = Pengiriman::with('details')->findOrFail($id);

        if (\App\Models\Journal::isPeriodClosed($pengiriman->tanggal_pengiriman)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($pengiriman->tanggal_pengiriman)) . ' sudah ditutup buku. Tidak dapat memproses pengiriman B2B pada periode yang sudah ditutup.');
        }

        if ($pengiriman->status_pengiriman !== 'Draft') {
            return back()->with('error', 'Data ini sudah disetujui sebelumnya.');
        }

        $pesanan = DB::table('pesanan')->where('id', $pengiriman->pesanan_id)->first();
        if (!$pesanan || $pesanan->status_pembayaran !== 'Lunas') {
            return back()->with('error', 'Gagal memproses Approve: Pesanan B2B ini belum lunas.');
        }

        DB::beginTransaction();
        try {
            $gudangB2B = MasterGudang::where('kategori', 'Produksi')->first();
            if (!$gudangB2B) throw new \Exception('Gudang kategori Produksi (Hasil Jadi) tidak ditemukan.');

            foreach ($pengiriman->details as $detail) {
                $barangId = $detail->barang_id;
                $qtyKirim = floatval($detail->qty_kirim);

                // 1. Ambil & Kunci SEMUA baris Alokasi Produksi Pesanan untuk pesanan+produk ini.
                // Bisa lebih dari satu baris kalau produksi barang ini dilakukan
                // bertahap/multi-batch (tiap batch produksi bikin baris alokasi
                // sendiri, bukan menumpuk ke baris yang sama). Diurutkan dari
                // yang paling lama (id asc) supaya pengurangan qty_terkirim
                // dilakukan berurutan sesuai batch produksinya (FIFO alokasi).
                $alokasiList = ProduksiPesanan::where('pesanan_id', $pengiriman->pesanan_id)
                    ->where('produk_id', $barangId)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($alokasiList->isEmpty()) {
                    throw new \Exception('Alokasi produksi untuk produk ini belum tersedia.');
                }

                $sisaAlokasi = $alokasiList->sum(function ($a) {
                    return floatval($a->qty_alokasi) - floatval($a->qty_terkirim);
                });

                if ($qtyKirim > $sisaAlokasi) {
                    throw new \Exception('Qty kirim (' . $qtyKirim . ') melebihi sisa alokasi barang jadi (' . $sisaAlokasi . ').');
                }

                // 2. Kunci & Kurangi Stok Gudang Produksi (Barang Jadi)
                $stok = DB::table('stok_gudang')
                    ->where('gudang_id', $gudangB2B->id)
                    ->where('barang_id', $barangId)
                    ->lockForUpdate()
                    ->first();

                if (!$stok || floatval($stok->jumlah) < $qtyKirim) {
                    throw new \Exception('Stok barang jadi di gudang tidak mencukupi.');
                }

                // Jalankan Increment Alokasi (didistribusikan ke tiap baris alokasi
                // secara berurutan, baris paling lama diisi/dihabiskan dulu) &
                // Decrement Stok Gudang
                $qtySisaUntukDikurangi = $qtyKirim;
                foreach ($alokasiList as $alokasi) {
                    if ($qtySisaUntukDikurangi <= 0) break;

                    $sisaBarisIni = floatval($alokasi->qty_alokasi) - floatval($alokasi->qty_terkirim);
                    if ($sisaBarisIni <= 0) continue;

                    $ambil = min($sisaBarisIni, $qtySisaUntukDikurangi);
                    $alokasi->increment('qty_terkirim', $ambil);
                    $qtySisaUntukDikurangi -= $ambil;
                }

                DB::table('stok_gudang')->where('id', $stok->id)->decrement('jumlah', $qtyKirim);

                // Potong Stok Batch (FIFO)
                $fifoResult = app(\App\Services\FifoService::class)->consumeFIFO(
                    $barangId,
                    $qtyKirim,
                    $gudangB2B->id,
                    true // allowNegative
                );

                $totalHppKirim = 0;
                foreach ($fifoResult as $layer) {
                    $totalHppKirim += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                }

                // Catat Log Transaksi Stok
                \App\Models\TransaksiStok::create([
                    'tanggal'        => now(),
                    'tipe'           => 'keluar',
                    'source_type'    => 'pengiriman',
                    'source_id'      => $pengiriman->id,
                    'gudang_asal_id' => $gudangB2B->id,
                    'barang_id'      => $barangId,
                    'qty'            => $qtyKirim,
                    'total_harga'    => $totalHppKirim,
                    'created_by'     => auth()->id() ?? 1,
                ]);
            }

            // 3. Hitung Akumulasi Total Terkirim Seluruh Surat Jalan untuk Pesanan Ini
            $pengiriman->update(['status_pengiriman' => 'Selesai']);

            $detailPesanan = DB::table('pesanan_detail')->where('pesanan_id', $pengiriman->pesanan_id)->get();
            $semuaSudahTerkirim = true;

            foreach ($detailPesanan as $dp) {
                $qtySudahKirim = DB::table('pengiriman_detail')
                    ->join('pengiriman', 'pengiriman_detail.pengiriman_id', '=', 'pengiriman.id')
                    ->where('pengiriman.pesanan_id', $pengiriman->pesanan_id)
                    ->where('pengiriman_detail.barang_id', $dp->produk_id)
                    ->where('pengiriman.status_pengiriman', 'Selesai')
                    ->sum('pengiriman_detail.qty_kirim');

                if (floatval($qtySudahKirim) < floatval($dp->qty)) {
                    $semuaSudahTerkirim = false;
                    break;
                }
            }

            // 4. Update status pesanan final
            DB::table('pesanan')
                ->where('id', $pengiriman->pesanan_id)
                ->update([
                    'status_pesanan' => $semuaSudahTerkirim ? 'Selesai' : 'Siap kirim',
                    'updated_at' => now(),
                ]);

            DB::commit();
            return redirect()->route('pengiriman.index')->with('success', 'Surat Jalan berhasil disetujui! Stok gudang telah dipotong.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses Approve: ' . $e->getMessage());
        }
    }
}