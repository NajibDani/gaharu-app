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

        if ($request->has('refresh_all')) {
            MasterBarang::syncAllResepIds();
            MasterBarang::syncAllResepSatuan();
            \App\Services\FifoService::clearHargaCache();
            return redirect()->route('harga-b2b.index', $request->except('refresh_all'))
                ->with('success', 'Seluruh formulasi resep dan HPP berhasil diperbarui per tanggal ' . now()->translatedFormat('d F Y H:i') . '!');
        }

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
                $detail = $this->getHppDetail($item->barang_id)->getData(true);
                $item->hpp_referensi = floatval($detail['hpp_per_unit'] ?? 0);
                if ($item->mengikuti_hpp) {
                    $item->harga_b2b = $item->hpp_referensi;
                    $item->laba_unit = 0.0;
                    $item->margin_persen = 0.0;
                } else {
                    $item->laba_unit = floatval($item->harga_b2b) - $item->hpp_referensi;
                    $item->margin_persen = floatval($item->harga_b2b) > 0 ? round(($item->laba_unit / floatval($item->harga_b2b)) * 100, 1) : 0;
                }
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

        $isMengikutiHpp = $request->boolean('mengikuti_hpp');

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'barang_id'   => 'required|exists:master_barang,id',
            'harga_b2b'   => $isMengikutiHpp ? 'nullable' : 'required',
        ]);

        $cleanHarga = floatval(str_replace(['.', ','], ['', '.'], str_replace('Rp', '', $request->harga_b2b ?? 0)));
        if ($cleanHarga < 0) {
            $cleanHarga = 0;
        }

        if ($isMengikutiHpp && $cleanHarga <= 0) {
            $cleanHarga = HargaBarangB2b::getRealtimeHpp($request->barang_id);
        }

        HargaBarangB2b::updateOrCreate(
            [
                'customer_id' => $request->customer_id,
                'barang_id'   => $request->barang_id,
            ],
            [
                'harga_b2b'     => $cleanHarga,
                'mengikuti_hpp' => $isMengikutiHpp,
                'keterangan'    => $request->keterangan,
            ]
        );

        $msg = $isMengikutiHpp 
            ? 'Pengaturan B2B berhasil disimpan: Harga otomatis mengikuti HPP riil Central Kitchen saat pemesanan.' 
            : 'Harga jual B2B khusus outlet berhasil disimpan!';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Update harga jual B2B
     */
    public function update(Request $request, $id)
    {
        HargaBarangB2b::ensureTableExists();

        $harga = HargaBarangB2b::findOrFail($id);

        $isMengikutiHpp = $request->has('mengikuti_hpp') ? $request->boolean('mengikuti_hpp') : $harga->mengikuti_hpp;

        if (!$isMengikutiHpp) {
            $request->validate([
                'harga_b2b' => 'required',
            ]);
        }

        $cleanHarga = floatval(str_replace(['.', ','], ['', '.'], str_replace('Rp', '', $request->harga_b2b ?? 0)));
        if ($cleanHarga < 0) {
            $cleanHarga = 0;
        }

        if ($isMengikutiHpp && $cleanHarga <= 0) {
            $cleanHarga = HargaBarangB2b::getRealtimeHpp($harga->barang_id);
        }

        $harga->update([
            'harga_b2b'     => $cleanHarga,
            'mengikuti_hpp' => $isMengikutiHpp,
            'keterangan'    => $request->keterangan ?? $harga->keterangan,
        ]);

        $msg = $isMengikutiHpp
            ? 'Perubahan disimpan: Harga B2B outlet ini disetel mengikuti HPP riil saat pemesanan.'
            : 'Perubahan harga jual B2B berhasil diperbarui!';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Toggle cepat status mengikuti HPP riil langsung dari tombol tabel
     */
    public function toggleHpp($id)
    {
        HargaBarangB2b::ensureTableExists();
        $harga = HargaBarangB2b::findOrFail($id);
        $newStatus = !$harga->mengikuti_hpp;

        $updateData = ['mengikuti_hpp' => $newStatus];
        if ($newStatus) {
            $liveHpp = HargaBarangB2b::getRealtimeHpp($harga->barang_id);
            if ($liveHpp > 0) {
                $updateData['harga_b2b'] = $liveHpp;
            }
        }

        $harga->update($updateData);

        $outletName = $harga->customer->nama ?? 'Outlet';
        $barangName = $harga->barang->nama ?? 'Produk';

        $msg = $newStatus 
            ? "Status diubah: [{$barangName}] untuk {$outletName} sekarang MENGIKUTI HPP RIIL saat pemesanan (0% margin)."
            : "Status diubah: [{$barangName}] untuk {$outletName} kembali menggunakan HARGA MANUAL (tetap).";

        return redirect()->back()->with('success', $msg);
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
    public function getHppDetail($barangId, Request $request = null)
    {
        $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
        if (!$barang) {
            return response()->json(['error' => 'Barang tidak ditemukan.'], 404);
        }

        if (!$request) {
            $request = request();
        }

        $isRefresh = $request->boolean('refresh') || $request->has('refresh');
        $tanggal = $request->query('tanggal');
        if ($tanggal && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = null;
        }

        if ($isRefresh) {
            MasterBarang::syncAllResepIds();
            MasterBarang::syncAllResepSatuan();
            \App\Services\FifoService::clearHargaCache();
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
            $fallbackHpp = floatval($barang->hpp_referensi ?? 0);
            return response()->json([
                'has_resep'           => false,
                'nama_produk'         => $barang->nama,
                'kode_barang'         => $barang->kode_barang,
                'satuan'              => $barang->satuan ?? 'unit',
                'hpp_referensi'       => $fallbackHpp,
                'formatted_hpp'       => number_format($fallbackHpp, 2, ',', '.'),
                'ingredients'         => [],
                'output_qty'          => 1,
                'satuan_output'       => $barang->satuan ?? 'unit',
                'total_bbb'           => $fallbackHpp,
                'formatted_bbb'       => number_format($fallbackHpp, 2, ',', '.'),
                'btkl'                => 0,
                'formatted_btkl'      => '0,00',
                'bop'                 => 0,
                'formatted_bop'       => '0,00',
                'total_hpp_batch'     => $fallbackHpp,
                'formatted_hpp_batch' => number_format($fallbackHpp, 2, ',', '.'),
                'hpp_per_unit'        => $fallbackHpp,
                'formatted_per_unit'  => number_format($fallbackHpp, 2, ',', '.'),
                'refreshed_at'        => now()->format('d/m/Y H:i'),
                'refreshed_date'      => $tanggal ?? date('Y-m-d'),
                'as_of_date_label'    => $tanggal ? \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') : now()->translatedFormat('d F Y H:i'),
                'message'             => 'Data HPP referensi master barang berhasil dimuat.',
            ]);
        }

        $outputQty = floatval($resep->output_qty) > 0 ? floatval($resep->output_qty) : 1.0;
        $ingredients = [];
        $totalBbb = $this->expandResep($resep, 1.0, 0, [$barang->id], $ingredients, $fifoService, $gudangCkId, $outputQty, $tanggal);

        $btkl = $totalBbb * 0.20;
        $bop = $totalBbb * 0.10;
        $totalHppBatch = $totalBbb * 1.30;
        $hppPerUnit = round($totalHppBatch / $outputQty, 2);

        // Jika user melakukan refresh, perbarui juga hpp_referensi di master_barang agar tersinkronisasi
        if ($isRefresh && $hppPerUnit > 0) {
            MasterBarang::withoutGlobalScopes()->where('id', $barang->id)->update([
                'hpp_referensi' => $hppPerUnit,
                'updated_at'    => now(),
            ]);
        }

        return response()->json([
            'has_resep'           => true,
            'nama_produk'         => $barang->nama,
            'kode_barang'         => $barang->kode_barang,
            'satuan'              => $barang->satuan ?? 'unit',
            'output_qty'          => $outputQty,
            'satuan_output'       => $resep->satuan_output ?? $barang->satuan ?? 'unit',
            'total_bbb'           => $totalBbb,
            'formatted_bbb'       => number_format($totalBbb, 2, ',', '.'),
            'btkl'                => $btkl,
            'formatted_btkl'      => number_format($btkl, 2, ',', '.'),
            'bop'                 => $bop,
            'formatted_bop'       => number_format($bop, 2, ',', '.'),
            'total_hpp_batch'     => $totalHppBatch,
            'formatted_hpp_batch' => number_format($totalHppBatch, 2, ',', '.'),
            'hpp_per_unit'        => $hppPerUnit,
            'formatted_per_unit'  => number_format($hppPerUnit, 2, ',', '.'),
            'ingredients'         => $ingredients,
            'refreshed_at'        => now()->format('d/m/Y H:i'),
            'refreshed_date'      => $tanggal ?? date('Y-m-d'),
            'as_of_date_label'    => $tanggal ? \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') : now()->translatedFormat('d F Y H:i'),
            'message'             => 'HPP dan formulasi resep berhasil diperbarui!',
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
    private function expandResep($resep, float $multiplier, int $level, array $visited, array &$rows, $fifoService, int $gudangId, float $topOutput, ?string $asOfDate = null): float
    {
        $total = 0.0;
        $gudangUtamaId = \App\Models\MasterGudang::getGudangUtamaId();

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
                    array_merge($visited, [$bahan->id]), $rows, $fifoService, $gudangId, $topOutput, $asOfDate
                );
                $harga = $qtyBatch > 0 ? $cost / $qtyBatch : 0;
                $rows[$idx] = array_merge($row, $this->costFields($harga, $cost, $topOutput), [
                    'sub_output' => $subOutput . ' ' . ($subResep->satuan_output ?? $satuan),
                ]);
            } else {
                $harga = $fifoService->getHargaTerakhirBahan((int) $bahan->id, $gudangId, [], $asOfDate);
                if ($harga <= 0 && $gudangUtamaId && $gudangId != $gudangUtamaId) {
                    $harga = $fifoService->getHargaTerakhirBahan((int) $bahan->id, $gudangUtamaId, [], $asOfDate);
                }
                if ($harga <= 0) {
                    $harga = floatval($bahan->hpp_referensi ?? 0);
                    $satDasar = strtolower(trim($bahan->satuan ?? ''));
                    $konv = floatval($bahan->konversi_pembelian ?? 1);
                    if (in_array($satDasar, ['gr', 'ml', 'gram']) && $konv > 1) {
                        if ($harga > 500) {
                            $harga = round($harga / $konv, 4);
                        } elseif ($harga > 0 && $harga < 1.0 && ($harga * $konv) >= 1.0 && ($harga * $konv) <= 1000) {
                            $harga = round($harga * $konv, 4);
                        }
                    }
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
        $perUnit = $topOutput > 0 ? ($cost / $topOutput) : 0;
        $decHarga = (fmod($harga, 1) != 0 || $harga < 100) ? 2 : 0;

        // Biaya / Satuan: tambahkan 2 angka setelah koma agar lebih akurat (tidak Rp 0).
        // Jika nilainya sangat kecil (> 0 dan round 2 desimal adalah 0), tampilkan hingga 4 angka setelah koma agar nilai tetap terbaca jelas.
        $decPerUnit = 2;
        if ($perUnit > 0 && round($perUnit, 2) == 0.0) {
            $decPerUnit = 4;
        }

        return [
            'harga_satuan'       => $harga,
            'formatted_harga'    => number_format($harga, $decHarga, ',', '.'),
            'subtotal_batch'     => $cost,
            'formatted_subtotal' => number_format($cost, 2, ',', '.'),
            'subtotal_per_unit'  => round($perUnit, 4),
            'formatted_per_unit' => number_format($perUnit, $decPerUnit, ',', '.'),
        ];
    }
}
