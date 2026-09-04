<!DOCTYPE html>
<html>
<head>
    <title>Laporan Posisi Stok Gudang</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; color: #1e3a8a; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #666; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; font-size: 11px; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .main-table th { background-color: #1e3a8a; color: #ffffff; text-align: left; padding: 8px; font-weight: bold; font-size: 11px; }
        .main-table td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 10px; }
        .main-table tr:nth-child(even) { background-color: #fcfcfc; }
        .main-table tr.habis td { background-color: #fde8ea; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .plus  { color:#198754; font-weight:700; }
        .minus { color:#dc3545; font-weight:700; }
        .fw-bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN POSISI STOK GUDANG</h2>
        <p>CV Gaharu App</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Kategori Laporan</strong></td>
            <td width="3%">:</td>
            <td>Stok Aktual Gudang</td>
            <td width="15%" class="text-right"><strong>Tanggal Cetak</strong></td>
            <td width="3%">:</td>
            <td width="20%">{{ date('d-m-Y H:i') }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="110">Kode Barang</th>
                <th>Nama Barang</th>
                <th width="130">Gudang & Divisi</th>
                <th width="80">Satuan</th>
                <th class="text-right" width="130">Stok</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                @php
                    $konversi = (float) ($row->konversi_pembelian ?? 1);
                    $hasKonversi = !empty($row->satuan_pembelian) && $konversi > 1;
                @endphp
                <tr class="{{ $row->jumlah == 0 ? 'habis' : '' }}">
                    <td class="fw-bold">{{ $row->kode_barang }}</td>
                    <td>{{ $row->nama_barang }}</td>
                    <td>
                        {{ $row->nama_gudang }}
                        @if(!empty($row->nama_divisi))
                            <span style="font-size: 9px; color: #555;">({{ $row->nama_divisi }})</span>
                        @endif
                    </td>
                    <td>
                        {{ $row->satuan }}
                        @if($hasKonversi)
                            <div style="font-size: 8px; color: #1e3a8a;">1 {{ $row->satuan_pembelian }} = {{ number_format($konversi, 0, ',', '.') }} {{ $row->satuan }}</div>
                        @endif
                    </td>
                    <td class="text-right fw-bold {{ $row->jumlah == 0 ? 'minus' : 'plus' }}">
                        {{ number_format($row->jumlah, 2) }} {{ $row->satuan }}
                        @if($hasKonversi)
                            <div style="font-size: 9px; font-weight: normal; color: #1e3a8a;">
                                ({{ number_format($row->jumlah / $konversi, 2) }} {{ $row->satuan_pembelian }})
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>