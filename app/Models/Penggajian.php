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
        'satuan_gaji',
        'satuan_gaji_2',
        'pilihan_periode',
        'tarif_harian_total',
        'gaji_utama',
        'gaji_pokok',
        'tunjangan_transport',
        'tunjangan_makan',
        'jam_lembur',
        'lembur',
        'banyak_target',
        'bonus_target',
        'catatan_bonus_target',
        'banyak_tanggal_merah',
        'bonus_tanggal_merah',
        'catatan_bonus_tanggal_merah',
        'banyak_birthday_service',
        'bonus_birthday',
        'pengembalian_deposit',
        'bonus_dll',
        'potongan_inventaris',
        'potongan_terlambat',
        'potongan_kasbon',
        'potongan_deposit',
        'potongan_dll',
        'catatan_potongan_dll',
        'total_earnings',
        'total_deductions',
        'total_gaji_bersih',
        'status',
        'status_jurnal',
        'journal_id'
    ];

    /**
     * Cache kolom tabel database agar tidak melakukan schema querying berulang
     */
    protected static ?array $tableColumns = null;

    /**
     * Pastikan semua kolom yang dibutuhkan selalu tersedia secara fisik di database
     * tanpa memerlukan file migrasi baru.
     */
    public static function ensureSchemaColumns(): void
    {
        if (static::$tableColumns !== null) {
            return;
        }

        try {
            $existing = \Illuminate\Support\Facades\Schema::getColumnListing('penggajian');
            $existingFlip = array_flip($existing);

            $requiredDefinitions = [
                'hari_kerja'                  => fn($t) => $t->decimal('hari_kerja', 8, 2)->default(0),
                'satuan_gaji'                 => fn($t) => $t->string('satuan_gaji', 30)->default('Harian'),
                'satuan_gaji_2'               => fn($t) => $t->string('satuan_gaji_2', 30)->default('Harian'),
                'pilihan_periode'             => fn($t) => $t->integer('pilihan_periode')->default(1),
                'tarif_harian_total'          => fn($t) => $t->decimal('tarif_harian_total', 15, 2)->default(0),
                'gaji_utama'                  => fn($t) => $t->decimal('gaji_utama', 15, 2)->default(0),
                'jam_lembur'                  => fn($t) => $t->decimal('jam_lembur', 8, 2)->default(0),
                'banyak_target'               => fn($t) => $t->integer('banyak_target')->default(0),
                'catatan_bonus_target'        => fn($t) => $t->string('catatan_bonus_target', 255)->nullable(),
                'banyak_tanggal_merah'        => fn($t) => $t->integer('banyak_tanggal_merah')->default(0),
                'catatan_bonus_tanggal_merah' => fn($t) => $t->string('catatan_bonus_tanggal_merah', 255)->nullable(),
                'banyak_birthday_service'     => fn($t) => $t->integer('banyak_birthday_service')->default(0),
                'pengembalian_deposit'        => fn($t) => $t->decimal('pengembalian_deposit', 15, 2)->default(0),
                'potongan_kasbon'             => fn($t) => $t->decimal('potongan_kasbon', 15, 2)->default(0),
                'potongan_deposit'            => fn($t) => $t->decimal('potongan_deposit', 15, 2)->default(0),
                'potongan_dll'                => fn($t) => $t->decimal('potongan_dll', 15, 2)->default(0),
                'catatan_potongan_dll'        => fn($t) => $t->string('catatan_potongan_dll', 255)->nullable(),
                'total_earnings'              => fn($t) => $t->decimal('total_earnings', 15, 2)->default(0),
                'total_deductions'            => fn($t) => $t->decimal('total_deductions', 15, 2)->default(0),
            ];

            $toAdd = [];
            foreach ($requiredDefinitions as $col => $def) {
                if (!isset($existingFlip[$col])) {
                    $toAdd[] = $def;
                }
            }

            if (!empty($toAdd)) {
                \Illuminate\Support\Facades\Schema::table('penggajian', function (\Illuminate\Database\Schema\Blueprint $table) use ($toAdd) {
                    foreach ($toAdd as $addCol) {
                        $addCol($table);
                    }
                });
                $existing = \Illuminate\Support\Facades\Schema::getColumnListing('penggajian');
            }

            static::$tableColumns = $existing;
        } catch (\Throwable $e) {
            try {
                static::$tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing('penggajian');
            } catch (\Throwable $ex) {
                static::$tableColumns = [];
            }
        }
    }

    public static function getTableColumns(): array
    {
        if (static::$tableColumns === null) {
            static::ensureSchemaColumns();
        }
        return static::$tableColumns ?? [];
    }

    /**
     * Pastikan $fillable hanya memuat kolom yang benar-benar ada di tabel fisik database
     */
    public function getFillable(): array
    {
        $columns = static::getTableColumns();
        if (!empty($columns)) {
            return array_values(array_intersect($this->fillable, $columns));
        }
        return $this->fillable;
    }

    /**
     * Filter atribut sebelum proses insert / update database agar tidak error jika kolom tidak ada di database fisik
     */
    public function save(array $options = [])
    {
        $columns = static::getTableColumns();
        if (!empty($columns)) {
            $this->attributes = array_intersect_key($this->attributes, array_flip($columns));
        }
        return parent::save($options);
    }

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
