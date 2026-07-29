<x-app-layout>
    <style>
        :root {
            --ink: #0f172a;
            --ink-soft: #475569;
            --line: #e2e8f0;
            --surface: #f8fafc;
            --accent: #2563eb;
            --positive: #15803d;
            --negative: #b91c1c;
        }

        .container-laporan {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--ink);
        }

        .card {
            background: white;
            padding: 28px 32px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
        }

        /* ---------- Filter bar ---------- */
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: var(--ink-soft);
            margin-bottom: 4px;
        }

        .field select,
        .field input {
            padding: 9px 10px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            min-width: 140px;
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
            line-height: 1;
            color: white;
            transition: filter 0.15s ease;
        }
        .btn:hover { filter: brightness(0.92); }
        .btn-primary { background: var(--accent); }
        .btn-excel   { background: #198754; }
        .btn-pdf     { background: #dc3545; }
        .btn-print   { background: #475569; }

        /* ---------- Report header ---------- */
        .report-head {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--ink);
        }
        .report-head h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 0.02em;
        }
        .report-head h2 {
            margin: 6px 0 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .report-head p {
            margin: 8px 0 0;
            color: #94a3b8;
            font-size: 13px;
        }

        /* ---------- Sections ---------- */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface);
            padding: 10px 14px;
            font-weight: 700;
            border-left: 4px solid var(--ink);
            margin: 26px 0 8px;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.04em;
            color: var(--ink);
        }

        .table-arus {
            width: 100%;
            border-collapse: collapse;
        }
        .table-arus td {
            padding: 9px 6px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .table-arus tr:last-child td { border-bottom: none; }

        .text-right { text-align: right; }

        .font-mono {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .row-subtotal td {
            background: var(--surface);
            font-weight: 700;
            padding: 11px 6px;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .empty-row td {
            color: #94a3b8;
            font-style: italic;
            font-size: 13px;
            padding: 10px 6px;
        }

        .val-positive { color: var(--positive); }
        .val-negative { color: var(--negative); }
        .pl-4 { padding-left: 20px; }
        .fw-bold { font-weight: 700; }

        /* ---------- Net box ---------- */
        .net-box {
            margin-top: 28px;
            padding: 20px 24px;
            color: white;
            background: var(--ink);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px;
            font-size: 17px;
        }
        .net-box span:first-child {
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .saldo-box {
            margin-top: 18px;
            display: flex;
            gap: 16px;
        }
        .saldo-card {
            flex: 1;
            background: var(--surface);
            border-radius: 8px;
            padding: 16px 18px;
        }
        .saldo-card .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ink-soft);
            font-weight: 600;
        }
        .saldo-card .value {
            margin-top: 6px;
            font-size: 17px;
        }

        @media print {
            .no-print { display: none; }
            .card {
                box-shadow: none;
                border: 1px solid #eee;
                padding: 20px;
            }
        }

        @media (max-width: 640px) {
            .filter-group { flex-direction: column; align-items: stretch; }
            .field select, .field input { width: 100%; }
            .saldo-box { flex-direction: column; }
        }
    </style>

    <div class="py-12">
        <div class="container-laporan">

            {{-- ============ FILTER ============ --}}
            <div class="card no-print">
                <h3 style="margin: 0 0 16px; font-weight: 700; font-size: 15px;">Filter Laporan</h3>
                <form action="{{ route('laporan.arus-kas.index') }}" method="GET" class="filter-group">
                    <div class="field">
                        <label>Bulan</label>
                        <select name="bulan">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ sprintf('%02d', $m) }}" {{ $bulan == $m ? 'selected' : '' }}>
                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Tahun</label>
                        <input type="number" name="tahun" value="{{ $tahun }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <a href="{{ route('laporan.arus-kas.index', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-excel">
                        📊 Export Excel
                    </a>
                    <a href="{{ route('laporan.arus-kas.index', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-pdf">
                        📕 Export PDF
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-print">
                        🖨️ Cetak
                    </button>
                </form>
            </div>

            {{-- ============ LAPORAN ============ --}}
            <div class="card">
                @php
                    $lastDay = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->day;
                    $fmt = fn($v) => ($v < 0 ? '(' : '') . 'Rp ' . number_format(abs($v), 2, ',', '.') . ($v < 0 ? ')' : '');
                    $cls = fn($v) => $v < 0 ? 'val-negative' : 'val-positive';
                @endphp

                <div class="report-head">
                    <h1>CV GAHARU AGUNG SEJAHTERA</h1>
                    <h2>Laporan Arus Kas</h2>
                    <p>Untuk Periode yang Berakhir pada {{ $lastDay }} {{ $namaBulan }} {{ $tahun }}</p>
                </div>

                {{-- ---------- Aktivitas Operasional ---------- --}}
                <div class="section-title">ARUS KAS DARI AKTIVITAS OPERASIONAL</div>
                <table class="table-arus">
                    {{-- Penerimaan Pelanggan --}}
                    <tr class="fw-bold">
                        <td colspan="2">Penerimaan Kas dari Pelanggan:</td>
                    </tr>
                    @forelse($penerimaanPelanggan as $item)
                        <tr>
                            <td class="pl-4">{{ $item['keterangan'] }}</td>
                            <td class="text-right font-mono {{ $cls($item['nominal']) }}">{{ $fmt($item['nominal']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="2" class="pl-4">- Tidak ada penerimaan -</td>
                        </tr>
                    @endforelse

                    {{-- Pengeluaran Operasional --}}
                    <tr class="fw-bold">
                        <td colspan="2" style="padding-top: 12px;">Pengeluaran Kas untuk Operasional:</td>
                    </tr>
                    @forelse($pengeluaranBahanBaku as $item)
                        <tr>
                            <td class="pl-4">{{ $item['keterangan'] }}</td>
                            <td class="text-right font-mono {{ $cls($item['nominal']) }}">{{ $fmt($item['nominal']) }}</td>
                        </tr>
                    @empty
                    @endforelse

                    @forelse($pengeluaranBebanOp as $item)
                        <tr>
                            <td class="pl-4">{{ $item['keterangan'] }}</td>
                            <td class="text-right font-mono {{ $cls($item['nominal']) }}">{{ $fmt($item['nominal']) }}</td>
                        </tr>
                    @empty
                    @endforelse

                    @if($pengeluaranBahanBaku->isEmpty() && $pengeluaranBebanOp->isEmpty())
                        <tr class="empty-row">
                            <td colspan="2" class="pl-4">- Tidak ada pengeluaran -</td>
                        </tr>
                    @endif

                    <tr class="row-subtotal">
                        <td>Arus Kas Bersih Dari Aktivitas Operasional</td>
                        <td class="text-right font-mono {{ $cls($kasBersihOperasional) }}">{{ $fmt($kasBersihOperasional) }}</td>
                    </tr>
                </table>

                {{-- ---------- Aktivitas Investasi ---------- --}}
                <div class="section-title">ARUS KAS DARI AKTIVITAS INVESTASI</div>
                <table class="table-arus">
                    @forelse($investasi as $item)
                        <tr>
                            <td>{{ $item['keterangan'] }}</td>
                            <td class="text-right font-mono {{ $cls($item['nominal']) }}">{{ $fmt($item['nominal']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td>Tidak ada transaksi kas dari aktivitas investasi selama periode ini</td>
                            <td class="text-right font-mono">Rp 0,00</td>
                        </tr>
                    @endforelse

                    <tr class="row-subtotal">
                        <td>Arus Kas Bersih Dari Aktivitas Investasi</td>
                        <td class="text-right font-mono {{ $cls($kasBersihInvestasi) }}">{{ $fmt($kasBersihInvestasi) }}</td>
                    </tr>
                </table>

                {{-- ---------- Aktivitas Pendanaan ---------- --}}
                <div class="section-title">ARUS KAS DARI AKTIVITAS PENDANAAN</div>
                <table class="table-arus">
                    @forelse($pendanaan as $item)
                        <tr>
                            <td>{{ $item['keterangan'] }}</td>
                            <td class="text-right font-mono {{ $cls($item['nominal']) }}">{{ $fmt($item['nominal']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td>Tidak ada transaksi kas dari aktivitas pendanaan selama periode ini</td>
                            <td class="text-right font-mono">Rp 0,00</td>
                        </tr>
                    @endforelse

                    <tr class="row-subtotal">
                        <td>Arus Kas Bersih Dari Aktivitas Pendanaan</td>
                        <td class="text-right font-mono {{ $cls($kasBersihPendanaan) }}">{{ $fmt($kasBersihPendanaan) }}</td>
                    </tr>
                </table>

                {{-- ---------- Kenaikan / Penurunan Kas ---------- --}}
                <div class="net-box">
                    <span>KENAIKAN (PENURUNAN) BERSIH KAS DAN BANK</span>
                    <span class="font-mono">{{ $fmt($kenaikanPenurunanKas) }}</span>
                </div>

                {{-- ---------- Rekonsiliasi Saldo Kas ---------- --}}
                <div class="saldo-box">
                    <div class="saldo-card">
                        <div class="label">KAS DAN BANK AWAL PERIODE (01/{{ sprintf('%02d', $bulan) }}/{{ $tahun }})</div>
                        <div class="value font-mono">{{ $fmt($saldoAwalKas) }}</div>
                    </div>
                    <div class="saldo-card">
                        <div class="label">KAS DAN BANK AKHIR PERIODE ({{ sprintf('%02d', $lastDay) }}/{{ sprintf('%02d', $bulan) }}/{{ $tahun }})</div>
                        <div class="value font-mono">{{ $fmt($saldoAkhirKas) }}</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>