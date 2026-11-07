<x-app-layout>
    <x-slot name="header">Laporan Pembelian</x-slot>

    <div class="container-fluid">

        {{-- ── FILTER CARD ── --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.pembelian') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">DARI TANGGAL</label>
                            <input type="date" name="dari" class="form-control form-control-sm" value="{{ request('dari') }}">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">SAMPAI TANGGAL</label>
                            <input type="date" name="sampai" class="form-control form-control-sm" value="{{ request('sampai') }}">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">OUTLET / GUDANG</label>
                            <select name="gudang_id" id="report_gudang_id" class="form-select form-select-sm">
                                <option value="">Semua Outlet &amp; Gudang</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                        {{ $g->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2" id="report_divisi_container">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">DIVISI</label>
                            <select name="divisi_id" id="report_divisi_id" class="form-select form-select-sm">
                                <option value="">Semua Divisi</option>
                                @foreach($divisis as $d)
                                    <option value="{{ $d->id }}" data-gudang="{{ $d->gudang_id }}" {{ request('divisi_id') == $d->id ? 'selected' : '' }}>
                                        {{ $d->nama }} ({{ $d->gudang->nama ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">SUPPLIER</label>
                            <select name="supplier_id" class="form-select form-select-sm">
                                <option value="">Semua Supplier</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">METODE BAYAR</label>
                            <select name="metode_pembayaran" class="form-select form-select-sm">
                                <option value="">Semua Metode</option>
                                <option value="cod"    {{ request('metode_pembayaran') === 'cod'    ? 'selected' : '' }}>COD</option>
                                <option value="termin" {{ request('metode_pembayaran') === 'termin' ? 'selected' : '' }}>Termin</option>
                                <option value="dp"     {{ request('metode_pembayaran') === 'dp'     ? 'selected' : '' }}>DP</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <div class="text-muted" style="font-size: 12px;">
                            @if(request()->anyFilled(['dari', 'sampai', 'gudang_id', 'divisi_id', 'supplier_id', 'metode_pembayaran']))
                                <span class="badge bg-light text-dark border me-2"><i class="bi bi-funnel-fill text-warning me-1"></i>Filter Aktif</span>
                                <a href="{{ route('laporan.pembelian') }}" class="text-decoration-none text-danger fw-semibold">
                                    <i class="bi bi-x-circle me-1"></i>Reset Filter
                                </a>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #d88656; border: none; font-weight: 500;">
                                <i class="bi bi-search me-1"></i> Tampilkan
                            </button>
                            <a href="{{ route('laporan.pembelian', array_merge(request()->all(), ['format'=>'excel'])) }}"
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
                            </a>
                            <a href="{{ route('laporan.pembelian', array_merge(request()->all(), ['format'=>'pdf'])) }}"
                               class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── SUMMARY CARDS ── --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#d88656; color:white;">
                    <div class="card-body">
                        <div style="font-size:11px; opacity:.9; text-transform:uppercase; letter-spacing:1px;">Total Nilai Pembelian</div>
                        <div class="fw-bold mt-1" style="font-size:22px;">Rp {{ number_format($totalNilai, 0, ',', '.') }}</div>
                        <div style="font-size:12px; opacity:.9; margin-top:4px;">{{ $totalTransaksi }} transaksi</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#f8f9fa;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#6c757d; text-transform:uppercase; letter-spacing:1px;">COD / Tunai</div>
                        <div class="fw-bold mt-1" style="font-size:22px; color:#198754;">{{ $totalCod }}</div>
                        <div style="font-size:12px; color:#6c757d;">transaksi lunas</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#f8f9fa;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#6c757d; text-transform:uppercase; letter-spacing:1px;">Termin & DP</div>
                        <div class="fw-bold mt-1" style="font-size:22px; color:#f59e0b;">{{ $totalTermin + $totalDp }}</div>
                        <div style="font-size:12px; color:#6c757d;">perlu dipantau</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#f8f9fa;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#6c757d; text-transform:uppercase; letter-spacing:1px;">Belum Dicatat</div>
                        <div class="fw-bold mt-1" style="font-size:22px; color:#dc3545;">{{ $belumDicatat }}</div>
                        <div style="font-size:12px; color:#6c757d;">transaksi</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TABEL DATA ── --}}
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-bottom:1px solid #eadfd4;">
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Data Pembelian</span>
                        <span class="text-muted ms-2" style="font-size:13px;">{{ $data->count() }} baris</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Kode</th>
                                <th style="background-color: #d88656; color: white;">Tanggal</th>
                                <th style="background-color: #d88656; color: white;">Supplier</th>
                                <th style="background-color: #d88656; color: white;">Outlet / Gudang</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Total</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Metode Bayar</th>
                                <th style="background-color: #d88656; color: white;">Jatuh Tempo</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Status</th>
                                <th style="background-color: #d88656; color: white;">Dicatat</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                @php
                                    $metodeBadge = match($row->metode_pembayaran) {
                                        'cod'    => ['COD',    'bg-success'],
                                        'termin' => ['Termin', 'bg-warning text-dark'],
                                        'dp'     => ['DP ' . $row->persen_dp . '%', 'bg-info'],
                                        default  => ['—', 'bg-secondary'],
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 fw-semibold" style="color:#d88656; font-size:12px;">
                                        {{ $row->kode_pembelian }}
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d M Y') }}</td>
                                    <td>{{ $row->supplier->nama ?? '-' }}</td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $row->gudang->nama ?? '-' }}</span>
                                        @if($row->divisi)
                                            <div class="mt-1">
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size:10.5px;">
                                                    <i class="bi bi-diagram-3 text-primary me-1"></i>{{ $row->divisi->nama }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">
                                        Rp {{ number_format($row->total, 0, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $metodeBadge[1] }}">{{ $metodeBadge[0] }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $jtDate = $row->tanggal_jatuh_tempo ?? $row->tanggal_pelunasan;
                                        @endphp
                                        @if($jtDate)
                                            @php $jt = \Carbon\Carbon::parse($jtDate); @endphp
                                            <span class="{{ $jt->isPast() && !$row->is_lunas ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                 {{ $jt->format('d M Y') }}
                                                 @if($jt->isPast() && !$row->is_lunas) <i class="bi bi-exclamation-circle"></i> @endif
                                            </span>
                                        @else
                                             <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($row->is_lunas)
                                            <span class="badge bg-success">Lunas</span>
                                            @if($row->lunas_at)
                                                <small class="d-block text-muted" style="font-size: 10px;">{{ \Carbon\Carbon::parse($row->lunas_at)->format('d M Y') }}</small>
                                            @endif
                                        @else
                                             <span class="badge bg-danger">Belum Lunas</span>
                                        @endif
                                    </td>
                                    <td class="text-muted" style="font-size:11px;">
                                        {{ $row->dicatat_pada ? \Carbon\Carbon::parse($row->dicatat_pada)->format('d M Y') : '—' }}
                                    </td>
                                    <td class="text-center">
                                        {{-- Tombol Detail --}}
                                        <button type="button" class="btn btn-sm text-white" style="background-color: #d88656; border: none; font-size: 11px;" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $row->id }}">
                                            <i class="bi bi-eye"></i> Detail
                                        </button>

                                        {{-- Modal --}}
                                        <div class="modal fade" id="modalDetail{{ $row->id }}" tabindex="-1" aria-labelledby="modalDetailLabel{{ $row->id }}" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content text-start">
                                                    <div class="modal-header text-white" style="background-color: #d88656;">
                                                        <h5 class="modal-title" id="modalDetailLabel{{ $row->id }}">Rincian Nota #{{ $row->kode_pembelian }}</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="font-size: 13px;">
                                                        
                                                        <div class="row mb-3 pb-2 border-bottom">
                                                            <div class="col-md-6">
                                                                <p class="mb-1"><strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($row->tanggal)->format('d M Y') }}</p>
                                                                <p class="mb-1"><strong>Supplier:</strong> {{ $row->supplier->nama ?? '-' }}</p>
                                                            </div>
                                                            <div class="col-md-6 text-md-end">
                                                                <p class="mb-1"><strong>Outlet / Gudang:</strong> {{ $row->gudang->nama ?? '-' }}</p>
                                                                @if($row->divisi)
                                                                    <p class="mb-1"><strong>Divisi / Alokasi:</strong> <span class="badge bg-light text-dark border">{{ $row->divisi->nama }}</span></p>
                                                                @endif
                                                                <p class="mb-1"><strong>Metode Bayar:</strong> <span class="badge {{ $metodeBadge[1] }}">{{ $metodeBadge[0] }}</span></p>
                                                            </div>
                                                        </div>

                                                        <div class="table-responsive">
                                                            <table class="table table-bordered table-striped align-middle text-center mb-0" style="font-size: 12px;">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th style="width: 50px;">No</th>
                                                                        <th>Nama Barang</th>
                                                                        <th>Jumlah</th>
                                                                        <th>Harga Satuan</th>
                                                                        <th>Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @forelse($row->details ?? $row->pembelianDetails ?? [] as $index => $detail)
                                                                        <tr>
                                                                            <td>{{ $index + 1 }}</td>
                                                                            <td class="text-start fw-semibold" style="color: #d88656;">
                                                                                {{ $detail->barang->nama ?? '-' }}
                                                                                <br><small class="text-muted text-uppercase" style="font-size: 10px;">{{ $detail->barang->kode_barang ?? '' }}</small>
                                                                            </td>
                                                                            <td>{{ number_format($detail->jumlah ?? $detail->qty ?? 0) }} <small class="text-muted">{{ $detail->barang->satuan ?? '' }}</small></td>
                                                                            <td class="text-end">Rp {{ number_format($detail->harga_satuan ?? $detail->harga ?? 0, 0, ',', '.') }}</td>
                                                                            <td class="text-end fw-bold">Rp {{ number_format(($detail->jumlah ?? $detail->qty ?? 0) * ($detail->harga_satuan ?? $detail->harga ?? 0), 0, ',', '.') }}</td>
                                                                        </tr>
                                                                    @empty
                                                                        <tr>
                                                                            <td colspan="5" class="text-center text-muted py-3">Tidak ada rincian barang ditemukan.</td>
                                                                        </tr>
                                                                    @endforelse
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="table-light fw-bold">
                                                                        <th colspan="4" class="text-end">Total Akhir:</th>
                                                                        <th class="text-end text-danger" style="font-size: 14px;">Rp {{ number_format($row->total, 0, ',', '.') }}</th>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>

                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        Tidak ada data untuk filter yang dipilih.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($data->count() > 0)
                        <tfoot>
                            <tr style="background:#fdf3ec; font-weight:600;">
                                <td colspan="4" class="px-4" style="color:#d88656;">Total</td>
                                <td class="text-end" style="color:#d88656;">
                                    Rp {{ number_format($totalNilai, 0, ',', '.') }}
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

    </div>

    <style>
        .btn-close-white {
            filter: invert(1) grayscale(1) brightness(2);
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gudangSelect = document.getElementById('report_gudang_id');
        const divisiSelect = document.getElementById('report_divisi_id');
        if (!gudangSelect || !divisiSelect) return;

        // Cache all initial divisi options
        const allDivisiOptions = Array.from(divisiSelect.querySelectorAll('option[data-gudang]')).map(opt => ({
            id: opt.value,
            nama: opt.textContent.trim(),
            gudangId: opt.getAttribute('data-gudang')
        }));

        const currentDivisiId = "{{ request('divisi_id') }}";

        function updateDivisiOptions(selectedGudangId, preserveSelectedId = null) {
            divisiSelect.innerHTML = '<option value="">Semua Divisi</option>';

            if (!selectedGudangId) {
                allDivisiOptions.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.nama;
                    opt.setAttribute('data-gudang', item.gudangId);
                    if (preserveSelectedId && String(preserveSelectedId) === String(item.id)) {
                        opt.selected = true;
                    }
                    divisiSelect.appendChild(opt);
                });
                return;
            }

            const filtered = allDivisiOptions.filter(item => String(item.gudangId) === String(selectedGudangId));
            if (filtered.length === 0) {
                const opt = document.createElement('option');
                opt.value = "";
                opt.textContent = "Tidak ada divisi";
                opt.disabled = true;
                divisiSelect.appendChild(opt);
            } else {
                filtered.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    // Bersihkan label outlet tambahan jika outlet sudah dipilih secara spesifik
                    const cleanName = item.nama.replace(/\s*\([^)]*\)$/, '');
                    opt.textContent = cleanName;
                    opt.setAttribute('data-gudang', item.gudangId);
                    if (preserveSelectedId && String(preserveSelectedId) === String(item.id)) {
                        opt.selected = true;
                    }
                    divisiSelect.appendChild(opt);
                });
            }
        }

        gudangSelect.addEventListener('change', function() {
            updateDivisiOptions(this.value);
        });

        if (gudangSelect.value) {
            updateDivisiOptions(gudangSelect.value, currentDivisiId);
        }
    });
    </script>
</x-app-layout>