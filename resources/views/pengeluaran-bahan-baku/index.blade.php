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
            <a href="{{ route('pengeluaran-bahan-baku.create') }}"
               class="btn btn-primary">
                <i class="bi bi-plus-circle"></i>
                Tambah
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
                                {{ $item->kode_pengeluaran }}
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

{{-- MODAL DETAIL PENGELUARAN MINIMALIST --}}
<div class="modal fade" id="detailPengeluaranModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background:#7A4517;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0">Detail Pengeluaran Bahan Baku</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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

<script>
function showDetailPengeluaran(id) {
    let modalEl = document.getElementById('detailPengeluaranModal');
    let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let body = document.getElementById('detailPengeluaranBody');

    body.innerHTML = `
        <div class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 mb-0">Memuat detail pengeluaran...</p>
        </div>
    `;

    modal.show();

    fetch(`/pengeluaran-bahan-baku/${id}/detail-json`)
        .then(response => response.json())
        .then(data => renderDetailPengeluaran(data))
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
        ? '<span class="badge bg-success px-3 py-2 fs-6">Approved</span>'
        : '<span class="badge bg-warning text-dark px-3 py-2 fs-6">Draft</span>';

    let divisiBadge = data.divisi_nama
        ? `<span class="badge bg-light text-primary border border-primary-subtle ms-1"><i class="bi bi-diagram-3 me-1"></i>${data.divisi_nama}</span>`
        : '';

    let rows = '';
    data.details.forEach(function (d, index) {
        rows += `
            <tr>
                <td class="text-center text-muted small">${index + 1}</td>
                <td>
                    <div class="fw-semibold text-dark">${d.nama_barang}</div>
                    <small class="text-muted font-monospace">${d.kode_barang}</small>
                </td>
                <td class="text-end fw-bold">
                    ${d.qty.toLocaleString('id-ID')} <span class="text-muted fw-normal small">${d.satuan}</span>
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

    let actionButtons = '';
    if (!data.is_approved) {
        let editBtn = !data.is_wo
            ? `<a href="${data.edit_url}" class="btn btn-warning btn-sm px-3 fw-semibold"><i class="bi bi-pencil-square me-1"></i> Edit</a>`
            : '';
        actionButtons = `
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                ${editBtn}
                <a href="${data.approve_url}" class="btn btn-success btn-sm px-3 fw-semibold" onclick="return confirm('Approve pengeluaran ini?')">
                    <i class="bi bi-check-circle me-1"></i> Approve Pengeluaran
                </a>
            </div>
        `;
    }

    body.innerHTML = `
        {{-- HEADER INFO CARDS --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="text-muted small">Kode Pengeluaran</div>
                    <div class="fw-bold fs-6 text-dark mt-1">${data.kode_pengeluaran}</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="text-muted small">Tujuan</div>
                    <div class="fw-bold fs-6 text-dark mt-1">${data.gudang_nama} ${divisiBadge}</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="p-3 bg-light rounded-3 h-100 border">
                    <div class="text-muted small">Tanggal</div>
                    <div class="fw-bold fs-6 text-dark mt-1">${data.tanggal}</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="p-3 bg-light rounded-3 h-100 border d-flex flex-column justify-content-between">
                    <div class="text-muted small">Status</div>
                    <div>${statusBadge}</div>
                </div>
            </div>
        </div>

        {{-- KETERANGAN JIKA ADA --}}
        ${data.keterangan && data.keterangan !== '-' ? `
            <div class="mb-3 p-3 bg-light rounded-3 border">
                <div class="text-muted small fw-semibold mb-1">Keterangan:</div>
                <div class="text-dark small">${data.keterangan}</div>
            </div>
        ` : ''}

        {{-- TABEL DETAIL BARANG --}}
        <div class="table-responsive rounded-3 border">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40" class="text-center">#</th>
                        <th>Barang</th>
                        <th width="120" class="text-end">Qty</th>
                        <th width="140" class="text-end">Harga Satuan</th>
                        <th width="160" class="text-end">Total HPP</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
                <tfoot class="table-light border-top">
                    <tr>
                        <th colspan="4" class="text-end fw-bold">Total Nilai FIFO ${!data.is_approved ? '<span class="text-muted fw-normal small">(Estimasi)</span>' : ''}:</th>
                        <th class="text-end fw-bold fs-6" style="color:#7A4517;">Rp ${data.grand_total.toLocaleString('id-ID')}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        ${actionButtons}
    `;
}
</script>

</x-app-layout>