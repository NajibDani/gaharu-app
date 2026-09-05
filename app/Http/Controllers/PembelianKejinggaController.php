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

    public function index(Request $request)
    {
        $this->authorizeAccess();

        $search = $request->query('search');
        $query = Pembelian::with(['supplier', 'gudang', 'user', 'details.barang'])
            ->where('gudang_id', 5); // Khusus Gudang Kejingga (ID 5)

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_pembelian', 'like', '%' . $search . '%')
                  ->orWhereHas('supplier', function($sq) use ($search) {
                      $sq->where('nama', 'like', '%' . $search . '%');
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
            $label = match($item->metode_pembayaran) {
                'cod'    => 'COD',
                'termin' => 'Termin',
                'dp'     => $item->nominal_dp && $item->nominal_dp > 0 
                            ? 'DP Rp ' . number_format((float) $item->nominal_dp, 0, ',', '.')
                            : 'DP ' . $item->persen_dp . '%',
                default  => '-',
            };
            return [$item->id => [
                'id'                  => $item->id,
                'kode'                => $item->kode_pembelian,
                'supplier_id'         => $item->supplier_id,
                'supplier_nama'       => $item->supplier->nama ?? 'Belum Ditentukan (Draft)',
                'supplier_telepon'    => $item->supplier->telepon ?? '-',
                'supplier_alamat'     => $item->supplier->alamat ?? '-',
                'user_nama'           => $item->user->nama ?? ($item->user->username ?? 'Staff Operasional'),
                'gudang_id'           => $item->gudang_id,
                'gudang_nama'         => $item->gudang->nama ?? 'Gudang KeJingga',
                'tanggal'             => \Carbon\Carbon::parse($item->tanggal)->format('d M Y'),
                'tanggal_raw'         => \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d'),
                'tax_service'         => (float) ($item->tax_service ?? 0),
                'total'               => (float) $item->total,
                'metode'              => $item->metode_pembayaran,
                'label'               => $label,
                'persen_dp'           => $item->persen_dp,
                'nominal_dp'          => (float) $item->nominal_dp,
                'is_lunas'            => (bool) $item->is_lunas,
                'is_diterima'         => (bool) $item->is_diterima,
                'is_terkunci'         => (bool) $item->isTerkunci(),
                'is_draft'            => (empty($item->supplier_id) || $item->total <= 0),
                'kekurangan'          => (float) ($item->kekurangan_pembayaran ?? ($item->is_lunas ? 0 : $item->total)),
                'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo ? \Carbon\Carbon::parse($item->tanggal_jatuh_tempo)->format('d M Y') : null,
                'tanggal_pelunasan'   => $item->tanggal_pelunasan ? \Carbon\Carbon::parse($item->tanggal_pelunasan)->format('d M Y') : null,
                'catatan'             => $item->catatan_pembayaran,
                'dicatat_pada'        => $item->dicatat_pada,
                'details'             => $item->details->map(function ($d) use ($stokKejinggaMap) {
                    $bItem = $d->barang;
                    $sPembelian = $d->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                    $konv = floatval($d->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                    $sUtama = $bItem->satuan ?? 'Pcs';
                    $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);
                    $stokTerkini = (float) ($stokKejinggaMap[$d->barang_id] ?? 0);

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
                        'stok_kejingga'      => $stokTerkini,
                        'qty'                => (float) $d->qty,
                        'qty_diterima'       => (float) ($d->qty_diterima ?? 0),
                        'harga'              => (float) $d->harga,
                        'harga_per_qty'      => (float) $d->harga_per_qty,
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
            'supplier_id'        => 'nullable|exists:suppliers,id',
            'tanggal'            => 'required|date',
            'items'              => 'required|array|min:1',
            'items.*.barang_id'  => 'required|exists:master_barang,id',
            'items.*.qty'        => 'required',
            'items.*.harga'      => 'nullable',
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
                    'satuan_pembelian'   => $it['satuan_pembelian'] ?? null,
                    'konversi_pembelian' => isset($it['konversi_pembelian']) ? (float) $it['konversi_pembelian'] : 1.00,
                    'qty'                => $qtyVal,
                    'harga'              => $hargaVal,
                ];
            }

            $grandTotal = $totalItems + $taxService;

            $pembelian = Pembelian::create([
                'kode_pembelian'    => $kodePembelian,
                'supplier_id'       => $request->supplier_id ?: null,
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

            $msgSupplier = empty($request->supplier_id) ? " (Draft Permintaan tanpa Supplier)" : "";
            return redirect()->route('pembelian-kejingga.index')->with('success', "Pembelian Kejingga ({$kodePembelian}) berhasil disimpan{$msgSupplier}.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan pembelian Kejingga: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $this->authorizeAccess();

        $pembelian = Pembelian::with(['supplier', 'gudang', 'user', 'penerimaDiterima', 'details.barang'])
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

        $pembelian = Pembelian::with('details.barang')->where('gudang_id', 5)->findOrFail($id);

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
            'supplier_id'        => 'nullable|exists:suppliers,id',
            'tanggal'            => 'required|date',
            'items'              => 'required|array|min:1',
            'items.*.barang_id'  => 'required|exists:master_barang,id',
            'items.*.qty'        => 'required',
            'items.*.harga'      => 'nullable',
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
                    'satuan_pembelian'   => $it['satuan_pembelian'] ?? null,
                    'konversi_pembelian' => isset($it['konversi_pembelian']) ? (float) $it['konversi_pembelian'] : 1.00,
                    'qty'                => $qtyVal,
                    'harga'              => $hargaVal,
                ];
            }

            $grandTotal = $totalItems + $taxService;

            $pembelian->update([
                'supplier_id' => $request->supplier_id ?: null,
                'tanggal'     => $request->tanggal,
                'total'       => $grandTotal,
                'tax_service' => $taxService,
            ]);

            // Clear existing details and re-create
            PembelianDetail::where('pembelian_id', $pembelian->id)->delete();

            foreach ($parsedItems as $it) {
                $barang = MasterBarang::withoutGlobalScopes()->find($it['barang_id']);
                $hargaPerQty = $it['qty'] > 0 ? $it['harga'] / $it['qty'] : 0;
                $satuan = $it['satuan_pembelian'] ?: ($barang->satuan_pembelian ?: ($barang->satuan ?: 'pcs'));
                $konversi = $it['konversi_pembelian'] > 0 ? $it['konversi_pembelian'] : ($barang->konversi_pembelian ?? 1.00);

                PembelianDetail::create([
                    'pembelian_id'       => $pembelian->id,
                    'barang_id'          => $it['barang_id'],
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

    public function catatPembayaran(Request $request, Pembelian $pembelian)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk mencatat pembayaran pembelian Kejingga.');
        }

        $validated = $request->validate([
            'metode_pembayaran'   => 'required|in:cod,dp,termin',
            'tanggal_jatuh_tempo' => 'nullable|date',
            'persen_dp'           => 'nullable|integer|min:1|max:99',
            'nominal_dp'          => 'nullable|numeric|min:0',
            'tanggal_pelunasan'   => 'required_if:metode_pembayaran,dp|nullable|date',
            'catatan_pembayaran'  => 'nullable|string|max:500',
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

        DB::transaction(function() use ($validated, $pembelian) {
            $isLunas = ($validated['metode_pembayaran'] === 'cod');
            $pembelian->update([
                'metode_pembayaran'   => $validated['metode_pembayaran'],
                'persen_dp'           => $validated['persen_dp'] ?? null,
                'nominal_dp'          => $validated['nominal_dp'] ?? null,
                'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?? null,
                'tanggal_pelunasan'   => $validated['tanggal_pelunasan'] ?? null,
                'catatan_pembayaran'  => $validated['catatan_pembayaran'] ?? null,
                'dicatat_pada'        => now(),
                'is_lunas'            => $isLunas,
                'lunas_at'            => $isLunas ? now() : null,
            ]);
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Metode pembayaran Pembelian Kejingga berhasil dicatat.');
    }

    public function lunasi(Request $request, Pembelian $pembelian)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk melunasi pembayaran.');
        }

        if ($pembelian->is_lunas) {
            return back()->with('error', 'Pembelian ini sudah lunas.');
        }

        DB::transaction(function() use ($pembelian) {
            $pembelian->update([
                'is_lunas' => true,
                'lunas_at' => now(),
            ]);
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Pembayaran pelunasan Pembelian Kejingga berhasil dicatat.');
    }

    public function terima(Request $request, Pembelian $pembelian)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang diizinkan untuk mengonfirmasi penerimaan barang.');
        }

        if (empty($pembelian->metode_pembayaran)) {
            return back()->with('error', 'Metode pembayaran belum dicatat.');
        }

        $request->validate([
            'qty_diterima'   => 'required|array',
            'qty_diterima.*' => 'required|numeric|min:0',
        ]);

        $pembelian->load('details.barang');

        DB::transaction(function () use ($request, $pembelian) {
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

            foreach ($pembelian->details as $detail) {
                $qtyBaruInput = floatval($request->qty_diterima[$detail->id] ?? 0);
                if ($qtyBaruInput <= 0) continue;

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
                    'supplier_id'         => $pembelian->supplier_id,
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
            }

            $allFullyReceived = true;
            foreach ($pembelian->details()->get() as $det) {
                if (floatval($det->qty_diterima) < floatval($det->qty)) {
                    $allFullyReceived = false;
                    break;
                }
            }

            $pembelian->update([
                'is_diterima'   => $allFullyReceived,
                'diterima_at'   => now(),
                'diterima_oleh' => auth()->id()
            ]);
        });

        return redirect()->route('pembelian-kejingga.index')->with('success', 'Barang Pembelian Kejingga berhasil diterima dan stok Gudang Kejingga telah bertambah.');
    }
}
