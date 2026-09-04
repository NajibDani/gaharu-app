<?php

namespace App\Http\Controllers;

use App\Models\PenjualanPos;
use App\Models\PenjualanPosDetail;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\StokGudang;
use App\Models\HargaPeriode; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PenjualanPosController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->query('search');
        $query = PenjualanPos::latest();
        
        if ($user->gudang_id) {
            $query->where('gudang_id', $user->gudang_id);
        }

        if ($search) {
            $query->where('no_transaksi', 'like', '%' . $search . '%');
        }
        
        $data = $query->paginate(10)->withQueryString();
        $gudangList = MasterGudang::all();
        return view('penjualan_pos.index', compact('data', 'gudangList'));
    }

    public function create()
    {
        $user = auth()->user();
        $queryProduk = MasterBarang::where('is_barang_jadi', 1)->where('is_active', true);
        $queryGudang = MasterGudang::query();

        if ($user->gudang_id) {
            if ($user->gudang_id == 2) {
                $queryProduk->where('tipe_penjualan', 'POS Gaharu');
            } elseif ($user->gudang_id == 4) {
                $queryProduk->where('tipe_penjualan', 'POS Kejingga');
            } else {
                $queryProduk->where('tipe_penjualan', 'POS Gaharu');
            }
            $queryGudang->where('id', $user->gudang_id);
        }

        $produk = $queryProduk->get();
        $gudang = $queryGudang->get();

        return view('penjualan_pos.create', compact('produk', 'gudang'));
    }

    /**
     * 1. SIMPAN INPUTAN BARU (STATUS: Draft)
     */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal'     => 'required',
            'gudang_id'   => 'required|exists:master_gudang,id',
            'produk_id'   => 'required|array',
            'produk_id.*' => 'required|exists:master_barang,id',
            'qty'         => 'required|array',
            'qty.*'       => 'required|numeric|min:0.01',
            'harga'       => 'required|array',
            'harga.*'     => 'required|numeric',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat membuat transaksi POS pada periode yang sudah ditutup.')->withInput();
        }

        if (date('Y-m-d', strtotime($request->tanggal)) < date('Y-m-d')) {
            return back()->with('error', 'Tanggal transaksi tidak boleh sebelum hari ini.')->withInput();
        }

        $user = auth()->user();
        if (in_array($request->gudang_id, [1, 2])) {
            return back()->with('error', 'Gudang Utama dan Central Kitchen hanya melayani transfer/pengeluaran bahan, tidak diizinkan untuk pemotongan stok transaksi penjualan POS.')->withInput();
        }

        if ($user->gudang_id && $request->gudang_id != $user->gudang_id) {
            return back()->with('error', 'Anda tidak diizinkan membuat transaksi untuk gudang lain.')->withInput();
        }

        // Validasi Resep & Harga Jual
        foreach ($request->produk_id as $key => $produkId) {
            $barang = MasterBarang::find($produkId);
            if (!$barang || !$barang->is_active) {
                return back()->with('error', "Gagal! Produk '" . ($barang->nama ?? 'pilihan') . "' sedang tidak aktif dan tidak dapat digunakan dalam transaksi.")
                    ->withInput();
            }

            if (is_null($barang->resep_id)) {
                return back()->with('error', "Gagal! Produk '{$barang->nama}' belum memiliki resep.")
                    ->withInput();
            }

            $tanggal = $request->tanggal ? date('Y-m-d', strtotime($request->tanggal)) : now()->toDateString();
            $hargaAktif = HargaPeriode::where('barang_id', $produkId)
                ->whereDate('tgl_mulai', '<=', $tanggal) 
                ->where(function($query) use ($tanggal) {
                    $query->whereNull('tgl_selesai')->orWhereDate('tgl_selesai', '>=', $tanggal);
                })
                ->orderBy('tgl_mulai', 'desc')
                ->first();
            $harga = $hargaAktif ? (float) $hargaAktif->harga_pos : (float) $barang->harga_jual_pos;
            if ($harga <= 0) {
                return back()->with('error', "Gagal! Produk '{$barang->nama}' belum memiliki harga jual POS yang aktif.")
                    ->withInput();
            }
        }
    
        DB::beginTransaction();
    
        try {
            $kodePos = 'POS-' . time();
            $tanggalTrans = date('Y-m-d H:i:s', strtotime($request->tanggal));

            $penjualan = PenjualanPos::create([
                'kode_transaksi' => $kodePos,
                'status'         => 'Draft', // Status awal selalu Draft
                'tanggal'        => $tanggalTrans,
                'gudang_id'      => $request->gudang_id,
                'total'          => 0,
                'created_by'     => auth()->id() ?? 1
            ]);
    
            // Kelompokkan produk jika ada menu/produk yang sama (totalkan Qty & Subtotal)
            $groupedItems = [];
            foreach ($request->produk_id as $key => $produkId) {
                if (!isset($request->qty[$key]) || !isset($request->harga[$key])) continue;

                $qtyTerjual = floatval($request->qty[$key]);
                $hargaJual  = floatval($request->harga[$key]);
                $subtotal   = $qtyTerjual * $hargaJual;

                if (isset($groupedItems[$produkId])) {
                    $groupedItems[$produkId]['qty'] += $qtyTerjual;
                    $groupedItems[$produkId]['subtotal'] += $subtotal;
                    if ($groupedItems[$produkId]['qty'] > 0) {
                        $groupedItems[$produkId]['harga'] = round($groupedItems[$produkId]['subtotal'] / $groupedItems[$produkId]['qty'], 2);
                    }
                } else {
                    $groupedItems[$produkId] = [
                        'produk_id' => $produkId,
                        'qty'       => $qtyTerjual,
                        'harga'     => $hargaJual,
                        'subtotal'  => $subtotal,
                    ];
                }
            }

            $total_penjualan = 0;
            foreach ($groupedItems as $it) {
                // HPP diset 0 saat simpan awal (Draft)
                PenjualanPosDetail::create([ 
                    'penjualan_id' => $penjualan->id, 
                    'produk_id'    => $it['produk_id'],
                    'qty'          => $it['qty'],
                    'harga'        => $it['harga'],
                    'hpp_satuan'   => 0, 
                    'subtotal'     => $it['subtotal']
                ]);

                $total_penjualan += $it['subtotal'];
            }
    
            $penjualan->update(['total' => $total_penjualan]);
            DB::commit();
    
            return redirect()->route('penjualan_pos.index')->with('success', 'Rekap berhasil disimpan (Status: Draft). HPP dan Stok belum dipotong sebelum di-Approve.');
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Simpan POS: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan sistem saat simpan: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id) 
    {
        $penjualan = PenjualanPos::with(['details.produk', 'creator', 'pembayaran'])->findOrFail($id);
        return view('penjualan_pos.show', compact('penjualan'));
    }



    /**
     * 2. TRANSAKSI BISA DIEDIT JIKA Draft ATAU JIKA DIEDIT OLEH SUPER ADMIN (UNTUK KOREKSI HPP/JUMLAH)
     */
    public function edit($id) 
    {
        $penjualan = PenjualanPos::with('details.produk')->findOrFail($id);
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        
        if ($penjualan->status !== 'Draft' && !$isSuperAdmin) {
            return redirect()->route('penjualan_pos.index')->with('error', 'Transaksi yang telah di-Approve hanya dapat diedit/dikoreksi oleh Super Admin.');
        }

        if ($penjualan->status === 'VOID') {
            return redirect()->route('penjualan_pos.index')->with('error', 'Transaksi yang telah di-VOID tidak dapat diubah.');
        }
        
        $queryProduk = MasterBarang::where('is_barang_jadi', 1)->where('is_active', true);
        $queryGudang = MasterGudang::query();

        if ($user->gudang_id && !$isSuperAdmin) {
            if ($user->gudang_id == 2) {
                $queryProduk->where('tipe_penjualan', 'POS Gaharu');
            } elseif ($user->gudang_id == 4) {
                $queryProduk->where('tipe_penjualan', 'POS Kejingga');
            } else {
                $queryProduk->where('tipe_penjualan', 'POS Gaharu');
            }
            $queryGudang->where('id', $user->gudang_id);
        }

        $produk = $queryProduk->get();
        $gudang = $queryGudang->get();

        return view('penjualan_pos.edit', compact('penjualan', 'produk', 'gudang', 'isSuperAdmin'));
    }

    /**
     * 3. PROSES UPDATE DATA (Draft / Koreksi Super Admin)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'tanggal'     => 'required',
            'gudang_id'   => 'required|exists:master_gudang,id',
            'produk_id'   => 'required|array',
            'produk_id.*' => 'required|exists:master_barang,id',
            'qty'         => 'required|array',
            'qty.*'       => 'required|numeric|min:0.01',
            'harga'       => 'required|array',
            'harga.*'     => 'required|numeric',
            'hpp_satuan'  => 'nullable|array',
            'hpp_satuan.*'=> 'nullable|numeric|min:0',
        ]);

        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        if (!$isSuperAdmin && date('Y-m-d', strtotime($request->tanggal)) < date('Y-m-d')) {
            return back()->with('error', 'Tanggal transaksi tidak boleh sebelum hari ini.')->withInput();
        }

        if (in_array($request->gudang_id, [1, 2])) {
            return back()->with('error', 'Gudang Utama dan Central Kitchen hanya melayani transfer/pengeluaran bahan, tidak diizinkan untuk pemotongan stok transaksi penjualan POS.')->withInput();
        }

        if ($user->gudang_id && !$isSuperAdmin && $request->gudang_id != $user->gudang_id) {
            return back()->with('error', 'Anda tidak diizinkan mengubah transaksi ke gudang lain.')->withInput();
        }

        // Validasi Resep & Produk Aktif
        foreach ($request->produk_id as $key => $produkId) {
            $barang = MasterBarang::find($produkId);
            if (!$barang || !$barang->is_active) {
                return back()->with('error', "Gagal! Produk '" . ($barang->nama ?? 'pilihan') . "' sedang tidak aktif dan tidak dapat digunakan dalam transaksi.")
                    ->withInput();
            }

            if (is_null($barang->resep_id) && !$isSuperAdmin) {
                return back()->with('error', "Gagal! Produk '{$barang->nama}' belum memiliki resep.")
                    ->withInput();
            }
        }
    
        DB::beginTransaction();

        try {
            $penjualan = PenjualanPos::with('details')->findOrFail($id);
            
            if ($penjualan->status !== 'Draft' && !$isSuperAdmin) {
                return redirect()->route('penjualan_pos.index')->with('error', 'Transaksi yang telah di-Approve hanya dapat diubah oleh Super Admin.');
            }

            if ($penjualan->status === 'VOID') {
                return redirect()->route('penjualan_pos.index')->with('error', 'Transaksi berstatus VOID tidak dapat diubah.');
            }

            $statusSebelumnya = $penjualan->status;

            // Jika sebelumnya sudah SUKSES (Approved), rollback stok dan jurnalnya terlebih dahulu
            if ($statusSebelumnya === 'SUKSES') {
                $gudangLamaId = $penjualan->gudang_id;
                foreach ($penjualan->details as $oldDetail) {
                    $barangJadi = DB::table('master_barang')->where('id', $oldDetail->produk_id)->first();
                    $resepUtama = ($barangJadi && $barangJadi->resep_id) ? DB::table('resep_btkl_bop')->where('id', $barangJadi->resep_id)->first() : null;

                    if ($resepUtama) {
                        $resepBahan = DB::table('resep_bahanbaku')->where('resep_id', $resepUtama->id)->get();
                        foreach ($resepBahan as $bahan) {
                            $kebutuhanPerPcs = floatval($bahan->qty_bahan);
                            $qtyKembali = $kebutuhanPerPcs * floatval($oldDetail->qty);

                            $stokGudang = StokGudang::where('gudang_id', $gudangLamaId)->where('barang_id', $bahan->bahan_id)->first();
                            if ($stokGudang) {
                                $stokGudang->increment('jumlah', $qtyKembali);
                            }

                            $batchTerakhir = DB::table('stok_gudang_batch')->where('gudang_id', $gudangLamaId)->where('barang_id', $bahan->bahan_id)->orderBy('id', 'desc')->first();
                            if ($batchTerakhir) {
                                DB::table('stok_gudang_batch')->where('id', $batchTerakhir->id)->update([
                                    'qty_sisa'   => DB::raw("qty_sisa + {$qtyKembali}"),
                                    'qty_keluar' => DB::raw("qty_keluar - {$qtyKembali}"),
                                    'is_habis'   => 0
                                ]);
                            }
                        }
                    }
                }

                // Hapus pengeluaran bahan baku lama terkait AUTO_POS
                $oldPengeluaranList = DB::table('pengeluaran_bahan_baku')->where('keterangan', 'AUTO_POS:' . $penjualan->kode_transaksi)->get();
                foreach ($oldPengeluaranList as $oldPeng) {
                    DB::table('pengeluaran_bahan_baku_fifo')->where('pengeluaran_id', $oldPeng->id)->delete();
                    DB::table('pengeluaran_bahan_baku_detail')->where('pengeluaran_id', $oldPeng->id)->delete();
                    DB::table('pengeluaran_bahan_baku')->where('id', $oldPeng->id)->delete();
                }

                // Hapus jurnal akuntansi lama terkait penjualan POS ini
                $jurnalPosList = DB::table('jurnal_penjualan_pos')->where('source_type', 'penjualan_pos')->where('source_id', $penjualan->id)->get();
                foreach ($jurnalPosList as $jp) {
                    DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_penjualan_pos')->delete();
                    DB::table('jurnal_penjualan_pos')->where('id', $jp->id)->delete();
                }
            }

            $penjualan->update([
                'tanggal'   => date('Y-m-d H:i:s', strtotime($request->tanggal)),
                'gudang_id' => $request->gudang_id,
                'status'    => 'Draft', // Set Draft sementara untuk pemrosesan ulang
            ]);

            // Hapus detail lama
            PenjualanPosDetail::where('penjualan_id', $id)->delete();
            
            // Kelompokkan produk jika ada menu/produk yang sama (totalkan Qty & Subtotal)
            $groupedItems = [];
            foreach ($request->produk_id as $key => $produkId) {
                if (!isset($request->qty[$key]) || !isset($request->harga[$key])) continue;

                $qtyTerjual = floatval($request->qty[$key]);
                $hargaJual  = floatval($request->harga[$key]);
                $subtotal   = $qtyTerjual * $hargaJual;
                $hppInput   = isset($request->hpp_satuan[$key]) ? floatval($request->hpp_satuan[$key]) : 0;

                if (isset($groupedItems[$produkId])) {
                    $groupedItems[$produkId]['qty'] += $qtyTerjual;
                    $groupedItems[$produkId]['subtotal'] += $subtotal;
                    if ($groupedItems[$produkId]['qty'] > 0) {
                        $groupedItems[$produkId]['harga'] = round($groupedItems[$produkId]['subtotal'] / $groupedItems[$produkId]['qty'], 2);
                    }
                    if ($hppInput > 0) {
                        $groupedItems[$produkId]['hpp_satuan'] = $hppInput;
                    }
                } else {
                    $groupedItems[$produkId] = [
                        'produk_id'  => $produkId,
                        'qty'        => $qtyTerjual,
                        'harga'      => $hargaJual,
                        'hpp_satuan' => $hppInput,
                        'subtotal'   => $subtotal,
                    ];
                }
            }

            $total_penjualan = 0;
            foreach ($groupedItems as $it) {
                PenjualanPosDetail::create([ 
                    'penjualan_id' => $penjualan->id, 
                    'produk_id'    => $it['produk_id'],
                    'qty'          => $it['qty'],
                    'harga'        => $it['harga'],
                    'hpp_satuan'   => $it['hpp_satuan'] ?? 0,
                    'subtotal'     => $it['subtotal']
                ]);

                $total_penjualan += $it['subtotal'];
            }

            $penjualan->update(['total' => $total_penjualan]);
            DB::commit();

            // Jika sebelumnya status SUKSES, jalankan approve ulang agar stok dan HPP terkoreksi sempurna
            if ($statusSebelumnya === 'SUKSES') {
                $manualHppMap = [];
                foreach ($groupedItems as $it) {
                    if (isset($it['hpp_satuan']) && $it['hpp_satuan'] > 0) {
                        $manualHppMap[$it['produk_id']] = $it['hpp_satuan'];
                    }
                }

                $this->approve($penjualan->id);

                // Jika Super Admin menentukan HPP manual, terapkan HPP manual tersebut dan update jurnal HPP
                if (!empty($manualHppMap)) {
                    foreach ($manualHppMap as $pId => $mHpp) {
                        PenjualanPosDetail::where('penjualan_id', $penjualan->id)
                            ->where('produk_id', $pId)
                            ->update(['hpp_satuan' => $mHpp]);
                    }

                    // Koreksi nilai HPP pada jurnal akuntansi
                    $newTotalHpp = PenjualanPosDetail::where('penjualan_id', $penjualan->id)
                        ->selectRaw('SUM(qty * hpp_satuan) as total_hpp')
                        ->value('total_hpp') ?? 0;

                    $jurnalPos = DB::table('jurnal_penjualan_pos')->where('source_type', 'penjualan_pos')->where('source_id', $penjualan->id)->latest()->first();
                    if ($jurnalPos) {
                        $gudang = DB::table('master_gudang')->where('id', $penjualan->gudang_id)->first();
                        $isKejingga = ($penjualan->gudang_id == 4) || ($gudang && stripos($gudang->nama, 'kejingga') !== false);
                        $kodeHpp = $isKejingga ? '5102' : '5101';
                        $idHppPos = DB::table('chart_of_accounts')->where('kode', $kodeHpp)->value('id') ?? ($isKejingga ? 42 : 41);
                        $idPersediaanJadi = DB::table('chart_of_accounts')->where('kode', '1301')->value('id') ?? 19;

                        DB::table('journal_items')->where('journal_id', $jurnalPos->id)->where('account_id', $idHppPos)->update(['debit' => $newTotalHpp]);
                        DB::table('journal_items')->where('journal_id', $jurnalPos->id)->where('account_id', $idPersediaanJadi)->update(['kredit' => $newTotalHpp]);
                    }
                }

                return redirect()->route('penjualan_pos.index')->with('success', 'Koreksi penjualan POS berhasil disimpan! Stok dan HPP telah dihitung ulang secara otomatis.');
            }

            return redirect()->route('penjualan_pos.index')->with('success', 'Perubahan rekap penjualan berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Update POS: ' . $e->getMessage());
            return back()->with('error', 'Gagal update data: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * 4. PROSES APPROVAL: HPP DIHITUNG & STOK BARU TERPOTONG
     */
    public function approve($id)
    {
        DB::beginTransaction();

        try {
            $penjualan = PenjualanPos::with('details')->findOrFail($id);

            if (\App\Models\Journal::isPeriodClosed($penjualan->tanggal)) {
                return redirect()->route('penjualan_pos.index')->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($penjualan->tanggal)) . ' sudah ditutup buku. Tidak dapat memproses transaksi pada periode yang sudah ditutup.');
            }

            if ($penjualan->status !== 'Draft') {
                return redirect()->route('penjualan_pos.index')->with('error', 'Transaksi ini sudah pernah diproses sebelumnya.');
            }

            $kodePos = $penjualan->kode_transaksi;
            $tanggalTrans = $penjualan->tanggal;
            $gudangId = $penjualan->gudang_id;

            // -- A. Hitung total kebutuhan bahan baku
            $totalKebutuhanBahan = [];

            // Helper function to resolve ingredients recursively
            $resolveBahan = function($barangId, $qtyNeeded) use (&$resolveBahan, &$totalKebutuhanBahan, $gudangId) {
                $barang = DB::table('master_barang')->where('id', $barangId)->first();
                if (!$barang) return;

                // Check available stock of this item in this warehouse
                $stokTersedia = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $barangId)
                    ->where('qty_sisa', '>', 0)
                    ->sum('qty_sisa');

                // If we have enough stock, or if it is NOT a semi-finished good (bahan baku biasa), or if it doesn't have a recipe:
                if ($stokTersedia >= $qtyNeeded || !$barang->is_bahan_setengah_jadi || !$barang->resep_id) {
                    if (isset($totalKebutuhanBahan[$barangId])) {
                        $totalKebutuhanBahan[$barangId]['jumlah'] += $qtyNeeded;
                    } else {
                        $totalKebutuhanBahan[$barangId] = [
                            'nama'   => $barang->nama,
                            'satuan' => $barang->satuan,
                            'jumlah' => $qtyNeeded
                        ];
                    }
                    return;
                }

                // If it is a semi-finished good and stock is not enough:
                // Consume whatever is available first
                $qtyRemaining = $qtyNeeded;
                if ($stokTersedia > 0) {
                    if (isset($totalKebutuhanBahan[$barangId])) {
                        $totalKebutuhanBahan[$barangId]['jumlah'] += $stokTersedia;
                    } else {
                        $totalKebutuhanBahan[$barangId] = [
                            'nama'   => $barang->nama,
                            'satuan' => $barang->satuan,
                            'jumlah' => $stokTersedia
                        ];
                    }
                    $qtyRemaining -= $stokTersedia;
                }

                // Explode the remaining qty using its recipe
                $resep = DB::table('resep_btkl_bop')->where('id', $barang->resep_id)->first();
                if ($resep) {
                    $resepBahan = DB::table('resep_bahanbaku')->where('resep_id', $resep->id)->get();
                    $outputQty = floatval($resep->output_qty) > 0 ? floatval($resep->output_qty) : 1.0;
                    $multiplier = $qtyRemaining / $outputQty;

                    foreach ($resepBahan as $subBahan) {
                        $subQtyNeeded = floatval($subBahan->qty_bahan) * $multiplier;
                        $resolveBahan($subBahan->bahan_id, $subQtyNeeded);
                    }
                } else {
                    // No recipe, fallback to requiring the remaining amount of this item
                    if (isset($totalKebutuhanBahan[$barangId])) {
                        $totalKebutuhanBahan[$barangId]['jumlah'] += $qtyRemaining;
                    } else {
                        $totalKebutuhanBahan[$barangId] = [
                            'nama'   => $barang->nama,
                            'satuan' => $barang->satuan,
                            'jumlah' => $qtyRemaining
                        ];
                    }
                }
            };

            foreach ($penjualan->details as $detail) {
                $qtyTerjual = floatval($detail->qty);
                $produkId = $detail->produk_id;

                $barangJadi = DB::table('master_barang')->where('id', $produkId)->first();
                $resepUtama = ($barangJadi && $barangJadi->resep_id) ? DB::table('resep_btkl_bop')->where('id', $barangJadi->resep_id)->first() : null;
                
                if ($resepUtama) {
                    $resepBahan = DB::table('resep_bahanbaku')->where('resep_id', $resepUtama->id)->get();
                    $outputQty = floatval($resepUtama->output_qty) > 0 ? floatval($resepUtama->output_qty) : 1;
                    $multiplier = $qtyTerjual / $outputQty;

                    foreach ($resepBahan as $bahan) {
                        $kebutuhanPerPcs = floatval($bahan->qty_bahan);
                        $butuh = $kebutuhanPerPcs * $multiplier;
                        $resolveBahan($bahan->bahan_id, $butuh);
                    }
                }
            }

            // -- B. Validasi Stok
            $pesanErrorStok = [];
            foreach ($totalKebutuhanBahan as $bahanId => $dataBahan) {
                $stokTersedia = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $bahanId)
                    ->where('qty_sisa', '>', 0)
                    ->sum('qty_sisa');

                if ($stokTersedia < $dataBahan['jumlah']) {
                    $pesanErrorStok[] = "• {$dataBahan['nama']} (Butuh: {$dataBahan['jumlah']}, Sisa: {$stokTersedia})";
                }
            }

            if (!empty($pesanErrorStok)) {
                DB::rollBack();
                $errorList = implode(", ", $pesanErrorStok);
                return back()->with('error', "Gagal Approve! Stok bahan baku tidak mencukupi: " . $errorList);
            }

            // -- C. Potong Stok & Hitung FIFO
            $pengeluaranId = DB::table('pengeluaran_bahan_baku')->insertGetId([
                'kode_pengeluaran' => 'OUT-' . $kodePos,
                'tanggal'          => $tanggalTrans,
                'gudang_id'        => $gudangId,
                'status'           => 'approved', 
                'keterangan'       => 'AUTO_POS:' . $kodePos, 
                'created_by'       => auth()->id() ?? 1,
                'approved_by'      => auth()->id() ?? 1,
                'approved_at'      => now(),
                'created_at'       => now(),
                'updated_at'       => now()
            ]);

            $mapHppBahanAvg = []; 

            foreach ($totalKebutuhanBahan as $bahanId => $dataBahan) {
                $totalDipotong = $dataBahan['jumlah'];
                $totalHppBahanGrup = 0;

                $pengeluaranDetailId = DB::table('pengeluaran_bahan_baku_detail')->insertGetId([
                    'pengeluaran_id' => $pengeluaranId,
                    'barang_id'      => $bahanId,
                    'qty'            => $totalDipotong,
                    'satuan'         => $dataBahan['satuan'],
                    'harga_satuan'   => 0, 
                    'total_harga'    => 0,
                    'hpp_total'      => 0,
                    'created_at'     => now(),
                    'updated_at'     => now()
                ]);

                // Global Stok Pengurang
                $stokGudang = StokGudang::firstOrCreate(
                    ['gudang_id' => $gudangId, 'barang_id' => $bahanId],
                    ['jumlah' => 0]
                );
                $stokGudang->decrement('jumlah', $totalDipotong);

                // Potong Batch (FIFO)
                $stokBatches = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $bahanId)
                    ->where('qty_sisa', '>', 0)
                    ->orderBy('id', 'asc')
                    ->get();

                $sisaKebutuhan = $totalDipotong;
                foreach ($stokBatches as $batch) {
                    if ($sisaKebutuhan <= 0) break;

                    $diambil = min($sisaKebutuhan, $batch->qty_sisa);
                    $nilaiHppDiambil = $diambil * $batch->harga_per_qty;
                    $totalHppBahanGrup += $nilaiHppDiambil; 

                    DB::table('stok_gudang_batch')->where('id', $batch->id)->update([
                        'qty_sisa'   => DB::raw("qty_sisa - {$diambil}"),
                        'qty_keluar' => DB::raw("qty_keluar + {$diambil}")
                    ]);
                    
                    DB::table('pengeluaran_bahan_baku_fifo')->insert([
                        'pengeluaran_id' => $pengeluaranId,
                        'detail_id'      => $pengeluaranDetailId,
                        'batch_id'       => $batch->id,
                        'batch_number'   => $batch->no_batch ?? $batch->batch_number ?? '-',
                        'qty_keluar'     => $diambil,
                        'harga_per_qty'  => $batch->harga_per_qty,
                        'total_harga'    => $nilaiHppDiambil,
                        'created_at'     => now(),
                        'updated_at'     => now()
                    ]);

                    $sisaKebutuhan -= $diambil;
                }

                DB::table('stok_gudang_batch')->where('qty_sisa', '<=', 0)->update(['is_habis' => 1]);

                $avgHppSatuan = $totalDipotong > 0 ? ($totalHppBahanGrup / $totalDipotong) : 0;
                DB::table('pengeluaran_bahan_baku_detail')->where('id', $pengeluaranDetailId)->update([
                    'harga_satuan' => $avgHppSatuan,
                    'total_harga'  => $totalHppBahanGrup,
                    'hpp_total'    => $totalHppBahanGrup
                ]);

                $mapHppBahanAvg[$bahanId] = $avgHppSatuan;
            }

            // -- D. Update HPP ke Detail Transaksi
            $getHppForBarang = function($barangId) use (&$getHppForBarang, &$mapHppBahanAvg) {
                if (isset($mapHppBahanAvg[$barangId])) {
                    return $mapHppBahanAvg[$barangId];
                }

                $barang = DB::table('master_barang')->where('id', $barangId)->first();
                if (!$barang) return 0;

                if ($barang->resep_id) {
                    $resep = DB::table('resep_btkl_bop')->where('id', $barang->resep_id)->first();
                    if ($resep) {
                        $resepBahan = DB::table('resep_bahanbaku')->where('resep_id', $resep->id)->get();
                        $outputQty = floatval($resep->output_qty) > 0 ? floatval($resep->output_qty) : 1.0;
                        $totalHpp = 0;
                        foreach ($resepBahan as $subBahan) {
                            $subHpp = $getHppForBarang($subBahan->bahan_id);
                            $totalHpp += (floatval($subBahan->qty_bahan) * $subHpp);
                        }
                        $mapHppBahanAvg[$barangId] = $totalHpp / $outputQty;
                        return $mapHppBahanAvg[$barangId];
                    }
                }

                return (float) ($barang->hpp_referensi ?: 0);
            };

            foreach ($penjualan->details as $detail) {
                $qtyTerjual = floatval($detail->qty);
                $produkId = $detail->produk_id;
    
                $hppSatuanProduk = 0;
                $totalHppBahan   = 0;

                $barangJadi = DB::table('master_barang')->where('id', $produkId)->first();
                $resepUtama = ($barangJadi && $barangJadi->resep_id) ? DB::table('resep_btkl_bop')->where('id', $barangJadi->resep_id)->first() : null;

                if ($resepUtama) {
                    $resepBahan = DB::table('resep_bahanbaku')->where('resep_id', $resepUtama->id)->get();
                    $outputQty = floatval($resepUtama->output_qty) > 0 ? floatval($resepUtama->output_qty) : 1;
                    foreach ($resepBahan as $bahan) {
                        $kebutuhanPerPcs = floatval($bahan->qty_bahan);
                        $hppBahanIni = $getHppForBarang($bahan->bahan_id);
                        $totalHppBahan += (($kebutuhanPerPcs * $hppBahanIni) / $outputQty);
                    }
                    $hppSatuanProduk = $totalHppBahan; // Hanya biaya bahan baku (BBB), tanpa markup BTKL & BOP
                } else {
                    $hppSatuanProduk = $barangJadi ? $barangJadi->hpp_referensi : 0;
                }

                $detail->update([
                    'hpp_satuan' => $hppSatuanProduk
                ]);
            }
    
            $penjualan->update(['status' => 'SUKSES']);

            // Auto post POS sale journal
            \App\Http\Controllers\JurnalController::autoPostPenjualanPos($penjualan->id);
            
            DB::commit();
            return redirect()->route('penjualan_pos.index')->with('success', 'Transaksi berhasil di-Approve! Stok terpotong dan jurnal telah terposting secara otomatis.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Approve POS: ' . $e->getMessage());
            return back()->with('error', 'Gagal approve transaksi: ' . $e->getMessage());
        }
    }

    /**
     * 5. HAPUS ATAU VOID
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        DB::beginTransaction();
    
        try {
            $penjualan = PenjualanPos::with('details')->findOrFail($id);
            
            if ($penjualan->status == 'SUKSES') {
                // A. JIKA SUDAH APPROVE
                $gudangId = $penjualan->gudang_id;
                foreach ($penjualan->details as $detail) {
                    $barangJadi = DB::table('master_barang')->where('id', $detail->produk_id)->first();
                    $resepUtama = ($barangJadi && $barangJadi->resep_id) ? DB::table('resep_btkl_bop')->where('id', $barangJadi->resep_id)->first() : null;

                    if ($resepUtama) {
                        $resepBahan = DB::table('resep_bahanbaku')->where('resep_id', $resepUtama->id)->get();
                        foreach ($resepBahan as $bahan) {
                            $kebutuhanPerPcs = floatval($bahan->qty_bahan);
                            $qtyKembali = $kebutuhanPerPcs * floatval($detail->qty);

                            $stokGudang = StokGudang::where('gudang_id', $gudangId)->where('barang_id', $bahan->bahan_id)->first();
                            if ($stokGudang) {
                                $stokGudang->increment('jumlah', $qtyKembali);
                            }

                            // Revert ke batch yang terpotong terakhir
                            $batchTerakhir = DB::table('stok_gudang_batch')->where('gudang_id', $gudangId)->where('barang_id', $bahan->bahan_id)->orderBy('id', 'desc')->first();
                            if ($batchTerakhir) {
                                DB::table('stok_gudang_batch')->where('id', $batchTerakhir->id)->update([
                                    'qty_sisa'   => DB::raw("qty_sisa + {$qtyKembali}"),
                                    'qty_keluar' => DB::raw("qty_keluar - {$qtyKembali}"),
                                    'is_habis'   => 0
                                ]);
                            }
                        }
                    }
                }
        
                // Hapus pengeluaran bahan baku terkait AUTO_POS
                $pengeluaranList = DB::table('pengeluaran_bahan_baku')->where('keterangan', 'AUTO_POS:' . $penjualan->kode_transaksi)->get();
                foreach ($pengeluaranList as $peng) {
                    DB::table('pengeluaran_bahan_baku_fifo')->where('pengeluaran_id', $peng->id)->delete();
                    DB::table('pengeluaran_bahan_baku_detail')->where('pengeluaran_id', $peng->id)->delete();
                    DB::table('pengeluaran_bahan_baku')->where('id', $peng->id)->delete();
                }

                // Hapus jurnal akuntansi POS
                $jurnalList = DB::table('jurnal_penjualan_pos')->where('source_type', 'penjualan_pos')->where('source_id', $penjualan->id)->get();
                foreach ($jurnalList as $jp) {
                    DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_penjualan_pos')->delete();
                    DB::table('jurnal_penjualan_pos')->where('id', $jp->id)->delete();
                }

                if ($isSuperAdmin) {
                    // Super Admin: Hapus total dari database agar dapat di-import ulang / dibuat ulang bersih
                    $penjualan->details()->delete();
                    $penjualan->delete();
                    $msg = 'Transaksi penjualan POS ' . $penjualan->kode_transaksi . ' berhasil dihapus permanen, stok telah dikembalikan, dan jurnal akuntansi telah dibersihkan!';
                } else {
                    // Non Super Admin: Set menjadi VOID
                    $penjualan->update(['status' => 'VOID']);
                    $msg = 'Transaksi dibatalkan. Status berubah menjadi VOID dan stok dikembalikan!';
                }
                
            } else {
                // B. JIKA MASIH Draft -> Hapus Permanen bersih
                $penjualan->details()->delete(); 
                $penjualan->delete();           
                $msg = 'Transaksi berstatus Draft berhasil dihapus secara permanen!';
            }
    
            DB::commit();
            return redirect()->route('penjualan_pos.index')->with('success', $msg);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Hapus/Void POS: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses penghapusan: ' . $e->getMessage());
        }
    }

    public function getHargaAktif(Request $request, $produk_id)
    {
        $tanggal = $request->tanggal ? date('Y-m-d', strtotime($request->tanggal)) : now()->toDateString();
        $barang = MasterBarang::findOrFail($produk_id);
        
        $hargaAktif = HargaPeriode::where('barang_id', $produk_id)
            ->whereDate('tgl_mulai', '<=', $tanggal) 
            ->where(function($query) use ($tanggal) {
                $query->whereNull('tgl_selesai')->orWhereDate('tgl_selesai', '>=', $tanggal);
            })
            ->orderBy('tgl_mulai', 'desc')
            ->first();

        $harga = 0;
        if ($hargaAktif) {
            $harga = (float) $hargaAktif->harga_pos;
        } else {
            $harga = (float) $barang->harga_jual_pos;
        }

        return response()->json([
            'harga_pos' => $harga,
            'has_resep' => !is_null($barang->resep_id),
            'nama'      => $barang->nama,
        ]);
    }

    public function cetakNotaPdf($id)
    {
        $penjualan = PenjualanPos::with(['gudang', 'creator', 'details.produk'])->findOrFail($id);
        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
        $pdf->loadView('penjualan_pos.nota-pdf', compact('penjualan'));
        return $pdf->stream('Sales-Order-POS-' . $penjualan->kode_transaksi . '.pdf');
    }

    /**
     * Helper pencocokan produk barang jadi berdasarkan nama item & varian (dan outlet jika ada)
     */
    private function matchBarangJadi(string $itemName, string $variantName = '', ?int $gudangId = null)
    {
        $fullName = trim($itemName . ' ' . $variantName);
        
        $applyTipeFilter = function ($query) use ($gudangId) {
            if ($gudangId == 2) {
                $query->where(function ($q) {
                    $q->where('tipe_penjualan', 'POS Gaharu')->orWhereNull('tipe_penjualan');
                });
            } elseif ($gudangId == 4) {
                $query->where(function ($q) {
                    $q->where('tipe_penjualan', 'POS Kejingga')->orWhereNull('tipe_penjualan');
                });
            }
        };

        $product = null;
        if (!empty($variantName)) {
            $query = MasterBarang::where('nama', $fullName)->where('is_barang_jadi', 1);
            $applyTipeFilter($query);
            $product = $query->first();
        }

        if (!$product) {
            $query = MasterBarang::where('nama', $itemName)->where('is_barang_jadi', 1);
            $applyTipeFilter($query);
            $product = $query->first();
        }

        if (!$product && !empty($fullName)) {
            $query = MasterBarang::where('is_barang_jadi', 1)->whereRaw('LOWER(TRIM(nama)) = ?', [strtolower(trim($fullName))]);
            $applyTipeFilter($query);
            $product = $query->first();
        }

        if (!$product) {
            $query = MasterBarang::where('is_barang_jadi', 1)->whereRaw('LOWER(TRIM(nama)) = ?', [strtolower(trim($itemName))]);
            $applyTipeFilter($query);
            $product = $query->first();
        }

        if (!$product) {
            $query = MasterBarang::where('nama', 'like', '%' . $itemName . '%')->where('is_barang_jadi', 1);
            $applyTipeFilter($query);
            $product = $query->first();
        }

        // Fallback jika belum cocok dengan filter tipe_penjualan: cari produk secara global
        if (!$product && !empty($variantName)) {
            $product = MasterBarang::where('nama', $fullName)->where('is_barang_jadi', 1)->first();
        }

        if (!$product) {
            $product = MasterBarang::where('nama', $itemName)->where('is_barang_jadi', 1)->first();
        }

        if (!$product && !empty($fullName)) {
            $product = MasterBarang::where('is_barang_jadi', 1)->whereRaw('LOWER(TRIM(nama)) = ?', [strtolower(trim($fullName))])->first();
        }

        if (!$product) {
            $product = MasterBarang::where('is_barang_jadi', 1)->whereRaw('LOWER(TRIM(nama)) = ?', [strtolower(trim($itemName))])->first();
        }

        if (!$product) {
            $product = MasterBarang::where('nama', 'like', '%' . $itemName . '%')->where('is_barang_jadi', 1)->first();
        }

        if (!$product) {
            $product = MasterBarang::where('is_barang_jadi', 1)->first();
        }

        return $product;
    }

    public function importMokaExcel(Request $request)
    {
        $request->validate([
            'moka_file'         => 'required|file',
            'tanggal_transaksi' => 'required|date',
            'gudang_id'         => 'required|exists:master_gudang,id',
        ]);

        try {
            $file = $request->file('moka_file');
            $selectedDate = $request->input('tanggal_transaksi');
            $gudangId = (int) $request->input('gudang_id');
            $gudangObj = \App\Models\MasterGudang::find($gudangId);
            $gudangNama = $gudangObj ? $gudangObj->nama : 'Outlet';
            
            $extension = strtolower($file->getClientOriginalExtension());
            $rows = [];

            if ($extension === 'csv' || $file->getMimeType() === 'text/csv' || $file->getMimeType() === 'text/plain') {
                $handle = fopen($file->getRealPath(), 'r');
                if ($handle) {
                    $firstLine = fgets($handle);
                    rewind($handle);
                    $delimiter = ',';
                    if (str_contains($firstLine, ';') && !str_contains($firstLine, ',')) {
                        $delimiter = ';';
                    }

                    $rowIndex = 1;
                    while (($data = fgetcsv($handle, 4000, $delimiter)) !== false) {
                        $row = [];
                        foreach ($data as $colIndex => $cellValue) {
                            $colLetter = chr(65 + $colIndex);
                            $row[$colLetter] = $cellValue;
                        }
                        $rows[$rowIndex] = $row;
                        $rowIndex++;
                    }
                    fclose($handle);
                }
            } else {
                if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                    return back()->with('error', 'Library PhpSpreadsheet tidak terinstall di live server.');
                }

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);
            }

            $headerRowIndex = null;
            $mapping = [];
            foreach ($rows as $rowIndex => $row) {
                $rowClean = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                foreach ($rowClean as $colLetter => $cellValue) {
                    if (str_contains($cellValue, 'receipt number') || str_contains($cellValue, 'no. transaksi')) {
                        $mapping['receipt'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'item name') || str_contains($cellValue, 'nama item') || $cellValue === 'item') {
                        $mapping['item'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'item variant name') || str_contains($cellValue, 'variant')) {
                        $mapping['variant'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'item sold') || str_contains($cellValue, 'quantity') || str_contains($cellValue, 'qty')) {
                        $mapping['qty'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'net sales') || str_contains($cellValue, 'subtotal')) {
                        $mapping['net_sales'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'price') || str_contains($cellValue, 'harga')) {
                        $mapping['price'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'tax') || str_contains($cellValue, 'pajak')) {
                        $mapping['tax'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'payment method') || str_contains($cellValue, 'metode')) {
                        $mapping['payment'] = $colLetter;
                    }
                    if (str_contains($cellValue, 'date') || str_contains($cellValue, 'tanggal')) {
                        $mapping['date'] = $colLetter;
                    }
                }
                if (isset($mapping['item']) && isset($mapping['qty'])) {
                    $headerRowIndex = $rowIndex;
                    break;
                }
            }

            if (!$headerRowIndex) {
                return back()->with('error', 'Format file Moka POS tidak dikenali.');
            }

            $isSummaryFormat = !isset($mapping['receipt']);
            $userId = auth()->id() ?? 1;

            if ($isSummaryFormat) {
                $prefixGudang = ($gudangId == 4 || stripos($gudangNama, 'kejingga') !== false) ? 'KJ' : 'GH';
                $receiptCode = "MOKA-SUM-{$prefixGudang}-" . date('Ymd', strtotime($selectedDate));
                
                $exists = DB::table('penjualan_pos')->where('kode_transaksi', $receiptCode)->exists();
                if ($exists) {
                    return back()->with('error', "Laporan Moka POS untuk outlet {$gudangNama} tanggal " . date('d/m/Y', strtotime($selectedDate)) . " sudah pernah di-import.");
                }

                $itemsToImport = [];
                for ($i = $headerRowIndex + 1; $i <= count($rows); $i++) {
                    $row = $rows[$i];
                    $itemName = trim((string)($row[$mapping['item']] ?? ''));
                    if (empty($itemName) || strtolower($itemName) === 'total') continue;

                    $qty = floatval($row[$mapping['qty']] ?? 0);
                    if ($qty <= 0) continue;

                    $variantName = isset($mapping['variant']) ? trim((string)($row[$mapping['variant']] ?? '')) : '';
                    $netSales = floatval($row[$mapping['net_sales'] ?? ''] ?? 0);
                    if ($netSales <= 0 && isset($mapping['price'])) {
                        $price = floatval($row[$mapping['price']] ?? 0);
                        $netSales = $qty * $price;
                    }

                    $product = $this->matchBarangJadi($itemName, $variantName, $gudangId);
                    if (!$product) {
                        throw new \Exception("Tidak ada produk jadi terdaftar di master_barang untuk item: {$itemName}");
                    }

                    $prodId = $product->id;

                    if (isset($itemsToImport[$prodId])) {
                        $itemsToImport[$prodId]['qty'] += $qty;
                        $itemsToImport[$prodId]['net_sales'] += $netSales;
                    } else {
                        $itemsToImport[$prodId] = [
                            'product'      => $product,
                            'item_name'    => $itemName,
                            'variant_name' => $variantName,
                            'qty'          => $qty,
                            'net_sales'    => $netSales
                        ];
                    }
                }

                if (empty($itemsToImport)) {
                    return back()->with('error', 'Tidak ada data transaksi yang valid.');
                }

                $totalSales = array_sum(array_column($itemsToImport, 'net_sales'));

                DB::beginTransaction();
                try {
                    $penjualan = PenjualanPos::create([
                        'kode_transaksi' => $receiptCode,
                        'status'         => 'Draft',
                        'tanggal'        => $selectedDate . ' ' . date('H:i:s'),
                        'gudang_id'      => $gudangId,
                        'total'          => $totalSales,
                        'created_by'     => $userId
                    ]);

                    foreach ($itemsToImport as $prodId => $it) {
                        $product = $it['product'];
                        $avgPrice = $it['qty'] > 0 ? round($it['net_sales'] / $it['qty'], 2) : (float) $product->harga_jual_pos;
                        PenjualanPosDetail::create([
                            'penjualan_id' => $penjualan->id,
                            'produk_id'    => $product->id,
                            'qty'          => $it['qty'],
                            'harga'        => $avgPrice,
                            'hpp_satuan'   => 0,
                            'subtotal'     => $it['net_sales']
                        ]);
                    }

                    DB::commit();
                    $this->approve($penjualan->id);

                    return redirect()->route('penjualan_pos.index')->with('success', "Import Ringkasan Moka POS untuk Outlet [{$gudangNama}] berhasil! Transaksi (Kode: {$receiptCode}) tanggal " . date('d/m/Y', strtotime($selectedDate)) . " berhasil dibuat, persediaan FIFO di [{$gudangNama}] terpotong, dan jurnal akuntansi telah diposting.");
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Gagal import ringkasan POS Moka: ' . $e->getMessage());
                    return back()->with('error', 'Gagal memproses import data: ' . $e->getMessage());
                }

            } else {
                $transactions = [];
                for ($i = $headerRowIndex + 1; $i <= count($rows); $i++) {
                    $row = $rows[$i];
                    $receipt = trim((string)($row[$mapping['receipt']] ?? ''));
                    if (empty($receipt)) continue;

                    $isRefund = false;
                    foreach ($row as $val) {
                        if (in_array(strtolower(trim((string)$val)), ['refunded', 'refund'])) {
                            $isRefund = true; break;
                        }
                    }
                    if ($isRefund) continue;

                    $itemName = trim((string)($row[$mapping['item']] ?? ''));
                    if (empty($itemName) || strtolower($itemName) === 'total') continue;

                    $variantName = isset($mapping['variant']) ? trim((string)($row[$mapping['variant']] ?? '')) : '';
                    $qty = floatval($row[$mapping['qty']] ?? 0);
                    if ($qty <= 0) continue;

                    $price = floatval($row[$mapping['price'] ?? ''] ?? 0);
                    $netSales = floatval($row[$mapping['net_sales'] ?? ''] ?? 0);
                    if ($netSales <= 0 && $price > 0) {
                        $netSales = $qty * $price;
                    }
                    $tax = floatval($row[$mapping['tax'] ?? ''] ?? 0);
                    
                    $dateVal = trim((string)($row[$mapping['date'] ?? ''] ?? ''));
                    $payment = trim((string)($row[$mapping['payment'] ?? ''] ?? 'Cash'));

                    if (!isset($transactions[$receipt])) {
                        $transactions[$receipt] = [
                            'receipt' => $receipt,
                            'date'    => !empty($dateVal) ? date('Y-m-d H:i:s', strtotime($dateVal)) : date('Y-m-d H:i:s'),
                            'payment' => $payment,
                            'tax'     => 0,
                            'items'   => []
                        ];
                    }
                    $transactions[$receipt]['tax'] += $tax;

                    $product = $this->matchBarangJadi($itemName, $variantName, $gudangId);
                    if (!$product) {
                        throw new \Exception("Tidak ada produk jadi terdaftar di master_barang untuk item: {$itemName}");
                    }

                    $prodId = $product->id;

                    if (isset($transactions[$receipt]['items'][$prodId])) {
                        $transactions[$receipt]['items'][$prodId]['qty'] += $qty;
                        $transactions[$receipt]['items'][$prodId]['subtotal'] += $netSales;
                        if ($transactions[$receipt]['items'][$prodId]['qty'] > 0) {
                            $transactions[$receipt]['items'][$prodId]['price'] = round($transactions[$receipt]['items'][$prodId]['subtotal'] / $transactions[$receipt]['items'][$prodId]['qty'], 2);
                        }
                    } else {
                        $transactions[$receipt]['items'][$prodId] = [
                            'product'   => $product,
                            'item_name' => $itemName,
                            'qty'       => $qty,
                            'price'     => $price > 0 ? $price : ($qty > 0 ? round($netSales / $qty, 2) : 0),
                            'subtotal'  => $netSales
                        ];
                    }
                }

                $successCount = 0;
                $skippedCount = 0;

                foreach ($transactions as $receipt => $tx) {
                    $exists = DB::table('penjualan_pos')->where('kode_transaksi', $receipt)->exists();
                    if ($exists) {
                        $skippedCount++;
                        continue;
                    }

                    DB::beginTransaction();
                    try {
                        $totalItems = array_sum(array_column($tx['items'], 'subtotal'));
                        $totalTx = $totalItems + $tx['tax'];

                        $penjualan = PenjualanPos::create([
                            'kode_transaksi' => $receipt,
                            'status'         => 'Draft',
                            'tanggal'        => $tx['date'],
                            'gudang_id'      => $gudangId,
                            'total'          => $totalTx,
                            'created_by'     => $userId
                        ]);

                        foreach ($tx['items'] as $it) {
                            PenjualanPosDetail::create([
                                'penjualan_id' => $penjualan->id,
                                'produk_id'    => $it['product']->id,
                                'qty'          => $it['qty'],
                                'harga'        => $it['price'],
                                'hpp_satuan'   => 0,
                                'subtotal'     => $it['subtotal']
                            ]);
                        }

                        DB::commit();
                        $this->approve($penjualan->id);
                        $successCount++;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Gagal import transaksi POS Moka: ' . $e->getMessage());
                        throw $e;
                    }
                }

                return redirect()->route('penjualan_pos.index')->with('success', "Import berhasil untuk Outlet [{$gudangNama}]! {$successCount} transaksi berhasil dimasukkan, stok bahan baku di [{$gudangNama}] terpotong, dan dijurnal otomatis. {$skippedCount} transaksi dilewati (duplikat struk).");
            }

        } catch (\Exception $e) {
            Log::error('Error Import Moka Excel: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses file import: ' . $e->getMessage());
        }
    }
}