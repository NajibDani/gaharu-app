<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preparation extends Model
{
    use HasFactory;

    protected $table = 'preparations';

    protected $fillable = [
        'kode',
        'tanggal',
        'gudang_id',
        'divisi_id',
        'status',
        'total_biaya',
        'keterangan',
        'alasan_tolak',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_biaya' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    public function inputs()
    {
        return $this->hasMany(PreparationInput::class, 'preparation_id');
    }

    public function outputs()
    {
        return $this->hasMany(PreparationOutput::class, 'preparation_id');
    }

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isMenunggu(): bool
    {
        return $this->status === 'menunggu';
    }

    public function isDisetujui(): bool
    {
        return $this->status === 'disetujui';
    }

    public function isDitolak(): bool
    {
        return $this->status === 'ditolak';
    }

    public function canEdit(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if ($user && $user->isSuperAdmin()) {
            return true;
        }
        return $this->isMenunggu();
    }

    public function canDelete(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if ($user && $user->isSuperAdmin()) {
            return true;
        }
        return $this->isMenunggu() || $this->isDitolak();
    }

    /**
     * Check real-time and historical stock sufficiency for all inputs in the designated gudang & divisi.
     * Memperhitungkan stok buku pembantu persediaan pada tanggal dokumen preparation dan sisa batch aktif.
     */
    public function getStockCheckDetails(): array
    {
        $gudangId = $this->gudang_id;
        $divisiId = $this->divisi_id;
        $targetDate = $this->tanggal ? date('Y-m-d', strtotime($this->tanggal)) : date('Y-m-d');
        $isSufficient = true;
        $items = [];

        $this->loadMissing(['inputs.barang']);

        foreach ($this->inputs as $input) {
            $barang = $input->barang;
            $qtyDibutuhkan = (float)$input->qty_base;

            // 1. Stok realtime saat ini di tabel stok_gudang
            $stokQuery = StokGudang::where('gudang_id', $gudangId)
                ->where('barang_id', $input->barang_id);

            if ($divisiId) {
                $stokQuery->where('divisi_id', $divisiId);
            } else {
                $stokQuery->whereNull('divisi_id');
            }

            $stokRealtime = (float)($stokQuery->value('jumlah') ?? 0);

            // 2. Stok buku pembantu persediaan pada tanggal dokumen preparation
            $stokBukuDivisi = $divisiId ? \App\Models\StokGudang::getStokBukuPembantu($input->barang_id, $gudangId, $divisiId, $targetDate) : 0;
            $stokBukuGudang = \App\Models\StokGudang::getStokBukuPembantu($input->barang_id, $gudangId, null, $targetDate);

            // 3. Sisa stok batch FIFO aktif di gudang / divisi
            $stokBatch = (float) \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $input->barang_id)
                ->when($divisiId, fn($q) => $q->where('divisi_id', $divisiId))
                ->sum('qty_sisa');

            // Gunakan nilai stok terbesar antara stok buku pembantu tanggal dokumen, stok realtime, atau batch aktif
            $availStock = max($stokRealtime, $stokBukuDivisi, $stokBukuGudang, $stokBatch);
            $satuan = $barang ? ($barang->satuan ?: 'unit') : ($input->satuan ?: 'unit');
            $cukup = ($availStock >= $qtyDibutuhkan);

            if (!$cukup) {
                $isSufficient = false;
            }

            $items[] = [
                'barang_id'   => $input->barang_id,
                'nama'        => $barang->nama ?? '-',
                'dibutuhkan'  => $qtyDibutuhkan,
                'tersedia'    => $availStock,
                'kekurangan'  => max(0, round($qtyDibutuhkan - $availStock, 4)),
                'satuan'      => $satuan,
                'is_cukup'    => $cukup,
            ];
        }

        $insufficientItems = array_values(array_filter($items, fn($i) => !$i['is_cukup']));

        return [
            'is_sufficient'      => $isSufficient,
            'items'              => $items,
            'insufficient_items' => $insufficientItems,
            'insufficient_count' => count($insufficientItems),
        ];
    }

    public function hasSufficientStock(): bool
    {
        return $this->getStockCheckDetails()['is_sufficient'];
    }
}
