<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - {{ $payroll->karyawan->nama_karyawan ?? 'Karyawan' }} - {{ \App\Models\Penggajian::formatPeriode($payroll->periode_bulan_tahun) }}</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- html2canvas untuk konversi slip menjadi gambar beresolusi tinggi -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    @php
        $k = $payroll->karyawan;
        $isCombined = $payroll->is_combined ?? false;
        $currentOutlet = $payroll->outlet ?? $k->outlet ?? 'Gaharu';
        $isKejingga = (strtolower($currentOutlet) === 'kejingga');

        // Palet Warna Brand
        $primaryColor  = $isKejingga ? '#ea580c' : '#7A4517';
        $primaryDark   = $isKejingga ? '#c2410c' : '#5a3416';
        $primaryLight  = $isKejingga ? '#fff7ed' : '#fdf8f4';
        $primaryBorder = $isKejingga ? '#ffedd5' : '#f5e6d8';

        $pilihanPeriode = $payroll->pilihan_periode ?? 1;
        $satuanGaji = ($pilihanPeriode == 2 && ($payroll->satuan_gaji_2 || ($k->satuan_gaji_2 ?? null)))
            ? ($payroll->satuan_gaji_2 ?? $k->satuan_gaji_2 ?? 'Harian')
            : ($payroll->satuan_gaji ?? $k->satuan_gaji ?? 'Harian');

        $hasMultiple = isset($allEntries) && $allEntries->count() > 1;
        $periodeLabel = \App\Models\Penggajian::formatPeriode($payroll->periode_bulan_tahun);
        $cleanName = $k->nama_karyawan ?? 'Karyawan';
        
        $takeHomePay = floatval($payroll->total_gaji_bersih > 0 ? $payroll->total_gaji_bersih : ($payroll->total_earnings - $payroll->total_deductions));
        $terbilangText = \App\Models\Penggajian::terbilang($takeHomePay);

        $pdfDownloadUrl = route('penggajian.slip.public-pdf', [
            'id' => $payroll->id,
            'periode' => $selectedP ?: ($isCombined ? 'all' : ($payroll->pilihan_periode ?? 1)),
            'token' => $token ?? \App\Models\Penggajian::generateSlipToken($payroll->id, $selectedP)
        ]);
    @endphp

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .public-wrapper {
            min-height: 100vh;
            padding: 24px 12px 60px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        /* Top Action Bar */
        .top-action-bar {
            width: 100%;
            max-width: 820px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 800;
            color: {{ $primaryColor }};
            background: #ffffff;
            padding: 6px 14px;
            border-radius: 9999px;
            border: 1.5px solid {{ $primaryBorder }};
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .btn-group-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            user-select: none;
        }

        .btn-image {
            background-color: {{ $primaryColor }};
            color: #ffffff;
        }
        .btn-image:hover, .btn-image:active {
            background-color: {{ $primaryDark }};
            transform: translateY(-1px);
        }

        .btn-pdf {
            background-color: #dc2626;
            color: #ffffff;
        }
        .btn-pdf:hover, .btn-pdf:active {
            background-color: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-print {
            background-color: #ffffff;
            color: #334155;
            border: 1.5px solid #cbd5e1;
        }
        .btn-print:hover, .btn-print:active {
            background-color: #f8fafc;
        }

        /* Period Switcher Tabs */
        .period-tabs {
            width: 100%;
            max-width: 820px;
            margin-bottom: 14px;
            display: flex;
            gap: 6px;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 12px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .period-tabs::-webkit-scrollbar {
            display: none;
        }
        .period-tab-item {
            flex: 1;
            text-align: center;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.15s;
            white-space: nowrap;
            min-width: max-content;
        }
        .period-tab-item.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* SLIP GAJI CARD CONTAINER */
        .slip-card {
            width: 100%;
            max-width: 820px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            position: relative;
        }

        /* Card Header */
        .slip-header {
            background: {{ $primaryColor }};
            color: #ffffff;
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .slip-header-brand h1 {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .slip-header-brand p {
            font-size: 11.5px;
            font-weight: 700;
            opacity: 0.9;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .slip-header-badge {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 14px;
            border-radius: 10px;
            text-align: right;
            backdrop-filter: blur(4px);
        }
        .slip-header-badge .periode-title {
            font-size: 13px;
            font-weight: 900;
        }
        .slip-header-badge .outlet-title {
            font-size: 11px;
            font-weight: 600;
            opacity: 0.9;
        }

        /* Employee Meta Info Box */
        .employee-info-box {
            background: {{ $primaryLight }};
            border-bottom: 1.5px solid {{ $primaryBorder }};
            padding: 16px 28px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            font-size: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            padding: 3px 0;
        }
        .info-label {
            color: #64748b;
            font-weight: 600;
            flex-shrink: 0;
        }
        .info-value {
            color: #0f172a;
            font-weight: 800;
            text-align: right;
            word-break: break-word;
        }

        /* Breakdown Grid (Penerimaan vs Potongan) */
        .breakdown-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1.5px solid #e2e8f0;
        }

        .breakdown-col {
            padding: 0;
        }
        .breakdown-col-left {
            border-right: 1.5px solid #e2e8f0;
        }

        .section-header {
            padding: 10px 24px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-header.earn {
            background: #f0fdf4;
            color: #166534;
        }
        .section-header.deduct {
            background: #fef2f2;
            color: #991b1b;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .items-table tr {
            border-bottom: 1px solid #f1f5f9;
        }
        .items-table tr:last-child {
            border-bottom: none;
        }
        .items-table td {
            padding: 9px 24px;
            color: #334155;
        }
        .items-table .item-name {
            font-weight: 600;
        }
        .items-table .item-sub {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            margin-top: 1px;
        }
        .items-table .item-amount {
            text-align: right;
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
        }

        .subtotal-row td {
            background: #f8fafc;
            font-weight: 900;
            padding: 10px 24px;
            border-top: 1px solid #e2e8f0;
        }
        .subtotal-row.earn .item-amount {
            color: #16a34a;
            font-size: 13px;
        }
        .subtotal-row.deduct .item-amount {
            color: #dc2626;
            font-size: 13px;
        }

        /* Take Home Pay Highlighting Box */
        .thp-box {
            background: {{ $primaryColor }};
            color: #ffffff;
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .thp-left {
            flex: 1;
            min-width: 200px;
        }

        .thp-left .thp-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            opacity: 0.95;
        }
        .thp-left .thp-terbilang {
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
            opacity: 0.85;
            font-style: italic;
            line-height: 1.4;
        }

        .thp-right .thp-amount {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.5px;
            white-space: nowrap;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }

        /* Detail Absensi Table (Jika ada denda terlambat) */
        .late-details-section {
            padding: 16px 28px;
            background: #fffbf5;
            border-top: 1px solid #fed7aa;
        }
        .late-title {
            font-size: 11px;
            font-weight: 800;
            color: #9a3412;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .late-scroll-hint {
            display: none;
            font-size: 10px;
            color: #b45309;
            font-weight: 600;
            margin-bottom: 8px;
            align-items: center;
            gap: 4px;
        }
        .late-tbl-scroll {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
            border: 1px solid #fed7aa;
            background: #ffffff;
        }
        .late-tbl {
            width: 100%;
            min-width: 500px;
            border-collapse: collapse;
            font-size: 11px;
        }
        .late-tbl th {
            text-align: left;
            padding: 7px 10px;
            color: #7c2d12;
            font-weight: 800;
            border-bottom: 1px solid #fdba74;
            background: #ffedd5;
            white-space: nowrap;
        }
        .late-tbl td {
            padding: 7px 10px;
            border-bottom: 1px solid #fef3c7;
            color: #431407;
            white-space: nowrap;
        }
        .late-tbl tr:last-child td {
            border-bottom: none;
        }

        /* Slip Footer Signatures */
        .slip-footer {
            padding: 20px 28px 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
            background: #ffffff;
        }

        .legal-notice {
            max-width: 420px;
            font-size: 10px;
            color: #64748b;
            line-height: 1.4;
        }
        .legal-notice strong {
            color: #334155;
        }

        .signature-box {
            text-align: center;
            min-width: 160px;
        }
        .signature-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }
        .signature-stamp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin: 10px 0 6px 0;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 900;
            color: #166534;
            background: #dcfce7;
            border: 1px dashed #86efac;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .signature-name {
            font-size: 11.5px;
            font-weight: 800;
            color: #0f172a;
            border-top: 1.5px solid #0f172a;
            padding-top: 4px;
        }

        /* Toast Popup */
        #toastContainer {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(0,0,0,0.35);
            display: none;
            z-index: 999999;
            align-items: center;
            gap: 8px;
            width: 90%;
            max-width: 400px;
            justify-content: center;
            text-align: center;
        }

        /* ========================================================================= */
        /* MOBILE / HANDPHONE RESPONSIVE ADAPTATIONS (max-width: 680px) */
        /* ========================================================================= */
        @media (max-width: 680px) {
            .public-wrapper {
                padding: 12px 8px 48px 8px;
            }

            .top-action-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                margin-bottom: 12px;
            }

            .brand-badge {
                width: 100%;
                justify-content: center;
                font-size: 12px;
                padding: 6px 10px;
            }

            .btn-group-actions {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .btn-action.btn-image {
                width: 100%;
                padding: 12px 16px;
                font-size: 13.5px;
                border-radius: 12px;
                box-shadow: 0 3px 8px rgba(0,0,0,0.15);
            }

            .btn-action.btn-pdf,
            .btn-action.btn-print {
                flex: 1;
                padding: 9px 12px;
                font-size: 12px;
                border-radius: 10px;
            }

            .period-tabs {
                margin-bottom: 10px;
                padding: 3px;
                border-radius: 10px;
            }
            .period-tab-item {
                font-size: 11.5px;
                padding: 6px 10px;
            }

            .slip-card {
                border-radius: 14px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            }

            .slip-header {
                padding: 16px 16px;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
            }

            .slip-header-brand h1 {
                font-size: 20px;
            }
            .slip-header-brand p {
                font-size: 10px;
                letter-spacing: 0.5px;
            }

            .slip-header-badge {
                padding: 5px 10px;
                border-radius: 8px;
            }
            .slip-header-badge .periode-title {
                font-size: 11.5px;
            }
            .slip-header-badge .outlet-title {
                font-size: 10px;
            }

            .employee-info-box {
                grid-template-columns: 1fr;
                padding: 12px 16px;
                gap: 4px;
                font-size: 11.5px;
            }
            .info-row {
                padding: 2px 0;
            }
            .info-label {
                font-size: 11px;
            }
            .info-value {
                font-size: 11.5px;
            }

            .breakdown-grid {
                grid-template-columns: 1fr;
            }
            .breakdown-col-left {
                border-right: none;
                border-bottom: 2px dashed #e2e8f0;
            }

            .section-header {
                padding: 8px 16px;
                font-size: 10.5px;
            }

            .items-table td {
                padding: 7px 16px;
                font-size: 11.5px;
            }
            .items-table .item-sub {
                font-size: 9.5px;
            }
            .items-table .item-amount {
                font-size: 11.5px;
            }

            .subtotal-row td {
                padding: 8px 16px;
                font-size: 12px;
            }

            .thp-box {
                padding: 16px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .thp-left {
                width: 100%;
            }
            .thp-left .thp-title {
                font-size: 11px;
            }
            .thp-left .thp-terbilang {
                font-size: 10.5px;
                margin-top: 2px;
            }
            .thp-right {
                width: 100%;
                display: flex;
                justify-content: flex-end;
            }
            .thp-right .thp-amount {
                font-size: 24px;
            }

            .late-details-section {
                padding: 14px 16px;
            }
            .late-scroll-hint {
                display: inline-flex;
            }

            .slip-footer {
                padding: 16px 16px 14px;
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 14px;
            }
            .legal-notice {
                text-align: center;
                font-size: 9.5px;
                max-width: 100%;
            }
            .signature-box {
                width: 100%;
            }
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .public-wrapper { padding: 0; }
            .slip-card { box-shadow: none; border-radius: 0; border: none; }
            .slip-header, .thp-box { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .section-header, .employee-info-box { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .late-tbl-scroll { border: none; overflow: visible; }
            .late-scroll-hint { display: none !important; }
        }
    </style>
</head>
<body>

<div class="public-wrapper">

    {{-- Top Action Toolbar --}}
    <div class="top-action-bar no-print">
        <div class="brand-badge">
            <i class="bi bi-shield-check" style="font-size: 15px;"></i>
            <span>Dokumen Resmi Slip Gaji &middot; {{ $currentOutlet }}</span>
        </div>

        <div class="btn-group-actions">
            <button type="button" class="btn-action btn-image" onclick="simpanGambarSlip()" id="btnSaveImage">
                <i class="bi bi-camera-fill"></i>
                <span>Simpan Gambar Slip</span>
            </button>
            <a href="{{ $pdfDownloadUrl }}" class="btn-action btn-pdf">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                <span>Unduh PDF</span>
            </a>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <i class="bi bi-printer-fill"></i>
                <span>Cetak</span>
            </button>
        </div>
    </div>

    {{-- Period Selector Tabs (jika ada lebih dari 1 periode dalam bulan tersebut) --}}
    @if($hasMultiple)
    <div class="period-tabs no-print">
        @php
            $tokenAll = \App\Models\Penggajian::generateSlipToken($payroll->id, 'all');
        @endphp
        <a href="{{ route('penggajian.slip.public', ['id' => $payroll->id, 'periode' => 'all', 'token' => $tokenAll]) }}"
           class="period-tab-item {{ ($isCombined || $selectedP === 'all' || !$selectedP) ? 'active' : '' }}">
            <i class="bi bi-layers-fill me-1"></i> Gabungan Seluruh Periode
        </a>
        @foreach($allEntries as $ent)
            @php
                $pNum = $ent->pilihan_periode ?? 1;
                $tokenP = \App\Models\Penggajian::generateSlipToken($ent->id, $pNum);
            @endphp
            <a href="{{ route('penggajian.slip.public', ['id' => $ent->id, 'periode' => $pNum, 'token' => $tokenP]) }}"
               class="period-tab-item {{ (!$isCombined && $selectedP == $pNum) ? 'active' : '' }}">
                Periode {{ $pNum }}
            </a>
        @endforeach
    </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- SLIP GAJI CARD CONTENT (Captured by html2canvas) --}}
    {{-- ========================================================================= --}}
    <div class="slip-card" id="slipGajiCard">
        
        <!-- Header -->
        <div class="slip-header">
            <div class="slip-header-brand">
                <h1>{{ $currentOutlet }}</h1>
                <p>Dokumen Resmi &middot; Slip Pembayaran Gaji Karyawan</p>
            </div>
            <div class="slip-header-badge">
                <div class="periode-title">{{ $periodeLabel }}</div>
                <div class="outlet-title">
                    @if($isCombined)
                        Akumulasi Periode 1 &amp; 2
                    @else
                        Periode {{ $payroll->pilihan_periode ?? 1 }}
                    @endif
                </div>
            </div>
        </div>

        <!-- Employee Metadata Box -->
        <div class="employee-info-box">
            <div>
                <div class="info-row">
                    <span class="info-label">Nama Karyawan</span>
                    <span class="info-value">{{ $cleanName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jabatan</span>
                    <span class="info-value">{{ $k->jabatan ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Departemen</span>
                    <span class="info-value">{{ $k->departemen ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Outlet</span>
                    <span class="info-value">{{ $currentOutlet }}</span>
                </div>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">No. Rekening</span>
                    <span class="info-value">{{ $k->no_rekening ? $k->no_rekening . ($k->nama_bank ? ' (' . $k->nama_bank . ')' : '') : '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Waktu Kerja</span>
                    <span class="info-value">
                        {{ (float)($payroll->hari_kerja ?? 0) }}
                        {{ $satuanGaji === 'Per Jam' ? 'Jam' : ($satuanGaji === 'Bulanan' ? 'Bulan' : 'Hari') }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Skema Satuan</span>
                    <span class="info-value">{{ $satuanGaji }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tarif Dasar</span>
                    <span class="info-value">
                        Rp {{ number_format($payroll->tarif_harian_total > 0 ? $payroll->tarif_harian_total : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0)), 0, ',', '.') }}
                        <small style="font-size: 10px; font-weight: normal; color: #64748b;">/{{ $satuanGaji === 'Per Jam' ? 'jam' : ($satuanGaji === 'Bulanan' ? 'bln' : 'hari') }}</small>
                    </span>
                </div>
            </div>
        </div>

        <!-- Breakdown Grid (Penerimaan vs Potongan) -->
        <div class="breakdown-grid">
            
            <!-- Kolom Kiri: Penerimaan -->
            <div class="breakdown-col breakdown-col-left">
                <div class="section-header earn">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Penerimaan / Pendapatan</span>
                </div>
                <table class="items-table">
                    <tbody>
                        <tr>
                            <td>
                                <div class="item-name">Gaji Pokok &amp; Kehadiran</div>
                                <div class="item-sub">
                                    {{ (float)($payroll->hari_kerja ?? 0) }} {{ $satuanGaji === 'Per Jam' ? 'jam' : ($satuanGaji === 'Bulanan' ? 'bulan' : 'hari') }} kerja
                                </div>
                            </td>
                            <td class="item-amount">Rp {{ number_format($payroll->gaji_utama ?? 0, 0, ',', '.') }}</td>
                        </tr>

                        @if(($payroll->lembur ?? 0) > 0 || ($payroll->jam_lembur ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Uang Lembur</div>
                                <div class="item-sub">{{ (float)($payroll->jam_lembur ?? 0) }} jam lembur</div>
                            </td>
                            <td class="item-amount">Rp {{ number_format($payroll->lembur ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->bonus_target ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Bonus Target Omzet</div>
                                <div class="item-sub">
                                    {{ ($payroll->banyak_target ?? 0) > 0 ? ($payroll->banyak_target . 'x target') : ($payroll->catatan_bonus_target ?? 'Pencapaian target') }}
                                </div>
                            </td>
                            <td class="item-amount">Rp {{ number_format($payroll->bonus_target ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->bonus_tanggal_merah ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Bonus Tanggal Merah</div>
                                <div class="item-sub">
                                    {{ ($payroll->banyak_tanggal_merah ?? 0) > 0 ? ($payroll->banyak_tanggal_merah . 'x hari libur nasional') : ($payroll->catatan_bonus_tanggal_merah ?? 'Masuk tanggal merah') }}
                                </div>
                            </td>
                            <td class="item-amount">Rp {{ number_format($payroll->bonus_tanggal_merah ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->bonus_birthday ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Birthday Service</div>
                                <div class="item-sub">{{ ($payroll->banyak_birthday_service ?? 0) }}x serving customer ultah</div>
                            </td>
                            <td class="item-amount">Rp {{ number_format($payroll->bonus_birthday ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->pengembalian_deposit ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name" style="color: #047857;">Pengembalian Deposit</div>
                                <div class="item-sub">Pencairan saldo deposit karyawan</div>
                            </td>
                            <td class="item-amount" style="color: #047857;">Rp {{ number_format($payroll->pengembalian_deposit ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->bonus_dll ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Bonus Lain-lain</div>
                                <div class="item-sub">Insentif / Tambahan khusus</div>
                            </td>
                            <td class="item-amount">Rp {{ number_format($payroll->bonus_dll ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        <tr class="subtotal-row earn">
                            <td>Total Penerimaan</td>
                            <td class="item-amount">Rp {{ number_format($payroll->total_earnings ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Kolom Kanan: Potongan -->
            <div class="breakdown-col">
                <div class="section-header deduct">
                    <i class="bi bi-dash-circle-fill"></i>
                    <span>Potongan &amp; Pengurangan</span>
                </div>
                <table class="items-table">
                    <tbody>
                        @if(($payroll->potongan_terlambat ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name" style="color: #b91c1c;">Denda Keterlambatan</div>
                                <div class="item-sub">Akumulasi presensi kehadiran</div>
                            </td>
                            <td class="item-amount" style="color: #b91c1c;">- Rp {{ number_format($payroll->potongan_terlambat ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->potongan_kasbon ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Potongan Kasbon</div>
                                <div class="item-sub">Cicilan / pinjaman karyawan</div>
                            </td>
                            <td class="item-amount">- Rp {{ number_format($payroll->potongan_kasbon ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->potongan_deposit ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Pengurangan Deposit</div>
                                <div class="item-sub">Tabungan deposit awal karyawan</div>
                            </td>
                            <td class="item-amount">- Rp {{ number_format($payroll->potongan_deposit ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->potongan_inventaris ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Kerusakan Inventaris</div>
                                <div class="item-sub">Ganti rugi barang operasional</div>
                            </td>
                            <td class="item-amount">- Rp {{ number_format($payroll->potongan_inventaris ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->potongan_dll ?? 0) > 0)
                        <tr>
                            <td>
                                <div class="item-name">Potongan Lain-lain</div>
                                <div class="item-sub">{{ $payroll->catatan_potongan_dll ?? 'Kewajiban lainnya' }}</div>
                            </td>
                            <td class="item-amount">- Rp {{ number_format($payroll->potongan_dll ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endif

                        @if(($payroll->total_deductions ?? 0) == 0)
                        <tr>
                            <td colspan="2" style="text-align: center; color: #94a3b8; padding: 24px 12px; font-style: italic;">
                                Tidak ada potongan pada periode ini.
                            </td>
                        </tr>
                        @endif

                        <tr class="subtotal-row deduct">
                            <td>Total Potongan</td>
                            <td class="item-amount">- Rp {{ number_format($payroll->total_deductions ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Take Home Pay Highlight Box -->
        <div class="thp-box">
            <div class="thp-left">
                <div class="thp-title">Total Gaji Bersih (Take Home Pay)</div>
                <div class="thp-terbilang">"{{ $terbilangText }} Rupiah"</div>
            </div>
            <div class="thp-right">
                <div class="thp-amount">Rp {{ number_format($takeHomePay, 0, ',', '.') }}</div>
            </div>
        </div>

        <!-- Rincian Keterlambatan (Jika ada) -->
        @if(isset($listKeterlambatan) && $listKeterlambatan->isNotEmpty() && ($payroll->potongan_terlambat ?? 0) > 0)
        <div class="late-details-section">
            <div class="late-title">
                <i class="bi bi-clock-history"></i>
                <span>Rincian Presensi Keterlambatan</span>
            </div>
            <div class="late-scroll-hint">
                <i class="bi bi-arrows-expand" style="transform: rotate(45deg);"></i>
                <span>Geser tabel ke samping untuk melihat detail presensi</span>
            </div>
            <div class="late-tbl-scroll">
                <table class="late-tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Shift</th>
                            <th>Jadwal Masuk</th>
                            <th>Absen Masuk</th>
                            <th>Menit Terlambat</th>
                            <th style="text-align: right;">Potongan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($listKeterlambatan as $late)
                        @php
                            $shiftVal = $late->shift ?? $late->shift_nama ?? '-';
                            $jamJadwal = $late->jam_shift ?? $late->jam_masuk_jadwal ?? null;
                            $jamAbsen = $late->jam_datang ?? $late->jam_masuk_absen ?? null;
                            $durasiMenit = $late->durasi_menit ?? $late->menit_terlambat ?? 0;

                            $fmtJadwal = $jamJadwal ? substr($jamJadwal, 0, 5) : '-';
                            $fmtAbsen = $jamAbsen ? substr($jamAbsen, 0, 5) : '-';
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($late->tanggal)->format('d/m/Y') }}</td>
                            <td>{{ $shiftVal }}</td>
                            <td>{{ $fmtJadwal }}</td>
                            <td>{{ $fmtAbsen }}</td>
                            <td>{{ $durasiMenit }} Menit</td>
                            <td style="text-align: right; font-weight: 800; color: #dc2626;">Rp {{ number_format($late->potongan, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Card Footer / Digital Verification -->
        <div class="slip-footer">
            <div class="legal-notice">
                <p><strong>Pemberitahuan:</strong></p>
                <p>Dokumen ini diterbitkan secara resmi melalui Sistem Informasi Manajemen Penggajian {{ $currentOutlet }} dan sah tanpa tanda tangan basah.</p>
                <p style="margin-top: 4px; font-size: 9.5px; color: #94a3b8;">Dicetak pada: {{ now()->translatedFormat('d F Y, H:i') }} WIB</p>
            </div>
            <div class="signature-box">
                <div class="signature-title">Dikeluarkan Oleh,</div>
                <div class="signature-stamp">
                    <i class="bi bi-patch-check-fill"></i> TERVERIFIKASI
                </div>
                <div class="signature-name">Manajemen {{ $currentOutlet }}</div>
            </div>
        </div>

    </div>

</div>

<!-- Toast Feedback -->
<div id="toastContainer">
    <i class="bi bi-check-circle-fill text-emerald-400"></i>
    <span id="toastMessage">Gambar slip gaji berhasil disimpan!</span>
</div>

<script>
    function showToast(msg) {
        const toast = document.getElementById('toastContainer');
        const toastMsg = document.getElementById('toastMessage');
        toastMsg.textContent = msg;
        toast.style.display = 'flex';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }

    async function simpanGambarSlip() {
        const btn = document.getElementById('btnSaveImage');
        const slipCard = document.getElementById('slipGajiCard');
        if (!slipCard) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> <span>Membuat Gambar...</span>';

        try {
            // Scroll ke atas untuk memastikan rendering bersih
            window.scrollTo(0, 0);

            const canvas = await html2canvas(slipCard, {
                scale: 2.5, // Resolusi 2.5x agar sangat tajam di layar HP/Retina
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                windowWidth: slipCard.scrollWidth,
                windowHeight: slipCard.scrollHeight
            });

            // Convert ke format data URL PNG
            const imgData = canvas.toDataURL('image/png');

            const employeeName = '{{ preg_replace("/[^A-Za-z0-9_-]/", "_", $cleanName) }}';
            const periodeName = '{{ preg_replace("/[^A-Za-z0-9_-]/", "_", $periodeLabel) }}';
            const fileName = `Slip_Gaji_${employeeName}_${periodeName}.png`;

            // Trigger download link
            const downloadLink = document.createElement('a');
            downloadLink.href = imgData;
            downloadLink.download = fileName;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);

            showToast('Gambar slip gaji berhasil disimpan ke perangkat Anda!');
        } catch (err) {
            console.error(err);
            alert('Gagal membuat gambar slip gaji. Silakan coba kembali atau gunakan tombol Unduh PDF.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>

</body>
</html>
