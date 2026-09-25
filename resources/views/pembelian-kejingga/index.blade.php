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

        /* Multi-modal z-index stacking so submodals open in front of modalDetail */
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
            <a href="{{ route('pembelian-kejingga.create') }}" class="btn text-white mb-0 shadow-sm fw-semibold d-inline-flex align-items-center gap-1" style="background-color: #DE8958; border-radius: 8px;">
                <i class="bi bi-plus-lg"></i> Tambah Pembelian Kejingga
            </a>
        </div>

        {{-- FILTER BAR --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="border: 1px solid #DCD3CB !important;">
            <div class="card-body py-3">
                <form action="{{ route('pembelian-kejingga.index') }}" method="GET" id="form-filter-pembelian">
                    <div class="row g-2 align-items-end">

                        {{-- CARI --}}
                        <div class="col-lg-3 col-md-4">
                            <label class="form-label fw-semibold small mb-1">Cari Kode / Keterangan</label>
                            <input type="text" name="search" class="form-control form-control-sm"
                                   placeholder="Cari kode, supplier, keterangan..."
                                   value="{{ request('search') }}">
                        </div>

                        {{-- URUTAN --}}
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label fw-semibold small mb-1">Urutan</label>
                            <select name="sort" class="form-select form-select-sm">
                                <option value="terbaru" {{ request('sort', 'terbaru') === 'terbaru' ? 'selected' : '' }}>Terbaru</option>
                                <option value="terlama" {{ request('sort') === 'terlama' ? 'selected' : '' }}>Terlama</option>
                            </select>
                        </div>

                        {{-- STATUS PEMBAYARAN --}}
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label fw-semibold small mb-1">Status Pembayaran</label>
                            <select name="status_pembayaran" class="form-select form-select-sm">
                                <option value="">-- Semua Status --</option>
                                <option value="belum_dicatat" {{ request('status_pembayaran') === 'belum_dicatat' ? 'selected' : '' }}>Belum Dicatat</option>
                                <option value="cod"           {{ request('status_pembayaran') === 'cod'           ? 'selected' : '' }}>COD</option>
                                <option value="belum_lunas"   {{ request('status_pembayaran') === 'belum_lunas'   ? 'selected' : '' }}>Belum Lunas</option>
                                <option value="lunas"         {{ request('status_pembayaran') === 'lunas'         ? 'selected' : '' }}>Lunas</option>
                            </select>
                        </div>

                        {{-- STATUS PENERIMAAN --}}
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label fw-semibold small mb-1">Status Penerimaan</label>
                            <select name="status_penerimaan" class="form-select form-select-sm">
                                <option value="">-- Semua Status --</option>
                                <option value="belum_diterima" {{ request('status_penerimaan') === 'belum_diterima' ? 'selected' : '' }}>Belum Diterima</option>
                                <option value="diterima"       {{ request('status_penerimaan') === 'diterima'       ? 'selected' : '' }}>Sudah Diterima</option>
                            </select>
                        </div>

                        {{-- FILTER TANGGAL --}}
                        <div class="col-lg-2 col-md-3 position-relative">
                            <label class="form-label fw-semibold small mb-1">Filter Tanggal</label>
                            <input type="hidden" name="dari"   id="pb_filter_dari"   value="{{ request('dari') }}">
                            <input type="hidden" name="sampai" id="pb_filter_sampai" value="{{ request('sampai') }}">

                            <button type="button" class="btn btn-sm btn-outline-secondary bg-white text-dark w-100 d-flex align-items-center justify-content-between py-1 px-2 rounded-3 shadow-none border"
                                    id="pb-btn-date-trigger" style="min-height: 31px;">
                                <span id="pb-date-range-label" class="small text-truncate">
                                    <i class="bi bi-calendar3 me-1 text-primary"></i>
                                    <span id="pb-text-date-display">Semua Tanggal</span>
                                </span>
                                <i class="bi bi-chevron-down small text-muted ms-1"></i>
                            </button>

                            {{-- POPOVER DATE RANGE --}}
                            <div id="pb-date-range-popover" class="card border-0 shadow-lg rounded-4 p-3 position-absolute"
                                 style="display:none; z-index:1060; width:680px; max-width:90vw; top:105%; right:0; background:#fff; border:1px solid #e2e8f0 !important;">
                                <div class="d-flex gap-3">
                                    {{-- Presets --}}
                                    <div class="d-flex flex-column gap-1 flex-shrink-0" style="width:130px;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="today">Hari Ini</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="yesterday">Kemarin</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="this_week">Minggu Ini</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="last_week">Minggu Lalu</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="this_month">Bulan Ini</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="last_month">Bulan Lalu</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="this_year">Tahun Ini</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium pb-btn-preset py-1 px-2" style="font-size:.78rem;" data-preset="last_year">Tahun Lalu</button>
                                    </div>

                                    {{-- Calendar --}}
                                    <div class="flex-grow-1 px-2 border-start border-end">
                                        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                            <button type="button" class="btn btn-xs btn-light border rounded-circle p-1" id="pb-cal-prev" title="Bulan Sebelumnya">
                                                <i class="bi bi-chevron-left"></i>
                                            </button>
                                            <div class="fw-bold text-dark font-monospace" id="pb-cal-title" style="font-size:.9rem;"></div>
                                            <button type="button" class="btn btn-xs btn-light border rounded-circle p-1" id="pb-cal-next" title="Bulan Selanjutnya">
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                        <div class="d-grid mb-1 text-center fw-bold text-muted" style="grid-template-columns:repeat(7,1fr);font-size:.7rem;">
                                            <div>MIN</div><div>SEN</div><div>SEL</div><div>RAB</div><div>KAM</div><div>JUM</div><div>SAB</div>
                                        </div>
                                        <div class="d-grid text-center" id="pb-cal-days" style="grid-template-columns:repeat(7,1fr);gap:2px;"></div>
                                    </div>

                                    {{-- Summary & Actions --}}
                                    <div class="d-flex flex-column justify-content-between flex-shrink-0" style="width:140px;">
                                        <div>
                                            <div class="mb-2">
                                                <label class="form-label text-muted small mb-1" style="font-size:.72rem;">Mulai</label>
                                                <input type="text" id="pb-display-start" class="form-control form-control-sm text-center bg-light fw-bold" style="font-size:.75rem;" readonly placeholder="dd/mm/yyyy">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted small mb-1" style="font-size:.72rem;">Selesai</label>
                                                <input type="text" id="pb-display-end" class="form-control form-control-sm text-center bg-light fw-bold" style="font-size:.75rem;" readonly placeholder="dd/mm/yyyy">
                                            </div>
                                        </div>
                                        <div class="d-grid gap-1">
                                            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm py-1" id="pb-btn-apply-date">Apply</button>
                                            <button type="button" class="btn btn-light btn-sm text-muted py-1" id="pb-btn-reset-date" style="font-size:.75rem;">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- TOMBOL FILTER & RESET --}}
                        <div class="col-lg-1 col-md-4 d-flex gap-1">
                            <button type="submit" class="btn btn-sm fw-semibold flex-fill text-white" style="background-color:#DE8958;" title="Terapkan Filter">
                                <i class="bi bi-funnel-fill"></i> Filter
                            </button>
                            @if(request('search') || (request('sort') && request('sort') !== 'terbaru') || request('status_pembayaran') || request('status_penerimaan') || request('dari') || request('sampai'))
                                <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-sm btn-secondary" title="Reset Filter">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            @endif
                        </div>

                    </div>
                </form>
            </div>
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
                                    @php
                                        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
                                    @endphp
                                    @if(!$item->isTerkunci())
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
                                    @elseif($isSuperAdmin && !$item->isReceived())
                                        {{-- Super Admin Hapus PO yang Belum Diterima/Terkirim --}}
                                        <form action="{{ route('pembelian-kejingga.destroy', $item->id) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('PERHATIAN (Superadmin):\nYakin ingin menghapus Purchase Order {{ $item->kode_pembelian }} yang belum diterima ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm btn-danger rounded-2 px-2 py-1 fw-semibold"
                                                    title="Hapus PO (Super Admin)">
                                                <i class="bi bi-trash3-fill"></i> Hapus PO
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

    <!-- MODAL LUNASI / BAYAR GABUNGAN MASSAL PER SUPPLIER (1 NOTA) -->
    <div class="modal fade" id="modalBayarMassalDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-receipt-cutoff text-success me-2"></i>Pelunasan Bersama (1 Nota Bukti Bayar)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formBayarMassalDetail" action="{{ route('pembelian-kejingga.bayar-massal-detail') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body p-3 p-md-4">
                        <div class="alert alert-info border-info d-flex align-items-center mb-3">
                            <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
                            <div class="small">
                                Anda memilih untuk melunasi beberapa item barang sekaligus dari supplier yang sama dalam <strong>1 nota bukti pembayaran bersama</strong>.
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-0">Nama Supplier</label>
                                <input type="text" id="massal_supplier_nama" class="form-control fw-bold bg-light" readonly>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-0">Total Pelunasan (Rp)</label>
                                <input type="text" id="massal_total_kekurangan" class="form-control fw-bold text-success font-monospace fs-6 bg-light" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1 fw-bold">Daftar Item Barang Terpilih:</label>
                            <div class="table-responsive border rounded-3" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-sm table-striped align-middle mb-0" style="font-size: 12px;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Nama Barang</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Nominal Dilunasi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="massal_items_list">
                                        {{-- Rendered dynamically via JS --}}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Hidden input IDs --}}
                        <div id="massal_hidden_inputs"></div>

                        <div class="mb-3">
                            <label class="form-label small text-dark fw-bold mb-1">
                                Upload 1 Nota / Bukti Pembayaran Bersama <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="bukti_pembayaran" class="form-control" accept="image/*,.pdf" required>
                            <small class="text-muted" style="font-size: 11px;">Format: JPG, PNG, WEBP, PDF (Maks 5MB). File ini akan otomatis ditautkan ke semua item barang di atas.</small>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small text-muted mb-1">Catatan Tambahan (Opsional)</label>
                            <input type="text" name="catatan" class="form-control form-control-sm" placeholder="Contoh: Transfer gabungan via BCA, Nota No. 1234">
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Pelunasan Bersama
                        </button>
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
                            <label class="form-label small text-dark fw-bold mb-1">Tanggal Diterima Barang <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_diterima" id="terima_detail_tanggal" class="form-control fw-bold" value="{{ date('Y-m-d') }}" required>
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
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold px-3 shadow-sm" id="btnEditPoModal" onclick="bukaModalEditFromDetail(currentDetailPoId)" title="Kelola / Tambah / Hapus baris barang pada draft PO ini">
                                <i class="bi bi-sliders me-1"></i> Kelola Baris PO (Draft)
                            </button>
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-3 shadow-sm" id="btnInputBarangTerpilihModal" onclick="bukaModalInputBarangTerpilih()" disabled>
                                <i class="bi bi-pencil-square me-1"></i> Input / Edit Barang Terpilih (<span id="footer-count-terpilih">0</span>)
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM TERSEMBUNYI UNTUK HAPUS DETAIL ITEM BARANG -->
    <form id="formHapusItemDetail" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>

    <!-- MODAL INPUT HARGA, NOTA, SUPPLIER, TAX & UPLOAD BUKTI UNTUK BARANG TERPILIH -->
    <div class="modal fade" id="modalInputBarangTerpilih" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable my-3" style="max-height: calc(100vh - 2rem); max-width: 860px;">
            <form id="formInputBarangTerpilih" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4" style="max-height: 100%; display: flex; flex-direction: column; background-color: #f8fafc;">
                @csrf
                <!-- MODAL HEADER -->
                <div class="modal-header border-bottom bg-white py-3 px-4 flex-shrink-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #eff6ff; color: #2563eb;">
                            <i class="bi bi-receipt fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="input-modal-title">Input / Edit Data Pembelian &amp; Nota</h5>
                            <div class="text-muted small mt-1 d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-file-earmark-text me-1"></i>PO <span id="input-modal-po-kode">#</span></span>
                                <span>&bull;</span>
                                <span id="input-modal-subtitle">Memperbarui <strong class="text-primary" id="input-modal-count">0</strong> item terpilih</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- MODAL BODY -->
                <div class="modal-body p-3 p-md-4" style="overflow-y: auto; flex: 1 1 auto; min-height: 0;">

                    <!-- CARD 1: INFORMASI SUPPLIER & NOTA -->
                    <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                <i class="bi bi-building text-secondary"></i>
                                <span class="fw-bold small text-dark text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Informasi Faktur &amp; Pemasok</span>
                            </div>
                            <div class="row g-2 pt-1">
                                <div class="col-12 col-md-5">
                                    <label class="form-label fw-semibold text-secondary small mb-1">Supplier / Toko <span class="text-danger">*</span></label>
                                    <select name="supplier_id" id="input_supplier_id" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih Supplier --</option>
                                        @foreach($suppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold text-secondary small mb-1">Nomor Faktur / Nota</label>
                                    <input type="text" name="nomor_nota" id="input_nomor_nota" class="form-control form-control-sm" placeholder="Contoh: INV-2026/09/001">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold text-secondary small mb-1">Tanggal Diterima</label>
                                    <input type="date" name="tanggal_diterima" id="input_tanggal_diterima" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: DAFTAR BARANG & INPUT HARGA -->
                    <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-box-seam text-secondary"></i>
                                    <span class="fw-bold small text-dark text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Daftar Barang &amp; Harga Total</span>
                                </div>
                                <span class="text-muted small" style="font-size: 11px;"><i class="bi bi-info-circle me-1"></i>Input total harga real pada nota</span>
                            </div>
                            <div class="table-responsive border rounded-2">
                                <table class="table table-sm table-hover align-middle mb-0" id="table-input-barang-terpilih">
                                    <thead class="bg-light text-secondary border-bottom">
                                        <tr>
                                            <th width="35" class="text-center small py-2">No</th>
                                            <th class="small py-2" style="min-width: 220px;">Nama Barang</th>
                                            <th width="115" class="text-center small py-2">Qty Dipesan</th>
                                            <th width="190" class="text-end small py-2">Total Harga (Rp) <span class="text-danger">*</span></th>
                                            <th width="135" class="text-end small py-2">Harga/Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-input-barang-terpilih" class="bg-white">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="3" class="text-end small fw-semibold text-secondary py-2">Subtotal Barang:</td>
                                            <td class="text-end fw-bold text-dark py-2" id="total-harga-terpilih-display">Rp 0</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 3 & 4: DOKUMEN & RINGKASAN PEMBAYARAN -->
                    <div class="row g-3">
                        <!-- KOLOM KIRI: BUKTI NOTA & STATUS BAYAR -->
                        <div class="col-12 col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                        <i class="bi bi-paperclip text-secondary"></i>
                                        <span class="fw-bold small text-dark text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Bukti &amp; Pembayaran</span>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-secondary small mb-1">Foto / File Nota Fisik</label>
                                        <input type="file" name="bukti_pembayaran" id="input_bukti_pembayaran" class="form-control form-control-sm" accept="image/*,application/pdf">
                                        <div class="text-muted mt-1" style="font-size: 11px;">Mendukung JPG, PNG, PDF (Maks. 5MB)</div>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold text-secondary small mb-1">Status Pembayaran</label>
                                        <div class="p-2 border rounded-2 bg-light">
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" role="switch" name="is_lunas" id="input_is_lunas" value="1" checked onchange="toggleMetodeBayarInputModal()">
                                                <label class="form-check-label fw-semibold text-dark small" for="input_is_lunas" id="label_is_lunas">
                                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Langsung Lunas</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mt-2" id="box_metode_bayar" style="display: none;">
                                            <select name="metode_pembayaran" id="input_metode_pembayaran" class="form-select form-select-sm">
                                                <option value="termin" selected>Termin / Hutang Supplier</option>
                                                <option value="cod">COD / Bayar Saat Terima</option>
                                                <option value="dp">Uang Muka (DP)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: RINGKASAN BIAYA & GRAND TOTAL -->
                        <div class="col-12 col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                            <i class="bi bi-calculator text-secondary"></i>
                                            <span class="fw-bold small text-dark text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Ringkasan Biaya</span>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-secondary small mb-1">Biaya Tambahan (Tax / Ongkir)</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white text-muted">Rp</span>
                                                <input type="text" name="tax_service" id="input_tax_service" class="form-control text-end fw-semibold mask-number" placeholder="0" oninput="hitungGrandTotalInputModal()">
                                            </div>
                                            <div class="text-muted mt-1" style="font-size: 11px;">Pajak, ongkos kirim, atau biaya lain jika ada</div>
                                        </div>
                                    </div>
                                    
                                    <!-- HIGHLIGHT GRAND TOTAL -->
                                    <div class="p-3 rounded-3 mt-2" style="background-color: #f1f5f9; border: 1px dashed #cbd5e1;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary small fw-semibold">Grand Total:</span>
                                            <span class="fs-5 fw-bold text-dark" id="input_grand_total_text">Rp 0</span>
                                        </div>
                                        <input type="hidden" id="input_grand_total_display" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- MODAL FOOTER -->
                <div class="modal-footer border-top bg-white rounded-bottom-4 py-2 px-4 d-flex justify-content-end gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-light btn-sm px-3 text-secondary border shadow-none" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-4 shadow-sm" id="btnSimpanInputBarangTerpilih">
                        <i class="bi bi-check-circle-fill me-1"></i> Simpan Data Barang
                    </button>
                </div>
            </form>
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

    let returnToDetailFromEdit = false;
    let isSubmittingEditPo = false;

    function bukaModalEditFromDetail(poId) {
        if (!poId) return;
        returnToDetailFromEdit = true;
        isSubmittingEditPo = false;

        const detailModalEl = document.getElementById('modalDetail');
        const detailModalInst = bootstrap.Modal.getInstance(detailModalEl);

        const doOpenEdit = () => {
            bukaModalEdit(poId);
        };

        if (detailModalInst && detailModalEl && detailModalEl.classList.contains('show')) {
            detailModalEl.addEventListener('hidden.bs.modal', function onDetailHiddenForEdit() {
                detailModalEl.removeEventListener('hidden.bs.modal', onDetailHiddenForEdit);
                if (returnToDetailFromEdit) {
                    doOpenEdit();
                }
            });
            detailModalInst.hide();
        } else {
            doOpenEdit();
        }
    }

    function hapusItemDetail(detailId, barangNama) {
        if (!confirm(`Apakah Anda yakin ingin menghapus barang "${barangNama}" dari PO ini?`)) {
            return;
        }
        const form = document.getElementById('formHapusItemDetail');
        if (form) {
            form.action = `/pembelian-kejingga/detail/${detailId}`;
            form.submit();
        }
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
        const tglEl = document.getElementById('terima_detail_tanggal');
        if (tglEl) {
            tglEl.value = new Date().toISOString().split('T')[0];
        }
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
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;">
                        <strong>${d.qty_diterima.toLocaleString('id-ID')} ${d.satuan}</strong>
                        ${d.tanggal_diterima ? `<br><small style="color:#059669; font-size:9.5px;">Tgl: ${d.tanggal_diterima}</small>` : ''}
                    </td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:right;">${d.harga > 0 ? 'Rp ' + d.harga_per_qty.toLocaleString('id-ID') : '—'}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:right; font-weight:bold;">${d.harga > 0 ? 'Rp ' + d.harga.toLocaleString('id-ID') : '—'}</td>
                </tr>
            `;
        });

        return `
            <!-- HEADER BLOCK - WARNA HIJAU MUDA (KEJINGGA MANDIRI) -->
            <div style="background-color:#10b981; color:#ffffff; padding:14px 18px; border-radius:6px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:16px; font-weight:bold; letter-spacing:0.5px; text-transform:uppercase;">KEJINGGA</div>
                    <div style="font-size:10.5px; opacity:0.95; margin-top:2px;">Pembelanjaan Mandiri Outlet KeJingga ke Supplier</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:14px; font-weight:bold; text-transform:uppercase;">PURCHASE ORDER KEJINGGA</div>
                    <div style="font-family:monospace; font-weight:bold; font-size:13px; margin-top:2px;">#${item.kode}</div>
                </div>
            </div>

            <!-- STANDAR INFO GRID METADATA -->
            <table style="width:100%; border-collapse:collapse; background-color:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; font-size:11px;">
                <tr>
                    <td style="padding:6px 10px; font-weight:bold; color:#475569; width:18%; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Judul Dokumen</td>
                    <td style="padding:6px 10px; font-weight:bold; color:#0f172a; width:32%; border-bottom:1px solid #e2e8f0;">PURCHASE ORDER KEJINGGA</td>
                    <td style="padding:6px 10px; font-weight:bold; color:#475569; width:18%; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Tanggal Order</td>
                    <td style="padding:6px 10px; font-weight:bold; color:#0f172a; width:32%; border-bottom:1px solid #e2e8f0;">${item.tanggal}</td>
                </tr>
                <tr>
                    <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Outlet Pemesan</td>
                    <td style="padding:6px 10px; font-weight:bold; color:#10b981; border-bottom:1px solid #e2e8f0;">Gudang KeJingga</td>
                    <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px; border-bottom:1px solid #e2e8f0;">Gudang Sumber</td>
                    <td style="padding:6px 10px; color:#0f172a; border-bottom:1px solid #e2e8f0;"><strong>${item.details[0]?.supplier_nama || 'Supplier Bahan'}</strong> <span style="font-size:10px; color:#64748b;">(Pemasok)</span></td>
                </tr>
                <tr>
                    <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px;">Status Dokumen</td>
                    <td style="padding:6px 10px; color:#0f172a;">
                        <span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; border-radius:3px; background-color:#dcfce7; color:#15803d; border:1px solid #bbf7d0;">PROSES / AKTIF</span>
                    </td>
                    <td style="padding:6px 10px; font-weight:bold; color:#475569; text-transform:uppercase; font-size:10px;">Dibuat Oleh</td>
                    <td style="padding:6px 10px; font-weight:bold; color:#0f172a;">${item.user_nama}</td>
                </tr>
            </table>

            <table style="width:100%; border-collapse:collapse; font-size:11px; margin-bottom:15px;">
                <thead>
                    <tr style="background:#1e293b; color:#ffffff;">
                        <th style="padding:7px 6px; border:1px solid #1e293b;" width="30">No</th>
                        <th style="padding:7px 8px; border:1px solid #1e293b;">Barang &amp; Supplier</th>
                        <th style="padding:7px 8px; border:1px solid #1e293b;" width="110">Stok Kejingga</th>
                        <th style="padding:7px 8px; border:1px solid #1e293b;" width="110">Qty Order</th>
                        <th style="padding:7px 8px; border:1px solid #1e293b;" width="95">Diterima</th>
                        <th style="padding:7px 8px; border:1px solid #1e293b;" width="110">Harga / Satuan</th>
                        <th style="padding:7px 8px; border:1px solid #1e293b;" width="115">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${detailsHtml}
                </tbody>
            </table>

            <div style="text-align:right; font-size:13px; font-weight:bold; color:#0f172a; margin-bottom:20px;">
                Total Purchase Order: <span style="color:#10b981; font-size:15px;">Rp ${item.total.toLocaleString('id-ID')}</span>
            </div>

            <div style="display:flex; justify-content:space-between; text-align:center; font-size:10.5px; margin-top:25px; border-top:1px solid #e2e8f0; padding-top:10px;">
                <div>Dibuat Oleh (KeJingga)<br><br><br><strong>( ${item.user_nama} )</strong></div>
                <div>Supplier / Vendor<br><br><br><strong>( Pemasok Bahan )</strong></div>
                <div>Gudang Penerima<br><br><br><strong>( Gudang Kejingga )</strong></div>
            </div>

            <div style="margin-top:20px; border-top:1px dashed #cbd5e1; padding-top:8px; font-size:9.5px; color:#94a3b8; display:flex; justify-content:space-between;">
                <div>Dokumen Resmi Sistem ERP - Outlet KeJingga</div>
                <div>Dicetak pada: ${new Date().toLocaleDateString('id-ID', {day:'2-digit', month:'2-digit', year:'numeric'})}</div>
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

    let currentDetailPoId = null;

    // ==========================================
    // BUKA MODAL DETAIL (POP-UP RESPONSIVE)
    // ==========================================
    function bukaModalDetail(id) {
        currentDetailPoId = id;
        const item = dataPembayaranMap[id];
        if (!item) return;

        const btnEditPo = document.getElementById('btnEditPoModal');
        if (btnEditPo) {
            btnEditPo.style.display = item.is_terkunci ? 'none' : 'inline-block';
        }

        let detailsHtml = '';
        let totalItemsCalculated = 0;
        item.details.forEach((d, idx) => {
            let subtotal = d.harga;
            totalItemsCalculated += subtotal;
            let konvInfo = d.has_konversi ? `<div class="text-primary small" style="font-size:11px;">= ${(d.qty * d.konversi_pembelian).toLocaleString('id-ID')} ${d.satuan_utama}</div>` : '';

            // Clean badges for supplier, nota, and payment status
            let supplierBadge = d.supplier_nama && d.supplier_nama !== 'Belum Ditentukan (Draft)'
                ? `<span class="badge bg-light text-dark border me-1"><i class="bi bi-shop text-primary me-1"></i>${d.supplier_nama}</span>`
                : `<span class="badge bg-light text-muted border me-1">Supplier: Belum Ditentukan</span>`;

            let notaBadge = d.catatan_pembayaran 
                ? `<span class="badge bg-light text-secondary border me-1"><i class="bi bi-receipt me-1"></i>Nota: ${d.catatan_pembayaran}</span>`
                : '';

            let taxBadge = d.tax_service > 0
                ? `<span class="badge bg-light text-secondary border me-1" title="Tax / Ongkir item ini"><i class="bi bi-receipt-cutoff text-secondary me-1"></i>Tax: Rp ${d.tax_service.toLocaleString('id-ID')}</span>`
                : '';

            let tglDiterimaBadge = d.tanggal_diterima
                ? `<span class="badge bg-light text-success border me-1" title="Tanggal Diterima"><i class="bi bi-calendar-check text-success me-1"></i>Diterima: ${d.tanggal_diterima}</span>`
                : '';

            let statusBayarBadge = '';
            if (d.is_lunas) {
                statusBayarBadge = `<span class="badge bg-success-subtle text-success border border-success me-1"><i class="bi bi-check-circle-fill me-1"></i>Lunas</span>`;
            } else if (d.metode_pembayaran) {
                statusBayarBadge = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning me-1">${d.label_pembayaran}</span>`;
            }

            let buktiBadge = '';
            if (d.bukti_pembayaran_url) {
                buktiBadge = `<a href="${d.bukti_pembayaran_url}" target="_blank" class="badge bg-info-subtle text-info-emphasis border text-decoration-none me-1" title="Lihat Bukti/Nota"><i class="bi bi-file-earmark-image me-1"></i>Lihat Bukti</a>`;
            }

            let hargaHtml = d.harga > 0
                ? `<div class="fw-bold text-dark fs-6">Rp ${d.harga.toLocaleString('id-ID')}</div>
                   <div class="text-muted small">@ Rp ${Math.round(d.harga_per_qty).toLocaleString('id-ID')} / ${d.satuan}</div>
                   ${d.tax_service > 0 ? `<div class="text-secondary small fw-semibold" style="font-size: 11px;">+ Tax/Ongkir: Rp ${d.tax_service.toLocaleString('id-ID')}</div>` : ''}`
                : `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Belum Diisi</span>`;

            let aksiHtml = '';
            if (!item.is_terkunci && !d.is_diterima_item) {
                aksiHtml = `
                    <div class="d-flex justify-content-center align-items-center gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fw-semibold" style="font-size: 11px;" onclick="bukaModalEditBarangSingle(${d.id})" title="Edit / Input Barang Ini">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fw-semibold" style="font-size: 11px;" onclick="hapusItemDetail(${d.id}, '${addslashes(d.nama)}')" title="Hapus Barang dari PO">
                            <i class="bi bi-trash me-1"></i>Hapus
                        </button>
                    </div>
                `;
            } else {
                aksiHtml = `<span class="badge bg-light text-muted border" style="font-size: 11px;"><i class="bi bi-lock-fill me-1"></i>Terkunci</span>`;
            }

            let checkHtml = `<input type="checkbox" class="form-check-input item-check-pilih border-primary" data-id="${d.id}" data-nama="${addslashes(d.nama)}" data-qty="${d.qty}" data-satuan="${d.satuan}" data-harga="${d.harga}" data-tax="${d.tax_service || 0}" data-supplier-id="${d.supplier_id || ''}" data-supplier-nama="${addslashes(d.supplier_nama || '')}" data-nota="${addslashes(d.catatan_pembayaran || '')}" onchange="updateItemSelection()">`;

            detailsHtml += `
                <tr id="row-detail-${d.id}">
                    <td class="text-center align-middle">${checkHtml}</td>
                    <td class="text-center align-middle fw-semibold text-muted">${idx + 1}</td>
                    <td>
                        <div class="fw-bold text-dark fs-6">${d.nama}</div>
                        <div class="font-monospace text-muted small mb-1" style="font-size: 11px;">${d.kode_barang || '—'}</div>
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                            ${supplierBadge}
                            ${notaBadge}
                            ${taxBadge}
                            ${tglDiterimaBadge}
                            ${statusBayarBadge}
                            ${buktiBadge}
                        </div>
                    </td>
                    <td class="text-center align-middle bg-light">
                        <span class="badge bg-white text-dark border px-2 py-1">${d.stok_kejingga.toLocaleString('id-ID')} ${d.satuan_utama}</span>
                    </td>
                    <td class="text-center align-middle">
                        <div class="fw-bold text-primary">${d.qty.toLocaleString('id-ID')} ${d.satuan}</div>
                        ${konvInfo}
                    </td>
                    <td class="text-end align-middle">
                        ${hargaHtml}
                    </td>
                    <td class="text-center align-middle">
                        ${aksiHtml}
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
                            Pembelanjaan Mandiri Outlet KeJingga ke Supplier<br>
                            <strong>Gudang:</strong> ${item.gudang_nama}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge text-white px-3 py-2 fs-6 fw-bold mb-2" style="background-color: #10b981;">PURCHASE ORDER KEJINGGA</span>
                        <div class="font-monospace fw-bold text-dark fs-5">#${item.kode}</div>
                        <div class="text-muted small">Tanggal: <strong>${item.tanggal}</strong></div>
                    </div>
                </div>

                <!-- TOOLBAR AKSI ITEM TERPILIH -->
                <div id="selection-action-bar" class="alert alert-warning border-warning py-2 px-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2 shadow-sm rounded-3" style="display: none !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle text-warning-emphasis fs-4"></i>
                        <div>
                            <span class="fw-bold text-dark" id="selection-count-text">0 barang dipilih</span>
                            <div class="small text-muted">Klik tombol di kanan untuk menginput harga, supplier, dan nota bersama</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="clearItemSelection()">Batal</button>
                        <button type="button" class="btn btn-warning btn-sm text-dark fw-bold py-1 px-3 shadow-sm" onclick="bukaModalInputBarangTerpilih()">
                            <i class="bi bi-pencil-square me-1"></i> Input / Edit Barang Terpilih
                        </button>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-dark">
                            <tr>
                                <th width="40" class="text-center">
                                    <input type="checkbox" id="check-all-items" class="form-check-input" title="Pilih Semua Barang" onchange="toggleCheckAllItems(this)">
                                </th>
                                <th width="40" class="text-center">No</th>
                                <th>Nama Barang</th>
                                <th width="140" class="text-center">Stok Kejingga</th>
                                <th width="140" class="text-center">Qty Dipesan</th>
                                <th width="160" class="text-end">Harga</th>
                                <th width="130" class="text-center">Aksi</th>
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
                                <td class="fs-5 fw-bold" style="color: #10b981;">Rp ${item.total.toLocaleString('id-ID')}</td>
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

        // Reset selections & footer buttons
        updateItemSelection();

        document.getElementById('btnDownloadJpgModal').onclick = function() {
            downloadJpgDirect(id);
        };

        document.getElementById('btnCetakPdfModal').href = `/pembelian/${id}/cetak-pdf`;

        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    function toggleCheckAllItems(master) {
        const checkboxes = document.querySelectorAll('.item-check-pilih');
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateItemSelection();
    }

    function updateItemSelection() {
        const checkboxes = document.querySelectorAll('.item-check-pilih');
        const checked = document.querySelectorAll('.item-check-pilih:checked');
        const count = checked.length;

        const countText = document.getElementById('selection-count-text');
        if (countText) countText.textContent = `${count} barang dipilih`;

        const footerBadge = document.getElementById('footer-count-terpilih');
        if (footerBadge) footerBadge.textContent = count;

        const btnFooter = document.getElementById('btnInputBarangTerpilihModal');
        if (btnFooter) btnFooter.disabled = (count === 0);

        const actionBar = document.getElementById('selection-action-bar');
        if (actionBar) {
            if (count > 0) {
                actionBar.style.setProperty('display', 'flex', 'important');
            } else {
                actionBar.style.setProperty('display', 'none', 'important');
            }
        }

        const master = document.getElementById('check-all-items');
        if (master && checkboxes.length > 0) {
            master.checked = (checkboxes.length === count);
            master.indeterminate = (count > 0 && count < checkboxes.length);
        }
    }

    function clearItemSelection() {
        const checkboxes = document.querySelectorAll('.item-check-pilih');
        checkboxes.forEach(cb => cb.checked = false);
        const master = document.getElementById('check-all-items');
        if (master) master.checked = false;
        updateItemSelection();
    }

    let returnToDetailFromInput = false;
    let isSubmittingInputBarang = false;

    // Buka modal input / edit hanya untuk 1 baris barang yang dipilih
    function bukaModalEditBarangSingle(detailId) {
        document.querySelectorAll('.item-check-pilih').forEach(cb => {
            cb.checked = (parseInt(cb.getAttribute('data-id')) === detailId);
        });
        updateItemSelection();
        bukaModalInputBarangTerpilih();
    }

    function bukaModalInputBarangTerpilih() {
        if (!currentDetailPoId) return;
        const po = dataPembayaranMap[currentDetailPoId];
        if (!po) return;

        const checkedBoxes = Array.from(document.querySelectorAll('.item-check-pilih:checked'));
        if (checkedBoxes.length === 0) {
            alert('Pilih minimal 1 barang yang ingin diinput harganya.');
            return;
        }

        const checkedIds = checkedBoxes.map(cb => parseInt(cb.getAttribute('data-id')));
        const selectedDetails = po.details.filter(d => checkedIds.includes(d.id));

        // Set Form Action
        const form = document.getElementById('formInputBarangTerpilih');
        form.action = `/pembelian-kejingga/${currentDetailPoId}/input-barang-terpilih`;

        // Header info
        const inputModalTitle = document.getElementById('input-modal-title');
        const inputModalSubtitle = document.getElementById('input-modal-subtitle');
        const inputModalCount = document.getElementById('input-modal-count');

        if (inputModalCount) inputModalCount.textContent = selectedDetails.length;
        document.getElementById('input-modal-po-kode').textContent = '#' + po.kode;

        if (selectedDetails.length === 1) {
            if (inputModalTitle) inputModalTitle.innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i>Edit Data Pembelian &amp; Nota';
            if (inputModalSubtitle) inputModalSubtitle.innerHTML = `Memperbarui barang: <strong class="text-primary">${selectedDetails[0].nama}</strong>`;
        } else {
            if (inputModalTitle) inputModalTitle.innerHTML = '<i class="bi bi-receipt text-primary me-2"></i>Input / Edit Data Pembelian &amp; Nota';
            if (inputModalSubtitle) inputModalSubtitle.innerHTML = `Memperbarui <strong class="text-primary">${selectedDetails.length}</strong> item terpilih`;
        }

        // Prefill Supplier: if all selected have same supplier_id, select it
        const supplierIds = Array.from(new Set(selectedDetails.map(d => d.supplier_id).filter(Boolean)));
        const supplierSelect = document.getElementById('input_supplier_id');
        if (supplierIds.length === 1) {
            supplierSelect.value = supplierIds[0];
        } else {
            supplierSelect.value = po.supplier_id || '';
        }

        // Prefill Nota
        const notas = Array.from(new Set(selectedDetails.map(d => d.catatan_pembayaran).filter(Boolean)));
        document.getElementById('input_nomor_nota').value = notas.length === 1 ? notas[0] : '';

        // Prefill Tanggal Diterima
        const tglDiterimas = Array.from(new Set(selectedDetails.map(d => d.tanggal_diterima_raw).filter(Boolean)));
        const tglInput = document.getElementById('input_tanggal_diterima');
        if (tglInput) {
            tglInput.value = tglDiterimas.length === 1 ? tglDiterimas[0] : new Date().toISOString().split('T')[0];
        }

        // Prefill Tax: HANYA jumlah tax_service dari barang-barang yang dipilih (tidak mengambil tax PO keseluruhan)
        const taxInput = document.getElementById('input_tax_service');
        if (taxInput) {
            const selectedTax = selectedDetails.reduce((sum, d) => sum + (parseFloat(d.tax_service) || 0), 0);
            taxInput.value = selectedTax > 0 ? formatNumberDisplay(selectedTax) : '';
        }

        // Prefill Status & Metode Pembayaran
        const isLunasCb = document.getElementById('input_is_lunas');
        const allLunas = selectedDetails.length > 0 && selectedDetails.every(d => d.is_lunas);
        if (isLunasCb) {
            isLunasCb.checked = allLunas;
            toggleMetodeBayarInputModal();
        }
        const metodes = Array.from(new Set(selectedDetails.map(d => d.metode_pembayaran).filter(Boolean)));
        if (metodes.length === 1 && document.getElementById('input_metode_pembayaran')) {
            document.getElementById('input_metode_pembayaran').value = metodes[0];
        }

        // Reset file input
        const fileInput = document.getElementById('input_bukti_pembayaran');
        if (fileInput) fileInput.value = '';

        // Render tbody
        let tbodyHtml = '';
        selectedDetails.forEach((d, idx) => {
            let hargaVal = d.harga > 0 ? formatNumberDisplay(d.harga) : '';
            let unitPriceText = (d.harga > 0 && d.qty > 0) ? 'Rp ' + Math.round(d.harga / d.qty).toLocaleString('id-ID') : '—';

            let barangCellHtml = '';
            if (d.harga <= 0 || !d.is_diterima_item) {
                let optionsHtml = barangsList.map(b => {
                    let isSel = (b.id == d.barang_id) ? 'selected' : '';
                    return `<option value="${b.id}" ${isSel}>${b.nama} (${b.kode})</option>`;
                }).join('');

                barangCellHtml = `
                    <input type="hidden" name="detail_ids[]" value="${d.id}">
                    <div class="mb-1">
                        <select name="items[${d.id}][barang_id]" class="form-select form-select-sm fw-semibold select-barang-input-modal" data-id="${d.id}" onchange="onBarangChangedInInputModal(${d.id}, this)">
                            ${optionsHtml}
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-1 small text-muted">
                        <span class="badge bg-light text-secondary border" id="satuan-label-input-${d.id}">Satuan: ${d.satuan}</span>
                        <span class="badge bg-light text-muted border" id="stok-label-input-${d.id}">Stok: ${d.stok_kejingga.toLocaleString('id-ID')} ${d.satuan_utama}</span>
                    </div>
                `;
            } else {
                barangCellHtml = `
                    <input type="hidden" name="detail_ids[]" value="${d.id}">
                    <input type="hidden" name="items[${d.id}][barang_id]" value="${d.barang_id}">
                    <div class="fw-bold text-dark">${d.nama}</div>
                    <div class="text-muted small">${d.satuan}</div>
                `;
            }

            tbodyHtml += `
                <tr id="input-item-row-${d.id}">
                    <td class="text-center align-middle">${idx + 1}</td>
                    <td class="align-middle">
                        ${barangCellHtml}
                    </td>
                    <td class="text-center align-middle">
                        <input type="number" step="any" min="0.01" name="items[${d.id}][qty]" class="form-control form-control-sm text-center fw-bold input-row-qty" data-id="${d.id}" value="${d.qty}" oninput="recalcItemRow(${d.id})">
                    </td>
                    <td class="align-middle">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white">Rp</span>
                            <input type="text" name="items[${d.id}][harga]" class="form-control text-end fw-bold mask-number input-row-harga" data-id="${d.id}" value="${hargaVal}" placeholder="0" oninput="recalcItemRow(${d.id})" required>
                        </div>
                    </td>
                    <td class="text-end align-middle fw-semibold text-muted" id="unit-price-display-${d.id}">
                        ${unitPriceText}
                    </td>
                </tr>
            `;
        });
        document.getElementById('tbody-input-barang-terpilih').innerHTML = tbodyHtml;

        // Pasang maskInput pada setiap input harga di modal
        document.querySelectorAll('#tbody-input-barang-terpilih .mask-number').forEach(inp => {
            maskInput(inp);
        });
        if (taxInput) maskInput(taxInput);

        // Recalculate totals
        hitungGrandTotalInputModal();

        // Transisi modal: Tutup modalDetail terlebih dahulu agar tidak bertumpukan
        returnToDetailFromInput = true;
        isSubmittingInputBarang = false;

        const detailModalEl = document.getElementById('modalDetail');
        const detailModalInst = bootstrap.Modal.getInstance(detailModalEl);

        const showInputModal = () => {
            const inputModalEl = document.getElementById('modalInputBarangTerpilih');
            bootstrap.Modal.getOrCreateInstance(inputModalEl).show();
        };

        if (detailModalInst && detailModalEl && detailModalEl.classList.contains('show')) {
            detailModalEl.addEventListener('hidden.bs.modal', function onDetailHidden() {
                detailModalEl.removeEventListener('hidden.bs.modal', onDetailHidden);
                if (returnToDetailFromInput) {
                    showInputModal();
                }
            });
            detailModalInst.hide();
        } else {
            showInputModal();
        }
    }

    function recalcItemRow(id) {
        const row = document.getElementById(`input-item-row-${id}`);
        if (!row) return;

        const qtyInput = row.querySelector('.input-row-qty');
        const hargaInput = row.querySelector('.input-row-harga');
        const unitPriceEl = document.getElementById(`unit-price-display-${id}`);

        const qty = parseFloat(qtyInput.value) || 0;
        const harga = unformatNumber(hargaInput.value);

        if (qty > 0 && harga > 0) {
            const unitPrice = Math.round(harga / qty);
            unitPriceEl.textContent = 'Rp ' + unitPrice.toLocaleString('id-ID');
        } else {
            unitPriceEl.textContent = '—';
        }

        hitungGrandTotalInputModal();
    }

    function hitungGrandTotalInputModal() {
        let totalItems = 0;
        document.querySelectorAll('.input-row-harga').forEach(inp => {
            totalItems += unformatNumber(inp.value);
        });

        const taxInput = document.getElementById('input_tax_service');
        let taxVal = 0;
        if (taxInput && taxInput.value) {
            taxVal = unformatNumber(taxInput.value);
        }

        const grandTotal = totalItems + taxVal;

        const totalItemsEl = document.getElementById('total-harga-terpilih-display');
        if (totalItemsEl) totalItemsEl.textContent = 'Rp ' + totalItems.toLocaleString('id-ID');

        const grandTotalTextEl = document.getElementById('input_grand_total_text');
        if (grandTotalTextEl) grandTotalTextEl.textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');

        const grandTotalEl = document.getElementById('input_grand_total_display');
        if (grandTotalEl) grandTotalEl.value = grandTotal.toLocaleString('id-ID');
    }

    function toggleMetodeBayarInputModal() {
        const isLunasCb = document.getElementById('input_is_lunas');
        const labelEl = document.getElementById('label_is_lunas');
        const boxMetode = document.getElementById('box_metode_bayar');
        const selectMetode = document.getElementById('input_metode_pembayaran');

        if (isLunasCb.checked) {
            labelEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Langsung Lunas</span>';
            boxMetode.style.display = 'none';
            selectMetode.value = 'cod';
        } else {
            labelEl.innerHTML = '<span class="text-warning-emphasis"><i class="bi bi-clock-history me-1"></i> Belum Lunas (Termin)</span>';
            boxMetode.style.display = 'block';
            selectMetode.value = 'termin';
        }
    }

    function onBarangChangedInInputModal(detailId, selectEl) {
        const barangId = selectEl.value;
        const b = barangsMap[barangId];
        if (!b) return;

        const satuanEl = document.getElementById(`satuan-label-input-${detailId}`);
        if (satuanEl) {
            const sat = b.satuan_pembelian || b.satuan_utama || 'Pcs';
            satuanEl.textContent = 'Satuan: ' + sat;
        }

        const stokEl = document.getElementById(`stok-label-input-${detailId}`);
        if (stokEl) {
            stokEl.textContent = 'Stok: ' + b.stok_kejingga.toLocaleString('id-ID') + ' ' + b.satuan_utama;
        }

        recalcItemRow(detailId);
    }

    // Event listener: Saat modalInputBarangTerpilih ditutup tanpa submit (batal), kembalikan ke modalDetail
    const modalInputEl = document.getElementById('modalInputBarangTerpilih');
    if (modalInputEl) {
        modalInputEl.addEventListener('hidden.bs.modal', function () {
            if (returnToDetailFromInput && !isSubmittingInputBarang && currentDetailPoId) {
                returnToDetailFromInput = false;
                const detailModalEl = document.getElementById('modalDetail');
                if (detailModalEl) {
                    bootstrap.Modal.getOrCreateInstance(detailModalEl).show();
                }
            }
        });
    }

    const formInputEl = document.getElementById('formInputBarangTerpilih');
    if (formInputEl) {
        formInputEl.addEventListener('submit', function () {
            isSubmittingInputBarang = true;
            returnToDetailFromInput = false;
        });
    }

    // Event listener: Saat modalEdit ditutup tanpa submit (batal), kembalikan ke modalDetail
    const modalEditEl = document.getElementById('modalEdit');
    if (modalEditEl) {
        modalEditEl.addEventListener('hidden.bs.modal', function () {
            if (returnToDetailFromEdit && !isSubmittingEditPo && currentDetailPoId) {
                returnToDetailFromEdit = false;
                const detailModalEl = document.getElementById('modalDetail');
                if (detailModalEl) {
                    bootstrap.Modal.getOrCreateInstance(detailModalEl).show();
                }
            }
        });
    }

    const formEditEl = document.getElementById('formEditModal');
    if (formEditEl) {
        formEditEl.addEventListener('submit', function () {
            isSubmittingEditPo = true;
            returnToDetailFromEdit = false;
        });
    }

    // Helper addslashes for JS strings
    function addslashes(str) {
        return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
    }

    // ==========================================
    // LOGIKA PELUNASAN GABUNGAN MASSAL (1 NOTA)
    // ==========================================
    let selectedMassalItems = []; // [{ id, nama, supplierId, supplierNama, nominal, qty }]

    function initCheckboxMassalListeners() {
        selectedMassalItems = [];
        updateMassalToolbar();

        const checkboxes = document.querySelectorAll('.item-check-massal');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const id = parseInt(this.getAttribute('data-id'));
                const nama = this.getAttribute('data-nama');
                const supplierId = this.getAttribute('data-supplier-id');
                const supplierNama = this.getAttribute('data-supplier-nama');
                const nominal = parseFloat(this.getAttribute('data-nominal')) || 0;
                const qty = this.getAttribute('data-qty');

                if (this.checked) {
                    // Validasi: pastikan supplier sama dengan item yang sudah terpilih
                    if (selectedMassalItems.length > 0) {
                        const currentSupplierId = selectedMassalItems[0].supplierId;
                        if (currentSupplierId !== supplierId) {
                            alert(`⚠️ Perhatian:\nAnda hanya dapat memilih item dari supplier yang sama dalam 1 nota pelunasan bersama.\n\nItem terpilih saat ini dari supplier: ${selectedMassalItems[0].supplierNama}.\nAnda memilih item dari supplier: ${supplierNama}.`);
                            this.checked = false;
                            return;
                        }
                    }

                    selectedMassalItems.push({ id, nama, supplierId, supplierNama, nominal, qty });
                    const row = document.getElementById(`row-detail-${id}`);
                    if (row) row.classList.add('table-success');
                } else {
                    selectedMassalItems = selectedMassalItems.filter(it => it.id !== id);
                    const row = document.getElementById(`row-detail-${id}`);
                    if (row) row.classList.remove('table-success');
                }

                updateMassalToolbar();
            });
        });
    }

    function updateMassalToolbar() {
        const toolbar = document.getElementById('toolbar-massal-supplier');
        if (!toolbar) return;

        if (selectedMassalItems.length > 0) {
            toolbar.style.setProperty('display', 'flex', 'important');
            const totalNominal = selectedMassalItems.reduce((acc, it) => acc + it.nominal, 0);
            const count = selectedMassalItems.length;
            const supplierNama = selectedMassalItems[0].supplierNama;

            document.getElementById('toolbar-massal-info').innerText = `${count} item terpilih (Total: Rp ${totalNominal.toLocaleString('id-ID')})`;
            document.getElementById('toolbar-massal-sub').innerText = `Supplier: ${supplierNama}`;
        } else {
            toolbar.style.setProperty('display', 'none', 'important');
        }
    }

    function batalPilihMassal() {
        document.querySelectorAll('.item-check-massal').forEach(cb => {
            cb.checked = false;
        });
        document.querySelectorAll('#po-modal-doc-render table tbody tr').forEach(tr => {
            tr.classList.remove('table-success');
        });
        selectedMassalItems = [];
        updateMassalToolbar();
    }

    function bukaModalBayarMassal() {
        if (selectedMassalItems.length === 0) {
            alert('Pilih minimal 1 item barang terlebih dahulu.');
            return;
        }

        const supplierNama = selectedMassalItems[0].supplierNama;
        const totalNominal = selectedMassalItems.reduce((acc, it) => acc + it.nominal, 0);

        document.getElementById('massal_supplier_nama').value = supplierNama;
        document.getElementById('massal_total_kekurangan').value = 'Rp ' + totalNominal.toLocaleString('id-ID');

        // Render table list
        let rowsHtml = '';
        let hiddenInputsHtml = '';
        selectedMassalItems.forEach(it => {
            rowsHtml += `
                <tr>
                    <td class="fw-bold">${it.nama}</td>
                    <td class="text-center">${it.qty}</td>
                    <td class="text-end fw-bold text-success">Rp ${it.nominal.toLocaleString('id-ID')}</td>
                </tr>
            `;
            hiddenInputsHtml += `<input type="hidden" name="detail_ids[]" value="${it.id}">`;
        });

        document.getElementById('massal_items_list').innerHTML = rowsHtml;
        document.getElementById('massal_hidden_inputs').innerHTML = hiddenInputsHtml;

        new bootstrap.Modal(document.getElementById('modalBayarMassalDetail')).show();
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

    {{-- ══════════════════ DATE RANGE PICKER — FILTER PEMBELIAN ══════════════════ --}}
    <script>
    (function () {
        const btnTrigger   = document.getElementById('pb-btn-date-trigger');
        const popover      = document.getElementById('pb-date-range-popover');
        const inputDari    = document.getElementById('pb_filter_dari');
        const inputSampai  = document.getElementById('pb_filter_sampai');
        const textDisplay  = document.getElementById('pb-text-date-display');
        const displayStart = document.getElementById('pb-display-start');
        const displayEnd   = document.getElementById('pb-display-end');
        const calTitle     = document.getElementById('pb-cal-title');
        const calDaysGrid  = document.getElementById('pb-cal-days');
        const btnPrevMonth = document.getElementById('pb-cal-prev');
        const btnNextMonth = document.getElementById('pb-cal-next');
        const btnApply     = document.getElementById('pb-btn-apply-date');
        const btnReset     = document.getElementById('pb-btn-reset-date');

        const monthNames = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];

        let activeYear  = new Date().getFullYear();
        let activeMonth = new Date().getMonth();
        let selStart    = inputDari  ? inputDari.value  : '';
        let selEnd      = inputSampai ? inputSampai.value : '';

        function fmtYMD(d) {
            if (!d) return '';
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        }
        function fmtDMY(ymd) {
            if (!ymd) return '';
            let p = ymd.split('-');
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : ymd;
        }

        function updateTriggerDisplay() {
            let d = inputDari  ? inputDari.value  : '';
            let s = inputSampai ? inputSampai.value : '';
            if (d && s) {
                textDisplay.innerText = d === s ? fmtDMY(d) : `${fmtDMY(d)} - ${fmtDMY(s)}`;
            } else if (d) {
                textDisplay.innerText = `Dari ${fmtDMY(d)}`;
            } else {
                textDisplay.innerText = 'Semua Tanggal';
            }
        }

        function updateSummaryInputs() {
            if (displayStart) displayStart.value = fmtDMY(selStart);
            if (displayEnd)   displayEnd.value   = fmtDMY(selEnd || selStart);
        }

        function renderCalendar() {
            if (!calTitle || !calDaysGrid) return;
            calTitle.innerText = `${monthNames[activeMonth]} ${activeYear}`;
            calDaysGrid.innerHTML = '';

            let firstDay   = new Date(activeYear, activeMonth, 1).getDay();
            let totalDays  = new Date(activeYear, activeMonth + 1, 0).getDate();
            let prevTotal  = new Date(activeYear, activeMonth, 0).getDate();

            for (let x = firstDay; x > 0; x--) {
                let el = document.createElement('div');
                el.className = 'py-1 text-muted opacity-25 small';
                el.innerText = prevTotal - x + 1;
                calDaysGrid.appendChild(el);
            }

            for (let i = 1; i <= totalDays; i++) {
                let ymd = `${activeYear}-${String(activeMonth+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
                let el  = document.createElement('div');
                el.className = 'py-1 rounded-2 small fw-semibold';
                el.innerText = i;
                el.style.cursor = 'pointer';

                let isStart  = (ymd === selStart);
                let isEnd    = (ymd === (selEnd || selStart));
                let inRange  = selStart && selEnd && ymd > selStart && ymd < selEnd;

                if (isStart || isEnd) {
                    el.classList.add('bg-primary','text-white','shadow-sm');
                } else if (inRange) {
                    el.classList.add('bg-primary-subtle','text-primary-emphasis');
                } else {
                    el.classList.add('text-dark');
                    el.addEventListener('mouseenter', () => el.classList.add('bg-light'));
                    el.addEventListener('mouseleave', () => el.classList.remove('bg-light'));
                }

                el.addEventListener('click', function () {
                    if (!selStart || (selStart && selEnd)) {
                        selStart = ymd; selEnd = '';
                    } else {
                        if (ymd < selStart) { selEnd = selStart; selStart = ymd; }
                        else                 { selEnd = ymd; }
                    }
                    updateSummaryInputs();
                    renderCalendar();
                });
                calDaysGrid.appendChild(el);
            }
        }

        function applyPreset(key) {
            let now = new Date(), y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
            let dow = now.getDay(), s, e;
            switch (key) {
                case 'today':      s = e = new Date(y,m,d); break;
                case 'yesterday':  s = e = new Date(y,m,d-1); break;
                case 'this_week':  let dm = d-(dow===0?6:dow-1); s=new Date(y,m,dm); e=new Date(y,m,dm+6); break;
                case 'last_week':  let dlm = d-(dow===0?6:dow-1)-7; s=new Date(y,m,dlm); e=new Date(y,m,dlm+6); break;
                case 'this_month': s=new Date(y,m,1); e=new Date(y,m+1,0); break;
                case 'last_month': s=new Date(y,m-1,1); e=new Date(y,m,0); break;
                case 'this_year':  s=new Date(y,0,1); e=new Date(y,11,31); break;
                case 'last_year':  s=new Date(y-1,0,1); e=new Date(y-1,11,31); break;
                default: return;
            }
            selStart = fmtYMD(s); selEnd = fmtYMD(e);
            activeYear = s.getFullYear(); activeMonth = s.getMonth();
            document.querySelectorAll('.pb-btn-preset').forEach(b => { b.classList.remove('btn-primary','text-white'); b.classList.add('btn-outline-secondary'); });
            let ab = document.querySelector(`.pb-btn-preset[data-preset="${key}"]`);
            if (ab) { ab.classList.remove('btn-outline-secondary'); ab.classList.add('btn-primary','text-white'); }
            updateSummaryInputs(); renderCalendar();
        }

        if (btnTrigger) {
            btnTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                let showing = popover.style.display === 'block';
                popover.style.display = showing ? 'none' : 'block';
                if (!showing) { updateSummaryInputs(); renderCalendar(); }
            });
        }
        if (btnPrevMonth) { btnPrevMonth.addEventListener('click', function(e) { e.stopPropagation(); activeMonth--; if (activeMonth<0){activeMonth=11;activeYear--;} renderCalendar(); }); }
        if (btnNextMonth) { btnNextMonth.addEventListener('click', function(e) { e.stopPropagation(); activeMonth++; if (activeMonth>11){activeMonth=0;activeYear++;} renderCalendar(); }); }

        document.querySelectorAll('.pb-btn-preset').forEach(btn => {
            btn.addEventListener('click', function(e) { e.stopPropagation(); applyPreset(this.dataset.preset); });
        });

        if (btnApply) {
            btnApply.addEventListener('click', function() {
                if (inputDari)   inputDari.value   = selStart;
                if (inputSampai) inputSampai.value = selEnd || selStart;
                updateTriggerDisplay();
                popover.style.display = 'none';
                document.getElementById('form-filter-pembelian').submit();
            });
        }
        if (btnReset) {
            btnReset.addEventListener('click', function() {
                selStart = ''; selEnd = '';
                if (inputDari)   inputDari.value   = '';
                if (inputSampai) inputSampai.value = '';
                updateSummaryInputs(); updateTriggerDisplay();
                popover.style.display = 'none';
                document.getElementById('form-filter-pembelian').submit();
            });
        }
        if (popover) { popover.addEventListener('click', e => e.stopPropagation()); }
        document.addEventListener('click', function() { if (popover && popover.style.display==='block') popover.style.display='none'; });

        updateTriggerDisplay();
    })();
    </script>
</x-app-layout>
