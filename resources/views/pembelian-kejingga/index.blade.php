<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <x-slot name="header">Pembelian Kejingga (Luar Gaharu)</x-slot>

    <style>
        .table-responsive {
            overflow: visible !important;
        }
        .ts-dropdown {
            z-index: 99999 !important;
        }

        /* Multi-modal z-index stacking so submodals (catat bayar, lunasi, upload, terima) open in front of modalDetail */
        #modalPembayaranDetail, 
        #modalLunasiDetail, 
        #modalUploadBuktiDetail, 
        #modalTerimaDetail {
            z-index: 1080 !important;
        }
        .modal-backdrop.show:nth-of-type(2) {
            z-index: 1070 !important;
        }

        /* ===== MOBILE RESPONSIVE STYLING FOR TABLE & MODAL ===== */
        @media (max-width: 767.98px) {
            .mobile-responsive-table thead {
                display: none;
            }
            .mobile-responsive-table tbody tr {
                display: block;
                background: #ffffff;
                border: 1px solid #cbd5e1 !important;
                border-radius: 12px;
                padding: 12px;
                margin-bottom: 15px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.04);
            }
            .mobile-responsive-table tbody td {
                display: block;
                width: 100% !important;
                border: none !important;
                padding: 5px 0 !important;
                text-align: left !important;
            }
            .mobile-responsive-table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                font-size: 0.75rem;
                text-transform: uppercase;
                color: #64748b;
                display: block;
                margin-bottom: 2px;
            }
            .mobile-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 5px;
            }
        }
    </style>

    @php
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
    @endphp

    <div class="container-fluid px-2 px-md-4 py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="m-0 fw-bold text-dark fs-5 fs-md-4">Data Pembelian Kejingga (Luar Gaharu)</h4>
                <p class="text-muted small mb-0">Kelola draft permintaan, supplier per barang, serta pembayaran &amp; penerimaan stok per item.</p>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold d-none d-sm-inline-block">Khusus Gudang KeJingga</span>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i> {{ session('error') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="{{ route('pembelian-kejingga.create') }}" class="btn btn-primary mb-0">
                <i class="bi bi-plus-circle me-1"></i> Tambah Pembelian Kejingga
            </a>

            <form action="{{ route('pembelian-kejingga.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kode/supplier/barang..." value="{{ request('search') }}" style="width: 220px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mobile-responsive-table" style="font-size:13px;">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Ringkasan Supplier / Items</th>
                        <th>Gudang</th>
                        <th class="text-end">Total PO</th>
                        <th class="text-center">Status Pembayaran</th>
                        <th class="text-center">Penerimaan Barang</th>
                        <th class="text-center" style="min-width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembelian as $item)
                        @php
                            $total = (float) $item->total;
                            $details = $item->details;
                            
                            $suppliersInPo = $details->map(fn($d) => $d->supplier->nama ?? null)->filter()->unique();
                            $supplierDisplay = match(true) {
                                $suppliersInPo->count() === 1 => $suppliersInPo->first(),
                                $suppliersInPo->count() > 1   => 'Multi Supplier (' . $suppliersInPo->count() . ')',
                                default                       => 'Draft Permintaan Staff',
                            };

                            $totalReceived = $details->sum('qty_diterima');
                            $totalOrdered = $details->sum('qty');
                            $isFullyReceived = $totalReceived >= $totalOrdered && $totalOrdered > 0;
                            $isPartiallyReceived = $totalReceived > 0 && !$isFullyReceived;

                            $allPaid = $details->where('is_lunas', true)->count() === $details->count() && $details->count() > 0;
                        @endphp
                        <tr>
                            <td data-label="Kode Transaksi" class="font-monospace fw-bold" style="font-size:12px;">{{ $item->kode_pembelian }}</td>
                            <td data-label="Tanggal">{{ \Carbon\Carbon::parse($item->tanggal)->format('d M Y') }}</td>
                            <td data-label="Supplier / Items">
                                @if($suppliersInPo->count() > 1)
                                    <span class="badge bg-info text-white me-1">Multi Supplier</span>
                                @elseif($suppliersInPo->count() === 1)
                                    <span class="fw-semibold text-dark">{{ $suppliersInPo->first() }}</span>
                                @else
                                    <span class="badge bg-secondary font-normal" style="font-size:11px;">Draft Permintaan Staff</span>
                                @endif
                                <div class="text-muted small" style="font-size: 11px;">{{ $details->count() }} jenis barang</div>
                            </td>
                            <td data-label="Gudang"><span class="badge bg-warning text-dark">{{ $item->gudang->nama ?? 'Gudang KeJingga' }}</span></td>

                            {{-- TOTAL PO --}}
                            <td data-label="Total PO" class="text-end fw-semibold">
                                @if($item->total > 0)
                                    Rp {{ number_format($item->total, 0, ',', '.') }}
                                @else
                                    <span class="text-muted" style="font-size:11px;">— (Draft)</span>
                                @endif
                            </td>

                            {{-- STATUS PEMBAYARAN --}}
                            <td data-label="Status Pembayaran" class="text-center">
                                @if($allPaid)
                                    <span class="badge bg-success">✓ Lunas Semua Item</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:11px;" onclick="bukaModalDetail({{ $item->id }})">
                                        Cek Status Item ({{ $details->where('is_lunas', true)->count() }}/{{ $details->count() }})
                                    </button>
                                @endif
                            </td>

                            {{-- BARANG DITERIMA --}}
                            <td data-label="Barang Diterima" class="text-center">
                                @if($isFullyReceived)
                                    <span class="badge bg-success">✓ Diterima Lengkap</span>
                                @elseif($isPartiallyReceived)
                                    <span class="badge bg-info text-white">Parsial ({{ number_format($totalReceived, 0) }}/{{ number_format($totalOrdered, 0) }})</span>
                                @else
                                    <span class="badge bg-light text-muted border">Belum Diterima</span>
                                @endif
                            </td>

                            {{-- AKSI --}}
                            <td data-label="Aksi" class="text-center" style="white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-center gap-1 mobile-actions">
                                    {{-- Detail Pop-up Modal --}}
                                    <button type="button"
                                            class="btn btn-sm btn-info text-white rounded-2 px-2 py-1"
                                            onclick="bukaModalDetail({{ $item->id }})"
                                            title="Lihat Detail PO (Pop-up)">
                                        <i class="bi bi-eye"></i> Detail
                                    </button>

                                    {{-- Cetak PO (PDF) --}}
                                    <a href="{{ route('pembelian.cetak-pdf', $item->id) }}"
                                       class="btn btn-sm btn-danger text-white rounded-2 px-2 py-1"
                                       target="_blank" title="Cetak PO (PDF)">
                                        <i class="bi bi-printer"></i>
                                    </a>

                                    {{-- Download JPG --}}
                                    <button type="button"
                                            class="btn btn-sm btn-success text-white rounded-2 px-2 py-1"
                                            onclick="downloadJpgDirect({{ $item->id }})"
                                            title="Download JPG">
                                        <i class="bi bi-file-image"></i>
                                    </button>

                                    {{-- FLEKSIBEL EDIT & HAPUS POP-UP --}}
                                    @if(!$item->isTerkunci())
                                        {{-- Edit Pop-up Modal --}}
                                        <button type="button"
                                                class="btn btn-sm btn-warning text-dark rounded-2 px-2 py-1"
                                                onclick="bukaModalEdit({{ $item->id }})"
                                                title="Edit PO (Pop-up)">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>

                                        {{-- Hapus --}}
                                        <form action="{{ route('pembelian-kejingga.destroy', $item->id) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Yakin ingin menghapus {{ $item->kode_pembelian }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger rounded-2 px-2 py-1"
                                                    title="Hapus Pembelian / Draft">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada data pembelian Kejingga.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pembelian->hasPages())
            <div class="mt-3">
                {{ $pembelian->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL CATAT PEMBAYARAN ITEM (KHUSUS SUPER ADMIN) -->
    @if($isSuperAdmin)
    <div class="modal fade" id="modalPembayaranDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Catat Pembayaran Item Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formPembayaranDetail" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body pt-3">
                        <div id="warning_harga_nol_bayar" class="alert alert-warning border-warning align-items-center mb-3" style="display:none;">
                            <div>
                                <strong>⚠️ Supplier &amp; Harga Barang Belum Lengkap!</strong><br>
                                Nama Supplier dan/atau Harga Barang belum diisi oleh tim Purchasing. Harap lengkapi terlebih dahulu dengan mengedit PO sebelum mencatat pembayaran.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Nama Barang</label>
                            <input type="text" id="bayar_detail_barang_nama" class="form-control fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Total Harga Item (Rp)</label>
                            <input type="text" id="bayar_detail_total_harga" class="form-control font-monospace text-primary fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Metode Pembayaran</label>
                            <select name="metode_pembayaran" id="metode_pembayaran_select_detail" class="form-select" required onchange="toggleMetodePembayaranDetail(this.value)">
                                <option value="cod">COD (Bayar Lunas Saat Terima)</option>
                                <option value="dp">DP (Uang Muka)</option>
                                <option value="termin">Termin / Kredit</option>
                            </select>
                        </div>
                        <div id="section_dp_detail" class="mb-3" style="display:none;">
                            <label class="form-label small text-muted">Nominal DP (Rp)</label>
                            <input type="number" name="nominal_dp" id="nominal_dp_detail" class="form-control" placeholder="0">
                        </div>
                        <div id="section_termin_detail" class="mb-3" style="display:none;">
                            <label class="form-label small text-muted">Tanggal Jatuh Tempo</label>
                            <input type="date" name="tanggal_jatuh_tempo" id="tanggal_jatuh_tempo_detail" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-semibold">Upload Nota / Bukti Pembayaran (Opsional)</label>
                            <input type="file" name="bukti_pembayaran" class="form-control" accept="image/*,.pdf">
                            <small class="text-muted" style="font-size:11px;">Format: JPG, PNG, WEBP, PDF (Maks 5MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btn_submit_bayar" class="btn btn-primary btn-sm">Simpan Pembayaran Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL LUNASI ITEM (KHUSUS SUPER ADMIN) -->
    <div class="modal fade" id="modalLunasiDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Pelunasan Item Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formLunasiDetail" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body pt-3">
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-0">Barang</label>
                            <input type="text" id="lunasi_detail_barang_nama" class="form-control fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-0">Total Kekurangan</label>
                            <input type="text" id="lunasi_kekurangan_text" class="form-control fw-bold text-danger" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-semibold">Upload Nota / Bukti Pelunasan (Opsional)</label>
                            <input type="file" name="bukti_pembayaran" class="form-control" accept="image/*,.pdf">
                            <small class="text-muted" style="font-size:11px;">Format: JPG, PNG, WEBP, PDF (Maks 5MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm">Konfirmasi Pelunasan Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL UPLOAD BUKTI PEMBAYARAN ITEM (KHUSUS SUPER ADMIN) -->
    <div class="modal fade" id="modalUploadBuktiDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Upload Nota / Bukti Bayar Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formUploadBuktiDetail" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body pt-3">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Nama Barang</label>
                            <input type="text" id="upload_detail_barang_nama" class="form-control fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-dark fw-semibold">File Nota / Bukti Pembayaran <span class="text-danger">*</span></label>
                            <input type="file" name="bukti_pembayaran" class="form-control" accept="image/*,.pdf" required>
                            <small class="text-muted" style="font-size:11px;">Format: JPG, PNG, WEBP, PDF (Maks 5MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i> Simpan Nota</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TERIMA BARANG ITEM (KHUSUS SUPER ADMIN) -->
    <div class="modal fade" id="modalTerimaDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Terima Stok Item Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formTerimaDetail" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-0">Nama Barang</label>
                            <input type="text" id="terima_detail_barang_nama" class="form-control fw-bold" readonly>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-0">Qty Dipesan</label>
                                <input type="text" id="terima_detail_qty_pesan" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-0">Sudah Diterima</label>
                                <input type="text" id="terima_detail_qty_diterima" class="form-control bg-light" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-dark fw-bold mb-1">Input Qty Terima Saat Ini <span class="text-danger">*</span></label>
                            <input type="number" name="qty_diterima" id="terima_detail_input" class="form-control fw-bold text-primary" step="any" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Proses Terima Stok</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- MODAL DETAIL POP-UP -->
    <div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-file-earmark-text text-primary me-2"></i>Detail Purchase Order Kejingga
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 p-md-4" id="contentDetail">
                    {{-- Rendered dynamically --}}
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <div class="d-flex w-100 justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success btn-sm fw-semibold" id="btnDownloadJpgModal">
                                <i class="bi bi-file-image me-1"></i> Download JPG
                            </button>
                            <a href="#" id="btnCetakPdfModal" target="_blank" class="btn btn-danger btn-sm fw-semibold">
                                <i class="bi bi-printer me-1"></i> Cetak PDF
                            </a>
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold" id="btnEditFromDetailModal">
                                <i class="bi bi-pencil-square me-1"></i> Edit PO
                            </button>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT POP-UP -->
    <!-- MODAL EDIT POP-UP -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow rounded-4" style="max-height: 90vh;">
                <form id="formEditModal" method="POST" style="display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; max-height: 90vh;">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-bottom pb-3 d-flex align-items-center justify-content-between">
                        <h5 class="modal-title fw-bold text-dark m-0" id="modalEditTitle">
                            <i class="bi bi-pencil-square text-warning me-2"></i>Edit Purchase Order Kejingga
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-success btn-sm fw-bold px-3">
                                <i class="bi bi-check-circle-fill me-1"></i> Simpan
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body p-3 p-md-4" style="overflow-y: auto;">
                        <div class="row g-3 mb-4">
                            {{-- GUDANG --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">Gudang Tujuan Stok</label>
                                <input type="text" class="form-control bg-light fw-bold text-warning" value="{{ $gudangKejingga->nama ?? 'Gudang KeJingga' }}" readonly>
                                <input type="hidden" name="gudang_id" value="5">
                            </div>

                            {{-- TANGGAL --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark mb-1">Tanggal Transaksi <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold m-0 text-dark">Detail Items Barang &amp; Supplier</h6>
                                <small class="text-muted">Supplier dapat diubah per baris item</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btn-add-edit-row">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Baris Barang
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mobile-responsive-table" id="table-edit-items">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Barang &amp; Stok Gudang</th>
                                        <th width="200">Supplier / Pemasok</th>
                                        <th width="120">Qty Input</th>
                                        <th width="160">Pilihan Satuan</th>
                                        <th width="140">Total Qty (Utama)</th>
                                        <th width="150">Total Harga (Rp)</th>
                                        <th width="140">Harga / Satuan</th>
                                        <th width="60" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Dynamically populated --}}
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-5 offset-md-7">
                                <div class="card border-0 bg-light p-3" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <label class="form-label fw-bold text-secondary small mb-1">Biaya Tambahan (Tax / Service / Ongkir)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white fw-semibold text-muted">Rp</span>
                                        <input type="text" name="tax_service" id="edit_tax_service" class="form-control mask-number fw-bold text-end" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light rounded-bottom-4 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold px-4">
                            <i class="bi bi-check-circle-fill me-1"></i> Simpan Perubahan PO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- HIDDEN CONTAINER FOR DIRECT JPG GENERATION --}}
    <div id="jpg-render-hidden" style="position: fixed; left: -9999px; top: 0; width: 900px; background: #ffffff; padding: 25px; font-family: sans-serif; color: #1e293b;"></div>

    <script>
    const dataPembayaranMap = @json($dataPembayaran);
    const isSuperAdminUser = @json($isSuperAdmin);

    const barangsList = [
        @foreach($barangs as $b)
            {
                id: {{ $b->id }},
                kode: "{{ addslashes($b->kode_barang) }}",
                nama: "{{ addslashes($b->nama) }}",
                satuan_utama: "{{ addslashes($b->satuan) }}",
                satuan_pembelian: "{{ addslashes($b->satuan_pembelian ?? '') }}",
                konversi_pembelian: {{ (float)($b->konversi_pembelian ?? 1) }},
                stok_kejingga: {{ (float)($b->stok_kejingga ?? 0) }}
            },
        @endforeach
    ];

    const suppliersList = [
        @foreach($suppliers as $s)
            { id: {{ $s->id }}, nama: "{{ addslashes($s->nama) }}" },
        @endforeach
    ];

    const barangsMap = {};
    barangsList.forEach(b => barangsMap[b.id] = b);

    let editRowIndex = 0;

    function formatNumberDisplay(num) {
        if (!num && num !== 0) return '';
        const parts = num.toString().split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(',');
    }

    function unformatNumber(str) {
        if (!str) return 0;
        return parseFloat(str.toString().replace(/\./g, '').replace(',', '.')) || 0;
    }

    function maskInput(input) {
        input.addEventListener('input', function() {
            let cursor = this.selectionStart;
            let originalLen = this.value.length;
            let raw = this.value.replace(/[^0-9,]/g, '');
            let parts = raw.split(',');
            if (parts.length > 2) raw = parts[0] + ',' + parts.slice(1).join('');
            if (parts[0]) parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            this.value = parts.join(',');
            let newLen = this.value.length;
            this.setSelectionRange(cursor + (newLen - originalLen), cursor + (newLen - originalLen));
        });
    }

    document.querySelectorAll('.mask-number').forEach(maskInput);

    function initTomSelect(el) {
        if (el.tomselect) return;
        new TomSelect(el, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "-- Pilih / Cari Barang --",
            allowEmptyOption: true,
            dropdownParent: 'body',
            maxOptions: 500,
            onChange: function(val) {
                let tr = el.closest('tr');
                updateEditRowBarang(tr, val);
            }
        });
    }

    function updateEditRowBarang(tr, barangId) {
        const b = barangsMap[barangId];
        const badgeDiv = tr.querySelector('.stok-info-badge');
        const selectSatuan = tr.querySelector('.satuan-select');
        
        if (!b) {
            if (badgeDiv) badgeDiv.innerHTML = '';
            if (selectSatuan) selectSatuan.innerHTML = '<option value="">-- Pilih Satuan --</option>';
            tr.querySelector('.konversi-input').value = 1;
            calcEditRow(tr);
            return;
        }

        if (badgeDiv) {
            badgeDiv.innerHTML = `
                <span class="badge bg-warning text-dark border">
                    <i class="bi bi-box-seam me-1"></i>Stok Kejingga: 
                    <strong>${b.stok_kejingga.toLocaleString('id-ID')} ${b.satuan_utama}</strong>
                </span>
            `;
        }

        let opts = `<option value="${b.satuan_utama}" data-konversi="1">${b.satuan_utama} (Utama)</option>`;
        if (b.satuan_pembelian && b.konversi_pembelian > 1 && b.satuan_pembelian !== b.satuan_utama) {
            opts += `<option value="${b.satuan_pembelian}" data-konversi="${b.konversi_pembelian}">${b.satuan_pembelian} (1 ${b.satuan_pembelian} = ${b.konversi_pembelian.toLocaleString('id-ID')} ${b.satuan_utama})</option>`;
        }

        if (selectSatuan) {
            selectSatuan.innerHTML = opts;
            selectSatuan.selectedIndex = (b.satuan_pembelian && b.konversi_pembelian > 1) ? 1 : 0;
            let selectedOpt = selectSatuan.options[selectSatuan.selectedIndex];
            let konvVal = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-konversi')) || 1 : 1;
            tr.querySelector('.konversi-input').value = konvVal;
        }

        calcEditRow(tr);
    }

    function calcEditRow(tr) {
        const barangSelect = tr.querySelector('.barang-select');
        const barangId = barangSelect ? (barangSelect.tomselect ? barangSelect.tomselect.getValue() : barangSelect.value) : '';
        const b = barangsMap[barangId];

        const qtyInput = tr.querySelector('.qty-input');
        const qtyVal = unformatNumber(qtyInput ? qtyInput.value : 0);

        const selectSatuan = tr.querySelector('.satuan-select');
        const selectedOpt = selectSatuan && selectSatuan.selectedIndex >= 0 ? selectSatuan.options[selectSatuan.selectedIndex] : null;
        const konvVal = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-konversi')) || 1 : 1;
        tr.querySelector('.konversi-input').value = konvVal;

        const hargaInput = tr.querySelector('.harga-input');
        const hargaVal = unformatNumber(hargaInput ? hargaInput.value : 0);

        const totalQtyDisplay = tr.querySelector('.total-qty-display');
        const konversiInfoText = tr.querySelector('.konversi-info-text');
        const perQtyInput = tr.querySelector('.harga-per-qty');

        if (b && qtyVal > 0) {
            const totalMainQty = qtyVal * konvVal;
            const unitChosen = selectSatuan ? selectSatuan.value : b.satuan_utama;
            
            if (totalQtyDisplay) totalQtyDisplay.innerHTML = `${formatNumberDisplay(totalMainQty)} ${b.satuan_utama}`;

            if (konversiInfoText) {
                if (konvVal > 1) {
                    konversiInfoText.innerHTML = `(${formatNumberDisplay(qtyVal)} ${unitChosen} @ ${formatNumberDisplay(konvVal)} ${b.satuan_utama})`;
                } else {
                    konversiInfoText.innerHTML = `(${formatNumberDisplay(qtyVal)} ${b.satuan_utama})`;
                }
            }

            if (perQtyInput) {
                if (hargaVal > 0) {
                    let perQty = hargaVal / qtyVal;
                    perQtyInput.value = 'Rp ' + formatNumberDisplay(Math.round(perQty)) + ' / ' + unitChosen;
                } else {
                    perQtyInput.value = '—';
                }
            }
        } else {
            if (totalQtyDisplay) totalQtyDisplay.innerHTML = '—';
            if (konversiInfoText) konversiInfoText.innerHTML = '';
            if (perQtyInput) perQtyInput.value = '—';
        }
    }

    function addEditRow(d = null) {
        const tbody = document.querySelector('#table-edit-items tbody');
        const tr = document.createElement('tr');
        tr.className = 'item-row';

        let barangOpts = '<option value="">-- Pilih / Cari Barang --</option>';
        barangsList.forEach(b => {
            let sel = (d && d.barang_id == b.id) ? 'selected' : '';
            barangOpts += `<option value="${b.id}" ${sel}>${b.kode} - ${b.nama} (Stok Kejingga: ${b.stok_kejingga.toLocaleString('id-ID')} ${b.satuan_utama})</option>`;
        });

        let supplierOpts = '<option value="">-- Draft (Kosong) --</option>';
        suppliersList.forEach(s => {
            let sel = (d && d.supplier_id == s.id) ? 'selected' : '';
            supplierOpts += `<option value="${s.id}" ${sel}>${s.nama}</option>`;
        });

        tr.innerHTML = `
            <td data-label="Nama Barang & Stok Gudang">
                <select name="items[${editRowIndex}][barang_id]" class="form-control barang-select" required>
                    ${barangOpts}
                </select>
                <div class="stok-info-badge mt-1" style="font-size: 11px;"></div>
            </td>
            <td data-label="Supplier / Pemasok">
                <select name="items[${editRowIndex}][supplier_id]" class="form-select supplier-select">
                    ${supplierOpts}
                </select>
            </td>
            <td data-label="Qty Input">
                <input type="text" name="items[${editRowIndex}][qty]" class="form-control qty-input mask-number" value="${d ? formatNumberDisplay(d.qty) : ''}" placeholder="0" required>
            </td>
            <td data-label="Pilihan Satuan">
                <select name="items[${editRowIndex}][satuan_pembelian]" class="form-select satuan-select">
                    <option value="">-- Pilih Satuan --</option>
                </select>
                <input type="hidden" name="items[${editRowIndex}][konversi_pembelian]" class="konversi-input" value="${d ? d.konversi_pembelian : 1}">
            </td>
            <td data-label="Total Qty (Utama)">
                <div class="fw-bold text-dark total-qty-display">—</div>
                <small class="text-muted konversi-info-text d-block" style="font-size: 10px;"></small>
            </td>
            <td data-label="Total Harga (Rp)">
                <input type="text" name="items[${editRowIndex}][harga]" class="form-control harga-input mask-number" value="${d && d.harga > 0 ? formatNumberDisplay(d.harga) : ''}" placeholder="0 (Opsional)">
            </td>
            <td data-label="Harga / Satuan">
                <input type="text" class="form-control harga-per-qty bg-light" readonly tabindex="-1" placeholder="—">
            </td>
            <td data-label="Aksi" class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-edit" title="Hapus Baris"><i class="bi bi-trash"></i> Hapus</button>
            </td>
        `;

        tbody.appendChild(tr);
        tr.querySelectorAll('.mask-number').forEach(maskInput);

        const sel = tr.querySelector('.barang-select');
        initTomSelect(sel);

        if (d && d.barang_id) {
            updateEditRowBarang(tr, d.barang_id);
            const sSelect = tr.querySelector('.satuan-select');
            if (sSelect && d.satuan_pembelian) {
                for (let i = 0; i < sSelect.options.length; i++) {
                    if (sSelect.options[i].value === d.satuan_pembelian) {
                        sSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            calcEditRow(tr);
        }

        editRowIndex++;
    }

    document.getElementById('btn-add-edit-row') && document.getElementById('btn-add-edit-row').addEventListener('click', function() {
        addEditRow();
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-edit')) {
            let trs = document.querySelectorAll('#table-edit-items tbody tr');
            if (trs.length > 1) {
                e.target.closest('tr').remove();
            } else {
                alert('Minimal 1 baris barang pembelian.');
            }
        }
    });

    function toggleMetodePembayaranDetail(val) {
        document.getElementById('section_dp_detail').style.display = (val === 'dp') ? 'block' : 'none';
        document.getElementById('section_termin_detail').style.display = (val === 'termin') ? 'block' : 'none';
    }

    function bukaModalBayarDetail(detailId, barangNama, totalHarga, hasSupplier) {
        document.getElementById('bayar_detail_barang_nama').value = barangNama;
        document.getElementById('bayar_detail_total_harga').value = totalHarga > 0 ? 'Rp ' + totalHarga.toLocaleString('id-ID') : 'Rp 0 (Belum Diisi)';
        document.getElementById('formPembayaranDetail').action = `/pembelian-kejingga/detail/${detailId}/catat-pembayaran`;

        const warnBox = document.getElementById('warning_harga_nol_bayar');
        const submitBtn = document.getElementById('btn_submit_bayar');

        if (totalHarga <= 0 || !hasSupplier) {
            if (warnBox) warnBox.style.display = 'block';
            if (submitBtn) submitBtn.disabled = true;
        } else {
            if (warnBox) warnBox.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
        }

        new bootstrap.Modal(document.getElementById('modalPembayaranDetail')).show();
    }

    function bukaModalEditFromDetail(poId) {
        const detailModalEl = document.getElementById('modalDetail');
        const detailModalInst = bootstrap.Modal.getInstance(detailModalEl);
        if (detailModalInst) {
            detailModalInst.hide();
        }
        setTimeout(() => {
            bukaModalEdit(poId);
        }, 300);
    }

    function bukaModalUploadBukti(detailId, barangNama) {
        document.getElementById('upload_detail_barang_nama').value = barangNama;
        document.getElementById('formUploadBuktiDetail').action = `/pembelian-kejingga/detail/${detailId}/upload-bukti`;
        new bootstrap.Modal(document.getElementById('modalUploadBuktiDetail')).show();
    }

    function bukaModalLunasiDetail(detailId, barangNama, sisa) {
        document.getElementById('lunasi_detail_barang_nama').value = barangNama;
        document.getElementById('lunasi_kekurangan_text').value = 'Rp ' + sisa.toLocaleString('id-ID');
        document.getElementById('formLunasiDetail').action = `/pembelian-kejingga/detail/${detailId}/lunasi`;
        new bootstrap.Modal(document.getElementById('modalLunasiDetail')).show();
    }

    function bukaModalTerimaDetail(detailId, barangNama, qtyPesan, qtyDiterima, satuan) {
        document.getElementById('terima_detail_barang_nama').value = barangNama;
        document.getElementById('terima_detail_qty_pesan').value = qtyPesan + ' ' + satuan;
        document.getElementById('terima_detail_qty_diterima').value = qtyDiterima + ' ' + satuan;
        const sisa = qtyPesan - qtyDiterima;
        const inputEl = document.getElementById('terima_detail_input');
        inputEl.value = sisa > 0 ? sisa : 0;
        inputEl.max = sisa;
        document.getElementById('formTerimaDetail').action = `/pembelian-kejingga/detail/${detailId}/terima`;
        new bootstrap.Modal(document.getElementById('modalTerimaDetail')).show();
    }

    function generateJpgHtml(item) {
        let detailsHtml = '';
        item.details.forEach((d, idx) => {
            let konvText = d.has_konversi ? `<br><small style="color:#0284c7;">= ${(d.qty * d.konversi_pembelian).toLocaleString('id-ID')} ${d.satuan_utama}</small>` : '';
            detailsHtml += `
                <tr>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;">${idx + 1}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1;">
                        <strong>${d.nama}</strong> (${d.kode_barang})<br>
                        <small style="color:#475569;">Supplier: <strong>${d.supplier_nama}</strong></small>
                    </td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center; background:#f8fafc;">${d.stok_kejingga.toLocaleString('id-ID')} ${d.satuan_utama}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;"><strong>${d.qty.toLocaleString('id-ID')} ${d.satuan}</strong>${konvText}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;">${d.qty_diterima.toLocaleString('id-ID')} ${d.satuan}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:right;">${d.harga > 0 ? 'Rp ' + d.harga_per_qty.toLocaleString('id-ID') : '—'}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:right; font-weight:bold;">${d.harga > 0 ? 'Rp ' + d.harga.toLocaleString('id-ID') : '—'}</td>
                </tr>
            `;
        });

        return `
            <div style="border-bottom:2px solid #0f172a; padding-bottom:12px; margin-bottom:15px; display:flex; justify-content:space-between;">
                <div>
                    <h3 style="margin:0; font-weight:bold; color:#0f172a;">KEJINGGA</h3>
                    <div style="font-size:12px; color:#475569; margin-top:4px;">Pengadaan & Logistik - Gudang KeJingga</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:18px; font-weight:bold; color:#d97706;">PURCHASE ORDER</div>
                    <div style="font-family:monospace; font-weight:bold; font-size:14px;">#${item.kode}</div>
                    <div style="font-size:12px; color:#475569;">Tanggal: ${item.tanggal}</div>
                </div>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:15px;">
                <thead>
                    <tr style="background:#0f172a; color:#ffffff;">
                        <th style="padding:8px; border:1px solid #0f172a;" width="30">No</th>
                        <th style="padding:8px; border:1px solid #0f172a;">Barang &amp; Supplier</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="120">Stok Kejingga</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="110">Qty Order</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="100">Diterima</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="120">Harga / Satuan</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="120">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${detailsHtml}
                </tbody>
            </table>

            <div style="text-align:right; font-size:14px; font-weight:bold; color:#0f172a; margin-bottom:20px;">
                Total Purchase Order: <span style="color:#2563eb;">Rp ${item.total.toLocaleString('id-ID')}</span>
            </div>

            <div style="display:flex; justify-content:space-between; text-align:center; font-size:11px; margin-top:30px; border-top:1px solid #e2e8f0; padding-top:10px;">
                <div>Dibuat Oleh<br><br><br><strong>( ${item.user_nama} )</strong></div>
                <div>Disetujui<br><br><br><strong>( Tim Purchasing )</strong></div>
                <div>Gudang Penerima<br><br><br><strong>( Gudang Kejingga )</strong></div>
            </div>
        `;
    }

    function downloadJpgDirect(id) {
        const item = dataPembayaranMap[id];
        if (!item) return;

        const container = document.getElementById('jpg-render-hidden');
        container.innerHTML = generateJpgHtml(item);

        html2canvas(container, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false
        }).then(canvas => {
            let link = document.createElement('a');
            link.download = 'PO-Kejingga-' + item.kode + '.jpg';
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            link.click();
            container.innerHTML = '';
        }).catch(err => {
            alert('Gagal mengunduh JPG: ' + err.message);
            container.innerHTML = '';
        });
    }

    // ==========================================
    // BUKA MODAL DETAIL (POP-UP RESPONSIVE)
    // ==========================================
    function bukaModalDetail(id) {
        const item = dataPembayaranMap[id];
        if (!item) return;

        let detailsHtml = '';
        let totalItemsCalculated = 0;
        item.details.forEach((d, idx) => {
            let subtotal = d.harga;
            totalItemsCalculated += subtotal;
            let konvInfo = d.has_konversi ? `<div class="text-primary small" style="font-size:11px;">= ${(d.qty * d.konversi_pembelian).toLocaleString('id-ID')} ${d.satuan_utama}</div>` : '';
            
            // Per item Payment status UI
            let bayarBadge = `<span class="badge bg-secondary">Belum Catat</span>`;
            if (d.metode_pembayaran) {
                if (d.is_lunas) {
                    bayarBadge = `<span class="badge bg-success">✓ Lunas (${d.label_pembayaran})</span>`;
                } else if (d.kekurangan > 0) {
                    bayarBadge = `<span class="badge bg-warning text-dark">${d.label_pembayaran} (Sisa: Rp ${d.kekurangan.toLocaleString('id-ID')})</span>`;
                } else {
                    bayarBadge = `<span class="badge bg-info text-white">${d.label_pembayaran}</span>`;
                }
            }

            let bayarActionBtn = '';
            if (isSuperAdminUser) {
                const needsHarga = d.harga <= 0;
                const needsSupplier = !d.supplier_id;

                if (needsHarga && needsSupplier) {
                    bayarActionBtn = `<button type="button" class="btn btn-xs btn-warning text-dark mt-1 py-1 px-2 fw-bold" style="font-size:10px;" onclick="bukaModalEditFromDetail(${item.id})" title="Klik untuk mengedit PO dan memasukkan harga & supplier"><i class="bi bi-pencil-square me-1"></i> Masukkan Harga & Supplier</button>`;
                } else if (needsHarga) {
                    bayarActionBtn = `<button type="button" class="btn btn-xs btn-warning text-dark mt-1 py-1 px-2 fw-bold" style="font-size:10px;" onclick="bukaModalEditFromDetail(${item.id})" title="Klik untuk mengedit PO dan memasukkan harga"><i class="bi bi-tag-fill me-1"></i> Masukkan Harga</button>`;
                } else if (needsSupplier) {
                    bayarActionBtn = `<button type="button" class="btn btn-xs btn-warning text-dark mt-1 py-1 px-2 fw-bold" style="font-size:10px;" onclick="bukaModalEditFromDetail(${item.id})" title="Klik untuk mengedit PO dan memilih supplier"><i class="bi bi-person-plus-fill me-1"></i> Pilih Supplier</button>`;
                } else if (!d.metode_pembayaran) {
                    bayarActionBtn = `<button type="button" class="btn btn-xs btn-outline-primary mt-1 py-1 px-2" style="font-size:10px;" onclick="bukaModalBayarDetail(${d.id}, '${addslashes(d.nama)}', ${d.harga}, true)">+ Catat Bayar</button>`;
                } else if (!d.is_lunas && d.kekurangan > 0) {
                    bayarActionBtn = `<button type="button" class="btn btn-xs btn-warning text-dark mt-1 py-1 px-2" style="font-size:10px;" onclick="bukaModalLunasiDetail(${d.id}, '${addslashes(d.nama)}', ${d.kekurangan})">Lunasi</button>`;
                }
            }

            // Nota / Bukti Pembayaran UI
            let notaColumn = '';
            if (d.bukti_pembayaran_url) {
                notaColumn = `
                    <a href="${d.bukti_pembayaran_url}" target="_blank" class="btn btn-xs btn-outline-success py-1 px-2 text-decoration-none" style="font-size:10px;" title="Lihat Nota / Bukti Bayar">
                        <i class="bi bi-file-earmark-check-fill me-1"></i> Lihat Nota
                    </a>
                `;
                if (isSuperAdminUser) {
                    notaColumn += `<br><button type="button" class="btn btn-xs btn-link text-muted p-0 mt-1" style="font-size:9px;" onclick="bukaModalUploadBukti(${d.id}, '${addslashes(d.nama)}')">Ganti Nota</button>`;
                }
            } else {
                if (isSuperAdminUser) {
                    notaColumn = `
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:10px;" onclick="bukaModalUploadBukti(${d.id}, '${addslashes(d.nama)}')">
                            <i class="bi bi-upload me-1"></i> Upload Nota
                        </button>
                    `;
                } else {
                    notaColumn = `<span class="text-muted small" style="font-size:10px;">— Belum ada</span>`;
                }
            }

            // Per item Reception status UI
            let terimaBadge = `<span class="badge bg-light text-muted border">Belum Diterima</span>`;
            if (d.is_diterima_item) {
                terimaBadge = `<span class="badge bg-success">✓ Diterima (${d.qty_diterima}/${d.qty})</span>`;
            } else if (d.qty_diterima > 0) {
                terimaBadge = `<span class="badge bg-info text-white">Parsial (${d.qty_diterima}/${d.qty})</span>`;
            }

            let terimaActionBtn = '';
            if (isSuperAdminUser && !d.is_diterima_item) {
                if (!d.metode_pembayaran) {
                    terimaActionBtn = `<small class="text-muted d-block mt-1" style="font-size:9px;">(Catat bayar dulu)</small>`;
                } else {
                    terimaActionBtn = `<button type="button" class="btn btn-xs btn-info text-white mt-1 py-1 px-2" style="font-size:10px;" onclick="bukaModalTerimaDetail(${d.id}, '${addslashes(d.nama)}', ${d.qty}, ${d.qty_diterima}, '${d.satuan}')">Terima Stok</button>`;
                }
            }

            detailsHtml += `
                <tr>
                    <td class="text-center">${idx + 1}</td>
                    <td>
                        <div class="fw-bold text-dark">${d.nama}</div>
                        <div class="font-monospace text-muted small" style="font-size:11px;">${d.kode_barang}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">${d.supplier_nama}</span>
                    </td>
                    <td class="text-center bg-light fw-semibold">${d.stok_kejingga.toLocaleString('id-ID')} ${d.satuan_utama}</td>
                    <td class="text-center fw-bold">
                        ${d.qty.toLocaleString('id-ID')} ${d.satuan}
                        ${konvInfo}
                    </td>
                    <td class="text-end">
                        ${d.harga > 0 ? 'Rp ' + d.harga_per_qty.toLocaleString('id-ID') : '—'}
                    </td>
                    <td class="text-end fw-bold">
                        ${d.harga > 0 ? 'Rp ' + subtotal.toLocaleString('id-ID') : '—'}
                    </td>
                    <td class="text-center align-middle">
                        ${bayarBadge}
                        ${bayarActionBtn}
                    </td>
                    <td class="text-center align-middle">
                        ${notaColumn}
                    </td>
                    <td class="text-center align-middle">
                        ${terimaBadge}
                        ${terimaActionBtn}
                    </td>
                </tr>
            `;
        });

        let html = `
            <div id="po-modal-doc-render" class="p-2 p-md-3 bg-white">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">KEJINGGA</h4>
                        <div class="text-muted small">
                            Pengadaan &amp; Logistik Operasional Kejingga<br>
                            <strong>Gudang:</strong> ${item.gudang_nama}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold mb-2">PURCHASE ORDER</span>
                        <div class="font-monospace fw-bold text-dark fs-5">#${item.kode}</div>
                        <div class="text-muted small">Tanggal: <strong>${item.tanggal}</strong></div>
                    </div>
                </div>

                <div class="table-responsive mb-3" style="overflow-x: auto;">
                    <table class="table table-bordered align-middle mb-0" style="min-width: 1150px; font-size: 12px;">
                        <thead class="table-dark">
                            <tr>
                                <th width="35" class="text-center">No</th>
                                <th style="min-width: 160px;">Nama Barang &amp; Kode</th>
                                <th style="min-width: 140px;">Supplier</th>
                                <th style="min-width: 100px;" class="text-center">Stok Kejingga</th>
                                <th style="min-width: 110px;" class="text-center">Qty Dipesan</th>
                                <th style="min-width: 110px;" class="text-end">Harga / Satuan</th>
                                <th style="min-width: 120px;" class="text-end">Subtotal</th>
                                <th style="min-width: 160px;" class="text-center">Pembayaran</th>
                                <th style="min-width: 110px;" class="text-center">Nota / Bukti</th>
                                <th style="min-width: 125px;" class="text-center">Penerimaan Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${detailsHtml}
                        </tbody>
                    </table>
                </div>

                <div class="row mb-3">
                    <div class="col-12 col-md-6 offset-md-6">
                        <table class="table table-borderless table-sm text-end mb-0">
                            ${item.tax_service > 0 ? `
                                <tr>
                                    <td class="text-muted">Subtotal Items:</td>
                                    <td class="fw-bold">Rp ${(item.total - item.tax_service).toLocaleString('id-ID')}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tax / Service / Ongkir:</td>
                                    <td class="fw-bold">Rp ${item.tax_service.toLocaleString('id-ID')}</td>
                                </tr>
                            ` : ''}
                            <tr class="border-top">
                                <td class="fs-5 fw-bold text-dark">Total Purchase Order:</td>
                                <td class="fs-5 fw-bold text-primary">Rp ${item.total.toLocaleString('id-ID')}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row text-center mt-4 pt-3 border-top" style="font-size: 11px;">
                    <div class="col-4">
                        <div class="text-muted">Dibuat Oleh:</div>
                        <div style="height: 40px;"></div>
                        <div class="fw-bold text-dark">(${item.user_nama})</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted">Disetujui Purchasing:</div>
                        <div style="height: 40px;"></div>
                        <div class="fw-bold text-dark">( Tim Purchasing )</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted">Gudang Penerima:</div>
                        <div style="height: 40px;"></div>
                        <div class="fw-bold text-dark">( Gudang Kejingga )</div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('contentDetail').innerHTML = html;

        document.getElementById('btnDownloadJpgModal').onclick = function() {
            downloadJpgDirect(id);
        };

        document.getElementById('btnCetakPdfModal').href = `/pembelian/${id}/cetak-pdf`;

        const btnEditDetail = document.getElementById('btnEditFromDetailModal');
        if (item.is_terkunci) {
            btnEditDetail.style.display = 'none';
        } else {
            btnEditDetail.style.display = 'inline-block';
            btnEditDetail.onclick = function() {
                const modalDetailEl = document.getElementById('modalDetail');
                const modalDetailInstance = bootstrap.Modal.getInstance(modalDetailEl);
                if (modalDetailInstance) modalDetailInstance.hide();
                setTimeout(() => bukaModalEdit(id), 300);
            };
        }

        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // Helper addslashes for JS strings
    function addslashes(str) {
        return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
    }

    // ==========================================
    // BUKA MODAL EDIT (POP-UP RESPONSIVE)
    // ==========================================
    function bukaModalEdit(id) {
        const item = dataPembayaranMap[id];
        if (!item) return;

        if (item.is_terkunci) {
            alert('Purchase Order ini sudah dikunci (dibayar atau diterima) dan tidak dapat diubah.');
            return;
        }

        document.getElementById('modalEditTitle').innerHTML = `<i class="bi bi-pencil-square text-warning me-2"></i>Edit Purchase Order (${item.kode})`;
        document.getElementById('formEditModal').action = `/pembelian-kejingga/${id}`;

        document.getElementById('edit_tanggal').value = item.tanggal_raw;
        document.getElementById('edit_tax_service').value = item.tax_service > 0 ? formatNumberDisplay(item.tax_service) : '';

        const tbody = document.querySelector('#table-edit-items tbody');
        tbody.innerHTML = '';
        editRowIndex = 0;

        if (item.details && item.details.length > 0) {
            item.details.forEach(d => {
                addEditRow(d);
            });
        } else {
            addEditRow();
        }

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }
    </script>
</x-app-layout>
