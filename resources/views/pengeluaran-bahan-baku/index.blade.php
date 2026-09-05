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

                                    @php
                                        $user = auth()->user();
                                        $canApprove = $user && $user->canApprovePengeluaran();
                                    @endphp

                                    @if(strtolower($item->status) == 'draft')
                                        <a href="{{ route('pengeluaran-bahan-baku.edit', $item->id) }}"
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

    let colQtyLabel = data.is_wasted ? 'Jumlah Wasted' : 'Jumlah Diminta';
    let colStokLabel = data.is_wasted ? `Stok Lokasi (${data.divisi_nama || data.gudang_nama})` : 'Stok Gudang Utama';

    const formatCurrency = (val) => {
        const num = Number(val || 0);
        if (num === 0) return 'Rp 0';
        const maxDec = (Math.abs(num) < 1 || (num % 1 !== 0 && Math.abs(num) < 100)) ? 4 : 2;
        return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: maxDec });
    };

    let rows = '';
    data.details.forEach(function (d, index) {
        let hasKonv = d.has_konversi;
        let konv = Number(d.konversi_pembelian || 1);
        let sBeli = d.satuan_pembelian;

        let kurangBadge = d.kekurangan > 0
            ? `<span class="text-danger fw-bold">-${d.kekurangan.toLocaleString('id-ID')} <small class="text-muted fw-normal">${d.satuan}</small></span>` +
              (hasKonv ? `<div class="text-danger small" style="font-size:10.5px;">-${(d.kekurangan / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : '')
            : `<span class="text-success fw-semibold"><i class="bi bi-check2"></i> 0</span>`;

        let statusPill = `<span class="badge bg-${d.status_color}-subtle text-${d.status_color} border border-${d.status_color}-subtle px-2 py-1">${d.status_stok}</span>`;

        rows += `
            <tr>
                <td class="text-center text-muted small">${index + 1}</td>
                <td>
                    <div class="fw-semibold text-dark">${d.nama_barang}</div>
                    <small class="text-muted font-monospace">${d.kode_barang}</small>
                    ${hasKonv ? `<div class="text-muted" style="font-size:10.5px;">1 ${sBeli} = ${konv.toLocaleString('id-ID')} ${d.satuan}</div>` : ''}
                </td>
                <td class="text-end fw-bold text-dark">
                    ${d.qty.toLocaleString('id-ID')} <span class="text-muted fw-normal small">${d.satuan}</span>
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
                <td class="text-end fw-bold text-dark">
                    ${formatCurrency(d.total_harga)}
                </td>
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
                ` : ''}
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
        `;
    }

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
                            <th width="140" class="text-end">${colStokLabel}</th>
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
                            <th class="text-end fw-bold fs-6" style="color:#7A4517;">${formatCurrency(data.grand_total)}</th>
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

        let kurangText = d.kekurangan > 0
            ? `<span style="font-weight:700; color:#dc2626;">-${d.kekurangan.toLocaleString('id-ID')}</span> <span style="font-size:9px; color:#64748b;">${d.satuan}</span>` +
              (hasKonv ? `<div style="font-size:8.5px; color:#dc2626;">-${(d.kekurangan / konv).toLocaleString('id-ID', {maximumFractionDigits: 2})} ${sBeli}</div>` : '')
            : '<span style="font-weight:600; color:#16a34a;">0</span>';

        let availPill = d.stok_tersedia > d.qty
            ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:3px;">Tersedia Penuh</span>'
            : (d.stok_tersedia == d.qty && d.stok_tersedia > 0
                ? '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fffbeb; color:#b45309; border:1px solid #fde68a; border-radius:3px;">Stok Terakhir (Segera Beli)</span>'
                : (d.stok_tersedia > 0
                    ? `<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Kurang ${d.kekurangan.toLocaleString('id-ID')} ${d.satuan}</span>`
                    : '<span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Habis (0)</span>'));

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
                    ${d.qty.toLocaleString('id-ID')} <span style="font-size:9px; color:#64748b; font-weight:normal;">${d.satuan}</span>
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
                <td style="padding:8px 8px; text-align:right; font-weight:700; color:#0f172a; border:1px solid #e2e8f0; font-size:11px;">
                    ${formatCurrency(d.total_harga)}
                </td>
            </tr>
        `;
    });

    let now = new Date();
    let timestamp = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    let docSubtitle = data.is_wasted 
        ? 'Berita Acara Pengeluaran Bahan Wasted / Rusak / Busuk'
        : 'Surat Permintaan &amp; Transfer Bahan Baku - Distribusi Antar Gudang';
    let docType = data.is_wasted ? 'Bukti Pengeluaran Wasted' : 'Bukti Permintaan Bahan';
    let colQtyTitle = data.is_wasted ? 'QTY WASTED' : 'QTY DIMINTA';
    let colStokTitle = data.is_wasted ? `STOK LOKASI` : 'STOK GDG UTAMA';

    let gridInfoHtml = '';
    if (data.is_wasted) {
        gridInfoHtml = `
            <tr>
                <td style="padding:10px 14px; width:50%; vertical-align:top; border-right:1px solid #e2e8f0;">
                    <div style="margin-bottom:6px;"><span style="color:#64748b; display:inline-block; width:135px;">Lokasi Wasted:</span> <strong style="color:#0f172a;">${data.lokasi_nama}</strong></div>
                    <div><span style="color:#64748b; display:inline-block; width:135px;">Jenis Pengeluaran:</span> <strong style="color:#dc2626;">Wasted / Busuk / Rusak</strong></div>
                </td>
                <td style="padding:10px 14px; width:50%; vertical-align:top;">
                    <div style="margin-bottom:6px;"><span style="color:#64748b; display:inline-block; width:125px;">Tanggal Laporan:</span> <strong style="color:#0f172a;">${data.tanggal}</strong></div>
                    <div><span style="color:#64748b; display:inline-block; width:125px;">Status Dokumen:</span> ${statusHtml}</div>
                </td>
            </tr>
        `;
    } else {
        gridInfoHtml = `
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
        <!-- HEADER KOP DOKUMEN -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #7A4517; padding-bottom:14px; margin-bottom:18px;">
            <div>
                <div style="font-size:20px; font-weight:800; color:#7A4517; letter-spacing:0.5px;">CV GAHARU AGUNG SEJAHTERA</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">${docSubtitle}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase;">${docType}</div>
                <div style="font-size:11px; color:#64748b; margin-top:3px;">No. Dokumen: <strong style="font-family:monospace; color:#7A4517; font-size:12px;">${data.kode_pengeluaran}</strong></div>
            </div>
        </div>

        <!-- INFO DETAIL GRID -->
        <table style="width:100%; border-collapse:collapse; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:18px; font-size:11.5px;">
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
                <tr style="background:#7A4517; color:#ffffff;">
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
</script>

</x-app-layout>