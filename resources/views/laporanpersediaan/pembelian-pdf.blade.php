<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pembelian</title>
    <style>
        /* Pengaturan Dasar */
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 12px; 
            color: #333; 
            margin: 30px; 
        }
        
        /* Bagian Header (Kop Laporan) */
        .header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 2px solid #1e3a8a; 
            padding-bottom: 12px; 
        }
        .header h2 { 
            margin: 0; 
            font-size: 20px; 
            color: #1e3a8a; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
        }
        .header p { 
            margin: 5px 0 0 0; 
            font-size: 13px; 
            color: #555; 
        }

        /* Tabel Informasi Periode & Tanggal */
        .info-table { 
            width: 100%; 
            margin-bottom: 20px; 
            border-collapse: collapse; 
        }
        .info-table td { 
            padding: 4px 0; 
            font-size: 12px; 
        }

        /* Tabel Data Utama */
        .main-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        .main-table th, .main-table td { 
            border: 1px solid #d1d5db; 
            padding: 8px 10px; 
        }
        .main-table th { 
            background-color: #1e3a8a; 
            color: #ffffff; 
            text-align: left; 
            font-weight: bold; 
            font-size: 12px; 
        }
        .main-table td { 
            font-size: 11px; 
        }
        .main-table tbody tr:nth-child(even) { 
            background-color: #f9fafb; 
        }
        .main-table tfoot td { 
            background-color: #e5e7eb; 
            font-weight: bold; 
            font-size: 12px; 
            color: #1f2937; 
        }

        /* Label / Badge Status */
        .badge { 
            display: inline-block; 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-size: 10px; 
            font-weight: bold; 
            text-transform: uppercase; 
        }
        .bs { background-color: #d1e7dd; color: #0f5132; }
        .bw { background-color: #fff3cd; color: #664d03; }
        .bi { background-color: #cff4fc; color: #055160; }
        .bd { background-color: #e2e3e5; color: #41464b; }
        .bl { background-color: #f8d7da; color: #842029; }

        /* Utility Classes */
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .text-danger { color: #dc3545 !important; }
        .fw-bold { font-weight: bold !important; }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN PEMBELIAN</h2>
        <p>CV Gaharu App</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%" class="fw-bold">Periode Laporan</td>
            <td width="2%">:</td>
            <td width="33%">
                @if(request('dari') || request('sampai'))
                    {{ request('dari') ?? '—' }} s/d {{ request('sampai') ?? '—' }}
                @else
                    Semua Periode
                @endif
            </td>
            <td width="15%" class="text-right fw-bold">Tanggal Cetak</td>
            <td width="2%" class="text-center">:</td>
            <td width="33%">{{ date('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td class="fw-bold">Outlet / Gudang</td>
            <td>:</td>
            <td>
                @if(request('gudang_id'))
                    {{ \App\Models\MasterGudang::find(request('gudang_id'))->nama ?? 'Semua Outlet' }}
                @else
                    Semua Outlet &amp; Gudang
                @endif
            </td>
            <td class="text-right fw-bold">Divisi</td>
            <td class="text-center">:</td>
            <td>
                @if(request('divisi_id'))
                    {{ \App\Models\GudangDivisi::find(request('divisi_id'))->nama ?? 'Semua Divisi' }}
                @else
                    Semua Divisi
                @endif
            </td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="12%">Kode</th>
                <th width="10%">Tanggal</th>
                <th width="17%">Supplier</th>
                <th width="15%">Outlet / Gudang</th>
                <th class="text-right" width="13%">Total</th>
                <th class="text-center" width="9%">Metode</th>
                <th class="text-center" width="12%">Jatuh Tempo</th>
                <th class="text-center" width="12%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                @php
                    [$label, $cls] = match($row->metode_pembayaran) {
                        'cod'    => ['COD',    'bs'],
                        'termin' => ['Termin', 'bw'],
                        'dp'     => ['DP '.$row->persen_dp.'%', 'bi'],
                        default  => ['—',      'bd'],
                    };
                @endphp
                <tr>
                    <td class="fw-bold">{{ $row->kode_pembelian }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                    <td>{{ $row->supplier->nama ?? '-' }}</td>
                    <td>
                        {{ $row->gudang->nama ?? '-' }}
                        @if($row->divisi)
                            <div style="font-size: 9px; color: #555; margin-top: 1px;">Div: {{ $row->divisi->nama }}</div>
                        @endif
                    </td>
                    <td class="text-right fw-bold">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                    <td class="text-center"><span class="badge {{ $cls }}">{{ $label }}</span></td>
                    <td class="text-center">
                        @php
                            $jtDate = $row->tanggal_jatuh_tempo ?? $row->tanggal_pelunasan;
                        @endphp
                        {{ $jtDate ? \Carbon\Carbon::parse($jtDate)->format('d/m/Y') : '—' }}
                    </td>
                    <td class="text-center">
                        @if($row->is_lunas)
                            <span class="badge bs">Lunas</span>
                            @if($row->lunas_at)
                                <div style="font-size: 8px; color: #555; margin-top: 2px;">{{ \Carbon\Carbon::parse($row->lunas_at)->format('d/m/Y') }}</div>
                            @endif
                        @else
                            <span class="badge bl">Belum Lunas</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Belum ada data transaksi pembelian.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">TOTAL ({{ $totalTransaksi }} transaksi):</td>
                <td class="text-right text-danger">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>