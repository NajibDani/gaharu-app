<x-app-layout>

<div class="container-fluid">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-0">
                Pengeluaran Bahan Baku
            </h2>

            <small class="text-muted">
                Manajemen pengeluaran stok bahan baku produksi
            </small>

        </div>

        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('pengeluaran-bahan-baku.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no pengeluaran..." value="{{ request('search') }}" style="width: 200px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
                @endif
            </form>
            <a href="{{ route('dashboard') }}"
               class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Dashboard
            </a>
            <a href="{{ route('pengeluaran-bahan-baku.create', ['jenis' => 'wasted']) }}"
               class="btn btn-warning text-dark fw-bold" title="Pengeluaran Stok Wasted / Busuk / Rusak">
                <i class="bi bi-trash3-fill me-1"></i>
                Tambah Wasted / Busuk
            </a>
            <a href="{{ route('pengeluaran-bahan-baku.create') }}"
               class="btn btn-primary">
                <i class="bi bi-plus-circle"></i>
                Tambah Transfer
            </a>
        </div>

    </div>

    <!-- STATISTIK -->
    <div class="row mb-4">

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Total Pengeluaran</h6>

                    <h2 class="fw-bold">
                        {{ $data->total() }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Draft</h6>

                    <h2 class="fw-bold text-warning">
                        {{ $data->where('status','draft')->count() }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Approved</h6>

                    <h2 class="fw-bold text-success">
                        {{
                            $data->whereIn(
                                'status',
                                ['approved','disetujui']
                            )->count()
                        }}
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

                    <thead class="table-dark">

                        <tr>

                            <th>No</th>
                            <th>Kode</th>
                            <th>Gudang & Divisi Tujuan</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th width="180">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    @forelse($data as $item)

                        <tr>

                            <td>
                                {{ $loop->iteration }}
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
                                            onclick="showDetailPengeluaran({{ $item->id }})"
                                            title="Lihat Detail">
                                        <i class="bi bi-eye me-1"></i> Detail
                                    </button>

                                    @if(strtolower($item->status) == 'draft')
                                        <a href="{{ route('pengeluaran-bahan-baku.edit', $item->id) }}"
                                           class="btn btn-warning btn-sm" title="Edit Permintaan / Pengeluaran">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('pengeluaran-bahan-baku.approve', $item->id) }}"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Approve pengeluaran ini?')">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
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

{{-- HTML2CANVAS LIBRARY UNTUK SAVE IMAGE --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
let currentDetailData = null;

function showDetailPengeluaran(id) {
    let modalEl = document.getElementById('detailPengeluaranModal');
    let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let body = document.getElementById('detailPengeluaranBody');
    let topActions = document.getElementById('modalTopActions');

    topActions.style.setProperty('display', 'none', 'important');
    body.innerHTML = `
        <div class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 mb-0">Memuat detail pengeluaran & ketersediaan stok...</p>
        </div>
    `;

    modal.show();

    fetch(`/pengeluaran-bahan-baku/${id}/detail-json`)
        .then(response => response.json())
        .then(data => {
            currentDetailData = data;
            renderDetailPengeluaran(data);
            topActions.style.removeProperty('display');
            document.getElementById('modalBtnPdf').href = data.pdf_url;
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
                    Gagal memuat data pengeluaran bahan baku.
                </div>
            `;
        });
}

function renderDetailPengeluaran(data) {
    let body = document.getElementById('detailPengeluaranBody');

    let statusBadge = data.is_approved
        ? '<span class="badge bg-success px-3 py-2 fs-6"><i class="bi bi-check-circle me-1"></i>Approved</span>'
        : '<span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="bi bi-clock me-1"></i>Draft / Pengajuan</span>';

    let divisiBadge = data.divisi_nama
        ? `<span class="badge bg-light text-primary border border-primary-subtle ms-1"><i class="bi bi-diagram-3 me-1"></i>${data.divisi_nama}</span>`
        : '';

    let alertShortage = '';
    if (data.total_item_kurang > 0) {
        alertShortage = `
            <div class="alert alert-warning d-flex align-items-center py-2 px-3 mb-3 rounded-3 border-warning-subtle" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
                <div class="small">
                    <strong>Peringatan Ketersediaan:</strong> Terdapat <strong>${data.total_item_kurang}</strong> item bahan yang stoknya di <strong>${data.gudang_utama_nama}</strong> belum mencukupi kuantitas yang diminta.
                </div>
            </div>
        `;
    }

    let rows = '';
    data.details.forEach(function (d, index) {
        let kurangBadge = d.kekurangan > 0
            ? `<span class="text-danger fw-bold">-${d.kekurangan.toLocaleString('id-ID')} <small class="text-muted fw-normal">${d.satuan}</small></span>`
            : `<span class="text-success fw-semibold"><i class="bi bi-check2"></i> 0</span>`;

        let statusPill = `<span class="badge bg-${d.status_color}-subtle text-${d.status_color} border border-${d.status_color}-subtle px-2 py-1">${d.status_stok}</span>`;

        rows += `
            <tr>
                <td class="text-center text-muted small">${index + 1}</td>
                <td>
                    <div class="fw-semibold text-dark">${d.nama_barang}</div>
                    <small class="text-muted font-monospace">${d.kode_barang}</small>
                </td>
                <td class="text-end fw-bold text-dark">
                    ${d.qty.toLocaleString('id-ID')} <span class="text-muted fw-normal small">${d.satuan}</span>
                </td>
                <td class="text-end">
                    <span class="fw-semibold ${d.stok_gudang_utama > 0 ? 'text-primary' : 'text-danger'}">
                        ${d.stok_gudang_utama.toLocaleString('id-ID')}
                    </span>
                    <span class="text-muted fw-normal small">${d.satuan}</span>
                </td>
                <td class="text-end">
                    ${kurangBadge}
                </td>
                <td class="text-center">
                    ${statusPill}
                </td>
                <td class="text-end text-muted small">
                    Rp ${d.harga_satuan.toLocaleString('id-ID')}
                </td>
                <td class="text-end fw-bold text-dark">
                    Rp ${d.total_harga.toLocaleString('id-ID')}
                </td>
            </tr>
        `;
    });

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
            <div class="d-flex gap-2">
                ${!data.is_approved ? `
                    ${!data.is_wo ? `<a href="${data.edit_url}" class="btn btn-warning btn-sm px-3 fw-semibold"><i class="bi bi-pencil-square me-1"></i> Edit</a>` : ''}
                    <a href="${data.approve_url}" class="btn btn-success btn-sm px-3 fw-semibold" onclick="return confirm('Approve pengeluaran dan kurangi stok gudang sumber?')">
                        <i class="bi bi-check-circle me-1"></i> Approve Pengeluaran
                    </a>
                ` : ''}
            </div>
        </div>
    `;

    body.innerHTML = `
        <div id="printableDetailArea" class="p-2">
            ${alertShortage}

            {{-- HEADER INFO CARDS --}}
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Kode Dokumen</div>
                        <div class="fw-bold fs-6 text-dark mt-1">${data.kode_pengeluaran}</div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 h-100 border">
                        <div class="text-muted small">Gudang Sumber</div>
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
                            <th width="110" class="text-end">Jumlah Diminta</th>
                            <th width="120" class="text-end">Stok Gudang Utama</th>
                            <th width="110" class="text-end">Kekurangan</th>
                            <th width="130">Ketersediaan</th>
                            <th width="110" class="text-end">Harga Satuan</th>
                            <th width="130" class="text-end">Total HPP</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <th colspan="7" class="text-end fw-bold">Total Nilai HPP ${!data.is_approved ? '<span class="text-muted fw-normal small">(Estimasi)</span>' : ''}:</th>
                            <th class="text-end fw-bold fs-6" style="color:#7A4517;">Rp ${data.grand_total.toLocaleString('id-ID')}</th>
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

    let statusHtml = data.is_approved
        ? '<span style="display:inline-block; padding:3px 12px; font-weight:bold; font-size:11px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:4px;">APPROVED / DISETUJUI</span>'
        : '<span style="display:inline-block; padding:3px 12px; font-weight:bold; font-size:11px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; border-radius:4px;">DRAFT / PENGAJUAN</span>';

    let totalDiminta = 0;
    let totalKurang = 0;
    let rowsHtml = '';

    data.details.forEach((d, idx) => {
        totalDiminta += d.qty;
        totalKurang += d.kekurangan;

        let kurangText = d.kekurangan > 0
            ? `<span style="color:#dc2626; font-weight:bold;">-${d.kekurangan.toLocaleString('id-ID')} <span style="font-size:9px; color:#64748b; font-weight:normal;">${d.satuan}</span></span>`
            : `<span style="color:#16a34a; font-weight:600;">0,00</span>`;

        let availPill = d.stok_gudang_utama >= d.qty
            ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:3px;">Tersedia</span>'
            : (d.stok_gudang_utama > 0
                ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fffbeb; color:#b45309; border:1px solid #fde68a; border-radius:3px;">Kurang</span>'
                : '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Habis (0)</span>');

        let bgRow = idx % 2 === 1 ? '#f8fafc' : '#ffffff';

        rowsHtml += `
            <tr style="background:${bgRow};">
                <td style="padding:8px 5px; text-align:center; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px;">${idx + 1}</td>
                <td style="padding:8px 8px; border:1px solid #e2e8f0;">
                    <div style="font-weight:700; color:#0f172a; font-size:11px;">${d.nama_barang}</div>
                    <div style="font-family:monospace; font-size:9.5px; color:#64748b;">${d.kode_barang}</div>
                </td>
                <td style="padding:8px 8px; text-align:right; font-weight:700; color:#0f172a; border:1px solid #e2e8f0; font-size:11px;">
                    ${d.qty.toLocaleString('id-ID')} <span style="font-size:9px; color:#64748b; font-weight:normal;">${d.satuan}</span>
                </td>
                <td style="padding:8px 8px; text-align:right; border:1px solid #e2e8f0; font-size:11px;">
                    <span style="font-weight:600; color:${d.stok_gudang_utama > 0 ? '#0284c7' : '#dc2626'};">${d.stok_gudang_utama.toLocaleString('id-ID')}</span>
                    <span style="font-size:9px; color:#64748b;">${d.satuan}</span>
                </td>
                <td style="padding:8px 8px; text-align:right; border:1px solid #e2e8f0; font-size:11px;">
                    ${kurangText}
                </td>
                <td style="padding:8px 6px; text-align:center; border:1px solid #e2e8f0;">
                    ${availPill}
                </td>
                <td style="padding:8px 8px; text-align:right; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px;">
                    Rp ${d.harga_satuan.toLocaleString('id-ID')}
                </td>
                <td style="padding:8px 8px; text-align:right; font-weight:700; color:#0f172a; border:1px solid #e2e8f0; font-size:11px;">
                    Rp ${d.total_harga.toLocaleString('id-ID')}
                </td>
            </tr>
        `;
    });

    let now = new Date();
    let timestamp = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

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
        <!-- HEADER KOP DOKUMEN -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #7A4517; padding-bottom:14px; margin-bottom:18px;">
            <div>
                <div style="font-size:20px; font-weight:800; color:#7A4517; letter-spacing:0.5px;">CV GAHARU AGUNG SEJAHTERA</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Surat Permintaan &amp; Transfer Bahan Baku - Distribusi Antar Gudang</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase;">Bukti Permintaan Bahan</div>
                <div style="font-size:11px; color:#64748b; margin-top:3px;">No. Dokumen: <strong style="font-family:monospace; color:#7A4517; font-size:12px;">${data.kode_pengeluaran}</strong></div>
            </div>
        </div>

        <!-- INFO DETAIL GRID -->
        <table style="width:100%; border-collapse:collapse; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:18px; font-size:11.5px;">
            <tr>
                <td style="padding:10px 14px; width:50%; vertical-align:top; border-right:1px solid #e2e8f0;">
                    <div style="margin-bottom:6px;"><span style="color:#64748b; display:inline-block; width:135px;">Gudang Sumber:</span> <strong style="color:#0f172a;">${data.gudang_utama_nama}</strong> <span style="font-size:10px; color:#64748b;">(Penyedia)</span></div>
                    <div><span style="color:#64748b; display:inline-block; width:135px;">Gudang Tujuan:</span> <strong style="color:#0f172a;">${data.gudang_nama}${divisiText}</strong></div>
                </td>
                <td style="padding:10px 14px; width:50%; vertical-align:top;">
                    <div style="margin-bottom:6px;"><span style="color:#64748b; display:inline-block; width:125px;">Tanggal Pengajuan:</span> <strong style="color:#0f172a;">${data.tanggal}</strong></div>
                    <div><span style="color:#64748b; display:inline-block; width:125px;">Status Dokumen:</span> ${statusHtml}</div>
                </td>
            </tr>
        </table>

        ${data.keterangan && data.keterangan !== '-' ? `
            <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; padding:8px 12px; margin-bottom:16px; font-size:11px; color:#92400e;">
                <strong>Catatan / Keterangan:</strong> ${data.keterangan}
            </div>
        ` : ''}

        <!-- TABEL BARANG -->
        <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:10.5px;">
            <thead>
                <tr style="background:#7A4517; color:#ffffff;">
                    <th style="padding:8px 5px; text-align:center; width:30px; border:1px solid #7A4517; font-size:10px;">NO</th>
                    <th style="padding:8px 8px; text-align:left; border:1px solid #7A4517; font-size:10px;">NAMA BAHAN BAKU</th>
                    <th style="padding:8px 8px; text-align:right; width:105px; border:1px solid #7A4517; font-size:10px;">QTY DIMINTA</th>
                    <th style="padding:8px 8px; text-align:right; width:120px; border:1px solid #7A4517; font-size:10px;">STOK GDG UTAMA</th>
                    <th style="padding:8px 8px; text-align:right; width:100px; border:1px solid #7A4517; font-size:10px;">KEKURANGAN</th>
                    <th style="padding:8px 6px; text-align:center; width:100px; border:1px solid #7A4517; font-size:10px;">KETERSEDIAAN</th>
                    <th style="padding:8px 8px; text-align:right; width:95px; border:1px solid #7A4517; font-size:10px;">HARGA (RP)</th>
                    <th style="padding:8px 8px; text-align:right; width:115px; border:1px solid #7A4517; font-size:10px;">TOTAL HPP</th>
                </tr>
            </thead>
            <tbody>
                ${rowsHtml}
            </tbody>
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold; font-size:11px;">
                    <td colspan="2" style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1;">TOTAL KESELURUHAN:</td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; color:#0f172a;">${totalDiminta.toLocaleString('id-ID')}</td>
                    <td style="border:1px solid #cbd5e1;"></td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; color:${totalKurang > 0 ? '#dc2626' : '#16a34a'};">
                        ${totalKurang > 0 ? '-' + totalKurang.toLocaleString('id-ID') : '0,00'}
                    </td>
                    <td colspan="2" style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1;">Total Nilai HPP:</td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; color:#7A4517; font-size:11.5px;">
                        Rp ${data.grand_total.toLocaleString('id-ID')}
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- SIGNATURE BLOCK -->
        <table style="width:100%; border-collapse:collapse; margin-top:25px; font-size:11px; text-align:center;">
            <tr>
                <td style="width:33.33%; padding:0 15px; vertical-align:top;">
                    <div style="color:#64748b; margin-bottom:50px;">Pemohon / Peminta</div>
                    <div style="font-weight:700; border-top:1px solid #94a3b8; padding-top:4px; color:#0f172a;">${data.gudang_nama}</div>
                </td>
                <td style="width:33.33%; padding:0 15px; vertical-align:top;">
                    <div style="color:#64748b; margin-bottom:50px;">Kepala Gudang</div>
                    <div style="font-weight:700; border-top:1px solid #94a3b8; padding-top:4px; color:#0f172a;">${data.gudang_utama_nama}</div>
                </td>
                <td style="width:33.33%; padding:0 15px; vertical-align:top;">
                    <div style="color:#64748b; margin-bottom:50px;">Management</div>
                    <div style="font-weight:700; border-top:1px solid #94a3b8; padding-top:4px; color:#0f172a;">CV Gaharu Agung Sejahtera</div>
                </td>
            </tr>
        </table>

        <!-- WATERMARK / TIMESTAMP -->
        <div style="margin-top:25px; padding-top:8px; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; font-size:9.5px; color:#94a3b8;">
            <div>Sistem ERP Gaharu - Dokumen Bukti Permintaan &amp; Transfer Bahan Baku</div>
            <div>Dicetak otomatis pada: ${timestamp}</div>
        </div>
    `;

    document.body.appendChild(container);

    html2canvas(container, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
    }).then(canvas => {
        document.body.removeChild(container);
        let link = document.createElement('a');
        link.download = `Transfer-Bahan-${data.kode_pengeluaran}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }).catch(err => {
        if (container.parentNode) document.body.removeChild(container);
        console.error('Error generating image:', err);
        alert('Gagal mendownload gambar: ' + err.message);
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
</script>

</x-app-layout>