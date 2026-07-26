<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use App\Models\MasterBarang;
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
    | 1. HALAMAN RIWAYAT & DRAFT PRODUKSI
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Produksi::with(['details.produk', 'pesanan.customer']);

        if ($search) {
            $query->where('no_produksi', 'like', '%' . $search . '%');
        }

        $riwayatProduksi = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $totalData = Produksi::count();
        $totalDraft = Produksi::where('status_produksi', 'Draft')->count();
        $totalApproved = Produksi::where('status_produksi', 'Selesai')->count();

        return view('produksi.index', compact('riwayatProduksi', 'totalData', 'totalDraft', 'totalApproved'));
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
                $resepItems = ResepBahanBaku::where('resep_id', $produk->resep_id)->get();

                foreach ($resepItems as $item) {
                    $qtyButuh = floatval($item->qty_bahan) * $qtyHasil;

                    $fifoResult = $fifoService->consumeFIFO(
                        $item->bahan_id,
                        $qtyButuh,
                        $gudangBahanId
                    );

                    foreach ($fifoResult as $layer) {
                        $totalBbbProduk += floatval($layer['qty_keluar']) * floatval($layer['harga_per_qty']);
                    }

                    $stokBahanGlobal = StokGudang::where('gudang_id', $gudangBahanId)
                        ->where('barang_id', $item->bahan_id)
                        ->first();

                    if ($stokBahanGlobal) {
                        $stokBahanGlobal->decrement('jumlah', $qtyButuh);
                    } else {
                        StokGudang::create([
                            'gudang_id' => $gudangBahanId,
                            'barang_id' => $item->bahan_id,
                            'jumlah'    => 0 - $qtyButuh,
                        ]);
                    }
                }

                // B. HITUNG BTKL & BOP
                $totalBtklBop = 0;
                $biayaTambahan = DB::table('resep_btkl_bop')->where('produk_id', $produkId)->first();

                if ($biayaTambahan && $biayaTambahan->output_qty > 0) {
                    $btklPerBatch = floatval($biayaTambahan->btkl_per_batch);
                    $bopPerBatch  = floatval($biayaTambahan->bop_per_batch);
                    $outputBatch  = floatval($biayaTambahan->output_qty);
                    $biayaPerItem = ($btklPerBatch + $bopPerBatch) / $outputBatch;

                    $totalBtklBop = $biayaPerItem * $qtyHasil;
                }

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