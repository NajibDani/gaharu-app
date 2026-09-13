<?php

namespace App\Http\Controllers;

use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Supplier;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;
use App\Services\StockService;
use App\Services\FifoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PembelianKejinggaController extends Controller
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

    private function authorizeAccess()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Anda belum login.');
        }

        $roleName = $user->role->nama ?? '';
        $allowedRoles = ['Super Admin', 'Superadmin', 'Administrator', 'Kepala Outlet Kejingga', 'Operasional Kejingga'];
        if (!$user->isSuperAdmin() && !in_array($roleName, $allowedRoles)) {
            abort(403, 'Akses terbatas. Hanya User Kejingga dan Super Admin yang diizinkan mengelola pembelian Kejingga.');
        }
    }

    /**
     * Pastikan selalu ada valid supplier_id untuk tabel pembelian induk
     * agar terhindar dari constraint NOT NULL di level database MySQL.
     */
    private function resolveSupplierId($supplierId = null)
    {
        if (!empty($supplierId)) {
            return (int) $supplierId;
        }

        try {
            $defaultSupplier = Supplier::firstOrCreate(
                ['nama' => 'Supplier Luar / Umum (KeJingga)'],
                [
                    'kode'      => 'SUP-KJG-GEN',
                    'telepon'   => '-',
                    'alamat'    => 'KeJingga Outlet',
                    'is_active' => true,
                ]
            );
            return $defaultSupplier->id;
        } catch (\Throwable $e) {
            return Supplier::first()?->id ?? 1;
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        $search = $request->query('search');
        $query = Pembelian::with(['supplier', 'gudang', 'user', 'details.barang', 'details.supplier'])
            ->where('gudang_id', 5); // Khusus Gudang Kejingga (ID 5)

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_pembelian', 'like', '%' . $search . '%')
                  ->orWhereHas('supplier', function($sq) use ($search) {
                      $sq->where('nama', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('details.supplier', function($sq) use ($search) {
                      $sq->where('nama', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('details.barang', function($bq) use ($search) {
                      $bq->where('nama', 'like', '%' . $search . '%')
                        ->orWhere('kode_barang', 'like', '%' . $search . '%');
                  });
            });
        }

        $pembelian = $query->orderBy('kode_pembelian', 'desc')->paginate(10)->withQueryString();

        // High efficiency fetch of stok gudang Kejingga (ID 5)
        $stokKejinggaMap = StokGudang::where('gudang_id', 5)
            ->groupBy('barang_id')
            ->select('barang_id', DB::raw('SUM(jumlah) as total_stok'))
            ->pluck('total_stok', 'barang_id');

        $dataPembayaran = $pembelian->mapWithKeys(function ($item) use ($stokKejinggaMap) {
            $total = (float) $item->total;
            
            return [$item->id => [
                'id'                  => $item->id,
                'kode'                => $item->kode_pembelian,
                'supplier_id'         => $item->supplier_id,
                'supplier_nama'       => $item->supplier->nama ?? 'Multi Supplier / Per Item',
                'gudang_id'           => $item->gudang_id,
                'gudang_nama'         => $item->gudang->nama ?? 'Gudang KeJingga',
                'tanggal'             => \Carbon\Carbon::parse($item->tanggal)->format('d M Y'),
                'tanggal_raw'         => \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d'),
                'tax_service'         => (float) ($item->tax_service ?? 0),
                'total'               => $total,
                'is_lunas'            => (bool) $item->is_lunas,
                'is_diterima'         => (bool) $item->is_diterima,
                'is_terkunci'         => (bool) $item->isTerkunci(),
                'user_nama'           => $item->user->nama ?? ($item->user->username ?? 'Staff Operasional'),
                'details'             => $item->details->map(function ($d) use ($stokKejinggaMap) {
                    $bItem = $d->barang;
                    $sPembelian = $d->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                    $konv = floatval($d->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                    $sUtama = $bItem->satuan ?? 'Pcs';
                    $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);
                    $stokTerkini = (float) ($stokKejinggaMap[$d->barang_id] ?? 0);

                    $qtyDetail = (float) $d->qty;
                    $qtyDiterimaDetail = (float) ($d->qty_diterima ?? 0);
                    $hargaDetail = (float) $d->harga;

                    // Kekurangan per detail item
                    $kekuranganDetail = 0;
                    if ($d->metode_pembayaran === 'dp') {
                        $nominalDp = (float) ($d->nominal_dp ?? 0);
                        $kekuranganDetail = max(0, $hargaDetail - $nominalDp);
                    } elseif ($d->metode_pembayaran === 'termin') {
                        $kekuranganDetail = $d->is_lunas ? 0 : $hargaDetail;
                    }

                    $labelMetodeDetail = match($d->metode_pembayaran) {
                        'cod'    => 'COD',
                        'termin' => 'Termin',
                        'dp'     => $d->nominal_dp && $d->nominal_dp > 0 
                                    ? 'DP Rp ' . number_format((float) $d->nominal_dp, 0, ',', '.')
                                    : 'DP ' . $d->persen_dp . '%',
                        default  => '-',
                    };

                    return [
                        'id'                 => $d->id,
                        'barang_id'          => $d->barang_id,
                        'nama'               => $bItem->nama ?? 'Barang',
                        'kode_barang'        => $bItem->kode_barang ?? '',
                        'supplier_id'         => $d->supplier_id,
                        'supplier_nama'       => $d->supplier->nama ?? 'Belum Ditentukan (Draft)',
                        'satuan'             => $sPembelian ?: $sUtama,
                        'satuan_pembelian'   => $sPembelian,
                        'satuan_utama'       => $sUtama,
                        'konversi_pembelian' => $konv,
                        'has_konversi'       => $hasKonv,
                        'stok_kejingga'      => $stokTerkini,
                        'qty'                => $qtyDetail,
                        'qty_diterima'       => $qtyDiterimaDetail,
                        'is_diterima_item'   => ($qtyDiterimaDetail >= $qtyDetail && $qtyDetail > 0),
                        'tanggal_diterima'   => $d->tanggal_diterima ? \Carbon\Carbon::parse($d->tanggal_diterima)->format('d M Y') : null,
                        'tanggal_diterima_raw'=> $d->tanggal_diterima ? \Carbon\Carbon::parse($d->tanggal_diterima)->format('Y-m-d') : null,
                        'harga'              => $hargaDetail,
                        'harga_per_qty'      => (float) $d->harga_per_qty,
                        'metode_pembayaran'   => $d->metode_pembayaran,
                        'label_pembayaran'   => $labelMetodeDetail,
                        'persen_dp'           => $d->persen_dp,
                        'nominal_dp'          => (float) $d->nominal_dp,
                        'kekurangan'          => $kekuranganDetail,
                        'is_lunas'            => (bool) $d->is_lunas,
                        'tanggal_jatuh_tempo' => $d->tanggal_jatuh_tempo ? \Carbon\Carbon::parse($d->tanggal_jatuh_tempo)->format('d M Y') : null,
                        'bukti_pembayaran'     => $d->bukti_pembayaran,
                        'bukti_pembayaran_url' => $d->bukti_pembayaran ? asset('storage/' . $d->bukti_pembayaran) : null,
                    ];
                }),
            ]];
        });

        $suppliers = Supplier::orderBy('nama')->get();
        $barangs   = MasterBarang::where('is_active', true)->orderBy('nama')->get();
        $gudangs   = MasterGudang::all();
        $gudangKejingga = MasterGudang::find(5);

        return view('pembelian-kejingga.index', compact('pembelian', 'dataPembayaran', 'suppliers', 'barangs', 'gudangs', 'gudangKejingga'));
    }

    public function create()
    {
        $this->authorizeAccess();

        $suppliers = Supplier::orderBy('nama')->get();
        
        $stokKejinggaMap = StokGudang::where('gudang_id', 5)
            ->groupBy('barang_id')
            ->select('barang_id', DB::raw('SUM(jumlah) as total_stok'))
            ->pluck('total_stok', 'barang_id');

        $barangs = MasterBarang::where('is_active', true)
            ->with(['minimumStocks'])
            ->orderBy('nama')
            ->get()
            ->map(function ($b) use ($stokKejinggaMap) {
                $b->stok_kejingga = (float) ($stokKejinggaMap[$b->id] ?? 0);
                return $b;
            });

        $gudangKejingga = MasterGudang::find(5);

        return view('pembelian-kejingga.create', compact('suppliers', 'barangs', 'gudangKejingga'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'tanggal'                 => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.barang_id'       => 'required|exists:master_barang,id',
            'items.*.supplier_id'     => 'nullable|exists:suppliers,id',
            'items.*.qty'             => 'required',
            'items.*.harga'           => 'nullable',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku.')->withInput();
        }

        $gudangId = 5; // Gudang Kejingga

        DB::beginTransaction();
        try {
            $taxService = 0;
            if (!empty($request->tax_service)) {
                $taxService = (float) str_replace('.', '', $request->tax_service);
            }

            $prefix = 'PB-KJG-' . date('Ymd', strtotime($request->tanggal)) . '-';
            $last = Pembelian::where('kode_pembelian', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
            $next = 1;
            if ($last) {
                $next = ((int) substr($last->kode_pembelian, strlen($prefix))) + 1;
            }
            $kodePembelian = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);

            $totalItems = 0;
            $parsedItems = [];
            foreach ($request->items as $it) {
                $qtyRaw   = str_replace('.', '', $it['qty']);
                $qtyVal   = (float) str_replace(',', '.', $qtyRaw);

                $hargaVal = 0;
                if (!empty($it['harga'])) {
                    $hargaRaw = str_replace('.', '', $it['harga']);
                    $hargaVal = (float) str_replace(',', '.', $hargaRaw);
                }

                $totalItems += $hargaVal;
                $parsedItems[] = [
                    'barang_id'          => $it['barang_id'],
                    'supplier_id'        => !empty($it['supplier_id']) ? $it['supplier_id'] : null,
                    'satuan_pembelian'   => $it['satuan_pembelian'] ?? null,
                    'konversi_pembelian' => isset($it['konversi_pembelian']) ? (float) $it['konversi_pembelian'] : 1.00,
                    'qty'                => $qtyVal,
                    'harga'              => $hargaVal,
                ];
            }

            $grandTotal = $totalItems + $taxService;

            // Set main supplier_id from first item if available, or fallback to default supplier
            $firstSupplierId = null;
            foreach ($parsedItems as $pit) {
                if ($pit['supplier_id']) {
                    $firstSupplierId = $pit['supplier_id'];
                    break;
                }
            }
            $resolvedSupplierId = $this->resolveSupplierId($firstSupplierId);

            $pembelian = Pembelian::create([
                'kode_pembelian'    => $kodePembelian,
                'supplier_id'       => $resolvedSupplierId,
                'gudang_id'         => $gudangId,
                'tanggal'           => $request->tanggal,
                'total'             => $grandTotal,
                'tax_service'       => $taxService,
                'metode_pembayaran' => null,
                'is_diterima'       => false,
                'is_lunas'          => false,
                'created_by'        => auth()->id() ?? 1,
            ]);

            foreach ($parsedItems as $it) {
                $barang = MasterBarang::withoutGlobalScopes()->find($it['barang_id']);
                $hargaPerQty = $it['qty'] > 0 ? $it['harga'] / $it['qty'] : 0;
                $satuan = $it['satuan_pembelian'] ?: ($barang->satuan_pembelian ?: ($barang->satuan ?: 'pcs'));
                $konversi = $it['konversi_pembelian'] > 0 ? $it['konversi_pembelian'] : ($barang->konversi_pembelian ?? 1.00);

                PembelianDetail::create([
                    'pembelian_id'       => $pembelian->id,
                    'barang_id'          => $it['barang_id'],
                    'supplier_id'        => $it['supplier_id'],
                    'satuan_pembelian'   => $satuan,
                    'konversi_pembelian' => $konversi,
                    'qty'                => $it['qty'],
                    'qty_diterima'       => 0,
                    'harga'              => $it['harga'],
                    'harga_per_qty'      => $hargaPerQty,
                    'batch_number'       => date('Ymd') . '-PBKJG' . rand(100, 999),
                ]);
            }

            DB::commit();

            return redirect()->route('pembelian-kejingga.index')->with('success', "Pembelian Kejingga ({$kodePembelian}) berhasil disimpan.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan pembelian Kejingga: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $this->authorizeAccess();

        $pembelian = Pembelian::with(['supplier', 'gudang', 'user', 'penerimaDiterima', 'details.barang', 'details.supplier'])
            ->where('gudang_id', 5)
            ->findOrFail($id);

        $stokKejinggaMap = StokGudang::where('gudang_id', 5)
            ->groupBy('barang_id')
            ->select('barang_id', DB::raw('SUM(jumlah) as total_stok'))
            ->pluck('total_stok', 'barang_id');

        return view('pembelian-kejingga.show', compact('pembelian', 'stokKejinggaMap'));
    }

    public function edit($id)
    {
        $this->authorizeAccess();

        $pembelian = Pembelian::with(['details.barang', 'details.supplier'])->where('gudang_id', 5)->findOrFail($id);

        if ($pembelian->isTerkunci()) {
            return redirect()->route('pembelian-kejingga.index')
                ->with('error', 'Purchase Order ' . $pembelian->kode_pembelian . ' sudah dikunci (dibayar atau diterima) dan tidak dapat diubah.');
        }

        $suppliers = Supplier::orderBy('nama')->get();

        $stokKejinggaMap = StokGudang::where('gudang_id', 5)
            ->groupBy('barang_id')
            ->select('barang_id', DB::raw('SUM(jumlah) as total_stok'))
            ->pluck('total_stok', 'barang_id');

        $barangs = MasterBarang::where('is_active', true)
            ->with(['minimumStocks'])
            ->orderBy('nama')
            ->get()
            ->map(function ($b) use ($stokKejinggaMap) {
                $b->stok_kejingga = (float) ($stokKejinggaMap[$b->id] ?? 0);
                return $b;
            });

        $gudangKejingga = MasterGudang::find(5);

        return view('pembelian-kejingga.edit', compact('pembelian', 'suppliers', 'barangs', 'gudangKejingga'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAccess();

        $pembelian = Pembelian::where('gudang_id', 5)->findOrFail($id);

        if ($pembelian->isTerkunci()) {
            return redirect()->route('pembelian-kejingga.index')
                ->with('error', 'Purchase Order ' . $pembelian->kode_pembelian . ' sudah dikunci (dibayar atau diterima) dan tidak dapat diubah.');
        }

        $request->validate([
            'tanggal'                 => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.barang_id'       => 'required|exists:master_barang,id',
            'items.*.supplier_id'     => 'nullable|exists:suppliers,id',
            'items.*.qty'             => 'required',
            'items.*.harga'           => 'nullable',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku.')->withInput();
        }

        DB::beginTransaction();
        try {
            $taxService = 0;
            if (!empty($request->tax_service)) {
                $taxService = (float) str_replace('.', '', $request->tax_service);
            }

            $totalItems = 0;
            $parsedItems = [];
            foreach ($request->items as $it) {
                $qtyRaw   = str_replace('.', '', $it['qty']);
                $qtyVal   = (float) str_replace(',', '.', $qtyRaw);

                $hargaVal = 0;
                if (!empty($it['harga'])) {
                    $hargaRaw = str_replace('.', '', $it['harga']);
                    $hargaVal = (float) str_replace(',', '.', $hargaRaw);
                }

                $totalItems += $hargaVal;
                $parsedItems[] = [
                    'barang_id'          => $it['barang_id'],
                    'supplier_id'        => !empty($it['supplier_id']) ? $it['supplier_id'] : null,
                    'satuan_pembelian'   => $it['satuan_pembelian'] ?? null,
                    'konversi_pembelian' => isset($it['konversi_pembelian']) ? (float) $it['konversi_pembelian'] : 1.00,
                    'qty'                => $qtyVal,
                    'harga'              => $hargaVal,
                ];
            }

            $grandTotal = $totalItems + $taxService;

            $firstSupplierId = null;
            foreach ($parsedItems as $pit) {
                if ($pit['supplier_id']) {
                    $firstSupplierId = $pit['supplier_id'];
                    break;
                }
            }
            $resolvedSupplierId = $this->resolveSupplierId($firstSupplierId);

            $pembelian->update([
                'supplier_id' => $resolvedSupplierId,
                'tanggal'     => $request->tanggal,
                'total'       => $grandTotal,
                'tax_service' => $taxService,
            ]);

            // Save existing payment & reception status before re-creating
            $existingDetails = PembelianDetail::where('pembelian_id', $pembelian->id)->get()->keyBy('barang_id');

            PembelianDetail::where('pembelian_id', $pembelian->id)->delete();

            foreach ($parsedItems as $it) {
                $barang = MasterBarang::withoutGlobalScopes()->find($it['barang_id']);
                $hargaPerQty = $it['qty'] > 0 ? $it['harga'] / $it['qty'] : 0;
                $satuan = $it['satuan_pembelian'] ?: ($barang->satuan_pembelian ?: ($barang->satuan ?: 'pcs'));
                $konversi = $it['konversi_pembelian'] > 0 ? $it['konversi_pembelian'] : ($barang->konversi_pembelian ?? 1.00);

                $oldDet = $existingDetails->get($it['barang_id']);

                $detailData = [
                    'pembelian_id'       => $pembelian->id,
                    'barang_id'          => $it['barang_id'],
                    'supplier_id'        => $it['supplier_id'],
                    'satuan_pembelian'   => $satuan,
                    'konversi_pembelian' => $konversi,
                    'qty'                => $it['qty'],
                    'qty_diterima'       => $oldDet ? $oldDet->qty_diterima : 0,
                    'harga'              => $it['harga'],
                    'harga_per_qty'      => $hargaPerQty,
                    'batch_number'       => $oldDet ? $oldDet->batch_number : (date('Ymd') . '-PBKJG' . rand(100, 999)),
                    'metode_pembayaran'   => $oldDet ? $oldDet->metode_pembayaran : null,
                    'persen_dp'           => $oldDet ? $oldDet->persen_dp : null,
                    'nominal_dp'          => $oldDet ? $oldDet->nominal_dp : null,
                    'tanggal_jatuh_tempo' => $oldDet ? $oldDet->tanggal_jatuh_tempo : null,
                    'tanggal_pelunasan'   => $oldDet ? $oldDet->tanggal_pelunasan : null,
                    'catatan_pembayaran'  => $oldDet ? $oldDet->catatan_pembayaran : null,
                    'is_lunas'            => $oldDet ? $oldDet->is_lunas : false,
                    'lunas_at'            => $oldDet ? $oldDet->lunas_at : null,
                ];

                if (Schema::hasColumn('pembelian_detail', 'tanggal_diterima')) {
                    $detailData['tanggal_diterima'] = $oldDet ? $oldDet->tanggal_diterima : null;
                }

                PembelianDetail::create($detailData);
            }

            DB::commit();

            return redirect()->route('pembelian-kejingga.index')->with('success', "Pembelian Kejingga ({$pembelian->kode_pembelian}) berhasil diperbarui.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui pembelian Kejingga: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $this->authorizeAccess();

        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        $pembelian = Pembelian::where('gudang_id', 5)->findOrFail($id);

        if ($pembelian->isReceived() && !$isSuperAdmin) {
            return back()->with('error', 'Pembelian ' . $pembelian->kode_pembelian . ' sudah diterima fisiknya dan hanya dapat dihapus / di-rollback oleh Super Admin.');
        }

        if ($pembelian->isTerkunci() && !$isSuperAdmin) {
            return back()->with('error', 'Pembelian ' . $pembelian->kode_pembelian . ' sudah dikunci (dibayar) dan hanya dapat dihapus oleh Super Admin.');
        }

        DB::transaction(function() use ($pembelian) {
            $pembelian->load(['details.barang']);
            $penerimaanList = \App\Models\PenerimaanPembelian::where('pembelian_id', $pembelian->id)->get();
            $batchList = \App\Models\StokGudangBatch::where('pembelian_id', $pembelian->id)->get();

            $affectedBarangIds = $pembelian->details->pluck('barang_id')
                ->merge($batchList->pluck('barang_id'))
                ->filter()
                ->unique()
                ->map(fn($id) => (int)$id)
                ->toArray();

            // Kembalikan stok gudang jika pernah diterima
            foreach ($batchList as $batch) {
                if ($batch->qty_masuk > 0) {
                    $stokGudang = \App\Models\StokGudang::where('barang_id', $batch->barang_id)
                        ->where('gudang_id', $batch->gudang_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stokGudang) {
                        $stokGudang->decrement('jumlah', (float) $batch->qty_masuk);
                    }

                    \App\Models\TransaksiStok::create([
                        'tanggal'        => now(),
                        'tipe'           => 'keluar',
                        'source_type'    => 'pembelian_batal',
                        'source_id'      => $pembelian->id,
                        'gudang_asal_id' => $batch->gudang_id,
                        'barang_id'      => $batch->barang_id,
                        'qty'            => (float) $batch->qty_masuk,
                        'total_harga'    => (float) ($batch->qty_masuk * $batch->harga_per_qty),
                        'created_by'     => auth()->id() ?? 1,
                    ]);
                }
            }

            \App\Models\StokGudangBatch::where('pembelian_id', $pembelian->id)->delete();

            foreach ($penerimaanList as $penerimaan) {
                $penerimaan->details()->delete();
                $penerimaan->delete();
            }

            \App\Models\PembelianDetail::where('pembelian_id', $pembelian->id)->delete();
            $pembelian->delete();

            // SINKRONISASI HPP BARANG SESUAI FIFO SETELAH PEMBELIAN DIHAPUS
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($affectedBarangIds as $barangId) {
                $fifoService->syncBarangHpp($barangId);
            }
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Purchase Order Kejingga berhasil dihapus dan HPP barang telah disesuaikan kembali sesuai FIFO.');
    }

    // ==========================================
    // PEMBAYARAN PER ITEM DETAIL (SUPER ADMIN ONLY)
    // ==========================================
    public function catatPembayaranDetail(Request $request, $detailId)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk mencatat pembayaran.');
        }

        $detail = PembelianDetail::with('pembelian')->findOrFail($detailId);

        // Check if price and supplier are entered by Purchasing
        if ((float) $detail->harga <= 0 || empty($detail->supplier_id)) {
            return back()->with('error', '⚠️ Pembayaran tidak dapat dicatat! Nama supplier dan harga barang wajib diisi oleh tim Purchasing terlebih dahulu dengan mengedit PO.');
        }

        $validated = $request->validate([
            'metode_pembayaran'   => 'required|in:cod,dp,termin',
            'tanggal_jatuh_tempo' => 'nullable|date',
            'persen_dp'           => 'nullable|integer|min:1|max:99',
            'nominal_dp'          => 'nullable|numeric|min:0',
            'tanggal_pelunasan'   => 'required_if:metode_pembayaran,dp|nullable|date',
            'catatan_pembayaran'  => 'nullable|string|max:500',
            'bukti_pembayaran'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        if ($validated['metode_pembayaran'] === 'dp') {
            if (empty($validated['persen_dp']) && empty($validated['nominal_dp'])) {
                return back()->withErrors(['persen_dp' => 'Persentase DP atau Nominal DP wajib diisi.'])->withInput();
            }

            $total = (float) $detail->harga;
            if (!empty($validated['persen_dp']) && empty($validated['nominal_dp'])) {
                $validated['nominal_dp'] = round($total * $validated['persen_dp'] / 100, 2);
            } elseif (!empty($validated['nominal_dp']) && empty($validated['persen_dp'])) {
                $validated['persen_dp'] = (int) round(($validated['nominal_dp'] / $total) * 100);
            }
        }

        $buktiPath = null;
        if ($request->hasFile('bukti_pembayaran')) {
            $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran_kejingga', 'public');
        }

        DB::transaction(function() use ($validated, $detail, $buktiPath) {
            $isLunas = ($validated['metode_pembayaran'] === 'cod');
            $updateData = [
                'metode_pembayaran'   => $validated['metode_pembayaran'],
                'persen_dp'           => $validated['persen_dp'] ?? null,
                'nominal_dp'          => $validated['nominal_dp'] ?? null,
                'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?? null,
                'tanggal_pelunasan'   => $validated['tanggal_pelunasan'] ?? null,
                'catatan_pembayaran'  => $validated['catatan_pembayaran'] ?? null,
                'is_lunas'            => $isLunas,
                'lunas_at'            => $isLunas ? now() : null,
            ];
            if ($buktiPath) {
                $updateData['bukti_pembayaran'] = $buktiPath;
            }

            $detail->update($updateData);

            // Check if all details in PO are paid
            $pembelian = $detail->pembelian;
            $allPaid = $pembelian->details()->where('is_lunas', false)->count() === 0;
            if ($allPaid) {
                $pembelian->update([
                    'metode_pembayaran' => 'mix',
                    'is_lunas'          => true,
                    'lunas_at'          => now(),
                ]);
            }
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Pembayaran item barang berhasil dicatat.');
    }

    public function lunasiDetail(Request $request, $detailId)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk melunasi pembayaran.');
        }

        $detail = PembelianDetail::with('pembelian')->findOrFail($detailId);

        if ((float) $detail->harga <= 0 || empty($detail->supplier_id)) {
            return back()->with('error', '⚠️ Pelunasan tidak dapat dicatat! Nama supplier dan harga barang wajib diisi oleh tim Purchasing terlebih dahulu dengan mengedit PO.');
        }

        if ($detail->is_lunas) {
            return back()->with('error', 'Item barang ini sudah lunas.');
        }

        $request->validate([
            'bukti_pembayaran' => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        $buktiPath = null;
        if ($request->hasFile('bukti_pembayaran')) {
            $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran_kejingga', 'public');
        }

        DB::transaction(function() use ($detail, $buktiPath) {
            $updateData = [
                'is_lunas' => true,
                'lunas_at' => now(),
            ];
            if ($buktiPath) {
                $updateData['bukti_pembayaran'] = $buktiPath;
            }
            $detail->update($updateData);

            $pembelian = $detail->pembelian;
            $allPaid = $pembelian->details()->where('is_lunas', false)->count() === 0;
            if ($allPaid) {
                $pembelian->update([
                    'is_lunas' => true,
                    'lunas_at' => now(),
                ]);
            }
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Pelunasan item barang berhasil dicatat.');
    }

    public function bayarMassalDetail(Request $request)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk memproses pembayaran/pelunasan.');
        }

        $request->validate([
            'detail_ids'       => 'required|array|min:1',
            'detail_ids.*'     => 'required|integer|exists:pembelian_detail,id',
            'bukti_pembayaran' => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
            'catatan'          => 'nullable|string|max:500',
        ]);

        $details = PembelianDetail::with(['pembelian', 'supplier', 'barang'])
            ->whereIn('id', $request->detail_ids)
            ->get();

        if ($details->isEmpty()) {
            return back()->with('error', 'Tidak ada item barang yang dipilih.');
        }

        // 1. Validasi: Semua item harus memiliki supplier_id yang sama
        $supplierIds = $details->pluck('supplier_id')->filter()->unique();
        if ($supplierIds->count() > 1) {
            return back()->with('error', '⚠️ Gagal! Item yang dipilih harus berasal dari supplier yang sama untuk dapat dibayarkan dalam 1 nota.');
        }

        // 2. Validasi: Pastikan semua item sudah memiliki harga > 0 dan supplier_id terisi
        foreach ($details as $d) {
            if ((float)$d->harga <= 0 || empty($d->supplier_id)) {
                return back()->with('error', "⚠️ Item '{$d->barang->nama}' belum memiliki nama supplier atau harga barang yang valid. Lengkapi terlebih dahulu.");
            }
        }

        // 3. Upload bukti pembayaran / nota jika ada (1 nota untuk semua item terpilih)
        $buktiPath = null;
        if ($request->hasFile('bukti_pembayaran')) {
            $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran_kejingga', 'public');
        }

        DB::transaction(function() use ($details, $buktiPath, $request) {
            $pembelianIds = [];

            foreach ($details as $detail) {
                $updateData = [
                    'is_lunas' => true,
                    'lunas_at' => now(),
                ];

                // Jika metode pembayaran belum diset sebelumnya, set default ke COD/Lunas Langsung
                if (empty($detail->metode_pembayaran)) {
                    $updateData['metode_pembayaran'] = 'cod';
                }

                if ($buktiPath) {
                    $updateData['bukti_pembayaran'] = $buktiPath;
                }

                if (!empty($request->catatan)) {
                    $updateData['catatan_pembayaran'] = $request->catatan;
                }

                $detail->update($updateData);
                $pembelianIds[] = $detail->pembelian_id;
            }

            // Periksa dan update status PO jika seluruh detail di PO tersebut sudah lunas
            $uniquePembelianIds = array_unique($pembelianIds);
            foreach ($uniquePembelianIds as $pId) {
                $po = Pembelian::find($pId);
                if ($po) {
                    $unpaidCount = $po->details()->where('is_lunas', false)->count();
                    if ($unpaidCount === 0) {
                        $po->update([
                            'is_lunas' => true,
                            'lunas_at' => now(),
                        ]);
                    }
                }
            }
        });

        $count = $details->count();
        $supplierName = $details->first()->supplier->nama ?? 'Supplier';
        return redirect()->route('pembelian-kejingga.index')->with('success', "Berhasil melunasi {$count} item barang dari {$supplierName} dalam 1 nota pembayaran.");
    }

    public function inputBarangTerpilih(Request $request, $id)
    {
        $this->authorizeAccess();

        $pembelian = Pembelian::where('gudang_id', 5)->findOrFail($id);

        $request->validate([
            'detail_ids'          => 'required|array|min:1',
            'detail_ids.*'        => 'required|integer|exists:pembelian_detail,id',
            'supplier_id'         => 'required|exists:suppliers,id',
            'tanggal_diterima'    => 'nullable|date',
            'items'               => 'required|array',
            'items.*.harga'       => 'nullable',
            'items.*.qty'         => 'nullable',
            'tax_service'         => 'nullable',
            'nomor_nota'          => 'nullable|string|max:100',
            'metode_pembayaran'   => 'nullable|in:cod,termin,dp',
            'is_lunas'            => 'nullable',
            'bukti_pembayaran'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        $buktiPath = null;
        if ($request->hasFile('bukti_pembayaran')) {
            $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran_kejingga', 'public');
        }

        $taxService = 0;
        if ($request->filled('tax_service')) {
            $taxService = (float) str_replace(['.', ','], ['', '.'], (string) $request->tax_service);
        }

        DB::transaction(function () use ($request, $pembelian, $buktiPath, $taxService) {
            $isLunas = $request->has('is_lunas') && ($request->is_lunas == '1' || $request->is_lunas == 'true' || $request->is_lunas === true);
            $metode = $request->metode_pembayaran ?: ($isLunas ? 'cod' : 'termin');
            $nomorNota = $request->nomor_nota;
            $tanggalDiterima = $request->filled('tanggal_diterima') ? $request->tanggal_diterima : null;

            $changedOldBarangIds = [];

            foreach ($request->detail_ids as $detailId) {
                $detail = PembelianDetail::where('pembelian_id', $pembelian->id)->find($detailId);
                if (!$detail) continue;

                $itemInput = $request->items[$detailId] ?? [];

                $qty = isset($itemInput['qty']) ? (float) str_replace(['.', ','], ['', '.'], (string) $itemInput['qty']) : (float) $detail->qty;
                if ($qty <= 0) $qty = (float) $detail->qty;

                $rawHarga = isset($itemInput['harga']) ? $itemInput['harga'] : $detail->harga;
                $harga = (float) str_replace(['.', ','], ['', '.'], (string) $rawHarga);
                $hargaPerQty = $qty > 0 ? ($harga / $qty) : 0;

                $updateData = [
                    'supplier_id'         => $request->supplier_id,
                    'qty'                 => $qty,
                    'harga'               => $harga,
                    'harga_per_qty'       => $hargaPerQty,
                    'metode_pembayaran'   => $metode,
                ];

                if ($tanggalDiterima) {
                    $updateData['tanggal_diterima'] = $tanggalDiterima;
                }

                // Penyesuaian fleksibel ganti barang jika barang diubah
                if (!empty($itemInput['barang_id']) && (int) $itemInput['barang_id'] !== (int) $detail->barang_id) {
                    $newBarang = MasterBarang::withoutGlobalScopes()->find($itemInput['barang_id']);
                    if ($newBarang) {
                        $changedOldBarangIds[] = (int) $detail->barang_id;
                        $updateData['barang_id']          = $newBarang->id;
                        $updateData['satuan_pembelian']   = $newBarang->satuan_pembelian ?: ($newBarang->satuan ?: 'pcs');
                        $updateData['konversi_pembelian'] = $newBarang->konversi_pembelian > 0 ? (float) $newBarang->konversi_pembelian : 1.00;
                    }
                }

                if (!empty($nomorNota)) {
                    $updateData['catatan_pembayaran'] = $nomorNota;
                }

                if ($buktiPath) {
                    $updateData['bukti_pembayaran'] = $buktiPath;
                }

                if ($isLunas) {
                    $updateData['is_lunas'] = true;
                    $updateData['lunas_at'] = now();
                }

                $detail->update($updateData);
            }

            // Sinkronisasi HPP jika ada barang yang diganti
            $fifoService = app(\App\Services\FifoService::class);
            foreach ($changedOldBarangIds as $oldBId) {
                $fifoService->syncBarangHpp($oldBId);
            }

            if ($request->filled('tax_service')) {
                $pembelian->tax_service = $taxService;
            }

            $totalDetails = $pembelian->details()->sum('harga');
            $pembelian->total = $totalDetails + (float) ($pembelian->tax_service ?? 0);

            if (empty($pembelian->supplier_id)) {
                $pembelian->supplier_id = $request->supplier_id;
            }

            $unpaidCount = $pembelian->details()->where('is_lunas', false)->count();
            if ($unpaidCount === 0 && $pembelian->details()->count() > 0) {
                $pembelian->is_lunas = true;
                $pembelian->lunas_at = now();
            }

            $pembelian->save();
        });

        $count = count($request->detail_ids);
        return redirect()->route('pembelian-kejingga.index')
            ->with('success', "Berhasil memperbarui data {$count} barang terpilih untuk PO {$pembelian->kode_pembelian}.");
    }

    public function destroyDetail($detailId)
    {
        $this->authorizeAccess();

        $detail = PembelianDetail::with('pembelian.details.barang')->findOrFail($detailId);
        $pembelian = $detail->pembelian;

        if ($pembelian->isTerkunci() && !(auth()->user() && auth()->user()->isSuperAdmin())) {
            return back()->with('error', 'Item tidak dapat dihapus karena PO ' . $pembelian->kode_pembelian . ' sudah dikunci.');
        }

        if ((float) ($detail->qty_diterima ?? 0) > 0) {
            return back()->with('error', 'Item ' . ($detail->barang->nama ?? '') . ' sudah pernah diterima fisiknya dan tidak dapat dihapus.');
        }

        if ($pembelian->details->count() <= 1) {
            return back()->with('error', 'PO harus memiliki minimal 1 item barang. Jika ingin membatalkan seluruh PO, silakan gunakan tombol Hapus PO.');
        }

        $barangId = (int) $detail->barang_id;
        $barangNama = $detail->barang->nama ?? 'Barang';

        DB::transaction(function () use ($detail, $pembelian, $barangId) {
            $detail->delete();

            // Hitung ulang total PO
            $totalDetails = $pembelian->details()->sum('harga');
            $pembelian->total = $totalDetails + (float) ($pembelian->tax_service ?? 0);
            $pembelian->save();

            // Sinkronisasi HPP barang sesuai FIFO
            app(\App\Services\FifoService::class)->syncBarangHpp($barangId);
        });

        return redirect()->route('pembelian-kejingga.index')
            ->with('success', "Barang {$barangNama} berhasil dihapus dari PO {$pembelian->kode_pembelian}.");
    }

    public function uploadBuktiDetail(Request $request, $detailId)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan mengunggah bukti pembayaran.');
        }

        $detail = PembelianDetail::findOrFail($detailId);

        $request->validate([
            'bukti_pembayaran' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        if ($request->hasFile('bukti_pembayaran')) {
            $path = $request->file('bukti_pembayaran')->store('bukti_pembayaran_kejingga', 'public');
            $detail->update(['bukti_pembayaran' => $path]);
        }

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Bukti pembayaran / nota berhasil diunggah.');
    }

    // ==========================================
    // TERIMA BARANG PER ITEM DETAIL (SUPER ADMIN ONLY)
    // ==========================================
    public function terimaDetail(Request $request, $detailId)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk mengonfirmasi penerimaan barang.');
        }

        $detail = PembelianDetail::with(['pembelian', 'barang'])->findOrFail($detailId);

        if (empty($detail->metode_pembayaran)) {
            return back()->with('error', 'Metode pembayaran untuk item ini belum dicatat.');
        }

        $request->validate([
            'qty_diterima'     => 'required|numeric|min:0.01',
            'tanggal_diterima' => 'nullable|date',
        ]);

        $qtyBaruInput = floatval($request->qty_diterima);
        $sisaMax = floatval($detail->qty) - floatval($detail->qty_diterima);
        $tglDiterima = $request->tanggal_diterima ? \Carbon\Carbon::parse($request->tanggal_diterima) : now();

        if ($qtyBaruInput > $sisaMax) {
            return back()->with('error', "Qty diterima ({$qtyBaruInput}) tidak boleh melebihi sisa pesanan ({$sisaMax}).");
        }

        DB::transaction(function () use ($detail, $qtyBaruInput, $tglDiterima) {
            $pembelian = $detail->pembelian;

            $noPenerimaan = 'RCV-KJG-' . date('Ymd') . '-' . rand(100, 999);
            while (DB::table('penerimaan_pembelian')->where('no_penerimaan', $noPenerimaan)->exists()) {
                $noPenerimaan = 'RCV-KJG-' . date('Ymd') . '-' . rand(100, 999);
            }

            $penerimaan = \App\Models\PenerimaanPembelian::create([
                'pembelian_id'  => $pembelian->id,
                'no_penerimaan' => $noPenerimaan,
                'tanggal'       => $tglDiterima,
                'created_by'    => auth()->id()
            ]);

            $accReceived = floatval($detail->qty_diterima ?? 0);
            $detailUpdateData = [
                'qty_diterima' => $accReceived + $qtyBaruInput,
            ];
            if (Schema::hasColumn('pembelian_detail', 'tanggal_diterima')) {
                $detailUpdateData['tanggal_diterima'] = $tglDiterima;
            }
            $detail->update($detailUpdateData);

            $penerimaan->details()->create([
                'pembelian_detail_id' => $detail->id,
                'barang_id'           => $detail->barang_id,
                'qty'                 => $qtyBaruInput,
                'harga_per_qty'       => floatval($detail->harga_per_qty)
            ]);

            $totalHargaDiterima = round($qtyBaruInput * floatval($detail->harga_per_qty), 2);
            $konversi = floatval($detail->konversi_pembelian ?? 1);
            if ($konversi <= 0) $konversi = 1;

            $qtyMasukStok = $qtyBaruInput * $konversi;
            $hargaPerQtyStok = floatval($detail->harga_per_qty) / $konversi;

            StokGudangBatch::create([
                'gudang_id'           => 5, // Gudang Kejingga
                'supplier_id'         => $detail->supplier_id ?: $pembelian->supplier_id,
                'barang_id'           => $detail->barang_id,
                'pembelian_id'        => $pembelian->id,
                'pembelian_detail_id' => $detail->id,
                'batch_number'        => $detail->batch_number . '-RCV-' . rand(10, 99),
                'qty_masuk'           => $qtyMasukStok,
                'qty_keluar'          => 0,
                'qty_sisa'            => $qtyMasukStok,
                'harga_per_qty'       => $hargaPerQtyStok,
                'is_habis'            => false,
            ]);

            $this->stockService->stockIn([
                'barang_id'       => $detail->barang_id,
                'gudang_tujuan_id'=> 5,
                'qty'             => $qtyMasukStok,
                'total_harga'     => $totalHargaDiterima,
                'source_type'     => 'pembelian_kejingga',
                'source_id'       => $pembelian->id,
                'user_id'         => auth()->id(),
            ]);

            // Sinkronisasi HPP barang sesuai FIFO
            app(\App\Services\FifoService::class)->syncBarangHpp((int) $detail->barang_id);

            // Check if all items in PO are fully received
            $allFullyReceived = true;
            foreach ($pembelian->details()->get() as $det) {
                if (floatval($det->qty_diterima) < floatval($det->qty)) {
                    $allFullyReceived = false;
                    break;
                }
            }

            $pembelian->update([
                'is_diterima'   => $allFullyReceived,
                'diterima_at'   => $allFullyReceived ? now() : $pembelian->diterima_at,
                'diterima_oleh' => auth()->id()
            ]);
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Stok barang ' . ($detail->barang->nama ?? '') . ' berhasil diterima dan masuk ke Stok Gudang Kejingga.');
    }
}
