<?php

namespace App\Http\Controllers;

use App\Models\MasterGudang;
use App\Models\GudangDivisi;
use App\Models\PengeluaranBahanBaku;
use App\Models\PengeluaranBahanBakuDetail;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST DATA
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = StockOpname::with(['gudang', 'divisi', 'user']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_opname', 'like', '%' . $search . '%')
                  ->orWhere('keterangan', 'like', '%' . $search . '%');
            });
        }

        $stockOpname = $query->latest()->paginate(20)->withQueryString();

        $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();

        return view('stock-opname.index', compact('stockOpname', 'gudangs'));
    }

    /*
    |--------------------------------------------------------------------------
    | FORM CREATE
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;

        if (!$gudangId) {
            return redirect()
                ->route('stock-opname.index')
                ->with('error', 'Silakan pilih gudang terlebih dahulu.');
        }

        $gudang = MasterGudang::with('divisi')->findOrFail($gudangId);
        $divisi = $divisiId ? GudangDivisi::find($divisiId) : null;

        // Jika gudang operasional memiliki divisi tapi divisi belum dipilih, arahkan untuk memilih divisi
        if (strtolower($gudang->kategori) === 'operasional' && $gudang->divisi->count() > 0 && !$divisiId) {
            return redirect()
                ->route('stock-opname.index')
                ->with('error', 'Gudang ' . $gudang->nama . ' memiliki beberapa divisi. Silakan pilih divisi yang akan di-opname.');
        }

        return view('stock-opname.create', compact('gudang', 'divisi', 'divisiId'));
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD BARANG AJAX
    |--------------------------------------------------------------------------
    */

    public function loadBarang(Request $request)
    {
        $request->validate(['gudang_id' => 'required']);
        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;

        $barang = DB::table('master_barang')
            ->leftJoin('stok_gudang', function ($join) use ($gudangId, $divisiId) {
                $join->on('master_barang.id', '=', 'stok_gudang.barang_id')
                     ->where('stok_gudang.gudang_id', '=', $gudangId);
                if ($divisiId) {
                    $join->where('stok_gudang.divisi_id', '=', $divisiId);
                } else {
                    $join->whereNull('stok_gudang.divisi_id');
                }
            })
            ->where('master_barang.is_active', true)
            ->where(function ($q) {
                $q->where('master_barang.is_bahan_baku', 1)
                  ->orWhere('master_barang.is_bahan_setengah_jadi', 1)
                  ->orWhere('master_barang.is_barang_jadi', 1)
                  ->orWhere('master_barang.is_operational', 1);
            })
            ->select(
                'master_barang.id',
                'master_barang.kode_barang',
                'master_barang.nama',
                'master_barang.satuan',
                'master_barang.is_bahan_setengah_jadi',
                'master_barang.is_bahan_baku',
                DB::raw('COALESCE(stok_gudang.jumlah, 0) as stok')
            )
            ->orderBy('master_barang.nama', 'asc')
            ->get();

        foreach ($barang as $item) {
            $item->harga_fifo = $this->getHargaFIFO(
                $gudangId,
                $item->id,
                $divisiId
            );
        }

        return response()->json($barang);
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG FIFO REALTIME (AJAX)
    |--------------------------------------------------------------------------
    */

    public function hitungFIFORealtime(Request $request)
    {
        $nilai = $this->hitungNilaiFIFO(
            $request->gudang_id,
            $request->barang_id,
            abs($request->selisih),
            $request->divisi_id
        );

        return response()->json(['nilai' => $nilai]);
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DRAFT STOCK OPNAME
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'gudang_id'   => 'required',
            'divisi_id'   => 'nullable|exists:gudang_divisi,id',
            'tanggal'     => 'nullable|date',
            'barang_id'   => 'required|array',
            'stok_sistem' => 'required|array',
            'stok_fisik'  => 'required|array',
        ]);

        $gudang = MasterGudang::with('divisi')->find($request->gudang_id);
        if ($gudang && strtolower($gudang->kategori) === 'operasional' && $gudang->divisi->count() > 0 && empty($request->divisi_id)) {
            return back()->withErrors(['divisi_id' => 'Silakan pilih divisi untuk gudang operasional ' . $gudang->nama . '.'])->withInput();
        }

        $tanggal = $request->tanggal ? date('Y-m-d', strtotime($request->tanggal)) : date('Y-m-d');

        if (\App\Models\Journal::isPeriodClosed($tanggal)) {
            return back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku. Tidak dapat membuat Stock Opname pada periode yang sudah ditutup.'])->withInput();
        }

        DB::beginTransaction();

        try {
            $opname = StockOpname::create([
                'kode_opname' => 'SO-' . now()->format('YmdHis'),
                'tanggal'     => $tanggal,
                'gudang_id'   => $request->gudang_id,
                'divisi_id'   => $request->divisi_id,
                'status'      => 'draft',
                'keterangan'  => $request->keterangan,
                'created_by'  => Auth::id(),
            ]);

            foreach ($request->barang_id as $index => $barangId) {
                $stokSistem   = (float) $request->stok_sistem[$index];
                $stokFisik    = (float) $request->stok_fisik[$index];
                $selisih      = $stokFisik - $stokSistem;
                $nilaiSelisih = $this->hitungNilaiFIFO(
                    $request->gudang_id,
                    $barangId,
                    abs($selisih),
                    $request->divisi_id
                );

                StockOpnameDetail::create([
                    'stock_opname_id' => $opname->id,
                    'barang_id'       => $barangId,
                    'stok_sistem'     => $stokSistem,
                    'stok_fisik'      => $stokFisik,
                    'selisih'         => $selisih,
                    'nilai_selisih'   => $nilaiSelisih,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('stock-opname.show', $opname->id)
                ->with('success', 'Draft Stock Opname berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL
    |--------------------------------------------------------------------------
    */

    public function show(string $id)
    {
        $opname = StockOpname::with([
            'gudang',
            'divisi',
            'user',
            'details.barang',
        ])->findOrFail($id);

        $pengeluaranOtomatis = $opname->pengeluaranOtomatis();

        return view('stock-opname.show', compact('opname', 'pengeluaranOtomatis'));
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL JSON (untuk modal index)
    |--------------------------------------------------------------------------
    */

    public function detailJson(string $id)
    {
        $opname = StockOpname::with([
            'gudang',
            'divisi',
            'user',
            'details.barang',
        ])->findOrFail($id);

        return response()->json([
            'id'          => $opname->id,
            'kode_opname' => $opname->kode_opname,
            'tanggal'     => \Carbon\Carbon::parse($opname->tanggal)->format('d M Y'),
            'gudang'      => $opname->gudang->nama ?? '-',
            'divisi'      => $opname->divisi->nama ?? '-',
            'user'        => $opname->user->nama_karyawan ?? $opname->user->name ?? '-',
            'status'      => $opname->status,
            'keterangan'  => $opname->keterangan ?? '-',
            'details'     => $opname->details->map(function ($d) {
                return [
                    'nama_barang'   => $d->barang->nama ?? '-',
                    'satuan'        => $d->barang->satuan ?? 'pcs',
                    'stok_sistem'   => (float) $d->stok_sistem,
                    'stok_fisik'    => (float) $d->stok_fisik,
                    'selisih'       => (float) $d->selisih,
                    'nilai_selisih' => (float) $d->nilai_selisih,
                ];
            }),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public function approve(string $id)
    {
        $opname = StockOpname::with(['details.barang', 'gudang', 'divisi'])->findOrFail($id);

        if ($opname->status === 'approved') {
            return back()->with('error', 'Stock Opname sudah disetujui sebelumnya.');
        }

        DB::beginTransaction();

        try {
            $itemSelisihNegatif = [];
            $surplusDebits = [];
            $totalSurplusKredit = 0;
            $idPendapatanLain = DB::table('chart_of_accounts')->where('kode', '4201')->value('id') ?? 32;

            foreach ($opname->details as $detail) {

                $hargaUnit = $this->getHargaFIFO(
                    $opname->gudang_id,
                    $detail->barang_id,
                    $opname->divisi_id
                );

                if ($detail->selisih < 0) {
                    $itemSelisihNegatif[] = [
                        'barang_id' => $detail->barang_id,
                        'qty'       => abs($detail->selisih),
                        'satuan'    => $detail->barang->satuan ?? 'pcs',
                    ];
                } elseif ($detail->selisih > 0) {
                    $defaultSupplierId  = DB::table('suppliers')->value('id') ?? 1;
                    $defaultPembelianId = DB::table('pembelian')->value('id') ?? 1;
                    $defaultPemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                    // 1. Buat batch FIFO baru untuk surplus
                    \App\Models\StokGudangBatch::create([
                        'gudang_id'           => $opname->gudang_id,
                        'divisi_id'           => $opname->divisi_id,
                        'supplier_id'         => $defaultSupplierId,
                        'barang_id'           => $detail->barang_id,
                        'pembelian_id'        => $defaultPembelianId,
                        'pembelian_detail_id' => $defaultPemDetailId,
                        'batch_number'        => 'SO-SURPLUS-' . $opname->kode_opname,
                        'qty_masuk'           => $detail->selisih,
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $detail->selisih,
                        'harga_per_qty'       => $hargaUnit,
                        'is_habis'            => false,
                    ]);

                    // 2. Tambah stok gudang menggunakan StockService
                    app(\App\Services\StockService::class)->stockIn([
                        'barang_id'        => $detail->barang_id,
                        'gudang_tujuan_id' => $opname->gudang_id,
                        'divisi_tujuan_id' => $opname->divisi_id,
                        'qty'              => $detail->selisih,
                        'total_harga'      => $detail->selisih * $hargaUnit,
                        'source_type'      => 'stock_opname',
                        'source_id'        => $opname->id,
                        'user_id'          => Auth::id() ?? 1,
                    ]);

                    // 3. Kirim otomatis ke Jurnal Penyesuaian (Surplus)
                    $totalHargaSO = round($detail->selisih * $hargaUnit, 2);
                    if ($totalHargaSO > 0) {
                        $isOperational = $detail->barang && ($detail->barang->is_operational || (!$detail->barang->is_bahan_baku && !$detail->barang->is_bahan_setengah_jadi));
                        $coaCode = $isOperational ? '1501' : '1301';
                        $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);
                        
                        if (!isset($surplusDebits[$idPersediaan])) {
                            $surplusDebits[$idPersediaan] = 0;
                        }
                        $surplusDebits[$idPersediaan] += $totalHargaSO;
                        $totalSurplusKredit += $totalHargaSO;
                    }
                }
            }

            if ($totalSurplusKredit > 0) {
                $jp = \App\Models\JurnalPenyesuaian::create([
                    'tanggal'     => $opname->tanggal,
                    'deskripsi'   => "[AJP] Penyesuaian Lebih (Surplus) Stock Opname: " . $opname->kode_opname,
                    'no_ref'      => 'AJP-SO-SURPLUS-' . $opname->kode_opname . '-' . rand(100, 999),
                    'source_type' => 'stock_opname',
                    'source_id'   => $opname->id,
                    'created_by'  => Auth::id() ?? 1,
                    'status'      => 'approved',
                ]);

                foreach ($surplusDebits as $accId => $debitAmount) {
                    $jp->details()->create([
                        'account_id'   => $accId,
                        'debit'        => round($debitAmount, 2),
                        'kredit'       => 0,
                        'journal_type' => \App\Models\JurnalPenyesuaian::class,
                    ]);
                }

                $jp->details()->create([
                    'account_id'   => $idPendapatanLain,
                    'debit'        => 0,
                    'kredit'       => round($totalSurplusKredit, 2),
                    'journal_type' => \App\Models\JurnalPenyesuaian::class,
                ]);
            }

            // ── Buat Pengeluaran Bahan Baku otomatis jika ada selisih negatif ──
            if (!empty($itemSelisihNegatif)) {
                $kode = 'PBK-SO-' . $opname->kode_opname;

                $pengeluaran = PengeluaranBahanBaku::create([
                    'kode_pengeluaran' => $kode,
                    'tanggal'          => $opname->tanggal,
                    'gudang_id'        => $opname->gudang_id,
                    'divisi_id'        => $opname->divisi_id,
                    'status'           => 'draft',
                    'keterangan'       => 'Auto dari Stock Opname: ' . $opname->kode_opname,
                    'created_by'       => Auth::id(),
                    'approved_by'      => null,
                    'approved_at'      => null,
                ]);

                foreach ($itemSelisihNegatif as $item) {
                    PengeluaranBahanBakuDetail::create([
                        'pengeluaran_id' => $pengeluaran->id,
                        'barang_id'      => $item['barang_id'],
                        'qty'            => $item['qty'],
                        'satuan'         => $item['satuan'],
                        'harga_satuan'   => 0,
                        'total_harga'    => 0,
                        'hpp_total'      => 0,
                    ]);
                }
            }

            // ── Update status opname ──
            $opname->update(['status' => 'approved']);

            DB::commit();

            $pesanTambahan = !empty($itemSelisihNegatif)
                ? ' Pengeluaran bahan baku (draft) telah dibuat otomatis — silakan approve di menu Raw Material Output.'
                : '';

            return back()->with(
                'success',
                'Stock opname berhasil diapprove.' . $pesanTambahan
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal approve: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        $opname = StockOpname::findOrFail($id);

        if ($opname->status === 'approved') {
            return back()->with(
                'error',
                'Stock Opname yang sudah approved tidak dapat dihapus.'
            );
        }

        $opname->details()->delete();
        $opname->delete();

        return redirect()
            ->route('stock-opname.index')
            ->with('success', 'Stock Opname berhasil dihapus.');
    }

    public function edit(string $id) {}

    public function update(Request $request, string $id)
    {
        $opname = StockOpname::findOrFail($id);

        if ($opname->status !== 'draft') {
            return back()->with('error', 'Stock Opname yang sudah diapprove tidak dapat diubah.');
        }

        $request->validate([
            'tanggal' => 'required|date',
        ]);

        $tanggal = date('Y-m-d', strtotime($request->tanggal));

        if (\App\Models\Journal::isPeriodClosed($tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.');
        }

        $opname->update([
            'tanggal'    => $tanggal,
            'keterangan' => $request->keterangan ?? $opname->keterangan,
        ]);

        return back()->with('success', 'Tanggal Stock Opname berhasil diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET HARGA FIFO (untuk preview di form)
    |--------------------------------------------------------------------------
    */

    private function getHargaFIFO($gudangId, $barangId, $divisiId = null): float
    {
        $q = DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->where('qty_sisa', '>', 0);

        if ($divisiId) {
            $q->where('divisi_id', $divisiId);
        }

        $harga = $q->orderBy('id', 'asc')->value('harga_per_qty');

        // Fallback 1: rata-rata semua batch historis di gudang/divisi ini
        if (!$harga) {
            $fbQ = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId);
            if ($divisiId) {
                $fbQ->where('divisi_id', $divisiId);
            }
            $harga = $fbQ->avg('harga_per_qty');
        }

        // Fallback 2: batch aktif di gudang manapun
        if (!$harga) {
            $harga = DB::table('stok_gudang_batch')
                ->where('barang_id', $barangId)
                ->where('qty_sisa', '>', 0)
                ->orderBy('id', 'desc')
                ->value('harga_per_qty');
        }

        // Fallback akhir: hpp_referensi di master barang
        if (!$harga) {
            $harga = DB::table('master_barang')
                ->where('id', $barangId)
                ->value('hpp_referensi') ?? 0;
        }

        return (float) $harga;
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG NILAI FIFO
    |--------------------------------------------------------------------------
    */

    private function hitungNilaiFIFO($gudangId, $barangId, $qty, $divisiId = null): float
    {
        if ($qty <= 0) return 0;

        $sisa  = $qty;
        $nilai = 0;

        // ── Tahap 1: FIFO dari batch terlama yang masih punya sisa ──
        $q = DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->where('qty_sisa', '>', 0);

        if ($divisiId) {
            $q->where('divisi_id', $divisiId);
        }

        $batches = $q->orderBy('id', 'asc')->get();

        foreach ($batches as $batch) {
            if ($sisa <= 0) break;
            $ambil  = min($sisa, $batch->qty_sisa);
            $nilai += $ambil * $batch->harga_per_qty;
            $sisa  -= $ambil;
        }

        // ── Tahap 2: Fallback rata-rata batch historis jika qty_sisa semua 0 ──
        if ($sisa > 0) {
            $fbQ = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId);
            if ($divisiId) {
                $fbQ->where('divisi_id', $divisiId);
            }
            $hargaRata = $fbQ->avg('harga_per_qty');

            if (!$hargaRata) {
                $hargaRata = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $barangId)
                    ->avg('harga_per_qty');
            }

            if ($hargaRata) {
                $nilai += $sisa * $hargaRata;
                $sisa   = 0;
            }
        }

        // ── Tahap 3: Fallback hpp_referensi master barang ──
        if ($sisa > 0) {
            $hpp = DB::table('master_barang')
                ->where('id', $barangId)
                ->value('hpp_referensi');

            if ($hpp) {
                $nilai += $sisa * $hpp;
            }
        }

        return (float) $nilai;
    }
}