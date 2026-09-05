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
    </style>

    @php
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
    @endphp

    <div class="container-fluid px-4 py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="m-0 fw-bold text-dark">Data Pembelian Kejingga (Luar Gaharu)</h4>
                <p class="text-muted small mb-0">Kelola draft permintaan &amp; pembelian bahan luar khusus gudang Kejingga.</p>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">Khusus Gudang KeJingga</span>
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
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kode/supplier..." value="{{ request('search') }}" style="width: 220px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle" style="font-size:13px;">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Supplier / Pemasok</th>
                        <th>Gudang</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Kekurangan</th>
                        <th class="text-center">Pembayaran</th>
                        <th class="text-center">Barang Diterima</th>
                        <th class="text-center" style="min-width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembelian as $item)
                        @php
                            $total = (float) $item->total;
                            if ($item->metode_pembayaran === 'dp') {
                                if ($item->nominal_dp && $item->nominal_dp > 0) {
                                    $nominalDp = (float) $item->nominal_dp;
                                } else {
                                    $persenDp   = (int) ($item->persen_dp ?? 0);
                                    $nominalDp  = $persenDp > 0 ? round($total * $persenDp / 100) : 0;
                                }
                            } else {
                                $nominalDp = 0;
                            }

                            $kekurangan = match(true) {
                                $item->metode_pembayaran === 'cod' => 0,
                                $item->is_lunas                    => 0,
                                $item->metode_pembayaran === 'dp'  => $total - $nominalDp,
                                $item->metode_pembayaran === 'termin' => $total,
                                default => 0,
                            };

                            $adaKekurangan = $kekurangan > 0 && !$item->is_lunas;
                            $isDraft = empty($item->supplier_id) || $total <= 0;
                        @endphp
                        <tr>
                            <td class="font-monospace fw-bold" style="font-size:12px;">{{ $item->kode_pembelian }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d M Y') }}</td>
                            <td>
                                @if($item->supplier)
                                    <span class="fw-semibold text-dark">{{ $item->supplier->nama }}</span>
                                @else
                                    <span class="badge bg-secondary font-normal" style="font-size:11px;">Draft Permintaan Staff</span>
                                @endif
                            </td>
                            <td><span class="badge bg-warning text-dark">{{ $item->gudang->nama ?? 'Gudang KeJingga' }}</span></td>

                            {{-- TOTAL --}}
                            <td class="text-end fw-semibold">
                                @if($item->total > 0)
                                    Rp {{ number_format($item->total, 0, ',', '.') }}
                                @else
                                    <span class="text-muted" style="font-size:11px;">— (Draft)</span>
                                @endif
                            </td>

                            {{-- KEKURANGAN --}}
                            <td class="text-end">
                                @if(!$item->metode_pembayaran)
                                    <span class="text-muted" style="font-size:11px;">—</span>
                                @elseif($item->metode_pembayaran === 'cod' || $item->is_lunas)
                                    <span class="badge bg-success" style="font-size:11px;">Lunas</span>
                                @elseif($adaKekurangan)
                                    <span class="fw-semibold text-danger">
                                        Rp {{ number_format($kekurangan, 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-muted" style="font-size:11px;">—</span>
                                @endif
                            </td>

                            {{-- PEMBAYARAN (BUTTON HANYA SUPER ADMIN) --}}
                            <td class="text-center">
                                @if($item->metode_pembayaran)
                                    @php
                                        $labelMetode = match($item->metode_pembayaran) {
                                            'cod'    => ['text' => 'COD', 'class' => 'bg-success'],
                                            'termin' => ['text' => 'Termin', 'class' => 'bg-warning text-dark'],
                                            'dp'     => ['text' => ($item->nominal_dp && $item->nominal_dp > 0 ? 'DP Rp ' . number_format($item->nominal_dp, 0, ',', '.') : 'DP ' . $item->persen_dp . '%'), 'class' => 'bg-info'],
                                            default  => ['text' => '-', 'class' => 'bg-secondary'],
                                        };
                                    @endphp
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <span class="badge {{ $labelMetode['class'] }}">
                                            {{ $labelMetode['text'] }}
                                        </span>

                                        @if($isSuperAdmin && $adaKekurangan)
                                            <button type="button"
                                                    class="btn btn-sm mt-1"
                                                    style="background:#dd7045; color:#fff; font-size:11px; padding:2px 10px; border-radius:6px;"
                                                    onclick="bukaModalLunasi({{ $item->id }}, '{{ $item->kode_pembelian }}', {{ $kekurangan }}, '{{ addslashes($item->supplier->nama ?? 'Belum Ada Supplier') }}')">
                                                <i class="bi bi-cash me-1"></i>Lunasi
                                            </button>
                                        @elseif($item->is_lunas && $item->metode_pembayaran !== 'cod')
                                            <span class="badge bg-success" style="font-size:10px;">✓ Lunas</span>
                                        @endif
                                    </div>
                                @else
                                    @if($isSuperAdmin)
                                        <button type="button"
                                                class="btn btn-sm"
                                                style="background:#606060; color:#fff; font-size:11px; padding:2px 10px;"
                                                onclick="bukaPembayaran({{ $item->id }}, '{{ $item->kode_pembelian }}', {{ $item->total }})">
                                            + Catat
                                        </button>
                                    @else
                                        <span class="badge bg-secondary" style="font-size:10px;">Belum Dicatat</span>
                                    @endif
                                @endif
                            </td>

                            {{-- BARANG DITERIMA (BUTTON HANYA SUPER ADMIN) --}}
                            <td class="text-center">
                                @php
                                    $totalQty = $item->details->sum('qty');
                                    $totalReceived = $item->details->sum('qty_diterima');
                                    $isPartiallyReceived = $totalReceived > 0 && $totalReceived < $totalQty;
                                @endphp

                                @if($item->is_diterima)
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge bg-success">✓ Diterima</span>
                                        <small class="text-muted mt-1" style="font-size:10px;">
                                            {{ \Carbon\Carbon::parse($item->diterima_at)->format('d M Y') }}
                                        </small>
                                    </div>
                                @else
                                    @if($isPartiallyReceived)
                                        <div class="mb-1">
                                            <span class="badge bg-info text-white">Parsial ({{ number_format($totalReceived, 0) }}/{{ number_format($totalQty, 0) }})</span>
                                        </div>
                                    @endif

                                    @if($isSuperAdmin)
                                        @if(!$item->metode_pembayaran)
                                            <button type="button"
                                                    class="btn btn-sm"
                                                    disabled
                                                    title="Metode pembayaran belum dicatat."
                                                    style="background:#d0d0d0; color:#888; font-size:11px; padding:2px 10px; cursor:not-allowed;">
                                                Terima Barang
                                            </button>
                                        @else
                                            <button type="button"
                                                    class="btn btn-sm text-white"
                                                    style="background:#0284c7; font-size:11px; padding:2px 10px;"
                                                    onclick="bukaModalTerimaBarang({{ $item->id }})">
                                                Terima Barang
                                            </button>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted border" style="font-size:10px;">Belum Diterima</span>
                                    @endif
                                @endif
                            </td>

                            {{-- AKSI --}}
                            <td class="text-center" style="white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                    {{-- Detail Pop-up Modal --}}
                                    <button type="button"
                                            class="btn btn-sm btn-info text-white rounded-2 px-2 py-1"
                                            onclick="bukaModalDetail({{ $item->id }})"
                                            title="Lihat Detail PO (Pop-up)">
                                        <i class="bi bi-eye"></i>
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
                                            <i class="bi bi-pencil-square"></i>
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
                            <td colspan="9" class="text-center py-4 text-muted">Belum ada data pembelian Kejingga.</td>
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

    <!-- MODAL CATAT PEMBAYARAN (KHUSUS SUPER ADMIN) -->
    @if($isSuperAdmin)
    <div class="modal fade" id="modalPembayaran" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Catat Metode Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formPembayaran" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <input type="hidden" id="pembelian_id_bayar" name="pembelian_id">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Kode Pembelian</label>
                            <input type="text" id="kode_pembelian_bayar" class="form-control font-monospace fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Metode Pembayaran</label>
                            <select name="metode_pembayaran" id="metode_pembayaran_select" class="form-select" required onchange="toggleMetodePembayaran(this.value)">
                                <option value="cod">COD (Bayar Lunas Saat Terima)</option>
                                <option value="dp">DP (Uang Muka)</option>
                                <option value="termin">Termin / Kredit</option>
                            </select>
                        </div>
                        <div id="section_dp" class="mb-3" style="display:none;">
                            <label class="form-label small text-muted">Nominal DP (Rp)</label>
                            <input type="number" name="nominal_dp" id="nominal_dp" class="form-control" placeholder="0">
                        </div>
                        <div id="section_termin" class="mb-3" style="display:none;">
                            <label class="form-label small text-muted">Tanggal Jatuh Tempo</label>
                            <input type="date" name="tanggal_jatuh_tempo" id="tanggal_jatuh_tempo" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL LUNASI (KHUSUS SUPER ADMIN) -->
    <div class="modal fade" id="modalLunasi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Pelunasan Pembelian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formLunasi" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <input type="hidden" id="pembelian_id_lunasi" name="pembelian_id">
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-0">Kode Pembelian</label>
                            <input type="text" id="kode_pembelian_lunasi" class="form-control font-monospace fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-0">Total Kekurangan</label>
                            <input type="text" id="kekurangan_text" class="form-control fw-bold text-danger" readonly>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm">Konfirmasi Pelunasan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TERIMA BARANG (KHUSUS SUPER ADMIN) -->
    <div class="modal fade" id="modalTerimaBarang" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Konfirmasi Penerimaan Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formTerimaBarang" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th class="text-center" width="100">Pesanan</th>
                                        <th class="text-center" width="100">Diterima</th>
                                        <th class="text-center" width="120">Input Terima</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyTerimaBarang"></tbody>
                            </table>
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
                <div class="modal-body p-4" id="contentDetail">
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
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark" id="modalEditTitle">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Purchase Order Kejingga
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditModal" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            {{-- SUPPLIER --}}
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small text-dark mb-1">
                                    Supplier / Pemasok Luar 
                                    <span class="text-muted font-normal">(Opsional - Draft)</span>
                                </label>
                                <select name="supplier_id" id="edit_supplier_id" class="form-control">
                                    <option value="">-- Kosongkan (Draft Permintaan) --</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->nama }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- GUDANG --}}
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small text-dark mb-1">Gudang Tujuan Stok</label>
                                <input type="text" class="form-control bg-light fw-bold text-warning" value="{{ $gudangKejingga->nama ?? 'Gudang KeJingga' }}" readonly>
                                <input type="hidden" name="gudang_id" value="5">
                            </div>

                            {{-- TANGGAL --}}
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small text-dark mb-1">Tanggal Transaksi <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold m-0 text-dark">Detail Barang Pembelian &amp; Konversi Satuan</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btn-add-edit-row">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Baris Barang
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="table-edit-items">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Barang &amp; Stok Gudang Kejingga</th>
                                        <th width="130">Qty Input</th>
                                        <th width="170">Pilihan Satuan</th>
                                        <th width="150">Total Qty (Utama)</th>
                                        <th width="160">Total Harga (Rp)</th>
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
                    <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning text-dark btn-sm fw-bold px-4">
                            <i class="bi bi-check-circle me-1"></i> Perbarui Pembelian Kejingga
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
                    <i class="bi bi-box-seam me-1"></i>Persediaan Terkini Gudang Kejingga: 
                    <strong>${b.stok_kejingga.toLocaleString('id-ID')} ${b.satuan_utama}</strong>
                </span>
            `;
        }

        let opts = `<option value="${b.satuan_utama}" data-konversi="1">${b.satuan_utama} (Satuan Utama)</option>`;
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

        tr.innerHTML = `
            <td>
                <select name="items[${editRowIndex}][barang_id]" class="form-control barang-select" required>
                    ${barangOpts}
                </select>
                <div class="stok-info-badge mt-1" style="font-size: 11px;"></div>
            </td>
            <td>
                <input type="text" name="items[${editRowIndex}][qty]" class="form-control qty-input mask-number" value="${d ? formatNumberDisplay(d.qty) : ''}" placeholder="0" required>
            </td>
            <td>
                <select name="items[${editRowIndex}][satuan_pembelian]" class="form-select satuan-select">
                    <option value="">-- Pilih Satuan --</option>
                </select>
                <input type="hidden" name="items[${editRowIndex}][konversi_pembelian]" class="konversi-input" value="${d ? d.konversi_pembelian : 1}">
            </td>
            <td>
                <div class="fw-bold text-dark total-qty-display">—</div>
                <small class="text-muted konversi-info-text d-block" style="font-size: 10px;"></small>
            </td>
            <td>
                <input type="text" name="items[${editRowIndex}][harga]" class="form-control harga-input mask-number" value="${d && d.harga > 0 ? formatNumberDisplay(d.harga) : ''}" placeholder="0 (Opsional)">
            </td>
            <td>
                <input type="text" class="form-control harga-per-qty bg-light" readonly tabindex="-1" placeholder="—">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-edit" title="Hapus Baris"><i class="bi bi-trash"></i></button>
            </td>
        `;

        tbody.appendChild(tr);
        tr.querySelectorAll('.mask-number').forEach(maskInput);

        const sel = tr.querySelector('.barang-select');
        initTomSelect(sel);

        if (d && d.barang_id) {
            updateEditRowBarang(tr, d.barang_id);
            // Select custom unit if specified
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

    document.addEventListener('input', function(e) {
        if (e.target.closest('#table-edit-items') && (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input'))) {
            calcEditRow(e.target.closest('tr'));
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target.closest('#table-edit-items') && e.target.classList.contains('satuan-select')) {
            let tr = e.target.closest('tr');
            let selectedOpt = e.target.options[e.target.selectedIndex];
            let konvVal = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-konversi')) || 1 : 1;
            tr.querySelector('.konversi-input').value = konvVal;
            calcEditRow(tr);
        }
    });

    function bukaPembayaran(id, kode, total) {
        document.getElementById('pembelian_id_bayar').value = id;
        document.getElementById('kode_pembelian_bayar').value = kode;
        document.getElementById('formPembayaran').action = `/pembelian-kejingga/${id}/catat-pembayaran`;
        new bootstrap.Modal(document.getElementById('modalPembayaran')).show();
    }

    function toggleMetodePembayaran(val) {
        document.getElementById('section_dp').style.display = (val === 'dp') ? 'block' : 'none';
        document.getElementById('section_termin').style.display = (val === 'termin') ? 'block' : 'none';
    }

    function bukaModalLunasi(id, kode, sisa, supplier) {
        document.getElementById('pembelian_id_lunasi').value = id;
        document.getElementById('kode_pembelian_lunasi').value = kode;
        document.getElementById('kekurangan_text').value = 'Rp ' + sisa.toLocaleString('id-ID');
        document.getElementById('formLunasi').action = `/pembelian-kejingga/${id}/lunasi`;
        new bootstrap.Modal(document.getElementById('modalLunasi')).show();
    }

    function bukaModalTerimaBarang(id) {
        const item = dataPembayaranMap[id];
        if (!item) return;
        const tbody = document.getElementById('tbodyTerimaBarang');
        tbody.innerHTML = '';
        item.details.forEach(d => {
            const sisa = d.qty - d.qty_diterima;
            tbody.innerHTML += `
                <tr>
                    <td><strong>${d.nama}</strong> (${d.kode_barang})</td>
                    <td class="text-center">${d.qty} ${d.satuan}</td>
                    <td class="text-center">${d.qty_diterima} ${d.satuan}</td>
                    <td>
                        <input type="number" name="qty_diterima[${d.id}]" class="form-control form-control-sm text-center" value="${sisa > 0 ? sisa : 0}" min="0" max="${sisa}">
                    </td>
                </tr>
            `;
        });
        document.getElementById('formTerimaBarang').action = `/pembelian-kejingga/${id}/terima`;
        new bootstrap.Modal(document.getElementById('modalTerimaBarang')).show();
    }

    function generateJpgHtml(item) {
        let detailsHtml = '';
        item.details.forEach((d, idx) => {
            let konvText = d.has_konversi ? `<br><small style="color:#0284c7;">= ${(d.qty * d.konversi_pembelian).toLocaleString('id-ID')} ${d.satuan_utama}</small>` : '';
            detailsHtml += `
                <tr>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;">${idx + 1}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1;"><strong>${d.nama}</strong> (${d.kode_barang})</td>
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
                    <h3 style="margin:0; font-weight:bold; color:#0f172a;">CV GAHARU AGUNG SEJAHTERA</h3>
                    <div style="font-size:12px; color:#475569; margin-top:4px;">Pengadaan & Logistik - Gudang KeJingga</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:18px; font-weight:bold; color:#d97706;">PURCHASE ORDER</div>
                    <div style="font-family:monospace; font-weight:bold; font-size:14px;">#${item.kode}</div>
                    <div style="font-size:12px; color:#475569;">Tanggal: ${item.tanggal}</div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; gap:15px; margin-bottom:15px;">
                <div style="flex:1; background:#f8fafc; border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:12px;">
                    <strong style="color:#64748b; text-transform:uppercase; font-size:10px;">Supplier:</strong><br>
                    <span style="font-weight:bold; font-size:14px;">${item.supplier_nama}</span>
                </div>
                <div style="flex:1; background:#f8fafc; border:1px solid #e2e8f0; padding:10px; border-radius:6px; font-size:12px;">
                    <strong style="color:#64748b; text-transform:uppercase; font-size:10px;">Status Pembayaran:</strong><br>
                    <span>Metode: <strong>${item.label}</strong></span> | <span>Lunas: <strong>${item.is_lunas ? 'YA' : 'BELUM'}</strong></span>
                </div>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:15px;">
                <thead>
                    <tr style="background:#0f172a; color:#ffffff;">
                        <th style="padding:8px; border:1px solid #0f172a;" width="30">No</th>
                        <th style="padding:8px; border:1px solid #0f172a;">Nama Barang</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="130">Stok Kejingga</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="120">Qty Order</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="100">Diterima</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="130">Harga / Satuan</th>
                        <th style="padding:8px; border:1px solid #0f172a;" width="130">Subtotal</th>
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
                <div>Disetujui<br><br><br><strong>( Purchasing )</strong></div>
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
    // BUKA MODAL DETAIL (POP-UP)
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
            detailsHtml += `
                <tr>
                    <td class="text-center">${idx + 1}</td>
                    <td>
                        <div class="fw-bold text-dark">${d.nama}</div>
                        <div class="font-monospace text-muted small">${d.kode_barang}</div>
                    </td>
                    <td class="text-center bg-light fw-semibold">${d.stok_kejingga.toLocaleString('id-ID')} ${d.satuan_utama}</td>
                    <td class="text-center fw-bold">
                        ${d.qty.toLocaleString('id-ID')} ${d.satuan}
                        ${konvInfo}
                    </td>
                    <td class="text-center">${d.qty_diterima.toLocaleString('id-ID')} ${d.satuan}</td>
                    <td class="text-end">
                        ${d.harga > 0 ? 'Rp ' + d.harga_per_qty.toLocaleString('id-ID') + ' / ' + d.satuan : '—'}
                    </td>
                    <td class="text-end fw-bold">
                        ${d.harga > 0 ? 'Rp ' + subtotal.toLocaleString('id-ID') : '—'}
                    </td>
                </tr>
            `;
        });

        let supplierCard = item.supplier_id ? `
            <div class="fw-bold text-dark fs-6">${item.supplier_nama}</div>
            <div class="text-muted small">No. Telp: ${item.supplier_telepon}</div>
            <div class="text-muted small">Alamat: ${item.supplier_alamat}</div>
        ` : `
            <div class="badge bg-secondary px-2 py-1 fs-6">Draft Permintaan (Belum Ada Supplier)</div>
            <div class="text-muted small mt-1">Dibuat oleh staff operasional, menunggu pengisian supplier oleh tim Purchasing.</div>
        `;

        let html = `
            <div id="po-modal-doc-render" class="p-3 bg-white">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">CV. GAHARU AGUNG SEJAHTERA</h4>
                        <div class="text-muted small">
                            Layanan Pengadaan &amp; Logistik Operasional Kejingga<br>
                            <strong>Gudang:</strong> ${item.gudang_nama}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold mb-2">PURCHASE ORDER (PO)</span>
                        <div class="font-monospace fw-bold text-dark fs-5">#${item.kode}</div>
                        <div class="text-muted small">Tanggal: <strong>${item.tanggal}</strong></div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <small class="text-muted fw-bold text-uppercase d-block mb-1">Supplier / Pemasok:</small>
                            ${supplierCard}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <small class="text-muted fw-bold text-uppercase d-block mb-1">Status &amp; Pembayaran:</small>
                            <div class="d-flex flex-column gap-1 small">
                                <div>Metode Pembayaran: <strong class="text-uppercase">${item.label}</strong></div>
                                <div>Status Pelunasan: ${item.is_lunas ? '<span class="badge bg-success">✓ LUNAS</span>' : '<span class="badge bg-danger">BELUM LUNAS</span>'}</div>
                                <div>Status Penerimaan: ${item.is_diterima ? '<span class="badge bg-success">✓ DITERIMA</span>' : '<span class="badge bg-warning text-dark">PROSES PENERIMAAN</span>'}</div>
                                <div>Dibuat Oleh: <strong>${item.user_nama}</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-dark">
                            <tr>
                                <th width="40" class="text-center">No</th>
                                <th>Nama Barang &amp; Kode</th>
                                <th width="150" class="text-center">Stok Gudang Kejingga</th>
                                <th width="140" class="text-center">Qty Dipesan</th>
                                <th width="130" class="text-center">Qty Diterima</th>
                                <th width="150" class="text-end">Harga / Satuan</th>
                                <th width="160" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${detailsHtml}
                        </tbody>
                    </table>
                </div>

                <div class="row mb-3">
                    <div class="col-6 offset-6">
                        <table class="table table-borderless table-sm text-end mb-0">
                            ${item.tax_service > 0 ? `
                                <tr>
                                    <td class="text-muted">Subtotal Barang:</td>
                                    <td class="fw-bold">Rp ${totalItemsCalculated.toLocaleString('id-ID')}</td>
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

    // ==========================================
    // BUKA MODAL EDIT (POP-UP)
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

        document.getElementById('edit_supplier_id').value = item.supplier_id || '';
        document.getElementById('edit_tanggal').value = item.tanggal_raw;
        document.getElementById('edit_tax_service').value = item.tax_service > 0 ? formatNumberDisplay(item.tax_service) : '';

        // Reset table items
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
