<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Wasted Bahan Baku</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm 10mm 15mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #2c3e50;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #d88656;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h2 {
            margin: 0;
            font-size: 16px;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 3px 0 0 0;
            font-size: 10px;
            color: #7f8c8d;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .summary-box {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .summary-box td {
            padding: 8px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        .summary-box .title {
            font-size: 9px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: bold;
        }
        .summary-box .val {
            font-size: 13px;
            font-weight: bold;
            margin-top: 2px;
            color: #2c3e50;
        }
        .summary-box .val-danger {
            color: #c0392b;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .main-table th {
            background-color: #f4ede6;
            color: #4a3628;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            text-transform: uppercase;
            font-size: 8.5px;
        }
        .main-table td {
            padding: 5px 4px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .main-table tr:nth-child(even) {
            background-color: #fafbfc;
        }
        .main-table tfoot td {
            background-color: #f1f3f5;
            font-weight: bold;
            border-top: 2px solid #adb5bd;
            font-size: 9.5px;
            padding: 6px 4px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-danger { color: #c0392b; }
        .fw-bold { font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .b-danger { background-color: #f8d7da; color: #721c24; }
        .b-warning { background-color: #fff3cd; color: #856404; }
        .b-success { background-color: #d4edda; color: #155724; }
        .b-secondary { background-color: #e2e3e5; color: #383d41; }
        .footer-note {
            margin-top: 15px;
            font-size: 8.5px;
            color: #7f8c8d;
            text-align: right;
            font-style: italic;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN WASTED / PEMBOROSAN BAHAN BAKU</h2>
        <p>CV Gaharu App &bull; Rekapitulasi Pemborosan Barang, Alokasi Outlet, dan Rasio terhadap Pembelian</p>
    </div>

    <table class="meta-table">
        <tr>
            <td width="15%"><strong>Periode Laporan</strong></td>
            <td width="2%">:</td>
            <td width="33%">
                {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}
            </td>
            <td width="15%"><strong>Tanggal Cetak</strong></td>
            <td width="2%">:</td>
            <td width="33%">
                {{ date('d/m/Y H:i') }} (WIB)
            </td>
        </tr>
        <tr>
            <td><strong>Filter Gudang / Outlet</strong></td>
            <td>:</td>
            <td>
                @if(request('gudang_id'))
                    {{ \App\Models\MasterGudang::find(request('gudang_id'))->nama ?? 'Semua Outlet' }}
                @else
                    Semua Outlet &amp; Gudang
                @endif
            </td>
            <td><strong>Status Dokumen</strong></td>
            <td>:</td>
            <td>
                {{ request('status') === 'all' ? 'Semua Status' : (request('status') === 'draft' ? 'Hanya Draft' : 'Hanya Approved (Sah)') }}
            </td>
        </tr>
    </table>

    <table class="summary-box">
        <tr>
            <td width="25%">
                <div class="title">Total Kerugian Wasted</div>
                <div class="val val-danger">Rp {{ number_format($summary['total_nominal_wasted'], 0, ',', '.') }}</div>
            </td>
            <td width="25%">
                <div class="title">Total Qty Wasted</div>
                <div class="val">{{ number_format($summary['total_qty_wasted'], 2, ',', '.') }} unit</div>
            </td>
            <td width="25%">
                <div class="title">Total Belanja Pembelian Terkait</div>
                <div class="val">Rp {{ number_format($summary['total_nominal_beli'], 0, ',', '.') }}</div>
            </td>
            <td width="25%">
                <div class="title">Rasio Global Wasted / Beli</div>
                <div class="val {{ $summary['global_persen_nominal'] > 15 ? 'val-danger' : '' }}">
                    {{ number_format($summary['global_persen_nominal'], 2, ',', '.') }}%
                </div>
            </td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="20" class="text-center">No</th>
                <th width="55">Kode</th>
                <th width="140">Nama Barang</th>
                <th width="35" class="text-center">Sat</th>
                <th width="65" class="text-right">Qty Wasted</th>
                <th width="85" class="text-right">Nilai Wasted</th>
                <th>Alokasi Outlet &amp; Divisi</th>
                <th width="65" class="text-right">Qty Beli</th>
                <th width="85" class="text-right">Nilai Beli</th>
                <th width="50" class="text-center">% Wasted (Qty)</th>
                <th width="50" class="text-center">% Wasted (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $idx => $item)
                @php
                    $pQty = $item['persen_qty'];
                    $pNom = $item['persen_nominal'];

                    $getBadge = function($val) {
                        if ($val === null) return 'b-secondary';
                        if ($val > 15) return 'b-danger';
                        if ($val > 5) return 'b-warning';
                        return 'b-success';
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $item['kode_barang'] }}</td>
                    <td class="fw-bold">{{ $item['nama_barang'] }}</td>
                    <td class="text-center">{{ strtoupper($item['satuan']) }}</td>
                    <td class="text-right fw-bold text-danger">{{ number_format($item['total_qty_wasted'], 2, ',', '.') }}</td>
                    <td class="text-right fw-bold text-danger">Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}</td>
                    <td>
                        @foreach($item['alokasi'] as $loc)
                            <div>
                                &bull; <strong>{{ $loc['lokasi'] }}</strong>: {{ number_format($loc['qty'], 2, ',', '.') }} {{ $item['satuan'] }}
                                (Rp {{ number_format($loc['nominal'], 0, ',', '.') }})
                            </div>
                        @endforeach
                    </td>
                    <td class="text-right">
                        {{ $item['total_qty_beli'] > 0 ? number_format($item['total_qty_beli'], 2, ',', '.') : '0' }}
                    </td>
                    <td class="text-right">
                        Rp {{ number_format($item['total_nominal_beli'], 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($pQty !== null)
                            <span class="badge {{ $getBadge($pQty) }}">{{ number_format($pQty, 1, ',', '.') }}%</span>
                        @else
                            <span class="badge b-secondary">Tanpa Beli</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($pNom !== null)
                            <span class="badge {{ $getBadge($pNom) }}">{{ number_format($pNom, 1, ',', '.') }}%</span>
                        @else
                            <span class="badge b-secondary">Tanpa Beli</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px;">
                        Tidak ada transaksi wasted tercatat pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($items->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">TOTAL KESELURUHAN:</td>
                    <td class="text-right text-danger">{{ number_format($summary['total_qty_wasted'], 2, ',', '.') }}</td>
                    <td class="text-right text-danger">Rp {{ number_format($summary['total_nominal_wasted'], 0, ',', '.') }}</td>
                    <td></td>
                    <td></td>
                    <td class="text-right">Rp {{ number_format($summary['total_nominal_beli'], 0, ',', '.') }}</td>
                    <td></td>
                    <td class="text-center">
                        <span class="badge {{ $getBadge($summary['global_persen_nominal']) }}">
                            {{ number_format($summary['global_persen_nominal'], 2, ',', '.') }}%
                        </span>
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer-note">
        Dokumen dicetak secara otomatis dari Sistem ERP CV Gaharu App pada {{ date('d-m-Y H:i:s') }}.
    </div>

</body>
</html>
