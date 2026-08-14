<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Pesanan Central Kitchen - {{ $pesanan->kode_pesanan }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #6a4126; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #6a4126; }
        .header p { margin: 2px 0 0; font-size: 11px; color: #666; }
        .table-info { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .table-info td { padding: 4px 6px; vertical-align: top; }
        .table-items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-items th { background-color: #6a4126; color: #fff; text-align: left; padding: 8px; font-size: 11px; }
        .table-items td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 11px; }
        .footer { margin-top: 30px; width: 100%; }
        .footer td { text-align: center; vertical-align: bottom; height: 60px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>CENTRAL KITCHEN ORDER</h2>
        <p>Gaharu & KeJingga Group - Internal Transfer Request</p>
    </div>

    <table class="table-info">
        <tr>
            <td style="width: 15%; font-weight: bold;">Kode Order</td>
            <td style="width: 35%;">: {{ $pesanan->kode_pesanan }}</td>
            <td style="width: 15%; font-weight: bold;">Tanggal Order</td>
            <td style="width: 35%;">: {{ date('d/m/Y', strtotime($pesanan->tanggal)) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Outlet Pemesan</td>
            <td>: {{ $pesanan->customer->nama ?? '-' }}</td>
            <td style="font-weight: bold;">Estimasi Kirim</td>
            <td>: {{ date('d/m/Y', strtotime($pesanan->estimasi_kirim)) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Status</td>
            <td>: {{ ucfirst($pesanan->status_pesanan) }}</td>
            <td style="font-weight: bold;">Tipe Transfer</td>
            <td>: Transfer HPP Internal (Rp 0 Penagihan)</td>
        </tr>
    </table>

    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">NO</th>
                <th style="width: 100px;">KODE ITEM</th>
                <th>NAMA ITEM / BAHAN</th>
                <th style="width: 100px; text-align: center;">QTY TARGET</th>
                <th style="width: 80px; text-align: center;">SATUAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pesanan->details as $index => $detail)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $detail->produk->kode_barang ?? '-' }}</td>
                    <td>{{ $detail->produk->nama ?? 'Item Hapus' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ number_format($detail->qty, 2) }}</td>
                    <td style="text-align: center;">{{ $detail->produk->satuan ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>
                Pemesan (Outlet)<br><br><br><br>
                ( ____________________ )
            </td>
            <td>
                Central Kitchen<br><br><br><br>
                ( ____________________ )
            </td>
            <td>
                Gudang & Logistik<br><br><br><br>
                ( ____________________ )
            </td>
        </tr>
    </table>

</body>
</html>
