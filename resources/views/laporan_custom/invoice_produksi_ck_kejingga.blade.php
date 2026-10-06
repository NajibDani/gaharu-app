@php
    $firstTx = $transactions->first();
    $noInvoice = $firstTx->no_invoice ?? ('INV-CK-KEJINGGA-' . date('YmdHis'));
    $tglBayar = $firstTx->tanggal_pembayaran ? $firstTx->tanggal_pembayaran->format('d/m/Y H:i') : date('d/m/Y H:i');
    $metodeBayar = $firstTx->metode_pembayaran ?? 'Transfer Bank';
    $catatanBayar = $firstTx->catatan_pembayaran ?? '-';
    $dibayarBy = $firstTx->dibayarByUser->nama_karyawan ?? ($firstTx->dibayarByUser->name ?? 'Admin Keuangan');
    $isLunas = $transactions->every(fn($t) => $t->status_pembayaran === 'lunas');
    $isPdf = $isPdf ?? false;

    // Ringkasan daftar Kode Produksi dan Periode
    $kodeProduksiList = $transactions->pluck('kode_produksi')->filter()->unique()->values();
    $minDate = $transactions->min('tanggal_mulai');
    $maxDate = $transactions->max('tanggal_mulai');
    $periodeStr = '-';
    if ($minDate && $maxDate) {
        $d1 = \Carbon\Carbon::parse($minDate);
        $d2 = \Carbon\Carbon::parse($maxDate);
        if ($d1->format('Y-m-d') === $d2->format('Y-m-d')) {
            $periodeStr = $d1->format('d/m/Y');
        } else {
            $periodeStr = $d1->format('d/m/Y') . ' - ' . $d2->format('d/m/Y');
        }
    }

    // Ringkasan Barang Produksi (Aggregated by Barang ID / Nama)
    $summaryItems = [];
    $grandTotal = 0;

    foreach ($transactions as $tx) {
        foreach ($tx->details as $detail) {
            $qtyNum = (float)($detail->jumlah ?? ($detail->qty ?? 0));
            if ($qtyNum <= 0) {
                continue;
            }

            $barangObj = $detail->barang ?? $detail->produk;
            $barangId = $detail->barang_id ?? ($detail->produk_id ?? ($barangObj->id ?? $detail->id));
            $namaBarang = strtoupper($barangObj->nama ?? ($detail->nama_barang ?? 'BARANG TANPA NAMA'));
            $satuan = strtoupper($barangObj->satuan ?? ($detail->satuan ?? ''));
            
            $detailNilai = ($detail->hpp_total && $detail->hpp_total > 0) ? (float)$detail->hpp_total : (float)($detail->subtotal ?? ($detail->total_harga ?? 0));

            if (!isset($summaryItems[$barangId])) {
                $summaryItems[$barangId] = [
                    'nama' => $namaBarang,
                    'satuan' => $satuan,
                    'total_qty' => 0,
                    'total_nilai' => 0,
                ];
            }

            $summaryItems[$barangId]['total_qty'] += $qtyNum;
            $summaryItems[$barangId]['total_nilai'] += $detailNilai;
            $grandTotal += $detailNilai;
        }
    }

    // Helper terbilang dengan spasi yang rapi
    if (!function_exists('terbilangRupiahInvoice')) {
        function terbilangRupiahInvoice($angka) {
            $angka = abs((float)$angka);
            $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
            if ($angka < 12) {
                return $baca[(int)$angka];
            } else if ($angka < 20) {
                return terbilangRupiahInvoice($angka - 10) . ' Belas';
            } else if ($angka < 100) {
                $sisa = $angka % 10;
                return terbilangRupiahInvoice(floor($angka / 10)) . ' Puluh' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            } else if ($angka < 200) {
                $sisa = $angka - 100;
                return 'Seratus' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            } else if ($angka < 1000) {
                $sisa = $angka % 100;
                return terbilangRupiahInvoice(floor($angka / 100)) . ' Ratus' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            } else if ($angka < 2000) {
                $sisa = $angka - 1000;
                return 'Seribu' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            } else if ($angka < 1000000) {
                $sisa = $angka % 1000;
                return terbilangRupiahInvoice(floor($angka / 1000)) . ' Ribu' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            } else if ($angka < 1000000000) {
                $sisa = fmod($angka, 1000000);
                return terbilangRupiahInvoice(floor($angka / 1000000)) . ' Juta' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            } else if ($angka < 1000000000000) {
                $sisa = fmod($angka, 1000000000);
                return terbilangRupiahInvoice(floor($angka / 1000000000)) . ' Miliar' . ($sisa ? ' ' . terbilangRupiahInvoice($sisa) : '');
            }
            return '';
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pembayaran CK Kejingga - {{ $noInvoice }}</title>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <style>
        @page {
            margin: 8mm 10mm 8mm 10mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            background-color: {{ $isPdf ? '#ffffff' : '#f8fafc' }};
            font-size: 11px;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        .no-print-bar {
            max-width: 850px;
            margin: 15px auto 10px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }

        .btn-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-back { background-color: #64748b; color: #ffffff; }
        .btn-print { background-color: #2563eb; color: #ffffff; }
        .btn-pdf { background-color: #dc2626; color: #ffffff; }
        .btn-jpg { background-color: #059669; color: #ffffff; }

        .invoice-card {
            background: #ffffff;
            max-width: {{ $isPdf ? '100%' : '850px' }};
            margin: {{ $isPdf ? '0' : '10px auto 20px auto' }};
            padding: {{ $isPdf ? '10px 15px' : '22px 28px' }};
            border-radius: {{ $isPdf ? '0' : '8px' }};
            box-shadow: {{ $isPdf ? 'none' : '0 3px 12px rgba(0,0,0,0.04)' }};
            border: {{ $isPdf ? 'none' : '1px solid #e2e8f0' }};
        }

        /* Layout Tables */
        .layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .header-border {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .brand-logo {
            font-size: 20px;
            font-weight: 800;
            color: #2563eb;
            letter-spacing: 0.5px;
        }

        .brand-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 1px;
        }

        .invoice-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .invoice-no {
            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
            text-align: right;
            font-family: monospace;
            margin-top: 2px;
        }

        .info-box {
            background-color: #f0f6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 8px 12px;
            vertical-align: top;
        }

        .info-title {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1e40af;
            margin-bottom: 6px;
            border-bottom: 1px dashed #93c5fd;
            padding-bottom: 3px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info-label {
            color: #64748b;
            width: 110px;
        }

        .info-val {
            font-weight: 600;
            color: #1e293b;
        }

        .badge-lunas {
            display: inline-block;
            background-color: #16a34a;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 15px;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 15px;
            letter-spacing: 0.5px;
        }

        .section-header {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            margin-top: 10px;
        }

        /* Items Table */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .table-items th {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 10.5px;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #2563eb;
        }

        .table-items td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }

        .table-items tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .table-items tfoot td {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 7px 8px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 700; }

        /* Terbilang Line */
        .terbilang-bar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 6px 12px;
            font-size: 10.5px;
            margin-bottom: 15px;
        }

        /* Signatures Compact */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }

        .sig-title {
            font-size: 10px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 35px;
        }

        .sig-line {
            border-bottom: 1px solid #94a3b8;
            width: 65%;
            margin: 0 auto 3px auto;
        }

        .sig-role {
            font-size: 9.5px;
            color: #64748b;
        }

        .footer-note {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }

        @media print {
            body {
                background-color: #ffffff !important;
            }
            .no-print-bar {
                display: none !important;
            }
            .invoice-card {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

    @if(!$isPdf)
    <!-- NO-PRINT TOOLBAR -->
    <div class="no-print-bar">
        <div>
            <a href="{{ route('laporan.laporan-custom.produksi-ck-kejingga') }}" class="btn-toolbar btn-back">
                &larr; Kembali ke Laporan
            </a>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn-toolbar btn-print">
                &#128438; Cetak Invoice (Print)
            </button>
            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->all(), ['pdf' => 1])) }}" class="btn-toolbar btn-pdf" target="_blank">
                &#128196; Download PDF
            </a>
            <button onclick="downloadJPG()" class="btn-toolbar btn-jpg">
                &#128444; Download JPG
            </button>
        </div>
    </div>
    @endif

    <div class="invoice-card" id="invoiceCard">
        <!-- HEADER BRAND & INVOICE NUMBER -->
        <table class="layout-table header-border">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="brand-logo">GAHARU ERP</div>
                    <div class="brand-sub">Central Kitchen Gaharu &bull; Sistem Produksi & Logistik</div>
                </td>
                <td style="vertical-align: middle; text-align: right;">
                    <div class="invoice-title">FAKTUR / INVOICE PEMBAYARAN PRODUKSI</div>
                    <div class="invoice-no">{{ $noInvoice }}</div>
                </td>
            </tr>
        </table>

        <!-- METADATA INFORMATION GRID -->
        <table class="layout-table" style="margin-bottom: 12px;">
            <tr>
                <td style="width: 50%; padding-right: 6px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-title">Informasi Pengirim & Penerima</div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Gudang Asal</td>
                                <td class="info-val">: Central Kitchen Gaharu</td>
                            </tr>
                            <tr>
                                <td class="info-label">Tujuan / Outlet</td>
                                <td class="info-val">: {{ $firstTx->gudangHasil->nama ?? 'Gudang KeJingga' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Jumlah Produksi</td>
                                <td class="info-val">: {{ $transactions->count() }} Transaksi Produksi</td>
                            </tr>
                            <tr>
                                <td class="info-label">Daftar Kode WO</td>
                                <td class="info-val" style="font-size: 10px;">: {{ $kodeProduksiList->implode(', ') }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Periode Produksi</td>
                                <td class="info-val">: {{ $periodeStr }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 6px; vertical-align: top;">
                    <div class="info-box">
                        <table style="width: 100%;">
                            <tr>
                                <td class="info-title" style="border: none; padding: 0;">Status Pembayaran</td>
                                <td style="text-align: right;">
                                    @if($isLunas)
                                        <span class="badge-lunas">&check; LUNAS</span>
                                    @else
                                        <span class="badge-pending">&bull; BELUM DIBAYAR</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px dashed #93c5fd; margin-bottom: 6px; margin-top: 3px;"></div>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Tgl Pembayaran</td>
                                <td class="info-val">: {{ $tglBayar }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Metode Bayar</td>
                                <td class="info-val">: {{ $metodeBayar }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Diproses Oleh</td>
                                <td class="info-val">: {{ $dibayarBy }}</td>
                            </tr>
                            @if(!empty($catatanBayar) && $catatanBayar !== '-')
                                <tr>
                                    <td class="info-label">Catatan</td>
                                    <td class="info-val" style="color: #2563eb;">: {{ $catatanBayar }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- RINGKASAN BARANG -->
        <div class="section-header">Ringkasan Hasil Produksi / Barang</div>
        <table class="table-items">
            <thead>
                <tr>
                    <th class="text-center" style="width: 32px;">NO</th>
                    <th class="text-left">NAMA BARANG / HASIL PRODUKSI</th>
                    <th class="text-center" style="width: 100px;">TOTAL QTY</th>
                    <th class="text-right" style="width: 110px;">HARGA SATUAN</th>
                    <th class="text-right" style="width: 130px;">SUBTOTAL NILAI</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($summaryItems as $item)
                    @php
                        $qtyFormatted = number_format($item['total_qty'], $item['total_qty'] == floor($item['total_qty']) ? 0 : 2, ',', '.');
                        $unitPrice = $item['total_qty'] > 0 ? ($item['total_nilai'] / $item['total_qty']) : 0;
                    @endphp
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $no++ }}</td>
                        <td class="fw-bold" style="color: #0f172a;">{{ $item['nama'] }}</td>
                        <td class="text-center fw-bold">
                            {{ $qtyFormatted }} 
                            @if(!empty($item['satuan']))
                                <span style="font-weight: normal; font-size: 9.5px; color: #64748b;">{{ $item['satuan'] }}</span>
                            @endif
                        </td>
                        <td class="text-right" style="color: #475569;">Rp {{ number_format($unitPrice, (fmod($unitPrice, 1) !== 0.0 && $unitPrice < 1000) ? 2 : 0, ',', '.') }}</td>
                        <td class="text-right fw-bold" style="color: #0f172a;">Rp {{ number_format($item['total_nilai'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right fw-bold" style="color: #475569; font-size: 11px;">GRAND TOTAL TAGIHAN PRODUKSI</td>
                    <td class="text-right fw-bold" style="color: #2563eb; font-size: 13.5px;">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- TERBILANG BAR -->
        <div class="terbilang-bar">
            <span style="font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 9px; margin-right: 6px;">Terbilang:</span>
            <span style="font-weight: 700; font-style: italic; color: #0f172a;">
                "{{ terbilangRupiahInvoice($grandTotal) }} Rupiah"
            </span>
        </div>

        <!-- SIGNATURES -->
        <table class="signature-table">
            <tr>
                <td>
                    <div class="sig-title">Yang Menyerahkan</div>
                    <div class="sig-line"></div>
                    <div class="sig-role">Central Kitchen Gaharu</div>
                </td>
                <td>
                    <div class="sig-title">Yang Menerima & Membayar</div>
                    <div class="sig-line"></div>
                    <div class="sig-role">Outlet Kejingga</div>
                </td>
                <td>
                    <div class="sig-title">Mengetahui / Keuangan</div>
                    <div class="sig-line"></div>
                    <div class="sig-role">Tim Finance Gaharu</div>
                </td>
            </tr>
        </table>

        <div class="footer-note">
            Faktur / Invoice ini diterbitkan secara otomatis oleh Sistem Gaharu ERP pada {{ date('d F Y H:i:s') }} WIB.
        </div>
    </div>

    @if(!$isPdf)
    <script>
        function downloadJPG() {
            const card = document.getElementById('invoiceCard');
            if (typeof html2canvas === 'undefined') {
                alert('Library html2canvas belum dimuat.');
                return;
            }
            html2canvas(card, {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Invoice-Produksi-CK-Kejingga-{{ $noInvoice }}.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();
            });
        }
    </script>
    @endif

</body>
</html>
