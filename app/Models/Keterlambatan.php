<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Keterlambatan extends Model
{
    protected $table = 'keterlambatan';

    protected $fillable = [
        'karyawan_id',
        'tanggal',
        'shift',
        'jam_shift',
        'jam_datang',
        'durasi_menit',
        'potongan',
        'keterangan',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'durasi_menit' => 'integer',
        'potongan'     => 'float',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id');
    }

    /**
     * Hitung durasi Keterlambatan (dalam Menit) dan Potongan Denda berdasarkan aturan:
     * 1 - 10 menit  => Rp 10.000
     * 11 - 20 menit => Rp 20.000
     * 21 - 30 menit => Rp 30.000
     * Kelipatan Rp 10.000 per 10 menit
     */
    public static function hitungPotongan(string $jamShift, string $jamDatang): array
    {
        try {
            $shiftTime = Carbon::createFromFormat('H:i:s', strlen($jamShift) == 5 ? $jamShift . ':00' : $jamShift);
            $datangTime = Carbon::createFromFormat('H:i:s', strlen($jamDatang) == 5 ? $jamDatang . ':00' : $jamDatang);

            if ($datangTime->gt($shiftTime)) {
                $durasiMenit = (int) ceil($shiftTime->diffInSeconds($datangTime) / 60);
                $potongan = (int) ceil($durasiMenit / 10) * 10000;
                return [
                    'durasi_menit' => $durasiMenit,
                    'potongan'     => $potongan,
                ];
            }
        } catch (\Exception $e) {
            // Abaikan penanganan jika parse jam gagal
        }

        return [
            'durasi_menit' => 0,
            'potongan'     => 0,
        ];
    }
}
