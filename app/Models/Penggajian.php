<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penggajian extends Model
{
    protected $table = 'penggajian';

    public $timestamps = false;

    protected $fillable = [
        'karyawan_id',
        'outlet',
        'periode_bulan_tahun',
        'tanggal_mulai',
        'tanggal_selesai',
        'hari_kerja',
        'tarif_harian_total',
        'gaji_utama',
        'gaji_pokok',
        'tunjangan_transport',
        'tunjangan_makan',
        'jam_lembur',
        'lembur',
        'banyak_target',
        'bonus_target',
        'banyak_tanggal_merah',
        'bonus_tanggal_merah',
        'banyak_birthday_service',
        'bonus_birthday',
        'bonus_dll',
        'potongan_inventaris',
        'potongan_terlambat',
        'potongan_kasbon',
        'potongan_dll',
        'total_earnings',
        'total_deductions',
        'total_gaji_bersih',
        'status',
        'status_jurnal',
        'journal_id'
    ];

    public function karyawan(): BelongsTo
    {
        // Pastikan model Karyawan sudah di-import di atas atau tulis lengkap path-nya
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id');
    }

    /**
     * Konversi format periode (contoh: 2026-08) menjadi format nama bulan dan tahun Bahasa Indonesia (contoh: Agustus 2026).
     */
    public static function formatPeriode($periode): string
    {
        if (empty($periode)) {
            return '-';
        }

        $bulanIndo = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        // Format YYYY-MM
        if (preg_match('/^(\d{4})-(\d{1,2})$/', trim($periode), $matches)) {
            $tahun = $matches[1];
            $bulan = (int)$matches[2];
            return ($bulanIndo[$bulan] ?? $bulan) . ' ' . $tahun;
        }

        // Format MM-YYYY
        if (preg_match('/^(\d{1,2})-(\d{4})$/', trim($periode), $matches)) {
            $bulan = (int)$matches[1];
            $tahun = $matches[2];
            return ($bulanIndo[$bulan] ?? $bulan) . ' ' . $tahun;
        }

        return (string)$periode;
    }

    /**
     * Accessor untuk nama periode yang ramah dibaca
     */
    public function getNamaPeriodeAttribute(): string
    {
        return self::formatPeriode($this->periode_bulan_tahun);
    }
}
