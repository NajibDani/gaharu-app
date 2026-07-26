<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Sales Order POS - {{ $penjualan->kode_transaksi }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 15px; }
        .header { border-bottom: 2px dashed #666; padding-bottom: 10px; margin-bottom: 15px; text-align: center; }
        .outlet-name { font-size: 18px; font-weight: bold; text-transform: uppercase; color: #000; }
        .doc-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #444; margin-top: 4px; }
        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; font-size: 11px; }
        .info-table td { padding: 3px 0; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .item-table th { background-color: #333; color: white; padding: 6px 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .item-table td { padding: 6px 8px; border-bottom: 1px dashed #ccc; font-size: 11px; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .total-box { margin-top: 10px; float: right; width: 45%; }
        .total-table { width: 100%; border-collapse: collapse; }
        .total-table td { padding: 4px 6px; font-size: 12px; }
        .footer-note { margin-top: 40px; text-align: center; font-size: 11px; color: #666; border-top: 1px dashed #ccc; padding-top: 10px; clear: both; }
        .badge { padding: 3px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .bg-success { background-color: #38a169; color: white; }
        .bg-warning { background-color: #dd6b20; color: white; }
    </style>
</head>
<body>

    <div class="header">
        <div class="outlet-name">{{ $penjualan->gudang->nama ?? 'OUTLET KASIR POS' }}</div>
        <div class="doc-title">STRUK SALES ORDER POS</div>
        <div style="font-size: 11px; color: #555; margin-top: 3px;">
            Kode Transaksi: <strong>#{{ $penjualan->kode_transaksi }}</strong>
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td style="width: 60%;">
                Tanggal Transaksi: <strong>{{ \Carbon\Carbon::parse($penjualan->tanggal)->format('d F Y, H:i') }}</strong><br>
                Kasir / Operator: <strong>{{ $penjualan->creator->name ?? 'Kasir Outlet' }}</strong>
            </td>
            <td style="width: 40%; text-align: right;">
                Status Transaksi: 
                @if(($penjualan->status ?? 'Draft') === 'SUKSES' || ($penjualan->status ?? 'Draft') === 'Approved')
                    <span class="badge bg-success">APPROVED</span>
                @else
                    <span class="badge bg-warning">DRAFT</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 8%;">No</th>
                <th>Nama Produk</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-end" style="width: 25%;">Harga Satuan</th>
                <th class="text-end" style="width: 25%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $totalCalc = 0; @endphp
            @foreach($penjualan->details as $idx => $detail)
                @php 
                    $sub = $detail->subtotal ?? ($detail->qty * $detail->harga);
                    $totalCalc += $sub;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><strong>{{ $detail->produk->nama ?? '-' }}</strong></td>
                    <td class="text-center">{{ number_format($detail->qty, 0, ',', '.') }} {{ $detail->produk->satuan ?? 'Pcs' }}</td>
                    <td class="text-end">Rp {{ number_format($detail->harga, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format($sub, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <table class="total-table">
            <tr>
                <td class="fw-bold">TOTAL OMZET:</td>
                <td class="text-end fw-bold" style="font-size: 15px; color: #1a202c;">
                    Rp {{ number_format($penjualan->total ?? $totalCalc, 0, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-note">
        Struk ini merupakan bukti sah rekapitulasi transaksi Retail POS.
    </div>

</body>
</html>
