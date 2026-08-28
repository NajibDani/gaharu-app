<!DOCTYPE html>
<html>
<head>
    <title>Laporan Laba Rugi</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 16px; color: #1e3a8a; }
        .header h3 { margin: 5px 0 0 0; font-size: 13px; color: #333; }
        .header p { margin: 5px 0 0 0; font-size: 11px; color: #666; }
        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; font-size: 10px; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .main-table th { background-color: #1e3a8a; color: #ffffff; text-align: left; padding: 8px; font-weight: bold; font-size: 10px; border: 1px solid #ddd; }
        .main-table td { padding: 8px; border: 1px solid #ddd; font-size: 9px; }
        .main-table tr.total-row { background-color: #f8fafc; font-weight: bold; }
        .main-table tr.subtotal-row { background-color: #fafbfc; font-weight: 600; font-size: 9px; }
        .main-table th.sub-header { background-color: #f1f5f9; color: #475569; font-size: 9px; font-weight: 700; }
        .footer-laba { margin-top: 20px; padding: 12px; color: #ffffff; background-color: #1e3a8a; font-weight: bold; font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .indent { padding-left: 20px !important; }
    </style>
</head>
<body>

    <div class="header">
        <h2>CV GAHARU AGUNG SEJAHTERA</h2>
        <h3>LAPORAN LABA RUGI</h3>
        <p>Periode: {{ date('F', mktime(0, 0, 0, $bulan, 1)) }} {{ $tahun }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Tanggal Cetak</strong></td>
            <td width="3%">:</td>
            <td>{{ date('d-m-Y H:i') }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th colspan="2">PENDAPATAN</th>
            </tr>
        </thead>
        <tbody>
            {{-- Penjualan B2B --}}
            <tr>
                <td colspan="2" class="sub-header" style="background-color: #f1f5f9; font-weight: 700; font-size: 9px; color: #475569;">Penjualan B2B</td>
            </tr>
            @forelse($detailsPenjualanB2b as $item)
                <tr>
                    <td class="indent">{{ $item->kode_akun }} - {{ $item->nama_akun }}</td>
                    <td class="text-right">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center indent" style="color: #999; font-style: italic;">Tidak ada transaksi penjualan B2B</td>
                </tr>
            @endforelse
            <tr class="subtotal-row">
                <td class="indent">Subtotal Penjualan B2B</td>
                <td class="text-right">Rp {{ number_format($totalPenjualanB2b ?? 0, 0, ',', '.') }}</td>
            </tr>

            {{-- Pendapatan Lainnya --}}
            <tr>
                <td colspan="2" class="sub-header" style="background-color: #f1f5f9; font-weight: 700; font-size: 9px; color: #475569;">Pendapatan Lainnya</td>
            </tr>
            @forelse($detailsPendapatanLain as $item)
                <tr>
                    <td class="indent">{{ $item->kode }} - {{ $item->nama }}</td>
                    <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center indent" style="color: #999; font-style: italic;">Tidak ada transaksi pendapatan lainnya</td>
                </tr>
            @endforelse
            <tr class="subtotal-row">
                <td class="indent">Subtotal Pendapatan Lainnya</td>
                <td class="text-right">Rp {{ number_format($totalPendapatanLain ?? 0, 0, ',', '.') }}</td>
            </tr>

            {{-- Total Pendapatan --}}
            <tr class="total-row">
                <td>TOTAL PENDAPATAN</td>
                <td class="text-right">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</td>
            </tr>

            <tr>
                <td colspan="2" style="border: none; padding: 10px;"></td>
            </tr>

            {{-- HARGA POKOK PENJUALAN --}}
            <tr style="border-top: 1px solid #ddd;">
                <th colspan="2" style="background-color: #c53030;">HARGA POKOK PENJUALAN</th>
            </tr>
            @forelse($detailsHpp as $item)
                <tr>
                    <td>{{ $item->kode }} - {{ $item->nama }}</td>
                    <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center" style="color: #999; font-style: italic;">Tidak ada transaksi HPP</td>
                </tr>
            @endforelse
            <tr class="total-row" style="color: #c53030;">
                <td>TOTAL HPP</td>
                <td class="text-right">(Rp {{ number_format($totalHpp ?? 0, 0, ',', '.') }})</td>
            </tr>

            <tr>
                <td colspan="2" style="border: none; padding: 10px;"></td>
            </tr>

            {{-- LABA KOTOR --}}
            @php $labaKotorCalc = $labaKotor ?? (($totalPendapatan ?? 0) - ($totalHpp ?? 0)); @endphp
            <tr style="background-color: #ebf8ff; font-weight: 800; color: #2b6cb0;">
                <td style="border-top: 2px solid #3182ce; border-bottom: 2px solid #3182ce; padding: 10px 8px; font-size: 11px;">LABA KOTOR</td>
                <td class="text-right" style="border-top: 2px solid #3182ce; border-bottom: 2px solid #3182ce; padding: 10px 8px; font-size: 11px;">Rp {{ number_format($labaKotorCalc, 0, ',', '.') }}</td>
            </tr>

            <tr>
                <td colspan="2" style="border: none; padding: 10px;"></td>
            </tr>

            {{-- BEBAN OPERASIONAL --}}
            <tr style="border-top: 1px solid #ddd;">
                <th colspan="2" style="background-color: #b91c1c;">BEBAN OPERASIONAL</th>
            </tr>
            @foreach($detailsBeban as $item)
                <tr>
                    <td>{{ $item->kode }} - {{ $item->nama }}</td>
                    <td class="text-right">Rp {{ number_format($item->saldo, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row" style="color: #b91c1c;">
                <td>TOTAL BEBAN</td>
                <td class="text-right">(Rp {{ number_format($totalBeban, 0, ',', '.') }})</td>
            </tr>
        </tbody>
    </table>

    @php $labaBersih = ($labaKotorCalc ?? $totalPendapatan) - $totalBeban; @endphp
    <table width="100%" style="margin-top: 20px; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px; background-color: {{ $labaBersih >= 0 ? '#1e3a8a' : '#b91c1c' }}; color: white; font-weight: bold; font-size: 11px;">
                LABA (RUGI) BERSIH
            </td>
            <td class="text-right" style="padding: 10px; background-color: {{ $labaBersih >= 0 ? '#1e3a8a' : '#b91c1c' }}; color: white; font-weight: bold; font-size: 12px; width: 200px;">
                Rp {{ number_format($labaBersih, 0, ',', '.') }}
            </td>
        </tr>
    </table>

</body>
</html>
