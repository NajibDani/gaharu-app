<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        .text-gray-800 { color: #1e293b; }
        .text-gray-700 { color: #334155; }
        .text-gray-600 { color: #475569; }
        .btn-custom-orange { background-color: #d88656; color: white; border: none; }
        .btn-custom-orange:hover { background-color: #c77545; color: white; }
    </style>

    <div class="container py-4" style="font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; min-height: 100vh; margin-top: 5.5rem !important;">
        <div class="row">
            {{-- Alert Notifikasi --}}
            <div class="col-md-12 mb-3">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important;">
                        <i class="bi bi-check-circle-fill me-3 fs-5 text-success"></i>
                        <div>
                            <span class="fw-bold text-success d-block">Berhasil</span>
                            <span class="small text-secondary">{{ session('success') }}</span>
                        </div>
                        <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 p-3" role="alert">
                        <ul class="mb-0 small">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>

            {{-- Header Section --}}
            <div class="col-md-12 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold text-gray-800 m-0"><i class="bi bi-tag-fill me-2" style="color: #d88656;"></i>Manajemen Harga Jual B2B per Outlet</h3>
                    <p class="text-muted small m-0 mt-1">Atur tarif harga jual khusus untuk setiap outlet/customer pemesan B2B secara fleksibel.</p>
                </div>
                <button type="button" class="btn btn-custom-orange shadow-sm px-3 py-2 fw-semibold rounded-3 d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalTambahHargaB2b">
                    <i class="bi bi-plus-circle"></i> Tambah Harga Jual B2B
                </button>
            </div>

            {{-- Filter & Tabel --}}
            <div class="col-md-12">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-body bg-white p-4">
                        <form action="{{ route('harga-b2b.index') }}" method="GET" class="row g-2 mb-3 align-items-center">
                            <div class="col-md-4">
                                <label class="small text-muted fw-semibold mb-1">Filter Outlet Pemesan</label>
                                <select name="customer_id" class="form-select form-select-sm rounded-3" onchange="this.form.submit()">
                                    <option value="">-- Semua Outlet / Customer --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" {{ $customerId == $c->id ? 'selected' : '' }}>{{ $c->nama ?? $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="small text-muted fw-semibold mb-1">Cari Nama / Kode Barang</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" value="{{ $search }}" class="form-control rounded-start-3" placeholder="Ketik nama produk...">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Cari</button>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                @if($customerId || $search)
                                    <a href="{{ route('harga-b2b.index') }}" class="btn btn-sm btn-outline-danger w-100 rounded-3">
                                        <i class="bi bi-x-circle me-1"></i> Reset Filter
                                    </a>
                                @endif
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background-color: #f8fafc; color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                                    <tr>
                                        <th class="ps-3 py-3" width="5%">No</th>
                                        <th class="py-3" width="22%">Outlet Pemesan</th>
                                        <th class="py-3">Nama Barang</th>
                                        <th class="py-3 text-end" width="15%">HPP / Satuan</th>
                                        <th class="py-3 text-end" width="17%">Harga Jual B2B</th>
                                        <th class="py-3 text-end" width="14%">Estimasi Laba</th>
                                        <th class="py-3 text-center" width="10%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($listHargaB2b as $index => $item)
                                        @php
                                            $itemSatuan = $item->barang->satuan ?? 'unit';
                                        @endphp
                                        <tr>
                                            <td class="ps-3 text-muted">{{ $listHargaB2b->firstItem() + $index }}</td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 fs-7">
                                                    <i class="bi bi-shop me-1"></i>{{ $item->customer->nama ?? ($item->customer->name ?? 'Semua Outlet') }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-gray-800">{{ $item->barang->nama ?? 'Barang #' . $item->barang_id }}</div>
                                                <div class="text-muted small font-monospace">{{ $item->barang->kode_barang ?? '-' }}</div>
                                                @if($item->keterangan)
                                                    <div class="text-secondary small fst-italic mt-0.5"><i class="bi bi-info-circle me-1"></i>{{ $item->keterangan }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-link text-decoration-none p-0 text-end btn-show-hpp-detail" 
                                                        data-barang-id="{{ $item->barang_id }}"
                                                        data-barang-nama="{{ $item->barang->nama ?? '' }}"
                                                        title="Klik untuk melihat rincian bahan & perhitungan HPP per {{ $itemSatuan }}">
                                                    <div class="fw-bold text-dark d-flex align-items-center justify-content-end gap-1">
                                                        <span>Rp {{ number_format($item->hpp_referensi ?? 0, 0, ',', '.') }}</span>
                                                        <i class="bi bi-info-circle-fill text-info" style="font-size: 13px;"></i>
                                                    </div>
                                                    <span class="text-primary small text-decoration-underline" style="font-size: 11px;">/ {{ $itemSatuan }} (lihat detail)</span>
                                                </button>
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold text-success fs-6">Rp {{ number_format($item->harga_b2b ?? 0, 0, ',', '.') }}</div>
                                                <span class="text-muted small" style="font-size: 11px;">/ {{ $itemSatuan }}</span>
                                            </td>
                                            <td class="text-end">
                                                @if(($item->laba_unit ?? 0) >= 0)
                                                    <div class="fw-bold text-primary">Rp {{ number_format($item->laba_unit ?? 0, 0, ',', '.') }}</div>
                                                    <span class="badge bg-success-subtle text-success small" style="font-size: 11px;">+{{ $item->margin_persen ?? 0 }}% / {{ $itemSatuan }}</span>
                                                @else
                                                    <div class="fw-bold text-danger">Rp {{ number_format($item->laba_unit ?? 0, 0, ',', '.') }}</div>
                                                    <span class="badge bg-danger-subtle text-danger small" style="font-size: 11px;">{{ $item->margin_persen ?? 0 }}% / {{ $itemSatuan }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm shadow-sm" role="group">
                                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditHargaB2b{{ $item->id }}" title="Edit Harga">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapusHargaB2b{{ $item->id }}" title="Hapus Aturan">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- MODAL EDIT HARGA B2B --}}
                                        <div class="modal fade" id="modalEditHargaB2b{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header bg-warning text-dark py-3 px-4">
                                                        <h5 class="modal-title fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Harga Jual B2B</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="{{ route('harga-b2b.update', $item->id) }}" method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-body p-4">
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold text-secondary small">Outlet Pemesan</label>
                                                                <input type="text" class="form-control bg-light" value="{{ $item->customer->nama ?? '-' }}" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold text-secondary small">Nama Barang</label>
                                                                <input type="text" class="form-control bg-light" value="{{ $item->barang->nama ?? '-' }} ({{ $item->barang->kode_barang ?? '-' }})" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold text-secondary small">Harga Jual B2B per Satuan (Rp) <span class="text-danger">*</span></label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light fw-bold">Rp</span>
                                                                    <input type="number" name="harga_b2b" class="form-control fw-bold text-success fs-6" value="{{ (int)$item->harga_b2b }}" required min="0" step="any">
                                                                    <span class="input-group-text bg-light text-muted fw-semibold">/ {{ $itemSatuan }}</span>
                                                                </div>
                                                                <div class="form-text small text-muted mt-1">
                                                                    Harga jual per <strong>{{ $itemSatuan }}</strong> khusus untuk outlet pemesan yang dipilih (Satuan Master Barang).
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold text-secondary small">Catatan / Keterangan (Opsional)</label>
                                                                <input type="text" name="keterangan" class="form-control" value="{{ $item->keterangan }}" placeholder="Misal: Harga kontrak Q3, diskon volume khusus">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light px-4 py-3">
                                                            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-warning px-4 fw-bold">Simpan Perubahan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- MODAL HAPUS HARGA B2B --}}
                                        <div class="modal fade" id="modalHapusHargaB2b{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header bg-danger text-white py-3 px-4">
                                                        <h5 class="modal-title fw-bold mb-0"><i class="bi bi-trash-fill me-2"></i>Hapus Pengaturan Harga B2B</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="{{ route('harga-b2b.destroy', $item->id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <div class="modal-body p-4">
                                                            <p class="mb-2">Apakah Anda yakin ingin menghapus pengaturan harga khusus B2B berikut?</p>
                                                            <div class="p-3 bg-light rounded-3 border">
                                                                <div><strong>Outlet:</strong> {{ $item->customer->nama ?? '-' }}</div>
                                                                <div><strong>Barang:</strong> {{ $item->barang->nama ?? '-' }}</div>
                                                                <div><strong>Harga:</strong> Rp {{ number_format($item->harga_b2b, 0, ',', '.') }} / {{ $itemSatuan }}</div>
                                                            </div>
                                                            <p class="text-muted small mt-2 mb-0">Setelah dihapus, harga jual untuk outlet ini akan kembali menggunakan harga standar barang.</p>
                                                        </div>
                                                        <div class="modal-footer bg-light px-4 py-3">
                                                            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-danger px-4 fw-bold">Ya, Hapus</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="bi bi-tags fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                                Belum ada pengaturan harga jual B2B khusus outlet pemesan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $listHargaB2b->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL TAMBAH HARGA B2B --}}
    <div class="modal fade" id="modalTambahHargaB2b" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header text-white py-3 px-4" style="background-color: #d88656;">
                    <h5 class="modal-title fw-bold mb-0"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Harga Jual B2B per Outlet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('harga-b2b.store') }}" method="POST" id="formTambahHargaB2b">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">Pilihan Outlet Pemesan <span class="text-danger">*</span></label>
                            <select name="customer_id" id="selectCustomerB2b" class="form-select select2-b2b-customer" required style="width: 100%;">
                                <option value="">-- Ketik / Pilih Outlet Pemesan --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" 
                                            data-nama="{{ strtolower($c->nama ?? $c->name) }}"
                                            {{ ($customerId == $c->id) ? 'selected' : '' }}>
                                        {{ $c->nama ?? $c->name }} ({{ $c->jenis ?? 'Customer B2B' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-semibold text-secondary small mb-0">Nama Barang / Produk <span class="text-danger">*</span></label>
                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-0.5" id="badgeItemCount" style="font-size: 0.72rem;">0 item</span>
                            </div>
                            <select name="barang_id" id="selectBarangB2b" class="form-select select2-b2b-barang" required style="width: 100%;">
                                <option value="">-- Pilih outlet pemesan terlebih dahulu --</option>
                            </select>
                            <div class="form-text small text-muted mt-1" id="barangFilterHint">
                                <i class="bi bi-info-circle me-1"></i>Pilih outlet pemesan untuk memuat produk yang sesuai (BSJ tagging divisi / POS).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">Harga Jual B2B Khusus (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">Rp</span>
                                <input type="number" name="harga_b2b" id="inputHargaB2b" class="form-control fw-bold text-success fs-6" placeholder="0" required min="0" step="any">
                                <span class="input-group-text bg-light text-muted fw-semibold" id="b2bSatuanSuffix">/ unit</span>
                            </div>
                            <div class="form-text small text-muted mt-1" id="b2bSatuanHelp">
                                Harga jual per unit/satuan master barang khusus untuk outlet pemesan yang dipilih.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">Catatan / Keterangan (Opsional)</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Misal: Harga khusus kontrak, diskon outlet">
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-custom-orange px-4 fw-bold">Simpan Harga B2B</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL DETAIL RINCIAN HPP & RESEP BAHAN --}}
    <div class="modal fade" id="modalDetailHppResep" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-calculator fs-4 text-warning"></i>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white" id="modalHppTitle">Rincian Komponen HPP &amp; Resep</h5>
                            <span class="badge bg-secondary-subtle text-white-50 small" id="modalHppSubtitle" style="font-size: 11px;">Memuat data...</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- SPINNER LOADING --}}
                    <div id="modalHppLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="text-muted mt-2 small">Mengambil rincian bahan &amp; harga referensi...</div>
                    </div>

                    {{-- ISI RINCIAN --}}
                    <div id="modalHppContent" style="display: none;">
                        {{-- ALERT IF NO RESEP --}}
                        <div id="modalHppNoResepAlert" class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2 rounded-3" style="display: none;">
                            <i class="bi bi-info-circle-fill text-info fs-5"></i>
                            <div class="small">
                                Produk ini belum memiliki formula resep BOM. Nilai HPP yang ditampilkan adalah harga referensi master barang.
                            </div>
                        </div>

                        {{-- INFO SUMMARY HEADER --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border text-center">
                                    <div class="text-muted small fw-semibold">Output Yield 1 Batch</div>
                                    <div class="fw-bold fs-5 text-dark" id="modalHppOutputBatch">-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border text-center">
                                    <div class="text-muted small fw-semibold">Total HPP 1 Batch</div>
                                    <div class="fw-bold fs-5 text-secondary" id="modalHppTotalBatch">-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary text-center">
                                    <div class="text-primary small fw-bold">HPP Satuan (Cost / Unit)</div>
                                    <div class="fw-bolder fs-4 text-primary" id="modalHppPerUnit">-</div>
                                </div>
                            </div>
                        </div>

                        {{-- TABEL BAHAN BAKU --}}
                        <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-basket3 text-primary"></i>
                            Rincian Bahan Baku &amp; Harga Referensi
                        </h6>
                        <div class="table-responsive border rounded-3 mb-4">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="text-secondary small">
                                        <th class="py-2 text-center" width="5%">#</th>
                                        <th class="py-2">Nama Bahan</th>
                                        <th class="py-2 text-end" width="15%">Qty 1 Batch</th>
                                        <th class="py-2 text-end" width="15%">Qty / Satuan</th>
                                        <th class="py-2 text-end" width="18%">Harga Satuan</th>
                                        <th class="py-2 text-end" width="18%">Biaya / Satuan</th>
                                    </tr>
                                </thead>
                                <tbody id="modalHppTableBody" class="small">
                                    <!-- Dynamic rows -->
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end py-2">Total Biaya Bahan Baku (BBB) / Satuan:</td>
                                        <td class="text-end py-2 text-primary" id="modalHppTotalBbbPerUnit">-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- RINCIAN KOMPONEN BIAYA (BBB, BTKL, BOP) --}}
                        <div class="card border-0 bg-light rounded-3 p-3">
                            <div class="row g-2 small">
                                <div class="col-md-4 d-flex justify-content-between">
                                    <span class="text-muted">Biaya Bahan Baku (BBB):</span>
                                    <strong id="modalHppBbbSummary">Rp 0</strong>
                                </div>
                                <div class="col-md-4 d-flex justify-content-between">
                                    <span class="text-muted">BTKL (20%):</span>
                                    <strong id="modalHppBtklSummary">Rp 0</strong>
                                </div>
                                <div class="col-md-4 d-flex justify-content-between">
                                    <span class="text-muted">BOP (10%):</span>
                                    <strong id="modalHppBopSummary">Rp 0</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const allBarangData = @json($barangData ?? []);

        function filterBarangByCustomer(customerId) {
            const selectCustomer = document.getElementById('selectCustomerB2b');
            const selectBarang = document.getElementById('selectBarangB2b');
            const badgeCount = document.getElementById('badgeItemCount');
            const filterHint = document.getElementById('barangFilterHint');
            const suffix = document.getElementById('b2bSatuanSuffix');
            const help = document.getElementById('b2bSatuanHelp');

            if (!selectCustomer || !selectBarang) return;

            let customerName = '';
            const selectedOpt = selectCustomer.querySelector(`option[value="${customerId}"]`);
            if (selectedOpt) {
                customerName = (selectedOpt.getAttribute('data-nama') || selectedOpt.textContent || '').toLowerCase();
            }

            let filtered = [];
            let outletTypeLabel = '';

            if (customerName.includes('kejingga')) {
                outletTypeLabel = 'KeJingga';
                filtered = allBarangData.filter(item => item.is_kejingga_bsj || item.is_kejingga_pos);
            } else if (customerName.includes('gaharu')) {
                outletTypeLabel = 'Gaharu';
                filtered = allBarangData.filter(item => item.is_gaharu_bsj || item.is_gaharu_pos);
            } else if (customerId) {
                outletTypeLabel = 'Outlet B2B';
                filtered = allBarangData.filter(item => item.for_b2b_general);
            } else {
                filtered = [];
            }

            // Bersihkan select barang
            selectBarang.innerHTML = '';

            if (!customerId) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '-- Pilih outlet pemesan terlebih dahulu --';
                selectBarang.appendChild(opt);
                if (badgeCount) badgeCount.textContent = '0 item';
                if (filterHint) {
                    filterHint.innerHTML = '<i class="bi bi-info-circle me-1"></i>Pilih outlet pemesan untuk memuat produk yang sesuai.';
                }
                if (suffix) suffix.textContent = '/ unit';
                if (help) help.textContent = 'Harga jual per unit/satuan master barang khusus untuk outlet pemesan yang dipilih.';
            } else if (filtered.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = `-- Tidak ada produk BSJ / POS untuk ${outletTypeLabel} --`;
                selectBarang.appendChild(opt);
                if (badgeCount) badgeCount.textContent = '0 item';
                if (filterHint) {
                    filterHint.innerHTML = `<i class="bi bi-exclamation-triangle text-warning me-1"></i>Belum ada barang setengah jadi dengan tagging divisi ${outletTypeLabel} atau barang jadi POS ${outletTypeLabel}.`;
                }
                if (suffix) suffix.textContent = '/ unit';
            } else {
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = `-- Ketik nama atau kode produk (${filtered.length} item ${outletTypeLabel}) --`;
                selectBarang.appendChild(defaultOpt);

                filtered.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.dataset.stdPrice = item.std_price;
                    opt.dataset.satuan = item.satuan || 'unit';

                    let tagLabel = '';
                    if (customerName.includes('kejingga')) {
                        if (item.is_kejingga_bsj) {
                            const divStr = item.kejingga_divisis && item.kejingga_divisis.length ? ' - ' + item.kejingga_divisis.join(', ') : '';
                            tagLabel = ` [BSJ Divisi Kejingga${divStr}]`;
                        } else if (item.is_kejingga_pos) {
                            tagLabel = ' [POS Kejingga]';
                        }
                    } else if (customerName.includes('gaharu')) {
                        if (item.is_gaharu_bsj) {
                            const divStr = item.gaharu_divisis && item.gaharu_divisis.length ? ' - ' + item.gaharu_divisis.join(', ') : '';
                            tagLabel = ` [BSJ Divisi Gaharu${divStr}]`;
                        } else if (item.is_gaharu_pos) {
                            tagLabel = ' [POS Gaharu]';
                        }
                    } else {
                        tagLabel = item.is_bahan_setengah_jadi ? ' [BSJ]' : (item.tipe_penjualan ? ` [${item.tipe_penjualan}]` : '');
                    }

                    const satStr = item.satuan ? ` / ${item.satuan}` : '';
                    opt.textContent = `${item.nama} [${item.kode_barang}]${tagLabel} - Std: Rp ${item.formatted_price}${satStr}`;
                    selectBarang.appendChild(opt);
                });

                if (badgeCount) badgeCount.textContent = `${filtered.length} item`;
                if (filterHint) {
                    filterHint.innerHTML = `<i class="bi bi-check-circle text-success me-1"></i>Menampilkan <strong>${filtered.length} produk</strong> untuk <strong>${outletTypeLabel}</strong> (BSJ tagging divisi &amp; Barang Jadi POS).`;
                }
            }

            // Trigger Select2 refresh
            if (typeof $ !== 'undefined' && $(selectBarang).data('select2')) {
                $(selectBarang).val('').trigger('change');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup Select2 pada modal tambah
            if (typeof $ !== 'undefined') {
                $('#modalTambahHargaB2b').on('shown.bs.modal', function () {
                    $('#selectCustomerB2b').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#modalTambahHargaB2b'),
                        width: '100%',
                        placeholder: '-- Ketik / Pilih Outlet Pemesan --',
                        allowClear: true
                    });

                    $('#selectBarangB2b').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#modalTambahHargaB2b'),
                        width: '100%',
                        placeholder: '-- Ketik nama atau kode produk --',
                        allowClear: true
                    });

                    const currentCust = $('#selectCustomerB2b').val();
                    filterBarangByCustomer(currentCust);
                });

                $('#selectCustomerB2b').on('change', function() {
                    const custId = $(this).val();
                    filterBarangByCustomer(custId);
                });

                $('#selectBarangB2b').on('change', function() {
                    const barangId = $(this).val();
                    const inputHarga = document.getElementById('inputHargaB2b');
                    const suffix = document.getElementById('b2bSatuanSuffix');
                    const help = document.getElementById('b2bSatuanHelp');

                    const item = allBarangData.find(b => String(b.id) === String(barangId));
                    if (item) {
                        const sat = item.satuan || 'unit';
                        if (suffix) suffix.textContent = `/ ${sat}`;
                        if (help) help.innerHTML = `Tarif harga jual khusus per <strong>${sat}</strong> untuk outlet pemesan yang dipilih (Satuan Master Barang).`;
                        if (inputHarga && (!inputHarga.value || inputHarga.value == '0')) {
                            if (item.std_price > 0) {
                                inputHarga.value = item.std_price;
                            }
                        }
                    } else {
                        if (suffix) suffix.textContent = '/ unit';
                        if (help) help.textContent = 'Harga jual per unit/satuan master barang khusus untuk outlet pemesan yang dipilih.';
                    }
                });
            }

            // Detail HPP Pop-up Handler
            const modalHppEl = document.getElementById('modalDetailHppResep');
            const modalHpp = modalHppEl ? new bootstrap.Modal(modalHppEl) : null;

            document.querySelectorAll('.btn-show-hpp-detail').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const barangId = this.dataset.barangId;
                    const barangNama = this.dataset.barangNama || 'Produk';

                    if (!barangId) return;

                    // Reset modal content state
                    document.getElementById('modalHppTitle').textContent = `Rincian HPP: ${barangNama}`;
                    document.getElementById('modalHppSubtitle').textContent = 'Memuat formulasi & harga referensi...';
                    document.getElementById('modalHppLoading').style.display = 'block';
                    document.getElementById('modalHppContent').style.display = 'none';
                    document.getElementById('modalHppNoResepAlert').style.display = 'none';

                    if (modalHpp) modalHpp.show();

                    // Fetch detail from backend
                    fetch(`{{ url('/harga-b2b/hpp-detail') }}/${barangId}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Gagal memuat rincian HPP');
                            return response.json();
                        })
                        .then(data => {
                            document.getElementById('modalHppLoading').style.display = 'none';
                            document.getElementById('modalHppContent').style.display = 'block';

                            document.getElementById('modalHppTitle').textContent = `${data.nama_produk} [${data.kode_barang}]`;
                            document.getElementById('modalHppSubtitle').textContent = data.has_resep 
                                ? `Formula BOM 1 Batch: ${data.output_qty} ${data.satuan_output}`
                                : `Harga Referensi Master Barang`;

                            document.getElementById('modalHppOutputBatch').textContent = `${data.output_qty} ${data.satuan_output}`;
                            document.getElementById('modalHppTotalBatch').textContent = `Rp ${data.formatted_hpp_batch}`;
                            document.getElementById('modalHppPerUnit').textContent = `Rp ${data.formatted_per_unit} / ${data.satuan}`;

                            document.getElementById('modalHppBbbSummary').textContent = `Rp ${data.formatted_bbb}`;
                            document.getElementById('modalHppBtklSummary').textContent = `Rp ${data.formatted_btkl}`;
                            document.getElementById('modalHppBopSummary').textContent = `Rp ${data.formatted_bop}`;

                            const tbody = document.getElementById('modalHppTableBody');
                            tbody.innerHTML = '';

                            let sumBbbPerUnit = 0;

                            if (!data.has_resep || !data.ingredients || data.ingredients.length === 0) {
                                document.getElementById('modalHppNoResepAlert').style.display = 'flex';
                                tbody.innerHTML = `
                                    <tr>
                                        <td class="text-center">1</td>
                                        <td>
                                            <span class="fw-bold">${data.nama_produk}</span>
                                            <div class="text-muted small">${data.kode_barang}</div>
                                        </td>
                                        <td class="text-end">1 ${data.satuan}</td>
                                        <td class="text-end">1 ${data.satuan}</td>
                                        <td class="text-end">Rp ${data.formatted_hpp}</td>
                                        <td class="text-end fw-bold">Rp ${data.formatted_hpp}</td>
                                    </tr>
                                `;
                                document.getElementById('modalHppTotalBbbPerUnit').textContent = `Rp ${data.formatted_hpp} / ${data.satuan}`;
                            } else {
                                let topNo = 0;
                                data.ingredients.forEach((ing, idx) => {
                                    sumBbbPerUnit += (ing.subtotal_per_unit || 0);

                                    let badgeType = '';
                                    if (ing.is_bsj) {
                                        badgeType = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 10px;">BSJ</span>`;
                                    } else if (ing.is_pos) {
                                        const posLabel = ing.tipe_penjualan ? `POS ${ing.tipe_penjualan}` : 'POS';
                                        badgeType = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size: 10px;">${posLabel}</span>`;
                                    }

                                    const tr = document.createElement('tr');
                                    const lvl = ing.level || 0;
                                    const indent = lvl * 20;
                                    const arrow = lvl > 0 ? '<i class="bi bi-arrow-return-right text-muted me-1"></i>' : '';
                                    const parentNote = ing.is_parent
                                        ? `<div class="text-info" style="font-size: 11px;"><i class="bi bi-diagram-3 me-1"></i>Resep turunan (1 batch = ${ing.sub_output}) — rincian di bawah</div>`
                                        : '';
                                    if (ing.is_parent) tr.classList.add('table-info');
                                    tr.innerHTML = `
                                        <td class="text-center text-muted">${lvl === 0 ? (++topNo) : ''}</td>
                                        <td style="padding-left: ${8 + indent}px;">
                                            <div class="fw-semibold text-dark">${arrow}${ing.nama_bahan} ${badgeType}</div>
                                            <div class="text-muted font-monospace" style="font-size: 11px;">${ing.kode_bahan || ''}</div>
                                            ${parentNote}
                                        </td>
                                        <td class="text-end">${Number(ing.qty_batch).toLocaleString('id-ID', {maximumFractionDigits: 4})} <span class="text-muted">${ing.satuan}</span></td>
                                        <td class="text-end fw-semibold text-dark">${Number(ing.qty_per_unit).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 4})} <span class="text-muted">${ing.satuan}</span></td>
                                        <td class="text-end text-muted">Rp ${ing.formatted_harga} <span style="font-size: 10px;">/${ing.satuan}</span></td>
                                        <td class="text-end ${ing.is_parent ? 'fst-italic text-info' : 'fw-bold text-dark'}">Rp ${ing.formatted_per_unit}${ing.is_parent ? '<div style="font-size:10px;">(subtotal)</div>' : ''}</td>
                                    `;
                                    tbody.appendChild(tr);
                                });

                                const bbbUnit = Math.round(data.total_bbb / (data.output_qty || 1));
                                document.getElementById('modalHppTotalBbbPerUnit').textContent = `Rp ${bbbUnit.toLocaleString('id-ID')} / ${data.satuan}`;
                            }
                        })
                        .catch(err => {
                            document.getElementById('modalHppLoading').style.display = 'none';
                            document.getElementById('modalHppContent').style.display = 'block';
                            document.getElementById('modalHppTableBody').innerHTML = `
                                <tr>
                                    <td colspan="6" class="text-center text-danger py-4">
                                        <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-1"></i>
                                        Gagal mengambil rincian bahan: ${err.message}
                                    </td>
                                </tr>
                            `;
                        });
                });
            });
        });
    </script>
</x-app-layout>

