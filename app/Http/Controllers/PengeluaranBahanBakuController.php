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
use App\Models\StokGudangBatch;
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
        $jenisFilter = $request->query('jenis');
        $divisiId = $request->query('divisi_id');
        $sort = $request->query('sort', 'terbaru');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

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
            $query->where(function($q) use ($search) {
                $q->where('pengeluaran_bahan_baku.kode_pengeluaran', 'like', '%' . $search . '%')
                  ->orWhere('pengeluaran_bahan_baku.keterangan', 'like', '%' . $search . '%');
            });
        }
        if ($jenisFilter) {
            $query->where('pengeluaran_bahan_baku.jenis_pengeluaran', $jenisFilter);
        }
        if ($divisiId) {
            $query->where('pengeluaran_bahan_baku.divisi_id', $divisiId);
        }
        if ($dari) {
            $query->whereDate('pengeluaran_bahan_baku.tanggal', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('pengeluaran_bahan_baku.tanggal', '<=', $sampai);
        }

        if ($sort === 'terlama') {
            $query->orderBy('pengeluaran_bahan_baku.tanggal', 'asc')
                  ->orderBy('pengeluaran_bahan_baku.id', 'asc');
        } else {
            $query->orderBy('pengeluaran_bahan_baku.tanggal', 'desc')
                  ->orderBy('pengeluaran_bahan_baku.id', 'desc');
        }

        $data = $query->paginate(10)->withQueryString();

        $divisiList = \App\Models\GudangDivisi::with('gudang')->orderBy('nama', 'asc')->get();

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
            compact('data', 'outletSuggestionsSummary', 'divisiList')
        );
    }

    /**
     * Mengambil saran Bahan Baku di bawah batas minimum stock untuk Gudang/Outlet tertentu (JSON)
     */
    public function suggestions(Request $request)
    {
        $gudangId = $request->query('gudang_id');
        $divisiId = $request->query('divisi_id');

        if (!$gudangId) {
            return response()->json([
                'gudang_name' => '',
                'divisi_name' => '',
                'suggestions' => [],
            ]);
        }

        $gudang = MasterGudang::find($gudangId);
        if (!$gudang) {
            return response()->json([
                'gudang_name' => '',
                'divisi_name' => '',
                'suggestions' => [],
            ]);
        }

        $divisi = $divisiId ? \App\Models\GudangDivisi::find($divisiId) : null;

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
            ->whereNotExists(function($notExistsQuery) use ($gudangId, $divisiId) {
                $notExistsQuery->select(DB::raw(1))
                    ->from('barang_minimum_stock')
                    ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                    ->where('barang_minimum_stock.gudang_id', $gudangId)
                    ->where('barang_minimum_stock.is_active', false);
                if ($divisiId) {
                    $notExistsQuery->where('barang_minimum_stock.divisi_id', $divisiId);
                } else {
                    $notExistsQuery->whereNull('barang_minimum_stock.divisi_id');
                }
            })
            ->with(['minimumStocks' => function ($q) use ($gudangId, $divisiId) {
                $q->where('gudang_id', $gudangId);
                if ($divisiId) {
                    $q->where(function ($sq) use ($divisiId) {
                        $sq->where('divisi_id', $divisiId)
                           ->orWhereNull('divisi_id');
                    });
                }
            }])
            ->orderBy('nama', 'asc')
            ->get();

        $suggestions = [];
        foreach ($items as $it) {
            $minStock = 0;

            // Prioritas 1: Tabel barang_minimum_stock untuk outlet + divisi terkait
            if ($it->minimumStocks && $it->minimumStocks->count() > 0) {
                if ($divisiId) {
                    $specific = $it->minimumStocks->firstWhere('divisi_id', (int)$divisiId);
                    if ($specific) {
                        if (!$specific->is_active) {
                            continue; // Skip jika non-aktif
                        }
                        if ($specific->minimum_stock !== null) {
                            $minStock = (float)$specific->minimum_stock;
                        }
                    } else {
                        $fallback = $it->minimumStocks->firstWhere('divisi_id', null);
                        if ($fallback) {
                            if (!$fallback->is_active) {
                                continue;
                            }
                            if ($fallback->minimum_stock !== null) {
                                $minStock = (float)$fallback->minimum_stock;
                            }
                        }
                    }
                } else {
                    $general = $it->minimumStocks->first();
                    if ($general) {
                        if (!$general->is_active) {
                            continue;
                        }
                        if ($general->minimum_stock !== null) {
                            $minStock = (float)$general->minimum_stock;
                        }
                    }
                }
            }

            // Prioritas 2: Kolom minimum_stock_ck/gaharu/kejingga jika ada
            if ($minStock <= 0 && $minStockField && !empty($it->{$minStockField})) {
                $minStock = (float) $it->{$minStockField};
            }

            // Prioritas 3: Kolom minimum_stock umum
            if ($minStock <= 0 && !empty($it->minimum_stock)) {
                $minStock = (float) $it->minimum_stock;
            }

            if ($minStock <= 0) {
                continue;
            }

            $stokQuery = StokGudang::where('gudang_id', $gudang->id)
                ->where('barang_id', $it->id);
            if ($divisiId) {
                $stokQuery->where('divisi_id', $divisiId);
            }

            $currentStock = (float) ($stokQuery->value('jumlah') ?? 0);

            if ($currentStock < $minStock) {
                $deficit = $minStock - $currentStock;
                $suggestedQty = max(1, (float) ceil($deficit));
                
                $konversi = (float) ($it->konversi_pembelian ?: 1);
                $satuanPembelian = $it->satuan_pembelian ?: $it->satuan;
                $hasKonversi = ($it->satuan_pembelian && $konversi > 1 && $it->satuan_pembelian !== $it->satuan);
                $suggestedQtyInput = $hasKonversi ? (float) ceil($suggestedQty / $konversi) : $suggestedQty;

                // Stok yang tersedia di Gudang Utama (Gudang ID 1)
                $stokUtama = (float) (StokGudang::where('gudang_id', 1)
                    ->where('barang_id', $it->id)
                    ->value('jumlah') ?? 0);

                $suggestions[] = [
                    'barang_id'            => $it->id,
                    'kode_barang'          => $it->kode_barang,
                    'nama'                 => $it->nama,
                    'satuan'               => $it->satuan,
                    'satuan_pembelian'      => $satuanPembelian,
                    'konversi_pembelian'    => $konversi,
                    'has_konversi'         => $hasKonversi,
                    'current_stock'        => $currentStock,
                    'min_stock'            => $minStock,
                    'stok_utama'           => $stokUtama,
                    'suggested_qty'        => $suggestedQty,
                    'suggested_qty_input'  => $suggestedQtyInput,
                ];
            }
        }

        return response()->json([
            'gudang_name' => $gudang->nama,
            'divisi_name' => $divisi ? $divisi->nama : null,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $selectedGudangId = $request->query('gudang_id');
        $jenis = $request->query('jenis', 'transfer');
        if ($request->query('wasted')) {
            $jenis = 'wasted';
        }

        if ($jenis === 'wasted') {
            $gudangSourceId = $selectedGudangId ?: 2;
        } else {
            $gudangUtama = MasterGudang::where('nama', 'like', '%Gudang Utama%')
                ->orWhere('nama', 'like', '%Utama%')
                ->first();
            $gudangSourceId = $gudangUtama ? $gudangUtama->id : 2;
        }

        $queryBarang = MasterBarang::query()
            ->leftJoin('stok_gudang', function ($join) use ($gudangSourceId) {
                $join->on(
                    'master_barang.id',
                    '=',
                    'stok_gudang.barang_id'
                );
                $join->where(
                    'stok_gudang.gudang_id',
                    $gudangSourceId
                );
            })
            ->where('master_barang.is_active', true);

        if ($jenis !== 'wasted') {
            $queryBarang->where('master_barang.is_bahan_baku', 1)
                        ->where('master_barang.is_bahan_setengah_jadi', 0);
        }

        $barang = $queryBarang->select([
                'master_barang.*',
                DB::raw('COALESCE(stok_gudang.jumlah,0) as stok')
            ])
            ->orderBy('master_barang.nama')
            ->get();

        $gudang = MasterGudang::with('divisi')->orderBy('nama')->get();

        return view(
            'pengeluaran-bahan-baku.create',
            compact(
                'barang',
                'gudang',
                'selectedGudangId',
                'jenis'
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

            'jenis_pengeluaran'
                => 'nullable|string|in:transfer,wasted',

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

        $jenisPengeluaran = $request->input('jenis_pengeluaran', 'transfer');
        $prefix = ($jenisPengeluaran === 'wasted') ? 'PBK-WST-' : 'PBK-';

        $data = PengeluaranBahanBaku::create([

            'kode_pengeluaran'
                => $prefix . time(),

            'tanggal'
                => now(),

            'gudang_id'
                => $request->gudang_id,

            'divisi_id'
                => $request->divisi_id,

            'jenis_pengeluaran'
                => $jenisPengeluaran,

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

                'harga_satuan'
                    => 0,

                'total_harga'
                    => 0,
            ]);
        }

        $msg = ($jenisPengeluaran === 'wasted') 
            ? 'Data pengeluaran barang wasted/busuk berhasil dibuat.' 
            : 'Data pengeluaran bahan baku berhasil dibuat.';

        return redirect()
            ->route('pengeluaran-bahan-baku.index')
            ->with('success', $msg);
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

        $isWasted = ($pengeluaran->jenis_pengeluaran === 'wasted' || str_starts_with($pengeluaran->kode_pengeluaran, 'PBK-WST-'));

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;

        $isApproved = in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);

        foreach ($pengeluaran->details as $detail) {
            if (!$isApproved) {
                $est = $this->fifoService->getEstimatedHargaFIFO(
                    $detail->barang_id,
                    $detail->qty,
                    $isWasted ? ($pengeluaran->gudang_id ?? 1) : $gudangUtamaId,
                    $isWasted ? $pengeluaran->divisi_id : null
                );
                $detail->hpp_total = $est['total_harga'];
            }

            if ($isWasted) {
                $stokGudangQuery = StokGudang::where('gudang_id', $pengeluaran->gudang_id)->where('barang_id', $detail->barang_id);
                if ($pengeluaran->divisi_id) {
                    $stokGudangQuery->where('divisi_id', $pengeluaran->divisi_id);
                }
                $stokTersedia = (float) ($stokGudangQuery->sum('jumlah') ?? 0);
            } else {
                $stokTersedia = (float) (StokGudang::where('gudang_id', $gudangUtamaId)->where('barang_id', $detail->barang_id)->sum('jumlah') ?? 0);
            }

            $detail->stok_tersedia = $stokTersedia;
            $detail->stok_gudang_utama = $stokTersedia; // backward compatibility
            $detail->kekurangan = max(0, (float)$detail->qty - $stokTersedia);
        }

        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();

        return view(
            'pengeluaran-bahan-baku.show',
            compact('pengeluaran', 'gudangUtama', 'isApproved', 'isWasted', 'isSuperAdmin')
        );
    }

    /**
     * Mengambil detail pengeluaran untuk Modal Pop-up Minimalist (JSON)
     */
    public function detailJson(string $id)
    {
        $pengeluaran = PengeluaranBahanBaku::with([
            'details.barang',
            'gudang',
            'divisi',
        ])->findOrFail($id);

        $isWasted = ($pengeluaran->jenis_pengeluaran === 'wasted' || str_starts_with($pengeluaran->kode_pengeluaran, 'PBK-WST-'));

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;

        $isApproved = in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);

        $grandTotal = 0;
        $totalKurang = 0;

        $details = $pengeluaran->details->map(function ($detail) use ($pengeluaran, $isApproved, $isWasted, $gudangUtamaId, &$grandTotal, &$totalKurang) {
            $hppTotal = (float) ($detail->hpp_total ?? 0);
            if (!$isApproved) {
                $est = $this->fifoService->getEstimatedHargaFIFO(
                    $detail->barang_id,
                    $detail->qty,
                    $isWasted ? ($pengeluaran->gudang_id ?? 1) : $gudangUtamaId,
                    $isWasted ? $pengeluaran->divisi_id : null
                );
                $hppTotal = (float) ($est['total_harga'] ?? 0);
            }
            $grandTotal += $hppTotal;
            $hargaSatuan = $detail->qty > 0 ? ($hppTotal / $detail->qty) : 0;

            $qtyDiminta = (float) $detail->qty;

            if ($isWasted) {
                $stokGudangQuery = StokGudang::where('gudang_id', $pengeluaran->gudang_id)->where('barang_id', $detail->barang_id);
                if ($pengeluaran->divisi_id) {
                    $stokGudangQuery->where('divisi_id', $pengeluaran->divisi_id);
                }
                $stokTersedia = (float) ($stokGudangQuery->sum('jumlah') ?? 0);
            } else {
                $stokTersedia = (float) (StokGudang::where('gudang_id', $gudangUtamaId)->where('barang_id', $detail->barang_id)->sum('jumlah') ?? 0);
            }

            $kekurangan = max(0, $qtyDiminta - $stokTersedia);

            if ($kekurangan > 0) {
                $totalKurang++;
            }

            $satuan = $detail->barang->satuan ?? ($detail->satuan ?? 'pcs');

            if ($stokTersedia > $qtyDiminta) {
                $statusStok = 'Tersedia Penuh';
                $statusColor = 'success';
            } elseif ($stokTersedia == $qtyDiminta && $stokTersedia > 0) {
                $statusStok = 'Stok Terakhir di Gudang (Segera Pembelian)';
                $statusColor = 'warning';
            } elseif ($stokTersedia > 0) {
                $statusStok = 'Kurang ' . number_format($kekurangan, 2, ',', '.') . ' ' . $satuan;
                $statusColor = 'danger';
            } else {
                $statusStok = 'Stok Habis (0)';
                $statusColor = 'danger';
            }

            $bItem       = $detail->barang;
            $satuanBeli  = $bItem->satuan_pembelian ?? '';
            $konversi    = (float) ($bItem->konversi_pembelian ?? 1);
            $hasKonv     = ($satuanBeli && $konversi > 1 && $satuanBeli !== $satuan);

            return [
                'id'                 => $detail->id,
                'nama_barang'        => $detail->barang->nama ?? '-',
                'kode_barang'        => $detail->barang->kode_barang ?? '-',
                'satuan'             => $satuan,
                'satuan_pembelian'   => $satuanBeli,
                'konversi_pembelian' => $konversi,
                'has_konversi'       => $hasKonv,
                'qty'                => $qtyDiminta,
                'stok_tersedia'      => $stokTersedia,
                'stok_gudang_utama'  => $stokTersedia, // backward compatibility
                'kekurangan'         => $kekurangan,
                'status_stok'        => $statusStok,
                'status_color'       => $statusColor,
                'harga_satuan'       => $hargaSatuan,
                'total_harga'        => $hppTotal,
            ];
        });

        $isWO = str_contains(
            strtolower($pengeluaran->keterangan ?? ''),
            'permintaan bahan baku untuk'
        );

        $lokasiNama = ($pengeluaran->gudang->nama ?? '-') . ($pengeluaran->divisi ? ' - ' . $pengeluaran->divisi->nama : '');

        return response()->json([
            'id'                  => $pengeluaran->id,
            'kode_pengeluaran'    => $pengeluaran->kode_pengeluaran,
            'tanggal'             => \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d M Y H:i'),
            'gudang_nama'         => $pengeluaran->gudang->nama ?? '-',
            'gudang_utama_nama'   => $gudangUtama->nama ?? 'Gudang Utama',
            'divisi_nama'         => $pengeluaran->divisi->nama ?? null,
            'lokasi_nama'         => $lokasiNama,
            'is_wasted'           => $isWasted,
            'jenis_pengeluaran'   => $pengeluaran->jenis_pengeluaran ?? ($isWasted ? 'wasted' : 'transfer'),
            'status'              => $pengeluaran->status,
            'is_approved'         => $isApproved,
            'is_superadmin'       => (bool) (auth()->user() && auth()->user()->isSuperAdmin()),
            'keterangan'          => $pengeluaran->keterangan ?? '-',
            'is_wo'               => $isWO,
            'grand_total'         => $grandTotal,
            'total_item'          => count($details),
            'total_item_kurang'   => $totalKurang,
            'can_approve'         => auth()->user() && auth()->user()->canApprovePengeluaran(),
            'pdf_url'             => route('pengeluaran-bahan-baku.cetak-pdf', $pengeluaran->id),
            'edit_url'            => route('pengeluaran-bahan-baku.edit', $pengeluaran->id),
            'approve_url'         => route('pengeluaran-bahan-baku.approve', $pengeluaran->id),
            'delete_url'          => route('pengeluaran-bahan-baku.destroy', $pengeluaran->id),
            'details'             => $details,
        ]);
    }

    /**
     * Cetak / Download PDF Surat Permintaan & Transfer Bahan Baku / Wasted
     */
    public function cetakPdf(string $id)
    {
        $pengeluaran = PengeluaranBahanBaku::with([
            'details.barang',
            'gudang',
            'divisi',
            'user',
        ])->findOrFail($id);

        $isWasted = ($pengeluaran->jenis_pengeluaran === 'wasted' || str_starts_with($pengeluaran->kode_pengeluaran, 'PBK-WST-'));

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;

        $isApproved = in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);
        $grandTotal = 0;

        foreach ($pengeluaran->details as $detail) {
            $hppTotal = (float) ($detail->hpp_total ?? 0);
            if (!$isApproved) {
                $est = $this->fifoService->getEstimatedHargaFIFO(
                    $detail->barang_id,
                    $detail->qty,
                    $isWasted ? ($pengeluaran->gudang_id ?? 1) : $gudangUtamaId,
                    $isWasted ? $pengeluaran->divisi_id : null
                );
                $hppTotal = (float) ($est['total_harga'] ?? 0);
            }
            $grandTotal += $hppTotal;
            $detail->calculated_hpp = $hppTotal;
            $detail->harga_satuan = $detail->qty > 0 ? ($hppTotal / $detail->qty) : 0;

            if ($isWasted) {
                $stokGudangQuery = StokGudang::where('barang_id', $detail->barang_id)->where('gudang_id', $pengeluaran->gudang_id);
                if ($pengeluaran->divisi_id) {
                    $stokGudangQuery->where('divisi_id', $pengeluaran->divisi_id);
                }
                $stokTersedia = (float) ($stokGudangQuery->sum('jumlah') ?? 0);
            } else {
                $stokTersedia = (float) (StokGudang::where('gudang_id', $gudangUtamaId)->where('barang_id', $detail->barang_id)->sum('jumlah') ?? 0);
            }

            $detail->stok_tersedia = $stokTersedia;
            $detail->stok_gudang_utama = $stokTersedia; // backward compatibility
            $detail->kekurangan = max(0, (float)$detail->qty - $stokTersedia);
        }

        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
        $pdf->loadView('pengeluaran-bahan-baku.pdf', compact('pengeluaran', 'gudangUtama', 'grandTotal', 'isApproved', 'isWasted'));

        $filename = ($isWasted ? 'Berita-Acara-Wasted-' : 'Transfer-Bahan-') . $pengeluaran->kode_pengeluaran . '.pdf';
        return $pdf->stream($filename);
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
        | VALIDASI STATUS & AKSES SUPER ADMIN
        |--------------------------------------------------------------------------
        */

        $isApproved = in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);

        if ($isApproved && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            return redirect()
                ->route('pengeluaran-bahan-baku.index')
                ->with(
                    'error',
                    'Pengeluaran yang sudah disetujui hanya dapat diedit oleh Super Admin.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | MASTER BARANG
        |--------------------------------------------------------------------------
        */

        $jenis = $pengeluaran->jenis_pengeluaran ?? (str_starts_with($pengeluaran->kode_pengeluaran, 'PBK-WST-') ? 'wasted' : 'transfer');

        $queryBarang = MasterBarang::query()
            ->leftJoin('stok_gudang', function ($join) use ($pengeluaran) {
                $join->on('master_barang.id', '=', 'stok_gudang.barang_id')
                     ->where('stok_gudang.gudang_id', $pengeluaran->gudang_id ?? 1);
            })
            ->where('master_barang.is_active', true);

        if ($jenis !== 'wasted') {
            $queryBarang->where('master_barang.is_bahan_baku', 1)
                        ->where('master_barang.is_bahan_setengah_jadi', 0);
        }

        $barang = $queryBarang->select([
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
                'gudang',
                'jenis',
                'isApproved'
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

        $isApprovedGlobal = false;

        try {
            DB::transaction(function () use (
                $request,
                $id,
                &$isApprovedGlobal
            ) {
                $data = PengeluaranBahanBaku::with('details')->findOrFail($id);

                /*
                |----------------------------------------------------------------------
                | LOCK APPROVED / HAK AKSES SUPER ADMIN
                |----------------------------------------------------------------------
                */
                $isApproved = in_array(strtolower($data->status), ['approved', 'disetujui']);
                $isApprovedGlobal = $isApproved;
                $user = auth()->user();
                $isSuperAdmin = $user && $user->isSuperAdmin();

                if ($isApproved && !$isSuperAdmin) {
                    throw new \Exception(
                        'Pengeluaran yang sudah disetujui hanya dapat diedit oleh Super Admin.'
                    );
                }

                if ($isApproved) {
                    // 1. Rollback alokasi stok lama
                    foreach ($data->details as $oldDetail) {
                        $this->rollbackApprovedDetail($data, $oldDetail);
                    }

                    // Hapus jurnal penyesuaian lama jika ada
                    $jps = DB::table('jurnal_penyesuaian')->where('source_type', 'pengeluaran_bahan_baku')->where('source_id', $data->id)->pluck('id');
                    if ($jps->isNotEmpty()) {
                        DB::table('journal_items')->whereIn('journal_id', $jps)->where('journal_type', 'jurnal_penyesuaian')->delete();
                        DB::table('jurnal_penyesuaian')->whereIn('id', $jps)->delete();
                    }

                    // Set status draft sementara agar proses re-approval berjalan lancar
                    $data->update(['status' => 'draft']);
                }

                /*
                |----------------------------------------------------------------------
                | UPDATE HEADER
                |----------------------------------------------------------------------
                */
                $data->update([
                    'gudang_id'  => $request->gudang_id,
                    'divisi_id'  => $request->divisi_id,
                    'keterangan' => $request->keterangan,
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
                        'pengeluaran_id' => $data->id,
                        'barang_id'      => $barangId,
                        'qty'            => $request->qty[$index],
                        'satuan'         => 'pcs',
                        'harga_satuan'   => 0,
                        'total_harga'    => 0,
                    ]);
                }

                /*
                |----------------------------------------------------------------------
                | RE-APPROVE JIKA SEBELUMNYA APPROVED
                |----------------------------------------------------------------------
                */
                if ($isApproved) {
                    $data->load('details');
                    $isFromOpname = str_starts_with($data->kode_pengeluaran, 'PBK-SO-');
                    $isWasted = ($data->jenis_pengeluaran === 'wasted' || str_starts_with($data->kode_pengeluaran, 'PBK-WST-'));

                    if ($isFromOpname || $isWasted) {
                        $this->executeApproveWastedOrOpname($data);
                    } else {
                        // Validasi stok Gudang Utama
                        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
                        $gudangAsalId = $gudangUtama ? $gudangUtama->id : 2;

                        foreach ($data->details as $detail) {
                            $stokTersedia = (float) (StokGudang::where('barang_id', $detail->barang_id)
                                ->where('gudang_id', $gudangAsalId)
                                ->sum('jumlah') ?? 0);

                            if ($stokTersedia < $detail->qty) {
                                $barang = MasterBarang::find($detail->barang_id);
                                $namaBarang = $barang ? $barang->nama : "ID Barang: {$detail->barang_id}";
                                $satuan = $barang->satuan ?? 'pcs';
                                $kurang = $detail->qty - $stokTersedia;
                                
                                throw new \Exception(
                                    "Gagal Menyimpan: Stok \"{$namaBarang}\" di Gudang Utama tidak mencukupi untuk disetujui (Diminta: " . number_format($detail->qty, 2, ',', '.') . " {$satuan}, Tersedia: " . number_format($stokTersedia, 2, ',', '.') . " {$satuan}, Kekurangan: -" . number_format($kurang, 2, ',', '.') . " {$satuan})."
                                );
                            }
                        }

                        $this->service->approve($data, auth()->id());
                    }
                }
            });

            return redirect()
                ->route('pengeluaran-bahan-baku.index')
                ->with(
                    'success',
                    'Pengeluaran bahan baku berhasil diperbarui' . ($isApprovedGlobal ? ' dan alokasi stok telah disinkronkan ulang.' : '.')
                );
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui pengeluaran: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $pengeluaran = PengeluaranBahanBaku::with('details')->findOrFail($id);
                $user = auth()->user();
                $isSuperAdmin = $user && $user->isSuperAdmin();

                $isApproved = in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);

                if ($isApproved && !$isSuperAdmin) {
                    throw new \Exception('Pengeluaran yang sudah disetujui hanya dapat dihapus oleh Super Admin.');
                }

                if ($isApproved) {
                    // Rollback stok untuk setiap detail
                    foreach ($pengeluaran->details as $detail) {
                        $this->rollbackApprovedDetail($pengeluaran, $detail);
                    }

                    // Hapus jurnal penyesuaian jika ada
                    $jps = DB::table('jurnal_penyesuaian')->where('source_type', 'pengeluaran_bahan_baku')->where('source_id', $pengeluaran->id)->pluck('id');
                    if ($jps->isNotEmpty()) {
                        DB::table('journal_items')->whereIn('journal_id', $jps)->where('journal_type', 'jurnal_penyesuaian')->delete();
                        DB::table('jurnal_penyesuaian')->whereIn('id', $jps)->delete();
                    }
                }

                // Hapus detail dan header
                $pengeluaran->details()->delete();
                $pengeluaran->delete();
            });

            return redirect()
                ->route('pengeluaran-bahan-baku.index')
                ->with('success', 'Data permintaan / pengeluaran bahan baku berhasil dihapus dan stok telah dikembalikan.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Gagal menghapus pengeluaran: ' . $e->getMessage());
        }
    }

    /**
     * Hapus satu item detail pengeluaran bahan baku.
     * Jika pengeluaran telah disetujui, hanya Super Admin yang diizinkan dan stok otomatis di-rollback.
     */
    public function destroyDetail(string $id, string $detailId)
    {
        try {
            DB::transaction(function () use ($id, $detailId) {
                $pengeluaran = PengeluaranBahanBaku::with('details')->findOrFail($id);
                $user = auth()->user();
                $isSuperAdmin = $user && $user->isSuperAdmin();
                $isApproved = in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);

                if ($isApproved && !$isSuperAdmin) {
                    throw new \Exception('Item pada pengeluaran yang sudah disetujui hanya dapat dihapus oleh Super Admin.');
                }

                if ($pengeluaran->details->count() <= 1) {
                    throw new \Exception('Item ini adalah satu-satunya barang dalam dokumen. Jika ingin membatalkan, gunakan tombol Hapus Seluruh Dokumen.');
                }

                $detail = $pengeluaran->details()->where('id', $detailId)->firstOrFail();

                if ($isApproved) {
                    $this->rollbackApprovedDetail($pengeluaran, $detail);
                }

                $detail->delete();
            });

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item bahan baku berhasil dihapus dan mutasi stok telah dibatalkan.',
                ]);
            }

            return redirect()->back()->with('success', 'Item bahan baku berhasil dihapus dan mutasi stok telah dibatalkan.');
        } catch (\Exception $e) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus item: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', 'Gagal menghapus item: ' . $e->getMessage());
        }
    }

    /**
     * Rollback alokasi stok untuk satu detail pengeluaran yang telah approved.
     */
    protected function rollbackApprovedDetail(PengeluaranBahanBaku $pengeluaran, PengeluaranBahanBakuDetail $detail): void
    {
        $isFromOpname = str_starts_with($pengeluaran->kode_pengeluaran, 'PBK-SO-');
        $isWasted = ($pengeluaran->jenis_pengeluaran === 'wasted' || str_starts_with($pengeluaran->kode_pengeluaran, 'PBK-WST-'));

        if ($isFromOpname || $isWasted) {
            // ALUR OPNAME / WASTED:
            // Stok awal dikeluarkan dari gudang_id & divisi_id
            $gudangLokasi = $pengeluaran->gudang_id;
            $divisiLokasi = $pengeluaran->divisi_id;

            // 1. Kembalikan stok_gudang
            $stokQuery = StokGudang::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $gudangLokasi);
            if ($divisiLokasi) {
                $stokQuery->where('divisi_id', $divisiLokasi);
            } else {
                $stokQuery->whereNull('divisi_id');
            }
            $stokGudang = $stokQuery->lockForUpdate()->first();
            if ($stokGudang) {
                $stokGudang->increment('jumlah', $detail->qty);
            } else {
                StokGudang::create([
                    'barang_id' => $detail->barang_id,
                    'gudang_id' => $gudangLokasi,
                    'divisi_id' => $divisiLokasi,
                    'jumlah'    => $detail->qty,
                ]);
            }

            // 2. Kembalikan batch FIFO yang terpotong
            $fifoRecords = PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)
                ->where('detail_id', $detail->id)
                ->get();
            foreach ($fifoRecords as $fifo) {
                $batch = StokGudangBatch::find($fifo->batch_id);
                if ($batch) {
                    $batch->qty_keluar = max(0, $batch->qty_keluar - $fifo->qty_keluar);
                    $batch->qty_sisa   += $fifo->qty_keluar;
                    $batch->is_habis   = false;
                    $batch->save();
                }
            }
            PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)
                ->where('detail_id', $detail->id)
                ->delete();

            // 3. Hapus TransaksiStok
            TransaksiStok::whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                ->where('source_id', $pengeluaran->id)
                ->where('barang_id', $detail->barang_id)
                ->delete();

        } else {
            // ALUR TRANSFER ANTAR GUDANG:
            // Sumber: Gudang Utama (ID 2 / Kategori Utama)
            // Tujuan: pengeluaran->gudang_id & pengeluaran->divisi_id
            $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
            $gudangAsalId = $gudangUtama ? $gudangUtama->id : 2;

            // 1. ROLLBACK SUMBER (GUDANG UTAMA): Kembalikan stok summary
            $stokAsal = StokGudang::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $gudangAsalId)
                ->whereNull('divisi_id')
                ->lockForUpdate()
                ->first();
            if ($stokAsal) {
                $stokAsal->increment('jumlah', $detail->qty);
            } else {
                StokGudang::create([
                    'barang_id' => $detail->barang_id,
                    'gudang_id' => $gudangAsalId,
                    'divisi_id' => null,
                    'jumlah'    => $detail->qty,
                ]);
            }

            // 2. ROLLBACK SUMBER (GUDANG UTAMA): Kembalikan batch FIFO
            $fifoRecords = PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)
                ->where('detail_id', $detail->id)
                ->get();
            if ($fifoRecords->isNotEmpty()) {
                foreach ($fifoRecords as $fifo) {
                    $batch = StokGudangBatch::find($fifo->batch_id);
                    if ($batch) {
                        $batch->qty_keluar = max(0, $batch->qty_keluar - $fifo->qty_keluar);
                        $batch->qty_sisa   += $fifo->qty_keluar;
                        $batch->is_habis   = false;
                        $batch->save();
                    }
                }
                PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)
                    ->where('detail_id', $detail->id)
                    ->delete();
            } else {
                // Fallback jika histori FIFO belum tercatat (data lama sebelum update)
                $sisaRestore = (float) $detail->qty;
                $consumedBatches = StokGudangBatch::where('barang_id', $detail->barang_id)
                    ->where('gudang_id', $gudangAsalId)
                    ->where('qty_keluar', '>', 0)
                    ->orderBy('id', 'desc')
                    ->get();

                foreach ($consumedBatches as $cBatch) {
                    if ($sisaRestore <= 0) break;
                    $balikan = min($sisaRestore, (float) $cBatch->qty_keluar);
                    $cBatch->qty_keluar -= $balikan;
                    $cBatch->qty_sisa   += $balikan;
                    $cBatch->is_habis   = false;
                    $cBatch->save();
                    $sisaRestore -= $balikan;
                }
            }

            // 3. ROLLBACK TUJUAN: Kurangi stok summary di gudang & divisi tujuan
            $stokTujuanQuery = StokGudang::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $pengeluaran->gudang_id);
            if ($pengeluaran->divisi_id) {
                $stokTujuanQuery->where('divisi_id', $pengeluaran->divisi_id);
            } else {
                $stokTujuanQuery->whereNull('divisi_id');
            }
            $stokTujuan = $stokTujuanQuery->lockForUpdate()->first();
            if ($stokTujuan) {
                $stokTujuan->decrement('jumlah', $detail->qty);
            }

            // 4. ROLLBACK TUJUAN: Hapus/Kurangi batch mutasi (-MUT) di gudang & divisi tujuan
            $destBatchesQuery = StokGudangBatch::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $pengeluaran->gudang_id)
                ->where('batch_number', 'like', '%-MUT');
            if ($pengeluaran->divisi_id) {
                $destBatchesQuery->where('divisi_id', $pengeluaran->divisi_id);
            } else {
                $destBatchesQuery->whereNull('divisi_id');
            }
            $destBatches = $destBatchesQuery->orderBy('id', 'desc')->get();

            $sisaKurang = (float) $detail->qty;
            foreach ($destBatches as $dB) {
                if ($sisaKurang <= 0) break;
                if ($dB->qty_sisa >= $sisaKurang && abs($dB->qty_masuk - $dB->qty_sisa) < 0.0001) {
                    // Batch belum dipakai di tujuan
                    if (abs($dB->qty_masuk - $sisaKurang) < 0.0001) {
                        $dB->delete();
                    } else {
                        $dB->qty_masuk -= $sisaKurang;
                        $dB->qty_sisa  -= $sisaKurang;
                        $dB->save();
                    }
                    $sisaKurang = 0;
                } else {
                    $potong = min($sisaKurang, (float) $dB->qty_sisa);
                    $dB->qty_sisa   -= $potong;
                    $dB->qty_masuk   = max(0, $dB->qty_masuk - $potong);
                    if ($dB->qty_sisa <= 0 && $dB->qty_masuk <= 0) {
                        $dB->delete();
                    } else {
                        $dB->is_habis = ($dB->qty_sisa <= 0);
                        $dB->save();
                    }
                    $sisaKurang -= $potong;
                }
            }

            // 5. Hapus TransaksiStok (baik keluar dari Gudang Utama maupun masuk ke Tujuan)
            TransaksiStok::where('source_type', 'pengeluaran_bahan_baku')
                ->where('source_id', $pengeluaran->id)
                ->where('barang_id', $detail->barang_id)
                ->delete();
        }
    }

    /**
     * Eksekusi alur approve untuk Stock Opname Shortage atau Wasted.
     */
    protected function executeApproveWastedOrOpname(PengeluaranBahanBaku $data): void
    {
        $isWasted = ($data->jenis_pengeluaran === 'wasted' || str_starts_with($data->kode_pengeluaran, 'PBK-WST-'));
        $gudangLokasi = $data->gudang_id;
        $divisiLokasi = $data->divisi_id;
        $idBebanSelisih = DB::table('chart_of_accounts')->where('kode', '6401')->value('id')
            ?? DB::table('chart_of_accounts')->where('kode', '5104')->value('id') 
            ?? DB::table('chart_of_accounts')->where('kode', '5103')->value('id') 
            ?? 44;

        foreach ($data->details as $detail) {
            $fifoResult = $this->fifoService->consumeFIFO(
                barangId:       $detail->barang_id,
                qtyKeluar:      $detail->qty,
                gudangId:       $gudangLokasi,
                allowNegative:  true,
                divisiId:       $divisiLokasi,
            );

            $hppTotal = 0;

            foreach ($fifoResult as $fifo) {
                $totalHarga = $fifo['qty_keluar'] * $fifo['harga_per_qty'];
                $hppTotal  += $totalHarga;

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

            if ($hppTotal > 0) {
                $barang = MasterBarang::find($detail->barang_id);
                $isOperational = $barang && ($barang->is_operational || (!$barang->is_bahan_baku && !$barang->is_bahan_setengah_jadi));
                $coaCode = $isOperational ? '1501' : ($barang->is_bahan_setengah_jadi ? '1302' : ($barang->is_barang_jadi ? '1303' : '1301'));
                $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);

                $deskripsiJp = $isWasted 
                    ? "[AJP] Pengeluaran Wasted / Busuk / Rusak: " . ($barang->nama ?? 'Barang')
                    : "[AJP] Penyesuaian Kurang (Shortage) Stock Opname: " . ($barang->nama ?? 'Barang');

                $refPrefix = $isWasted ? 'AJP-WASTED-' : 'AJP-SO-SHORTAGE-';

                $jp = \App\Models\JurnalPenyesuaian::create([
                    'tanggal'     => now(),
                    'deskripsi'   => $deskripsiJp,
                    'no_ref'      => $refPrefix . $data->kode_pengeluaran . '-' . rand(100, 999),
                    'source_type' => 'pengeluaran_bahan_baku',
                    'source_id'   => $data->id,
                    'created_by'  => auth()->id(),
                    'status'      => 'approved',
                ]);

                // Debit: Beban Selisih HPP / Kerusakan
                $jp->details()->create([
                    'account_id'   => $idBebanSelisih,
                    'debit'        => $hppTotal,
                    'kredit'       => 0,
                    'journal_type' => 'jurnal_penyesuaian',
                ]);

                // Kredit: Persediaan
                $jp->details()->create([
                    'account_id'   => $idPersediaan,
                    'debit'        => 0,
                    'kredit'       => $hppTotal,
                    'journal_type' => 'jurnal_penyesuaian',
                ]);
            }

            $stokQuery = StokGudang::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $gudangLokasi);

            if ($divisiLokasi) {
                $stokQuery->where('divisi_id', $divisiLokasi);
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
                'source_type'    => $isWasted ? 'pengeluaran_wasted' : 'pengeluaran_bahan_baku',
                'source_id'      => $data->id,
                'gudang_asal_id' => $gudangLokasi,
                'divisi_asal_id' => $divisiLokasi,
                'barang_id'      => $detail->barang_id,
                'qty'            => $detail->qty,
                'total_harga'    => $hppTotal,
                'created_by'     => auth()->id(),
            ]);
        }

        $data->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */
    public function approve($id)
    {
        $user = auth()->user();
        if (!$user || !$user->canApprovePengeluaran()) {
            return redirect()
                ->back()
                ->with('error', 'Akses ditolak! Hanya pihak Gudang dan Super Admin yang memiliki hak akses untuk menyetujui (approve) permintaan / transfer bahan baku.');
        }

        try {
            DB::transaction(function () use ($id) {
                $data = PengeluaranBahanBaku::with('details')->findOrFail($id);

                if ($data->status === 'approved' || $data->status === 'disetujui') {
                    throw new \Exception('Pengeluaran sudah diapprove.');
                }

                $isFromOpname = str_starts_with($data->kode_pengeluaran, 'PBK-SO-');
                $isWasted = ($data->jenis_pengeluaran === 'wasted' || str_starts_with($data->kode_pengeluaran, 'PBK-WST-'));

                if ($isFromOpname || $isWasted) {
                    $this->executeApproveWastedOrOpname($data);
                } else {
                    $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
                    $gudangAsalId = $gudangUtama ? $gudangUtama->id : 2;

                    foreach ($data->details as $detail) {
                        $stokTersedia = (float) (StokGudang::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $gudangAsalId)
                            ->sum('jumlah') ?? 0);

                        if ($stokTersedia < $detail->qty) {
                            $barang = MasterBarang::find($detail->barang_id);
                            $namaBarang = $barang ? $barang->nama : "ID Barang: {$detail->barang_id}";
                            $satuan = $barang->satuan ?? 'pcs';
                            $kurang = $detail->qty - $stokTersedia;
                            
                            throw new \Exception(
                                "Gagal Approve: Stok \"{$namaBarang}\" di Gudang Utama tidak mencukupi (Diminta: " . number_format($detail->qty, 2, ',', '.') . " {$satuan}, Tersedia: " . number_format($stokTersedia, 2, ',', '.') . " {$satuan}, Kekurangan: -" . number_format($kurang, 2, ',', '.') . " {$satuan}). Dokumen tidak dapat disetujui."
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
                ->with('success', 'Pengeluaran berhasil disetujui dan mutasi stok berhasil diproses.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}