<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\Customer;
use App\Models\MasterBarang;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CentralKitchenOrderController extends Controller
{
    /**
     * Tampilkan daftar pesanan Central Kitchen
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Pesanan::centralKitchen()->with(['customer', 'details.produk', 'gudang']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_pesanan', 'like', '%' . $search . '%')
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('nama', 'like', '%' . $search . '%');
                  });
            });
        }

        $pesanan = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        foreach ($pesanan as $p) {
            $woDetail = WorkOrderDetail::where('pesanan_id', $p->id)->first();
            if ($woDetail) {
                $wo = WorkOrder::find($woDetail->work_order_id);
                $p->wo_status = $wo ? strtolower($wo->status_wo) : null;
            } else {
                $p->wo_status = null;
            }
        }

        $totalPesanan = Pesanan::centralKitchen()->count();
        $totalProses = Pesanan::centralKitchen()->whereIn('status_pesanan', ['Draft', 'Proses', 'Siap kirim', 'pending', 'ready', 'Diproses'])->count();
        $totalSelesai = Pesanan::centralKitchen()->where('status_pesanan', 'Selesai')->count();

        // Ambil data outlet operasional dari Master Gudang
        $customers = $this->getOutletCustomers();

        $produk = MasterBarang::where('is_active', true)
            ->where('is_bahan_setengah_jadi', true)
            ->orderBy('nama', 'asc')
            ->get();

        // Hitung ringkasan saran restock Bahan Setengah Jadi per outlet (di bawah minimum stock)
        $outletSuggestionsSummary = [];
        foreach ($customers as $c) {
            $g = null;
            $mField = null;
            $cName = strtolower($c->nama);
            if (str_contains($cName, 'gaharu')) {
                $g = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')->first();
                $mField = 'minimum_stock_gaharu';
            } elseif (str_contains($cName, 'kejingga')) {
                $g = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')->orWhere('nama', 'like', '%Kejingga%')->first();
                $mField = 'minimum_stock_kejingga';
            } else {
                $g = \App\Models\MasterGudang::find($c->gudang_id ?? 0) ?? \App\Models\MasterGudang::where('nama', 'like', '%' . $c->nama . '%')->first();
                $mField = 'minimum_stock';
            }

            if ($g && $mField) {
                $bsjItems = MasterBarang::where('is_active', true)
                    ->where('is_bahan_setengah_jadi', true)
                    ->whereNotNull($mField)
                    ->where($mField, '>', 0)
                    ->get();
                $deficitItems = [];
                foreach ($bsjItems as $it) {
                    $curStok = (float)(\App\Models\StokGudang::where('gudang_id', $g->id)->where('barang_id', $it->id)->value('jumlah') ?? 0);
                    $mStok = (float)$it->{$mField};
                    if ($curStok < $mStok) {
                        $deficitItems[] = [
                            'barang_id'     => $it->id,
                            'kode_barang'   => $it->kode_barang,
                            'nama'          => $it->nama,
                            'satuan'        => $it->satuan,
                            'current_stock' => $curStok,
                            'min_stock'     => $mStok,
                            'suggested_qty' => max(1, (float) ceil($mStok - $curStok)),
                        ];
                    }
                }
                if (!empty($deficitItems)) {
                    $outletSuggestionsSummary[] = [
                        'customer_id'   => $c->id,
                        'customer_nama' => $c->nama,
                        'gudang_id'     => $g->id,
                        'gudang_nama'   => $g->nama,
                        'count'         => count($deficitItems),
                        'items'         => $deficitItems,
                    ];
                }
            }
        }

        return view('central_kitchen.orders.index', compact('pesanan', 'totalPesanan', 'totalProses', 'totalSelesai', 'customers', 'produk', 'outletSuggestionsSummary'));
    }

    /**
     * Helper untuk mengambil data outlet dari Master Gudang
     */
    private function getOutletCustomers()
    {
        $outletGudangs = \App\Models\MasterGudang::where('kategori', 'Operasional')
            ->orWhere('nama', 'like', '%Gaharu%')
            ->orWhere('nama', 'like', '%KeJingga%')
            ->orWhere('nama', 'like', '%Kejingga%')
            ->orderBy('nama', 'asc')
            ->get();

        $customers = collect();
        foreach ($outletGudangs as $og) {
            $cName = str_starts_with($og->nama, 'Gudang ') ? 'Outlet ' . substr($og->nama, 7) : $og->nama;
            $customer = Customer::firstOrCreate(
                ['nama' => $cName],
                [
                    'jenis'  => 'Outlet Internal',
                    'no_hp'  => '-',
                    'alamat' => $og->nama,
                ]
            );
            $customer->gudang_id = $og->id;
            $customer->gudang_nama = $og->nama;
            $customers->push($customer);
        }

        if ($customers->isEmpty()) {
            $customers = Customer::where('jenis', 'Outlet Internal')
                ->orWhereIn('nama', ['Outlet Gaharu', 'Outlet KeJingga'])
                ->get();
        }

        return $customers;
    }

    /**
     * Mengambil saran Bahan Setengah Jadi di bawah batas minimum stock untuk Outlet tertentu (JSON)
     */
    public function suggestions(Request $request)
    {
        $customerId = $request->query('customer_id');
        $gudangId = $request->query('gudang_id');

        $customer = $customerId ? Customer::find($customerId) : null;
        $gudang = null;
        $minStockField = null;

        if ($gudangId) {
            $gudang = \App\Models\MasterGudang::find($gudangId);
        }

        if (!$gudang && $customer) {
            $customerName = strtolower($customer->nama);
            if (str_contains($customerName, 'gaharu')) {
                $gudang = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')->first();
            } elseif (str_contains($customerName, 'kejingga')) {
                $gudang = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')->orWhere('nama', 'like', '%Kejingga%')->first();
            } else {
                $gudang = \App\Models\MasterGudang::where('nama', 'like', '%' . $customer->nama . '%')->first()
                    ?? \App\Models\MasterGudang::where('kategori', 'Operasional')->first();
            }
        }

        if (!$gudang) {
            $outletName = $customer ? $customer->nama : '';
            return response()->json(['suggestions' => [], 'outlet_name' => $outletName]);
        }

        $gudangName = strtolower($gudang->nama);
        if (str_contains($gudangName, 'gaharu')) {
            $minStockField = 'minimum_stock_gaharu';
        } elseif (str_contains($gudangName, 'kejingga')) {
            $minStockField = 'minimum_stock_kejingga';
        } else {
            $minStockField = 'minimum_stock';
        }

        $items = MasterBarang::where('is_active', true)
            ->where('is_bahan_setengah_jadi', true)
            ->whereNotNull($minStockField)
            ->where($minStockField, '>', 0)
            ->orderBy('nama', 'asc')
            ->get();

        $suggestions = [];
        foreach ($items as $it) {
            $currentStock = (float) (\App\Models\StokGudang::where('gudang_id', $gudang->id)
                ->where('barang_id', $it->id)
                ->value('jumlah') ?? 0);
            $minStock = (float) $it->{$minStockField};

            if ($currentStock < $minStock) {
                $deficit = $minStock - $currentStock;
                $suggestedQty = max(1, (float) ceil($deficit));
                $suggestions[] = [
                    'barang_id'     => $it->id,
                    'kode_barang'   => $it->kode_barang,
                    'nama'          => $it->nama,
                    'satuan'        => $it->satuan,
                    'current_stock' => $currentStock,
                    'min_stock'     => $minStock,
                    'suggested_qty' => $suggestedQty,
                ];
            }
        }

        return response()->json([
            'outlet_name' => $customer ? $customer->nama : ($gudang->nama),
            'gudang_name' => $gudang->nama,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Form pembuatan Central Kitchen Order baru
     */
    public function create()
    {
        $customers = $this->getOutletCustomers();

        // Ambil barang aktif khusus Bahan Setengah Jadi (BSJ) untuk Central Kitchen Order
        $produk = MasterBarang::where('is_active', true)
            ->where('is_bahan_setengah_jadi', true)
            ->orderBy('nama', 'asc')
            ->get();

        return view('central_kitchen.orders.create', compact('customers', 'produk'));
    }

    /**
     * Simpan Central Kitchen Order baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => 'required',
            'tanggal'        => 'required|date',
            'estimasi_kirim' => 'required|date',
            'produk_id'      => 'required|array|min:1',
            'qty'            => 'required|array|min:1',
        ]);

        if (date('Y-m-d', strtotime($request->estimasi_kirim)) < date('Y-m-d', strtotime($request->tanggal))) {
            return redirect()->back()->withErrors(['estimasi_kirim' => 'Estimasi kirim tidak boleh sebelum tanggal pesanan.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $kode = 'CKO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // Tentukan gudang OUTLET TUJUAN berdasarkan customer pemesan
            $customer = Customer::findOrFail($request->customer_id);
            $custNama = strtolower($customer->nama);
            $gudangOutlet = null;

            if (str_contains($custNama, 'kejingga')) {
                $gudangOutlet = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')
                    ->orWhere('nama', 'like', '%Kejingga%')->first();
            } elseif (str_contains($custNama, 'gaharu')) {
                $gudangOutlet = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')
                    ->where('kategori', 'Operasional')->first();
            } else {
                $gudangOutlet = \App\Models\MasterGudang::where('kategori', 'Operasional')->first();
            }
            $gudangId = $gudangOutlet ? $gudangOutlet->id : null;

            $pesanan = Pesanan::create([
                'kode_pesanan'      => $request->kode_pesanan ?? $kode,
                'tipe_pesanan'      => 'central_kitchen',
                'customer_id'       => $request->customer_id,
                'tanggal'           => $request->tanggal,
                'estimasi_kirim'    => $request->estimasi_kirim,
                'estimasi_produksi' => $request->estimasi_produksi,
                'total_pesanan'     => 0.00, // Tanpa harga jual (HPP berpindah)
                'tax_percentage'    => 0,
                'tax_service'       => 0,
                'status_pesanan'    => 'pending',
                'status_pembayaran' => 'Lunas', // Bebas penagihan
                'created_by'        => auth()->id(),
                'gudang_id'         => $gudangId,
            ]);

            foreach ($request->produk_id as $key => $produkId) {
                if (!$produkId || floatval($request->qty[$key]) <= 0) continue;

                PesananDetail::create([
                    'pesanan_id' => $pesanan->id,
                    'produk_id'  => $produkId,
                    'qty'        => $request->qty[$key],
                    'harga'      => 0.00,
                    'subtotal'   => 0.00,
                ]);
            }

            DB::commit();
            return redirect()->route('ck-orders.index')->with('success', 'Central Kitchen Order berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan pesanan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Tampilkan detail Central Kitchen Order
     */
    public function show($id)
    {
        $pesanan = Pesanan::centralKitchen()->with(['customer', 'details.produk', 'gudang', 'creator'])->findOrFail($id);
        
        $woDetail = WorkOrderDetail::where('pesanan_id', $pesanan->id)->first();
        $workOrder = $woDetail ? WorkOrder::find($woDetail->work_order_id) : null;

        return view('central_kitchen.orders.show', compact('pesanan', 'workOrder'));
    }

    /**
     * Hapus Central Kitchen Order
     */
    public function destroy($id)
    {
        $pesanan = Pesanan::centralKitchen()->findOrFail($id);

        $sudahWO = WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        if ($sudahWO) {
            return redirect()->route('ck-orders.index')
                ->with('error', 'Gagal menghapus: Order ini sudah diproses dalam Work Order Produksi.');
        }

        PesananDetail::where('pesanan_id', $pesanan->id)->delete();
        $pesanan->delete();

        return redirect()->route('ck-orders.index')->with('success', 'Central Kitchen Order berhasil dihapus.');
    }

    /**
     * Cetak PDF Surat Pesanan CK
     */
    public function cetakPdf($id)
    {
        $pesanan = Pesanan::centralKitchen()->with(['customer', 'gudang', 'creator', 'details.produk'])->findOrFail($id);
        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
        $pdf->loadView('central_kitchen.orders.show-pdf', compact('pesanan'));
        return $pdf->stream('CK-Order-' . $pesanan->kode_pesanan . '.pdf');
    }
}
