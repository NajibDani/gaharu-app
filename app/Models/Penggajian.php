<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

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
        'catatan_bonus_dll',
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
        'journal_id',
        'tipe_pembayaran'
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
                'bonus_dll'                   => fn($t) => $t->decimal('bonus_dll', 15, 2)->default(0),
                'catatan_bonus_dll'           => fn($t) => $t->string('catatan_bonus_dll', 255)->nullable(),
                'potongan_kasbon'             => fn($t) => $t->decimal('potongan_kasbon', 15, 2)->default(0),
                'potongan_deposit'            => fn($t) => $t->decimal('potongan_deposit', 15, 2)->default(0),
                'potongan_dll'                => fn($t) => $t->decimal('potongan_dll', 15, 2)->default(0),
                'catatan_potongan_dll'        => fn($t) => $t->string('catatan_potongan_dll', 255)->nullable(),
                'total_earnings'              => fn($t) => $t->decimal('total_earnings', 15, 2)->default(0),
                'total_deductions'            => fn($t) => $t->decimal('total_deductions', 15, 2)->default(0),
                'tipe_pembayaran'             => fn($t) => $t->string('tipe_pembayaran', 30)->nullable(),
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

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id', 'id');
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

    /**
     * Generate secure token for public slip viewing without login
     */
    public static function generateSlipToken($payrollId, $periode = null): string
    {
        $key = config('app.key') ?: 'gaharu_public_slip_token_key';
        return substr(hash_hmac('sha256', "public_slip_{$payrollId}_{$periode}", $key), 0, 16);
    }

    /**
     * Generate public URL to view/download slip without login
     */
    public function getPublicSlipUrl($periode = null): string
    {
        $token = self::generateSlipToken($this->id, $periode);
        $params = ['id' => $this->id, 'token' => $token];
        if ($periode !== null && $periode !== '') {
            $params['periode'] = $periode;
        }
        return route('penggajian.slip.public', $params);
    }

    /**
     * Helper Konversi Angka ke Kalimat Terbilang Rupiah
     */
    public static function terbilang($number): string
    {
        $number = abs(round((float)$number));
        if ($number == 0) return 'Nol';
        $huruf = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($number < 12) {
            $hasil = $huruf[$number];
        } elseif ($number < 20) {
            $hasil = self::terbilang($number - 10) . ' Belas';
        } elseif ($number < 100) {
            $hasil = self::terbilang((int)($number / 10)) . ' Puluh ' . self::terbilang($number % 10);
        } elseif ($number < 200) {
            $hasil = 'Seratus ' . self::terbilang($number - 100);
        } elseif ($number < 1000) {
            $hasil = self::terbilang((int)($number / 100)) . ' Ratus ' . self::terbilang($number % 100);
        } elseif ($number < 2000) {
            $hasil = 'Seribu ' . self::terbilang($number - 1000);
        } elseif ($number < 1000000) {
            $hasil = self::terbilang((int)($number / 1000)) . ' Ribu ' . self::terbilang($number % 1000);
        } elseif ($number < 1000000000) {
            $hasil = self::terbilang((int)($number / 1000000)) . ' Juta ' . self::terbilang($number % 1000000);
        } elseif ($number < 1000000000000) {
            $hasil = self::terbilang((int)($number / 1000000000)) . ' Miliar ' . self::terbilang(fmod($number, 1000000000));
        } else {
            $hasil = self::terbilang((int)($number / 1000000000000)) . ' Triliun ' . self::terbilang(fmod($number, 1000000000000));
        }

        return trim(preg_replace('/\s+/', ' ', $hasil));
    }

    /**
     * Tentukan rentang tanggal aktual (lintas bulan jika berlaku) untuk suatu periode slip gaji karyawan.
     * Mengakomodasi:
     * 1. Karyawan baru yang masa training-nya (P1) lintas bulan (misal: 21 Okt – 21 Nov)
     * 2. Karyawan yang belum pernah menerima gaji sebelumnya, sehingga seluruh data sejak tanggal masuk hingga cut-off ditarik
     * 3. Karyawan dengan 2 struktur periode (P1 dan P2)
     * 4. Karyawan reguler bulanan (1 s.d. akhir bulan)
     *
     * @param mixed $karyawan
     * @param string $periodeBulanTahun (Format 'Y-m', misal '2026-11')
     * @param int|string $pilihanPeriode (1 atau 2 atau 'all')
     * @param Penggajian|null $payroll
     * @return array ['start' => string, 'end' => string]
     */
    public static function getDateRangeForPayroll($karyawan, string $periodeBulanTahun, $pilihanPeriode = 1, ?self $payroll = null): array
    {
        if (is_numeric($karyawan)) {
            $karyawan = Karyawan::find($karyawan);
        }

        $carbonPeriode = Carbon::parse($periodeBulanTahun . '-01');
        $monthStart    = $carbonPeriode->copy()->startOfMonth()->format('Y-m-d');
        $monthEnd      = $carbonPeriode->copy()->endOfMonth()->format('Y-m-d');

        if (!$karyawan) {
            return ['start' => $monthStart, 'end' => $monthEnd];
        }

        $pNum = (int)$pilihanPeriode;

        $p2Total = floatval($karyawan->gaji_pokok_2 ?? 0) + floatval($karyawan->uang_makan_2 ?? 0) + floatval($karyawan->uang_transport_2 ?? 0);
        $hasP2 = ($karyawan->gaji_pokok_2 !== null && $p2Total > 0);

        // Cek apakah karyawan ini sudah pernah memiliki slip gaji yang dibayar/disetujui sebelum periode ini
        $hasPriorPaidPayroll = self::where('karyawan_id', $karyawan->id)
            ->where('periode_bulan_tahun', '<', $periodeBulanTahun)
            ->where(function ($q) {
                $q->where('status_jurnal', true)
                  ->orWhere('status', 'approved')
                  ->orWhere('hari_kerja', '>', 0);
            })
            ->exists();

        // Tanggal master dari karyawan atau slip payroll yang ada
        $p1M = $payroll?->tanggal_mulai
            ? Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d')
            : ($karyawan->tanggal_mulai ? Carbon::parse($karyawan->tanggal_mulai)->format('Y-m-d') : null);

        $p1S = $payroll?->tanggal_selesai
            ? Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d')
            : ($karyawan->tanggal_selesai ? Carbon::parse($karyawan->tanggal_selesai)->format('Y-m-d') : null);

        $p2M = ($hasP2 && $payroll && $pNum === 2 && $payroll->tanggal_mulai)
            ? Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d')
            : ($hasP2 && $karyawan->tanggal_mulai_2 ? Carbon::parse($karyawan->tanggal_mulai_2)->format('Y-m-d') : null);

        $p2S = ($hasP2 && $payroll && $pNum === 2 && $payroll->tanggal_selesai)
            ? Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d')
            : ($hasP2 && $karyawan->tanggal_selesai_2 ? Carbon::parse($karyawan->tanggal_selesai_2)->format('Y-m-d') : null);

        if ($pNum === 2 && $hasP2) {
            // PERIODE 2
            // Start P2: jika tanggal_mulai_2 jatuh di bulan ini, gunakan tanggal_mulai_2; jika sebelumnya, gunakan awal bulan
            if ($p2M && $p2M >= $monthStart && $p2M <= $monthEnd) {
                $startDate = $p2M;
            } else {
                $startDate = $monthStart;
            }

            // End P2: jika tanggal_selesai_2 jatuh di bulan ini, gunakan tanggal_selesai_2; jika melebihi, gunakan akhir bulan
            if ($p2S && $p2S >= $monthStart && $p2S <= $monthEnd) {
                $endDate = $p2S;
            } else {
                $endDate = $monthEnd;
            }

            return ['start' => $startDate, 'end' => $endDate];
        }

        // PERIODE 1
        // Tentukan End Date untuk P1:
        if ($hasP2 && $p2M && $p2M >= $monthStart && $p2M <= $monthEnd) {
            // Jika ada P2 yang mulai di bulan ini (misal 22 Nov), maka P1 berakhir sehari sebelumnya atau sesuai p1S (misal 21 Nov)
            $p1CutOff = $p1S ?: Carbon::parse($p2M)->subDay()->format('Y-m-d');
            $endDate = $p1CutOff;
        } elseif ($p1S && $p1S >= $monthStart && $p1S <= $monthEnd) {
            $endDate = $p1S;
        } else {
            $endDate = ($p1S && $p1S < $monthEnd) ? $p1S : $monthEnd;
        }

        // Tentukan Start Date untuk P1:
        // Kasus 1: Karyawan baru (belum pernah menerima gaji sebelumnya)
        //          Semua keterlambatan sejak awal masuk ($p1M) harus ditarik penuh hingga cut-off
        if (!$hasPriorPaidPayroll && $p1M && $p1M < $monthStart) {
            $startDate = $p1M;
        }
        // Kasus 2: P1 memiliki tanggal selesai spesifik di bulan ini dan tanggal mulai di bulan sebelumnya (lintas bulan training)
        elseif ($p1M && $p1S && $p1M < $monthStart && $p1S >= $monthStart && $p1S <= $monthEnd) {
            $startDate = $p1M;
        }
        // Kasus 3: Karyawan reguler yang masuk di pertengahan bulan ini
        elseif ($p1M && $p1M >= $monthStart && $p1M <= $monthEnd) {
            $startDate = $p1M;
        }
        // Kasus 4: Karyawan reguler yang sudah ada (kontrak lama)
        else {
            $startDate = $monthStart;
        }

        return ['start' => $startDate, 'end' => $endDate];
    }

    /**
     * Hitung akumulasi denda keterlambatan karyawan pada bulan dan periode pilihan tertentu.
     * Secara dinamis menarik data keterlambatan sesuai rentang tanggal periode aktual karyawan (lintas bulan).
     */
    public static function hitungPotonganTerlambat($karyawan, $periodeBulanTahun, $pilihanPeriode = 1): float
    {
        if (is_numeric($karyawan)) {
            $karyawan = Karyawan::find($karyawan);
        }
        if (!$karyawan) {
            return 0.0;
        }

        $p2Total = floatval($karyawan->gaji_pokok_2 ?? 0) + floatval($karyawan->uang_makan_2 ?? 0) + floatval($karyawan->uang_transport_2 ?? 0);
        $hasMultipleP = ($karyawan->gaji_pokok_2 !== null && $p2Total > 0);

        if ($pilihanPeriode === 'all') {
            if ($hasMultipleP) {
                return self::hitungPotonganTerlambat($karyawan, $periodeBulanTahun, 1) +
                       self::hitungPotonganTerlambat($karyawan, $periodeBulanTahun, 2);
            }
            $pilihanPeriode = 1;
        }

        $dateRange = self::getDateRangeForPayroll($karyawan, $periodeBulanTahun, $pilihanPeriode);
        $startDate = $dateRange['start'];
        $endDate   = $dateRange['end'];

        $lateQuery = Keterlambatan::where('karyawan_id', $karyawan->id)
            ->whereBetween('tanggal', [$startDate, $endDate]);

        $allLate = $lateQuery->get();

        if ($allLate->isEmpty()) {
            return 0.0;
        }

        // Self-heal jika ada potongan lama yang terpotong hanya sebesar gaji pokok
        foreach ($allLate as $t) {
            if ($t->jam_shift && $t->jam_datang) {
                $kalkulasi = Keterlambatan::hitungPotongan($t->jam_shift, $t->jam_datang);
                $gajiHarian = $karyawan->getGajiHarianForDate($t->tanggal);
                $potonganSeharusnya = min((float)$kalkulasi['potongan'], (float)$gajiHarian);
                if ($potonganSeharusnya > 0 && abs((float)$t->potongan - $potonganSeharusnya) > 0.01) {
                    $t->update(['potongan' => $potonganSeharusnya]);
                    $t->potongan = $potonganSeharusnya;
                }
            }
        }

        if ($hasMultipleP) {
            if ((int)$pilihanPeriode === 2) {
                return (float) $allLate->filter(function ($t) use ($karyawan) {
                    $pInfo = $karyawan->getPeriodeGajiLabelForDate($t->tanggal);
                    return ($pInfo['key'] ?? '') === 'P2';
                })->sum('potongan');
            } else {
                return (float) $allLate->filter(function ($t) use ($karyawan) {
                    $pInfo = $karyawan->getPeriodeGajiLabelForDate($t->tanggal);
                    return ($pInfo['key'] ?? '') !== 'P2';
                })->sum('potongan');
            }
        }

        return (float) $allLate->sum('potongan');
    }
}
