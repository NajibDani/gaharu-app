<!DOCTYPE html>
<html>
<head>
    <title>Laporan Stock Opname</title>
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
        
        /* Baris Data Utama */
        .main-table tbody tr.main-row { 
            background-color: #ffffff; 
        }
        .main-table tbody tr.main-row:nth-of-type(even) { 
            background-color: #f9fafb; 
        }

        /* Baris Sub/Detail Item */
        .main-table tr.sub td { 
            background-color: #f3f4f6; 
            color: #4b5563; 
            font-size: 10px; 
            border-top: 1px dashed #cbd5e1; 
            border-bottom: 1px dashed #cbd5e1;
        }

        /* Footer / Total */
        .main-table tfoot td { 
            background-color: #e5e7eb; 
            font-weight: bold; 
            font-size: 12px; 
            color: #1f2937; 
            border-top: 2px solid #9ca3af;
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
        .bd { background-color: #e2e3e5; color: #41464b; }

        /* Warna Angka Selisih */
        .plus  { color: #166534 !important; font-weight: bold; }
        .minus { color: #dc2626 !important; font-weight: bold; }

        /* Utility Classes */
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .fw-bold { font-weight: bold !important; }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN STOCK OPNAME</h2>
        <p>CV Gaharu App</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%" class="fw-bold">Periode Laporan</td>
            <td width="2%">:</td>
            <td>
                @if(request('dari') || request('sampai'))
                    {{ request('dari') ?? '—' }} s/d {{ request('sampai') ?? '—' }}
                @else
                    Semua Periode
                @endif
            </td>
            <td width="15%" class="text-right fw-bold">Tanggal Cetak</td>
            <td width="2%" class="text-center">:</td>
            <td width="20%">{{ date('d-m-Y H:i') }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="15%">Kode Opname</th>
                <th width="12%">Tanggal</th>
                <th width="15%">Gudang</th>
                <th class="text-center" width="8%">Item</th>
                <th class="text-center" width="10%">Selisih +</th>
                <th class="text-center" width="10%">Selisih −</th>
                <th class="text-right" width="18%">Nilai Selisih</th>
                <th class="text-center" width="12%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                @php
                    $sPlus  = $row->details->where('selisih','>',0)->sum('selisih');
                    $sMinus = $row->details->where('selisih','<',0)->sum('selisih');
                    $sNilai = $row->details->sum('nilai_selisih');
                @endphp
                
                <tr class="main-row">
                    <td class="fw-bold">{{ $row->kode_opname }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                    <td>{{ $row->gudang->nama ?? '-' }}</td>
                    <td class="text-center">{{ $row->details->count() }}</td>
                    <td class="text-center plus">{{ $sPlus > 0 ? '+'.number_format($sPlus,2) : '—' }}</td>
                    <td class="text-center minus">{{ $sMinus < 0 ? number_format($sMinus,2) : '—' }}</td>
                    <td class="text-right fw-bold {{ $sNilai < 0 ? 'minus' : ($sNilai > 0 ? 'plus' : '') }}">
                        {{ $sNilai != 0 ? 'Rp '.number_format($sNilai,0,',','.') : '—' }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $row->status === 'approved' ? 'bs' : 'bd' }}">{{ $row->status }}</span>
                    </td>
                </tr>
                
                @foreach($row->details->where('selisih','!=',0) as $d)
                    @php
                        $k = (float) ($d->barang->konversi_pembelian ?? 1);
                        $hasK = !empty($d->barang->satuan_pembelian) && $k > 1;
                        $stn = $d->barang->satuan ?? 'pcs';
                        $stnB = $d->barang->satuan_pembelian ?? '';
                    @endphp
                    <tr class="sub">
                        <td style="padding-left:18px;">
                            ↳ {{ $d->barang->nama ?? '-' }}
                            @if($hasK)
                                <div style="font-size: 8px; color: #1e3a8a;">(1 {{ $stnB }} = {{ number_format($k, 0, ',', '.') }} {{ $stn }})</div>
                            @endif
                        </td>
                        <td colspan="2">
                            Sistem: {{ number_format($d->stok_sistem,2) }} {{ $stn }} @if($hasK) ({{ number_format($d->stok_sistem / $k, 2) }} {{ $stnB }}) @endif
                            → Fisik: {{ number_format($d->stok_fisik,2) }} {{ $stn }} @if($hasK) ({{ number_format($d->stok_fisik / $k, 2) }} {{ $stnB }}) @endif
                        </td>
                        <td></td>
                        <td class="text-center plus">
                            @if($d->selisih > 0)
                                +{{ number_format($d->selisih,2) }} {{ $stn }}
                                @if($hasK)
                                    <div style="font-size: 8px;">(+{{ number_format($d->selisih / $k, 2) }} {{ $stnB }})</div>
                                @endif
                            @endif
                        </td>
                        <td class="text-center minus">
                            @if($d->selisih < 0)
                                {{ number_format($d->selisih,2) }} {{ $stn }}
                                @if($hasK)
                                    <div style="font-size: 8px;">({{ number_format($d->selisih / $k, 2) }} {{ $stnB }})</div>
                                @endif
                            @endif
                        </td>
                        <td class="text-right">Rp {{ number_format($d->nilai_selisih,0,',','.') }}</td>
                        <td></td>
                    </tr>
                @endforeach
                
            @empty
                <tr>
                    <td colspan="8" class="text-center">Belum ada data Stock Opname.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right">TOTAL NILAI SELISIH:</td>
                <td class="text-right fw-bold {{ $data->sum(fn($d) => $d->details->sum('nilai_selisih')) < 0 ? 'minus' : 'plus' }}">
                    Rp {{ number_format($data->sum(fn($d) => $d->details->sum('nilai_selisih')), 0, ',', '.') }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>