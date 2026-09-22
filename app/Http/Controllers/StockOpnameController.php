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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
                  ->orWhere('keterangan', 'like', '%' . $search . '%')
                  ->orWhereHas('details.barang', function($bq) use ($search) {
                      $bq->where('nama', 'like', '%' . $search . '%')
                         ->orWhere('kode_barang', 'like', '%' . $search . '%');
                  });
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
        $isSuperAdmin = $this->isSuperAdminUser();

        return view('stock-opname.index', compact('stockOpname', 'gudangs', 'kategoris', 'totalDraft', 'totalApproved', 'isSuperAdmin'));
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
                if ($divisiId) {
                    // Ketika SO dilakukan per divisi:
                    // Bahan Baku HANYA muncul jika tagging divisinya aktif (ON) untuk divisi tersebut
                    $q->where(function($subBaku) use ($gudangId, $divisiId) {
                        $subBaku->where('master_barang.is_bahan_baku', 1)
                                ->whereExists(function($existsQuery) use ($gudangId, $divisiId) {
                                    $existsQuery->select(DB::raw(1))
                                        ->from('barang_minimum_stock')
                                        ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                                        ->where('barang_minimum_stock.divisi_id', $divisiId)
                                        ->where('barang_minimum_stock.is_active', true);
                                });
                    })->orWhere(function($subNonBaku) {
                        $subNonBaku->where('master_barang.is_bahan_baku', 0);
                    });
                } else {
                    // Jika SO gudang umum / tanpa divisi
                    $q->where('master_barang.is_bahan_baku', 0)
                      ->orWhereNotExists(function($notExistsQuery) use ($gudangId) {
                          $notExistsQuery->select(DB::raw(1))
                              ->from('barang_minimum_stock')
                              ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                              ->where('barang_minimum_stock.gudang_id', $gudangId)
                              ->where('barang_minimum_stock.is_active', false)
                              ->whereNull('barang_minimum_stock.divisi_id');
                      });
                }
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

        $tanggal = $request->tanggal;
        if ($tanggal && $tanggal !== date('Y-m-d')) {
            $cutoff = $tanggal . ' 23:59:59';
            foreach ($barang as $item) {
                $qIn = DB::table('transaksi_stok')->where('barang_id', $item->id)->where('tanggal', '<=', $cutoff);
                $qOut = DB::table('transaksi_stok')->where('barang_id', $item->id)->where('tanggal', '<=', $cutoff);
                if ($gudangId && $divisiId) {
                    $qIn->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                    $qOut->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                } elseif ($gudangId) {
                    $qIn->where('gudang_tujuan_id', $gudangId);
                    $qOut->where('gudang_asal_id', $gudangId);
                }
                $item->stok = max(0, (float)($qIn->sum('qty') - $qOut->sum('qty')));
            }
        }

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
        $selisih = (float) ($request->selisih ?? 0);
        $nilai = $this->hitungNilaiOpname(
            $request->gudang_id,
            $request->barang_id,
            $selisih,
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
                $nilaiSelisih = $this->hitungNilaiOpname(
                    $request->gudang_id,
                    $barangId,
                    $selisih,
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
        $isSuperAdmin = $this->isSuperAdminUser();

        return view('stock-opname.show', compact('stockOpname', 'pengeluaranOtomatis', 'isSuperAdmin'));
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

        $isSuperAdmin = $this->isSuperAdminUser();

        $detailsSorted = $opname->details->sortBy(function ($d) {
            $stok = (float) $d->stok_sistem;
            $order = $stok > 0 ? 0 : ($stok < 0 ? 1 : 2);
            return sprintf('%d_%s', $order, strtolower($d->barang->nama ?? ''));
        })->values();

        return response()->json([
            'id'            => $opname->id,
            'kode_opname'   => $opname->kode_opname,
            'tanggal'       => \Carbon\Carbon::parse($opname->tanggal)->format('d M Y'),
            'gudang'        => $opname->gudang->nama ?? '-',
            'divisi'        => $opname->divisi->nama ?? '-',
            'user'          => $opname->user->nama_karyawan ?? $opname->user->name ?? '-',
            'status'        => $opname->status,
            'keterangan'    => $opname->keterangan ?? '-',
            'is_superadmin' => $isSuperAdmin,
            'details'       => $detailsSorted->map(function ($d) {
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
                        $nilaiSelisihBaru = $this->hitungNilaiOpname(
                            $gudangId,
                            $item->id,
                            $selisihBaru,
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
                        $nilaiSelisihBaru = $this->hitungNilaiOpname(
                            $gudangId,
                            $barangId,
                            $selisihBaru,
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
            $pbk = $this->createOrUpdateDraftPbk($opname);
            $opname->update(['status' => 'approved']);

            DB::commit();

            return back()->with(
                'success',
                'Stock Opname (' . $opname->kode_opname . ') berhasil disetujui. Dokumen pengajuan persetujuan stok (' . $pbk->kode_pengeluaran . ') telah dibuat di menu Permintaan / Transfer Bahan dengan status Draft. Stok gudang akan terpotong/bertambah secara otomatis setelah dokumen tersebut disetujui di menu tersebut.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal approve: ' . $e->getMessage());
        }
    }

    public function createOrUpdateDraftPbk(StockOpname $opname): PengeluaranBahanBaku
    {
        $cleanKode = $opname->kode_opname;
        if (str_starts_with($cleanKode, 'SO-')) {
            $kodePbk = 'PBK-' . $cleanKode;
        } else {
            $kodePbk = 'PBK-SO-' . $cleanKode;
        }

        $tanggalTx = \Carbon\Carbon::parse($opname->tanggal)->format('Y-m-d H:i:s');

        $pbk = PengeluaranBahanBaku::where('kode_pengeluaran', $kodePbk)
            ->orWhere('kode_pengeluaran', 'PBK-SO-' . $opname->kode_opname)
            ->orWhere('kode_pengeluaran', 'PBK-SO-SO-' . substr($cleanKode, 3))
            ->orWhere('keterangan', 'like', '%' . $opname->kode_opname . '%')
            ->first();

        if (!$pbk) {
            $pbk = PengeluaranBahanBaku::create([
                'kode_pengeluaran'  => $kodePbk,
                'tanggal'           => $tanggalTx,
                'gudang_id'         => $opname->gudang_id,
                'divisi_id'         => $opname->divisi_id,
                'jenis_pengeluaran' => 'stock_opname',
                'status'            => 'draft',
                'keterangan'        => 'Stock Opname: ' . $opname->kode_opname,
                'created_by'        => Auth::id() ?? 1,
            ]);
        } else {
            $pbk->update([
                'kode_pengeluaran'  => $kodePbk,
                'gudang_id'         => $opname->gudang_id,
                'divisi_id'         => $opname->divisi_id,
                'jenis_pengeluaran' => 'stock_opname',
                'status'            => 'draft',
                'keterangan'        => 'Stock Opname: ' . $opname->kode_opname,
            ]);
            $pbk->details()->delete();
        }

        foreach ($opname->details as $detail) {
            if (abs((float)$detail->selisih) > 0.0001) {
                $qty = abs((float)$detail->selisih);
                $nilai = abs((float)$detail->nilai_selisih);
                if ($nilai <= 0) {
                    $nilai = $this->hitungNilaiOpname(
                        $opname->gudang_id,
                        $detail->barang_id,
                        (float)$detail->selisih,
                        $opname->divisi_id
                    );
                }
                $hargaSatuan = $qty > 0 ? ($nilai / $qty) : 0;

                PengeluaranBahanBakuDetail::create([
                    'pengeluaran_id' => $pbk->id,
                    'barang_id'      => $detail->barang_id,
                    'qty'            => $qty,
                    'satuan'         => $detail->barang->satuan ?? 'pcs',
                    'harga_satuan'   => $hargaSatuan,
                    'total_harga'    => $nilai,
                    'hpp_total'      => $nilai,
                ]);
            }
        }

        return $pbk;
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id)
    {
        $opname = StockOpname::findOrFail($id);
        $isSuperAdmin = $this->isSuperAdminUser();

        if ($opname->status === 'approved' && !$isSuperAdmin) {
            return back()->with(
                'error',
                'Stock Opname yang sudah approved hanya dapat dihapus oleh Super Admin.'
            );
        }

        DB::beginTransaction();
        try {
            if ($opname->status === 'approved') {
                $this->revertApprovalEffects($opname);
            }

            $opname->details()->delete();
            $opname->delete();

            \App\Models\StokGudang::reconcileStockSummary(null, $opname->gudang_id, $opname->divisi_id);

            DB::commit();

            return redirect()
                ->route('stock-opname.index')
                ->with('success', 'Stock Opname ' . $opname->kode_opname . ' berhasil dihapus dan stok telah di-rollback ke kondisi semula.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus Stock Opname: ' . $e->getMessage());
        }
    }

    public function edit(string $id)
    {
        $opname = StockOpname::with([
            'gudang.divisi',
            'divisi',
            'details.barang'
        ])->findOrFail($id);

        $isSuperAdmin = $this->isSuperAdminUser();

        if ($opname->status !== 'draft' && !$isSuperAdmin) {
            return redirect()
                ->route('stock-opname.show', $opname->id)
                ->with('error', 'Stock Opname yang sudah diapprove hanya dapat diedit oleh Super Admin.');
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
            'existingDetails',
            'isSuperAdmin'
        ));
    }

    public function update(Request $request, string $id)
    {
        $opname = StockOpname::with(['details.barang', 'gudang', 'divisi'])->findOrFail($id);
        $isSuperAdmin = $this->isSuperAdminUser();

        if ($opname->status !== 'draft' && !$isSuperAdmin) {
            return redirect()
                ->route('stock-opname.show', $opname->id)
                ->with('error', 'Stock Opname yang sudah diapprove hanya dapat diubah oleh Super Admin.');
        }

        $wasApproved = ($opname->status === 'approved');

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
                // Jika sebelumnya sudah approved, kembalikan (revert) semua mutasi, FIFO, dan jurnal lama
                if ($wasApproved) {
                    $this->revertApprovalEffects($opname);
                }

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
                    $nilaiSelisih = $this->hitungNilaiOpname(
                        $opname->gudang_id,
                        $barangId,
                        $selisih,
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

                // Jika sebelumnya sudah approved, terapkan ulang efek mutasi, FIFO, dan jurnal baru
                if ($wasApproved) {
                    $opname->load(['details.barang', 'gudang', 'divisi']);
                    $this->executeApprovalEffects($opname);
                    $opname->update(['status' => 'approved']);
                }

                DB::commit();

                $msg = $wasApproved
                    ? 'Perubahan Stock Opname (' . $opname->kode_opname . ') berhasil disimpan dan disinkronkan kembali ke stok gudang, FIFO, & jurnal.'
                    : 'Perubahan Stock Opname (' . $opname->kode_opname . ') berhasil disimpan.';

                return redirect()
                    ->route('stock-opname.show', $opname->id)
                    ->with('success', $msg);

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

        DB::beginTransaction();
        try {
            if ($wasApproved) {
                $this->revertApprovalEffects($opname);
            }

            $opname->update([
                'tanggal'    => $tanggal,
                'keterangan' => $request->keterangan ?? $opname->keterangan,
            ]);

            if ($wasApproved) {
                $opname->load(['details.barang', 'gudang', 'divisi']);
                $this->executeApprovalEffects($opname);
                $opname->update(['status' => 'approved']);
            }

            DB::commit();
            return back()->with('success', 'Tanggal Stock Opname berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui tanggal Stock Opname: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: CEK SUPERADMIN
    |--------------------------------------------------------------------------
    */

    private function isSuperAdminUser(): bool
    {
        $user = Auth::user();
        return $user && ($user->isSuperAdmin() || $user->username === 'superadmin');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: REVERT APPROVAL EFFECTS (MUTASI STOK, FIFO, JURNAL, PBK)
    |--------------------------------------------------------------------------
    */

    public function revertApprovalEffects(StockOpname $opname): void
    {
        // 1. Revert Shortage (Pengeluaran Bahan Baku & FIFO consumption)
        $pengeluaranList = PengeluaranBahanBaku::where('kode_pengeluaran', 'PBK-SO-' . $opname->kode_opname)
            ->orWhere(function($q) use ($opname) {
                $q->where('jenis_pengeluaran', 'stock_opname')
                  ->where('keterangan', 'like', '%' . $opname->kode_opname . '%');
            })
            ->get();

        foreach ($pengeluaranList as $pengeluaran) {
            $fifoRecords = \App\Models\PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)->get();
            foreach ($fifoRecords as $fifo) {
                if (!empty($fifo->batch_id)) {
                    $batch = \App\Models\StokGudangBatch::find($fifo->batch_id);
                    if ($batch) {
                        $batch->increment('qty_sisa', (float) $fifo->qty_keluar);
                        $batch->decrement('qty_keluar', (float) $fifo->qty_keluar);
                        $batch->update(['is_habis' => false]);
                    }
                }
            }
            \App\Models\PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)->delete();
            $pengeluaran->details()->delete();
            $pengeluaran->delete();
        }

        // 2. Revert Surplus (Batch SO-SURPLUS-... & stock increment)
        $surplusBatches = \App\Models\StokGudangBatch::where('batch_number', 'like', '%SO-SURPLUS%' . $opname->kode_opname . '%')
            ->orWhere('batch_number', 'like', '%' . $opname->kode_opname . '%')
            ->where('gudang_id', $opname->gudang_id)
            ->when($opname->divisi_id, fn($q) => $q->where('divisi_id', $opname->divisi_id), fn($q) => $q->whereNull('divisi_id'))
            ->get();

        foreach ($surplusBatches as $batch) {
            $batch->delete();
        }

        // 3. Revert TransaksiStok
        \App\Models\TransaksiStok::where('source_type', 'stock_opname')
            ->where('source_id', $opname->id)
            ->delete();

        // 4. Revert Jurnal Penyesuaian
        $jurnals = \App\Models\JurnalPenyesuaian::where('source_type', 'stock_opname')
            ->where('source_id', $opname->id)
            ->get();

        foreach ($jurnals as $jurnal) {
            $jurnal->details()->delete();
            $jurnal->delete();
        }

        // 5. Rekonsiliasi ringkasan stok gudang
        \App\Models\StokGudang::reconcileStockSummary(null, $opname->gudang_id, $opname->divisi_id);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: EXECUTE APPROVAL EFFECTS (MUTASI STOK, FIFO, JURNAL, PBK)
    |--------------------------------------------------------------------------
    */

    public function executeApprovalEffects(StockOpname $opname, ?PengeluaranBahanBaku $pbkParam = null): void
    {
        $this->revertApprovalEffects($opname);

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
        $tanggalTx = $tanggalBase . ' 10:29:00';

        $pbk = $pbkParam ?: PengeluaranBahanBaku::where('kode_pengeluaran', 'PBK-SO-' . $opname->kode_opname)
            ->orWhere('keterangan', 'like', '%' . $opname->kode_opname . '%')
            ->first();

        foreach ($opname->details as $detail) {
            // Hitung stok sistem aktual di lokasi (gudang & divisi) sebelum waktu SO (10:29:00)
            $queryIn = DB::table('transaksi_stok')
                ->where('barang_id', $detail->barang_id)
                ->where('tanggal', '<', $tanggalTx);
            $queryOut = DB::table('transaksi_stok')
                ->where('barang_id', $detail->barang_id)
                ->where('tanggal', '<', $tanggalTx);

            if ($opname->gudang_id && $opname->divisi_id) {
                $queryIn->where('gudang_tujuan_id', $opname->gudang_id)->where('divisi_tujuan_id', $opname->divisi_id);
                $queryOut->where('gudang_asal_id', $opname->gudang_id)->where('divisi_asal_id', $opname->divisi_id);
            } elseif ($opname->gudang_id) {
                $queryIn->where('gudang_tujuan_id', $opname->gudang_id);
                $queryOut->where('gudang_asal_id', $opname->gudang_id);
            } elseif ($opname->divisi_id) {
                $queryIn->where('divisi_tujuan_id', $opname->divisi_id);
                $queryOut->where('divisi_asal_id', $opname->divisi_id);
            }

            $stokSistemAktual = max(0, (float)($queryIn->sum('qty') - $queryOut->sum('qty')));
            $stokFisik = (float)$detail->stok_fisik;

            if ($stokFisik < $stokSistemAktual) {
                $qtyKurang = max(0, min($stokSistemAktual, $stokSistemAktual - $stokFisik));
                $selisih = -$qtyKurang;
                $qtySurplus = 0;
            } else {
                $qtySurplus = $stokFisik - $stokSistemAktual;
                $selisih = $qtySurplus;
                $qtyKurang = 0;
            }

            $detail->update([
                'stok_sistem' => $stokSistemAktual,
                'selisih'     => $selisih,
            ]);

            $pbkDet = $pbk ? $pbk->details->firstWhere('barang_id', $detail->barang_id) : null;

            if ($selisih < 0 && $qtyKurang > 0) {
                $qtyKurang = abs((float) $selisih);

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

                    if ($pbk && $pbkDet && !empty($fifo['batch_id'])) {
                        \App\Models\PengeluaranBahanBakuFifo::create([
                            'pengeluaran_id' => $pbk->id,
                            'detail_id'      => $pbkDet->id,
                            'batch_id'       => $fifo['batch_id'],
                            'batch_number'   => $fifo['batch_number'],
                            'qty_keluar'     => $fifo['qty_keluar'],
                            'harga_per_qty'  => $fifo['harga_per_qty'],
                            'total_harga'    => $layerTotal,
                        ]);
                    }
                }

                // Fallback jika belum ada batch masuk sebelumnya: pakai harga terakhir barang
                if ($hppTotalKurang <= 0) {
                    $hargaUnit = $this->getHargaTerakhirBarang($detail->barang_id);
                    $hppTotalKurang = round($qtyKurang * $hargaUnit, 2);
                } else {
                    $hppTotalKurang = round($hppTotalKurang, 2);
                }

                // 2. Catat mutasi keluar di transaksi_stok (terbaca di Buku Pembantu Persediaan)
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

                // 3. Akumulasi untuk Jurnal Penyesuaian Shortage
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

            } elseif ($selisih > 0 && $qtySurplus > 0) {
                $hargaUnit = $this->getHargaTerakhirBarang($detail->barang_id);
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
                    'qty_masuk'           => $selisih,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $selisih,
                    'harga_per_qty'       => $hargaUnit,
                    'is_habis'            => false,
                ]);

                // 2. Catat mutasi masuk di transaksi_stok
                \App\Models\TransaksiStok::create([
                    'tanggal'          => $tanggalTx,
                    'tipe'             => 'masuk',
                    'source_type'      => 'stock_opname',
                    'source_id'        => $opname->id,
                    'gudang_tujuan_id' => $opname->gudang_id,
                    'divisi_tujuan_id' => $opname->divisi_id,
                    'barang_id'        => $detail->barang_id,
                    'qty'              => $selisih,
                    'total_harga'      => round($selisih * $hargaUnit, 2),
                    'created_by'       => Auth::id() ?? 1,
                ]);

                // 3. Akumulasi untuk Jurnal Penyesuaian (Surplus)
                $totalHargaSO = round($selisih * $hargaUnit, 2);
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

        // ── Update Pengeluaran Bahan Baku otomatis jika ada PBK ──
        if (!empty($itemSelisihNegatif) && !$pbk) {
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

        \App\Models\StokGudang::reconcileStockSummary(null, $opname->gudang_id, $opname->divisi_id);
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

    public function getHargaTerakhirBarang($barangId): float
    {
        $hargaBatch = DB::table('stok_gudang_batch')
            ->where('barang_id', $barangId)
            ->where('harga_per_qty', '>', 0)
            ->orderBy('id', 'desc')
            ->value('harga_per_qty');

        if ($hargaBatch && (float)$hargaBatch > 0) {
            return (float) $hargaBatch;
        }

        $hargaBeli = DB::table('pembelian_detail')
            ->where('barang_id', $barangId)
            ->where('harga_per_qty', '>', 0)
            ->orderBy('id', 'desc')
            ->value('harga_per_qty');

        if (!$hargaBeli) {
            $hargaBeli = DB::table('pembelian_detail')
                ->where('barang_id', $barangId)
                ->where('harga', '>', 0)
                ->orderBy('id', 'desc')
                ->value('harga');
        }

        if ($hargaBeli && (float)$hargaBeli > 0) {
            return (float) $hargaBeli;
        }

        $hppRef = DB::table('master_barang')
            ->where('id', $barangId)
            ->value('hpp_referensi');

        return (float) ($hppRef ?? 0);
    }

    public function hitungNilaiOpname($gudangId, $barangId, $selisih, $divisiId = null): float
    {
        if (abs($selisih) < 0.0001) {
            return 0.0;
        }

        if ($selisih < 0) {
            $nilaiFifo = $this->hitungNilaiFIFO($gudangId, $barangId, abs($selisih), $divisiId);
            if ($nilaiFifo > 0) {
                return round($nilaiFifo, 2);
            }
            $hargaTerakhir = $this->getHargaTerakhirBarang($barangId);
            return round(abs($selisih) * $hargaTerakhir, 2);
        } else {
            $hargaTerakhir = $this->getHargaTerakhirBarang($barangId);
            return round($selisih * $hargaTerakhir, 2);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT & IMPORT EXCEL
    |--------------------------------------------------------------------------
    */

    /**
     * Download Excel template atau list barang aktif gudang dengan stok sistem
     */
    public function downloadTemplate(Request $request)
    {
        $gudangId = $request->query('gudang_id');
        $divisiId = $request->query('divisi_id');
        $tanggal  = $request->query('tanggal', date('Y-m-d'));

        if (!$gudangId) {
            return redirect()->back()->with('error', 'Silakan pilih gudang terlebih dahulu.');
        }

        $gudang = MasterGudang::find($gudangId);
        $divisi = $divisiId ? GudangDivisi::find($divisiId) : null;
        $namaGudang = $gudang ? $gudang->nama : 'Gudang';

        // Load items using same query as loadBarang logic
        $query = \App\Models\MasterBarang::with('kategori')->where('is_active', true);

        // Filter bahan baku sesuai alokasi divisi
        $query->where(function ($q) use ($gudangId, $divisiId) {
            if ($divisiId) {
                $q->where(function($subBaku) use ($divisiId) {
                    $subBaku->where('is_bahan_baku', true)
                            ->whereExists(function($existsQuery) use ($divisiId) {
                                $existsQuery->select(DB::raw(1))
                                    ->from('barang_minimum_stock')
                                    ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                                    ->where('barang_minimum_stock.divisi_id', $divisiId)
                                    ->where('barang_minimum_stock.is_active', true);
                            });
                })->orWhere(function($subNonBaku) {
                    $subNonBaku->where('is_bahan_baku', false);
                });
            } else {
                $q->where('is_bahan_baku', false)
                  ->orWhereNotExists(function ($notExistsQuery) use ($gudangId) {
                      $notExistsQuery->select(DB::raw(1))
                          ->from('barang_minimum_stock')
                          ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                          ->where('barang_minimum_stock.gudang_id', $gudangId)
                          ->where('barang_minimum_stock.is_active', false)
                          ->whereNull('barang_minimum_stock.divisi_id');
                  });
            }
        });

        $barangs = $query->orderBy('kode_barang', 'asc')->get();

        $items = [];

        foreach ($barangs as $b) {
            $stokSistem = $this->hitungStokSistemByTanggal($gudangId, $b->id, $tanggal, $divisiId);
            $hargaFifo = $this->getHargaTerakhirBarang($b->id);

            $items[] = [
                'kode_barang' => $b->kode_barang,
                'nama'        => $b->nama,
                'kategori'    => $b->kategori->nama ?? '-',
                'satuan'      => $b->satuan_stok ?? $b->satuan ?? 'PCS',
                'stok_sistem' => (float) $stokSistem,
                'stok_fisik'  => (float) $stokSistem,
                'harga_fifo'  => (float) $hargaFifo,
            ];
        }

        $title = "Template Stock Opname - " . $namaGudang . ($divisi ? " ({$divisi->nama})" : "");
        $slugGudang = \Illuminate\Support\Str::slug($namaGudang, '_');
        return $this->generateExcelSpreadsheet($items, $title, "Template_Stock_Opname_{$slugGudang}_{$tanggal}.xlsx");
    }

    /**
     * Export Excel dari dokumen Stock Opname yang sudah tersimpan (Draft / Approved)
     */
    public function exportExcel($id)
    {
        $opname = StockOpname::with(['gudang', 'divisi', 'details.barang.kategori'])->findOrFail($id);
        $namaGudang = $opname->gudang ? $opname->gudang->nama : 'Gudang';

        $items = [];
        foreach ($opname->details as $d) {
            $b = $d->barang;
            $items[] = [
                'kode_barang' => $b ? $b->kode_barang : '-',
                'nama'        => $b ? $b->nama : '-',
                'kategori'    => ($b && $b->kategori) ? $b->kategori->nama : '-',
                'satuan'      => $b ? ($b->satuan_stok ?? $b->satuan ?? 'PCS') : 'PCS',
                'stok_sistem' => (float) $d->stok_sistem,
                'stok_fisik'  => (float) $d->stok_fisik,
                'harga_fifo'  => (float) ($d->selisih != 0 ? abs($d->nilai_selisih / $d->selisih) : $this->getHargaTerakhirBarang($d->barang_id)),
            ];
        }

        $title = "Stock Opname " . $opname->kode_opname . " - " . $namaGudang;
        $tanggalStr = date('Y-m-d', strtotime($opname->tanggal));
        return $this->generateExcelSpreadsheet($items, $title, "Stock_Opname_{$opname->kode_opname}_{$tanggalStr}.xlsx");
    }

    /**
     * Shared helper to construct and download Excel file
     */
    private function generateExcelSpreadsheet(array $items, string $title, string $fileName)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Opname');

        // Header Title
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = [
            'No',
            'Kode Barang',
            'Nama Barang',
            'Kategori',
            'Satuan',
            'Stok Sistem',
            'Stok Fisik',
            'Selisih',
            'Harga FIFO / HPP',
            'Nilai Selisih'
        ];

        $startRow = 3;
        $sheet->fromArray($headers, null, "A{$startRow}");

        // Format header row
        $headerRange = "A{$startRow}:J{$startRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('7A4517'); // Theme color

        // Highlight Stok Fisik header column (G)
        $sheet->getStyle("G{$startRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('2E7D32'); // Dark green

        $row = $startRow + 1;
        $no = 1;

        foreach ($items as $item) {
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $item['kode_barang']);
            $sheet->setCellValue("C{$row}", $item['nama']);
            $sheet->setCellValue("D{$row}", $item['kategori']);
            $sheet->setCellValue("E{$row}", $item['satuan']);
            $sheet->setCellValue("F{$row}", $item['stok_sistem']);
            $sheet->setCellValue("G{$row}", $item['stok_fisik']);
            $sheet->setCellValue("H{$row}", "=G{$row}-F{$row}");
            $sheet->setCellValue("I{$row}", $item['harga_fifo']);
            $sheet->setCellValue("J{$row}", "=H{$row}*I{$row}");

            // Colorize column G (Stok Fisik) input cell
            $sheet->getStyle("G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8F5E9'); // Light green

            $no++;
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= $startRow + 1) {
            // Number formatting
            $sheet->getStyle("F" . ($startRow + 1) . ":H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("I" . ($startRow + 1) . ":J{$lastRow}")->getNumberFormat()->setFormatCode('Rp #,##0.00');
            
            // Borders
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D0D0D0'],
                    ],
                ],
            ];
            $sheet->getStyle("A{$startRow}:J{$lastRow}")->applyFromArray($borderStyle);
        }

        // Auto-fit columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        if (ob_get_length()) ob_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Import Excel file to update stok_fisik in form
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file_excel');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $importedItems = [];
            // Auto-detect header row (could be row 1 or row 3)
            $headerRowIndex = 1;
            $kodeColLetter = 'B';
            $stokFisikColLetter = 'G';

            foreach ($rows as $rowIndex => $row) {
                // Check if row contains 'Kode Barang' or 'Kode'
                foreach ($row as $colLetter => $val) {
                    if (is_string($val) && (strcasecmp(trim($val), 'Kode Barang') === 0 || strcasecmp(trim($val), 'Kode') === 0)) {
                        $headerRowIndex = $rowIndex;
                        $kodeColLetter = $colLetter;
                        break;
                    }
                }
                if ($headerRowIndex === $rowIndex) {
                    foreach ($row as $colLetter => $val) {
                        if (is_string($val) && strcasecmp(trim($val), 'Stok Fisik') === 0) {
                            $stokFisikColLetter = $colLetter;
                            break;
                        }
                    }
                    break;
                }
            }

            for ($r = $headerRowIndex + 1; $r <= count($rows); $r++) {
                $row = $rows[$r] ?? null;
                if (!$row) continue;

                $kodeBarang = trim((string)($row[$kodeColLetter] ?? ''));
                $stokFisikVal = $row[$stokFisikColLetter] ?? null;

                if (!empty($kodeBarang)) {
                    $cleanStokFisik = 0;
                    if ($stokFisikVal !== null && $stokFisikVal !== '') {
                        if (is_numeric($stokFisikVal)) {
                            $cleanStokFisik = (float) $stokFisikVal;
                        } else if (is_string($stokFisikVal)) {
                            $valStr = trim($stokFisikVal);
                            if (strpos($valStr, '.') !== false && strpos($valStr, ',') !== false) {
                                $valStr = str_replace('.', '', $valStr);
                                $valStr = str_replace(',', '.', $valStr);
                            } else if (strpos($valStr, ',') !== false) {
                                $valStr = str_replace(',', '.', $valStr);
                            }
                            $cleanStokFisik = (float) $valStr;
                        }
                    }

                    $importedItems[] = [
                        'kode_barang' => $kodeBarang,
                        'stok_fisik'  => $cleanStokFisik,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Berhasil membaca ' . count($importedItems) . ' item dari file Excel.',
                'data'    => $importedItems,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Membuat dokumen Stock Opname (Draft) baru secara langsung dari file Excel yang diunggah
     */
    public function importStore(Request $request)
    {
        $request->validate([
            'gudang_id'  => 'required|exists:master_gudang,id',
            'divisi_id'  => 'nullable|exists:gudang_divisi,id',
            'tanggal'    => 'required|date',
            'keterangan' => 'nullable|string',
            'file_excel' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;
        $tanggal  = $request->tanggal;
        $keterangan = $request->keterangan ?? 'Import Stock Opname dari Excel';

        // 1. Parse Excel file & extract ONLY kode_barang and stok_fisik
        try {
            $file = $request->file('file_excel');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $excelStokFisikMap = [];
            $headerRowIndex = 1;
            $kodeColLetter = 'B';
            $stokFisikColLetter = 'G';

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $colLetter => $val) {
                    if (is_string($val) && (strcasecmp(trim($val), 'Kode Barang') === 0 || strcasecmp(trim($val), 'Kode') === 0)) {
                        $headerRowIndex = $rowIndex;
                        $kodeColLetter = $colLetter;
                        break;
                    }
                }
                if ($headerRowIndex === $rowIndex) {
                    foreach ($row as $colLetter => $val) {
                        if (is_string($val) && strcasecmp(trim($val), 'Stok Fisik') === 0) {
                            $stokFisikColLetter = $colLetter;
                            break;
                        }
                    }
                    break;
                }
            }

            for ($r = $headerRowIndex + 1; $r <= count($rows); $r++) {
                $row = $rows[$r] ?? null;
                if (!$row) continue;

                $kodeBarang = trim((string)($row[$kodeColLetter] ?? ''));
                $stokFisikVal = $row[$stokFisikColLetter] ?? null;

                if (!empty($kodeBarang)) {
                    $cleanStokFisik = 0;
                    if ($stokFisikVal !== null && $stokFisikVal !== '') {
                        if (is_numeric($stokFisikVal)) {
                            $cleanStokFisik = (float) $stokFisikVal;
                        } else if (is_string($stokFisikVal)) {
                            $valStr = trim($stokFisikVal);
                            if (strpos($valStr, '.') !== false && strpos($valStr, ',') !== false) {
                                $valStr = str_replace('.', '', $valStr);
                                $valStr = str_replace(',', '.', $valStr);
                            } else if (strpos($valStr, ',') !== false) {
                                $valStr = str_replace(',', '.', $valStr);
                            }
                            $cleanStokFisik = (float) $valStr;
                        }
                    }
                    $excelStokFisikMap[strtolower($kodeBarang)] = $cleanStokFisik;
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage());
        }

        if (empty($excelStokFisikMap)) {
            return redirect()->back()->with('error', 'File Excel tidak berisi data Kode Barang atau Stok Fisik yang valid.');
        }

        // 2. Load active items for that gudang/divisi
        $query = \App\Models\MasterBarang::with('kategori')->where('is_active', true);

        // Filter bahan baku dinonaktifkan di outlet & divisi
        $query->where(function ($q) use ($gudangId, $divisiId) {
            $q->where('is_bahan_baku', false)
              ->orWhere(function ($subQ) use ($gudangId, $divisiId) {
                  $subQ->where('is_bahan_baku', true)
                       ->whereNotExists(function ($notExistsQuery) use ($gudangId, $divisiId) {
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
              });
        });

        $barangs = $query->get();

        DB::beginTransaction();
        try {
            // Generate Kode Opname
            $prefix = 'SO-' . date('Ymd', strtotime($tanggal)) . '-';
            $lastOpname = StockOpname::where('kode_opname', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
            if ($lastOpname) {
                $lastNumber = (int) substr($lastOpname->kode_opname, -4);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }
            $kodeOpname = $prefix . $newNumber;

            $opname = StockOpname::create([
                'kode_opname' => $kodeOpname,
                'tanggal'     => $tanggal,
                'gudang_id'   => $gudangId,
                'divisi_id'   => $divisiId,
                'status'      => 'draft',
                'keterangan'  => $keterangan,
                'created_by'  => Auth::id(),
            ]);

            $totalDetailCount = 0;

            foreach ($barangs as $b) {
                $key = strtolower(trim($b->kode_barang));
                $stokSistem = $this->hitungStokSistemByTanggal($gudangId, $b->id, $tanggal, $divisiId);
                
                if (isset($excelStokFisikMap[$key])) {
                    $stokFisik = $excelStokFisikMap[$key];
                } else {
                    $stokFisik = (float) $stokSistem;
                }

                $selisih = $stokFisik - (float)$stokSistem;
                $nilaiSelisih = $this->hitungNilaiOpname($gudangId, $b->id, $selisih, $divisiId);

                StockOpnameDetail::create([
                    'stock_opname_id' => $opname->id,
                    'barang_id'       => $b->id,
                    'stok_sistem'     => $stokSistem,
                    'stok_fisik'      => $stokFisik,
                    'selisih'         => $selisih,
                    'nilai_selisih'   => $nilaiSelisih,
                ]);

                $totalDetailCount++;
            }

            DB::commit();

            return redirect()->route('stock-opname.show', $opname->id)
                ->with('success', "Berhasil membuat Stock Opname {$opname->kode_opname} dengan {$totalDetailCount} detail barang dari file Excel.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan dokumen Stock Opname: ' . $e->getMessage());
        }
    }

    /**
     * Hitung stok sistem per tanggal cutoff
     */
    private function hitungStokSistemByTanggal($gudangId, $barangId, $tanggal = null, $divisiId = null): float
    {
        if ($tanggal && date('Y-m-d', strtotime($tanggal)) !== date('Y-m-d')) {
            $cutoff = date('Y-m-d', strtotime($tanggal)) . ' 23:59:59';
            $qIn = DB::table('transaksi_stok')->where('barang_id', $barangId)->where('tanggal', '<=', $cutoff);
            $qOut = DB::table('transaksi_stok')->where('barang_id', $barangId)->where('tanggal', '<=', $cutoff);
            if ($gudangId && $divisiId) {
                $qIn->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                $qOut->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
            } elseif ($gudangId) {
                $qIn->where('gudang_tujuan_id', $gudangId);
                $qOut->where('gudang_asal_id', $gudangId);
            }
            return max(0, (float)($qIn->sum('qty') - $qOut->sum('qty')));
        } else {
            $q = DB::table('stok_gudang')->where('gudang_id', $gudangId)->where('barang_id', $barangId);
            if ($divisiId) {
                $q->where('divisi_id', $divisiId);
            } else {
                $q->whereNull('divisi_id');
            }
            return (float) ($q->value('jumlah') ?? 0);
        }
    }
}