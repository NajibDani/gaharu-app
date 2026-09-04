<?php

namespace App\Http\Controllers;

use App\Models\ResepBahanBaku;
use App\Models\ResepBtklBop;
use App\Models\MasterBarang;
use Illuminate\Http\Request;

class ResepBahanBakuController extends Controller
{
    public function index()
    {
        $data = ResepBahanBaku::with('bahanbaku')->get();
        return view('resep_bahan.index', compact('data'));
    }

    public function show($id)
    {
        $resep = ResepBtklBop::whereHas('produk')->with('produk')->findOrFail($id);

        $bahan = ResepBahanBaku::with('bahan')
                    ->where('resep_id', $id)
                    ->get();

        $master = MasterBarang::where('is_active', true)->orderBy('nama')->get();

        return view('resep.bahan', compact('resep', 'bahan', 'master'));
    }

    public function store(Request $request, $id)
    {
        $resep = ResepBtklBop::whereHas('produk')->findOrFail($id);
        $request->validate([
            'bahan_id' => 'required|array',
            'bahan_id.*' => 'required|exists:master_barang,id',
            'qty_bahan' => 'required|array',
            'qty_bahan.*' => 'required|numeric|min:0.01',
            'satuan' => 'required|array',
            'satuan.*' => 'required|string'
        ]);

        foreach ($request->bahan_id as $i => $bahan_id) {

            // ambil qty & satuan dengan aman
            $qty = $request->qty_bahan[$i] ?? 0;
            $satuan = $request->satuan[$i] ?? '';

            // cek apakah bahan sudah ada di resep ini
            $existing = ResepBahanBaku::where('resep_id', $id)
                        ->where('bahan_id', $bahan_id)
                        ->first();

            if ($existing) {
                // update qty (ditambah)
                $existing->qty_bahan += $qty;
                $existing->save();
            } else {
                // insert baru
                ResepBahanBaku::create([
                    'resep_id' => $id,
                    'bahan_id' => $bahan_id,
                    'qty_bahan' => $qty,
                    'satuan' => $satuan,
                ]);
            }
        }

        return back()->with('success', 'Bahan berhasil ditambahkan');
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'qty_bahan' => 'required|numeric|min:0.01',
        ]);
    
        $bahan = ResepBahanBaku::whereHas('resep.produk')->findOrFail($id);
    
        $bahan->update([
            'qty_bahan' => $request->qty_bahan,
        ]);
    
        return back()->with('success', 'Bahan berhasil diupdate');
    }

    public function destroy($id)
    {
        $bahan = ResepBahanBaku::whereHas('resep.produk')->findOrFail($id);
        $bahan->delete();

        return back()->with('success', 'Bahan berhasil dihapus');
    }
}