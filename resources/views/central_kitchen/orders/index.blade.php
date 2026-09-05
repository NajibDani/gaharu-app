<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .ts-dropdown { z-index: 99999 !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }

        .table-custom-header th {
            background-color: #6a4126 !important;
            color: #ffffff !important;
            font-weight: 600;
            border-bottom: none;
            font-size: 0.78rem;
            padding: 10px 10px;
            white-space: nowrap;
        }
        .table-custom-body td {
            font-size: 0.8rem;
            padding: 8px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            line-height: 1.3;
        }
        .table-custom-body tr:hover td { background-color: #fafafa; }

        .btn-custom-orange { background-color: #db7946; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; transition: all 0.2s; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
        .summary-card { border-radius: 12px; border: 1px solid #eaeaea; background: #ffffff; padding: 14px 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

        .badge-subtle { border-radius: 6px; padding: 3px 9px; font-weight: 600; font-size: 0.7rem; display: inline-block; text-transform: capitalize; line-height: 1.4; }
        .badge-status-pending { background-color: #fef3c7; color: #d97706; }
        .badge-status-proses { background-color: #e0f2fe; color: #0369a1; }
        .badge-status-ready { background-color: #e0e7ff; color: #4338ca; }
        .badge-status-selesai { background-color: #dcfce7; color: #15803d; }
        .badge-status-batal { background-color: #fee2e2; color: #b91c1c; }

        .action-btn-group { display: flex; justify-content: center; align-items: center; gap: 6px; flex-wrap: nowrap; }
        .btn-action-base {
            border-radius: 7px; width: 30px; height: 30px; font-size: 0.82rem;
            border: none; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: all 0.2s; background-color: transparent; flex-shrink: 0;
        }

        .btn-action-eye { background-color: #f0f9ff; color: #0369a1 !important; border: 1px solid #e0f2fe; }
        .btn-action-eye:hover { background-color: #0369a1; color: white !important; }

        .btn-action-delete { background-color: #fef2f2; color: #b91c1c !important; border: 1px solid #fee2e2; cursor: pointer; }
        .btn-action-delete:hover { background-color: #b91c1c; color: white !important; }

        .btn-action-pdf { background-color: #fee2e2; color: #b91c1c !important; border: 1px solid #fca5a5; }
        .btn-action-pdf:hover { background-color: #b91c1c; color: white !important; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important;">

        {{-- ALERT MESSAGES --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 text-sm mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 text-sm mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Central Kitchen Orders</h4>
                <p class="text-muted small mb-0">Kelola permintaan barang & bahan setengah jadi dari Outlet (Gaharu & KeJingga)</p>
            </div>
            <div>
                <button type="button" class="btn btn-custom-orange shadow-sm d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalCreateCkOrder">
                    <i class="bi bi-plus-circle me-2"></i> Buat Pesanan CK
                </button>
            </div>
        </div>

        {{-- ALERT STOK KRITIS OUTLET BANNER (JIKA ADA BAHAN SETENGAH JADI DI BAWAH MINIMUM) --}}
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
                                    Saran Restock Bahan Setengah Jadi di Outlet
                                </h6>
                                <p class="text-muted small mb-0">
                                    Terdapat stok Bahan Setengah Jadi yang berada di bawah batas minimum stock. Klik tombol outlet untuk langsung membuat order restock:
                                </p>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($outletSuggestionsSummary as $sum)
                                <button type="button" class="btn btn-sm btn-outline-dark bg-white fw-bold shadow-sm d-inline-flex align-items-center gap-2 btn-quick-suggest-order"
                                        data-customer-id="{{ $sum['customer_id'] }}"
                                        data-customer-name="{{ $sum['customer_nama'] }}">
                                    <i class="bi bi-cart-plus-fill text-warning"></i>
                                    <span>{{ $sum['customer_nama'] }}</span>
                                    <span class="badge bg-danger text-white rounded-pill">{{ $sum['count'] }} item</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- SUMMARY CARDS --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="summary-card">
                    <span class="text-muted small d-block mb-1 fw-semibold">Total Order CK</span>
                    <h4 class="fw-bold text-dark mb-0">{{ number_format($totalPesanan) }}</h4>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="summary-card">
                    <span class="text-muted small d-block mb-1 fw-semibold">Dalam Proses / Production</span>
                    <h4 class="fw-bold text-warning mb-0">{{ number_format($totalProses) }}</h4>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="summary-card">
                    <span class="text-muted small d-block mb-1 fw-semibold">Selesai / Terkirim</span>
                    <h4 class="fw-bold text-success mb-0">{{ number_format($totalSelesai) }}</h4>
                </div>
            </div>
        </div>

        {{-- TABLE CARD --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom border-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fw-bold mb-0 text-dark">Daftar Central Kitchen Orders</h6>

                <form method="GET" action="{{ route('ck-orders.index') }}" class="d-flex align-items-center" style="max-width: 300px;">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control border-end-0" placeholder="Cari Kode / Outlet..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary border-start-0" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="text-center" style="width: 50px;">NO</th>
                            <th>KODE ORDER</th>
                            <th>OUTLET PEMESAN</th>
                            <th>DIVISI CK</th>
                            <th>TANGGAL ORDER</th>
                            <th>ESTIMASI KIRIM</th>
                            <th class="text-center">STATUS PRODUKSI</th>
                            <th class="text-center" style="width: 140px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="table-custom-body">
                        @forelse($pesanan as $index => $p)
                            <tr>
                                <td class="text-center fw-semibold text-muted">{{ $pesanan->firstItem() + $index }}</td>
                                <td class="fw-bold text-dark">{{ $p->kode_pesanan }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $p->customer->nama ?? '-' }}</span>
                                </td>
                                <td>
                                    @if($p->divisi)
                                        <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;font-size:0.72rem;font-weight:600;">
                                            <i class="bi bi-layers-half me-1"></i>{{ $p->divisi->nama }}
                                        </span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>{{ date('d M Y', strtotime($p->tanggal)) }}</td>
                                <td>
                                    <span class="fw-medium {{ strtotime($p->estimasi_kirim) < strtotime(date('Y-m-d')) ? 'text-danger' : 'text-dark' }}">
                                        {{ date('d M Y', strtotime($p->estimasi_kirim)) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @php
                                        $statusClass = 'badge-status-pending';
                                        if (in_array(strtolower($p->status_pesanan), ['proses', 'diproses', 'ready', 'siap kirim'])) {
                                            $statusClass = 'badge-status-proses';
                                        } elseif (strtolower($p->status_pesanan) == 'selesai') {
                                            $statusClass = 'badge-status-selesai';
                                        } elseif (strtolower($p->status_pesanan) == 'dibatalkan') {
                                            $statusClass = 'badge-status-batal';
                                        }
                                    @endphp
                                    <span class="badge-subtle {{ $statusClass }}">
                                        {{ ucfirst($p->status_pesanan) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="action-btn-group">
                                        <button type="button" class="btn-action-base btn-action-eye" data-bs-toggle="modal" data-bs-target="#modalDetailCkOrder{{ $p->id }}" title="Lihat Detail Pesanan">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="{{ route('ck-orders.cetak-pdf', $p->id) }}" target="_blank" class="btn-action-base btn-action-pdf" title="Cetak PDF">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>

                                        @if(!$p->wo_status)
                                            <form action="{{ route('ck-orders.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus pesanan ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-action-base btn-action-delete" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                    {{-- MODAL DETAIL PESANAN CK --}}
                                    <div class="modal fade text-start" id="modalDetailCkOrder{{ $p->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                <div class="modal-header bg-dark text-white">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-receipt me-2"></i> Detail Pesanan CK: {{ $p->kode_pesanan }}
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-3 p-md-4">
                                                    <div id="po-doc-container-{{ $p->id }}" class="p-3 p-md-4 bg-white rounded-3 border">
                                                        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                                                            <div>
                                                                <h4 class="fw-bold text-dark mb-1">PURCHASE ORDER CENTRAL KITCHEN</h4>
                                                                <h6 class="fw-bold text-primary mb-1">CENTRAL KITCHEN CV GAHARU AGUNG SEJAHTERA</h6>
                                                                <div class="text-muted small">
                                                                    Pengadaan &amp; Distribusi Bahan Setengah Jadi<br>
                                                                    <strong>Divisi CK:</strong> {{ $p->divisi->nama ?? 'Gudang Central Kitchen' }}
                                                                </div>
                                                            </div>
                                                            <div class="text-end">
                                                                <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold mb-2">PURCHASE ORDER</span>
                                                                <div class="font-monospace fw-bold text-dark fs-5">#{{ $p->kode_pesanan }}</div>
                                                                <div class="text-muted small">Tanggal: <strong>{{ date('d M Y', strtotime($p->tanggal)) }}</strong></div>
                                                            </div>
                                                        </div>

                                                        <div class="row g-2 mb-3 p-3 bg-light rounded-3 border">
                                                            <div class="col-6 col-md-3">
                                                                <span class="text-muted small d-block">Outlet Pemesan:</span>
                                                                <strong class="text-dark">{{ $p->customer->nama ?? '-' }}</strong>
                                                            </div>
                                                            <div class="col-6 col-md-3">
                                                                <span class="text-muted small d-block">Tanggal Order:</span>
                                                                <strong class="text-dark">{{ date('d M Y', strtotime($p->tanggal)) }}</strong>
                                                            </div>
                                                            <div class="col-6 col-md-3">
                                                                <span class="text-muted small d-block">Estimasi Kirim:</span>
                                                                <strong class="text-dark">{{ date('d M Y', strtotime($p->estimasi_kirim)) }}</strong>
                                                            </div>
                                                            <div class="col-6 col-md-3">
                                                                <span class="text-muted small d-block">Status Pesanan:</span>
                                                                <span class="badge-subtle {{ $statusClass }}">
                                                                    {{ ucfirst($p->status_pesanan) }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase">Daftar Bahan Setengah Jadi / Barang</h6>
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered align-middle text-center mb-0" style="font-size: 12px;">
                                                                <thead class="table-dark">
                                                                    <tr>
                                                                        <th style="width: 40px;">No</th>
                                                                        <th style="width: 110px;">Kode Item</th>
                                                                        <th class="text-start">Nama Bahan Setengah Jadi</th>
                                                                        <th style="width: 200px;">Konversi Resep / Batch</th>
                                                                        <th style="width: 140px;" class="text-end">Total Target Qty</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($p->details as $idx => $d)
                                                                        @php
                                                                            $resepObj = $d->produk->resepBtklBop ?? null;
                                                                            $outQty = floatval($resepObj->output_qty ?? 0);
                                                                            $outSatuan = $resepObj->satuan_output ?? ($d->produk->satuan ?? '');
                                                                            $resepText = '-';
                                                                            if ($outQty > 0) {
                                                                                $resepCount = $d->qty / $outQty;
                                                                                $resepCountFmt = (fmod($resepCount, 1) == 0) ? number_format($resepCount, 0) : number_format($resepCount, 2, ',', '.');
                                                                                $resepText = $resepCountFmt . ' Resep (@ ' . number_format($outQty, 0, ',', '.') . ' ' . $outSatuan . ')';
                                                                            }
                                                                        @endphp
                                                                        <tr>
                                                                            <td>{{ $idx + 1 }}</td>
                                                                            <td class="font-monospace fw-bold">{{ $d->produk->kode_barang ?? '-' }}</td>
                                                                            <td class="text-start fw-bold text-dark">
                                                                                {{ $d->produk->nama ?? 'N/A' }}
                                                                            </td>
                                                                            <td>
                                                                                @if($outQty > 0)
                                                                                    <span class="badge bg-warning-subtle text-dark border px-2 py-1">
                                                                                        <i class="bi bi-journal-bookmark me-1"></i>{{ $resepText }}
                                                                                    </span>
                                                                                @else
                                                                                    <span class="text-muted small">Standard (Non-Resep)</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-end fw-bold text-dark">
                                                                                {{ (fmod($d->qty, 1) == 0) ? number_format($d->qty, 0, ',', '.') : number_format($d->qty, 2, ',', '.') }} {{ $d->produk->satuan ?? '-' }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        <div class="row text-center mt-4 pt-3 border-top" style="font-size: 11px;">
                                                            <div class="col-4">
                                                                <div class="text-muted">Pemesan (Outlet):</div>
                                                                <div style="height: 35px;"></div>
                                                                <div class="fw-bold text-dark">({{ $p->customer->nama ?? 'Kepala Outlet' }})</div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="text-muted">Central Kitchen:</div>
                                                                <div style="height: 35px;"></div>
                                                                <div class="fw-bold text-dark">( Dapur Pusat CV Gaharu )</div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="text-muted">Gudang &amp; Logistik:</div>
                                                                <div style="height: 35px;"></div>
                                                                <div class="fw-bold text-dark">( Tim Warehouse CK )</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2 justify-content-between">
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-success btn-sm px-3 fw-bold" onclick="downloadCkOrderJpg({{ $p->id }}, '{{ $p->kode_pesanan }}')">
                                                            <i class="bi bi-file-image me-1"></i> Download JPG
                                                        </button>
                                                        <a href="{{ route('ck-orders.cetak-pdf', $p->id) }}" target="_blank" class="btn btn-outline-danger btn-sm px-3 fw-bold">
                                                            <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
                                                        </a>
                                                    </div>
                                                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                    Belum ada data Central Kitchen Orders.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($pesanan->hasPages())
                <div class="card-footer bg-white py-3 border-top border-light">
                    {{ $pesanan->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL BUAT PESANAN CK BARU --}}
    <div class="modal fade text-start" id="modalCreateCkOrder" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-custom-orange text-white" style="background-color: #db7946;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Buat Central Kitchen Order Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form action="{{ route('ck-orders.store') }}" method="POST" id="modal-form-ck-order">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-secondary">Outlet Pemesan <span class="text-danger">*</span></label>
                                <select name="customer_id" id="modal-select-customer" class="form-select rounded-3" required>
                                    <option value="">-- Pilih Outlet Pemesan --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" data-gudang-id="{{ $c->gudang_id ?? '' }}">
                                            {{ $c->nama }} ({{ $c->gudang_nama ?? $c->nama }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Tanggal Order <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Estimasi Kirim <span class="text-danger">*</span></label>
                                <input type="date" name="estimasi_kirim" class="form-control rounded-3" value="{{ date('Y-m-d', strtotime('+1 day')) }}" required>
                            </div>
                            <input type="hidden" name="divisi_id" value="1">
                        </div>

                        {{-- SUGGESTION RESTOCK BOX --}}
                        <div id="modal-suggestion-box" class="mb-4 p-3 rounded-3 border bg-light" style="display: none; border-left: 4px solid #f59e0b !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <strong class="text-dark small d-flex align-items-center">
                                        <i class="bi bi-lightbulb-fill text-warning fs-6 me-2"></i>
                                        Saran Restock Bahan Setengah Jadi (<span id="modal-suggest-outlet-name"></span>)
                                    </strong>
                                    <span class="text-muted small" style="font-size: 0.75rem;">Item di bawah batas minimum stock gudang outlet</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm" id="modal-btn-apply-all-suggestions">
                                    <i class="bi bi-plus-circle-fill me-1"></i> Gunakan Semua Saran Restock
                                </button>
                            </div>
                            <div id="modal-suggestion-list" class="d-flex flex-wrap gap-2 pt-1">
                                <!-- Dynamic suggestion pills -->
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0 small text-uppercase">Daftar Barang / Item Pesanan</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="modal-btn-add-item">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle text-center mb-0" id="modal-table-items">
                                <thead class="bg-light font-weight-bold">
                                    <tr>
                                        <th class="text-start" style="width: 32%;">Nama Bahan</th>
                                        <th style="width: 15%;">Qty</th>
                                        <th style="width: 20%;">Satuan</th>
                                        <th style="width: 28%;">Total Qty (Konversi)</th>
                                        <th style="width: 5%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-item-rows">
                                    <tr>
                                        <td class="text-start">
                                            <select name="produk_id[]" class="form-select form-select-sm modal-select-produk" required>
                                                <option value="">-- Cari / Pilih Barang BSJ --</option>
                                                @foreach($produk as $item)
                                                    @php
                                                        $outQty = floatval($item->resepBtklBop->output_qty ?? 0);
                                                        $outSatuan = $item->resepBtklBop->satuan_output ?? ($item->satuan ?? '');
                                                    @endphp
                                                    <option value="{{ $item->id }}" 
                                                            data-satuan="{{ $item->satuan }}"
                                                            data-output-qty="{{ $outQty }}"
                                                            data-satuan-output="{{ $outSatuan }}">
                                                        {{ $item->kode_barang }} - {{ $item->nama }}
                                                        @if($outQty > 0)
                                                            (1 Resep = {{ number_format($outQty, 0, ',', '.') }} {{ $outSatuan }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0.01" name="qty[]" class="form-control form-control-sm text-end modal-input-qty fw-bold" placeholder="0" required>
                                        </td>
                                        <td>
                                            <select name="order_mode[]" class="form-select form-select-sm modal-select-mode text-center fw-bold">
                                                <option value="resep">Resep</option>
                                                <option value="satuan">Satuan</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="modal-konversi-info small text-start p-1 px-2 bg-light rounded border">
                                                <span class="text-muted">-</span>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger modal-btn-remove-row" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-3">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-custom-orange px-4 fw-bold shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> Simpan Pesanan CK
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function downloadCkOrderJpg(id, kode) {
            const el = document.getElementById('po-doc-container-' + id);
            if (!el) return;
            html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'PO-CentralKitchen-' + kode + '.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('modal-item-rows');
            const btnAdd = document.getElementById('modal-btn-add-item');
            const customerSelect = document.getElementById('modal-select-customer');
            const suggestionBox = document.getElementById('modal-suggestion-box');
            const suggestionList = document.getElementById('modal-suggestion-list');
            const suggestionOutletName = document.getElementById('modal-suggest-outlet-name');
            const btnApplyAll = document.getElementById('modal-btn-apply-all-suggestions');

            let currentSuggestions = [];

            // TomSelect instances map
            const tomSelectInstances = new Map();

            function initTomSelectOnSelect(selectEl) {
                if (!selectEl) return null;
                if (tomSelectInstances.has(selectEl)) {
                    return tomSelectInstances.get(selectEl);
                }
                if (typeof TomSelect === 'undefined') return null;

                // Clean up TomSelect attributes if cloned from an existing row
                delete selectEl.tomselect;
                selectEl.classList.remove('tomselected', 'ts-hidden-accessible');
                selectEl.removeAttribute('id');
                selectEl.removeAttribute('tabindex');
                selectEl.removeAttribute('aria-hidden');
                selectEl.style.display = '';

                const ts = new TomSelect(selectEl, {
                    create: false,
                    placeholder: '-- Cari / Pilih Barang BSJ --',
                    allowEmptyOption: true,
                    dropdownParent: 'body',
                    onChange: function() {
                        const row = selectEl.closest('tr');
                        updateModeOptions(row);
                        updateKonversi(row);
                    }
                });
                tomSelectInstances.set(selectEl, ts);
                return ts;
            }

            function updateModeOptions(row) {
                if (!row) return;
                const selectEl = row.querySelector('.modal-select-produk');
                const modeEl = row.querySelector('.modal-select-mode');
                if (!selectEl || !modeEl) return;

                const selected = selectEl.options[selectEl.selectedIndex];
                const satuanUtama = (selected && selectEl.value) ? (selected.getAttribute('data-satuan') || 'Satuan') : 'Satuan';
                const outputQty = (selected && selectEl.value) ? parseFloat(selected.getAttribute('data-output-qty') || 0) : 0;

                const currentVal = modeEl.value;
                modeEl.innerHTML = '';

                if (outputQty > 0) {
                    const optResep = new Option('Resep', 'resep');
                    modeEl.add(optResep);
                }

                const optSatuan = new Option(satuanUtama.toUpperCase(), 'satuan');
                modeEl.add(optSatuan);

                if (currentVal === 'resep' && outputQty > 0) {
                    modeEl.value = 'resep';
                } else {
                    modeEl.value = 'satuan';
                }
            }

            function updateKonversi(row) {
                if (!row) return;
                const selectEl = row.querySelector('.modal-select-produk');
                const modeEl = row.querySelector('.modal-select-mode');
                const qtyEl = row.querySelector('.modal-input-qty');
                const infoEl = row.querySelector('.modal-konversi-info');

                if (!selectEl || !modeEl || !qtyEl || !infoEl) return;

                const selected = selectEl.options[selectEl.selectedIndex];
                if (!selected || !selectEl.value) {
                    infoEl.innerHTML = '<span class="text-muted">-</span>';
                    return;
                }

                const outputQty = parseFloat(selected.getAttribute('data-output-qty') || 0);
                const outputSatuan = selected.getAttribute('data-satuan-output') || '';
                const satuanUtama = selected.getAttribute('data-satuan') || '';
                const mode = modeEl.value;
                const qtyInput = parseFloat(qtyEl.value || 0);

                if (mode === 'resep' && outputQty > 0) {
                    const totalTarget = qtyInput > 0 ? (qtyInput * outputQty) : 0;
                    infoEl.innerHTML = `
                        <div class="fw-bold text-success" style="font-size: 0.85rem;">${totalTarget.toLocaleString('id-ID')} ${outputSatuan}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">(1 Resep = ${outputQty.toLocaleString('id-ID')} ${outputSatuan})</div>
                    `;
                } else {
                    const resepEquivalent = outputQty > 0 && qtyInput > 0 ? (qtyInput / outputQty) : 0;
                    const resepFmt = (resepEquivalent % 1 === 0) ? resepEquivalent.toFixed(0) : resepEquivalent.toFixed(2);
                    infoEl.innerHTML = `
                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">${qtyInput.toLocaleString('id-ID')} ${satuanUtama || '-'}</div>
                        ${outputQty > 0 && qtyInput > 0 ? `<div class="text-primary" style="font-size: 0.72rem;">(= ${resepFmt} Resep)</div>` : ''}
                    `;
                }
            }

            // Delegasi Event untuk Hapus Baris & Update Dynamic Info
            tableBody.addEventListener('click', function(e) {
                const btnRemove = e.target.closest('.modal-btn-remove-row');
                if (btnRemove && !btnRemove.disabled) {
                    const row = btnRemove.closest('tr');
                    if (row) {
                        const selectEl = row.querySelector('.modal-select-produk');
                        if (selectEl && tomSelectInstances.has(selectEl)) {
                            tomSelectInstances.get(selectEl).destroy();
                            tomSelectInstances.delete(selectEl);
                        }
                        row.remove();
                        checkRows();
                    }
                }
            });

            tableBody.addEventListener('change', function(e) {
                if (e.target.classList.contains('modal-select-produk') || e.target.classList.contains('modal-select-mode')) {
                    updateKonversi(e.target.closest('tr'));
                }
            });

            tableBody.addEventListener('input', function(e) {
                if (e.target.classList.contains('modal-input-qty')) {
                    updateKonversi(e.target.closest('tr'));
                }
            });

            function checkRows() {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(r => {
                    const btnRemove = r.querySelector('.modal-btn-remove-row');
                    if (btnRemove) {
                        btnRemove.disabled = (rows.length === 1);
                    }
                });
            }

            // Tambah baris item baru
            function addItemRow(produkId = '', qty = '', satuan = '') {
                const rows = tableBody.querySelectorAll('tr');
                let targetRow = null;

                if (rows.length === 1) {
                    const firstSelect = rows[0].querySelector('.modal-select-produk');
                    const firstQty = rows[0].querySelector('.modal-input-qty');
                    if (!firstSelect.value && !firstQty.value) {
                        targetRow = rows[0];
                    }
                }

                if (!targetRow) {
                    const firstRow = rows[0];
                    targetRow = firstRow.cloneNode(true);
                    
                    // Cleanup TomSelect artifacts if cloned
                    const tsWrapper = targetRow.querySelector('.ts-wrapper');
                    if (tsWrapper) tsWrapper.remove();
                    const oldSelect = targetRow.querySelector('select.modal-select-produk');
                    if (oldSelect) {
                        delete oldSelect.tomselect;
                        oldSelect.classList.remove('tomselected', 'ts-hidden-accessible');
                        oldSelect.removeAttribute('id');
                        oldSelect.removeAttribute('tabindex');
                        oldSelect.removeAttribute('aria-hidden');
                        oldSelect.style.display = '';
                        oldSelect.value = '';
                    }

                    targetRow.querySelector('.modal-btn-remove-row').removeAttribute('disabled');
                    
                    // Reset values for the new row
                    const modeSelect = targetRow.querySelector('.modal-select-mode');
                    if (modeSelect) modeSelect.value = 'resep';
                    const qtyInput = targetRow.querySelector('.modal-input-qty');
                    if (qtyInput) qtyInput.value = '';
                    const infoBox = targetRow.querySelector('.modal-konversi-info');
                    if (infoBox) infoBox.innerHTML = '<span class="text-muted">-</span>';

                    tableBody.appendChild(targetRow);
                }

                const select = targetRow.querySelector('.modal-select-produk');
                const inputQty = targetRow.querySelector('.modal-input-qty');

                if (inputQty && qty !== '') {
                    inputQty.value = qty;
                }

                const ts = initTomSelectOnSelect(select);
                if (ts) {
                    if (produkId) {
                        ts.setValue(produkId);
                    } else {
                        ts.setValue('', true);
                    }
                } else if (select) {
                    select.value = produkId || '';
                    updateModeOptions(targetRow);
                    updateKonversi(targetRow);
                }

                checkRows();
                return targetRow;
            }

            // Init TomSelect di baris pertama
            document.querySelectorAll('.modal-select-produk').forEach(select => {
                initTomSelectOnSelect(select);
            });

            // Fetch suggestions saat customer dipilih
            function fetchSuggestions(customerId, autoApply = false) {
                if (!customerId) {
                    suggestionBox.style.display = 'none';
                    suggestionList.innerHTML = '';
                    currentSuggestions = [];
                    return;
                }

                fetch("{{ route('ck-orders.suggestions') }}?customer_id=" + customerId)
                    .then(res => res.json())
                    .then(data => {
                        currentSuggestions = data.suggestions || [];
                        suggestionOutletName.innerText = data.outlet_name || '';

                        if (currentSuggestions.length > 0) {
                            suggestionBox.style.display = 'block';
                            suggestionList.innerHTML = '';

                            currentSuggestions.forEach(item => {
                                const pill = document.createElement('div');
                                pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded-3';
                                pill.innerHTML = `
                                    <div class="text-start">
                                        <div class="fw-bold">${item.nama}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            Stok: <span class="text-danger fw-bold">${item.current_stock}</span> / Min: <span class="fw-bold">${item.min_stock}</span> ${item.satuan}
                                            <span class="text-success fw-bold ms-1">(Saran: ${item.suggested_qty} ${item.satuan})</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold btn-add-single-suggest py-1 px-2" style="font-size: 0.75rem;" title="Tambah item ini">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </button>
                                `;

                                pill.querySelector('.btn-add-single-suggest').addEventListener('click', function() {
                                    addItemRow(item.barang_id, item.suggested_qty, item.satuan);
                                    pill.classList.remove('bg-white');
                                    pill.classList.add('bg-warning-subtle');
                                    this.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                                    this.disabled = true;
                                });

                                suggestionList.appendChild(pill);
                            });

                            if (autoApply) {
                                applyAllSuggestions();
                            }
                        } else {
                            suggestionBox.style.display = 'none';
                            suggestionList.innerHTML = '';
                        }
                    })
                    .catch(() => {
                        suggestionBox.style.display = 'none';
                    });
            }

            function applyAllSuggestions() {
                if (!currentSuggestions.length) return;
                
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((r, idx) => {
                    if (idx > 0) r.remove();
                });
                const firstRow = tableBody.querySelector('tr');
                const firstSelect = firstRow.querySelector('.modal-select-produk');
                if (tomSelectInstances.has(firstSelect)) {
                    tomSelectInstances.get(firstSelect).setValue('');
                } else {
                    firstSelect.value = '';
                }
                firstRow.querySelector('.modal-input-qty').value = '';

                currentSuggestions.forEach(item => {
                    addItemRow(item.barang_id, item.suggested_qty, item.satuan);
                });

                suggestionList.querySelectorAll('.btn-add-single-suggest').forEach(btn => {
                    btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                    btn.disabled = true;
                });
            }

            customerSelect.addEventListener('change', function() {
                fetchSuggestions(this.value);
            });

            btnApplyAll.addEventListener('click', applyAllSuggestions);

            if (btnAdd) {
                btnAdd.addEventListener('click', function() {
                    addItemRow();
                });
            }

            // Handler tombol quick suggestion di banner atas
            document.querySelectorAll('.btn-quick-suggest-order').forEach(btn => {
                btn.addEventListener('click', function() {
                    const custId = this.getAttribute('data-customer-id');
                    customerSelect.value = custId;
                    const modalEl = document.getElementById('modalCreateCkOrder');
                    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalInstance.show();
                    fetchSuggestions(custId, true);
                });
            });
        });
    </script>
</x-app-layout>
