<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    protected $table = 'journal_items';
    protected $fillable = ['journal_id', 'journal_type', 'account_id', 'debit', 'kredit'];
    public $timestamps = false;

    public function coa(): BelongsTo
    {
        // Parameter: (ModelTujuan, foreign_key_di_sini, primary_key_di_tujuan)
        return $this->belongsTo(ChartOfAccount::class, 'account_id', 'id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id', 'id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id', 'id');
    }

    // Relasi balik ke Jurnal Pembelian
    public function jurnalPembelianHeader(): BelongsTo
    {
        return $this->belongsTo(JurnalPembelian::class, 'journal_id', 'id');
    }

    // Relasi balik ke Jurnal Penjualan B2B
    public function jurnalPenjualanB2bHeader(): BelongsTo
    {
        return $this->belongsTo(JurnalPenjualanB2b::class, 'journal_id', 'id');
    }

    // Relasi balik ke Jurnal Penjualan POS
    public function jurnalPenjualanPosHeader(): BelongsTo
    {
        return $this->belongsTo(JurnalPenjualanPos::class, 'journal_id', 'id');
    }

    // Relasi balik ke Jurnal Penyesuaian
    public function jurnalPenyesuaianHeader(): BelongsTo
    {
        return $this->belongsTo(JurnalPenyesuaian::class, 'journal_id', 'id');
    }
}
