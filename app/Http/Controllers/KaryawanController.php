<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class KaryawanController extends Controller
{
    private function getOutlet(Request $request): string
    {
        $user = auth()->user();
        $role = $user->role->nama ?? '';

        if ($role === 'Kepala Outlet Gaharu') {
            return 'Gaharu';
        }
        if ($role === 'Kepala Outlet Kejingga') {
            return 'Kejingga';
        }

        return $request->query('outlet', 'Gaharu');
    }

    /**
     * Menampilkan daftar semua karyawan.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $selectedOutlet = $this->getOutlet($request);
        $departemen = $request->query('departemen');
        $jabatan = $request->query('jabatan');
        $sort = strtolower($request->query('sort', ''));

        $query = Karyawan::where('outlet', $selectedOutlet);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_karyawan', 'like', '%' . $search . '%')
                  ->orWhere('nik', 'like', '%' . $search . '%')
                  ->orWhere('whatsapp', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('jabatan', 'like', '%' . $search . '%')
                  ->orWhere('departemen', 'like', '%' . $search . '%');
            });
        }

        if ($departemen) {
            $query->where('departemen', $departemen);
        }

        if ($jabatan) {
            $query->where('jabatan', $jabatan);
        }

        if ($sort === 'asc') {
            $query->orderBy('nama_karyawan', 'asc');
        } elseif ($sort === 'desc') {
            $query->orderBy('nama_karyawan', 'desc');
        } else {
            $query->orderBy('urutan', 'asc')->orderBy('id', 'asc');
        }

        $karyawans = $query->paginate(100)->withQueryString();

        $departemenList = Karyawan::DEPARTEMEN_LIST;
        $jabatanList = Karyawan::getJabatanList();

        return view('karyawan.index', compact('karyawans', 'selectedOutlet', 'departemenList', 'jabatanList'));
    }

    /**
     * AJAX: Ambil seluruh daftar Master Jabatan
     */
    public function getJabatan(): \Illuminate\Http\JsonResponse
    {
        \App\Models\MasterJabatan::ensureTableExists();
        $jabatans = \App\Models\MasterJabatan::orderBy('urutan', 'asc')->orderBy('id', 'asc')->get();
        return response()->json($jabatans);
    }

    /**
     * AJAX: Tambah Master Jabatan baru
     */
    public function storeJabatan(Request $request): \Illuminate\Http\JsonResponse
    {
        \App\Models\MasterJabatan::ensureTableExists();
        $request->validate([
            'nama' => 'required|string|max:100',
        ]);

        $nama = strtoupper(trim($request->nama));

        $existing = \App\Models\MasterJabatan::where('nama', $nama)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Jabatan / Posisi ini sudah ada.',
            ], 422);
        }

        $maxUrutan = \App\Models\MasterJabatan::max('urutan') ?? 0;
        $jabatan = \App\Models\MasterJabatan::create([
            'nama'   => $nama,
            'urutan' => $maxUrutan + 1,
        ]);

        $allJabatan = \App\Models\MasterJabatan::orderBy('urutan', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'success'  => true,
            'message'  => 'Pilihan jabatan baru berhasil ditambahkan.',
            'jabatan'  => $jabatan,
            'jabatans' => $allJabatan,
        ]);
    }

    /**
     * AJAX: Edit Master Jabatan
     */
    public function updateJabatan(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'nama' => 'required|string|max:100',
        ]);

        $nama = strtoupper(trim($request->nama));
        $existing = \App\Models\MasterJabatan::where('nama', $nama)->where('id', '!=', $id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Nama Jabatan / Posisi ini sudah digunakan oleh opsi lain.',
            ], 422);
        }

        $jabatan = \App\Models\MasterJabatan::findOrFail($id);
        $oldNama = $jabatan->nama;
        $jabatan->update([
            'nama' => $nama,
        ]);

        // Opsional: update data karyawan yang menggunakan nama jabatan lama agar tetap sinkron
        if ($oldNama !== $nama) {
            Karyawan::where('jabatan', $oldNama)->update(['jabatan' => $nama]);
        }

        $allJabatan = \App\Models\MasterJabatan::orderBy('urutan', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'success'  => true,
            'message'  => 'Pilihan jabatan berhasil diperbarui.',
            'jabatan'  => $jabatan,
            'jabatans' => $allJabatan,
        ]);
    }

    /**
     * AJAX: Hapus Master Jabatan
     */
    public function deleteJabatan($id): \Illuminate\Http\JsonResponse
    {
        $jabatan = \App\Models\MasterJabatan::findOrFail($id);
        $jabatan->delete();

        $allJabatan = \App\Models\MasterJabatan::orderBy('urutan', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'success'  => true,
            'message'  => 'Pilihan jabatan berhasil dihapus.',
            'jabatans' => $allJabatan,
        ]);
    }

    /**
     * Menampilkan detil karyawan.
     */
    public function show($id)
    {
        $karyawan = Karyawan::findOrFail($id);
        return view('karyawan.show', compact('karyawan'));
    }

    /**
     * Menampilkan form untuk menambah karyawan baru.
     */
    public function create(): View
    {
        $departemenList = Karyawan::DEPARTEMEN_LIST;
        $jabatanList = Karyawan::getJabatanList();
        return view('karyawan.create', compact('departemenList', 'jabatanList'));
    }

    /**
     * Menyimpan data karyawan baru ke database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_karyawan'      => 'required|string|max:255',
            'nik'                => 'nullable|string|max:50',
            'tempat_lahir'       => 'nullable|string|max:100',
            'tanggal_lahir'      => 'nullable|date',
            'ttl'                => 'nullable|string|max:100',
            'whatsapp'           => 'nullable|string|max:50',
            'email'              => 'nullable|email|max:100',
            'nomor_darurat'      => 'nullable|string|max:100',
            'jabatan'            => 'required|string',
            'jenis_tenaga_kerja' => 'required|string',
            'departemen'         => 'required|string',
            'outlet'             => 'required|string|in:Gaharu,Kejingga',
            'satuan_gaji'        => 'nullable|string|in:Harian,Bulanan,Per Jam',
            'no_rekening'        => 'nullable|string|max:100',
            'gaji_pokok'         => 'nullable|numeric|min:0',
            'uang_makan'         => 'nullable|numeric|min:0',
            'uang_transport'     => 'nullable|numeric|min:0',
        ]);

        $validated['satuan_gaji'] = $validated['satuan_gaji'] ?? 'Harian';
        $validated['gaji_pokok'] = $validated['gaji_pokok'] ?? 0;
        $validated['uang_makan'] = $validated['uang_makan'] ?? 0;
        $validated['uang_transport'] = $validated['uang_transport'] ?? 0;

        if (empty($validated['ttl']) && (!empty($validated['tempat_lahir']) || !empty($validated['tanggal_lahir']))) {
            $parts = array_filter([$validated['tempat_lahir'] ?? null, $validated['tanggal_lahir'] ?? null]);
            $validated['ttl'] = implode(', ', $parts);
        }

        $maxUrutan = Karyawan::where('outlet', $validated['outlet'])->max('urutan') ?? 0;
        $validated['urutan'] = $maxUrutan + 1;

        Karyawan::create($validated);

        return redirect()->route('karyawan.index', ['outlet' => $request->outlet])->with('success', 'Data Karyawan berhasil disimpan!');
    }

    /**
     * Menyimpan urutan baru baris karyawan setelah drag-and-drop.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:karyawan,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $index => $id) {
                Karyawan::where('id', $id)->update(['urutan' => $index + 1]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Urutan karyawan berhasil disimpan.'
        ]);
    }

    /**
     * Menampilkan form edit untuk satu karyawan tertentu.
     */
    public function edit(Karyawan $karyawan): View
    {
        $departemenList = Karyawan::DEPARTEMEN_LIST;
        $jabatanList = Karyawan::getJabatanList();
        return view('karyawan.edit', compact('karyawan', 'departemenList', 'jabatanList'));
    }

    /**
     * Memperbarui data karyawan di database.
     */
    public function update(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $validated = $request->validate([
            'nama_karyawan'      => 'required|string|max:255',
            'nik'                => 'nullable|string|max:50',
            'tempat_lahir'       => 'nullable|string|max:100',
            'tanggal_lahir'      => 'nullable|date',
            'ttl'                => 'nullable|string|max:100',
            'whatsapp'           => 'nullable|string|max:50',
            'email'              => 'nullable|email|max:100',
            'nomor_darurat'      => 'nullable|string|max:100',
            'jabatan'            => 'required|string',
            'jenis_tenaga_kerja' => 'required|string',
            'departemen'         => 'required|string',
            'outlet'             => 'required|string|in:Gaharu,Kejingga',
            'satuan_gaji'        => 'nullable|string|in:Harian,Bulanan,Per Jam',
            'no_rekening'        => 'nullable|string|max:100',
            'gaji_pokok'         => 'nullable|numeric|min:0',
            'uang_makan'         => 'nullable|numeric|min:0',
            'uang_transport'     => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['ttl']) && (!empty($validated['tempat_lahir']) || !empty($validated['tanggal_lahir']))) {
            $parts = array_filter([$validated['tempat_lahir'] ?? null, $validated['tanggal_lahir'] ?? null]);
            $validated['ttl'] = implode(', ', $parts);
        }

        $karyawan->update($validated);

        return redirect()->route('karyawan.index', ['outlet' => $request->outlet])
            ->with('success', 'Data berhasil diperbarui.');
    }

    /**
     * Menghapus data karyawan.
     */
    public function destroy(Karyawan $karyawan): RedirectResponse
    {
        $karyawan->delete();

        return redirect()->route('karyawan.index')
            ->with('success', 'Data berhasil dihapus.');
    }
}
