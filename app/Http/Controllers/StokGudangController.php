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

        // Rekonsiliasi mutasi orphan dan ringkasan stok untuk 20 item pada halaman aktif
        foreach ($itemIds as $bId) {
            self::autoCleanOrphanMutations($bId);
            \App\Models\StokGudang::reconcileStockSummary($bId);
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
            foreach ($itemIds as $bId) {
                self::autoCleanOrphanMutations($bId);
                self::autoHealMissingDivisiInTransaksiStok($bId);
                self::autoHealApprovedPaTransaksiStok($bId);
                self::autoHealApprovedPbkTransaksiStok($bId);
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

        // Auto-clean mutasi orphan untuk barang ini sebelum perhitungan mutasi
        self::autoCleanOrphanMutations($barangId);

        // Auto-heal jika ada batch pembelian yang kuantitasnya belum terkonversi (tersimpan satuan beli, bukan satuan stok dasar)
        MasterBarang::autoHealUnconvertedPembelianBatches($barangId);

        // Auto-heal mutasi yang divisi-nya belum tersinkronisasi di transaksi_stok
        self::autoHealMissingDivisiInTransaksiStok($barangId);

        // Auto-heal mutasi Persediaan Awal disetujui
        self::autoHealApprovedPaTransaksiStok($barangId);

        // Auto-heal mutasi PBK disetujui yang belum tercatat atau belum lengkap di transaksi_stok
        self::autoHealApprovedPbkTransaksiStok($barangId);

        // Auto-heal mutasi stok opname prematur yang PBK-nya masih berstatus Draft
        $this->autoHealPrematureDraftSoMutations($barangId);

        // Rekonsiliasi ringkasan stok gudang agar 100% selaras dengan batch aktif & transaksi stok
        \App\Models\StokGudang::reconcileStockSummary($barangId, $gudangId, $divisiId);

        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        $satuanStok = $barang ? ($barang->satuan ?: 'pcs') : 'pcs';
        $satuanBeliDefault = $barang ? ($barang->satuan_pembelian ?: $satuanStok) : $satuanStok;
        $konversiBarang = $barang ? (float)($barang->konversi_pembelian ?: 1.0) : 1.0;
        if ($konversiBarang <= 0) $konversiBarang = 1.0;

        $saQty = 0;
        $saNilai = 0;

        // Cari tanggal persediaan awal disetujui terbaru untuk item barang dan gudang/divisi ini (Cut-off Baseline Refresh Inventory)
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
        $minTanggal = $latestSaTanggal ? (date('Y-m-d', strtotime($latestSaTanggal)) . ' 00:00:00') : null;

        $rawBefore = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<', $startDate . ' 00:00:00');

        $rawPeriod = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->whereBetween('tanggal', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($minTanggal) {
            $rawBefore->where('tanggal', '>=', $minTanggal);
        }

        if ($gudangId && $divisiId) {
            $rawBefore->where(function ($q) use ($gudangId, $divisiId) {
                $q->where(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                });
            });
            $rawPeriod->where(function ($q) use ($gudangId, $divisiId) {
                $q->where(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                });
            });
        } elseif ($gudangId) {
            $rawBefore->where(function ($q) use ($gudangId) {
                $q->where('gudang_asal_id', $gudangId)
                  ->orWhere('gudang_tujuan_id', $gudangId);
            });
            $rawPeriod->where(function ($q) use ($gudangId) {
                $q->where('gudang_asal_id', $gudangId)
                  ->orWhere('gudang_tujuan_id', $gudangId);
            });
        } elseif ($divisiId) {
            $rawBefore->where(function ($q) use ($divisiId) {
                $q->where('divisi_asal_id', $divisiId)
                  ->orWhere('divisi_tujuan_id', $divisiId);
            });
            $rawPeriod->where(function ($q) use ($divisiId) {
                $q->where('divisi_asal_id', $divisiId)
                  ->orWhere('divisi_tujuan_id', $divisiId);
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

                if ($matchTujuan && !$matchAsal) {
                    $isMasuk = true;
                } elseif ($matchAsal && !$matchTujuan) {
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
                        if ($totalHarga > $prevSaNilai && $prevSaNilai > 0) {
                            $totalHarga = $prevSaNilai;
                        }
                        $saNilai = max(0, $prevSaNilai - $totalHarga);
                    }
                }
            }

            if ($saQty > 0 && $saNilai <= 0) {
                $fifoService = app(\App\Services\FifoService::class);
                $fallbackPrice = $fifoService->getHargaTerakhirBahan($barangId, $gudangId);
                if ($fallbackPrice <= 0) {
                    $fallbackPrice = \Illuminate\Support\Facades\DB::table('master_barang')->where('id', $barangId)->value('hpp_referensi') ?? 0;
                }
                $saNilai = $saQty * (float) $fallbackPrice;
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
                $konversiRow = (isset($pDetailInfo['konversi']) && $pDetailInfo['konversi'] > 1) ? $pDetailInfo['konversi'] : $konversiBarang;
                $satBeliRow = $pDetailInfo['satuan_pembelian'] ?: $satuanBeliDefault;
                $qtyBeliInput = $pDetailInfo['qty_input'];

                if ($konversiRow > 1 && $qtyBeliInput > 0) {
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

                if ($matchTujuan && !$matchAsal) {
                    $isMasuk = true;
                } elseif ($matchAsal && !$matchTujuan) {
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
                            if ($totalHarga > $prevRunningNilai && $prevRunningNilai > 0) {
                                $totalHarga = $prevRunningNilai;
                            }
                            $runningNilai = max(0, $prevRunningNilai - $totalHarga);
                        }
                    }
                }

                if ($runningQty > 0 && $runningNilai <= 0) {
                    $fifoService = app(\App\Services\FifoService::class);
                    $fallbackPrice = $fifoService->getHargaTerakhirBahan($barangId, $gudangId);
                    if ($fallbackPrice <= 0) {
                        $fallbackPrice = \Illuminate\Support\Facades\DB::table('master_barang')->where('id', $barangId)->value('hpp_referensi') ?? 0;
                    }
                    $runningNilai = $runningQty * (float) $fallbackPrice;
                }

                $hargaSatuan = $qty > 0 ? ($totalHarga / $qty) : 0;

                $keterangan = $this->formatSourceDescription($row->source_type, $row->source_id, $isMasuk) . $keteranganExtra;
                if ($row->tipe === 'transfer') {
                    $gAsal = DB::table('master_gudang')->where('id', $row->gudang_asal_id)->value('nama');
                    $gTujuan = DB::table('master_gudang')->where('id', $row->gudang_tujuan_id)->value('nama');
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
        $pDetail = null;
        $sourceType = strtolower($row->source_type ?? '');

        if ($sourceType === 'pembelian') {
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
        $masterKonv = $barang ? (float)($barang->konversi_pembelian ?? 1) : 1.0;
        $masterSatBeli = $barang ? $barang->satuan_pembelian : null;

        if ($pDetail) {
            $detailKonv = (float) ($pDetail->konversi_pembelian ?? 1);
            $finalKonv = $detailKonv > 1 ? $detailKonv : ($masterKonv > 1 ? $masterKonv : 1.0);
            return [
                'konversi'          => $finalKonv,
                'satuan_pembelian' => $pDetail->satuan_pembelian ?: $masterSatBeli,
                'qty_input'         => (float) ($pDetail->qty ?? 0),
            ];
        }

        return [
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

        switch (strtolower($type)) {
            case 'pembelian':
                $p = \App\Models\Pembelian::with('supplier')->find($id);
                if ($p) {
                    $supplierName = $p->supplier->nama ?? '-';
                    return "Pembelian: {$p->kode_pembelian} [Supplier: {$supplierName}]";
                }
                return "Pembelian (ID: {$id})";

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

            case 'pengiriman':
                $del = \App\Models\Pengiriman::find($id);
                if ($del) {
                    return "Pengiriman B2B: {$del->no_pengiriman}";
                }
                return "Pengiriman (ID: {$id})";

            case 'stock_opname':
                $opname = \App\Models\StockOpname::find($id);
                if ($opname) {
                    $kode = $opname->kode_opname ?? $opname->no_opname ?? "ID: {$id}";
                    return "Stock Opname: {$kode}";
                }
                return "Stock Opname (ID: {$id})";

            case 'penjualan_pos':
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

                $opname->update(['status' => 'draft']);
            }
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
                ->whereIn('ts.source_type', ['pembelian', 'penerimaan_pembelian'])
                ->where('ts.tipe', 'masuk')
                ->whereIn('ts.gudang_tujuan_id', $outletIds)
                ->whereNull('ts.divisi_tujuan_id');
            if ($barangId) {
                $q6->where('ts.barang_id', $barangId);
            }
            $pembelianOutletTxs = $q6->get();

            foreach ($pembelianOutletTxs as $pTx) {
                $divId = \App\Models\MasterGudang::resolveDivisiIdForBarang($pTx->gudang_tujuan_id, $pTx->barang_id);
                if ($divId) {
                    DB::table('transaksi_stok')->where('id', $pTx->id)->update(['divisi_tujuan_id' => $divId]);
                    if ($pTx->source_id) {
                        DB::table('stok_gudang_batch')
                            ->where('pembelian_id', $pTx->source_id)
                            ->where('barang_id', $pTx->barang_id)
                            ->where('gudang_id', $pTx->gudang_tujuan_id)
                            ->whereNull('divisi_id')
                            ->update(['divisi_id' => $divId]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealMissingDivisiInTransaksiStok error: ' . $e->getMessage());
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
                $isOpnameOrWasted = str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-SO-')
                    || $pbk->jenis_pengeluaran === 'wasted'
                    || str_starts_with($pbk->kode_pengeluaran ?? '', 'PBK-WST-');

                $details = $pbk->details;
                if ($barangId) {
                    $details = $details->where('barang_id', $barangId);
                }

                foreach ($details as $detail) {
                    $bId = $detail->barang_id;
                    $qty = (float) $detail->qty;
                    $totalHarga = (float) ($detail->total_harga ?: ($detail->hpp_total ?: ($qty * (float) $detail->harga_satuan)));
                    $tanggal = $pbk->tanggal ?? $pbk->created_at ?? now();

                    if (!$isOpnameOrWasted && $pbk->gudang_id) {
                        // 1. Mutasi Masuk di Gudang/Divisi Tujuan (e.g. Kejingga)
                        $txMasuk = \App\Models\TransaksiStok::where('source_type', 'pengeluaran_bahan_baku')
                            ->where('source_id', $pbk->id)
                            ->where('barang_id', $bId)
                            ->where('tipe', 'masuk')
                            ->first();

                        if (!$txMasuk) {
                            \App\Models\TransaksiStok::create([
                                'tanggal'          => $tanggal,
                                'tipe'             => 'masuk',
                                'source_type'      => 'pengeluaran_bahan_baku',
                                'source_id'        => $pbk->id,
                                'gudang_asal_id'   => null,
                                'divisi_asal_id'   => null,
                                'gudang_tujuan_id' => $pbk->gudang_id,
                                'divisi_tujuan_id' => $pbk->divisi_id,
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
                            if ($txMasuk->divisi_tujuan_id != $pbk->divisi_id) {
                                $upMasuk['divisi_tujuan_id'] = $pbk->divisi_id;
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
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealApprovedPbkTransaksiStok error: ' . $e->getMessage());
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
                ->whereIn('source_type', ['pembelian', 'pembelian_batal'])
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
                $targetOutletId = \App\Models\MasterGudang::resolveOutletId($posHeader->kode_transaksi);
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
                $targetOutletId = $gudangGaharuId;
                $pos = \App\Models\PenjualanPos::find($tx->source_id);
                if ($pos) {
                    $targetOutletId = \App\Models\MasterGudang::resolveOutletId($pos->kode_transaksi);
                } else {
                    $pbk = \App\Models\PengeluaranBahanBaku::find($tx->source_id);
                    if ($pbk) {
                        $targetOutletId = \App\Models\MasterGudang::resolveOutletId($pbk->keterangan ?? $pbk->kode_pengeluaran);
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
            // 6a. Hapus transaksi_stok saldo awal yang dokumen persediaan_awal nya sudah tidak ada ATAU masih berstatus draft
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
                // Skenario 1: Pembelian / Penerimaan Pembelian
                if (in_array($sourceType, ['pembelian', 'pembelian_batal', 'penerimaan_pembelian'])) {
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

                        // Hapus transaksi stok pembelian & pembelian_batal terkait
                        \App\Models\TransaksiStok::where('barang_id', $targetBarangId)
                            ->where('source_id', $pembelian->id)
                            ->whereIn('source_type', ['pembelian', 'pembelian_batal'])
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
            // 1. Auto-clean orphaned transactions
            self::autoCleanOrphanMutations($barangId);

            // 2. Auto-heal missing divisi in transaksi_stok
            self::autoHealMissingDivisiInTransaksiStok($barangId);

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