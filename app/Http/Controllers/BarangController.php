<?php

namespace App\Http\Controllers;

use App\Imports\MasterBarangImporter;
use App\Models\MasterBarang;
use App\Models\Kategori;
use App\Models\ResepBtklBop;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        $data = $query->orderBy('kode_barang', 'asc')->paginate(10)->withQueryString();
        
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

            // Simpan minimum stock & status aktif per outlet & divisi jika jenis BAHAN_BAKU
            if ($request->jenis_utama === 'BAHAN_BAKU') {
                $gudangListAll = \App\Models\MasterGudang::with('divisi')->get();
                foreach ($gudangListAll as $g) {
                    if ($g->divisi->count() > 0) {
                        foreach ($g->divisi as $div) {
                            $minVal = $request->input("min_stock_outlet.{$g->id}.{$div->id}");
                            $isActive = (bool)$request->input("min_stock_active.{$g->id}.{$div->id}", true);
                            
                            // Jika ada nilai minimum stock atau status dinonaktifkan (atau diset khusus), simpan
                            if (($minVal !== null && $minVal !== '') || !$isActive) {
                                \App\Models\BarangMinimumStock::create([
                                    'barang_id'     => $barang->id,
                                    'gudang_id'     => $g->id,
                                    'divisi_id'     => $div->id,
                                    'minimum_stock' => ($minVal !== null && $minVal !== '') ? (float)$minVal : 0,
                                    'is_active'     => $isActive,
                                ]);
                            }
                        }
                    } else {
                        $minVal = $request->input("min_stock_outlet.{$g->id}.none", $request->input("min_stock_outlet.{$g->id}"));
                        $isActive = (bool)$request->input("min_stock_active.{$g->id}.none", $request->input("min_stock_active.{$g->id}", true));
                        
                        if (($minVal !== null && $minVal !== '') || !$isActive) {
                            \App\Models\BarangMinimumStock::create([
                                'barang_id'     => $barang->id,
                                'gudang_id'     => $g->id,
                                'divisi_id'     => null,
                                'minimum_stock' => ($minVal !== null && $minVal !== '') ? (float)$minVal : 0,
                                'is_active'     => $isActive,
                            ]);
                        }
                    }
                }
            }
    
            return redirect()->route('barang.index')->with('success', 'Data berhasil ditambah');
    
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
    
        if (in_array($request->jenis_utama, ['BAHAN_BAKU', 'BAHAN_SETENGAH_JADI', 'OPERATIONAL'])) {
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
            'is_bahan_setengah_jadi' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI',
            'is_barang_jadi' => $request->jenis_utama == 'BARANG_JADI',
            'is_operational' => $request->jenis_utama == 'OPERATIONAL',
            'is_direct_consumption' => false,
    
            'hpp_referensi'  => $hpp,
            'harga_jual_b2b' => $harga_b2b,
            'harga_jual_pos' => $harga_pos,
            'minimum_stock'  => $request->minimum_stock,
            'minimum_stock_ck' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? $request->minimum_stock_ck : null,
            'minimum_stock_kejingga' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? $request->minimum_stock_kejingga : null,
            'minimum_stock_gaharu' => $request->jenis_utama == 'BAHAN_SETENGAH_JADI' ? $request->minimum_stock_gaharu : null,
            'minimum_order'  => $request->minimum_order ?? 1.00,
            'tipe_penjualan' => $request->jenis_utama == 'BARANG_JADI' ? $request->tipe_penjualan : null,
        ]);

        // Simpan / update minimum stock & status aktif per outlet & divisi
        \App\Models\BarangMinimumStock::where('barang_id', $data->id)->delete();
        if ($request->jenis_utama === 'BAHAN_BAKU') {
            $gudangListAll = \App\Models\MasterGudang::with('divisi')->get();
            foreach ($gudangListAll as $g) {
                if ($g->divisi->count() > 0) {
                    foreach ($g->divisi as $div) {
                        $minVal = $request->input("min_stock_outlet.{$g->id}.{$div->id}");
                        $isActive = (bool)$request->input("min_stock_active.{$g->id}.{$div->id}", true);
                        
                        if (($minVal !== null && $minVal !== '') || !$isActive) {
                            \App\Models\BarangMinimumStock::create([
                                'barang_id'     => $data->id,
                                'gudang_id'     => $g->id,
                                'divisi_id'     => $div->id,
                                'minimum_stock' => ($minVal !== null && $minVal !== '') ? (float)$minVal : 0,
                                'is_active'     => $isActive,
                            ]);
                        }
                    }
                } else {
                    $minVal = $request->input("min_stock_outlet.{$g->id}.none", $request->input("min_stock_outlet.{$g->id}"));
                    $isActive = (bool)$request->input("min_stock_active.{$g->id}.none", $request->input("min_stock_active.{$g->id}", true));
                    
                    if (($minVal !== null && $minVal !== '') || !$isActive) {
                        \App\Models\BarangMinimumStock::create([
                            'barang_id'     => $data->id,
                            'gudang_id'     => $g->id,
                            'divisi_id'     => null,
                            'minimum_stock' => ($minVal !== null && $minVal !== '') ? (float)$minVal : 0,
                            'is_active'     => $isActive,
                        ]);
                    }
                }
            }
        }
    
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

    /**
     * Download template Excel untuk import Master Barang.
     * Sheet 1 "Barang" = kolom yang harus diisi + 1 baris contoh.
     * Sheet 2 "Referensi Kategori" = daftar kategori yang tersedia saat ini di sistem,
     * supaya kolom "kategori" di sheet 1 diisi persis sama.
     */
    public function importTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Barang');

        $headers = [
            'kode_barang', 'nama', 'kategori', 'jenis_utama', 'satuan',
            'satuan_pembelian', 'konversi_pembelian', 'tipe_penjualan',
            'harga_jual_b2b', 'harga_jual_pos', 'hpp_referensi',
            'min_stock_ck', 'min_stock_kejingga_kitchen', 'min_stock_kejingga_barista', 'min_stock_kejingga_server',
            'min_stock_gaharu_kitchen', 'min_stock_gaharu_barista', 'min_stock_gaharu_server', 'min_stock_b2b',
            'minimum_stock_umum', 'minimum_order',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);
        $sheet->getStyle('A1:U1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:U1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D88656');

        // Baris contoh (boleh dihapus user sebelum import)
        $sheet->fromArray([
            'BMB003', 'SAMBAL MATAH', 'BUMBU', 'BAHAN_BAKU', 'GR',
            'JERIGEN', '5000', '',
            '0', '0', '0', 
            '5000', '2000', '1000', '',
            '3000', '1500', '', '',
            '', '1',
        ], null, 'A2');
        $sheet->fromArray([
            'BSJ001', 'SAUS BOLOGNESE JADI', 'BUMBU', 'BAHAN_SETENGAH_JADI', 'GR',
            '', '1', '',
            '0', '0', '0', 
            '2000', '1000', '', '',
            '1000', '', '', '',
            '', '1',
        ], null, 'A3');

        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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

        // Sheet panduan singkat
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Panduan');
        $guide->fromArray([
            ['Kolom', 'Wajib?', 'Keterangan'],
            ['kode_barang', 'Ya', 'Harus unik. Jika kode sudah ada di sistem, baris akan DILEWATI otomatis.'],
            ['nama', 'Ya', 'Nama barang.'],
            ['kategori', 'Ya', 'Isi persis sama dengan nama di sheet "Referensi Kategori".'],
            ['jenis_utama', 'Ya', 'Salah satu: BAHAN_BAKU, BAHAN_SETENGAH_JADI, BARANG_JADI, OPERATIONAL. Bahan Setengah Jadi = barang olahan awal (mis. saus dasar) yang bisa punya resep sendiri sekaligus dipakai sebagai bahan di resep lain.'],
            ['satuan', 'Ya', 'Contoh: GR, KG, PCS, LITER'],
            ['satuan_pembelian', 'Tidak', 'Kosongkan jika tidak ada satuan pembelian berbeda.'],
            ['konversi_pembelian', 'Tidak', 'Default 1 jika kosong.'],
            ['tipe_penjualan', 'Wajib jika BARANG_JADI', 'Salah satu: POS Kejingga, POS Gaharu, B2B'],
            ['harga_jual_b2b', 'Tidak', 'Hanya dipakai jika jenis_utama = BARANG_JADI.'],
            ['harga_jual_pos', 'Tidak', 'Hanya dipakai jika jenis_utama = BARANG_JADI.'],
            ['hpp_referensi', 'Tidak', 'Default 0 jika kosong.'],
            ['min_stock_ck', 'Tidak', 'Minimum stock Central Kitchen (Bahan Baku / Bahan Setengah Jadi). Boleh kosong.'],
            ['min_stock_kejingga_kitchen', 'Tidak', 'Minimum stock KeJingga - Divisi Kitchen. Boleh kosong.'],
            ['min_stock_kejingga_barista', 'Tidak', 'Minimum stock KeJingga - Divisi Barista. Boleh kosong.'],
            ['min_stock_kejingga_server', 'Tidak', 'Minimum stock KeJingga - Divisi Server. Boleh kosong.'],
            ['min_stock_gaharu_kitchen', 'Tidak', 'Minimum stock Gaharu - Divisi Kitchen. Boleh kosong.'],
            ['min_stock_gaharu_barista', 'Tidak', 'Minimum stock Gaharu - Divisi Barista. Boleh kosong.'],
            ['min_stock_gaharu_server', 'Tidak', 'Minimum stock Gaharu - Divisi Server. Boleh kosong.'],
            ['min_stock_b2b', 'Tidak', 'Minimum stock Gudang B2B. Boleh kosong.'],
            ['minimum_stock_umum', 'Tidak', 'Minimum stock umum / fallback. Boleh kosong.'],
            ['minimum_order', 'Tidak', 'Default 1 jika kosong.'],
        ], null, 'A1');
        $guide->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $col) {
            $guide->getColumnDimension($col)->setWidth(30);
        }
        $guide->getStyle('A1:C22')->getAlignment()->setWrapText(true);

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
            ->with('success', "Import Master Barang selesai. {$result['created']} barang ditambahkan, {$result['skipped']} dilewati (kode sudah ada).");
    }
} // <-- FIX: Kurung tutup ganda yang salah sudah dihapus