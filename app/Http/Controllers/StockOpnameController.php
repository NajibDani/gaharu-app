<?php

namespace App\Http\Controllers;

use App\Models\MasterGudang;
use App\Models\GudangDivisi;
use App\Models\PengeluaranBahanBaku;
use App\Models\PengeluaranBahanBakuDetail;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST DATA
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $search = $request->query('search');
        $gudangId = $request->query('gudang_id');
        $kategoriId = $request->query('kategori_id');
        $jenisBarang = $request->query('jenis_barang');

        $query = StockOpname::with(['gudang', 'divisi', 'user']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_opname', 'like', '%' . $search . '%')
                  ->orWhere('keterangan', 'like', '%' . $search . '%');
            });
        }

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }

        // Filter berdasarkan kategori atau jenis barang pada detail stock opname
        if ($kategoriId || $jenisBarang) {
            $query->whereHas('details.barang', function($q) use ($kategoriId, $jenisBarang) {
                if ($kategoriId) {
                    $q->where('kategori_id', $kategoriId);
                }
                if ($jenisBarang) {
                    if ($jenisBarang === 'bahan_baku') {
                        $q->where('is_bahan_baku', 1);
                    } elseif ($jenisBarang === 'bahan_setengah_jadi') {
                        $q->where('is_bahan_setengah_jadi', 1);
                    } elseif ($jenisBarang === 'barang_jadi') {
                        $q->where('is_barang_jadi', 1);
                    } elseif ($jenisBarang === 'operational') {
                        $q->where('is_operational', 1);
                    }
                }
            });
        }

        $stockOpname = $query->latest()->paginate(20)->withQueryString();

        $totalDraft = StockOpname::where('status', 'draft')->count();
        $totalApproved = StockOpname::where('status', 'approved')->count();

        $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        $kategoris = \App\Models\Kategori::orderBy('nama')->get();

        return view('stock-opname.index', compact('stockOpname', 'gudangs', 'kategoris', 'totalDraft', 'totalApproved'));
    }

    /*
    |--------------------------------------------------------------------------
    | FORM CREATE
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;

        if (!$gudangId) {
            return redirect()
                ->route('stock-opname.index')
                ->with('error', 'Silakan pilih gudang terlebih dahulu.');
        }

        $gudang = MasterGudang::with('divisi')->findOrFail($gudangId);
        $divisi = $divisiId ? GudangDivisi::find($divisiId) : null;
        $kategoris = \App\Models\Kategori::orderBy('nama')->get();

        // Jika gudang operasional memiliki divisi tapi divisi belum dipilih, arahkan untuk memilih divisi
        if (strtolower($gudang->kategori) === 'operasional' && $gudang->divisi->count() > 0 && !$divisiId) {
            return redirect()
                ->route('stock-opname.index')
                ->with('error', 'Gudang ' . $gudang->nama . ' memiliki beberapa divisi. Silakan pilih divisi yang akan di-opname.');
        }

        return view('stock-opname.create', compact('gudang', 'divisi', 'divisiId', 'kategoris'));
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD BARANG AJAX
    |--------------------------------------------------------------------------
    */

    public function loadBarang(Request $request)
    {
        $request->validate(['gudang_id' => 'required']);
        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;

        $barang = DB::table('master_barang')
            ->leftJoin('kategori', 'master_barang.kategori_id', '=', 'kategori.id')
            ->leftJoin('stok_gudang', function ($join) use ($gudangId, $divisiId) {
                $join->on('master_barang.id', '=', 'stok_gudang.barang_id')
                     ->where('stok_gudang.gudang_id', '=', $gudangId);
                if ($divisiId) {
                    $join->where('stok_gudang.divisi_id', '=', $divisiId);
                } else {
                    $join->whereNull('stok_gudang.divisi_id');
                }
            })
            ->where('master_barang.is_active', true)
            ->where(function ($q) {
                $q->where('master_barang.is_bahan_baku', 1)
                  ->orWhere('master_barang.is_bahan_setengah_jadi', 1)
                  ->orWhere('master_barang.is_barang_jadi', 1)
                  ->orWhere('master_barang.is_operational', 1);
            })
            ->where(function($q) use ($gudangId, $divisiId) {
                $q->where('master_barang.is_bahan_baku', 0)
                  ->orWhereNotExists(function($notExistsQuery) use ($gudangId, $divisiId) {
                      $notExistsQuery->select(DB::raw(1))
                          ->from('barang_minimum_stock')
                          ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                          ->where('barang_minimum_stock.gudang_id', $gudangId)
                          ->where('barang_minimum_stock.is_active', false);
                      if ($divisiId) {
                          $notExistsQuery->where('barang_minimum_stock.divisi_id', $divisiId);
                      } else {
                          $notExistsQuery->whereNull('barang_minimum_stock.divisi_id');
                      }
                  });
            })
            ->select(
                'master_barang.id',
                'master_barang.kode_barang',
                'master_barang.nama',
                'master_barang.satuan',
                'master_barang.satuan_pembelian',
                'master_barang.konversi_pembelian',
                'master_barang.kategori_id',
                'kategori.nama as kategori_nama',
                'master_barang.is_bahan_baku',
                'master_barang.is_bahan_setengah_jadi',
                'master_barang.is_barang_jadi',
                'master_barang.is_operational',
                DB::raw('COALESCE(stok_gudang.jumlah, 0) as stok')
            )
            ->orderByRaw('CASE 
                WHEN COALESCE(stok_gudang.jumlah, 0) > 0 THEN 0 
                WHEN COALESCE(stok_gudang.jumlah, 0) < 0 THEN 1 
                ELSE 2 
            END ASC')
            ->orderBy('master_barang.nama', 'asc')
            ->get();

        foreach ($barang as $item) {
            $item->harga_fifo = $this->getHargaFIFO(
                $gudangId,
                $item->id,
                $divisiId
            );
        }

        return response()->json($barang);
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG FIFO REALTIME (AJAX)
    |--------------------------------------------------------------------------
    */

    public function hitungFIFORealtime(Request $request)
    {
        $nilai = $this->hitungNilaiFIFO(
            $request->gudang_id,
            $request->barang_id,
            abs($request->selisih),
            $request->divisi_id
        );

        return response()->json(['nilai' => $nilai]);
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DRAFT STOCK OPNAME
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'gudang_id'   => 'required',
            'divisi_id'   => 'nullable|exists:gudang_divisi,id',
            'tanggal'     => 'nullable|date',
        ]);

        $gudang = MasterGudang::with('divisi')->find($request->gudang_id);
        if ($gudang && strtolower($gudang->kategori) === 'operasional' && $gudang->divisi->count() > 0 && empty($request->divisi_id)) {
            return back()->withErrors(['divisi_id' => 'Silakan pilih divisi untuk gudang operasional ' . $gudang->nama . '.'])->withInput();
        }

        $tanggal = $request->tanggal ? date('Y-m-d', strtotime($request->tanggal)) : date('Y-m-d');

        if (\App\Models\Journal::isPeriodClosed($tanggal)) {
            return back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku. Tidak dapat membuat Stock Opname pada periode yang sudah ditutup.'])->withInput();
        }

        // Ambil data item baik dari items_json (bebas batasan PHP max_input_vars) atau array fallback
        $items = [];
        if ($request->filled('items_json')) {
            $decoded = json_decode($request->items_json, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        } elseif ($request->has('barang_id') && is_array($request->barang_id)) {
            foreach ($request->barang_id as $index => $barangId) {
                $items[] = [
                    'barang_id'   => $barangId,
                    'stok_sistem' => $request->stok_sistem[$index] ?? 0,
                    'stok_fisik'  => $request->stok_fisik[$index] ?? 0,
                ];
            }
        }

        if (empty($items)) {
            return back()->with('error', 'Tidak ada data barang yang disimpan. Silakan periksa kembali daftar barang opname.')->withInput();
        }

        DB::beginTransaction();

        try {
            $opname = StockOpname::create([
                'kode_opname' => 'SO-' . now()->format('YmdHis'),
                'tanggal'     => $tanggal,
                'gudang_id'   => $request->gudang_id,
                'divisi_id'   => $request->divisi_id,
                'status'      => 'draft',
                'keterangan'  => $request->keterangan,
                'created_by'  => Auth::id(),
            ]);

            foreach ($items as $item) {
                $barangId     = $item['barang_id'];
                $stokSistem   = (float) ($item['stok_sistem'] ?? 0);
                $stokFisik    = (float) ($item['stok_fisik'] ?? 0);
                $selisih      = $stokFisik - $stokSistem;
                $nilaiSelisih = $this->hitungNilaiFIFO(
                    $request->gudang_id,
                    $barangId,
                    abs($selisih),
                    $request->divisi_id
                );

                StockOpnameDetail::create([
                    'stock_opname_id' => $opname->id,
                    'barang_id'       => $barangId,
                    'stok_sistem'     => $stokSistem,
                    'stok_fisik'      => $stokFisik,
                    'selisih'         => $selisih,
                    'nilai_selisih'   => $nilaiSelisih,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('stock-opname.show', $opname->id)
                ->with('success', 'Draft Stock Opname berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL
    |--------------------------------------------------------------------------
    */

    public function show(string $id)
    {
        $stockOpname = StockOpname::with([
            'gudang',
            'divisi',
            'user',
            'details.barang',
        ])->findOrFail($id);

        $pengeluaranOtomatis = $stockOpname->pengeluaranOtomatis();

        return view('stock-opname.show', compact('stockOpname', 'pengeluaranOtomatis'));
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL JSON (untuk modal index)
    |--------------------------------------------------------------------------
    */

    public function detailJson(string $id)
    {
        $opname = StockOpname::with([
            'gudang',
            'divisi',
            'user',
            'details.barang',
        ])->findOrFail($id);

        $detailsSorted = $opname->details->sortBy(function ($d) {
            $stok = (float) $d->stok_sistem;
            $order = $stok > 0 ? 0 : ($stok < 0 ? 1 : 2);
            return sprintf('%d_%s', $order, strtolower($d->barang->nama ?? ''));
        })->values();

        return response()->json([
            'id'          => $opname->id,
            'kode_opname' => $opname->kode_opname,
            'tanggal'     => \Carbon\Carbon::parse($opname->tanggal)->format('d M Y'),
            'gudang'      => $opname->gudang->nama ?? '-',
            'divisi'      => $opname->divisi->nama ?? '-',
            'user'        => $opname->user->nama_karyawan ?? $opname->user->name ?? '-',
            'status'      => $opname->status,
            'keterangan'  => $opname->keterangan ?? '-',
            'details'     => $detailsSorted->map(function ($d) {
                $konversi = (float) ($d->barang->konversi_pembelian ?? 1);
                $hasKonversi = !empty($d->barang->satuan_pembelian) && $konversi > 1;

                return [
                    'nama_barang'       => $d->barang->nama ?? '-',
                    'kode_barang'       => $d->barang->kode_barang ?? '-',
                    'satuan'            => $d->barang->satuan ?? 'pcs',
                    'satuan_pembelian'  => $d->barang->satuan_pembelian ?? null,
                    'konversi_pembelian'=> $konversi,
                    'has_konversi'      => $hasKonversi,
                    'stok_sistem'       => (float) $d->stok_sistem,
                    'stok_fisik'        => (float) $d->stok_fisik,
                    'selisih'           => (float) $d->selisih,
                    'nilai_selisih'     => (float) $d->nilai_selisih,
                    'stok_sistem_konv'  => $hasKonversi ? ((float)$d->stok_sistem / $konversi) : null,
                    'stok_fisik_konv'   => $hasKonversi ? ((float)$d->stok_fisik / $konversi) : null,
                    'selisih_konv'      => $hasKonversi ? ((float)$d->selisih / $konversi) : null,
                ];
            }),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REFRESH / SINKRONKAN STOK SISTEM (DRAFT ONLY)
    |--------------------------------------------------------------------------
    */

    public function refreshStok(Request $request, string $id)
    {
        $opname = StockOpname::with('details')->findOrFail($id);

        if ($opname->status !== 'draft') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock Opname yang sudah diapprove tidak dapat diperbarui.'
                ], 422);
            }
            return back()->with('error', 'Stock Opname yang sudah diapprove tidak dapat diperbarui.');
        }

        DB::beginTransaction();

        try {
            $gudangId = $opname->gudang_id;
            $divisiId = $opname->divisi_id;

            // Ambil semua barang aktif beserta stok sistem terkini di gudang/divisi ini
            $barangList = DB::table('master_barang')
                ->leftJoin('stok_gudang', function ($join) use ($gudangId, $divisiId) {
                    $join->on('master_barang.id', '=', 'stok_gudang.barang_id')
                         ->where('stok_gudang.gudang_id', '=', $gudangId);
                    if ($divisiId) {
                        $join->where('stok_gudang.divisi_id', '=', $divisiId);
                    } else {
                        $join->whereNull('stok_gudang.divisi_id');
                    }
                })
                ->where('master_barang.is_active', true)
                ->where(function ($q) {
                    $q->where('master_barang.is_bahan_baku', 1)
                      ->orWhere('master_barang.is_bahan_setengah_jadi', 1)
                      ->orWhere('master_barang.is_barang_jadi', 1)
                      ->orWhere('master_barang.is_operational', 1);
                })
                ->where(function($q) use ($gudangId, $divisiId) {
                    $q->where('master_barang.is_bahan_baku', 0)
                      ->orWhereNotExists(function($notExistsQuery) use ($gudangId, $divisiId) {
                          $notExistsQuery->select(DB::raw(1))
                              ->from('barang_minimum_stock')
                              ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                              ->where('barang_minimum_stock.gudang_id', $gudangId)
                              ->where('barang_minimum_stock.is_active', false);
                          if ($divisiId) {
                              $notExistsQuery->where('barang_minimum_stock.divisi_id', $divisiId);
                          } else {
                              $notExistsQuery->whereNull('barang_minimum_stock.divisi_id');
                          }
                      });
                })
                ->select(
                    'master_barang.id',
                    DB::raw('COALESCE(stok_gudang.jumlah, 0) as stok')
                )
                ->get();

            $existingDetails = $opname->details->keyBy('barang_id');
            $updatedCount = 0;
            $addedCount = 0;

            foreach ($barangList as $item) {
                $stokSistemBaru = (float) $item->stok;

                if ($existingDetails->has($item->id)) {
                    $detail = $existingDetails->get($item->id);
                    $stokFisik = (float) $detail->stok_fisik;

                    // Jika sebelumnya item ini tidak memiliki selisih (stok_fisik == stok_sistem),
                    // maka pertahankan kondisi imbang dengan mengikuti stok sistem baru.
                    if (abs((float)$detail->stok_fisik - (float)$detail->stok_sistem) < 0.0001) {
                        $stokFisik = $stokSistemBaru;
                        $selisihBaru = 0;
                        $nilaiSelisihBaru = 0;
                    } else {
                        $selisihBaru = $stokFisik - $stokSistemBaru;
                        $nilaiSelisihBaru = $this->hitungNilaiFIFO(
                            $gudangId,
                            $item->id,
                            abs($selisihBaru),
                            $divisiId
                        );
                    }

                    $detail->update([
                        'stok_sistem'   => $stokSistemBaru,
                        'stok_fisik'    => $stokFisik,
                        'selisih'       => $selisihBaru,
                        'nilai_selisih' => $nilaiSelisihBaru,
                    ]);
                    $updatedCount++;
                } else {
                    // Barang baru di gudang yang belum tercatat pada draft opname
                    StockOpnameDetail::create([
                        'stock_opname_id' => $opname->id,
                        'barang_id'       => $item->id,
                        'stok_sistem'     => $stokSistemBaru,
                        'stok_fisik'      => $stokSistemBaru,
                        'selisih'         => 0,
                        'nilai_selisih'   => 0,
                    ]);
                    $addedCount++;
                }
            }

            // Untuk barang yang ada di detail tetapi tidak ada di query master aktif (misal barang non-aktif):
            $processedBarangIds = $barangList->pluck('id')->all();
            foreach ($existingDetails as $barangId => $detail) {
                if (!in_array($barangId, $processedBarangIds)) {
                    $stokAktual = (float) (DB::table('stok_gudang')
                        ->where('barang_id', $barangId)
                        ->where('gudang_id', $gudangId)
                        ->when($divisiId, function($q) use ($divisiId) {
                            return $q->where('divisi_id', $divisiId);
                        }, function($q) {
                            return $q->whereNull('divisi_id');
                        })
                        ->value('jumlah') ?? 0);

                    $selisihLama = (float)$detail->stok_fisik - (float)$detail->stok_sistem;
                    if (abs($selisihLama) < 0.0001) {
                        $stokFisikBaru = $stokAktual;
                        $selisihBaru = 0;
                        $nilaiSelisihBaru = 0;
                    } else {
                        $stokFisikBaru = (float) $detail->stok_fisik;
                        $selisihBaru = $stokFisikBaru - $stokAktual;
                        $nilaiSelisihBaru = $this->hitungNilaiFIFO(
                            $gudangId,
                            $barangId,
                            abs($selisihBaru),
                            $divisiId
                        );
                    }

                    $detail->update([
                        'stok_sistem'   => $stokAktual,
                        'stok_fisik'    => $stokFisikBaru,
                        'selisih'       => $selisihBaru,
                        'nilai_selisih' => $nilaiSelisihBaru,
                    ]);
                    $updatedCount++;
                }
            }

            DB::commit();

            $msg = "Stok sistem berhasil diperbarui. ($updatedCount barang disinkronkan" . ($addedCount > 0 ? ", $addedCount barang baru ditambahkan" : "") . ")";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success'       => true,
                    'message'       => $msg,
                    'updated_count' => $updatedCount,
                    'added_count'   => $addedCount,
                ]);
            }

            return back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui stok: ' . $e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Gagal memperbarui stok: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public function approve(string $id)
    {
        $opname = StockOpname::with(['details.barang', 'gudang', 'divisi'])->findOrFail($id);

        if ($opname->status === 'approved') {
            return back()->with('error', 'Stock Opname sudah disetujui sebelumnya.');
        }

        DB::beginTransaction();

        try {
            $fifoService = app(\App\Services\FifoService::class);
            $itemSelisihNegatif = [];
            $surplusDebits = [];
            $totalSurplusKredit = 0;
            $shortageKredits = [];
            $totalShortageDebit = 0;

            $idPendapatanLain = DB::table('chart_of_accounts')->where('kode', '4201')->value('id') ?? 32;
            $idBebanSelisih = DB::table('chart_of_accounts')->where('kode', '6401')->value('id')
                ?? DB::table('chart_of_accounts')->where('kode', '5104')->value('id') 
                ?? DB::table('chart_of_accounts')->where('kode', '5103')->value('id') 
                ?? 44;

            $tanggalBase = \Carbon\Carbon::parse($opname->tanggal)->format('Y-m-d');
            $tanggalTx = $tanggalBase . ' ' . now()->format('H:i:s');

            foreach ($opname->details as $detail) {

                $hargaUnit = $this->getHargaFIFO(
                    $opname->gudang_id,
                    $detail->barang_id,
                    $opname->divisi_id
                );

                if ($detail->selisih < 0) {
                    $qtyKurang = abs((float) $detail->selisih);

                    // 1. Eksekusi pemotongan FIFO di gudang & divisi lokasi opname
                    $fifoResult = $fifoService->consumeFIFO(
                        barangId:      $detail->barang_id,
                        qtyKeluar:     $qtyKurang,
                        gudangId:      $opname->gudang_id,
                        allowNegative: true,
                        divisiId:      $opname->divisi_id,
                    );

                    $hppTotalKurang = 0;
                    $fifoRecords = [];
                    foreach ($fifoResult as $fifo) {
                        $layerTotal = $fifo['qty_keluar'] * $fifo['harga_per_qty'];
                        $hppTotalKurang += $layerTotal;
                        $fifoRecords[] = [
                            'batch_id'      => $fifo['batch_id'],
                            'batch_number'  => $fifo['batch_number'],
                            'qty_keluar'    => $fifo['qty_keluar'],
                            'harga_per_qty' => $fifo['harga_per_qty'],
                            'total_harga'   => $layerTotal,
                        ];
                    }

                    // Fallback jika belum ada batch masuk sebelumnya
                    if ($hppTotalKurang <= 0 && $hargaUnit > 0) {
                        $hppTotalKurang = round($qtyKurang * $hargaUnit, 2);
                    } else {
                        $hppTotalKurang = round($hppTotalKurang, 2);
                    }

                    // 2. Kurangi stok summary di stok_gudang
                    $stokQuery = \App\Models\StokGudang::where('barang_id', $detail->barang_id)
                        ->where('gudang_id', $opname->gudang_id);
                    if ($opname->divisi_id) {
                        $stokQuery->where('divisi_id', $opname->divisi_id);
                    } else {
                        $stokQuery->whereNull('divisi_id');
                    }
                    $stokGudang = $stokQuery->lockForUpdate()->first();
                    if ($stokGudang) {
                        $stokGudang->decrement('jumlah', $qtyKurang);
                    } else {
                        \App\Models\StokGudang::create([
                            'barang_id' => $detail->barang_id,
                            'gudang_id' => $opname->gudang_id,
                            'divisi_id' => $opname->divisi_id,
                            'jumlah'    => -$qtyKurang,
                        ]);
                    }

                    // 3. Catat mutasi keluar di transaksi_stok (terbaca di Buku Pembantu Persediaan)
                    \App\Models\TransaksiStok::create([
                        'tanggal'        => $tanggalTx,
                        'tipe'           => 'keluar',
                        'source_type'    => 'stock_opname',
                        'source_id'      => $opname->id,
                        'gudang_asal_id' => $opname->gudang_id,
                        'divisi_asal_id' => $opname->divisi_id,
                        'barang_id'      => $detail->barang_id,
                        'qty'            => $qtyKurang,
                        'total_harga'    => $hppTotalKurang,
                        'created_by'     => Auth::id() ?? 1,
                    ]);

                    // 4. Akumulasi untuk Jurnal Penyesuaian Shortage
                    if ($hppTotalKurang > 0) {
                        $isOperational = $detail->barang && ($detail->barang->is_operational || (!$detail->barang->is_bahan_baku && !$detail->barang->is_bahan_setengah_jadi));
                        $coaCode = $isOperational ? '1501' : ($detail->barang && $detail->barang->is_bahan_setengah_jadi ? '1302' : ($detail->barang && $detail->barang->is_barang_jadi ? '1303' : '1301'));
                        $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);

                        if (!isset($shortageKredits[$idPersediaan])) {
                            $shortageKredits[$idPersediaan] = 0;
                        }
                        $shortageKredits[$idPersediaan] += $hppTotalKurang;
                        $totalShortageDebit += $hppTotalKurang;
                    }

                    $itemSelisihNegatif[] = [
                        'barang_id'    => $detail->barang_id,
                        'qty'          => $qtyKurang,
                        'satuan'       => $detail->barang->satuan ?? 'pcs',
                        'hpp_total'    => $hppTotalKurang,
                        'harga_satuan' => $qtyKurang > 0 ? ($hppTotalKurang / $qtyKurang) : 0,
                        'fifo_records' => $fifoRecords,
                    ];

                } elseif ($detail->selisih > 0) {
                    $defaultSupplierId  = DB::table('suppliers')->value('id') ?? 1;
                    $defaultPembelianId = DB::table('pembelian')->value('id') ?? 1;
                    $defaultPemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

                    // 1. Buat batch FIFO baru untuk surplus
                    \App\Models\StokGudangBatch::create([
                        'gudang_id'           => $opname->gudang_id,
                        'divisi_id'           => $opname->divisi_id,
                        'supplier_id'         => $defaultSupplierId,
                        'barang_id'           => $detail->barang_id,
                        'pembelian_id'        => $defaultPembelianId,
                        'pembelian_detail_id' => $defaultPemDetailId,
                        'batch_number'        => 'SO-SURPLUS-' . $opname->kode_opname,
                        'qty_masuk'           => $detail->selisih,
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $detail->selisih,
                        'harga_per_qty'       => $hargaUnit,
                        'is_habis'            => false,
                    ]);

                    // 2. Tambah stok gudang menggunakan StockService
                    app(\App\Services\StockService::class)->stockIn([
                        'barang_id'        => $detail->barang_id,
                        'gudang_tujuan_id' => $opname->gudang_id,
                        'divisi_tujuan_id' => $opname->divisi_id,
                        'qty'              => $detail->selisih,
                        'total_harga'      => $detail->selisih * $hargaUnit,
                        'source_type'      => 'stock_opname',
                        'source_id'        => $opname->id,
                        'user_id'          => Auth::id() ?? 1,
                    ]);

                    // 3. Akumulasi untuk Jurnal Penyesuaian (Surplus)
                    $totalHargaSO = round($detail->selisih * $hargaUnit, 2);
                    if ($totalHargaSO > 0) {
                        $isOperational = $detail->barang && ($detail->barang->is_operational || (!$detail->barang->is_bahan_baku && !$detail->barang->is_bahan_setengah_jadi));
                        $coaCode = $isOperational ? '1501' : ($detail->barang && $detail->barang->is_bahan_setengah_jadi ? '1302' : ($detail->barang && $detail->barang->is_barang_jadi ? '1303' : '1301'));
                        $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);
                        
                        if (!isset($surplusDebits[$idPersediaan])) {
                            $surplusDebits[$idPersediaan] = 0;
                        }
                        $surplusDebits[$idPersediaan] += $totalHargaSO;
                        $totalSurplusKredit += $totalHargaSO;
                    }
                }
            }

            // ── Buat Jurnal Penyesuaian Surplus jika ada ──
            if ($totalSurplusKredit > 0) {
                $jpSurplus = \App\Models\JurnalPenyesuaian::create([
                    'tanggal'     => $opname->tanggal,
                    'deskripsi'   => "[AJP] Penyesuaian Lebih (Surplus) Stock Opname: " . $opname->kode_opname,
                    'no_ref'      => 'AJP-SO-SURPLUS-' . $opname->kode_opname . '-' . rand(100, 999),
                    'source_type' => 'stock_opname',
                    'source_id'   => $opname->id,
                    'created_by'  => Auth::id() ?? 1,
                    'status'      => 'approved',
                ]);

                foreach ($surplusDebits as $accId => $debitAmount) {
                    $jpSurplus->details()->create([
                        'account_id'   => $accId,
                        'debit'        => round($debitAmount, 2),
                        'kredit'       => 0,
                        'journal_type' => \App\Models\JurnalPenyesuaian::class,
                    ]);
                }

                $jpSurplus->details()->create([
                    'account_id'   => $idPendapatanLain,
                    'debit'        => 0,
                    'kredit'       => round($totalSurplusKredit, 2),
                    'journal_type' => \App\Models\JurnalPenyesuaian::class,
                ]);
            }

            // ── Buat Jurnal Penyesuaian Shortage jika ada ──
            if ($totalShortageDebit > 0) {
                $jpShortage = \App\Models\JurnalPenyesuaian::create([
                    'tanggal'     => $opname->tanggal,
                    'deskripsi'   => "[AJP] Penyesuaian Kurang (Shortage) Stock Opname: " . $opname->kode_opname,
                    'no_ref'      => 'AJP-SO-SHORTAGE-' . $opname->kode_opname . '-' . rand(100, 999),
                    'source_type' => 'stock_opname',
                    'source_id'   => $opname->id,
                    'created_by'  => Auth::id() ?? 1,
                    'status'      => 'approved',
                ]);

                $jpShortage->details()->create([
                    'account_id'   => $idBebanSelisih,
                    'debit'        => round($totalShortageDebit, 2),
                    'kredit'       => 0,
                    'journal_type' => \App\Models\JurnalPenyesuaian::class,
                ]);

                foreach ($shortageKredits as $accId => $kreditAmount) {
                    $jpShortage->details()->create([
                        'account_id'   => $accId,
                        'debit'        => 0,
                        'kredit'       => round($kreditAmount, 2),
                        'journal_type' => \App\Models\JurnalPenyesuaian::class,
                    ]);
                }
            }

            // ── Buat Pengeluaran Bahan Baku otomatis berstatus approved jika ada selisih negatif (audit trail) ──
            if (!empty($itemSelisihNegatif)) {
                $kode = 'PBK-SO-' . $opname->kode_opname;

                $pengeluaran = PengeluaranBahanBaku::where('kode_pengeluaran', $kode)->first();
                if (!$pengeluaran) {
                    $pengeluaran = PengeluaranBahanBaku::create([
                        'kode_pengeluaran'  => $kode,
                        'tanggal'           => $tanggalTx,
                        'gudang_id'         => $opname->gudang_id,
                        'divisi_id'         => $opname->divisi_id,
                        'jenis_pengeluaran' => 'stock_opname',
                        'status'            => 'approved',
                        'keterangan'        => 'Auto dari Stock Opname: ' . $opname->kode_opname,
                        'created_by'        => Auth::id() ?? 1,
                        'approved_by'       => Auth::id() ?? 1,
                        'approved_at'       => now(),
                    ]);
                } else {
                    $pengeluaran->update([
                        'status'            => 'approved',
                        'jenis_pengeluaran' => 'stock_opname',
                        'approved_by'       => Auth::id() ?? 1,
                        'approved_at'       => now(),
                    ]);
                    $pengeluaran->details()->delete();
                }

                foreach ($itemSelisihNegatif as $item) {
                    $pbkDetail = PengeluaranBahanBakuDetail::create([
                        'pengeluaran_id' => $pengeluaran->id,
                        'barang_id'      => $item['barang_id'],
                        'qty'            => $item['qty'],
                        'satuan'         => $item['satuan'],
                        'harga_satuan'   => $item['harga_satuan'],
                        'total_harga'    => $item['hpp_total'],
                        'hpp_total'      => $item['hpp_total'],
                    ]);

                    foreach ($item['fifo_records'] as $fr) {
                        if (!empty($fr['batch_id'])) {
                            \App\Models\PengeluaranBahanBakuFifo::create([
                                'pengeluaran_id' => $pengeluaran->id,
                                'detail_id'      => $pbkDetail->id,
                                'batch_id'       => $fr['batch_id'],
                                'batch_number'   => $fr['batch_number'],
                                'qty_keluar'     => $fr['qty_keluar'],
                                'harga_per_qty'  => $fr['harga_per_qty'],
                                'total_harga'    => $fr['total_harga'],
                            ]);
                        }
                    }
                }
            }

            // ── Update status opname ──
            $opname->update(['status' => 'approved']);

            DB::commit();

            return back()->with(
                'success',
                'Stock Opname (' . $opname->kode_opname . ') berhasil disetujui. Stok gudang, mutasi FIFO, dan jurnal penyesuaian telah disinkronkan secara langsung.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal approve: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        $opname = StockOpname::findOrFail($id);

        if ($opname->status === 'approved') {
            return back()->with(
                'error',
                'Stock Opname yang sudah approved tidak dapat dihapus.'
            );
        }

        $opname->details()->delete();
        $opname->delete();

        return redirect()
            ->route('stock-opname.index')
            ->with('success', 'Stock Opname berhasil dihapus.');
    }

    public function edit(string $id)
    {
        $opname = StockOpname::with([
            'gudang.divisi',
            'divisi',
            'details.barang'
        ])->findOrFail($id);

        if ($opname->status !== 'draft') {
            return redirect()
                ->route('stock-opname.show', $opname->id)
                ->with('error', 'Stock Opname yang sudah diapprove tidak dapat diedit.');
        }

        $gudang = $opname->gudang;
        $divisi = $opname->divisi;
        $divisiId = $opname->divisi_id;
        $kategoris = \App\Models\Kategori::orderBy('nama')->get();

        $existingDetails = $opname->details->mapWithKeys(function ($d) {
            return [$d->barang_id => [
                'stok_sistem' => (float) $d->stok_sistem,
                'stok_fisik'  => (float) $d->stok_fisik,
                'selisih'     => (float) $d->selisih,
                'nilai'       => (float) $d->nilai_selisih,
            ]];
        });

        return view('stock-opname.edit', compact(
            'opname',
            'gudang',
            'divisi',
            'divisiId',
            'kategoris',
            'existingDetails'
        ));
    }

    public function update(Request $request, string $id)
    {
        $opname = StockOpname::findOrFail($id);

        if ($opname->status !== 'draft') {
            return redirect()
                ->route('stock-opname.show', $opname->id)
                ->with('error', 'Stock Opname yang sudah diapprove tidak dapat diubah.');
        }

        // Jika form berasal dari edit lengkap (via items_json atau array barang_id)
        if ($request->filled('items_json') || $request->has('barang_id')) {
            $request->validate([
                'tanggal' => 'required|date',
            ]);

            $tanggal = date('Y-m-d', strtotime($request->tanggal));

            if (\App\Models\Journal::isPeriodClosed($tanggal)) {
                return back()->withErrors(['tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.'])->withInput();
            }

            // Ambil data item baik dari items_json (bebas batasan PHP max_input_vars) atau array fallback
            $items = [];
            if ($request->filled('items_json')) {
                $decoded = json_decode($request->items_json, true);
                if (is_array($decoded)) {
                    $items = $decoded;
                }
            } elseif ($request->has('barang_id') && is_array($request->barang_id)) {
                foreach ($request->barang_id as $index => $barangId) {
                    $items[] = [
                        'barang_id'   => $barangId,
                        'stok_sistem' => $request->stok_sistem[$index] ?? 0,
                        'stok_fisik'  => $request->stok_fisik[$index] ?? 0,
                    ];
                }
            }

            if (empty($items)) {
                return back()->with('error', 'Tidak ada data barang yang disimpan. Silakan periksa kembali formulir opname.')->withInput();
            }

            DB::beginTransaction();

            try {
                $opname->update([
                    'tanggal'    => $tanggal,
                    'keterangan' => $request->keterangan,
                ]);

                // Hapus detail lama dan perbarui dengan detail terbaru
                StockOpnameDetail::where('stock_opname_id', $opname->id)->delete();

                foreach ($items as $item) {
                    $barangId     = $item['barang_id'];
                    $stokSistem   = (float) ($item['stok_sistem'] ?? 0);
                    $stokFisik    = (float) ($item['stok_fisik'] ?? 0);
                    $selisih      = $stokFisik - $stokSistem;
                    $nilaiSelisih = $this->hitungNilaiFIFO(
                        $opname->gudang_id,
                        $barangId,
                        abs($selisih),
                        $opname->divisi_id
                    );

                    StockOpnameDetail::create([
                        'stock_opname_id' => $opname->id,
                        'barang_id'       => $barangId,
                        'stok_sistem'     => $stokSistem,
                        'stok_fisik'      => $stokFisik,
                        'selisih'         => $selisih,
                        'nilai_selisih'   => $nilaiSelisih,
                    ]);
                }

                DB::commit();

                return redirect()
                    ->route('stock-opname.show', $opname->id)
                    ->with('success', 'Perubahan Stock Opname (' . $opname->kode_opname . ') berhasil disimpan.');

            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Gagal menyimpan perubahan Stock Opname: ' . $e->getMessage())->withInput();
            }
        }

        // Quick update tanggal dari halaman show
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        $tanggal = date('Y-m-d', strtotime($request->tanggal));

        if (\App\Models\Journal::isPeriodClosed($tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.');
        }

        $opname->update([
            'tanggal'    => $tanggal,
            'keterangan' => $request->keterangan ?? $opname->keterangan,
        ]);

        return back()->with('success', 'Tanggal Stock Opname berhasil diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET HARGA FIFO (untuk preview di form)
    |--------------------------------------------------------------------------
    */

    private function getHargaFIFO($gudangId, $barangId, $divisiId = null): float
    {
        $q = DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->where('qty_sisa', '>', 0);

        if ($divisiId) {
            $q->where('divisi_id', $divisiId);
        }

        $harga = $q->orderBy('id', 'asc')->value('harga_per_qty');

        // Fallback 1: rata-rata semua batch historis di gudang/divisi ini
        if (!$harga) {
            $fbQ = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId);
            if ($divisiId) {
                $fbQ->where('divisi_id', $divisiId);
            }
            $harga = $fbQ->avg('harga_per_qty');
        }

        // Fallback 2: batch aktif di gudang manapun
        if (!$harga) {
            $harga = DB::table('stok_gudang_batch')
                ->where('barang_id', $barangId)
                ->where('qty_sisa', '>', 0)
                ->orderBy('id', 'desc')
                ->value('harga_per_qty');
        }

        // Fallback akhir: hpp_referensi di master barang
        if (!$harga) {
            $harga = DB::table('master_barang')
                ->where('id', $barangId)
                ->value('hpp_referensi') ?? 0;
        }

        return (float) $harga;
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG NILAI FIFO
    |--------------------------------------------------------------------------
    */

    private function hitungNilaiFIFO($gudangId, $barangId, $qty, $divisiId = null): float
    {
        if ($qty <= 0) return 0;

        $sisa  = $qty;
        $nilai = 0;

        // ── Tahap 1: FIFO dari batch terlama yang masih punya sisa ──
        $q = DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangId)
            ->where('barang_id', $barangId)
            ->where('qty_sisa', '>', 0);

        if ($divisiId) {
            $q->where('divisi_id', $divisiId);
        }

        $batches = $q->orderBy('id', 'asc')->get();

        foreach ($batches as $batch) {
            if ($sisa <= 0) break;
            $ambil  = min($sisa, $batch->qty_sisa);
            $nilai += $ambil * $batch->harga_per_qty;
            $sisa  -= $ambil;
        }

        // ── Tahap 2: Fallback rata-rata batch historis jika qty_sisa semua 0 ──
        if ($sisa > 0) {
            $fbQ = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId);
            if ($divisiId) {
                $fbQ->where('divisi_id', $divisiId);
            }
            $hargaRata = $fbQ->avg('harga_per_qty');

            if (!$hargaRata) {
                $hargaRata = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $barangId)
                    ->avg('harga_per_qty');
            }

            if ($hargaRata) {
                $nilai += $sisa * $hargaRata;
                $sisa   = 0;
            }
        }

        // ── Tahap 3: Fallback hpp_referensi master barang ──
        if ($sisa > 0) {
            $hpp = DB::table('master_barang')
                ->where('id', $barangId)
                ->value('hpp_referensi');

            if ($hpp) {
                $nilai += $sisa * $hpp;
            }
        }

        return (float) $nilai;
    }
}