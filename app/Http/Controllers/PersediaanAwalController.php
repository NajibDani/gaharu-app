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
     * Helper untuk parsing string format angka desimal/Indonesia
     */
    private function parseFormattedNumber($value): float
    {
        if (is_null($value) || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $clean = trim($value);
            if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else if (strpos($clean, ',') !== false) {
                $clean = str_replace(',', '.', $clean);
            }
            return (float) $clean;
        }
        return 0.0;
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
        if ($request->filled('items_json')) {
            $items = json_decode($request->items_json, true);
            if (is_array($items)) {
                $barangIds = [];
                $qtys = [];
                $hargas = [];
                $satuanTipes = [];
                foreach ($items as $item) {
                    $barangIds[] = $item['barang_id'] ?? null;
                    $qtys[] = $item['qty'] ?? 0;
                    $hargas[] = $item['harga_satuan'] ?? 0;
                    $satuanTipes[] = $item['satuan_tipe'] ?? 'pembelian';
                }
                $request->merge([
                    'barang_id'    => $barangIds,
                    'qty'          => $qtys,
                    'harga_satuan' => $hargas,
                    'satuan_tipe'  => $satuanTipes,
                ]);
            }
        }

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

                $satuanStok = $barang->satuan ?: 'pcs';
                $satuanBeli = $barang->satuan_pembelian ?: $satuanStok;
                $satuanTipe = $request->satuan_tipe[$index] ?? 'pembelian';
                $isPembelian = ($satuanTipe === 'pembelian');

                $multiplier = $isPembelian ? $konversi : 1.00;
                $qtyStok = $qtyInput * $multiplier;

                if ($isGudangUtama) {
                    $hargaInput = (float) str_replace(',', '.', $request->harga_satuan[$index] ?? 0);
                    $hargaStok = $multiplier > 0 ? (max(0, $hargaInput) / $multiplier) : max(0, $hargaInput);
                } else {
                    // Gudang non-Utama: harga otomatis mengikuti harga referensi Gudang Utama
                    $hargaStok = (float) ($hargaUtamaMap[$barangId] ?? ($barang->hpp_referensi ?? 0));
                    $hargaInput = $hargaStok * $multiplier;
                }

                $totalNilai = round($qtyInput * max(0, $hargaInput), 2);

                $validItems[] = [
                    'barang_id'          => $barangId,
                    'barang'             => $barang,
                    'qty_input'          => $qtyInput,
                    'harga_input'        => max(0, $hargaInput),
                    'satuan_dipilih'     => $isPembelian ? $satuanBeli : $satuanStok,
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
    | EDIT: Form Koreksi Persediaan Awal (Khusus Super Admin)
    |--------------------------------------------------------------------------
    */
    public function edit(string $id)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Akses terbatas. Hanya Super Admin yang diizinkan mengedit transaksi persediaan awal.');
        }

        $persediaanAwal = PersediaanAwal::with([
            'gudang.divisi',
            'divisi',
            'details.barang.kategori',
        ])->findOrFail($id);

        if (Journal::isPeriodClosed($persediaanAwal->tanggal->format('Y-m-d'))) {
            return redirect()
                ->route('persediaan-awal.show', $persediaanAwal->id)
                ->with('error', 'Periode akuntansi sudah ditutup buku. Data transaksi tidak dapat diedit.');
        }

        $hargaUtamaMap = $this->getHargaGudangUtamaMap();

        $detailsData = [];
        foreach ($persediaanAwal->details as $d) {
            $barang = $d->barang;
            if (!$barang) continue;

            $konv = (float)($d->konversi_pembelian ?: ($barang->konversi_pembelian ?: 1.00));
            if ($konv <= 0) $konv = 1.00;

            $satStok = $d->satuan ?: ($barang->satuan ?: 'pcs');
            $satBeli = $d->satuan_pembelian ?: ($barang->satuan_pembelian ?: $satStok);

            $hasKonv = $satBeli && $konv > 1 && ($satBeli !== $satStok);

            $qtyInput = $d->qty_pembelian !== null ? (float)$d->qty_pembelian : ($hasKonv ? round((float)$d->qty / $konv, 2) : (float)$d->qty);
            $hargaInput = $d->harga_pembelian !== null ? (float)$d->harga_pembelian : ($hasKonv ? round((float)$d->harga_satuan * $konv, 2) : (float)$d->harga_satuan);
            $satuanTipe = ($hasKonv && $d->qty_pembelian !== null) ? 'pembelian' : 'utama';

            $hrgStokUtama = (float)($hargaUtamaMap[$barang->id] ?? ($barang->hpp_referensi ?? 0));
            $hrgBeliUtama = $hrgStokUtama * $konv;

            $detailsData[] = [
                'barang_id'          => $barang->id,
                'kode_barang'        => $barang->kode_barang,
                'nama'               => $barang->nama,
                'kategori_id'        => $barang->kategori_id,
                'kategori_nama'      => $barang->kategori->nama ?? '-',
                'satuan'             => $satStok,
                'satuan_pembelian'   => $satBeli,
                'konversi_pembelian' => $konv,
                'qty_input'          => $qtyInput,
                'satuan_tipe'        => $satuanTipe,
                'harga_input'        => $hargaInput,
                'harga_stok_utama'   => $hrgStokUtama,
                'harga_beli_utama'   => $hrgBeliUtama,
            ];
        }

        $allBarang = MasterBarang::with('kategori')
            ->where('is_active', true)
            ->orderBy('nama', 'asc')
            ->get();

        return view('persediaan-awal.edit', compact(
            'persediaanAwal',
            'detailsData',
            'allBarang',
            'hargaUtamaMap'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE: Simpan Perubahan Persediaan Awal (Khusus Super Admin)
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Akses terbatas. Hanya Super Admin yang diizinkan mengedit transaksi persediaan awal.');
        }

        $persediaanAwal = PersediaanAwal::with('details')->findOrFail($id);

        if (Journal::isPeriodClosed($persediaanAwal->tanggal->format('Y-m-d'))) {
            return back()->with('error', 'Periode akuntansi tanggal transaksi lama sudah ditutup buku. Tidak dapat diubah.');
        }

        if ($request->filled('items_json')) {
            $items = json_decode($request->items_json, true);
            if (is_array($items)) {
                $barangIds = [];
                $qtys = [];
                $hargas = [];
                $satuanTipes = [];
                foreach ($items as $item) {
                    $barangIds[] = $item['barang_id'] ?? null;
                    $qtys[] = $item['qty'] ?? 0;
                    $hargas[] = $item['harga_satuan'] ?? 0;
                    $satuanTipes[] = $item['satuan_tipe'] ?? 'pembelian';
                }
                $request->merge([
                    'barang_id'    => $barangIds,
                    'qty'          => $qtys,
                    'harga_satuan' => $hargas,
                    'satuan_tipe'  => $satuanTipes,
                ]);
            }
        }

        $request->validate([
            'tanggal'        => 'required|date',
            'barang_id'      => 'required|array|min:1',
            'barang_id.*'    => 'required|exists:master_barang,id',
            'qty'            => 'required|array|min:1',
            'harga_satuan'   => 'required|array|min:1',
            'keterangan'     => 'nullable|string|max:500',
        ]);

        $tanggalBaru = date('Y-m-d', strtotime($request->tanggal));
        if (Journal::isPeriodClosed($tanggalBaru)) {
            return back()->withErrors([
                'tanggal' => 'Periode akuntansi tanggal baru ' . date('d/m/Y', strtotime($tanggalBaru)) . ' sudah ditutup buku.',
            ])->withInput();
        }

        $gudang = MasterGudang::findOrFail($persediaanAwal->gudang_id);
        $gudangId = $persediaanAwal->gudang_id;
        $divisiId = $persediaanAwal->divisi_id;

        $gudangUtama = MasterGudang::where('kategori', 'Utama')->orWhere('nama', 'like', '%Gudang Utama%')->first() ?? MasterGudang::find(2);
        $gudangUtamaId = $gudangUtama ? $gudangUtama->id : 2;
        $isGudangUtama = ($gudangId == $gudangUtamaId || strtolower($gudang->kategori) === 'utama');

        $hargaUtamaMap = $this->getHargaGudangUtamaMap();

        // 1. Kumpulkan semua input item yang dikirimkan oleh form HTML
        $submittedMap = [];
        if (!empty($request->barang_id) && is_array($request->barang_id)) {
            foreach ($request->barang_id as $idx => $bId) {
                $qtyRaw = $request->qty[$idx] ?? 0;
                $hargaRaw = $request->harga_satuan[$idx] ?? 0;
                $satuanTipe = $request->satuan_tipe[$idx] ?? 'pembelian';

                $submittedMap[$bId] = [
                    'index'       => $idx,
                    'qty_input'   => $this->parseFormattedNumber($qtyRaw),
                    'harga_input' => $this->parseFormattedNumber($hargaRaw),
                    'satuan_tipe' => $satuanTipe,
                ];
            }
        }

        $oldDetailsMap = $persediaanAwal->details->keyBy('barang_id');
        $qtyErrors = [];

        // Validasi A: Item lama yang BENAR-BENAR dihapus dari tabel (baris HTML/DOM dihapus oleh user)
        foreach ($oldDetailsMap as $oldBarangId => $oldDetail) {
            if (!isset($submittedMap[$oldBarangId])) {
                if ($oldDetail->batch_number) {
                    $existingBatch = StokGudangBatch::where('gudang_id', $gudangId)
                        ->where('barang_id', $oldBarangId)
                        ->where('batch_number', $oldDetail->batch_number)
                        ->first();

                    if ($existingBatch && (float)$existingBatch->qty_keluar > 0) {
                        $barangNama = $oldDetail->barang->nama ?? 'Barang #' . $oldBarangId;
                        $satuanStok = $oldDetail->barang->satuan ?? 'pcs';
                        $qtyKeluar = (float) $existingBatch->qty_keluar;
                        $qtyErrors[] = "Barang \"{$barangNama}\" tidak dapat dihapus dari daftar karena stoknya sudah terpakai sebanyak {$qtyKeluar} {$satuanStok}.";
                    }
                }
            }
        }

        // Validasi B: Item yang MASIH ADA di tabel, tetapi Qty diubah menjadi kurang dari stok terpakai
        foreach ($submittedMap as $bId => $sub) {
            $oldDetail = $oldDetailsMap->get($bId);
            if ($oldDetail && $oldDetail->batch_number) {
                $existingBatch = StokGudangBatch::where('gudang_id', $gudangId)
                    ->where('barang_id', $bId)
                    ->where('batch_number', $oldDetail->batch_number)
                    ->first();

                if ($existingBatch && (float)$existingBatch->qty_keluar > 0) {
                    $barang = MasterBarang::find($bId);
                    if ($barang) {
                        $konversi = (float) ($barang->konversi_pembelian ?: 1.00);
                        if ($konversi <= 0) $konversi = 1.00;

                        $satuanStok = $barang->satuan ?: 'pcs';
                        $satuanBeli = $barang->satuan_pembelian ?: $satuanStok;
                        $isPembelian = ($sub['satuan_tipe'] === 'pembelian');
                        $multiplier = $isPembelian ? $konversi : 1.00;

                        $qtyStokSubmitted = $sub['qty_input'] * $multiplier;
                        $qtyKeluar = (float) $existingBatch->qty_keluar;

                        if ($qtyStokSubmitted < $qtyKeluar) {
                            $barangNama = $barang->nama ?? 'Barang #' . $bId;
                            if ($isPembelian && $konversi > 1) {
                                $minInput = ceil($qtyKeluar / $konversi);
                                $qtyErrors[] = "Qty Persediaan Awal untuk \"{$barangNama}\" tidak boleh kurang dari stok yang sudah terpakai ({$qtyKeluar} {$satuanStok}). Qty yang diinput ({$sub['qty_input']} {$satuanBeli}), minimal input adalah {$minInput} {$satuanBeli}.";
                            } else {
                                $qtyErrors[] = "Qty Persediaan Awal untuk \"{$barangNama}\" tidak boleh kurang dari stok yang sudah terpakai ({$qtyKeluar} {$satuanStok}). Qty yang diinput ({$qtyStokSubmitted} {$satuanStok}) kurang dari stok terpakai ({$qtyKeluar} {$satuanStok}).";
                            }
                        }
                    }
                }
            }
        }

        if (!empty($qtyErrors)) {
            return back()->withErrors([
                'error' => implode(' ', $qtyErrors),
            ])->withInput();
        }

        // 2. Kalkulasi valid items (item dengan Qty > 0)
        $validItems = [];
        foreach ($submittedMap as $bId => $sub) {
            $qtyInput = $sub['qty_input'];

            if ($qtyInput > 0) {
                $barang = MasterBarang::find($bId);
                if (!$barang) continue;

                $oldDetail = $oldDetailsMap->get($bId);

                $konversi = (float) ($barang->konversi_pembelian ?: 1.00);
                if ($konversi <= 0) $konversi = 1.00;

                $satuanStok = $barang->satuan ?: 'pcs';
                $satuanBeli = $barang->satuan_pembelian ?: $satuanStok;
                $satuanTipe = $sub['satuan_tipe'];
                $isPembelian = ($satuanTipe === 'pembelian');

                // Cek apakah item ini tidak diubah nilainya oleh user dibanding detail lama
                $isUnchanged = false;
                if ($oldDetail) {
                    $hasKonvOld = $oldDetail->satuan_pembelian && ($oldDetail->konversi_pembelian ?: $konversi) > 1 && ($oldDetail->satuan_pembelian !== $oldDetail->satuan);
                    $oldQtyInput = $oldDetail->qty_pembelian !== null ? (float)$oldDetail->qty_pembelian : ($hasKonvOld ? round((float)$oldDetail->qty / ($oldDetail->konversi_pembelian ?: $konversi), 2) : (float)$oldDetail->qty);
                    $oldHargaInput = $oldDetail->harga_pembelian !== null ? (float)$oldDetail->harga_pembelian : ($hasKonvOld ? round((float)$oldDetail->harga_satuan * ($oldDetail->konversi_pembelian ?: $konversi), 2) : (float)$oldDetail->harga_satuan);
                    $oldSatuanTipe = ($hasKonvOld && $oldDetail->qty_pembelian !== null) ? 'pembelian' : 'utama';

                    if (
                        abs($qtyInput - $oldQtyInput) < 0.0001 &&
                        abs($sub['harga_input'] - $oldHargaInput) < 0.01 &&
                        $satuanTipe === $oldSatuanTipe
                    ) {
                        $isUnchanged = true;
                    }
                }

                if ($isUnchanged && $oldDetail) {
                    // JIKA TIDAK DIEDIT: Pertahankan nilai asli 100% tanpa pembulatan ulang / recalculate
                    $qtyStok   = (float)$oldDetail->qty;
                    $hargaStok = (float)$oldDetail->harga_satuan;
                    $hargaInput = $oldDetail->harga_pembelian !== null ? (float)$oldDetail->harga_pembelian : $oldHargaInput;
                    $totalNilai = (float)$oldDetail->total_nilai;
                    $satuanDipilih = $oldDetail->satuan_pembelian ?: $satuanStok;
                    $konversiDipakai = (float)($oldDetail->konversi_pembelian ?: $konversi);
                } else {
                    // JIKA DIEDIT ATAU ITEM BARU: Hitung nilai baru
                    $multiplier = $isPembelian ? $konversi : 1.00;
                    $qtyStok = $qtyInput * $multiplier;

                    if ($isGudangUtama) {
                        $hargaInput = $sub['harga_input'];
                        $hargaStok = $multiplier > 0 ? (max(0, $hargaInput) / $multiplier) : max(0, $hargaInput);
                    } else {
                        // Jika ada input harga dari form > 0, gunakan input harga tersebut, jika tidak gunakan harga utama
                        if ($sub['harga_input'] > 0) {
                            $hargaInput = $sub['harga_input'];
                            $hargaStok = $multiplier > 0 ? (max(0, $hargaInput) / $multiplier) : max(0, $hargaInput);
                        } else {
                            $hargaStok = (float) ($hargaUtamaMap[$bId] ?? ($barang->hpp_referensi ?? 0));
                            $hargaInput = $hargaStok * $multiplier;
                        }
                    }

                    $totalNilai = round($qtyInput * max(0, $hargaInput), 2);
                    $satuanDipilih = $isPembelian ? $satuanBeli : $satuanStok;
                    $konversiDipakai = $konversi;
                }

                $validItems[] = [
                    'barang_id'          => $bId,
                    'barang'             => $barang,
                    'qty_input'          => $qtyInput,
                    'harga_input'        => max(0, $hargaInput),
                    'satuan_dipilih'     => $satuanDipilih,
                    'satuan_pembelian'   => $satuanBeli,
                    'konversi_pembelian' => $konversiDipakai,
                    'qty_stok'           => $qtyStok,
                    'harga_stok'         => $hargaStok,
                    'total_nilai'        => $totalNilai,
                    'is_unchanged'       => $isUnchanged,
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
            // Map detail lama untuk perbandingan stok dan pembaruan batch
            $oldDetailsMap = $persediaanAwal->details->keyBy('barang_id');
            $processedBarangIds = [];

            // Hapus mutasi TransaksiStok lama
            TransaksiStok::where('source_type', 'saldo_awal')
                ->where('source_id', $persediaanAwal->id)
                ->delete();

            // Hapus detail lama persediaan_awal_detail
            $persediaanAwal->details()->delete();

            // TERAPKAN DATA BARU
            $totalItem  = count($validItems);
            $totalQty   = array_sum(array_column($validItems, 'qty_stok'));
            $totalNilai = array_sum(array_column($validItems, 'total_nilai'));

            $defaultSupplierId  = DB::table('suppliers')->value('id') ?? 1;
            $defaultPembelianId = DB::table('pembelian')->value('id') ?? 1;
            $defaultPemDetailId = DB::table('pembelian_detail')->value('id') ?? 1;

            $surplusDebits = [];
            $totalKredit   = 0;

            foreach ($validItems as $item) {
                $barang = $item['barang'];
                $barangId = $item['barang_id'];
                $processedBarangIds[] = $barangId;
                $satuanStok = $barang->satuan ?? 'pcs';

                $oldDetail = $oldDetailsMap->get($barangId);
                $oldQtyStok = $oldDetail ? (float)$oldDetail->qty : 0.0;
                $deltaQty = $item['qty_stok'] - $oldQtyStok;

                // Tentukan batch_number: gunakan batch_number lama jika ada agar riwayat terhubung
                $batchNumber = ($oldDetail && $oldDetail->batch_number)
                    ? $oldDetail->batch_number
                    : ('SA-' . date('Ymd', strtotime($tanggalBaru)) . '-' . ($barang->kode_barang ?? $barangId));

                // 1. Simpan detail baru
                PersediaanAwalDetail::create([
                    'persediaan_awal_id' => $persediaanAwal->id,
                    'barang_id'          => $barangId,
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

                // 2. Adjust stok fisik di stok_gudang (selisih delta)
                $stokQuery = StokGudang::where('barang_id', $barangId)
                    ->where('gudang_id', $gudangId);
                if ($divisiId) {
                    $stokQuery->where('divisi_id', $divisiId);
                } else {
                    $stokQuery->whereNull('divisi_id');
                }

                $stokGudang = $stokQuery->lockForUpdate()->first();
                if ($stokGudang) {
                    if ($deltaQty > 0) {
                        $stokGudang->increment('jumlah', $deltaQty);
                    } elseif ($deltaQty < 0) {
                        $stokGudang->decrement('jumlah', min($stokGudang->jumlah, abs($deltaQty)));
                    }
                } else {
                    StokGudang::create([
                        'barang_id' => $barangId,
                        'gudang_id' => $gudangId,
                        'divisi_id' => $divisiId,
                        'jumlah'    => max(0, $item['qty_stok']),
                    ]);
                }

                // 3. Update atau buat Batch FIFO baru di stok_gudang_batch
                $existingBatch = null;
                if ($oldDetail && $oldDetail->batch_number) {
                    $existingBatch = StokGudangBatch::where('gudang_id', $gudangId)
                        ->where('barang_id', $barangId)
                        ->where('batch_number', $oldDetail->batch_number)
                        ->first();
                }

                if ($existingBatch) {
                    $existingQtyKeluar = (float) $existingBatch->qty_keluar;
                    $newQtySisa = max(0, $item['qty_stok'] - $existingQtyKeluar);
                    $existingBatch->update([
                        'qty_masuk'     => $item['qty_stok'],
                        'qty_sisa'      => $newQtySisa,
                        'harga_per_qty' => $item['harga_stok'],
                        'is_habis'      => ($newQtySisa <= 0),
                    ]);
                    $batchId = $existingBatch->id;
                } else {
                    $newBatch = StokGudangBatch::create([
                        'gudang_id'           => $gudangId,
                        'divisi_id'           => $divisiId,
                        'supplier_id'         => $defaultSupplierId,
                        'barang_id'           => $barangId,
                        'pembelian_id'        => $defaultPembelianId,
                        'pembelian_detail_id' => $defaultPemDetailId,
                        'batch_number'        => $batchNumber,
                        'qty_masuk'           => $item['qty_stok'],
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $item['qty_stok'],
                        'harga_per_qty'       => $item['harga_stok'],
                        'is_habis'            => false,
                    ]);
                    $batchId = $newBatch->id;
                }

                // 4. Update otomatis harga & HPP pada transaksi Pengeluaran Bahan Baku yang sudah menggunakan batch ini
                $fifoRecords = \App\Models\PengeluaranBahanBakuFifo::where('batch_id', $batchId)
                    ->orWhere('batch_number', $batchNumber)
                    ->get();

                $affectedDetailIds = [];
                foreach ($fifoRecords as $fifo) {
                    $fifoTotal = round((float)$fifo->qty_keluar * $item['harga_stok'], 2);
                    $fifo->update([
                        'harga_per_qty' => $item['harga_stok'],
                        'total_harga'   => $fifoTotal,
                    ]);
                    $affectedDetailIds[] = $fifo->detail_id;
                }

                foreach (array_unique($affectedDetailIds) as $detId) {
                    $pbbDetail = \App\Models\PengeluaranBahanBakuDetail::find($detId);
                    if ($pbbDetail) {
                        $newHppTotal = \App\Models\PengeluaranBahanBakuFifo::where('detail_id', $detId)->sum('total_harga');
                        $avgHarga = $pbbDetail->qty > 0 ? ($newHppTotal / $pbbDetail->qty) : 0;
                        $pbbDetail->update([
                            'harga_satuan' => $avgHarga,
                            'total_harga'  => $newHppTotal,
                            'hpp_total'    => $newHppTotal,
                        ]);
                    }
                }

                // Update juga batch turunan hasil mutasi (misalnya batch_number dengan suffix -MUT)
                StokGudangBatch::where('batch_number', 'like', $batchNumber . '%')
                    ->where('id', '!=', $batchId)
                    ->update(['harga_per_qty' => $item['harga_stok']]);

                // 5. Catat Transaksi Stok baru
                TransaksiStok::create([
                    'tanggal'          => $tanggalBaru . ' ' . date('H:i:s'),
                    'tipe'             => 'masuk',
                    'source_type'      => 'saldo_awal',
                    'source_id'        => $persediaanAwal->id,
                    'gudang_tujuan_id' => $gudangId,
                    'divisi_tujuan_id' => $divisiId,
                    'barang_id'        => $barangId,
                    'qty'              => $item['qty_stok'],
                    'total_harga'      => $item['total_nilai'],
                    'created_by'       => Auth::id() ?? 1,
                ]);

                // Update HPP referensi di master barang jika bernilai > 0 (hanya untuk barang yang diedit)
                if (empty($item['is_unchanged']) && $item['harga_stok'] > 0) {
                    $barang->update(['hpp_referensi' => $item['harga_stok']]);
                }

                // Akun persediaan
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

            // Tangani item lama yang dihapus dari form edit (tidak ada lagi di validItems)
            foreach ($oldDetailsMap as $oldBarangId => $oldDetail) {
                if (!in_array($oldBarangId, $processedBarangIds)) {
                    $oldBatch = StokGudangBatch::where('gudang_id', $gudangId)
                        ->where('barang_id', $oldBarangId)
                        ->where('batch_number', $oldDetail->batch_number)
                        ->first();

                    $stokKurang = $oldDetail->qty;
                    if ($oldBatch) {
                        if ($oldBatch->qty_keluar > 0) {
                            $stokKurang = max(0, $oldDetail->qty - $oldBatch->qty_keluar);
                            $oldBatch->update([
                                'qty_masuk' => $oldBatch->qty_keluar,
                                'qty_sisa'  => 0,
                                'is_habis'  => true,
                            ]);
                        } else {
                            $oldBatch->delete();
                        }
                    }

                    if ($stokKurang > 0) {
                        $stok = StokGudang::where('barang_id', $oldBarangId)
                            ->where('gudang_id', $gudangId);
                        if ($divisiId) {
                            $stok->where('divisi_id', $divisiId);
                        } else {
                            $stok->whereNull('divisi_id');
                        }
                        $stokRecord = $stok->first();
                        if ($stokRecord) {
                            $stokRecord->decrement('jumlah', min($stokRecord->jumlah, $stokKurang));
                        }
                    }
                }
            }

            // 4. Update Header Transaksi
            $persediaanAwal->update([
                'tanggal'     => $tanggalBaru,
                'total_item'  => $totalItem,
                'total_qty'   => $totalQty,
                'total_nilai' => $totalNilai,
                'keterangan'  => $request->keterangan ?? $persediaanAwal->keterangan,
            ]);

            // 5. Update Jurnal Penyesuaian Terkait
            $jp = JurnalPenyesuaian::where('source_type', 'saldo_awal')
                ->where('source_id', $persediaanAwal->id)
                ->first();

            if ($totalKredit > 0) {
                $idEkuitas = DB::table('chart_of_accounts')->where('kode', '3101')->value('id')
                          ?? DB::table('chart_of_accounts')->where('kode', '3103')->value('id')
                          ?? 30;

                if (!$jp) {
                    $jp = JurnalPenyesuaian::create([
                        'tanggal'     => $tanggalBaru,
                        'deskripsi'   => "[Saldo Awal Koreksi] Persediaan Awal: {$persediaanAwal->kode_transaksi} ({$gudang->nama})",
                        'no_ref'      => 'AJP-SA-' . $persediaanAwal->kode_transaksi,
                        'source_type' => 'saldo_awal',
                        'source_id'   => $persediaanAwal->id,
                        'created_by'  => Auth::id() ?? 1,
                        'status'      => 'approved',
                    ]);
                } else {
                    $jp->update([
                        'tanggal'   => $tanggalBaru,
                        'deskripsi' => "[Saldo Awal Koreksi] Persediaan Awal: {$persediaanAwal->kode_transaksi} ({$gudang->nama})",
                    ]);
                    $jp->details()->delete();
                }

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
            } elseif ($jp) {
                $jp->details()->delete();
                $jp->delete();
            }

            DB::commit();

            return redirect()
                ->route('persediaan-awal.show', $persediaanAwal->id)
                ->with('success', "Perubahan data Persediaan Awal ({$persediaanAwal->kode_transaksi}) berhasil disimpan. Posisi stok gudang, batch FIFO, dan jurnal penyesuaian telah disesuaikan.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui Persediaan Awal: ' . $e->getMessage())->withInput();
        }
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
                $batch = StokGudangBatch::where('gudang_id', $persediaanAwal->gudang_id)
                    ->where('barang_id', $detail->barang_id)
                    ->where('batch_number', $detail->batch_number)
                    ->first();

                $stokKurang = $detail->qty;
                if ($batch) {
                    if ($batch->qty_keluar > 0) {
                        // Jika sudah ada yang terpakai, pertahankan batch sebesar porsi yang terpakai
                        $stokKurang = max(0, $detail->qty - $batch->qty_keluar);
                        $batch->update([
                            'qty_masuk' => $batch->qty_keluar,
                            'qty_sisa'  => 0,
                            'is_habis'  => true,
                        ]);
                    } else {
                        // Jika belum terpakai sama sekali, hapus batch
                        $batch->delete();
                    }
                }

                if ($stokKurang > 0) {
                    // Kurangi stok di stok_gudang sebesar sisa porsi yang belum terpakai
                    $stok = StokGudang::where('barang_id', $detail->barang_id)
                        ->where('gudang_id', $persediaanAwal->gudang_id);
                    if ($persediaanAwal->divisi_id) {
                        $stok->where('divisi_id', $persediaanAwal->divisi_id);
                    } else {
                        $stok->whereNull('divisi_id');
                    }
                    $stokRecord = $stok->first();
                    if ($stokRecord) {
                        $stokRecord->decrement('jumlah', min($stokRecord->jumlah, $stokKurang));
                    }
                }
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
