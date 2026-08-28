<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Gaji - {{ $payroll->karyawan->nama_karyawan ?? 'Karyawan' }} - {{ $payroll->periode_bulan_tahun }}</title>
    <style>
        @page {
            margin: 10mm;
            size: a4 portrait;
        }
        * {
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #111;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        @php
            $currentOutlet = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';
            $isKejingga = (strtolower($currentOutlet) === 'kejingga');
            $carbonPeriode = \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-01');
            $daysInMonth = $carbonPeriode->daysInMonth;
            $namaBulanTahunIndo = \App\Models\Penggajian::formatPeriode($payroll->periode_bulan_tahun);

            // Dynamic color schemes
            $bgCard = $isKejingga ? '#134e4a' : '#c87a4b';
            $takeHomeBg = $isKejingga ? '#ea580c' : '#b8622f';
        @endphp

        .slip-card {
            background-color: {{ $bgCard }};
            padding: 12px 14px;
            border-radius: 6px;
            color: #ffffff;
            width: 100%;
        }

        /* HEADER */
        .slip-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .slip-brand-cell {
            vertical-align: middle;
        }

        .brand-logo-text {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #ffffff;
            margin: 0;
            line-height: 1.1;
        }

        .brand-logo-kejingga-ke {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            vertical-align: super;
            color: #fdba74;
            margin-right: 1px;
        }

        .brand-logo-kejingga-jingga {
            color: #fdba74;
        }

        .brand-logo-gaharu-icon {
            display: inline-block;
            border: 1.5px solid #ffffff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            text-align: center;
            line-height: 18px;
            font-size: 10px;
            margin-right: 5px;
        }

        .slip-title-meta {
            margin-top: 3px;
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
            line-height: 1.25;
        }

        .slip-employee-box {
            border: 1.5px solid rgba(255, 255, 255, 0.95);
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.12);
        }

        .emp-table {
            width: 100%;
            border-collapse: collapse;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
        }

        .emp-table td {
            padding: 3px 6px;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        /* BODY GRID TABLES */
        .body-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border: 1.5px solid #222222;
        }

        .body-table td {
            vertical-align: top;
            padding: 0;
            width: 50%;
        }

        .table-slip {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            color: #111;
        }

        .table-slip th, .table-slip td {
            border: 1px solid #333333;
            padding: 2.5px 5px;
        }

        .bg-header-pendapatan {
            background-color: #2e6945;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            padding: 4px !important;
            letter-spacing: 0.5px;
        }

        .bg-header-pengurangan {
            background-color: #eab308;
            color: #111827;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            padding: 4px !important;
            letter-spacing: 0.5px;
        }

        .bg-section-title {
            background-color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            color: #000;
        }

        .bg-subtotal-row {
            background-color: #fef3c7;
            font-weight: bold;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* BOTTOM TOTALS */
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #222222;
            border-top: none;
            background: #ffffff;
        }

        .total-pendapatan-box {
            background-color: #2e6945;
            color: #ffffff;
            padding: 5px 8px;
            font-weight: bold;
            font-size: 10px;
            width: 50%;
            border-right: 1px solid #333;
        }

        .total-pengurangan-box {
            background-color: #eab308;
            color: #111827;
            padding: 5px 8px;
            font-weight: bold;
            font-size: 10px;
            width: 50%;
        }

        .take-home-pay-bar {
            background-color: {{ $takeHomeBg }};
            color: #ffffff;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 11.5px;
            border: 1.5px solid #222222;
            border-top: none;
            width: 100%;
            box-sizing: border-box;
        }

        .signature-table {
            width: 100%;
            margin-top: 10px;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="slip-card">
        {{-- HEADER TABLE --}}
        <table class="slip-header-table">
            <tr>
                <td style="width: 45%; vertical-align: middle;" class="slip-brand-cell">
                    <div class="slip-title-meta" style="padding-left: 10px;">
                        <div style="font-size: 18px; font-weight: 800; letter-spacing: 1px;">SLIP GAJI</div>
                        <div style="font-size: 11px; margin-top: 4px;">
                            @if($payroll->tanggal_mulai && $payroll->tanggal_selesai)
                                PERIODE {{ \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d') }}-{{ \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d') }} {{ strtoupper($namaBulanTahunIndo) }}
                            @else
                                PERIODE 1-{{ $daysInMonth }} {{ strtoupper($namaBulanTahunIndo) }}
                            @endif
                        </div>
                    </div>
                </td>
                <td style="width: 55%;" class="slip-brand-cell">
                    <div class="slip-employee-box">
                        <table class="emp-table">
                            <tr>
                                <td style="width: 85px;">NAMA</td>
                                <td style="width: 8px; text-align: center;">:</td>
                                <td>{{ strtoupper($payroll->karyawan->nama_karyawan ?? '-') }}</td>
                            </tr>
                            <tr>
                                <td>OUTLET</td>
                                <td style="text-align: center;">:</td>
                                <td>{{ strtoupper($payroll->karyawan->outlet ?? '-') }}</td>
                            </tr>
                            <tr>
                                <td>DIVISI</td>
                                <td style="text-align: center;">:</td>
                                <td>{{ strtoupper($payroll->karyawan->departemen ?? '-') }}</td>
                            </tr>
                            <tr>
                                <td>NO. REKENING</td>
                                <td style="text-align: center;">:</td>
                                <td>{{ strtoupper($payroll->karyawan->no_rekening ?? '-') }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        {{-- BODY TABLES GRID --}}
        <table class="body-table">
            <tr>
                {{-- KOLOM PENDAPATAN --}}
                <td style="border-right: 1px solid #333333;">
                    <table class="table-slip">
                        <thead>
                            <tr>
                                <th colspan="4" class="bg-header-pendapatan">PENDAPATAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="bg-section-title">
                                    GAJI
                                    @if($payroll->tanggal_mulai && $payroll->tanggal_selesai)
                                        <span style="float: right; font-size: 8.5px; font-weight: normal; color: #333333;">
                                            ({{ \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m/Y') }})
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 12px;"></td>
                                <td>Gaji Pokok</td>
                                <td class="text-center" style="width: 25px;">Rp</td>
                                <td class="text-end" style="width: 70px;">{{ number_format($payroll->gaji_pokok ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Tunjangan Makan</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ number_format($payroll->tunjangan_makan ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Tunjangan/bonus lain-lain</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ number_format(($payroll->tunjangan_transport ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    Hari Kerja
                                    @if($payroll->tanggal_mulai && $payroll->tanggal_selesai)
                                        <span style="font-size: 8px; color: #555555;">({{ \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m') }} - {{ \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m') }})</span>
                                    @endif
                                </td>
                                <td colspan="2" class="text-center">{{ $payroll->hari_kerja }} hari</td>
                            </tr>
                            @php
                                $gajiUtamaVal = $payroll->gaji_utama > 0 ? $payroll->gaji_utama : ($payroll->hari_kerja * ($payroll->tarif_harian_total ?? ($payroll->gaji_pokok + $payroll->tunjangan_makan + $payroll->tunjangan_transport)));
                            @endphp
                            <tr class="bg-subtotal-row">
                                <td colspan="2">TOTAL GAJI</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ number_format($gajiUtamaVal, 0, ',', '.') }}</td>
                            </tr>

                            <tr>
                                <td colspan="4" class="bg-section-title">LEMBUR</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Upah per jam</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ number_format(10000, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Banyak jam lembur</td>
                                <td colspan="2" class="text-center">{{ $payroll->jam_lembur ?? 0 }} jam</td>
                            </tr>
                            <tr class="bg-subtotal-row">
                                <td colspan="2">TOTAL UPAH LEMBUR</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->lembur ?? 0) > 0 ? number_format($payroll->lembur, 0, ',', '.') : '-' }}</td>
                            </tr>

                            @if(!$isKejingga || ($payroll->bonus_target ?? 0) > 0)
                            <tr>
                                <td colspan="4" class="bg-section-title">BONUS TARGET PENJUALAN</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Bonus per target</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->banyak_target ?? 0) > 0 ? number_format(($payroll->bonus_target / $payroll->banyak_target), 0, ',', '.') : '-' }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Banyak target</td>
                                <td colspan="2" class="text-center">{{ ($payroll->banyak_target ?? 0) > 0 ? $payroll->banyak_target . ' target' : '-' }}</td>
                            </tr>
                            <tr class="bg-subtotal-row">
                                <td colspan="2">TOTAL BONUS TARGET PENJUALAN</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->bonus_target ?? 0) > 0 ? number_format($payroll->bonus_target, 0, ',', '.') : '-' }}</td>
                            </tr>
                            @endif

                            <tr>
                                <td colspan="4" class="bg-section-title">BONUS TANGGAL MERAH</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Bonus per tanggal</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->banyak_tanggal_merah ?? 0) > 0 ? number_format(($payroll->bonus_tanggal_merah / $payroll->banyak_tanggal_merah), 0, ',', '.') : '-' }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Banyak tanggal merah</td>
                                <td colspan="2" class="text-center">{{ ($payroll->banyak_tanggal_merah ?? 0) > 0 ? $payroll->banyak_tanggal_merah . ' hari' : '-' }}</td>
                            </tr>
                            <tr class="bg-subtotal-row">
                                <td colspan="2">TOTAL BONUS TANGGAL MERAH</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->bonus_tanggal_merah ?? 0) > 0 ? number_format($payroll->bonus_tanggal_merah, 0, ',', '.') : '-' }}</td>
                            </tr>

                            <tr>
                                <td colspan="4" class="bg-section-title">BIRTHDAY SERVICE</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Bonus per service</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->banyak_birthday_service ?? 0) > 0 ? number_format(5000, 0, ',', '.') : '-' }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Banyak service</td>
                                <td colspan="2" class="text-center">{{ ($payroll->banyak_birthday_service ?? 0) > 0 ? $payroll->banyak_birthday_service : '-' }}</td>
                            </tr>
                            <tr class="bg-subtotal-row">
                                <td colspan="2">TOTAL BONUS SERVICE</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->bonus_birthday ?? 0) > 0 ? number_format($payroll->bonus_birthday, 0, ',', '.') : '-' }}</td>
                            </tr>

                            <tr class="bg-subtotal-row">
                                <td colspan="2"><b>BONUS / UPAH LAIN-LAIN</b></td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->bonus_dll ?? 0) > 0 ? number_format($payroll->bonus_dll, 0, ',', '.') : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>

                {{-- KOLOM PENGURANGAN --}}
                <td>
                    <table class="table-slip">
                        <thead>
                            <tr>
                                <th colspan="5" class="bg-header-pengurangan">PENGURANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="bg-section-title">KETERLAMBATAN</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: bold; font-size: 8px;">
                                <td class="text-center" style="width: 28%;">Tanggal</td>
                                <td class="text-center" style="width: 25%;">Shift</td>
                                <td class="text-center" style="width: 22%;">Jam datang</td>
                                <td class="text-center" colspan="2" style="width: 25%;">Potongan</td>
                            </tr>

                            @php
                                $countTerlambat = count($listKeterlambatan);
                            @endphp

                            @forelse($listKeterlambatan as $t)
                            <tr>
                                <td class="text-center">{{ \Carbon\Carbon::parse($t->tanggal)->translatedFormat('d F Y') }}</td>
                                <td class="text-center">{{ $t->shift ?? '-' }}</td>
                                <td class="text-center">{{ substr($t->jam_datang, 0, 8) }}</td>
                                <td class="text-center" style="width: 15px;">Rp</td>
                                <td class="text-end">{{ number_format($t->potongan, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center" style="color: #666; font-style: italic;">Tidak ada keterlambatan</td>
                            </tr>
                            @endforelse

                            @for($i = $countTerlambat; $i < ($countTerlambat == 0 ? 4 : 2); $i++)
                            <tr>
                                <td style="height: 14px;">&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                            @endfor

                            <tr class="bg-subtotal-row">
                                <td colspan="3">TOTAL POTONGAN KETERLAMBATAN</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_terlambat ?? 0) > 0 ? number_format($payroll->potongan_terlambat, 0, ',', '.') : '-' }}</td>
                            </tr>

                            <tr>
                                <td colspan="5" class="bg-section-title">KERUSAKAN INVENTARIS</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: bold; font-size: 8px;">
                                <td colspan="3" class="text-center">Banyak Pecah</td>
                                <td colspan="2" class="text-center">Potongan per satuan</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-center">-</td>
                                <td class="text-center" style="width: 15px;">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_inventaris ?? 0) > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '0' }}</td>
                            </tr>
                            <tr class="bg-subtotal-row">
                                <td colspan="3">TOTAL POTONGAN KERUSAKAN INVENTARIS</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_inventaris ?? 0) > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '0' }}</td>
                            </tr>

                            <tr>
                                <td colspan="5" class="bg-section-title">LAIN-LAIN</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: bold; font-size: 8px;">
                                <td colspan="3" class="text-center">Keterangan</td>
                                <td colspan="2" class="text-center">Banyak Potongan</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-center">{{ ($payroll->potongan_dll ?? 0) > 0 ? 'Potongan Lainnya' : '-' }}</td>
                                <td class="text-center" style="width: 15px;">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_dll ?? 0) > 0 ? number_format($payroll->potongan_dll, 0, ',', '.') : '-' }}</td>
                            </tr>

                            <tr class="bg-subtotal-row">
                                <td colspan="3"><b>KASBON</b></td>
                                <td class="text-center">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_kasbon ?? 0) > 0 ? number_format($payroll->potongan_kasbon, 0, ',', '.') : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        {{-- BOTTOM TOTALS --}}
        @php
            $calcEarnings = $payroll->total_earnings > 0 ? $payroll->total_earnings : (
                $gajiUtamaVal + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
                ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->bonus_dll ?? 0)
            );

            $calcDeductions = $payroll->total_deductions > 0 ? $payroll->total_deductions : (
                ($payroll->potongan_terlambat ?? 0) + ($payroll->potongan_inventaris ?? 0) +
                ($payroll->potongan_kasbon ?? 0) + ($payroll->potongan_dll ?? 0)
            );
        @endphp

        <table class="bottom-table">
            <tr>
                <td class="total-pendapatan-box">
                    <table style="width:100%;">
                        <tr>
                            <td>TOTAL PENDAPATAN</td>
                            <td class="text-center" style="width: 25px;">Rp</td>
                            <td class="text-end" style="width: 70px;">{{ number_format($calcEarnings, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
                <td class="total-pengurangan-box">
                    <table style="width:100%;">
                        <tr>
                            <td>TOTAL POTONGAN</td>
                            <td class="text-center" style="width: 25px;">Rp</td>
                            <td class="text-end" style="width: 70px;">{{ number_format($calcDeductions, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="take-home-pay-bar">
            <table style="width:100%;">
                <tr>
                    <td style="font-size: 11.5px; text-transform: uppercase;">TOTAL GAJI BERSIH</td>
                    <td class="text-center" style="width: 25px; font-size: 11.5px;">Rp</td>
                    <td class="text-end" style="width: 120px; font-size: 12.5px; font-weight: 900;">{{ number_format($payroll->total_gaji_bersih, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        {{-- SIGNATURES --}}
        <table class="signature-table">
            <tr>
                <td style="width: 50%;">
                    Penerima Gaji,<br><br><br>
                    <u>( {{ strtoupper($payroll->karyawan->nama_karyawan ?? 'Karyawan') }} )</u>
                </td>
                <td style="width: 50%;">
                    Semarang, {{ date('d') }} {{ $namaBulanTahunIndo }}<br>
                    Manager / HRD,<br><br><br>
                    <u>( ________________________ )</u>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
