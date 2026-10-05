<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HargaBarangB2b;
use App\Models\MasterBarang;
use App\Models\Customer;
use App\Services\FifoService;

class HargaBarangB2bController extends Controller
{
    /**
     * Tampilkan daftar harga jual B2B per Outlet Pemesan
     */
    public function index(Request $request)
    {
        HargaBarangB2b::ensureTableExists();

        $customerId = $request->query('customer_id');
        $search = $request->query('search');

        $query = HargaBarangB2b::with(['customer', 'barang']);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($search) {
            $query->whereHas('barang', function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode_barang', 'like', '%' . $search . '%');
            });
        }

        $listHargaB2b = $query->orderBy('customer_id')->orderBy('barang_id')->paginate(15)->withQueryString();

        $customers = Customer::orderBy('nama')->get();

        $kejinggaGudangIds = \App\Models\MasterGudang::where('nama', 'like', '%KeJingga%')
            ->orWhere('nama', 'like', '%Kejingga%')
            ->pluck('id')
            ->toArray();

        $gaharuGudangIds = \App\Models\MasterGudang::where('nama', 'like', '%Gaharu%')
            ->pluck('id')
            ->toArray();

        $listBarangRaw = MasterBarang::with(['minimumStocks.gudang', 'minimumStocks.divisi'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_barang_jadi', 1)
                  ->orWhere('is_bahan_setengah_jadi', 1);
            })
            ->orderBy('nama')
            ->get();

        $barangData = $listBarangRaw->map(function ($b) use ($kejinggaGudangIds, $gaharuGudangIds) {
            // Check Kejingga tagging
            $taggedKejinggaDivisis = $b->minimumStocks
                ->filter(function ($ms) use ($kejinggaGudangIds) {
                    return in_array($ms->gudang_id, $kejinggaGudangIds) && ($ms->is_active ?? true);
                })
                ->map(fn($ms) => $ms->divisi ? $ms->divisi->nama : 'Utama')
                ->unique()
                ->values()
                ->toArray();

            $isKejinggaBsj = (bool)$b->is_bahan_setengah_jadi && (
                !empty($taggedKejinggaDivisis) || floatval($b->minimum_stock_kejingga ?? 0) > 0
            );

            $isKejinggaPos = (bool)$b->is_barang_jadi && (
                $b->tipe_penjualan === 'POS Kejingga' || stripos($b->tipe_penjualan ?? '', 'kejingga') !== false
            );

            // Check Gaharu tagging
            $taggedGaharuDivisis = $b->minimumStocks
                ->filter(function ($ms) use ($gaharuGudangIds) {
                    return in_array($ms->gudang_id, $gaharuGudangIds) && ($ms->is_active ?? true);
                })
                ->map(fn($ms) => $ms->divisi ? $ms->divisi->nama : 'Utama')
                ->unique()
                ->values()
                ->toArray();

            $isGaharuBsj = (bool)$b->is_bahan_setengah_jadi && (
                !empty($taggedGaharuDivisis) || floatval($b->minimum_stock_gaharu ?? 0) > 0
            );

            $isGaharuPos = (bool)$b->is_barang_jadi && (
                $b->tipe_penjualan === 'POS Gaharu' || stripos($b->tipe_penjualan ?? '', 'gaharu') !== false
            );

            $stdPrice = floatval($b->harga_jual_b2b > 0 ? $b->harga_jual_b2b : ($b->harga_jual_pos > 0 ? $b->harga_jual_pos : ($b->hpp_referensi ?? 0)));

            return [
                'id'                     => $b->id,
                'nama'                   => $b->nama,
                'kode_barang'            => $b->kode_barang,
                'satuan'                 => $b->satuan,
                'is_bahan_setengah_jadi' => (bool)$b->is_bahan_setengah_jadi,
                'is_barang_jadi'         => (bool)$b->is_barang_jadi,
                'tipe_penjualan'         => $b->tipe_penjualan,
                'std_price'              => $stdPrice,
                'formatted_price'        => number_format($stdPrice, 0, ',', '.'),
                'is_kejingga_bsj'        => $isKejinggaBsj,
                'is_kejingga_pos'        => $isKejinggaPos,
                'kejingga_divisis'       => $taggedKejinggaDivisis,
                'is_gaharu_bsj'          => $isGaharuBsj,
                'is_gaharu_pos'          => $isGaharuPos,
                'gaharu_divisis'         => $taggedGaharuDivisis,
                'for_kejingga'           => ($isKejinggaBsj || $isKejinggaPos),
                'for_gaharu'             => ($isGaharuBsj || $isGaharuPos),
                'for_b2b_general'        => ((bool)$b->is_bahan_setengah_jadi || $b->tipe_penjualan === 'B2B'),
            ];
        });

        $listBarang = $listBarangRaw;

        $fifoService = app(FifoService::class);
        foreach ($listHargaB2b as $item) {
            if ($item->barang) {
                $hppResep = $fifoService->getHppResepBsj($item->barang_id);
                $item->hpp_referensi = $hppResep > 0 ? $hppResep : floatval($item->barang->hpp_referensi ?? ($item->barang->harga_beli ?? 0));
                $item->laba_unit = floatval($item->harga_b2b) - $item->hpp_referensi;
                $item->margin_persen = floatval($item->harga_b2b) > 0 ? round(($item->laba_unit / floatval($item->harga_b2b)) * 100, 1) : 0;
            } else {
                $item->hpp_referensi = 0;
                $item->laba_unit = 0;
                $item->margin_persen = 0;
            }
        }

        return view('harga_b2b.index', compact('listHargaB2b', 'customers', 'listBarang', 'barangData', 'customerId', 'search'));
    }

    /**
     * Simpan pengaturan harga jual B2B baru / update jika sudah ada
     */
    public function store(Request $request)
    {
        HargaBarangB2b::ensureTableExists();

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'barang_id'   => 'required|exists:master_barang,id',
            'harga_b2b'   => 'required',
        ]);

        $cleanHarga = floatval(str_replace(['.', ','], ['', '.'], str_replace('Rp', '', $request->harga_b2b)));
        if ($cleanHarga < 0) {
            $cleanHarga = 0;
        }

        HargaBarangB2b::updateOrCreate(
            [
                'customer_id' => $request->customer_id,
                'barang_id'   => $request->barang_id,
            ],
            [
                'harga_b2b'   => $cleanHarga,
                'keterangan'  => $request->keterangan,
            ]
        );

        return redirect()->back()->with('success', 'Harga jual B2B khusus outlet berhasil disimpan!');
    }

    /**
     * Update harga jual B2B
     */
    public function update(Request $request, $id)
    {
        HargaBarangB2b::ensureTableExists();

        $harga = HargaBarangB2b::findOrFail($id);

        $request->validate([
            'harga_b2b' => 'required',
        ]);

        $cleanHarga = floatval(str_replace(['.', ','], ['', '.'], str_replace('Rp', '', $request->harga_b2b)));
        if ($cleanHarga < 0) {
            $cleanHarga = 0;
        }

        $harga->update([
            'harga_b2b'  => $cleanHarga,
            'keterangan' => $request->keterangan ?? $harga->keterangan,
        ]);

        return redirect()->back()->with('success', 'Perubahan harga jual B2B berhasil diperbarui!');
    }

    /**
     * Hapus harga jual B2B khusus
     */
    public function destroy($id)
    {
        HargaBarangB2b::ensureTableExists();

        $harga = HargaBarangB2b::findOrFail($id);
        $harga->delete();

        return redirect()->back()->with('success', 'Data harga jual B2B khusus outlet berhasil dihapus.');
    }
}
