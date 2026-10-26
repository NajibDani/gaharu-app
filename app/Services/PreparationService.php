<?php

namespace App\Services;

use App\Models\MasterBarang;
use App\Models\Preparation;
use App\Models\PreparationInput;
use App\Models\PreparationOutput;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreparationService
{
    protected FifoService $fifoService;

    public function __construct(FifoService $fifoService)
    {
        $this->fifoService = $fifoService;
    }

    /**
     * Generate next document code (PRP-0001, PRP-0002, ...)
     */
    public static function generateKode(): string
    {
        $latest = DB::table('preparations')->orderBy('id', 'desc')->first();
        $nextNum = $latest ? ($latest->id + 1) : 1;

        do {
            $kode = 'PRP-' . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
            $exists = DB::table('preparations')->where('kode', $kode)->exists();
            if ($exists) {
                $nextNum++;
            }
        } while ($exists);

        return $kode;
    }

    /**
     * Get conversion factor to base unit
     */
    public static function getConversionFactor(MasterBarang $barang, ?string $inputSatuan): float
    {
        $baseSatuan = strtolower(trim($barang->satuan ?? ''));
        $targetSatuan = strtolower(trim($inputSatuan ?? ''));

        if (empty($targetSatuan) || $baseSatuan === $targetSatuan) {
            return 1.0;
        }

        // KG -> GR
        if (in_array($targetSatuan, ['kg', 'kilogram']) && in_array($baseSatuan, ['gr', 'gram', 'g'])) {
            return 1000.0;
        }
        // GR -> KG
        if (in_array($targetSatuan, ['gr', 'gram', 'g']) && in_array($baseSatuan, ['kg', 'kilogram'])) {
            return 0.001;
        }

        // L -> ML
        if (in_array($targetSatuan, ['l', 'liter', 'ltr']) && in_array($baseSatuan, ['ml', 'mililiter'])) {
            return 1000.0;
        }
        // ML -> L
        if (in_array($targetSatuan, ['ml', 'mililiter']) && in_array($baseSatuan, ['l', 'liter', 'ltr'])) {
            return 0.001;
        }

        // Satuan Pembelian check
        if (!empty($barang->satuan_pembelian) && strtolower(trim($barang->satuan_pembelian)) === $targetSatuan) {
            $k = (float)($barang->konversi_pembelian ?? 1);
            if ($k > 0) {
                return $k;
            }
        }

        return 1.0;
    }

    /**
     * Get available stock in specified gudang & optional divisi
     */
    public function getAvailableStock(int $barangId, int $gudangId, ?int $divisiId = null): float
    {
        $query = StokGudang::where('gudang_id', $gudangId)
            ->where('barang_id', $barangId);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        } else {
            $query->whereNull('divisi_id');
        }

        return (float)($query->value('jumlah') ?? 0);
    }

    /**
     * Create a new preparation draft
     */
    public function createDraft(array $data, int $userId): Preparation
    {
        $this->validateData($data);

        return DB::transaction(function () use ($data, $userId) {
            $kode = !empty($data['kode']) ? $data['kode'] : self::generateKode();

            $prep = Preparation::create([
                'kode'        => $kode,
                'tanggal'     => $data['tanggal'],
                'gudang_id'   => $data['gudang_id'],
                'divisi_id'   => !empty($data['divisi_id']) ? $data['divisi_id'] : null,
                'status'      => 'menunggu',
                'total_biaya' => 0,
                'keterangan'  => $data['keterangan'] ?? null,
                'created_by'  => $userId,
            ]);

            // Save Inputs
            foreach ($data['inputs'] as $in) {
                $barang = MasterBarang::findOrFail($in['barang_id']);
                $konversi = self::getConversionFactor($barang, $in['satuan'] ?? $barang->satuan);
                $qty = (float)$in['qty'];
                $qtyBase = round($qty * $konversi, 4);

                PreparationInput::create([
                    'preparation_id' => $prep->id,
                    'barang_id'      => $barang->id,
                    'qty'             => $qty,
                    'satuan'          => $in['satuan'] ?? $barang->satuan,
                    'konversi'        => $konversi,
                    'qty_base'        => $qtyBase,
                    'hpp_satuan'      => 0,
                    'total_biaya'     => 0,
                ]);
            }

            // Save Outputs
            foreach ($data['outputs'] as $out) {
                $barang = MasterBarang::findOrFail($out['barang_id']);
                $konversi = self::getConversionFactor($barang, $out['satuan'] ?? $barang->satuan);
                $qty = (float)$out['qty'];
                $qtyBase = round($qty * $konversi, 4);

                PreparationOutput::create([
                    'preparation_id' => $prep->id,
                    'barang_id'      => $barang->id,
                    'qty'             => $qty,
                    'satuan'          => $out['satuan'] ?? $barang->satuan,
                    'konversi'        => $konversi,
                    'qty_base'        => $qtyBase,
                    'hpp_satuan'      => 0,
                    'total_nilai'     => 0,
                ]);
            }

            return $prep->load(['inputs.barang', 'outputs.barang', 'gudang', 'divisi', 'creator']);
        });
    }

    /**
     * Update an existing preparation draft (or approved preparation if performed by superadmin)
     */
    public function updateDraft(Preparation $prep, array $data, int $userId): Preparation
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        if (!$isSuperAdmin && !$prep->canEdit($user)) {
            throw ValidationException::withMessages([
                'status' => ['Dokumen preparation dengan status "' . $prep->status . '" tidak dapat diubah.'],
            ]);
        }

        $this->validateData($data, $prep->id);

        return DB::transaction(function () use ($prep, $data, $userId, $isSuperAdmin) {
            $wasApproved = ($prep->status === 'disetujui');

            // If it was already approved, revert previous stock, FIFO, and batch effects
            if ($wasApproved) {
                $this->revertApprovalEffects($prep);
            }

            $prep->update([
                'tanggal'    => $data['tanggal'],
                'gudang_id'  => $data['gudang_id'],
                'divisi_id'  => !empty($data['divisi_id']) ? $data['divisi_id'] : null,
                'keterangan' => $data['keterangan'] ?? null,
                'status'     => $wasApproved ? 'menunggu' : $prep->status, // temporarily set to menunggu if re-approving
            ]);

            // Recreate inputs
            $prep->inputs()->delete();
            foreach ($data['inputs'] as $in) {
                $barang = MasterBarang::findOrFail($in['barang_id']);
                $konversi = self::getConversionFactor($barang, $in['satuan'] ?? $barang->satuan);
                $qty = (float)$in['qty'];
                $qtyBase = round($qty * $konversi, 4);

                PreparationInput::create([
                    'preparation_id' => $prep->id,
                    'barang_id'      => $barang->id,
                    'qty'             => $qty,
                    'satuan'          => $in['satuan'] ?? $barang->satuan,
                    'konversi'        => $konversi,
                    'qty_base'        => $qtyBase,
                    'hpp_satuan'      => 0,
                    'total_biaya'     => 0,
                ]);
            }

            // Recreate outputs
            $prep->outputs()->delete();
            foreach ($data['outputs'] as $out) {
                $barang = MasterBarang::findOrFail($out['barang_id']);
                $konversi = self::getConversionFactor($barang, $out['satuan'] ?? $barang->satuan);
                $qty = (float)$out['qty'];
                $qtyBase = round($qty * $konversi, 4);

                PreparationOutput::create([
                    'preparation_id' => $prep->id,
                    'barang_id'      => $barang->id,
                    'qty'             => $qty,
                    'satuan'          => $out['satuan'] ?? $barang->satuan,
                    'konversi'        => $konversi,
                    'qty_base'        => $qtyBase,
                    'hpp_satuan'      => 0,
                    'total_nilai'     => 0,
                ]);
            }

            // If it was previously approved, re-run approval immediately with new items
            if ($wasApproved) {
                $prep->refresh();
                return $this->approve($prep, $userId);
            }

            return $prep->fresh(['inputs.barang', 'outputs.barang', 'gudang', 'divisi', 'creator']);
        });
    }

    /**
     * Approve preparation (FIFO consumption, output batch creation, stock mutation, HPP calculation)
     */
    public function approve(Preparation $prep, int $userId): Preparation
    {
        return DB::transaction(function () use ($prep, $userId) {
            // Pessimistic lock on preparation record to prevent double approval race condition
            /** @var Preparation $lockedPrep */
            $lockedPrep = Preparation::where('id', $prep->id)->lockForUpdate()->first();

            if (!$lockedPrep) {
                throw new \Exception('Dokumen preparation tidak ditemukan.');
            }

            if ($lockedPrep->status === 'disetujui') {
                throw new \Exception('Dokumen ini sudah pernah disetujui sebelumnya.');
            }

            if ($lockedPrep->status === 'ditolak') {
                throw new \Exception('Dokumen yang telah ditolak tidak dapat disetujui.');
            }

            $lockedPrep->load(['inputs.barang', 'outputs.barang']);

            // Validate that all raw materials are currently sufficient before executing FIFO
            $stockCheck = $this->checkStockAvailability($lockedPrep);
            if (!$stockCheck['is_sufficient']) {
                $details = [];
                foreach ($stockCheck['insufficient_items'] as $item) {
                    $details[] = "{$item['nama']} (Tersedia: {$item['tersedia']} {$item['satuan']}, Dibutuhkan: {$item['dibutuhkan']} {$item['satuan']})";
                }
                throw new \Exception('Stok bahan baku tidak mencukupi untuk disetujui: ' . implode(', ', $details) . '. Harap penuhi stok terlebih dahulu.');
            }

            $gudangId = $lockedPrep->gudang_id;
            $divisiId = $lockedPrep->divisi_id;
            $totalBiayaPreparation = 0;

            // 1. Process Inputs using FIFO
            foreach ($lockedPrep->inputs as $input) {
                $barang = $input->barang;
                $qtyDibutuhkan = (float)$input->qty_base;

                // Lock and consume FIFO batches
                $fifoLayers = $this->fifoService->consumeFIFO(
                    $input->barang_id,
                    $qtyDibutuhkan,
                    $gudangId,
                    true, // allowNegative = true: allow fallback to latest purchase price when physical stock exists but batches in StokGudangBatch are depleted
                    $divisiId
                );

                $totalBiayaInput = 0;
                foreach ($fifoLayers as $layer) {
                    $totalBiayaInput += (float)$layer['qty_keluar'] * (float)$layer['harga_per_qty'];
                }

                $hppSatuanInput = $qtyDibutuhkan > 0 ? round($totalBiayaInput / $qtyDibutuhkan, 4) : 0;

                // Update input row
                $input->update([
                    'hpp_satuan'     => $hppSatuanInput,
                    'total_biaya'    => $totalBiayaInput,
                    'fifo_breakdown' => $fifoLayers,
                ]);

                // Update summary stock StokGudang
                $stokQuery = StokGudang::where('gudang_id', $gudangId)
                    ->where('barang_id', $input->barang_id);
                if ($divisiId) {
                    $stokQuery->where('divisi_id', $divisiId);
                } else {
                    $stokQuery->whereNull('divisi_id');
                }
                $stokRow = $stokQuery->lockForUpdate()->first();

                if ($stokRow) {
                    $stokRow->decrement('jumlah', $qtyDibutuhkan);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangId,
                        'divisi_id' => $divisiId,
                        'barang_id' => $input->barang_id,
                        'jumlah'    => -$qtyDibutuhkan,
                    ]);
                }

                // Record mutasi keluar di Buku Pembantu Persediaan (transaksi_stok)
                TransaksiStok::create([
                    'tanggal'        => $lockedPrep->tanggal ? $lockedPrep->tanggal->format('Y-m-d H:i:s') : now(),
                    'tipe'           => 'keluar',
                    'source_type'    => 'preparation_bahan',
                    'source_id'      => $lockedPrep->id,
                    'gudang_asal_id' => $gudangId,
                    'divisi_asal_id' => $divisiId,
                    'barang_id'      => $input->barang_id,
                    'qty'            => $qtyDibutuhkan,
                    'total_harga'    => $totalBiayaInput,
                    'created_by'     => $userId,
                ]);

                $totalBiayaPreparation += $totalBiayaInput;
            }

            // 2. Process Outputs
            $totalOutputBaseQty = $lockedPrep->outputs->sum(function ($out) {
                return (float)$out->qty_base;
            });

            $supplierId = DB::table('suppliers')->value('id');

            foreach ($lockedPrep->outputs as $index => $output) {
                $qtyOutput = (float)$output->qty_base;

                // Cost allocation (if multiple outputs, distribute proportionally)
                $outputNilai = $totalOutputBaseQty > 0
                    ? round($totalBiayaPreparation * ($qtyOutput / $totalOutputBaseQty), 4)
                    : 0;
                $hppPerUnit = $qtyOutput > 0 ? round($outputNilai / $qtyOutput, 4) : 0;

                // Create new batch in StokGudangBatch
                $batchNumber = $lockedPrep->kode . '-BSJ' . ($index > 0 ? ('-' . ($index + 1)) : '');

                $batch = StokGudangBatch::create([
                    'gudang_id'           => $gudangId,
                    'divisi_id'           => $divisiId,
                    'supplier_id'         => $supplierId ?: null,
                    'barang_id'           => $output->barang_id,
                    'pembelian_id'        => null,
                    'pembelian_detail_id' => null,
                    'batch_number'        => $batchNumber,
                    'qty_masuk'           => $qtyOutput,
                    'qty_keluar'          => 0,
                    'qty_sisa'            => $qtyOutput,
                    'harga_per_qty'       => $hppPerUnit,
                    'is_habis'            => false,
                    'created_at'          => $lockedPrep->tanggal ? $lockedPrep->tanggal->startOfDay() : now(),
                ]);

                $output->update([
                    'hpp_satuan'  => $hppPerUnit,
                    'total_nilai' => $outputNilai,
                    'batch_id'    => $batch->id,
                ]);

                // Update summary stock StokGudang
                $stokOutQuery = StokGudang::where('gudang_id', $gudangId)
                    ->where('barang_id', $output->barang_id);
                if ($divisiId) {
                    $stokOutQuery->where('divisi_id', $divisiId);
                } else {
                    $stokOutQuery->whereNull('divisi_id');
                }
                $stokOutRow = $stokOutQuery->lockForUpdate()->first();

                if ($stokOutRow) {
                    $stokOutRow->increment('jumlah', $qtyOutput);
                } else {
                    StokGudang::create([
                        'gudang_id' => $gudangId,
                        'divisi_id' => $divisiId,
                        'barang_id' => $output->barang_id,
                        'jumlah'    => $qtyOutput,
                    ]);
                }

                // Record mutasi masuk di Buku Pembantu Persediaan (transaksi_stok)
                TransaksiStok::create([
                    'tanggal'          => $lockedPrep->tanggal ? $lockedPrep->tanggal->format('Y-m-d H:i:s') : now(),
                    'tipe'             => 'masuk',
                    'source_type'      => 'preparation_bahan',
                    'source_id'        => $lockedPrep->id,
                    'gudang_tujuan_id' => $gudangId,
                    'divisi_tujuan_id' => $divisiId,
                    'barang_id'        => $output->barang_id,
                    'qty'              => $qtyOutput,
                    'total_harga'      => $outputNilai,
                    'created_by'       => $userId,
                ]);

                // Update master_barang hpp_referensi
                MasterBarang::where('id', $output->barang_id)->update([
                    'hpp_referensi' => $hppPerUnit,
                ]);
            }

            // 3. Mark Preparation as Disetujui
            $lockedPrep->update([
                'status'      => 'disetujui',
                'total_biaya' => $totalBiayaPreparation,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $lockedPrep->fresh(['inputs.barang', 'outputs.barang', 'gudang', 'divisi', 'approver']);
        });
    }

    /**
     * Reject preparation with mandatory reason
     */
    public function reject(Preparation $prep, string $alasanTolak, int $userId): Preparation
    {
        if ($prep->status === 'disetujui') {
            throw ValidationException::withMessages([
                'status' => ['Dokumen preparation yang sudah disetujui tidak dapat ditolak.'],
            ]);
        }

        if (empty(trim($alasanTolak))) {
            throw ValidationException::withMessages([
                'alasan_tolak' => ['Alasan penolakan wajib diisi.'],
            ]);
        }

        $prep->update([
            'status'       => 'ditolak',
            'alasan_tolak' => trim($alasanTolak),
            'approved_by'  => $userId,
            'approved_at'  => now(),
        ]);

        return $prep;
    }

    /**
     * Revert approval effects (restore consumed raw material FIFO batches, remove created BSJ batches, remove TransaksiStok, and reconcile StokGudang)
     */
    public function revertApprovalEffects(Preparation $prep): void
    {
        $prep->loadMissing(['inputs', 'outputs']);
        $gudangId = $prep->gudang_id;
        $divisiId = $prep->divisi_id;
        $affectedBarangIds = [];

        // 1. Revert consumed raw material FIFO batches for each input
        foreach ($prep->inputs as $input) {
            $affectedBarangIds[] = $input->barang_id;
            $breakdown = $input->fifo_breakdown;
            if (is_array($breakdown)) {
                foreach ($breakdown as $layer) {
                    $bId = $layer['batch_id'] ?? null;
                    $qtyKeluar = (float)($layer['qty_keluar'] ?? 0);
                    if ($bId && $qtyKeluar > 0) {
                        $batch = StokGudangBatch::find($bId);
                        if ($batch) {
                            $batch->increment('qty_sisa', $qtyKeluar);
                            $batch->decrement('qty_keluar', min((float)$batch->qty_keluar, $qtyKeluar));
                            $batch->update(['is_habis' => false]);
                        }
                    }
                }
            }
        }

        // 2. Remove generated BSJ batches in StokGudangBatch
        foreach ($prep->outputs as $output) {
            $affectedBarangIds[] = $output->barang_id;
            if ($output->batch_id) {
                StokGudangBatch::where('id', $output->batch_id)->delete();
            }
        }
        // Fallback delete batches by batch_number pattern
        StokGudangBatch::where('batch_number', 'like', $prep->kode . '-BSJ%')->delete();

        // 3. Remove TransaksiStok records created by this preparation
        TransaksiStok::where('source_type', 'preparation_bahan')
            ->where('source_id', $prep->id)
            ->delete();

        // 4. Reconcile StokGudang for all affected barang
        $affectedBarangIds = array_unique(array_filter($affectedBarangIds));
        foreach ($affectedBarangIds as $bId) {
            StokGudang::reconcileStockSummary($bId, $gudangId, $divisiId);
        }
    }

    /**
     * Delete preparation (allowed if status is menunggu/ditolak, or by superadmin)
     */
    public function delete(Preparation $prep, ?\App\Models\User $user = null): bool
    {
        $user = $user ?? auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        if (!$isSuperAdmin && !$prep->canDelete($user)) {
            throw ValidationException::withMessages([
                'status' => ['Dokumen preparation dengan status "' . $prep->status . '" tidak dapat dihapus.'],
            ]);
        }

        return DB::transaction(function () use ($prep, $isSuperAdmin) {
            if ($prep->status === 'disetujui') {
                $this->revertApprovalEffects($prep);
            }

            $prep->inputs()->delete();
            $prep->outputs()->delete();
            return (bool)$prep->delete();
        });
    }

    /**
     * Validate preparation form data
     */
    protected function validateData(array $data, ?int $ignoreId = null): void
    {
        if (empty($data['tanggal'])) {
            throw ValidationException::withMessages(['tanggal' => ['Tanggal preparation wajib diisi.']]);
        }

        if (empty($data['gudang_id'])) {
            throw ValidationException::withMessages(['gudang_id' => ['Gudang/Outlet asal wajib dipilih.']]);
        }

        if (empty($data['inputs']) || !is_array($data['inputs'])) {
            throw ValidationException::withMessages(['inputs' => ['Daftar bahan baku yang dipakai wajib diisi minimal 1 bahan.']]);
        }

        if (empty($data['outputs']) || !is_array($data['outputs'])) {
            throw ValidationException::withMessages(['outputs' => ['Daftar bahan setengah jadi yang dihasilkan wajib diisi minimal 1 bahan.']]);
        }

        // Validate unique input items & qty > 0 & check available stock
        $gudangId = (int)$data['gudang_id'];
        $divisiId = !empty($data['divisi_id']) ? (int)$data['divisi_id'] : null;

        $inputBarangIds = [];
        foreach ($data['inputs'] as $idx => $in) {
            $bId = $in['barang_id'] ?? null;
            $qty = (float)($in['qty'] ?? 0);

            if (!$bId) {
                throw ValidationException::withMessages(["inputs.{$idx}.barang_id" => ['Bahan baku wajib dipilih.']]);
            }
            if ($qty <= 0) {
                throw ValidationException::withMessages(["inputs.{$idx}.qty" => ['Qty bahan baku harus lebih dari 0.']]);
            }
            if (in_array($bId, $inputBarangIds)) {
                throw ValidationException::withMessages(["inputs.{$idx}.barang_id" => ['Bahan baku tidak boleh duplikat dalam satu preparation.']]);
            }
            $inputBarangIds[] = $bId;

            // Catatan: Pengecekan kecukupan stok tidak memblokir simpan draft (status menunggu).
            // Tombol approve akan otomatis dinonaktifkan jika stok belum cukup.
        }

        // Validate unique output items & qty > 0
        $outputBarangIds = [];
        foreach ($data['outputs'] as $idx => $out) {
            $bId = $out['barang_id'] ?? null;
            $qty = (float)($out['qty'] ?? 0);

            if (!$bId) {
                throw ValidationException::withMessages(["outputs.{$idx}.barang_id" => ['Bahan hasil wajib dipilih.']]);
            }
            if ($qty <= 0) {
                throw ValidationException::withMessages(["outputs.{$idx}.qty" => ['Qty hasil harus lebih dari 0.']]);
            }
            if (in_array($bId, $outputBarangIds)) {
                throw ValidationException::withMessages(["outputs.{$idx}.barang_id" => ['Bahan hasil tidak boleh duplikat dalam satu preparation.']]);
            }
            $outputBarangIds[] = $bId;
        }
    }

    /**
     * Check real-time stock sufficiency for all inputs of a preparation document
     */
    public function checkStockAvailability(Preparation $prep): array
    {
        return $prep->getStockCheckDetails();
    }
}
