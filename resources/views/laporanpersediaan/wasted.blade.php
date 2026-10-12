<x-app-layout>
    <x-slot name="header">Laporan Wasted / Pemborosan Bahan Baku</x-slot>

    <div class="container-fluid py-2">

        {{-- ── TITLE & BREADCRUMB ── --}}
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1" style="color: #2c3e50;">
                    <i class="bi bi-trash3-fill text-danger me-2"></i>Laporan Wasted / Pemborosan Bahan Baku
                </h4>
                <p class="text-muted small mb-0">
                    Memuat total pemborosan (wasted/busuk) tiap barang, alokasi per outlet/divisi, dan rasio perbandingannya terhadap total pembelian.
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
                            <i class="bi bi-boxes text-purple fs-5" style="color: #6c5ce7;"></i>
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

        {{-- ── TABEL UTAMA LAPORAN WASTED ── --}}
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-table me-2 text-primary"></i>Rincian Wasted Tiap Barang &amp; Alokasi Outlet
                    </h6>
                    <small class="text-muted">
                        Periode: {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}
                    </small>
                </div>
                <span class="badge bg-light text-dark border px-3 py-2 fw-semibold" style="font-size: 12px;">
                    Total: {{ $items->count() }} Jenis Bahan
                </span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef;">
                            <tr>
                                <th class="text-center py-3" style="width: 40px;">No</th>
                                <th class="py-3" style="min-width: 200px;">Nama Barang / Bahan</th>
                                <th class="text-center py-3" style="width: 80px;">Satuan</th>
                                <th class="text-end py-3 text-danger fw-bold" style="min-width: 130px; background-color: #fff5f5;">Qty Wasted</th>
                                <th class="text-end py-3 text-danger fw-bold" style="min-width: 140px; background-color: #fff5f5;">Nominal Wasted</th>
                                <th class="py-3" style="min-width: 280px;">Alokasi Outlet &amp; Divisi</th>
                                <th class="text-end py-3" style="min-width: 120px; background-color: #f8fafc;">Qty Beli</th>
                                <th class="text-end py-3" style="min-width: 130px; background-color: #f8fafc;">Nominal Beli</th>
                                <th class="text-center py-3" style="min-width: 110px;">% Wasted (Qty)</th>
                                <th class="text-center py-3" style="min-width: 110px;">% Wasted (Rp)</th>
                                <th class="text-center py-3" style="width: 80px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $idx => $item)
                                @php
                                    $pQty = $item['persen_qty'];
                                    $pNom = $item['persen_nominal'];

                                    // Helper style untuk badge persentase
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
                                    <td class="text-end fw-bold text-danger" style="background-color: #fffdfd;">
                                        {{ number_format($item['total_qty_wasted'], 2, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-bold text-danger" style="background-color: #fffdfd;">
                                        Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}
                                    </td>

                                    {{-- Alokasi Outlet / Divisi --}}
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($item['alokasi'] as $loc)
                                                @php
                                                    $locPercent = $item['total_qty_wasted'] > 0 
                                                        ? round(($loc['qty'] / $item['total_qty_wasted']) * 100, 1) 
                                                        : 0;
                                                @endphp
                                                <div class="d-flex justify-content-between align-items-center bg-light px-2 py-1 rounded border" style="font-size: 11px;">
                                                    <span class="fw-semibold text-dark">
                                                        <i class="bi bi-geo-alt-fill me-1 text-danger" style="font-size: 10px;"></i>{{ $loc['lokasi'] }}
                                                    </span>
                                                    <span class="text-end">
                                                        <strong>{{ number_format($loc['qty'], 2, ',', '.') }}</strong> {{ $item['satuan'] }}
                                                        <span class="text-muted">({{ $locPercent }}%)</span>
                                                        &bull; <span class="text-danger">Rp {{ number_format($loc['nominal'], 0, ',', '.') }}</span>
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>

                                    {{-- Total Pembelian --}}
                                    <td class="text-end text-muted font-monospace" style="background-color: #fbfcfd;">
                                        @if($item['total_qty_beli'] > 0)
                                            {{ number_format($item['total_qty_beli'], 2, ',', '.') }}
                                        @else
                                            <span class="text-muted fst-italic">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-dark" style="background-color: #fbfcfd;">
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

                                    {{-- Aksi Rincian --}}
                                    <td class="text-center">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary p-1 px-2 btn-show-detail-wasted shadow-sm"
                                                data-barang-nama="{{ $item['nama_barang'] }}"
                                                data-barang-kode="{{ $item['kode_barang'] }}"
                                                data-barang-satuan="{{ $item['satuan'] }}"
                                                data-total-qty="{{ number_format($item['total_qty_wasted'], 2, ',', '.') }}"
                                                data-total-nominal="Rp {{ number_format($item['total_nominal_wasted'], 0, ',', '.') }}"
                                                data-transaksi="{{ json_encode($item['transaksi_list']) }}"
                                                title="Lihat Rincian Transaksi PBK Wasted">
                                            <i class="bi bi-info-circle me-1"></i> Rincian
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                        <strong>Tidak ada transaksi wasted tercatat pada periode ini.</strong>
                                        <p class="small text-muted mb-0">Coba ubah filter rentang tanggal, gudang, atau status dokumen.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($items->isNotEmpty())
                            <tfoot class="table-light fw-bold text-dark border-top">
                                <tr>
                                    <td colspan="3" class="text-end py-3 text-uppercase">TOTAL KESELURUHAN:</td>
                                    <td class="text-end py-3 text-danger fw-bold" style="background-color: #fff5f5;">
                                        {{ number_format($summary['total_qty_wasted'], 2, ',', '.') }}
                                    </td>
                                    <td class="text-end py-3 text-danger fw-bold" style="background-color: #fff5f5;">
                                        Rp {{ number_format($summary['total_nominal_wasted'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 text-muted small">
                                        Akumulasi seluruh outlet &amp; divisi
                                    </td>
                                    <td class="text-end py-3" style="background-color: #f8fafc;">—</td>
                                    <td class="text-end py-3 text-dark fw-bold" style="background-color: #f8fafc;">
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

    {{-- ── MODAL RINCIAN DOKUMEN WASTED ── --}}
    <div class="modal fade" id="modalDetailWasted" tabindex="-1" aria-labelledby="modalDetailWastedLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background-color: #d88656;">
                    <div>
                        <h5 class="modal-title fw-bold" id="modalDetailWastedLabel">
                            <i class="bi bi-trash3-fill me-2"></i>Rincian Dokumen Wasted
                        </h5>
                        <small id="modalBarangSub" class="opacity-75"></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3 p-3 bg-light rounded border">
                        <div class="col-6">
                            <span class="text-muted small d-block">Total Kuantitas Wasted:</span>
                            <span id="modalTotalQty" class="fw-bold fs-6 text-danger"></span>
                        </div>
                        <div class="col-6 text-end">
                            <span class="text-muted small d-block">Total Nominal Kerugian:</span>
                            <span id="modalTotalNominal" class="fw-bold fs-6 text-danger"></span>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;">Daftar Transaksi PBK Wasted:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>No. PBK</th>
                                    <th>Tanggal</th>
                                    <th>Lokasi (Gudang - Divisi)</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th class="text-end">Total HPP</th>
                                    <th>Keterangan / Alasan</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
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
        const modalTableBody = document.getElementById('modalTableBody');

        document.querySelectorAll('.btn-show-detail-wasted').forEach(btn => {
            btn.addEventListener('click', function() {
                const nama = this.dataset.barangNama;
                const kode = this.dataset.barangKode;
                const satuan = this.dataset.barangSatuan;
                const totalQty = this.dataset.totalQty;
                const totalNominal = this.dataset.totalNominal;
                const transaksiList = JSON.parse(this.dataset.transaksi || '[]');

                modalBarangSub.textContent = `${nama} (${kode})`;
                modalTotalQty.textContent = `${totalQty} ${satuan.toUpperCase()}`;
                modalTotalNominal.textContent = totalNominal;

                let rowsHtml = '';
                if (transaksiList.length > 0) {
                    transaksiList.forEach(t => {
                        const hrgSat = Number(t.harga_satuan).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        const nom = Number(t.nominal).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        const qtyFmt = Number(t.qty).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

                        rowsHtml += `
                            <tr>
                                <td class="font-monospace fw-bold text-dark">${t.kode}</td>
                                <td>${t.tanggal}</td>
                                <td><span class="badge bg-light text-dark border">${t.lokasi}</span></td>
                                <td class="text-end fw-semibold text-danger">${qtyFmt} ${satuan.toUpperCase()}</td>
                                <td class="text-end text-muted">Rp ${hrgSat}</td>
                                <td class="text-end fw-bold text-danger">Rp ${nom}</td>
                                <td class="text-muted small">${t.keterangan || '-'}</td>
                            </tr>
                        `;
                    });
                } else {
                    rowsHtml = '<tr><td colspan="7" class="text-center py-3 text-muted">Tidak ada rincian transaksi</td></tr>';
                }

                modalTableBody.innerHTML = rowsHtml;
                modal.show();
            });
        });
    });
    </script>
</x-app-layout>
