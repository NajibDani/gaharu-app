<?php

namespace App\Http\Controllers;

use App\Models\ResepBahanBaku;
use App\Models\ResepBtklBop;
use App\Models\MasterBarang;
use Illuminate\Http\Request;

class ResepBtklBopController extends Controller
{
    public function index()
    {
        // 1. Mengambil data utama resep beserta relasinya untuk tabel list
        $data = ResepBtklBop::whereHas('produk')->with(['produk', 'bahanbaku'])->get();

        $produk = MasterBarang::where('is_barang_jadi', '1')->where('is_active', true)->orderBy('nama')->get();
        $bahan  = MasterBarang::where('is_bahan_baku', '1')->where('is_active', true)->orderBy('nama')->get();

        // 3. Mengirimkan ketiga variabel ke view resep.index
        return view('resep.index', compact('data', 'produk', 'bahan'));
    }

    public function show($id)
{
    // Kita ubah nama variabelnya menjadi $resep agar singkron dengan view
    $resep = ResepBtklBop::whereHas('produk')->with(['produk', 'bahanbaku.bahan'])->findOrFail($id);

    return view('resep.show', compact('resep'));
}

    public function create()
    {
        // Fungsi ini sudah tidak terpakai karena beralih ke popup, tapi biarkan saja agar tidak merusak route yang ada
        $produk = MasterBarang::where('is_barang_jadi', '1')->where('is_active', true)->get();
        $bahan  = MasterBarang::where('is_bahan_baku', '1')->where('is_active', true)->get();

        return view('resep.create', compact('produk', 'bahan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'produk_id' => 'required',
            'output_qty' => 'required|numeric|min:1',
            'btkl_per_batch' => 'nullable|numeric',
            'bop_per_batch' => 'nullable|numeric',
            'bahan_id' => 'required|array',
            'bahan_id.*' => 'required|exists:master_barang,id',
            'qty_bahan' => 'required|array',
            'qty_bahan.*' => 'required|numeric|min:0.01',
            'satuan' => 'required|array',
            'satuan.*' => 'required'
        ]);

        $cek = ResepBtklBop::where('produk_id', $request->produk_id)->exists();

        if ($cek) {
            return back()->with('error', 'Produk sudah punya resep!');
        }

        // 1. Simpan header resep
        $resep = ResepBtklBop::create([
            'produk_id' => $request->produk_id,
            'output_qty' => $request->output_qty,
            'satuan_output' => $request->satuan_output ?? 'Batch',
            'btkl_per_batch' => $request->btkl_per_batch ?? 0,
            'bop_per_batch' => $request->bop_per_batch ?? 0,
        ]);

        // 🎯 SINKRONISASI: Update resep_id di tabel master_barang secara otomatis
        MasterBarang::where('id', $request->produk_id)->update([
            'resep_id' => $resep->id
        ]);

        // 2. Grouping & Simpan Bahan Baku
        $grouped = [];
        foreach ($request->bahan_id as $i => $bahan_id) {
            $qty = $request->qty_bahan[$i];
            $satuan = $request->satuan[$i];

            if (isset($grouped[$bahan_id])) {
                $grouped[$bahan_id]['qty'] += $qty;
            } else {
                $grouped[$bahan_id] = [
                    'qty' => $qty,
                    'satuan' => $satuan
                ];
            }
        }

        foreach ($grouped as $bahan_id => $val) {
            ResepBahanBaku::create([
                'resep_id' => $resep->id,
                'bahan_id' => $bahan_id,
                'qty_bahan' => $val['qty'],
                'satuan' => $val['satuan'],
            ]);
        }

        return redirect()->route('resep.index')->with('success', 'Resep berhasil dibuat dan dihubungkan ke produk');
    }

    public function edit($id)
    {
        $data = ResepBtklBop::whereHas('produk')->with('bahanbaku.bahan')->findOrFail($id);
        $produk = MasterBarang::where('is_barang_jadi', 1)->where('is_active', true)->orderBy('nama')->get();
        $bahan  = MasterBarang::where('is_bahan_baku', 1)->where('is_active', true)->orderBy('nama')->get();
    
        return view('resep.edit', compact('data', 'produk', 'bahan'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'produk_id' => 'required',
            'output_qty' => 'required|numeric|min:1',
            'btkl_per_batch' => 'nullable|numeric',
            'bop_per_batch' => 'nullable|numeric',
            'bahan_id' => 'required|array',
            'bahan_id.*' => 'required|exists:master_barang,id',
            'qty_bahan' => 'required|array',
            'qty_bahan.*' => 'required|numeric|min:0.01',
        ]);

        $resep = ResepBtklBop::whereHas('produk')->findOrFail($id);

        // 1. Update header
        $resep->update([
            'produk_id' => $request->produk_id,
            'output_qty' => $request->output_qty,
            'satuan_output' => $request->satuan_output,
            'btkl_per_batch' => $request->btkl_per_batch ?? 0,
            'bop_per_batch' => $request->bop_per_batch ?? 0,
        ]);

        // 🎯 SINKRONISASI: Pastikan master_barang tetap terhubung ke resep ini
        MasterBarang::where('id', $request->produk_id)->update([
            'resep_id' => $resep->id
        ]);

        // 2. Refresh Bahan Baku
        ResepBahanBaku::where('resep_id', $id)->delete();

        $grouped = [];
        foreach ($request->bahan_id as $i => $bahan_id) {
            $qty = $request->qty_bahan[$i];
            if (isset($grouped[$bahan_id])) {
                $grouped[$bahan_id] += $qty;
            } else {
                $grouped[$bahan_id] = $qty;
            }
        }

        foreach ($grouped as $bahan_id => $qty) {
            $barang = MasterBarang::find($bahan_id);
            ResepBahanBaku::create([
                'resep_id' => $id,
                'bahan_id' => $bahan_id,
                'qty_bahan' => $qty,
                'satuan' => $barang->satuan ?? '-',
            ]);
        }

        return redirect()->route('resep.index')->with('success', 'Resep dan koneksi produk berhasil diupdate');
    }

    public function destroy($id)
    {
        $resep = ResepBtklBop::whereHas('produk')->findOrFail($id);

        // 🎯 SINKRONISASI: Sebelum resep dihapus, set resep_id di master_barang jadi NULL lagi
        MasterBarang::where('resep_id', $id)->update(['resep_id' => null]);

        ResepBahanBaku::where('resep_id', $id)->delete();
        $resep->delete();

        return back()->with('success', 'Resep berhasil dihapus');
    }
}