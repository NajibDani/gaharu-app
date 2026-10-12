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
        $gGaharuId = MasterGudang::where('nama', 'like', '%Gaharu%')->first()?->id ?? 3;
        $gKejinggaId = MasterGudang::where('nama', 'like', '%KeJingga%')->first()?->id ?? 5;
        $gUtamaId = MasterGudang::getGudangUtamaId();

        if ($roleName === 'Kepala Outlet Kejingga') {
            $request->merge(['gudang_id' => $gKejinggaId]);
            $gudangs = MasterGudang::with('divisi')->where('id', $gKejinggaId)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $request->merge(['gudang_id' => $gGaharuId]);
            $gudangs = MasterGudang::with('divisi')->where('id', $gGaharuId)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $request->merge(['gudang_id' => $gUtamaId]);
            $gudangs = MasterGudang::with('divisi')->where('id', $gUtamaId)->get();
        } else {
            $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        }
        $kategoris = DB::table('kategori')->orderBy('nama')->get();

        $query = StokGudang::with(['barang.kategori', 'gudang', 'divisi'])
            ->join('master_barang', 'stok_gudang.barang_id', '=', 'master_barang.id')
            ->join('master_gudang', 'stok_gudang.gudang_id', '=', 'master_gudang.id')
            ->leftJoin('gudang_divisi', 'stok_gudang.divisi_id', '=', 'gudang_divisi.id')
            ->select(
                'stok_gudang.*',
                'master_barang.nama as nama_barang',
                'master_barang.kode_barang',
                'master_barang.satuan',
                'master_barang.satuan_pembelian',
                'master_barang.konversi_pembelian',
                'master_barang.is_bahan_baku',
                'master_barang.is_bahan_setengah_jadi',
                'master_barang.is_barang_jadi',
                'master_barang.is_operational',
                'master_gudang.nama as nama_gudang',
                'gudang_divisi.nama as nama_divisi',
                'master_barang.minimum_stock',
                'master_barang.minimum_stock_ck',
                'master_barang.minimum_stock_kejingga',
                'master_barang.minimum_stock_gaharu'
            )
            ->orderBy('master_barang.nama');

        if ($request->filled('gudang_id')) {
            $query->where('stok_gudang.gudang_id', $request->gudang_id);
        }
        if ($request->filled('divisi_id')) {
            $query->where('stok_gudang.divisi_id', $request->divisi_id);
        }
        if ($request->filled('kategori_id')) {
            $query->where('master_barang.kategori_id', $request->kategori_id);
        }
        if ($request->filled('jenis_utama')) {
            $kolom = match($request->jenis_utama) {
                'bahan_baku'          => 'master_barang.is_bahan_baku',
                'bahan_setengah_jadi' => 'master_barang.is_bahan_setengah_jadi',
                'barang_jadi'         => 'master_barang.is_barang_jadi',
                'operational'         => 'master_barang.is_operational',
                default               => null,
            };
            if ($kolom) {
                $query->where($kolom, true);
            }
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('master_barang.nama', 'like', "%{$search}%")
                  ->orWhere('master_barang.kode_barang', 'like', "%{$search}%");
            });
        }

        // --- MENGAMBIL DATA UTAMA (TABEL) ---
        $data = $query->get();
        $totalItem = $data->count();

        // --- MENGAMBIL 5 DATA STOK KRITIS ---
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
            $gudangs = MasterGudang::with('divisi')->where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::with('divisi')->where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::with('divisi')->where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        }

        $query = PengeluaranBahanBaku::with(['gudang', 'divisi', 'details.barang'])
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
        if ($request->filled('divisi_id')) {
            $query->where('divisi_id', $request->divisi_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('jenis_pengeluaran')) {
            if ($request->jenis_pengeluaran === 'wasted') {
                $query->where(function ($q) {
                    $q->where('jenis_pengeluaran', 'wasted')
                      ->orWhere('kode_pengeluaran', 'like', 'PBK-WST-%');
                });
            } elseif ($request->jenis_pengeluaran === 'transfer') {
                $query->where(function ($q) {
                    $q->where(function ($sq) {
                        $sq->whereNull('jenis_pengeluaran')
                          ->orWhere('jenis_pengeluaran', 'transfer');
                    })
                    ->where('kode_pengeluaran', 'not like', 'PBK-SO-%')
                    ->where('kode_pengeluaran', 'not like', 'PBK-WST-%');
                });
            } elseif ($request->jenis_pengeluaran === 'opname') {
                $query->where('kode_pengeluaran', 'like', 'PBK-SO-%');
            }
        }

        $data = $query->get();

        $totalTransaksi = $data->count();
        $totalNilaiHpp  = $data->sum(function($d) {
            $isOpname = ($d->jenis_pengeluaran === 'stock_opname' || str_starts_with($d->kode_pengeluaran, 'PBK-SO-'));
            if ($isOpname) {
                $so = $d->findAssociatedStockOpname();
                if ($so) {
                    $map = [];
                    foreach ($so->details as $sod) $map[$sod->barang_id] = (float)$sod->selisih;
                    $net = 0;
                    foreach ($d->details as $det) {
                        $hpp = (float)($det->hpp_total ?? 0);
                        if (($map[$det->barang_id] ?? 0) > 0) {
                            $net -= $hpp;
                        } else {
                            $net += $hpp;
                        }
                    }
                    return $net;
                }
            }
            return $d->details->sum('hpp_total');
        });
        $totalQty       = $data->sum(fn($d) => $d->details->sum('qty'));
        $totalApproved  = $data->whereIn('status', ['approved', 'disetujui'])->count();

        $wastedData = $data->filter(fn($d) => $d->jenis_pengeluaran === 'wasted' || str_starts_with($d->kode_pengeluaran, 'PBK-WST-'));
        $totalWastedTransaksi = $wastedData->count();
        $totalWastedNilaiHpp  = $wastedData->sum(fn($d) => $d->details->sum('hpp_total'));
        $totalWastedQty       = $wastedData->sum(fn($d) => $d->details->sum('qty'));

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
                'divisi'  => $row->divisi->nama ?? '-',
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
            'totalWastedTransaksi', 'totalWastedNilaiHpp', 'totalWastedQty',
            'topBahan', 'detailData', 'request'
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
            $gudangs = MasterGudang::with('divisi')->where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::with('divisi')->where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::with('divisi')->where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        }

        $query = StockOpname::with(['gudang', 'divisi', 'user', 'details.barang'])
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
        if ($request->filled('divisi_id')) {
            $query->where('divisi_id', $request->divisi_id);
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
            fputcsv($f, ['Kode Barang', 'Nama Barang', 'Jenis', 'Satuan', 'Gudang', 'Divisi', 'Stok Utama', 'Satuan Pembelian', 'Faktor Konversi', 'Stok Konversi', 'Status']);
            foreach ($data as $row) {
                $jenis  = $row->is_bahan_baku  ? 'Bahan Baku'
                        : ($row->is_bahan_setengah_jadi ? 'Bahan Setengah Jadi'
                        : ($row->is_barang_jadi ? 'Barang Jadi'
                        : ($row->is_operational ? 'Operational' : '-')));
                $status = $row->jumlah == 0 ? 'Habis' : 'Tersedia';
                $konversi = (float) ($row->konversi_pembelian ?? 1);
                $hasKonversi = !empty($row->satuan_pembelian) && $konversi > 1;
                $stokKonv = $hasKonversi ? ($row->jumlah / $konversi) : '-';

                fputcsv($f, [
                    $row->kode_barang,
                    $row->nama_barang,
                    $jenis,
                    $row->satuan,
                    $row->nama_gudang,
                    $row->nama_divisi ?? '-',
                    $row->jumlah,
                    $row->satuan_pembelian ?? '-',
                    $hasKonversi ? $konversi : '-',
                    $stokKonv,
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

    /*
    |--------------------------------------------------------------------------
    | LAPORAN WASTED / PEMBOROSAN BAHAN BAKU
    |--------------------------------------------------------------------------
    */
    public function wasted(Request $request)
    {
        $roleName = auth()->user()->role->nama ?? '';

        $dari   = $request->filled('dari') ? $request->dari : date('Y-m-01');
        $sampai = $request->filled('sampai') ? $request->sampai : date('Y-m-d');

        // Master dropdowns
        $gudangs   = MasterGudang::with('divisi')->orderBy('nama')->get();
        $divisis   = \App\Models\GudangDivisi::with('gudang')->orderBy('nama')->get();
        $kategoris = \App\Models\Kategori::orderBy('nama')->get();

        $gudangId   = $request->gudang_id;
        $divisiId   = $request->divisi_id;
        $kategoriId = $request->kategori_id;
        $search     = $request->search;
        $status     = $request->filled('status') ? $request->status : 'approved'; // default approved

        // 1. Query Dokumen PBK Wasted
        $wastedQuery = PengeluaranBahanBaku::where(function($q) {
                $q->where('jenis_pengeluaran', 'wasted')
                  ->orWhere('kode_pengeluaran', 'like', 'PBK-WST-%');
            })
            ->whereBetween('tanggal', [$dari . ' 00:00:00', $sampai . ' 23:59:59'])
            ->with(['gudang', 'divisi', 'details.barang.kategori']);

        if ($status !== 'all') {
            if ($status === 'draft') {
                $wastedQuery->where('status', 'draft');
            } else {
                $wastedQuery->whereIn('status', ['approved', 'disetujui']);
            }
        }

        if ($gudangId) {
            $wastedQuery->where('gudang_id', $gudangId);
        }
        if ($divisiId) {
            $wastedQuery->where('divisi_id', $divisiId);
        }

        $wastedDocs = $wastedQuery->get();

        // 2. Agregasi Wasted per Barang
        $itemsMap = [];
        foreach ($wastedDocs as $doc) {
            $lokasiNama = ($doc->gudang->nama ?? 'Tanpa Gudang') . ($doc->divisi ? ' - ' . $doc->divisi->nama : '');
            
            foreach ($doc->details as $d) {
                $b = $d->barang;
                if (!$b) continue;

                // Filter kategori jika ada
                if ($kategoriId && $b->kategori_id != $kategoriId) {
                    continue;
                }

                // Filter search jika ada
                if ($search) {
                    $sLower = strtolower($search);
                    $matchName = str_contains(strtolower($b->nama ?? ''), $sLower);
                    $matchCode = str_contains(strtolower($b->kode_barang ?? ''), $sLower);
                    if (!$matchName && !$matchCode) {
                        continue;
                    }
                }

                $bId = $b->id;
                if (!isset($itemsMap[$bId])) {
                    $itemsMap[$bId] = [
                        'barang_id'            => $bId,
                        'kode_barang'          => $b->kode_barang ?? '-',
                        'nama_barang'          => $b->nama ?? 'Unknown',
                        'kategori'             => $b->kategori->nama ?? '-',
                        'satuan'               => $b->satuan ?: 'pcs',
                        'satuan_pembelian'     => $b->satuan_pembelian ?: $b->satuan,
                        'konversi_pembelian'   => (float)($b->konversi_pembelian ?: 1),
                        'total_qty_wasted'     => 0,
                        'total_nominal_wasted' => 0,
                        'alokasi'              => [],
                        'transaksi_list'       => [],
                    ];
                }

                $qty = (float) $d->qty;
                $nominal = (float) ($d->hpp_total ?: ($d->total_harga ?: ($qty * (float) $d->harga_satuan)));

                $itemsMap[$bId]['total_qty_wasted'] += $qty;
                $itemsMap[$bId]['total_nominal_wasted'] += $nominal;

                // Alokasi per lokasi (Gudang / Divisi)
                if (!isset($itemsMap[$bId]['alokasi'][$lokasiNama])) {
                    $itemsMap[$bId]['alokasi'][$lokasiNama] = [
                        'lokasi'    => $lokasiNama,
                        'gudang_id' => $doc->gudang_id,
                        'divisi_id' => $doc->divisi_id,
                        'qty'       => 0,
                        'nominal'   => 0,
                        'count'     => 0,
                    ];
                }
                $itemsMap[$bId]['alokasi'][$lokasiNama]['qty'] += $qty;
                $itemsMap[$bId]['alokasi'][$lokasiNama]['nominal'] += $nominal;
                $itemsMap[$bId]['alokasi'][$lokasiNama]['count']++;

                // Simpan transaksi untuk rincian modal
                $itemsMap[$bId]['transaksi_list'][] = [
                    'kode'         => $doc->kode_pengeluaran,
                    'tanggal'      => Carbon::parse($doc->tanggal)->format('d/m/Y H:i'),
                    'lokasi'       => $lokasiNama,
                    'qty'          => $qty,
                    'harga_satuan' => (float) ($d->harga_satuan ?: ($qty > 0 ? $nominal / $qty : 0)),
                    'nominal'      => $nominal,
                    'keterangan'   => $doc->keterangan ?: '-',
                ];
            }
        }

        // 3. Ambil data Pembelian untuk barang-barang ini
        $barangIds = array_keys($itemsMap);
        
        $pembelianQuery = DB::table('pembelian_detail as pd')
            ->join('pembelian as p', 'p.id', '=', 'pd.pembelian_id')
            ->whereBetween('p.tanggal', [$dari . ' 00:00:00', $sampai . ' 23:59:59']);

        if (!empty($barangIds)) {
            $pembelianQuery->whereIn('pd.barang_id', $barangIds);
        }

        $pembelianRows = $pembelianQuery->select(
            'pd.barang_id',
            'pd.qty',
            'pd.konversi_pembelian',
            'pd.harga',
            'pd.harga_per_qty'
        )->get();

        $pembelianAgg = [];
        foreach ($pembelianRows as $pr) {
            $bId = $pr->barang_id;
            if (!isset($pembelianAgg[$bId])) {
                $pembelianAgg[$bId] = [
                    'qty'     => 0,
                    'nominal' => 0,
                ];
            }
            $konv = (float) ($pr->konversi_pembelian ?: 1);
            $qtyDasar = (float) $pr->qty * $konv;
            $nom = (float) ($pr->harga ?: ($pr->qty * $pr->harga_per_qty));

            $pembelianAgg[$bId]['qty'] += $qtyDasar;
            $pembelianAgg[$bId]['nominal'] += $nom;
        }

        // 4. Hitung persentase wasted dan siapkan list akhir
        $items = collect($itemsMap)->map(function ($item) use ($pembelianAgg) {
            $bId = $item['barang_id'];
            $totalBeliQty = (float) ($pembelianAgg[$bId]['qty'] ?? 0);
            $totalBeliNom = (float) ($pembelianAgg[$bId]['nominal'] ?? 0);

            $item['total_qty_beli']     = $totalBeliQty;
            $item['total_nominal_beli'] = $totalBeliNom;

            $item['persen_qty'] = $totalBeliQty > 0
                ? round(($item['total_qty_wasted'] / $totalBeliQty) * 100, 2)
                : null;

            $item['persen_nominal'] = $totalBeliNom > 0
                ? round(($item['total_nominal_wasted'] / $totalBeliNom) * 100, 2)
                : null;

            return $item;
        })
        ->sortByDesc('total_nominal_wasted')
        ->values();

        // 5. Hitung Summary Cards / KPI
        $summary = [
            'total_items_wasted'   => $items->count(),
            'total_qty_wasted'     => $items->sum('total_qty_wasted'),
            'total_nominal_wasted' => $items->sum('total_nominal_wasted'),
            'total_nominal_beli'   => $items->sum('total_nominal_beli'),
            'global_persen_nominal'=> $items->sum('total_nominal_beli') > 0
                ? round(($items->sum('total_nominal_wasted') / $items->sum('total_nominal_beli')) * 100, 2)
                : 0,
            'total_dokumen'        => $wastedDocs->count(),
        ];

        // Format PDF
        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanpersediaan.wasted-pdf', compact('items', 'summary', 'request', 'dari', 'sampai'));
            return $pdf->download('laporan-wasted-' . now()->format('Ymd') . '.pdf');
        }

        // Format Excel / CSV
        if ($request->format === 'excel') {
            return $this->exportExcelWasted($items, $summary, $request);
        }

        return view('laporanpersediaan.wasted', compact(
            'items', 'summary', 'gudangs', 'divisis', 'kategoris',
            'dari', 'sampai', 'request'
        ));
    }

    public function wastedExportExcel(Request $request)
    {
        $request->merge(['format' => 'excel']);
        return $this->wasted($request);
    }

    public function wastedPdf(Request $request)
    {
        $request->merge(['format' => 'pdf']);
        return $this->wasted($request);
    }

    private function exportExcelWasted($items, $summary, $request)
    {
        $filename = 'laporan-wasted-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($items) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, [
                'No', 'Kode Barang', 'Nama Barang', 'Kategori', 'Satuan Dasar',
                'Total Qty Wasted', 'Total Nilai Wasted (Rp)', 'Alokasi Outlet & Divisi',
                'Total Qty Beli', 'Total Nilai Beli (Rp)', '% Wasted (Qty)', '% Wasted (Nilai)'
            ]);

            $no = 1;
            foreach ($items as $item) {
                $alokasiStr = collect($item['alokasi'])->map(function($a) use ($item) {
                    return $a['lokasi'] . ': ' . number_format($a['qty'], 2, ',', '.') . ' ' . $item['satuan'] . ' (Rp ' . number_format($a['nominal'], 0, ',', '.') . ')';
                })->implode(' | ');

                fputcsv($f, [
                    $no++,
                    $item['kode_barang'],
                    $item['nama_barang'],
                    $item['kategori'],
                    $item['satuan'],
                    $item['total_qty_wasted'],
                    $item['total_nominal_wasted'],
                    $alokasiStr,
                    $item['total_qty_beli'],
                    $item['total_nominal_beli'],
                    $item['persen_qty'] !== null ? $item['persen_qty'] . '%' : 'Tanpa Pembelian',
                    $item['persen_nominal'] !== null ? $item['persen_nominal'] . '%' : 'Tanpa Pembelian',
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}