<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Karyawan extends Model
{
    protected $table = 'karyawan';
    public $timestamps = false;
    protected $fillable = [
        'nama_karyawan',
        'nik',
        'tempat_lahir',
        'tanggal_lahir',
        'ttl',
        'whatsapp',
        'email',
        'nomor_darurat',
        'jabatan',
        'jenis_tenaga_kerja',
        'satuan_gaji',
        'satuan_gaji_2',
        'departemen',
        'outlet',
        'no_rekening',
        'tanggal_mulai',
        'tanggal_selesai',
        'gaji_pokok',
        'uang_makan',
        'uang_transport',
        'tanggal_mulai_2',
        'tanggal_selesai_2',
        'gaji_pokok_2',
        'uang_makan_2',
        'uang_transport_2',
        'urutan',
    ];

    public const SATUAN_GAJI_LIST = [
        'Harian',
        'Bulanan',
        'Per Jam',
    ];

    public const DEPARTEMEN_LIST = [
        'Frontline',
        'Kitchen',
        'Cold Kitchen',
        'Central Kitchen',
        'Gudang',
        'Manajemen',
        'Satpam',
        'Cleaning Service',
    ];

    public const JABATAN_LIST = [
        'BARISTA',
        'CAPTAIN',
        'KASIR',
        'FLOOR',
        'PROBATION',
        'PART TIME',
        'CLEANING SERVICE',
        'HEAD KITCHEN',
        'COOK',
        'COOK HELPER',
        'DISHWASHER',
        'ADMIN GUDANG',
        'STAFF GUDANG',
        'SECURITY',
        'HRD',
        'SUPERVISOR',
        'MANAGER',
        'CEO',
    ];

    public static function getJabatanList(): array
    {
        try {
            MasterJabatan::ensureTableExists();
            $list = MasterJabatan::orderBy('urutan', 'asc')->orderBy('id', 'asc')->pluck('nama')->toArray();
            return !empty($list) ? $list : self::JABATAN_LIST;
        } catch (\Throwable $e) {
            return self::JABATAN_LIST;
        }
    }


    protected $casts = [
        'tanggal_lahir'      => 'date:Y-m-d',
        'gaji_pokok'         => 'float',
        'uang_makan'         => 'float',
        'uang_transport'     => 'float',
        'tanggal_mulai'      => 'date:Y-m-d',
        'tanggal_selesai'    => 'date:Y-m-d',
        'gaji_pokok_2'       => 'float',
        'uang_makan_2'       => 'float',
        'uang_transport_2'   => 'float',
        'tanggal_mulai_2'    => 'date:Y-m-d',
        'tanggal_selesai_2'  => 'date:Y-m-d',
    ];

    /**
     * Pastikan format tanggal saat serialisasi JSON tidak bergeser karena konversi zona waktu UTC
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * Format Tempat, Tanggal Lahir (TTL)
     */
    public function getTtlFormattedAttribute(): string
    {
        $parts = [];
        if (!empty($this->tempat_lahir)) {
            $parts[] = $this->tempat_lahir;
        }
        if (!empty($this->tanggal_lahir)) {
            $parts[] = $this->tanggal_lahir->translatedFormat('d F Y');
        }
        if (!empty($parts)) {
            return implode(', ', $parts);
        }
        return $this->ttl ?: '-';
    }

    /**
     * Tarif Harian Total = Gaji Pokok Harian + Uang Makan + Uang Transport
     */
    public function getTarifHarianTotalAttribute(): float
    {
        return (float) ($this->gaji_pokok + $this->uang_makan + $this->uang_transport);
    }

    /**
     * Tarif Harian Total Periode 2
     */
    public function getTarifHarianTotal2Attribute(): float
    {
        return (float) (($this->gaji_pokok_2 ?? 0) + ($this->uang_makan_2 ?? 0) + ($this->uang_transport_2 ?? 0));
    }

    /**
     * Get the daily base salary (gaji pokok harian) for a specific date
     */
    public function getGajiHarianForDate($tanggal): float
    {
        $pInfo = $this->getPeriodeGajiLabelForDate($tanggal);
        if ($pInfo['key'] === 'P2') {
            return (float) ($this->gaji_pokok_2 ?? 0);
        }
        return (float) ($this->gaji_pokok ?? 0);
    }

    /**
     * Mendapatkan label dan kunci periode gaji berdasarkan tanggal presensi/keterlambatan
     */
    public function getPeriodeGajiLabelForDate($tanggal): array
    {
        $tgl = \Carbon\Carbon::parse($tanggal)->format('Y-m-d');

        $p1M = $this->tanggal_mulai ? $this->tanggal_mulai->format('Y-m-d') : null;
        $p1S = $this->tanggal_selesai ? $this->tanggal_selesai->format('Y-m-d') : null;
        $p2M = $this->tanggal_mulai_2 ? $this->tanggal_mulai_2->format('Y-m-d') : null;
        $p2S = $this->tanggal_selesai_2 ? $this->tanggal_selesai_2->format('Y-m-d') : null;

        $hasP2 = ($this->gaji_pokok_2 !== null && $this->tarif_harian_total_2 > 0);
        if ($hasP2 && $p2M && $tgl >= $p2M && (!$p2S || $tgl <= $p2S)) {
            $datesStr = ($p2M ? \Carbon\Carbon::parse($p2M)->format('d/m') : '') . ($p2S ? '–' . \Carbon\Carbon::parse($p2S)->format('d/m') : '');
            return [
                'key'         => 'P2',
                'label'       => 'Periode B' . ($datesStr ? " ($datesStr)" : ''),
                'badge_class' => 'bg-warning-subtle text-warning-800 border border-warning-subtle',
                'mulai'       => $p2M,
                'selesai'     => $p2S,
            ];
        }

        if ($p1M && $tgl >= $p1M && (!$p1S || $tgl <= $p1S)) {
            $datesStr = ($p1M ? \Carbon\Carbon::parse($p1M)->format('d/m') : '') . ($p1S ? '–' . \Carbon\Carbon::parse($p1S)->format('d/m') : '');
            return [
                'key'         => 'P1',
                'label'       => 'Periode A' . ($datesStr ? " ($datesStr)" : ''),
                'badge_class' => 'bg-info-subtle text-info-800 border border-info-subtle',
                'mulai'       => $p1M,
                'selesai'     => $p1S,
            ];
        }

        if ($p1M || $p1S) {
            $datesStr = ($p1M ? \Carbon\Carbon::parse($p1M)->format('d/m') : '') . ($p1S ? '–' . \Carbon\Carbon::parse($p1S)->format('d/m') : '');
            return [
                'key'         => 'P1',
                'label'       => 'Periode 1' . ($datesStr ? " ($datesStr)" : ''),
                'badge_class' => 'bg-light text-dark border',
                'mulai'       => $p1M,
                'selesai'     => $p1S,
            ];
        }

        return [
            'key'         => 'REG',
            'label'       => 'Reguler',
            'badge_class' => 'bg-light text-secondary border',
            'mulai'       => null,
            'selesai'     => null,
        ];
    }


    public function penggajian(): HasMany
    {
        return $this->hasMany(Penggajian::class, 'karyawan_id', 'id');
    }

    public function keterlambatan(): HasMany
    {
        return $this->hasMany(Keterlambatan::class, 'karyawan_id', 'id');
    }

    /**
     * Periksa apakah masa kontrak karyawan mencakup periode penggajian tertentu (YYYY-MM).
     * Asumsi: periode gaji sama dengan periode masa kontrak kerja karyawan.
     * Karyawan yang masa kontraknya sudah melewati periode gaji tidak ditampilkan lagi,
     * dan karyawan hanya muncul jika periode gaji berada dalam rentang tanggal mulai dan akhir kontraknya.
     */
    public function isContractActiveInPeriod(string $periode): bool
    {
        try {
            $periodeStart = \Carbon\Carbon::parse($periode . '-01')->startOfMonth()->format('Y-m-d');
            $periodeEnd   = \Carbon\Carbon::parse($periode . '-01')->endOfMonth()->format('Y-m-d');
        } catch (\Throwable $e) {
            return true;
        }

        // Cek Periode 1
        $p1M = $this->tanggal_mulai ? \Carbon\Carbon::parse($this->tanggal_mulai)->format('Y-m-d') : null;
        $p1S = $this->tanggal_selesai ? \Carbon\Carbon::parse($this->tanggal_selesai)->format('Y-m-d') : null;

        $p1Active = true;
        if ($p1M && $p1M > $periodeEnd) {
            $p1Active = false; // Kontrak baru mulai setelah periode ini
        }
        if ($p1S && $p1S < $periodeStart) {
            $p1Active = false; // Kontrak sudah berakhir sebelum periode ini dimulai
        }

        // Cek Periode 2 jika ada master P2
        $p2Total = floatval($this->gaji_pokok_2 ?? 0) + floatval($this->uang_makan_2 ?? 0) + floatval($this->uang_transport_2 ?? 0);
        $hasP2 = ($this->gaji_pokok_2 !== null && $p2Total > 0);

        if ($hasP2) {
            $p2M = $this->tanggal_mulai_2 ? \Carbon\Carbon::parse($this->tanggal_mulai_2)->format('Y-m-d') : null;
            $p2S = $this->tanggal_selesai_2 ? \Carbon\Carbon::parse($this->tanggal_selesai_2)->format('Y-m-d') : null;

            $p2Active = true;
            if ($p2M && $p2M > $periodeEnd) {
                $p2Active = false;
            }
            if ($p2S && $p2S < $periodeStart) {
                $p2Active = false;
            }

            return $p1Active || $p2Active;
        }

        return $p1Active;
    }
}
