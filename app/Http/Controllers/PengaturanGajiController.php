<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PengaturanGajiController extends Controller
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
     * Menampilkan daftar komponen gaji karyawan (Master Data Pengaturan Gaji).
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $selectedOutlet = $this->getOutlet($request);
        $query = Karyawan::where('outlet', $selectedOutlet);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_karyawan', 'like', '%' . $search . '%')
                  ->orWhere('jabatan', 'like', '%' . $search . '%')
                  ->orWhere('departemen', 'like', '%' . $search . '%');
            });
        }

        $karyawans = $query->orderBy('nama_karyawan', 'asc')->paginate(15)->withQueryString();

        // Data statistik ringkasan per outlet
        $totalKaryawan = Karyawan::where('outlet', $selectedOutlet)->count();
        $avgTarifHarian = Karyawan::where('outlet', $selectedOutlet)->get()->avg(fn($k) => $k->tarif_harian_total);

        return view('pengaturan-gaji.index', compact('karyawans', 'totalKaryawan', 'avgTarifHarian', 'selectedOutlet'));
    }

    /**
     * Memperbarui komponen gaji harian karyawan.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'gaji_pokok'         => 'required|numeric|min:0',
            'uang_makan'         => 'required|numeric|min:0',
            'uang_transport'     => 'required|numeric|min:0',
            'tanggal_mulai'      => 'nullable|date',
            'tanggal_selesai'    => 'nullable|date|after_or_equal:tanggal_mulai',
            'gaji_pokok_2'       => 'nullable|numeric|min:0',
            'uang_makan_2'       => 'nullable|numeric|min:0',
            'uang_transport_2'   => 'nullable|numeric|min:0',
            'tanggal_mulai_2'    => 'nullable|date',
            'tanggal_selesai_2'  => 'nullable|date|after_or_equal:tanggal_mulai_2',
        ]);

        $karyawan = Karyawan::findOrFail($id);
        $karyawan->update([
            'gaji_pokok'         => $request->gaji_pokok,
            'uang_makan'         => $request->uang_makan,
            'uang_transport'     => $request->uang_transport,
            'tanggal_mulai'      => $request->tanggal_mulai,
            'tanggal_selesai'    => $request->tanggal_selesai,
            'gaji_pokok_2'       => $request->gaji_pokok_2,
            'uang_makan_2'       => $request->uang_makan_2,
            'uang_transport_2'   => $request->uang_transport_2,
            'tanggal_mulai_2'    => $request->tanggal_mulai_2,
            'tanggal_selesai_2'  => $request->tanggal_selesai_2,
        ]);

        return redirect()->route('pengaturan-gaji.index', ['outlet' => $karyawan->outlet])
            ->with('success', "Pengaturan gaji harian untuk {$karyawan->nama_karyawan} berhasil diperbarui.");
    }
}
