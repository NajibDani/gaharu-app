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
        .subkategori-header {
            background: #f1f5f9;
            padding: 8px 14px;
            font-weight: 700;
            font-size: 12px;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .laporan-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f2f4;
        }
        .indent-item {
            padding-left: 28px !important;
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
        .text-center {
            text-align: center;
        }
        .empty-row {
            color: #9ca3af;
            font-style: italic;
            font-size: 13px;
            padding: 12px 14px;
        }
        .row-subtotal td {
            background: #f8fafc;
            font-weight: 600;
            font-size: 13px;
            border-top: 1px dashed #cbd5e1;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
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

        /* ===== Row Laba Kotor ===== */
        .row-laba-kotor td {
            background: #ebf8ff;
            font-weight: 800;
            font-size: 15px;
            color: #2b6cb0;
            border-top: 2px solid #3182ce;
            border-bottom: 2px solid #3182ce;
            padding: 12px 14px;
        }

        /* ===== Button Formula & Modal ===== */
        .btn-b2b-detail {
            font-size: 11px;
            background-color: #ebf8ff;
            color: #2b6cb0;
            border: 1px solid #bee3f8;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 8px;
            font-weight: 600;
        }
        .btn-b2b-detail:hover {
            background-color: #bee3f8;
        }

        /* ===== Modal Styling ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #ffffff;
            width: 90%;
            max-width: 750px;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }
        .modal-close-btn {
            border: none;
            background: transparent;
            font-size: 22px;
            color: #9ca3af;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close-btn:hover {
            color: #374151;
        }
        .table-modal {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .table-modal th {
            background-color: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 8px 12px;
            border-bottom: 1px solid #cbd5e1;
            font-weight: 600;
        }
        .table-modal td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
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

            <!-- Card Filter -->
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

            <!-- Card Laporan Utama -->
            <div class="card">
                <div class="report-header">
                    <h1>CV GAHARU AGUNG SEJAHTERA</h1>
                    <h2>Laporan Laba Rugi</h2>
                    <p>Periode: {{ date('F', mktime(0, 0, 0, $bulan, 1)) }} {{ $tahun }}</p>
                </div>

                <table class="laporan-table">
                    <!-- 1. PENDAPATAN -->
                    <tr>
                        <th colspan="2" class="kategori-header" style="color: #2b6cb0;">Pendapatan</th>
                    </tr>

                    <!-- 1a. Penjualan B2B -->
                    <tr>
                        <td colspan="2" class="subkategori-header">Penjualan B2B</td>
                    </tr>
                    @forelse($detailsPenjualanB2b as $item)
                    <tr>
                        <td class="indent-item">{{ $item->kode_akun }} &ndash; {{ $item->nama_akun }}</td>
                        <td class="text-right">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center empty-row indent-item">Tidak ada transaksi penjualan B2B pada periode ini.</td>
                    </tr>
                    @endforelse
                    <tr class="row-subtotal">
                        <td class="indent-item">
                            Subtotal Penjualan B2B
                            <button type="button" class="btn-b2b-detail no-print" onclick="openModalB2b()">
                                🔍 Lihat Detail Invoice
                            </button>
                        </td>
                        <td class="text-right">Rp {{ number_format($totalPenjualanB2b ?? 0, 0, ',', '.') }}</td>
                    </tr>

                    <!-- 1b. Pendapatan Lainnya -->
                    <tr>
                        <td colspan="2" class="subkategori-header">Pendapatan Lainnya</td>
                    </tr>
                    @forelse($detailsPendapatanLain as $item)
                    <tr>
                        <td class="indent-item">{{ $item->kode }} &ndash; {{ $item->nama }}</td>
                        <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center empty-row indent-item">Tidak ada transaksi pendapatan lainnya pada periode ini.</td>
                    </tr>
                    @endforelse
                    <tr class="row-subtotal">
                        <td class="indent-item">Subtotal Pendapatan Lainnya</td>
                        <td class="text-right">Rp {{ number_format($totalPendapatanLain ?? 0, 0, ',', '.') }}</td>
                    </tr>

                    <!-- Total Pendapatan Overall -->
                    <tr class="row-total">
                        <td style="color: #2b6cb0;">Total Pendapatan</td>
                        <td class="text-right" style="color: #2b6cb0;">Rp {{ number_format($totalPendapatan ?? 0, 0, ',', '.') }}</td>
                    </tr>

                    <tr class="row-spacer">
                        <td colspan="2"></td>
                    </tr>

                    <!-- 2. HARGA POKOK PENJUALAN (HPP) -->
                    <tr>
                        <th colspan="2" class="kategori-header" style="color: #c53030;">Harga Pokok Penjualan</th>
                    </tr>
                    @forelse($detailsHpp as $item)
                    <tr>
                        <td>{{ $item->kode }} &ndash; {{ $item->nama }}</td>
                        <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center empty-row">Tidak ada transaksi HPP pada periode ini.</td>
                    </tr>
                    @endforelse
                    <tr class="row-total">
                        <td style="color: #c53030;">
                            Total Harga Pokok Penjualan
                        </td>
                        <td class="text-right" style="color: #c53030;">(Rp {{ number_format($totalHpp ?? 0, 0, ',', '.') }})</td>
                    </tr>
                </table>

                <table class="laporan-table" style="margin-top: 10px;">
                    <!-- 3. LABA KOTOR -->
                    @php
                        $labaKotorCalc = $labaKotor ?? (($totalPendapatan ?? 0) - ($totalHpp ?? 0));
                    @endphp
                    <tr class="row-laba-kotor">
                        <td>LABA KOTOR</td>
                        <td class="text-right">Rp {{ number_format($labaKotorCalc, 0, ',', '.') }}</td>
                    </tr>

                    <tr class="row-spacer">
                        <td colspan="2"></td>
                    </tr>

                    <!-- 4. BEBAN OPERASIONAL -->
                    <tr>
                        <th colspan="2" class="kategori-header" style="color: #d69e2e;">Beban Operasional</th>
                    </tr>
                    @forelse($detailsBeban as $item)
                    <tr>
                        <td>{{ $item->kode }} &ndash; {{ $item->nama }}</td>
                        <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center empty-row">Tidak ada transaksi beban operasional pada periode ini.</td>
                    </tr>
                    @endforelse
                    <tr class="row-total">
                        <td style="color: #d69e2e;">Total Beban Operasional</td>
                        <td class="text-right" style="color: #d69e2e;">(Rp {{ number_format($totalBeban ?? 0, 0, ',', '.') }})</td>
                    </tr>
                </table>

                <!-- 5. LABA / RUGI BERSIH -->
                @php
                    $labaBersihCalc = $labaBersih ?? ($labaKotorCalc - ($totalBeban ?? 0));
                    $isLaba = $labaBersihCalc >= 0;
                @endphp
                <div class="footer-laba {{ $isLaba ? 'is-laba' : 'is-rugi' }}">
                    <span class="label">
                        {{ $isLaba ? 'Laba Bersih' : 'Rugi Bersih' }}
                        <span class="badge">{{ $isLaba ? 'SURPLUS' : 'DEFISIT' }}</span>
                    </span>
                    <span class="amount">
                        <span class="arrow">{{ $isLaba ? '▲' : '▼' }}</span>
                        Rp {{ number_format(abs($labaBersihCalc), 0, ',', '.') }}
                    </span>
                </div>
            </div>

        </div>
    </div>

    <!-- ===== MODAL POP-UP RINCIAN INVOICE B2B ===== -->
    <div id="modal-b2b" class="modal-overlay no-print" onclick="closeModalB2bOnOverlay(event)">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Rincian Transaksi Penjualan B2B</h3>
                <button type="button" class="modal-close-btn" onclick="closeModalB2b()">&times;</button>
            </div>
            
            <table class="table-modal">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal</th>
                        <th>Pelanggan / Customer</th>
                        <th class="text-right">Total Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rincianInvoiceB2b ?? [] as $inv)
                    <tr>
                        <td><strong>{{ $inv->no_faktur ?? $inv->nomor_transaksi ?? '-' }}</strong></td>
                        <td>{{ isset($inv->tanggal) ? \Carbon\Carbon::parse($inv->tanggal)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $inv->customer->nama ?? $inv->nama_pelanggan ?? 'Pelanggan B2B' }}</td>
                        <td class="text-right">Rp {{ number_format($inv->grand_total ?? $inv->total_nominal ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center empty-row">Tidak ada rincian invoice B2B pada periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function openModalB2b() {
            document.getElementById('modal-b2b').style.display = 'flex';
        }

        function closeModalB2b() {
            document.getElementById('modal-b2b').style.display = 'none';
        }

        function closeModalB2bOnOverlay(event) {
            if (event.target.id === 'modal-b2b') {
                closeModalB2b();
            }
        }
    </script>
</x-app-layout>