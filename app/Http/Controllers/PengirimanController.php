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
        $tipe = $request->query('tipe'); // 'all', 'b2b', 'central_kitchen'

        $query = Pesanan::with(['customer', 'gudang', 'details.produk', 'pengirimans.details.barang']);

        if ($tipe === 'central_kitchen') {
            $query->where('tipe_pesanan', 'central_kitchen');
        } elseif ($tipe === 'b2b') {
            $query->where(function($q) {
                $q->where('tipe_pesanan', 'b2b')->orWhereNull('tipe_pesanan');
            });
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_pesanan', 'like', '%' . $search . '%')
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('nama', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('gudang', function($gq) use ($search) {
                      $gq->where('nama', 'like', '%' . $search . '%');
                  });
            });
        }

        $pesanans = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $countQuery = Pesanan::query();
        if ($tipe === 'central_kitchen') {
            $countQuery->where('tipe_pesanan', 'central_kitchen');
        } elseif ($tipe === 'b2b') {
            $countQuery->where(function($q) {
                $q->where('tipe_pesanan', 'b2b')->orWhereNull('tipe_pesanan');
            });
        }

        $totalData = (clone $countQuery)->count();
        $totalBelum = (clone $countQuery)->whereDoesntHave('pengirimans', function($q) {
            $q->where('status_pengiriman', 'Selesai');
        })->count();
        $totalTerkirim = (clone $countQuery)->whereHas('pengirimans', function($q) {
            $q->where('status_pengiriman', 'Selesai');
        })->count();

        return view('pengiriman.index', compact('pesanans', 'totalData', 'totalBelum', 'totalTerkirim', 'tipe'));
    }

    public function create()
    {
        return redirect()->route('pengiriman.index');
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
            'details.*.qty_kirim' => 'required|numeric|min:0',
        ]);

        // Filter out items with qty_kirim = 0 (already fully shipped)
        $activeDetails = collect($request->details)->filter(function ($d) {
            return floatval($d['qty_kirim']) > 0;
        })->values()->all();

        if (empty($activeDetails)) {
            return back()->with('error', 'Tidak ada item yang perlu dikirim. Semua item sudah terkirim lengkap.');
        }

        if (\App\Models\Journal::isPeriodClosed($request->tanggal_pengiriman)) {
            return back()->with('error', 'Gagal memproses pengiriman: Periode akuntansi untuk tanggal ' . date('d/m/Y', strtotime($request->tanggal_pengiriman)) . ' sudah ditutup buku (closing).')->withInput();
        }

        $pesanan = DB::table('pesanan')->where('id', $request->pesanan_id)->first();
        if (!$pesanan) {
            return back()->with('error', 'Data pesanan tidak ditemukan.');
        }

        $isCentralKitchen = ($pesanan->tipe_pesanan ?? 'b2b') === 'central_kitchen';

        DB::beginTransaction();
        try {
            // Langsung Simpan sebagai Selesai (tanpa Draft)
            $pengiriman = Pengiriman::create([
                'no_pengiriman' => 'SJ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'pesanan_id' => $request->pesanan_id,
                'tanggal_pengiriman' => $request->tanggal_pengiriman,
                'kurir' => $request->kurir,
                'status_pengiriman' => 'Selesai',
            ]);

            $gudangB2B = MasterGudang::where('nama', 'Gudang Central Kitchen')->first() 
                ?? MasterGudang::where('kategori', 'Produksi')->first();
            if (!$gudangB2B) throw new \Exception('Gudang Central Kitchen / Produksi tidak ditemukan.');

            // Tentukan gudang tujuan outlet (berlaku untuk Central Kitchen maupun Cold Kitchen jika pemesan adalah outlet)
            $gudangTujuanOutlet = null;
            // Gunakan gudang_id pesanan jika tersedia DAN bukan gudang asal
            if ($pesanan->gudang_id && $pesanan->gudang_id != $gudangB2B->id) {
                $gudangTujuanOutlet = MasterGudang::find($pesanan->gudang_id);
            }
            // Fallback: resolusi dari nama customer/outlet
            if (!$gudangTujuanOutlet && $pesanan->customer_id) {
                $customer = DB::table('customers')->where('id', $pesanan->customer_id)->first();
                $custNama = strtolower($customer->nama ?? '');
                if (str_contains($custNama, 'kejingga')) {
                    $gudangTujuanOutlet = MasterGudang::where('nama', 'like', '%KeJingga%')
                        ->orWhere('nama', 'like', '%Kejingga%')->first();
                } elseif (str_contains($custNama, 'gaharu')) {
                    $gudangTujuanOutlet = MasterGudang::where('nama', 'like', '%Gaharu%')
                        ->where('kategori', 'Operasional')->first();
                }
            }

            foreach ($activeDetails as $detail) {
                $barangId = $detail['barang_id'];
                $qtyKirim = floatval($detail['qty_kirim']);

                PengirimanDetail::create([
                    'pengiriman_id' => $pengiriman->id,
                    'barang_id' => $barangId,
                    'qty_kirim' => $qtyKirim,
                ]);

                // Update alokasi produksi jika ada
                $alokasiList = ProduksiPesanan::where('pesanan_id', $pesanan->id)
                    ->where('produk_id', $barangId)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($alokasiList->isNotEmpty()) {
                    $qtySisaUntukDikurangi = $qtyKirim;
                    foreach ($alokasiList as $alokasi) {
                        if ($qtySisaUntukDikurangi <= 0) break;
                        $sisaBarisIni = floatval($alokasi->qty_alokasi) - floatval($alokasi->qty_terkirim);
                        if ($sisaBarisIni <= 0) continue;
                        $ambil = min($sisaBarisIni, $qtySisaUntukDikurangi);
                        $alokasi->increment('qty_terkirim', $ambil);
                        $qtySisaUntukDikurangi -= $ambil;
                    }
                }

                $stok = DB::table('stok_gudang')
                    ->where('gudang_id', $gudangB2B->id)
                    ->where('barang_id', $barangId)
                    ->lockForUpdate()
                    ->first();

                if (!$stok || floatval($stok->jumlah) < $qtyKirim) {
                    $barangNama = DB::table('master_barang')->where('id', $barangId)->value('nama') ?? 'Barang';
                    throw new \Exception("Stok '{$barangNama}' di Central Kitchen tidak mencukupi untuk dikirim.");
                }

                DB::table('stok_gudang')->where('id', $stok->id)->decrement('jumlah', $qtyKirim);

                // Potong Stok Batch (FIFO) di Gudang CK
                $fifoResult = app(\App\Services\FifoService::class)->consumeFIFO(
                    $barangId,
                    $qtyKirim,
                    $gudangB2B->id,
                    true
                );

                $totalHppKirim = 0;
                foreach ($fifoResult as $layer) {
                    $totalHppKirim += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                }

                $hppPerUnitKirim = $qtyKirim > 0 ? ($totalHppKirim / $qtyKirim) : 0;

                // Catat Log Transaksi Keluar dari CK
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

                // Masukkan stok & batch ke Gudang Outlet Pemesan (berlaku untuk Central Kitchen & Cold Kitchen)
                if ($gudangTujuanOutlet) {
                    $stokOutlet = DB::table('stok_gudang')
                        ->where('gudang_id', $gudangTujuanOutlet->id)
                        ->where('barang_id', $barangId)
                        ->first();

                    if ($stokOutlet) {
                        DB::table('stok_gudang')->where('id', $stokOutlet->id)->increment('jumlah', $qtyKirim);
                    } else {
                        \App\Models\StokGudang::create([
                            'gudang_id' => $gudangTujuanOutlet->id,
                            'barang_id' => $barangId,
                            'jumlah'    => $qtyKirim,
                        ]);
                    }

                    $supplierId  = DB::table('suppliers')->value('id') ?? 1;
                    $pembelianId = DB::table('pembelian')->value('id') ?? 1;
                    $pemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                    \App\Models\StokGudangBatch::create([
                        'gudang_id'           => $gudangTujuanOutlet->id,
                        'supplier_id'         => $supplierId,
                        'barang_id'           => $barangId,
                        'pembelian_id'        => $pembelianId,
                        'pembelian_detail_id' => $pemDetailId,
                        'batch_number'        => 'TRANSFER-CK-' . $pengiriman->no_pengiriman,
                        'qty_masuk'           => $qtyKirim,
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $qtyKirim,
                        'harga_per_qty'       => $hppPerUnitKirim,
                        'is_habis'            => false,
                    ]);

                    \App\Models\TransaksiStok::create([
                        'tanggal'          => now(),
                        'tipe'             => 'masuk',
                        'source_type'      => 'transfer_ck',
                        'source_id'        => $pengiriman->id,
                        'gudang_tujuan_id' => $gudangTujuanOutlet->id,
                        'barang_id'        => $barangId,
                        'qty'              => $qtyKirim,
                        'total_harga'      => $totalHppKirim,
                        'created_by'       => auth()->id() ?? 1,
                    ]);
                }
            }

            $detailPesanan = DB::table('pesanan_detail')->where('pesanan_id', $pesanan->id)->get();
            $semuaSudahTerkirim = true;

            foreach ($detailPesanan as $dp) {
                $qtySudahKirim = DB::table('pengiriman_detail')
                    ->join('pengiriman', 'pengiriman_detail.pengiriman_id', '=', 'pengiriman.id')
                    ->where('pengiriman.pesanan_id', $pesanan->id)
                    ->where('pengiriman_detail.barang_id', $dp->produk_id)
                    ->where('pengiriman.status_pengiriman', 'Selesai')
                    ->sum('pengiriman_detail.qty_kirim');

                if (floatval($qtySudahKirim) < floatval($dp->qty)) {
                    $semuaSudahTerkirim = false;
                    break;
                }
            }

            DB::table('pesanan')
                ->where('id', $pesanan->id)
                ->update([
                    'status_pesanan' => $semuaSudahTerkirim ? 'Selesai' : 'Siap kirim',
                    'updated_at' => now(),
                ]);

            if (!$isCentralKitchen) {
                // Auto post B2B shipment journal (Omzet & HPP)
                \App\Http\Controllers\JurnalController::autoPostPenjualanB2b($pengiriman->id, 'pengiriman');
            }

            DB::commit();

            $msg = $isCentralKitchen
                ? 'Pengiriman Central Kitchen berhasil diproses! Barang telah otomatis masuk ke Gudang Outlet.'
                : 'Pengiriman pesanan B2B berhasil diproses! Stok berkurang dan jurnal penjualan otomatis terposting.';

            return redirect()->route('pengiriman.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses pengiriman: ' . $e->getMessage());
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
        if (!$pesanan) {
            return back()->with('error', 'Gagal memproses Approve: Data pesanan tidak ditemukan.');
        }

        $isCentralKitchen = ($pesanan->tipe_pesanan ?? 'b2b') === 'central_kitchen';
        if (!$isCentralKitchen && $pesanan->status_pembayaran !== 'Lunas') {
            return back()->with('error', 'Gagal memproses Approve: Pesanan B2B ini belum lunas.');
        }

        DB::beginTransaction();
        try {
            $gudangB2B = MasterGudang::where('nama', 'Gudang Central Kitchen')->first() 
                ?? MasterGudang::where('kategori', 'Produksi')->first();
            if (!$gudangB2B) throw new \Exception('Gudang Central Kitchen / Produksi tidak ditemukan.');

            // Tentukan gudang tujuan outlet jika ini pesanan Central Kitchen
            $gudangTujuanOutlet = null;
            if ($isCentralKitchen) {
                // Gunakan gudang_id pesanan jika tersedia DAN bukan gudang CK (sumber)
                if ($pesanan->gudang_id && $pesanan->gudang_id != $gudangB2B->id) {
                    $gudangTujuanOutlet = MasterGudang::find($pesanan->gudang_id);
                }
                // Fallback: resolusi dari nama customer
                if (!$gudangTujuanOutlet) {
                    $customer = DB::table('customers')->where('id', $pesanan->customer_id)->first();
                    $custNama = strtolower($customer->nama ?? '');
                    if (str_contains($custNama, 'kejingga')) {
                        $gudangTujuanOutlet = MasterGudang::where('nama', 'like', '%KeJingga%')
                            ->orWhere('nama', 'like', '%Kejingga%')->first();
                    } else {
                        $gudangTujuanOutlet = MasterGudang::where('nama', 'like', '%Gaharu%')
                            ->where('kategori', 'Operasional')->first();
                    }
                }
            }

            foreach ($pengiriman->details as $detail) {
                $barangId = $detail->barang_id;
                $qtyKirim = floatval($detail->qty_kirim);

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
                    throw new \Exception('Qty kirim (' . $qtyKirim . ') melebihi sisa alokasi barang (' . $sisaAlokasi . ').');
                }

                $stok = DB::table('stok_gudang')
                    ->where('gudang_id', $gudangB2B->id)
                    ->where('barang_id', $barangId)
                    ->lockForUpdate()
                    ->first();

                if (!$stok || floatval($stok->jumlah) < $qtyKirim) {
                    throw new \Exception('Stok barang di Central Kitchen tidak mencukupi.');
                }

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

                // Potong Stok Batch (FIFO) di Gudang CK
                $fifoResult = app(\App\Services\FifoService::class)->consumeFIFO(
                    $barangId,
                    $qtyKirim,
                    $gudangB2B->id,
                    true
                );

                $totalHppKirim = 0;
                foreach ($fifoResult as $layer) {
                    $totalHppKirim += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                }

                $hppPerUnitKirim = $qtyKirim > 0 ? ($totalHppKirim / $qtyKirim) : 0;

                // Catat Log Transaksi Keluar dari CK
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

                // JIKA CENTRAL KITCHEN: Masukkan stok & batch ke Gudang Outlet Pemesan dengan nilai HPP
                if ($isCentralKitchen && $gudangTujuanOutlet) {
                    $stokOutlet = DB::table('stok_gudang')
                        ->where('gudang_id', $gudangTujuanOutlet->id)
                        ->where('barang_id', $barangId)
                        ->first();

                    if ($stokOutlet) {
                        DB::table('stok_gudang')->where('id', $stokOutlet->id)->increment('jumlah', $qtyKirim);
                    } else {
                        \App\Models\StokGudang::create([
                            'gudang_id' => $gudangTujuanOutlet->id,
                            'barang_id' => $barangId,
                            'jumlah'    => $qtyKirim,
                        ]);
                    }

                    $supplierId  = DB::table('suppliers')->value('id') ?? 1;
                    $pembelianId = DB::table('pembelian')->value('id') ?? 1;
                    $pemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                    \App\Models\StokGudangBatch::create([
                        'gudang_id'           => $gudangTujuanOutlet->id,
                        'supplier_id'         => $supplierId,
                        'barang_id'           => $barangId,
                        'pembelian_id'        => $pembelianId,
                        'pembelian_detail_id' => $pemDetailId,
                        'batch_number'        => 'TRANSFER-CK-' . $pengiriman->no_pengiriman,
                        'qty_masuk'           => $qtyKirim,
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $qtyKirim,
                        'harga_per_qty'       => $hppPerUnitKirim,
                        'is_habis'            => false,
                    ]);

                    \App\Models\TransaksiStok::create([
                        'tanggal'          => now(),
                        'tipe'             => 'masuk',
                        'source_type'      => 'transfer_ck',
                        'source_id'        => $pengiriman->id,
                        'gudang_tujuan_id' => $gudangTujuanOutlet->id,
                        'barang_id'        => $barangId,
                        'qty'              => $qtyKirim,
                        'total_harga'      => $totalHppKirim,
                        'created_by'       => auth()->id() ?? 1,
                    ]);
                }
            }

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

            DB::table('pesanan')
                ->where('id', $pengiriman->pesanan_id)
                ->update([
                    'status_pesanan' => $semuaSudahTerkirim ? 'Selesai' : 'Siap kirim',
                    'updated_at' => now(),
                ]);

            if (!$isCentralKitchen) {
                // Auto post B2B shipment journal (Omzet & HPP)
                \App\Http\Controllers\JurnalController::autoPostPenjualanB2b($pengiriman->id, 'pengiriman');
            }

            DB::commit();
            $msg = $isCentralKitchen 
                ? 'Pengiriman Central Kitchen berhasil disetujui! Barang telah berhasil disetor ke Gudang Outlet dengan nilai HPP.'
                : 'Surat Jalan berhasil disetujui! Stok gudang telah dipotong dan jurnal penjualan B2B otomatis terposting.';
            return redirect()->route('pengiriman.index')->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses Approve: ' . $e->getMessage());
        }
    }
}