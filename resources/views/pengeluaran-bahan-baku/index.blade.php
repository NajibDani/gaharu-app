<x-app-layout>

<div class="container-fluid px-2 px-md-4 py-3">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <div>

            <h2 class="fw-bold mb-0 text-dark">
                Pengeluaran Bahan Baku
            </h2>

            <small class="text-muted">
                Manajemen pengeluaran stok bahan baku produksi
            </small>

        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('dashboard') }}"
               class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Dashboard
            </a>
            <a href="{{ route('pengeluaran-bahan-baku.create', ['jenis' => 'wasted']) }}"
               class="btn btn-outline-danger fw-bold" title="Pengeluaran Stok Wasted / Busuk / Rusak">
                <i class="bi bi-trash3-fill me-1"></i>
                Tambah Wasted / Busuk
            </a>
            <a href="{{ route('pengeluaran-bahan-baku.create') }}"
               class="btn text-white fw-bold shadow-sm" style="background-color: #DE8958;">
                <i class="bi bi-plus-circle"></i>
                Tambah Transfer
            </a>
        </div>

    </div>

    <!-- FILTER BAR -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="border: 1px solid #DCD3CB !important;">
        <div class="card-body py-3">
            <form action="{{ route('pengeluaran-bahan-baku.index') }}" method="GET" id="form-filter-pbk">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label fw-semibold small mb-1">Cari Kode / Keterangan</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no pengeluaran..." value="{{ request('search') }}">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-semibold small mb-1">Urutan Permintaan</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="terbaru" {{ request('sort', 'terbaru') === 'terbaru' ? 'selected' : '' }}>Terbaru</option>
                            <option value="terlama" {{ request('sort') === 'terlama' ? 'selected' : '' }}>Terlama</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-semibold small mb-1">Status Permintaan</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft (Belum Approved)</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved (Disetujui)</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-semibold small mb-1">Filter Gudang Tujuan</label>
                        <select name="gudang_id" id="filter_gudang_select" class="form-select form-select-sm">
                            <option value="">-- Semua Gudang --</option>
                            @foreach($gudangList as $g)
                                <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                    {{ $g->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-semibold small mb-1">Filter Divisi</label>
                        <select name="divisi_id" id="filter_divisi_select" class="form-select form-select-sm">
                            <option value="">-- Semua Divisi --</option>
                            @foreach($divisiList as $div)
                                <option value="{{ $div->id }}" data-gudang-id="{{ $div->gudang_id }}" {{ request('divisi_id') == $div->id ? 'selected' : '' }}>
                                    {{ $div->nama }}{{ $div->gudang ? ' ('.$div->gudang->nama.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 position-relative">
                        <label class="form-label fw-semibold small mb-1">Filter Tanggal</label>
                        <input type="hidden" name="dari" id="filter_dari" value="{{ request('dari') }}">
                        <input type="hidden" name="sampai" id="filter_sampai" value="{{ request('sampai') }}">
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary bg-white text-dark w-100 d-flex align-items-center justify-content-between py-1 px-2 rounded-3 shadow-none border" id="btn-date-range-trigger" style="min-height: 31px;">
                            <span id="date-range-label" class="small text-truncate">
                                <i class="bi bi-calendar3 me-1 text-primary"></i> <span id="text-date-display">Semua Tanggal</span>
                            </span>
                            <i class="bi bi-chevron-down small text-muted ms-1"></i>
                        </button>

                        {{-- POPOVER DATE RANGE PICKER --}}
                        <div id="date-range-popover" class="card border-0 shadow-lg rounded-4 p-3 position-absolute" style="display:none; z-index:1060; width: 680px; max-width: 90vw; top: 105%; right: 0; background: #fff; border: 1px solid #e2e8f0 !important;">
                            <div class="d-flex gap-3">
                                <!-- LEFT PRESETS -->
                                <div class="d-flex flex-column gap-1 flex-shrink-0" style="width: 130px;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="today">Hari Ini</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="yesterday">Kemarin</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="this_week">Minggu Ini</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="last_week">Minggu Lalu</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="this_month">Bulan Ini</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="last_month">Bulan Lalu</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="this_year">Tahun Ini</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="last_year">Tahun Lalu</button>
                                </div>

                                <!-- MIDDLE CALENDAR -->
                                <div class="flex-grow-1 px-2 border-start border-end">
                                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                        <button type="button" class="btn btn-xs btn-light border rounded-circle p-1" id="cal-prev-month" title="Bulan Sebelumnya">
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <div class="fw-bold text-dark font-monospace" id="cal-month-year-title" style="font-size: 0.9rem;"></div>
                                        <button type="button" class="btn btn-xs btn-light border rounded-circle p-1" id="cal-next-month" title="Bulan Selanjutnya">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>

                                    <div class="d-grid mb-1 text-center fw-bold text-muted" style="grid-template-columns: repeat(7, 1fr); font-size: 0.7rem;">
                                        <div>MIN</div><div>SEN</div><div>SEL</div><div>RAB</div><div>KAM</div><div>JUM</div><div>SAB</div>
                                    </div>

                                    <div class="d-grid text-center" id="cal-days-grid" style="grid-template-columns: repeat(7, 1fr); gap: 2px;">
                                    </div>
                                </div>

                                <!-- RIGHT SUMMARY & ACTIONS -->
                                <div class="d-flex flex-column justify-content-between flex-shrink-0" style="width: 140px;">
                                    <div>
                                        <div class="mb-2">
                                            <label class="form-label text-muted small mb-1" style="font-size:0.72rem;">Starts (Mulai)</label>
                                            <input type="text" id="display-range-start" class="form-control form-control-sm text-center bg-light fw-bold" style="font-size:0.75rem;" readonly placeholder="dd/mm/yyyy">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small mb-1" style="font-size:0.72rem;">Ends (Selesai)</label>
                                            <input type="text" id="display-range-end" class="form-control form-control-sm text-center bg-light fw-bold" style="font-size:0.75rem;" readonly placeholder="dd/mm/yyyy">
                                        </div>
                                    </div>

                                    <div class="d-grid gap-1">
                                        <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm py-1" id="btn-apply-date-range">
                                            Apply
                                        </button>
                                        <button type="button" class="btn btn-light btn-sm text-muted py-1" id="btn-reset-date-range" style="font-size:0.75rem;">
                                            Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1 col-md-4 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill" title="Terapkan Filter">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                        @if(request('search') || (request('sort') && request('sort') !== 'terbaru') || request('status') || request('gudang_id') || request('divisi_id') || request('dari') || request('sampai'))
                            <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="btn btn-sm btn-secondary" title="Reset Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- STATISTIK -->
    <div class="row mb-4">

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Total Pengeluaran</h6>

                    <h2 class="fw-bold">
                        {{ $totalCount ?? $data->total() }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Draft (Belum Approved)</h6>

                    <h2 class="fw-bold text-warning">
                        {{ $draftCount ?? 0 }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Approved (Disetujui)</h6>

                    <h2 class="fw-bold text-success">
                        {{ $approvedCount ?? 0 }}
                    </h2>

                </div>

            </div>

        </div>

    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Berhasil!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Gagal!</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ALERT SARAN RESTOCK BAHAN BAKU OUTLET --}}
    @if(!empty($outletSuggestionsSummary))
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-left: 5px solid #f97316 !important;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; font-size: 20px;">
                            <i class="bi bi-shield-exclamation"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-1">
                                Saran Restock Bahan Baku di Outlet / Cabang
                            </h6>
                            <p class="text-muted small mb-0">
                                Terdapat stok Bahan Baku yang berada di bawah batas minimum stock gudang outlet. Klik tombol gudang tujuan untuk langsung membuat pengeluaran bahan baku:
                            </p>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($outletSuggestionsSummary as $sum)
                            <a href="{{ route('pengeluaran-bahan-baku.create', ['gudang_id' => $sum['gudang_id']]) }}" 
                               class="btn btn-sm btn-outline-dark bg-white fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-box-arrow-up-right text-warning"></i>
                                <span>{{ $sum['gudang_nama'] }}</span>
                                <span class="badge bg-danger text-white rounded-pill">{{ $sum['count'] }} item</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- TABEL -->
    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-custom-header">

                        <tr>

                            <th style="background-color: #715745 !important;">No</th>
                            <th style="background-color: #715745 !important;">Kode</th>
                            <th style="background-color: #715745 !important;">Gudang & Divisi Tujuan</th>
                            <th style="background-color: #715745 !important;">Tanggal</th>
                            <th style="background-color: #715745 !important;">Status</th>
                            <th width="180" style="background-color: #715745 !important;">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    @forelse($data as $item)

                        <tr>

                            <td>
                                {{ $data->firstItem() + $loop->index }}
                            </td>

                            <td class="fw-semibold">
                                <div>{{ $item->kode_pengeluaran }}</div>
                                @if(($item->jenis_pengeluaran ?? '') === 'wasted' || str_starts_with($item->kode_pengeluaran, 'PBK-WST-'))
                                    <span class="badge bg-danger" style="font-size:0.7rem;"><i class="bi bi-trash3 me-1"></i>Wasted / Busuk</span>
                                @elseif(str_starts_with($item->kode_pengeluaran, 'PBK-SO-'))
                                    <span class="badge bg-secondary" style="font-size:0.7rem;"><i class="bi bi-clipboard-check me-1"></i>Stock Opname</span>
                                @else
                                    <span class="badge bg-info text-dark" style="font-size:0.7rem;"><i class="bi bi-box-arrow-right me-1"></i>Transfer</span>
                                @endif
                            </td>

                            <td>
                                <div class="fw-bold">{{ $item->nama_gudang }}</div>
                                @if(!empty($item->nama_divisi))
                                    <span class="badge bg-light text-primary border border-primary-subtle mt-1" style="font-size: 0.75rem;">
                                        <i class="bi bi-diagram-3 me-1"></i>{{ $item->nama_divisi }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($item->tanggal)->format('d-m-Y H:i') }}
                            </td>

                            <td>

                                @if(
                                    strtolower($item->status) == 'approved'
                                    ||
                                    strtolower($item->status) == 'disetujui'
                                )

                                    <span class="badge bg-success">
                                        Approved
                                    </span>

                                @else

                                    <span class="badge bg-warning text-dark">
                                        Draft
                                    </span>

                                @endif

                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#detailPengeluaranModal"
                                            onclick="showDetailPengeluaran({{ $item->id }})"
                                            title="Lihat Detail Pengeluaran">
                                        <i class="bi bi-eye me-1"></i> Detail
                                    </button>

                                    @php
                                        $user = auth()->user();
                                        $canApprove = $user && $user->canApprovePengeluaran();
                                        $isSuperAdmin = $user && $user->isSuperAdmin();
                                        $isDraft = strtolower($item->status) === 'draft';
                                    @endphp

                                    @if($isDraft)
                                        <a href="{{ route('pengeluaran-bahan-baku.edit', ['pengeluaran_bahan_baku' => $item->id, 'page' => $data->currentPage()]) }}"
                                           class="btn btn-warning btn-sm" title="Edit Permintaan / Pengeluaran">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        @if($canApprove)
                                            <a href="{{ route('pengeluaran-bahan-baku.approve', $item->id) }}"
                                               class="btn btn-success btn-sm"
                                               onclick="return confirm('Approve pengeluaran ini dan potong stok di gudang terkait?')">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                        @endif

                                        {{-- Tombol Hapus Draft --}}
                                        <form action="{{ route('pengeluaran-bahan-baku.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus draft pengeluaran/permintaan {{ $item->kode_pengeluaran }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm text-white" title="Hapus Draft">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    @elseif($isSuperAdmin)
                                        {{-- SUPER ADMIN BOLEH EDIT & HAPUS APPROVED --}}
                                        <a href="{{ route('pengeluaran-bahan-baku.edit', ['pengeluaran_bahan_baku' => $item->id, 'page' => $data->currentPage()]) }}"
                                           class="btn btn-warning btn-sm" title="Edit Pengeluaran (Super Admin)">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form action="{{ route('pengeluaran-bahan-baku.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('PERINGATAN SUPER ADMIN: Dokumen {{ $item->kode_pengeluaran }} ini telah disetujui.\nMenghapusnya akan membatalkan pemotongan/mutasi stok dan mengembalikannya ke gudang asal.\n\nYakin ingin menghapus?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm text-white" title="Hapus Pengeluaran Approved (Super Admin)">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6"
                                class="text-center text-muted">

                                Belum ada data pengeluaran bahan baku.

                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>
            </div>
        </div>
        <div class="mt-3">
            {{ $data->links() }}
        </div>
    </div>

</div>

{{-- MODAL DETAIL PENGELUARAN --}}
<div class="modal fade" id="detailPengeluaranModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background:#7A4517;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0">Detail Permintaan / Transfer Bahan Baku</h5>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto me-2" id="modalTopActions" style="display:none !important;">
                    <button type="button" class="btn btn-sm btn-light text-success fw-semibold px-3 shadow-sm" onclick="refreshCurrentModalStok(this)" id="modalBtnRefreshStok" title="Perbarui dan cek kembali ketersediaan stok terkini di gudang utama">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Stok
                    </button>
                    <a href="#" id="modalBtnPdf" target="_blank" class="btn btn-sm btn-light text-danger fw-semibold px-3 shadow-sm">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Save PDF
                    </a>
                    <button type="button" class="btn btn-sm btn-light text-primary fw-semibold px-3 shadow-sm" onclick="downloadModalAsImage()">
                        <i class="bi bi-image me-1"></i> Save Image
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light px-2" onclick="printModalContent()" title="Print">
                        <i class="bi bi-printer"></i>
                    </button>
                </div>
                <button type="button" class="btn-close btn-close-white ms-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detailPengeluaranBody">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 mb-0">Memuat detail pengeluaran...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spinClockwise {
    100% { transform: rotate(360deg); }
}
.spin-clockwise {
    display: inline-block;
    animation: spinClockwise 0.8s linear infinite;
}
</style>

{{-- HTML2CANVAS LIBRARY UNTUK SAVE IMAGE --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
let currentDetailData = null;

function refreshCurrentModalStok(triggerEl) {
    if (!currentDetailData || !currentDetailData.id) return;
    
    let btn = document.getElementById('modalBtnRefreshStok') || triggerEl;
    let icon = btn ? btn.querySelector('i') : null;
    if (icon) {
        icon.classList.add('spin-clockwise');
    }
    if (btn) btn.disabled = true;

    fetch(`/pengeluaran-bahan-baku/${currentDetailData.id}/detail-json`)
        .then(response => response.json())
        .then(data => {
            currentDetailData = data;
            renderDetailPengeluaran(data);

            let alertEl = document.createElement('div');
            alertEl.className = 'alert alert-success alert-dismissible fade show py-2 px-3 mb-3 rounded-3 shadow-sm border-0 bg-success-subtle text-success-emphasis';
            alertEl.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-check-circle-fill me-2 fs-6 text-success"></i>
                        <span class="small fw-semibold">Stok terkini di <strong>${data.is_wasted ? data.lokasi_nama : data.gudang_utama_nama}</strong> berhasil diperbarui!</span>
                    </div>
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert" style="font-size: 0.75rem;"></button>
                </div>
            `;
            let printArea = document.getElementById('printableDetailArea');
            if (printArea) {
                printArea.insertBefore(alertEl, printArea.firstChild);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Gagal merefresh stok: ' + err.message);
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                if (icon) icon.classList.remove('spin-clockwise');
            }
        });
}

function showDetailPengeluaran(id) {
    let body = document.getElementById('detailPengeluaranBody');
    let topActions = document.getElementById('modalTopActions');

    if (topActions) topActions.style.setProperty('display', 'none', 'important');
    if (body) {
        body.innerHTML = `
            <div class="text-center text-muted py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 mb-0">Memuat detail pengeluaran & ketersediaan stok...</p>
            </div>
        `;
    }

    try {
        let modalEl = document.getElementById('detailPengeluaranModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    } catch (e) {
        console.warn('Bootstrap modal instance handle notice:', e);
    }

    fetch(`/pengeluaran-bahan-baku/${id}/detail-json`)
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            currentDetailData = data;
            renderDetailPengeluaran(data);
            if (topActions) topActions.style.removeProperty('display');
            let pdfBtn = document.getElementById('modalBtnPdf');
            if (pdfBtn && data.pdf_url) pdfBtn.href = data.pdf_url;
        })
        .catch(err => {
            console.error(err);
            if (body) {
                body.innerHTML = `
                    <div class="text-center text-danger py-4">
                        <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
                        Gagal memuat detail pengeluaran.
                        <div class="mt-3">
                            <a href="/pengeluaran-bahan-baku/${id}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Detail Dokumen
                            </a>
                        </div>
                    </div>
                `;
            }
        });
}

function renderDetailPengeluaran(data) {
    let body = document.getElementById('detailPengeluaranBody');
    let modalTitle = document.querySelector('#detailPengeluaranModal .modal-title');
    let modalIcon = document.querySelector('#detailPengeluaranModal .modal-header i');

    if (modalTitle) {
        modalTitle.innerText = data.is_wasted ? 'Detail Pengeluaran Bahan Wasted / Rusak' : 'Detail Permintaan / Transfer Bahan Baku';
    }
    if (modalIcon) {
        modalIcon.className = data.is_wasted ? 'bi bi-trash3 fs-5 me-1 text-warning' : 'bi bi-box-seam fs-5 me-1';
    }

    let statusBadge = data.is_approved
        ? '<span class="badge bg-success px-3 py-2 fs-6"><i class="bi bi-check-circle me-1"></i>Approved</span>'
        : '<span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="bi bi-clock me-1"></i>Draft / Pengajuan</span>';

    let divisiBadge = data.divisi_nama
        ? `<span class="badge bg-light text-primary border border-primary-subtle ms-1"><i class="bi bi-diagram-3 me-1"></i>${data.divisi_nama}</span>`
        : '';

    let alertShortage = '';
    if (data.total_item_kurang > 0) {
        let lokasiWarning = data.is_wasted ? data.lokasi_nama : data.gudang_utama_nama;
        let contextWarning = data.is_wasted ? 'kuantitas wasted yang dilaporkan' : 'kuantitas yang diminta';
        alertShortage = `
            <div class="alert alert-warning d-flex align-items-center py-2 px-3 mb-3 rounded-3 border-warning-subtle" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
                <div class="small">
                    <strong>Peringatan Ketersediaan:</strong> Terdapat <strong>${data.total_item_kurang}</strong> item bahan yang stoknya di <strong>${lokasiWarning}</strong> belum mencukupi ${contextWarning}.
                </div>
            </div>
        `;
    }

    const formatCurrency = (val) => {
        const num = Number(val || 0);
        if (num === 0) return 'Rp 0';
        const maxDec = (Math.abs(num) < 1 || (num % 1 !== 0 && Math.abs(num) < 100)) ? 4 : 2;
        return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: maxDec });
    };

    let canModifyItems = data.is_superadmin || !data.is_approved;

    let rows = '';
    data.details.forEach(function (d, index) {
        let hasKonv = d.has_konversi;
        let konv = Number(d.konversi_pembelian || 1);
        let sBeli = d.satuan_pembelian;

        let isSurplus = d.selisih_type === 'surplus';
        let signedHppVal = d.signed_hpp !== undefined ? d.signed_hpp : (isSurplus ? -d.total_harga : d.total_harga);

        let kurangBadge = data.is_opname
            ? `<span class="text-muted small">-</span>`
            : (d.kekurangan > 0
                ? `<span class="text-danger fw-bold">-${d.kekurangan.toLocaleString('id-ID')} <small class="text-muted fw-normal">${d.satuan}</small></span>` +
                  (hasKonv ? `<div class="text-danger small" style="font-size:10.5px;">-${(d.kekurangan / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : '')
                : `<span class="text-success fw-semibold"><i class="bi bi-check2"></i> 0</span>`);

        let statusPill = `<span class="badge bg-${d.status_color}-subtle text-${d.status_color} border border-${d.status_color}-subtle px-2 py-1">${d.status_stok}</span>`;

        let deleteItemBtn = canModifyItems ? `
            <td class="text-center">
                ${data.details.length > 1 ? `
                    <button type="button" class="btn btn-outline-danger btn-sm p-1" title="Hapus item ${d.nama_barang}" onclick="deleteModalDetailItem(${data.id}, ${d.id}, '${d.nama_barang.replace(/'/g, "\\'")}', ${data.is_approved})">
                        <i class="bi bi-trash"></i>
                    </button>
                ` : `<span class="text-muted small">-</span>`}
            </td>
        ` : '';

        rows += `
            <tr>
                <td class="text-center text-muted small">${index + 1}</td>
                <td>
                    <div class="fw-semibold text-dark">${d.nama_barang}</div>
                    <small class="text-muted font-monospace">${d.kode_barang}</small>
                    ${hasKonv ? `<div class="text-muted" style="font-size:10.5px;">1 ${sBeli} = ${konv.toLocaleString('id-ID')} ${d.satuan}</div>` : ''}
                </td>
                <td class="text-end fw-bold text-dark">
                    ${data.is_opname ? `<span class="${isSurplus ? 'text-success' : 'text-danger'}">${isSurplus ? '+' : '-'}${d.qty.toLocaleString('id-ID')}</span>` : d.qty.toLocaleString('id-ID')} <span class="text-muted fw-normal small">${d.satuan}</span>
                    ${hasKonv ? `<div class="text-primary fw-normal small" style="font-size:11px;">= ${(d.qty / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : ''}
                </td>
                <td class="text-end">
                    <span class="fw-semibold ${d.stok_tersedia > 0 ? 'text-primary' : 'text-danger'}">
                        ${d.stok_tersedia.toLocaleString('id-ID')}
                    </span>
                    <span class="text-muted fw-normal small">${d.satuan}</span>
                    ${hasKonv ? `<div class="text-muted small" style="font-size:10.5px;">= ${(d.stok_tersedia / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : ''}
                </td>
                <td class="text-end">
                    ${kurangBadge}
                </td>
                <td class="text-center">
                    ${statusPill}
                </td>
                <td class="text-end text-muted small">
                    ${formatCurrency(d.harga_satuan)}
                </td>
                <td class="text-end fw-bold ${data.is_opname && isSurplus ? 'text-success' : 'text-dark'}">
                    ${data.is_opname && isSurplus ? '-Rp ' + formatCurrency(d.total_harga).replace('Rp ', '') : formatCurrency(d.total_harga)}
                </td>
                ${deleteItemBtn}
            </tr>
        `;
    });

    let csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

    let actionButtons = `
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
            <div class="d-flex gap-2">
                <a href="${data.pdf_url}" target="_blank" class="btn btn-outline-danger btn-sm px-3 fw-semibold shadow-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Save PDF
                </a>
                <button type="button" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-sm" onclick="downloadModalAsImage()">
                    <i class="bi bi-image me-1"></i> Save Image (PNG)
                </button>
            </div>
            <div class="d-flex gap-2 align-items-center">
                ${!data.is_approved ? `
                    <form action="${data.delete_url}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus dokumen permintaan / pengeluaran ${data.kode_pengeluaran}?')">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold shadow-sm">
                            <i class="bi bi-trash-fill me-1"></i> Hapus
                        </button>
                    </form>
                    ${!data.is_wo ? `<a href="${data.edit_url}" class="btn btn-warning btn-sm px-3 fw-semibold"><i class="bi bi-pencil-square me-1"></i> Edit</a>` : ''}
                    ${data.can_approve ? (
                        data.total_item_kurang > 0
                            ? `<button type="button" class="btn btn-secondary btn-sm px-3 fw-semibold shadow-sm" disabled title="Tidak dapat di-approve karena stok di gudang sumber tidak mencukupi"><i class="bi bi-x-circle me-1"></i> Stok Kurang (Tidak Bisa Di-Approve)</button>`
                            : `<a href="${data.approve_url}" class="btn btn-success btn-sm px-3 fw-semibold" onclick="return confirm('Approve pengeluaran dan potong stok di gudang terkait?')"><i class="bi bi-check-circle me-1"></i> Approve Pengeluaran</a>`
                    ) : ''}
                ` : (data.is_superadmin ? `
                    <form action="${data.delete_url}" method="POST" class="d-inline" onsubmit="return confirm('PERINGATAN SUPERADMIN: Dokumen ini telah disetujui.\nMenghapus dokumen ini akan membatalkan pemotongan/mutasi stok secara otomatis.\n\nYakin ingin menghapus seluruh dokumen?')">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold shadow-sm" title="Hapus Dokumen Approved">
                            <i class="bi bi-trash-fill me-1"></i> Hapus Dokumen (Superadmin)
                        </button>
                    </form>
                    <a href="${data.edit_url}" class="btn btn-warning btn-sm px-3 fw-semibold shadow-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit Pengeluaran (Superadmin)
                    </a>
                ` : '')}
            </div>
        </div>
    `;

    let infoCardsHtml = '';
    if (data.is_wasted) {
        infoCardsHtml = `
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Kode Dokumen</div>
                        <div class="fw-bold fs-6 text-dark mt-1">${data.kode_pengeluaran}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Lokasi Terjadinya Wasted</div>
                        <div class="fw-bold fs-6 text-danger mt-1"><i class="bi bi-geo-alt-fill me-1"></i>${data.lokasi_nama}</div>
                        <small class="text-muted">Gudang Operasional</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Jenis Pengeluaran</div>
                        <div class="mt-1"><span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-trash3 me-1"></i>Wasted / Rusak / Busuk</span></div>
                        <small class="text-muted">Tanggal: ${data.tanggal}</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border d-flex flex-column justify-content-between">
                        <div class="text-muted small">Status Dokumen</div>
                        <div>${statusBadge}</div>
                    </div>
                </div>
            </div>
        `;
    } else if (data.is_opname) {
        infoCardsHtml = `
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Kode Dokumen</div>
                        <div class="fw-bold fs-6 text-dark mt-1">${data.kode_pengeluaran}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">Gudang / Lokasi SO</div>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill shadow-none" onclick="refreshCurrentModalStok(this)" title="Refresh ketersediaan stok lokasi SO" style="font-size:0.75rem;">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="fw-bold fs-6 text-primary mt-1">${data.gudang_nama} ${divisiBadge}</div>
                        <small class="text-muted">Lokasi Opname Fisik</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Tujuan Dokumen</div>
                        <div class="mt-1"><span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-sliders me-1"></i>Penyesuaian Stock Opname (Fisik)</span></div>
                        <small class="text-muted">Tanggal: ${data.tanggal}</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border d-flex flex-column justify-content-between">
                        <div class="text-muted small">Status Dokumen</div>
                        <div>${statusBadge}</div>
                    </div>
                </div>
            </div>
        `;
    } else {
        infoCardsHtml = `
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Kode Dokumen</div>
                        <div class="fw-bold fs-6 text-dark mt-1">${data.kode_pengeluaran}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">Gudang Sumber</div>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill shadow-none" onclick="refreshCurrentModalStok(this)" title="Refresh ketersediaan stok gudang utama" style="font-size:0.75rem;">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="fw-bold fs-6 text-dark mt-1">${data.gudang_utama_nama}</div>
                        <small class="text-muted">Gudang Penyedia</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Tujuan Transfer</div>
                        <div class="fw-bold fs-6 text-dark mt-1">${data.gudang_nama} ${divisiBadge}</div>
                        <small class="text-muted">Tanggal: ${data.tanggal}</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border d-flex flex-column justify-content-between">
                        <div class="text-muted small">Status Dokumen</div>
                        <div>${statusBadge}</div>
                    </div>
                </div>
            </div>
        `;
    }

    let colQtyLabel = data.is_wasted ? 'Qty Wasted' : (data.is_opname ? 'Selisih SO' : 'Qty Diminta');
    let colStokLabel = data.is_wasted ? 'Stok Lokasi' : (data.is_opname ? 'Stok Lokasi' : 'Stok Gd. Utama');

    body.innerHTML = `
        <div id="printableDetailArea" class="p-2">
            ${alertShortage}

            {{-- HEADER INFO CARDS --}}
            ${infoCardsHtml}

            {{-- KETERANGAN JIKA ADA --}}
            ${data.keterangan && data.keterangan !== '-' ? `
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold mb-1">Catatan / Keterangan:</div>
                    <div class="text-dark small">${data.keterangan}</div>
                </div>
            ` : ''}

            {{-- TABEL DETAIL BARANG --}}
            <div class="table-responsive rounded-3 border">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-center small">
                        <tr>
                            <th width="35">#</th>
                            <th class="text-start">Barang</th>
                            <th width="110" class="text-end">${colQtyLabel}</th>
                            <th width="150" class="text-end">
                                <span>${colStokLabel}</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-primary ms-1" onclick="refreshCurrentModalStok(this)" title="Refresh stok gudang utama"><i class="bi bi-arrow-clockwise"></i></button>
                            </th>
                            <th width="110" class="text-end">${data.is_opname ? 'Tipe' : 'Kekurangan'}</th>
                            <th width="130">Ketersediaan</th>
                            <th width="110" class="text-end">Harga Satuan</th>
                            <th width="130" class="text-end">Total HPP</th>
                            ${canModifyItems ? '<th width="50" class="text-center">Aksi</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <th colspan="7" class="text-end fw-bold">Total Nilai HPP ${data.is_opname ? 'Net' : ''} ${!data.is_approved ? '<span class="text-muted fw-normal small">(Estimasi)</span>' : ''}:</th>
                            <th class="text-end fw-bold fs-6" style="color:#7A4517;">
                                ${data.grand_total < 0 ? `<span class="text-success">-Rp ${formatCurrency(Math.abs(data.grand_total)).replace('Rp ', '')}</span>` : formatCurrency(data.grand_total)}
                            </th>
                            ${canModifyItems ? '<th></th>' : ''}
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        ${actionButtons}
    `;
}

function downloadModalAsImage() {
    if (!currentDetailData) return;

    let data = currentDetailData;
    let divisiText = data.divisi_nama ? ` (Divisi: ${data.divisi_nama})` : '';

    const formatCurrency = (val) => {
        const num = Number(val || 0);
        if (num === 0) return 'Rp 0';
        const maxDec = (Math.abs(num) < 1 || (num % 1 !== 0 && Math.abs(num) < 100)) ? 4 : 2;
        return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: maxDec });
    };

    let statusHtml = data.is_approved
        ? '<span style="display:inline-block; padding:3px 12px; font-weight:bold; font-size:11px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:4px;">APPROVED / DISETUJUI</span>'
        : '<span style="display:inline-block; padding:3px 12px; font-weight:bold; font-size:11px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; border-radius:4px;">DRAFT / PENGAJUAN</span>';

    let totalDiminta = 0;
    let totalKurang = 0;
    let rowsHtml = '';

    data.details.forEach(function (d, idx) {
        let hasKonv = d.has_konversi;
        let konv = Number(d.konversi_pembelian || 1);
        let sBeli = d.satuan_pembelian;
        totalDiminta += d.qty;
        totalKurang += d.kekurangan;

        let isSurplus = d.selisih_type === 'surplus';

        let kurangText = data.is_opname
            ? '<span style="color:#64748b;">-</span>'
            : (d.kekurangan > 0
                ? `<span style="font-weight:700; color:#dc2626;">-${d.kekurangan.toLocaleString('id-ID')}</span> <span style="font-size:9px; color:#64748b;">${d.satuan}</span>` +
                  (hasKonv ? `<div style="font-size:8.5px; color:#dc2626;">-${(d.kekurangan / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : '')
                : '<span style="font-weight:600; color:#16a34a;">0</span>');

        let availPill = data.is_opname
            ? (isSurplus
                ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:3px;">Selisih Lebih (+)</span>'
                : '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Selisih Kurang (-)</span>')
            : (d.stok_tersedia > d.qty
                ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:3px;">Tersedia Penuh</span>'
                : (d.stok_tersedia == d.qty && d.stok_tersedia > 0
                    ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fffbeb; color:#b45309; border:1px solid #fde68a; border-radius:3px;">Stok Terakhir (Segera Beli)</span>'
                    : (d.stok_tersedia > 0
                        ? `<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Kurang ${d.kekurangan.toLocaleString('id-ID')} ${d.satuan}</span>`
                        : '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Habis (0)</span>')));

        let bgRow = idx % 2 === 1 ? '#f8fafc' : '#ffffff';

        rowsHtml += `
            <tr style="background:${bgRow};">
                <td style="padding:8px 5px; text-align:center; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px;">${idx + 1}</td>
                <td style="padding:8px 8px; border:1px solid #e2e8f0;">
                    <div style="font-weight:700; color:#0f172a; font-size:11px;">${d.nama_barang}</div>
                    <div style="font-family:monospace; font-size:9.5px; color:#64748b;">${d.kode_barang}</div>
                    ${hasKonv ? `<div style="font-size:8.5px; color:#64748b;">1 ${sBeli} = ${konv.toLocaleString('id-ID')} ${d.satuan}</div>` : ''}
                </td>
                <td style="padding:8px 8px; text-align:right; font-weight:700; color:#0f172a; border:1px solid #e2e8f0; font-size:11px;">
                    ${data.is_opname ? `<span style="color:${isSurplus ? '#16a34a' : '#dc2626'};">${isSurplus ? '+' : '-'}${d.qty.toLocaleString('id-ID')}</span>` : d.qty.toLocaleString('id-ID')} <span style="font-size:9px; color:#64748b; font-weight:normal;">${d.satuan}</span>
                    ${hasKonv ? `<div style="font-size:8.5px; color:#0284c7; font-weight:normal;">= ${(d.qty / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : ''}
                </td>
                <td style="padding:8px 8px; text-align:right; border:1px solid #e2e8f0; font-size:11px;">
                    <span style="font-weight:600; color:${d.stok_tersedia > 0 ? '#0284c7' : '#dc2626'};">${d.stok_tersedia.toLocaleString('id-ID')}</span>
                    <span style="font-size:9px; color:#64748b;">${d.satuan}</span>
                    ${hasKonv ? `<div style="font-size:8.5px; color:#64748b; font-weight:normal;">= ${(d.stok_tersedia / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : ''}
                </td>
                <td style="padding:8px 8px; text-align:right; border:1px solid #e2e8f0; font-size:11px;">
                    ${kurangText}
                </td>
                <td style="padding:8px 6px; text-align:center; border:1px solid #e2e8f0;">
                    ${availPill}
                </td>
                <td style="padding:8px 8px; text-align:right; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px;">
                    ${formatCurrency(d.harga_satuan)}
                </td>
                <td style="padding:8px 8px; text-align:right; font-weight:700; color:${data.is_opname && isSurplus ? '#16a34a' : '#0f172a'}; border:1px solid #e2e8f0; font-size:11px;">
                    ${data.is_opname && isSurplus ? '-Rp ' + formatCurrency(d.total_harga).replace('Rp ', '') : formatCurrency(d.total_harga)}
                </td>
            </tr>
        `;
    });

    let now = new Date();
    let timestamp = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    let destText = ((data.gudang_nama || '') + ' ' + (data.divisi_nama || '')).toLowerCase();
    let isProduksiOrCK = destText.includes('central kitchen') || destText.includes('cold kitchen') || destText.includes('produksi');
    let headerBgColor = isProduksiOrCK ? '#1d4ed8' : '#d97706';
    let docTitle = data.is_wasted 
        ? 'BERITA ACARA WASTED' 
        : (isProduksiOrCK ? 'SURAT PERMINTAAN & TRANSFER BAHAN (CK / PRODUKSI)' : 'SURAT PERMINTAAN & TRANSFER BAHAN BAKU');

    let docSubtitle = data.is_wasted 
        ? 'Berita Acara Pengeluaran Bahan Wasted / Rusak / Busuk'
        : (isProduksiOrCK ? 'Distribusi Permintaan Bahan ke Produksi / Kitchen' : 'Sistem Pengelolaan Stok & Distribusi Bahan Baku Antar Gudang');
    let colQtyTitle = data.is_wasted ? 'QTY WASTED' : 'QTY DIMINTA';
    let colStokTitle = data.is_wasted ? `STOK LOKASI` : 'STOK GDG UTAMA';

    let gridInfoHtml = '';
    if (data.is_wasted) {
        gridInfoHtml = `
            <tr>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; width:18%; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Judul Dokumen</td>
                <td style="padding:6px 10px; font-weight:bold; color:#0f172a; width:32%; border-bottom:1px solid #e2e8f0;">BERITA ACARA WASTED</td>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; width:18%; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Tanggal Laporan</td>
                <td style="padding:6px 10px; font-weight:bold; color:#0f172a; width:32%; border-bottom:1px solid #e2e8f0;">${data.tanggal}</td>
            </tr>
            <tr>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Outlet Pemesan</td>
                <td style="padding:6px 10px; font-weight:bold; color:${headerBgColor}; border-bottom:1px solid #e2e8f0;">${data.lokasi_nama}</td>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Gudang Sumber</td>
                <td style="padding:6px 10px; color:#0f172a; border-bottom:1px solid #e2e8f0;"><strong>${data.gudang_nama || '-'}</strong> (Lokasi Wasted)</td>
            </tr>
            <tr>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px;">Status Dokumen</td>
                <td style="padding:6px 10px; color:#0f172a;">${statusHtml}</td>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px;">Dicatat Oleh</td>
                <td style="padding:6px 10px; font-weight:bold; color:#0f172a;">${data.user_nama || '-'}</td>
            </tr>
        `;
    } else {
        gridInfoHtml = `
            <tr>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; width:18%; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Judul Dokumen</td>
                <td style="padding:6px 10px; font-weight:bold; color:#0f172a; width:32%; border-bottom:1px solid #e2e8f0;">${docTitle}</td>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; width:18%; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Tanggal Pengajuan</td>
                <td style="padding:6px 10px; font-weight:bold; color:#0f172a; width:32%; border-bottom:1px solid #e2e8f0;">${data.tanggal}</td>
            </tr>
            <tr>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Outlet Pemesan</td>
                <td style="padding:6px 10px; font-weight:bold; color:${headerBgColor}; border-bottom:1px solid #e2e8f0;">${data.gudang_nama}${divisiText}</td>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Gudang Sumber</td>
                <td style="padding:6px 10px; color:#0f172a; border-bottom:1px solid #e2e8f0;"><strong>${data.gudang_utama_nama}</strong> (Penyedia)</td>
            </tr>
            <tr>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px;">Status Dokumen</td>
                <td style="padding:6px 10px; color:#0f172a;">${statusHtml}</td>
                <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px;">Dicatat Oleh</td>
                <td style="padding:6px 10px; font-weight:bold; color:#0f172a;">${data.user_nama || '-'}</td>
            </tr>
        `;
    }

    let container = document.createElement('div');
    container.style.position = 'fixed';
    container.style.left = '-9999px';
    container.style.top = '0';
    container.style.width = '1000px';
    container.style.backgroundColor = '#ffffff';
    container.style.fontFamily = "'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    container.style.color = '#1e293b';
    container.style.padding = '35px 40px';
    container.style.boxSizing = 'border-box';
    container.style.zIndex = '999999';

    container.innerHTML = `
        <!-- HEADER BLOCK BERWARNA -->
        <div style="background-color:${headerBgColor}; color:#ffffff; padding:14px 18px; border-radius:6px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:16px; font-weight:bold; letter-spacing:0.5px; text-transform:uppercase;">CV GAHARU AGUNG SEJAHTERA</div>
                <div style="font-size:10.5px; opacity:0.95; margin-top:2px;">${docSubtitle}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:bold; text-transform:uppercase;">${docTitle}</div>
                <div style="font-family:monospace; font-weight:bold; font-size:13px; margin-top:2px;">#${data.kode_pengeluaran}</div>
            </div>
        </div>

        <!-- INFO DETAIL GRID -->
        <table style="width:100%; border-collapse:collapse; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; font-size:11px;">
            ${gridInfoHtml}
        </table>

        ${data.keterangan && data.keterangan !== '-' ? `
            <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; padding:8px 12px; margin-bottom:16px; font-size:11px; color:#92400e;">
                <strong>Catatan / Keterangan:</strong> ${data.keterangan}
            </div>
        ` : ''}

        <!-- TABEL BARANG -->
        <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:10.5px;">
            <thead>
                <tr style="background:#1e293b; color:#ffffff;">
                    <th style="padding:8px 5px; text-align:center; width:30px; border:1px solid #7A4517; font-size:10px;">NO</th>
                    <th style="padding:8px 8px; text-align:left; border:1px solid #7A4517; font-size:10px;">NAMA BAHAN BAKU</th>
                    <th style="padding:8px 8px; text-align:right; width:105px; border:1px solid #7A4517; font-size:10px;">${colQtyTitle}</th>
                    <th style="padding:8px 8px; text-align:right; width:120px; border:1px solid #7A4517; font-size:10px;">${colStokTitle}</th>
                    <th style="padding:8px 8px; text-align:right; width:100px; border:1px solid #7A4517; font-size:10px;">KEKURANGAN</th>
                    <th style="padding:8px 6px; text-align:center; width:100px; border:1px solid #7A4517; font-size:10px;">KETERSEDIAAN</th>
                    <th style="padding:8px 8px; text-align:right; width:100px; border:1px solid #7A4517; font-size:10px;">HARGA SATUAN</th>
                    <th style="padding:8px 8px; text-align:right; width:115px; border:1px solid #7A4517; font-size:10px;">TOTAL HPP</th>
                </tr>
            </thead>
            <tbody>
                ${rowsHtml}
            </tbody>
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td colspan="2" style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; font-size:11px;">TOTAL:</td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; font-size:11px; color:#0f172a;">${totalDiminta.toLocaleString('id-ID')}</td>
                    <td style="padding:9px 8px; border:1px solid #cbd5e1;"></td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; font-size:11px; color:${totalKurang > 0 ? '#dc2626' : '#16a34a'};">${totalKurang > 0 ? '-' + totalKurang.toLocaleString('id-ID') : '0,00'}</td>
                    <td colspan="2" style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; font-size:11px;">TOTAL NILAI HPP:</td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; font-size:11.5px; color:#7A4517; font-weight:800;">Rp ${data.grand_total.toLocaleString('id-ID')}</td>
                </tr>
            </tfoot>
        </table>

        <!-- TANDA TANGAN 3 PIHAK -->
        <table style="width:100%; border-collapse:collapse; margin-top:25px;">
            <tr>
                <td style="width:33.33%; text-align:center; vertical-align:top; font-size:11px;">
                    <div style="color:#64748b; margin-bottom:50px;">${data.is_wasted ? 'Yang Melaporkan (Kitchen/Outlet)' : 'Pemohon / Peminta'}</div>
                    <div style="font-weight:700; color:#0f172a; text-decoration:underline;">( ............................................ )</div>
                    <div style="font-size:10px; color:#64748b; margin-top:3px;">Divisi / Outlet</div>
                </td>
                <td style="width:33.33%; text-align:center; vertical-align:top; font-size:11px;">
                    <div style="color:#64748b; margin-bottom:50px;">Kepala Gudang</div>
                    <div style="font-weight:700; color:#0f172a; text-decoration:underline;">( ............................................ )</div>
                    <div style="font-size:10px; color:#64748b; margin-top:3px;">${data.is_wasted ? 'Petugas / Supervisor' : (data.gudang_utama_nama || 'Gudang Utama')}</div>
                </td>
                <td style="width:33.33%; text-align:center; vertical-align:top; font-size:11px;">
                    <div style="color:#64748b; margin-bottom:50px;">Management</div>
                    <div style="font-weight:700; color:#0f172a; text-decoration:underline;">( ............................................ )</div>
                    <div style="font-size:10px; color:#64748b; margin-top:3px;">Operasional &amp; Keuangan</div>
                </td>
            </tr>
        </table>

        <!-- FOOTER TIMESTAMP -->
        <div style="margin-top:25px; padding-top:10px; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; font-size:9.5px; color:#94a3b8;">
            <div>Dokumen Resmi Sistem ERP - CV Gaharu Agung Sejahtera</div>
            <div>Dicetak / Diunduh pada: ${timestamp}</div>
        </div>
    `;

    document.body.appendChild(container);

    html2canvas(container, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        document.body.removeChild(container);
        let link = document.createElement('a');
        let filenamePrefix = data.is_wasted ? 'Bukti-Wasted-' : 'Bukti-Transfer-Bahan-';
        link.download = filenamePrefix + data.kode_pengeluaran + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }).catch(err => {
        if (container.parentNode) {
            document.body.removeChild(container);
        }
        console.error('Error generating image:', err);
        alert('Gagal menghasilkan gambar: ' + err.message);
    });
}

function printModalContent() {
    let printContents = document.getElementById('printableDetailArea').innerHTML;
    let printWindow = window.open('', '', 'height=700,width=900');
    printWindow.document.write('<html><head><title>Print Transfer Bahan</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('</head><body class="p-4">');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

function deleteModalDetailItem(pengeluaranId, detailId, namaBarang, isApproved) {
    let warningMsg = isApproved
        ? `PERINGATAN SUPER ADMIN:\nDokumen ini telah disetujui (Approved).\nMenghapus item "${namaBarang}" akan membatalkan pemotongan/mutasi stok dan mengembalikan stok ke posisi semula.\n\nYakin ingin menghapus item ini?`
        : `Yakin ingin menghapus item "${namaBarang}" dari dokumen ini?`;

    if (!confirm(warningMsg)) return;

    let csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

    fetch(`/pengeluaran-bahan-baku/${pengeluaranId}/detail/${detailId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            // Refresh detail modal
            showDetailPengeluaran(pengeluaranId);
        } else {
            alert(res.message || 'Gagal menghapus item.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan saat menghapus item.');
    });
}

// =========================================================
// DATE RANGE PICKER POPOVER LOGIC
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
    const btnTrigger   = document.getElementById('btn-date-range-trigger');
    const popover      = document.getElementById('date-range-popover');
    const inputDari    = document.getElementById('filter_dari');
    const inputSampai  = document.getElementById('filter_sampai');
    const textDisplay  = document.getElementById('text-date-display');
    const displayStart = document.getElementById('display-range-start');
    const displayEnd   = document.getElementById('display-range-end');

    const calTitle     = document.getElementById('cal-month-year-title');
    const calDaysGrid  = document.getElementById('cal-days-grid');
    const btnPrevMonth = document.getElementById('cal-prev-month');
    const btnNextMonth = document.getElementById('cal-next-month');
    const btnApply     = document.getElementById('btn-apply-date-range');
    const btnReset     = document.getElementById('btn-reset-date-range');

    const monthNamesIndo = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];

    let activeYear  = new Date().getFullYear();
    let activeMonth = new Date().getMonth();
    let selStart    = inputDari ? inputDari.value : '';
    let selEnd      = inputSampai ? inputSampai.value : '';

    function formatDateToYMD(d) {
        if (!d) return '';
        let y = d.getFullYear();
        let m = String(d.getMonth() + 1).padStart(2, '0');
        let day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function formatDateToDMY(ymdStr) {
        if (!ymdStr) return '';
        let parts = ymdStr.split('-');
        if (parts.length !== 3) return ymdStr;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function updateTriggerDisplay() {
        let dVal = inputDari ? inputDari.value : '';
        let sVal = inputSampai ? inputSampai.value : '';
        if (dVal && sVal) {
            if (dVal === sVal) {
                textDisplay.innerText = formatDateToDMY(dVal);
            } else {
                textDisplay.innerText = `${formatDateToDMY(dVal)} - ${formatDateToDMY(sVal)}`;
            }
        } else if (dVal) {
            textDisplay.innerText = `Dari ${formatDateToDMY(dVal)}`;
        } else if (sVal) {
            textDisplay.innerText = `Sampai ${formatDateToDMY(sVal)}`;
        } else {
            textDisplay.innerText = 'Semua Tanggal';
        }
    }

    function updateSummaryInputs() {
        if (displayStart) displayStart.value = formatDateToDMY(selStart);
        if (displayEnd) displayEnd.value = formatDateToDMY(selEnd || selStart);
    }

    function renderCalendar() {
        if (!calTitle || !calDaysGrid) return;
        calTitle.innerText = `${monthNamesIndo[activeMonth]} ${activeYear}`;
        calDaysGrid.innerHTML = '';

        let firstDayIndex = new Date(activeYear, activeMonth, 1).getDay();
        let totalDaysInMonth = new Date(activeYear, activeMonth + 1, 0).getDate();
        let prevMonthTotalDays = new Date(activeYear, activeMonth, 0).getDate();

        // Prev month days
        for (let x = firstDayIndex; x > 0; x--) {
            let dayNum = prevMonthTotalDays - x + 1;
            let el = document.createElement('div');
            el.className = 'py-1 text-muted opacity-25 small';
            el.innerText = dayNum;
            calDaysGrid.appendChild(el);
        }

        // Current month days
        for (let i = 1; i <= totalDaysInMonth; i++) {
            let monthStr = String(activeMonth + 1).padStart(2, '0');
            let dayStr = String(i).padStart(2, '0');
            let ymd = `${activeYear}-${monthStr}-${dayStr}`;

            let el = document.createElement('div');
            el.className = 'py-1 rounded-2 small cursor-pointer day-cell fw-semibold';
            el.innerText = i;
            el.style.cursor = 'pointer';

            let isStart = (ymd === selStart);
            let isEnd = (ymd === (selEnd || selStart));
            let inRange = false;

            if (selStart && selEnd && ymd > selStart && ymd < selEnd) {
                inRange = true;
            }

            if (isStart || isEnd) {
                el.classList.add('bg-primary', 'text-white', 'shadow-sm');
            } else if (inRange) {
                el.classList.add('bg-primary-subtle', 'text-primary-emphasis');
            } else {
                el.classList.add('text-dark');
                el.addEventListener('mouseenter', () => el.classList.add('bg-light'));
                el.addEventListener('mouseleave', () => el.classList.remove('bg-light'));
            }

            el.addEventListener('click', function () {
                if (!selStart || (selStart && selEnd)) {
                    selStart = ymd;
                    selEnd = '';
                } else if (selStart && !selEnd) {
                    if (ymd < selStart) {
                        selEnd = selStart;
                        selStart = ymd;
                    } else {
                        selEnd = ymd;
                    }
                }
                updateSummaryInputs();
                renderCalendar();
            });

            calDaysGrid.appendChild(el);
        }
    }

    function applyPreset(presetKey) {
        let now = new Date();
        let y = now.getFullYear();
        let m = now.getMonth();
        let d = now.getDate();
        let dayOfWeek = now.getDay(); // 0 Sun - 6 Sat

        let startDate, endDate;

        switch (presetKey) {
            case 'today':
                startDate = new Date(y, m, d);
                endDate = new Date(y, m, d);
                break;
            case 'yesterday':
                startDate = new Date(y, m, d - 1);
                endDate = new Date(y, m, d - 1);
                break;
            case 'this_week':
                let diffMon = d - (dayOfWeek === 0 ? 6 : dayOfWeek - 1);
                startDate = new Date(y, m, diffMon);
                endDate = new Date(y, m, diffMon + 6);
                break;
            case 'last_week':
                let diffLastMon = d - (dayOfWeek === 0 ? 6 : dayOfWeek - 1) - 7;
                startDate = new Date(y, m, diffLastMon);
                endDate = new Date(y, m, diffLastMon + 6);
                break;
            case 'this_month':
                startDate = new Date(y, m, 1);
                endDate = new Date(y, m + 1, 0);
                break;
            case 'last_month':
                startDate = new Date(y, m - 1, 1);
                endDate = new Date(y, m, 0);
                break;
            case 'this_year':
                startDate = new Date(y, 0, 1);
                endDate = new Date(y, 11, 31);
                break;
            case 'last_year':
                startDate = new Date(y - 1, 0, 1);
                endDate = new Date(y - 1, 11, 31);
                break;
        }

        selStart = formatDateToYMD(startDate);
        selEnd = formatDateToYMD(endDate);

        activeYear = startDate.getFullYear();
        activeMonth = startDate.getMonth();

        document.querySelectorAll('.btn-preset-range').forEach(b => {
            b.classList.remove('btn-primary', 'text-white');
            b.classList.add('btn-outline-secondary');
        });

        let activeBtn = document.querySelector(`.btn-preset-range[data-preset="${presetKey}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-secondary');
            activeBtn.classList.add('btn-primary', 'text-white');
        }

        updateSummaryInputs();
        renderCalendar();
    }

    // Event listeners
    if (btnTrigger) {
        btnTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            let isShowing = popover.style.display === 'block';
            popover.style.display = isShowing ? 'none' : 'block';
            if (!isShowing) {
                if (selStart) {
                    let parts = selStart.split('-');
                    if (parts.length === 3) {
                        activeYear = parseInt(parts[0]);
                        activeMonth = parseInt(parts[1]) - 1;
                    }
                }
                updateSummaryInputs();
                renderCalendar();
            }
        });
    }

    if (btnPrevMonth) {
        btnPrevMonth.addEventListener('click', function (e) {
            e.stopPropagation();
            activeMonth--;
            if (activeMonth < 0) {
                activeMonth = 11;
                activeYear--;
            }
            renderCalendar();
        });
    }

    if (btnNextMonth) {
        btnNextMonth.addEventListener('click', function (e) {
            e.stopPropagation();
            activeMonth++;
            if (activeMonth > 11) {
                activeMonth = 0;
                activeYear++;
            }
            renderCalendar();
        });
    }

    document.querySelectorAll('.btn-preset-range').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            let key = this.dataset.preset;
            applyPreset(key);
        });
    });

    if (btnApply) {
        btnApply.addEventListener('click', function () {
            if (inputDari) inputDari.value = selStart;
            if (inputSampai) inputSampai.value = selEnd || selStart;
            updateTriggerDisplay();
            popover.style.display = 'none';
            document.getElementById('form-filter-pbk').submit();
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            selStart = '';
            selEnd = '';
            if (inputDari) inputDari.value = '';
            if (inputSampai) inputSampai.value = '';
            updateSummaryInputs();
            updateTriggerDisplay();
            popover.style.display = 'none';
            document.getElementById('form-filter-pbk').submit();
        });
    }

    if (popover) {
        popover.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    document.addEventListener('click', function (e) {
        if (popover && popover.style.display === 'block') {
            popover.style.display = 'none';
        }
    });

    // Dynamic Divisi Filter based on Selected Gudang
    const gudangSelect = document.getElementById('filter_gudang_select');
    const divisiSelect = document.getElementById('filter_divisi_select');

    if (gudangSelect && divisiSelect) {
        function filterDivisiOptions() {
            const selectedGudangId = gudangSelect.value;
            const currentDivisiVal = divisiSelect.value;
            let currentValVisible = false;

            Array.from(divisiSelect.options).forEach(opt => {
                if (!opt.value) {
                    opt.hidden = false; // '-- Semua Divisi --'
                    return;
                }
                const optGudangId = opt.getAttribute('data-gudang-id');
                if (!selectedGudangId || optGudangId === selectedGudangId) {
                    opt.hidden = false;
                    if (opt.value === currentDivisiVal) currentValVisible = true;
                } else {
                    opt.hidden = true;
                }
            });

            if (currentDivisiVal && !currentValVisible) {
                divisiSelect.value = '';
            }
        }

        gudangSelect.addEventListener('change', filterDivisiOptions);
        filterDivisiOptions();
    }

    // Init display on page load
    updateTriggerDisplay();
});
</script>

</x-app-layout>