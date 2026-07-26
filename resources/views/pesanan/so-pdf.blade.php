<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sales Order B2B - {{ $pesanan->kode_pesanan }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 15px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .company-name { font-size: 20px; font-weight: bold; text-transform: uppercase; color: #6a4126; }
        .doc-title { font-size: 16px; font-weight: bold; text-align: right; color: #6a4126; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 4px 0; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { background-color: #6a4126; color: white; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .item-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .total-box { margin-top: 15px; float: right; width: 45%; }
        .total-table { width: 100%; border-collapse: collapse; }
        .total-table td { padding: 6px; font-size: 12px; }
        .signature-section { margin-top: 50px; width: 100%; clear: both; }
        .signature-box { float: left; width: 30%; text-align: center; }
        .signature-space { height: 60px; }
        .badge { padding: 3px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .bg-success { background-color: #38a169; color: white; }
        .bg-warning { background-color: #dd6b20; color: white; }
        .bg-info { background-color: #3182ce; color: white; }
    </style>
</head>
<body>

    <table style="width:100%; margin-bottom: 15px;">
        <tr>
            <td style="width: 50%;">
                <div class="company-name">GAHARU APP B2B</div>
                <div style="font-size: 11px; color: #666; margin-top: 4px;">
                    Manajemen Penjualan & Distribusi B2B<br>
                    Gudang Pengirim: {{ $pesanan->gudang->nama ?? 'Gudang Utama' }}
                </div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="doc-title">SALES ORDER (SO)</div>
                <div style="font-weight: bold; font-size: 13px; margin-top: 4px;">#{{ $pesanan->kode_pesanan }}</div>
                <div style="color: #666; font-size: 11px;">Tanggal: {{ \Carbon\Carbon::parse($pesanan->tanggal)->format('d F Y') }}</div>
            </td>
        </tr>
    </table>

    <div style="border-top: 2px solid #6a4126; margin-bottom: 15px;"></div>

    <table class="info-table">
        <tr>
            <td style="width: 55%;">
                <strong style="color: #4a5568; font-size: 11px; text-transform: uppercase;">PELANGGAN / CUSTOMER B2B:</strong><br>
                <span style="font-size: 14px; font-weight: bold;">{{ $pesanan->customer->nama ?? $pesanan->customer->name ?? 'Umum / Tanpa Nama' }}</span><br>
                Telepon: {{ $pesanan->customer->telepon ?? '-' }}<br>
                Alamat: {{ $pesanan->customer->alamat ?? '-' }}
            </td>
            <td style="width: 45%;">
                <strong style="color: #4a5568; font-size: 11px; text-transform: uppercase;">INFORMASI ORDER:</strong><br>
                Estimasi Pengiriman: <strong>{{ \Carbon\Carbon::parse($pesanan->estimasi_kirim)->format('d F Y') }}</strong><br>
                Status Pesanan: <span class="badge bg-info">{{ strtoupper($pesanan->status_pesanan ?? 'DRAFT') }}</span><br>
                Status Pembayaran: 
                @if(($pesanan->status_pembayaran ?? '') == 'Lunas')
                    <span class="badge bg-success">LUNAS</span>
                @elseif(($pesanan->status_pembayaran ?? '') == 'DP')
                    <span class="badge bg-warning">DP / UANG MUKA</span>
                @else
                    <span class="badge bg-warning">BELUM BAYAR</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Nama Produk</th>
                <th class="text-center" style="width: 15%;">Jumlah (Qty)</th>
                <th class="text-end" style="width: 20%;">Harga Satuan</th>
                <th class="text-end" style="width: 20%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalSub = 0; 
                $details = $pesanan->details;
            @endphp
            @foreach($details as $idx => $detail)
                @php 
                    $subtotal = $detail->subtotal ?? ($detail->qty * $detail->harga);
                    $totalSub += $subtotal;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><strong>{{ $detail->produk->nama ?? '-' }}</strong></td>
                    <td class="text-center">{{ number_format($detail->qty, 0, ',', '.') }} {{ $detail->produk->satuan ?? 'Pcs' }}</td>
                    <td class="text-end">Rp {{ number_format($detail->harga, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <table class="total-table">
            <tr>
                <td class="fw-bold">Total Sales Order:</td>
                <td class="text-end fw-bold" style="font-size: 14px; color: #6a4126;">
                    Rp {{ number_format($pesanan->total_pesanan ?? $totalSub, 0, ',', '.') }}
                </td>
            </tr>
            @php 
                $totalBayar = $pesanan->pembayaran->sum('jumlah_bayar'); 
                $sisaTagihan = max(0, ($pesanan->total_pesanan ?? $totalSub) - $totalBayar);
            @endphp
            @if($totalBayar > 0)
            <tr>
                <td>Total Sudah Dibayar:</td>
                <td class="text-end text-success fw-bold">Rp {{ number_format($totalBayar, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fw-bold">Sisa Pelunasan:</td>
                <td class="text-end fw-bold text-danger">Rp {{ number_format($sisaTagihan, 0, ',', '.') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="signature-section">
        
        <div class="signature-box" style="margin-left: 5%;">
            Disetujui Oleh,<br>
            <div class="signature-space"></div>
            <strong>( Manajer Operasional )</strong>
        </div>
        <div class="signature-box" style="float: right;">
            Pemesan / Customer,<br>
            <div class="signature-space"></div>
            <strong>({{ $pesanan->customer->nama ?? $pesanan->customer->name ?? 'Pelanggan' }})</strong>
        </div>
    </div>

</body>
</html>
