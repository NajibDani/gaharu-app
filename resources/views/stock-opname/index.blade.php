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
            <form action="{{ route('stock-opname.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no opname/ket..." value="{{ request('search') }}" style="width: 200px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('stock-opname.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
                @endif
            </form>
            <button
                class="btn btn-primary px-4"
                data-bs-toggle="modal"
                data-bs-target="#createOpnameModal">
                <i class="bi bi-plus-circle me-2"></i>
                Buat Stock Opname
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- SUMMARY CARD --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="text-muted mb-2">Total Stock Opname</div>
                    <h2 class="fw-bold mb-0">{{ $stockOpname->total() }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="text-muted mb-2">Draft</div>
                    <h2 class="fw-bold text-warning mb-0">{{ $stockOpname->where('status','draft')->count() }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="text-muted mb-2">Approved</div>
                    <h2 class="fw-bold text-success mb-0">{{ $stockOpname->where('status','approved')->count() }}</h2>
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
                    <thead style="background:#7A4517;color:white;">
                        <tr>
                            <th>Kode</th>
                            <th>Tanggal</th>
                            <th>Gudang & Divisi</th>
                            <th>Petugas</th>
                            <th>Status</th>
                            <th width="180">Aksi</th>
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
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        onclick="showDetailOpname({{ $row->id }})">
                                        <i class="bi bi-eye me-1"></i> Detail
                                    </button>
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
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:#A55A1A;">
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
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-play-circle me-1"></i> Mulai Opname
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL DETAIL & APPROVE --}}
<div class="modal fade" id="detailOpnameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:#7A4517;">
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
        let selisihBadge = '<span class="badge bg-secondary">0</span>';

        if (detail.selisih < 0) {
            selisihBadge = `<span class="badge bg-danger">${detail.selisih.toLocaleString('id-ID')}</span>`;
        } else if (detail.selisih > 0) {
            selisihBadge = `<span class="badge bg-success">+${detail.selisih.toLocaleString('id-ID')}</span>`;
        }

        grandTotal += detail.nilai_selisih;

        rows += `
            <tr>
                <td>${index + 1}</td>
                <td>${detail.nama_barang}</td>
                <td>${detail.stok_sistem.toLocaleString('id-ID')} ${detail.satuan}</td>
                <td>${detail.stok_fisik.toLocaleString('id-ID')} ${detail.satuan}</td>
                <td>${selisihBadge}</td>
                <td>Rp ${detail.nilai_selisih.toLocaleString('id-ID')}</td>
            </tr>
        `;
    });

    let approveButton = '';

    if (data.status === 'draft') {
        approveButton = `
            <a href="/stock-opname/${data.id}/approve" class="btn btn-success" onclick="return confirm('Approve stock opname ini? Selisih negatif akan otomatis membuat pengeluaran bahan baku.')">
                <i class="bi bi-check-circle me-1"></i>
                Approve Stock Opname
            </a>
        `;
    }

    body.innerHTML = `
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
                <div class="mt-1">${statusBadge}</div>
            </div>
        </div>

        <div class="mb-3">
            <small class="text-muted">Keterangan</small>
            <p class="mb-0">${data.keterangan}</p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr style="background:#7A4517;color:white">
                        <th>No</th>
                        <th>Barang</th>
                        <th>Stok Sistem</th>
                        <th>Stok Fisik</th>
                        <th>Selisih</th>
                        <th>Nilai Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
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
</script>

</x-app-layout>