<?php

namespace App\Http\Controllers;

use App\Imports\ResepImporter;
use App\Models\ResepBahanBaku;
use App\Models\ResepBtklBop;
use App\Models\MasterBarang;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ResepBtklBopController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = ResepBtklBop::whereHas('produk')->with(['produk', 'bahanbaku']);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereHas('produk', function($qp) use ($search) {
                    $qp->where('nama', 'like', "%{$search}%")
                       ->orWhere('kode_barang', 'like', "%{$search}%");
                });
            });
        }

        // Paginasi 10 data per halaman
        $data = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $produk = MasterBarang::where(function($q) {
            $q->where('is_barang_jadi', '1')->orWhere('is_bahan_setengah_jadi', '1');
        })->where('is_active', true)->orderBy('nama')->get();
        
        $bahan  = MasterBarang::where(function($q) {
            $q->where('is_bahan_baku', '1')->orWhere('is_bahan_setengah_jadi', '1');
        })->where('is_active', true)->orderBy('nama')->get();

        return view('resep.index', compact('data', 'produk', 'bahan', 'search'));
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
        $produk = MasterBarang::where(function($q) {
            $q->where('is_barang_jadi', '1')->orWhere('is_bahan_setengah_jadi', '1');
        })->where('is_active', true)->get();
        $bahan  = MasterBarang::where(function($q) {
            $q->where('is_bahan_baku', '1')->orWhere('is_bahan_setengah_jadi', '1');
        })->where('is_active', true)->get();

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
        $produk = MasterBarang::where(function($q) {
            $q->where('is_barang_jadi', 1)->orWhere('is_bahan_setengah_jadi', 1);
        })->where('is_active', true)->orderBy('nama')->get();
        $bahan  = MasterBarang::where(function($q) {
            $q->where('is_bahan_baku', 1)->orWhere('is_bahan_setengah_jadi', 1);
        })->where('is_active', true)->orderBy('nama')->get();
    
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

        $page = $request->query('page', 1);
        return redirect()->route('resep.index', ['page' => $page])->with('success', 'Resep dan koneksi produk berhasil diupdate');
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

    /**
     * Download template Excel untuk import Resep.
     * Format: 1 baris = 1 bahan baku. Baris dengan kode_produk sama akan digabung
     * menjadi 1 resep dengan banyak bahan.
     * Sheet 2 "Referensi Barang" = daftar kode barang (bahan baku & barang jadi) yang
     * tersedia saat ini di sistem.
     */
    public function importTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resep');

        $headers = [
            'kode_produk', 'output_qty', 'satuan_output', 'btkl_per_batch', 'bop_per_batch',
            'kode_bahan', 'qty_bahan', 'satuan_bahan',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D88656');

        // Contoh: 1 produk (BMB001) dengan 2 bahan baku, plus contoh resep berjenjang:
        // BSJ001 (Bahan Setengah Jadi) punya resepnya sendiri, lalu dipakai sebagai
        // bahan di resep BJD010 (Barang Jadi).
        $sheet->fromArray([
            ['BMB001', '1', 'Batch', '50000', '20000', 'BMB002', '200', 'GR'],
            ['BMB001', '1', 'Batch', '50000', '20000', 'BBK010', '2', 'PCS'],
            ['BSJ001', '5000', 'Batch', '30000', '10000', 'BMB002', '3000', 'GR'],
            ['BJD010', '1', 'Batch', '15000', '5000', 'BSJ001', '150', 'GR'],
        ], null, 'A2');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet referensi kode barang yang tersedia saat ini
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi Barang');
        $refSheet->fromArray(['kode_barang', 'nama', 'jenis', 'satuan'], null, 'A1');
        $refSheet->getStyle('A1:D1')->getFont()->setBold(true);

        $row = 2;
        foreach (MasterBarang::withoutGlobalScopes()->orderBy('kode_barang')->get() as $b) {
            if ($b->is_barang_jadi) {
                $jenis = 'Barang Jadi';
            } elseif ($b->is_bahan_setengah_jadi) {
                $jenis = 'Bahan Setengah Jadi';
            } elseif ($b->is_bahan_baku) {
                $jenis = 'Bahan Baku';
            } else {
                $jenis = 'Operational';
            }
            $refSheet->setCellValue('A' . $row, $b->kode_barang);
            $refSheet->setCellValue('B' . $row, $b->nama);
            $refSheet->setCellValue('C' . $row, $jenis);
            $refSheet->setCellValue('D' . $row, $b->satuan);
            $row++;
        }
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $refSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet panduan singkat
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Panduan');
        $guide->fromArray([
            ['Kolom', 'Wajib?', 'Keterangan'],
            ['kode_produk', 'Ya', 'Kode barang jenis Barang Jadi ATAU Bahan Setengah Jadi (lihat sheet "Referensi Barang"). Jika produk sudah punya resep, seluruh baris untuk kode ini akan DILEWATI otomatis.'],
            ['output_qty', 'Ya', 'Jumlah output per batch. Cukup diisi di baris pertama tiap produk (jika diisi berulang di tiap baris, akan diambil dari baris pertama).'],
            ['satuan_output', 'Tidak', 'Default "Batch" jika kosong.'],
            ['btkl_per_batch', 'Tidak', 'Biaya tenaga kerja per batch. Default 0.'],
            ['bop_per_batch', 'Tidak', 'Biaya overhead per batch. Default 0.'],
            ['kode_bahan', 'Ya', 'Kode barang jenis Bahan Baku ATAU Bahan Setengah Jadi (lihat sheet "Referensi Barang"). 1 baris = 1 bahan. Ini memungkinkan resep berjenjang, mis. "Saus Bolognese" (Bahan Setengah Jadi) dipakai sebagai bahan resep "Spaghetti Bolognese".'],
            ['qty_bahan', 'Ya', 'Jumlah bahan yang dipakai untuk 1 batch resep ini.'],
            ['satuan_bahan', 'Tidak', 'Jika kosong, akan memakai satuan utama bahan tersebut.'],
            ['', '', 'Cara isi: ulang kode_produk yang sama di beberapa baris untuk menambahkan lebih dari 1 bahan ke resep yang sama (lihat contoh di sheet Resep).'],
        ], null, 'A1');
        $guide->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $col) {
            $guide->getColumnDimension($col)->setWidth(30);
        }
        $guide->getStyle('A1:C10')->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'template_import_resep.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Proses upload file Excel Resep.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $importer = new ResepImporter();
        $result = $importer->import($request->file('file')->getRealPath());

        return back()
            ->with('import_result_resep', $result)
            ->with('success', "Import Resep selesai. {$result['createdRecipes']} resep dibuat, {$result['skippedRecipes']} dilewati (produk sudah punya resep).");
    }
}