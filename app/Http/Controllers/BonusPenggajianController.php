<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class BonusPenggajianController extends Controller
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
     * Tampilan daftar periode untuk modul Bonus & Lembur
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

        return view('penggajian.bonus.index', compact('payrolls', 'periods', 'selectedOutlet'));
    }

    /**
     * Menampilkan daftar bonus & lembur karyawan per periode
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

        $hasPotDeposit = \Illuminate\Support\Facades\Schema::hasColumn('penggajian', 'potongan_deposit');
        $hasRetDeposit = \Illuminate\Support\Facades\Schema::hasColumn('penggajian', 'pengembalian_deposit');

        $allPotonganDeposit = $hasPotDeposit
            ? Penggajian::whereIn('karyawan_id', $karyawanIds)
                ->groupBy('karyawan_id')
                ->selectRaw('karyawan_id, SUM(potongan_deposit) as total_pot_deposit')
                ->pluck('total_pot_deposit', 'karyawan_id')
            : collect();

        $allReturnedOther = $hasRetDeposit
            ? Penggajian::whereIn('karyawan_id', $karyawanIds)
                ->where('periode_bulan_tahun', '!=', $targetPeriode)
                ->groupBy('karyawan_id')
                ->selectRaw('karyawan_id, SUM(pengembalian_deposit) as total_ret_deposit')
                ->pluck('total_ret_deposit', 'karyawan_id')
            : collect();

        $payrolls = $rawPayrolls->groupBy('karyawan_id')->map(function ($items) use ($allPotonganDeposit, $allReturnedOther) {
            $first = $items->first();
            $primaryPayroll = $items->sortByDesc('hari_kerja')->first() ?? $first;

            $totalJamLembur = $items->sum('jam_lembur');
            $totalLembur = $items->sum('lembur');
            $totalBanyakTarget = $items->sum('banyak_target');
            $totalBonusTarget = $items->sum('bonus_target');
            $totalBanyakMerah = $items->sum('banyak_tanggal_merah');
            $totalBonusMerah = $items->sum('bonus_tanggal_merah');
            $totalBanyakBirthday = $items->sum('banyak_birthday_service');
            $totalBonusBirthday = $items->sum('bonus_birthday');
            $totalPengembalianDeposit = $items->sum('pengembalian_deposit');
            $totalBonusDll = $items->sum('bonus_dll');

            $totalBonusKeseluruhan = $totalLembur + $totalBonusTarget + $totalBonusMerah + $totalBonusBirthday + $totalPengembalianDeposit + $totalBonusDll;

            $tarifHarian = $primaryPayroll->tarif_harian_total > 0
                ? $primaryPayroll->tarif_harian_total
                : (($primaryPayroll->gaji_pokok ?? 0) + ($primaryPayroll->tunjangan_makan ?? 0) + ($primaryPayroll->tunjangan_transport ?? 0));

            $potDeposit = (float) ($allPotonganDeposit[$first->karyawan_id] ?? 0);
            $retOther = (float) ($allReturnedOther[$first->karyawan_id] ?? 0);
            $saldoDeposit = max(0, $potDeposit - $retOther);

            return (object) [
                'id'                          => $primaryPayroll->id,
                'payroll_id'                  => $primaryPayroll->id,
                'karyawan_id'                 => $first->karyawan_id,
                'karyawan'                    => $first->karyawan,
                'outlet'                      => $first->outlet,
                'satuan_gaji'                 => $primaryPayroll->satuan_gaji ?? $first->karyawan->satuan_gaji ?? 'Harian',
                'periode_bulan_tahun'         => $first->periode_bulan_tahun,
                'hari_kerja'                  => $items->sum('hari_kerja') ?: ($primaryPayroll->hari_kerja ?? 0),
                'tarif_harian_total'          => $tarifHarian,
                'jam_lembur'                  => $totalJamLembur,
                'lembur'                      => $totalLembur,
                'banyak_target'               => $totalBanyakTarget,
                'bonus_target'                => $totalBonusTarget,
                'catatan_bonus_target'        => $primaryPayroll->catatan_bonus_target,
                'banyak_tanggal_merah'        => $totalBanyakMerah,
                'bonus_tanggal_merah'         => $totalBonusMerah,
                'catatan_bonus_tanggal_merah' => $primaryPayroll->catatan_bonus_tanggal_merah,
                'banyak_birthday_service'     => $totalBanyakBirthday,
                'bonus_birthday'              => $totalBonusBirthday,
                'pengembalian_deposit'        => $totalPengembalianDeposit,
                'saldo_deposit'               => $saldoDeposit,
                'bonus_dll'                   => $totalBonusDll,
                'total_bonus'                 => $totalBonusKeseluruhan,
                'status'                      => $primaryPayroll->status,
                'is_paid'                     => $items->every(fn($p) => $p->status_jurnal || $p->status === 'approved'),
            ];
        })->values();

        $currentStatus = $payrolls->isEmpty() ? 'draft' : $payrolls->first()->status;

        return view('penggajian.bonus.show-periode', compact('payrolls', 'targetPeriode', 'selectedOutlet', 'currentStatus'));
    }

    /**
     * Form edit bonus untuk karyawan tertentu
     */
    public function edit(Request $request, $id): View
    {
        $payroll = Penggajian::with('karyawan')->findOrFail($id);
        $targetPeriode = $payroll->periode_bulan_tahun;
        $selectedOutlet = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';

        $tarifHarian = $payroll->tarif_harian_total > 0
            ? $payroll->tarif_harian_total
            : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));

        $hasPotDeposit = \Illuminate\Support\Facades\Schema::hasColumn('penggajian', 'potongan_deposit');
        $hasRetDeposit = \Illuminate\Support\Facades\Schema::hasColumn('penggajian', 'pengembalian_deposit');

        $totalPotDeposit = $hasPotDeposit ? Penggajian::where('karyawan_id', $payroll->karyawan_id)->sum('potongan_deposit') : 0;
        $totalRetOther = $hasRetDeposit ? Penggajian::where('karyawan_id', $payroll->karyawan_id)->where('id', '!=', $payroll->id)->sum('pengembalian_deposit') : 0;
        $saldoDeposit = max(0, $totalPotDeposit - $totalRetOther);

        return view('penggajian.bonus.edit', compact('payroll', 'targetPeriode', 'selectedOutlet', 'tarifHarian', 'saldoDeposit'));
    }

    /**
     * Simpan pembaruan bonus
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $payroll = Penggajian::findOrFail($id);

        if ($payroll->status === 'approved') {
            return redirect()->back()->with('error', 'Perubahan ditolak karena periode sudah disetujui (Approved).');
        }

        $satuanGaji = $payroll->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian';

        $request->validate([
            'jam_lembur'                  => 'nullable|numeric|min:0',
            'banyak_target'               => 'nullable|integer|min:0',
            'banyak_tanggal_merah'        => 'nullable|integer|min:0',
            'manual_bonus_target'         => 'nullable|string',
            'manual_bonus_tanggal_merah'  => 'nullable|string',
            'catatan_bonus_target'        => 'nullable|string|max:255',
            'catatan_bonus_tanggal_merah' => 'nullable|string|max:255',
            'banyak_birthday_service'     => 'nullable|integer|min:0',
            'pengembalian_deposit'        => 'nullable|string',
            'bonus_dll'                   => 'nullable|string',
        ]);

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
        };

        $tarifHarian = $payroll->tarif_harian_total > 0
            ? $payroll->tarif_harian_total
            : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));

        $jamLembur             = floatval($request->jam_lembur ?? 0);
        $banyakTarget          = intval($request->banyak_target ?? 0);
        $banyakTanggalMerah    = intval($request->banyak_tanggal_merah ?? 0);
        $banyakBirthdayService = intval($request->banyak_birthday_service ?? 0);
        $pengembalianDeposit   = $cleanRupiah($request->pengembalian_deposit);
        $bonusDll              = $cleanRupiah($request->bonus_dll);

        $lembur               = $jamLembur * 10000;
        $bonusBirthdayService = $banyakBirthdayService * 5000;

        if ($satuanGaji === 'Harian') {
            $bonusTarget         = $banyakTarget * $tarifHarian;
            $bonusTanggalMerah   = $banyakTanggalMerah * $tarifHarian;
            $catatanTarget       = null;
            $catatanTanggalMerah = null;
        } else {
            // Untuk Bulanan dan Per Jam: bonus target dan tgl merah diinput manual nominal rupiah & rincian catatan
            $bonusTarget         = $request->filled('manual_bonus_target') ? $cleanRupiah($request->manual_bonus_target) : ($banyakTarget > 0 ? $banyakTarget * $tarifHarian : $cleanRupiah($request->bonus_target ?? 0));
            $bonusTanggalMerah   = $request->filled('manual_bonus_tanggal_merah') ? $cleanRupiah($request->manual_bonus_tanggal_merah) : ($banyakTanggalMerah > 0 ? $banyakTanggalMerah * $tarifHarian : $cleanRupiah($request->bonus_tanggal_merah ?? 0));
            $catatanTarget       = $request->catatan_bonus_target;
            $catatanTanggalMerah = $request->catatan_bonus_tanggal_merah;
        }

        $gajiUtama = floatval($payroll->gaji_utama ?? 0);
        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $pengembalianDeposit + $bonusDll;

        $totalDeductions = floatval($payroll->total_deductions ?? 0);
        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $payroll->update([
            'jam_lembur'                  => $jamLembur,
            'lembur'                      => $lembur,
            'banyak_target'               => $banyakTarget,
            'bonus_target'                => $bonusTarget,
            'catatan_bonus_target'        => $catatanTarget,
            'banyak_tanggal_merah'        => $banyakTanggalMerah,
            'bonus_tanggal_merah'         => $bonusTanggalMerah,
            'catatan_bonus_tanggal_merah' => $catatanTanggalMerah,
            'banyak_birthday_service'     => $banyakBirthdayService,
            'bonus_birthday'              => $bonusBirthdayService,
            'pengembalian_deposit'        => $pengembalianDeposit,
            'bonus_dll'                   => $bonusDll,
            'total_earnings'              => $totalEarnings,
            'total_gaji_bersih'           => $totalGajiBersih,
        ]);

        return redirect()->route('penggajian.bonus.periode', ['periode' => $payroll->periode_bulan_tahun, 'outlet' => $payroll->outlet ?? 'Gaharu'])
            ->with('success', "Data bonus & lembur karyawan {$payroll->karyawan->nama_karyawan} berhasil diperbarui.");
    }

    /**
     * Simpan batch/massal bonus & lembur seluruh karyawan dari tabel langsung
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
            return redirect()->back()->with('error', 'Tidak ada data bonus yang dikirim.');
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

            $satuanGaji = $payroll->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian';
            $tarifHarian = $payroll->tarif_harian_total > 0
                ? $payroll->tarif_harian_total
                : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));

            $jamLembur             = floatval($item['jam_lembur'] ?? $payroll->jam_lembur ?? 0);
            $banyakTarget          = intval($item['banyak_target'] ?? $payroll->banyak_target ?? 0);
            $banyakTanggalMerah    = intval($item['banyak_tanggal_merah'] ?? $payroll->banyak_tanggal_merah ?? 0);
            $banyakBirthdayService = intval($item['banyak_birthday_service'] ?? $payroll->banyak_birthday_service ?? 0);
            $pengembalianDeposit   = isset($item['pengembalian_deposit']) ? $cleanRupiah($item['pengembalian_deposit']) : (float)$payroll->pengembalian_deposit;
            $bonusDll              = isset($item['bonus_dll']) ? $cleanRupiah($item['bonus_dll']) : (float)$payroll->bonus_dll;

            $lembur               = $jamLembur * 10000;
            $bonusBirthdayService = $banyakBirthdayService * 5000;

            if ($satuanGaji === 'Harian') {
                $bonusTarget         = $banyakTarget * $tarifHarian;
                $bonusTanggalMerah   = $banyakTanggalMerah * $tarifHarian;
                $catatanTarget       = null;
                $catatanTanggalMerah = null;
            } else {
                $bonusTarget         = isset($item['bonus_target']) ? $cleanRupiah($item['bonus_target']) : ($banyakTarget > 0 ? $banyakTarget * $tarifHarian : (float)$payroll->bonus_target);
                $bonusTanggalMerah   = isset($item['bonus_tanggal_merah']) ? $cleanRupiah($item['bonus_tanggal_merah']) : ($banyakTanggalMerah > 0 ? $banyakTanggalMerah * $tarifHarian : (float)$payroll->bonus_tanggal_merah);
                $catatanTarget       = $item['catatan_bonus_target'] ?? $payroll->catatan_bonus_target;
                $catatanTanggalMerah = $item['catatan_bonus_tanggal_merah'] ?? $payroll->catatan_bonus_tanggal_merah;
            }

            $gajiUtama = floatval($payroll->gaji_utama ?? 0);
            $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $pengembalianDeposit + $bonusDll;

            $totalDeductions = floatval($payroll->total_deductions ?? 0);
            $totalGajiBersih = $totalEarnings - $totalDeductions;

            $payroll->update([
                'jam_lembur'                  => $jamLembur,
                'lembur'                      => $lembur,
                'banyak_target'               => $banyakTarget,
                'bonus_target'                => $bonusTarget,
                'catatan_bonus_target'        => $catatanTarget,
                'banyak_tanggal_merah'        => $banyakTanggalMerah,
                'bonus_tanggal_merah'         => $bonusTanggalMerah,
                'catatan_bonus_tanggal_merah' => $catatanTanggalMerah,
                'banyak_birthday_service'     => $banyakBirthdayService,
                'bonus_birthday'              => $bonusBirthdayService,
                'pengembalian_deposit'        => $pengembalianDeposit,
                'bonus_dll'                   => $bonusDll,
                'total_earnings'              => $totalEarnings,
                'total_gaji_bersih'           => $totalGajiBersih,
            ]);

            $updatedCount++;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan bonus & lembur untuk {$updatedCount} karyawan."
            ]);
        }

        return redirect()->route('penggajian.bonus.periode', ['periode' => $periode, 'outlet' => $outlet])
            ->with('success', "Seluruh bonus & lembur ({$updatedCount} karyawan) berhasil diperbarui secara bersamaan.");
    }
}
