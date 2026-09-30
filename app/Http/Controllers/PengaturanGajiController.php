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
            'satuan_gaji'        => 'required|in:Harian,Bulanan,Per Jam',
            'gaji_pokok'         => 'required|numeric|min:0',
            'uang_makan'         => 'required|numeric|min:0',
            'uang_transport'     => 'required|numeric|min:0',
            'tanggal_mulai'      => 'nullable|date',
            'tanggal_selesai'    => 'nullable|date|after_or_equal:tanggal_mulai',
            'satuan_gaji_2'      => 'nullable|in:Harian,Bulanan,Per Jam',
            'gaji_pokok_2'       => 'nullable|numeric|min:0',
            'uang_makan_2'       => 'nullable|numeric|min:0',
            'uang_transport_2'   => 'nullable|numeric|min:0',
            'tanggal_mulai_2'    => 'nullable|date',
            'tanggal_selesai_2'  => 'nullable|date|after_or_equal:tanggal_mulai_2',
        ]);

        $karyawan = Karyawan::findOrFail($id);
        $karyawan->update([
            'satuan_gaji'        => $request->satuan_gaji ?? 'Harian',
            'gaji_pokok'         => $request->gaji_pokok,
            'uang_makan'         => $request->uang_makan,
            'uang_transport'     => $request->uang_transport,
            'tanggal_mulai'      => $request->tanggal_mulai,
            'tanggal_selesai'    => $request->tanggal_selesai,
            'satuan_gaji_2'      => $request->satuan_gaji_2 ?? ($request->satuan_gaji ?? 'Harian'),
            'gaji_pokok_2'       => $request->gaji_pokok_2,
            'uang_makan_2'       => $request->uang_makan_2,
            'uang_transport_2'   => $request->uang_transport_2,
            'tanggal_mulai_2'    => $request->tanggal_mulai_2,
            'tanggal_selesai_2'  => $request->tanggal_selesai_2,
        ]);

        // Otomatis sinkronkan data penggajian yang berstatus draft/belum approved untuk karyawan ini
        $draftPayrolls = \App\Models\Penggajian::where('karyawan_id', $karyawan->id)
            ->where('status', '!=', 'approved')
            ->where('status_jurnal', false)
            ->get();

        foreach ($draftPayrolls as $dp) {
            $pNum = (int)($dp->pilihan_periode ?? 1);
            if ($pNum === 2 && $karyawan->gaji_pokok_2 !== null) {
                $gp = floatval($karyawan->gaji_pokok_2);
                $um = floatval($karyawan->uang_makan_2);
                $ut = floatval($karyawan->uang_transport_2);
                $sat = $karyawan->satuan_gaji_2 ?? $karyawan->satuan_gaji ?? 'Harian';
            } else {
                $gp = floatval($karyawan->gaji_pokok);
                $um = floatval($karyawan->uang_makan);
                $ut = floatval($karyawan->uang_transport);
                $sat = $karyawan->satuan_gaji ?? 'Harian';
            }
            $tar = $gp + $um + $ut;
            $hk = floatval($dp->hari_kerja ?? 0);
            $gu = ($sat === 'Bulanan') ? $tar : ($hk * $tar);

            $bTarget = intval($dp->banyak_target ?? 0);
            $bMerah = intval($dp->banyak_tanggal_merah ?? 0);
            $bonusTarget = ($sat === 'Harian' && $bTarget > 0) ? ($bTarget * $tar) : floatval($dp->bonus_target ?? 0);
            $bonusMerah = ($sat === 'Harian' && $bMerah > 0) ? ($bMerah * $tar) : floatval($dp->bonus_tanggal_merah ?? 0);

            $lembur = floatval($dp->lembur ?? 0);
            $bonusBday = floatval($dp->bonus_birthday ?? 0);
            $depKembali = floatval($dp->pengembalian_deposit ?? 0);
            $bonusDll = floatval($dp->bonus_dll ?? 0);
            $totalBonus = $lembur + $bonusTarget + $bonusMerah + $bonusBday + $depKembali + $bonusDll;

            $totalEarnings = $gu + $totalBonus;
            $totalDeductions = floatval($dp->total_deductions ?? (
                floatval($dp->potongan_terlambat ?? 0) + floatval($dp->potongan_inventaris ?? 0) + 
                floatval($dp->potongan_kasbon ?? 0) + floatval($dp->potongan_deposit ?? 0) + floatval($dp->potongan_dll ?? 0)
            ));
            $thp = $totalEarnings - $totalDeductions;

            $dp->update([
                'satuan_gaji'        => $sat,
                'satuan_gaji_2'      => $karyawan->satuan_gaji_2 ?? $sat,
                'gaji_pokok'         => $gp,
                'tunjangan_makan'    => $um,
                'tunjangan_transport'=> $ut,
                'tarif_harian_total' => $tar,
                'gaji_utama'         => $gu,
                'bonus_target'       => $bonusTarget,
                'bonus_tanggal_merah'=> $bonusMerah,
                'total_earnings'     => $totalEarnings,
                'total_deductions'   => $totalDeductions,
                'total_gaji_bersih'  => $thp,
            ]);
        }

        return redirect()->route('pengaturan-gaji.index', ['outlet' => $karyawan->outlet])
            ->with('success', "Pengaturan gaji ({$karyawan->satuan_gaji}) untuk {$karyawan->nama_karyawan} berhasil diperbarui.");
    }
}
