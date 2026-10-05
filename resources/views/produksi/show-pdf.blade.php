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

    <h3 class="title">Item Hasil Produksi &amp; Analisis Laba</h3>
    <table class="main-table">
        <thead>
            <tr>
                <th width="25" class="text-center">No</th>
                <th>Nama Produk</th>
                <th class="text-right" width="80">HPP / Unit</th>
                <th class="text-right" width="85">Harga Jual</th>
                <th class="text-center" width="65">Qty Hasil</th>
                <th class="text-right" width="90">Total HPP</th>
                <th class="text-right" width="95">Total Jual</th>
                <th class="text-right" width="95">Total Laba</th>
            </tr>
        </thead>
        <tbody>
            @php
                $customerId = $produksi->pesanan ? $produksi->pesanan->customer_id : null;
                $totalQtyPdf = 0;
                $totalHppPdf = 0;
                $totalOmsetPdf = 0;
                $totalLabaPdf = 0;
            @endphp
            @forelse($produksi->details as $index => $detail)
            @php
                $qtyVal = floatval($detail->qty);
                $hppTotalVal = floatval($detail->hpp_total);
                $hppUnitVal = ($qtyVal > 0 && $hppTotalVal > 0) ? ($hppTotalVal / $qtyVal) : floatval($detail->produk->hpp_referensi ?? ($detail->produk->harga_beli ?? 0));
                
                // Cek pengaturan harga jual B2B per customer/outlet
                $b2bPrice = \App\Models\HargaBarangB2b::getHargaB2bKhusus($customerId, $detail->produk_id);
                $isB2bSet = ($b2bPrice !== null);
                $hargaJualUnit = $isB2bSet ? $b2bPrice : 0;
                $totalHargaJual = $isB2bSet ? ($hargaJualUnit * $qtyVal) : 0;
                $effectiveHppTotal = ($hppTotalVal > 0) ? $hppTotalVal : ($hppUnitVal * $qtyVal);
                $totalLaba = $isB2bSet ? ($totalHargaJual - $effectiveHppTotal) : 0;

                $totalQtyPdf += $qtyVal;
                $totalHppPdf += $effectiveHppTotal;
                if ($isB2bSet) {
                    $totalOmsetPdf += $totalHargaJual;
                    $totalLabaPdf += $totalLaba;
                }
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="fw-bold">{{ $detail->produk->nama ?? 'Produk Tidak Diketahui' }}</td>
                <td class="text-right">Rp {{ number_format($hppUnitVal, 0, ',', '.') }}</td>
                <td class="text-right fw-bold">
                    @if($isB2bSet)
                        Rp {{ number_format($hargaJualUnit, 0, ',', '.') }}
                    @else
                        <span style="color: #b45309; font-size: 8px;">Harap mengatur harga jual B2B lebih dulu</span>
                    @endif
                </td>
                <td class="text-center fw-bold">{{ number_format($detail->qty, 0, ',', '.') }} Unit</td>
                <td class="text-right">Rp {{ number_format($effectiveHppTotal, 0, ',', '.') }}</td>
                <td class="text-right fw-bold">
                    @if($isB2bSet)
                        Rp {{ number_format($totalHargaJual, 0, ',', '.') }}
                    @else
                        <span style="color: #64748b; font-size: 9px; font-style: italic;">Belum diatur</span>
                    @endif
                </td>
                <td class="text-right fw-bold {{ $totalLaba >= 0 ? 'text-success' : 'text-danger' }}" style="padding-right: 10px;">
                    @if($isB2bSet)
                        Rp {{ number_format($totalLaba, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-muted">Tidak ada detail produk.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="2" class="text-center">Total Keseluruhan</td>
                <td colspan="2"></td>
                <td class="text-center">{{ number_format($totalQtyPdf, 0, ',', '.') }} Unit</td>
                <td class="text-right">Rp {{ number_format($totalHppPdf, 0, ',', '.') }}</td>
                <td class="text-right">
                    @if($totalOmsetPdf > 0)
                        Rp {{ number_format($totalOmsetPdf, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right text-success" style="padding-right: 10px;">
                    @if($totalOmsetPdf > 0)
                        Rp {{ number_format($totalLabaPdf, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
