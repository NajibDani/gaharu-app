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

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Akses terbatas. Hanya Super Admin yang diizinkan mengelola pembelian luar Kejingga.');
        }

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

        $dataPembayaran = $pembelian->mapWithKeys(function ($item) {
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
                'supplier_nama'       => $item->supplier->nama ?? '-',
                'gudang_id'           => $item->gudang_id,
                'gudang_nama'         => $item->gudang->nama ?? '-',
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
                'kekurangan'          => (float) ($item->kekurangan_pembayaran ?? ($item->is_lunas ? 0 : $item->total)),
                'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo ? \Carbon\Carbon::parse($item->tanggal_jatuh_tempo)->format('d M Y') : null,
                'tanggal_pelunasan'   => $item->tanggal_pelunasan ? \Carbon\Carbon::parse($item->tanggal_pelunasan)->format('d M Y') : null,
                'catatan'             => $item->catatan_pembayaran,
                'dicatat_pada'        => $item->dicatat_pada,
                'details'             => $item->details->map(function ($d) {
                    $bItem = $d->barang;
                    $sPembelian = $d->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                    $konv = floatval($d->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                    $sUtama = $bItem->satuan ?? 'Pcs';
                    $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);

                    return [
                        'id'                 => $d->id,
                        'barang_id'          => $d->barang_id,
                        'nama'               => $bItem->nama ?? 'Barang',
                        'kode_barang'        => $d->barang->kode_barang ?? '',
                        'satuan'             => $sPembelian ?: $sUtama,
                        'satuan_pembelian'   => $sPembelian,
                        'satuan_utama'       => $sUtama,
                        'konversi_pembelian' => $konv,
                        'has_konversi'       => $hasKonv,
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
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Akses terbatas. Hanya Super Admin yang diizinkan mengelola pembelian luar Kejingga.');
        }

        $suppliers = Supplier::orderBy('nama')->get();
        $barangs   = MasterBarang::where('is_active', true)->orderBy('nama')->get();
        $gudangKejingga = MasterGudang::find(5);

        return view('pembelian-kejingga.create', compact('suppliers', 'barangs', 'gudangKejingga'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Akses terbatas.');
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'tanggal'     => 'required|date',
            'items'       => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:master_barang,id',
            'items.*.qty'       => 'required',
            'items.*.harga'     => 'required',
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
                $hargaRaw = str_replace('.', '', $it['harga']);
                $hargaVal = (float) str_replace(',', '.', $hargaRaw);

                $totalItems += $hargaVal;
                $parsedItems[] = [
                    'barang_id' => $it['barang_id'],
                    'qty'       => $qtyVal,
                    'harga'     => $hargaVal,
                ];
            }

            $grandTotal = $totalItems + $taxService;

            $pembelian = Pembelian::create([
                'kode_pembelian'    => $kodePembelian,
                'supplier_id'       => $request->supplier_id,
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
                $satuan = $barang->satuan_pembelian ?: ($barang->satuan ?: 'pcs');

                $detail = PembelianDetail::create([
                    'pembelian_id'       => $pembelian->id,
                    'barang_id'          => $it['barang_id'],
                    'satuan_pembelian'   => $satuan,
                    'konversi_pembelian' => $barang->konversi_pembelian ?? 1.00,
                    'qty'                => $it['qty'],
                    'qty_diterima'       => 0,
                    'harga'              => $it['harga'],
                    'harga_per_qty'      => $hargaPerQty,
                    'batch_number'       => date('Ymd') . '-PBKJG' . rand(100, 999),
                ]);
            }

            DB::commit();

            return redirect()->route('pembelian-kejingga.index')->with('success', "Pembelian Kejingga ({$kodePembelian}) berhasil disimpan. Silakan catat metode pembayaran dan konfirmasi penerimaan barang.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan pembelian Kejingga: ' . $e->getMessage())->withInput();
        }
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
