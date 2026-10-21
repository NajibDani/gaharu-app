<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePembelianRequest;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\StokGudang;

use App\Services\StockService;
use App\Services\FifoService;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PembelianController extends Controller
{
    protected StockService $stockService;
    protected FifoService $fifoService;

    public function __construct(
        StockService $stockService,
        FifoService $fifoService
    ) {
        $this->stockService = $stockService;
        $this->fifoService  = $fifoService;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        if ($request->has('heal')) {
            MasterBarang::autoHealUnconvertedPembelianBatches();
        }

        // Auto-heal jika ada transaksi pembelian yang harganya terkorupsi (cth: PB202610080001)
        MasterBarang::autoHealCorruptedPembelianPrices();

        // Auto-heal sinkronisasi tanggal penerimaan barang dan mutasi stok ke tanggal transaksi pembelian
        self::autoHealHistoricalPembelianPenerimaanTanggal();

        $search            = $request->query('search');
        $sort              = $request->query('sort', 'terbaru');
        $statusPembayaran  = $request->query('status_pembayaran');
        $statusPenerimaan  = $request->query('status_penerimaan');
        $dari              = $request->query('dari');
        $sampai            = $request->query('sampai');

        $user = auth()->user();
        $roleName = $user?->role?->nama ?? '';
        $isSuperAdmin = $user && $user->isSuperAdmin();

        $query = Pembelian::with(['supplier', 'gudang', 'user', 'details.barang']);

        // Hak Akses Role: User Kepala Gudang hanya dapat melihat PBK yang masuk ke Gudang Utama saja.
        // PBK Gudang KeJingga hanya bisa diakses oleh Superadmin.
        if (!$isSuperAdmin && ($user?->isGudang() || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            $query->where('gudang_id', $gudangUtamaId);
        }

        // Filter pencarian
        if ($search) {
            $hasKeterangan = Schema::hasColumn('pembelian', 'keterangan');
            $query->where(function($q) use ($search, $hasKeterangan) {
                $q->where('kode_pembelian', 'like', '%' . $search . '%');
                if ($hasKeterangan) {
                    $q->orWhere('keterangan', 'like', '%' . $search . '%');
                }
                $q->orWhereHas('supplier', function($sq) use ($search) {
                    $sq->where('nama', 'like', '%' . $search . '%');
                });
            });
        }

        // Filter status pembayaran
        if ($statusPembayaran === 'belum_dicatat') {
            $query->whereNull('metode_pembayaran');
        } elseif ($statusPembayaran === 'cod') {
            $query->where('metode_pembayaran', 'cod');
        } elseif ($statusPembayaran === 'belum_lunas') {
            $query->whereNotNull('metode_pembayaran')
                  ->where('metode_pembayaran', '!=', 'cod')
                  ->where('is_lunas', false);
        } elseif ($statusPembayaran === 'lunas') {
            $query->where(function($q) {
                $q->where('metode_pembayaran', 'cod')
                  ->orWhere('is_lunas', true);
            });
        }

        // Filter status penerimaan
        if ($statusPenerimaan === 'belum_diterima') {
            $query->where('is_diterima', false);
        } elseif ($statusPenerimaan === 'diterima') {
            $query->where('is_diterima', true);
        }

        // Filter tanggal
        if ($dari) {
            $query->whereDate('tanggal', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('tanggal', '<=', $sampai);
        }

        // Urutan
        if ($sort === 'terlama') {
            $query->orderBy('tanggal', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('tanggal', 'desc')->orderBy('id', 'desc');
        }

        $pembelian = $query->paginate(10)->withQueryString();

        $dataPembayaran = $pembelian->mapWithKeys(function ($item) {
            $label = match($item->metode_pembayaran) {
                'cod'    => 'COD',
                'termin' => 'Termin',
                'dp'     => $item->nominal_dp && $item->nominal_dp > 0
                            ? 'DP Rp ' . number_format($item->nominal_dp, 0, ',', '.')
                            : 'DP ' . $item->persen_dp . '%',
                default  => '-',
            };
            return [$item->id => [
                'id'                  => $item->id,
                'kode'                => $item->kode_pembelian,
                'supplier_id'         => $item->supplier_id,
                'supplier_nama'       => $item->supplier->nama ?? '-',
                'gudang_id'           => $item->gudang_id,
                'gudang_nama'         => $item->gudang->nama ?? '-',
                'tanggal'             => \Carbon\Carbon::parse($item->tanggal)->format('d M Y'),
                'tanggal_raw'         => \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d'),
                'keterangan'          => $item->keterangan ?? '',
                'tax_service'         => (float) ($item->tax_service ?? 0),
                'total'               => (float) $item->total,
                'metode'              => $item->metode_pembayaran,
                'label'               => $label,
                'persen_dp'           => $item->persen_dp,
                'nominal_dp'          => (float) $item->nominal_dp,
                'is_lunas'            => (bool) $item->is_lunas,
                'is_diterima'         => (bool) $item->is_diterima,
                'is_terkunci'         => (bool) $item->isTerkunci(),
                'kekurangan'          => (float) ($item->kekurangan_pembayaran ?? ($item->is_lunas ? 0 : $item->total)),
                'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo ? \Carbon\Carbon::parse($item->tanggal_jatuh_tempo)->format('d M Y') : null,
                'tanggal_pelunasan'   => $item->tanggal_pelunasan ? \Carbon\Carbon::parse($item->tanggal_pelunasan)->format('d M Y') : null,
                'catatan'             => $item->catatan_pembayaran,
                'dicatat_pada'        => $item->dicatat_pada,
                'details'             => $item->details->map(function ($d) {
                    $bItem = $d->barang;
                    $sPembelian = $d->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                    $dKonv = floatval($d->konversi_pembelian ?? 1);
                    $mKonv = floatval($bItem->konversi_pembelian ?? 1);
                    $konv = $dKonv > 1 ? $dKonv : ($mKonv > 1 ? $mKonv : 1.0);
                    $sUtama = $bItem->satuan ?? 'Pcs';
                    $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);

                    return [
                        'id'                 => $d->id,
                        'barang_id'          => $d->barang_id,
                        'nama'               => $bItem->nama ?? 'Barang',
                        'kode_barang'        => $bItem->kode_barang ?? '',
                        'satuan'             => $sPembelian ?: $sUtama,
                        'satuan_pembelian'   => $sPembelian,
                        'satuan_utama'       => $sUtama,
                        'konversi_pembelian' => $konv,
                        'has_konversi'       => $hasKonv,
                        'qty'                => floatval($d->qty),
                        'qty_diterima'       => floatval($d->qty_diterima ?? 0),
                        'harga'              => floatval($d->harga ?? ($d->qty * $d->harga_per_qty)),
                        'harga_per_qty'      => floatval($d->harga_per_qty),
                        'batch_number'       => $d->batch_number,
                    ];
                })->values()->toArray(),
            ]];
        });

        // Hitung ringkasan saran restock Gudang Utama
        $gudangUtama = MasterGudang::where('nama', 'Gudang Utama')->first();
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 1;
        
        $itemsRestock = MasterBarang::where('is_active', true)
            ->where(function ($q) {
                $q->where('is_bahan_baku', true)
                  ->orWhere('is_bahan_setengah_jadi', true)
                  ->orWhere('is_operational', true)
                  ->orWhere('is_direct_consumption', true);
            })
            ->with(['minimumStocks' => function ($q) use ($gudangUtamaId) {
                $q->where('gudang_id', $gudangUtamaId)->whereNull('divisi_id');
            }])
            ->get();

        $countLowStockUtama = 0;
        foreach ($itemsRestock as $it) {
            $minStock = 0;
            if ($it->minimumStocks && $it->minimumStocks->count() > 0) {
                $ms = $it->minimumStocks->first();
                if ($ms && $ms->is_active && $ms->minimum_stock !== null) {
                    $minStock = (float) $ms->minimum_stock;
                }
            }
            if ($minStock <= 0 && !empty($it->minimum_stock)) {
                $minStock = (float) $it->minimum_stock;
            }
            if ($minStock > 0) {
                $currentStock = (float) (StokGudang::where('gudang_id', $gudangUtamaId)
                    ->whereNull('divisi_id')
                    ->where('barang_id', $it->id)
                    ->value('jumlah') ?? 0);
                if ($currentStock <= $minStock) {
                    $countLowStockUtama++;
                }
            }
        }

        $suppliers = Supplier::orderBy('nama')->get();
        if (!$isSuperAdmin && ($user?->isGudang() || $roleName === 'Kepala Gudang')) {
            $gudangs = MasterGudang::where('id', $gudangUtamaId)->get();
            if ($gudangs->isEmpty()) {
                $gudangs = MasterGudang::where('nama', 'Gudang Utama')->get();
            }
        } else {
            $gudangs = MasterGudang::orderBy('nama')->get();
        }
        $barangs = MasterBarang::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_bahan_baku', true)
                  ->orWhere('is_bahan_setengah_jadi', true)
                  ->orWhere('is_operational', true)
                  ->orWhere('is_direct_consumption', true);
            })
            ->orderBy('nama')
            ->get();

        // Pastikan barang yang sudah ada di detail PO (meski tidak memenuhi filter di atas) tetap masuk ke daftar
        // agar form edit bisa menampilkan dan mengirim barang tersebut dengan benar
        $existingDetailBarangIds = $pembelian->pluck('details')->flatten()->pluck('barang_id')->unique()->filter()->values();
        if ($existingDetailBarangIds->isNotEmpty()) {
            $missingBarangs = MasterBarang::withoutGlobalScopes()
                ->whereIn('id', $existingDetailBarangIds)
                ->whereNotIn('id', $barangs->pluck('id'))
                ->get();
            if ($missingBarangs->isNotEmpty()) {
                $barangs = $barangs->concat($missingBarangs)->sortBy('nama')->values();
            }
        }

        return view('pembelian.index', compact('pembelian', 'dataPembayaran', 'countLowStockUtama', 'suppliers', 'gudangs', 'barangs'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return redirect()->route('pembelian.index', ['tambah' => 1]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGESTIONS FOR RESTOCK GUDANG UTAMA
    |--------------------------------------------------------------------------
    */
    public function suggestions(Request $request)
    {
        $gudangUtamaId = MasterGudang::getGudangUtamaId();
        $gudangUtama = MasterGudang::find($gudangUtamaId);
        $gudangId = $gudangUtamaId;

        $items = MasterBarang::where('is_active', true)
            ->where(function ($q) {
                $q->where('is_bahan_baku', true)
                  ->orWhere('is_bahan_setengah_jadi', true)
                  ->orWhere('is_operational', true)
                  ->orWhere('is_direct_consumption', true);
            })
            ->with(['minimumStocks' => function ($q) use ($gudangId) {
                $q->where('gudang_id', $gudangId)->whereNull('divisi_id');
            }])
            ->orderBy('nama', 'asc')
            ->get();

        $suggestions = [];
        foreach ($items as $it) {
            $minStock = 0;

            // 1. Prioritas tabel barang_minimum_stock
            if ($it->minimumStocks && $it->minimumStocks->count() > 0) {
                $ms = $it->minimumStocks->first();
                if ($ms && $ms->is_active && $ms->minimum_stock !== null) {
                    $minStock = (float) $ms->minimum_stock;
                }
            }

            // 2. Fallback ke kolom minimum_stock di master_barang
            if ($minStock <= 0 && !empty($it->minimum_stock)) {
                $minStock = (float) $it->minimum_stock;
            }

            if ($minStock <= 0) {
                continue;
            }

            // Hitung stok saat ini di Gudang Utama
            $currentStock = (float) (StokGudang::where('gudang_id', $gudangId)
                ->whereNull('divisi_id')
                ->where('barang_id', $it->id)
                ->value('jumlah') ?? 0);

            if ($currentStock <= $minStock) {
                $deficit = $minStock - $currentStock;
                $suggestedQty = max(1, (float) ceil($deficit));

                // Jika ada konversi pembelian, kita tampilkan juga saran qty dalam satuan pembelian
                $konversi = (float) ($it->konversi_pembelian ?: 1);
                $suggestedQtyPembelian = (float) ceil($suggestedQty / $konversi);

                $suggestions[] = [
                    'barang_id'                => $it->id,
                    'kode_barang'              => $it->kode_barang,
                    'nama'                     => $it->nama,
                    'satuan'                   => $it->satuan,
                    'satuan_pembelian'         => $it->satuan_pembelian ?: $it->satuan,
                    'konversi_pembelian'       => $konversi,
                    'current_stock'            => $currentStock,
                    'min_stock'                => $minStock,
                    'suggested_qty'            => $suggestedQty,
                    'suggested_qty_pembelian'  => $suggestedQtyPembelian,
                    'hpp_referensi'            => (float) ($it->hpp_referensi ?? 0),
                ];
            }
        }

        return response()->json([
            'gudang_name' => $gudangUtama ? $gudangUtama->nama : 'Gudang Utama',
            'suggestions' => $suggestions,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — otomatis mencatat penerimaan barang tepat pada tanggal transaksi
    |--------------------------------------------------------------------------
    */

    public function store(StorePembelianRequest $request)
    {
        $data = $request->validated();

        if (\App\Models\Journal::isPeriodClosed($data['tanggal'])) {
            return back()->withErrors(['error' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($data['tanggal'])) . ' sudah ditutup buku. Tidak dapat menambah transaksi pembelian pada periode yang sudah ditutup.'])->withInput();
        }

        DB::transaction(function () use ($data, $request) {

            $taxService = (float) ($data['tax_service'] ?? 0);
            $total = collect($data['items'])->sum(fn($item) => (float) $item['harga']) + $taxService;

            $txDate = $data['tanggal'];
            $txDateTime = $txDate . ' ' . date('H:i:s');

            $createData = [
                'kode_pembelian' => $this->generateKodePembelian($txDate),
                'supplier_id'    => $data['supplier_id'],
                'gudang_id'      => $data['gudang_id'],
                'tanggal'        => $txDate,
                'total'          => $total,
                'tax_service'    => $taxService,
                'created_by'     => auth()->id(),
                'is_diterima'    => true, // Otomatis diterima pada tanggal transaksi
                'diterima_at'    => $txDateTime,
                'diterima_oleh'  => auth()->id(),
                'is_lunas'       => false,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('pembelian', 'keterangan')) {
                $createData['keterangan'] = $data['keterangan'] ?? null;
            }

            $pembelian = Pembelian::create($createData);

            // Buat header penerimaan_pembelian otomatis pada tanggal transaksi
            $supplier = Supplier::find($data['supplier_id']);
            $prefix = strtoupper($supplier->prefix ?? 'SUP');
            $noPenerimaan = 'RCV-' . $prefix . '-' . date('Ymd', strtotime($txDate)) . '-' . rand(100, 999);
            while (DB::table('penerimaan_pembelian')->where('no_penerimaan', $noPenerimaan)->exists()) {
                $noPenerimaan = 'RCV-' . $prefix . '-' . date('Ymd', strtotime($txDate)) . '-' . rand(100, 999);
            }

            $penerimaan = \App\Models\PenerimaanPembelian::create([
                'pembelian_id'  => $pembelian->id,
                'no_penerimaan' => $noPenerimaan,
                'tanggal'       => $txDate,
                'created_by'    => auth()->id(),
                'created_at'    => $txDateTime,
                'updated_at'    => $txDateTime,
            ]);

            foreach ($data['items'] as $item) {

                $qtyInput = (float) $item['qty'];
                $hargaTotalItem = (float) $item['harga'];
                $hargaPerQty = $qtyInput > 0 ? $hargaTotalItem / $qtyInput : 0;

                $barang = MasterBarang::withoutGlobalScopes()->find($item['barang_id']);
                $satBeli = $barang ? $barang->satuan_pembelian : null;
                $satDasar = $barang ? ($barang->satuan ?: 'pcs') : 'pcs';
                $konv = (float)($barang->konversi_pembelian ?? 1.0);
                if ($satBeli && strtolower(trim($satBeli)) === strtolower(trim($satDasar))) {
                    $konv = 1.00;
                }
                if ($konv <= 0) $konv = 1.00;

                // Simpan detail pembelian langsung dengan qty_diterima penuh
                $detail = $pembelian->details()->create([
                    'barang_id'          => $item['barang_id'],
                    'satuan_pembelian'   => $satBeli,
                    'konversi_pembelian' => $konv,
                    'qty'                => $qtyInput,
                    'qty_diterima'       => $qtyInput,
                    'harga'              => $hargaTotalItem,
                    'harga_per_qty'      => $hargaPerQty,
                    'batch_number'       => 'TEMP',
                ]);

                $batchNumber = date('Ymd', strtotime($txDate)) . '-PB' . $detail->id;
                $detail->update([
                    'batch_number' => $batchNumber,
                ]);

                // Buat detail penerimaan_pembelian_detail
                $penerimaan->details()->create([
                    'pembelian_detail_id' => $detail->id,
                    'barang_id'           => $detail->barang_id,
                    'qty'                 => $qtyInput,
                    'harga_per_qty'       => $hargaPerQty,
                    'created_at'          => $txDateTime,
                    'updated_at'          => $txDateTime,
                ]);

                $qtyMasukStok = $qtyInput * $konv;
                $hargaPerQtyStok = $konv > 0 ? ($hargaPerQty / $konv) : $hargaPerQty;
                $targetDivisiId = MasterGudang::resolveDivisiIdForBarang($pembelian->gudang_id, $detail->barang_id);

                // Buat batch persediaan FIFO dengan tanggal persis tanggal transaksi pembelian
                \App\Models\StokGudangBatch::create([
                    'gudang_id'           => $pembelian->gudang_id,
                    'divisi_id'           => $targetDivisiId,
                    'supplier_id'         => $pembelian->supplier_id,
                    'barang_id'           => $detail->barang_id,
                    'pembelian_id'        => $pembelian->id,
                    'pembelian_detail_id' => $detail->id,
                    'batch_number'        => $batchNumber . '-RCV-' . rand(10, 99) . '-' . date('His'),
                    'qty_masuk'           => $qtyMasukStok,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyMasukStok,
                    'harga_per_qty'       => $hargaPerQtyStok,
                    'is_habis'            => false,
                    'created_at'          => $txDateTime,
                    'updated_at'          => $txDateTime,
                ]);

                // Tambahkan mutasi masuk ke transaksi_stok tepat pada tanggal transaksi pembelian
                $this->stockService->stockIn([
                    'tanggal'          => $txDateTime,
                    'barang_id'        => $detail->barang_id,
                    'gudang_tujuan_id' => $pembelian->gudang_id,
                    'divisi_tujuan_id' => $targetDivisiId,
                    'qty'              => $qtyMasukStok,
                    'total_harga'      => $hargaTotalItem,
                    'source_type'      => 'pembelian',
                    'source_id'        => $pembelian->id,
                    'user_id'          => auth()->id(),
                ]);

                // Sinkronisasi HPP barang sesuai FIFO
                app(\App\Services\FifoService::class)->syncBarangHpp((int) $detail->barang_id);

                // Rekonsiliasi ringkasan stok gudang
                \App\Models\StokGudang::reconcileStockSummary($detail->barang_id, $pembelian->gudang_id, $targetDivisiId);
            }
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Pembelian berhasil disimpan dan penerimaan barang otomatis dicatat tepat pada tanggal transaksi.');
    }

    /*
    |--------------------------------------------------------------------------
    | TERIMA BARANG — stok masuk di sini
    |--------------------------------------------------------------------------
    */

    public function terima(Request $request, Pembelian $pembelian)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';

        if (!$isSuperAdmin && ($user?->isGudang() || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                return back()->with('error', 'Akses ditolak: User Kepala Gudang hanya dapat menerima barang untuk Gudang Utama.');
            }
        }

        if (empty($pembelian->metode_pembayaran)) {
            return back()->with('error', 'Gagal memproses penerimaan barang: Metode pembayaran belum dicatat. Silakan catat metode pembayaran (COD / DP / Termin) terlebih dahulu.');
        }

        $request->validate([
            'qty_diterima'   => 'required|array',
            'qty_diterima.*' => 'required|numeric|min:0',
        ]);

        $pembelian->load('details.barang');

        // Check if there is at least one item with newly received qty > 0
        $anyQty = false;
        foreach ($pembelian->details as $detail) {
            $qtyBaruInput = floatval($request->qty_diterima[$detail->id] ?? 0);
            if ($qtyBaruInput < 0) {
                return back()->with('error', 'Quantity tidak boleh negatif.');
            }
            if ($qtyBaruInput > 0) {
                $anyQty = true;
            }

            // Check if accumulated received + new received exceeds ordered qty
            $accReceived = floatval($detail->qty_diterima ?? 0);
            if ($accReceived + $qtyBaruInput > floatval($detail->qty)) {
                return back()->with('error', "Gagal! Qty yang diterima untuk '" . ($detail->barang->nama ?? 'Barang') . "' melebihi sisa pesanan (Ordered: " . floatval($detail->qty) . ", Received: " . $accReceived . ", Input: " . $qtyBaruInput . ").");
            }
        }

        if (!$anyQty) {
            return back()->with('error', 'Gagal! Tidak ada quantity barang yang diinputkan untuk diterima.');
        }

        DB::transaction(function () use ($request, $pembelian) {
            $txDate = $pembelian->tanggal;
            $txDateTime = $txDate . ' ' . date('H:i:s');

            // Create PenerimaanPembelian header
            $noPenerimaan = 'RCV-' . strtoupper($pembelian->supplier->prefix ?? 'SUP') . '-' . date('Ymd', strtotime($txDate)) . '-' . rand(100, 999);
            // Ensure uniqueness of no_penerimaan
            while (DB::table('penerimaan_pembelian')->where('no_penerimaan', $noPenerimaan)->exists()) {
                $noPenerimaan = 'RCV-' . strtoupper($pembelian->supplier->prefix ?? 'SUP') . '-' . date('Ymd', strtotime($txDate)) . '-' . rand(100, 999);
            }

            $penerimaan = \App\Models\PenerimaanPembelian::create([
                'pembelian_id'  => $pembelian->id,
                'no_penerimaan' => $noPenerimaan,
                'tanggal'       => $txDate,
                'created_by'    => auth()->id(),
                'created_at'    => $txDateTime,
                'updated_at'    => $txDateTime,
            ]);

            foreach ($pembelian->details as $detail) {
                $qtyBaruInput = floatval($request->qty_diterima[$detail->id] ?? 0);
                if ($qtyBaruInput <= 0) {
                    continue;
                }

                // Update accumulated qty_diterima
                $accReceived = floatval($detail->qty_diterima ?? 0);
                $detail->update([
                    'qty_diterima' => $accReceived + $qtyBaruInput
                ]);

                // Create PenerimaanPembelianDetail
                $penerimaan->details()->create([
                    'pembelian_detail_id' => $detail->id,
                    'barang_id'           => $detail->barang_id,
                    'qty'                 => $qtyBaruInput,
                    'harga_per_qty'       => floatval($detail->harga_per_qty),
                    'created_at'          => $txDateTime,
                    'updated_at'          => $txDateTime,
                ]);

                $totalHargaDiterima = round($qtyBaruInput * floatval($detail->harga_per_qty), 2);

                // Create StokGudangBatch
                $suffix = rand(10, 99) . '-' . date('His');
                $satBeli = $detail->satuan_pembelian ?: ($detail->barang->satuan_pembelian ?? null);
                $satDasar = $detail->barang->satuan ?? 'pcs';
                $isSameUnit = ($satBeli && strtolower(trim($satBeli)) === strtolower(trim($satDasar)));

                $detailKonv = !$isSameUnit ? floatval($detail->konversi_pembelian ?? 1) : 1.0;
                $barangKonv = !$isSameUnit ? floatval($detail->barang->konversi_pembelian ?? 1) : 1.0;
                $konversi = $detailKonv > 1 ? $detailKonv : ($barangKonv > 1 ? $barangKonv : 1.0);
                if ($isSameUnit || $konversi <= 0) {
                    $konversi = 1.0;
                }

                if ($detailKonv <= 1 && $konversi > 1) {
                    $detail->update([
                        'konversi_pembelian' => $konversi,
                        'satuan_pembelian'   => $satBeli,
                    ]);
                }

                // Proteksi jika user memasukkan qty dalam satuan dasar (misal 31.520 GR) bukan satuan beli (LOAFT)
                if ($konversi > 1 && $qtyBaruInput >= $konversi && floatval($detail->harga_per_qty) >= 10.0 && (floatval($detail->harga_per_qty) / $konversi) < 1.0) {
                    $konversi = 1.0;
                }

                $qtyMasukStok = $qtyBaruInput * $konversi;
                $hargaPerQtyStok = floatval($detail->harga_per_qty) / $konversi;

                $targetDivisiId = MasterGudang::resolveDivisiIdForBarang($pembelian->gudang_id, $detail->barang_id);

                \App\Models\StokGudangBatch::create([
                    'gudang_id'           => $pembelian->gudang_id,
                    'divisi_id'           => $targetDivisiId,
                    'supplier_id'         => $pembelian->supplier_id,
                    'barang_id'           => $detail->barang_id,
                    'pembelian_id'        => $pembelian->id,
                    'pembelian_detail_id' => $detail->id,
                    'batch_number'        => $detail->batch_number . '-RCV-' . $suffix,
                    'qty_masuk'           => $qtyMasukStok,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyMasukStok,
                    'harga_per_qty'       => $hargaPerQtyStok,
                    'is_habis'            => false,
                    'created_at'          => $txDateTime,
                    'updated_at'          => $txDateTime,
                ]);

                // Tambah stok gudang
                $this->stockService->stockIn([
                    'tanggal'          => $txDateTime,
                    'barang_id'        => $detail->barang_id,
                    'gudang_tujuan_id' => $pembelian->gudang_id,
                    'divisi_tujuan_id' => $targetDivisiId,
                    'qty'              => $qtyMasukStok,
                    'total_harga'      => $totalHargaDiterima,
                    'source_type'      => 'pembelian',
                    'source_id'        => $pembelian->id,
                    'user_id'          => auth()->id(),
                ]);

                // Sinkronisasi HPP barang sesuai FIFO
                app(\App\Services\FifoService::class)->syncBarangHpp((int) $detail->barang_id);

                // Rekonsiliasi ringkasan stok gudang
                \App\Models\StokGudang::reconcileStockSummary($detail->barang_id, $pembelian->gudang_id, $targetDivisiId);
            }

            // Check if all items are fully received
            $allFullyReceived = true;
            foreach ($pembelian->details()->get() as $det) {
                if (floatval($det->qty_diterima) < floatval($det->qty)) {
                    $allFullyReceived = false;
                    break;
                }
            }

            // Update status pembelian. Jika sudah semua diterima, is_diterima = true
            $pembelian->update([
                'is_diterima'   => $allFullyReceived,
                'diterima_at'   => $txDateTime,
                'diterima_oleh' => auth()->id()
            ]);

            // Auto-post Jurnal Persediaan (reklas_lunas, termin, atau cod)
            $tahapJurnal = 'cod';
            if ($pembelian->metode_pembayaran === 'dp') {
                $tahapJurnal = 'reklas_lunas';
            } elseif ($pembelian->metode_pembayaran === 'termin') {
                $tahapJurnal = 'termin';
            }
            \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, $tahapJurnal, $penerimaan->id);
        });

        return back()->with('success', 'Barang berhasil diterima secara parsial dan stok sudah diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | LUNASI — untuk DP & Termin
    |--------------------------------------------------------------------------
    */

    public function lunasi(Request $request, Pembelian $pembelian)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk menginput atau melunasi pembayaran pembelian.');
        }

        if ($pembelian->is_lunas) {
            return back()->with('error', 'Pembelian ini sudah lunas.');
        }

        if (!in_array($pembelian->metode_pembayaran, ['dp', 'termin'])) {
            return back()->with('error', 'Hanya pembelian DP atau Termin yang perlu dilunasi.');
        }

        $request->validate([
            'nominal_pelunasan' => 'required|numeric|min:1',
            'catatan_pelunasan' => 'nullable|string|max:500',
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

        DB::transaction(function() use ($request, $pembelian, $buktiFiles) {
            $pembelian->update([
                'is_lunas'          => true,
                'lunas_at'          => now(),
                'nominal_pelunasan' => $request->nominal_pelunasan,
                'catatan_pelunasan' => $request->catatan_pelunasan,
            ]);

            \App\Models\Pembayaran::create([
                'pembelian_id' => $pembelian->id,
                'kategori_pembayaran' => 'pembelian',
                'tanggal_bayar' => now(),
                'jumlah_bayar' => floatval($request->nominal_pelunasan),
                'metode_pembayaran' => 'Pelunasan',
                'catatan' => $request->catatan_pelunasan,
                'bukti_pembayaran' => $buktiFiles,
                'created_by' => auth()->id()
            ]);

            // Auto-post Jurnal Pelunasan
            \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, 'pelunasan');
        });

        return back()->with('success', 'Pembayaran lunas berhasil dicatat.');
    }

    /*
    |--------------------------------------------------------------------------
    | CATAT PEMBAYARAN
    |--------------------------------------------------------------------------
    */

    public function catatPembayaran(Request $request, Pembelian $pembelian)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';

        if (!$user || (!$isSuperAdmin && !$user->isGudang())) {
            return back()->with('error', 'Hanya Super Admin atau pengguna Gudang yang diizinkan untuk mencatat pembayaran pembelian.');
        }

        if (!$isSuperAdmin && ($user->isGudang() || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                return back()->with('error', 'Akses ditolak: User Kepala Gudang hanya dapat mencatat pembayaran pembelian Gudang Utama.');
            }
        }

        $validated = $request->validate([
            'metode_pembayaran'   => 'required|in:cod,dp,termin',
            'tanggal_jatuh_tempo' => 'nullable|date',
            'persen_dp'           => 'nullable|integer|min:1|max:99',
            'nominal_dp'          => 'nullable|numeric|min:0',
            'tanggal_pelunasan'   => 'required_if:metode_pembayaran,dp|nullable|date',
            'catatan_pembayaran'  => 'nullable|string|max:500',
            'bukti_file'          => 'nullable|array',
            'bukti_file.*'        => 'file|image|max:2048'
        ], [
            'tanggal_pelunasan.required_if' => 'Tanggal pelunasan wajib diisi untuk metode DP.'
        ]);

        if ($validated['metode_pembayaran'] === 'dp') {
            if (empty($validated['persen_dp']) && empty($validated['nominal_dp'])) {
                return back()->withErrors(['persen_dp' => 'Persentase DP atau Nominal DP wajib diisi.'])->withInput();
            }

            $total = (float) $pembelian->total;
            if (!empty($validated['persen_dp']) && empty($validated['nominal_dp'])) {
                $validated['nominal_dp'] = round($total * $validated['persen_dp'] / 100, 2);
            } elseif (!empty($validated['nominal_dp']) && empty($validated['persen_dp'])) {
                $validated['persen_dp'] = (int) round(($validated['nominal_dp'] / $total) * 100);
            }
        }

        // COD langsung lunas
        $isLunas = $validated['metode_pembayaran'] === 'cod';

        $buktiFiles = [];
        if ($request->hasFile('bukti_file')) {
            foreach ($request->file('bukti_file') as $file) {
                $path = $file->store('pembayaran_bukti', 'public');
                $buktiFiles[] = $path;
            }
        }

        DB::transaction(function () use ($validated, $pembelian, $isLunas, $buktiFiles) {
            $pembelianData = collect($validated)->except(['bukti_file'])->toArray();

            $pembelian->update([
                ...$pembelianData,
                'dicatat_oleh' => auth()->id(),
                'dicatat_pada' => now(),
                'is_lunas'     => $isLunas,
                'lunas_at'     => $isLunas ? now() : null,
            ]);

            $jumlahBayar = $isLunas ? floatval($pembelian->total) : floatval($validated['nominal_dp']);

            \App\Models\Pembayaran::create([
                'pembelian_id' => $pembelian->id,
                'kategori_pembayaran' => 'pembelian',
                'tanggal_bayar' => now(),
                'jumlah_bayar' => $jumlahBayar,
                'metode_pembayaran' => $isLunas ? 'COD' : 'DP',
                'catatan' => $validated['catatan_pembayaran'] ?? null,
                'bukti_pembayaran' => $buktiFiles,
                'created_by' => auth()->id()
            ]);

            // Auto-post Jurnal Pembelian sesuai metode pembayaran
            $penerimaan = \App\Models\PenerimaanPembelian::where('pembelian_id', $pembelian->id)->first();
            $penerimaanId = $penerimaan?->id;

            if ($validated['metode_pembayaran'] === 'dp') {
                \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, 'dp');
                if ($pembelian->is_diterima) {
                    \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, 'reklas_lunas', $penerimaanId);
                }
            } elseif ($validated['metode_pembayaran'] === 'cod') {
                if ($pembelian->is_diterima) {
                    \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, 'cod', $penerimaanId);
                }
            } elseif ($validated['metode_pembayaran'] === 'termin') {
                if ($pembelian->is_diterima) {
                    \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, 'termin', $penerimaanId);
                }
            }
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Informasi pembayaran berhasil disimpan.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(Pembelian $pembelian)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';
        if (!$isSuperAdmin && ($user?->isGudang() || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                abort(403, 'Akses ditolak: User Kepala Gudang hanya dapat melihat transaksi pembelian Gudang Utama.');
            }
        }

        $pembelian->load(['supplier', 'gudang', 'details.barang', 'user', 'pembayaran']);
        return view('pembelian.show', compact('pembelian'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Pembelian $pembelian)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';
        $isGudang = $user && $user->isGudang();

        if (!$isSuperAdmin && ($isGudang || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                return redirect()->route('pembelian.index')
                    ->with('error', 'Akses ditolak: User Kepala Gudang hanya dapat mengedit transaksi pembelian Gudang Utama.');
            }
        }

        if ($pembelian->isTerkunci() && !$isSuperAdmin) {
            return redirect()->route('pembelian.index')
                ->with('error', 'Pembelian yang sudah dibayar atau diterima barangnya hanya dapat diubah oleh Super Admin.');
        }

        if (!$pembelian->isTerkunci() && !$isSuperAdmin && !$isGudang) {
            return redirect()->route('pembelian.index')
                ->with('error', 'Hanya Super Admin atau User Gudang yang diizinkan mengedit transaksi pembelian.');
        }

        $pembelian->load('details');
        $suppliers = Supplier::orderBy('nama')->get();
        if (!$isSuperAdmin && ($isGudang || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            $gudangs = MasterGudang::where('id', $gudangUtamaId)->get();
            if ($gudangs->isEmpty()) {
                $gudangs = MasterGudang::where('nama', 'Gudang Utama')->get();
            }
        } else {
            $gudangs = MasterGudang::orderBy('nama')->get();
        }
        $barangs   = MasterBarang::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_bahan_baku', true)
                  ->orWhere('is_bahan_setengah_jadi', true)
                  ->orWhere('is_operational', true)
                  ->orWhere('is_direct_consumption', true);
            })
            ->orderBy('nama')
            ->get();

        return view('pembelian.edit', compact('pembelian', 'suppliers', 'gudangs', 'barangs'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(StorePembelianRequest $request, Pembelian $pembelian)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';
        $isGudang = $user && $user->isGudang();

        if (!$isSuperAdmin && ($isGudang || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                return redirect()->route('pembelian.index')
                    ->with('error', 'Akses ditolak: User Kepala Gudang hanya dapat mengedit transaksi pembelian Gudang Utama.');
            }
        }

        if ($pembelian->isTerkunci() && !$isSuperAdmin) {
            return redirect()->route('pembelian.index')
                ->with('error', 'Pembelian yang sudah dibayar atau diterima barangnya hanya dapat diubah oleh Super Admin.');
        }

        if (!$pembelian->isTerkunci() && !$isSuperAdmin && !$isGudang) {
            return redirect()->route('pembelian.index')
                ->with('error', 'Hanya Super Admin atau User Gudang yang diizinkan mengedit transaksi pembelian.');
        }

        $data = $request->validated();

        try {
        DB::transaction(function () use ($data, $pembelian, $request) {

            $pembelian->load('details');
            $existingDetails = $pembelian->details->keyBy('barang_id');
            $submittedBarangIds = collect($data['items'])->pluck('barang_id')->map(fn($id) => (int)$id)->toArray();

            // 1. Hapus item detail yang tidak ada lagi di data input
            foreach ($existingDetails as $oldBarangId => $oldDet) {
                if (!in_array((int)$oldBarangId, $submittedBarangIds)) {
                    // Hapus batch terkait di stok_gudang_batch jika ada
                    $batches = \App\Models\StokGudangBatch::where('pembelian_detail_id', $oldDet->id)->get();
                    foreach ($batches as $batch) {
                        if ($batch->qty_masuk > 0) {
                            $stokGudang = \App\Models\StokGudang::where('barang_id', $batch->barang_id)
                                ->where('gudang_id', $batch->gudang_id)
                                ->when($batch->divisi_id, fn($q) => $q->where('divisi_id', $batch->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                ->lockForUpdate()
                                ->first();
                            if ($stokGudang) {
                                $stokGudang->decrement('jumlah', (float) $batch->qty_masuk);
                            }
                        }
                        $batch->delete();
                    }

                    // Hapus detail penerimaan terkait jika ada
                    \App\Models\PenerimaanPembelianDetail::where('pembelian_detail_id', $oldDet->id)->delete();

                    // Hapus detail pembelian
                    $oldDet->delete();

                    // Sinkronisasi HPP FIFO untuk barang yang dihapus dari detail
                    app(\App\Services\FifoService::class)->syncBarangHpp((int) $oldBarangId);
                }
            }

            $taxService = (float) ($data['tax_service'] ?? 0);

            $total = collect($data['items'])->sum(fn($item) => (float) $item['harga']) + $taxService;

            $updateData = [
                'supplier_id' => $data['supplier_id'],
                'gudang_id'   => $data['gudang_id'],
                'tanggal'     => $data['tanggal'],
                'total'       => $total,
                'tax_service' => $taxService,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('pembelian', 'keterangan')) {
                $updateData['keterangan'] = $data['keterangan'] ?? null;
            }

            $pembelian->update($updateData);

            // 2. Insert / Update detail barang
            foreach ($data['items'] as $item) {
                $barangId = (int) $item['barang_id'];
                $qtyInput = (float) $item['qty'];
                $hargaInput = (float) $item['harga'];
                $hargaPerQty = $qtyInput > 0 ? $hargaInput / $qtyInput : 0;

                $barang = \App\Models\MasterBarang::withoutGlobalScopes()->find($barangId);
                $oldDet = $existingDetails->get($barangId);
                $satBeli = $oldDet ? ($oldDet->satuan_pembelian ?: ($barang->satuan_pembelian ?? null)) : ($barang->satuan_pembelian ?? null);
                $satDasar = $barang ? ($barang->satuan ?: 'pcs') : 'pcs';
                $isSameUnit = ($satBeli && strtolower(trim($satBeli)) === strtolower(trim($satDasar)));
                $konversi = !$isSameUnit ? (float) ($barang->konversi_pembelian ?? 1.0) : 1.0;
                if ($konversi <= 0) $konversi = 1.0;

                if ($oldDet) {
                    // Update detail yang sudah ada tanpa menghapus ID-nya
                    $qtyOld = (float) $oldDet->qty;
                    $diffQty = $qtyInput - $qtyOld;

                    $newQtyDiterima = $oldDet->qty_diterima;
                    if ($pembelian->is_diterima || $oldDet->qty_diterima >= $qtyOld) {
                        $newQtyDiterima = $qtyInput;
                    }

                    $oldDet->update([
                        'satuan_pembelian'   => $satBeli,
                        'konversi_pembelian' => $konversi,
                        'qty'                => $qtyInput,
                        'qty_diterima'       => $newQtyDiterima,
                        'harga'              => $hargaInput,
                        'harga_per_qty'      => $hargaPerQty,
                    ]);

                    // Update stok_gudang_batch & stok_gudang jika batch sudah dibuat
                    $batch = \App\Models\StokGudangBatch::where('pembelian_detail_id', $oldDet->id)->first();
                    if ($batch) {
                        if ($konversi > 1 && $qtyInput >= $konversi && floatval($hargaPerQty) >= 10.0 && (floatval($hargaPerQty) / $konversi) < 1.0) {
                            $konversi = 1.0;
                        }
                        $diffQtyKonv = $diffQty * $konversi;
                        if ($diffQty != 0) {
                            $batch->qty_masuk = max(0, (float)$batch->qty_masuk + $diffQtyKonv);
                            $batch->qty_sisa  = max(0, (float)$batch->qty_sisa + $diffQtyKonv);
                            $batch->is_habis  = ($batch->qty_sisa <= 0);
                        }
                        $batch->harga_per_qty = $hargaPerQty / $konversi;
                        $batch->save();

                        if ($diffQty != 0) {
                            $stokGudang = \App\Models\StokGudang::where('barang_id', $barangId)
                                ->where('gudang_id', $pembelian->gudang_id)
                                ->when($batch->divisi_id, fn($q) => $q->where('divisi_id', $batch->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                ->lockForUpdate()
                                ->first();

                            if ($stokGudang) {
                                if ($diffQtyKonv > 0) {
                                    $stokGudang->increment('jumlah', $diffQtyKonv);
                                } else {
                                    $stokGudang->decrement('jumlah', abs($diffQtyKonv));
                                }
                            }
                        }
                    }
                } else {
                    // Item baru ditambahkan
                    $detail = $pembelian->details()->create([
                        'barang_id'          => $barangId,
                        'satuan_pembelian'   => $barang->satuan_pembelian ?? $barang->satuan,
                        'konversi_pembelian' => $konversi,
                        'qty'                => $qtyInput,
                        'qty_diterima'       => $pembelian->is_diterima ? $qtyInput : 0,
                        'harga'              => $hargaInput,
                        'harga_per_qty'      => $hargaPerQty,
                        'batch_number'       => 'TEMP',
                        'metode_pembayaran'  => $pembelian->metode_pembayaran,
                        'is_lunas'           => $pembelian->is_lunas,
                        'lunas_at'           => $pembelian->lunas_at,
                    ]);

                    $batchNo = date('Ymd') . '-PB' . $detail->id;
                    $detail->update(['batch_number' => $batchNo]);

                    if ($pembelian->is_diterima) {
                        if ($konversi > 1 && $qtyInput >= $konversi && floatval($hargaPerQty) >= 10.0 && (floatval($hargaPerQty) / $konversi) < 1.0) {
                            $konversi = 1.0;
                        }
                        $qtyMasukStok = $qtyInput * $konversi;
                        $hargaPerQtyStok = $hargaPerQty / $konversi;

                        $targetDivisiId = MasterGudang::resolveDivisiIdForBarang($pembelian->gudang_id, $barangId);

                        \App\Models\StokGudangBatch::create([
                            'gudang_id'           => $pembelian->gudang_id,
                            'divisi_id'           => $targetDivisiId,
                            'supplier_id'         => $pembelian->supplier_id,
                            'barang_id'           => $barangId,
                            'pembelian_id'        => $pembelian->id,
                            'pembelian_detail_id' => $detail->id,
                            'batch_number'        => $batchNo,
                            'harga_per_qty'       => $hargaPerQtyStok,
                            'qty_masuk'           => $qtyMasukStok,
                            'qty_keluar'          => 0,
                            'qty_sisa'            => $qtyMasukStok,
                            'is_habis'            => false,
                        ]);

                        $stokGudang = \App\Models\StokGudang::firstOrCreate(
                            ['gudang_id' => $pembelian->gudang_id, 'barang_id' => $barangId, 'divisi_id' => $targetDivisiId],
                            ['jumlah' => 0]
                        );
                        $stokGudang->increment('jumlah', $qtyMasukStok);
                    }
                }
            }

            // Sinkronisasi HPP FIFO untuk semua barang dalam pembelian
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($submittedBarangIds as $bId) {
                $fifoService->syncBarangHpp((int) $bId);
            }
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Pembelian berhasil diperbarui.');
    } catch (\Exception $e) {
        return redirect()
            ->route('pembelian.index')
            ->with('error', 'Gagal memperbarui pembelian: ' . $e->getMessage());
    }
}

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(Pembelian $pembelian)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';
        $isGudang = $user && $user->isGudang();

        if (!$isSuperAdmin && ($isGudang || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                return back()->with('error', 'Akses ditolak: User Kepala Gudang hanya dapat menghapus transaksi pembelian Gudang Utama.');
            }
        }

        // Jika terkunci (sudah diterima/lunas) dan bukan Super Admin, tolak
        if ($pembelian->isTerkunci() && !$isSuperAdmin) {
            return back()->with('error', 'Pembelian yang sudah diterima atau lunas hanya dapat dihapus / di-rollback oleh Super Admin.');
        }

        // Jika belum terkunci tapi user bukan SuperAdmin dan bukan Gudang, tolak
        if (!$pembelian->isTerkunci() && !$isSuperAdmin && !$isGudang) {
            return back()->with('error', 'Hanya Super Admin atau pengguna Gudang yang dapat menghapus transaksi pembelian.');
        }

        DB::transaction(function () use ($pembelian, $isSuperAdmin) {
            $pembelian->load(['details.barang', 'gudang']);

            $penerimaanList = \App\Models\PenerimaanPembelian::where('pembelian_id', $pembelian->id)->get();
            $batchList = \App\Models\StokGudangBatch::where('pembelian_id', $pembelian->id)->get();

            // Simpan daftar barang_id yang terpengaruh untuk sinkronisasi HPP FIFO setelah rollback
            $affectedBarangIds = $pembelian->details->pluck('barang_id')
                ->merge($batchList->pluck('barang_id'))
                ->filter()
                ->unique()
                ->map(fn($id) => (int)$id)
                ->toArray();

            // 1. Rollback Stok Gudang & Stok Batch jika barang sudah pernah diterima
            // Kembalikan stok gudang berdasarkan batch penerimaan
            foreach ($batchList as $batch) {
                if ($batch->qty_masuk > 0) {
                    $stokGudang = \App\Models\StokGudang::where('barang_id', $batch->barang_id)
                        ->where('gudang_id', $batch->gudang_id)
                        ->when($batch->divisi_id, fn($q) => $q->where('divisi_id', $batch->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                        ->lockForUpdate()
                        ->first();

                    if ($stokGudang) {
                        // Kurangi stok sebesar qty_masuk yang pernah di-inbox dari pembelian ini
                        $stokGudang->decrement('jumlah', (float) $batch->qty_masuk);
                    }
                }
            }

            // Hapus mutasi TransaksiStok terkait pembelian ini secara permanen
            \App\Models\TransaksiStok::where('source_id', $pembelian->id)
                ->whereIn('source_type', ['pembelian', 'pembelian_batal'])
                ->delete();

            // Hapus batch
            \App\Models\StokGudangBatch::where('pembelian_id', $pembelian->id)->delete();

            // Hapus riwayat penerimaan pembelian
            foreach ($penerimaanList as $penerimaan) {
                $penerimaan->details()->delete();
                $penerimaan->delete();
            }

            // 2. Hapus Jurnal Akuntansi yang terkait dengan pembelian ini
            $jurnalPembelianList = DB::table('jurnal_pembelian')
                ->where(function($q) use ($pembelian, $penerimaanList) {
                    $q->where(function($sub) use ($pembelian) {
                        $sub->where('source_type', 'pembelian')->where('source_id', $pembelian->id);
                    })->orWhere(function($sub) use ($penerimaanList) {
                        $sub->where('source_type', 'penerimaan_pembelian')->whereIn('source_id', $penerimaanList->pluck('id'));
                    });
                })
                ->get();

            foreach ($jurnalPembelianList as $jp) {
                DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_pembelian')->delete();
                DB::table('jurnal_pembelian')->where('id', $jp->id)->delete();
            }

            // 3. Hapus Pembayaran
            \App\Models\Pembayaran::where('pembelian_id', $pembelian->id)->delete();

            // 4. Tandai status pembelian sebagai Dihapus / Dibatalkan agar tetap tercatat sebagai riwayat
            // Menggunakan catatan_pembayaran dengan format [DELETED] sehingga bekerja tanpa perlu migrasi DB
            $deletedNote = '[DELETED] Dihapus pada ' . now()->format('d/m/Y H:i') . ' oleh ' . (auth()->user()->nama ?? auth()->user()->name ?? 'Pengguna');
            $updateData = [
                'catatan_pembayaran' => $deletedNote,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('pembelian', 'is_deleted')) {
                $updateData['is_deleted']   = true;
                $updateData['deleted_at']   = now();
                $updateData['deleted_by']   = auth()->id();
                $updateData['alasan_batal'] = 'Dihapus oleh ' . (auth()->user()->nama ?? 'Pengguna');
            }

            $pembelian->update($updateData);

            // 5. Rekonsiliasi stok & SINKRONISASI HPP BARANG SESUAI FIFO SETELAH PEMBELIAN DIHAPUS
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($affectedBarangIds as $barangId) {
                \App\Models\StokGudang::reconcileStockSummary($barangId);
                $fifoService->syncBarangHpp($barangId);
            }
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Transaksi pembelian ' . $pembelian->kode_pembelian . ' berhasil dihapus dan seluruh stok/jurnal terkait telah di-rollback secara otomatis.');
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE KODE
    |--------------------------------------------------------------------------
    */

    private function generateKodePembelian(string $tanggal): string
    {
        $prefix = 'PB' . Carbon::parse($tanggal)->format('Ymd');

        $last = Pembelian::where('kode_pembelian', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $number = $last ? ((int) substr($last->kode_pembelian, -4)) + 1 : 1;

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function cetakPoPdf($id)
    {
        $pembelian = Pembelian::with(['supplier', 'gudang', 'user', 'details.barang'])->findOrFail($id);

        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $roleName = $user?->role?->nama ?? '';

        if (!$isSuperAdmin && ($user?->isGudang() || $roleName === 'Kepala Gudang')) {
            $gudangUtamaId = MasterGudang::getGudangUtamaId();
            if ($pembelian->gudang_id != $gudangUtamaId) {
                abort(403, 'Akses ditolak: User Kepala Gudang hanya dapat mencetak PO pembelian Gudang Utama.');
            }
        }

        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
        $pdf->loadView('pembelian.po-pdf', compact('pembelian'));
        return $pdf->stream('Purchase-Order-' . $pembelian->kode_pembelian . '.pdf');
    }

    /**
     * Koreksi dan sinkronisasi seluruh data historis transaksi pembelian:
     * - Menyamakan tanggal penerimaan barang ke tanggal transaksi pembelian
     * - Menyamakan timestamp batch FIFO dan transaksi_stok ke tanggal transaksi pembelian
     * - Memastikan seluruh pembelian aktif berstatus diterima penuh
     * - Menghitung ulang mutasi stok dan buku pembantu persediaan agar saldo berjalan kembali akurat.
     */
    public static function autoHealHistoricalPembelianPenerimaanTanggal($targetBarangId = null)
    {
        try {
            $hasIsDeletedCol = Schema::hasColumn('pembelian', 'is_deleted');

            $pembelianQuery = Pembelian::withoutGlobalScopes()
                ->where(function ($q) use ($hasIsDeletedCol) {
                    if ($hasIsDeletedCol) {
                        $q->where('is_deleted', false)->orWhereNull('is_deleted');
                    }
                })
                ->where(function ($q) {
                    $q->whereNull('catatan_pembayaran')
                      ->orWhere(function ($sub) {
                          $sub->where('catatan_pembayaran', 'not like', '[DELETED]%')
                              ->where('catatan_pembayaran', 'not like', '[BATAL]%');
                      });
                })
                ->where(function ($q) {
                    $q->whereNull('keterangan')
                      ->orWhere('keterangan', 'not like', '[BATAL]%');
                })
                ->with(['details.barang', 'supplier']);

            if ($targetBarangId) {
                $pembelianQuery->whereHas('details', function ($q) use ($targetBarangId) {
                    $q->where('barang_id', $targetBarangId);
                });
            }

            $pembelians = $pembelianQuery->get();
            $affectedItems = [];

            foreach ($pembelians as $pb) {
                $txDate = date('Y-m-d', strtotime($pb->tanggal));
                $txTime = '08:00:00';
                if ($pb->created_at) {
                    $createdTime = date('H:i:s', strtotime($pb->created_at));
                    if ($createdTime && $createdTime !== '00:00:00') {
                        $txTime = $createdTime;
                    }
                }
                $txDateTime = $txDate . ' ' . $txTime;

                // 1. Pastikan pembelian ditandai diterima dan tanggal terima selaras dengan tanggal pembelian
                $needsPbUpdate = false;
                $pbUpdate = [];
                if (!$pb->is_diterima) {
                    $pbUpdate['is_diterima'] = true;
                    $needsPbUpdate = true;
                }
                if (!$pb->diterima_at || date('Y-m-d', strtotime($pb->diterima_at)) !== $txDate) {
                    $pbUpdate['diterima_at'] = $txDateTime;
                    $needsPbUpdate = true;
                }
                if (!$pb->diterima_oleh) {
                    $pbUpdate['diterima_oleh'] = $pb->created_by ?: 1;
                    $needsPbUpdate = true;
                }
                if ($needsPbUpdate) {
                    $pb->update($pbUpdate);
                }

                // 2. Pastikan header penerimaan_pembelian ada dan tanggalnya selaras
                $penerimaan = DB::table('penerimaan_pembelian')->where('pembelian_id', $pb->id)->first();
                if ($penerimaan) {
                    $penerimaanDate = date('Y-m-d', strtotime($penerimaan->tanggal));
                    if ($penerimaanDate !== $txDate || date('Y-m-d', strtotime($penerimaan->created_at)) !== $txDate) {
                        DB::table('penerimaan_pembelian')->where('id', $penerimaan->id)->update([
                            'tanggal'    => $txDate,
                            'created_at' => $txDateTime,
                            'updated_at' => $txDateTime,
                        ]);
                    }
                    $penerimaanId = $penerimaan->id;
                } else {
                    $prefix = strtoupper($pb->supplier->prefix ?? 'SUP');
                    $noPenerimaan = 'RCV-' . $prefix . '-' . date('Ymd', strtotime($txDate)) . '-' . rand(100, 999);
                    $penerimaanId = DB::table('penerimaan_pembelian')->insertGetId([
                        'pembelian_id'  => $pb->id,
                        'no_penerimaan' => $noPenerimaan,
                        'tanggal'       => $txDate,
                        'created_by'    => $pb->created_by ?: 1,
                        'created_at'    => $txDateTime,
                        'updated_at'    => $txDateTime,
                    ]);
                }

                // 3. Proses detail pembelian
                $details = $pb->details;
                if ($targetBarangId) {
                    $details = $details->where('barang_id', $targetBarangId);
                }

                foreach ($details as $detail) {
                    $bId = $detail->barang_id;
                    $gId = $pb->gudang_id;
                    $targetDivisiId = MasterGudang::resolveDivisiIdForBarang($gId, $bId);

                    $satBeli = $detail->satuan_pembelian ?: ($detail->barang->satuan_pembelian ?? null);
                    $satDasar = $detail->barang->satuan ?? 'pcs';
                    $isSameUnit = ($satBeli && strtolower(trim($satBeli)) === strtolower(trim($satDasar)));

                    $detailKonv = !$isSameUnit ? floatval($detail->konversi_pembelian ?? 1) : 1.0;
                    $barangKonv = !$isSameUnit ? floatval($detail->barang->konversi_pembelian ?? 1) : 1.0;
                    $konversi = $detailKonv > 1 ? $detailKonv : ($barangKonv > 1 ? $barangKonv : 1.0);
                    if ($isSameUnit || $konversi <= 0) {
                        $konversi = 1.0;
                    }

                    $qtyDetail = floatval($detail->qty);
                    $qtyMasukStok = $qtyDetail * $konversi;
                    $hargaPerQty = floatval($detail->harga_per_qty ?: ($qtyDetail > 0 ? (floatval($detail->harga) / $qtyDetail) : 0));
                    $hargaPerQtyStok = $konversi > 0 ? ($hargaPerQty / $konversi) : $hargaPerQty;
                    $totalHargaDiterima = floatval($detail->harga ?: ($qtyDetail * $hargaPerQty));

                    // Pastikan qty_diterima dan tanggal_diterima pada detail pembelian selaras
                    $detUpdateData = [];
                    if (floatval($detail->qty_diterima) < $qtyDetail) {
                        $detUpdateData['qty_diterima'] = $qtyDetail;
                    }
                    if (Schema::hasColumn('pembelian_detail', 'tanggal_diterima')) {
                        if (!$detail->tanggal_diterima || date('Y-m-d', strtotime($detail->tanggal_diterima)) !== $txDate) {
                            $detUpdateData['tanggal_diterima'] = $txDate;
                        }
                    }
                    if (!empty($detUpdateData)) {
                        $detail->update($detUpdateData);
                    }

                    // Pastikan penerimaan_pembelian_detail ada
                    $rcvDetail = DB::table('penerimaan_pembelian_detail')
                        ->where('penerimaan_pembelian_id', $penerimaanId)
                        ->where('pembelian_detail_id', $detail->id)
                        ->first();
                    if (!$rcvDetail) {
                        DB::table('penerimaan_pembelian_detail')->insert([
                            'penerimaan_pembelian_id' => $penerimaanId,
                            'pembelian_detail_id'     => $detail->id,
                            'barang_id'               => $bId,
                            'qty'                     => $qtyDetail,
                            'harga_per_qty'           => $hargaPerQty,
                            'created_at'              => $txDateTime,
                            'updated_at'              => $txDateTime,
                        ]);
                    }

                    // Pastikan batch FIFO tercipta dan timestamp-nya tepat pada tanggal pembelian
                    $batches = DB::table('stok_gudang_batch')
                        ->where('pembelian_id', $pb->id)
                        ->where('barang_id', $bId)
                        ->get();

                    if ($batches->isEmpty()) {
                        $suffix = rand(10, 99) . '-' . date('His');
                        DB::table('stok_gudang_batch')->insert([
                            'gudang_id'           => $gId,
                            'divisi_id'           => $targetDivisiId,
                            'supplier_id'         => $pb->supplier_id,
                            'barang_id'           => $bId,
                            'pembelian_id'        => $pb->id,
                            'pembelian_detail_id' => $detail->id,
                            'batch_number'        => ($detail->batch_number ?: (date('Ymd', strtotime($txDate)) . '-PB' . $detail->id)) . '-RCV-' . $suffix,
                            'qty_masuk'           => $qtyMasukStok,
                            'qty_keluar'          => 0,
                            'qty_sisa'            => $qtyMasukStok,
                            'harga_per_qty'       => $hargaPerQtyStok,
                            'is_habis'            => false,
                            'created_at'          => $txDateTime,
                            'updated_at'          => $txDateTime,
                        ]);
                    } else {
                        foreach ($batches as $b) {
                            if (date('Y-m-d', strtotime($b->created_at)) !== $txDate) {
                                DB::table('stok_gudang_batch')->where('id', $b->id)->update([
                                    'created_at' => $txDateTime,
                                    'updated_at' => $txDateTime,
                                ]);
                            }
                        }
                    }

                    // Pastikan transaksi_stok tercatat tepat pada tanggal pembelian
                    $stokTxs = DB::table('transaksi_stok')
                        ->where('barang_id', $bId)
                        ->where(function ($q) use ($pb, $penerimaanId) {
                            $q->where(fn($s) => $s->whereIn('source_type', ['pembelian', 'pembelian_kejingga'])->where('source_id', $pb->id))
                              ->orWhere(fn($s) => $s->where('source_type', 'penerimaan_pembelian')->whereIn('source_id', [$pb->id, $penerimaanId]));
                        })
                        ->get();

                    if ($stokTxs->isEmpty()) {
                        DB::table('transaksi_stok')->insert([
                            'tanggal'          => $txDateTime,
                            'tipe'             => 'masuk',
                            'source_type'      => 'pembelian',
                            'source_id'        => $pb->id,
                            'gudang_asal_id'   => null,
                            'divisi_asal_id'   => null,
                            'gudang_tujuan_id' => $gId,
                            'divisi_tujuan_id' => $targetDivisiId,
                            'barang_id'        => $bId,
                            'qty'              => $qtyMasukStok,
                            'total_harga'      => $totalHargaDiterima,
                            'created_by'       => $pb->created_by ?: 1,
                            'created_at'       => $txDateTime,
                            'updated_at'       => $txDateTime,
                        ]);
                    } else {
                        foreach ($stokTxs as $stx) {
                            $needsTxUp = false;
                            $txUp = [];
                            if (date('Y-m-d', strtotime($stx->tanggal)) !== $txDate) {
                                $txUp['tanggal'] = $txDateTime;
                                $txUp['updated_at'] = $txDateTime;
                                $needsTxUp = true;
                            }
                            if ($targetDivisiId && $stx->divisi_tujuan_id != $targetDivisiId) {
                                $txUp['divisi_tujuan_id'] = $targetDivisiId;
                                $needsTxUp = true;
                            }
                            if ($needsTxUp) {
                                DB::table('transaksi_stok')->where('id', $stx->id)->update($txUp);
                            }
                        }
                    }

                    $affectedItems[$bId] = [
                        'barang_id' => $bId,
                        'gudang_id' => $gId,
                        'divisi_id' => $targetDivisiId
                    ];
                }

                // 4. Samakan tanggal jurnal_pembelian (kecuali pelunasan yang memang dicatat di tanggal pelunasan)
                $jurnals = DB::table('jurnal_pembelian')
                    ->where('source_type', 'pembelian')
                    ->where('source_id', $pb->id)
                    ->get();

                foreach ($jurnals as $jp) {
                    if ($jp->tahap !== 'pelunasan' && date('Y-m-d', strtotime($jp->tanggal)) !== $txDate) {
                        DB::table('jurnal_pembelian')->where('id', $jp->id)->update([
                            'tanggal' => $txDate
                        ]);
                    }
                }
            }

            // 5. Rekonsiliasi ringkasan stok & sync FIFO untuk semua barang yang terpengaruh
            foreach ($affectedItems as $aff) {
                StokGudang::reconcileStockSummary($aff['barang_id'], $aff['gudang_id'], $aff['divisi_id']);
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$aff['barang_id']);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealHistoricalPembelianPenerimaanTanggal error: ' . $e->getMessage());
        }
    }
}