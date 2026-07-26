<!DOCTYPE html>
<html>
<head>
    <title>Detail Produksi - {{ $produksi->kode_produksi }}</title>
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
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .title { margin-top: 20px; font-size: 12px; font-weight: bold; color: #1e3a8a; border-bottom: 1px solid #1e3a8a; padding-bottom: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>DETAIL REALISASI PRODUKSI</h2>
        <p>CV Gaharu App</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="18%"><strong>Kode Produksi</strong></td>
            <td width="3%">:</td>
            <td width="29%">{{ $produksi->kode_produksi }}</td>
            <td width="18%"><strong>Kode Pesanan</strong></td>
            <td width="3%">:</td>
            <td width="29%">{{ $produksi->pesanan->kode_pesanan ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal Mulai</strong></td>
            <td>:</td>
            <td>{{ \Carbon\Carbon::parse($produksi->tanggal_mulai)->format('d-M-Y H:i') }}</td>
            <td><strong>Nama Customer</strong></td>
            <td>:</td>
            <td>{{ $produksi->pesanan->customer->nama ?? 'Tidak Ada / Umum' }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal Selesai</strong></td>
            <td>:</td>
            <td>
                @if($produksi->tanggal_selesai)
                    {{ \Carbon\Carbon::parse($produksi->tanggal_selesai)->format('d-M-Y H:i') }}
                @else
                    Belum Selesai
                @endif
            </td>
            <td><strong>Gudang Hasil</strong></td>
            <td>:</td>
            <td>{{ $namaGudang }}</td>
        </tr>
        <tr>
            <td><strong>Status Produksi</strong></td>
            <td>:</td>
            <td>{{ strtoupper($produksi->status_produksi) }}</td>
            <td><strong>Dicatat Oleh</strong></td>
            <td>:</td>
            <td>{{ $produksi->creator->nama ?? '-' }}</td>
        </tr>
    </table>

    <h3 class="title">Item Hasil Produksi</h3>
    <table class="main-table">
        <thead>
            <tr>
                <th width="30" class="text-center">No</th>
                <th>Nama Produk</th>
                <th class="text-center" width="150">Qty Hasil</th>
                <th class="text-right" width="200">Total HPP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($produksi->details as $index => $detail)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="fw-bold">{{ $detail->produk->nama ?? 'Produk Tidak Diketahui' }}</td>
                <td class="text-center fw-bold">{{ number_format($detail->qty, 0, ',', '.') }} Unit</td>
                <td class="text-right fw-bold text-success" style="padding-right: 15px;">
                    @if($produksi->status_produksi === 'Draft')
                        Dihitung saat Approve
                    @else
                        Rp {{ number_format($detail->hpp_total, 0, ',', '.') }}
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center text-muted">Tidak ada detail produk.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
