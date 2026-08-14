<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePembelianRequest;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\Pembelian;
use App\Models\Supplier;

use App\Services\StockService;
use App\Services\FifoService;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $search = $request->query('search');
        $query = Pembelian::with(['supplier', 'gudang', 'user', 'details.barang']);

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
                'kode'                => $item->kode_pembelian,
                'total'               => (float) $item->total,
                'metode'              => $item->metode_pembayaran,
                'label'               => $label,
                'persen_dp'           => $item->persen_dp,
                'nominal_dp'          => (float) $item->nominal_dp,
                'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo,
                'tanggal_pelunasan'   => $item->tanggal_pelunasan,
                'catatan'             => $item->catatan_pembayaran,
                'dicatat_pada'        => $item->dicatat_pada,
                'details'             => $item->details->map(function ($d) {
                    return [
                        'id'            => $d->id,
                        'nama'          => $d->barang->nama ?? 'Barang',
                        'satuan'        => $d->satuan_pembelian ?: ($d->barang->satuan ?? 'Pcs'),
                        'qty'           => floatval($d->qty),
                        'qty_diterima'  => floatval($d->qty_diterima ?? 0),
                        'harga_per_qty' => floatval($d->harga_per_qty),
                    ];
                })->values()->toArray(),
            ]];
        });

        return view('pembelian.index', compact('pembelian', 'dataPembayaran'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $suppliers = Supplier::orderBy('nama')->get();
        $gudangs   = MasterGudang::orderBy('nama')->get();
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

        return view('pembelian.create', compact('suppliers', 'gudangs', 'barangs'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — stok TIDAK langsung masuk, tunggu konfirmasi terima
    |--------------------------------------------------------------------------
    */

    public function store(StorePembelianRequest $request)
    {
        $data = $request->validated();

        if (\App\Models\Journal::isPeriodClosed($data['tanggal'])) {
            return back()->withErrors(['error' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($data['tanggal'])) . ' sudah ditutup buku. Tidak dapat menambah transaksi pembelian pada periode yang sudah ditutup.'])->withInput();
        }

        DB::transaction(function () use ($data, $request) {

            $taxService = 0;
            if (!empty($request->tax_service)) {
                $taxService = (float) str_replace('.', '', $request->tax_service);
            }

            $total = collect($data['items'])->sum(fn($item) => (float) $item['harga']) + $taxService;

            $pembelian = Pembelian::create([
                'kode_pembelian' => $this->generateKodePembelian($data['tanggal']),
                'supplier_id'    => $data['supplier_id'],
                'gudang_id'      => $data['gudang_id'],
                'tanggal'        => $data['tanggal'],
                'total'          => $total,
                'tax_service'    => $taxService,
                'created_by'     => auth()->id(),
                'is_diterima'    => false,
                'is_lunas'       => false,
            ]);

            foreach ($data['items'] as $item) {

                $hargaPerQty = (float) $item['qty'] > 0
                    ? (float) $item['harga'] / (float) $item['qty']
                    : 0;

                $barang = MasterBarang::withoutGlobalScopes()->find($item['barang_id']);

                // Simpan detail dulu — FIFO & stok masuk saat "Terima"
                $detail = $pembelian->details()->create([
                    'barang_id'          => $item['barang_id'],
                    'satuan_pembelian'   => $barang->satuan_pembelian,
                    'konversi_pembelian' => $barang->konversi_pembelian ?? 1.00,
                    'qty'                => $item['qty'],
                    'harga'              => $item['harga'],
                    'harga_per_qty'      => $hargaPerQty,
                    'batch_number'       => 'TEMP',
                ]);

                $detail->update([
                    'batch_number' => date('Ymd') . '-PB' . $detail->id,
                ]);
            }
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Pembelian berhasil disimpan. Klik Terima setelah barang tiba.');
    }

    /*
    |--------------------------------------------------------------------------
    | TERIMA BARANG — stok masuk di sini
    |--------------------------------------------------------------------------
    */

    public function terima(Request $request, Pembelian $pembelian)
    {
        if (!$pembelian->is_lunas) {
            return back()->with('error', 'Gagal memproses penerimaan barang: Pembayaran belum lunas. Silakan lunasi pembayaran terlebih dahulu.');
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
            // Create PenerimaanPembelian header
            $noPenerimaan = 'RCV-' . strtoupper($pembelian->supplier->prefix ?? 'SUP') . '-' . date('Ymd') . '-' . rand(100, 999);
            // Ensure uniqueness of no_penerimaan
            while (DB::table('penerimaan_pembelian')->where('no_penerimaan', $noPenerimaan)->exists()) {
                $noPenerimaan = 'RCV-' . strtoupper($pembelian->supplier->prefix ?? 'SUP') . '-' . date('Ymd') . '-' . rand(100, 999);
            }

            $penerimaan = \App\Models\PenerimaanPembelian::create([
                'pembelian_id' => $pembelian->id,
                'no_penerimaan' => $noPenerimaan,
                'tanggal' => now(),
                'created_by' => auth()->id()
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
                    'barang_id' => $detail->barang_id,
                    'qty' => $qtyBaruInput,
                    'harga_per_qty' => floatval($detail->harga_per_qty)
                ]);

                $totalHargaDiterima = round($qtyBaruInput * floatval($detail->harga_per_qty), 2);

                // Create StokGudangBatch
                $suffix = rand(10, 99) . '-' . date('His');
                $konversi = floatval($detail->konversi_pembelian ?? 1);
                if ($konversi <= 0) {
                    $konversi = 1;
                }

                $qtyMasukStok = $qtyBaruInput * $konversi;
                $hargaPerQtyStok = floatval($detail->harga_per_qty) / $konversi;

                \App\Models\StokGudangBatch::create([
                    'gudang_id' => $pembelian->gudang_id,
                    'supplier_id' => $pembelian->supplier_id,
                    'barang_id' => $detail->barang_id,
                    'pembelian_id' => $pembelian->id,
                    'pembelian_detail_id' => $detail->id,
                    'batch_number' => $detail->batch_number . '-RCV-' . $suffix,
                    'qty_masuk' => $qtyMasukStok,
                    'qty_keluar' => 0,
                    'qty_sisa' => $qtyMasukStok,
                    'harga_per_qty' => $hargaPerQtyStok,
                    'is_habis' => false,
                ]);

                // Tambah stok gudang
                $this->stockService->stockIn([
                    'barang_id'       => $detail->barang_id,
                    'gudang_tujuan_id'=> $pembelian->gudang_id,
                    'qty'             => $qtyMasukStok,
                    'total_harga'     => $totalHargaDiterima,
                    'source_type'     => 'pembelian',
                    'source_id'       => $pembelian->id,
                    'user_id'         => auth()->id(),
                ]);
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
                'is_diterima' => $allFullyReceived,
                'diterima_at' => now(),
                'diterima_oleh' => auth()->id()
            ]);

            // Auto-post Jurnal Persediaan (reklas_lunas atau cod)
            $tahapJurnal = 'cod';
            if ($pembelian->metode_pembayaran === 'dp') {
                $tahapJurnal = 'reklas_lunas';
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
        $validated = $request->validate([
            'metode_pembayaran'   => 'required|in:cod,dp',
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

            // Auto-post Jurnal DP jika metodenya DP
            if ($validated['metode_pembayaran'] === 'dp') {
                \App\Http\Controllers\JurnalController::autoPostPembelian($pembelian->id, 'dp');
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
        if ($pembelian->isTerkunci()) {
            return redirect()->route('pembelian.index')
                ->with('error', 'Pembelian ini tidak bisa diedit karena sudah diterima atau lunas.');
        }

        $pembelian->load('details');
        $suppliers = Supplier::orderBy('nama')->get();
        $gudangs   = MasterGudang::orderBy('nama')->get();
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
        if ($pembelian->isTerkunci()) {
            return redirect()->route('pembelian.index')
                ->with('error', 'Pembelian ini tidak bisa diubah.');
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $pembelian, $request) {

            $pembelian->load('details');

            // Hapus detail lama (belum ada stok karena belum diterima)
            $pembelian->details()->delete();

            $taxService = 0;
            if (!empty($request->tax_service)) {
                $taxService = (float) str_replace('.', '', $request->tax_service);
            }

            $total = collect($data['items'])->sum(fn($item) => (float) $item['harga']) + $taxService;

            $pembelian->update([
                'supplier_id' => $data['supplier_id'],
                'gudang_id'   => $data['gudang_id'],
                'tanggal'     => $data['tanggal'],
                'total'       => $total,
                'tax_service' => $taxService,
            ]);

            foreach ($data['items'] as $item) {

                $hargaPerQty = (float) $item['qty'] > 0
                    ? (float) $item['harga'] / (float) $item['qty']
                    : 0;

                $detail = $pembelian->details()->create([
                    'barang_id'     => $item['barang_id'],
                    'qty'           => $item['qty'],
                    'harga'         => $item['harga'],
                    'harga_per_qty' => $hargaPerQty,
                    'batch_number'  => 'TEMP',
                ]);

                $detail->update([
                    'batch_number' => date('Ymd') . '-PB' . $detail->id,
                ]);
            }
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Pembelian berhasil diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(Pembelian $pembelian)
    {
        if ($pembelian->isTerkunci()) {
            return back()->with('error', 'Pembelian yang sudah diterima atau lunas tidak bisa dihapus.');
        }

        DB::transaction(function () use ($pembelian) {
            $pembelian->details()->delete();
            $pembelian->delete();
        });

        return redirect()
            ->route('pembelian.index')
            ->with('success', 'Pembelian berhasil dihapus.');
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
        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
        $pdf->loadView('pembelian.po-pdf', compact('pembelian'));
        return $pdf->stream('Purchase-Order-' . $pembelian->kode_pembelian . '.pdf');
    }
}