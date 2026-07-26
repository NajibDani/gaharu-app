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
                    ->select(
                        'pengeluaran_bahan_baku.*',
                        'master_gudang.nama as nama_gudang'
                    );

        if ($search) {
            $query->where('no_pengeluaran', 'like', '%' . $search . '%');
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view(
            'pengeluaran-bahan-baku.index',
            compact('data')
        );
    }

    /**
     * Show the form for creating a new resource.
     */

    public function create()
{
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
        ->where('master_barang.is_active', true)
        ->select([
            'master_barang.*',
            DB::raw('COALESCE(stok_gudang.jumlah,0) as stok')
        ])
        ->orderBy('master_barang.nama')
        ->get();

    $gudang = MasterGudang::all();

    return view(
        'pengeluaran-bahan-baku.create',
        compact(
            'barang',
            'gudang'
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

        $data = PengeluaranBahanBaku::create([

            'kode_pengeluaran'
                => 'PBK-' . time(),

            'tanggal'
                => now(),

            /*
            |--------------------------------------------------------------------------
            | GUDANG
            |--------------------------------------------------------------------------
            */

            'gudang_id'
                => $request->gudang_id,

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
            'gudang'
        ])->findOrFail($id);

        if ($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui') {
            foreach ($pengeluaran->details as $detail) {
                $est = $this->fifoService->getEstimatedHargaFIFO(
                    $detail->barang_id,
                    $detail->qty,
                    $pengeluaran->gudang_id ?? 1
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
    $pengeluaran = PengeluaranBahanBaku::with(
        'details'
    )->findOrFail($id);

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
    | TIDAK BOLEH EDIT JIKA DARI WORK ORDER
    |--------------------------------------------------------------------------
    */

    if (
        str_contains(
            strtolower($pengeluaran->keterangan ?? ''),
            'permintaan bahan baku untuk'
        )
    ) {

        return redirect()
            ->route(
                'pengeluaran-bahan-baku.show',
                $pengeluaran->id
            )
            ->with(
                'error',
                'Pengeluaran dari Work Order tidak dapat diedit.'
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

    $gudang = MasterGudang::orderBy('nama')->get();

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
                            $isOperational = $barang && ($barang->is_operational || !$barang->is_bahan_baku);
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
                            
                            // if (!isset($shortageCredits[$idPersediaan])) {
                            //     $shortageCredits[$idPersediaan] = 0;
                            // }
                            // $shortageCredits[$idPersediaan] += $hppTotal;
                            // $totalShortageDebit += $hppTotal;
                        }

                        /*
                        |------------------------------------------------------------------
                        | KURANGI STOK SUMMARY (stok_gudang)
                        |------------------------------------------------------------------
                        |
                        | stockOut() biasanya validasi stok_gudang dulu, tapi untuk
                        | Stock Opname kita bypass validasi dan langsung decrement
                        | karena selisih negatif opname memang harus tetap diproses
                        | meski summary sudah menunjukkan 0.
                        |
                        */

                        $stokGudang = StokGudang::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $gudangOpname)
                            ->lockForUpdate()
                            ->first();

                        if ($stokGudang) {
                            // Decrement langsung, boleh jadi negatif untuk koreksi opname
                            $stokGudang->decrement('jumlah', $detail->qty);
                        }
                        // Jika baris stok_gudang tidak ada, tidak perlu dibuat
                        // (barang ini memang tidak ada di gudang — konsisten dengan opname)

                        TransaksiStok::create([
                            'tanggal'        => now(),
                            'tipe'           => 'keluar',
                            'source_type'    => 'pengeluaran_bahan_baku',
                            'source_id'      => $data->id,
                            'gudang_asal_id' => $gudangOpname,
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