<x-app-layout>
@php
    $k = $payroll->karyawan;
    $currentOutlet = $payroll->outlet ?? $k->outlet ?? 'Gaharu';
    $isKejingga    = (strtolower($currentOutlet) === 'kejingga');
    $accentColor   = $isKejingga ? '#0f766e' : '#7A4517';
    $accentLight   = $isKejingga ? '#f0fdfa' : '#fffbf5';
    $accentBorder  = $isKejingga ? '#99f6e4' : '#fde68a';

    $carbonPeriode   = \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-01');
    $namaBulanTahun  = $carbonPeriode->translatedFormat('F Y');

    // Period label
    $periodeLabel = \App\Models\Penggajian::formatPeriode($payroll->periode_bulan_tahun);

    $isCombined = !empty($payroll->is_combined);
    $allEntries = $allEntries ?? collect([$payroll]);
    $hasMultiple = $allEntries->count() > 1;

    // Satuan gaji
    $pilihanPeriode = $payroll->pilihan_periode ?? 1;
    $satuan = ($pilihanPeriode == 2 && $k->satuan_gaji_2) ? ($k->satuan_gaji_2 ?? 'Harian') : ($k->satuan_gaji ?? 'Harian');

    // Tariff rates (from the chosen period)
    $gpRate  = ($pilihanPeriode == 2 && $k->gaji_pokok_2  !== null) ? $k->gaji_pokok_2  : ($k->gaji_pokok  ?? 0);
    $umRate  = ($pilihanPeriode == 2 && $k->uang_makan_2  !== null) ? $k->uang_makan_2  : ($k->uang_makan  ?? 0);
    $utRate  = ($pilihanPeriode == 2 && $k->uang_transport_2 !== null) ? $k->uang_transport_2 : ($k->uang_transport ?? 0);
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

    // Tanggal slip
    $tglSlip = '';
    if ($payroll->tanggal_mulai && $payroll->tanggal_selesai) {
        $tglSlip = \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m/Y')
                 . ' – '
                 . \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m/Y');
    }

    // WhatsApp link
    $waRaw = preg_replace('/\D/', '', $k->whatsapp ?? '');
    if ($waRaw !== '' && substr($waRaw, 0, 1) === '0') {
        $waRaw = '62' . substr($waRaw, 1);
    } elseif ($waRaw !== '' && substr($waRaw, 0, 2) !== '62') {
        $waRaw = '62' . $waRaw;
    }
    $pdfUrlParams = [];
    if ($isCombined) {
        $pdfUrlParams['periode'] = 'all';
    } elseif ($selectedP) {
        $pdfUrlParams['periode'] = $selectedP;
    }
    $pdfUrl = route('penggajian.pdf', array_merge(['id' => $payroll->id], $pdfUrlParams));
    $slipTitleType = $isCombined ? 'Gabungan (P1 & P2)' : ('Periode ' . ($payroll->pilihan_periode ?? '1'));
    $cleanEmployeeName = $k->nama_karyawan ?? 'Karyawan';
    $pdfFileName = 'Slip_Gaji_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cleanEmployeeName) . '_' . str_replace(' ', '_', $periodeLabel) . '.pdf';
    $nominalFmt = number_format($takeHomePay, 0, ',', '.');
    $waMessage = "Halo *{$cleanEmployeeName}*,\n\nTerlampir kami sampaikan dokumen resmi *Slip Gaji* untuk periode *{$periodeLabel}* ({$currentOutlet}).\nTotal Gaji Bersih (Take Home Pay): *Rp {$nominalFmt}*\n\nTerima kasih atas dedikasi dan kerja keras yang telah Anda berikan untuk tim. Semoga berkah dan memotivasi kinerja ke depan.\n\n🙏✨\n*Salam hangat,*\n*Manajemen {$currentOutlet}*";
    $waUrl = $waRaw !== ''
        ? 'https://api.whatsapp.com/send/?phone=' . $waRaw . '&text=' . rawurlencode($waMessage) . '&type=phone_number&app_absent=0'
        : null;
@endphp
<style>
    .sp-wrap { background: #f1f5f9; min-height: 100vh; padding: 28px 16px; }
    .sp-card {
        max-width: 900px; margin: 0 auto;
        background: #fff; border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.10);
        overflow: hidden;
    }

    /* HEADER BAND */
    .sp-header {
        background: {{ $accentColor }};
        color: #fff;
        padding: 18px 28px 16px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }
    .sp-header-left .sp-brand { font-size: 22px; font-weight: 900; letter-spacing: 0.5px; line-height: 1; }
    .sp-header-left .sp-slip-label { font-size: 11px; font-weight: 700; opacity: 0.8; margin-top: 4px; letter-spacing: 1px; text-transform: uppercase; }
    .sp-header-left .sp-periode { font-size: 13px; font-weight: 700; margin-top: 6px; opacity: 0.95; }

    .sp-emp-box {
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.4);
        border-radius: 10px;
        padding: 10px 14px;
        min-width: 260px;
        font-size: 11.5px;
        font-weight: 700;
    }
    .sp-emp-box table { width: 100%; border-collapse: collapse; color: #fff; }
    .sp-emp-box td { padding: 2.5px 4px; }
    .sp-emp-box .lbl { width: 100px; opacity: 0.8; font-size: 10.5px; }
    .sp-emp-box .sep { width: 10px; text-align: center; }

    /* BODY */
    .sp-body { display: grid; grid-template-columns: 1fr 1fr; }
    .sp-col { padding: 0; }
    .sp-col-left { border-right: 1.5px solid #e2e8f0; }

    /* SECTION HEADER */
    .sp-section-hd {
        font-size: 10px; font-weight: 900; text-transform: uppercase;
        letter-spacing: 0.6px; padding: 6px 14px; border-bottom: 1px solid #e2e8f0;
    }
    .sp-section-hd.earn { background: #ecfdf5; color: #065f46; }
    .sp-section-hd.deduct { background: #fefce8; color: #713f12; }
    .sp-section-hd.sub { background: #f8fafc; color: #334155; font-weight: 800; }

    /* ROW TABLE */
    .sp-tbl { width: 100%; border-collapse: collapse; font-size: 11px; }
    .sp-tbl tr { border-bottom: 1px solid #f1f5f9; }
    .sp-tbl td { padding: 5px 14px; color: #334155; }
    .sp-tbl .lbl { font-weight: 600; }
    .sp-tbl .val { text-align: right; font-weight: 800; color: #0f172a; white-space: nowrap; }
    .sp-tbl .note { font-size: 10px; color: #94a3b8; font-weight: 500; }
    .sp-tbl .subtotal td { background: #f8fafc; font-weight: 800; color: #1e293b; border-top: 1px solid #e2e8f0; }
    .sp-tbl .zero { color: #cbd5e1 !important; }

    /* BADGE */
    .badge-period {
        display: inline-block; font-size: 9.5px; font-weight: 800;
        border-radius: 4px; padding: 1px 7px; margin-left: 6px;
        vertical-align: middle;
    }
    .badge-p1 { background: #fef3c7; color: #78350f; border: 1px solid #fcd34d; }
    .badge-p2 { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
    .badge-satuan { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    /* TARIF INFO BOX */
    .sp-tarif-box {
        margin: 10px 14px;
        background: {{ $accentLight }};
        border: 1px solid {{ $accentBorder }};
        border-radius: 8px;
        padding: 8px 12px;
        display: grid;
        grid-template-columns: repeat({{ ($utRate > 0) ? 4 : 3 }}, 1fr);
        gap: 8px;
    }
    .sp-tarif-item { text-align: center; }
    .sp-tarif-item .t-label { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }
    .sp-tarif-item .t-val { font-size: 11px; font-weight: 800; color: #0f172a; margin-top: 2px; }

    /* FOOTER TOTALS */
    .sp-totals {
        display: grid; grid-template-columns: 1fr 1fr;
        border-top: 1.5px solid #e2e8f0;
    }
    .sp-total-earn { background: #ecfdf5; padding: 10px 18px; border-right: 1px solid #a7f3d0; }
    .sp-total-deduct { background: #fefce8; padding: 10px 18px; }
    .sp-total-earn .t-title, .sp-total-deduct .t-title { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #475569; }
    .sp-total-earn .t-amount { font-size: 15px; font-weight: 900; color: #059669; margin-top: 2px; }
    .sp-total-deduct .t-amount { font-size: 15px; font-weight: 900; color: #d97706; margin-top: 2px; }

    .sp-thp {
        background: {{ $accentColor }};
        padding: 14px 24px;
        display: flex; justify-content: space-between; align-items: center;
        gap: 16px;
        box-sizing: border-box;
    }
    .sp-thp .thp-label { font-size: 13px; font-weight: 900; color: #fff; letter-spacing: 0.5px; text-transform: uppercase; }
    .sp-thp .thp-amt { font-size: 22px; font-weight: 900; color: #fff; white-space: nowrap; flex-shrink: 0; }
    .sp-thp .thp-sub { font-size: 11px; color: rgba(255,255,255,0.75); margin-top: 2px; }

    /* LATE TABLE */
    .late-tbl { width: 100%; border-collapse: collapse; font-size: 10.5px; }
    .late-tbl th { background: #f8fafc; padding: 4px 8px; font-size: 9.5px; font-weight: 800; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
    .late-tbl td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .late-tbl .val { text-align: right; font-weight: 700; }

    /* SIGNATURES */
    .sp-sig { display: grid; grid-template-columns: 1fr 1fr; text-align: center; padding: 20px 28px 16px; font-size: 11px; font-weight: 700; color: #475569; gap: 20px; border-top: 1px solid #f1f5f9; }
    .sp-sig .sig-line { border-top: 1.5px solid #475569; margin-top: 48px; padding-top: 4px; display: inline-block; min-width: 180px; }

    @media print {
        .no-print { display: none !important; }
        .sp-wrap { padding: 0; background: transparent; }
        .sp-card { box-shadow: none; border-radius: 0; max-width: 100%; }
        .sp-header, .sp-thp { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .sp-section-hd.earn, .sp-section-hd.deduct, .sp-section-hd.sub { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .sp-tarif-box, .sp-total-earn, .sp-total-deduct { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .sp-tbl .subtotal td { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="sp-wrap">

    {{-- ACTION BAR --}}
    <div class="no-print" style="max-width: 900px; margin: 0 auto 16px auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('penggajian.show-periode', ['periode' => $payroll->periode_bulan_tahun, 'outlet' => $currentOutlet]) }}"
               style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #fff; border: 1.5px solid #cbd5e1; color: #334155; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
               onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'"
               title="Kembali ke daftar Hitung Gaji Pokok Periode {{ $periodeLabel }}">
                ← Kembali ke Hitung Gaji
            </a>
            <a href="{{ route('penggajian.index', ['outlet' => $currentOutlet]) }}"
               style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #fff; border: 1.5px solid #cbd5e1; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
               onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'"
               title="Daftar Semua Periode Penggajian">
                &#128197; Daftar Periode
            </a>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            @if($hasMultiple)
            <div style="display: inline-flex; background: #e2e8f0; border-radius: 8px; padding: 2px;">
                <a href="{{ route('penggajian.show', ['penggajian' => $payroll->id, 'periode' => 'all']) }}"
                   style="padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 800; text-decoration: none; {{ $isCombined ? 'background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'color:#475569;' }}">
                    Gabungan
                </a>
                @foreach($allEntries as $ent)
                    @php $pNum = $ent->pilihan_periode ?? 1; @endphp
                    <a href="{{ route('penggajian.show', ['penggajian' => $ent->id, 'periode' => $pNum]) }}"
                       style="padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 800; text-decoration: none; {{ (!$isCombined && $pilihanPeriode == $pNum) ? 'background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(0,0,0,0.1);' : 'color:#475569;' }}">
                        Periode {{ $pNum }}
                    </a>
                @endforeach
            </div>
            @endif
            @if($waUrl)
            <button type="button"
                    onclick="handleKirimWhatsApp('{{ $pdfUrl }}', '{{ $waUrl }}', '{{ addslashes($cleanEmployeeName) }}', '{{ $pdfFileName }}')"
                    style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #16a34a; color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(22,163,74,0.25);"
                    onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'"
                    title="Unduh file PDF dan buka WhatsApp ke {{ $k->whatsapp }}">
                &#128242; Kirim WhatsApp
            </button>
            @else
            <button type="button" disabled
                    style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #94a3b8; color: #fff; border: none; cursor: not-allowed; display: inline-flex; align-items: center; gap: 5px; opacity: 0.6;"
                    title="Nomor WhatsApp karyawan belum diisi">
                &#128242; Kirim WhatsApp
            </button>
            @endif
            <a href="{{ $pdfUrl }}"
               style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #dc2626; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(220,38,38,0.25);"
               onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
                &#128196; Save as PDF
            </a>
            <button onclick="window.print()"
                    style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: {{ $accentColor }}; color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.15);">
                &#128424; Cetak Slip
            </button>
        </div>
    </div>

    {{-- SLIP CARD --}}
    <div class="sp-card">

        {{-- ═══ HEADER ═══ --}}
        <div class="sp-header">
            <div class="sp-header-left">
                <div class="sp-brand">
                    @if($isKejingga)
                        <span style="font-size: 13px; font-weight: 700; vertical-align: super; color: #fdba74;">ke</span><span style="color: #fdba74; font-size: 22px;">JINGGA</span>
                    @else
                        GAHARU
                    @endif
                </div>
                <div class="sp-slip-label">Slip Gaji Karyawan {{ $isCombined ? '· GABUNGAN SEMUA PERIODE' : '· PERIODE ' . $pilihanPeriode }}</div>
                <div class="sp-periode">
                    Periode {{ $periodeLabel }}
                    @if($tglSlip) · {{ $tglSlip }} @endif
                </div>
            </div>
            <div class="sp-emp-box">
                <table>
                    <tr>
                        <td class="lbl">NAMA</td>
                        <td class="sep">:</td>
                        <td style="font-size: 13px; font-weight: 900;">{{ strtoupper($k->nama_karyawan ?? '-') }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">JABATAN</td>
                        <td class="sep">:</td>
                        <td>{{ $k->jabatan ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">DIVISI</td>
                        <td class="sep">:</td>
                        <td>{{ $k->departemen ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">OUTLET</td>
                        <td class="sep">:</td>
                        <td>{{ strtoupper($currentOutlet) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">NO. REK</td>
                        <td class="sep">:</td>
                        <td style="letter-spacing: 0.5px;">{{ $k->no_rekening ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- ═══ TARIF INFO BOX ═══ --}}
        @if($isCombined)
        <div style="margin: 10px 14px; background: {{ $accentLight }}; border: 1px solid {{ $accentBorder }}; border-radius: 8px; padding: 10px 14px;">
            <div style="font-size: 10.5px; font-weight: 800; color: #78350f; text-transform: uppercase; margin-bottom: 6px;">
                Rincian Periode Tergabung di Bulan Ini
            </div>
            <div style="display: grid; grid-template-columns: repeat({{ count($allEntries) }}, 1fr); gap: 10px;">
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
                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 11px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span style="font-weight: 800; color: #0f172a;">Periode {{ $pNum }}</span>
                            <span style="font-size: 9.5px; font-weight: 700; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; color: #475569;">{{ $sat }}</span>
                        </div>
                        <div style="color: #64748b; font-size: 10px;">
                            @if($ent->tanggal_mulai && $ent->tanggal_selesai)
                                {{ \Carbon\Carbon::parse($ent->tanggal_mulai)->format('d/m') }} - {{ \Carbon\Carbon::parse($ent->tanggal_selesai)->format('d/m/Y') }}
                            @endif
                        </div>
                        <div style="margin-top: 4px; display: flex; justify-content: space-between; font-weight: 700;">
                            <span>Tarif: Rp {{ number_format($tar, 0, ',', '.') }}</span>
                            <span style="color: #0369a1;">{{ $ent->hari_kerja }}{{ $hkUnit }}</span>
                        </div>
                        <div style="margin-top: 2px; text-align: right; font-weight: 800; color: #059669;">
                            Rp {{ number_format($ent->gaji_utama, 0, ',', '.') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="sp-tarif-box">
            <div class="sp-tarif-item">
                <div class="t-label">
                    Tarif Periode
                    <span class="badge-period {{ $pilihanPeriode == 2 ? 'badge-p2' : 'badge-p1' }}">P{{ $pilihanPeriode }}</span>
                </div>
                <div class="t-val">
                    <span class="badge-satuan" style="font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 4px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">{{ $satuan }}</span>
                </div>
            </div>
            <div class="sp-tarif-item">
                <div class="t-label">Gaji Pokok/{{ $satuan == 'Per Jam' ? 'Jam' : 'Hari' }}</div>
                <div class="t-val">Rp {{ number_format($gpRate, 0, ',', '.') }}</div>
            </div>
            <div class="sp-tarif-item">
                <div class="t-label">U. Makan/{{ $satuan == 'Per Jam' ? 'Jam' : 'Hari' }}</div>
                <div class="t-val">Rp {{ number_format($umRate, 0, ',', '.') }}</div>
            </div>
            @if($utRate > 0)
            <div class="sp-tarif-item">
                <div class="t-label">Transport/{{ $satuan == 'Per Jam' ? 'Jam' : 'Hari' }}</div>
                <div class="t-val">Rp {{ number_format($utRate, 0, ',', '.') }}</div>
            </div>
            @endif
        </div>
        @endif

        {{-- ═══ BODY: 2 COLUMNS ═══ --}}
        <div class="sp-body">

            {{-- LEFT: PENDAPATAN --}}
            <div class="sp-col sp-col-left">
                <div class="sp-section-hd earn">&#9650; Pendapatan</div>

                {{-- Gaji Pokok --}}
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">GAJI POKOK</div>
                <table class="sp-tbl">
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
                                    <span class="note" style="display:block;">{{ $ent->hari_kerja }}{{ $hkUnit }} kerja</span>
                                </td>
                                <td class="val">Rp {{ number_format($ent->gaji_utama, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @else
                        @if($satuan == 'Harian')
                        <tr>
                            <td class="lbl">Tarif / Hari</td>
                            <td class="val">Rp {{ number_format($tarifTotal, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Hari Kerja</td>
                            <td class="val">{{ $payroll->hari_kerja }} hari</td>
                        </tr>
                        @elseif($satuan == 'Bulanan')
                        <tr>
                            <td class="lbl">Tarif Bulanan</td>
                            <td class="val">Rp {{ number_format($tarifTotal, 0, ',', '.') }}</td>
                        </tr>
                        @elseif($satuan == 'Per Jam')
                        <tr>
                            <td class="lbl">Tarif / Jam</td>
                            <td class="val">Rp {{ number_format($tarifTotal, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Jam Kerja</td>
                            <td class="val">{{ $payroll->hari_kerja }} jam</td>
                        </tr>
                        @endif
                    @endif
                    <tr class="subtotal">
                        <td>Total Gaji Pokok</td>
                        <td class="val" style="color: #059669;">Rp {{ number_format($gajiUtama, 0, ',', '.') }}</td>
                    </tr>
                </table>

                {{-- Lembur --}}
                @if(($payroll->lembur ?? 0) > 0 || ($payroll->jam_lembur ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">LEMBUR</div>
                <table class="sp-tbl">
                    <tr>
                        <td class="lbl">Jam Lembur</td>
                        <td class="val">{{ $payroll->jam_lembur ?? 0 }} jam</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Total Lembur</td>
                        <td class="val" style="color: #059669;">Rp {{ number_format($payroll->lembur ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Bonus Target --}}
                @if(($payroll->bonus_target ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">BONUS TARGET PENJUALAN</div>
                <table class="sp-tbl">
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
                        <td class="val" style="color: #059669;">Rp {{ number_format($payroll->bonus_target, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Bonus Tanggal Merah --}}
                @if(($payroll->bonus_tanggal_merah ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">BONUS HARI MERAH</div>
                <table class="sp-tbl">
                    <tr>
                        <td class="lbl">Banyak Hari Merah</td>
                        <td class="val">{{ $payroll->banyak_tanggal_merah }} hari</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Total Bonus Hari Merah</td>
                        <td class="val" style="color: #059669;">Rp {{ number_format($payroll->bonus_tanggal_merah, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Birthday Service --}}
                @if(($payroll->bonus_birthday ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">BIRTHDAY SERVICE</div>
                <table class="sp-tbl">
                    <tr>
                        <td class="lbl">Banyak Service</td>
                        <td class="val">{{ $payroll->banyak_birthday_service }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Total Bonus Birthday</td>
                        <td class="val" style="color: #059669;">Rp {{ number_format($payroll->bonus_birthday, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Pengembalian Deposit --}}
                @if(($payroll->pengembalian_deposit ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px; color: #047857; background: #f0fdf4;">PENGEMBALIAN DEPOSIT</div>
                <table class="sp-tbl">
                    <tr class="subtotal">
                        <td>Pengembalian Deposit</td>
                        <td class="val" style="color: #059669;">Rp {{ number_format($payroll->pengembalian_deposit, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Bonus Lain --}}
                @if(($payroll->bonus_dll ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">BONUS LAIN-LAIN</div>
                <table class="sp-tbl">
                    <tr class="subtotal">
                        <td>Total Bonus Lain</td>
                        <td class="val" style="color: #059669;">Rp {{ number_format($payroll->bonus_dll, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif
            </div>

            {{-- RIGHT: POTONGAN --}}
            <div class="sp-col">
                <div class="sp-section-hd deduct">&#9660; Potongan</div>

                {{-- Keterlambatan --}}
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">KETERLAMBATAN</div>
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
                <div style="padding: 8px 14px; font-size: 10.5px; color: #94a3b8; font-style: italic;">Tidak ada catatan keterlambatan</div>
                @endif
                <table class="sp-tbl">
                    <tr class="subtotal">
                        <td>Total Potongan Terlambat</td>
                        <td class="val {{ ($payroll->potongan_terlambat ?? 0) == 0 ? 'zero' : '' }}" style="{{ ($payroll->potongan_terlambat ?? 0) > 0 ? 'color:#dc2626;' : '' }}">
                            {{ ($payroll->potongan_terlambat ?? 0) > 0 ? 'Rp ' . number_format($payroll->potongan_terlambat, 0, ',', '.') : '-' }}
                        </td>
                    </tr>
                </table>

                {{-- Inventaris --}}
                @if(($payroll->potongan_inventaris ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">KERUSAKAN INVENTARIS</div>
                <table class="sp-tbl">
                    <tr class="subtotal">
                        <td>Total Potongan Inventaris</td>
                        <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_inventaris, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Kasbon --}}
                @if(($payroll->potongan_kasbon ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">KASBON</div>
                <table class="sp-tbl">
                    <tr class="subtotal">
                        <td>Total Kasbon</td>
                        <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_kasbon, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Pengurangan Deposit --}}
                @if(($payroll->potongan_deposit ?? 0) > 0)
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px; color: #991b1b; background: #fff5f5;">PENGURANGAN DEPOSIT</div>
                <table class="sp-tbl">
                    <tr class="subtotal">
                        <td>Pengurangan Deposit (Karyawan Baru)</td>
                        <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_deposit, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif

                {{-- Potongan Lain --}}
                @if(($payroll->potongan_dll ?? 0) > 0 || !empty($payroll->catatan_potongan_dll))
                <div class="sp-section-hd sub" style="font-size: 9.5px; font-weight: 700; padding: 4px 14px;">POTONGAN LAIN-LAIN</div>
                <table class="sp-tbl">
                    @if(!empty($payroll->catatan_potongan_dll))
                    <tr>
                        <td class="lbl">Keterangan</td>
                        <td class="val" style="font-weight: 600; color: #475569; font-size: 10.5px;">{{ $payroll->catatan_potongan_dll }}</td>
                    </tr>
                    @endif
                    <tr class="subtotal">
                        <td>Total Potongan Lain</td>
                        <td class="val" style="color:#dc2626;">Rp {{ number_format($payroll->potongan_dll ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
                @endif
            </div>
        </div>

        {{-- ═══ TOTALS ROW ═══ --}}
        <div class="sp-totals">
            <div class="sp-total-earn">
                <div class="t-title">&#9650; Total Pendapatan</div>
                <div class="t-amount">Rp {{ number_format($calcEarnings, 0, ',', '.') }}</div>
            </div>
            <div class="sp-total-deduct">
                <div class="t-title">&#9660; Total Potongan</div>
                <div class="t-amount">Rp {{ number_format($calcDeductions, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- ═══ TAKE HOME PAY ═══ --}}
        <div class="sp-thp">
            <div>
                <div class="thp-label">&#127968; Gaji Bersih Diterima</div>
                <div class="thp-sub">{{ strtoupper($k->nama_karyawan ?? '') }} · Periode {{ $periodeLabel }}</div>
            </div>
            <div class="thp-amt">Rp {{ number_format($takeHomePay, 0, ',', '.') }}</div>
        </div>

        {{-- ═══ SIGNATURES ═══ --}}
        <div class="sp-sig">
            <div>
                <div>Penerima Gaji,</div>
                <div class="sig-line">( {{ strtoupper($k->nama_karyawan ?? 'Karyawan') }} )</div>
            </div>
            <div>
                <div>Semarang, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</div>
                <div style="margin-top: 4px;">Manager / HRD,</div>
                <div class="sig-line">( _____________________ )</div>
            </div>
        </div>

    </div>{{-- end .sp-card --}}
</div>{{-- end .sp-wrap --}}

<script>
    function handleKirimWhatsApp(pdfUrl, waUrl, empName, fileName) {
        // 1. Buka tab WhatsApp Web LANGSUNG seketika saat klik agar tidak diblokir popup blocker
        if (waUrl) {
            window.open(waUrl, '_blank');
        }

        // 2. Download file PDF secara otomatis
        const a = document.createElement('a');
        a.href = pdfUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        // 3. Tampilkan popup panduan praktis
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Slip PDF Terunduh & WhatsApp Dibuka!',
                html: '<div style="text-align: left; font-size: 13px; color: #334155; line-height: 1.6;">' +
                      '<p style="margin-bottom: 8px;">Dokumen <strong>' + fileName + '</strong> otomatis terunduh dan tab WhatsApp karyawan sudah terbuka.</p>' +
                      '<div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;">' +
                      '<strong>Langkah Praktis:</strong><br>' +
                      '1. Masuk ke tab <strong>WhatsApp Web</strong> yang terbuka.<br>' +
                      '2. Cukup <strong>tarik (drag & drop)</strong> file PDF dari bilah download ke kolom chat.<br>' +
                      '3. Atau klik ikon <strong>Klip Kertas 📎 &rarr; Dokumen</strong>, lalu tekan <strong>Kirim</strong>.' +
                      '</div>' +
                      '</div>',
                confirmButtonText: 'Siap, Mengerti',
                confirmButtonColor: '#16a34a',
                width: 480
            });
        }
    }
</script>
</x-app-layout>