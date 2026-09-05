<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PURCHASE ORDER CENTRAL KITCHEN CV GAHARU - {{ $pesanan->kode_pesanan }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px double #6a4126; padding-bottom: 8px; }
        .header h2 { margin: 0; color: #6a4126; font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .header h3 { margin: 2px 0 0; color: #334155; font-size: 13px; font-weight: bold; }
        .header p { margin: 2px 0 0; font-size: 10px; color: #64748b; }
        .table-info { width: 100%; margin-bottom: 15px; border-collapse: collapse; background: #f8fafc; border: 1px solid #e2e8f0; }
        .table-info td { padding: 6px 10px; vertical-align: top; font-size: 11px; }
        .table-items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-items th { background-color: #6a4126; color: #fff; text-align: left; padding: 8px; font-size: 10px; font-weight: bold; text-transform: uppercase; border: 1px solid #6a4126; }
        .table-items td { padding: 8px; border: 1px solid #cbd5e1; font-size: 11px; vertical-align: middle; }
        .badge-batch { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 10px; display: inline-block; }
        .footer { margin-top: 40px; width: 100%; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .footer td { text-align: center; vertical-align: bottom; height: 50px; font-size: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>PURCHASE ORDER CENTRAL KITCHEN</h2>
        <h3>CENTRAL KITCHEN CV GAHARU AGUNG SEJAHTERA</h3>
        <p>Layanan Pengadaan &amp; Distribusi Bahan Setengah Jadi - Internal Order Request</p>
    </div>

    <table class="table-info">
        <tr>
            <td style="width: 15%; font-weight: bold; color: #475569;">Kode Order</td>
            <td style="width: 35%; font-weight: bold; color: #0284c7;">: {{ $pesanan->kode_pesanan }}</td>
            <td style="width: 15%; font-weight: bold; color: #475569;">Tanggal Order</td>
            <td style="width: 35%;">: {{ date('d/m/Y', strtotime($pesanan->tanggal)) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; color: #475569;">Outlet Pemesan</td>
            <td>: <strong>{{ $pesanan->customer->nama ?? '-' }}</strong></td>
            <td style="font-weight: bold; color: #475569;">Estimasi Kirim</td>
            <td>: {{ date('d/m/Y', strtotime($pesanan->estimasi_kirim)) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; color: #475569;">Status Order</td>
            <td>: <strong style="color: #d97706;">{{ strtoupper($pesanan->status_pesanan) }}</strong></td>
            <td style="font-weight: bold; color: #475569;">Divisi CK</td>
            <td>: {{ $pesanan->divisi->nama ?? 'Gudang Central Kitchen' }}</td>
        </tr>
    </table>

    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">NO</th>
                <th style="width: 90px;">KODE ITEM</th>
                <th>NAMA BAHAN SETENGAH JADI</th>
                <th style="width: 160px; text-align: center;">KONVERSI RESEP / BATCH</th>
                <th style="width: 110px; text-align: right;">TOTAL TARGET QTY</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pesanan->details as $index => $detail)
                @php
                    $resepObj = $detail->produk->resepBtklBop ?? null;
                    $outQty = floatval($resepObj->output_qty ?? 0);
                    $outSatuan = $resepObj->satuan_output ?? ($detail->produk->satuan ?? '');
                    $resepText = '-';
                    if ($outQty > 0) {
                        $resepCount = $detail->qty / $outQty;
                        $resepCountFmt = (fmod($resepCount, 1) == 0) ? number_format($resepCount, 0) : number_format($resepCount, 2, ',', '.');
                        $resepText = $resepCountFmt . ' Resep (@ ' . number_format($outQty, 0, ',', '.') . ' ' . $outSatuan . ')';
                    }
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $detail->produk->kode_barang ?? '-' }}</td>
                    <td><strong>{{ $detail->produk->nama ?? 'Item Hapus' }}</strong></td>
                    <td style="text-align: center;">
                        @if($outQty > 0)
                            <span class="badge-batch">{{ $resepText }}</span>
                        @else
                            <span style="color: #94a3b8;">Standard (Non-Resep)</span>
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">
                        {{ (fmod($detail->qty, 1) == 0) ? number_format($detail->qty, 0, ',', '.') : number_format($detail->qty, 2, ',', '.') }} {{ $detail->produk->satuan ?? '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>
                Pemesan (Outlet)<br><br><br><br>
                ( <strong>{{ $pesanan->customer->nama ?? 'Kepala Outlet' }}</strong> )
            </td>
            <td>
                Central Kitchen<br><br><br><br>
                ( <strong>Dapur Pusat CV Gaharu</strong> )
            </td>
            <td>
                Gudang &amp; Logistik<br><br><br><br>
                ( <strong>Tim Warehouse CK</strong> )
            </td>
        </tr>
    </table>

</body>
</html>
