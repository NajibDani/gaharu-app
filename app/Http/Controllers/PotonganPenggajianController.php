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
            $totalPotDeposit = $items->sum('potongan_deposit');
            $totalPotDll = $items->sum('potongan_dll');

            $totalPotonganKeseluruhan = $totalPotTerlambat + $totalPotInventaris + $totalPotKasbon + $totalPotDeposit + $totalPotDll;

            return (object) [
                'id'                   => $primaryPayroll->id,
                'payroll_id'           => $primaryPayroll->id,
                'karyawan_id'          => $first->karyawan_id,
                'karyawan'             => $first->karyawan,
                'outlet'               => $first->outlet,
                'periode_bulan_tahun'  => $first->periode_bulan_tahun,
                'hari_kerja'           => $items->sum('hari_kerja') ?: ($primaryPayroll->hari_kerja ?? 0),
                'terlambat_sum'        => $terlambatMap[$first->karyawan_id] ?? 0,
                'potongan_terlambat'   => $totalPotTerlambat,
                'potongan_inventaris'  => $totalPotInventaris,
                'potongan_kasbon'      => $totalPotKasbon,
                'potongan_deposit'     => $totalPotDeposit,
                'potongan_dll'         => $totalPotDll,
                'catatan_potongan_dll' => $primaryPayroll->catatan_potongan_dll,
                'total_potongan'       => $totalPotonganKeseluruhan,
                'status'               => $primaryPayroll->status,
                'is_paid'              => $items->every(fn($p) => $p->status_jurnal || $p->status === 'approved'),
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
            'potongan_terlambat'   => 'nullable|string',
            'potongan_inventaris'  => 'nullable|string',
            'potongan_kasbon'      => 'nullable|string',
            'potongan_deposit'     => 'nullable|string',
            'potongan_dll'         => 'nullable|string',
            'catatan_potongan_dll' => 'nullable|string|max:255',
        ]);

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
        };

        $potonganTerlambat  = $cleanRupiah($request->potongan_terlambat);
        $potonganInventaris = $cleanRupiah($request->potongan_inventaris);
        $potonganKasbon     = $cleanRupiah($request->potongan_kasbon);
        $potonganDeposit    = $cleanRupiah($request->potongan_deposit);
        $potonganDll        = $cleanRupiah($request->potongan_dll);
        $catatanPotonganDll = $request->catatan_potongan_dll;

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDeposit + $potonganDll;

        $totalEarnings = floatval($payroll->total_earnings > 0 ? $payroll->total_earnings : (
            ($payroll->gaji_utama ?? 0) + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
            ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->pengembalian_deposit ?? 0) + ($payroll->bonus_dll ?? 0)
        ));

        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $payroll->update([
            'potongan_terlambat'   => $potonganTerlambat,
            'potongan_inventaris'  => $potonganInventaris,
            'potongan_kasbon'      => $potonganKasbon,
            'potongan_deposit'     => $potonganDeposit,
            'potongan_dll'         => $potonganDll,
            'catatan_potongan_dll' => $catatanPotonganDll,
            'total_deductions'     => $totalDeductions,
            'total_gaji_bersih'    => $totalGajiBersih,
        ]);

        return redirect()->route('penggajian.potongan.periode', ['periode' => $payroll->periode_bulan_tahun, 'outlet' => $payroll->outlet ?? 'Gaharu'])
            ->with('success', "Data potongan karyawan {$payroll->karyawan->nama_karyawan} berhasil diperbarui.");
    }

    /**
     * Simpan batch/massal potongan seluruh karyawan dari tabel langsung
     */
    public function batchUpdate(Request $request)
    {
        $items = $request->input('items', []);
        $periode = $request->input('periode');
        $outlet = $request->input('outlet', 'Gaharu');

        if (empty($items)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Tidak ada data yang dikirim.'], 400);
            }
            return redirect()->back()->with('error', 'Tidak ada data potongan yang dikirim.');
        }

        $cleanRupiah = function ($value) {
            if (is_null($value) || $value === '') return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string)$value));
        };

        $updatedCount = 0;

        foreach ($items as $item) {
            $payrollId = $item['id'] ?? null;
            if (!$payrollId) continue;

            $payroll = Penggajian::find($payrollId);
            if (!$payroll || $payroll->status === 'approved') continue;

            $potonganTerlambat  = isset($item['potongan_terlambat']) ? $cleanRupiah($item['potongan_terlambat']) : (float)$payroll->potongan_terlambat;
            $potonganInventaris = isset($item['potongan_inventaris']) ? $cleanRupiah($item['potongan_inventaris']) : (float)$payroll->potongan_inventaris;
            $potonganKasbon     = isset($item['potongan_kasbon']) ? $cleanRupiah($item['potongan_kasbon']) : (float)$payroll->potongan_kasbon;
            $potonganDeposit    = isset($item['potongan_deposit']) ? $cleanRupiah($item['potongan_deposit']) : (float)$payroll->potongan_deposit;
            $potonganDll        = isset($item['potongan_dll']) ? $cleanRupiah($item['potongan_dll']) : (float)$payroll->potongan_dll;
            $catatanPotonganDll = array_key_exists('catatan_potongan_dll', $item) ? $item['catatan_potongan_dll'] : $payroll->catatan_potongan_dll;

            $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDeposit + $potonganDll;

            $totalEarnings = floatval($payroll->total_earnings > 0 ? $payroll->total_earnings : (
                ($payroll->gaji_utama ?? 0) + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
                ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->pengembalian_deposit ?? 0) + ($payroll->bonus_dll ?? 0)
            ));

            $totalGajiBersih = $totalEarnings - $totalDeductions;

            $payroll->update([
                'potongan_terlambat'   => $potonganTerlambat,
                'potongan_inventaris'  => $potonganInventaris,
                'potongan_kasbon'      => $potonganKasbon,
                'potongan_deposit'     => $potonganDeposit,
                'potongan_dll'         => $potonganDll,
                'catatan_potongan_dll' => $catatanPotonganDll,
                'total_deductions'     => $totalDeductions,
                'total_gaji_bersih'    => $totalGajiBersih,
            ]);

            $updatedCount++;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan potongan untuk {$updatedCount} karyawan."
            ]);
        }

        return redirect()->route('penggajian.potongan.periode', ['periode' => $periode, 'outlet' => $outlet])
            ->with('success', "Seluruh potongan ({$updatedCount} karyawan) berhasil diperbarui secara bersamaan.");
    }
}
