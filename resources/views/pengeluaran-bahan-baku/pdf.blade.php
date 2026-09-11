@php
    $tujuanNama = strtolower(($pengeluaran->gudang->nama ?? '') . ' ' . ($pengeluaran->divisi->nama ?? ''));
    $isProduksiOrCK = str_contains($tujuanNama, 'central kitchen') || str_contains($tujuanNama, 'cold kitchen') || str_contains($tujuanNama, 'produksi');
    $headerBgColor = $isProduksiOrCK ? '#1d4ed8' : '#d97706'; // Biru untuk Produksi/CK/Cold Kitchen, Kuning untuk Bahan Baku Operasional
    $judulDokumen = $isWasted 
        ? 'BERITA ACARA WASTED' 
        : ($isProduksiOrCK ? 'SURAT PERMINTAAN & TRANSFER BAHAN (CK / PRODUKSI)' : 'SURAT PERMINTAAN & TRANSFER BAHAN BAKU');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judulDokumen }} - {{ $pengeluaran->kode_pengeluaran }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 15px;
        }

        /* HEADER COLOR BLOCK */
        .header-block {
            background-color: {{ $headerBgColor }};
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .company-name { font-size: 17px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .sub-company { font-size: 10px; opacity: 0.95; margin-top: 2px; }
        .doc-badge-title { font-size: 14px; font-weight: bold; text-align: right; text-transform: uppercase; letter-spacing: 0.5px; }
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
        .badge-approved { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-draft { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .table-items th {
            background-color: #1e293b;
            color: #ffffff;
            font-size: 9.5px;
            padding: 7px 5px;
            text-align: left;
            text-transform: uppercase;
            border: 1px solid #1e293b;
        }
        .table-items td {
            padding: 6px 5px;
            font-size: 10px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .table-items tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-success { color: #15803d; font-weight: bold; }
        .text-danger { color: #dc2626; font-weight: bold; }
        .text-warning { color: #b45309; font-weight: bold; }
        .text-muted { color: #64748b; }

        .status-pill {
            font-size: 8.5px;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .status-ok { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-shortage { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .status-empty { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        .footer-sign {
            margin-top: 25px;
            width: 100%;
            border-collapse: collapse;
        }
        .footer-sign td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 10.5px;
        }
        .sign-space {
            height: 55px;
        }
        .keterangan-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 10px;
            color: #92400e;
        }
        .footer-note {
            margin-top: 20px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 9px;
            color: #94a3b8;
            width: 100%;
        }
    </style>
</head>
<body>

    {{-- HEADER BLOCK BERWARNA --}}
    <div class="header-block">
        <table class="header-table">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="company-name">CV GAHARU AGUNG SEJAHTERA</div>
                    <div class="sub-company">
                        {{ $isWasted ? 'Berita Acara Pengeluaran Bahan Wasted / Rusak / Busuk' : ($isProduksiOrCK ? 'Distribusi Permintaan Bahan ke Produksi / Kitchen' : 'Sistem Pengelolaan Stok & Distribusi Bahan Baku Antar Gudang') }}
                    </div>
                </td>
                <td style="vertical-align: middle; text-align: right;">
                    <div class="doc-badge-title">{{ $judulDokumen }}</div>
                    <div class="doc-code">#{{ $pengeluaran->kode_pengeluaran }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- STANDAR INFO GRID METADATA --}}
    <table class="info-grid">
        @if($isWasted)
            <tr>
                <td class="info-label">Judul Dokumen</td>
                <td class="info-value"><strong>BERITA ACARA WASTED / RUSAK</strong></td>
                <td class="info-label">Tanggal Laporan</td>
                <td class="info-value"><strong>{{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d F Y H:i') }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Outlet Pemesan</td>
                <td class="info-value">
                    <strong style="color: {{ $headerBgColor }};">{{ $pengeluaran->gudang->nama ?? '-' }}</strong>
                    @if($pengeluaran->divisi) (Divisi: {{ $pengeluaran->divisi->nama }}) @endif
                </td>
                <td class="info-label">Gudang Sumber</td>
                <td class="info-value"><strong>{{ $pengeluaran->gudang->nama ?? '-' }}</strong> (Lokasi Wasted)</td>
            </tr>
            <tr>
                <td class="info-label">Status Dokumen</td>
                <td class="info-value">
                    @if($isApproved)
                        <span class="badge badge-approved">APPROVED / DISETUJUI</span>
                    @else
                        <span class="badge badge-draft">DRAFT / LAPORAN</span>
                    @endif
                </td>
                <td class="info-label">Dicatat Oleh</td>
                <td class="info-value"><strong>{{ $pengeluaran->user->nama_karyawan ?? $pengeluaran->user->name ?? '-' }}</strong></td>
            </tr>
        @else
            <tr>
                <td class="info-label">Judul Dokumen</td>
                <td class="info-value"><strong>{{ $judulDokumen }}</strong></td>
                <td class="info-label">Tanggal Pengajuan</td>
                <td class="info-value"><strong>{{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d F Y H:i') }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Outlet Pemesan</td>
                <td class="info-value">
                    <strong style="color: {{ $headerBgColor }}; font-size: 11.5px;">{{ $pengeluaran->gudang->nama ?? '-' }}</strong>
                    @if($pengeluaran->divisi) (Divisi: {{ $pengeluaran->divisi->nama }}) @endif
                </td>
                <td class="info-label">Gudang Sumber</td>
                <td class="info-value"><strong>{{ $gudangUtama->nama ?? 'Gudang Utama' }}</strong> (Penyedia)</td>
            </tr>
            <tr>
                <td class="info-label">Status Dokumen</td>
                <td class="info-value">
                    @if($isApproved)
                        <span class="badge badge-approved">APPROVED / DISETUJUI</span>
                    @else
                        <span class="badge badge-draft">DRAFT / PERMINTAAN</span>
                    @endif
                </td>
                <td class="info-label">Dicatat Oleh</td>
                <td class="info-value"><strong>{{ $pengeluaran->user->nama_karyawan ?? $pengeluaran->user->name ?? '-' }}</strong></td>
            </tr>
        @endif
    </table>

    @if(!empty($pengeluaran->keterangan) && $pengeluaran->keterangan !== '-')
        <div class="keterangan-box">
            <strong>Catatan / Keterangan:</strong> {{ $pengeluaran->keterangan }}
        </div>
    @endif

    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th style="width: 75px;">Kode</th>
                <th>Nama Bahan / Barang</th>
                <th style="width: 80px;" class="text-end">{{ $isWasted ? 'Qty Wasted' : 'Qty Diminta' }}</th>
                <th style="width: 85px;" class="text-end">{{ $isWasted ? 'Stok di Lokasi' : 'Stok Gd. Utama' }}</th>
                <th style="width: 75px;" class="text-end">Kekurangan</th>
                <th style="width: 85px;" class="text-center">Ketersediaan</th>
                <th style="width: 75px;" class="text-end">Harga (Rp)</th>
                <th style="width: 90px;" class="text-end">Total Nilai</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalDiminta = 0;
                $totalKekurangan = 0;
            @endphp
            @forelse($pengeluaran->details as $index => $detail)
                @php
                    $qtyDiminta = (float) $detail->qty;
                    $stokTersedia = (float) ($detail->stok_tersedia ?? $detail->stok_gudang_utama ?? 0);
                    $kurang     = (float) ($detail->kekurangan ?? max(0, $qtyDiminta - $stokTersedia));
                    $bItem      = $detail->barang;
                    $satuan     = $bItem->satuan ?? ($detail->satuan ?? 'pcs');
                    $satuanBeli = $bItem->satuan_pembelian ?? '';
                    $konversi   = (float) ($bItem->konversi_pembelian ?? 1);
                    $hasKonv    = ($satuanBeli && $konversi > 1 && $satuanBeli !== $satuan);

                    $totalDiminta += $qtyDiminta;
                    $totalKekurangan += $kurang;
                @endphp
                <tr>
                    <td class="text-center text-muted">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $detail->barang->kode_barang ?? '-' }}</td>
                    <td class="fw-bold">
                        {{ $detail->barang->nama ?? '-' }}
                        @if($hasKonv)
                            <div style="font-size: 8px; color: #718096; font-weight: normal;">1 {{ $satuanBeli }} = {{ number_format($konversi, 0, ',', '.') }} {{ $satuan }}</div>
                        @endif
                    </td>
                    <td class="text-end fw-bold">
                        {{ number_format($qtyDiminta, 2, ',', '.') }} <span class="text-muted" style="font-size: 8.5px;">{{ $satuan }}</span>
                        @if($hasKonv)
                            <div style="font-size: 8px; color: #2b6cb0; font-weight: normal;">= {{ number_format($qtyDiminta / $konversi, 2, ',', '.') }} {{ $satuanBeli }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="{{ $stokTersedia > 0 ? '' : 'text-danger' }}">{{ number_format($stokTersedia, 2, ',', '.') }}</span> <span class="text-muted" style="font-size: 8.5px;">{{ $satuan }}</span>
                        @if($hasKonv)
                            <div style="font-size: 8px; color: #718096; font-weight: normal;">= {{ number_format($stokTersedia / $konversi, 2, ',', '.') }} {{ $satuanBeli }}</div>
                        @endif
                    </td>
                    <td class="text-end {{ $kurang > 0 ? 'text-danger' : 'text-success' }}">
                        @if($kurang > 0)
                            -{{ number_format($kurang, 2, ',', '.') }} <span style="font-size: 8.5px;">{{ $satuan }}</span>
                            @if($hasKonv)
                                <div style="font-size: 8px; color: #e53e3e; font-weight: normal;">-{{ number_format($kurang / $konversi, 2, ',', '.') }} {{ $satuanBeli }}</div>
                            @endif
                        @else
                            0,00
                        @endif
                    </td>
                    <td class="text-center">
                        @if($stokTersedia > $qtyDiminta)
                            <span class="status-pill status-ok">Tersedia Penuh</span>
                        @elseif($stokTersedia == $qtyDiminta && $stokTersedia > 0)
                            <span class="status-pill status-shortage">Stok Terakhir (Segera Beli)</span>
                        @elseif($stokTersedia > 0)
                            <span class="status-pill status-empty">Kurang {{ number_format($kurang, 2, ',', '.') }} {{ $satuan }}</span>
                        @else
                            <span class="status-pill status-empty">Habis (0)</span>
                        @endif
                    </td>
                    @php
                        $hargaSat = (float) ($detail->harga_satuan ?? 0);
                        $hppVal = (float) ($detail->calculated_hpp ?? $detail->hpp_total ?? 0);
                        $decSat = ($hargaSat > 0 && ($hargaSat < 1 || ($hargaSat < 100 && floor($hargaSat) != $hargaSat))) ? 4 : 2;
                        $decHpp = ($hppVal > 0 && ($hppVal < 1 || ($hppVal < 100 && floor($hppVal) != $hppVal))) ? 4 : 2;
                    @endphp
                    <td class="text-end text-muted">
                        {{ number_format($hargaSat, $decSat, ',', '.') }}
                    </td>
                    <td class="text-end fw-bold">
                        Rp {{ number_format($hppVal, $decHpp, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted">Tidak ada rincian bahan baku.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f1ede8; font-weight: bold;">
                <td colspan="3" class="text-end">TOTAL KESELURUHAN:</td>
                <td class="text-end">{{ number_format($totalDiminta, 2, ',', '.') }}</td>
                <td></td>
                <td class="text-end {{ $totalKekurangan > 0 ? 'text-danger' : 'text-success' }}">
                    {{ $totalKekurangan > 0 ? '-' . number_format($totalKekurangan, 2, ',', '.') : '0,00' }}
                </td>
                <td></td>
                <td class="text-end">Total HPP:</td>
                <td class="text-end" style="color: #7A4517; font-size: 11px;">
                    Rp {{ number_format($grandTotal, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-sign">
        <tr>
            <td>
                {{ $isWasted ? 'Yang Melaporkan (Kitchen/Outlet)' : 'Pemohon / Peminta' }}<br><br>
                <div class="sign-space"></div>
                ( __________________________ )<br>
                <small class="text-muted">{{ $pengeluaran->divisi ? ($pengeluaran->gudang->nama . ' - ' . $pengeluaran->divisi->nama) : ($pengeluaran->gudang->nama ?? 'Unit Pemohon') }}</small>
            </td>
            <td>
                Kepala Gudang<br><br>
                <div class="sign-space"></div>
                ( __________________________ )<br>
                <small class="text-muted">{{ $isWasted ? 'Petugas / Supervisor' : ($gudangUtama->nama ?? 'Gudang Utama') }}</small>
            </td>
            <td>
                Management<br><br>
                <div class="sign-space"></div>
                ( __________________________ )<br>
                <small class="text-muted">CV Gaharu Agung Sejahtera</small>
            </td>
        </tr>
    <table class="footer-note">
        <tr>
            <td>Dokumen Resmi Sistem ERP - CV Gaharu Agung Sejahtera</td>
            <td style="text-align: right;">Dicetak pada: {{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>

</body>
</html>
