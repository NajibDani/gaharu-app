<x-app-layout>
    <style>
        .slip-page-wrapper {
            background-color: #f1f5f9;
            padding: 30px 15px;
            min-height: 100vh;
        }

        @php
            $currentOutlet = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';
            $isKejingga = (strtolower($currentOutlet) === 'kejingga');
            $carbonPeriode = \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-01');
            $daysInMonth = $carbonPeriode->daysInMonth;
            $namaBulanTahun = $carbonPeriode->translatedFormat('F Y');

            // Dynamic color schemes
            $bgCard = $isKejingga ? '#134e4a' : '#c87a4b';
            $takeHomeBg = $isKejingga ? '#ea580c' : '#b8622f';
        @endphp

        .slip-container {
            max-width: 920px;
            margin: 0 auto;
            background-color: {{ $bgCard }};
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #ffffff;
        }

        /* HEADER */
        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 20px;
        }

        .slip-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .brand-logo-text {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #ffffff;
            margin: 0;
            line-height: 1.1;
        }

        .brand-logo-kejingga-ke {
            font-size: 15px;
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
            border: 2px solid #ffffff;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            text-align: center;
            line-height: 24px;
            font-size: 14px;
            margin-right: 8px;
        }

        .slip-title-meta {
            margin-top: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.3;
        }

        .slip-employee-box {
            border: 1.5px solid rgba(255, 255, 255, 0.9);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.12);
            min-width: 360px;
        }

        .emp-table-show {
            width: 100%;
            border-collapse: collapse;
            color: #ffffff;
            font-weight: bold;
            font-size: 12px;
        }

        .emp-table-show td {
            padding: 5px 8px;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        /* TABLES GRID */
        .slip-body-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            background: #ffffff;
            border: 1.5px solid #222222;
        }

        .slip-col-left {
            border-right: 1px solid #333333;
        }

        .slip-col-right {
        }

        .table-slip {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            color: #111;
        }

        .table-slip th, .table-slip td {
            border: 1px solid #333333;
            padding: 3.5px 6px;
        }

        .bg-header-pendapatan {
            background-color: #2e6945 !important;
            color: #ffffff !important;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.5px;
            padding: 5px !important;
        }

        .bg-header-pengurangan {
            background-color: #eab308 !important;
            color: #111827 !important;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.5px;
            padding: 5px !important;
        }

        .bg-section-title {
            background-color: #ffffff !important;
            font-weight: bold;
            font-size: 11px;
            color: #000;
        }

        .bg-subtotal-row {
            background-color: #fef3c7 !important;
            font-weight: bold;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* BOTTOM TOTALS */
        .slip-bottom-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1.5px solid #222222;
            border-top: none;
            background: #ffffff;
        }

        .total-pendapatan-box {
            background-color: #2e6945;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 11.5px;
            border-right: 1px solid #333;
        }

        .total-pengurangan-box {
            background-color: #eab308;
            color: #111827;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 11.5px;
        }

        .take-home-pay-bar {
            background-color: {{ $takeHomeBg }};
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            font-weight: 900;
            font-size: 15px;
            border: 1.5px solid #222222;
            border-top: none;
            letter-spacing: 0.5px;
        }

        /* PRINT STYLES */
        @media print {
            .no-print {
                display: none !important;
            }
            .slip-page-wrapper {
                padding: 0;
                background: transparent;
            }
            .slip-container {
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                padding: 12px;
                border-radius: 0;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <div class="slip-page-wrapper">
        @php
            $currentOutlet = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';
            $isKejingga = (strtolower($currentOutlet) === 'kejingga');
            $carbonPeriode = \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-01');
            $daysInMonth = $carbonPeriode->daysInMonth;
            $namaBulanTahun = $carbonPeriode->translatedFormat('F Y');
        @endphp

        {{-- TOMBOL AKSI ATAS --}}
        <div class="no-print d-flex justify-content-between align-items-center mb-3" style="max-width: 960px; margin: 0 auto 15px auto;">
            <a href="{{ route('penggajian.show-periode', ['periode' => $payroll->periode_bulan_tahun, 'outlet' => $currentOutlet]) }}" class="btn btn-outline-secondary btn-sm px-3 rounded-3 shadow-sm bg-white">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Periode ({{ $currentOutlet }})
            </a>
            <div class="d-flex gap-2">
                @if($payroll->status !== 'approved')
                <a href="{{ route('penggajian.edit', $payroll->id) }}" class="btn btn-warning btn-sm px-3 rounded-3 shadow-sm text-dark font-medium">
                    <i class="bi bi-pencil-square me-1"></i> Edit Data
                </a>
                @endif
                <a href="{{ route('penggajian.pdf', $payroll->id) }}" class="btn btn-danger btn-sm px-3 rounded-3 shadow-sm text-white font-medium" style="background:#dc2626; border-color:#dc2626;">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
                </a>
                <button onclick="window.print()" class="btn btn-primary btn-sm px-4 rounded-3 shadow-sm" style="background:#5a3416; border-color:#5a3416;">
                    <i class="bi bi-printer me-1"></i> Cetak Slip Gaji
                </button>
            </div>
        </div>

        {{-- KARTU SLIP GAJI GAHARU --}}
        <div class="slip-container">
            {{-- HEADER --}}
            <div class="slip-header">
                <div class="slip-brand">
                    <div class="slip-title-meta" style="padding-left: 10px; text-align: left;">
                        <div style="font-size: 22px; font-weight: 800; letter-spacing: 1px;">SLIP GAJI</div>
                        <div style="font-size: 13px; margin-top: 4px;">
                            @if($payroll->tanggal_mulai && $payroll->tanggal_selesai)
                                PERIODE {{ \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d') }}-{{ \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d') }} {{ strtoupper($namaBulanTahun) }}
                            @else
                                PERIODE 1-{{ $daysInMonth }} {{ strtoupper($namaBulanTahun) }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="slip-employee-box">
                    <table class="emp-table-show">
                        <tr>
                            <td style="width: 100px;">NAMA</td>
                            <td style="width: 15px; text-align: center;">:</td>
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
            </div>

            {{-- BODY: 2 KOLOM (PENDAPATAN & PENGURANGAN) --}}
            <div class="slip-body-grid">
                {{-- KOLOM KIRI: PENDAPATAN --}}
                <div class="slip-col-left">
                    <table class="table-slip">
                        <thead>
                            <tr>
                                <th colspan="4" class="bg-header-pendapatan">PENDAPATAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- SEKSI GAJI --}}
                            <tr>
                                <td colspan="4" class="bg-section-title">
                                    GAJI
                                    @if($payroll->tanggal_mulai && $payroll->tanggal_selesai)
                                        <span class="badge bg-light text-dark float-end" style="font-size: 10px; font-weight: normal;">
                                            {{ \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td>Gaji Pokok</td>
                                <td class="text-center" style="width: 25px;">Rp</td>
                                <td class="text-end" style="width: 80px;">{{ number_format($payroll->gaji_pokok ?? 0, 0, ',', '.') }}</td>
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
                                        <small class="text-muted">({{ \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m') }} - {{ \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m') }})</small>
                                    @endif
                                </td>
                                <td colspan="2" class="text-center">{{ $payroll->hari_kerja }} hari</td>
                            </tr>
                            @php
                                $gajiUtamaVal = $payroll->gaji_utama > 0 ? $payroll->gaji_utama : ($payroll->hari_kerja * ($payroll->tarif_harian_total ?? ($payroll->gaji_pokok + $payroll->tunjangan_makan + $payroll->tunjangan_transport)));
                            @endphp
                            <tr class="bg-subtotal-row">
                                <td colspan="2" style="font-weight: 800;">TOTAL GAJI</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ number_format($gajiUtamaVal, 0, ',', '.') }}</td>
                            </tr>

                            {{-- SEKSI LEMBUR --}}
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
                                <td colspan="2" style="font-weight: 800;">TOTAL UPAH LEMBUR</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->lembur ?? 0) > 0 ? number_format($payroll->lembur, 0, ',', '.') : '-' }}</td>
                            </tr>

                            {{-- SEKSI BONUS TARGET PENJUALAN --}}
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
                                <td colspan="2" style="font-weight: 800;">TOTAL BONUS TARGET PENJUALAN</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->bonus_target ?? 0) > 0 ? number_format($payroll->bonus_target, 0, ',', '.') : '-' }}</td>
                            </tr>
                            @endif

                            {{-- SEKSI BONUS TANGGAL MERAH --}}
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
                                <td colspan="2" style="font-weight: 800;">TOTAL BONUS TANGGAL MERAH</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->bonus_tanggal_merah ?? 0) > 0 ? number_format($payroll->bonus_tanggal_merah, 0, ',', '.') : '-' }}</td>
                            </tr>

                            {{-- SEKSI BIRTHDAY SERVICE --}}
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
                                <td colspan="2" style="font-weight: 800;">TOTAL BONUS SERVICE</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->bonus_birthday ?? 0) > 0 ? number_format($payroll->bonus_birthday, 0, ',', '.') : '-' }}</td>
                            </tr>

                            {{-- BONUS / UPAH LAIN-LAIN --}}
                            <tr class="bg-subtotal-row">
                                <td colspan="2" class="bg-section-title" style="font-weight: 800;">BONUS / UPAH LAIN-LAIN</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->bonus_dll ?? 0) > 0 ? number_format($payroll->bonus_dll, 0, ',', '.') : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- KOLOM KANAN: PENGURANGAN --}}
                <div class="slip-col-right">
                    <table class="table-slip">
                        <thead>
                            <tr>
                                <th colspan="5" class="bg-header-pengurangan">PENGURANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- SEKSI KETERLAMBATAN --}}
                            <tr>
                                <td colspan="5" class="bg-section-title">KETERLAMBATAN</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: 700; font-size: 10px;">
                                <td class="text-center" style="width: 28%;">Tanggal</td>
                                <td class="text-center" style="width: 25%;">Shift</td>
                                <td class="text-center" style="width: 22%;">Jam datang</td>
                                <td class="text-center" colspan="2" style="width: 25%;">Potongan</td>
                            </tr>

                            {{-- Looping Catatan Keterlambatan --}}
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
                                <td colspan="5" class="text-center text-muted py-1" style="font-style: italic;">Tidak ada catatan keterlambatan</td>
                            </tr>
                            @endforelse

                            {{-- Tambah baris kosong jika catatan sedikit agar tabel simetris dan mirip spreadsheet --}}
                            @for($i = $countTerlambat; $i < ($countTerlambat == 0 ? 4 : 2); $i++)
                            <tr>
                                <td style="height: 18px;">&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                            @endfor

                            <tr class="bg-subtotal-row">
                                <td colspan="3" style="font-weight: 800;">TOTAL POTONGAN KETERLAMBATAN</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->potongan_terlambat ?? 0) > 0 ? number_format($payroll->potongan_terlambat, 0, ',', '.') : '-' }}</td>
                            </tr>

                            {{-- SEKSI KERUSAKAN INVENTARIS --}}
                            <tr>
                                <td colspan="5" class="bg-section-title">KERUSAKAN INVENTARIS</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: 700; font-size: 10px;">
                                <td colspan="3" class="text-center">Banyak Pecah</td>
                                <td colspan="2" class="text-center">Potongan per satuan</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-center">-</td>
                                <td class="text-center" style="width: 15px;">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_inventaris ?? 0) > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '0' }}</td>
                            </tr>
                            <tr class="bg-subtotal-row">
                                <td colspan="3" style="font-weight: 800;">TOTAL POTONGAN KERUSAKAN INVENTARIS</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->potongan_inventaris ?? 0) > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '0' }}</td>
                            </tr>

                            {{-- SEKSI LAIN-LAIN --}}
                            <tr>
                                <td colspan="5" class="bg-section-title">LAIN-LAIN</td>
                            </tr>
                            <tr style="background: #f8fafc; font-weight: 700; font-size: 10px;">
                                <td colspan="3" class="text-center">Keterangan</td>
                                <td colspan="2" class="text-center">Banyak Potongan</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-center">{{ ($payroll->potongan_dll ?? 0) > 0 ? 'Potongan Lain-lain' : '-' }}</td>
                                <td class="text-center" style="width: 15px;">Rp</td>
                                <td class="text-end">{{ ($payroll->potongan_dll ?? 0) > 0 ? number_format($payroll->potongan_dll, 0, ',', '.') : '-' }}</td>
                            </tr>

                            {{-- SEKSI KASBON --}}
                            <tr class="bg-subtotal-row">
                                <td colspan="3" style="font-weight: 800;">KASBON</td>
                                <td class="text-center">Rp</td>
                                <td class="text-end" style="font-weight: 800;">{{ ($payroll->potongan_kasbon ?? 0) > 0 ? number_format($payroll->potongan_kasbon, 0, ',', '.') : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- BAR TOTAL PENDAPATAN & TOTAL PENGURANGAN --}}
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
            <div class="slip-bottom-bar">
                <div class="total-pendapatan-box">
                    <span>TOTAL PENDAPATAN</span>
                    <span>Rp {{ number_format($calcEarnings, 0, ',', '.') }}</span>
                </div>
                <div class="total-pengurangan-box">
                    <span>TOTAL POTONGAN</span>
                    <span>Rp {{ number_format($calcDeductions, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- BAR TOTAL GAJI BERSIH (TAKE HOME PAY) --}}
            <div class="take-home-pay-bar">
                <span>TOTAL GAJI BERSIH</span>
                <span>Rp {{ number_format($payroll->total_gaji_bersih, 0, ',', '.') }}</span>
            </div>

            {{-- TANDA TANGAN (TAMBAHAN RESMI SAAT CETAK) --}}
            <div style="margin-top: 24px; display: grid; grid-template-columns: 1fr 1fr; text-align: center; font-size: 11px; color: #fff; font-weight: bold;">
                <div>
                    Penerima Gaji,<br><br><br><br>
                    <u>( {{ strtoupper($payroll->karyawan->nama_karyawan ?? 'Karyawan') }} )</u>
                </div>
                <div>
                    Semarang, {{ date('d') }} {{ $namaBulanTahun }}<br>
                    Manager / HRD,<br><br><br><br>
                    <u>( ________________________ )</u>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>