<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\PengeluaranBahanBaku;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\PengeluaranBahanBakuDetail;

use App\Services\PengeluaranBahanBakuService;
use App\Services\FifoService;
use App\Models\PengeluaranBahanBakuFifo;
use App\Models\StokGudang;
use App\Models\TransaksiStok;

class PengeluaranBahanBakuController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PROPERTY
    |--------------------------------------------------------------------------
    */

    protected $service;

    protected $fifoService;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        PengeluaranBahanBakuService $service,
        FifoService $fifoService
    ) {
        $this->service = $service;

        $this->fifoService = $fifoService;
    }

    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = DB::table('pengeluaran_bahan_baku')
                    ->join(
                        'master_gudang',
                        'pengeluaran_bahan_baku.gudang_id',
                        '=',
                        'master_gudang.id'
                    )
                    ->leftJoin(
                        'gudang_divisi',
                        'pengeluaran_bahan_baku.divisi_id',
                        '=',
                        'gudang_divisi.id'
                    )
                    ->select(
                        'pengeluaran_bahan_baku.*',
                        'master_gudang.nama as nama_gudang',
                        'gudang_divisi.nama as nama_divisi'
                    );

        if ($search) {
            $query->where('no_pengeluaran', 'like', '%' . $search . '%');
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        // Hitung ringkasan saran restock per outlet/gudang cabang (selain Gudang Utama ID 1)
        $outletGudangs = MasterGudang::where('id', '!=', 1)->get();
        $outletSuggestionsSummary = [];

        foreach ($outletGudangs as $g) {
            $gudangNama = strtolower($g->nama);
            $minStockField = null;
            if (str_contains($gudangNama, 'gaharu')) {
                $minStockField = 'minimum_stock_gaharu';
            } elseif (str_contains($gudangNama, 'kejingga')) {
                $minStockField = 'minimum_stock_kejingga';
            } elseif (str_contains($gudangNama, 'central kitchen')) {
                $minStockField = 'minimum_stock_ck';
            }

            $items = MasterBarang::where('is_active', true)
                ->where('is_bahan_baku', 1)
                ->where('is_bahan_setengah_jadi', 0)
                ->get();

            $criticalCount = 0;
            foreach ($items as $it) {
                $minStock = 0;
                if ($minStockField && !empty($it->{$minStockField})) {
                    $minStock = (float) $it->{$minStockField};
                } elseif (!empty($it->minimum_stock)) {
                    $minStock = (float) $it->minimum_stock;
                }

                if ($minStock > 0) {
                    $currentStock = (float) (StokGudang::where('gudang_id', $g->id)
                        ->where('barang_id', $it->id)
                        ->value('jumlah') ?? 0);
                    if ($currentStock < $minStock) {
                        $criticalCount++;
                    }
                }
            }

            if ($criticalCount > 0) {
                $outletSuggestionsSummary[] = [
                    'gudang_id'   => $g->id,
                    'gudang_nama' => $g->nama,
                    'count'       => $criticalCount,
                ];
            }
        }

        return view(
            'pengeluaran-bahan-baku.index',
            compact('data', 'outletSuggestionsSummary')
        );
    }

    /**
     * Mengambil saran Bahan Baku di bawah batas minimum stock untuk Gudang/Outlet tertentu (JSON)
     */
    public function suggestions(Request $request)
    {
        $gudangId = $request->query('gudang_id');
        $gudang = MasterGudang::find($gudangId);

        if (!$gudang) {
            return response()->json(['suggestions' => [], 'gudang_name' => '']);
        }

        $gudangNama = strtolower($gudang->nama);
        $minStockField = null;
        if (str_contains($gudangNama, 'gaharu')) {
            $minStockField = 'minimum_stock_gaharu';
        } elseif (str_contains($gudangNama, 'kejingga')) {
            $minStockField = 'minimum_stock_kejingga';
        } elseif (str_contains($gudangNama, 'central kitchen')) {
            $minStockField = 'minimum_stock_ck';
        }

        $items = MasterBarang::where('is_active', true)
            ->where('is_bahan_baku', 1)
            ->where('is_bahan_setengah_jadi', 0)
            ->orderBy('nama', 'asc')
            ->get();

        $suggestions = [];
        foreach ($items as $it) {
            $minStock = 0;
            if ($minStockField && !empty($it->{$minStockField})) {
                $minStock = (float) $it->{$minStockField};
            } elseif (!empty($it->minimum_stock)) {
                $minStock = (float) $it->minimum_stock;
            }

            if ($minStock <= 0) {
                continue;
            }

            $currentStock = (float) (StokGudang::where('gudang_id', $gudang->id)
                ->where('barang_id', $it->id)
                ->value('jumlah') ?? 0);

            if ($currentStock < $minStock) {
                $deficit = $minStock - $currentStock;
                $suggestedQty = max(1, (float) ceil($deficit));

                // Stok yang tersedia di Gudang Utama (Gudang ID 1)
                $stokUtama = (float) (StokGudang::where('gudang_id', 1)
                    ->where('barang_id', $it->id)
                    ->value('jumlah') ?? 0);

                $suggestions[] = [
                    'barang_id'     => $it->id,
                    'kode_barang'   => $it->kode_barang,
                    'nama'          => $it->nama,
                    'satuan'        => $it->satuan,
                    'current_stock' => $currentStock,
                    'min_stock'     => $minStock,
                    'stok_utama'    => $stokUtama,
                    'suggested_qty' => $suggestedQty,
                ];
            }
        }

        return response()->json([
            'gudang_name' => $gudang->nama,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $selectedGudangId = $request->query('gudang_id');

        $barang = MasterBarang::query()
            ->leftJoin('stok_gudang', function ($join) {
                $join->on(
                    'master_barang.id',
                    '=',
                    'stok_gudang.barang_id'
                );
                $join->where(
                    'stok_gudang.gudang_id',
                    1
                );
            })
            ->where('master_barang.is_bahan_baku', 1)
            ->where('master_barang.is_bahan_setengah_jadi', 0)
            ->where('master_barang.is_active', true)
            ->select([
                'master_barang.*',
                DB::raw('COALESCE(stok_gudang.jumlah,0) as stok')
            ])
            ->orderBy('master_barang.nama')
            ->get();

        $gudang = MasterGudang::with('divisi')->orderBy('nama')->get(); // Semua gudang sesuai Master Gudang

        return view(
            'pengeluaran-bahan-baku.create',
            compact(
                'barang',
                'gudang',
                'selectedGudangId'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $request->validate([

            'gudang_id'
                => 'required|exists:master_gudang,id',

            'divisi_id'
                => 'nullable|exists:gudang_divisi,id',

            'barang_id'
                => 'required|array|min:1',

            'barang_id.*'
                => 'required|exists:master_barang,id',

            'qty'
                => 'required|array|min:1',

            'qty.*'
                => 'required|numeric|min:0.01',

            'keterangan'
                => 'nullable|string',
        ]);

        $selectedGudang = MasterGudang::with('divisi')->find($request->gudang_id);
        if ($selectedGudang && strtolower($selectedGudang->kategori) === 'operasional' && $selectedGudang->divisi->count() > 0 && empty($request->divisi_id)) {
            return back()->withErrors(['divisi_id' => 'Silakan pilih divisi untuk gudang operasional ' . $selectedGudang->nama . '.'])->withInput();
        }

        $data = PengeluaranBahanBaku::create([

            'kode_pengeluaran'
                => 'PBK-' . time(),

            'tanggal'
                => now(),

            /*
            |--------------------------------------------------------------------------
            | GUDANG & DIVISI
            |--------------------------------------------------------------------------
            */

            'gudang_id'
                => $request->gudang_id,

            'divisi_id'
                => $request->divisi_id,

            'status'
                => 'draft',

            'keterangan'
                => $request->keterangan,

            'created_by'
                => auth()->id(),
        ]);

        foreach ($request->barang_id as $index => $barangId) {

            PengeluaranBahanBakuDetail::create([

                'pengeluaran_id'
                    => $data->id,

                'barang_id'
                    => $barangId,

                'qty'
                    => $request->qty[$index],

                'satuan'
                    => 'pcs',

                /*
                |--------------------------------------------------------------------------
                | NANTI BISA DIISI FIFO HPP
                |--------------------------------------------------------------------------
                */

                'harga_satuan'
                    => 0,

                'total_harga'
                    => 0,
            ]);
        }

        return redirect()
            ->route('pengeluaran-bahan-baku.index')
            ->with(
                'success',
                'Data pengeluaran bahan baku berhasil dibuat.'
            );
    }

    /**
     * Display the specified resource.
     */

    public function show(string $id)
    {
        $pengeluaran = PengeluaranBahanBaku::with([
            'details.barang',
            'gudang',
            'divisi',
        ])->findOrFail($id);

        if ($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui') {
            foreach ($pengeluaran->details as $detail) {
                $est = $this->fifoService->getEstimatedHargaFIFO(
                    $detail->barang_id,
                    $detail->qty,
                    $pengeluaran->gudang_id ?? 1,
                    $pengeluaran->divisi_id
                );
                $detail->hpp_total = $est['total_harga'];
            }
        }

        return view(
            'pengeluaran-bahan-baku.show',
            compact('pengeluaran')
        );
    }

    /**
     * Show the form for editing the specified resource.
     */

    public function edit(string $id)
{
    $pengeluaran = PengeluaranBahanBaku::with([
        'details',
        'gudang',
        'divisi',
    ])->findOrFail($id);

    /*
    |--------------------------------------------------------------------------
    | TIDAK BOLEH EDIT JIKA SUDAH APPROVED
    |--------------------------------------------------------------------------
    */

    if (
        strtolower($pengeluaran->status) === 'approved'
        ||
        strtolower($pengeluaran->status) === 'disetujui'
    ) {

        return redirect()
            ->route('pengeluaran-bahan-baku.index')
            ->with(
                'error',
                'Pengeluaran yang sudah disetujui tidak dapat diedit.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MASTER BARANG
    |--------------------------------------------------------------------------
    */

    $barang = MasterBarang::query()
        ->leftJoin('stok_gudang', function ($join) {
            $join->on('master_barang.id', '=', 'stok_gudang.barang_id')
                 ->where('stok_gudang.gudang_id', 1);
        })
        ->where('master_barang.is_bahan_baku', 1)
        ->where('master_barang.is_bahan_setengah_jadi', 0)
        ->where('master_barang.is_active', true)
        ->select([
            'master_barang.*',
            DB::raw('COALESCE(stok_gudang.jumlah,0) as stok')
        ])
        ->orderBy('master_barang.nama')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | MASTER GUDANG
    |--------------------------------------------------------------------------
    */

    $gudang = MasterGudang::with('divisi')->orderBy('nama')->get();

    return view(
        'pengeluaran-bahan-baku.edit',
        compact(
            'pengeluaran',
            'barang',
            'gudang'
        )
    );
}
    /**
     * Update the specified resource in storage.
     */

    public function update(
        Request $request,
        string $id
    ) {

    $request->validate([

        'gudang_id'
            => 'required|exists:master_gudang,id',

        'divisi_id'
            => 'nullable|exists:gudang_divisi,id',

        'barang_id'
            => 'required|array|min:1',

        'barang_id.*'
            => 'required|exists:master_barang,id',

        'qty'
            => 'required|array|min:1',

        'qty.*'
            => 'required|numeric|min:0.01',

        'keterangan'
            => 'nullable|string',
    ]);

    $selectedGudang = MasterGudang::with('divisi')->find($request->gudang_id);
    if ($selectedGudang && strtolower($selectedGudang->kategori) === 'operasional' && $selectedGudang->divisi->count() > 0 && empty($request->divisi_id)) {
        return back()->withErrors(['divisi_id' => 'Silakan pilih divisi untuk gudang operasional ' . $selectedGudang->nama . '.'])->withInput();
    }

    DB::transaction(function () use (
        $request,
        $id
    ) {

        $data = PengeluaranBahanBaku::with(
            'details'
        )->findOrFail($id);

        /*
        |----------------------------------------------------------------------
        | LOCK APPROVED
        |----------------------------------------------------------------------
        */

        if (
            strtolower($data->status)
            === 'approved'
            ||
            strtolower($data->status)
            === 'disetujui'
        ) {

            throw new \Exception(
                'Pengeluaran yang sudah disetujui tidak dapat diedit.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | UPDATE HEADER
        |----------------------------------------------------------------------
        */

        $data->update([

            'gudang_id'
                => $request->gudang_id,

            'divisi_id'
                => $request->divisi_id,

            'keterangan'
                => $request->keterangan,
        ]);

        /*
        |----------------------------------------------------------------------
        | HAPUS DETAIL LAMA
        |----------------------------------------------------------------------
        */

        $data->details()->delete();

        /*
        |----------------------------------------------------------------------
        | INSERT DETAIL BARU
        |----------------------------------------------------------------------
        */

        foreach ($request->barang_id as $index => $barangId) {

            PengeluaranBahanBakuDetail::create([

                'pengeluaran_id'
                    => $data->id,

                'barang_id'
                    => $barangId,

                'qty'
                    => $request->qty[$index],

                'satuan'
                    => 'pcs',

                'harga_satuan'
                    => 0,

                'total_harga'
                    => 0,
            ]);
        }
    });

    return redirect()
        ->route('pengeluaran-bahan-baku.index')
        ->with(
            'success',
            'Pengeluaran bahan baku berhasil diperbarui.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */

    public function destroy(string $id)
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    |
    | Saat approve:
    | 1. Stock summary dikurangi
    | 2. FIFO batch dikurangi
    |
    */

    public function approve($id)
    {
        try {
            DB::transaction(function () use ($id) {

                $data = PengeluaranBahanBaku::with(
                    'details'
                )->findOrFail($id);

                /*
                |--------------------------------------------------------------------------
                | VALIDASI STATUS
                |--------------------------------------------------------------------------
                */

                if ($data->status === 'approved' || $data->status === 'disetujui') {

                    throw new \Exception(
                        'Pengeluaran sudah diapprove.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | DETEKSI JENIS PENGELUARAN
                |--------------------------------------------------------------------------
                |
                | Ada 2 jenis pengeluaran dengan alur yang BERBEDA:
                |
                | 1. DARI STOCK OPNAME (kode PBK-SO-*)
                |    → Pengurangan stok murni dari gudang opname
                |    → consumeFIFO dengan allowNegative = true
                |    → Hanya stockOut, TIDAK ada stockIn / batch baru di gudang lain
                |    → Status akhir: 'approved'
                |
                | 2. TRANSFER ANTAR GUDANG (pengeluaran manual / dari WO)
                |    → Dikerjakan sepenuhnya oleh PengeluaranBahanBakuService->approve()
                |    → Service sudah handle: consumeFIFO + batch baru + stockOut + stockIn
                |    → Status akhir: 'disetujui' (set oleh service)
                |    → Controller TIDAK boleh memanggil consumeFIFO lagi agar tidak dobel
                |
                */

                $isFromOpname = str_starts_with($data->kode_pengeluaran, 'PBK-SO-');

                if ($isFromOpname) {

                    /*
                    |----------------------------------------------------------------------
                    | ALUR 1: STOCK OPNAME — pengurangan stok murni
                    |----------------------------------------------------------------------
                    */

                    $gudangOpname = $data->gudang_id;
                    $divisiOpname = $data->divisi_id;
                    $shortageCredits = [];
                    $totalShortageDebit = 0;
                    $idBebanSelisih = DB::table('chart_of_accounts')->where('kode', '6401')->value('id')
                        ?? DB::table('chart_of_accounts')->where('kode', '5104')->value('id') 
                        ?? DB::table('chart_of_accounts')->where('kode', '5103')->value('id') 
                        ?? 44;

                    foreach ($data->details as $detail) {

                        /*
                        | consumeFIFO dengan allowNegative = true:
                        | stok boleh tidak cukup (selisih opname tetap diproses)
                        */
                        $fifoResult = $this->fifoService->consumeFIFO(
                            barangId:       $detail->barang_id,
                            qtyKeluar:      $detail->qty,
                            gudangId:       $gudangOpname,
                            allowNegative:  true,
                            divisiId:       $divisiOpname,
                        );

                        $hppTotal = 0;

                        foreach ($fifoResult as $fifo) {

                            $totalHarga = $fifo['qty_keluar'] * $fifo['harga_per_qty'];
                            $hppTotal  += $totalHarga;

                            // Hanya simpan histori jika ada batch nyata (bukan fallback)
                            if ($fifo['batch_id'] !== null) {
                                PengeluaranBahanBakuFifo::create([
                                    'pengeluaran_id' => $data->id,
                                    'detail_id'      => $detail->id,
                                    'batch_id'       => $fifo['batch_id'],
                                    'batch_number'   => $fifo['batch_number'],
                                    'qty_keluar'     => $fifo['qty_keluar'],
                                    'harga_per_qty'  => $fifo['harga_per_qty'],
                                    'total_harga'    => $totalHarga,
                                ]);
                            }
                        }

                        $hppTotal = round($hppTotal, 2);
                        $detail->update(['hpp_total' => $hppTotal]);

                        // Akumulasi jurnal penyesuaian
                        if ($hppTotal > 0) {
                            $barang = \App\Models\MasterBarang::find($detail->barang_id);
                            $isOperational = $barang && ($barang->is_operational || (!$barang->is_bahan_baku && !$barang->is_bahan_setengah_jadi));
                            $coaCode = $isOperational ? '1501' : '1301';
                            $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);

                            $idBebanSelisih = DB::table('chart_of_accounts')->where('kode', '6401')->value('id')
                                ?? DB::table('chart_of_accounts')->where('kode', '5104')->value('id') 
                                ?? DB::table('chart_of_accounts')->where('kode', '5103')->value('id') 
                                ?? 44;

                            $jp = \App\Models\JurnalPenyesuaian::create([
                                'tanggal'     => now(),
                                'deskripsi'   => "[AJP] Penyesuaian Kurang (Shortage) Stock Opname: " . ($barang->nama ?? 'Barang'),
                                'no_ref'      => 'AJP-SO-SHORTAGE-' . $data->kode_pengeluaran . '-' . rand(100, 999),
                                'source_type' => 'pengeluaran_bahan_baku',
                                'source_id'   => $data->id,
                                'created_by'  => auth()->id(),
                                'status'      => 'approved',
                            ]);

                            // Debit: Beban Selisih HPP
                            $jp->details()->create([
                                'account_id'   => $idBebanSelisih,
                                'debit'        => $hppTotal,
                                'kredit'       => 0,
                                'journal_type' => 'jurnal_penyesuaian',
                            ]);

                            // Kredit: Persediaan (1301 / 1302)
                            $jp->details()->create([
                                'account_id'   => $idPersediaan,
                                'debit'        => 0,
                                'kredit'       => $hppTotal,
                                'journal_type' => 'jurnal_penyesuaian',
                            ]);
                        }

                        /*
                        |------------------------------------------------------------------
                        | KURANGI STOK SUMMARY (stok_gudang)
                        |------------------------------------------------------------------
                        */

                        $stokQuery = StokGudang::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $gudangOpname);

                        if ($divisiOpname) {
                            $stokQuery->where('divisi_id', $divisiOpname);
                        } else {
                            $stokQuery->whereNull('divisi_id');
                        }

                        $stokGudang = $stokQuery->lockForUpdate()->first();

                        if ($stokGudang) {
                            $stokGudang->decrement('jumlah', $detail->qty);
                        }

                        TransaksiStok::create([
                            'tanggal'        => now(),
                            'tipe'           => 'keluar',
                            'source_type'    => 'pengeluaran_bahan_baku',
                            'source_id'      => $data->id,
                            'gudang_asal_id' => $gudangOpname,
                            'divisi_asal_id' => $divisiOpname,
                            'barang_id'      => $detail->barang_id,
                            'qty'            => $detail->qty,
                            'total_harga'    => $hppTotal,
                            'created_by'     => auth()->id(),
                        ]);
                    }

                    // Update status langsung (service tidak dipanggil)
                    $data->update([
                        'status'      => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                } else {

                    /*
                    |----------------------------------------------------------------------
                    | ALUR 2: TRANSFER ANTAR GUDANG — delegasi penuh ke service
                    |----------------------------------------------------------------------
                    */

                    // Ambil Gudang Utama (Gudang Asal)
                    $gudangUtama = \App\Models\MasterGudang::where('nama', 'Gudang Utama')->first();
                    $gudangAsalId = $gudangUtama ? $gudangUtama->id : 1;

                    // Validasi Stok di Gudang Utama
                    foreach ($data->details as $detail) {
                        $stokTersedia = StokGudang::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $gudangAsalId)
                            ->value('jumlah') ?? 0;

                        if ($stokTersedia < $detail->qty) {
                            $barang = \App\Models\MasterBarang::find($detail->barang_id);
                            $namaBarang = $barang ? $barang->nama : "ID Barang: {$detail->barang_id}";
                            
                            throw new \Exception(
                                "Gagal Approve: Stok {$namaBarang} di Gudang Utama tidak mencukupi. (Diminta: {$detail->qty}, Tersedia: {$stokTersedia})"
                            );
                        }
                    }

                    $this->service->approve(
                        $data,
                        auth()->id()
                    );
                }
            });

            return redirect()
                ->route('pengeluaran-bahan-baku.index')
                ->with(
                    'success',
                    'Pengeluaran berhasil disetujui dan FIFO berhasil diproses.'
                );
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}