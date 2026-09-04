<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $pembelian->kode_pembelian }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 15px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .company-name { font-size: 20px; font-weight: bold; text-transform: uppercase; color: #1a202c; }
        .doc-title { font-size: 16px; font-weight: bold; text-align: right; color: #2b6cb0; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 4px 0; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { background-color: #2b6cb0; color: white; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .item-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .total-box { margin-top: 15px; float: right; width: 40%; }
        .total-table { width: 100%; border-collapse: collapse; }
        .total-table td { padding: 6px; font-size: 12px; }
        .signature-section { margin-top: 50px; width: 100%; clear: both; }
        .signature-box { float: left; width: 30%; text-align: center; }
        .signature-space { height: 60px; }
        .badge { padding: 3px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .bg-success { background-color: #38a169; color: white; }
        .bg-warning { background-color: #dd6b20; color: white; }
    </style>
</head>
<body>

    <table style="width:100%; margin-bottom: 15px;">
        <tr>
            <td style="width: 50%;">
                <div class="company-name">PT. GAHARU APP</div>
                <div style="font-size: 11px; color: #666; margin-top: 4px;">
                    Layanan Logistik & Pengadaan Bahan Baku<br>
                    Gudang: {{ $pembelian->gudang->nama ?? 'Gudang Utama' }}
                </div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="doc-title">PURCHASE ORDER (PO)</div>
                <div style="font-weight: bold; font-size: 13px; margin-top: 4px;">#{{ $pembelian->kode_pembelian }}</div>
                <div style="color: #666; font-size: 11px;">Tanggal: {{ \Carbon\Carbon::parse($pembelian->tanggal)->format('d F Y') }}</div>
            </td>
        </tr>
    </table>

    <div style="border-top: 2px solid #2b6cb0; margin-bottom: 15px;"></div>

    <table class="info-table">
        <tr>
            <td style="width: 55%;">
                <strong style="color: #4a5568; font-size: 11px; text-transform: uppercase;">PEMBESAR / SUPPLIER:</strong><br>
                <span style="font-size: 14px; font-weight: bold;">{{ $pembelian->supplier->nama ?? '-' }}</span><br>
                Telepon: {{ $pembelian->supplier->telepon ?? '-' }}<br>
                Alamat: {{ $pembelian->supplier->alamat ?? '-' }}
            </td>
            <td style="width: 45%;">
                <strong style="color: #4a5568; font-size: 11px; text-transform: uppercase;">INFORMASI PEMBAYARAN:</strong><br>
                Metode Pembayaran: <strong>{{ strtoupper($pembelian->metode_pembayaran ?? 'COD') }}</strong><br>
                Status Pelunasan: 
                @if($pembelian->is_lunas)
                    <span class="badge bg-success">LUNAS</span>
                @else
                    <span class="badge bg-warning">BELUM LUNAS</span>
                @endif
                <br>
                Status Penerimaan: 
                @if($pembelian->is_diterima)
                    <span class="badge bg-success">BARANG DITERIMA</span>
                @else
                    <span class="badge bg-warning">PROSES PENERIMAAN</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Nama Barang / Bahan Baku</th>
                <th class="text-center" style="width: 15%;">Qty Dipesan</th>
                <th class="text-center" style="width: 15%;">Qty Diterima</th>
                <th class="text-end" style="width: 20%;">Harga / Satuan</th>
                <th class="text-end" style="width: 20%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $totalSub = 0; @endphp
            @foreach($pembelian->details as $idx => $detail)
                @php 
                    $bItem = $detail->barang;
                    $sPembelian = $detail->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                    $konv = (float)($detail->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                    $sUtama = $bItem->satuan ?? 'Unit';
                    $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);
                    $unitDisplay = $sPembelian ?: $sUtama;

                    $qtyDiterima = $detail->qty_diterima ?? $detail->qty;
                    $subtotal = $detail->qty * $detail->harga_per_qty; 
                    $totalSub += $subtotal;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $detail->barang->nama ?? '-' }}</strong>
                        <div style="font-size: 9.5px; color: #718096; font-family: monospace;">{{ $detail->barang->kode_barang ?? '' }}</div>
                    </td>
                    <td class="text-center">
                        <strong>{{ number_format($detail->qty, 2, ',', '.') }} {{ $unitDisplay }}</strong>
                        @if($hasKonv)
                            <div style="font-size: 9px; color: #2b6cb0;">= {{ number_format($detail->qty * $konv, 2, ',', '.') }} {{ $sUtama }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        <strong>{{ number_format($qtyDiterima, 2, ',', '.') }} {{ $unitDisplay }}</strong>
                        @if($hasKonv)
                            <div style="font-size: 9px; color: #2b6cb0;">= {{ number_format($qtyDiterima * $konv, 2, ',', '.') }} {{ $sUtama }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        Rp {{ number_format($detail->harga_per_qty, 0, ',', '.') }} / {{ $unitDisplay }}
                        @if($hasKonv && $konv > 0)
                            <div style="font-size: 9px; color: #718096;">(~Rp {{ number_format($detail->harga_per_qty / $konv, 2, ',', '.') }} / {{ $sUtama }})</div>
                        @endif
                    </td>
                    <td class="text-end fw-bold">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <table class="total-table">
            <tr>
                <td class="fw-bold">Total Pembelian:</td>
                <td class="text-end fw-bold" style="font-size: 14px; color: #2b6cb0;">
                    Rp {{ number_format($pembelian->total ?? $totalSub, 0, ',', '.') }}
                </td>
            </tr>
            @if($pembelian->nominal_dp > 0)
            <tr>
                <td>Uang Muka (DP):</td>
                <td class="text-end">Rp {{ number_format($pembelian->nominal_dp, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fw-bold">Sisa Tagihan:</td>
                <td class="text-end fw-bold text-danger">Rp {{ number_format($pembelian->total - $pembelian->nominal_dp, 0, ',', '.') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            Dibuat Oleh,<br>
            <div class="signature-space"></div>
            <strong>({{ $pembelian->user->name ?? 'Bagian Pembelian' }})</strong>
        </div>
        <div class="signature-box" style="margin-left: 5%;">
            Disetujui Oleh,<br>
            <div class="signature-space"></div>
            <strong>( Supplier / Vendor )</strong>
        </div>
        <div class="signature-box" style="float: right;">
            Diterima Oleh,<br>
            <div class="signature-space"></div>
            <strong>({{ $pembelian->penerimaDiterima->name ?? 'Gudang Penerima' }})</strong>
        </div>
    </div>

</body>
</html>
