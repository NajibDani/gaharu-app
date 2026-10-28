<?php

namespace App\Http\Controllers;

use App\Models\MasterGudang;
use App\Models\MasterBarang;
use App\Models\Pengiriman;
use App\Models\PengirimanDetail;
use App\Models\ProduksiPesanan;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokGudangController extends Controller
{
    private static array $sourceDescCache = [];
    private static array $pembelianDetailCache = [];
    private static array $gudangNamaCache = [];

    public function index(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        $gudangId    = $request->gudang_id;
        $divisiId    = $request->divisi_id;
        $barangId    = $request->barang_id;
        $jenisBarang = $request->jenis_barang ?? $request->jenis_utama;
        

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
                'master_barang.satuan_pembelian',
                'master_barang.konversi_pembelian',
                'master_barang.is_bahan_baku',
                'master_barang.is_bahan_setengah_jadi',
                'master_barang.is_barang_jadi',
                'master_barang.is_operational',
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

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('master_barang.nama', 'like', "%{$search}%")
                  ->orWhere('master_barang.kode_barang', 'like', "%{$search}%");
            });
        }

        if ($jenisBarang) {
            $kolom = match($jenisBarang) {
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

        // Paginasi langsung di tingkat database (jauh lebih hemat memori dan CPU)
        $stokGudang = $query->orderBy('master_barang.nama')
            ->orderBy('master_gudang.nama')
            ->paginate(20)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | BULK PRE-FETCH HARGA FIFO & FALLBACK (Hanya pada 20 items halaman aktif)
        |--------------------------------------------------------------------------
        */
        $itemIds   = $stokGudang->pluck('id')->unique()->toArray();
        $gudangIds = $stokGudang->pluck('gudang_id')->unique()->toArray();

        // Rekonsiliasi mutasi orphan dan ringkasan stok untuk 20 item pada halaman aktif hanya jika diminta secara eksplisit
        if ($request->has('refresh') || $request->has('sync')) {
            foreach ($itemIds as $bId) {
                self::autoCleanOrphanMutations($bId);
                \App\Models\StokGudang::reconcileStockSummary($bId);
            }
        }

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
        | HITUNG STATUS & NILAI FIFO PER BARIS (Transform items paginasi)
        |--------------------------------------------------------------------------
        */
        $stokGudang->getCollection()->transform(function ($row) use ($batchPrices, $historicalPrices, $hppReferences) {
            // Ambil kuantitas stok yang telah terekonsiliasi dari buku pembantu / stok_gudang
            $reconciledJumlah = DB::table('stok_gudang')
                ->where('barang_id', $row->id)
                ->where('gudang_id', $row->gudang_id)
                ->when($row->divisi_id, fn($q) => $q->where('divisi_id', $row->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                ->value('jumlah');

            if ($reconciledJumlah !== null) {
                $row->qty = (float) $reconciledJumlah;
            }

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


        /*
        |--------------------------------------------------------------------------
        | FILTER OPTIONS
        |--------------------------------------------------------------------------
        */

        $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        $barangs = MasterBarang::orderBy('nama')->get();

        return view(
            'stok-gudang.index',
            compact('stokGudang', 'gudangs', 'barangs', 'gudangId', 'divisiId', 'barangId', 'jenisBarang')
        );
    }

    public function bukuPembantuIndex(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        $gudangId    = $request->gudang_id;
        $divisiId    = $request->divisi_id;
        $search      = $request->search;
        $jenisBarang = $request->jenis_barang;
        $kategoriId  = $request->kategori_id;

        $startDate = $request->start_date ?: date('Y-m-01');
        $endDate   = $request->end_date ?: date('Y-m-d');

        // Pastikan seluruh BSJ hasil produksi Central Kitchen sinkron hanya saat refresh diminta
        if ($request->has('refresh') || $request->has('sync')) {
            self::autoHealApprovedCkProductionTransfers();
        }

        $query = MasterBarang::query()->with('kategori');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode_barang', 'like', '%' . $search . '%');
            });
        }

        if ($jenisBarang) {
            $kolom = match ($jenisBarang) {
                'bahan_baku'          => 'is_bahan_baku',
                'bahan_setengah_jadi' => 'is_bahan_setengah_jadi',
                'barang_jadi'         => 'is_barang_jadi',
                'operational'         => 'is_operational',
                default               => null,
            };
            if ($kolom) {
                $query->where($kolom, true);
            }
        }

        if ($kategoriId) {
            $query->where('kategori_id', $kategoriId);
        }

        $items = $query->orderBy('nama')->paginate(20)->withQueryString();

        // Hitung stok akhir untuk 20 barang pada halaman aktif secara bulk dalam 1 query agregat
        $itemIds = $items->pluck('id')->toArray();
        if (!empty($itemIds)) {
            if ($request->has('refresh') || $request->has('sync')) {
                \App\Http\Controllers\PembelianController::autoHealHistoricalPembelianPenerimaanTanggal();
                foreach ($itemIds as $bId) {
                    self::autoHealApprovedCkProductionTransfers($bId);
                    self::autoCleanOrphanMutations($bId);
                    self::autoHealMissingDivisiInTransaksiStok($bId);
                    self::autoHealApprovedPaTransaksiStok($bId);
                    self::autoHealApprovedPbkTransaksiStok($bId);
                }
            }
            $bulkStok = \App\Models\StokGudang::getBulkStokBukuPembantu($itemIds, $gudangId, $divisiId, $endDate);
            foreach ($items as $item) {
                $item->stok_akhir = (float) ($bulkStok[$item->id] ?? 0);
            }
        }

        $gudangs    = MasterGudang::orderBy('nama')->get();
        $divisis    = \App\Models\GudangDivisi::with('gudang')->orderBy('nama')->get();
        $kategoris  = \App\Models\Kategori::orderBy('nama')->get();

        return view('stok-gudang.buku-pembantu', compact(
            'items', 'gudangs', 'divisis', 'kategoris', 'gudangId', 'divisiId', 'startDate', 'endDate', 'search', 'jenisBarang', 'kategoriId'
        ));
    }

    private function calculateStockAtDate($barangId, $gudangId, $divisiId, $date)
    {
        return \App\Models\StokGudang::getStokBukuPembantu($barangId, $gudangId, $divisiId, $date);
    }

    public function bukuPembantuMutasi(Request $request)
    {
        $barangId  = $request->barang_id;
        $gudangId  = $request->gudang_id;
        $divisiId  = $request->divisi_id;
        $startDate = $request->start_date ?: date('Y-m-01');
        $endDate   = $request->end_date ?: date('Y-m-d');

        // Cek cepat jika ada transfer produksi CK selesai yang belum tercatat untuk barang ini
        if ($barangId && !$request->boolean('refresh')) {
            $hasUnrecordedCk = DB::table('alokasi_produksi_pesanan as app')
                ->join('produksi as p', 'app.produksi_id', '=', 'p.id')
                ->join('pesanan as pes', 'app.pesanan_id', '=', 'pes.id')
                ->where(DB::raw('LOWER(p.status_produksi)'), 'selesai')
                ->where('app.qty_alokasi', '>', 0)
                ->where('app.produk_id', $barangId)
                ->when($gudangId, function($q) use ($gudangId) {
                    $q->where('pes.gudang_id', $gudangId);
                })
                ->whereNotExists(function($sq) {
                    $sq->select(DB::raw(1))
                       ->from('transaksi_stok')
                       ->whereRaw('transaksi_stok.barang_id = app.produk_id')
                       ->whereRaw('transaksi_stok.gudang_tujuan_id = pes.gudang_id')
                       ->where('tipe', 'masuk')
                       ->where('source_type', 'transfer_ck')
                       ->whereRaw('transaksi_stok.source_id = app.produksi_id');
                })
                ->exists();

            if ($hasUnrecordedCk) {
                self::autoHealApprovedCkProductionTransfers($barangId);
            }
        }

        // Jalankan auto-healing penuh jika tombol refresh ditekan atau diminta secara eksplisit
        if ($request->boolean('refresh')) {
            if ($barangId) {
                self::autoHealApprovedCkProductionTransfers($barangId);
                \App\Http\Controllers\PembelianController::autoHealHistoricalPembelianPenerimaanTanggal($barangId);
            }
            // Auto-clean mutasi orphan untuk barang ini sebelum perhitungan mutasi
            self::autoCleanOrphanMutations($barangId);

            // Auto-heal jika ada batch pembelian yang kuantitasnya belum terkonversi (tersimpan satuan beli, bukan satuan stok dasar)
            MasterBarang::autoHealUnconvertedPembelianBatches($barangId);
            MasterBarang::autoHealSaldoAwalBatches($barangId);

            // Bersihkan resep duplikat agar pemotongan resep akurat
            self::autoCleanDuplicateResepBahanBaku($barangId);

            // Auto-heal mutasi yang divisi-nya belum tersinkronisasi di transaksi_stok
            self::autoHealMissingDivisiInTransaksiStok($barangId);

            // Auto-heal timestamp mutasi stock opname agar berada di penutupan hari (23:59:00) setelah transaksi harian
            self::autoHealStockOpnameTimestamp($barangId);

            // Auto-heal mutasi Penjualan POS disetujui / Moka import
            self::autoHealApprovedPosStockMutations($barangId);

            // Auto-heal mutasi Persediaan Awal disetujui
            self::autoHealApprovedPaTransaksiStok($barangId);

            // Auto-heal mutasi PBK disetujui yang belum tercatat atau belum lengkap di transaksi_stok
            self::autoHealApprovedPbkTransaksiStok($barangId);

            // Auto-heal mutasi stok opname prematur yang PBK-nya masih berstatus Draft
            $this->autoHealPrematureDraftSoMutations($barangId);

            // Rekonsiliasi ringkasan stok gudang agar 100% selaras dengan batch aktif & transaksi stok
            \App\Models\StokGudang::reconcileStockSummary($barangId, $gudangId, $divisiId);
        }

        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        $satuanStok = $barang ? ($barang->satuan ?: 'pcs') : 'pcs';
        $satuanBeliDefault = $barang ? ($barang->satuan_pembelian ?: $satuanStok) : $satuanStok;
        $konversiBarang = $barang ? (float)($barang->konversi_pembelian ?: 1.0) : 1.0;
        if ($konversiBarang <= 0 || strtolower(trim($satuanBeliDefault)) === strtolower(trim($satuanStok))) {
            $konversiBarang = 1.0;
        }

        $cachedFallbackPrice = null;

        $saQty = 0;
        $saNilai = 0;

        // 1. Cari tanggal cut-off persediaan awal gudang/divisi disetujui terbaru pada atau sebelum endDate
        $paCutoffQuery = DB::table('persediaan_awal')
            ->whereIn('status', ['approved', 'posted'])
            ->where('tanggal', '<=', $endDate);

        if ($gudangId && $divisiId) {
            $paCutoffQuery->where('gudang_id', $gudangId)->where('divisi_id', $divisiId);
        } elseif ($gudangId) {
            $paCutoffQuery->where('gudang_id', $gudangId)->whereNull('divisi_id');
        } elseif ($divisiId) {
            $paCutoffQuery->where('divisi_id', $divisiId);
        }

        $latestPaTanggal = $paCutoffQuery->max('tanggal');
        $minGudangTanggal = $latestPaTanggal ? (date('Y-m-d', strtotime($latestPaTanggal)) . ' 00:00:00') : null;

        // 2. Cari tanggal saldo awal spesifik barang ini pada gudang/divisi ini
        $saQuery = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->whereIn('source_type', ['saldo_awal', 'persediaan_awal'])
            ->where('tanggal', '<=', $endDate . ' 23:59:59');

        if ($gudangId && $divisiId) {
            $saQuery->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
        } elseif ($gudangId) {
            $saQuery->where('gudang_tujuan_id', $gudangId);
        } elseif ($divisiId) {
            $saQuery->where('divisi_tujuan_id', $divisiId);
        }

        $latestSaTanggal = $saQuery->max('tanggal');
        $minBarangTanggal = $latestSaTanggal ? (date('Y-m-d', strtotime($latestSaTanggal)) . ' 00:00:00') : null;

        // Persediaan awal berfungsi sebagai refresh & cut-off baseline persediaan
        $minTanggal = null;
        if ($minGudangTanggal && $minBarangTanggal) {
            $minTanggal = max($minGudangTanggal, $minBarangTanggal);
        } elseif ($minGudangTanggal) {
            $minTanggal = $minGudangTanggal;
        } else {
            $minTanggal = $minBarangTanggal;
        }

        $rawBefore = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<', $startDate . ' 00:00:00');

        $rawPeriod = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->whereBetween('tanggal', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($minTanggal) {
            $rawBefore->where('tanggal', '>=', $minTanggal);
            if ($minTanggal > ($startDate . ' 00:00:00')) {
                $rawPeriod->where('tanggal', '>=', $minTanggal);
            }
        }

        if ($gudangId && $divisiId) {
            $rawBefore->where(function ($q) use ($gudangId, $divisiId) {
                $q->where(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('tipe', 'keluar')->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('tipe', 'masuk')->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                });
            });
            $rawPeriod->where(function ($q) use ($gudangId, $divisiId) {
                $q->where(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('tipe', 'keluar')->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('tipe', 'masuk')->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                });
            });
        } elseif ($gudangId) {
            $rawBefore->where(function ($q) use ($gudangId) {
                $q->where(function($sub) use ($gudangId) {
                    $sub->where('tipe', 'keluar')->where('gudang_asal_id', $gudangId);
                })->orWhere(function($sub) use ($gudangId) {
                    $sub->where('tipe', 'masuk')->where('gudang_tujuan_id', $gudangId);
                });
            });
            $rawPeriod->where(function ($q) use ($gudangId) {
                $q->where(function($sub) use ($gudangId) {
                    $sub->where('tipe', 'keluar')->where('gudang_asal_id', $gudangId);
                })->orWhere(function($sub) use ($gudangId) {
                    $sub->where('tipe', 'masuk')->where('gudang_tujuan_id', $gudangId);
                });
            });
        } elseif ($divisiId) {
            $rawBefore->where(function ($q) use ($divisiId) {
                $q->where(function($sub) use ($divisiId) {
                    $sub->where('tipe', 'keluar')->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($divisiId) {
                    $sub->where('tipe', 'masuk')->where('divisi_tujuan_id', $divisiId);
                });
            });
            $rawPeriod->where(function ($q) use ($divisiId) {
                $q->where(function($sub) use ($divisiId) {
                    $sub->where('tipe', 'keluar')->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($divisiId) {
                    $sub->where('tipe', 'masuk')->where('divisi_tujuan_id', $divisiId);
                });
            });
        }

        $itemsBefore = $rawBefore->orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();

        foreach ($itemsBefore as $row) {
            $qty = floatval($row->qty);
            $totalHarga = floatval($row->total_harga);

            if (in_array(strtolower($row->source_type ?? ''), ['pembelian', 'penerimaan_pembelian', 'pembelian_batal'])) {
                $qty = $this->calculateStockQtyForPembelian($row, $barangId, $konversiBarang);
            }

            $isMasuk = false;
            $isKeluar = false;

            if ($gudangId || $divisiId) {
                $matchTujuan = true;
                $matchAsal   = true;

                if ($gudangId) {
                    $matchTujuan = $matchTujuan && ($row->gudang_tujuan_id == $gudangId);
                    $matchAsal   = $matchAsal   && ($row->gudang_asal_id   == $gudangId);
                }
                if ($divisiId) {
                    $matchTujuan = $matchTujuan && ($row->divisi_tujuan_id == $divisiId);
                    $matchAsal   = $matchAsal   && ($row->divisi_asal_id   == $divisiId);
                }

                if ($row->tipe === 'masuk' && $matchTujuan) {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar' && $matchAsal) {
                    $isKeluar = true;
                }
            } else {
                if ($row->tipe === 'masuk') {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar') {
                    $isKeluar = true;
                }
            }

            $isStockOpname = (strtolower($row->source_type ?? '') === 'stock_opname');

            if ($isMasuk) {
                $prevSaQty = $saQty;
                $saQty += $qty;
                if ($prevSaQty < 0) {
                    if ($saQty > 0) {
                        $unitPrice = $qty > 0 ? ($totalHarga / $qty) : 0;
                        $saNilai = $saQty * $unitPrice;
                    } else {
                        $saNilai = 0;
                    }
                } else {
                    $saNilai += $totalHarga;
                }
            } elseif ($isKeluar) {
                $prevSaQty = $saQty;
                $prevSaNilai = $saNilai;

                $saQty -= $qty;

                if ($saQty <= 0) {
                    $saNilai = 0;
                } else {
                    if ($isStockOpname && $prevSaQty > 0) {
                        $deductedNilai = min($prevSaNilai, $prevSaNilai * ($qty / $prevSaQty));
                        $saNilai = max(0, $prevSaNilai - $deductedNilai);
                    } else {
                        $currentAvgPrice = $prevSaQty > 0 ? ($prevSaNilai / $prevSaQty) : 0;
                        if ($currentAvgPrice > 0) {
                            $expectedDeduct = round($qty * $currentAvgPrice, 2);
                            if ($totalHarga > $prevSaNilai || abs($totalHarga - $prevSaNilai) < 1) {
                                $totalHarga = min($prevSaNilai, $expectedDeduct);
                            }
                        } elseif ($totalHarga > $prevSaNilai && $prevSaNilai > 0) {
                            $totalHarga = $prevSaNilai;
                        }
                        $saNilai = max(0, $prevSaNilai - $totalHarga);
                    }
                }
            }

            if ($saQty > 0 && $saNilai <= 0) {
                if ($cachedFallbackPrice === null) {
                    $fifoService = app(\App\Services\FifoService::class);
                    $fallbackPrice = $fifoService->getHargaTerakhirBahan($barangId, $gudangId);
                    if ($fallbackPrice <= 0 || abs($fallbackPrice - 233) <= 1 || abs($fallbackPrice - 25888.89) <= 1) {
                        $refHpp = (float)(\Illuminate\Support\Facades\DB::table('master_barang')->where('id', $barangId)->value('hpp_referensi') ?? 0);
                        if ($refHpp > 0 && abs($refHpp - 233) > 1 && abs($refHpp - 25888.89) > 1) {
                            $fallbackPrice = $refHpp;
                        }
                    }
                    $cachedFallbackPrice = (float) $fallbackPrice;
                }
                $saNilai = round($saQty * $cachedFallbackPrice, 2);
            }
        }

        $itemsPeriod = $rawPeriod->orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();

        $mutations = [];
        $runningQty = $saQty;
        $runningNilai = $saNilai;

        foreach ($itemsPeriod as $row) {
            $rawQty = floatval($row->qty);
            $totalHarga = floatval($row->total_harga);
            $qty = $rawQty;

            $keteranganExtra = '';

            if (in_array(strtolower($row->source_type ?? ''), ['pembelian', 'penerimaan_pembelian', 'pembelian_batal'])) {
                $pDetailInfo = $this->getPembelianDetailInfo($row, $barangId);
                $satBeliRow = $pDetailInfo['satuan_pembelian'] ?: $satuanBeliDefault;
                $isSameUnit = strtolower(trim($satBeliRow)) === strtolower(trim($satuanStok));
                $konversiRow = (!$isSameUnit && isset($pDetailInfo['konversi']) && $pDetailInfo['konversi'] > 1) ? $pDetailInfo['konversi'] : 1.0;
                $qtyBeliInput = $pDetailInfo['qty_input'];

                if (!$isSameUnit && $konversiRow > 1 && $qtyBeliInput > 0) {
                    $keteranganExtra = " (setara {$qtyBeliInput} {$satBeliRow} @ 1 {$satBeliRow} = " . (float)$konversiRow . " {$satuanStok})";
                }
            }

            $isMasuk = false;
            $isKeluar = false;

            if ($gudangId || $divisiId) {
                $matchTujuan = true;
                $matchAsal   = true;

                if ($gudangId) {
                    $matchTujuan = $matchTujuan && ($row->gudang_tujuan_id == $gudangId);
                    $matchAsal   = $matchAsal   && ($row->gudang_asal_id   == $gudangId);
                }
                if ($divisiId) {
                    $matchTujuan = $matchTujuan && ($row->divisi_tujuan_id == $divisiId);
                    $matchAsal   = $matchAsal   && ($row->divisi_asal_id   == $divisiId);
                }

                if ($row->tipe === 'masuk' && $matchTujuan) {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar' && $matchAsal) {
                    $isKeluar = true;
                }
            } else {
                if ($row->tipe === 'masuk') {
                    $isMasuk = true;
                } elseif ($row->tipe === 'keluar') {
                    $isKeluar = true;
                }
            }

            $isStockOpname = (strtolower($row->source_type ?? '') === 'stock_opname');

            if ($isMasuk || $isKeluar) {
                $prevRunningQty = $runningQty;
                $prevRunningNilai = $runningNilai;

                if ($isMasuk) {
                    $runningQty += $qty;
                    if ($prevRunningQty < 0) {
                        if ($runningQty > 0) {
                            $unitPrice = $qty > 0 ? ($totalHarga / $qty) : 0;
                            $runningNilai = $runningQty * $unitPrice;
                        } else {
                            $runningNilai = 0;
                        }
                    } else {
                        $runningNilai += $totalHarga;
                    }
                } else {
                    $runningQty -= $qty;

                    if ($runningQty <= 0) {
                        $runningNilai = 0;
                    } else {
                        if ($isStockOpname && $prevRunningQty > 0) {
                            $deductedNilai = min($prevRunningNilai, $prevRunningNilai * ($qty / $prevRunningQty));
                            $totalHarga = $deductedNilai;
                            $runningNilai = max(0, $prevRunningNilai - $deductedNilai);
                        } else {
                            // Hitung unit price sebelum keluar
                            $currentAvgPrice = $prevRunningQty > 0 ? ($prevRunningNilai / $prevRunningQty) : 0;
                            // Pastikan totalHarga keluar proporsional dan tidak menguras seluruh nilai sisa stok
                            if ($currentAvgPrice > 0) {
                                $expectedDeduct = round($qty * $currentAvgPrice, 2);
                                if ($totalHarga > $prevRunningNilai || abs($totalHarga - $prevRunningNilai) < 1) {
                                    $totalHarga = min($prevRunningNilai, $expectedDeduct);
                                }
                            } elseif ($totalHarga > $prevRunningNilai && $prevRunningNilai > 0) {
                                $totalHarga = $prevRunningNilai;
                            }
                            $runningNilai = max(0, $prevRunningNilai - $totalHarga);
                        }
                    }
                }

                if ($runningQty > 0 && $runningNilai <= 0) {
                    if ($cachedFallbackPrice === null) {
                        $fifoService = app(\App\Services\FifoService::class);
                        $fallbackPrice = $fifoService->getHargaTerakhirBahan($barangId, $gudangId);
                        if ($fallbackPrice <= 0 || abs($fallbackPrice - 233) <= 1 || abs($fallbackPrice - 25888.89) <= 1) {
                            $refHpp = (float)(\Illuminate\Support\Facades\DB::table('master_barang')->where('id', $barangId)->value('hpp_referensi') ?? 0);
                            if ($refHpp > 0 && abs($refHpp - 233) > 1 && abs($refHpp - 25888.89) > 1) {
                                $fallbackPrice = $refHpp;
                            }
                        }
                        $cachedFallbackPrice = (float) $fallbackPrice;
                    }
                    $runningNilai = round($runningQty * $cachedFallbackPrice, 2);
                }

                $hargaSatuan = $qty > 0 ? ($totalHarga / $qty) : 0;

                $keterangan = $this->formatSourceDescription($row->source_type, $row->source_id, $isMasuk) . $keteranganExtra;
                if ($row->tipe === 'transfer') {
                    $gAsal = self::$gudangNamaCache[$row->gudang_asal_id] ??= DB::table('master_gudang')->where('id', $row->gudang_asal_id)->value('nama');
                    $gTujuan = self::$gudangNamaCache[$row->gudang_tujuan_id] ??= DB::table('master_gudang')->where('id', $row->gudang_tujuan_id)->value('nama');
                    $keterangan = "Transfer: dari {$gAsal} ke {$gTujuan}";
                }

                $mutations[] = [
                    'id' => $row->id,
                    'source_type' => $row->source_type,
                    'source_id' => $row->source_id,
                    'tanggal_formatted' => date('d/m/Y H:i', strtotime($row->tanggal)),
                    'keterangan' => $keterangan,
                    'is_masuk' => $isMasuk,
                    'qty' => $qty,
                    'qty_pembelian' => $konversiBarang > 1 ? ($qty / $konversiBarang) : $qty,
                    'harga_satuan' => $hargaSatuan,
                    'harga_satuan_pembelian' => $konversiBarang > 1 ? ($hargaSatuan * $konversiBarang) : $hargaSatuan,
                    'total_harga' => $totalHarga,
                    'saldo_qty' => $runningQty,
                    'saldo_qty_pembelian' => $konversiBarang > 1 ? ($runningQty / $konversiBarang) : $runningQty,
                    'saldo_nilai' => $runningNilai,
                ];
            }
        }

        return response()->json([
            'barang' => [
                'id' => $barang ? $barang->id : $barangId,
                'nama' => $barang ? $barang->nama : '',
                'kode_barang' => $barang ? $barang->kode_barang : '',
                'satuan' => $satuanStok,
                'satuan_pembelian' => $satuanBeliDefault,
                'konversi_pembelian' => $konversiBarang,
            ],
            'saldo_awal' => [
                'qty' => $saQty,
                'qty_pembelian' => $konversiBarang > 1 ? ($saQty / $konversiBarang) : $saQty,
                'nilai' => $saNilai
            ],
            'mutasi' => $mutations,
            'saldo_akhir' => [
                'qty' => $runningQty,
                'qty_pembelian' => $konversiBarang > 1 ? ($runningQty / $konversiBarang) : $runningQty,
                'nilai' => $runningNilai
            ]
        ]);
    }

    private function getPembelianDetailInfo($row, $barangId)
    {
        $sourceType = strtolower($row->source_type ?? '');
        $cacheKey = "{$sourceType}_{$row->source_id}_{$barangId}";
        if (isset(self::$pembelianDetailCache[$cacheKey])) {
            return self::$pembelianDetailCache[$cacheKey];
        }

        $pDetail = null;

        if (in_array($sourceType, ['pembelian', 'pembelian_kejingga'])) {
            $pDetail = DB::table('pembelian_detail')
                ->where('pembelian_id', $row->source_id)
                ->where('barang_id', $barangId)
                ->first();
        } elseif ($sourceType === 'penerimaan_pembelian') {
            $rcv = DB::table('penerimaan_pembelian')->where('id', $row->source_id)->first();
            if ($rcv) {
                $pDetail = DB::table('pembelian_detail')
                    ->where('pembelian_id', $rcv->pembelian_id)
                    ->where('barang_id', $barangId)
                    ->first();
            }
        }

        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        $satuanDasar = $barang ? ($barang->satuan ?: 'pcs') : 'pcs';
        $masterSatBeli = $barang ? $barang->satuan_pembelian : null;
        $isSameAsMaster = ($masterSatBeli && strtolower(trim($masterSatBeli)) === strtolower(trim($satuanDasar)));
        $masterKonv = ($barang && !$isSameAsMaster) ? (float)($barang->konversi_pembelian ?? 1) : 1.0;

        if ($pDetail) {
            $detailSatBeli = $pDetail->satuan_pembelian ?: $masterSatBeli;
            $isSameDetail = ($detailSatBeli && strtolower(trim($detailSatBeli)) === strtolower(trim($satuanDasar)));
            $detailKonv = !$isSameDetail ? (float) ($pDetail->konversi_pembelian ?? 1) : 1.0;
            $finalKonv = $detailKonv > 1 ? $detailKonv : ($masterKonv > 1 ? $masterKonv : 1.0);
            return self::$pembelianDetailCache[$cacheKey] = [
                'konversi'          => $finalKonv,
                'satuan_pembelian' => $detailSatBeli,
                'qty_input'         => (float) ($pDetail->qty ?? 0),
            ];
        }

        return self::$pembelianDetailCache[$cacheKey] = [
            'konversi'          => $masterKonv > 1 ? $masterKonv : 1.0,
            'satuan_pembelian' => $masterSatBeli,
            'qty_input'         => 0.0,
        ];
    }

    private function calculateStockQtyForPembelian($row, $barangId, $konversiDefault)
    {
        return (float) $row->qty;
    }

    private function formatSourceDescription($type, $id, $isMasuk = false)
    {
        if (empty($type) || empty($id)) {
            return 'Manual / Saldo Awal';
        }

        $cacheKey = "{$type}_{$id}_" . ($isMasuk ? '1' : '0');
        if (isset(self::$sourceDescCache[$cacheKey])) {
            return self::$sourceDescCache[$cacheKey];
        }

        $desc = $this->resolveSourceDescription($type, $id, $isMasuk);
        return self::$sourceDescCache[$cacheKey] = $desc;
    }

    private function resolveSourceDescription($type, $id, $isMasuk = false)
    {
        switch (strtolower($type)) {
            case 'pembelian':
            case 'pembelian_kejingga':
                $p = \App\Models\Pembelian::with('supplier')->find($id);
                if ($p) {
                    $supplierName = $p->supplier->nama ?? '-';
                    $prefixLabel = ($type === 'pembelian_kejingga') ? 'Pembelian Kejingga' : 'Pembelian';
                    return "{$prefixLabel}: {$p->kode_pembelian} [Supplier: {$supplierName}]";
                }
                return ($type === 'pembelian_kejingga') ? "Pembelian Kejingga (ID: {$id})" : "Pembelian (ID: {$id})";

            case 'pembelian_batal':
                $p = \App\Models\Pembelian::with('supplier')->find($id);
                if ($p) {
                    $supplierName = $p->supplier->nama ?? '-';
                    return "Pembelian Batal: {$p->kode_pembelian} [Supplier: {$supplierName}]";
                }
                return "Pembelian Batal (ID: {$id})";

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
                    $kode = $out->kode_pengeluaran ?? $out->no_pengeluaran ?? "ID: {$id}";
                    if (str_starts_with($kode, 'PBK-SO-')) {
                        return "Stock Opname (Kurang): {$kode}";
                    }
                    if (str_starts_with($kode, 'PBK-WST-')) {
                        return "Material Wasted: {$kode}";
                    }
                    $gudangTujuan = $out->gudang->nama ?? '';
                    $divisiTujuan = $out->divisi->nama ?? '';
                    $tujuanInfo = [];
                    if ($gudangTujuan) $tujuanInfo[] = "Gudang: {$gudangTujuan}";
                    if ($divisiTujuan) $tujuanInfo[] = "Divisi: {$divisiTujuan}";
                    $tujuanStr = !empty($tujuanInfo) ? ' [' . implode(' - ', $tujuanInfo) . ']' : '';

                    if ($isMasuk) {
                        return "Permintaan / Transfer Bahan Masuk: {$kode}{$tujuanStr}";
                    }
                    return "Pengeluaran Bahan Baku: {$kode}{$tujuanStr}";
                }
                return $isMasuk ? "Permintaan Bahan Masuk (ID: {$id})" : "Pengeluaran Bahan Baku (ID: {$id})";

            case 'produksi':
                $prod = \App\Models\Produksi::find($id);
                if ($prod) {
                    return "Produksi: {$prod->kode_produksi}";
                }
                return "Produksi (ID: {$id})";

            case 'preparation_bahan':
                $prep = \App\Models\Preparation::with(['gudang', 'divisi'])->find($id);
                if ($prep) {
                    $kode = $prep->kode ?? "ID: {$id}";
                    $gudang = $prep->gudang->nama ?? '';
                    $divisi = $prep->divisi->nama ?? '';
                    $info = [];
                    if ($gudang) $info[] = $gudang;
                    if ($divisi) $info[] = $divisi;
                    $infoStr = !empty($info) ? ' [' . implode(' - ', $info) . ']' : '';
                    if ($isMasuk) {
                        return "Hasil Preparation: {$kode}{$infoStr}";
                    }
                    return "Bahan Preparation: {$kode}{$infoStr}";
                }
                return $isMasuk ? "Hasil Preparation (ID: {$id})" : "Bahan Preparation (ID: {$id})";

            case 'pengiriman':
                $del = \App\Models\Pengiriman::find($id);
                if ($del) {
                    return "Pengiriman B2B: {$del->no_pengiriman}";
                }
                return "Pengiriman (ID: {$id})";

            case 'transfer_ck':
                $prod = \App\Models\Produksi::find($id);
                if ($prod) {
                    $doNumber = \App\Models\Pengiriman::where('pesanan_id', $prod->pesanan_id)->value('no_pengiriman');
                    $batchDesc = $doNumber ?: $prod->kode_produksi;
                    return $isMasuk ? "Transfer Masuk dari CK: {$batchDesc}" : "Transfer Keluar ke Outlet: {$batchDesc}";
                }
                $del = \App\Models\Pengiriman::find($id);
                if ($del) {
                    return $isMasuk ? "Transfer Masuk dari CK: {$del->no_pengiriman}" : "Transfer Keluar ke Outlet: {$del->no_pengiriman}";
                }
                return $isMasuk ? "Transfer Masuk dari Central Kitchen (ID: {$id})" : "Transfer Keluar ke Outlet (ID: {$id})";

            case 'produksi_ck':
                $prod = \App\Models\Produksi::find($id);
                if ($prod) {
                    return $isMasuk ? "Hasil Produksi Central Kitchen: {$prod->kode_produksi}" : "Bahan Produksi Central Kitchen: {$prod->kode_produksi}";
                }
                return $isMasuk ? "Hasil Produksi Central Kitchen (ID: {$id})" : "Bahan Produksi Central Kitchen (ID: {$id})";

            case 'stock_opname':
                $opname = \App\Models\StockOpname::find($id);
                if ($opname) {
                    $kode = $opname->kode_opname ?? $opname->no_opname ?? "ID: {$id}";
                    return "Stock Opname: {$kode}";
                }
                return "Stock Opname (ID: {$id})";

            case 'penjualan_pos':
            case 'pos':
                $pos = \App\Models\PenjualanPos::find($id);
                if ($pos) {
                    $kode = $pos->kode_transaksi ?? $pos->kode_penjualan ?? "ID: {$id}";
                    $gudangNama = $pos->gudang->nama ?? '';
                    return $gudangNama ? "Penjualan POS: {$kode} [{$gudangNama}]" : "Penjualan POS: {$kode}";
                }
                $pbk = \App\Models\PengeluaranBahanBaku::find($id);
                if ($pbk) {
                    $kode = $pbk->kode_pengeluaran ?? "ID: {$id}";
                    $gudangNama = $pbk->gudang->nama ?? '';
                    return $gudangNama ? "Penjualan POS: {$kode} [{$gudangNama}]" : "Penjualan POS: {$kode}";
                }
                return "Penjualan POS (ID: {$id})";

            case 'penyesuaian_stok':
                return "Penyesuaian Stok: Netralisir Defisit Saldo";

            default:
                return ucfirst(str_replace('_', ' ', $type)) . " (ID: {$id})";
        }
    }

    private function autoHealPrematureDraftSoMutations($barangId = null)
    {
        try {
            $draftPbks = \App\Models\PengeluaranBahanBaku::where('status', 'draft')
                ->where(function($q) {
                    $q->where('jenis_pengeluaran', 'stock_opname')
                      ->orWhere('kode_pengeluaran', 'like', 'PBK-SO-%')
                      ->orWhere('keterangan', 'like', '%Stock Opname%');
                })
                ->get();

            if ($draftPbks->isEmpty()) {
                return;
            }

            foreach ($draftPbks as $pbk) {
                $opname = $pbk->findAssociatedStockOpname();

                if ($opname) {
                    // Jika stock opname sudah approved, pastikan PBK-nya juga tersinkron approved
                    if ($opname->status === 'approved') {
                        $pbk->update([
                            'status'      => 'approved',
                            'approved_by' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                            'approved_at' => now(),
                        ]);
                        continue;
                    }

                    // Hanya jika stock opname masih berstatus draft, bersihkan mutasi prematur jika ada
                    if ($opname->status === 'draft') {
                        $txQuery = \App\Models\TransaksiStok::where('source_type', 'stock_opname')
                            ->where('source_id', $opname->id);
                        if ($barangId) {
                            $txQuery->where('barang_id', $barangId);
                        }
                        $txs = $txQuery->get();

                        foreach ($txs as $tx) {
                            $qty = (float) $tx->qty;
                            if ($tx->tipe === 'keluar') {
                                $stok = \App\Models\StokGudang::where('barang_id', $tx->barang_id)
                                    ->where('gudang_id', $tx->gudang_asal_id)
                                    ->when($tx->divisi_asal_id, fn($q) => $q->where('divisi_id', $tx->divisi_asal_id), fn($q) => $q->whereNull('divisi_id'))
                                    ->first();
                                if ($stok) {
                                    $stok->increment('jumlah', $qty);
                                }
                            } elseif ($tx->tipe === 'masuk') {
                                $stok = \App\Models\StokGudang::where('barang_id', $tx->barang_id)
                                    ->where('gudang_id', $tx->gudang_tujuan_id)
                                    ->when($tx->divisi_tujuan_id, fn($q) => $q->where('divisi_id', $tx->divisi_tujuan_id), fn($q) => $q->whereNull('divisi_id'))
                                    ->first();
                                if ($stok) {
                                    $stok->decrement('jumlah', $qty);
                                }
                            }
                            $tx->delete();
                        }

                        \App\Models\StokGudangBatch::where('batch_number', 'SO-SURPLUS-' . $opname->kode_opname)->delete();

                        $jps = DB::table('jurnal_penyesuaian')->where('source_type', 'stock_opname')->where('source_id', $opname->id)->get();
                        foreach ($jps as $jp) {
                            DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_penyesuaian')->delete();
                            DB::table('jurnal_penyesuaian')->where('id', $jp->id)->delete();
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("autoHealPrematureDraftSoMutations failed: " . $e->getMessage());
        }
    }

    /**
     * Auto-heal transaksi_stok yang divisi_tujuan_id atau divisi_asal_id-nya belum tersinkronisasi dari dokumen sumber (PBK, SO, Persediaan Awal).
     */
    public static function autoHealMissingDivisiInTransaksiStok($barangId = null)
    {
        try {
            // 1. Backfill divisi_tujuan_id dari pengeluaran_bahan_baku (PBK Masuk ke Divisi)
            $q1 = DB::table('transaksi_stok as ts')
                ->join('pengeluaran_bahan_baku as pbk', 'ts.source_id', '=', 'pbk.id')
                ->where('ts.source_type', 'pengeluaran_bahan_baku')
                ->where('ts.tipe', 'masuk')
                ->whereNotNull('pbk.divisi_id')
                ->where(function($q) {
                    $q->whereNull('ts.divisi_tujuan_id')
                      ->orWhereColumn('ts.divisi_tujuan_id', '!=', 'pbk.divisi_id');
                });
            if ($barangId) {
                $q1->where('ts.barang_id', $barangId);
            }
            $q1->update(['ts.divisi_tujuan_id' => DB::raw('pbk.divisi_id')]);

            // 2. Backfill divisi_asal_id dari pengeluaran_bahan_baku / wasted (PBK Keluar dari Divisi)
            $q2 = DB::table('transaksi_stok as ts')
                ->join('pengeluaran_bahan_baku as pbk', 'ts.source_id', '=', 'pbk.id')
                ->whereIn('ts.source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                ->where('ts.tipe', 'keluar')
                ->whereNotNull('pbk.divisi_id')
                ->whereColumn('ts.gudang_asal_id', 'pbk.gudang_id')
                ->where(function($q) {
                    $q->whereNull('ts.divisi_asal_id')
                      ->orWhereColumn('ts.divisi_asal_id', '!=', 'pbk.divisi_id');
                });
            if ($barangId) {
                $q2->where('ts.barang_id', $barangId);
            }
            $q2->update(['ts.divisi_asal_id' => DB::raw('pbk.divisi_id')]);

            // 3. Backfill divisi_tujuan_id dari stock_opname (Surplus Divisi)
            $q3 = DB::table('transaksi_stok as ts')
                ->join('stock_opname as so', 'ts.source_id', '=', 'so.id')
                ->where('ts.source_type', 'stock_opname')
                ->where('ts.tipe', 'masuk')
                ->whereNotNull('so.divisi_id')
                ->where(function($q) {
                    $q->whereNull('ts.divisi_tujuan_id')
                      ->orWhereColumn('ts.divisi_tujuan_id', '!=', 'so.divisi_id');
                });
            if ($barangId) {
                $q3->where('ts.barang_id', $barangId);
            }
            $q3->update(['ts.divisi_tujuan_id' => DB::raw('so.divisi_id')]);

            // 4. Backfill divisi_asal_id dari stock_opname (Shortage Divisi)
            $q4 = DB::table('transaksi_stok as ts')
                ->join('stock_opname as so', 'ts.source_id', '=', 'so.id')
                ->where('ts.source_type', 'stock_opname')
                ->where('ts.tipe', 'keluar')
                ->whereNotNull('so.divisi_id')
                ->where(function($q) {
                    $q->whereNull('ts.divisi_asal_id')
                      ->orWhereColumn('ts.divisi_asal_id', '!=', 'so.divisi_id');
                });
            if ($barangId) {
                $q4->where('ts.barang_id', $barangId);
            }
            $q4->update(['ts.divisi_asal_id' => DB::raw('so.divisi_id')]);

            // 5. Backfill divisi_tujuan_id dari persediaan_awal (Saldo Awal Divisi)
            $q5 = DB::table('transaksi_stok as ts')
                ->join('persediaan_awal as pa', 'ts.source_id', '=', 'pa.id')
                ->where('ts.source_type', 'persediaan_awal')
                ->where('ts.tipe', 'masuk')
                ->whereNotNull('pa.divisi_id')
                ->where(function($q) {
                    $q->whereNull('ts.divisi_tujuan_id')
                      ->orWhereColumn('ts.divisi_tujuan_id', '!=', 'pa.divisi_id');
                });
            if ($barangId) {
                $q5->where('ts.barang_id', $barangId);
            }
            $q5->update(['ts.divisi_tujuan_id' => DB::raw('pa.divisi_id')]);

            // 6. Backfill divisi_tujuan_id untuk Pembelian ke Outlet (Kejingga / Luar) sesuai ketentuan tagging divisi
            $outletIds = \App\Models\MasterGudang::getOutletGudangIds();
            $q6 = DB::table('transaksi_stok as ts')
                ->whereIn('ts.source_type', ['pembelian', 'penerimaan_pembelian', 'pembelian_kejingga'])
                ->where('ts.tipe', 'masuk')
                ->whereIn('ts.gudang_tujuan_id', $outletIds);
            if ($barangId) {
                $q6->where('ts.barang_id', $barangId);
            }
            $pembelianOutletTxs = $q6->get();

            foreach ($pembelianOutletTxs as $pTx) {
                $divId = \App\Models\MasterGudang::resolveDivisiIdForBarang($pTx->gudang_tujuan_id, $pTx->barang_id);
                if ($divId && $pTx->divisi_tujuan_id != $divId) {
                    DB::table('transaksi_stok')->where('id', $pTx->id)->update(['divisi_tujuan_id' => $divId]);
                    if ($pTx->source_id) {
                        DB::table('stok_gudang_batch')
                            ->where('pembelian_id', $pTx->source_id)
                            ->where('barang_id', $pTx->barang_id)
                            ->where('gudang_id', $pTx->gudang_tujuan_id)
                            ->update(['divisi_id' => $divId]);
                    }
                }
            }

            // 7. Backfill & sinkronkan divisi_asal_id dan gudang_asal_id untuk Penjualan POS (penjualan_pos & pos)
            $q7 = DB::table('transaksi_stok as ts')
                ->whereIn('ts.source_type', ['penjualan_pos', 'pos'])
                ->where('ts.tipe', 'keluar');
            if ($barangId) {
                $q7->where('ts.barang_id', $barangId);
            }
            $posTxs = $q7->get();

            foreach ($posTxs as $pTx) {
                $posHeader = DB::table('penjualan_pos')->where('id', $pTx->source_id)->first();
                $actualGudangId = $posHeader
                    ? self::resolveOutletForPos($posHeader)
                    : ($pTx->gudang_asal_id && in_array($pTx->gudang_asal_id, $outletIds) ? $pTx->gudang_asal_id : \App\Models\MasterGudang::resolveOutletId());

                $targetDivId = \App\Models\MasterGudang::resolveDivisiIdForBarang($actualGudangId, $pTx->barang_id);

                $upData = [];
                if ($pTx->gudang_asal_id != $actualGudangId) {
                    $upData['gudang_asal_id'] = $actualGudangId;
                }
                if ($targetDivId && $pTx->divisi_asal_id != $targetDivId) {
                    $upData['divisi_asal_id'] = $targetDivId;
                }

                if (!empty($upData)) {
                    DB::table('transaksi_stok')->where('id', $pTx->id)->update($upData);

                    // Sinkronkan ringkasan stok_gudang
                    if ($pTx->divisi_asal_id) {
                        \App\Models\StokGudang::reconcileStockSummary($pTx->barang_id, $pTx->gudang_asal_id, $pTx->divisi_asal_id);
                    }
                    if ($targetDivId) {
                        \App\Models\StokGudang::reconcileStockSummary($pTx->barang_id, $actualGudangId, $targetDivId);
                    }
                }
            }

            // 8. Backfill divisi_tujuan_id untuk transfer_ck & pengiriman ke gudang outlet agar langsung masuk ke Divisi Kitchen
            $q8 = DB::table('transaksi_stok as ts')
                ->whereIn('ts.source_type', ['transfer_ck', 'pengiriman'])
                ->where('ts.tipe', 'masuk')
                ->whereIn('ts.gudang_tujuan_id', $outletIds);
            if ($barangId) {
                $q8->where('ts.barang_id', $barangId);
            }
            $ckOutletTxs = $q8->get();
            foreach ($ckOutletTxs as $cTx) {
                $kDivId = DB::table('gudang_divisi')
                    ->where('gudang_id', $cTx->gudang_tujuan_id)
                    ->where(function($q) {
                        $q->where('nama', 'like', '%kitchen%')->orWhere('nama', 'like', '%dapur%');
                    })
                    ->value('id') ?? MasterGudang::resolveDivisiIdForBarang($cTx->gudang_tujuan_id, $cTx->barang_id);

                if ($kDivId && $cTx->divisi_tujuan_id != $kDivId) {
                    DB::table('transaksi_stok')->where('id', $cTx->id)->update(['divisi_tujuan_id' => $kDivId]);

                    DB::table('stok_gudang_batch')
                        ->where('gudang_id', $cTx->gudang_tujuan_id)
                        ->where('barang_id', $cTx->barang_id)
                        ->where(function($q) {
                            $q->where('batch_number', 'like', 'TRANSFER-CK-%')
                              ->orWhere('batch_number', 'like', 'CK-TRF-%')
                              ->orWhere('batch_number', 'like', 'CK-%');
                        })
                        ->update(['divisi_id' => $kDivId]);

                    \App\Models\StokGudang::reconcileStockSummary($cTx->barang_id, $cTx->gudang_tujuan_id, $kDivId);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealMissingDivisiInTransaksiStok error: ' . $e->getMessage());
        }
    }

    /**
     * Auto-heal transfer bahan setengah jadi (BSJ) dari hasil produksi Central Kitchen yang sudah disetujui (Approved / Selesai)
     * agar langsung tercatat masuk ke Divisi Kitchen outlet pemesan.
     */
    public static function autoHealApprovedCkProductionTransfers($barangId = null)
    {
        try {
            $gudangCk = MasterGudang::where('nama', 'like', '%Central Kitchen%')->first();
            $gudangCkId = $gudangCk ? $gudangCk->id : 1;

            $affectedItems = [];

            // 1. Perbaiki transaksi transfer_ck, pengiriman, atau produksi yang sudah ada tetapi divisi_tujuan_id-nya belum ke Kitchen
            $qExist = DB::table('transaksi_stok as ts')
                ->whereIn('ts.source_type', ['transfer_ck', 'pengiriman'])
                ->where('ts.tipe', 'masuk')
                ->where('ts.gudang_tujuan_id', '!=', $gudangCkId);
            if ($barangId) {
                $qExist->where('ts.barang_id', $barangId);
            }
            $existingTransfers = $qExist->get();

            foreach ($existingTransfers as $tr) {
                $kDivId = DB::table('gudang_divisi')
                    ->where('gudang_id', $tr->gudang_tujuan_id)
                    ->where(function($q) {
                        $q->where('nama', 'like', '%kitchen%')->orWhere('nama', 'like', '%dapur%');
                    })
                    ->value('id') ?? MasterGudang::resolveDivisiIdForBarang($tr->gudang_tujuan_id, $tr->barang_id);

                if ($kDivId && $tr->divisi_tujuan_id != $kDivId) {
                    DB::table('transaksi_stok')->where('id', $tr->id)->update(['divisi_tujuan_id' => $kDivId]);
                    DB::table('stok_gudang_batch')
                        ->where('gudang_id', $tr->gudang_tujuan_id)
                        ->where('barang_id', $tr->barang_id)
                        ->where(function($q) {
                            $q->where('batch_number', 'like', 'TRANSFER-CK-%')
                              ->orWhere('batch_number', 'like', 'CK-TRF-%')
                              ->orWhere('batch_number', 'like', 'CK-%');
                        })
                        ->update(['divisi_id' => $kDivId]);

                    $affectedItems[] = ['barang_id' => $tr->barang_id, 'gudang_id' => $tr->gudang_tujuan_id, 'divisi_id' => $kDivId];
                }
            }

            // Helper resolver untuk outlet gudang dan divisi kitchen
            $resolveOutletInfo = function($pesananGudangId, $pesananDivisiId, $customerNama, $customerId, $produkId) use ($gudangCkId) {
                $custNama = strtolower($customerNama ?? '');
                if (!$custNama && $customerId) {
                    $custNama = strtolower(DB::table('customers')->where('id', $customerId)->value('nama') ?? '');
                }

                $outletGudangId = null;
                if ($pesananGudangId && $pesananGudangId != $gudangCkId) {
                    $outletGudangId = $pesananGudangId;
                }
                if (!$outletGudangId) {
                    if (str_contains($custNama, 'central kitchen')) {
                        return [null, null];
                    }

                    if (str_contains($custNama, 'kejingga') || str_contains($custNama, 'kj')) {
                        $outletGudangId = MasterGudang::where(function($q) {
                            $q->where('nama', 'like', '%KeJingga%')->orWhere('nama', 'like', '%Kejingga%');
                        })->value('id') ?? 5;
                    } elseif (str_contains($custNama, 'gaharu')) {
                        $outletGudangId = MasterGudang::where('nama', 'like', '%Gaharu%')
                            ->where('kategori', 'Operasional')
                            ->value('id') ?? MasterGudang::where('nama', 'like', '%Gaharu%')->value('id') ?? 3;
                    }
                }

                if (!$outletGudangId) {
                    return [null, null];
                }

                // Cari ID divisi Kitchen pada gudang outlet
                $kitchenDivId = null;
                if ($pesananDivisiId) {
                    $validDiv = DB::table('gudang_divisi')->where('id', $pesananDivisiId)->where('gudang_id', $outletGudangId)->value('id');
                    if ($validDiv) $kitchenDivId = $validDiv;
                }
                if (!$kitchenDivId) {
                    $kitchenDivId = DB::table('gudang_divisi')
                        ->where('gudang_id', $outletGudangId)
                        ->where(function($q) {
                            $q->where('nama', 'like', '%kitchen%')->orWhere('nama', 'like', '%dapur%');
                        })
                        ->value('id') ?? MasterGudang::resolveDivisiIdForBarang($outletGudangId, $produkId);
                }
                if (!$kitchenDivId) {
                    $kitchenDivId = DB::table('gudang_divisi')->where('gudang_id', $outletGudangId)->value('id');
                }

                return [$outletGudangId, $kitchenDivId];
            };

            // 2. Cari seluruh alokasi pesanan dari produksi CK berstatus Selesai yang belum tercatat di outlet kitchen
            $allocQuery = DB::table('alokasi_produksi_pesanan as app')
                ->join('produksi as p', 'app.produksi_id', '=', 'p.id')
                ->join('pesanan as pes', 'app.pesanan_id', '=', 'pes.id')
                ->leftJoin('customers as cust', 'pes.customer_id', '=', 'cust.id')
                ->where(DB::raw('LOWER(p.status_produksi)'), 'selesai')
                ->where('app.qty_alokasi', '>', 0)
                ->whereExists(function($sq) {
                    $sq->select(DB::raw(1))
                       ->from('pesanan_detail as pd_chk')
                       ->whereRaw('pd_chk.pesanan_id = app.pesanan_id')
                       ->whereRaw('pd_chk.produk_id = app.produk_id');
                })
                ->select(
                    'app.*',
                    'p.kode_produksi',
                    'p.tanggal_selesai',
                    'p.tanggal_mulai',
                    'p.gudang_hasil_id',
                    'pes.customer_id',
                    'cust.nama as customer_nama',
                    'pes.gudang_id as pesanan_gudang_id',
                    'pes.divisi_id as pesanan_divisi_id',
                    'pes.kode_pesanan',
                    'pes.tanggal as pesanan_tanggal'
                );

            if ($barangId) {
                $allocQuery->where('app.produk_id', $barangId);
            }

            $allocations = $allocQuery->get();

            foreach ($allocations as $al) {
                [$outletGudangId, $kitchenDivId] = $resolveOutletInfo(
                    $al->pesanan_gudang_id,
                    $al->pesanan_divisi_id,
                    $al->customer_nama,
                    $al->customer_id,
                    $al->produk_id
                );

                if (!$outletGudangId || !$kitchenDivId) {
                    continue;
                }

                // Cek apakah mutasi masuk ke outlet sudah pernah dicatat
                $existingTxOutlet = DB::table('transaksi_stok')
                    ->where('barang_id', $al->produk_id)
                    ->where('gudang_tujuan_id', $outletGudangId)
                    ->where('tipe', 'masuk')
                    ->where(function($q) use ($al) {
                        $q->where(function($sub) use ($al) {
                            $sub->where('source_type', 'transfer_ck')
                                ->where('source_id', $al->produksi_id);
                        })->orWhere(function($sub) use ($al) {
                            $sub->whereIn('source_type', ['transfer_ck', 'pengiriman'])
                                ->whereIn('source_id', function($sq) use ($al) {
                                    $sq->select('id')->from('pengiriman')->where('pesanan_id', $al->pesanan_id);
                                });
                        });
                    })
                    ->first();

                // Tanggal selesai produksi di CK
                $prodDate = $al->tanggal_selesai ?: ($al->tanggal_mulai ?: now());

                // Ambil tanggal WO jika ada
                $woDate = DB::table('work_order_detail as wod_dt')
                    ->join('work_order as wo_dt', 'wod_dt.work_order_id', '=', 'wo_dt.id')
                    ->where('wod_dt.pesanan_id', $al->pesanan_id)
                    ->where('wod_dt.produk_id', $al->produk_id)
                    ->value('wo_dt.tanggal_wo');

                // Tanggal transfer masuk outlet paling lambat H+1 setelah produksi selesai di WO
                $txDate = $prodDate;

                if ($existingTxOutlet) {
                    $updatePayload = [];
                    if ($kitchenDivId && $existingTxOutlet->divisi_tujuan_id != $kitchenDivId) {
                        $updatePayload['divisi_tujuan_id'] = $kitchenDivId;
                    }
                    // Jika tanggal transfer tercatat sebelum produksi selesai atau lebih dari H+1, perbaiki ke tanggal produksi
                    $prodDateH1 = date('Y-m-d 23:59:59', strtotime($prodDate . ' +1 day'));
                    if ($existingTxOutlet->tanggal < $prodDate || $existingTxOutlet->tanggal > $prodDateH1) {
                        $updatePayload['tanggal'] = $txDate;
                    }

                    if (!empty($updatePayload)) {
                        DB::table('transaksi_stok')->where('id', $existingTxOutlet->id)->update($updatePayload);
                        
                        // Perbarui juga transaksi keluar terkait di CK jika tanggalnya berubah
                        if (isset($updatePayload['tanggal'])) {
                            DB::table('transaksi_stok')
                                ->where('source_type', 'transfer_ck')
                                ->where('source_id', $al->produksi_id)
                                ->where('tipe', 'keluar')
                                ->where('barang_id', $al->produk_id)
                                ->update(['tanggal' => $txDate]);
                        }

                        $batchUpdate = [];
                        if ($kitchenDivId) $batchUpdate['divisi_id'] = $kitchenDivId;
                        if (isset($updatePayload['tanggal'])) $batchUpdate['created_at'] = $txDate;

                        DB::table('stok_gudang_batch')
                            ->where('gudang_id', $outletGudangId)
                            ->where('barang_id', $al->produk_id)
                            ->where(function($q) use ($al) {
                                $q->where('batch_number', 'like', "%{$al->kode_produksi}%")
                                  ->orWhere('batch_number', 'like', 'TRANSFER-CK-%');
                            })
                            ->update($batchUpdate);

                        $affectedItems[] = ['barang_id' => $al->produk_id, 'gudang_id' => $outletGudangId, 'divisi_id' => $kitchenDivId];
                    }
                    continue;
                }

                // JIKA BELUM ADA SAMA SEKALI: Catat transfer masuk ke divisi Kitchen outlet & keluar dari CK
                $qtyTransfer = floatval($al->qty_alokasi);
                $hppPerUnit  = floatval($al->hpp_per_unit);
                $totalHpp    = $qtyTransfer * $hppPerUnit;

                // 1. Kurangi sisa stok dan batch di CK
                $stokCk = DB::table('stok_gudang')
                    ->where('gudang_id', $gudangCkId)
                    ->where('barang_id', $al->produk_id)
                    ->first();
                if ($stokCk) {
                    DB::table('stok_gudang')->where('id', $stokCk->id)->decrement('jumlah', min(floatval($stokCk->jumlah), $qtyTransfer));
                }

                $batchCk = StokGudangBatch::where('gudang_id', $gudangCkId)
                    ->where('barang_id', $al->produk_id)
                    ->where('batch_number', 'like', '%' . $al->kode_produksi . '%')
                    ->first();
                if ($batchCk) {
                    $batchCk->increment('qty_keluar', $qtyTransfer);
                    $batchCk->decrement('qty_sisa', min(floatval($batchCk->qty_sisa), $qtyTransfer));
                    if ($batchCk->qty_sisa <= 0) $batchCk->is_habis = true;
                    $batchCk->save();
                }

                // 2. Transaksi keluar dari Central Kitchen (jika belum tercatat)
                $existingTxKeluar = DB::table('transaksi_stok')
                    ->where('source_type', 'transfer_ck')
                    ->where('source_id', $al->produksi_id)
                    ->where('tipe', 'keluar')
                    ->where('barang_id', $al->produk_id)
                    ->first();
                if (!$existingTxKeluar) {
                    TransaksiStok::create([
                        'tanggal'          => $txDate,
                        'tipe'             => 'keluar',
                        'source_type'      => 'transfer_ck',
                        'source_id'        => $al->produksi_id,
                        'gudang_asal_id'   => $gudangCkId,
                        'gudang_tujuan_id' => $outletGudangId,
                        'divisi_tujuan_id' => $kitchenDivId,
                        'barang_id'        => $al->produk_id,
                        'qty'              => $qtyTransfer,
                        'total_harga'      => $totalHpp,
                        'created_by'       => 1,
                    ]);
                }

                // 3. Tambah stok gudang outlet pada divisi Kitchen
                $stokOutlet = DB::table('stok_gudang')
                    ->where('gudang_id', $outletGudangId)
                    ->where('divisi_id', $kitchenDivId)
                    ->where('barang_id', $al->produk_id)
                    ->first();

                if ($stokOutlet) {
                    DB::table('stok_gudang')->where('id', $stokOutlet->id)->increment('jumlah', $qtyTransfer);
                } else {
                    DB::table('stok_gudang')->insert([
                        'gudang_id' => $outletGudangId,
                        'divisi_id' => $kitchenDivId,
                        'barang_id' => $al->produk_id,
                        'jumlah'    => $qtyTransfer,
                    ]);
                }

                // 4. Buat batch di outlet divisi Kitchen
                $supplierId = DB::table('suppliers')->value('id') ?? 1;
                $batchNumber = 'TRANSFER-CK-' . $al->kode_produksi . '-' . $al->pesanan_id;
                $existingBatch = StokGudangBatch::where('gudang_id', $outletGudangId)
                    ->where('barang_id', $al->produk_id)
                    ->where('batch_number', $batchNumber)
                    ->first();

                if ($existingBatch) {
                    $existingBatch->divisi_id = $kitchenDivId;
                    $existingBatch->created_at = $txDate;
                    $existingBatch->save();
                } else {
                    StokGudangBatch::create([
                        'gudang_id'           => $outletGudangId,
                        'divisi_id'           => $kitchenDivId,
                        'supplier_id'         => $supplierId,
                        'barang_id'           => $al->produk_id,
                        'pembelian_id'        => null,
                        'pembelian_detail_id' => null,
                        'batch_number'        => $batchNumber,
                        'qty_masuk'           => $qtyTransfer,
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $qtyTransfer,
                        'harga_per_qty'       => $hppPerUnit,
                        'is_habis'            => false,
                        'created_at'          => $txDate,
                    ]);
                }

                // 5. Transaksi masuk ke Outlet Kitchen
                TransaksiStok::create([
                    'tanggal'          => $txDate,
                    'tipe'             => 'masuk',
                    'source_type'      => 'transfer_ck',
                    'source_id'        => $al->produksi_id,
                    'gudang_asal_id'   => $gudangCkId,
                    'gudang_tujuan_id' => $outletGudangId,
                    'divisi_tujuan_id' => $kitchenDivId,
                    'barang_id'        => $al->produk_id,
                    'qty'              => $qtyTransfer,
                    'total_harga'      => $totalHpp,
                    'created_by'       => 1,
                ]);

                // 6. Buat Surat Jalan otomatis dengan status Selesai jika belum ada
                $existingPengiriman = Pengiriman::where('pesanan_id', $al->pesanan_id)
                    ->orWhere('no_pengiriman', 'DO-CK-' . $al->kode_produksi . '-' . $al->pesanan_id)
                    ->first();
                if (!$existingPengiriman) {
                    try {
                        $existingPengiriman = Pengiriman::firstOrCreate(
                            ['no_pengiriman' => 'DO-CK-' . $al->kode_produksi . '-' . $al->pesanan_id],
                            [
                                'pesanan_id'         => $al->pesanan_id,
                                'tanggal_pengiriman' => $txDate,
                                'kurir'              => 'Internal CK Transfer',
                                'status_pengiriman'  => 'Selesai',
                            ]
                        );
                    } catch (\Exception $e) {
                        $existingPengiriman = Pengiriman::where('pesanan_id', $al->pesanan_id)
                            ->orWhere('no_pengiriman', 'DO-CK-' . $al->kode_produksi . '-' . $al->pesanan_id)
                            ->first();
                    }
                } else {
                    $existingPengiriman->update(['status_pengiriman' => 'Selesai']);
                }

                if ($existingPengiriman) {
                    PengirimanDetail::firstOrCreate(
                        ['pengiriman_id' => $existingPengiriman->id, 'barang_id' => $al->produk_id],
                        ['qty_kirim' => $qtyTransfer]
                    );
                }

                // 7. Update alokasi & status pesanan
                DB::table('alokasi_produksi_pesanan')->where('id', $al->id)->update([
                    'qty_terkirim' => $qtyTransfer,
                ]);
                DB::table('pesanan')->where('id', $al->pesanan_id)->update([
                    'status_pesanan' => 'Selesai',
                ]);

                $affectedItems[] = ['barang_id' => $al->produk_id, 'gudang_id' => $outletGudangId, 'divisi_id' => $kitchenDivId];
                $affectedItems[] = ['barang_id' => $al->produk_id, 'gudang_id' => $gudangCkId, 'divisi_id' => null];
            }

            // 3. Periksa langsung dari tabel produksi & produksi_detail jika ada produksi Selesai yang belum dialokasikan
            $prodDetailQuery = DB::table('produksi_detail as pd')
                ->join('produksi as p', 'pd.produksi_id', '=', 'p.id')
                ->leftJoin('pesanan as pes', 'p.pesanan_id', '=', 'pes.id')
                ->leftJoin('customers as cust', 'pes.customer_id', '=', 'cust.id')
                ->where(DB::raw('LOWER(p.status_produksi)'), 'selesai')
                ->where('pd.qty', '>', 0)
                ->select(
                    'pd.*',
                    'p.kode_produksi',
                    'p.tanggal_selesai',
                    'p.tanggal_mulai',
                    'p.pesanan_id',
                    'p.gudang_hasil_id',
                    'pes.customer_id',
                    'cust.nama as customer_nama',
                    'pes.gudang_id as pesanan_gudang_id',
                    'pes.divisi_id as pesanan_divisi_id',
                    'pes.kode_pesanan',
                    'pes.tanggal as pesanan_tanggal'
                );

            if ($barangId) {
                $prodDetailQuery->where('pd.produk_id', $barangId);
            }

            $prodDetails = $prodDetailQuery->get();

            foreach ($prodDetails as $pdet) {
                // Jika pesanan_id kosong, coba cari dari work_order_detail
                $pesananId = $pdet->pesanan_id;
                $custNama = $pdet->customer_nama;
                $custId = $pdet->customer_id;
                $pesGudangId = $pdet->pesanan_gudang_id;
                $pesDivId = $pdet->pesanan_divisi_id;
                $pesTanggal = $pdet->pesanan_tanggal;

                if (!$pesananId) {
                    $wod = DB::table('work_order_detail as wod')
                        ->join('pesanan as ps', 'wod.pesanan_id', '=', 'ps.id')
                        ->leftJoin('customers as cust', 'ps.customer_id', '=', 'cust.id')
                        ->where('wod.produk_id', $pdet->produk_id)
                        ->select('wod.pesanan_id', 'cust.nama as customer_nama', 'ps.customer_id', 'ps.gudang_id', 'ps.divisi_id', 'ps.tanggal')
                        ->first();
                    if ($wod) {
                        $pesananId = $wod->pesanan_id;
                        $custNama = $wod->customer_nama;
                        $custId = $wod->customer_id;
                        $pesGudangId = $wod->gudang_id;
                        $pesDivId = $wod->divisi_id;
                        $pesTanggal = $wod->tanggal;
                    }
                }

                if (!$pesananId) {
                    continue;
                }

                // Pastikan item produk ini memang dipesan dalam pesanan_detail (bukan alokasi anomali)
                $pesananDetailExists = DB::table('pesanan_detail')
                    ->where('pesanan_id', $pesananId)
                    ->where('produk_id', $pdet->produk_id)
                    ->exists();

                if (!$pesananDetailExists) {
                    continue;
                }

                [$outletGudangId, $kitchenDivId] = $resolveOutletInfo(
                    $pesGudangId,
                    $pesDivId,
                    $custNama,
                    $custId,
                    $pdet->produk_id
                );

                if (!$outletGudangId || !$kitchenDivId) {
                    continue;
                }

                // Cek apakah sudah tercatat transaksi masuk ke outlet
                $hasTxMasuk = DB::table('transaksi_stok')
                    ->where('barang_id', $pdet->produk_id)
                    ->where('gudang_tujuan_id', $outletGudangId)
                    ->where('tipe', 'masuk')
                    ->where(function($q) use ($pdet, $pesananId) {
                        $q->where(function($sub) use ($pdet) {
                            $sub->where('source_type', 'transfer_ck')
                                ->where('source_id', $pdet->produksi_id);
                        })->orWhere(function($sub) use ($pesananId) {
                            $sub->whereIn('source_type', ['transfer_ck', 'pengiriman'])
                                ->whereIn('source_id', function($sq) use ($pesananId) {
                                    $sq->select('id')->from('pengiriman')->where('pesanan_id', $pesananId);
                                });
                        });
                    })
                    ->first();

                if ($hasTxMasuk) {
                    if ($hasTxMasuk->divisi_tujuan_id != $kitchenDivId) {
                        DB::table('transaksi_stok')->where('id', $hasTxMasuk->id)->update(['divisi_tujuan_id' => $kitchenDivId]);
                        DB::table('stok_gudang_batch')
                            ->where('gudang_id', $outletGudangId)
                            ->where('barang_id', $pdet->produk_id)
                            ->where(function($q) use ($pdet) {
                                $q->where('batch_number', 'like', "%{$pdet->kode_produksi}%")
                                  ->orWhere('batch_number', 'like', 'TRANSFER-CK-%');
                            })
                            ->update(['divisi_id' => $kitchenDivId]);
                        $affectedItems[] = ['barang_id' => $pdet->produk_id, 'gudang_id' => $outletGudangId, 'divisi_id' => $kitchenDivId];
                    }
                    continue;
                }

                // Catat transaksi transfer masuk jika belum ada
                $qtyTransfer = floatval($pdet->qty);
                $totalHpp    = floatval($pdet->hpp_total);
                $hppPerUnit  = $qtyTransfer > 0 ? ($totalHpp / $qtyTransfer) : 0;
                $txDate      = $pdet->tanggal_selesai ?: ($pdet->tanggal_mulai ?: ($pesTanggal ?: now()));

                // Kurangi stok CK
                $stokCk = DB::table('stok_gudang')
                    ->where('gudang_id', $gudangCkId)
                    ->where('barang_id', $pdet->produk_id)
                    ->first();
                if ($stokCk) {
                    DB::table('stok_gudang')->where('id', $stokCk->id)->decrement('jumlah', min(floatval($stokCk->jumlah), $qtyTransfer));
                }

                $batchCk = StokGudangBatch::where('gudang_id', $gudangCkId)
                    ->where('barang_id', $pdet->produk_id)
                    ->where('batch_number', 'like', '%' . $pdet->kode_produksi . '%')
                    ->first();
                if ($batchCk) {
                    $batchCk->increment('qty_keluar', $qtyTransfer);
                    $batchCk->decrement('qty_sisa', min(floatval($batchCk->qty_sisa), $qtyTransfer));
                    if ($batchCk->qty_sisa <= 0) $batchCk->is_habis = true;
                    $batchCk->save();
                }

                // Transaksi keluar CK
                TransaksiStok::create([
                    'tanggal'          => $txDate,
                    'tipe'             => 'keluar',
                    'source_type'      => 'transfer_ck',
                    'source_id'        => $pdet->produksi_id,
                    'gudang_asal_id'   => $gudangCkId,
                    'gudang_tujuan_id' => $outletGudangId,
                    'divisi_tujuan_id' => $kitchenDivId,
                    'barang_id'        => $pdet->produk_id,
                    'qty'              => $qtyTransfer,
                    'total_harga'      => $totalHpp,
                    'created_by'       => 1,
                ]);

                // Tambah stok gudang outlet Kitchen
                $stokOutlet = DB::table('stok_gudang')
                    ->where('gudang_id', $outletGudangId)
                    ->where('divisi_id', $kitchenDivId)
                    ->where('barang_id', $pdet->produk_id)
                    ->first();

                if ($stokOutlet) {
                    DB::table('stok_gudang')->where('id', $stokOutlet->id)->increment('jumlah', $qtyTransfer);
                } else {
                    DB::table('stok_gudang')->insert([
                        'gudang_id' => $outletGudangId,
                        'divisi_id' => $kitchenDivId,
                        'barang_id' => $pdet->produk_id,
                        'jumlah'    => $qtyTransfer,
                    ]);
                }

                // Batch outlet
                $supplierId = DB::table('suppliers')->value('id') ?? 1;
                $batchNumber = 'TRANSFER-CK-' . $pdet->kode_produksi . '-' . $pesananId;
                $existingBatch = StokGudangBatch::where('gudang_id', $outletGudangId)
                    ->where('barang_id', $pdet->produk_id)
                    ->where('batch_number', $batchNumber)
                    ->first();

                if ($existingBatch) {
                    $existingBatch->divisi_id = $kitchenDivId;
                    $existingBatch->save();
                } else {
                    StokGudangBatch::create([
                        'gudang_id'           => $outletGudangId,
                        'divisi_id'           => $kitchenDivId,
                        'supplier_id'         => $supplierId,
                        'barang_id'           => $pdet->produk_id,
                        'pembelian_id'        => null,
                        'pembelian_detail_id' => null,
                        'batch_number'        => $batchNumber,
                        'qty_masuk'           => $qtyTransfer,
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $qtyTransfer,
                        'harga_per_qty'       => $hppPerUnit,
                        'is_habis'            => false,
                    ]);
                }

                // Transaksi masuk ke outlet Kitchen
                TransaksiStok::create([
                    'tanggal'          => $txDate,
                    'tipe'             => 'masuk',
                    'source_type'      => 'transfer_ck',
                    'source_id'        => $pdet->produksi_id,
                    'gudang_asal_id'   => $gudangCkId,
                    'gudang_tujuan_id' => $outletGudangId,
                    'divisi_tujuan_id' => $kitchenDivId,
                    'barang_id'        => $pdet->produk_id,
                    'qty'              => $qtyTransfer,
                    'total_harga'      => $totalHpp,
                    'created_by'       => 1,
                ]);

                // Buat Pengiriman jika belum ada
                $existingPengiriman = Pengiriman::where('pesanan_id', $pesananId)
                    ->orWhere('no_pengiriman', 'DO-CK-' . $pdet->kode_produksi . '-' . $pesananId)
                    ->first();
                if (!$existingPengiriman) {
                    try {
                        $existingPengiriman = Pengiriman::firstOrCreate(
                            ['no_pengiriman' => 'DO-CK-' . $pdet->kode_produksi . '-' . $pesananId],
                            [
                                'pesanan_id'         => $pesananId,
                                'tanggal_pengiriman' => $txDate,
                                'kurir'              => 'Internal CK Transfer',
                                'status_pengiriman'  => 'Selesai',
                            ]
                        );
                    } catch (\Exception $e) {
                        $existingPengiriman = Pengiriman::where('pesanan_id', $pesananId)
                            ->orWhere('no_pengiriman', 'DO-CK-' . $pdet->kode_produksi . '-' . $pesananId)
                            ->first();
                    }
                } else {
                    $existingPengiriman->update(['status_pengiriman' => 'Selesai']);
                }

                if ($existingPengiriman) {
                    PengirimanDetail::firstOrCreate(
                        ['pengiriman_id' => $existingPengiriman->id, 'barang_id' => $pdet->produk_id],
                        ['qty_kirim' => $qtyTransfer]
                    );
                }

                // Pastikan record di alokasi_produksi_pesanan ada
                ProduksiPesanan::updateOrCreate(
                    [
                        'produksi_id' => $pdet->produksi_id,
                        'pesanan_id'  => $pesananId,
                        'produk_id'   => $pdet->produk_id,
                    ],
                    [
                        'qty_alokasi'       => $qtyTransfer,
                        'qty_terkirim'      => $qtyTransfer,
                        'hpp_per_unit'      => $hppPerUnit,
                        'total_hpp_alokasi' => $totalHpp,
                    ]
                );

                DB::table('pesanan')->where('id', $pesananId)->update(['status_pesanan' => 'Selesai']);

                $affectedItems[] = ['barang_id' => $pdet->produk_id, 'gudang_id' => $outletGudangId, 'divisi_id' => $kitchenDivId];
                $affectedItems[] = ['barang_id' => $pdet->produk_id, 'gudang_id' => $gudangCkId, 'divisi_id' => null];
            }

            // 4. Sinkronkan ringkasan stok untuk seluruh item yang terpengaruh
            $uniqueAffected = collect($affectedItems)->unique(function($item) {
                return $item['barang_id'] . '-' . $item['gudang_id'] . '-' . ($item['divisi_id'] ?? 'null');
            });

            foreach ($uniqueAffected as $aff) {
                \App\Models\StokGudang::reconcileStockSummary($aff['barang_id'], $aff['gudang_id'], $aff['divisi_id']);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealApprovedCkProductionTransfers error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * Auto-heal transaksi_stok untuk seluruh Persediaan Awal yang berstatus approved / posted
     * agar tercatat akurat sebagai baseline saldo awal per gudang dan divisi.
     */
    public static function autoHealApprovedPaTransaksiStok($barangId = null)
    {
        try {
            $paQuery = \App\Models\PersediaanAwal::whereIn(DB::raw('LOWER(status)'), ['approved', 'posted'])
                ->with(['details']);

            if ($barangId) {
                $paQuery->whereHas('details', function ($q) use ($barangId) {
                    $q->where('barang_id', $barangId);
                });
            }

            $approvedPas = $paQuery->get();

            foreach ($approvedPas as $pa) {
                $details = $pa->details;
                if ($barangId) {
                    $details = $details->where('barang_id', $barangId);
                }

                $tanggalPa = ($pa->tanggal ? $pa->tanggal->format('Y-m-d') : date('Y-m-d')) . ' 00:00:01';

                foreach ($details as $detail) {
                    $bId = $detail->barang_id;
                    $qty = (float) $detail->qty;
                    $totalNilai = (float) ($detail->total_nilai ?: ($qty * (float) $detail->harga_satuan));

                    if ($qty > 0) {
                        $txPa = \App\Models\TransaksiStok::whereIn('source_type', ['saldo_awal', 'persediaan_awal'])
                            ->where('source_id', $pa->id)
                            ->where('barang_id', $bId)
                            ->where('tipe', 'masuk')
                            ->first();

                        if (!$txPa) {
                            \App\Models\TransaksiStok::create([
                                'tanggal'          => $tanggalPa,
                                'tipe'             => 'masuk',
                                'source_type'      => 'persediaan_awal',
                                'source_id'        => $pa->id,
                                'gudang_asal_id'   => null,
                                'divisi_asal_id'   => null,
                                'gudang_tujuan_id' => $pa->gudang_id,
                                'divisi_tujuan_id' => $pa->divisi_id,
                                'barang_id'        => $bId,
                                'qty'              => $qty,
                                'total_harga'      => $totalNilai,
                                'created_by'       => $pa->created_by ?? 1,
                            ]);
                        } else {
                            $needsUpdate = false;
                            $upData = [];
                            if ($txPa->gudang_tujuan_id != $pa->gudang_id) {
                                $upData['gudang_tujuan_id'] = $pa->gudang_id;
                                $needsUpdate = true;
                            }
                            if ($txPa->divisi_tujuan_id != $pa->divisi_id) {
                                $upData['divisi_tujuan_id'] = $pa->divisi_id;
                                $needsUpdate = true;
                            }
                            if (abs((float)$txPa->qty - $qty) > 0.01) {
                                $upData['qty'] = $qty;
                                $needsUpdate = true;
                            }
                            if (abs((float)$txPa->total_harga - $totalNilai) > 0.01) {
                                $upData['total_harga'] = $totalNilai;
                                $needsUpdate = true;
                            }
                            if ($needsUpdate) {
                                $txPa->update($upData);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealApprovedPaTransaksiStok error: ' . $e->getMessage());
        }
    }

    /**
     * Auto-heal transaksi_stok untuk seluruh Pengeluaran/Permintaan Bahan Baku yang berstatus disetujui/approved
     * agar mutasi transfer masuk ke outlet (Kejingga/Gaharu) dan keluar dari Gudang Utama tercatat akurat.
     */
    public static function autoHealApprovedPbkTransaksiStok($barangId = null)
    {
        try {
            $gudangUtamaId = \App\Models\MasterGudang::getGudangUtamaId();

            $pbkQuery = \App\Models\PengeluaranBahanBaku::whereIn(DB::raw('LOWER(status)'), ['approved', 'disetujui'])
                ->with(['details']);

            if ($barangId) {
                $pbkQuery->whereHas('details', function ($q) use ($barangId) {
                    $q->where('barang_id', $barangId);
                });
            }

            $approvedPbks = $pbkQuery->get();

            foreach ($approvedPbks as $pbk) {
                $isPosOrSales = str_starts_with($pbk->kode_pengeluaran ?? '', 'OUT-MOKA-')
                    || str_starts_with($pbk->kode_pengeluaran ?? '', 'AUTO_POS')
                    || str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-POS-')
                    || $pbk->jenis_pengeluaran === 'penjualan';

                $isOpnameOrWasted = str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-SO-')
                    || $pbk->jenis_pengeluaran === 'wasted'
                    || str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-WST-')
                    || $isPosOrSales;

                $isTransferFromUtama = ($pbk->jenis_pengeluaran === 'transfer' || str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-'))
                    && !$isOpnameOrWasted;

                $details = $pbk->details;
                if ($barangId) {
                    $details = $details->where('barang_id', $barangId);
                }

                foreach ($details as $detail) {
                    $bId = $detail->barang_id;
                    $barang = \App\Models\MasterBarang::find($bId);
                    if (!$barang) continue;

                    $satuanMaster = strtolower(trim($barang->satuan ?: 'pcs'));
                    $konversi = (float)($barang->konversi_pembelian ?: 1);
                    if ($konversi <= 0) $konversi = 1;

                    // 1. Dapatkan harga referensi modal satuan dasar yang valid
                    $refUnit = 0;
                    $paDetail = DB::table('persediaan_awal_detail as pad')
                        ->join('persediaan_awal as pa', 'pa.id', '=', 'pad.persediaan_awal_id')
                        ->where('pad.barang_id', $bId)
                        ->where('pa.gudang_id', $gudangUtamaId)
                        ->whereIn('pa.status', ['approved', 'posted'])
                        ->where('pad.harga_satuan', '>', 0)
                        ->orderBy('pa.tanggal', 'desc')
                        ->select('pad.harga_satuan')
                        ->first();
                    if ($paDetail && (float)$paDetail->harga_satuan > 0) {
                        $refUnit = (float)$paDetail->harga_satuan;
                    }

                    if ($refUnit <= 0) {
                        $fifoService = app(\App\Services\FifoService::class);
                        $refUnit = $fifoService->getHargaTerakhirBahan($bId, $gudangUtamaId);
                    }
                    if ($refUnit <= 0 || abs($refUnit - 233) <= 1 || abs($refUnit - 25888.89) <= 1) {
                        $refUnit = (float) ($barang->hpp_referensi ?: 0);
                    }

                    $qty = (float) $detail->qty;
                    $totalHarga = (float) ($detail->total_harga ?: ($detail->hpp_total ?: ($qty * (float) $detail->harga_satuan)));
                    $unitPrice = $qty > 0 ? ($totalHarga / $qty) : 0;
                    $curPrice = (float) ($detail->harga_satuan ?: $unitPrice);

                    // A. Deteksi jika harga satuan transaksi terdistorsi oleh harga dummy corrupt (~233)
                    if ($unitPrice > 0 && (abs($unitPrice - 233) <= 1 || abs($unitPrice - 25888.89) <= 1) && $refUnit > 0) {
                        $totalHarga = round($qty * $refUnit, 2);
                        $detail->update([
                            'harga_satuan' => $refUnit,
                            'total_harga'  => $totalHarga,
                            'hpp_total'    => $totalHarga,
                        ]);
                        $unitPrice = $refUnit;
                    }

                    // B. Deteksi jika satuan master adalah PCS, tetapi detail PBK tersimpan kuantitas dalam gram
                    //    atau harga satuan PBK masih tertinggal harga gram lama (cth: Rp 32 vs Rp 800)
                    $priceRatio = $curPrice > 0 ? ($refUnit / $curPrice) : 0;
                    $isGramToPcsMismatch = ($satuanMaster === 'pcs' && $refUnit > 0 && $curPrice > 0 && (
                        abs($priceRatio - 25) < 2.0 || 
                        ($curPrice < ($refUnit * 0.25) && $curPrice > 0)
                    ));

                    if ($isGramToPcsMismatch) {
                        // Jika qty >= 1000, berarti kuantitas masih dalam gram (cth: 4000 GR -> 160 PCS)
                        if ($qty >= 1000) {
                            $realQty = round($qty / 25, 2);
                        } else {
                            // Qty sudah dalam pcs (cth: 160 PCS), pertahankan qty dan cukup update harga!
                            $realQty = $qty;
                        }
                        $realUnitPrice = $refUnit; // Rp 800
                        $realTotal = round($realQty * $refUnit, 2); // 160 * 800 = 128.000

                        $detail->update([
                            'qty'          => $realQty,
                            'satuan'       => 'pcs',
                            'harga_satuan' => $realUnitPrice,
                            'total_harga'  => $realTotal,
                            'hpp_total'    => $realTotal,
                        ]);

                        $qty = $realQty;
                        $totalHarga = $realTotal;
                        $unitPrice = $realUnitPrice;

                        // Perbaiki batch di Gudang Utama:
                        // Tutup batch lama September (sebelum cut-off) agar tidak digunakan oleh transaksi Oktober
                        \App\Models\StokGudangBatch::where('barang_id', $bId)
                            ->where('gudang_id', $gudangUtamaId)
                            ->where('batch_number', 'like', 'SA-202609%')
                            ->update([
                                'qty_keluar' => 0,
                                'qty_sisa'   => 0,
                                'is_habis'   => 1,
                            ]);

                        // Perbarui batch Oktober agar menampung mutasi keluar yang valid
                        \App\Models\StokGudangBatch::where('barang_id', $bId)
                            ->where('gudang_id', $gudangUtamaId)
                            ->where('batch_number', 'like', 'SA-202610%')
                            ->each(function($b) use ($realQty) {
                                $newKeluar = min((float)$b->qty_masuk, $realQty);
                                $newSisa = max(0, (float)$b->qty_masuk - $newKeluar);
                                $b->update([
                                    'qty_keluar' => $newKeluar,
                                    'qty_sisa'   => $newSisa,
                                    'is_habis'   => ($newSisa <= 0),
                                ]);
                            });

                        // Perbaiki batch mutasi di gudang tujuan
                        if ($pbk->gudang_id) {
                            \App\Models\StokGudangBatch::where('barang_id', $bId)
                                ->where('gudang_id', $pbk->gudang_id)
                                ->where('batch_number', 'like', '%-MUT%')
                                ->where('batch_number', 'like', 'SA-202609%')
                                ->update([
                                    'qty_sisa' => 0,
                                    'is_habis' => 1,
                                ]);

                            \App\Models\StokGudangBatch::where('barang_id', $bId)
                                ->where('gudang_id', $pbk->gudang_id)
                                ->where('batch_number', 'like', 'SA-202610%-MUT')
                                ->each(function($b) use ($realQty, $realUnitPrice) {
                                    $b->update([
                                        'qty_masuk'     => $realQty,
                                        'qty_sisa'      => max(0, $realQty - (float)$b->qty_keluar),
                                        'harga_per_qty' => $realUnitPrice,
                                    ]);
                                });
                        }
                    } elseif ($refUnit > 0 && ($unitPrice <= 0 || $totalHarga <= 0)) {
                        $totalHarga = round($qty * $refUnit, 2);
                        $detail->update([
                            'harga_satuan' => $refUnit,
                            'total_harga'  => $totalHarga,
                            'hpp_total'    => $totalHarga,
                        ]);
                        $unitPrice = $refUnit;
                    }

                    $tanggal = $pbk->tanggal ?? $pbk->created_at ?? now();

                    if ($isTransferFromUtama && $pbk->gudang_id) {
                        // 1. Mutasi Masuk di Gudang/Divisi Tujuan (e.g. Kejingga)
                        $txMasuk = \App\Models\TransaksiStok::where('source_type', 'pengeluaran_bahan_baku')
                            ->where('source_id', $pbk->id)
                            ->where('barang_id', $bId)
                            ->where('tipe', 'masuk')
                            ->first();

                        $targetMasukDivisiId = $pbk->divisi_id ?: \App\Models\MasterGudang::resolveDivisiIdForBarang($pbk->gudang_id, $bId);

                        if (!$txMasuk) {
                            \App\Models\TransaksiStok::create([
                                'tanggal'          => $tanggal,
                                'tipe'             => 'masuk',
                                'source_type'      => 'pengeluaran_bahan_baku',
                                'source_id'        => $pbk->id,
                                'gudang_asal_id'   => null,
                                'divisi_asal_id'   => null,
                                'gudang_tujuan_id' => $pbk->gudang_id,
                                'divisi_tujuan_id' => $targetMasukDivisiId,
                                'barang_id'        => $bId,
                                'qty'              => $qty,
                                'total_harga'      => $totalHarga,
                                'created_by'       => $pbk->approved_by ?? $pbk->created_by ?? 1,
                            ]);
                        } else {
                            $needsUpdate = false;
                            $upMasuk = [];
                            if ($txMasuk->gudang_tujuan_id != $pbk->gudang_id) {
                                $upMasuk['gudang_tujuan_id'] = $pbk->gudang_id;
                                $needsUpdate = true;
                            }
                            if ($targetMasukDivisiId && $txMasuk->divisi_tujuan_id != $targetMasukDivisiId) {
                                $upMasuk['divisi_tujuan_id'] = $targetMasukDivisiId;
                                $needsUpdate = true;
                            }
                            if (abs((float)$txMasuk->qty - $qty) > 0.01) {
                                $upMasuk['qty'] = $qty;
                                $needsUpdate = true;
                            }
                            if (abs((float)$txMasuk->total_harga - $totalHarga) > 0.01) {
                                $upMasuk['total_harga'] = $totalHarga;
                                $needsUpdate = true;
                            }
                            if ($needsUpdate) {
                                $txMasuk->update($upMasuk);
                            }
                        }

                        // 2. Mutasi Keluar di Gudang Asal (Gudang Utama)
                        $txKeluar = \App\Models\TransaksiStok::where('source_type', 'pengeluaran_bahan_baku')
                            ->where('source_id', $pbk->id)
                            ->where('barang_id', $bId)
                            ->where('tipe', 'keluar')
                            ->first();

                        if (!$txKeluar) {
                            \App\Models\TransaksiStok::create([
                                'tanggal'          => $tanggal,
                                'tipe'             => 'keluar',
                                'source_type'      => 'pengeluaran_bahan_baku',
                                'source_id'        => $pbk->id,
                                'gudang_asal_id'   => $gudangUtamaId,
                                'divisi_asal_id'   => null,
                                'gudang_tujuan_id' => null,
                                'divisi_tujuan_id' => null,
                                'barang_id'        => $bId,
                                'qty'              => $qty,
                                'total_harga'      => $totalHarga,
                                'created_by'       => $pbk->approved_by ?? $pbk->created_by ?? 1,
                            ]);
                        } else {
                            $upKeluar = [];
                            if (abs((float)$txKeluar->qty - $qty) > 0.01) {
                                $upKeluar['qty'] = $qty;
                            }
                            if (abs((float)$txKeluar->total_harga - $totalHarga) > 0.01) {
                                $upKeluar['total_harga'] = $totalHarga;
                            }
                            if (!empty($upKeluar)) {
                                $txKeluar->update($upKeluar);
                            }
                        }
                    }
                }
            }

            // Pastikan batch persediaan awal aktif di Gudang Utama mencerminkan mutasi keluar PBK disetujui
            if ($barangId) {
                $targetBarangIds = [$barangId];
            } else {
                $targetBarangIds = $approvedPbks->pluck('details')->flatten()->pluck('barang_id')->unique()->toArray();
            }

            foreach ($targetBarangIds as $tBId) {
                $tPbkKeluar = (float) \App\Models\TransaksiStok::where('barang_id', $tBId)
                    ->where('gudang_asal_id', $gudangUtamaId)
                    ->where('source_type', 'pengeluaran_bahan_baku')
                    ->where('tipe', 'keluar')
                    ->where('tanggal', '>=', '2026-10-01 00:00:00')
                    ->sum('qty');

                if ($tPbkKeluar > 0) {
                    \App\Models\StokGudangBatch::where('barang_id', $tBId)
                        ->where('gudang_id', $gudangUtamaId)
                        ->where('batch_number', 'like', 'SA-202610%')
                        ->each(function($b) use ($tPbkKeluar) {
                            $newKeluar = min((float)$b->qty_masuk, $tPbkKeluar);
                            $newSisa = max(0, (float)$b->qty_masuk - $newKeluar);
                            $b->update([
                                'qty_keluar' => $newKeluar,
                                'qty_sisa'   => $newSisa,
                                'is_habis'   => ($newSisa <= 0),
                            ]);
                        });
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealApprovedPbkTransaksiStok error: ' . $e->getMessage());
        }
    }

    /**
     * Auto-heal mutasi pemotongan stok untuk transaksi Penjualan POS (Moka) yang disetujui / di-import.
     * Memastikan:
     * 1. Penjualan POS berstatus Draft (misal dari import Moka) yang memiliki formulasi resep di-approve otomatis.
     * 2. Penjualan POS yang berstatus Approved/Disetujui tetapi belum memiliki catatan transaksi_stok dijalankan pemotongan FIFO-nya.
     * 3. Penjualan POS yang produknya menggunakan barangId tertentu namun pemotongan stok bahan tersebut belum tercatat di transaksi_stok diproses ulang.
     * 4. Transaksi_stok penjualan POS yang divisi_asal_id nya null / salah gudang disinkronkan ke divisi operasional yang tepat (Barista / Kitchen).
     */
    /**
     * Resolusi gudang outlet yang akurat untuk transaksi Penjualan POS.
     * Memeriksa tipe_penjualan pada produk-produk yang dijual (POS Kejingga vs POS Gaharu),
     * prefix kode transaksi, serta kepemilikan gudang outlet.
     */
    public static function resolveOutletForPos($pos): int
    {
        $kejinggaId = \App\Models\MasterGudang::resolveOutletId('kejingga');
        $gaharuId   = \App\Models\MasterGudang::resolveOutletId('gaharu');

        if (!$pos) return $gaharuId;

        // 1. Cek dari produk di dalam detail transaksi POS
        $posId = is_object($pos) ? ($pos->id ?? null) : (int) $pos;
        if ($posId) {
            $details = \App\Models\PenjualanPosDetail::where('penjualan_id', $posId)->get();
            foreach ($details as $d) {
                $prod = \App\Models\MasterBarang::find($d->produk_id);
                if ($prod) {
                    if (stripos($prod->tipe_penjualan ?? '', 'kejingga') !== false) {
                        return $kejinggaId;
                    }
                    if (stripos($prod->tipe_penjualan ?? '', 'gaharu') !== false) {
                        return $gaharuId;
                    }
                }
            }
        }

        // 2. Cek kode_transaksi
        $kode = is_object($pos) ? ($pos->kode_transaksi ?? '') : '';
        if (!empty($kode)) {
            if (stripos($kode, 'kj') !== false || stripos($kode, 'kejingga') !== false) {
                return $kejinggaId;
            }
        }

        // 3. Jika pos->gudang_id sudah valid gudang outlet, pertahankan
        $gId = is_object($pos) ? ($pos->gudang_id ?? null) : null;
        if ($gId && in_array($gId, [$kejinggaId, $gaharuId])) {
            return $gId;
        }

        return $gaharuId;
    }

    /**
     * Auto-heal mutasi pemotongan stok untuk transaksi Penjualan POS (Moka) yang disetujui / di-import.
     * Memastikan:
     * 1. Penjualan POS berstatus Draft (misal dari import Moka) yang memiliki formulasi resep di-approve otomatis.
     * 2. Penjualan POS yang berstatus Approved/Disetujui tetapi belum memiliki catatan transaksi_stok dijalankan pemotongan FIFO-nya.
     * 3. Penjualan POS yang produknya menggunakan barangId tertentu namun pemotongan stok bahan tersebut belum tercatat di transaksi_stok diproses ulang.
     * 4. Transaksi_stok penjualan POS yang divisi_asal_id nya null / salah gudang disinkronkan ke divisi operasional yang tepat (Barista / Kitchen).
     */
    public static function autoHealApprovedPosStockMutations($barangId = null)
    {
        try {
            $outletIds = \App\Models\MasterGudang::getOutletGudangIds();
            $posController = app(\App\Http\Controllers\PenjualanPosController::class);

            if (!$barangId) {
                // 0. Pastikan setiap header Penjualan POS terhubung ke gudang outlet yang benar sesuai produk yang dijual
                $allPos = \App\Models\PenjualanPos::all();
                foreach ($allPos as $pHeader) {
                    $correctOutletId = self::resolveOutletForPos($pHeader);
                    if ($correctOutletId && $pHeader->gudang_id != $correctOutletId) {
                        $pHeader->update(['gudang_id' => $correctOutletId]);
                        DB::table('transaksi_stok')
                            ->whereIn('source_type', ['penjualan_pos', 'pos'])
                            ->where('source_id', $pHeader->id)
                            ->update(['gudang_asal_id' => $correctOutletId]);
                    }
                }

                // 1. Penjualan POS yang masih berstatus 'Draft' (hasil import Moka)
                $draftPosList = \App\Models\PenjualanPos::whereIn(DB::raw('LOWER(status)'), ['draft'])
                    ->whereIn('gudang_id', $outletIds)
                    ->get();

                foreach ($draftPosList as $draftPos) {
                    $hasRecipeProduct = false;
                    foreach ($draftPos->details as $d) {
                        if ($d->produk && $d->produk->hasResep()) {
                            $hasRecipeProduct = true;
                            break;
                        }
                    }
                    if ($hasRecipeProduct) {
                        try {
                            $posController->approve($draftPos->id, true);
                        } catch (\Exception $exApprove) {
                            \Illuminate\Support\Facades\Log::warning("Auto-approve draft POS {$draftPos->id} failed: " . $exApprove->getMessage());
                        }
                    }
                }

                // 2a. Penjualan POS berstatus Approved / Disetujui / Sukses tapi TIDAK memiliki transaksi_stok pemotongan sama sekali
                $approvedPosQuery = \App\Models\PenjualanPos::whereIn(DB::raw('LOWER(status)'), ['approved', 'disetujui', 'sukses'])
                    ->whereIn('gudang_id', $outletIds)
                    ->whereNotExists(function($q) {
                        $q->select(DB::raw(1))
                          ->from('transaksi_stok')
                          ->whereIn('transaksi_stok.source_type', ['penjualan_pos', 'pos'])
                          ->whereColumn('transaksi_stok.source_id', 'penjualan_pos.id');
                    });

                $missingStockPosList = $approvedPosQuery->get();
                foreach ($missingStockPosList as $mPos) {
                    try {
                        // Bersihkan pengeluaran_bahan_baku AUTO_POS sisa jika ada
                        DB::table('pengeluaran_bahan_baku')
                            ->where('keterangan', 'like', 'AUTO_POS:' . $mPos->kode_transaksi . '%')
                            ->orWhere('kode_pengeluaran', 'like', 'OUT-' . $mPos->kode_transaksi . '%')
                            ->delete();

                        $mPos->update(['status' => 'Draft']);
                        $posController->approve($mPos->id, true);
                    } catch (\Exception $exApprove) {
                        \Illuminate\Support\Facades\Log::warning("Auto-heal approved POS {$mPos->id} failed: " . $exApprove->getMessage());
                    }
                }

                // 2a-2. Periksa apakah ada mutasi POS di gudang outlet yang memotong bahan baku mentah (BBB) milik BSJ
                // Central Kitchen (misal daging mentah BBB250 Iga atau BBB187 Fillet Paha).
                // Jika ada, re-approve transaksi POS tersebut agar pemotongan beralih ke item BSJ yang benar.
                $rawBahanInBsj = DB::table('resep_bahanbaku')
                    ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                    ->join('master_barang', 'resep_btkl_bop.produk_id', '=', 'master_barang.id')
                    ->where('master_barang.is_bahan_setengah_jadi', 1)
                    ->whereNotIn('master_barang.id', [1489, 1488])
                    ->where('master_barang.nama', 'not like', '%ACAR KOMPLIT%')
                    ->where('master_barang.nama', 'not like', '%SAMBAL MATAH KOMPLIT%')
                    ->pluck('resep_bahanbaku.bahan_id')
                    ->unique()
                    ->toArray();

                // Jangan masukkan bahan mentah yang JUGA digunakan langsung di resep barang jadi (misal Matcha Diltea, susu, sirup, dll)
                if (!empty($rawBahanInBsj)) {
                    $rawBahanInFinishedGoods = DB::table('resep_bahanbaku')
                        ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                        ->join('master_barang', 'resep_btkl_bop.produk_id', '=', 'master_barang.id')
                        ->where('master_barang.is_barang_jadi', 1)
                        ->pluck('resep_bahanbaku.bahan_id')
                        ->unique()
                        ->toArray();

                    $rawBahanInBsj = array_values(array_diff($rawBahanInBsj, $rawBahanInFinishedGoods));
                }

                if (!empty($rawBahanInBsj)) {
                    $posWithWrongRawMutations = DB::table('transaksi_stok')
                        ->whereIn('source_type', ['penjualan_pos', 'pos'])
                        ->whereIn('gudang_asal_id', $outletIds)
                        ->whereIn('barang_id', $rawBahanInBsj)
                        ->pluck('source_id')
                        ->unique()
                        ->toArray();

                    foreach ($posWithWrongRawMutations as $wrongPosId) {
                        $wPos = \App\Models\PenjualanPos::find($wrongPosId);
                        if ($wPos) {
                            $originalStatus = $wPos->status;
                            try {
                                DB::beginTransaction();

                                DB::table('pengeluaran_bahan_baku')
                                    ->where('keterangan', 'like', 'AUTO_POS:' . $wPos->kode_transaksi . '%')
                                    ->orWhere('kode_pengeluaran', 'like', 'OUT-' . $wPos->kode_transaksi . '%')
                                    ->delete();
                                DB::table('transaksi_stok')
                                    ->whereIn('source_type', ['penjualan_pos', 'pos'])
                                    ->where('source_id', $wPos->id)
                                    ->delete();

                                $wPos->update(['status' => 'Draft']);
                                $appRes = $posController->approve($wPos->id, true);

                                if (is_array($appRes) && isset($appRes['status']) && $appRes['status'] === 'success') {
                                    DB::commit();
                                } else {
                                    DB::rollBack();
                                    $wPos->update(['status' => $originalStatus]);
                                    \Illuminate\Support\Facades\Log::warning("Auto-heal wrong raw POS {$wPos->id} not approved: " . json_encode($appRes));
                                }
                            } catch (\Exception $exApprove) {
                                DB::rollBack();
                                $wPos->update(['status' => $originalStatus]);
                                \Illuminate\Support\Facades\Log::warning("Auto-heal wrong raw POS {$wPos->id} failed: " . $exApprove->getMessage());
                            }
                        }
                    }
                }
            }

            // 2b. Periksa Penjualan POS yang disetujui untuk sinkronisasi pemotongan resep bahan baku terbaru
            if ($barangId) {
                $bRow = DB::table('master_barang')->where('id', $barangId)->first();
                $bSatDasar = strtolower(trim($bRow->satuan ?? ''));
                $bKonv = floatval($bRow->konversi_pembelian ?? 1);

                $isUsedInBsj = DB::table('resep_bahanbaku')
                    ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                    ->join('master_barang', 'resep_btkl_bop.produk_id', '=', 'master_barang.id')
                    ->where('resep_bahanbaku.bahan_id', $barangId)
                    ->where('master_barang.is_bahan_setengah_jadi', 1)
                    ->whereNotIn('master_barang.id', [1489, 1488])
                    ->where('master_barang.nama', 'not like', '%ACAR KOMPLIT%')
                    ->where('master_barang.nama', 'not like', '%SAMBAL MATAH KOMPLIT%')
                    ->exists();

                // Pastikan pemetaan resep_id pada master_barang tersinkronisasi
                \App\Models\MasterBarang::syncAllResepIds();

                // Cari resep yang menggunakan barang ini
                $resepIdsUsingBarang = DB::table('resep_bahanbaku')
                    ->where('bahan_id', $barangId)
                    ->pluck('resep_id')
                    ->merge(
                        DB::table('resep_bahanbaku_alternatif')
                            ->join('resep_bahanbaku', 'resep_bahanbaku_alternatif.resep_bahanbaku_id', '=', 'resep_bahanbaku.id')
                            ->where('resep_bahanbaku_alternatif.bahan_id', $barangId)
                            ->pluck('resep_bahanbaku.resep_id')
                    )->unique()->toArray();

                $posIdsWithBarangMutasi = DB::table('transaksi_stok')
                    ->whereIn('source_type', ['penjualan_pos', 'pos'])
                    ->where('barang_id', $barangId)
                    ->pluck('source_id')
                    ->unique()->toArray();

                // Cari produk jadi yang menggunakan resep ini, baik melalui resep_id langsung, resep_btkl_bop.produk_id, maupun nama produk
                $productIds = [];
                if (!empty($resepIdsUsingBarang)) {
                    $directProductIds = DB::table('master_barang')->whereIn('resep_id', $resepIdsUsingBarang)->pluck('id')->toArray();
                    $resepProdukIds = DB::table('resep_btkl_bop')->whereIn('id', $resepIdsUsingBarang)->whereNotNull('produk_id')->pluck('produk_id')->toArray();
                    $allMatchedProdIds = array_unique(array_merge($directProductIds, $resepProdukIds));

                    // Jika bahan ini digunakan oleh BSJ, cari juga produk akhir yang menggunakan BSJ tersebut
                    if ($isUsedInBsj) {
                        $parentBsjIds = DB::table('master_barang')->whereIn('id', $allMatchedProdIds)->where('is_bahan_setengah_jadi', 1)->pluck('id')->toArray();
                        if (!empty($parentBsjIds)) {
                            $parentResepIds = DB::table('resep_bahanbaku')->whereIn('bahan_id', $parentBsjIds)->pluck('resep_id')->toArray();
                            $finishedProdIds = DB::table('master_barang')->whereIn('resep_id', $parentResepIds)->pluck('id')->toArray();
                            $finishedResepProdIds = DB::table('resep_btkl_bop')->whereIn('id', $parentResepIds)->whereNotNull('produk_id')->pluck('produk_id')->toArray();
                            $allMatchedProdIds = array_unique(array_merge($allMatchedProdIds, $finishedProdIds, $finishedResepProdIds));
                        }
                    }

                    // Ambil nama-nama produk untuk mencari padanan antar-outlet (mis. POS Gaharu vs POS Kejingga)
                    $prodNames = DB::table('master_barang')->whereIn('id', $allMatchedProdIds)->pluck('nama')->toArray();
                    $lowerNames = array_unique(array_filter(array_map(function($n) { return strtolower(trim($n)); }, $prodNames)));

                    $counterpartProductIds = !empty($lowerNames)
                        ? DB::table('master_barang')->whereIn(DB::raw('LOWER(TRIM(nama))'), $lowerNames)->pluck('id')->toArray()
                        : [];

                    $productIds = array_unique(array_merge($allMatchedProdIds, $counterpartProductIds));
                }

                $posIdsWithProducts = !empty($productIds)
                    ? DB::table('penjualanpos_detail')->whereIn('produk_id', $productIds)->pluck('penjualan_id')->unique()->toArray()
                    : [];

                $allCandidatePosIds = array_unique(array_merge($posIdsWithBarangMutasi, $posIdsWithProducts));

                if (empty($allCandidatePosIds)) {
                    return;
                }

                // Cek apakah barang ini adalah produk rakitan outlet (assembled item seperti Acar Komplit)
                $isAssembledBarang = \App\Http\Controllers\PenjualanPosController::isAssembledProduct($bRow);

                if ($isAssembledBarang) {
                    // Produk rakitan di outlet tidak boleh dipotong barang jadinya di POS
                    $resepBahanMap = collect();
                    $altBahanMap = collect();
                } else {
                    // Pre-fetch resep info and ingredient usage in memory for instant lookups (O(1))
                    $resepBahanMap = DB::table('resep_bahanbaku')
                        ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                        ->where('resep_bahanbaku.bahan_id', $barangId)
                        ->select('resep_bahanbaku.resep_id', 'resep_bahanbaku.qty_bahan', 'resep_bahanbaku.satuan', 'resep_btkl_bop.output_qty')
                        ->get()
                        ->keyBy('resep_id');

                    // Jika bahan ini digunakan di dalam produk rakitan outlet (cth: BASE ACAR, CUKA, SIMPLE SYRUP di ACAR KOMPLIT):
                    // Map juga resep menu induk yang menggunakan produk rakitan tersebut
                    $assembledUsages = DB::table('resep_bahanbaku')
                        ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                        ->join('master_barang', 'resep_btkl_bop.produk_id', '=', 'master_barang.id')
                        ->where('resep_bahanbaku.bahan_id', $barangId)
                        ->where(function($q) {
                            $q->whereIn('master_barang.id', [1489, 1488])
                              ->orWhere('master_barang.nama', 'like', '%ACAR KOMPLIT%')
                              ->orWhere('master_barang.nama', 'like', '%SAMBAL MATAH KOMPLIT%');
                        })
                        ->select('master_barang.id as assembled_id', 'resep_bahanbaku.qty_bahan', 'resep_btkl_bop.output_qty')
                        ->get();

                    foreach ($assembledUsages as $au) {
                        $subRatio = floatval($au->output_qty) > 0 ? (floatval($au->qty_bahan) / floatval($au->output_qty)) : floatval($au->qty_bahan);
                        $parentRecipes = DB::table('resep_bahanbaku')
                            ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                            ->where('resep_bahanbaku.bahan_id', $au->assembled_id)
                            ->select('resep_bahanbaku.resep_id', 'resep_bahanbaku.qty_bahan as parent_qty', 'resep_btkl_bop.output_qty as parent_output')
                            ->get();

                        foreach ($parentRecipes as $pr) {
                            $parentOutput = floatval($pr->parent_output) > 0 ? floatval($pr->parent_output) : 1.0;
                            $effectiveQty = floatval($pr->parent_qty) * $subRatio;
                            $resepBahanMap->put($pr->resep_id, (object)[
                                'resep_id'   => $pr->resep_id,
                                'qty_bahan'  => $effectiveQty,
                                'satuan'     => $bSatDasar,
                                'output_qty' => $parentOutput,
                            ]);
                        }
                    }

                    $altBahanMap = DB::table('resep_bahanbaku_alternatif')
                        ->join('resep_bahanbaku', 'resep_bahanbaku_alternatif.resep_bahanbaku_id', '=', 'resep_bahanbaku.id')
                        ->join('resep_btkl_bop', 'resep_bahanbaku.resep_id', '=', 'resep_btkl_bop.id')
                        ->where('resep_bahanbaku_alternatif.bahan_id', $barangId)
                        ->select('resep_bahanbaku.resep_id', 'resep_bahanbaku.qty_bahan', 'resep_bahanbaku.satuan', 'resep_btkl_bop.output_qty')
                        ->get()
                        ->keyBy('resep_id');
                }

                $fallbackNamaResepMap = [];
                $mbList = DB::table('master_barang')
                    ->where('is_barang_jadi', 1)
                    ->whereNotNull('resep_id')
                    ->select('nama', 'resep_id')
                    ->get();
                foreach ($mbList as $mb) {
                    $key = strtolower(trim($mb->nama ?? ''));
                    if ($key !== '' && !isset($fallbackNamaResepMap[$key])) {
                        $fallbackNamaResepMap[$key] = $mb->resep_id;
                    }
                }

                // Pre-fetch all transaksi_stok for candidate POS transactions in 1 single query
                $currentStokTxsByPosId = DB::table('transaksi_stok')
                    ->whereIn('source_type', ['penjualan_pos', 'pos'])
                    ->whereIn('source_id', $allCandidatePosIds)
                    ->where('barang_id', $barangId)
                    ->get()
                    ->groupBy('source_id');

                $candidatePosList = \App\Models\PenjualanPos::whereIn('id', $allCandidatePosIds)
                    ->whereIn('gudang_id', $outletIds)
                    ->whereIn(DB::raw('LOWER(status)'), ['approved', 'disetujui', 'sukses'])
                    ->with(['details.produk'])
                    ->get();

                foreach ($candidatePosList as $tPos) {
                    $expectedQtyNeeded = 0.0;
                    $usesThisIngredient = false;

                    foreach ($tPos->details as $d) {
                        $p = $d->produk;
                        if (!$p) continue;
                        $resepId = $p->resep_id;
                        if (!$resepId && !empty($p->nama)) {
                            $namaKey = strtolower(trim($p->nama));
                            $resepId = $fallbackNamaResepMap[$namaKey] ?? null;
                        }
                        if ($resepId) {
                            $rawBahan = $resepBahanMap->get($resepId);
                            if ($rawBahan) {
                                $usesThisIngredient = true;
                                $outQty = floatval($rawBahan->output_qty) > 0 ? floatval($rawBahan->output_qty) : 1.0;
                                $bQty = floatval($rawBahan->qty_bahan);
                                if ($bKonv > 1 && !empty($rawBahan->satuan) && !empty($bRow->satuan_pembelian)) {
                                    if (strcasecmp(trim($rawBahan->satuan), trim($bRow->satuan_pembelian)) === 0 && strcasecmp(trim($rawBahan->satuan), trim($bRow->satuan)) !== 0) {
                                        $bQty *= $bKonv;
                                    }
                                }
                                $expectedQtyNeeded += ($bQty / $outQty) * floatval($d->qty);
                            } else {
                                $altBahan = $altBahanMap->get($resepId);
                                if ($altBahan) {
                                    $usesThisIngredient = true;
                                    $outQty = floatval($altBahan->output_qty) > 0 ? floatval($altBahan->output_qty) : 1.0;
                                    $bQty = floatval($altBahan->qty_bahan);
                                    if ($bKonv > 1 && !empty($altBahan->satuan) && !empty($bRow->satuan_pembelian)) {
                                        if (strcasecmp(trim($altBahan->satuan), trim($bRow->satuan_pembelian)) === 0 && strcasecmp(trim($altBahan->satuan), trim($bRow->satuan)) !== 0) {
                                            $bQty *= $bKonv;
                                        }
                                    }
                                    $expectedQtyNeeded += ($bQty / $outQty) * floatval($d->qty);
                                }
                            }
                        }
                    }

                    // Ambil mutasi transaksi_stok dari map in-memory
                    $currentStokTxs = $currentStokTxsByPosId->get($tPos->id, collect());
                    $actualDeductedQty = (float) $currentStokTxs->sum('qty');
                    $needsResync = false;

                    if ($usesThisIngredient) {
                        if ($currentStokTxs->isEmpty()) {
                            // Belum tercatat potong sama sekali
                            $needsResync = true;
                        } elseif (abs($actualDeductedQty - $expectedQtyNeeded) > 0.001) {
                            // Kuantitas di resep telah diupdate / berbeda dengan yang terpotong sebelumnya
                            $needsResync = true;
                        }
                    } elseif ($isUsedInBsj && $currentStokTxs->isNotEmpty()) {
                        // Jika bahan mentah (BBB) ini TIDAK digunakan langsung di menu yang dipesan,
                        // tapi pernah terpotong oleh POS karena BSJ-nya dulu sempat diurai di outlet,
                        // set needsResync = true agar pemotongan stok dialihkan ke barang BSJ itu sendiri.
                        $needsResync = true;
                    } elseif (!$usesThisIngredient && $currentStokTxs->isNotEmpty()) {
                        // Resep sudah tidak lagi menggunakan bahan ini, tapi mutasi masih ada
                        $needsResync = true;
                    } elseif ($currentStokTxs->count() > 1) {
                        // Jika mutasi terpecah menjadi lebih dari 1 baris untuk 1 transaksi POS (akibat layer FIFO), satukan kembali
                        $needsResync = true;
                    } elseif ($currentStokTxs->isNotEmpty() && in_array($bSatDasar, ['gr', 'ml', 'gram']) && $bKonv > 1) {
                        // Cek anomali harga satuan (misal harga per kemasan tersimpan sebagai harga per gram)
                        foreach ($currentStokTxs as $ctx) {
                            $unitPrice = $ctx->qty > 0 ? ((float)$ctx->total_harga / (float)$ctx->qty) : 0;
                            if ($unitPrice >= 500) {
                                $needsResync = true;
                                break;
                            }
                        }
                    }

                    if ($needsResync) {
                        $originalStatus = $tPos->status;
                        try {
                            DB::beginTransaction();

                            // 1. Kembalikan sisa batch FIFO yang sebelumnya dikonsumsi oleh AUTO_POS ini
                            $oldPbkIds = DB::table('pengeluaran_bahan_baku')
                                ->where('keterangan', 'like', 'AUTO_POS:' . $tPos->kode_transaksi . '%')
                                ->orWhere('kode_pengeluaran', 'like', 'OUT-' . $tPos->kode_transaksi . '%')
                                ->pluck('id');

                            if ($oldPbkIds->isNotEmpty()) {
                                $oldFifos = DB::table('pengeluaran_bahan_baku_fifo')
                                    ->whereIn('pengeluaran_id', $oldPbkIds)
                                    ->get();

                                foreach ($oldFifos as $of) {
                                    if ($of->batch_id && (float)$of->qty_keluar > 0) {
                                        $b = \App\Models\StokGudangBatch::find($of->batch_id);
                                        if ($b) {
                                            $b->increment('qty_sisa', (float)$of->qty_keluar);
                                            $b->decrement('qty_keluar', min((float)$b->qty_keluar, (float)$of->qty_keluar));
                                            $b->update(['is_habis' => false]);
                                        }
                                    }
                                }

                                DB::table('pengeluaran_bahan_baku_fifo')->whereIn('pengeluaran_id', $oldPbkIds)->delete();
                                DB::table('pengeluaran_bahan_baku_detail')->whereIn('pengeluaran_id', $oldPbkIds)->delete();
                                DB::table('pengeluaran_bahan_baku')->whereIn('id', $oldPbkIds)->delete();
                            }

                            // 2. Hapus mutasi transaksi_stok lama untuk POS ini
                            DB::table('transaksi_stok')
                                ->whereIn('source_type', ['penjualan_pos', 'pos'])
                                ->where('source_id', $tPos->id)
                                ->delete();

                            // 3. Re-approve POS agar memotong bahan sesuai formulasi resep terbaru
                            $tPos->update(['status' => 'Draft']);
                            $appRes = $posController->approve($tPos->id, true);

                            if (is_array($appRes) && isset($appRes['status']) && $appRes['status'] === 'success') {
                                DB::commit();
                            } else {
                                DB::rollBack();
                                $tPos->update(['status' => $originalStatus]);
                                \Illuminate\Support\Facades\Log::warning("Re-approve POS {$tPos->id} not approved: " . json_encode($appRes));
                            }
                        } catch (\Exception $exApprove) {
                            DB::rollBack();
                            $tPos->update(['status' => $originalStatus]);
                            \Illuminate\Support\Facades\Log::warning("Re-approve POS {$tPos->id} for recipe update {$barangId} failed: " . $exApprove->getMessage());
                        }
                    }
                }
            }

            // 3. Pastikan divisi_asal_id dan gudang_asal_id pada transaksi_stok terisi dengan divisi operasional yang benar
            self::autoHealMissingDivisiInTransaksiStok($barangId);

            // 4. Sinkronkan seluruh draft Stock Opname yang memuat bahan ini agar stok sistemnya langsung ter-update
            if ($barangId) {
                $openDraftSos = \App\Models\StockOpname::whereIn(DB::raw('LOWER(status)'), ['draft', 'menunggu_persetujuan'])
                    ->whereHas('details', function($q) use ($barangId) {
                        $q->where('barang_id', $barangId);
                    })
                    ->get();
                foreach ($openDraftSos as $draftSo) {
                    \App\Http\Controllers\StockOpnameController::syncDraftOpname($draftSo);
                }
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealApprovedPosStockMutations error: ' . $e->getMessage());
        }
    }

    /**
     * Bersihkan duplikat resep_bahanbaku untuk menjaga keakuratan pemotongan bahan baku POS.
     */
    public static function autoCleanDuplicateResepBahanBaku($barangId = null)
    {
        try {
            $q = DB::table('resep_bahanbaku')
                ->select('resep_id', 'bahan_id', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('resep_id', 'bahan_id')
                ->having('cnt', '>', 1);
            if ($barangId) {
                $q->where('bahan_id', $barangId);
            }
            $duplicates = $q->get();

            foreach ($duplicates as $dupe) {
                DB::table('resep_bahanbaku')
                    ->where('resep_id', $dupe->resep_id)
                    ->where('bahan_id', $dupe->bahan_id)
                    ->where('id', '!=', $dupe->keep_id)
                    ->delete();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoCleanDuplicateResepBahanBaku error: ' . $e->getMessage());
        }
    }

    /**
     * Pastikan mutasi stock opname tercatat pada penutupan hari (23:59:00) agar seluruh transaksi operasional
     * harian (POS, PBK) tercatat terlebih dahulu sebelum penyesuaian fisik opname.
     */
    public static function autoHealStockOpnameTimestamp($barangId = null)
    {
        try {
            $q = DB::table('transaksi_stok')
                ->where('source_type', 'stock_opname')
                ->whereRaw("TIME(tanggal) = '10:29:00'");
            if ($barangId) {
                $q->where('barang_id', $barangId);
            }
            $q->update([
                'tanggal' => DB::raw("CONCAT(DATE(tanggal), ' 23:59:00')")
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealStockOpnameTimestamp error: ' . $e->getMessage());
        }
    }

    /**
     * Hapus / Bersihkan semua transaksi pembelian untuk 1 item barang yang dipilih.
     * Mengembalikan / membersihkan stok, batch pembelian, penerimaan, dan jurnal terkait.
     */
    public function resetPembelianBarang(Request $request)
    {
        $barangId = $request->barang_id;
        $barang = MasterBarang::withoutGlobalScopes()->findOrFail($barangId);

        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $isGudang = $user && $user->isGudang();

        if (!$isSuperAdmin && !$isGudang) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menghapus transaksi barang ini.'
            ], 403);
        }

        try {
            $deletedCount = 0;

            DB::transaction(function () use ($barangId, &$deletedCount) {
                // 1. Ambil seluruh pembelian detail untuk barang ini
                $pembelianDetails = \App\Models\PembelianDetail::where('barang_id', $barangId)->get();

                foreach ($pembelianDetails as $detail) {
                    $pembelian = \App\Models\Pembelian::find($detail->pembelian_id);

                    // 1a. Hapus batches terkait detail ini
                    $batches = \App\Models\StokGudangBatch::where('pembelian_detail_id', $detail->id)->get();
                    if ($batches->isEmpty()) {
                        $batches = \App\Models\StokGudangBatch::where('pembelian_id', $detail->pembelian_id)
                            ->where('barang_id', $barangId)
                            ->get();
                    }

                    foreach ($batches as $batch) {
                        if ($batch->qty_masuk > 0) {
                            $stokGudang = \App\Models\StokGudang::where('barang_id', $batch->barang_id)
                                ->where('gudang_id', $batch->gudang_id)
                                ->when($batch->divisi_id, fn($q) => $q->where('divisi_id', $batch->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                ->lockForUpdate()
                                ->first();

                            if ($stokGudang) {
                                $stokGudang->decrement('jumlah', (float) $batch->qty_masuk);
                            }
                        }
                        $batch->delete();
                    }

                    // 1b. Hapus riwayat penerimaan pembelian untuk detail ini
                    $rcvDetails = \App\Models\PenerimaanPembelianDetail::where('pembelian_detail_id', $detail->id)->get();
                    foreach ($rcvDetails as $rcvD) {
                        $headerId = $rcvD->penerimaan_pembelian_id;
                        $rcvD->delete();

                        // Jika header penerimaan sudah tidak punya detail lagi, hapus headernya
                        if (\App\Models\PenerimaanPembelianDetail::where('penerimaan_pembelian_id', $headerId)->count() === 0) {
                            \App\Models\PenerimaanPembelian::where('id', $headerId)->delete();
                        }
                    }

                    // 1c. Hapus transaksi stok pembelian & pembelian_batal terkait
                    \App\Models\TransaksiStok::where('barang_id', $barangId)
                        ->where('source_id', $detail->pembelian_id)
                        ->whereIn('source_type', ['pembelian', 'pembelian_batal'])
                        ->delete();

                    // 1d. Hapus Jurnal Akuntansi terkait jika pembelian hanya berisi barang ini
                    if ($pembelian) {
                        $otherDetailsCount = \App\Models\PembelianDetail::where('pembelian_id', $pembelian->id)
                            ->where('id', '!=', $detail->id)
                            ->count();

                        if ($otherDetailsCount === 0) {
                            // Hapus jurnal
                            $jurnalList = DB::table('jurnal_pembelian')
                                ->where('source_type', 'pembelian')
                                ->where('source_id', $pembelian->id)
                                ->get();

                            foreach ($jurnalList as $jp) {
                                DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_pembelian')->delete();
                                DB::table('jurnal_pembelian')->where('id', $jp->id)->delete();
                            }

                            // Hapus pembayaran
                            \App\Models\Pembayaran::where('pembelian_id', $pembelian->id)->delete();

                            // Hapus header pembelian
                            $pembelian->delete();
                        } else {
                            // Update total pembelian
                            $subtotalDetail = (float)$detail->qty * (float)$detail->harga_per_qty;
                            $pembelian->decrement('total', $subtotalDetail);
                        }
                    }

                    // Hapus pembelian_detail
                    $detail->delete();
                    $deletedCount++;
                }

                // 2. Bersihkan sisa TransaksiStok yatim (misal ID pembelian sudah terhapus) untuk barang ini
                \App\Models\TransaksiStok::where('barang_id', $barangId)
                    ->whereIn('source_type', ['pembelian', 'pembelian_batal'])
                    ->delete();

                // 3. Rekonsiliasi ringkasan stok gudang
                \App\Models\StokGudang::reconcileStockSummary($barangId);

                // 4. Sinkronisasi HPP FIFO
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$barangId);
            });

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus seluruh transaksi pembelian untuk item '{$barang->nama}'. Stok dan HPP telah disesuaikan ulang."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pembelian: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus / Bersihkan semua transaksi pengeluaran/permintaan transfer bahan untuk 1 item barang yang dipilih.
     * Mengembalikan alokasi stok ke gudang asal dan membatalkan mutasi stok terkait.
     */
    public function resetPermintaanBarang(Request $request)
    {
        $barangId = $request->barang_id;
        $barang = MasterBarang::withoutGlobalScopes()->findOrFail($barangId);

        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $isGudang = $user && $user->isGudang();

        if (!$isSuperAdmin && !$isGudang) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menghapus permintaan barang ini.'
            ], 403);
        }

        try {
            $deletedCount = 0;

            DB::transaction(function () use ($barangId, &$deletedCount) {
                // Ambil semua detail pengeluaran/permintaan untuk barang ini
                $details = \App\Models\PengeluaranBahanBakuDetail::where('barang_id', $barangId)->get();

                $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
                $gudangAsalId = $gudangUtama ? $gudangUtama->id : 2;

                foreach ($details as $detail) {
                    $pengeluaran = \App\Models\PengeluaranBahanBaku::find($detail->pengeluaran_id);
                    $isApproved = $pengeluaran && in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']);

                    if ($isApproved) {
                        // Rollback alokasi stok gudang asal
                        $isOpnameOrWasted = str_starts_with($pengeluaran->kode_pengeluaran ?? '', 'PBK-SO-') 
                            || $pengeluaran->jenis_pengeluaran === 'wasted' 
                            || str_starts_with($pengeluaran->kode_pengeluaran ?? '', 'PBK-WST-');

                        $asalId = $isOpnameOrWasted ? $pengeluaran->gudang_id : $gudangAsalId;
                        $asalDivisiId = $isOpnameOrWasted ? $pengeluaran->divisi_id : null;

                        $stokAsal = \App\Models\StokGudang::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $asalId)
                            ->when($asalDivisiId, fn($q) => $q->where('divisi_id', $asalDivisiId), fn($q) => $q->whereNull('divisi_id'))
                            ->lockForUpdate()
                            ->first();

                        if ($stokAsal) {
                            $stokAsal->increment('jumlah', (float)$detail->qty);
                        }

                        // Rollback batch FIFO
                        $fifoRecords = \App\Models\PengeluaranBahanBakuFifo::where('pengeluaran_id', $pengeluaran->id)
                            ->where('detail_id', $detail->id)
                            ->get();

                        foreach ($fifoRecords as $fifo) {
                            $batch = \App\Models\StokGudangBatch::find($fifo->batch_id);
                            if ($batch) {
                                $batch->qty_keluar = max(0, $batch->qty_keluar - $fifo->qty_keluar);
                                $batch->qty_sisa   += $fifo->qty_keluar;
                                $batch->is_habis   = false;
                                $batch->save();
                            }
                            $fifo->delete();
                        }

                        // Rollback di tujuan jika transfer
                        if (!$isOpnameOrWasted && $pengeluaran->gudang_id) {
                            $stokTujuan = \App\Models\StokGudang::where('barang_id', $detail->barang_id)
                                ->where('gudang_id', $pengeluaran->gudang_id)
                                ->when($pengeluaran->divisi_id, fn($q) => $q->where('divisi_id', $pengeluaran->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                ->lockForUpdate()
                                ->first();

                            if ($stokTujuan) {
                                $stokTujuan->decrement('jumlah', min((float)$stokTujuan->jumlah, (float)$detail->qty));
                            }

                            // Hapus batch mutasi di tujuan
                            \App\Models\StokGudangBatch::where('barang_id', $detail->barang_id)
                                ->where('gudang_id', $pengeluaran->gudang_id)
                                ->where('batch_number', 'like', '%-MUT')
                                ->delete();
                        }

                        // Hapus TransaksiStok terkait
                        \App\Models\TransaksiStok::where('source_id', $pengeluaran->id)
                            ->where('barang_id', $detail->barang_id)
                            ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                            ->delete();
                    }

                    // Hapus detail
                    $pId = $detail->pengeluaran_id;
                    $detail->delete();
                    $deletedCount++;

                    // Jika pengeluaran sudah kosong, hapus dokumen pengeluarannya
                    if ($pId && \App\Models\PengeluaranBahanBakuDetail::where('pengeluaran_id', $pId)->count() === 0) {
                        \App\Models\PengeluaranBahanBaku::where('id', $pId)->delete();
                    }
                }

                // Bersihkan TransaksiStok yatim
                \App\Models\TransaksiStok::where('barang_id', $barangId)
                    ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                    ->delete();

                // Rekonsiliasi ringkasan stok gudang
                \App\Models\StokGudang::reconcileStockSummary($barangId);

                // Sinkronisasi HPP FIFO
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$barangId);
            });

            return response()->json([
                'success' => true,
                'message' => "Berhasil membatalkan/menghapus seluruh permintaan/pengeluaran untuk item '{$barang->nama}'. Stok dan HPP telah disesuaikan ulang."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus permintaan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bersihkan secara otomatis seluruh data transaksi yatim (orphan) di tabel transaksi_stok
    /**
     * Bersihkan secara otomatis seluruh data transaksi yatim (orphan) di tabel transaksi_stok
     * yang dokumen induknya (Pembelian, Pengeluaran, Penerimaan) sudah dihapus atau tidak valid.
     */
    public static function autoCleanOrphanMutations($barangId = null)
    {
        try {
            $hasIsDeletedCol = \Illuminate\Support\Facades\Schema::hasColumn('pembelian', 'is_deleted');

            // 1. Orphan Pembelian / Pembelian Batal (source_id tidak ada di tabel pembelian, dibatalkan/dihapus, ATAU detail item sudah dihapus)
            $orphanPembelianQuery = DB::table('transaksi_stok')
                ->whereIn('source_type', ['pembelian', 'pembelian_batal', 'pembelian_kejingga'])
                ->whereNotNull('source_id')
                ->where(function($q) use ($hasIsDeletedCol) {
                    $q->whereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('pembelian')
                          ->whereColumn('pembelian.id', 'transaksi_stok.source_id');
                    })->orWhereExists(function($sub) use ($hasIsDeletedCol) {
                        $sub->select(DB::raw(1))
                          ->from('pembelian')
                          ->whereColumn('pembelian.id', 'transaksi_stok.source_id')
                          ->where(function($batalSub) use ($hasIsDeletedCol) {
                              $batalSub->where('catatan_pembayaran', 'like', '[DELETED]%')
                                       ->orWhere('catatan_pembayaran', 'like', '[BATAL]%')
                                       ->orWhere('keterangan', 'like', '[BATAL]%');
                              if ($hasIsDeletedCol) {
                                  $batalSub->orWhere('is_deleted', true);
                              }
                          });
                    })->orWhereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('pembelian_detail')
                          ->whereColumn('pembelian_detail.pembelian_id', 'transaksi_stok.source_id')
                          ->whereColumn('pembelian_detail.barang_id', 'transaksi_stok.barang_id');
                    });
                });
            if ($barangId) {
                $orphanPembelianQuery->where('barang_id', $barangId);
            }
            $orphanPembelianQuery->delete();

            // 2. Orphan Penerimaan Pembelian (source_id tidak ada di tabel penerimaan_pembelian ATAU detail item sudah tidak ada)
            $orphanRcvQuery = DB::table('transaksi_stok')
                ->where('source_type', 'penerimaan_pembelian')
                ->whereNotNull('source_id')
                ->where(function($q) {
                    $q->whereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('penerimaan_pembelian')
                          ->whereColumn('penerimaan_pembelian.id', 'transaksi_stok.source_id');
                    })->orWhereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('penerimaan_pembelian_detail')
                          ->whereColumn('penerimaan_pembelian_detail.penerimaan_pembelian_id', 'transaksi_stok.source_id')
                          ->whereColumn('penerimaan_pembelian_detail.barang_id', 'transaksi_stok.barang_id');
                    });
                });
            if ($barangId) {
                $orphanRcvQuery->where('barang_id', $barangId);
            }
            $orphanRcvQuery->delete();

            // 3. Orphan Pengeluaran Bahan Baku / Wasted:
            // - Header tidak ada, ATAU
            // - Status bukan 'approved' / 'disetujui' (misal masih draft, batal, ditolak), ATAU
            // - Detail item barang_id sudah dihapus dari dokumen pengeluaran
            $orphanPbkQuery = DB::table('transaksi_stok')
                ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                ->whereNotNull('source_id')
                ->where(function($q) {
                    $q->whereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('pengeluaran_bahan_baku')
                          ->whereColumn('pengeluaran_bahan_baku.id', 'transaksi_stok.source_id');
                    })->orWhereExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('pengeluaran_bahan_baku')
                          ->whereColumn('pengeluaran_bahan_baku.id', 'transaksi_stok.source_id')
                          ->whereNotIn(DB::raw('LOWER(pengeluaran_bahan_baku.status)'), ['approved', 'disetujui']);
                    })->orWhereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('pengeluaran_bahan_baku_detail')
                          ->whereColumn('pengeluaran_bahan_baku_detail.pengeluaran_id', 'transaksi_stok.source_id')
                          ->whereColumn('pengeluaran_bahan_baku_detail.barang_id', 'transaksi_stok.barang_id');
                    });
                });
            if ($barangId) {
                $orphanPbkQuery->where('barang_id', $barangId);
            }
            $orphanPbkQuery->delete();

            // 3b. Orphan Stock Opname:
            // - Header tidak ada, atau status bukan 'approved', atau detail barang_id tidak ada
            $orphanSoQuery = DB::table('transaksi_stok')
                ->where('source_type', 'stock_opname')
                ->whereNotNull('source_id')
                ->where(function($q) {
                    $q->whereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('stock_opname')
                          ->whereColumn('stock_opname.id', 'transaksi_stok.source_id');
                    })->orWhereExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('stock_opname')
                          ->whereColumn('stock_opname.id', 'transaksi_stok.source_id')
                          ->whereNotIn(DB::raw('LOWER(stock_opname.status)'), ['approved', 'disetujui']);
                    })->orWhereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('stock_opname_detail')
                          ->whereColumn('stock_opname_detail.stock_opname_id', 'transaksi_stok.source_id')
                          ->whereColumn('stock_opname_detail.barang_id', 'transaksi_stok.barang_id');
                    });
                });
            if ($barangId) {
                $orphanSoQuery->where('barang_id', $barangId);
            }
            $orphanSoQuery->delete();

            // 4. Orphan Stok Gudang Batch yang pembelian_id-nya sudah tidak ada di tabel pembelian atau pembelian dibatalkan/dihapus
            $orphanBatchQuery = DB::table('stok_gudang_batch')
                ->whereNotNull('pembelian_id')
                ->where(function($q) use ($hasIsDeletedCol) {
                    $q->whereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('pembelian')
                          ->whereColumn('pembelian.id', 'stok_gudang_batch.pembelian_id');
                    })->orWhereExists(function($sub) use ($hasIsDeletedCol) {
                        $sub->select(DB::raw(1))
                          ->from('pembelian')
                          ->whereColumn('pembelian.id', 'stok_gudang_batch.pembelian_id')
                          ->where(function($batalSub) use ($hasIsDeletedCol) {
                              $batalSub->where('catatan_pembayaran', 'like', '[DELETED]%')
                                       ->orWhere('catatan_pembayaran', 'like', '[BATAL]%')
                                       ->orWhere('keterangan', 'like', '[BATAL]%');
                              if ($hasIsDeletedCol) {
                                  $batalSub->orWhere('is_deleted', true);
                              }
                          });
                    });
                });
            if ($barangId) {
                $orphanBatchQuery->where('barang_id', $barangId);
            }
            $orphanBatchQuery->delete();

            // 4b. Orphan Penjualan POS (transaksi_stok yang transaksi POS induknya sudah dihapus dari menu Penjualan POS)
            $orphanPosQuery = DB::table('transaksi_stok')
                ->where('source_type', 'penjualan_pos')
                ->whereNotNull('source_id')
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('penjualan_pos')
                      ->whereColumn('penjualan_pos.id', 'transaksi_stok.source_id');
                })
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('pengeluaran_bahan_baku')
                      ->whereColumn('pengeluaran_bahan_baku.id', 'transaksi_stok.source_id');
                });
            if ($barangId) {
                $orphanPosQuery->where('barang_id', $barangId);
            }
            $orphanPosQuery->delete();

            // 5. Auto-heal transaksi POS yang salah gudang (masuk ke Gudang Utama / bukan gudang outlet)
            $gudangUtamaId = \App\Models\MasterGudang::getGudangUtamaId();
            $outletIds = \App\Models\MasterGudang::getOutletGudangIds();
            $gudangGaharuId = \App\Models\MasterGudang::resolveOutletId('gaharu');
            $gudangKejinggaId = \App\Models\MasterGudang::resolveOutletId('kejingga');

            // 5a. Pastikan seluruh header PenjualanPos tersimpan di gudang outlet
            $misplacedPosHeaders = \App\Models\PenjualanPos::whereNotIn('gudang_id', $outletIds)->get();
            foreach ($misplacedPosHeaders as $posHeader) {
                $targetOutletId = self::resolveOutletForPos($posHeader);
                $posHeader->update(['gudang_id' => $targetOutletId]);
            }

            // 5b. Kembalikan pengeluaran bahan baku AUTO_POS yang salah masuk ke Gudang Utama
            $misplacedPosOutputs = \App\Models\PengeluaranBahanBaku::where('keterangan', 'like', 'AUTO_POS%')
                ->where('gudang_id', $gudangUtamaId)
                ->with(['details'])
                ->get();

            foreach ($misplacedPosOutputs as $pbk) {
                $targetGudangId = \App\Models\MasterGudang::resolveOutletId($pbk->keterangan ?? $pbk->kode_pengeluaran);

                foreach ($pbk->details as $d) {
                    $qty = (float) $d->qty;
                    $bId = $d->barang_id;

                    // Kembalikan stok Gudang Utama
                    $stokUtama = \App\Models\StokGudang::where('gudang_id', $gudangUtamaId)->where('barang_id', $bId)->first();
                    if ($stokUtama) {
                        $stokUtama->increment('jumlah', $qty);
                    }

                    // Kembalikan sisa batch FIFO di Gudang Utama
                    $fifoRecords = DB::table('pengeluaran_bahan_baku_fifo')
                        ->where('pengeluaran_id', $pbk->id)
                        ->where('detail_id', $d->id)
                        ->get();

                    foreach ($fifoRecords as $fr) {
                        if ($fr->batch_id) {
                            $batch = \App\Models\StokGudangBatch::find($fr->batch_id);
                            if ($batch && $batch->gudang_id == $gudangUtamaId) {
                                $batch->increment('qty_sisa', $fr->qty_keluar);
                                $batch->decrement('qty_keluar', $fr->qty_keluar);
                                $batch->update(['is_habis' => false]);
                            }
                        }
                    }

                    // Kurangkan stok di Gudang Outlet yang sebenarnya
                    $stokOutlet = \App\Models\StokGudang::firstOrCreate(
                        ['gudang_id' => $targetGudangId, 'barang_id' => $bId],
                        ['jumlah' => 0]
                    );
                    $stokOutlet->decrement('jumlah', $qty);
                }

                $pbk->update(['gudang_id' => $targetGudangId]);
            }

            // 5c. Perbaiki mutasi TransaksiStok POS dan PBK-POS yang tercatat memotong Gudang Utama
            $misplacedPosTxQuery = \App\Models\TransaksiStok::where('gudang_asal_id', $gudangUtamaId)
                ->where(function($q) {
                    $q->where('source_type', 'penjualan_pos')
                      ->orWhere(function($sub) {
                          $sub->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                              ->whereExists(function($pSub) {
                                  $pSub->select(DB::raw(1))
                                       ->from('pengeluaran_bahan_baku')
                                       ->whereColumn('pengeluaran_bahan_baku.id', 'transaksi_stok.source_id')
                                       ->where(function($ketQ) {
                                           $ketQ->where('keterangan', 'like', 'AUTO_POS%')
                                                ->orWhere('kode_pengeluaran', 'like', 'PBK-POS%')
                                                ->orWhere('kode_pengeluaran', 'like', '%POS%');
                                       });
                              });
                      });
                });
            if ($barangId) {
                $misplacedPosTxQuery->where('barang_id', $barangId);
            }
            $misplacedPosTxs = $misplacedPosTxQuery->get();

            foreach ($misplacedPosTxs as $tx) {
                $pos = \App\Models\PenjualanPos::find($tx->source_id);
                if ($pos) {
                    $targetOutletId = self::resolveOutletForPos($pos);
                } else {
                    $pbk = \App\Models\PengeluaranBahanBaku::find($tx->source_id);
                    if ($pbk) {
                        $targetOutletId = ($pbk->gudang_id && in_array($pbk->gudang_id, $outletIds))
                            ? $pbk->gudang_id
                            : \App\Models\MasterGudang::resolveOutletId($pbk->keterangan ?? $pbk->kode_pengeluaran);
                    } else {
                        $targetOutletId = $gudangGaharuId;
                    }
                }

                $alreadyAtOutlet = \App\Models\TransaksiStok::whereIn('source_type', ['penjualan_pos', 'pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                    ->where('source_id', $tx->source_id)
                    ->where('barang_id', $tx->barang_id)
                    ->where('gudang_asal_id', $targetOutletId)
                    ->exists();

                if ($alreadyAtOutlet) {
                    $tx->delete();
                } else {
                    $tx->update(['gudang_asal_id' => $targetOutletId]);
                }
            }

            // 5d. Kembalikan sisa batch FIFO di Gudang Utama jika transaksi pengeluaran FIFO sudah tidak ada
            $batchesUtama = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangUtamaId);
            if ($barangId) {
                $batchesUtama->where('barang_id', $barangId);
            }
            $batchesUtamaList = $batchesUtama->get();

            foreach ($batchesUtamaList as $b) {
                $actualOut = (float) DB::table('pengeluaran_bahan_baku_fifo')
                    ->where('batch_id', $b->id)
                    ->sum('qty_keluar');

                if ($b->qty_keluar > $actualOut) {
                    $newSisa = max(0, (float)$b->qty_masuk - $actualOut);
                    DB::table('stok_gudang_batch')->where('id', $b->id)->update([
                        'qty_keluar' => $actualOut,
                        'qty_sisa'   => $newSisa,
                        'is_habis'   => ($newSisa <= 0),
                    ]);
                }
            }

            // 6. Duplicate & Orphan Saldo Awal (Persediaan Awal)
            // 6a. Hapus transaksi_stok saldo awal yang dokumen persediaan_awal nya sudah tidak ada, masih berstatus draft, ATAU detail itemnya sudah dihapus
            $orphanSaldoAwalQuery = DB::table('transaksi_stok')
                ->whereIn('source_type', ['saldo_awal', 'persediaan_awal'])
                ->whereNotNull('source_id')
                ->where(function($q) {
                    $q->whereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('persediaan_awal')
                          ->whereColumn('persediaan_awal.id', 'transaksi_stok.source_id');
                    })->orWhereExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('persediaan_awal')
                          ->whereColumn('persediaan_awal.id', 'transaksi_stok.source_id')
                          ->whereNotIn(DB::raw('LOWER(persediaan_awal.status)'), ['approved', 'posted']);
                    })->orWhereNotExists(function($sub) {
                        $sub->select(DB::raw(1))
                          ->from('persediaan_awal_detail')
                          ->whereColumn('persediaan_awal_detail.persediaan_awal_id', 'transaksi_stok.source_id')
                          ->whereColumn('persediaan_awal_detail.barang_id', 'transaksi_stok.barang_id');
                    });
                });
            if ($barangId) {
                $orphanSaldoAwalQuery->where('barang_id', $barangId);
            }
            $orphanSaldoAwalQuery->delete();

            // 6b. Bersihkan duplikasi transaksi_stok saldo_awal untuk pasangan (source_id, barang_id, gudang_tujuan_id) yang sama
            $dupSaldoAwalQuery = DB::table('transaksi_stok')
                ->whereIn('source_type', ['saldo_awal', 'persediaan_awal'])
                ->whereNotNull('source_id');
            if ($barangId) {
                $dupSaldoAwalQuery->where('barang_id', $barangId);
            }

            $duplicateGroups = $dupSaldoAwalQuery
                ->select(
                    'source_id',
                    'barang_id',
                    'gudang_tujuan_id',
                    DB::raw('COALESCE(divisi_tujuan_id, 0) as div_id'),
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('MAX(id) as keep_id')
                )
                ->groupBy('source_id', 'barang_id', 'gudang_tujuan_id', DB::raw('COALESCE(divisi_tujuan_id, 0)'))
                ->having('total_count', '>', 1)
                ->get();

            foreach ($duplicateGroups as $group) {
                $deleteQuery = DB::table('transaksi_stok')
                    ->whereIn('source_type', ['saldo_awal', 'persediaan_awal'])
                    ->where('source_id', $group->source_id)
                    ->where('barang_id', $group->barang_id)
                    ->where('gudang_tujuan_id', $group->gudang_tujuan_id)
                    ->where('id', '!=', $group->keep_id);

                if ($group->div_id == 0) {
                    $deleteQuery->whereNull('divisi_tujuan_id');
                } else {
                    $deleteQuery->where('divisi_tujuan_id', $group->div_id);
                }

                $deleteQuery->delete();
            }

            // 7. Non-CK Incoming mutations for Barang Setengah Jadi (BSJ):
            // Barang berstatus BSJ di divisi Kitchen hanya boleh masuk melalui produksi Central Kitchen (transfer_ck / produksi_ck)
            // Mutasi masuk dari pengeluaran_bahan_baku / manual transfer ke gudang outlet dihapus/dibersihkan
            $bsjItemIds = DB::table('master_barang')
                ->where('is_bahan_setengah_jadi', 1)
                ->when($barangId, fn($q) => $q->where('id', $barangId))
                ->pluck('id')
                ->toArray();

            if (!empty($bsjItemIds)) {
                $invalidBsjMasukQuery = DB::table('transaksi_stok')
                    ->whereIn('barang_id', $bsjItemIds)
                    ->where('tipe', 'masuk')
                    ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted']);

                $invalidTxs = $invalidBsjMasukQuery->get();
                foreach ($invalidTxs as $invTx) {
                    // Hapus juga transaksi keluar pasangannya jika ada (misal dari gudang bahan baku yang jadi minus)
                    if ($invTx->source_id) {
                        DB::table('transaksi_stok')
                            ->where('source_type', $invTx->source_type)
                            ->where('source_id', $invTx->source_id)
                            ->where('barang_id', $invTx->barang_id)
                            ->where('tipe', 'keluar')
                            ->delete();
                    }
                    DB::table('transaksi_stok')->where('id', $invTx->id)->delete();
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoCleanOrphanMutations error: ' . $e->getMessage());
        }
    }

    /**
     * Hapus 1 baris transaksi mutasi secara permanen langsung dari modal Buku Pembantu Persediaan.
     */
    public function deleteMutasiRow(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $isGudang = $user && $user->isGudang();

        if (!$isSuperAdmin && !$isGudang) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menghapus mutasi transaksi ini.'
            ], 403);
        }

        $mutasiId = $request->mutasi_id;
        $barangId = $request->barang_id;

        $tx = \App\Models\TransaksiStok::find($mutasiId);
        if (!$tx) {
            if ($barangId) {
                self::autoCleanOrphanMutations($barangId);
                \App\Models\StokGudang::reconcileStockSummary($barangId);
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$barangId);
            }
            return response()->json([
                'success' => true,
                'message' => 'Transaksi sudah tidak ada atau telah dibersihkan.'
            ]);
        }

        $targetBarangId = $tx->barang_id ?: $barangId;
        $sourceType = strtolower($tx->source_type ?? '');
        $sourceId = $tx->source_id;

        try {
            DB::transaction(function () use ($tx, $targetBarangId, $sourceType, $sourceId) {
                // Skenario 1: Pembelian / Penerimaan Pembelian / Pembelian Kejingga
                if (in_array($sourceType, ['pembelian', 'pembelian_batal', 'penerimaan_pembelian', 'pembelian_kejingga'])) {
                    $pembelianId = $sourceId;
                    if ($sourceType === 'penerimaan_pembelian') {
                        $rcv = \App\Models\PenerimaanPembelian::find($sourceId);
                        if ($rcv) {
                            $pembelianId = $rcv->pembelian_id;
                        }
                    }

                    $pembelian = $pembelianId ? \App\Models\Pembelian::find($pembelianId) : null;

                    if ($pembelian) {
                        $details = \App\Models\PembelianDetail::where('pembelian_id', $pembelian->id)->get();
                        $targetDetails = $details->where('barang_id', $targetBarangId);

                        // Hapus batches & kurangi stok gudang
                        $batches = \App\Models\StokGudangBatch::where('pembelian_id', $pembelian->id)
                            ->where('barang_id', $targetBarangId)
                            ->get();

                        foreach ($batches as $b) {
                            if ($b->qty_masuk > 0) {
                                $stokGudang = \App\Models\StokGudang::where('barang_id', $b->barang_id)
                                    ->where('gudang_id', $b->gudang_id)
                                    ->when($b->divisi_id, fn($q) => $q->where('divisi_id', $b->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                    ->lockForUpdate()
                                    ->first();
                                if ($stokGudang) {
                                    $stokGudang->decrement('jumlah', (float) $b->qty_masuk);
                                }
                            }
                            $b->delete();
                        }

                        // Hapus penerimaan detail terkait
                        foreach ($targetDetails as $td) {
                            $rcvDetails = \App\Models\PenerimaanPembelianDetail::where('pembelian_detail_id', $td->id)->get();
                            foreach ($rcvDetails as $rcvD) {
                                $headerId = $rcvD->penerimaan_pembelian_id;
                                $rcvD->delete();
                                if (\App\Models\PenerimaanPembelianDetail::where('penerimaan_pembelian_id', $headerId)->count() === 0) {
                                    \App\Models\PenerimaanPembelian::where('id', $headerId)->delete();
                                }
                            }
                        }

                        // Hapus transaksi stok pembelian, pembelian_batal, & pembelian_kejingga terkait
                        \App\Models\TransaksiStok::where('barang_id', $targetBarangId)
                            ->where('source_id', $pembelian->id)
                            ->whereIn('source_type', ['pembelian', 'pembelian_batal', 'pembelian_kejingga'])
                            ->delete();

                        // Hapus jurnal & pembayaran terkait pembelian ini
                        $jurnalList = DB::table('jurnal_pembelian')
                            ->where('source_type', 'pembelian')
                            ->where('source_id', $pembelian->id)
                            ->get();
                        foreach ($jurnalList as $jp) {
                            DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_pembelian')->delete();
                            DB::table('jurnal_pembelian')->where('id', $jp->id)->delete();
                        }
                        \App\Models\Pembayaran::where('pembelian_id', $pembelian->id)->delete();

                        $deletedNote = '[DELETED] Dihapus pada ' . now()->format('d/m/Y H:i') . ' melalui Buku Pembantu Persediaan oleh ' . (auth()->user()->nama ?? auth()->user()->name ?? 'Pengguna');
                        $updateData = [
                            'catatan_pembayaran' => $deletedNote,
                        ];

                        if (\Illuminate\Support\Facades\Schema::hasColumn('pembelian', 'is_deleted')) {
                            $updateData['is_deleted']   = true;
                            $updateData['deleted_at']   = now();
                            $updateData['deleted_by']   = auth()->id();
                            $updateData['alasan_batal'] = 'Dihapus melalui Buku Pembantu Persediaan';
                        }

                        $pembelian->update($updateData);
                    } else {
                        // Orphan row pembelian
                        if ($pembelianId) {
                            \App\Models\TransaksiStok::where('source_id', $pembelianId)
                                ->whereIn('source_type', ['pembelian', 'pembelian_batal'])
                                ->delete();
                            \App\Models\StokGudangBatch::where('pembelian_id', $pembelianId)->delete();
                        }
                    }
                }
                // Skenario 2: Pengeluaran Bahan Baku / Wasted
                elseif (in_array($sourceType, ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])) {
                    $pbk = $sourceId ? \App\Models\PengeluaranBahanBaku::with('details')->find($sourceId) : null;
                    if ($pbk) {
                        $isApproved = in_array(strtolower($pbk->status), ['approved', 'disetujui']);
                        $isOpnameOrWasted = str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-SO-') 
                            || $pbk->jenis_pengeluaran === 'wasted' 
                            || str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-WST-');
                        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
                        $gudangAsalId = $gudangUtama ? $gudangUtama->id : 2;

                        $targetDetails = $pbk->details->where('barang_id', $targetBarangId);

                        foreach ($targetDetails as $detail) {
                            if ($isApproved) {
                                $asalId = $isOpnameOrWasted ? $pbk->gudang_id : $gudangAsalId;
                                $asalDivisiId = $isOpnameOrWasted ? $pbk->divisi_id : null;

                                $stokAsal = \App\Models\StokGudang::where('barang_id', $detail->barang_id)
                                    ->where('gudang_id', $asalId)
                                    ->when($asalDivisiId, fn($q) => $q->where('divisi_id', $asalDivisiId), fn($q) => $q->whereNull('divisi_id'))
                                    ->lockForUpdate()
                                    ->first();
                                if ($stokAsal) {
                                    $stokAsal->increment('jumlah', (float)$detail->qty);
                                }

                                $fifoRecords = \App\Models\PengeluaranBahanBakuFifo::where('pengeluaran_id', $pbk->id)
                                    ->where('detail_id', $detail->id)
                                    ->get();
                                foreach ($fifoRecords as $fifo) {
                                    $batch = \App\Models\StokGudangBatch::find($fifo->batch_id);
                                    if ($batch) {
                                        $batch->qty_keluar = max(0, $batch->qty_keluar - $fifo->qty_keluar);
                                        $batch->qty_sisa   += $fifo->qty_keluar;
                                        $batch->is_habis   = false;
                                        $batch->save();
                                    }
                                    $fifo->delete();
                                }

                                if (!$isOpnameOrWasted && $pbk->gudang_id) {
                                    $stokTujuan = \App\Models\StokGudang::where('barang_id', $detail->barang_id)
                                        ->where('gudang_id', $pbk->gudang_id)
                                        ->when($pbk->divisi_id, fn($q) => $q->where('divisi_id', $pbk->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                        ->lockForUpdate()
                                        ->first();
                                    if ($stokTujuan) {
                                        $stokTujuan->decrement('jumlah', min((float)$stokTujuan->jumlah, (float)$detail->qty));
                                    }
                                }

                                \App\Models\TransaksiStok::where('source_id', $pbk->id)
                                    ->where('barang_id', $detail->barang_id)
                                    ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                                    ->delete();
                            }
                            $detail->delete();
                        }

                        if ($pbk->details()->count() === 0) {
                            $jps = DB::table('jurnal_penyesuaian')->where('source_type', 'pengeluaran_bahan_baku')->where('source_id', $pbk->id)->pluck('id');
                            if ($jps->isNotEmpty()) {
                                DB::table('journal_items')->whereIn('journal_id', $jps)->where('journal_type', 'jurnal_penyesuaian')->delete();
                                DB::table('jurnal_penyesuaian')->whereIn('id', $jps)->delete();
                            }
                            $pbk->delete();
                        }
                    } else {
                        if ($sourceId) {
                            \App\Models\TransaksiStok::where('source_id', $sourceId)
                                ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                                ->delete();
                        }
                    }
                }
                // Skenario 3: Penjualan POS
                elseif ($sourceType === 'penjualan_pos') {
                    $pos = \App\Models\PenjualanPos::find($sourceId);
                    if ($pos) {
                        $outletId = $pos->gudang_id;
                        $stokOutlet = \App\Models\StokGudang::where('barang_id', $targetBarangId)
                            ->where('gudang_id', $outletId)
                            ->first();
                        if ($stokOutlet) {
                            $stokOutlet->increment('jumlah', (float)$tx->qty);
                        }
                    }
                }
                // Skenario 4: Saldo Awal / Persediaan Awal
                elseif (in_array($sourceType, ['saldo_awal', 'persediaan_awal'])) {
                    if ($sourceId) {
                        $pa = \App\Models\PersediaanAwal::find($sourceId);
                        if ($pa) {
                            $paDetail = \App\Models\PersediaanAwalDetail::where('persediaan_awal_id', $pa->id)
                                ->where('barang_id', $targetBarangId)
                                ->first();

                            if ($paDetail) {
                                // Hapus atau kurangi batch FIFO terkait saldo awal ini
                                if ($paDetail->batch_number) {
                                    $batch = \App\Models\StokGudangBatch::where('gudang_id', $pa->gudang_id)
                                        ->where('barang_id', $targetBarangId)
                                        ->where('batch_number', $paDetail->batch_number)
                                        ->first();
                                    if ($batch) {
                                        if ($batch->qty_keluar > 0) {
                                            $batch->update([
                                                'qty_masuk' => $batch->qty_keluar,
                                                'qty_sisa'  => 0,
                                                'is_habis'  => true,
                                            ]);
                                        } else {
                                            $batch->delete();
                                        }
                                    }
                                }

                                // Kurangi jumlah di stok_gudang
                                $stokGudang = \App\Models\StokGudang::where('barang_id', $targetBarangId)
                                    ->where('gudang_id', $pa->gudang_id)
                                    ->when($pa->divisi_id, fn($q) => $q->where('divisi_id', $pa->divisi_id), fn($q) => $q->whereNull('divisi_id'))
                                    ->first();
                                if ($stokGudang) {
                                    $stokGudang->decrement('jumlah', min((float)$stokGudang->jumlah, (float)$paDetail->qty));
                                }

                                // Hapus detail item dari Persediaan Awal
                                $paDetail->delete();

                                // Update header Persediaan Awal
                                $pa->update([
                                    'total_item'  => $pa->details()->count(),
                                    'total_qty'   => (float) $pa->details()->sum('qty'),
                                    'total_nilai' => (float) $pa->details()->sum('total_nilai'),
                                ]);
                            }
                        }
                    }
                }

                // Hapus transaksi stok ini
                $tx->delete();

                // Bersihkan orphan mutasi jika ada
                self::autoCleanOrphanMutations($targetBarangId);

                // Rekonsiliasi & Sinkronisasi
                \App\Models\StokGudang::reconcileStockSummary($targetBarangId);
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$targetBarangId);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus secara permanen dan persediaan telah disinkronkan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mutasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk menetralkan stok minus / defisit historis item barang.
     */
    public function netralisirStokMinus(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isGudang())) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menetralkan stok minus.'
            ], 403);
        }

        $barangId = $request->barang_id;
        $gudangId = $request->gudang_id ?: \App\Models\MasterGudang::getGudangUtamaId();
        $divisiId = $request->divisi_id;

        try {
            $barangList = [];
            if ($barangId) {
                $barangList = MasterBarang::withoutGlobalScopes()->where('id', $barangId)->get();
            } else {
                $allBarangs = MasterBarang::withoutGlobalScopes()->get();
                foreach ($allBarangs as $b) {
                    $stk = \App\Models\StokGudang::getStokBukuPembantu($b->id, $gudangId, $divisiId);
                    if ($stk < -0.0001) {
                        $barangList[] = $b;
                    }
                }
            }

            $countNeutralized = 0;

            DB::transaction(function () use ($barangList, $gudangId, $divisiId, &$countNeutralized) {
                foreach ($barangList as $barang) {
                    $bId = $barang->id;

                    // 1. Bersihkan transaksi orphan terlebih dahulu
                    self::autoCleanOrphanMutations($bId);
                    self::autoHealMissingDivisiInTransaksiStok($bId);

                    // 2. Ambil seluruh transaksi stok untuk barang ini di gudang terkait
                    $txs = DB::table('transaksi_stok')
                        ->where('barang_id', $bId)
                        ->where(function ($q) use ($gudangId, $divisiId) {
                            if ($gudangId && $divisiId) {
                                $q->where(fn($s) => $s->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId))
                                  ->orWhere(fn($s) => $s->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId));
                            } elseif ($gudangId) {
                                $q->where('gudang_asal_id', $gudangId)
                                  ->orWhere('gudang_tujuan_id', $gudangId);
                            }
                        })
                        ->orderBy('tanggal', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();

                    $running = 0;
                    $minBalance = 0;
                    $firstTxDate = now();
                    if ($txs->isNotEmpty()) {
                        $firstTxDate = $txs->first()->tanggal;
                        foreach ($txs as $tx) {
                            $isMasuk = ($gudangId && $tx->gudang_tujuan_id == $gudangId && (!$divisiId || $tx->divisi_tujuan_id == $divisiId))
                                || (!$gudangId && $tx->tipe === 'masuk');
                            $isKeluar = ($gudangId && $tx->gudang_asal_id == $gudangId && (!$divisiId || $tx->divisi_asal_id == $divisiId))
                                || (!$gudangId && $tx->tipe === 'keluar');

                            if ($isMasuk) $running += (float) $tx->qty;
                            elseif ($isKeluar) $running -= (float) $tx->qty;

                            if ($running < $minBalance) {
                                $minBalance = $running;
                            }
                        }
                    }

                    $currentStock = \App\Models\StokGudang::getStokBukuPembantu($bId, $gudangId, $divisiId);

                    $deficitToHeal = 0;
                    if ($minBalance < -0.0001) {
                        $deficitToHeal = abs($minBalance);
                    } elseif ($currentStock < -0.0001) {
                        $deficitToHeal = abs($currentStock);
                    }

                    if ($deficitToHeal > 0.0001) {
                        $fifoService = app(\App\Services\FifoService::class);
                        $unitPrice = $fifoService->getHargaTerakhirBahan($bId, $gudangId);
                        if ($unitPrice <= 0) {
                            $unitPrice = (float) ($barang->hpp_referensi ?? 0);
                        }

                        $adjustDate = date('Y-m-d H:i:s', strtotime($firstTxDate . ' - 1 minute'));

                        \App\Models\TransaksiStok::create([
                            'tanggal'          => $adjustDate,
                            'tipe'             => 'masuk',
                            'source_type'      => 'penyesuaian_stok',
                            'source_id'        => null,
                            'gudang_asal_id'   => null,
                            'divisi_asal_id'   => null,
                            'gudang_tujuan_id' => $gudangId,
                            'divisi_tujuan_id' => $divisiId,
                            'barang_id'        => $bId,
                            'qty'              => $deficitToHeal,
                            'total_harga'      => round($deficitToHeal * $unitPrice, 2),
                            'created_by'       => auth()->id(),
                        ]);

                        $countNeutralized++;
                    }

                    \App\Models\StokGudang::reconcileStockSummary($bId, $gudangId, $divisiId);
                    app(\App\Services\FifoService::class)->syncBarangHpp((int)$bId);
                }
            });

            if ($barangId && !empty($barangList)) {
                $barang = $barangList->first();
                $finalStock = \App\Models\StokGudang::getStokBukuPembantu($barangId, $gudangId, $divisiId);
                return response()->json([
                    'success' => true,
                    'message' => "Stok minus untuk {$barang->nama} berhasil dinetralisir. Stok terkini: " . number_format($finalStock, 2, ',', '.') . " {$barang->satuan}."
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil menetralkan stok minus untuk {$countNeutralized} item barang."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menetralkan stok minus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk melakukan sinkronisasi dan refresh menyeluruh pada Buku Pembantu Persediaan.
     */
    public function syncRefreshBukuPembantu(Request $request)
    {
        $barangId = $request->barang_id;
        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;

        try {
            // 0. Auto-heal sinkronisasi tanggal penerimaan pembelian & stok
            \App\Http\Controllers\PembelianController::autoHealHistoricalPembelianPenerimaanTanggal($barangId);

            // 1. Auto-clean orphaned transactions
            self::autoCleanOrphanMutations($barangId);

            // 2. Auto-heal missing divisi in transaksi_stok
            self::autoHealMissingDivisiInTransaksiStok($barangId);

            // 2-POS. Auto-heal mutasi Penjualan POS disetujui / Moka import
            self::autoHealApprovedPosStockMutations($barangId);

            // 2a. Auto-heal missing Persediaan Awal transactions
            self::autoHealApprovedPaTransaksiStok($barangId);

            // 2b. Auto-heal missing PBK transactions
            self::autoHealApprovedPbkTransaksiStok($barangId);

            // 3. Auto-heal SO prematur
            $this->autoHealPrematureDraftSoMutations($barangId);

            // 4. Reconcile stok gudang
            if ($barangId) {
                MasterBarang::autoHealUnconvertedPembelianBatches($barangId);
                \App\Models\StokGudang::reconcileStockSummary($barangId, $gudangId, $divisiId);
                app(\App\Services\FifoService::class)->syncBarangHpp((int)$barangId);
            } else {
                MasterBarang::autoHealUnconvertedPembelianBatches();
                \App\Models\StokGudang::reconcileStockSummary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Buku Pembantu Persediaan berhasil disegarkan dan seluruh stok telah disinkronkan 100%.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyinkronkan data: ' . $e->getMessage()
            ], 500);
        }
    }
}