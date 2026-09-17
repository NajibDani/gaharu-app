<x-app-layout>

<x-slot name="header">
    Stock Opname
</x-slot>

<div class="container-fluid">

    {{-- PAGE HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                Stock Opname
            </h3>
            <p class="text-muted mb-0">
                Penyesuaian persediaan fisik gudang dan divisi operasional (Kitchen, Barista, Server, dll).
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button
                class="btn btn-success text-white px-3 min-hitbox d-inline-flex align-items-center justify-content-center fw-bold"
                data-bs-toggle="modal"
                data-bs-target="#importOpnameModal">
                <i class="bi bi-file-earmark-arrow-up me-2"></i>
                Import Excel SO
            </button>
            <button
                class="btn text-white px-4 min-hitbox d-inline-flex align-items-center justify-content-center"
                style="background-color: #DE8958; border: none;"
                data-bs-toggle="modal"
                data-bs-target="#createOpnameModal">
                <i class="bi bi-plus-circle me-2"></i>
                Buat Stock Opname
            </button>
        </div>
    </div>

    {{-- FILTER FORM CARD --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('stock-opname.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Cari Opname / Barang / Ket</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Kode / nama barang / ket..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Filter Gudang</label>
                    <select name="gudang_id" class="form-select form-select-sm">
                        <option value="">-- Semua Gudang --</option>
                        @foreach($gudangs as $g)
                            <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                {{ $g->nama }} ({{ $g->kategori }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-semibold mb-1">Kategori Barang</label>
                    <select name="kategori_id" class="form-select form-select-sm">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($kategoris as $k)
                            <option value="{{ $k->id }}" {{ request('kategori_id') == $k->id ? 'selected' : '' }}>
                                {{ $k->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-semibold mb-1">Jenis Barang</label>
                    <select name="jenis_barang" class="form-select form-select-sm">
                        <option value="">-- Semua Jenis --</option>
                        <option value="bahan_baku" {{ request('jenis_barang') == 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                        <option value="bahan_setengah_jadi" {{ request('jenis_barang') == 'bahan_setengah_jadi' ? 'selected' : '' }}>Bahan Setengah Jadi</option>
                        <option value="barang_jadi" {{ request('jenis_barang') == 'barang_jadi' ? 'selected' : '' }}>Barang Jadi</option>
                        <option value="operational" {{ request('jenis_barang') == 'operational' ? 'selected' : '' }}>Operational</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end gap-1" style="padding-top: 22px;">
                    <button type="submit" class="btn btn-sm text-white flex-fill min-hitbox" style="background-color: #DE8958; border: none;">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search', 'gudang_id', 'kategori_id', 'jenis_barang']))
                        <a href="{{ route('stock-opname.index') }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center justify-content-center" title="Reset Filter">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show m-3 d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show m-3 d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- SUMMARY CARD --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="text-muted mb-2">Total Stock Opname</div>
                    <h2 class="fw-bold mb-0">{{ $stockOpname->total() }}</h2>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="text-muted mb-2">Draft</div>
                    <h2 class="fw-bold text-warning mb-0">{{ $totalDraft ?? 0 }}</h2>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="text-muted mb-2">Approved</div>
                    <h2 class="fw-bold text-success mb-0">{{ $totalApproved ?? 0 }}</h2>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLE CARD --}}
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-0">Daftar Stock Opname</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead style="background-color: #715745; color: white;">
                        <tr>
                            <th class="text-white">Kode</th>
                            <th class="text-white">Tanggal</th>
                            <th class="text-white">Gudang & Divisi</th>
                            <th class="text-white">Petugas</th>
                            <th class="text-white">Status</th>
                            <th class="text-white" width="180">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($stockOpname as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->kode_opname }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d M Y') }}</td>
                                <td>
                                    <div class="fw-bold">{{ $row->gudang->nama ?? '-' }}</div>
                                    @if($row->divisi)
                                        <span class="badge bg-light text-primary border border-primary-subtle mt-1">
                                            <i class="bi bi-diagram-3 me-1"></i>{{ $row->divisi->nama }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $row->user->nama_karyawan ?? $row->user->name ?? '-' }}</td>
                                <td>
                                    @if($row->status == 'approved')
                                        <span class="badge bg-success">Approved</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Draft</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            onclick="showDetailOpname({{ $row->id }})">
                                            <i class="bi bi-eye me-1"></i> Detail
                                        </button>
                                        <a href="{{ route('stock-opname.export-excel', $row->id) }}"
                                           class="btn btn-sm btn-outline-success fw-medium"
                                           title="Download Excel Document">
                                            <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                        </a>
                                        @if($row->status === 'draft' || ($row->status === 'approved' && $isSuperAdmin))
                                            <a href="{{ route('stock-opname.edit', $row->id) }}"
                                               class="btn btn-sm btn-outline-warning text-dark fw-medium"
                                               title="{{ $row->status === 'approved' ? 'Edit Approved Opname (Super Admin)' : 'Edit Stock Opname' }}">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </a>
                                            <form action="{{ route('stock-opname.destroy', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Stock Opname {{ $row->kode_opname }}? Seluruh efek penyesuaian stok, FIFO, dan jurnal terkait akan di-rollback kembali ke kondisi semula.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger fw-medium" title="Hapus & Rollback Stock Opname">
                                                    <i class="bi bi-trash me-1"></i> Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    Belum ada data Stock Opname
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white">
            {{ $stockOpname->links() }}
        </div>
    </div>

</div>

{{-- MODAL CREATE --}}
<div class="modal fade" id="createOpnameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background-color: #715745;">
                <h5 class="modal-title fw-bold">Buat Stock Opname</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="GET" action="{{ route('stock-opname.create') }}" id="formModalOpname">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Gudang <span class="text-danger">*</span></label>
                        <select name="gudang_id" id="modal_select_gudang" class="form-select" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($gudangs as $gudang)
                                <option value="{{ $gudang->id }}" data-kategori="{{ strtolower($gudang->kategori) }}">
                                    {{ $gudang->nama }} ({{ $gudang->kategori }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3" id="modal_divisi_wrapper" style="display: none;">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-diagram-3-fill text-primary me-1"></i> Pilih Divisi Operasional <span class="text-danger">*</span>
                        </label>
                        <select name="divisi_id" id="modal_select_divisi" class="form-select">
                            <option value="">-- Pilih Divisi --</option>
                        </select>
                        <div class="form-text text-muted">
                            Stock opname gudang operasional dilakukan per divisi (Kitchen / Barista / Server, dll).
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white px-4 min-hitbox" style="background-color: #DE8958; border: none;">
                        <i class="bi bi-play-circle me-1"></i> Mulai Opname
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL IMPORT EXCEL STOCK OPNAME --}}
<div class="modal fade" id="importOpnameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header text-white" style="background-color: #2E7D32;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-excel me-2"></i>Import Stock Opname dari Excel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('stock-opname.import-store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-success border-0 bg-success-subtle text-success-emphasis small rounded-3 mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Sistem hanya akan membaca data <strong>Kode Barang</strong> dan <strong>Stok Fisik</strong> dari file Excel. Data Stok Sistem, Selisih, dan HPP/FIFO dihitung otomatis.
                    </div>

                    {{-- Pilih Gudang --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Gudang <span class="text-danger">*</span></label>
                        <select name="gudang_id" id="import_select_gudang" class="form-select" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($gudangs as $gudang)
                                <option value="{{ $gudang->id }}" data-kategori="{{ strtolower($gudang->kategori) }}">
                                    {{ $gudang->nama }} ({{ $gudang->kategori }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Pilih Divisi (Jika gudang operasional) --}}
                    <div class="mb-3" id="import_divisi_wrapper" style="display: none;">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-diagram-3-fill text-primary me-1"></i> Pilih Divisi Operasional <span class="text-danger">*</span>
                        </label>
                        <select name="divisi_id" id="import_select_divisi" class="form-select">
                            <option value="">-- Pilih Divisi --</option>
                        </select>
                    </div>

                    {{-- Tanggal Opname --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal Opname <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    {{-- Keterangan --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keterangan / Catatan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Stock Opname dari Excel..."></textarea>
                    </div>

                    {{-- File Excel --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File Excel (.xlsx / .xls / .csv) <span class="text-danger">*</span></label>
                        <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success text-white px-4 fw-bold">
                        <i class="bi bi-upload me-1"></i> Import & Simpan Draft SO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL DETAIL & APPROVE --}}
<div class="modal fade" id="detailOpnameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background-color: #715745;">
                <h5 class="modal-title fw-bold">Detail Stock Opname</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailOpnameBody">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-3 mb-0">Memuat data...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalSelectGudang = document.getElementById('modal_select_gudang');
    const modalDivisiWrapper = document.getElementById('modal_divisi_wrapper');
    const modalSelectDivisi = document.getElementById('modal_select_divisi');

    if (modalSelectGudang) {
        modalSelectGudang.addEventListener('change', function() {
            const gudangId = this.value;
            if (!gudangId) {
                modalDivisiWrapper.style.display = 'none';
                modalSelectDivisi.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                modalSelectDivisi.required = false;
                return;
            }

            fetch('/gudangs/' + gudangId + '/divisi')
                .then(res => res.json())
                .then(data => {
                    if (data.is_operasional && data.divisi && data.divisi.length > 0) {
                        modalDivisiWrapper.style.display = 'block';
                        modalSelectDivisi.required = true;
                        let opts = '<option value="">-- Pilih Divisi --</option>';
                        data.divisi.forEach(d => {
                            opts += `<option value="${d.id}">${d.nama}</option>`;
                        });
                        modalSelectDivisi.innerHTML = opts;
                    } else {
                        modalDivisiWrapper.style.display = 'none';
                        modalSelectDivisi.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                        modalSelectDivisi.required = false;
                    }
                })
                .catch(() => {
                    modalDivisiWrapper.style.display = 'none';
                    modalSelectDivisi.required = false;
                });
        });
    }

    const importSelectGudang = document.getElementById('import_select_gudang');
    const importDivisiWrapper = document.getElementById('import_divisi_wrapper');
    const importSelectDivisi = document.getElementById('import_select_divisi');

    if (importSelectGudang) {
        importSelectGudang.addEventListener('change', function() {
            const gudangId = this.value;
            if (!gudangId) {
                importDivisiWrapper.style.display = 'none';
                importSelectDivisi.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                importSelectDivisi.required = false;
                return;
            }

            fetch('/gudangs/' + gudangId + '/divisi')
                .then(res => res.json())
                .then(data => {
                    if (data.is_operasional && data.divisi && data.divisi.length > 0) {
                        importDivisiWrapper.style.display = 'block';
                        importSelectDivisi.required = true;
                        let opts = '<option value="">-- Pilih Divisi --</option>';
                        data.divisi.forEach(d => {
                            opts += `<option value="${d.id}">${d.nama}</option>`;
                        });
                        importSelectDivisi.innerHTML = opts;
                    } else {
                        importDivisiWrapper.style.display = 'none';
                        importSelectDivisi.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                        importSelectDivisi.required = false;
                    }
                })
                .catch(() => {
                    importDivisiWrapper.style.display = 'none';
                    importSelectDivisi.required = false;
                });
        });
    }
});

function showDetailOpname(id) {
    let modalEl = document.getElementById('detailOpnameModal');
    let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let body = document.getElementById('detailOpnameBody');

    body.innerHTML = `
        <div class="text-center text-muted py-5">
            <div class="spinner-border" role="status"></div>
            <p class="mt-3 mb-0">Memuat data...</p>
        </div>
    `;

    modal.show();

    fetch(`/stock-opname/${id}/detail-json`)
        .then(response => response.json())
        .then(data => renderDetailOpname(data))
        .catch(() => {
            body.innerHTML = `
                <div class="text-center text-danger py-5">
                    Gagal memuat data stock opname.
                </div>
            `;
        });
}

function renderDetailOpname(data) {
    let body = document.getElementById('detailOpnameBody');

    let statusBadge = data.status === 'approved'
        ? '<span class="badge bg-success">Approved</span>'
        : '<span class="badge bg-warning text-dark">Draft</span>';

    let rows = '';
    let grandTotal = 0;

    data.details.forEach(function (detail, index) {
        let selisihBadge = `<span class="badge bg-secondary">0 ${detail.satuan}</span>`;

        if (detail.selisih < 0) {
            selisihBadge = `<span class="badge bg-danger">${detail.selisih.toLocaleString('id-ID')} ${detail.satuan}</span>`;
        } else if (detail.selisih > 0) {
            selisihBadge = `<span class="badge bg-success">+${detail.selisih.toLocaleString('id-ID')} ${detail.satuan}</span>`;
        }

        let konvSistem = detail.has_konversi
            ? `<div class="small text-primary mt-1" style="font-size:0.75rem;"><i class="bi bi-arrow-repeat me-1"></i>${detail.stok_sistem_konv.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${detail.satuan_pembelian}</div>`
            : '';

        let konvFisik = detail.has_konversi
            ? `<div class="small text-primary mt-1" style="font-size:0.75rem;"><i class="bi bi-arrow-repeat me-1"></i>${detail.stok_fisik_konv.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${detail.satuan_pembelian}</div>`
            : '';

        let konvSelisih = detail.has_konversi
            ? `<div class="small ${detail.selisih > 0 ? 'text-success' : (detail.selisih < 0 ? 'text-danger' : 'text-muted')} mt-1 font-monospace" style="font-size:0.75rem;">(${detail.selisih > 0 ? '+' : ''}${detail.selisih_konv.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${detail.satuan_pembelian})</div>`
            : '';

        let konvHeader = detail.has_konversi
            ? `<small class="text-primary d-block font-monospace" style="font-size:0.72rem;">1 ${detail.satuan_pembelian} = ${detail.konversi_pembelian.toLocaleString('id-ID')} ${detail.satuan}</small>`
            : '';

        grandTotal += detail.nilai_selisih;

        let safeNama = (detail.nama_barang || '').replace(/"/g, '&quot;');
        let safeKode = (detail.kode_barang || '').replace(/"/g, '&quot;');

        rows += `
            <tr class="opname-detail-item-row" data-name="${safeNama.toLowerCase()}" data-code="${safeKode.toLowerCase()}">
                <td class="opname-row-index">${index + 1}</td>
                <td>
                    <div class="fw-bold">${detail.nama_barang}</div>
                    <small class="text-muted font-monospace">${detail.kode_barang}</small>
                    ${konvHeader}
                </td>
                <td>
                    <div>${detail.stok_sistem.toLocaleString('id-ID')} <span class="text-muted small">${detail.satuan}</span></div>
                    ${konvSistem}
                </td>
                <td>
                    <div>${detail.stok_fisik.toLocaleString('id-ID')} <span class="text-muted small">${detail.satuan}</span></div>
                    ${konvFisik}
                </td>
                <td>
                    ${selisihBadge}
                    ${konvSelisih}
                </td>
                <td class="fw-bold">Rp ${detail.nilai_selisih.toLocaleString('id-ID')}</td>
            </tr>
        `;
    });

    let approveButton = '';
    let csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

    if (data.status === 'draft') {
        approveButton = `
            <button type="button" class="btn btn-outline-primary fw-semibold me-2" onclick="refreshStokOpname(${data.id})" id="btnRefreshStok_${data.id}">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh / Sinkronkan Stok
            </button>
            <a href="/stock-opname/${data.id}/edit" class="btn btn-warning text-dark fw-bold me-2">
                <i class="bi bi-pencil me-1"></i>
                Edit Stock Opname
            </a>
            <a href="/stock-opname/${data.id}/approve" class="btn btn-success me-2" onclick="return confirm('Approve stock opname ini? Selisih negatif akan otomatis membuat pengeluaran bahan baku.')">
                <i class="bi bi-check-circle me-1"></i>
                Approve Stock Opname
            </a>
            <form action="/stock-opname/${data.id}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Stock Opname ${data.kode_opname}? Data penyesuaian stok akan dibatalkan.')">
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-outline-danger fw-bold">
                    <i class="bi bi-trash me-1"></i> Hapus
                </button>
            </form>
        `;
    } else if (data.status === 'approved' && data.is_superadmin) {
        approveButton = `
            <a href="/stock-opname/${data.id}/edit" class="btn btn-warning text-dark fw-bold me-2">
                <i class="bi bi-pencil-square me-1"></i>
                Edit Stock Opname (Super Admin)
            </a>
            <form action="/stock-opname/${data.id}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Stock Opname ${data.kode_opname}? Seluruh efek penyesuaian stok, FIFO, dan jurnal terkait akan di-rollback kembali ke kondisi sebelum opname.')">
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-danger text-white fw-bold">
                    <i class="bi bi-trash me-1"></i> Hapus & Rollback Stock
                </button>
            </form>
        `;
    }

    body.innerHTML = `
        <div id="opnameAlertContainer"></div>
        <div class="row mb-4">
            <div class="col-md-3">
                <small class="text-muted">Kode Opname</small>
                <h6 class="fw-bold mt-1">${data.kode_opname}</h6>
            </div>
            <div class="col-md-3">
                <small class="text-muted">Gudang & Divisi</small>
                <h6 class="fw-bold mt-1">
                    ${data.gudang}
                    ${data.divisi && data.divisi !== '-' ? `<span class="badge bg-light text-primary border border-primary-subtle ms-1"><i class="bi bi-diagram-3 me-1"></i>${data.divisi}</span>` : ''}
                </h6>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block mb-1">Tanggal</small>
                <h6 class="fw-bold mt-1">${data.tanggal}</h6>
            </div>
            <div class="col-md-3">
                <small class="text-muted">Status</small>
                <div class="mt-1 d-flex align-items-center gap-2">
                    ${statusBadge}
                    ${data.status === 'draft' ? `
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" onclick="refreshStokOpname(${data.id})" title="Refresh stok sistem dengan data gudang terkini" style="font-size: 0.75rem;">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>

        <div class="mb-3">
            <small class="text-muted">Keterangan</small>
            <p class="mb-0">${data.keterangan || '-'}</p>
        </div>

        {{-- SEARCH BAR DALAM MODAL DETAIL --}}
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div style="min-width: 280px; max-width: 380px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="opnameModalSearchInput" class="form-control border-start-0" placeholder="Cari nama barang atau kode barang..." oninput="filterOpnameDetailModal(this.value)">
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('opnameModalSearchInput').value=''; filterOpnameDetailModal('');" title="Reset">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <div class="text-muted small">
                Menampilkan <span id="opnameVisibleCount" class="fw-bold text-dark">${data.details.length}</span> dari <span class="fw-bold text-dark">${data.details.length}</span> barang
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr style="background-color:#715745;color:white">
                        <th class="text-white">No</th>
                        <th class="text-white">Barang</th>
                        <th class="text-white">Stok Sistem</th>
                        <th class="text-white">Stok Fisik</th>
                        <th class="text-white">Selisih</th>
                        <th class="text-white">Nilai Selisih</th>
                    </tr>
                </thead>
                <tbody id="opnameDetailTableBody">
                    ${rows}
                    <tr id="opnameEmptySearchRow" style="display:none;">
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-search fs-4 d-block mb-1 text-muted"></i>
                            Tidak ada barang yang cocok dengan pencarian.
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">TOTAL NILAI SELISIH</th>
                        <th class="fw-bold">Rp ${grandTotal.toLocaleString('id-ID')}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-4 text-end">
            ${approveButton}
        </div>
    `;
}

function filterOpnameDetailModal(query) {
    let q = (query || '').toLowerCase().trim();
    let rows = document.querySelectorAll('.opname-detail-item-row');
    let visibleCount = 0;

    rows.forEach(row => {
        let name = row.getAttribute('data-name') || '';
        let code = row.getAttribute('data-code') || '';
        if (!q || name.includes(q) || code.includes(q)) {
            row.style.display = '';
            visibleCount++;
            let indexCell = row.querySelector('.opname-row-index');
            if (indexCell) indexCell.innerText = visibleCount;
        } else {
            row.style.display = 'none';
        }
    });

    let countEl = document.getElementById('opnameVisibleCount');
    if (countEl) countEl.innerText = visibleCount;

    let emptyRow = document.getElementById('opnameEmptySearchRow');
    if (emptyRow) {
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
    }
}

function refreshStokOpname(id) {
    if (!confirm('Apakah Anda yakin ingin memperbarui dan menyinkronkan stok sistem dengan kondisi data gudang terkini?')) {
        return;
    }

    let btn = document.getElementById(`btnRefreshStok_${id}`);
    let originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memperbarui...`;
    }

    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch(`/stock-opname/${id}/refresh-stok`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(res => {
        if (res.success) {
            // Re-fetch detail dan re-render modal
            fetch(`/stock-opname/${id}/detail-json`)
                .then(response => response.json())
                .then(data => {
                    renderDetailOpname(data);

                    let alertContainer = document.getElementById('opnameAlertContainer');
                    if (alertContainer) {
                        alertContainer.innerHTML = `
                            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> ${res.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                    }
                });
        } else {
            alert(res.message || 'Gagal memperbarui stok.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan koneksi saat memperbarui stok.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
}
</script>

</x-app-layout>