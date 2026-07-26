<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\PengeluaranBahanBaku;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\MasterGudang;
use App\Models\MasterBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanPersediaanController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LAPORAN PEMBELIAN
    |--------------------------------------------------------------------------
    */
    public function pembelian(Request $request)
    {
        $suppliers = Supplier::orderBy('nama')->get();
        $gudangs   = MasterGudang::orderBy('nama')->get();

        $query = Pembelian::with(['supplier', 'gudang', 'details.barang'])
            ->orderBy('tanggal', 'desc');

        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->sampai);
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }
        if ($request->filled('metode_pembayaran')) {
            $query->where('metode_pembayaran', $request->metode_pembayaran);
        }

        $data = $query->get();

        $totalNilai     = $data->sum('total');
        $totalTransaksi = $data->count();
        $totalCod       = $data->where('metode_pembayaran', 'cod')->count();
        $totalTermin    = $data->where('metode_pembayaran', 'termin')->count();
        $totalDp        = $data->where('metode_pembayaran', 'dp')->count();
        $belumDicatat   = $data->whereNull('metode_pembayaran')->count();

        $bySupplier = $data->groupBy('supplier_id')->map(fn($g) => [
            'nama'  => $g->first()->supplier->nama ?? '-',
            'total' => $g->sum('total'),
            'count' => $g->count(),
        ])->values();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanpersediaan.pembelian-pdf', compact(
                'data', 'totalNilai', 'totalTransaksi', 'request'
            ));
            return $pdf->download('laporan-pembelian-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelPembelian($data, $request);
        }

        return view('laporanpersediaan.pembelian', compact(
            'data', 'suppliers', 'gudangs',
            'totalNilai', 'totalTransaksi',
            'totalCod', 'totalTermin', 'totalDp', 'belumDicatat',
            'bySupplier', 'request'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | LAPORAN POSISI STOK GUDANG
    |--------------------------------------------------------------------------
    */
    public function stokGudang(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        // Auto filter warehouse based on role
        if ($roleName === 'Kepala Outlet Kejingga') {
            $request->merge(['gudang_id' => 4]);
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $request->merge(['gudang_id' => 2]);
        } elseif ($roleName === 'Kepala Gudang') {
            $request->merge(['gudang_id' => 1]);
        }

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::orderBy('nama')->get();
        }
        $kategoris = DB::table('kategori')->orderBy('nama')->get();

        $query = StokGudang::with(['barang.kategori', 'gudang'])
            ->join('master_barang', 'stok_gudang.barang_id', '=', 'master_barang.id')
            ->join('master_gudang', 'stok_gudang.gudang_id', '=', 'master_gudang.id')
            ->select(
                'stok_gudang.*',
                'master_barang.nama as nama_barang',
                'master_barang.kode_barang',
                'master_barang.satuan',
                'master_barang.is_bahan_baku',
                'master_barang.is_barang_jadi',
                'master_barang.is_operational',
                'master_gudang.nama as nama_gudang',
                'master_barang.minimum_stock' // <-- Tambahan agar kolom ini bisa dibaca
            )
            ->orderBy('master_barang.nama');

        if ($request->filled('gudang_id')) {
            $query->where('stok_gudang.gudang_id', $request->gudang_id);
        }
        if ($request->filled('kategori_id')) {
            $query->where('master_barang.kategori_id', $request->kategori_id);
        }
        if ($request->filled('jenis_utama')) {
            $kolom = match($request->jenis_utama) {
                'bahan_baku'  => 'master_barang.is_bahan_baku',
                'barang_jadi' => 'master_barang.is_barang_jadi',
                'operational' => 'master_barang.is_operational',
                default       => null,
            };
            if ($kolom) {
                $query->where($kolom, true);
            }
        }

        // --- MENGAMBIL DATA UTAMA (TABEL) ---
        $data = $query->get();
        $totalItem = $data->count();

        // --- MENGAMBIL 5 DATA STOK KRITIS ---
        // (Menggantikan stokHabis dan stokAda)
        $queryKritis = clone $query; 
        $stokKritis = $queryKritis->whereNotNull('master_barang.minimum_stock')
                                  ->whereColumn('stok_gudang.jumlah', '<=', 'master_barang.minimum_stock')
                                  ->take(5)
                                  ->get();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanpersediaan.stock-gudang-pdf', compact('data', 'request'));
            return $pdf->download('laporan-stock-gudang-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelStok($data, $request);
        }

        $hasPurchaseAccess = in_array($roleName, ['Kepala Outlet Gaharu', 'Kepala Gudang', 'Direktur Keuangan', 'Super Admin', 'Administrator']);

        // --- PASSING DATA KE VIEW ---
        // Menghapus stokHabis & stokAda, memasukkan stokKritis
        return view('laporanpersediaan.stock-gudang', compact(
            'data', 'gudangs', 'kategoris',
            'totalItem', 'stokKritis',
            'request', 'hasPurchaseAccess'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | LAPORAN PENGELUARAN BAHAN BAKU
    |--------------------------------------------------------------------------
    */
    public function pengeluaranBahanBaku(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        // Auto filter warehouse based on role
        if ($roleName === 'Kepala Outlet Kejingga') {
            $request->merge(['gudang_id' => 4]);
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $request->merge(['gudang_id' => 2]);
        } elseif ($roleName === 'Kepala Gudang') {
            $request->merge(['gudang_id' => 1]);
        }

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::orderBy('nama')->get();
        }

        $query = PengeluaranBahanBaku::with(['gudang', 'details.barang'])
            ->orderBy('tanggal', 'desc');

        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->sampai);
        }
        if ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $data = $query->get();

        $totalTransaksi = $data->count();
        $totalNilaiHpp  = $data->sum(fn($d) => $d->details->sum('hpp_total'));
        $totalQty       = $data->sum(fn($d) => $d->details->sum('qty'));
        $totalApproved  = $data->where('status', 'approved')->count();

        $topBahan = $data->flatMap->details
            ->groupBy('barang_id')
            ->map(fn($g) => [
                'nama'  => $g->first()->barang->nama ?? '-',
                'qty'   => $g->sum('qty'),
                'nilai' => $g->sum('hpp_total'),
            ])
            ->sortByDesc('qty')
            ->take(5)
            ->values();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanpersediaan.pengeluaran-bahan-baku-pdf', compact('data', 'request'));
            return $pdf->download('laporan-pengeluaran-bb-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelPengeluaran($data, $request);
        }
// Siapkan data detail untuk modal JavaScript
$detailData = $data->mapWithKeys(function ($row) {
    return [$row->id => [
        'kode'    => $row->kode_pengeluaran,
        'tanggal' => \Carbon\Carbon::parse($row->tanggal)->format('d M Y'),
        'gudang'  => $row->gudang->nama ?? '-',
        'status'  => $row->status,
        'total'   => $row->details->sum('hpp_total'),
        'items'   => $row->details->map(function ($d) {
            return [
                'nama'   => $d->barang->nama ?? '-',
                'qty'    => $d->qty,
                'satuan' => $d->satuan,
                'hpp'    => $d->hpp_total,
            ];
        })->values(),
    ]];
});

return view('laporanpersediaan.pengeluaran-bahan-baku', compact(
    'data', 'gudangs',
    'totalTransaksi', 'totalNilaiHpp', 'totalQty', 'totalApproved',
    'topBahan', 'detailData', 'request'  // ← tambah detailData
));
        return view('laporanpersediaan.pengeluaran-bahan-baku', compact(
            'data', 'gudangs',
            'totalTransaksi', 'totalNilaiHpp', 'totalQty', 'totalApproved',
            'topBahan', 'request'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | LAPORAN STOCK OPNAME
    |--------------------------------------------------------------------------
    */
    public function stockOpname(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        // Auto filter warehouse based on role
        if ($roleName === 'Kepala Outlet Kejingga') {
            $request->merge(['gudang_id' => 4]);
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $request->merge(['gudang_id' => 2]);
        } elseif ($roleName === 'Kepala Gudang') {
            $request->merge(['gudang_id' => 1]);
        }

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::orderBy('nama')->get();
        }

        $query = StockOpname::with(['gudang', 'user', 'details.barang'])
            ->orderBy('tanggal', 'desc');

        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->sampai);
        }
        if ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $data = $query->get();

        $totalOpname       = $data->count();
        $totalSelisih      = $data->sum(fn($d) => $d->details->sum(fn($r) => abs($r->selisih)));
        $totalNilaiSelisih = $data->sum(fn($d) => $d->details->sum('nilai_selisih'));
        $totalApproved     = $data->where('status', 'approved')->count();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanpersediaan.stock-opname-pdf', compact('data', 'request'));
            return $pdf->download('laporan-stock-opname-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelOpname($data, $request);
        }

        return view('laporanpersediaan.stock-opname', compact(
            'data', 'gudangs',
            'totalOpname', 'totalSelisih', 'totalNilaiSelisih', 'totalApproved',
            'request'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL (CSV)
    |--------------------------------------------------------------------------
    */
    private function exportExcelPembelian($data, $request)
    {
        $filename = 'laporan-pembelian-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode', 'Tanggal', 'Supplier', 'Gudang', 'Total', 'Metode Bayar', 'Jatuh Tempo', 'Status Lunas', 'Tanggal Pelunasan', 'Dicatat Pada']);
            foreach ($data as $row) {
                $statusLunas = $row->is_lunas ? 'Lunas' : 'Belum Lunas';
                $tglPelunasan = $row->is_lunas && $row->lunas_at 
                    ? Carbon::parse($row->lunas_at)->format('d/m/Y') 
                    : ($row->tanggal_pelunasan ? Carbon::parse($row->tanggal_pelunasan)->format('d/m/Y') : '-');

                fputcsv($f, [
                    $row->kode_pembelian,
                    Carbon::parse($row->tanggal)->format('d/m/Y'),
                    $row->supplier->nama ?? '-',
                    $row->gudang->nama ?? '-',
                    $row->total,
                    strtoupper($row->metode_pembayaran ?? 'Belum dicatat'),
                    ($row->tanggal_jatuh_tempo ?? $row->tanggal_pelunasan) ? Carbon::parse($row->tanggal_jatuh_tempo ?? $row->tanggal_pelunasan)->format('d/m/Y') : '-',
                    $statusLunas,
                    $tglPelunasan,
                    $row->dicatat_pada ? Carbon::parse($row->dicatat_pada)->format('d/m/Y H:i:s') : '-',
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelStok($data, $request)
    {
        $filename = 'laporan-stock-gudang-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode Barang', 'Nama Barang', 'Jenis', 'Satuan', 'Gudang', 'Stok', 'Status']);
            foreach ($data as $row) {
                $jenis  = $row->is_bahan_baku  ? 'Bahan Baku'
                        : ($row->is_barang_jadi ? 'Barang Jadi'
                        : ($row->is_operational ? 'Operational' : '-'));
                $status = $row->jumlah == 0 ? 'Habis' : 'Tersedia';
                fputcsv($f, [
                    $row->kode_barang,
                    $row->nama_barang,
                    $jenis,
                    $row->satuan,
                    $row->nama_gudang,
                    $row->jumlah,
                    $status,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelPengeluaran($data, $request)
    {
        $filename = 'laporan-pengeluaran-bb-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode', 'Tanggal', 'Gudang', 'Status', 'Total HPP', 'Keterangan']);
            foreach ($data as $row) {
                fputcsv($f, [
                    $row->kode_pengeluaran,
                    Carbon::parse($row->tanggal)->format('d/m/Y'),
                    $row->gudang->nama ?? '-',
                    ucfirst($row->status),
                    $row->details->sum('hpp_total'),
                    $row->keterangan ?? '-',
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelOpname($data, $request)
    {
        $filename = 'laporan-stock-opname-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode Opname', 'Tanggal', 'Gudang', 'Status', 'Total Item', 'Total Selisih', 'Nilai Selisih']);
            foreach ($data as $row) {
                fputcsv($f, [
                    $row->kode_opname,
                    Carbon::parse($row->tanggal)->format('d/m/Y'),
                    $row->gudang->nama ?? '-',
                    ucfirst($row->status),
                    $row->details->count(),
                    $row->details->sum(fn($d) => abs($d->selisih)),
                    $row->details->sum('nilai_selisih'),
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}