<?php

namespace App\Http\Controllers;

use App\Models\MasterGudang;
use App\Models\GudangDivisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GudangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = MasterGudang::with('divisi');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kategori', 'like', '%' . $search . '%');
            });
        }

        $gudangs = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        return view('gudangs.index', compact('gudangs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('gudangs.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama'     => 'required|string|max:255',
            'kategori' => 'required|string|max:255',
            'divisi'   => 'nullable|array',
            'divisi.*' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $gudang = MasterGudang::create([
                'nama'     => $request->nama,
                'kategori' => $request->kategori,
            ]);

            if (strtolower($request->kategori) === 'operasional' && is_array($request->divisi)) {
                $divisiList = array_filter(array_map('trim', $request->divisi));
                foreach ($divisiList as $namaDivisi) {
                    if (!empty($namaDivisi)) {
                        GudangDivisi::create([
                            'gudang_id'  => $gudang->id,
                            'nama'       => $namaDivisi,
                            'keterangan' => 'Divisi ' . $namaDivisi . ' untuk ' . $gudang->nama,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('gudangs.index')
            ->with('success', 'Data gudang berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $gudang = MasterGudang::with('divisi')->findOrFail($id);

        return view('gudangs.show', compact('gudang'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $gudang = MasterGudang::with('divisi')->findOrFail($id);

        return view('gudangs.edit', compact('gudang'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $gudang = MasterGudang::findOrFail($id);

        $request->validate([
            'nama'     => 'required|string|max:255',
            'kategori' => 'required|string|max:255',
            'divisi'   => 'nullable|array',
            'divisi.*' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $gudang) {
            $gudang->update([
                'nama'     => $request->nama,
                'kategori' => $request->kategori,
            ]);

            if (strtolower($request->kategori) === 'operasional') {
                $submittedNames = array_values(array_filter(array_map('trim', $request->divisi ?? [])));

                // Hapus divisi yang sudah tidak ada dalam daftar yang disubmit
                // Namun hati-hati jika divisi sudah ada transaksi, delete cascade akan ditangani atau pertahankan
                $existingDivisions = GudangDivisi::where('gudang_id', $gudang->id)->get();
                $existingMap = $existingDivisions->keyBy('nama');

                foreach ($existingDivisions as $existing) {
                    if (!in_array($existing->nama, $submittedNames)) {
                        $existing->delete();
                    }
                }

                // Tambahkan divisi baru yang belum ada
                foreach ($submittedNames as $divName) {
                    if (!empty($divName) && !$existingMap->has($divName)) {
                        GudangDivisi::create([
                            'gudang_id'  => $gudang->id,
                            'nama'       => $divName,
                            'keterangan' => 'Divisi ' . $divName . ' untuk ' . $gudang->nama,
                        ]);
                    }
                }
            } else {
                // Jika kategori diubah dari Operasional ke selain Operasional, opsional hapus divisi
            }
        });

        return redirect()->route('gudangs.index')
            ->with('success', 'Data gudang berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $gudang = MasterGudang::findOrFail($id);

        $gudang->delete();

        return redirect()->route('gudangs.index')
            ->with('success', 'Data gudang berhasil dihapus.');
    }

    /**
     * AJAX endpoint to fetch divisions for a specific warehouse
     */
    public function getDivisi(string $id)
    {
        $gudang = MasterGudang::with('divisi')->find($id);

        if (!$gudang) {
            return response()->json([
                'success' => false,
                'is_operasional' => false,
                'divisi' => [],
            ], 404);
        }

        $isOperasional = strtolower($gudang->kategori) === 'operasional';
        $divisi = $gudang->divisi->map(fn($d) => [
            'id'   => $d->id,
            'nama' => $d->nama,
        ]);

        return response()->json([
            'success'        => true,
            'gudang_id'      => $gudang->id,
            'nama'           => $gudang->nama,
            'kategori'       => $gudang->kategori,
            'is_operasional' => $isOperasional,
            'divisi'         => $divisi,
        ]);
    }
}