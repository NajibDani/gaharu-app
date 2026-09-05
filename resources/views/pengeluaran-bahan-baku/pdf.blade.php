<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $isWasted ? 'Berita Acara Wasted' : 'Surat Permintaan & Transfer' }} - {{ $pengeluaran->kode_pengeluaran }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #7A4517;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header h2 {
            margin: 0;
            color: #7A4517;
            font-size: 17px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 3px 0 0;
            font-size: 10px;
            color: #555;
        }
        .badge-status {
            display: inline-block;
            padding: 3px 10px;
            font-weight: bold;
            font-size: 10px;
            border-radius: 4px;
            margin-top: 4px;
        }
        .badge-approved { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .badge-draft { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }

        .table-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .table-info td {
            padding: 4px 6px;
            font-size: 10.5px;
            vertical-align: top;
        }
        .table-info .lbl {
            font-weight: bold;
            color: #555;
            width: 16%;
        }
        .table-info .val {
            width: 34%;
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .table-items th {
            background-color: #7A4517;
            color: #ffffff;
            font-size: 9.5px;
            padding: 6px 5px;
            text-align: left;
            text-transform: uppercase;
            border: 1px solid #7A4517;
        }
        .table-items td {
            padding: 6px 5px;
            font-size: 10px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        .table-items tr:nth-child(even) {
            background-color: #faf8f5;
        }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-success { color: #198754; font-weight: bold; }
        .text-danger { color: #dc3545; font-weight: bold; }
        .text-warning { color: #b45309; font-weight: bold; }
        .text-muted { color: #6c757d; }

        .status-pill {
            font-size: 8.5px;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .status-ok { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-shortage { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .status-empty { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

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
            height: 60px;
        }
        .keterangan-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>CV GAHARU AGUNG SEJAHTERA</h2>
        <p>{{ $isWasted ? 'Berita Acara Pengeluaran Bahan Wasted / Rusak / Busuk - Lokasi Operasional' : 'Surat Permintaan & Transfer Bahan Baku - Sistem Pengelolaan Stok & Distribusi Antar Gudang' }}</p>
    </div>

    <table class="table-info">
        @if($isWasted)
            <tr>
                <td class="lbl">Kode Dokumen</td>
                <td class="val">: <strong>{{ $pengeluaran->kode_pengeluaran }}</strong></td>
                <td class="lbl">Tanggal Laporan</td>
                <td class="val">: {{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d F Y H:i') }}</td>
            </tr>
            <tr>
                <td class="lbl">Lokasi Wasted</td>
                <td class="val">: 
                    <strong style="color: #dc3545;">{{ $pengeluaran->gudang->nama ?? '-' }}</strong>
                    @if($pengeluaran->divisi)
                        (Divisi: {{ $pengeluaran->divisi->nama }})
                    @endif
                </td>
                <td class="lbl">Jenis Pengeluaran</td>
                <td class="val">: <strong style="color: #dc3545;">Wasted / Busuk / Rusak</strong></td>
            </tr>
            <tr>
                <td class="lbl">Status Dokumen</td>
                <td class="val">: 
                    @if($isApproved)
                        <span class="badge-status badge-approved">APPROVED / DISETUJUI</span>
                    @else
                        <span class="badge-status badge-draft">DRAFT / LAPORAN</span>
                    @endif
                </td>
                <td class="lbl">Dicatat Oleh</td>
                <td class="val">: {{ $pengeluaran->user->nama_karyawan ?? $pengeluaran->user->name ?? '-' }}</td>
            </tr>
        @else
            <tr>
                <td class="lbl">Kode Dokumen</td>
                <td class="val">: <strong>{{ $pengeluaran->kode_pengeluaran }}</strong></td>
                <td class="lbl">Tanggal Pengajuan</td>
                <td class="val">: {{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d F Y H:i') }}</td>
            </tr>
            <tr>
                <td class="lbl">Gudang Sumber</td>
                <td class="val">: <strong>{{ $gudangUtama->nama ?? 'Gudang Utama' }}</strong> (Penyedia)</td>
                <td class="lbl">Gudang &amp; Divisi Tujuan</td>
                <td class="val">: 
                    <strong>{{ $pengeluaran->gudang->nama ?? '-' }}</strong>
                    @if($pengeluaran->divisi)
                        (Divisi: {{ $pengeluaran->divisi->nama }})
                    @endif
                </td>
            </tr>
            <tr>
                <td class="lbl">Status Dokumen</td>
                <td class="val">: 
                    @if($isApproved)
                        <span class="badge-status badge-approved">APPROVED / DISETUJUI</span>
                    @else
                        <span class="badge-status badge-draft">DRAFT / PERMINTAAN</span>
                    @endif
                </td>
                <td class="lbl">Dicatat Oleh</td>
                <td class="val">: {{ $pengeluaran->user->nama_karyawan ?? $pengeluaran->user->name ?? '-' }}</td>
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
    </table>

</body>
</html>
