<?php

namespace App\Http\Controllers;

use App\Models\Keterlambatan;
use App\Models\Karyawan;
use App\Models\Penggajian;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class KeterlambatanController extends Controller
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
     * TAMPILAN UTAMA DATA KETERLAMBATAN (Mirip Spreadsheet Gambar 1 & Gambar 2)
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $periode = $request->query('periode', date('Y-m'));
        $selectedOutlet = $this->getOutlet($request);

        $query = Keterlambatan::with('karyawan')
            ->whereHas('karyawan', function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet);
            });

        // Filter berdasarkan bulan & tahun (misal 2026-02)
        if ($periode) {
            $query->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
        }

        if ($search) {
            $query->whereHas('karyawan', function ($q) use ($search) {
                $q->where('nama_karyawan', 'like', '%' . $search . '%');
            });
        }

        $listKeterlambatan = $query->orderBy('karyawan_id', 'asc')
            ->orderBy('tanggal', 'asc')
            ->get();

        // Hitung Akumulasi Potongan Per Karyawan & Per Periode Gaji Spesifik
        $akumulasiPerKaryawanPeriode = [];
        $lastRowPerKaryawanPeriode   = [];
        $totalKejadian               = $listKeterlambatan->count();
        $totalPotonganBulanIni       = $listKeterlambatan->sum('potongan');

        foreach ($listKeterlambatan as $item) {
            $karyawan = $item->karyawan;
            $pInfo = $karyawan ? $karyawan->getPeriodeGajiLabelForDate($item->tanggal) : [
                'key'         => 'REG',
                'label'       => 'Reguler',
                'badge_class' => 'bg-light text-secondary border',
                'mulai'       => null,
                'selesai'     => null,
            ];
            $item->periode_info = $pInfo;

            $kId = $item->karyawan_id;
            $pKey = $pInfo['key'];

            if (!isset($akumulasiPerKaryawanPeriode[$kId])) {
                $akumulasiPerKaryawanPeriode[$kId] = [];
            }
            if (!isset($akumulasiPerKaryawanPeriode[$kId][$pKey])) {
                $akumulasiPerKaryawanPeriode[$kId][$pKey] = 0;
            }
            $akumulasiPerKaryawanPeriode[$kId][$pKey] += $item->potongan;

            $lastRowPerKaryawanPeriode[$kId][$pKey] = $item->id;
        }

        $karyawans = Karyawan::where('outlet', $selectedOutlet)->orderBy('nama_karyawan', 'asc')->get();

        // Daftar Periode Tersedia
        $periodes = Keterlambatan::selectRaw("DATE_FORMAT(tanggal, '%Y-%m') as periode")
            ->groupBy('periode')
            ->orderBy('periode', 'desc')
            ->pluck('periode')
            ->toArray();

        if (!in_array(date('Y-m'), $periodes)) {
            array_unshift($periodes, date('Y-m'));
        }

        return view('keterlambatan.index', compact(
            'listKeterlambatan',
            'karyawans',
            'periode',
            'periodes',
            'search',
            'akumulasiPerKaryawanPeriode',
            'lastRowPerKaryawanPeriode',
            'totalKejadian',
            'totalPotonganBulanIni',
            'selectedOutlet'
        ));
    }

    /**
     * MENYIMPAN DATA KETERLAMBATAN BARU
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'karyawan_id' => 'required|exists:karyawan,id',
            'tanggal'     => 'required|date',
            'shift'       => 'nullable|string|max:100',
            'jam_shift'   => 'required',
            'jam_datang'  => 'required',
            'keterangan'  => 'nullable|string',
        ]);

        $jamShift = strlen($request->jam_shift) == 5 ? $request->jam_shift . ':00' : $request->jam_shift;
        $jamDatang = strlen($request->jam_datang) == 5 ? $request->jam_datang . ':00' : $request->jam_datang;

        $kalkulasi = Keterlambatan::hitungPotongan($jamShift, $jamDatang);

        $karyawan = Karyawan::findOrFail($request->karyawan_id);
        $gajiHarian = $karyawan->getGajiHarianForDate($request->tanggal);
        
        $potongan = $kalkulasi['potongan'];
        if ($potongan > $gajiHarian) {
            $potongan = $gajiHarian;
        }

        $keterlambatan = Keterlambatan::create([
            'karyawan_id'  => $request->karyawan_id,
            'tanggal'      => $request->tanggal,
            'shift'        => $request->shift,
            'jam_shift'    => $jamShift,
            'jam_datang'   => $jamDatang,
            'durasi_menit' => $kalkulasi['durasi_menit'],
            'potongan'     => $potongan,
            'keterangan'   => $request->keterangan,
        ]);

        // Otomatis Sync / Linked ke Penggajian Karyawan
        $this->syncKePenggajian($request->karyawan_id, $request->tanggal);

        return redirect()->back()->with('success', "Data keterlambatan {$karyawan->nama_karyawan} berhasil dicatat (Potongan: Rp " . number_format($potongan, 0, ',', '.') . ") dan otomatis terhubung ke Penggajian.");
    }

    /**
     * UPDATE DATA KETERLAMBATAN
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'karyawan_id' => 'required|exists:karyawan,id',
            'tanggal'     => 'required|date',
            'shift'       => 'nullable|string|max:100',
            'jam_shift'   => 'required',
            'jam_datang'  => 'required',
            'keterangan'  => 'nullable|string',
        ]);

        $keterlambatan = Keterlambatan::findOrFail($id);
        $oldKaryawanId = $keterlambatan->karyawan_id;
        $oldTanggal    = $keterlambatan->tanggal;

        $jamShift = strlen($request->jam_shift) == 5 ? $request->jam_shift . ':00' : $request->jam_shift;
        $jamDatang = strlen($request->jam_datang) == 5 ? $request->jam_datang . ':00' : $request->jam_datang;

        $kalkulasi = Keterlambatan::hitungPotongan($jamShift, $jamDatang);

        $karyawan = Karyawan::findOrFail($request->karyawan_id);
        $gajiHarian = $karyawan->getGajiHarianForDate($request->tanggal);
        
        $potongan = $kalkulasi['potongan'];
        if ($potongan > $gajiHarian) {
            $potongan = $gajiHarian;
        }

        $keterlambatan->update([
            'karyawan_id'  => $request->karyawan_id,
            'tanggal'      => $request->tanggal,
            'shift'        => $request->shift,
            'jam_shift'    => $jamShift,
            'jam_datang'   => $jamDatang,
            'durasi_menit' => $kalkulasi['durasi_menit'],
            'potongan'     => $potongan,
            'keterangan'   => $request->keterangan,
        ]);

        // Sync ke penggajian karyawan baru & lama
        $this->syncKePenggajian($request->karyawan_id, $request->tanggal);
        if ($oldKaryawanId != $request->karyawan_id || $oldTanggal != $request->tanggal) {
            $this->syncKePenggajian($oldKaryawanId, $oldTanggal);
        }

        return redirect()->back()->with('success', 'Data keterlambatan berhasil diperbarui dan otomatis disinkronkan ke Penggajian.');
    }

    /**
     * HAPUS DATA KETERLAMBATAN
     */
    public function destroy($id): RedirectResponse
    {
        $keterlambatan = Keterlambatan::findOrFail($id);
        $karyawanId = $keterlambatan->karyawan_id;
        $tanggal = $keterlambatan->tanggal;

        $keterlambatan->delete();

        // Sync ke penggajian
        $this->syncKePenggajian($karyawanId, $tanggal);

        return redirect()->back()->with('success', 'Data keterlambatan berhasil dihapus dan potongan penggajian diperbarui.');
    }

    /**
     * AJAX HITUNG DURASI & POTONGAN SECARA LIVE
     */
    public function hitungAjax(Request $request): JsonResponse
    {
        $jamShift = $request->query('jam_shift');
        $jamDatang = $request->query('jam_datang');
        $karyawanId = $request->query('karyawan_id');
        $tanggal = $request->query('tanggal');

        if (!$jamShift || !$jamDatang) {
            return response()->json(['durasi_menit' => 0, 'potongan' => 0, 'potongan_formatted' => 'Rp 0']);
        }

        $res = Keterlambatan::hitungPotongan($jamShift, $jamDatang);
        
        if ($karyawanId && $tanggal) {
            $karyawan = Karyawan::find($karyawanId);
            if ($karyawan) {
                $gajiHarian = $karyawan->getGajiHarianForDate($tanggal);
                if ($res['potongan'] > $gajiHarian) {
                    $res['potongan'] = $gajiHarian;
                }
            }
        }
        
        $res['potongan_formatted'] = 'Rp ' . number_format($res['potongan'], 0, ',', '.');

        return response()->json($res);
    }

    /**
     * HELPER METHOD: Sinkronkan Akumulasi Potongan Keterlambatan ke Penggajian Karyawan secara terpisah per periode
     */
    private function syncKePenggajian($karyawanId, $tanggal): void
    {
        $carbonTgl = Carbon::parse($tanggal);
        $periodeStr = $carbonTgl->format('Y-m');

        // Cari semua record penggajian karyawan ini di bulan terkait
        $payrolls = Penggajian::where('karyawan_id', $karyawanId)
            ->where('periode_bulan_tahun', $periodeStr)
            ->get();

        foreach ($payrolls as $payroll) {
            if ($payroll->status === 'approved') continue;

            // Jika slip memiliki rentang tanggal_mulai dan tanggal_selesai
            if ($payroll->tanggal_mulai && $payroll->tanggal_selesai) {
                $pMulai = Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d');
                $pSelesai = Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d');

                // Hitung total potongan keterlambatan HANYA yang berada dalam rentang slip ini
                $potonganPeriode = Keterlambatan::where('karyawan_id', $karyawanId)
                    ->whereBetween('tanggal', [$pMulai, $pSelesai])
                    ->sum('potongan');
            } else {
                // Jika tidak ada batasan tanggal spesifik, ambil sebulan penuh
                $potonganPeriode = Keterlambatan::where('karyawan_id', $karyawanId)
                    ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periodeStr])
                    ->sum('potongan');
            }

            $totalDeductions = floatval($potonganPeriode) +
                               floatval($payroll->potongan_inventaris ?? 0) +
                               floatval($payroll->potongan_kasbon ?? 0) +
                               floatval($payroll->potongan_dll ?? 0);

            $totalEarnings = floatval($payroll->total_earnings ?? (
                ($payroll->gaji_utama ?? 0) + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
                ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->bonus_dll ?? 0)
            ));

            $totalGajiBersih = $totalEarnings - $totalDeductions;

            $payroll->update([
                'potongan_terlambat' => $potonganPeriode,
                'total_deductions'   => $totalDeductions,
                'total_gaji_bersih'  => $totalGajiBersih,
            ]);
        }
    }
}
