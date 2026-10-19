<?php

namespace App\Http\Controllers;

use App\Models\GudangDivisi;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\Preparation;
use App\Services\PreparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreparationController extends Controller
{
    protected PreparationService $service;

    public function __construct(PreparationService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of preparations
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('search');
        $gudangId = $request->query('gudang_id');
        $divisiId = $request->query('divisi_id');
        $sort = $request->query('sort', 'terbaru');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $query = Preparation::with([
            'inputs.barang',
            'outputs.barang',
            'gudang',
            'divisi',
            'creator',
            'approver',
        ]);

        // Filter tab
        if ($tab === 'menunggu') {
            $query->where('status', 'menunggu');
        } elseif ($tab === 'disetujui') {
            $query->where('status', 'disetujui');
        } elseif ($tab === 'ditolak') {
            $query->where('status', 'ditolak');
        }

        // Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%")
                  ->orWhere('alasan_tolak', 'like', "%{$search}%")
                  ->orWhereHas('inputs.barang', function ($sub) use ($search) {
                      $sub->where('nama', 'like', "%{$search}%");
                  })
                  ->orWhereHas('outputs.barang', function ($sub) use ($search) {
                      $sub->where('nama', 'like', "%{$search}%");
                  });
            });
        }

        // Warehouse & Division
        if (!empty($gudangId)) {
            $query->where('gudang_id', $gudangId);
        }
        if (!empty($divisiId)) {
            $query->where('divisi_id', $divisiId);
        }

        // Date range
        if (!empty($dari)) {
            $query->whereDate('tanggal', '>=', $dari);
        }
        if (!empty($sampai)) {
            $query->whereDate('tanggal', '<=', $sampai);
        }

        // Sorting
        if ($sort === 'terlama') {
            $query->orderBy('tanggal', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('tanggal', 'desc')->orderBy('id', 'desc');
        }

        $preparations = $query->paginate(15)->appends($request->query());

        // Tab counts
        $countsQuery = Preparation::query();
        if (!empty($gudangId)) {
            $countsQuery->where('gudang_id', $gudangId);
        }
        if (!empty($divisiId)) {
            $countsQuery->where('divisi_id', $divisiId);
        }

        $tabCounts = [
            'semua'     => (clone $countsQuery)->count(),
            'menunggu'  => (clone $countsQuery)->where('status', 'menunggu')->count(),
            'disetujui' => (clone $countsQuery)->where('status', 'disetujui')->count(),
            'ditolak'   => (clone $countsQuery)->where('status', 'ditolak')->count(),
        ];

        // Master data
        $gudangs = MasterGudang::orderBy('nama')->get();
        $divisis = GudangDivisi::with('gudang')
            ->whereNotIn(DB::raw('LOWER(nama)'), ['kasir', 'satpam', 'office', 'cleaning service', 'security', 'kebersihan'])
            ->orderBy('nama')
            ->get();

        // Raw materials and semi finished (optimize payload)
        $rawMaterials = MasterBarang::select('id', 'nama', 'kode_barang', 'satuan', 'satuan_pembelian', 'konversi_pembelian')
            ->orderBy('nama')
            ->get();
        $bsjMaterials = MasterBarang::select('id', 'nama', 'kode_barang', 'satuan', 'satuan_pembelian', 'konversi_pembelian')
            ->where('is_bahan_setengah_jadi', 1)
            ->orderBy('nama')
            ->get();
        if ($bsjMaterials->isEmpty()) {
            $bsjMaterials = $rawMaterials;
        }

        return view('preparation-bahan.index', compact(
            'preparations',
            'tabCounts',
            'gudangs',
            'divisis',
            'rawMaterials',
            'bsjMaterials',
            'tab'
        ));
    }

    /**
     * Store a newly created preparation
     */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal'   => 'required|date',
            'gudang_id' => 'required|exists:master_gudang,id',
            'divisi_id' => 'nullable|exists:gudang_divisi,id',
            'inputs'    => 'required|array|min:1',
            'outputs'   => 'required|array|min:1',
        ]);

        try {
            $prep = $this->service->createDraft($request->all(), auth()->id() ?? 1);

            $stockCheck = $prep->getStockCheckDetails();
            if (!$stockCheck['is_sufficient']) {
                $kurang = collect($stockCheck['insufficient_items'])->pluck('nama')->implode(', ');
                return redirect()
                    ->route('preparation-bahan.index')
                    ->with('warning', "Preparation Bahan {$prep->kode} berhasil disimpan (Menunggu). Perhatian: Stok bahan baku ({$kurang}) belum mencukupi. Tombol persetujuan dinonaktifkan sampai stok terpenuhi.");
            }

            return redirect()
                ->route('preparation-bahan.index')
                ->with('success', "Preparation Bahan {$prep->kode} berhasil dibuat dengan status Menunggu.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal membuat preparation: ' . $e->getMessage());
        }
    }

    /**
     * Return JSON for read-only View modal
     */
    public function detailJson($id)
    {
        $prep = Preparation::with([
            'inputs.barang',
            'outputs.barang',
            'outputs.batch',
            'gudang',
            'divisi',
            'creator.role',
            'approver.role',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $prep->id,
                'kode'         => $prep->kode,
                'tanggal'      => $prep->tanggal ? $prep->tanggal->format('d/m/Y') : '-',
                'status'       => $prep->status,
                'gudang_nama'  => $prep->gudang->nama ?? '-',
                'divisi_nama'  => $prep->divisi->nama ?? null,
                'keterangan'   => $prep->keterangan ?: '-',
                'alasan_tolak' => $prep->alasan_tolak ?: null,
                'creator_nama' => $prep->creator->name ?? ($prep->creator->nama ?? '-'),
                'approver_nama' => $prep->approver ? ($prep->approver->name ?? ($prep->approver->nama ?? '-')) : null,
                'approved_at'  => $prep->approved_at ? $prep->approved_at->format('d/m/Y H:i') : null,
                'total_biaya'  => (float)$prep->total_biaya,
                'stock_check'  => $prep->isMenunggu() ? $prep->getStockCheckDetails() : null,
                'inputs'       => $prep->inputs->map(function ($in) {
                    return [
                        'id'             => $in->id,
                        'barang_id'      => $in->barang_id,
                        'barang_nama'    => $in->barang->nama ?? '-',
                        'barang_kode'    => $in->barang->kode ?? '',
                        'qty'            => (float)$in->qty,
                        'satuan'         => $in->satuan,
                        'konversi'       => (float)$in->konversi,
                        'qty_base'       => (float)$in->qty_base,
                        'satuan_base'    => $in->barang->satuan ?? $in->satuan,
                        'hpp_satuan'     => (float)$in->hpp_satuan,
                        'total_biaya'    => (float)$in->total_biaya,
                        'fifo_breakdown' => $in->fifo_breakdown,
                    ];
                }),
                'outputs'      => $prep->outputs->map(function ($out) {
                    return [
                        'id'          => $out->id,
                        'barang_id'   => $out->barang_id,
                        'barang_nama' => $out->barang->nama ?? '-',
                        'barang_kode' => $out->barang->kode ?? '',
                        'qty'         => (float)$out->qty,
                        'satuan'      => $out->satuan,
                        'konversi'    => (float)$out->konversi,
                        'qty_base'    => (float)$out->qty_base,
                        'satuan_base' => $out->barang->satuan ?? $out->satuan,
                        'hpp_satuan'  => (float)$out->hpp_satuan,
                        'total_nilai' => (float)$out->total_nilai,
                        'batch_number' => $out->batch->batch_number ?? null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Return JSON for Edit modal
     */
    public function editJson($id)
    {
        $prep = Preparation::with(['inputs', 'outputs'])->findOrFail($id);

        if (!$prep->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen dengan status "' . $prep->status . '" tidak dapat diedit.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $prep->id,
                'kode'       => $prep->kode,
                'tanggal'    => $prep->tanggal ? $prep->tanggal->format('Y-m-d') : '',
                'gudang_id'  => $prep->gudang_id,
                'divisi_id'  => $prep->divisi_id,
                'keterangan' => $prep->keterangan,
                'inputs'     => $prep->inputs->map(function ($in) {
                    return [
                        'barang_id' => $in->barang_id,
                        'qty'       => (float)$in->qty,
                        'satuan'    => $in->satuan,
                    ];
                }),
                'outputs'    => $prep->outputs->map(function ($out) {
                    return [
                        'barang_id' => $out->barang_id,
                        'qty'       => (float)$out->qty,
                        'satuan'    => $out->satuan,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update an existing preparation
     */
    public function update(Request $request, $id)
    {
        $prep = Preparation::findOrFail($id);

        $request->validate([
            'tanggal'   => 'required|date',
            'gudang_id' => 'required|exists:master_gudang,id',
            'divisi_id' => 'nullable|exists:gudang_divisi,id',
            'inputs'    => 'required|array|min:1',
            'outputs'   => 'required|array|min:1',
        ]);

        try {
            $this->service->updateDraft($prep, $request->all(), auth()->id() ?? 1);
            $prep->refresh();

            $stockCheck = $prep->getStockCheckDetails();
            if (!$stockCheck['is_sufficient']) {
                $kurang = collect($stockCheck['insufficient_items'])->pluck('nama')->implode(', ');
                return redirect()
                    ->route('preparation-bahan.index')
                    ->with('warning', "Preparation Bahan {$prep->kode} berhasil diperbarui (Menunggu). Perhatian: Stok bahan baku ({$kurang}) belum mencukupi. Tombol persetujuan dinonaktifkan sampai stok terpenuhi.");
            }

            return redirect()
                ->route('preparation-bahan.index')
                ->with('success', "Preparation Bahan {$prep->kode} berhasil diperbarui.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui preparation: ' . $e->getMessage());
        }
    }

    /**
     * Approve preparation
     */
    public function approve($id)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'canApprovePreparation') && !$user->canApprovePreparation()) {
            return redirect()
                ->route('preparation-bahan.index')
                ->with('error', 'Anda tidak memiliki hak akses untuk menyetujui preparation bahan.');
        }

        $prep = Preparation::findOrFail($id);

        try {
            $this->service->approve($prep, auth()->id() ?? 1);

            return redirect()
                ->route('preparation-bahan.index')
                ->with('success', "Preparation Bahan {$prep->kode} berhasil disetujui. Stok bahan baku telah dipotong secara FIFO dan stok bahan setengah jadi telah masuk.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('preparation-bahan.index')
                ->with('error', 'Gagal menyetujui preparation: ' . $e->getMessage());
        }
    }

    /**
     * Reject preparation
     */
    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'canApprovePreparation') && !$user->canApprovePreparation()) {
            return redirect()
                ->route('preparation-bahan.index')
                ->with('error', 'Anda tidak memiliki hak akses untuk menolak preparation bahan.');
        }

        $request->validate([
            'alasan_tolak' => 'required|string|max:1000',
        ], [
            'alasan_tolak.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $prep = Preparation::findOrFail($id);

        try {
            $this->service->reject($prep, $request->input('alasan_tolak'), auth()->id() ?? 1);

            return redirect()
                ->route('preparation-bahan.index')
                ->with('success', "Preparation Bahan {$prep->kode} berhasil ditolak.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('preparation-bahan.index')
                ->with('error', 'Gagal menolak preparation: ' . $e->getMessage());
        }
    }

    /**
     * Delete preparation
     */
    public function destroy($id)
    {
        $prep = Preparation::findOrFail($id);

        if (!$prep->canDelete()) {
            return redirect()
                ->route('preparation-bahan.index')
                ->with('error', 'Dokumen preparation yang sudah disetujui tidak dapat dihapus.');
        }

        try {
            $kode = $prep->kode;
            $this->service->delete($prep);

            return redirect()
                ->route('preparation-bahan.index')
                ->with('success', "Preparation Bahan {$kode} berhasil dihapus.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('preparation-bahan.index')
                ->with('error', 'Gagal menghapus preparation: ' . $e->getMessage());
        }
    }

    /**
     * Get real-time stock for barang in gudang + divisi
     */
    public function getStock(Request $request)
    {
        $barangId = (int)$request->query('barang_id');
        $gudangId = (int)$request->query('gudang_id');
        $divisiId = $request->query('divisi_id') ? (int)$request->query('divisi_id') : null;

        if (!$barangId || !$gudangId) {
            return response()->json(['stok' => 0, 'satuan' => '']);
        }

        $barang = MasterBarang::find($barangId);
        $stock = $this->service->getAvailableStock($barangId, $gudangId, $divisiId);

        return response()->json([
            'success'           => true,
            'stok'              => $stock,
            'satuan'            => $barang ? ($barang->satuan ?: 'gr') : '',
            'satuan_pembelian' => $barang ? $barang->satuan_pembelian : null,
            'konversi'          => $barang ? (float)($barang->konversi_pembelian ?? 1) : 1,
        ]);
    }

    /**
     * Check real-time stock availability for a specific preparation document (AJAX)
     */
    public function stockCheck($id)
    {
        $prep = Preparation::with(['inputs.barang'])->findOrFail($id);

        return response()->json([
            'success'     => true,
            'id'          => $prep->id,
            'kode'        => $prep->kode,
            'status'      => $prep->status,
            'stock_check' => $prep->getStockCheckDetails(),
        ]);
    }
}
