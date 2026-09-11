<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>PURCHASE ORDER CENTRAL KITCHEN - {{ $pesanan->kode_pesanan }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; margin: 0; padding: 15px; }
        
        /* HEADER COLOR BLOCK - BIRU (PRODUKSI / CENTRAL KITCHEN) */
        .header-block {
            background-color: #1d4ed8;
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .company-name { font-size: 17px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .sub-company { font-size: 10px; opacity: 0.9; margin-top: 2px; }
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
        .badge-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-proses { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-selesai { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-batal { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-batch { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 9.5px; }

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

        /* SIGNATURES & FOOTER */
        .signature-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
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

    {{-- HEADER BLOCK - WARNA BIRU (PRODUKSI / CENTRAL KITCHEN) --}}
    <div class="header-block">
        <table class="header-table">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="company-name">CV GAHARU AGUNG SEJAHTERA</div>
                    <div class="sub-company">Central Kitchen Production &amp; Internal Order Management</div>
                </td>
                <td style="vertical-align: middle; text-align: right;">
                    <div class="doc-badge-title">PURCHASE ORDER CENTRAL KITCHEN</div>
                    <div class="doc-code">#{{ $pesanan->kode_pesanan }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- STANDAR INFO GRID METADATA --}}
    <table class="info-grid">
        <tr>
            <td class="info-label">Judul Dokumen</td>
            <td class="info-value"><strong>PURCHASE ORDER CENTRAL KITCHEN</strong></td>
            <td class="info-label">Tanggal Order</td>
            <td class="info-value"><strong>{{ \Carbon\Carbon::parse($pesanan->tanggal)->format('d F Y') }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Outlet Pemesan</td>
            <td class="info-value"><strong style="color: #1d4ed8; font-size: 12px;">{{ $pesanan->customer->nama ?? '-' }}</strong></td>
            <td class="info-label">Gudang Sumber</td>
            <td class="info-value"><strong>{{ $pesanan->divisi->nama ?? 'Gudang Central Kitchen' }}</strong> (Penyedia)</td>
        </tr>
        <tr>
            <td class="info-label">Status Dokumen</td>
            <td class="info-value">
                @php
                    $st = strtolower($pesanan->status_pesanan);
                    $badgeClass = 'badge-pending';
                    if(in_array($st, ['selesai', 'approved', 'disetujui'])) $badgeClass = 'badge-selesai';
                    elseif(in_array($st, ['diproses', 'proses', 'dikirim', 'siap kirim'])) $badgeClass = 'badge-proses';
                    elseif($st == 'batal' || $st == 'dibatalkan') $badgeClass = 'badge-batal';
                @endphp
                <span class="badge {{ $badgeClass }}">{{ strtoupper($pesanan->status_pesanan) }}</span>
            </td>
            <td class="info-label">Estimasi Kirim</td>
            <td class="info-value"><strong>{{ \Carbon\Carbon::parse($pesanan->estimasi_kirim)->format('d F Y') }}</strong></td>
        </tr>
    </table>

    {{-- TABEL ITEM PESANAN --}}
    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">NO</th>
                <th style="width: 85px;">KODE ITEM</th>
                <th>NAMA BAHAN SETENGAH JADI</th>
                <th style="width: 170px; text-align: center;">KONVERSI RESEP / BATCH</th>
                <th style="width: 120px; text-align: right;">TARGET QTY</th>
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
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $detail->produk->kode_barang ?? '-' }}</td>
                    <td><strong style="color: #0f172a;">{{ $detail->produk->nama ?? 'Item Hapus' }}</strong></td>
                    <td class="text-center">
                        @if($outQty > 0)
                            <span class="badge-batch">{{ $resepText }}</span>
                        @else
                            <span style="color: #94a3b8; font-size: 9.5px;">Standard (Non-Resep)</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold" style="color: #0f172a; font-size: 11px;">
                        {{ (fmod($detail->qty, 1) == 0) ? number_format($detail->qty, 0, ',', '.') : number_format($detail->qty, 2, ',', '.') }} {{ $detail->produk->satuan ?? '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TANDA TANGAN 3 PIHAK --}}
    <table class="signature-table">
        <tr>
            <td>
                Pemesan (Outlet)<br><br>
                <div class="sign-space"></div>
                <strong>( {{ $pesanan->customer->nama ?? 'Kepala Outlet' }} )</strong><br>
                <span style="font-size: 9.5px; color: #64748b;">Unit Pemesan</span>
            </td>
            <td>
                Central Kitchen<br><br>
                <div class="sign-space"></div>
                <strong>( Tim Produksi CK )</strong><br>
                <span style="font-size: 9.5px; color: #64748b;">Gudang Central Kitchen</span>
            </td>
            <td>
                Management / Otorisasi<br><br>
                <div class="sign-space"></div>
                <strong>( Kepala Central Kitchen )</strong><br>
                <span style="font-size: 9.5px; color: #64748b;">CV Gaharu Agung Sejahtera</span>
            </td>
        </tr>
    </table>

    <table class="footer-note">
        <tr>
            <td>Dokumen Resmi Sistem ERP - CV Gaharu Agung Sejahtera</td>
            <td style="text-align: right;">Dicetak pada: {{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>

</body>
</html>
