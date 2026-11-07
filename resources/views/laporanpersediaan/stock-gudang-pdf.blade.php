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
            <td width="15%"><strong>Posisi Stok Per</strong></td>
            <td width="2%">:</td>
            <td width="33%">
                {{ request('tanggal') ? \Carbon\Carbon::parse(request('tanggal'))->format('d/m/Y') : 'Saat Ini (' . date('d/m/Y H:i') . ')' }}
            </td>
            <td width="15%" class="text-right"><strong>Tanggal Cetak</strong></td>
            <td width="2%" class="text-center">:</td>
            <td width="33%">{{ date('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td><strong>Total Item</strong></td>
            <td>:</td>
            <td>{{ number_format($totalItem ?? $data->count()) }} item</td>
            <td class="text-right"><strong>Total Nilai Stok</strong></td>
            <td class="text-center">:</td>
            <td class="plus">Rp {{ number_format($totalNilaiStok ?? 0, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="90">Kode Barang</th>
                <th>Nama Barang</th>
                <th width="120">Gudang & Divisi</th>
                <th width="60">Satuan</th>
                <th class="text-right" width="90">Stok</th>
                <th class="text-right" width="85">Harga Satuan</th>
                <th class="text-right" width="105">Total Harga</th>
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
                    <td class="text-right fw-bold {{ $row->jumlah <= 0 ? 'minus' : 'plus' }}">
                        {{ number_format($row->jumlah, 2) }}
                    </td>
                    <td class="text-right">
                        Rp {{ number_format($row->harga_satuan ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-right fw-bold {{ ($row->total_harga ?? 0) <= 0 ? 'minus' : 'plus' }}">
                        Rp {{ number_format($row->total_harga ?? 0, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #e5e7eb; font-weight: bold;">
                <td colspan="4" class="text-right">TOTAL NILAI PERSEDIAAN:</td>
                <td class="text-right">{{ number_format($data->sum('jumlah'), 2) }}</td>
                <td></td>
                <td class="text-right plus">Rp {{ number_format($totalNilaiStok ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

</body>
</html>