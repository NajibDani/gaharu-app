<x-app-layout>
    <x-slot name="header">Laporan Posisi Stok Gudang</x-slot>

    <div class="container-fluid">

        {{-- ── FILTER ── --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.stok-gudang') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">CARI ITEM BARANG</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari nama atau kode..." value="{{ request('search') }}">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">PER TANGGAL</label>
                            <input type="date" name="tanggal" class="form-control form-control-sm" value="{{ request('tanggal') }}">
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">GUDANG</label>
                            <select name="gudang_id" id="report_gudang_id" class="form-select form-select-sm">
                                <option value="">Semua Gudang</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                        {{ $g->nama }} ({{ $g->kategori }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2" id="report_divisi_container" style="{{ !request('divisi_id') && !request('gudang_id') ? 'display:none;' : '' }}">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">DIVISI</label>
                            <select name="divisi_id" id="report_divisi_id" class="form-select form-select-sm">
                                <option value="">Semua Divisi</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">KATEGORI</label>
                            <select name="kategori_id" class="form-select form-select-sm">
                                <option value="">Semua Kategori</option>
                                @foreach($kategoris as $k)
                                    <option value="{{ $k->id }}" {{ request('kategori_id') == $k->id ? 'selected' : '' }}>
                                        {{ $k->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end mt-1">
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label fw-semibold text-muted" style="font-size:12px;">JENIS BARANG</label>
                            <select name="jenis_utama" class="form-select form-select-sm">
                                <option value="">Semua Jenis</option>
                                <option value="bahan_baku"  {{ request('jenis_utama') === 'bahan_baku'  ? 'selected' : '' }}>Bahan Baku</option>
                                <option value="bahan_setengah_jadi" {{ request('jenis_utama') === 'bahan_setengah_jadi' ? 'selected' : '' }}>Bahan Setengah Jadi</option>
                                <option value="barang_jadi" {{ request('jenis_utama') === 'barang_jadi' ? 'selected' : '' }}>Barang Jadi</option>
                                <option value="operational" {{ request('jenis_utama') === 'operational' ? 'selected' : '' }}>Operational</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <div class="text-muted" style="font-size: 12px;">
                            @if(request()->anyFilled(['search', 'tanggal', 'gudang_id', 'divisi_id', 'kategori_id', 'jenis_utama']))
                                <span class="badge bg-light text-dark border me-2"><i class="bi bi-funnel-fill text-warning me-1"></i>Filter Aktif</span>
                                <a href="{{ route('laporan.stok-gudang') }}" class="text-decoration-none text-danger fw-semibold">
                                    <i class="bi bi-x-circle me-1"></i>Reset Filter
                                </a>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #d88656; border: none; font-weight: 500;">
                                <i class="bi bi-search me-1"></i> Tampilkan
                            </button>
                            <a href="{{ route('laporan.stok-gudang', array_merge(request()->all(), ['format'=>'excel'])) }}"
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
                            </a>
                            <a href="{{ route('laporan.stok-gudang', array_merge(request()->all(), ['format'=>'pdf'])) }}"
                               class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── SUMMARY CARDS ── --}}
        <div class="row g-3 mb-4 align-items-stretch">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#d88656; color:white;">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div style="font-size:11px; opacity:.9; text-transform:uppercase; letter-spacing:1px;">Total Nilai Stok</div>
                        <div class="fw-bold mt-1" style="font-size:20px;">Rp {{ number_format($totalNilaiStok ?? 0, 0, ',', '.') }}</div>
                        <div style="font-size:12px; opacity:.9;">
                            @if(request('tanggal'))
                                Posisi per {{ \Carbon\Carbon::parse(request('tanggal'))->format('d M Y') }}
                            @else
                                Posisi saat ini
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm bg-white">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="text-muted" style="font-size:11px; text-transform:uppercase; letter-spacing:1px;">Total Item Barang</div>
                        <div class="fw-bold text-dark mt-1" style="font-size:24px;">{{ number_format($totalItem) }}</div>
                        <div class="text-muted" style="font-size:12px;">{{ $data->count() }} item terfilter</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card border-danger h-100 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid #f5c2c7;">
                        <div class="text-danger fw-bold d-flex align-items-center" style="font-size: 13px;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Stok Kritis (Maksimal 5)
                        </div>
                        @if($hasPurchaseAccess)
                            <a href="{{ route('pembelian.index') }}"
                            class="btn btn-sm text-white shadow-sm py-1 px-2"
                            style="background-color: #d88656; border: none; font-size: 12px;"
                            id="buatPesananBtn">
                                <i class="bi bi-cart-plus me-1"></i> Buat Pesanan
                            </a>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 120px;">
                            <table class="table table-hover align-middle mb-0" style="font-size:12px;">
                                <tbody>
                                    @forelse($stokKritis as $kritis)
                                    <tr>
                                        <td class="ps-3 fw-medium py-1">{{ $kritis->nama_barang }}</td>
                                        <td class="text-end pe-3 py-1">
                                            <span class="badge bg-danger-subtle text-danger px-2 py-1 border border-danger-subtle">
                                                {{ number_format($kritis->jumlah, 2) }} {{ $kritis->satuan }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-3 text-muted">
                                            <i class="bi bi-check-circle text-success fs-5 d-block mb-1"></i>
                                            Semua stok dalam kondisi aman
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TABEL ── --}}
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-bottom:1px solid #eadfd4;">
                    <span class="fw-bold" style="color:#d88656;">
                        @if(request('tanggal'))
                            Posisi Stok Gudang
                            <span class="text-muted fw-normal ms-1" style="font-size:13px;">
                                per {{ \Carbon\Carbon::parse(request('tanggal'))->format('d M Y') }} (Cut-off)
                            </span>
                        @else
                            Posisi Stok Saat Ini
                            <span class="text-muted fw-normal ms-1" style="font-size:13px;">
                                per {{ now()->format('d M Y, H:i') }}
                            </span>
                        @endif
                    </span>
                    <span class="text-muted" style="font-size:13px;">{{ $data->count() }} item</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Kode Barang</th>
                                <th style="background-color: #d88656; color: white;">Nama Barang</th>
                                <th style="background-color: #d88656; color: white;">Jenis</th>
                                <th style="background-color: #d88656; color: white;">Gudang & Divisi</th>
                                <th style="background-color: #d88656; color: white;">Satuan</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Stok</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Harga Satuan</th>
                                <th style="background-color: #d88656; color: white;" class="text-end pe-4">Total Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                @php
                                    $minStockEffective = $row->minimum_stock;
                                    if ($row->is_bahan_setengah_jadi) {
                                        $gudangName = strtolower($row->nama_gudang ?? '');
                                        if (str_contains($gudangName, 'central kitchen')) {
                                            $minStockEffective = $row->minimum_stock_ck;
                                        } elseif (str_contains($gudangName, 'kejingga')) {
                                            $minStockEffective = $row->minimum_stock_kejingga;
                                        } elseif (str_contains($gudangName, 'gaharu')) {
                                            $minStockEffective = $row->minimum_stock_gaharu;
                                        }
                                    }
                                    $isKritis = $minStockEffective !== null && $row->jumlah <= $minStockEffective;
                                @endphp
                                <tr class="{{ $isKritis ? 'table-danger' : '' }}">
                                    <td class="px-4 font-monospace fw-semibold" style="font-size:12px; color:#d88656;">
                                        {{ $row->kode_barang }}
                                    </td>
                                    <td class="fw-semibold">{{ $row->nama_barang }}</td>
                                    <td>
                                        @if($row->is_bahan_baku)
                                            <span style="font-size:11px; font-weight:600; color:#6c757d;">Bahan Baku</span>
                                        @elseif($row->is_bahan_setengah_jadi)
                                            <span style="font-size:11px; font-weight:600; color:#0dcaf0;">Bahan Setengah Jadi</span>
                                        @elseif($row->is_barang_jadi)
                                            <span style="font-size:11px; font-weight:600; color:#d88656;">Barang Jadi</span>
                                        @elseif($row->is_operational)
                                            <span style="font-size:11px; font-weight:600; color:#0d6efd;">Operational</span>
                                        @else
                                            <span style="font-size:11px; color:#aaa;">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">{{ $row->nama_gudang }}</div>
                                        @if(!empty($row->nama_divisi))
                                            <span class="badge bg-light text-primary border border-primary-subtle" style="font-size: 0.72rem;">
                                                <i class="bi bi-diagram-3 me-1"></i>{{ $row->nama_divisi }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-muted">
                                        <div>{{ $row->satuan }}</div>
                                        @if(!empty($row->satuan_pembelian) && (float)($row->konversi_pembelian ?? 1) > 1)
                                            <small class="text-primary font-monospace" style="font-size: 0.72rem;">
                                                1 {{ $row->satuan_pembelian }} = {{ number_format((float)$row->konversi_pembelian, 0, ',', '.') }} {{ $row->satuan }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold" style="font-size:14px; {{ $isKritis ? 'color:#dc3545;' : 'color:#198754;' }}">
                                            {{ number_format($row->jumlah, 2, ',', '.') }}
                                        </div>
                                        @if(!empty($row->satuan_pembelian) && (float)($row->konversi_pembelian ?? 1) > 1)
                                            <div class="small text-primary fw-semibold mt-1" style="font-size: 0.75rem;">
                                                <i class="bi bi-arrow-repeat me-1"></i>{{ number_format($row->jumlah / (float)$row->konversi_pembelian, 2, ',', '.') }} {{ $row->satuan_pembelian }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end text-muted font-monospace" style="font-size: 12px;">
                                        Rp {{ number_format($row->harga_satuan ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end pe-4 font-monospace fw-bold text-dark" style="font-size: 13px;">
                                        Rp {{ number_format($row->total_harga ?? 0, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        Tidak ada data stok.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($data->count() > 0)
                            <tfoot style="border-top: 2px solid #eadfd4;">
                                <tr style="background-color: #fdfaf7; font-weight: 600;">
                                    <td colspan="5" class="px-4 text-end text-muted">TOTAL</td>
                                    <td class="text-end font-monospace text-success fw-bold" style="font-size: 14px;">
                                        {{ number_format($data->sum('jumlah'), 2, ',', '.') }}
                                    </td>
                                    <td></td>
                                    <td class="text-end pe-4 font-monospace text-success fw-bold" style="font-size: 14px;">
                                        Rp {{ number_format($totalNilaiStok ?? $data->sum('total_harga'), 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gudangSelect = document.getElementById('report_gudang_id');
        const divisiContainer = document.getElementById('report_divisi_container');
        const divisiSelect = document.getElementById('report_divisi_id');
        const currentDivisiId = "{{ request('divisi_id') }}";

        function loadDivisi(gudangId, selectedId = null) {
            if (!gudangId) {
                divisiContainer.style.display = 'none';
                divisiSelect.innerHTML = '<option value="">Semua Divisi</option>';
                return;
            }

            fetch('/gudangs/' + gudangId + '/divisi')
                .then(res => res.json())
                .then(data => {
                    if (data.is_operasional && data.divisi && data.divisi.length > 0) {
                        divisiContainer.style.display = 'block';
                        let opts = '<option value="">Semua Divisi</option>';
                        data.divisi.forEach(d => {
                            let isSel = (selectedId && selectedId == d.id) ? 'selected' : '';
                            opts += `<option value="${d.id}" ${isSel}>${d.nama}</option>`;
                        });
                        divisiSelect.innerHTML = opts;
                    } else {
                        divisiContainer.style.display = 'none';
                        divisiSelect.innerHTML = '<option value="">Semua Divisi</option>';
                    }
                })
                .catch(() => {
                    divisiContainer.style.display = 'none';
                });
        }

        if (gudangSelect) {
            gudangSelect.addEventListener('change', function() {
                loadDivisi(this.value);
            });

            if (gudangSelect.value) {
                loadDivisi(gudangSelect.value, currentDivisiId);
            }
        }
    });
    </script>
</x-app-layout>