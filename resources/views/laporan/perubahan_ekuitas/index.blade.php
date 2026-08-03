<x-app-layout>
    <style>
        .container-laporan {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
            border: 1px solid #eef0f3;
        }

        /* ===== Filter Card ===== */
        .filter-title {
            margin: 0 0 18px 0;
            font-weight: 700;
            font-size: 16px;
            color: #111827;
        }

        .filter-group {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 6px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .field-input {
            padding: 9px 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .field-input:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.12);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            transition: filter 0.15s ease, transform 0.05s ease;
            white-space: nowrap;
        }
        .btn:hover { filter: brightness(0.92); }
        .btn:active { transform: translateY(1px); }

        .btn-primary { background: #1a56db; }
        .btn-secondary { background: #6b7280; }

        /* ===== Report Header ===== */
        .report-header {
            text-align: center;
            margin-bottom: 28px;
            border-bottom: 2px solid #f1f2f4;
            padding-bottom: 20px;
        }
        .report-header h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 0.01em;
            color: #111827;
        }
        .report-header h2 {
            margin: 6px 0 0 0;
            font-size: 15px;
            font-weight: 600;
            color: #6b7280;
        }
        .report-header p {
            margin: 6px 0 0 0;
            font-size: 13px;
            color: #9ca3af;
        }

        /* ===== Table Layout ===== */
        .laporan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .laporan-table td, .laporan-table th {
            padding: 14px 16px;
            font-size: 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .laporan-table th.kategori-header {
            font-size: 15px;
            font-weight: 700;
            text-align: left;
            background-color: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
            padding-top: 16px;
            padding-bottom: 8px;
            color: #1a56db;
        }

        .laporan-table tr.row-subtotal td {
            font-weight: 700;
            border-top: 1.5px solid #cbd5e1;
            border-bottom: 1.5px solid #cbd5e1;
            background-color: #f8fafc;
        }

        .laporan-table tr.row-grandtotal td {
            font-weight: 800;
            border-top: 2px solid #94a3b8;
            border-bottom: 2px double #94a3b8;
            background-color: #1e293b;
            color: white;
            font-size: 15px;
        }

        .indent-item {
            padding-left: 28px !important;
            color: #475569;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-success {
            color: #0f766e !important;
        }

        .text-danger {
            color: #b91c1c !important;
        }

        /* ===== Preset Links ===== */
        .preset-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #f1f2f4;
        }

        .preset-label {
            font-size: 12px;
            color: #9ca3af;
            margin-right: 4px;
        }

        .preset-badge {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            color: #475569;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .preset-badge:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        .preset-badge.active {
            background: #1e293b;
            border-color: #1e293b;
            color: white;
        }

        .btn-print {
            background: #4b5563;
        }

        @media print {
            .no-print { display: none !important; }
            .card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
        }
    </style>

    <div class="py-12">
        <div class="container-laporan">

            <!-- Card Filter -->
            <div class="card no-print">
                <h3 class="filter-title">Filter Laporan Perubahan Ekuitas</h3>
                <form method="GET" action="{{ url()->current() }}" class="filter-group">
                    <div>
                        <label for="start_date" class="field-label">Tanggal Mulai</label>
                        <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" max="{{ $endDate }}" class="field-input">
                    </div>
                    <div>
                        <label for="end_date" class="field-label">Tanggal Selesai</label>
                        <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" min="{{ $startDate }}" class="field-input">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        Tampilkan
                    </button>
                    <a href="{{ url()->current() }}" class="btn btn-secondary">
                        Reset
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-print">
                        🖨️ Cetak Laporan
                    </button>
                </form>

                <div class="preset-container">
                    <span class="preset-label">Pilihan cepat:</span>
                    @php
                        $presets = [
                            'Bulan Ini' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                            'Bulan Lalu' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                            'Tahun Ini' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
                            'Tahun Lalu' => [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
                        ];
                        $isActivePreset = fn($range) => $range[0] === $startDate && $range[1] === $endDate;
                    @endphp
                    @foreach ($presets as $label => $range)
                        <a href="{{ url()->current() }}?start_date={{ $range[0] }}&end_date={{ $range[1] }}"
                           class="preset-badge {{ $isActivePreset($range) ? 'active' : '' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Card Laporan Perubahan Ekuitas -->
            <div class="card">
                <div class="report-header">
                    <h1>CV GAHARU AGUNG SEJAHTERA</h1>
                    <h2>Laporan Perubahan Ekuitas</h2>
                    <p>Periode: {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}</p>
                </div>

                @if ($modalAwal == 0 && $penambahanModal == 0 && $labaRugiBersih == 0 && $prive == 0)
                    <div class="text-center py-5" style="color: #6b7280;">
                        <p>Belum ada data untuk periode ini.</p>
                        <p style="font-size: 12px; margin-top: 4px;">Coba pilih rentang tanggal lain, atau pastikan transaksi pada periode ini sudah dicatat.</p>
                    </div>
                @else
                    <table class="laporan-table">
                        <thead>
                            <tr>
                                <th colspan="2" class="kategori-header">Alur Perubahan Ekuitas (Modal)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Modal Awal Periode</strong></td>
                                <td class="text-right"><strong>Rp {{ number_format($modalAwal, 0, ',', '.') }}</strong></td>
                            </tr>
                            <tr>
                                <td class="indent-item">Setoran Modal / Penambahan Modal</td>
                                <td class="text-right text-success">+ Rp {{ number_format($penambahanModal, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="indent-item">
                                    {{ $labaRugiBersih >= 0 ? 'Laba Bersih Periode Berjalan' : 'Rugi Bersih Periode Berjalan' }}
                                </td>
                                <td class="text-right {{ $labaRugiBersih >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $labaRugiBersih >= 0 ? '+' : '-' }} Rp {{ number_format(abs($labaRugiBersih), 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="indent-item">Prive / Pengambilan Pribadi Pemilik</td>
                                <td class="text-right text-danger">- Rp {{ number_format($prive, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="row-subtotal">
                                <td>Total Perubahan Ekuitas Neto</td>
                                <td class="text-right {{ $perubahanEkuitas >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $perubahanEkuitas >= 0 ? '+' : '-' }} Rp {{ number_format(abs($perubahanEkuitas), 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr class="row-grandtotal">
                                <td>MODAL AKHIR PERIODE</td>
                                <td class="text-right">Rp {{ number_format($modalAkhir, 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="card no-print" style="margin-top: 20px; padding: 20px;">
                <h4 style="margin: 0 0 10px 0; font-weight: 700; font-size: 14px; color: #374151;">Informasi Perhitungan Laporan Perubahan Ekuitas</h4>
                <ul style="font-size: 13px; color: #4b5563; line-height: 1.6; padding-left: 20px; margin: 0;">
                    <li><strong>Modal Awal</strong> &mdash; Saldo kumulatif akun Modal Disetor (3101) dan Laba Ditahan (3103) sebelum tanggal mulai.</li>
                    <li><strong>Laba/Rugi Bersih</strong> &mdash; Total Pendapatan (Kepala 4) dikurangi total HPP dan Beban (Kepala 5 &amp; 6) selama periode berjalan.</li>
                    <li><strong>Prive</strong> &mdash; Pengambilan dana oleh pemilik untuk kepentingan pribadi (Akun 3102).</li>
                    <li><strong>Modal Akhir</strong> &mdash; Hasil kalkulasi modal awal ditambah total perubahan ekuitas neto.</li>
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>