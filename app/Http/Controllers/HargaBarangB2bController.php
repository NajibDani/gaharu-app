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

        $listBarang = MasterBarang::where('is_active', true)
            ->where(function ($q) {
                $q->where('is_barang_jadi', 1)
                  ->orWhere('is_bahan_setengah_jadi', 1);
            })
            ->orderBy('nama')
            ->get();

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

        return view('harga_b2b.index', compact('listHargaB2b', 'customers', 'listBarang', 'customerId', 'search'));
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
