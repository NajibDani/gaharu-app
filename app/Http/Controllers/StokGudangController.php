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
        $itemIds   = $stokGudang->pluck('id')->toArray();
        $gudangIds = $stokGudang->pluck('gudang_id')->unique()->toArray();

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

        $rawBefore = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<', $startDate . ' 00:00:00');

        if ($gudangId && $divisiId) {
            $rawBefore->where(function ($q) use ($gudangId, $divisiId) {
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
        } elseif ($divisiId) {
            $rawBefore->where(function ($q) use ($divisiId) {
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
        }

        $rawPeriod = DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->whereBetween('tanggal', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($gudangId && $divisiId) {
            $rawPeriod->where(function ($q) use ($gudangId, $divisiId) {
                $q->where(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
                })->orWhere(function($sub) use ($gudangId, $divisiId) {
                    $sub->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
                });
            });
        } elseif ($gudangId) {
            $rawPeriod->where(function ($q) use ($gudangId) {
                $q->where('gudang_asal_id', $gudangId)
                  ->orWhere('gudang_tujuan_id', $gudangId);
            });
        } elseif ($divisiId) {
            $rawPeriod->where(function ($q) use ($divisiId) {
                $q->where('divisi_asal_id', $divisiId)
                  ->orWhere('divisi_tujuan_id', $divisiId);
            });
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

                $hargaSatuan = $qty > 0 ? ($totalHarga / $qty) : 0;

                $keterangan = $this->formatSourceDescription($row->source_type, $row->source_id) . $keteranganExtra;
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
                    return "Material Output: {$kode}{$tujuanStr}";
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
                    $kode = $opname->kode_opname ?? $opname->no_opname ?? "ID: {$id}";
                    return "Stock Opname: {$kode}";
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
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('autoHealMissingDivisiInTransaksiStok error: ' . $e->getMessage());
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
     * yang dokumen induknya (Pembelian, Pengeluaran, Penerimaan) sudah dihapus atau tidak valid.
     */
    public static function autoCleanOrphanMutations($barangId = null)
    {
        try {
            // 1. Orphan Pembelian / Pembelian Batal (source_id tidak ada di tabel pembelian ATAU pembelian dibatalkan/dihapus)
            $hasIsDeletedCol = \Illuminate\Support\Facades\Schema::hasColumn('pembelian', 'is_deleted');
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
                    });
                });
            if ($barangId) {
                $orphanPembelianQuery->where('barang_id', $barangId);
            }
            $orphanPembelianQuery->delete();

            // 2. Orphan Penerimaan Pembelian (source_id tidak ada di tabel penerimaan_pembelian)
            $orphanRcvQuery = DB::table('transaksi_stok')
                ->where('source_type', 'penerimaan_pembelian')
                ->whereNotNull('source_id')
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('penerimaan_pembelian')
                      ->whereColumn('penerimaan_pembelian.id', 'transaksi_stok.source_id');
                });
            if ($barangId) {
                $orphanRcvQuery->where('barang_id', $barangId);
            }
            $orphanRcvQuery->delete();

            // 3. Orphan Pengeluaran Bahan Baku / Wasted (source_id tidak ada di tabel pengeluaran_bahan_baku)
            $orphanPbkQuery = DB::table('transaksi_stok')
                ->whereIn('source_type', ['pengeluaran_bahan_baku', 'pengeluaran_wasted'])
                ->whereNotNull('source_id')
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('pengeluaran_bahan_baku')
                      ->whereColumn('pengeluaran_bahan_baku.id', 'transaksi_stok.source_id');
                });
            if ($barangId) {
                $orphanPbkQuery->where('barang_id', $barangId);
            }
            $orphanPbkQuery->delete();

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

            // 5. Auto-heal transaksi POS yang salah gudang (masuk ke Gudang Utama)
            $gudangUtamaId = \App\Models\MasterGudang::getGudangUtamaId();
            $misplacedPosOutputs = \App\Models\PengeluaranBahanBaku::where('keterangan', 'like', 'AUTO_POS%')
                ->where('gudang_id', $gudangUtamaId)
                ->with(['details'])
                ->get();

            if ($misplacedPosOutputs->isNotEmpty()) {
                $gudangGaharu = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')->first();
                $gudangGaharuId = $gudangGaharu ? $gudangGaharu->id : 3;
                $gudangKejingga = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')->first();
                $gudangKejinggaId = $gudangKejingga ? $gudangKejingga->id : 5;

                foreach ($misplacedPosOutputs as $pbk) {
                    $isKejingga = str_contains(strtolower($pbk->keterangan ?? ''), 'kj') || str_contains(strtolower($pbk->kode_pengeluaran ?? ''), 'kj');
                    $targetGudangId = $isKejingga ? $gudangKejinggaId : $gudangGaharuId;

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

                        // Catat ke TransaksiStok untuk Gudang Outlet jika belum ada
                        $exists = DB::table('transaksi_stok')
                            ->where('source_type', 'penjualan_pos')
                            ->where('source_id', $pbk->id)
                            ->where('barang_id', $bId)
                            ->exists();

                        if (!$exists) {
                            DB::table('transaksi_stok')->insert([
                                'tanggal'        => $pbk->tanggal ?? now(),
                                'tipe'           => 'keluar',
                                'source_type'    => 'penjualan_pos',
                                'source_id'      => $pbk->id,
                                'gudang_asal_id' => $targetGudangId,
                                'barang_id'      => $bId,
                                'qty'            => $qty,
                                'total_harga'    => (float) $d->hpp_total,
                                'created_by'     => $pbk->created_by ?? 1,
                            ]);
                        }
                    }

                    $pbk->update(['gudang_id' => $targetGudangId]);
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

                        // Tandai pembelian sebagai Dihapus / Dibatalkan agar tetap tercatat di menu pembelian
                        // Menggunakan catatan_pembayaran dengan format [DELETED] sehingga bekerja tanpa migrasi DB
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

            // 2. Auto-heal SO prematur
            $this->autoHealPrematureDraftSoMutations($barangId);

            // 3. Reconcile stok gudang
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