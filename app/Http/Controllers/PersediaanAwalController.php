<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\GudangDivisi;
use App\Models\Journal;
use App\Models\JurnalPenyesuaian;
use App\Models\Kategori;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\PersediaanAwal;
use App\Models\PersediaanAwalDetail;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PersediaanAwalController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX: Daftar Transaksi Persediaan Awal
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        $gudangId  = $request->query('gudang_id');
        $divisiId  = $request->query('divisi_id');
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $search    = $request->query('search');

        // Filter otomatis sesuai hak akses role jika bukan Super Admin / Direktur Keuangan
        if ($roleName === 'Kepala Outlet Kejingga' && !$gudangId) {
            $gudangId = 4;
        } elseif ($roleName === 'Kepala Outlet Gaharu' && !$gudangId) {
            $gudangId = 2;
        } elseif ($roleName === 'Kepala Gudang' && !$gudangId) {
            $gudangId = 1;
        }

        $query = PersediaanAwal::with(['gudang', 'divisi', 'user', 'details.barang'])
            ->latest('tanggal')
            ->latest('id');

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        }

        if ($startDate) {
            $query->whereDate('tanggal', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('tanggal', '<=', $endDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_transaksi', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        $data = $query->paginate(15)->withQueryString();

        // Ringkasan Statistik
        $summaryQuery = PersediaanAwal::query();
        if ($gudangId) $summaryQuery->where('gudang_id', $gudangId);
        if ($divisiId) $summaryQuery->where('divisi_id', $divisiId);
        if ($startDate) $summaryQuery->whereDate('tanggal', '>=', $startDate);
        if ($endDate) $summaryQuery->whereDate('tanggal', '<=', $endDate);

        $totalTransaksi = $summaryQuery->count();
        $totalNilai     = $summaryQuery->sum('total_nilai');
        $totalQty       = $summaryQuery->sum('total_qty');

        // Master Gudang untuk dropdown filter
        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::with('divisi')->where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::with('divisi')->where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::with('divisi')->where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        }

        return view('persediaan-awal.index', compact(
            'data', 'gudangs', 'gudangId', 'divisiId',
            'startDate', 'endDate', 'search',
            'totalTransaksi', 'totalNilai', 'totalQty'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE: Form Input Persediaan Awal
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::with('divisi')->where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::with('divisi')->where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::with('divisi')->where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        }

        $kategoris = Kategori::orderBy('nama')->get();

        $defaultGudangId = $gudangs->first()->id ?? null;

        return view('persediaan-awal.create', compact('gudangs', 'kategoris', 'defaultGudangId'));
    }

    /**
     * Mengambil map harga referensi persediaan awal dari Gudang Utama (ID: 2 / Kategori Utama)
     * Format output: [barang_id => harga_satuan_stok]
     */
    private function getHargaGudangUtamaMap(): array
    {
        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;

        // 1. Ambil dari persediaan awal Gudang Utama yang harganya > 0
        $paUtamaPrices = DB::table('persediaan_awal_detail as pad')
            ->join('persediaan_awal as pa', 'pa.id', '=', 'pad.persediaan_awal_id')
            ->where('pa.gudang_id', $gudangUtamaId)
            ->where('pad.harga_satuan', '>', 0)
            ->orderBy('pa.tanggal', 'desc')
            ->orderBy('pad.id', 'desc')
            ->pluck('pad.harga_satuan', 'pad.barang_id')
            ->toArray();

        // 2. Ambil dari batch stok Gudang Utama
        $batchPrices = DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangUtamaId)
            ->where('harga_per_qty', '>', 0)
            ->orderBy('id', 'desc')
            ->pluck('harga_per_qty', 'barang_id')
            ->toArray();

        // 3. Ambil dari master_barang hpp_referensi
        $masterHpp = MasterBarang::where('hpp_referensi', '>', 0)->pluck('hpp_referensi', 'id')->toArray();

        // Prioritas: Persediaan Awal Gudang Utama > Batch Gudang Utama > HPP Referensi Master Barang
        return $paUtamaPrices + $batchPrices + $masterHpp;
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD BARANG AJAX: Mengambil semua master barang aktif beserta info stok/HPP
    |--------------------------------------------------------------------------
    */
    public function loadBarang(Request $request)
    {
        $gudangId = $request->gudang_id;
        $divisiId = $request->divisi_id;
        $kategoriId = $request->kategori_id;
        $search = $request->search;

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;

        $gudangPilihan = $gudangId ? MasterGudang::find($gudangId) : null;
        $isGudangUtama = $gudangPilihan ? (strtolower($gudangPilihan->kategori) === 'utama' || $gudangPilihan->id == $gudangUtamaId) : true;

        $hargaUtamaMap = $this->getHargaGudangUtamaMap();

        $query = MasterBarang::with('kategori')
            ->where('is_active', true);

        if ($kategoriId) {
            $query->where('kategori_id', $kategoriId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode_barang', 'like', "%{$search}%");
            });
        }

        // Filter bahan baku yang dinonaktifkan di outlet & divisi terpilih
        if ($gudangId) {
            $query->where(function($q) use ($gudangId, $divisiId) {
                // Barang non-bahan baku tetap lolos
                $q->where('is_bahan_baku', false)
                  ->orWhere(function($subQ) use ($gudangId, $divisiId) {
                      $subQ->where('is_bahan_baku', true)
                           ->whereNotExists(function($notExistsQuery) use ($gudangId, $divisiId) {
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
        }

        $barangList = $query->orderBy('kode_barang', 'asc')->get();

        // Ambil stok saat ini jika gudang dipilih
        $stockMap = [];
        if ($gudangId) {
            $stokQuery = DB::table('stok_gudang')
                ->where('gudang_id', $gudangId);
            if ($divisiId) {
                $stokQuery->where('divisi_id', $divisiId);
            } else {
                $stokQuery->whereNull('divisi_id');
            }
            $stockMap = $stokQuery->pluck('jumlah', 'barang_id')->toArray();
        }

        $result = $barangList->map(function ($b) use ($stockMap, $hargaUtamaMap, $isGudangUtama) {
            $jenis = 'Bahan Baku';
            if ($b->is_bahan_setengah_jadi) {
                $jenis = 'Bahan Setengah Jadi';
            } elseif ($b->is_barang_jadi) {
                $jenis = 'Barang Jadi';
            } elseif ($b->is_operational) {
                $jenis = 'Operational';
            }

            $konversi = (float) ($b->konversi_pembelian ?: 1.00);
            if ($konversi <= 0) $konversi = 1.00;

            // Harga referensi stok Gudang Utama
            $hargaStokUtama = (float) ($hargaUtamaMap[$b->id] ?? ($b->hpp_referensi ?? 0));
            $hargaBeliUtama = $hargaStokUtama * $konversi;

            return [
                'id'                 => $b->id,
                'kode_barang'        => $b->kode_barang,
                'nama'               => $b->nama,
                'kategori_id'        => $b->kategori_id,
                'kategori_nama'      => $b->kategori->nama ?? '-',
                'satuan'             => $b->satuan,
                'satuan_pembelian'   => $b->satuan_pembelian ?: $b->satuan,
                'konversi_pembelian' => $konversi,
                'jenis'              => $jenis,
                'is_gudang_utama'    => $isGudangUtama,
                'hpp_referensi'      => (float) ($b->hpp_referensi ?? 0),
                'hpp_satuan_utama'   => $hargaStokUtama,
                'harga_beli_utama'   => $hargaBeliUtama,
                'stok_sekarang'      => (float) ($stockMap[$b->id] ?? 0),
            ];
        });

        return response()->json([
            'status'            => 'success',
            'is_gudang_utama'   => $isGudangUtama,
            'gudang_utama_nama' => $gudangUtama->nama ?? 'Gudang Utama',
            'data'              => $result,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE: Simpan Transaksi Persediaan Awal & Update Stok, Batch FIFO, Jurnal
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'gudang_id'      => 'required|exists:master_gudang,id',
            'divisi_id'      => 'nullable|exists:gudang_divisi,id',
            'tanggal'        => 'required|date',
            'barang_id'      => 'required|array|min:1',
            'barang_id.*'    => 'required|exists:master_barang,id',
            'qty'            => 'required|array|min:1',
            'harga_satuan'   => 'required|array|min:1',
            'keterangan'     => 'nullable|string|max:500',
        ]);

        $gudang = MasterGudang::with('divisi')->findOrFail($request->gudang_id);
        if (strtolower($gudang->kategori) === 'operasional' && $gudang->divisi->count() > 0 && empty($request->divisi_id)) {
            return back()->withErrors(['divisi_id' => 'Silakan pilih divisi untuk gudang operasional ' . $gudang->nama . '.'])->withInput();
        }

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;
        $isGudangUtama = ($request->gudang_id == $gudangUtamaId || strtolower($gudang->kategori) === 'utama');

        $hargaUtamaMap = $this->getHargaGudangUtamaMap();

        $tanggal = date('Y-m-d', strtotime($request->tanggal));

        if (Journal::isPeriodClosed($tanggal)) {
            return back()->withErrors([
                'tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku. Tidak dapat mencatat Persediaan Awal pada periode yang sudah ditutup.',
            ])->withInput();
        }

        // Filter hanya item yang qty > 0
        $validItems = [];
        foreach ($request->barang_id as $index => $barangId) {
            $qtyInput = (float) str_replace(',', '.', $request->qty[$index] ?? 0);

            if ($qtyInput > 0) {
                $barang = MasterBarang::find($barangId);
                if (!$barang) continue;

                $konversi = (float) ($barang->konversi_pembelian ?: 1.00);
                if ($konversi <= 0) $konversi = 1.00;

                $satuanBeli = $barang->satuan_pembelian ?: $barang->satuan;
                $qtyStok = $qtyInput * $konversi;

                if ($isGudangUtama) {
                    $hargaInput = (float) str_replace(',', '.', $request->harga_satuan[$index] ?? 0);
                    $hargaStok = $konversi > 0 ? (max(0, $hargaInput) / $konversi) : max(0, $hargaInput);
                } else {
                    // Gudang non-Utama: harga otomatis mengikuti harga referensi Gudang Utama
                    $hargaStok = (float) ($hargaUtamaMap[$barangId] ?? ($barang->hpp_referensi ?? 0));
                    $hargaInput = $hargaStok * $konversi;
                }

                $totalNilai = round($qtyInput * max(0, $hargaInput), 2);

                $validItems[] = [
                    'barang_id'          => $barangId,
                    'barang'             => $barang,
                    'qty_input'          => $qtyInput,
                    'harga_input'        => max(0, $hargaInput),
                    'satuan_pembelian'   => $satuanBeli,
                    'konversi_pembelian' => $konversi,
                    'qty_stok'           => $qtyStok,
                    'harga_stok'         => $hargaStok,
                    'total_nilai'        => $totalNilai,
                ];
            }
        }

        if (empty($validItems)) {
            return back()->withErrors([
                'error' => 'Harap isi minimal 1 barang dengan Qty Persediaan Awal lebih dari 0.',
            ])->withInput();
        }

        DB::beginTransaction();

        try {
            // Generate Kode Transaksi unik
            $prefix = 'SA-' . date('Ymd', strtotime($tanggal)) . '-';
            $lastTrans = PersediaanAwal::where('kode_transaksi', 'like', $prefix . '%')
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastTrans) {
                $lastCodeNumber = (int) substr($lastTrans->kode_transaksi, strlen($prefix));
                $nextNumber = $lastCodeNumber + 1;
            }
            $kodeTransaksi = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $totalItem  = count($validItems);
            $totalQty   = array_sum(array_column($validItems, 'qty_stok'));
            $totalNilai = array_sum(array_column($validItems, 'total_nilai'));

            // 1. Buat Header Persediaan Awal
            $persediaanAwal = PersediaanAwal::create([
                'kode_transaksi' => $kodeTransaksi,
                'tanggal'        => $tanggal,
                'gudang_id'      => $request->gudang_id,
                'divisi_id'      => $request->divisi_id,
                'total_item'     => $totalItem,
                'total_qty'      => $totalQty,
                'total_nilai'    => $totalNilai,
                'keterangan'     => $request->keterangan ?? 'Persediaan Awal / Saldo Awal Barang',
                'status'         => 'posted',
                'created_by'     => Auth::id() ?? 1,
            ]);

            $defaultSupplierId  = DB::table('suppliers')->value('id') ?? 1;
            $defaultPembelianId = DB::table('pembelian')->value('id') ?? 1;
            $defaultPemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

            $surplusDebits = [];
            $totalKredit   = 0;

            foreach ($validItems as $item) {
                $barang = $item['barang'];
                $satuanStok = $barang->satuan ?? 'pcs';
                $batchNumber = 'SA-' . date('Ymd', strtotime($tanggal)) . '-' . ($barang->kode_barang ?? $item['barang_id']);

                // 2. Simpan Detail Persediaan Awal (lengkap dengan satuan beli & satuan stok)
                PersediaanAwalDetail::create([
                    'persediaan_awal_id' => $persediaanAwal->id,
                    'barang_id'          => $item['barang_id'],
                    'qty'                => $item['qty_stok'],
                    'satuan'             => $satuanStok,
                    'satuan_pembelian'   => $item['satuan_pembelian'],
                    'konversi_pembelian' => $item['konversi_pembelian'],
                    'qty_pembelian'      => $item['qty_input'],
                    'harga_pembelian'    => $item['harga_input'],
                    'harga_satuan'       => $item['harga_stok'],
                    'total_nilai'        => $item['total_nilai'],
                    'batch_number'       => $batchNumber,
                ]);

                // 3. Tambah Stok Gudang (Satuan Stok Utama)
                $stokQuery = StokGudang::where('barang_id', $item['barang_id'])
                    ->where('gudang_id', $request->gudang_id);
                if ($request->divisi_id) {
                    $stokQuery->where('divisi_id', $request->divisi_id);
                } else {
                    $stokQuery->whereNull('divisi_id');
                }

                $stokGudang = $stokQuery->lockForUpdate()->first();
                if ($stokGudang) {
                    $stokGudang->increment('jumlah', $item['qty_stok']);
                } else {
                    StokGudang::create([
                        'barang_id' => $item['barang_id'],
                        'gudang_id' => $request->gudang_id,
                        'divisi_id' => $request->divisi_id,
                        'jumlah'    => $item['qty_stok'],
                    ]);
                }

                // 4. Buat Batch FIFO di stok_gudang_batch (Satuan Stok Utama)
                StokGudangBatch::create([
                    'gudang_id'           => $request->gudang_id,
                    'divisi_id'           => $request->divisi_id,
                    'supplier_id'         => $defaultSupplierId,
                    'barang_id'           => $item['barang_id'],
                    'pembelian_id'        => $defaultPembelianId,
                    'pembelian_detail_id' => $defaultPemDetailId,
                    'batch_number'        => $batchNumber,
                    'qty_masuk'           => $item['qty_stok'],
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $item['qty_stok'],
                    'harga_per_qty'       => $item['harga_stok'],
                    'is_habis'            => false,
                ]);

                // 5. Catat Transaksi Stok (Masuk)
                TransaksiStok::create([
                    'tanggal'          => $tanggal . ' ' . date('H:i:s'),
                    'tipe'             => 'masuk',
                    'source_type'      => 'saldo_awal',
                    'source_id'        => $persediaanAwal->id,
                    'gudang_tujuan_id' => $request->gudang_id,
                    'divisi_tujuan_id' => $request->divisi_id,
                    'barang_id'        => $item['barang_id'],
                    'qty'              => $item['qty_stok'],
                    'total_harga'      => $item['total_nilai'],
                    'created_by'       => Auth::id() ?? 1,
                ]);

                // Update HPP Referensi di Master Barang jika sebelumnya masih 0
                if (($barang->hpp_referensi == 0 || empty($barang->hpp_referensi)) && $item['harga_stok'] > 0) {
                    $barang->update(['hpp_referensi' => $item['harga_stok']]);
                }

                // Kelompokkan akun untuk jurnal
                if ($item['total_nilai'] > 0) {
                    $isOperational = $barang && ($barang->is_operational || (!$barang->is_bahan_baku && !$barang->is_bahan_setengah_jadi && !$barang->is_barang_jadi));
                    $coaCode = $isOperational ? '1501' : '1301';
                    $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);

                    if (!isset($surplusDebits[$idPersediaan])) {
                        $surplusDebits[$idPersediaan] = 0;
                    }
                    $surplusDebits[$idPersediaan] += $item['total_nilai'];
                    $totalKredit += $item['total_nilai'];
                }
            }

            // 6. Buat Jurnal Penyesuaian / Saldo Awal (Debit Persediaan, Kredit Modal Disetor / Laba Ditahan)
            if ($totalKredit > 0) {
                // Akun Kredit: Modal Disetor (3101) atau Laba Ditahan (3103) atau Modal Ekuitas
                $idEkuitas = DB::table('chart_of_accounts')->where('kode', '3101')->value('id')
                          ?? DB::table('chart_of_accounts')->where('kode', '3103')->value('id')
                          ?? 30;

                $jp = JurnalPenyesuaian::create([
                    'tanggal'     => $tanggal,
                    'deskripsi'   => "[Saldo Awal] Persediaan Awal Barang: {$kodeTransaksi} ({$gudang->nama})",
                    'no_ref'      => 'AJP-SA-' . $kodeTransaksi,
                    'source_type' => 'saldo_awal',
                    'source_id'   => $persediaanAwal->id,
                    'created_by'  => Auth::id() ?? 1,
                    'status'      => 'approved',
                ]);

                foreach ($surplusDebits as $accId => $debitAmount) {
                    $jp->details()->create([
                        'account_id'   => $accId,
                        'debit'        => round($debitAmount, 2),
                        'kredit'       => 0,
                        'journal_type' => JurnalPenyesuaian::class,
                    ]);
                }

                $jp->details()->create([
                    'account_id'   => $idEkuitas,
                    'debit'        => 0,
                    'kredit'       => round($totalKredit, 2),
                    'journal_type' => JurnalPenyesuaian::class,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('persediaan-awal.show', $persediaanAwal->id)
                ->with('success', "Persediaan Awal ({$kodeTransaksi}) berhasil dicatat. Stok gudang, batch FIFO, dan jurnal penyesuaian telah dibuat.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan Persediaan Awal: ' . $e->getMessage())->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW: Rincian Transaksi Persediaan Awal
    |--------------------------------------------------------------------------
    */
    public function show(string $id)
    {
        $persediaanAwal = PersediaanAwal::with([
            'gudang',
            'divisi',
            'user',
            'details.barang.kategori',
        ])->findOrFail($id);

        $jurnal = JurnalPenyesuaian::with('details.account')
            ->where('source_type', 'saldo_awal')
            ->where('source_id', $persediaanAwal->id)
            ->first();

        return view('persediaan-awal.show', compact('persediaanAwal', 'jurnal'));
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD TEMPLATE EXCEL
    |--------------------------------------------------------------------------
    */
    public function importTemplate(Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Persediaan Awal');

        $headers = [
            'kode_barang', 'nama_barang', 'kategori', 'satuan_stok', 'satuan_beli', 'konversi', 'qty_beli_awal', 'harga_beli_satuan',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D88656');

        $hargaUtamaMap = $this->getHargaGudangUtamaMap();

        // Isi semua data master barang aktif sebagai referensi / template langsung isi
        $barangs = MasterBarang::with('kategori')
            ->where('is_active', true)
            ->orderBy('kode_barang', 'asc')
            ->get();

        $rowNum = 2;
        foreach ($barangs as $b) {
            $konversi = (float) ($b->konversi_pembelian ?: 1.00);
            if ($konversi <= 0) $konversi = 1.00;
            $satuanBeli = $b->satuan_pembelian ?: $b->satuan;
            $hargaStokUtama = (float) ($hargaUtamaMap[$b->id] ?? ($b->hpp_referensi ?? 0));
            $defaultHargaBeli = $hargaStokUtama * $konversi;

            $sheet->setCellValue('A' . $rowNum, $b->kode_barang);
            $sheet->setCellValue('B' . $rowNum, $b->nama);
            $sheet->setCellValue('C' . $rowNum, $b->kategori->nama ?? '-');
            $sheet->setCellValue('D' . $rowNum, $b->satuan);
            $sheet->setCellValue('E' . $rowNum, $satuanBeli);
            $sheet->setCellValue('F' . $rowNum, $konversi);
            $sheet->setCellValue('G' . $rowNum, 0); // Default Qty Beli Awal 0
            $sheet->setCellValue('H' . $rowNum, $defaultHargaBeli); // Default Harga Satuan Beli
            $rowNum++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet Panduan
        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Panduan');
        $guideSheet->fromArray([
            ['Kolom', 'Wajib?', 'Keterangan'],
            ['kode_barang', 'Ya', 'Kode barang sesuai Master Barang. Jangan diubah.'],
            ['nama_barang', 'Tidak', 'Nama barang (hanya untuk referensi).'],
            ['kategori', 'Tidak', 'Kategori barang (hanya untuk referensi).'],
            ['satuan_stok', 'Tidak', 'Satuan stok dasar barang (misal: ml, gram, pcs).'],
            ['satuan_beli', 'Tidak', 'Satuan pembelian barang (misal: galon, dus, kg).'],
            ['konversi', 'Tidak', 'Faktor konversi pembelian (1 satuan beli = X satuan stok).'],
            ['qty_beli_awal', 'Ya', 'Jumlah kuantitas saldo awal dalam satuan beli (misal: 1 galon).'],
            ['harga_beli_satuan', 'Khusus Gudang Utama', 'Harga beli satuan diisi untuk Gudang Utama. Untuk gudang cabang/operasional lainnya, harga otomatis mengikuti referensi Gudang Utama.'],
        ], null, 'A1');
        $guideSheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $col) {
            $guideSheet->getColumnDimension($col)->setWidth(30);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'template_persediaan_awal_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL: Upload File Excel Persediaan Awal
    |--------------------------------------------------------------------------
    */
    public function importExcel(Request $request)
    {
        $request->validate([
            'gudang_id'  => 'required|exists:master_gudang,id',
            'divisi_id'  => 'nullable|exists:gudang_divisi,id',
            'tanggal'    => 'required|date',
            'file_excel' => 'required|file|mimes:xlsx,xls,csv',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $gudang = MasterGudang::with('divisi')->findOrFail($request->gudang_id);
        if (strtolower($gudang->kategori) === 'operasional' && $gudang->divisi->count() > 0 && empty($request->divisi_id)) {
            return back()->withErrors(['divisi_id' => 'Silakan pilih divisi untuk gudang operasional ' . $gudang->nama . '.'])->withInput();
        }

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;
        $isGudangUtama = ($request->gudang_id == $gudangUtamaId || strtolower($gudang->kategori) === 'utama');

        $hargaUtamaMap = $this->getHargaGudangUtamaMap();

        $tanggal = date('Y-m-d', strtotime($request->tanggal));

        if (Journal::isPeriodClosed($tanggal)) {
            return back()->withErrors([
                'tanggal' => 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.',
            ])->withInput();
        }

        try {
            $spreadsheet = IOFactory::load($request->file('file_excel')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, false, false);

            if (empty($rows) || count($rows) < 2) {
                return back()->with('error', 'File Excel kosong atau tidak memiliki baris data.');
            }

            $headerRow = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
            $colIndex  = array_flip($headerRow);

            if (!isset($colIndex['kode_barang'])) {
                return back()->with('error', "Kolom 'kode_barang' tidak ditemukan di header Excel.");
            }

            $get = fn(array $r, string $key) => isset($colIndex[$key], $r[$colIndex[$key]]) ? trim((string) $r[$colIndex[$key]]) : '';
            $num = function ($val, $default = 0) {
                if ($val === '' || $val === null) return $default;
                if (is_numeric($val)) return (float) $val;
                // Bersihkan karakter selain angka, titik, koma, minus (menghapus Rp, spasi, simbol mata uang)
                $clean = preg_replace('/[^0-9.,-]/', '', (string) $val);
                if ($clean === '') return $default;
                // Penanganan jika ada titik ribuan dan koma desimal (format Indo/Eropa)
                if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } elseif (strpos($clean, ',') !== false) {
                    $clean = str_replace(',', '.', $clean);
                }
                return is_numeric($clean) ? (float) $clean : $default;
            };

            $allBarang = MasterBarang::all();
            $barangMapByCode = $allBarang->keyBy('kode_barang');
            $barangMapByName = $allBarang->keyBy(fn($b) => strtolower(trim((string)$b->nama)));

            $validItems = [];
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $kodeBarang = $get($row, 'kode_barang');
                $namaBarang = $get($row, 'nama_barang') ?: ($get($row, 'nama') ?: '');
                $normNama   = strtolower(trim((string)$namaBarang));

                $barang = null;

                // 1. Prioritaskan pencocokan via kode_barang (karena kode unik untuk setiap item & kategori)
                if (!empty($kodeBarang) && $barangMapByCode->has($kodeBarang)) {
                    $barang = $barangMapByCode->get($kodeBarang);
                }

                // 2. Jika tidak ditemukan via kode, cari berdasarkan nama_barang
                if (!$barang && !empty($normNama) && $barangMapByName->has($normNama)) {
                    $barang = $barangMapByName->get($normNama);
                }

                // 3. Jika barang belum ada sama sekali di database, buat otomatis di master_barang
                if (!$barang && !empty($kodeBarang)) {
                    $katName = strtoupper(trim((string)$get($row, 'kategori')));
                    $kategori = Kategori::firstOrCreate(['nama' => $katName ?: 'BAHAN BAKU'], ['prefix' => substr($kodeBarang, 0, 3)]);

                    $isBahanBaku = ($katName === 'BAHAN BAKU') ? 1 : 0;
                    $isBahanSetengahJadi = ($katName === 'BAHAN SETENGAH JADI') ? 1 : 0;
                    $isBarangJadi = ($katName === 'MAKANAN & MINUMAN') ? 1 : 0;

                    $barang = MasterBarang::create([
                        'kode_barang'            => $kodeBarang,
                        'nama'                   => $namaBarang ?: $kodeBarang,
                        'kategori_id'            => $kategori->id,
                        'satuan'                 => strtoupper(trim((string)$get($row, 'satuan_stok'))) ?: 'PCS',
                        'satuan_pembelian'       => strtoupper(trim((string)$get($row, 'satuan_beli'))) ?: 'PCS',
                        'konversi_pembelian'     => $num($get($row, 'konversi') ?: 1),
                        'is_bahan_baku'          => $isBahanBaku,
                        'is_bahan_setengah_jadi' => $isBahanSetengahJadi,
                        'is_barang_jadi'         => $isBarangJadi,
                        'is_active'              => 1,
                    ]);
                    $barangMapByCode->put($kodeBarang, $barang);
                    if (!empty($normNama)) {
                        $barangMapByName->put($normNama, $barang);
                    }
                }

                if (!$barang) continue;

                // Baca konversi dari Excel terlebih dahulu, fallback ke database
                $excelKonversi = $num($get($row, 'konversi') ?: ($get($row, 'konversi_pembelian') ?: ($get($row, 'faktor_konversi') ?: 0)));
                $konversi = $excelKonversi > 0 ? $excelKonversi : (float) ($barang->konversi_pembelian ?: 1.00);
                if ($konversi <= 0) $konversi = 1.00;

                // Baca satuan beli dari Excel terlebih dahulu, fallback ke database
                $excelSatuanBeli = $get($row, 'satuan_beli') ?: ($get($row, 'satuan_pembelian') ?: '');
                $satuanBeli = !empty($excelSatuanBeli) ? $excelSatuanBeli : ($barang->satuan_pembelian ?: $barang->satuan);

                // Sinkronkan ke master barang jika di Excel terdapat data konversi / satuan beli yang baru / diperbarui
                if ($excelKonversi > 0 && ($excelKonversi != $barang->konversi_pembelian || (!empty($excelSatuanBeli) && $excelSatuanBeli != $barang->satuan_pembelian))) {
                    $barang->update([
                        'satuan_pembelian'   => $satuanBeli,
                        'konversi_pembelian' => $konversi,
                    ]);
                }

                $qtyInput = $num($get($row, 'qty_beli_awal') ?: ($get($row, 'qty_awal') ?: ($get($row, 'qty') ?: 0)));

                if ($isGudangUtama) {
                    $hargaInput = $num($get($row, 'harga_beli_satuan') ?: ($get($row, 'harga_satuan') ?: ($get($row, 'harga') ?: ($barang->hpp_referensi * $konversi))));
                    $hargaStok = $konversi > 0 ? (max(0, $hargaInput) / $konversi) : max(0, $hargaInput);
                } else {
                    // Gudang non-Utama: harga otomatis mengikuti harga referensi Gudang Utama
                    $hargaStok = (float) ($hargaUtamaMap[$barang->id] ?? ($barang->hpp_referensi ?? 0));
                    $hargaInput = $hargaStok * $konversi;
                }

                // Simpan seluruh baris barang (termasuk yang bernilai 0) agar urutan baris sesuai dengan file Excel
                $qtyStok = $qtyInput * $konversi;
                $totalNilai = round($qtyInput * max(0, $hargaInput), 2);

                $validItems[] = [
                    'barang_id'          => $barang->id,
                    'barang'             => $barang,
                    'qty_input'          => $qtyInput,
                    'harga_input'        => max(0, $hargaInput),
                    'satuan_pembelian'   => $satuanBeli,
                    'konversi_pembelian' => $konversi,
                    'qty_stok'           => $qtyStok,
                    'harga_stok'         => $hargaStok,
                    'total_nilai'        => $totalNilai,
                ];
            }

            if (empty($validItems)) {
                return back()->with('error', 'Tidak ada baris data barang yang valid dalam file Excel.');
            }

            DB::beginTransaction();

            // Generate Kode Transaksi
            $prefix = 'SA-' . date('Ymd', strtotime($tanggal)) . '-';
            $lastTrans = PersediaanAwal::where('kode_transaksi', 'like', $prefix . '%')
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastTrans) {
                $lastCodeNumber = (int) substr($lastTrans->kode_transaksi, strlen($prefix));
                $nextNumber = $lastCodeNumber + 1;
            }
            $kodeTransaksi = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $totalItem  = count($validItems);
            $totalQty   = array_sum(array_column($validItems, 'qty_stok'));
            $totalNilai = array_sum(array_column($validItems, 'total_nilai'));

            $persediaanAwal = PersediaanAwal::create([
                'kode_transaksi' => $kodeTransaksi,
                'tanggal'        => $tanggal,
                'gudang_id'      => $request->gudang_id,
                'divisi_id'      => $request->divisi_id,
                'total_item'     => $totalItem,
                'total_qty'      => $totalQty,
                'total_nilai'    => $totalNilai,
                'keterangan'     => $request->keterangan ?? ('Import Excel Persediaan Awal: ' . $request->file('file_excel')->getClientOriginalName()),
                'status'         => 'posted',
                'created_by'     => Auth::id() ?? 1,
            ]);

            $defaultSupplierId  = DB::table('suppliers')->value('id') ?? 1;
            $defaultPembelianId = DB::table('pembelian')->value('id') ?? 1;
            $defaultPemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

            $surplusDebits = [];
            $totalKredit   = 0;

            foreach ($validItems as $item) {
                $barang = $item['barang'];
                $satuanStok = $barang->satuan ?? 'pcs';
                $batchNumber = 'SA-' . date('Ymd', strtotime($tanggal)) . '-' . ($barang->kode_barang ?? $item['barang_id']);

                PersediaanAwalDetail::create([
                    'persediaan_awal_id' => $persediaanAwal->id,
                    'barang_id'          => $item['barang_id'],
                    'qty'                => $item['qty_stok'],
                    'satuan'             => $satuanStok,
                    'satuan_pembelian'   => $item['satuan_pembelian'],
                    'konversi_pembelian' => $item['konversi_pembelian'],
                    'qty_pembelian'      => $item['qty_input'],
                    'harga_pembelian'    => $item['harga_input'],
                    'harga_satuan'       => $item['harga_stok'],
                    'total_nilai'        => $item['total_nilai'],
                    'batch_number'       => $batchNumber,
                ]);

                // Update Stok Gudang
                $stokQuery = StokGudang::where('barang_id', $item['barang_id'])
                    ->where('gudang_id', $request->gudang_id);
                if ($request->divisi_id) {
                    $stokQuery->where('divisi_id', $request->divisi_id);
                } else {
                    $stokQuery->whereNull('divisi_id');
                }

                $stokGudang = $stokQuery->lockForUpdate()->first();
                if ($stokGudang) {
                    if ($item['qty_stok'] > 0) {
                        $stokGudang->increment('jumlah', $item['qty_stok']);
                    }
                } else {
                    StokGudang::create([
                        'barang_id' => $item['barang_id'],
                        'gudang_id' => $request->gudang_id,
                        'divisi_id' => $request->divisi_id,
                        'jumlah'    => $item['qty_stok'],
                    ]);
                }

                // Buat Batch FIFO jika kuantitas masuk > 0
                if ($item['qty_stok'] > 0) {
                    StokGudangBatch::create([
                        'gudang_id'           => $request->gudang_id,
                        'divisi_id'           => $request->divisi_id,
                        'supplier_id'         => $defaultSupplierId,
                        'barang_id'           => $item['barang_id'],
                        'pembelian_id'        => $defaultPembelianId,
                        'pembelian_detail_id' => $defaultPemDetailId,
                        'batch_number'        => $batchNumber,
                        'qty_masuk'           => $item['qty_stok'],
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $item['qty_stok'],
                        'harga_per_qty'       => $item['harga_stok'],
                        'is_habis'            => false,
                    ]);

                    // Catat Transaksi Stok
                    TransaksiStok::create([
                        'tanggal'          => $tanggal . ' ' . date('H:i:s'),
                        'tipe'             => 'masuk',
                        'source_type'      => 'saldo_awal',
                        'source_id'        => $persediaanAwal->id,
                        'gudang_tujuan_id' => $request->gudang_id,
                        'divisi_tujuan_id' => $request->divisi_id,
                        'barang_id'        => $item['barang_id'],
                        'qty'              => $item['qty_stok'],
                        'total_harga'      => $item['total_nilai'],
                        'created_by'       => Auth::id() ?? 1,
                    ]);
                }

                if (($barang->hpp_referensi == 0 || empty($barang->hpp_referensi)) && $item['harga_stok'] > 0) {
                    $barang->update(['hpp_referensi' => $item['harga_stok']]);
                }

                if ($item['total_nilai'] > 0) {
                    $isOperational = $barang && ($barang->is_operational || (!$barang->is_bahan_baku && !$barang->is_bahan_setengah_jadi && !$barang->is_barang_jadi));
                    $coaCode = $isOperational ? '1501' : '1301';
                    $idPersediaan = DB::table('chart_of_accounts')->where('kode', $coaCode)->value('id') ?? ($isOperational ? 27 : 19);

                    if (!isset($surplusDebits[$idPersediaan])) {
                        $surplusDebits[$idPersediaan] = 0;
                    }
                    $surplusDebits[$idPersediaan] += $item['total_nilai'];
                    $totalKredit += $item['total_nilai'];
                }
            }

            // Jurnal Penyesuaian
            if ($totalKredit > 0) {
                $idEkuitas = DB::table('chart_of_accounts')->where('kode', '3101')->value('id')
                          ?? DB::table('chart_of_accounts')->where('kode', '3103')->value('id')
                          ?? 30;

                $jp = JurnalPenyesuaian::create([
                    'tanggal'     => $tanggal,
                    'deskripsi'   => "[Saldo Awal Import] Persediaan Awal: {$kodeTransaksi} ({$gudang->nama})",
                    'no_ref'      => 'AJP-SA-' . $kodeTransaksi,
                    'source_type' => 'saldo_awal',
                    'source_id'   => $persediaanAwal->id,
                    'created_by'  => Auth::id() ?? 1,
                    'status'      => 'approved',
                ]);

                foreach ($surplusDebits as $accId => $debitAmount) {
                    $jp->details()->create([
                        'account_id'   => $accId,
                        'debit'        => round($debitAmount, 2),
                        'kredit'       => 0,
                        'journal_type' => JurnalPenyesuaian::class,
                    ]);
                }

                $jp->details()->create([
                    'account_id'   => $idEkuitas,
                    'debit'        => 0,
                    'kredit'       => round($totalKredit, 2),
                    'journal_type' => JurnalPenyesuaian::class,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('persediaan-awal.show', $persediaanAwal->id)
                ->with('success', "Import Persediaan Awal berhasil! {$totalItem} barang dicatat dengan total nilai Rp " . number_format($totalNilai, 0, ',', '.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal import Excel: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY: Hapus Transaksi Persediaan Awal
    |--------------------------------------------------------------------------
    */
    public function destroy(string $id)
    {
        $persediaanAwal = PersediaanAwal::with('details')->findOrFail($id);

        if (Journal::isPeriodClosed($persediaanAwal->tanggal->format('Y-m-d'))) {
            return back()->with('error', 'Periode akuntansi sudah ditutup buku. Data tidak dapat dihapus.');
        }

        DB::beginTransaction();
        try {
            // Hapus Jurnal terkait
            $jp = JurnalPenyesuaian::where('source_type', 'saldo_awal')
                ->where('source_id', $persediaanAwal->id)
                ->first();
            if ($jp) {
                $jp->details()->delete();
                $jp->delete();
            }

            // Hapus Transaksi Stok & Revert Stok
            foreach ($persediaanAwal->details as $detail) {
                // Kurangi stok di stok_gudang
                $stok = StokGudang::where('barang_id', $detail->barang_id)
                    ->where('gudang_id', $persediaanAwal->gudang_id);
                if ($persediaanAwal->divisi_id) {
                    $stok->where('divisi_id', $persediaanAwal->divisi_id);
                } else {
                    $stok->whereNull('divisi_id');
                }
                $stokRecord = $stok->first();
                if ($stokRecord) {
                    $stokRecord->decrement('jumlah', min($stokRecord->jumlah, $detail->qty));
                }

                // Hapus Batch FIFO terkait jika belum terkonsumsi
                StokGudangBatch::where('gudang_id', $persediaanAwal->gudang_id)
                    ->where('barang_id', $detail->barang_id)
                    ->where('batch_number', $detail->batch_number)
                    ->delete();
            }

            TransaksiStok::where('source_type', 'saldo_awal')
                ->where('source_id', $persediaanAwal->id)
                ->delete();

            $persediaanAwal->details()->delete();
            $persediaanAwal->delete();

            DB::commit();

            return redirect()
                ->route('persediaan-awal.index')
                ->with('success', 'Data Persediaan Awal berhasil dihapus dan stok telah disesuaikan kembali.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
