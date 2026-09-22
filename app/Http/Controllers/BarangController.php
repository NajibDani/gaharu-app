<?php

namespace App\Http\Controllers;

use App\Imports\MasterBarangImporter;
use App\Models\EventNotifikasi;
use App\Models\MasterBarang;
use App\Models\Kategori;
use App\Models\ResepBtklBop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BarangController extends Controller
{
    // Jalur: app/Http/Controllers/BarangController.php

    public function index(Request $request)
    {
        MasterBarang::syncAllResepIds();

        $user = auth()->user();
        $roleName = $user->role->nama ?? '';
        $gudangRole = $roleName === 'Kepala Gudang' ? 'Kepala Gudang' : null;
        $kategoriId = $request->query('kategori_id');
        $search     = $request->query('search');

        $query = MasterBarang::with(['kategori', 'resep', 'minimumStocks.gudang', 'minimumStocks.divisi']);

        if ($kategoriId) {
            $query->where('kategori_id', $kategoriId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode_barang', 'like', '%' . $search . '%');
            });
        }

        $sort = $request->query('sort', 'kode_asc');

        switch ($sort) {
            case 'az':
                $query->orderBy('nama', 'asc');
                break;
            case 'za':
                $query->orderBy('nama', 'desc');
                break;
            case 'terlama':
                $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;
            case 'terbaru':
                $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
                break;
            default:
                $query->orderBy('kode_barang', 'asc');
                break;
        }

        $data = $query->paginate(10)->withQueryString();
        
        $kategori = Kategori::all();
        $reseps   = ResepBtklBop::all();
        $gudangList = \App\Models\MasterGudang::with('divisi')->orderBy('id')->get();

        return view('barang.index', compact('data', 'kategori', 'reseps', 'gudangRole', 'gudangList'));
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
        $barang = MasterBarang::with(['kategori', 'resep', 'minimumStocks.gudang', 'minimumStocks.divisi'])->findOrFail($id);
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

        // Ambil prefix dari tabel kategori atau buat dari nama
        $prefix = !empty($kategori->prefix)
            ? strtoupper($kategori->prefix)
            : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $kategori->nama ?? 'BRG'), 0, 3));

        if (empty($prefix)) {
            $prefix = 'BRG';
        }

        // Cari nomor urut terbesar
        $allCodes = MasterBarang::withoutGlobalScopes()
            ->where('kode_barang', 'like', $prefix . '%')
            ->pluck('kode_barang');

        $maxNumber = 0;
        foreach ($allCodes as $code) {
            $suffix = substr($code, strlen($prefix));
            if (is_numeric($suffix)) {
                $num = (int) $suffix;
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $newNumber = $maxNumber + 1;
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

        if ($request->jenis_utama === 'BAHAN_SETENGAH_JADI') {
            $request->validate([
                'satuan' => 'required|in:gr,ml,porsi,pcs,GR,ML,PORSI,PCS,gram,mililiter,Gram,Mililiter,Porsi,Pcs,piece,pieces,PIECE,PIECES',
            ], [
                'satuan.in' => 'Untuk Bahan Setengah Jadi, satuan harus berupa gram (gr), mililiter (ml), porsi, atau pcs.',
            ]);
            
            $satuanUpper = strtoupper(trim($request->satuan));
            if ($satuanUpper === 'GRAM') {
                $satuanUpper = 'GR';
            } elseif ($satuanUpper === 'MILILITER') {
                $satuanUpper = 'ML';
            } elseif ($satuanUpper === 'PORSI') {
                $satuanUpper = 'PORSI';
            } elseif ($satuanUpper === 'PCS' || $satuanUpper === 'PIECE' || $satuanUpper === 'PIECES') {
                $satuanUpper = 'PCS';
            }
            $request->merge(['satuan' => $satuanUpper]);
        }

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
    
            if (in_array($request->jenis_utama, ['BAHAN_BAKU', 'BAHAN_SETENGAH_JADI', 'OPERATIONAL'])) {
                $harga_b2b = 0;
                $harga_pos = 0;
            }
    
            $barang = MasterBarang::create([
                'kategori_id'           => $request->kategori_id,
                'resep_id'              => $request->resep_id, 
                'kode_barang'           => $request->kode_barang,
                'nama'                  => $request->nama,
                'satuan'                => $request->satuan,
                'satuan_pembelian'      => $request->satuan_pembelian,
                'konversi_pembelian'    => $request->konversi_pembelian ?? 1.00,
                'is_bahan_baku'         => $request->jenis_utama == 'BAHAN_BAKU',
                'is_bahan_setengah_jadi' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI',
                'is_barang_jadi'        => $request->jenis_utama == 'BARANG_JADI',
                'is_operational'        => $request->jenis_utama == 'OPERATIONAL',
                'is_direct_consumption' => false,
                'hpp_referensi'         => $hpp,
                'harga_jual_b2b'        => $harga_b2b,
                'harga_jual_pos'        => $harga_pos,
                'minimum_stock'         => $request->minimum_stock,
                'minimum_stock_ck'      => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? $request->minimum_stock_ck : null,
                'minimum_stock_kejingga' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? $request->minimum_stock_kejingga : null,
                'minimum_stock_gaharu'  => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? $request->minimum_stock_gaharu : null,
                'minimum_order'         => $request->minimum_order ?? 1.00,
                'tipe_penjualan'        => $request->jenis_utama == 'BARANG_JADI' ? $request->tipe_penjualan : null,
            ]);

            // Simpan alokasi/tagging divisi jika jenis BAHAN_BAKU atau BAHAN_SETENGAH_JADI
            if (in_array($request->jenis_utama, ['BAHAN_BAKU', 'BAHAN_SETENGAH_JADI']) && $request->has('divisi_tag')) {
                $gudangListAll = \App\Models\MasterGudang::with('divisi')->get();
                foreach ($gudangListAll as $g) {
                    if ($g->divisi->count() > 0) {
                        foreach ($g->divisi as $div) {
                            $isTagged = (bool)$request->input("divisi_tag.{$g->id}.{$div->id}", false);
                            if ($isTagged) {
                                \App\Models\BarangMinimumStock::create([
                                    'barang_id'     => $barang->id,
                                    'gudang_id'     => $g->id,
                                    'divisi_id'     => $div->id,
                                    'minimum_stock' => 0,
                                    'is_active'     => true,
                                ]);
                            }
                        }
                    } else {
                        $isTagged = (bool)$request->input("divisi_tag.{$g->id}.none", false);
                        if ($isTagged) {
                            \App\Models\BarangMinimumStock::create([
                                'barang_id'     => $barang->id,
                                'gudang_id'     => $g->id,
                                'divisi_id'     => null,
                                'minimum_stock' => 0,
                                'is_active'     => true,
                            ]);
                        }
                    }
                }
            }
    
            return redirect()->route('barang.index')->with('success', "Item barang '{$barang->nama}' ({$barang->kode_barang}) berhasil ditambahkan.");
    
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withErrors(['error' => 'Gagal simpan: ' . $e->getMessage()]);
        }
    }
    
    public function edit($id)
    {
        // Fungsi ini sekarang opsional karena sudah pakai popup di index
        $data = MasterBarang::with('minimumStocks')->findOrFail($id);
        $kategori = Kategori::all();
        $reseps = ResepBtklBop::all(); 
        $gudangList = \App\Models\MasterGudang::with('divisi')->orderBy('id')->get();

        $data->jenis_utama = $data->is_bahan_baku ? 'BAHAN_BAKU' : ($data->is_bahan_setengah_jadi ? 'BAHAN_SETENGAH_JADI' : ($data->is_barang_jadi ? 'BARANG_JADI' : ($data->is_operational ? 'OPERATIONAL' : 'BAHAN_BAKU')));

        return view('barang.edit', compact('data', 'kategori', 'reseps', 'gudangList'));
    }

    public function update(Request $request, $id)
    {
        $data = MasterBarang::withoutGlobalScopes()->findOrFail($id);

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

        if ($request->jenis_utama === 'BAHAN_SETENGAH_JADI') {
            $request->validate([
                'satuan' => 'required|in:gr,ml,porsi,pcs,GR,ML,PORSI,PCS,gram,mililiter,Gram,Mililiter,Porsi,Pcs,piece,pieces,PIECE,PIECES',
            ], [
                'satuan.in' => 'Untuk Bahan Setengah Jadi, satuan harus berupa gram (gr), mililiter (ml), porsi, atau pcs.',
            ]);
            
            $satuanUpper = strtoupper(trim($request->satuan));
            if ($satuanUpper === 'GRAM') {
                $satuanUpper = 'GR';
            } elseif ($satuanUpper === 'MILILITER') {
                $satuanUpper = 'ML';
            } elseif ($satuanUpper === 'PORSI') {
                $satuanUpper = 'PORSI';
            } elseif ($satuanUpper === 'PCS' || $satuanUpper === 'PIECE' || $satuanUpper === 'PIECES') {
                $satuanUpper = 'PCS';
            }
            $request->merge(['satuan' => $satuanUpper]);
        }

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
    
        $hpp = 0;
        $harga_b2b = 0;
        $harga_pos = 0;
        
        // HPP Referensi
        if ($request->jenis_utama == 'BAHAN_BAKU' || $request->jenis_utama == 'OPERATIONAL') {
            $hpp = $request->hpp_referensi ?? $data->hpp_referensi ?? 0;
        } elseif ($request->jenis_utama == 'BAHAN_SETENGAH_JADI') {
            $hpp = $request->hpp_bsj ?? $request->hpp_referensi ?? $data->hpp_referensi ?? 0;
        } elseif ($request->jenis_utama == 'BARANG_JADI') {
            $hpp = $request->hpp_bj ?? $request->hpp_referensi ?? $data->hpp_referensi ?? 0;
        }
    
        // Harga Jual
        if ($request->jenis_utama == 'BARANG_JADI') {
            if ($request->tipe_penjualan == 'B2B') {
                $harga_b2b = $request->harga_jual_b2b ?? $data->harga_jual_b2b ?? 0;
            } elseif ($request->tipe_penjualan == 'POS Kejingga' || $request->tipe_penjualan == 'POS Gaharu') {
                $harga_pos = $request->harga_jual_pos ?? $data->harga_jual_pos ?? 0;
            }
        }
    
        $harga_b2b = (float) str_replace('.', '', (string)($harga_b2b ?? 0));
        $harga_pos = (float) str_replace('.', '', (string)($harga_pos ?? 0));
        $hpp       = (float) str_replace('.', '', (string)($hpp ?? 0));
    
        $data->update([
            'kategori_id'    => $request->kategori_id,
            'resep_id'       => $request->resep_id,
            'kode_barang'    => $request->kode_barang,
            'nama'           => $request->nama,
            'satuan'         => $request->satuan,
            'satuan_pembelian' => $request->satuan_pembelian,
            'konversi_pembelian' => $request->konversi_pembelian ?? 1.00,
            'is_bahan_baku'  => $request->jenis_utama == 'BAHAN_BAKU',
            'is_bahan_setengah_jadi' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI',
            'is_barang_jadi' => $request->jenis_utama == 'BARANG_JADI',
            'is_operational' => $request->jenis_utama == 'OPERATIONAL',
            'hpp_referensi'  => $hpp,
            'harga_jual_b2b' => $harga_b2b,
            'harga_jual_pos' => $harga_pos,
            'minimum_stock'  => $request->has('minimum_stock') ? $request->minimum_stock : $data->minimum_stock,
            'minimum_stock_ck' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? ($request->has('minimum_stock_ck') ? $request->minimum_stock_ck : $data->minimum_stock_ck) : null,
            'minimum_stock_kejingga' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? ($request->has('minimum_stock_kejingga') ? $request->minimum_stock_kejingga : $data->minimum_stock_kejingga) : null,
            'minimum_stock_gaharu' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? ($request->has('minimum_stock_gaharu') ? $request->minimum_stock_gaharu : $data->minimum_stock_gaharu) : null,
            'minimum_order'  => $request->minimum_order ?? 1.00,
            'tipe_penjualan' => $request->jenis_utama == 'BARANG_JADI' ? $request->tipe_penjualan : null,
        ]);

        // Simpan / update tagging divisi jika form mengirimkan divisi_tag
        if (in_array($request->jenis_utama, ['BAHAN_BAKU', 'BAHAN_SETENGAH_JADI']) && $request->has('divisi_tag')) {
            $existingMinStocks = \App\Models\BarangMinimumStock::where('barang_id', $data->id)->get();
            \App\Models\BarangMinimumStock::where('barang_id', $data->id)->delete();

            $gudangListAll = \App\Models\MasterGudang::with('divisi')->get();
            foreach ($gudangListAll as $g) {
                if ($g->divisi->count() > 0) {
                    foreach ($g->divisi as $div) {
                        $isTagged = (bool)$request->input("divisi_tag.{$g->id}.{$div->id}", false);
                        if ($isTagged) {
                            $old = $existingMinStocks->where('gudang_id', $g->id)->where('divisi_id', $div->id)->first();
                            $minVal = $old ? (float)$old->minimum_stock : 0;
                            \App\Models\BarangMinimumStock::create([
                                'barang_id'     => $data->id,
                                'gudang_id'     => $g->id,
                                'divisi_id'     => $div->id,
                                'minimum_stock' => $minVal,
                                'is_active'     => true,
                            ]);
                        }
                    }
                } else {
                    $isTagged = (bool)$request->input("divisi_tag.{$g->id}.none", false);
                    if ($isTagged) {
                        $old = $existingMinStocks->where('gudang_id', $g->id)->whereNull('divisi_id')->first();
                        $minVal = $old ? (float)$old->minimum_stock : 0;
                        \App\Models\BarangMinimumStock::create([
                            'barang_id'     => $data->id,
                            'gudang_id'     => $g->id,
                            'divisi_id'     => null,
                            'minimum_stock' => $minVal,
                            'is_active'     => true,
                        ]);
                    }
                }
            }
        }
    
        $page = $request->query('page', 1);
        return redirect()->route('barang.index', ['page' => $page])->with('success', 'Data berhasil diupdate');
    }

    /**
     * Update Minimum Stock khusus dari Modal "Atur Minimum Stock"
     */
    public function updateMinStock(Request $request, $id)
    {
        $barang = MasterBarang::withoutGlobalScopes()->findOrFail($id);

        if ($barang->is_bahan_baku || $barang->is_bahan_setengah_jadi) {
            $gudangListAll = \App\Models\MasterGudang::with('divisi')->get();
            foreach ($gudangListAll as $g) {
                if ($g->divisi->count() > 0) {
                    foreach ($g->divisi as $div) {
                        $minVal = $request->input("min_stock_outlet.{$g->id}.{$div->id}");
                        $isActive = (bool)$request->input("min_stock_active.{$g->id}.{$div->id}", false);

                        if ($isActive || ($minVal !== null && $minVal !== '')) {
                            \App\Models\BarangMinimumStock::updateOrCreate(
                                [
                                    'barang_id' => $barang->id,
                                    'gudang_id' => $g->id,
                                    'divisi_id' => $div->id,
                                ],
                                [
                                    'minimum_stock' => ($minVal !== null && $minVal !== '') ? (float)$minVal : 0,
                                    'is_active'     => $isActive,
                                ]
                            );
                        } else {
                            \App\Models\BarangMinimumStock::where('barang_id', $barang->id)
                                ->where('gudang_id', $g->id)
                                ->where('divisi_id', $div->id)
                                ->delete();
                        }
                    }
                } else {
                    $minVal = $request->input("min_stock_outlet.{$g->id}.none");
                    $isActive = (bool)$request->input("min_stock_active.{$g->id}.none", false);

                    if ($isActive || ($minVal !== null && $minVal !== '')) {
                        \App\Models\BarangMinimumStock::updateOrCreate(
                            [
                                'barang_id' => $barang->id,
                                'gudang_id' => $g->id,
                                'divisi_id' => null,
                            ],
                            [
                                'minimum_stock' => ($minVal !== null && $minVal !== '') ? (float)$minVal : 0,
                                'is_active'     => $isActive,
                            ]
                        );
                    } else {
                        \App\Models\BarangMinimumStock::where('barang_id', $barang->id)
                            ->where('gudang_id', $g->id)
                            ->whereNull('divisi_id')
                            ->delete();
                    }
                }
            }

            if ($barang->is_bahan_setengah_jadi && ($request->has('minimum_stock_ck') || $request->has('minimum_stock_kejingga') || $request->has('minimum_stock_gaharu'))) {
                $barang->update([
                    'minimum_stock_ck'       => $request->filled('minimum_stock_ck') ? (float)$request->minimum_stock_ck : null,
                    'minimum_stock_kejingga' => $request->filled('minimum_stock_kejingga') ? (float)$request->minimum_stock_kejingga : null,
                    'minimum_stock_gaharu'   => $request->filled('minimum_stock_gaharu') ? (float)$request->minimum_stock_gaharu : null,
                ]);
            }
        } else {
            $barang->update([
                'minimum_stock' => $request->filled('minimum_stock') ? (float)$request->minimum_stock : null,
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Minimum stock untuk '{$barang->nama}' berhasil disimpan."
            ]);
        }

        return redirect()->back()->with('success', "Minimum stock untuk '{$barang->nama}' berhasil disimpan.");
    }

    public function destroy(MasterBarang $barang)
    {
        // 1. Cek riwayat transfer / pengiriman
        $hasTransfer = DB::table('pengiriman_detail')->where('barang_id', $barang->id)->exists()
            || DB::table('pesanan_detail')->where('produk_id', $barang->id)->exists()
            || DB::table('transaksi_stok')->where('barang_id', $barang->id)->whereIn('source_type', ['pengiriman', 'transfer_ck'])->exists();

        if ($hasTransfer) {
            return back()->with('error', "Barang '{$barang->nama}' ({$barang->kode_barang}) tidak dapat dihapus karena sudah memiliki riwayat transfer / pengiriman. Silakan gunakan tombol Nonaktifkan jika barang tidak lagi digunakan.");
        }

        // 2. Cek riwayat produksi / resep
        $hasProduksi = DB::table('produksi_detail')->where('produk_id', $barang->id)->exists()
            || DB::table('work_order_detail')->where('produk_id', $barang->id)->exists()
            || DB::table('alokasi_produksi_pesanan')->where('produk_id', $barang->id)->exists()
            || DB::table('resep_bahanbaku')->where('bahan_id', $barang->id)->exists()
            || DB::table('resep_bahanbaku_alternatif')->where('bahan_id', $barang->id)->exists()
            || DB::table('resep_btkl_bop')->where('produk_id', $barang->id)->exists()
            || DB::table('transaksi_stok')->where('barang_id', $barang->id)->whereIn('source_type', ['produksi', 'produksi_ck'])->exists();

        if ($hasProduksi) {
            return back()->with('error', "Barang '{$barang->nama}' ({$barang->kode_barang}) tidak dapat dihapus karena sudah memiliki riwayat produksi atau terdaftar di resep. Silakan gunakan tombol Nonaktifkan jika barang tidak lagi dipakai.");
        }

        // 3. Cek riwayat transaksi operasional lain (pembelian, penjualan POS, pengeluaran bahan baku, stock opname)
        $hasTransaksiLain = DB::table('pembelian_detail')->where('barang_id', $barang->id)->exists()
            || DB::table('penerimaan_pembelian_detail')->where('barang_id', $barang->id)->exists()
            || DB::table('penjualanpos_detail')->where('produk_id', $barang->id)->exists()
            || DB::table('pengeluaran_bahan_baku_detail')->where('barang_id', $barang->id)->exists()
            || DB::table('stock_opname_detail')->where('barang_id', $barang->id)->exists()
            || DB::table('transaksi_stok')->where('barang_id', $barang->id)->whereIn('source_type', ['pembelian', 'pembelian_kejingga', 'pengeluaran_bahan_baku'])->exists();

        if ($hasTransaksiLain) {
            return back()->with('error', "Barang '{$barang->nama}' ({$barang->kode_barang}) tidak dapat dihapus karena sudah memiliki riwayat transaksi pembelian/penjualan/operasional. Silakan gunakan tombol Nonaktifkan jika barang tidak lagi dipakai.");
        }

        // Jika belum memiliki riwayat transaksi/pergerakan riil, bersihkan data saldo awal & relasi master
        try {
            DB::transaction(function () use ($barang) {
                if (Schema::hasTable('fifo_layers')) {
                    DB::table('fifo_layers')->where('barang_id', $barang->id)->delete();
                }
                DB::table('transaksi_stok')->where('barang_id', $barang->id)->delete();
                DB::table('stok_gudang_batch')->where('barang_id', $barang->id)->delete();
                DB::table('stok_gudang')->where('barang_id', $barang->id)->delete();
                DB::table('persediaan_awal_detail')->where('barang_id', $barang->id)->delete();
                DB::table('barang_minimum_stock')->where('barang_id', $barang->id)->delete();
                DB::table('harga_barang_pos')->where('barang_id', $barang->id)->delete();

                $barang->delete();
            });

            return back()->with('success', "Barang '{$barang->nama}' ({$barang->kode_barang}) berhasil dihapus.");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus barang: " . $e->getMessage());
        }
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

    /**
     * Download template Excel untuk import Master Barang.
     * Sheet 1 "Barang" = semua data barang dari database beserta minimum stock saat ini,
     * sehingga user bisa langsung mengisi/mengedit kolom minimum stock dan re-import.
     * Sheet 2 "Referensi Kategori" = daftar kategori yang tersedia saat ini di sistem.
     * Sheet 3 "Panduan" = penjelasan setiap kolom.
     */
    public function importTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Barang');

        // Base fixed headers
        $headers = [
            'kode_barang', 'nama', 'kategori', 'jenis_utama', 'satuan',
            'satuan_pembelian', 'konversi_pembelian', 'tipe_penjualan',
            'harga_jual_b2b', 'harga_jual_pos', 'hpp_referensi',
        ];

        // Load semua gudang dan divisi secara dinamis
        $allGudangs = \App\Models\MasterGudang::with(['divisi' => function($q) {
            $q->orderBy('nama', 'asc');
        }])->orderBy('nama', 'asc')->get();

        // Buat mapping kolom min_stock dinamis per gudang / divisi
        // Struktur: [ 'key' => ..., 'label' => ..., 'gudang_id' => ..., 'divisi_id' => ..., 'deskripsi' => ... ]
        $minStockColumns = [];

        foreach ($allGudangs as $g) {
            $slugGudang = \Illuminate\Support\Str::slug($g->nama, '_');
            if ($g->divisi->count() > 0) {
                foreach ($g->divisi as $d) {
                    $slugDiv = \Illuminate\Support\Str::slug($d->nama, '_');
                    $colKey = "min_stock_{$slugGudang}_{$slugDiv}";
                    $minStockColumns[] = [
                        'key'       => $colKey,
                        'gudang_id' => $g->id,
                        'divisi_id' => $d->id,
                        'label'     => "Min Stock: {$g->nama} ({$d->nama})",
                        'desc'      => "Minimum stock di {$g->nama} - Divisi {$d->nama}. Kosongkan jika tidak perlu diubah.",
                    ];
                }
            } else {
                $colKey = "min_stock_{$slugGudang}";
                $minStockColumns[] = [
                    'key'       => $colKey,
                    'gudang_id' => $g->id,
                    'divisi_id' => null,
                    'label'     => "Min Stock: {$g->nama}",
                    'desc'      => "Minimum stock di {$g->nama}. Kosongkan jika tidak perlu diubah.",
                ];
            }
        }

        // Tambahkan dynamic min stock keys ke header
        $minStockStartIndex = count($headers); // 0-based index
        foreach ($minStockColumns as $msc) {
            $headers[] = $msc['key'];
        }
        $minStockEndIndex = count($headers) - 1;

        // Tambahan kolom akhir
        $headers[] = 'minimum_stock';
        $headers[] = 'minimum_stock_umum';
        $headers[] = 'minimum_order';

        $totalCols = count($headers);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastColLetter}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D88656');

        // Highlight kolom minimum stock agar user tahu kolom mana yang perlu diisi
        if ($minStockEndIndex >= $minStockStartIndex) {
            $startMinCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($minStockStartIndex + 1);
            $endMinCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($minStockEndIndex + 1);
            $sheet->getStyle("{$startMinCol}1:{$endMinCol}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('2E7D32'); // hijau gelap untuk kolom min stock dinamis
        }

        // Isi semua data barang dari database beserta minimum stock saat ini
        $barangs = MasterBarang::with('kategori')
            ->where('is_active', true)
            ->orderBy('kode_barang', 'asc')
            ->get();

        // Pre-load semua minimum stock per barang
        $minStockAll = \App\Models\BarangMinimumStock::all()->groupBy('barang_id');

        // Helper: cari min stock dari collection
        $getMinStock = function ($barangId, $gudangId, $divisiId = null) use ($minStockAll) {
            $items = $minStockAll->get($barangId);
            if (!$items) return '';
            $found = $items->first(function ($ms) use ($gudangId, $divisiId) {
                if ($ms->gudang_id != $gudangId) return false;
                if ($divisiId === null) return $ms->divisi_id === null;
                return $ms->divisi_id == $divisiId;
            });
            return $found ? $found->minimum_stock : '';
        };

        $rowNum = 2;
        foreach ($barangs as $b) {
            // Tentukan jenis_utama
            $jenis = 'BAHAN_BAKU';
            if ($b->is_bahan_setengah_jadi) $jenis = 'BAHAN_SETENGAH_JADI';
            elseif ($b->is_barang_jadi) $jenis = 'BARANG_JADI';
            elseif ($b->is_operational) $jenis = 'OPERATIONAL';

            $rowData = [
                $b->kode_barang,
                $b->nama,
                $b->kategori->nama ?? '',
                $jenis,
                $b->satuan,
                $b->satuan_pembelian ?? '',
                $b->konversi_pembelian ?? 1,
                $b->tipe_penjualan ?? '',
                (float) ($b->harga_jual_b2b ?? 0),
                (float) ($b->harga_jual_pos ?? 0),
                (float) ($b->hpp_referensi ?? 0),
            ];

            // Isi nilai minimum stock per gudang / divisi dinamis
            foreach ($minStockColumns as $msc) {
                $val = $getMinStock($b->id, $msc['gudang_id'], $msc['divisi_id']);
                $rowData[] = $val;
            }

            $rowData[] = $b->minimum_stock ?? '';
            $rowData[] = $b->minimum_order ?? 1;

            $sheet->fromArray($rowData, null, 'A' . $rowNum);
            $rowNum++;
        }

        for ($c = 1; $c <= $totalCols; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Sheet referensi kategori supaya kolom "kategori" diisi persis sesuai sistem
        $kategoriSheet = $spreadsheet->createSheet();
        $kategoriSheet->setTitle('Referensi Kategori');
        $kategoriSheet->fromArray(['nama_kategori', 'prefix'], null, 'A1');
        $kategoriSheet->getStyle('A1:B1')->getFont()->setBold(true);

        $row = 2;
        foreach (Kategori::orderBy('nama')->get() as $k) {
            $kategoriSheet->setCellValue('A' . $row, $k->nama);
            $kategoriSheet->setCellValue('B' . $row, $k->prefix);
            $row++;
        }
        foreach (['A', 'B'] as $col) {
            $kategoriSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet referensi gudang & divisi untuk kemudahan user
        $gudangRefSheet = $spreadsheet->createSheet();
        $gudangRefSheet->setTitle('Referensi Gudang');
        $gudangRefSheet->fromArray(['nama_gudang', 'kategori_gudang', 'divisi', 'kolom_excel_min_stock'], null, 'A1');
        $gudangRefSheet->getStyle('A1:D1')->getFont()->setBold(true);

        $gRow = 2;
        foreach ($allGudangs as $g) {
            if ($g->divisi->count() > 0) {
                foreach ($g->divisi as $d) {
                    $slugG = \Illuminate\Support\Str::slug($g->nama, '_');
                    $slugD = \Illuminate\Support\Str::slug($d->nama, '_');
                    $gudangRefSheet->fromArray([$g->nama, $g->kategori, $d->nama, "min_stock_{$slugG}_{$slugD}"], null, 'A' . $gRow);
                    $gRow++;
                }
            } else {
                $slugG = \Illuminate\Support\Str::slug($g->nama, '_');
                $gudangRefSheet->fromArray([$g->nama, $g->kategori, '-', "min_stock_{$slugG}"], null, 'A' . $gRow);
                $gRow++;
            }
        }
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $gudangRefSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet panduan singkat
        $guideData = [
            ['Kolom', 'Wajib?', 'Keterangan'],
            ['kode_barang', 'Ya', 'Harus unik. Jika kode sudah ada di sistem, minimum stock akan di-UPDATE (data barang lainnya tidak berubah).'],
            ['nama', 'Ya (barang baru)', 'Nama barang. Untuk barang yang sudah ada, kolom ini diabaikan.'],
            ['kategori', 'Ya (barang baru)', 'Isi persis sama dengan nama di sheet "Referensi Kategori". Untuk barang yang sudah ada, kolom ini diabaikan.'],
            ['jenis_utama', 'Ya (barang baru)', 'Salah satu: BAHAN_BAKU, BAHAN_SETENGAH_JADI, BARANG_JADI, OPERATIONAL. Untuk barang yang sudah ada, kolom ini diabaikan.'],
            ['satuan', 'Ya (barang baru)', 'Contoh: GR, KG, PCS, LITER. Untuk barang yang sudah ada, kolom ini diabaikan.'],
            ['satuan_pembelian', 'Tidak', 'Kosongkan jika tidak ada satuan pembelian berbeda.'],
            ['konversi_pembelian', 'Tidak', 'Default 1 jika kosong.'],
            ['tipe_penjualan', 'Wajib jika BARANG_JADI', 'Salah satu: POS Kejingga, POS Gaharu, B2B'],
            ['harga_jual_b2b', 'Tidak', 'Hanya dipakai jika jenis_utama = BARANG_JADI.'],
            ['harga_jual_pos', 'Tidak', 'Hanya dipakai jika jenis_utama = BARANG_JADI.'],
            ['hpp_referensi', 'Tidak', 'Default 0 jika kosong.'],
        ];

        foreach ($minStockColumns as $msc) {
            $guideData[] = [$msc['key'], 'Tidak', $msc['desc']];
        }

        $guideData[] = ['minimum_stock_umum', 'Tidak', 'Minimum stock umum / fallback. Kosongkan jika tidak perlu diubah.'];
        $guideData[] = ['minimum_order', 'Tidak', 'Default 1 jika kosong.'];
        $guideData[] = ['', '', ''];
        $guideData[] = ['CARA PAKAI', '', 'Download template ini → isi/edit kolom min_stock (kolom hijau yang digenerate otomatis sesuai gudang & divisi aktif) → Import kembali file ini.'];
        $guideData[] = ['', '', 'Barang yang kode_barang-nya sudah ada di sistem: hanya minimum stock yang akan diperbarui.'];
        $guideData[] = ['', '', 'Barang baru (kode_barang belum ada): akan ditambahkan sebagai master barang baru.'];

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Panduan');
        $guide->fromArray($guideData, null, 'A1');
        $guide->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $col) {
            $guide->getColumnDimension($col)->setWidth(35);
        }
        $guide->getStyle('A1:C' . count($guideData))->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'template_import_master_barang.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Proses upload file Excel Master Barang.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $importer = new MasterBarangImporter();
        $result = $importer->import($request->file('file')->getRealPath());

        return back()
            ->with('import_result_barang', $result)
            ->with('success', "Import Master Barang selesai. {$result['created']} barang baru ditambahkan, {$result['skipped']} barang diupdate minimum stock-nya.");
    }
} // <-- FIX: Kurung tutup ganda yang salah sudah dihapus