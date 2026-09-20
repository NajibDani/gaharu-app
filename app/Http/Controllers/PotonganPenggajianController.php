<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Models\Karyawan;
use App\Models\Keterlambatan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PotonganPenggajianController extends Controller
{
    /**
     * Helper untuk mendapatkan outlet terpilih
     */
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

        return $request->input('outlet') ?? $request->query('outlet', 'Gaharu');
    }

    /**
     * Tampilan daftar periode untuk modul Potongan & Pengurangan
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $selectedOutlet = $this->getOutlet($request);

        $periodsQuery = Penggajian::select('periode_bulan_tahun')
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->groupBy('periode_bulan_tahun')
            ->orderBy('periode_bulan_tahun', 'desc');

        if ($search) {
            $periodsQuery->where(function($q) use ($search) {
                $q->where('periode_bulan_tahun', 'like', '%' . $search . '%')
                  ->orWhereHas('karyawan', function($kq) use ($search) {
                      $kq->where('nama_karyawan', 'like', '%' . $search . '%');
                  });
            });
        }

        $periods = $periodsQuery->paginate(10)->withQueryString();
        $periodNames = $periods->pluck('periode_bulan_tahun')->toArray();

        $payrolls = Penggajian::with('karyawan')
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->whereIn('periode_bulan_tahun', $periodNames)
            ->orderBy('periode_bulan_tahun', 'desc')
            ->get();

        return view('penggajian.potongan.index', compact('payrolls', 'periods', 'selectedOutlet'));
    }

    /**
     * Menampilkan daftar potongan karyawan per periode
     */
    public function showPeriode(Request $request, $periode = null): View
    {
        $targetPeriode = $periode ?? $request->query('periode');
        $selectedOutlet = $this->getOutlet($request);

        $rawPayrolls = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $targetPeriode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->get();

        $karyawanIds = $rawPayrolls->pluck('karyawan_id')->unique();
        $terlambatMap = Keterlambatan::whereIn('karyawan_id', $karyawanIds)
            ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$targetPeriode])
            ->selectRaw('karyawan_id, SUM(potongan) as total_potongan')
            ->groupBy('karyawan_id')
            ->pluck('total_potongan', 'karyawan_id');

        $payrolls = $rawPayrolls->groupBy('karyawan_id')->map(function ($items) use ($terlambatMap) {
            $first = $items->first();
            $primaryPayroll = $items->sortByDesc('hari_kerja')->first() ?? $first;

            $totalPotTerlambat = $items->sum('potongan_terlambat');
            $totalPotInventaris = $items->sum('potongan_inventaris');
            $totalPotKasbon = $items->sum('potongan_kasbon');
            $totalPotDll = $items->sum('potongan_dll');

            $totalPotonganKeseluruhan = $totalPotTerlambat + $totalPotInventaris + $totalPotKasbon + $totalPotDll;

            return (object) [
                'id'                  => $primaryPayroll->id,
                'payroll_id'          => $primaryPayroll->id,
                'karyawan_id'         => $first->karyawan_id,
                'karyawan'            => $first->karyawan,
                'outlet'              => $first->outlet,
                'periode_bulan_tahun' => $first->periode_bulan_tahun,
                'hari_kerja'          => $items->sum('hari_kerja') ?: ($primaryPayroll->hari_kerja ?? 0),
                'terlambat_sum'       => $terlambatMap[$first->karyawan_id] ?? 0,
                'potongan_terlambat'  => $totalPotTerlambat,
                'potongan_inventaris' => $totalPotInventaris,
                'potongan_kasbon'     => $totalPotKasbon,
                'potongan_dll'        => $totalPotDll,
                'total_potongan'      => $totalPotonganKeseluruhan,
                'status'              => $primaryPayroll->status,
                'is_paid'             => $items->every(fn($p) => $p->status_jurnal || $p->status === 'approved'),
            ];
        })->values();

        $currentStatus = $payrolls->isEmpty() ? 'draft' : $payrolls->first()->status;

        return view('penggajian.potongan.show-periode', compact('payrolls', 'targetPeriode', 'selectedOutlet', 'currentStatus'));
    }

    /**
     * Form edit potongan untuk karyawan tertentu
     */
    public function edit(Request $request, $id): View
    {
        $payroll = Penggajian::with('karyawan')->findOrFail($id);
        $targetPeriode = $payroll->periode_bulan_tahun;
        $selectedOutlet = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';

        // Ambil data keterlambatan untuk info
        $terlambatSum = Keterlambatan::where('karyawan_id', $payroll->karyawan_id)
            ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$targetPeriode])
            ->sum('potongan');

        return view('penggajian.potongan.edit', compact('payroll', 'targetPeriode', 'selectedOutlet', 'terlambatSum'));
    }

    /**
     * Simpan pembaruan potongan
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $payroll = Penggajian::findOrFail($id);

        if ($payroll->status === 'approved') {
            return redirect()->back()->with('error', 'Perubahan ditolak karena periode sudah disetujui (Approved).');
        }

        $request->validate([
            'potongan_terlambat'  => 'nullable|string',
            'potongan_inventaris' => 'nullable|string',
            'potongan_kasbon'     => 'nullable|string',
            'potongan_dll'        => 'nullable|string',
        ]);

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
        };

        $potonganTerlambat  = $cleanRupiah($request->potongan_terlambat);
        $potonganInventaris = $cleanRupiah($request->potongan_inventaris);
        $potonganKasbon     = $cleanRupiah($request->potongan_kasbon);
        $potonganDll        = $cleanRupiah($request->potongan_dll);

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDll;

        $totalEarnings = floatval($payroll->total_earnings > 0 ? $payroll->total_earnings : (
            ($payroll->gaji_utama ?? 0) + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
            ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->bonus_dll ?? 0)
        ));

        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $payroll->update([
            'potongan_terlambat'  => $potonganTerlambat,
            'potongan_inventaris' => $potonganInventaris,
            'potongan_kasbon'     => $potonganKasbon,
            'potongan_dll'        => $potonganDll,
            'total_deductions'    => $totalDeductions,
            'total_gaji_bersih'   => $totalGajiBersih,
        ]);

        return redirect()->route('penggajian.potongan.periode', ['periode' => $payroll->periode_bulan_tahun, 'outlet' => $payroll->outlet ?? 'Gaharu'])
            ->with('success', "Data potongan karyawan {$payroll->karyawan->nama_karyawan} berhasil diperbarui.");
    }
}
