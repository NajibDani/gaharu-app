<?php

namespace App\Http\Controllers;

use App\Models\MasterGudang;
use App\Models\MasterBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokGudangController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;
        $barangId = $request->barang_id;

        // Auto filter warehouse based on role
        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangId = 4;
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangId = 2;
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangId = 1;
        }

        /*
        |--------------------------------------------------------------------------
        | QUERY UTAMA
        |--------------------------------------------------------------------------
        */

        $query = DB::table('stok_gudang')
            ->join('master_barang', 'master_barang.id', '=', 'stok_gudang.barang_id')
            ->join('master_gudang',  'master_gudang.id',  '=', 'stok_gudang.gudang_id')
            ->leftJoin('gudang_divisi', 'gudang_divisi.id', '=', 'stok_gudang.divisi_id')
            ->select([
                'master_barang.id',
                'master_barang.kode_barang',
                'master_barang.nama',
                'master_barang.satuan',
                'master_gudang.nama   as nama_gudang',
                'gudang_divisi.nama   as nama_divisi',
                'stok_gudang.gudang_id',
                'stok_gudang.divisi_id',
                'stok_gudang.jumlah   as qty',
            ]);

        if ($gudangId) {
            $query->where('stok_gudang.gudang_id', $gudangId);
        }

        if ($divisiId) {
            $query->where('stok_gudang.divisi_id', $divisiId);
        }

        if ($barangId) {
            $query->where('master_barang.id', $barangId);
        }

        // Filter bahan baku yang dinonaktifkan di outlet & divisi masing-masing
        $query->where(function($q) {
            $q->where('master_barang.is_bahan_baku', 0)
              ->orWhereNotExists(function($notExistsQuery) {
                  $notExistsQuery->select(DB::raw(1))
                      ->from('barang_minimum_stock')
                      ->whereColumn('barang_minimum_stock.barang_id', 'master_barang.id')
                      ->whereColumn('barang_minimum_stock.gudang_id', 'stok_gudang.gudang_id')
                      ->where(function($divQ) {
                          $divQ->whereColumn('barang_minimum_stock.divisi_id', 'stok_gudang.divisi_id')
                               ->orWhere(function($subDivQ) {
                                   $subDivQ->whereNull('barang_minimum_stock.divisi_id')
                                           ->whereNull('stok_gudang.divisi_id');
                               });
                      })
                      ->where('barang_minimum_stock.is_active', false);
              });
        });

        // Ambil semua hasil (kita paginate manual di bawah)
        $rows = $query->orderBy('master_barang.nama')->orderBy('master_gudang.nama')->get();

        /*
        |--------------------------------------------------------------------------
        | PAGINATE MANUAL (Slice Terlebih Dahulu Sebelum Map untuk Efisiensi)
        |--------------------------------------------------------------------------
        */
        $perPage     = 20;
        $currentPage = (int) ($request->page ?? 1);
        $total       = $rows->count();
        $items       = $rows->slice(($currentPage - 1) * $perPage, $perPage)->values();

        /*
        |--------------------------------------------------------------------------
        | BULK PRE-FETCH HARGA FIFO & FALLBACK
        |--------------------------------------------------------------------------
        */
        $itemIds = $items->pluck('id')->toArray();
        $gudangIds = $items->pluck('gudang_id')->unique()->toArray();

        // 1. Ambil harga rata-rata dari batch aktif
        $batchPrices = DB::table('stok_gudang_batch')
            ->whereIn('barang_id', $itemIds)
            ->whereIn('gudang_id', $gudangIds)
            ->where('qty_sisa', '>', 0)
            ->select('barang_id', 'gudang_id', 'divisi_id')
            ->selectRaw('AVG(harga_per_qty) as avg_harga')
            ->groupBy('barang_id', 'gudang_id', 'divisi_id')
            ->get()
            ->keyBy(fn($x) => $x->barang_id . '-' . $x->gudang_id . '-' . ($x->divisi_id ?? '0'));

        // 2. Fallback: Ambil harga rata-rata dari semua batch historis
        $historicalPrices = DB::table('stok_gudang_batch')
            ->whereIn('barang_id', $itemIds)
            ->whereIn('gudang_id', $gudangIds)
            ->select('barang_id', 'gudang_id', 'divisi_id')
            ->selectRaw('AVG(harga_per_qty) as avg_harga')
            ->groupBy('barang_id', 'gudang_id', 'divisi_id')
            ->get()
            ->keyBy(fn($x) => $x->barang_id . '-' . $x->gudang_id . '-' . ($x->divisi_id ?? '0'));

        // 3. Fallback akhir: HPP referensi master barang
        $hppReferences = DB::table('master_barang')
            ->whereIn('id', $itemIds)
            ->pluck('hpp_referensi', 'id');

        /*
        |--------------------------------------------------------------------------
        | HITUNG STATUS & NILAI FIFO PER BARIS (Hanya pada items halaman aktif)
        |--------------------------------------------------------------------------
        */
        $items = $items->map(function ($row) use ($batchPrices, $historicalPrices, $hppReferences) {
            $row->status = $row->qty > 0 ? 'tersedia' : 'habis';

            $key = $row->id . '-' . $row->gudang_id . '-' . ($row->divisi_id ?? '0');
            
            // Cek harga FIFO batch aktif
            $hargaFifo = $batchPrices->get($key)?->avg_harga;

            // Fallback rata-rata batch historis
            if (!$hargaFifo) {
                $hargaFifo = $historicalPrices->get($key)?->avg_harga;
            }

            // Fallback HPP referensi
            if (!$hargaFifo) {
                $hargaFifo = $hppReferences->get($row->id) ?? 0;
            }

            $row->harga_fifo  = (float) $hargaFifo;
            $row->nilai_stok  = $row->qty * $row->harga_fifo;

            return $row;
        });

        $stokGudang = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );


        /*
        |--------------------------------------------------------------------------
        | FILTER OPTIONS
        |--------------------------------------------------------------------------
        */

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::with('divisi')->where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::with('divisi')->where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::with('divisi')->where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        }
        $barangs = MasterBarang::orderBy('nama')->get();

        return view(
            'stok-gudang.index',
            compact('stokGudang', 'gudangs', 'barangs', 'gudangId', 'divisiId', 'barangId')
        );
    }

    public function bukuPembantuIndex(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        $gudangId = $request->gudang_id;
        $search = $request->search;

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangId = 4;
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangId = 2;
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangId = 1;
        }

        $startDate = $request->start_date ?: date('Y-m-01');
        $endDate = $request->end_date ?: date('Y-m-d');

        $query = MasterBarang::query()->with('kategori');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode_barang', 'like', '%' . $search . '%');
            });
        }

        $items = $query->orderBy('nama')->paginate(20)->withQueryString();

        foreach ($items as $item) {
            $item->stok_akhir = $this->calculateStockAtDate($item->id, $gudangId, $endDate);
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

        return view('stok-gudang.buku-pembantu', compact(
            'items', 'gudangs', 'gudangId', 'startDate', 'endDate', 'search'
        ));
    }

    private function calculateStockAtDate($barangId, $gudangId, $date)
    {
        $queryIn = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<=', $date . ' 23:59:59');

        $queryOut = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<=', $date . ' 23:59:59');

        if ($gudangId) {
            $queryIn->where('gudang_tujuan_id', $gudangId);
            $queryOut->where('gudang_asal_id', $gudangId);

            $in = $queryIn->sum('qty');
            $out = $queryOut->sum('qty');
        } else {
            $in = $queryIn->where('tipe', 'masuk')->sum('qty');
            $out = $queryOut->where('tipe', 'keluar')->sum('qty');
        }

        return max(0, floatval($in) - floatval($out));
    }

    public function bukuPembantuMutasi(Request $request)
    {
        $barangId = $request->barang_id;
        $gudangId = $request->gudang_id;
        $startDate = $request->start_date ?: date('Y-m-01');
        $endDate = $request->end_date ?: date('Y-m-d');

        $saQty = 0;
        $saNilai = 0;

        $rawBefore = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<', $startDate . ' 00:00:00');

        if ($gudangId) {
            $rawBefore->where(function ($q) use ($gudangId) {
                $q->where('gudang_asal_id', $gudangId)
                  ->orWhere('gudang_tujuan_id', $gudangId);
            });
        }

        $itemsBefore = $rawBefore->orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();

        foreach ($itemsBefore as $row) {
            $qty = floatval($row->qty);
            $totalHarga = floatval($row->total_harga);

            $isMasuk = false;
            $isKeluar = false;

            if ($gudangId) {
                if ($row->gudang_tujuan_id == $gudangId) {
                    $isMasuk = true;
                } elseif ($row->gudang_asal_id == $gudangId) {
                    $isKeluar = true;
                }
            } else {
                if ($row->tipe === 'masuk') {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar') {
                    $isKeluar = true;
                }
            }

            if ($isMasuk) {
                $saQty += $qty;
                $saNilai += $totalHarga;
            } elseif ($isKeluar) {
                $saQty -= $qty;
                $saNilai -= $totalHarga;
            }
        }

        $rawPeriod = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->whereBetween('tanggal', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($gudangId) {
            $rawPeriod->where(function ($q) use ($gudangId) {
                $q->where('gudang_asal_id', $gudangId)
                  ->orWhere('gudang_tujuan_id', $gudangId);
            });
        }

        $itemsPeriod = $rawPeriod->orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();

        $mutations = [];
        $runningQty = $saQty;
        $runningNilai = $saNilai;

        foreach ($itemsPeriod as $row) {
            $qty = floatval($row->qty);
            $totalHarga = floatval($row->total_harga);
            $hargaSatuan = $qty > 0 ? ($totalHarga / $qty) : 0;

            $isMasuk = false;
            $isKeluar = false;

            if ($gudangId) {
                if ($row->gudang_tujuan_id == $gudangId) {
                    $isMasuk = true;
                } elseif ($row->gudang_asal_id == $gudangId) {
                    $isKeluar = true;
                }
            } else {
                if ($row->tipe === 'masuk') {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar') {
                    $isKeluar = true;
                }
            }

            if ($isMasuk || $isKeluar) {
                if ($isMasuk) {
                    $runningQty += $qty;
                    $runningNilai += $totalHarga;
                } else {
                    $runningQty -= $qty;
                    $runningNilai -= $totalHarga;
                }

                $keterangan = $this->formatSourceDescription($row->source_type, $row->source_id);
                if ($row->tipe === 'transfer') {
                    $gAsal = DB::table('master_gudang')->where('id', $row->gudang_asal_id)->value('nama');
                    $gTujuan = DB::table('master_gudang')->where('id', $row->gudang_tujuan_id)->value('nama');
                    $keterangan = "Transfer: dari {$gAsal} ke {$gTujuan}";
                }

                $mutations[] = [
                    'id' => $row->id,
                    'tanggal_formatted' => date('d/m/Y H:i', strtotime($row->tanggal)),
                    'keterangan' => $keterangan,
                    'is_masuk' => $isMasuk,
                    'qty' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'total_harga' => $totalHarga,
                    'saldo_qty' => $runningQty,
                    'saldo_nilai' => $runningNilai,
                ];
            }
        }

        return response()->json([
            'saldo_awal' => [
                'qty' => $saQty,
                'nilai' => $saNilai
            ],
            'mutasi' => $mutations,
            'saldo_akhir' => [
                'qty' => $runningQty,
                'nilai' => $runningNilai
            ]
        ]);
    }

    private function formatSourceDescription($type, $id)
    {
        if (empty($type) || empty($id)) {
            return 'Manual / Saldo Awal';
        }

        switch (strtolower($type)) {
            case 'pembelian':
                $p = \App\Models\Pembelian::with('supplier')->find($id);
                if ($p) {
                    $supplierName = $p->supplier->nama ?? '-';
                    return "Pembelian: {$p->kode_pembelian} [Supplier: {$supplierName}]";
                }
                return "Pembelian (ID: {$id})";

            case 'penerimaan_pembelian':
                $rcv = \App\Models\PenerimaanPembelian::with('pembelian.supplier')->find($id);
                if ($rcv) {
                    $supplierName = $rcv->pembelian->supplier->nama ?? '-';
                    return "Penerimaan Pembelian: {$rcv->no_penerimaan} [Supplier: {$supplierName}]";
                }
                return "Penerimaan Pembelian (ID: {$id})";

            case 'pengeluaran_bahan_baku':
                $out = \App\Models\PengeluaranBahanBaku::with(['gudang', 'divisi'])->find($id);
                if ($out) {
                    $gudangTujuan = $out->gudang->nama ?? '';
                    $divisiTujuan = $out->divisi->nama ?? '';
                    $tujuanInfo = [];
                    if ($gudangTujuan) $tujuanInfo[] = "Gudang: {$gudangTujuan}";
                    if ($divisiTujuan) $tujuanInfo[] = "Divisi: {$divisiTujuan}";
                    $tujuanStr = !empty($tujuanInfo) ? ' [' . implode(' - ', $tujuanInfo) . ']' : '';
                    return "Material Output: {$out->no_pengeluaran}{$tujuanStr}";
                }
                return "Material Output (ID: {$id})";

            case 'produksi':
                $prod = \App\Models\Produksi::find($id);
                if ($prod) {
                    return "Produksi: {$prod->kode_produksi}";
                }
                return "Produksi (ID: {$id})";

            case 'pengiriman':
                $del = \App\Models\Pengiriman::find($id);
                if ($del) {
                    return "Pengiriman B2B: {$del->no_pengiriman}";
                }
                return "Pengiriman (ID: {$id})";

            case 'stock_opname':
                $opname = \App\Models\StockOpname::find($id);
                if ($opname) {
                    return "Stock Opname: {$opname->no_opname}";
                }
                return "Stock Opname (ID: {$id})";

            case 'penjualan_pos':
                $pos = \App\Models\PenjualanPos::find($id);
                if ($pos) {
                    return "Penjualan POS: {$pos->kode_penjualan}";
                }
                return "Penjualan POS (ID: {$id})";

            default:
                return ucfirst(str_replace('_', ' ', $type)) . " (ID: {$id})";
        }
    }
}