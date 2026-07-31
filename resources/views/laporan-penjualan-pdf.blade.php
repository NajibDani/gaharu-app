<!DOCTYPE html>
<html>
<head>
    <title>Laporan Penjualan B2B</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; color: #1e3a8a; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #666; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; font-size: 11px; }
        .summary-box { width: 100%; margin-bottom: 25px; }
        .summary-box td { width: 33.33%; padding: 10px; border: 1px solid #ddd; background-color: #fafafa; text-align: center; }
        .summary-box td span { display: block; font-size: 10px; text-transform: uppercase; color: #666; font-weight: bold; }
        .summary-box td strong { display: block; font-size: 14px; margin-top: 5px; color: #333; }
        .summary-box td.selesai strong { color: #198754; }
        .summary-box td.pending strong { color: #ffc107; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .main-table th { background-color: #1e3a8a; color: #ffffff; text-align: left; padding: 8px; font-weight: bold; font-size: 11px; }
        .main-table td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 10px; }
        .main-table tr:nth-child(even) { background-color: #fcfcfc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN PENJUALAN B2B</h2>
        <p>CV Gaharu App</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Periode Laporan</strong></td>
            <td width="3%">:</td>
            <td>{{ date('d-m-Y', strtotime($tanggal_mulai)) }} s/d {{ date('d-m-Y', strtotime($tanggal_selesai)) }}</td>
            <td width="15%" class="text-right"><strong>Tanggal Cetak</strong></td>
            <td width="3%">:</td>
            <td width="20%">{{ date('d-m-Y H:i') }}</td>
        </tr>
    </table>

    <table class="summary-box">
        <tr>
            <td>
                <span>Total Omzet Penjualan</span>
                <strong>Rp {{ number_format($total_omzet, 0, ',', '.') }}</strong>
            </td>
            <td class="selesai">
                <span>Pesanan Selesai</span>
                <strong>{{ $pesanan_selesai }} Transaksi</strong>
            </td>
            <td class="pending">
                <span>Jumlah Pelanggan</span>
                <strong>{{ $jumlah_customer }} Pelanggan</strong>
            </td>
        </tr>
    </table>

    @php
        // $pesanans yang dikirim controller sudah difilter hanya status 'selesai',
        // di sini tinggal dikelompokkan berdasarkan customer (sama seperti tampilan web)
        $groupedPesanans = $pesanans->groupBy(function ($p) {
            return $p->customer->nama ?? 'N/A';
        })->sortKeys();
    @endphp

    <table class="main-table">
        <thead>
            <tr>
                <th width="30" class="text-center">No</th>
                <th>Kode Pesanan</th>
                <th>Tanggal</th>
                <th class="text-right">Total HPP</th>
                <th class="text-right">Total Omzet</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedPesanans as $namaCustomer => $items)
            <tr>
                <td colspan="3" class="fw-bold" style="background-color: #eef2ff;">{{ $namaCustomer }} ({{ $items->count() }} Transaksi)</td>
                <td class="text-right fw-bold" style="background-color: #eef2ff; color: #666;">Rp {{ number_format($items->sum('total_hpp'), 0, ',', '.') }}</td>
                <td class="text-right fw-bold" style="background-color: #eef2ff;">Rp {{ number_format($items->sum('details_sum_subtotal'), 0, ',', '.') }}</td>
            </tr>
            @foreach($items as $index => $p)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="fw-bold">{{ $p->kode_pesanan }}</td>
                <td>{{ date('d M Y', strtotime($p->tanggal)) }}</td>
                <td class="text-right" style="color: #666;">Rp {{ number_format($p->total_hpp ?? 0, 0, ',', '.') }}</td>
                <td class="text-right fw-bold">Rp {{ number_format($p->details_sum_subtotal ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            @empty
            <tr>
                <td colspan="5" class="text-center text-muted">Tidak ada data penjualan ditemukan pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>