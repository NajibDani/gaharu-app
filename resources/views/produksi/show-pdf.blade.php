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

    @php
        $customerId = $produksi->pesanan ? $produksi->pesanan->customer_id : null;
        $customerNama = $produksi->pesanan && $produksi->pesanan->customer ? ($produksi->pesanan->customer->nama ?? ($produksi->pesanan->customer->name ?? '')) : '';
        $isCustomerKejingga = stripos($customerNama, 'kejingga') !== false;

        $itemsListPdf = [];
        $totalQtyPdf = 0;
        $totalHppPdf = 0;
        $totalOmsetPdf = 0;
        $totalLabaPdf = 0;
        $isAllB2bSameAsHpp = true;

        foreach ($produksi->details as $index => $detail) {
            $qtyVal = floatval($detail->qty);
            $b2bPrice = \App\Models\HargaBarangB2b::getHargaB2bKhusus($customerId, $detail->produk_id);
            $isMengikutiHpp = \App\Models\HargaBarangB2b::isMengikutiHpp($customerId, $detail->produk_id);

            $refHpp = \App\Models\HargaBarangB2b::getRealtimeHpp($detail->produk_id);
            if ($refHpp <= 0 && $detail->produk) {
                $refHpp = floatval($detail->produk->hpp_referensi ?? ($detail->produk->harga_beli ?? 0));
            }
            if ($refHpp <= 0 && $b2bPrice > 0) {
                $refHpp = $b2bPrice;
            }

            $rawHppTotal = floatval($detail->hpp_total);
            $rawUnitHpp = ($qtyVal > 0 && $rawHppTotal > 0) ? ($rawHppTotal / $qtyVal) : $refHpp;

            $isSameHppItem = $isMengikutiHpp || $isCustomerKejingga || ($b2bPrice !== null && abs($b2bPrice - $rawUnitHpp) < 50);

            if ($isSameHppItem && $b2bPrice !== null && $b2bPrice > 0) {
                $hppUnitVal = $b2bPrice;
                $hargaJualUnit = $b2bPrice;
                $effectiveHppTotal = $b2bPrice * $qtyVal;
                $totalHargaJual = $effectiveHppTotal;
            } elseif ($b2bPrice !== null && $b2bPrice > 0 && $rawUnitHpp > ($b2bPrice * 2)) {
                $hppUnitVal = ($refHpp > 0 && $refHpp <= $b2bPrice) ? $refHpp : $b2bPrice;
                $effectiveHppTotal = $hppUnitVal * $qtyVal;
                $hargaJualUnit = $b2bPrice;
                $totalHargaJual = $b2bPrice * $qtyVal;
            } else {
                $hppUnitVal = ($rawUnitHpp > 0) ? $rawUnitHpp : $refHpp;
                $effectiveHppTotal = $hppUnitVal * $qtyVal;
                $hargaJualUnit = ($b2bPrice !== null) ? $b2bPrice : 0;
                $totalHargaJual = ($b2bPrice !== null) ? ($hargaJualUnit * $qtyVal) : 0;
            }

            $isB2bSet = ($b2bPrice !== null);
            $totalLaba = $isB2bSet ? ($totalHargaJual - $effectiveHppTotal) : 0;

            if ($isB2bSet && abs($totalHargaJual - $effectiveHppTotal) > 100 && !$isSameHppItem) {
                $isAllB2bSameAsHpp = false;
            }

            $totalQtyPdf += $qtyVal;
            $totalHppPdf += $effectiveHppTotal;
            if ($isB2bSet) {
                $totalOmsetPdf += $totalHargaJual;
                $totalLabaPdf += $totalLaba;
            }

            $itemsListPdf[] = [
                'detail'            => $detail,
                'qtyVal'            => $qtyVal,
                'hppUnitVal'        => $hppUnitVal,
                'effectiveHppTotal' => $effectiveHppTotal,
                'isB2bSet'          => $isB2bSet,
                'hargaJualUnit'     => $hargaJualUnit,
                'totalHargaJual'    => $totalHargaJual,
                'totalLaba'         => $totalLaba,
            ];
        }
    @endphp

    <h3 class="title">Item Hasil Produksi {{ !$isAllB2bSameAsHpp ? '& Analisis Laba' : '' }}</h3>
    <table class="main-table">
        <thead>
            <tr>
                <th width="25" class="text-center">No</th>
                <th>Nama Produk</th>
                <th class="text-right" width="85">HPP / Unit</th>
                <th class="text-right" width="90">Harga Jual</th>
                <th class="text-center" width="70">Qty Hasil</th>
                <th class="text-right" width="100">Total HPP</th>
                <th class="text-right" width="100">Total Jual</th>
                @if(!$isAllB2bSameAsHpp)
                    <th class="text-right" width="95">Total Laba</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($itemsListPdf as $index => $item)
            @php
                $detail = $item['detail'];
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="fw-bold">{{ $detail->produk->nama ?? 'Produk Tidak Diketahui' }}</td>
                <td class="text-right">Rp {{ number_format($item['hppUnitVal'], 0, ',', '.') }}</td>
                <td class="text-right fw-bold">
                    @if($item['isB2bSet'])
                        Rp {{ number_format($item['hargaJualUnit'], 0, ',', '.') }}
                    @else
                        <span style="color: #b45309; font-size: 8px;">Harap mengatur harga jual B2B lebih dulu</span>
                    @endif
                </td>
                <td class="text-center fw-bold">{{ (fmod($item['qtyVal'], 1) == 0 ? number_format($item['qtyVal'], 0, ',', '.') : number_format($item['qtyVal'], 2, ',', '.')) }} {{ strtoupper($detail->produk->satuan ?? 'PCS') }}</td>
                <td class="text-right">Rp {{ number_format($item['effectiveHppTotal'], 0, ',', '.') }}</td>
                <td class="text-right fw-bold">
                    @if($item['isB2bSet'])
                        Rp {{ number_format($item['totalHargaJual'], 0, ',', '.') }}
                    @else
                        <span style="color: #64748b; font-size: 9px; font-style: italic;">Belum diatur</span>
                    @endif
                </td>
                @if(!$isAllB2bSameAsHpp)
                    <td class="text-right fw-bold {{ $item['totalLaba'] >= 0 ? 'text-success' : 'text-danger' }}" style="padding-right: 10px;">
                        @if($item['isB2bSet'])
                            Rp {{ number_format($item['totalLaba'], 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ $isAllB2bSameAsHpp ? 7 : 8 }}" class="text-center text-muted">Tidak ada detail produk.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="2" class="text-center">Total Keseluruhan</td>
                <td colspan="2"></td>
                <td class="text-center">
                    @if(count($itemsListPdf) === 1 && isset($detail))
                        {{ (fmod($totalQtyPdf, 1) == 0 ? number_format($totalQtyPdf, 0, ',', '.') : number_format($totalQtyPdf, 2, ',', '.')) }} {{ strtoupper($detail->produk->satuan ?? 'PCS') }}
                    @else
                        {{ count($itemsListPdf) }} Item
                    @endif
                </td>
                <td class="text-right">Rp {{ number_format($totalHppPdf, 0, ',', '.') }}</td>
                <td class="text-right">
                    @if($totalOmsetPdf > 0)
                        Rp {{ number_format($totalOmsetPdf, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
                @if(!$isAllB2bSameAsHpp)
                    <td class="text-right text-success" style="padding-right: 10px;">
                        @if($totalOmsetPdf > 0)
                            Rp {{ number_format($totalLabaPdf, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                @endif
            </tr>
        </tfoot>
    </table>

</body>
</html>
