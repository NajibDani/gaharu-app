<?php

namespace App\Http\Controllers;

use App\Models\MasterBarang;
use App\Models\Kategori;
use App\Models\ResepBtklBop; 
use Illuminate\Http\Request;

class BarangController extends Controller
{
    // Jalur: app/Http/Controllers/BarangController.php

    public function index(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';
        $gudangRole = $roleName === 'Kepala Gudang' ? 'Kepala Gudang' : null;
        $kategoriId = $request->query('kategori_id');
        $search     = $request->query('search');

        $query = MasterBarang::with(['kategori', 'resep']);

        if ($kategoriId) {
            $query->where('kategori_id', $kategoriId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode_barang', 'like', '%' . $search . '%');
            });
        }

        $data = $query->orderBy('kode_barang', 'asc')->paginate(10)->withQueryString();
        
        $kategori = Kategori::all();
        $reseps   = ResepBtklBop::all(); 

        return view('barang.index', compact('data', 'kategori', 'reseps', 'gudangRole'));
    }

    public function checkNama(Request $request)
    {
        $nama = $request->query('nama');
        $excludeId = $request->query('exclude_id');
        $query = MasterBarang::whereRaw('LOWER(nama) = ?', [strtolower($nama)]);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return response()->json(['exists' => $query->exists()]);
    }

    public function show($id)
    {
        $barang = MasterBarang::with(['kategori', 'resep'])->findOrFail($id);
        return view('barang.show', compact('barang'));
    }

    public function create()
    {
        // Fungsi ini sekarang opsional karena sudah pakai popup di index
        return redirect()->route('barang.index');
    }

    public function generateKode($kategoriId)
    {
        $kategori = Kategori::find($kategoriId);

        if (!$kategori) {
            return response()->json([
                'error' => 'Kategori tidak ditemukan'
            ], 404);
        }

        // Ambil prefix dari tabel kategori
        $prefix = strtoupper($kategori->prefix);

        // Cari kode terakhir berdasarkan prefix
        $lastBarang = MasterBarang::where('kode_barang', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(kode_barang, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->first();

        if ($lastBarang) {
            // Ambil angka setelah prefix
            $lastNumber = (int) substr($lastBarang->kode_barang, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $kodeBarang = $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'kode_barang' => $kodeBarang
        ]);
    }

    public function store(Request $request)
    {
        $namaClean = trim($request->nama);
        $request->merge([
            'nama' => $namaClean,
            'kode_barang' => trim($request->kode_barang),
        ]);

        $request->validate([
            'kategori_id' => 'required',
            'kode_barang' => 'required|unique:master_barang,kode_barang',
            'nama'        => 'required',
            'jenis_utama' => 'required',
            'tipe_penjualan' => 'required_if:jenis_utama,BARANG_JADI|nullable|in:POS Kejingga,POS Gaharu,B2B',
            'satuan_pembelian' => 'nullable|string',
            'konversi_pembelian' => 'nullable|numeric|min:0.01',
        ], [
            'kode_barang.unique' => 'Kode barang sudah digunakan, harap gunakan kode barang yang unik.',
        ]);

        $nameExists = MasterBarang::whereRaw('LOWER(nama) = ?', [strtolower($namaClean)])->exists();
        if ($nameExists) {
            return back()->withErrors(['nama' => 'Nama barang sudah ada di sistem. Nama barang harus unik (tidak sensitif huruf besar/kecil).'])->withInput();
        }

        $user = auth()->user();
        if ($user && $user->role) {
            $roleName = $user->role->nama;
            if ($roleName === 'Kepala Outlet Gaharu') {
                $allowed = ['POS Gaharu', 'B2B'];
            } elseif ($roleName === 'Kepala Outlet Kejingga') {
                $allowed = ['POS Kejingga'];
            } elseif ($roleName === 'Kepala Gudang') {
                $allowed = ['B2B'];
            } else {
                $allowed = ['POS Kejingga', 'POS Gaharu', 'B2B'];
            }
            
            if ($request->jenis_utama === 'BARANG_JADI' && !in_array($request->tipe_penjualan, $allowed)) {
                return back()->withErrors(['tipe_penjualan' => 'Tipe penjualan tidak valid untuk role Anda.'])->withInput();
            }
        }
    
        try {
            $harga_b2b = str_replace('.', '', $request->harga_jual_b2b ?? 0);
            $harga_pos = str_replace('.', '', $request->harga_jual_pos ?? 0);
            $hpp       = str_replace('.', '', $request->hpp_referensi ?? 0);
    
            if ($request->jenis_utama == 'BAHAN_BAKU' || $request->jenis_utama == 'OPERATIONAL') {
                $harga_b2b = 0;
                $harga_pos = 0;
            }
    
            MasterBarang::create([
                'kategori_id'           => $request->kategori_id,
                'resep_id'              => $request->resep_id, 
                'kode_barang'           => $request->kode_barang,
                'nama'                  => $request->nama,
                'satuan'                => $request->satuan,
                'satuan_pembelian'      => $request->satuan_pembelian,
                'konversi_pembelian'    => $request->konversi_pembelian ?? 1.00,
                'is_bahan_baku'         => $request->jenis_utama == 'BAHAN_BAKU',
                'is_barang_jadi'        => $request->jenis_utama == 'BARANG_JADI',
                'is_operational'        => $request->jenis_utama == 'OPERATIONAL',
                'is_direct_consumption' => false,
                'hpp_referensi'         => $hpp,
                'harga_jual_b2b'        => $harga_b2b,
                'harga_jual_pos'        => $harga_pos,
                'minimum_stock'         => $request->minimum_stock,
                'minimum_order'         => $request->minimum_order ?? 1.00,
                'tipe_penjualan'        => $request->jenis_utama == 'BARANG_JADI' ? $request->tipe_penjualan : null,
            ]);
    
            return redirect()->route('barang.index')->with('success', 'Data berhasil ditambah');
    
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withErrors(['error' => 'Gagal simpan: ' . $e->getMessage()]);
        }
    }
    
    public function edit($id)
    {
        // Fungsi ini sekarang opsional karena sudah pakai popup di index
        $data = MasterBarang::findOrFail($id);
        $kategori = Kategori::all();
        $reseps = ResepBtklBop::all(); 

        $data->jenis_utama = $data->is_bahan_baku ? 'BAHAN_BAKU' : ($data->is_barang_jadi ? 'BARANG_JADI' : 'OPERATIONAL');

        return view('barang.edit', compact('data', 'kategori', 'reseps'));
    }

    public function update(Request $request, $id)
    {
        $namaClean = trim($request->nama);
        $request->merge([
            'nama' => $namaClean,
            'kode_barang' => trim($request->kode_barang),
        ]);

        $request->validate([
            'kategori_id' => 'required',
            'kode_barang' => 'required|unique:master_barang,kode_barang,' . $id,
            'nama'        => 'required',
            'jenis_utama' => 'required',
            'tipe_penjualan' => 'required_if:jenis_utama,BARANG_JADI|nullable|in:POS Kejingga,POS Gaharu,B2B',
            'satuan_pembelian' => 'nullable|string',
            'konversi_pembelian' => 'nullable|numeric|min:0.01',
        ], [
            'kode_barang.unique' => 'Kode barang sudah digunakan, harap gunakan kode barang yang unik.',
        ]);

        $nameExists = MasterBarang::whereRaw('LOWER(nama) = ?', [strtolower($namaClean)])
            ->where('id', '!=', $id)
            ->exists();
        if ($nameExists) {
            return back()->withErrors(['nama' => 'Nama barang sudah ada di sistem. Nama barang harus unik (tidak sensitif huruf besar/kecil).'])->withInput();
        }

        $user = auth()->user();
        if ($user && $user->role) {
            $roleName = $user->role->nama;
            if ($roleName === 'Kepala Outlet Gaharu') {
                $allowed = ['POS Gaharu', 'B2B'];
            } elseif ($roleName === 'Kepala Outlet Kejingga') {
                $allowed = ['POS Kejingga'];
            } elseif ($roleName === 'Kepala Gudang') {
                $allowed = ['B2B'];
            } else {
                $allowed = ['POS Kejingga', 'POS Gaharu', 'B2B'];
            }
            
            if ($request->jenis_utama === 'BARANG_JADI' && !in_array($request->tipe_penjualan, $allowed)) {
                return back()->withErrors(['tipe_penjualan' => 'Tipe penjualan tidak valid untuk role Anda.'])->withInput();
            }
        }

        $data = MasterBarang::findOrFail($id);
    
        $harga_b2b = str_replace('.', '', $request->harga_jual_b2b ?? 0);
        $harga_pos = str_replace('.', '', $request->harga_jual_pos ?? 0);
        $hpp = str_replace('.', '', $request->hpp_referensi ?? 0);
    
        if ($request->jenis_utama == 'BAHAN_BAKU' || $request->jenis_utama == 'OPERATIONAL') {
            $harga_b2b = 0;
            $harga_pos = 0;
        }
    
        $data->update([
            'kategori_id' => $request->kategori_id,
            'resep_id'    => $request->resep_id, 
            'kode_barang' => $request->kode_barang,
            'nama'        => $request->nama,
            'satuan'      => $request->satuan,
            'satuan_pembelian' => $request->satuan_pembelian,
            'konversi_pembelian' => $request->konversi_pembelian ?? 1.00,
    
            'is_bahan_baku'  => $request->jenis_utama == 'BAHAN_BAKU',
            'is_barang_jadi' => $request->jenis_utama == 'BARANG_JADI',
            'is_operational' => $request->jenis_utama == 'OPERATIONAL',
            'is_direct_consumption' => false,
    
            'hpp_referensi'  => $hpp,
            'harga_jual_b2b' => $harga_b2b,
            'harga_jual_pos' => $harga_pos,
            'minimum_stock'  => $request->minimum_stock,
            'minimum_order'  => $request->minimum_order ?? 1.00,
            'tipe_penjualan' => $request->jenis_utama == 'BARANG_JADI' ? $request->tipe_penjualan : null,
        ]);
    
        return redirect()->route('barang.index')->with('success', 'Data berhasil diupdate');
    }

    public function destroy(MasterBarang $barang)
    {
        // Cek apakah barang sudah dipakai di tabel manapun
        $dipakai = \Illuminate\Support\Facades\DB::table('pembelian_detail')
                    ->where('barang_id', $barang->id)->exists()
                || \Illuminate\Support\Facades\DB::table('stok_gudang')
                    ->where('barang_id', $barang->id)->exists()
                || \Illuminate\Support\Facades\DB::table('pengeluaran_bahan_baku_detail')
                    ->where('barang_id', $barang->id)->exists()
                || \Illuminate\Support\Facades\DB::table('stock_opname_detail')
                    ->where('barang_id', $barang->id)->exists();

        if ($dipakai) {
            return back()->with('error', 'Barang sudah digunakan dalam transaksi dan tidak bisa dihapus. Gunakan fitur nonaktifkan jika barang tidak lagi dipakai.');
        }

        $barang->delete();

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    public function toggleStatus($id)
    {
        $barang = \App\Models\MasterBarang::findOrFail($id);
        $barang->is_active = !$barang->is_active;
        $barang->save();

        return back()->with('success', 'Status barang berhasil diubah.');
    }

    public function toggle(MasterBarang $barang)
    {
        $barang->update([
            'is_active' => !$barang->is_active,
        ]);

        return back()->with('success', 'Status barang berhasil diubah.');
    }
} // <-- FIX: Kurung tutup ganda yang salah sudah dihapus