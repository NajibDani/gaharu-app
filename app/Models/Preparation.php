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

    public function canEdit(): bool
    {
        return $this->isMenunggu();
    }

    public function canDelete(): bool
    {
        return $this->isMenunggu() || $this->isDitolak();
    }

    /**
     * Check real-time stock sufficiency for all inputs in the designated gudang & divisi
     */
    public function getStockCheckDetails(): array
    {
        $gudangId = $this->gudang_id;
        $divisiId = $this->divisi_id;
        $isSufficient = true;
        $items = [];

        $this->loadMissing(['inputs.barang']);

        foreach ($this->inputs as $input) {
            $barang = $input->barang;
            $qtyDibutuhkan = (float)$input->qty_base;

            $stokQuery = StokGudang::where('gudang_id', $gudangId)
                ->where('barang_id', $input->barang_id);

            if ($divisiId) {
                $stokQuery->where('divisi_id', $divisiId);
            } else {
                $stokQuery->whereNull('divisi_id');
            }

            $availStock = (float)($stokQuery->value('jumlah') ?? 0);
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
