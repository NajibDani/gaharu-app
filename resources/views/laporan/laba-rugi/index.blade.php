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
        .btn-excel   { background: #198754; }
        .btn-pdf     { background: #dc3545; }
        .btn-print   { background: #374151; }

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
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .report-header p {
            margin: 10px 0 0 0;
            color: #9ca3af;
            font-size: 13px;
        }

        /* ===== Table ===== */
        .laporan-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .kategori-header {
            background: #f8f9fb;
            padding: 12px 14px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            border-top: 1px solid #e5e7eb;
            text-align: left;
        }
        .laporan-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f2f4;
        }
        .laporan-table tbody tr:hover td {
            background: #fafbfc;
        }
        .text-right {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            color: #1f2937;
        }
        .row-total td {
            background: #f8f9fb;
            font-weight: 700;
            border-top: 1px solid #e5e7eb;
            border-bottom: 2px solid #e5e7eb;
        }
        .row-spacer td {
            border: none;
            padding: 10px 0;
        }

        /* ===== Footer Laba/Rugi ===== */
        .footer-laba {
            margin-top: 26px;
            padding: 22px 26px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 10px;
        }
        .footer-laba.is-laba { background: #065f46; }
        .footer-laba.is-rugi { background: #b91c1c; }
        .footer-laba .label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            opacity: 0.95;
        }
        .footer-laba .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            letter-spacing: 0.04em;
        }
        .footer-laba .amount {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 22px;
            font-variant-numeric: tabular-nums;
        }
        .footer-laba .arrow {
            font-size: 20px;
            line-height: 1;
        }

        @media (max-width: 640px) {
            .filter-group { flex-direction: column; align-items: stretch; }
            .footer-laba { flex-direction: column; align-items: flex-start; gap: 6px; }
        }

        @media print {
            .no-print { display: none; }
            .card {
                box-shadow: none;
                border: none;
                padding: 0;
            }
        }
    </style>

    <div class="py-12">
        <div class="container-laporan">

            <div class="card no-print">
                <h3 class="filter-title">Filter Laporan Laba Rugi</h3>
                <form action="{{ route('laporan.laba-rugi.index') }}" method="GET" class="filter-group">
                    <div>
                        <label class="field-label">Bulan</label>
                        <select name="bulan" class="field-input" style="width: 160px;">
                            @foreach(range(1, 12) as $m)
                            <option value="{{ sprintf('%02d', $m) }}" {{ $bulan == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Tahun</label>
                        <input type="number" name="tahun" value="{{ $tahun }}" class="field-input" style="width: 110px;">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        Tampilkan
                    </button>
                    <a href="{{ route('laporan.laba-rugi.index', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-excel">
                        📊 Export Excel
                    </a>
                    <a href="{{ route('laporan.laba-rugi.index', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-pdf">
                        📕 Export PDF
                    </a>
                </form>
            </div>

            <div class="card">
                <div class="report-header">
                    <h1>CV GAHARU AGUNG SEJAHTERA</h1>
                    <h2>Laporan Laba Rugi</h2>
                    <p>Periode: {{ date('F', mktime(0, 0, 0, $bulan, 1)) }} {{ $tahun }}</p>
                </div>

                <table class="laporan-table">
                    <tr>
                        <th colspan="2" class="kategori-header">Pendapatan</th>
                    </tr>
                    @foreach($detailsPendapatan as $item)
                    <tr>
                        <td>{{ $item->kode }} &ndash; {{ $item->nama }}</td>
                        <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr class="row-total">
                        <td>Total Pendapatan</td>
                        <td class="text-right">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</td>
                    </tr>

                    <tr class="row-spacer">
                        <td colspan="2"></td>
                    </tr>

                    <tr>
                        <th colspan="2" class="kategori-header" style="color: #c53030;">Beban Operasional</th>
                    </tr>
                    @foreach($detailsBeban as $item)
                    <tr>
                        <td>{{ $item->kode }} &ndash; {{ $item->nama }}</td>
                        <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr class="row-total">
                        <td style="color: #c53030;">Total Beban</td>
                        <td class="text-right" style="color: #c53030;">(Rp {{ number_format($totalBeban, 0, ',', '.') }})</td>
                    </tr>
                </table>

                @php
                    $labaBersih = $totalPendapatan - $totalBeban;
                    $isLaba = $labaBersih >= 0;
                @endphp
                <div class="footer-laba {{ $isLaba ? 'is-laba' : 'is-rugi' }}">
                    <span class="label">
                        {{ $isLaba ? 'Laba Bersih' : 'Rugi Bersih' }}
                        <span class="badge">{{ $isLaba ? 'SURPLUS' : 'DEFISIT' }}</span>
                    </span>
                    <span class="amount">
                        <span class="arrow">{{ $isLaba ? '▲' : '▼' }}</span>
                        Rp {{ number_format(abs($labaBersih), 0, ',', '.') }}
                    </span>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>