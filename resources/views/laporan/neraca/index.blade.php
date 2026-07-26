<x-app-layout>
    <style>
        .container-laporan {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 4px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            color: white;
        }

        .btn-primary   { background: #1a56db; }
        .btn-success   { background: #198754; }
        .btn-danger    { background: #dc3545; }
        .btn-dark      { background: #333; }

        .laporan-header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .laporan-header h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 0.3px;
        }

        .laporan-header h2 {
            margin: 6px 0 4px;
            font-size: 16px;
            font-weight: 700;
            color: #374151;
        }

        .laporan-header p {
            margin: 0;
            font-size: 13px;
            color: #6b7280;
        }

        .neraca-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 720px) {
            .neraca-grid {
                grid-template-columns: 1fr;
            }
        }

        .sisi-panel {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 18px 20px;
        }

        .judul-sisi {
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 14px;
            color: #374151;
            background: #f4f4f5;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 16px;
        }

        .sub-judul {
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6b7280;
            margin: 16px 0 6px;
        }

        .sub-judul:first-of-type {
            margin-top: 0;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 4px 0;
            font-size: 14px;
        }

        .item-row.muted {
            font-style: italic;
            color: #9ca3af;
        }

        .item-row.pengurang span:last-child {
            color: #b91c1c;
        }

        .item-row.info span:last-child {
            color: #1d4ed8;
        }

        .subtotal-row {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 14px;
            border-top: 1px solid #d1d5db;
            margin-top: 6px;
            padding-top: 6px;
        }

        .total-box {
            margin-top: 18px;
            padding: 12px 14px;
            border-top: 3px double #111827;
            font-weight: 700;
            font-size: 15px;
            background: #f4f4f5;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
        }

        .balance-check {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            padding: 8px;
            border-radius: 6px;
        }

        .balance-check.ok {
            color: #15803d;
            background: #f0fdf4;
        }

        .balance-check.warn {
            color: #b91c1c;
            background: #fef2f2;
        }

        @media print {
            .no-print {
                display: none;
            }

            .card {
                box-shadow: none;
                border: none;
            }
        }
    </style>

    <div class="py-12">
        <div class="container-laporan">

            <div class="card no-print">
                <h3 style="margin-bottom: 15px; font-weight: 700;">Filter laporan neraca</h3>
                <form action="{{ route('laporan.neraca.index') }}" method="GET" class="filter-group">
                    <div>
                        <label>Bulan</label>
                        <select name="bulan">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ sprintf('%02d', $m) }}" {{ $bulan == $m ? 'selected' : '' }}>
                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Tahun</label>
                        <input type="number" name="tahun" value="{{ $tahun }}" style="width: 100px;">
                    </div>
                    <button type="submit" class="btn btn-primary">Tampilkan neraca</button>
                    <a href="{{ route('laporan.neraca.index', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success">
                        Export excel
                    </a>
                    <a href="{{ route('laporan.neraca.index', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-danger">
                        Export pdf
                    </a>
                </form>
            </div>

            <div class="card">
                <div class="laporan-header">
                    <h1>CV Gaharu Agung Sejahtera</h1>
                    <h2>Laporan Neraca</h2>
                    <p>Periode: {{ date('F', mktime(0, 0, 0, $bulan, 1)) }} {{ $tahun }} &middot; per {{ \Carbon\Carbon::parse($tanggalCutoff)->translatedFormat('d F Y') }}</p>
                </div>

                <div class="neraca-grid">

                    {{-- AKTIVA --}}
                    <div class="sisi-panel">
                        <div class="judul-sisi">Aktiva</div>

                        <div class="sub-judul">Aset lancar</div>
                        @forelse($asetLancar as $item)
                            <div class="item-row">
                                <span>{{ $item->nama }}</span>
                                <span>Rp {{ number_format($item->saldo, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="item-row muted">
                                <span>Tidak ada aset lancar</span>
                                <span>Rp 0</span>
                            </div>
                        @endforelse
                        <div class="subtotal-row">
                            <span>Total aset lancar</span>
                            <span>Rp {{ number_format($totalAsetLancar, 0, ',', '.') }}</span>
                        </div>

                        <div class="sub-judul">Aset tetap</div>
                        @forelse($asetTetap as $item)
                            <div class="item-row {{ strtoupper($item->saldo_normal) === 'KREDIT' ? 'pengurang' : '' }}">
                                <span>{{ $item->nama }}</span>
                                <span>
                                    @if(strtoupper($item->saldo_normal) === 'KREDIT')
                                        (Rp {{ number_format($item->saldo, 0, ',', '.') }})
                                    @else
                                        Rp {{ number_format($item->saldo, 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>
                        @empty
                            <div class="item-row muted">
                                <span>Tidak ada aset tetap</span>
                                <span>Rp 0</span>
                            </div>
                        @endforelse
                        <div class="subtotal-row">
                            <span>Total aset tetap</span>
                            <span>Rp {{ number_format($totalAsetTetap, 0, ',', '.') }}</span>
                        </div>

                        <div class="total-box">
                            <span>Total aktiva</span>
                            <span>Rp {{ number_format($totalAktiva, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- PASSIVA --}}
                    <div class="sisi-panel">
                        <div class="judul-sisi">Passiva</div>

                        <div class="sub-judul">Kewajiban (liabilitas)</div>
                        @forelse($passiva->where('tipe', 'Liabilitas') as $item)
                            <div class="item-row">
                                <span>{{ $item->nama }}</span>
                                <span>Rp {{ number_format($item->saldo, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="item-row muted">
                                <span>Tidak ada kewajiban</span>
                                <span>Rp 0</span>
                            </div>
                        @endforelse
                        <div class="subtotal-row">
                            <span>Total kewajiban</span>
                            <span>Rp {{ number_format($totalKewajiban, 0, ',', '.') }}</span>
                        </div>

                        <div class="sub-judul">Ekuitas (modal)</div>
                        @forelse($passiva->where('tipe', 'Ekuitas') as $item)
                            <div class="item-row">
                                <span>{{ $item->nama }}</span>
                                <span>Rp {{ number_format($item->saldo, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="item-row muted">
                                <span>Tidak ada modal awal</span>
                                <span>Rp 0</span>
                            </div>
                        @endforelse
                        <div class="item-row info">
                            <span>Laba tahun berjalan (YTD)</span>
                            <span>Rp {{ number_format($labaBerjalan, 0, ',', '.') }}</span>
                        </div>
                        @if($totalPrive != 0)
                            <div class="item-row pengurang">
                                <span>Prive (pengurang modal)</span>
                                <span>(Rp {{ number_format($totalPrive, 0, ',', '.') }})</span>
                            </div>
                        @endif
                        <div class="subtotal-row">
                            <span>Total ekuitas</span>
                            <span>Rp {{ number_format($modalAkhir, 0, ',', '.') }}</span>
                        </div>

                        <div class="total-box">
                            <span>Total passiva</span>
                            <span>Rp {{ number_format($totalPassiva, 0, ',', '.') }}</span>
                        </div>
                    </div>

                </div>

                @php $selisih = $totalAktiva - $totalPassiva; @endphp
                <div class="balance-check {{ abs($selisih) < 1 ? 'ok' : 'warn' }}">
                    @if(abs($selisih) < 1)
                        Neraca seimbang &mdash; total aktiva sama dengan total passiva.
                    @else
                        Neraca tidak seimbang, selisih Rp {{ number_format($selisih, 0, ',', '.') }}. Periksa kembali jurnal pada periode ini.
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>