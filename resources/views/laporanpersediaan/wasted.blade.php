<x-app-layout>
    <x-slot name="header">Laporan Wasted / Pemborosan Bahan Baku</x-slot>

    <div class="container-fluid py-2">

        {{-- ── TITLE & ACTIONS ── --}}
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1" style="color: #2c3e50;">
                    <i class="bi bi-trash3-fill text-danger me-2"></i>Laporan Wasted / Pemborosan Bahan Baku
                </h4>
                <p class="text-muted small mb-0">
                    Ringkasan pemborosan bahan baku tiap barang, rasio terhadap pembelian, dan alokasi divisi/outlet.
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="{{ route('laporan.wasted', array_merge(request()->all(), ['format' => 'excel'])) }}"
                   class="btn btn-sm btn-outline-success fw-semibold shadow-sm d-flex align-items-center px-3"
                   title="Export Data ke Excel (CSV)">
                    <i class="bi bi-file-earmark-spreadsheet me-1 fs-6"></i> Export Excel
                </a>
                <a href="{{ route('laporan.wasted', array_merge(request()->all(), ['format' => 'pdf'])) }}"
                   class="btn btn-sm btn-outline-danger fw-semibold shadow-sm d-flex align-items-center px-3"
                   target="_blank"
                   title="Cetak Laporan ke PDF">
                    <i class="bi bi-file-earmark-pdf me-1 fs-6"></i> Cetak PDF
                </a>
            </div>
        </div>

        {{-- ── FILTER CARD ── --}}
        <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="{{ route('laporan.wasted') }}" class="row g-2 g-md-3 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">DARI TANGGAL</label>
                        <input type="date" name="dari" class="form-control form-control-sm" value="{{ $dari }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">SAMPAI TANGGAL</label>
                        <input type="date" name="sampai" class="form-control form-control-sm" value="{{ $sampai }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">OUTLET / GUDANG</label>
                        <select name="gudang_id" id="report_gudang_id" class="form-select form-select-sm">
                            <option value="">Semua Outlet & Gudang</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                    {{ $g->nama }} ({{ $g->kategori }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2" id="report_divisi_container" style="{{ !request('divisi_id') && !request('gudang_id') ? 'display:none;' : '' }}">
                        <label class="form-label fw-semibold text-muted small mb-1">DIVISI</label>
                        <select name="divisi_id" id="report_divisi_id" class="form-select form-select-sm">
                            <option value="">Semua Divisi</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">KATEGORI BARANG</label>
                        <select name="kategori_id" class="form-select form-select-sm">
                            <option value="">Semua Kategori</option>
                            @foreach($kategoris as $kat)
                                <option value="{{ $kat->id }}" {{ request('kategori_id') == $kat->id ? 'selected' : '' }}>
                                    {{ $kat->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">CARI BARANG</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama / Kode Barang..." value="{{ request('search') }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">STATUS PBK</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="approved" {{ request('status', 'approved') === 'approved' ? 'selected' : '' }}>Hanya Approved (Sah)</option>
                            <option value="all"      {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status</option>
                            <option value="draft"    {{ request('status') === 'draft' ? 'selected' : '' }}>Hanya Draft</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">URUTKAN</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="nominal_desc" {{ request('sort', 'nominal_desc') === 'nominal_desc' ? 'selected' : '' }}>Nominal Wasted Tertinggi</option>
                            <option value="nominal_asc"  {{ request('sort') === 'nominal_asc' ? 'selected' : '' }}>Nominal Wasted Terendah</option>
                            <option value="qty_desc"     {{ request('sort') === 'qty_desc' ? 'selected' : '' }}>Qty Wasted Tertinggi</option>
                            <option value="qty_asc"      {{ request('sort') === 'qty_asc' ? 'selected' : '' }}>Qty Wasted Terendah</option>
                            <option value="persen_desc"  {{ request('sort') === 'persen_desc' ? 'selected' : '' }}>% Wasted Tertinggi</option>
                            <option value="persen_asc"   {{ request('sort') === 'persen_asc' ? 'selected' : '' }}>% Wasted Terendah</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm text-white fw-semibold flex-grow-1 shadow-sm" style="background-color: #d88656; border: none; border-radius: 8px;">
                            <i class="bi bi-filter me-1"></i> Terapkan
                        </button>
                        <a href="{{ route('laporan.wasted') }}" class="btn btn-sm btn-outline-secondary shadow-sm" title="Reset Filter" style="border-radius: 8px;">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── SUMMARY / KPI CARDS ── --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-semibold" style="font-size: 11px; opacity: 0.9; letter-spacing: 0.5px;">Total Kerugian Wasted</span>
                            <i class="bi bi-trash3-fill fs-5" style="opacity: 0.85;"></i>
                        </div>
                        <div class="fw-bold" style="font-size: 22px;">
                            Rp {{ number_format($summary['total_nominal_wasted'], 0, ',', '.') }}
                        </div>
                        <div class="small mt-1" style="font-size: 11px; opacity: 0.9;">
                            {{ number_format($summary['total_qty_wasted'], 2, ',', '.') }} total kuantitas busuk/rusak
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm" style="background: #ffffff; border-left: 4px solid #3498db !important; border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-semibold text-muted" style="font-size: 11px; letter-spacing: 0.5px;">Total Pembelian Terkait</span>
                            <i class="bi bi-cart-check-fill text-primary fs-5"></i>
                        </div>
                        <div class="fw-bold text-dark" style="font-size: 22px;">
                            Rp {{ number_format($summary['total_nominal_beli'], 0, ',', '.') }}
                        </div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">
                            Belanja barang terdampak pada periode ini
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                @php
                    $globalRatio = $summary['global_persen_nominal'];
                    $ratioBg = $globalRatio > 15 ? 'linear-gradient(135deg, #d35400 0%, #e67e22 100%)' : ($globalRatio > 5 ? 'linear-gradient(135deg, #f39c12 0%, #f1c40f 100%)' : 'linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)');
                @endphp
                <div class="card h-100 border-0 shadow-sm text-white" style="background: {{ $ratioBg }}; border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-semibold" style="font-size: 11px; opacity: 0.9; letter-spacing: 0.5px;">Rasio Global Wasted / Beli</span>
                            <i class="bi bi-percent fs-5" style="opacity: 0.85;"></i>
                        </div>
                        <div class="fw-bold" style="font-size: 24px;">
                            {{ number_format($globalRatio, 2, ',', '.') }}%
                        </div>
                        <div class="small mt-1" style="font-size: 11px; opacity: 0.9;">
                            Perbandingan nominal pemborosan terhadap belanja
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm" style="background: #ffffff; border-left: 4px solid #6c5ce7 !important; border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-semibold text-muted" style="font-size: 11px; letter-spacing: 0.5px;">Bahan Terdampak Wasted</span>
                            <i class="bi bi-boxes fs-5" style="color: #6c5ce7;"></i>
                        </div>
                        <div class="fw-bold text-dark" style="font-size: 22px;">
                            {{ $summary['total_items_wasted'] }} <span style="font-size: 14px; font-weight: normal; color: #6c757d;">Item</span>
                        </div>
                        <div class="small text-muted mt-1" style="font-size: 11px;">
                            Dari {{ $summary['total_dokumen'] }} dokumen PBK Wasted
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TABEL UTAMA LAPORAN WASTED (RINGKAS & USER FRIENDLY) ── --}}
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-table me-2 text-primary"></i>Ringkasan Wasted Bahan Baku &amp; Rasio Pembelian
                    </h6>
                    <small class="text-muted">
                        Periode: {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border px-2 py-1 small">
                        <i class="bi bi-info-circle me-1"></i>Klik "Detail" untuk melihat rincian divisi &amp; dokumen
                    </span>
                    <span class="badge bg-light text-dark border px-3 py-2 fw-semibold" style="font-size: 12px;">
                        Total: {{ $items->count() }} Bahan
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead>
                            @php
                                $currentSort = request('sort', 'nominal_desc');
                                $sortLink = function($col) use ($currentSort) {
                                    $next = ($currentSort === "{$col}_desc") ? "{$col}_asc" : "{$col}_desc";
                                    return request()->fullUrlWithQuery(['sort' => $next]);
                                };
                                $sortIcon = function($col) use ($currentSort) {
                                    if ($currentSort === "{$col}_desc") return '<i class="bi bi-arrow-down-short ms-1 text-danger"></i>';
                                    if ($currentSort === "{$col}_asc")  return '<i class="bi bi-arrow-up-short ms-1 text-danger"></i>';
                                    return '<i class="bi bi-arrow-down-up ms-1 text-muted opacity-50" style="font-size: 11px;"></i>';
                                };
                            @endphp
                            <tr>
                                <th class="text-center py-3" style="width: 45px;">No</th>
                                <th class="py-3" style="min-width: 180px;">Nama Barang / Bahan</th>
                                <th class="text-center py-3" style="width: 70px;">Satuan</th>
                                <th class="text-end py-3 text-danger fw-bold" style="min-width: 110px;">
                                    <a href="{{ $sortLink('qty') }}" class="text-danger text-decoration-none d-inline-flex align-items-center justify-content-end" title="Urutkan Qty Wasted">
                                        Qty Wasted {!! $sortIcon('qty') !!}
                                    </a>
                                </th>
                                <th class="text-end py-3 text-danger fw-bold" style="min-width: 125px;">
                                    <a href="{{ $sortLink('nominal') }}" class="text-danger text-decoration-none d-inline-flex align-items-center justify-content-end" title="Urutkan Nominal Wasted">
                                        Nominal Wasted {!! $sortIcon('nominal') !!}
                                    </a>
                                </th>
                                <th class="text-center py-3" style="min-width: 130px;">Alokasi Divisi</th>
                                <th class="text-end py-3" style="min-width: 110px;">Qty Beli</th>
                                <th class="text-end py-3" style="min-width: 125px;">Nominal Beli</th>
                                <th class="text-center py-3" style="min-width: 100px;">% Wasted (Qty)</th>
                                <th class="text-center py-3" style="min-width: 105px;">
                                    <a href="{{ $sortLink('persen') }}" class="text-dark text-decoration-none d-inline-flex align-items-center justify-content-center" title="Urutkan % Wasted">
                                        % Wasted (Rp) {!! $sortIcon('persen') !!}
                                    </a>
                                </th>
                                <th class="text-center py-3" style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $idx => $item)
                                @php
                                    $pQty = $item['persen_qty'];
                                    $pNom = $item['persen_nominal'];
                                    $locCount = count($item['alokasi']);
                                    $firstLoc = $locCount > 0 ? reset($item['alokasi']) : null;

                                    // Helper style badge persentase
                                    $getBadgeClass = function($val) {
                                        if ($val === null) return 'bg-secondary text-white';
                                        if ($val > 15) return 'bg-danger text-white';
                                        if ($val > 5)  return 'bg-warning text-dark';
                                        return 'bg-success text-white';
                                    };
                                @endphp
                                <tr>
                                    <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $item['nama_barang'] }}</div>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 10px;">
                                                {{ $item['kode_barang'] }}
                                            </span>
                                            <span class="badge bg-light text-dark border" style="font-size: 10px;">
                                                {{ $item['kategori'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center font-monospace small">
                                        <span class="badge bg-light text-dark border">{{ strtoupper($item['satuan']) }}</span>
                                    </td>

                                    {{-- Qty & Nominal Wasted --}}
                                    <td class="text-end fw-bold text-danger">
                                        {{ number_format($item['total_qty_wasted'], 2, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}
                                    </td>

                                    {{-- Ringkasan Alokasi Lokasi (Singkat & User-Friendly) --}}
                                    <td class="text-center">
                                        @if($locCount === 1)
                                            <button type="button" 
                                                    class="btn btn-sm btn-light border py-1 px-2 rounded-pill text-dark fw-semibold btn-show-detail-wasted shadow-none" 
                                                    style="font-size: 11px;"
                                                    data-barang-nama="{{ $item['nama_barang'] }}"
                                                    data-barang-kode="{{ $item['kode_barang'] }}"
                                                    data-barang-kategori="{{ $item['kategori'] }}"
                                                    data-barang-satuan="{{ $item['satuan'] }}"
                                                    data-total-qty="{{ number_format($item['total_qty_wasted'], 2, ',', '.') }}"
                                                    data-total-nominal="Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}"
                                                    data-total-beli-qty="{{ number_format($item['total_qty_beli'], 2, ',', '.') }}"
                                                    data-total-beli-nominal="Rp {{ number_format($item['total_nominal_beli'], 0, ',', '.') }}"
                                                    data-persen-qty="{{ $pQty !== null ? number_format($pQty, 2, ',', '.') . '%' : 'Tanpa Pembelian' }}"
                                                    data-persen-nominal="{{ $pNom !== null ? number_format($pNom, 2, ',', '.') . '%' : 'Tanpa Pembelian' }}"
                                                    data-alokasi="{{ json_encode(array_values($item['alokasi'])) }}"
                                                    data-transaksi="{{ json_encode($item['transaksi_list']) }}"
                                                    title="Klik untuk melihat rincian alokasi">
                                                <i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ $firstLoc['lokasi'] }}
                                            </button>
                                        @elseif($locCount > 1)
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger py-1 px-2 rounded-pill fw-semibold btn-show-detail-wasted" 
                                                    style="font-size: 11px;"
                                                    data-barang-nama="{{ $item['nama_barang'] }}"
                                                    data-barang-kode="{{ $item['kode_barang'] }}"
                                                    data-barang-kategori="{{ $item['kategori'] }}"
                                                    data-barang-satuan="{{ $item['satuan'] }}"
                                                    data-total-qty="{{ number_format($item['total_qty_wasted'], 2, ',', '.') }}"
                                                    data-total-nominal="Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}"
                                                    data-total-beli-qty="{{ number_format($item['total_qty_beli'], 2, ',', '.') }}"
                                                    data-total-beli-nominal="Rp {{ number_format($item['total_nominal_beli'], 0, ',', '.') }}"
                                                    data-persen-qty="{{ $pQty !== null ? number_format($pQty, 2, ',', '.') . '%' : 'Tanpa Pembelian' }}"
                                                    data-persen-nominal="{{ $pNom !== null ? number_format($pNom, 2, ',', '.') . '%' : 'Tanpa Pembelian' }}"
                                                    data-alokasi="{{ json_encode(array_values($item['alokasi'])) }}"
                                                    data-transaksi="{{ json_encode($item['transaksi_list']) }}"
                                                    title="Tersebar di {{ $locCount }} divisi/outlet. Klik untuk rincian.">
                                                <i class="bi bi-geo-alt-fill me-1"></i>{{ $locCount }} Divisi / Outlet <i class="bi bi-chevron-right ms-1" style="font-size: 9px;"></i>
                                            </button>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    {{-- Total Pembelian --}}
                                    <td class="text-end text-muted font-monospace">
                                        @if($item['total_qty_beli'] > 0)
                                            {{ number_format($item['total_qty_beli'], 2, ',', '.') }}
                                        @else
                                            <span class="text-muted fst-italic">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-dark">
                                        @if($item['total_nominal_beli'] > 0)
                                            Rp {{ number_format($item['total_nominal_beli'], 0, ',', '.') }}
                                        @else
                                            <span class="text-muted fst-italic">Rp 0</span>
                                        @endif
                                    </td>

                                    {{-- Persentase Wasted terhadap Pembelian --}}
                                    <td class="text-center">
                                        @if($pQty !== null)
                                            <span class="badge {{ $getBadgeClass($pQty) }} px-2 py-1 shadow-sm" style="font-size: 11px;">
                                                {{ number_format($pQty, 2, ',', '.') }}%
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 10px;" title="Barang tidak dibeli pada periode ini (wasted dari saldo persediaan lama)">
                                                Tanpa Beli
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($pNom !== null)
                                            <span class="badge {{ $getBadgeClass($pNom) }} px-2 py-1 shadow-sm" style="font-size: 11px;">
                                                {{ number_format($pNom, 2, ',', '.') }}%
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 10px;" title="Barang tidak dibeli pada periode ini (wasted dari saldo persediaan lama)">
                                                Tanpa Beli
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Tombol Detail --}}
                                    <td class="text-center">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary px-2 py-1 fw-semibold btn-show-detail-wasted shadow-sm"
                                                style="border-radius: 6px; font-size: 12px;"
                                                data-barang-nama="{{ $item['nama_barang'] }}"
                                                data-barang-kode="{{ $item['kode_barang'] }}"
                                                data-barang-kategori="{{ $item['kategori'] }}"
                                                data-barang-satuan="{{ $item['satuan'] }}"
                                                data-total-qty="{{ number_format($item['total_qty_wasted'], 2, ',', '.') }}"
                                                data-total-nominal="Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}"
                                                data-total-beli-qty="{{ number_format($item['total_qty_beli'], 2, ',', '.') }}"
                                                data-total-beli-nominal="Rp {{ number_format($item['total_nominal_beli'], 0, ',', '.') }}"
                                                data-persen-qty="{{ $pQty !== null ? number_format($pQty, 2, ',', '.') . '%' : 'Tanpa Pembelian' }}"
                                                data-persen-nominal="{{ $pNom !== null ? number_format($pNom, 2, ',', '.') . '%' : 'Tanpa Pembelian' }}"
                                                data-alokasi="{{ json_encode(array_values($item['alokasi'])) }}"
                                                data-transaksi="{{ json_encode($item['transaksi_list']) }}"
                                                title="Lihat Rincian Alokasi Divisi &amp; Riwayat Dokumen PBK Wasted">
                                            <i class="bi bi-eye me-1"></i>Detail
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                        <strong>Tidak ada transaksi wasted tercatat pada periode ini.</strong>
                                        <p class="small text-muted mb-0">Coba sesuaikan filter rentang tanggal atau gudang.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($items->isNotEmpty())
                            <tfoot class="table-light fw-bold text-dark border-top">
                                <tr>
                                    <td colspan="3" class="text-end py-3 text-uppercase">TOTAL KESELURUHAN:</td>
                                    <td class="text-end py-3 text-danger fw-bold">
                                        {{ number_format($summary['total_qty_wasted'], 2, ',', '.') }}
                                    </td>
                                    <td class="text-end py-3 text-danger fw-bold">
                                        Rp {{ number_format($summary['total_nominal_wasted'], 0, ',', '.') }}
                                    </td>
                                    <td class="text-center py-3 text-muted small">
                                        {{ $summary['total_items_wasted'] }} Item
                                    </td>
                                    <td class="text-end py-3 text-muted">—</td>
                                    <td class="text-end py-3 text-dark fw-bold">
                                        Rp {{ number_format($summary['total_nominal_beli'], 0, ',', '.') }}
                                    </td>
                                    <td class="text-center py-3">—</td>
                                    <td class="text-center py-3">
                                        <span class="badge {{ $getBadgeClass($summary['global_persen_nominal']) }} px-2 py-1 shadow-sm" style="font-size: 12px;">
                                            {{ number_format($summary['global_persen_nominal'], 2, ',', '.') }}%
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- ── MODAL DETAIL RINCIAN ALOKASI DIVISI & DOKUMEN WASTED ── --}}
    <div class="modal fade" id="modalDetailWasted" tabindex="-1" aria-labelledby="modalDetailWastedLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow" style="border-radius: 12px;">
                <div class="modal-header text-white" style="background-color: #d88656; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <div>
                        <h5 class="modal-title fw-bold" id="modalDetailWastedLabel">
                            <i class="bi bi-geo-alt-fill me-2"></i>Rincian Alokasi Divisi &amp; Riwayat Wasted
                        </h5>
                        <div id="modalBarangSub" class="opacity-90 small mt-1 font-monospace"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Mini KPI Card inside Modal --}}
                    <div class="row g-2 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded border bg-light text-center h-100">
                                <span class="text-muted d-block" style="font-size: 11px;">Total Qty Wasted</span>
                                <span id="modalTotalQty" class="fw-bold text-danger fs-6"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded border bg-light text-center h-100">
                                <span class="text-muted d-block" style="font-size: 11px;">Total Kerugian Wasted</span>
                                <span id="modalTotalNominal" class="fw-bold text-danger fs-6"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded border bg-light text-center h-100">
                                <span class="text-muted d-block" style="font-size: 11px;">Total Pembelian Terkait</span>
                                <span id="modalTotalBeliNominal" class="fw-bold text-dark fs-6"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded border bg-light text-center h-100">
                                <span class="text-muted d-block" style="font-size: 11px;">Rasio Wasted / Beli</span>
                                <span id="modalPersenNominal" class="fw-bold fs-6"></span>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 1: RINCIAN ALOKASI OUTLET & DIVISI --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;">
                                <i class="bi bi-diagram-3-fill me-1 text-danger"></i> 1. Alokasi Wasted pada Outlet &amp; Divisi
                            </h6>
                            <small class="text-muted" style="font-size: 11px;">Distribusi tempat terjadinya pemborosan</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">No</th>
                                        <th>Outlet &amp; Divisi Terkait</th>
                                        <th class="text-end" style="width: 140px;">Kuantitas Wasted</th>
                                        <th class="text-center" style="width: 90px;">Porsi (%)</th>
                                        <th class="text-end" style="width: 150px;">Nominal Kerugian</th>
                                        <th class="text-center" style="width: 80px;">Transaksi</th>
                                    </tr>
                                </thead>
                                <tbody id="modalAlokasiBody">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- SECTION 2: RIWAYAT DOKUMEN TRANSAKSI PBK WASTED --}}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;">
                                <i class="bi bi-file-earmark-text-fill me-1 text-primary"></i> 2. Riwayat Dokumen Transaksi PBK Wasted
                            </h6>
                            <small class="text-muted" style="font-size: 11px;">Daftar dokumen fisik PBK-WST</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th>No. PBK</th>
                                        <th>Tanggal</th>
                                        <th>Lokasi Dokumen</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th class="text-end">Total HPP</th>
                                        <th>Keterangan / Alasan</th>
                                    </tr>
                                </thead>
                                <tbody id="modalTransaksiBody">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── STYLES & SCRIPT ── --}}
    <style>
        .btn-close-white {
            filter: invert(1) grayscale(1) brightness(2);
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Dynamic Divisi Dropdown
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

        // 2. Modal Detail Wasted Handler
        const modalEl = document.getElementById('modalDetailWasted');
        const modal = new bootstrap.Modal(modalEl);
        const modalBarangSub = document.getElementById('modalBarangSub');
        const modalTotalQty = document.getElementById('modalTotalQty');
        const modalTotalNominal = document.getElementById('modalTotalNominal');
        const modalTotalBeliNominal = document.getElementById('modalTotalBeliNominal');
        const modalPersenNominal = document.getElementById('modalPersenNominal');
        const modalAlokasiBody = document.getElementById('modalAlokasiBody');
        const modalTransaksiBody = document.getElementById('modalTransaksiBody');

        document.querySelectorAll('.btn-show-detail-wasted').forEach(btn => {
            btn.addEventListener('click', function() {
                const nama = this.dataset.barangNama;
                const kode = this.dataset.barangKode;
                const kategori = this.dataset.barangKategori || '';
                const satuan = (this.dataset.barangSatuan || '').toUpperCase();
                const totalQty = this.dataset.totalQty;
                const totalNominal = this.dataset.totalNominal;
                const totalBeliNominal = this.dataset.totalBeliNominal;
                const persenNominal = this.dataset.persenNominal;
                const alokasiList = JSON.parse(this.dataset.alokasi || '[]');
                const transaksiList = JSON.parse(this.dataset.transaksi || '[]');

                modalBarangSub.textContent = `${nama} (${kode}) — ${kategori} [${satuan}]`;
                modalTotalQty.textContent = `${totalQty} ${satuan}`;
                modalTotalNominal.textContent = totalNominal;
                modalTotalBeliNominal.textContent = totalBeliNominal;

                // Colorize rasio badge
                modalPersenNominal.textContent = persenNominal;
                if (persenNominal.includes('%')) {
                    const numVal = parseFloat(persenNominal);
                    if (numVal > 15) {
                        modalPersenNominal.className = 'fw-bold fs-6 text-danger';
                    } else if (numVal > 5) {
                        modalPersenNominal.className = 'fw-bold fs-6 text-warning';
                    } else {
                        modalPersenNominal.className = 'fw-bold fs-6 text-success';
                    }
                } else {
                    modalPersenNominal.className = 'fw-bold fs-6 text-secondary';
                }

                // Populate Alokasi Table
                let alokasiHtml = '';
                if (alokasiList.length > 0) {
                    const rawTotalQty = alokasiList.reduce((acc, curr) => acc + Number(curr.qty || 0), 0);
                    alokasiList.forEach((loc, i) => {
                        const locQty = Number(loc.qty || 0);
                        const locNom = Number(loc.nominal || 0);
                        const porsi = rawTotalQty > 0 ? ((locQty / rawTotalQty) * 100).toFixed(1) : '0';
                        const qtyFmt = locQty.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const nomFmt = locNom.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

                        alokasiHtml += `
                            <tr>
                                <td class="text-center text-muted fw-semibold">${i + 1}</td>
                                <td>
                                    <span class="fw-bold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>${loc.lokasi}</span>
                                </td>
                                <td class="text-end fw-bold text-danger">${qtyFmt} ${satuan}</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">${porsi}%</span>
                                </td>
                                <td class="text-end fw-bold text-danger">Rp ${nomFmt}</td>
                                <td class="text-center text-muted">${loc.count || 1} dok</td>
                            </tr>
                        `;
                    });
                } else {
                    alokasiHtml = '<tr><td colspan="6" class="text-center py-3 text-muted">Tidak ada data alokasi</td></tr>';
                }
                modalAlokasiBody.innerHTML = alokasiHtml;

                // Populate Transaksi Table
                let transHtml = '';
                if (transaksiList.length > 0) {
                    transaksiList.forEach(t => {
                        const hrgSat = Number(t.harga_satuan).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        const nom = Number(t.nominal).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        const qtyFmt = Number(t.qty).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        transHtml += `
                            <tr>
                                <td class="font-monospace fw-bold text-dark">${t.kode}</td>
                                <td>${t.tanggal}</td>
                                <td><span class="badge bg-light text-dark border">${t.lokasi}</span></td>
                                <td class="text-end fw-semibold text-danger">${qtyFmt} ${satuan}</td>
                                <td class="text-end text-muted">Rp ${hrgSat}</td>
                                <td class="text-end fw-bold text-danger">Rp ${nom}</td>
                                <td class="text-muted small">${t.keterangan || '-'}</td>
                            </tr>
                        `;
                    });
                } else {
                    transHtml = '<tr><td colspan="7" class="text-center py-3 text-muted">Tidak ada riwayat dokumen transaksi</td></tr>';
                }
                modalTransaksiBody.innerHTML = transHtml;

                modal.show();
            });
        });
    });
    </script>
</x-app-layout>
