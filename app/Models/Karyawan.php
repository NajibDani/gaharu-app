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
        'jabatan',
        'jenis_tenaga_kerja',
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
    ];

    protected $casts = [
        'gaji_pokok'         => 'float',
        'uang_makan'         => 'float',
        'uang_transport'     => 'float',
        'tanggal_mulai'      => 'date',
        'tanggal_selesai'    => 'date',
        'gaji_pokok_2'       => 'float',
        'uang_makan_2'       => 'float',
        'uang_transport_2'   => 'float',
        'tanggal_mulai_2'    => 'date',
        'tanggal_selesai_2'  => 'date',
    ];

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
     * Mendapatkan label dan kunci periode gaji berdasarkan tanggal presensi/keterlambatan
     */
    public function getPeriodeGajiLabelForDate($tanggal): array
    {
        $tgl = \Carbon\Carbon::parse($tanggal)->format('Y-m-d');

        $p1M = $this->tanggal_mulai ? $this->tanggal_mulai->format('Y-m-d') : null;
        $p1S = $this->tanggal_selesai ? $this->tanggal_selesai->format('Y-m-d') : null;
        $p2M = $this->tanggal_mulai_2 ? $this->tanggal_mulai_2->format('Y-m-d') : null;
        $p2S = $this->tanggal_selesai_2 ? $this->tanggal_selesai_2->format('Y-m-d') : null;

        if ($p2M && $tgl >= $p2M && (!$p2S || $tgl <= $p2S)) {
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
}
