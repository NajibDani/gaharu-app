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

                PembelianDetail::create([
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
                ]);
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

        $pembelian = Pembelian::where('gudang_id', 5)->findOrFail($id);

        if ($pembelian->isTerkunci()) {
            return back()->with('error', 'Pembelian ' . $pembelian->kode_pembelian . ' sudah dikunci (dibayar atau diterima) dan tidak dapat dihapus.');
        }

        DB::transaction(function() use ($pembelian) {
            PembelianDetail::where('pembelian_id', $pembelian->id)->delete();
            $pembelian->delete();
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Pembelian Kejingga berhasil dihapus.');
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
            'qty_diterima' => 'required|numeric|min:0.01',
        ]);

        $qtyBaruInput = floatval($request->qty_diterima);
        $sisaMax = floatval($detail->qty) - floatval($detail->qty_diterima);

        if ($qtyBaruInput > $sisaMax) {
            return back()->with('error', "Qty diterima ({$qtyBaruInput}) tidak boleh melebihi sisa pesanan ({$sisaMax}).");
        }

        DB::transaction(function () use ($detail, $qtyBaruInput) {
            $pembelian = $detail->pembelian;

            $noPenerimaan = 'RCV-KJG-' . date('Ymd') . '-' . rand(100, 999);
            while (DB::table('penerimaan_pembelian')->where('no_penerimaan', $noPenerimaan)->exists()) {
                $noPenerimaan = 'RCV-KJG-' . date('Ymd') . '-' . rand(100, 999);
            }

            $penerimaan = \App\Models\PenerimaanPembelian::create([
                'pembelian_id'  => $pembelian->id,
                'no_penerimaan' => $noPenerimaan,
                'tanggal'       => now(),
                'created_by'    => auth()->id()
            ]);

            $accReceived = floatval($detail->qty_diterima ?? 0);
            $detail->update(['qty_diterima' => $accReceived + $qtyBaruInput]);

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
