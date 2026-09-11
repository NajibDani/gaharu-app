@php
    $isKejingga = str_contains(strtolower($pembelian->kode_pembelian ?? ''), 'kjg') 
        || str_contains(strtolower($pembelian->gudang->nama ?? ''), 'kejingga') 
        || ($pembelian->gudang_id == 5);
    $headerBg = $isKejingga ? '#10b981' : '#d97706';
    $companyTitle = $isKejingga ? 'KEJINGGA' : 'CV GAHARU AGUNG SEJAHTERA';
    $companySubtitle = $isKejingga ? 'Pembelanjaan Mandiri Outlet KeJingga ke Supplier' : 'Pengadaan & Logistik Bahan Baku Operasional';
    $docTitle = $isKejingga ? 'PURCHASE ORDER KEJINGGA' : 'PURCHASE ORDER BAHAN BAKU';
    $accentColor = $isKejingga ? '#10b981' : '#d97706';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $docTitle }} - {{ $pembelian->kode_pembelian }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; margin: 0; padding: 15px; }

        /* HEADER COLOR BLOCK - HIJAU MUDA (KEJINGGA MANDIRI) / KUNING AMBER (BAHAN BAKU GAHARU) */
        .header-block {
            background-color: {{ $headerBg }};
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .company-name { font-size: 17px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .sub-company { font-size: 10px; opacity: 0.95; margin-top: 2px; }
        .doc-badge-title { font-size: 15px; font-weight: bold; text-align: right; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-code { font-size: 12px; font-family: monospace; font-weight: bold; text-align: right; margin-top: 3px; }

        /* METADATA INFO GRID */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .info-grid td {
            padding: 7px 10px;
            font-size: 11px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-label { font-weight: bold; color: #475569; width: 18%; text-transform: uppercase; font-size: 10px; }
        .info-value { width: 32%; color: #0f172a; }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 9.5px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-warning { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* ITEMS TABLE */
        .table-items { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 15px; }
        .table-items th {
            background-color: #1e293b;
            color: #ffffff;
            text-align: left;
            padding: 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #1e293b;
        }
        .table-items td {
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
            vertical-align: middle;
        }
        .table-items tr:nth-child(even) { background-color: #f8fafc; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }

        /* TOTAL SECTION */
        .total-box { margin-top: 10px; float: right; width: 45%; }
        .total-table { width: 100%; border-collapse: collapse; }
        .total-table td { padding: 5px 8px; font-size: 11px; }

        /* SIGNATURES & FOOTER */
        .signature-table { width: 100%; border-collapse: collapse; margin-top: 30px; clear: both; }
        .signature-table td { width: 33.33%; text-align: center; vertical-align: top; font-size: 10.5px; }
        .sign-space { height: 55px; }
        .footer-note {
            margin-top: 25px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            font-size: 9px;
            color: #94a3b8;
            width: 100%;
        }
    </style>
</head>
<body>

    {{-- HEADER BLOCK --}}
    <div class="header-block">
        <table class="header-table">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="company-name">{{ $companyTitle }}</div>
                    <div class="sub-company">{{ $companySubtitle }}</div>
                </td>
                <td style="vertical-align: middle; text-align: right;">
                    <div class="doc-badge-title">{{ $docTitle }}</div>
                    <div class="doc-code">#{{ $pembelian->kode_pembelian }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- STANDAR INFO GRID METADATA --}}
    <table class="info-grid">
        <tr>
            <td class="info-label">Judul Dokumen</td>
            <td class="info-value"><strong>{{ $docTitle }}</strong></td>
            <td class="info-label">Tanggal Order</td>
            <td class="info-value"><strong>{{ \Carbon\Carbon::parse($pembelian->tanggal)->format('d F Y') }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Outlet Pemesan</td>
            <td class="info-value"><strong style="color: {{ $accentColor }}; font-size: 12px;">{{ $pembelian->gudang->nama ?? 'Gudang KeJingga' }}</strong></td>
            <td class="info-label">Gudang Sumber</td>
            <td class="info-value"><strong>{{ $pembelian->supplier->nama ?? '-' }}</strong> <span style="font-size: 10px; color: #64748b;">(Supplier / Vendor)</span></td>
        </tr>
        <tr>
            <td class="info-label">Status Dokumen</td>
            <td class="info-value">
                @if($pembelian->is_diterima)
                    <span class="badge badge-success">BARANG DITERIMA</span>
                @else
                    <span class="badge badge-warning">PROSES PENERIMAAN</span>
                @endif
                @if($pembelian->is_lunas)
                    <span class="badge badge-success" style="margin-left: 4px;">LUNAS</span>
                @else
                    <span class="badge badge-danger" style="margin-left: 4px;">BELUM LUNAS</span>
                @endif
            </td>
            <td class="info-label">Pembayaran</td>
            <td class="info-value"><strong>{{ strtoupper($pembelian->metode_pembayaran ?? 'COD') }}</strong></td>
        </tr>
        @if($pembelian->supplier && ($pembelian->supplier->telepon || $pembelian->supplier->alamat))
        <tr>
            <td class="info-label">Kontak Supplier</td>
            <td class="info-value" colspan="3">
                Telp: {{ $pembelian->supplier->telepon ?? '-' }} | Alamat: {{ $pembelian->supplier->alamat ?? '-' }}
            </td>
        </tr>
        @endif
        @if($pembelian->keterangan)
        <tr>
            <td class="info-label">Keterangan</td>
            <td class="info-value" colspan="3">
                {{ $pembelian->keterangan }}
            </td>
        </tr>
        @endif
    </table>

    {{-- TABEL ITEM BARANG --}}
    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">NO</th>
                <th>NAMA BARANG / BAHAN BAKU</th>
                <th style="width: 105px; text-align: center;">QTY DIPESAN</th>
                <th style="width: 105px; text-align: center;">QTY DITERIMA</th>
                <th style="width: 130px; text-align: right;">HARGA / SATUAN</th>
                <th style="width: 135px; text-align: right;">SUBTOTAL</th>
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
                        <strong style="color: #0f172a;">{{ $detail->barang->nama ?? '-' }}</strong>
                        <div style="font-size: 9px; color: #64748b; font-family: monospace;">{{ $detail->barang->kode_barang ?? '' }}</div>
                    </td>
                    <td class="text-center">
                        <strong>{{ number_format($detail->qty, 2, ',', '.') }} {{ $unitDisplay }}</strong>
                        @if($hasKonv)
                            <div style="font-size: 8.5px; color: {{ $accentColor }};">= {{ number_format($detail->qty * $konv, 2, ',', '.') }} {{ $sUtama }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        <strong>{{ number_format($qtyDiterima, 2, ',', '.') }} {{ $unitDisplay }}</strong>
                        @if($hasKonv)
                            <div style="font-size: 8.5px; color: {{ $accentColor }};">= {{ number_format($qtyDiterima * $konv, 2, ',', '.') }} {{ $sUtama }}</div>
                        @endif
                        @if($detail->tanggal_diterima)
                            <div style="font-size: 8px; color: #059669; margin-top: 2px;">Tgl: {{ \Carbon\Carbon::parse($detail->tanggal_diterima)->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        Rp {{ number_format($detail->harga_per_qty, 0, ',', '.') }} / {{ $unitDisplay }}
                        @if($hasKonv && $konv > 0)
                            <div style="font-size: 8.5px; color: #718096;">(~Rp {{ number_format($detail->harga_per_qty / $konv, 2, ',', '.') }} / {{ $sUtama }})</div>
                        @endif
                    </td>
                    <td class="text-end fw-bold">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTAL PEMBELIAN --}}
    <div class="total-box">
        <table class="total-table">
            <tr>
                <td class="fw-bold">Total Pembelian:</td>
                <td class="text-end fw-bold" style="font-size: 13px; color: {{ $accentColor }};">
                    Rp {{ number_format($pembelian->total ?? $totalSub, 0, ',', '.') }}
                </td>
            </tr>
            @if($pembelian->nominal_dp > 0)
            <tr>
                <td>Uang Muka (DP):</td>
                <td class="text-end" style="color: #15803d; font-weight: bold;">Rp {{ number_format($pembelian->nominal_dp, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fw-bold">Sisa Tagihan:</td>
                <td class="text-end fw-bold" style="color: #b91c1c;">Rp {{ number_format($pembelian->total - $pembelian->nominal_dp, 0, ',', '.') }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- TANDA TANGAN 3 PIHAK --}}
    <table class="signature-table">
        <tr>
            <td>
                Pemohon (Operasional)<br><br>
                <div class="sign-space"></div>
                <strong>( {{ $pembelian->user->nama ?? $pembelian->user->name ?? 'Staff Operasional' }} )</strong><br>
                <span style="font-size: 9.5px; color: #64748b;">{{ $pembelian->gudang->nama ?? 'Unit Pemohon' }}</span>
            </td>
            <td>
                Supplier / Vendor<br><br>
                <div class="sign-space"></div>
                <strong>( {{ $pembelian->supplier->nama ?? 'Pemasok Bahan' }} )</strong><br>
                <span style="font-size: 9.5px; color: #64748b;">Penyedia Bahan Baku</span>
            </td>
            <td>
                Gudang Penerima<br><br>
                <div class="sign-space"></div>
                <strong>( {{ $pembelian->penerimaDiterima->nama ?? $pembelian->penerimaDiterima->name ?? 'Petugas Gudang' }} )</strong><br>
                <span style="font-size: 9.5px; color: #64748b;">{{ $isKejingga ? 'Gudang KeJingga' : 'CV Gaharu Agung Sejahtera' }}</span>
            </td>
        </tr>
    </table>

    <table class="footer-note">
        <tr>
            <td>Dokumen Resmi Sistem ERP - {{ $isKejingga ? 'Outlet KeJingga' : 'CV Gaharu Agung Sejahtera' }}</td>
            <td style="text-align: right;">Dicetak pada: {{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>

</body>
</html>
