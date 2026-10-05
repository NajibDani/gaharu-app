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

    /**
     * Detail rincian formula resep & HPP per unit untuk pop-up modal
     */
    public function getHppDetail($barangId)
    {
        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        if (!$barang) {
            return response()->json(['error' => 'Barang tidak ditemukan.'], 404);
        }

        $fifoService = app(FifoService::class);
        
        // Cari gudang Central Kitchen (kategori Produksi)
        $gudangCk = \Illuminate\Support\Facades\DB::table('master_gudang')
            ->where(function ($q) {
                $q->where('kategori', 'Produksi')
                  ->orWhere('nama', 'like', '%Central Kitchen%');
            })
            ->orderByRaw("CASE WHEN kategori = 'Produksi' THEN 0 ELSE 1 END")
            ->first();
        $gudangCkId = $gudangCk ? (int) $gudangCk->id : 1;

        $resep = $this->findResep($barang);

        if (!$resep || $resep->bahanbaku->isEmpty()) {
            $fallbackHpp = floatval($barang->hpp_referensi ?? ($barang->harga_beli ?? 0));
            return response()->json([
                'has_resep'          => false,
                'nama_produk'        => $barang->nama,
                'kode_barang'        => $barang->kode_barang,
                'satuan'             => $barang->satuan ?? 'unit',
                'hpp_referensi'      => $fallbackHpp,
                'formatted_hpp'      => number_format($fallbackHpp, 0, ',', '.'),
                'ingredients'        => [],
                'output_qty'         => 1,
                'satuan_output'      => $barang->satuan ?? 'unit',
                'total_bbb'          => $fallbackHpp,
                'formatted_bbb'      => number_format($fallbackHpp, 0, ',', '.'),
                'btkl'               => 0,
                'formatted_btkl'     => '0',
                'bop'                => 0,
                'formatted_bop'      => '0',
                'total_hpp_batch'    => $fallbackHpp,
                'formatted_hpp_batch'=> number_format($fallbackHpp, 0, ',', '.'),
                'hpp_per_unit'       => $fallbackHpp,
                'formatted_per_unit' => number_format($fallbackHpp, 0, ',', '.'),
            ]);
        }

        $outputQty = floatval($resep->output_qty) > 0 ? floatval($resep->output_qty) : 1.0;
        $ingredients = [];
        $totalBbb = $this->expandResep($resep, 1.0, 0, [$barang->id], $ingredients, $fifoService, $gudangCkId, $outputQty);

        $btkl = $totalBbb * 0.20;
        $bop = $totalBbb * 0.10;
        $totalHppBatch = $totalBbb * 1.30;
        $hppPerUnit = round($totalHppBatch / $outputQty, 2);

        return response()->json([
            'has_resep'           => true,
            'nama_produk'         => $barang->nama,
            'kode_barang'         => $barang->kode_barang,
            'satuan'              => $barang->satuan ?? 'unit',
            'output_qty'          => $outputQty,
            'satuan_output'       => $resep->satuan_output ?? $barang->satuan ?? 'unit',
            'total_bbb'           => $totalBbb,
            'formatted_bbb'       => number_format($totalBbb, 0, ',', '.'),
            'btkl'                => $btkl,
            'formatted_btkl'      => number_format($btkl, 0, ',', '.'),
            'bop'                 => $bop,
            'formatted_bop'       => number_format($bop, 0, ',', '.'),
            'total_hpp_batch'     => $totalHppBatch,
            'formatted_hpp_batch' => number_format($totalHppBatch, 0, ',', '.'),
            'hpp_per_unit'        => $hppPerUnit,
            'formatted_per_unit'  => number_format($hppPerUnit, 0, ',', '.'),
            'ingredients'         => $ingredients,
        ]);
    }

    /**
     * Cari resep produk: via resep_id, produk_id, atau barang lain bernama sama
     * (misal barang POS yang resepnya tercatat pada barang CK / BSJ dengan nama identik).
     */
    private function findResep($barang)
    {
        $with = ['bahanbaku.bahan'];
        $candidates = [];

        $rawResepId = $barang->getAttributes()['resep_id'] ?? null;
        if (!empty($rawResepId)) {
            $candidates[] = \App\Models\ResepBtklBop::with($with)->find($rawResepId);
        }
        $candidates[] = \App\Models\ResepBtklBop::with($with)->where('produk_id', $barang->id)->first();

        foreach ($candidates as $r) {
            if ($r && $r->bahanbaku->isNotEmpty()) return $r;
        }

        // Fallback: barang lain dengan nama sama yang memiliki resep
        $sameNameIds = MasterBarang::withoutGlobalScopes()
            ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim($barang->nama))])
            ->where('id', '!=', $barang->id)
            ->pluck('id');
        if ($sameNameIds->isNotEmpty()) {
            $r = \App\Models\ResepBtklBop::with($with)
                ->whereIn('produk_id', $sameNameIds)
                ->whereHas('bahanbaku')
                ->first();
            if ($r) return $r;
        }

        return null;
    }

    /**
     * Jabarkan resep secara rekursif (resep berlapis / bertingkat).
     * Mengembalikan total biaya bahan baku (hanya bahan baku paling dasar) untuk 1 batch produk utama.
     *
     * @param float $multiplier skala resep ini relatif terhadap 1 batch produk utama
     */
    private function expandResep($resep, float $multiplier, int $level, array $visited, array &$rows, $fifoService, int $gudangId, float $topOutput): float
    {
        $total = 0.0;

        foreach ($resep->bahanbaku as $bb) {
            $bahan = $bb->bahan;
            if (!$bahan) continue;

            $qtyBatch = floatval($bb->qty_bahan) * $multiplier;
            $satuan = $bb->satuan ?? $bahan->satuan ?? 'unit';

            $subResep = null;
            if ($level < 6 && !in_array($bahan->id, $visited)) {
                $subResep = $this->findResep($bahan);
            }

            $row = [
                'level'          => $level,
                'is_parent'      => (bool) $subResep,
                'nama_bahan'     => $bahan->nama,
                'kode_bahan'     => $bahan->kode_barang,
                'is_bsj'         => (bool) $bahan->is_bahan_setengah_jadi,
                'is_pos'         => (bool) $bahan->is_barang_jadi,
                'tipe_penjualan' => $bahan->tipe_penjualan,
                'satuan'         => $satuan,
                'qty_batch'      => round($qtyBatch, 4),
                'qty_per_unit'   => round($qtyBatch / $topOutput, 4),
            ];

            if ($subResep) {
                $idx = count($rows);
                $rows[] = $row; // placeholder, diisi setelah anak dihitung
                $subOutput = floatval($subResep->output_qty) > 0 ? floatval($subResep->output_qty) : 1.0;
                $cost = $this->expandResep(
                    $subResep, $qtyBatch / $subOutput, $level + 1,
                    array_merge($visited, [$bahan->id]), $rows, $fifoService, $gudangId, $topOutput
                );
                $harga = $qtyBatch > 0 ? $cost / $qtyBatch : 0;
                $rows[$idx] = array_merge($row, $this->costFields($harga, $cost, $topOutput), [
                    'sub_output' => $subOutput . ' ' . ($subResep->satuan_output ?? $satuan),
                ]);
            } else {
                $harga = $fifoService->getHargaTerakhirBahan((int) $bahan->id, $gudangId);
                if ($harga <= 0) {
                    $harga = floatval($bahan->hpp_referensi ?? ($bahan->harga_beli ?? 0));
                }
                $cost = $qtyBatch * $harga;
                $rows[] = array_merge($row, $this->costFields($harga, $cost, $topOutput));
            }

            $total += $cost;
        }

        return $total;
    }

    private function costFields(float $harga, float $cost, float $topOutput): array
    {
        $perUnit = $cost / $topOutput;
        return [
            'harga_satuan'       => $harga,
            'formatted_harga'    => number_format($harga, $harga < 100 ? 2 : 0, ',', '.'),
            'subtotal_batch'     => $cost,
            'formatted_subtotal' => number_format($cost, 0, ',', '.'),
            'subtotal_per_unit'  => round($perUnit, 2),
            'formatted_per_unit' => number_format($perUnit, 0, ',', '.'),
        ];
    }
}
