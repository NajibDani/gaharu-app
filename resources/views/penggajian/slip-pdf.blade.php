<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji - {{ $payroll->karyawan->nama_karyawan ?? 'Karyawan' }} - {{ $payroll->periode_bulan_tahun }}</title>
    @php
        $k = $payroll->karyawan;
        $currentOutlet   = $payroll->outlet ?? $k->outlet ?? 'Gaharu';
        $isKejingga      = (strtolower($currentOutlet) === 'kejingga');
        $accentColor     = $isKejingga ? '#0f766e' : '#7A4517';
        $accentLight     = $isKejingga ? '#f0fdfa' : '#fffbf5';
        $accentBorder    = $isKejingga ? '#99f6e4' : '#fde68a';

        $carbonPeriode   = \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-01');
        $periodeLabel    = \App\Models\Penggajian::formatPeriode($payroll->periode_bulan_tahun);

        $isCombined = !empty($payroll->is_combined);
        $allEntries = $allEntries ?? collect([$payroll]);

        // Salary period
        $pilihanPeriode = $payroll->pilihan_periode ?? 1;
        $satuan = ($pilihanPeriode == 2 && $k->satuan_gaji_2) ? ($k->satuan_gaji_2 ?? 'Harian') : ($k->satuan_gaji ?? 'Harian');

        // Tariff rates from selected period
        $gpRate = ($pilihanPeriode == 2 && $k->gaji_pokok_2 !== null) ? $k->gaji_pokok_2 : ($k->gaji_pokok ?? 0);
        $umRate = ($pilihanPeriode == 2 && $k->uang_makan_2 !== null) ? $k->uang_makan_2 : ($k->uang_makan ?? 0);
        $utRate = ($pilihanPeriode == 2 && $k->uang_transport_2 !== null) ? $k->uang_transport_2 : ($k->uang_transport ?? 0);
        $tarifTotal = $gpRate + $umRate + $utRate;

        // Earnings
        $gajiUtama = $payroll->gaji_utama > 0
            ? $payroll->gaji_utama
            : ($payroll->hari_kerja * ($payroll->tarif_harian_total ?? $tarifTotal));

        $calcEarnings = $payroll->total_earnings > 0 ? $payroll->total_earnings : (
            $gajiUtama + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
            ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->bonus_dll ?? 0)
        );
        $calcDeductions = $payroll->total_deductions > 0 ? $payroll->total_deductions : (
            ($payroll->potongan_terlambat ?? 0) + ($payroll->potongan_inventaris ?? 0) +
            ($payroll->potongan_kasbon ?? 0) + ($payroll->potongan_dll ?? 0)
        );
        $takeHomePay = $payroll->total_gaji_bersih ?: ($calcEarnings - $calcDeductions);

        $tglSlip = '';
        if ($payroll->tanggal_mulai && $payroll->tanggal_selesai) {
            $tglSlip = \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m/Y')
                     . ' – ' . \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m/Y');
        }
    @endphp
    <style>
        @page {
            margin: 8mm 10mm;
            size: a4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* ── CARD CONTAINER ── */
        .card {
            background: #ffffff;
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        /* ── HEADER BAND ── */
        .hdr {
            background-color: {{ $accentColor }};
            color: #ffffff;
            padding: 16px 20px;
            width: 100%;
        }
        .hdr-tbl {
            width: 100%;
            border-collapse: collapse;
        }
        .hdr-brand {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }
        .hdr-sliplabel {
            font-size: 9px;
            font-weight: bold;
            color: #fde68a;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .hdr-periode {
            font-size: 11px;
            font-weight: bold;
            color: #ffffff;
            margin-top: 5px;
        }
        .emp-box {
            border: 1px solid rgba(255,255,255,0.45);
            border-radius: 8px;
            background-color: rgba(255,255,255,0.14);
            padding: 8px 12px;
        }
        .emp-tbl {
            width: 100%;
            border-collapse: collapse;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
        }
        .emp-tbl td {
            padding: 2.5px 3px;
            vertical-align: middle;
        }

        /* ── TARIF INFO BOX ── */
        .tarif-box-wrap {
            padding: 10px 14px;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .tarif-box-card {
            background-color: {{ $accentLight }};
            border: 1px solid {{ $accentBorder }};
            border-radius: 8px;
            padding: 10px 14px;
        }
        .tarif-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #78350f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .tarif-tbl-multi {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            font-size: 9.5px;
        }
        .tarif-cell-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .badge-unit {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 1px 4px;
        }

        /* TARIF SINGLE TABLE */
        .tarif-tbl-single {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        .tarif-tbl-single td {
            padding: 4px 6px;
            border-right: 1px solid #e2e8f0;
        }
        .tarif-tbl-single td:last-child {
            border-right: none;
        }
        .tarif-tbl-single .t-lbl {
            font-size: 8px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }
        .tarif-tbl-single .t-val {
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }

        /* ── BODY: 2 COLUMNS ── */
        .body-tbl {
            width: 100%;
            border-collapse: collapse;
        }
        .body-tbl > tbody > tr > td {
            vertical-align: top;
            width: 50%;
            padding: 0;
        }
        .body-tbl > tbody > tr > td.left-col {
            border-right: 1.5px solid #e2e8f0;
        }

        /* ── SECTION HEADERS ── */
        .sec-earn {
            background-color: #ecfdf5;
            color: #065f46;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 6px 12px;
            border-bottom: 1px solid #d1fae5;
        }
        .sec-deduct {
            background-color: #fefce8;
            color: #713f12;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 6px 12px;
            border-bottom: 1px solid #fde68a;
        }
        .sec-sub {
            background-color: #f8fafc;
            color: #334155;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 4px 12px;
            border-bottom: 1px solid #e2e8f0;
            border-top: 1px solid #e2e8f0;
        }

        /* ── DETAIL ROW TABLE ── */
        .row-tbl {
            width: 100%;
            border-collapse: collapse;
        }
        .row-tbl td {
            padding: 5px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9.5px;
            color: #334155;
            vertical-align: middle;
        }
        .row-tbl .lbl {
            font-weight: 500;
        }
        .row-tbl .note {
            display: block;
            font-size: 8.5px;
            color: #64748b;
            font-weight: normal;
            margin-top: 1px;
        }
        .row-tbl .val {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
            white-space: nowrap;
        }
        .row-tbl .subtotal td {
            background-color: #f8fafc;
            font-weight: bold;
            color: #1e293b;
            border-top: 1px solid #e2e8f0;
            padding: 6px 12px;
        }
        .zero-val {
            color: #94a3b8 !important;
        }

        /* ── LATE TABLE ── */
        .late-tbl {
            width: 100%;
            border-collapse: collapse;
        }
        .late-tbl th {
            background: #f8fafc;
            padding: 4px 10px;
            font-size: 8.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .late-tbl td {
            padding: 4px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9px;
            color: #334155;
        }
        .late-tbl .val {
            text-align: right;
            font-weight: bold;
        }
        .late-none {
            padding: 8px 12px;
            font-size: 9px;
            color: #94a3b8;
            font-style: italic;
        }

        /* ── FOOTER TOTALS ── */
        .totals-tbl {
            width: 100%;
            border-collapse: collapse;
            border-top: 1.5px solid #e2e8f0;
        }
        .totals-tbl td {
            padding: 8px 16px;
            width: 50%;
            vertical-align: middle;
        }
        .totals-tbl .earn-box {
            background-color: #ecfdf5;
            border-right: 1.5px solid #a7f3d0;
        }
        .totals-tbl .deduct-box {
            background-color: #fefce8;
        }
        .total-label {
            font-size: 8.5px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .total-earn-amt {
            font-size: 13.5px;
            font-weight: bold;
            color: #059669;
            margin-top: 2px;
        }
        .total-deduct-amt {
            font-size: 13.5px;
            font-weight: bold;
            color: #d97706;
            margin-top: 2px;
        }

        /* ── TAKE HOME PAY BAND ── */
        .thp-wrap {
            background-color: {{ $accentColor }};
            color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .thp-tbl {
            width: 100%;
            border-collapse: collapse;
            color: #ffffff;
        }
        .thp-tbl td {
            padding: 10px 18px;
            vertical-align: middle;
        }
        .thp-label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #ffffff;
        }
        .thp-sub {
            font-size: 8.5px;
            color: rgba(255,255,255,0.85);
            margin-top: 2px;
        }
        .thp-amt {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            color: #ffffff;
            white-space: nowrap;
            padding-right: 20px !important;
        }

        /* ── SIGNATURES ── */
        .sig-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            text-align: center;
            font-size: 9.5px;
            font-weight: bold;
            color: #475569;
        }
        .sig-tbl td {
            padding: 4px;
            width: 50%;
            vertical-align: top;
        }
        .sig-line {
            border-top: 1.5px solid #475569;
            margin-top: 44px;
            padding-top: 4px;
            display: inline-block;
            min-width: 180px;
        }
    </style>
</head>
<body>
<div class="card">

    {{-- ═══ HEADER ═══ --}}
    <div class="hdr">
        <table class="hdr-tbl">
            <tr>
                <td style="width: 44%; vertical-align: middle;">
                    <div class="hdr-brand">
                        @if($isKejingga)
                            <span style="font-size: 13px; font-weight: bold; vertical-align: super; color: #fdba74;">ke</span><span style="color: #fdba74;">JINGGA</span>
                        @else
                            GAHARU
                        @endif
                    </div>
                    <div class="hdr-sliplabel">SLIP GAJI KARYAWAN {{ $isCombined ? '· GABUNGAN SEMUA PERIODE' : '· PERIODE ' . $pilihanPeriode }}</div>
                    <div class="hdr-periode">
                        Periode {{ $periodeLabel }}
                        @if($tglSlip) &nbsp;&bull;&nbsp; {{ $tglSlip }} @endif
                    </div>
                </td>
                <td style="width: 56%; vertical-align: middle;">
                    <div class="emp-box">
                        <table class="emp-tbl">
                            <tr>
                                <td style="width: 78px; opacity: 0.9;">NAMA</td>
                                <td style="width: 8px; text-align: center;">:</td>
                                <td style="font-size: 11px; font-weight: bold;">{{ strtoupper($k->nama_karyawan ?? '-') }}</td>
                            </tr>
                            <tr>
                                <td style="opacity: 0.9;">JABATAN</td>
                                <td style="text-align: center;">:</td>
                                <td>{{ $k->jabatan ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td style="opacity: 0.9;">DIVISI</td>
                                <td style="text-align: center;">:</td>
                                <td>{{ $k->departemen ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td style="opacity: 0.9;">OUTLET</td>
                                <td style="text-align: center;">:</td>
                                <td>{{ strtoupper($currentOutlet) }}</td>
                            </tr>
                            <tr>
                                <td style="opacity: 0.9;">NO. REKENING</td>
                                <td style="text-align: center;">:</td>
                                <td style="letter-spacing: 0.5px;">{{ $k->no_rekening ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══ TARIF INFO BOX ═══ --}}
    <div class="tarif-box-wrap">
        @if($isCombined)
        <div class="tarif-box-card">
            <div class="tarif-title">
                Rincian Periode Tergabung di Bulan Ini
            </div>
            <table class="tarif-tbl-multi">
                <tr>
                    @foreach($allEntries as $ent)
                        @php
                            $pNum = $ent->pilihan_periode ?? 1;
                            $sat = ($pNum == 2 && $k->satuan_gaji_2) ? ($k->satuan_gaji_2 ?? 'Harian') : ($k->satuan_gaji ?? 'Harian');
                            $gp = ($pNum == 2 && $k->gaji_pokok_2 !== null) ? $k->gaji_pokok_2 : ($k->gaji_pokok ?? 0);
                            $um = ($pNum == 2 && $k->uang_makan_2 !== null) ? $k->uang_makan_2 : ($k->uang_makan ?? 0);
                            $ut = ($pNum == 2 && $k->uang_transport_2 !== null) ? $k->uang_transport_2 : ($k->uang_transport ?? 0);
                            $tar = $gp + $um + $ut;
                            $hkUnit = $sat == 'Per Jam' ? ' jam' : ($sat == 'Bulanan' ? ' bln' : ' hari');
                        @endphp
                        <td class="tarif-cell-card" style="width: {{ 100 / count($allEntries) }}%;">
                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
                                <tr>
                                    <td style="font-weight: bold; color: #0f172a; font-size: 10px;">Periode {{ $pNum }}</td>
                                    <td style="text-align: right;"><span class="badge-unit">{{ $sat }}</span></td>
                                </tr>
                            </table>
                            <div style="color: #64748b; font-size: 8.5px; margin-bottom: 3px;">
                                @if($ent->tanggal_mulai && $ent->tanggal_selesai)
                                    {{ \Carbon\Carbon::parse($ent->tanggal_mulai)->format('d/m') }} - {{ \Carbon\Carbon::parse($ent->tanggal_selesai)->format('d/m/Y') }}
                                @endif
                            </div>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 3px; font-weight: bold; font-size: 9px;">
                                <tr>
                                    <td style="color: #334155;">Tarif: Rp {{ number_format($tar, 0, ',', '.') }}</td>
                                    <td style="text-align: right; color: #0284c7;">{{ $ent->hari_kerja }}{{ $hkUnit }}</td>
                                </tr>
                            </table>
                            <div style="font-weight: bold; color: #059669; font-size: 10.5px; text-align: right; margin-top: 3px;">
                                Rp {{ number_format($ent->gaji_utama, 0, ',', '.') }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
        @else
        <div class="tarif-box-card" style="padding: 6px 10px;">
            <table class="tarif-tbl-single">
                <tr>
                    <td>
                        <div class="t-lbl">Tarif Periode</div>
                        <div class="t-val">
                            P{{ $pilihanPeriode }}
                            <span class="badge-unit" style="margin-left: 2px;">{{ $satuan }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="t-lbl">Gaji Pokok / {{ $satuan == 'Per Jam' ? 'Jam' : 'Hari' }}</div>
                        <div class="t-val">Rp {{ number_format($gpRate, 0, ',', '.') }}</div>
                    </td>
                    <td>
                        <div class="t-lbl">Uang Makan / {{ $satuan == 'Per Jam' ? 'Jam' : 'Hari' }}</div>
                        <div class="t-val">Rp {{ number_format($umRate, 0, ',', '.') }}</div>
                    </td>
                    <td>
                        <div class="t-lbl">Transport / {{ $satuan == 'Per Jam' ? 'Jam' : 'Hari' }}</div>
                        <div class="t-val">Rp {{ number_format($utRate, 0, ',', '.') }}</div>
                    </td>
                </tr>
            </table>
        </div>
        @endif
    </div>

    {{-- ═══ BODY 2-COL ═══ --}}
    <table class="body-tbl">
        <tbody>
            <tr>
                {{-- LEFT: PENDAPATAN --}}
                <td class="left-col">
                    <div class="sec-earn">&#9650; Pendapatan</div>

                    {{-- Gaji Pokok --}}
                    <div class="sec-sub">Gaji Pokok</div>
                    <table class="row-tbl">
                        @if($isCombined)
                            @foreach($allEntries as $ent)
                                @php
                                    $pNum = $ent->pilihan_periode ?? 1;
                                    $sat = ($pNum == 2 && $k->satuan_gaji_2) ? ($k->satuan_gaji_2 ?? 'Harian') : ($k->satuan_gaji ?? 'Harian');
                                    $hkUnit = $sat == 'Per Jam' ? ' jam' : ($sat == 'Bulanan' ? ' bln' : ' hari');
                                @endphp
                                <tr>
                                    <td class="lbl">
                                        Periode {{ $pNum }} ({{ $sat }})
                                        <span class="note">{{ $ent->hari_kerja }}{{ $hkUnit }} kerja</span>
                                    </td>
                                    <td class="val">Rp {{ number_format($ent->gaji_utama, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        @else
                            @if($satuan == 'Harian')
                            <tr><td class="lbl">Tarif / Hari</td><td class="val">Rp {{ number_format($tarifTotal, 0, ',', '.') }}</td></tr>
                            <tr><td class="lbl">Hari Kerja</td><td class="val">{{ $payroll->hari_kerja }} hari</td></tr>
                            @elseif($satuan == 'Bulanan')
                            <tr><td class="lbl">Tarif Bulanan</td><td class="val">Rp {{ number_format($tarifTotal, 0, ',', '.') }}</td></tr>
                            @elseif($satuan == 'Per Jam')
                            <tr><td class="lbl">Tarif / Jam</td><td class="val">Rp {{ number_format($tarifTotal, 0, ',', '.') }}</td></tr>
                            <tr><td class="lbl">Jam Kerja</td><td class="val">{{ $payroll->hari_kerja }} jam</td></tr>
                            @endif
                        @endif
                        <tr class="subtotal">
                            <td>Total Gaji Pokok</td>
                            <td class="val" style="color:#059669;">Rp {{ number_format($gajiUtama, 0, ',', '.') }}</td>
                        </tr>
                    </table>

                    @if(($payroll->lembur ?? 0) > 0 || ($payroll->jam_lembur ?? 0) > 0)
                    <div class="sec-sub">Lembur</div>
                    <table class="row-tbl">
                        <tr>
                            <td class="lbl">Jam Lembur</td>
                            <td class="val">{{ $payroll->jam_lembur ?? 0 }} jam</td>
                        </tr>
                        <tr class="subtotal">
                            <td>Total Lembur</td>
                            <td class="val" style="color:#059669;">Rp {{ number_format($payroll->lembur ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif

                    @if(($payroll->bonus_target ?? 0) > 0)
                    <div class="sec-sub">Bonus Target Penjualan</div>
                    <table class="row-tbl">
                        <tr>
                            <td class="lbl">Banyak Target</td>
                            <td class="val">{{ $payroll->banyak_target }} target</td>
                        </tr>
                        @if($payroll->banyak_target > 0)
                        <tr>
                            <td class="lbl">Bonus / Target</td>
                            <td class="val">Rp {{ number_format($payroll->bonus_target / $payroll->banyak_target, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        <tr class="subtotal">
                            <td>Total Bonus Target</td>
                            <td class="val" style="color:#059669;">Rp {{ number_format($payroll->bonus_target, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif

                    @if(($payroll->bonus_tanggal_merah ?? 0) > 0)
                    <div class="sec-sub">Bonus Hari Merah</div>
                    <table class="row-tbl">
                        <tr>
                            <td class="lbl">Banyak Hari Merah</td>
                            <td class="val">{{ $payroll->banyak_tanggal_merah }} hari</td>
                        </tr>
                        <tr class="subtotal">
                            <td>Total Bonus Hari Merah</td>
                            <td class="val" style="color:#059669;">Rp {{ number_format($payroll->bonus_tanggal_merah, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif

                    @if(($payroll->bonus_birthday ?? 0) > 0)
                    <div class="sec-sub">Birthday Service</div>
                    <table class="row-tbl">
                        <tr>
                            <td class="lbl">Banyak Service</td>
                            <td class="val">{{ $payroll->banyak_birthday_service }}</td>
                        </tr>
                        <tr class="subtotal">
                            <td>Total Bonus Birthday</td>
                            <td class="val" style="color:#059669;">Rp {{ number_format($payroll->bonus_birthday, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif

                    @if(($payroll->bonus_dll ?? 0) > 0)
                    <div class="sec-sub">Bonus Lain-lain</div>
                    <table class="row-tbl">
                        <tr class="subtotal">
                            <td>Total Bonus Lain</td>
                            <td class="val" style="color:#059669;">Rp {{ number_format($payroll->bonus_dll, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif
                </td>

                {{-- RIGHT: POTONGAN --}}
                <td>
                    <div class="sec-deduct">&#9660; Potongan</div>

                    {{-- Keterlambatan --}}
                    <div class="sec-sub">Keterlambatan</div>
                    @if(count($listKeterlambatan) > 0)
                    <table class="late-tbl">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Shift</th>
                                <th>Jam Datang</th>
                                <th class="val">Potongan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($listKeterlambatan as $t)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($t->tanggal)->format('d/m/Y') }}</td>
                                <td>{{ $t->shift ?? '-' }}</td>
                                <td>{{ substr($t->jam_datang, 0, 5) }}</td>
                                <td class="val">Rp {{ number_format($t->potongan, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div class="late-none">Tidak ada catatan keterlambatan</div>
                    @endif
                    <table class="row-tbl">
                        <tr class="subtotal">
                            <td>Total Potongan Terlambat</td>
                            <td class="val {{ ($payroll->potongan_terlambat ?? 0) == 0 ? 'zero-val' : '' }}" style="{{ ($payroll->potongan_terlambat ?? 0) > 0 ? 'color:#dc2626;' : '' }}">
                                {{ ($payroll->potongan_terlambat ?? 0) > 0 ? 'Rp ' . number_format($payroll->potongan_terlambat, 0, ',', '.') : '-' }}
                            </td>
                        </tr>
                    </table>

                    @if(($payroll->potongan_inventaris ?? 0) > 0)
                    <div class="sec-sub">Kerusakan Inventaris</div>
                    <table class="row-tbl">
                        <tr class="subtotal">
                            <td>Total Potongan Inventaris</td>
                            <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_inventaris, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif

                    @if(($payroll->potongan_kasbon ?? 0) > 0)
                    <div class="sec-sub">Kasbon</div>
                    <table class="row-tbl">
                        <tr class="subtotal">
                            <td>Total Kasbon</td>
                            <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_kasbon, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif

                    @if(($payroll->potongan_dll ?? 0) > 0)
                    <div class="sec-sub">Potongan Lain-lain</div>
                    <table class="row-tbl">
                        <tr class="subtotal">
                            <td>Total Potongan Lain</td>
                            <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_dll, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ═══ TOTAL ROW ═══ --}}
    <table class="totals-tbl">
        <tr>
            <td class="earn-box">
                <div class="total-label">&#9650; Total Pendapatan</div>
                <div class="total-earn-amt">Rp {{ number_format($calcEarnings, 0, ',', '.') }}</div>
            </td>
            <td class="deduct-box">
                <div class="total-label">&#9660; Total Potongan</div>
                <div class="total-deduct-amt">Rp {{ number_format($calcDeductions, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    {{-- ═══ TAKE HOME PAY ═══ --}}
    <div class="thp-wrap">
        <table class="thp-tbl">
            <tr>
                <td style="width: 60%;">
                    <div class="thp-label">Gaji Bersih Diterima</div>
                    <div class="thp-sub">{{ strtoupper($k->nama_karyawan ?? '') }} &bull; Periode {{ $periodeLabel }}</div>
                </td>
                <td class="thp-amt" style="width: 40%;">Rp {{ number_format($takeHomePay, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

</div>{{-- end .card --}}

{{-- ═══ SIGNATURES (OUTSIDE CARD FOR BALANCED LAYOUT) ═══ --}}
<table class="sig-tbl">
    <tr>
        <td>
            Penerima Gaji,<br><br><br><br>
            <span class="sig-line">( {{ strtoupper($k->nama_karyawan ?? 'Karyawan') }} )</span>
        </td>
        <td>
            Semarang, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
            Manager / HRD,<br><br><br><br>
            <span class="sig-line">( _____________________ )</span>
        </td>
    </tr>
</table>

</body>
</html>
