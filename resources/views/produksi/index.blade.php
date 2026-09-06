<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F7F5; }
        .table-custom-header th { background-color: #715745 !important; color: #ffffff !important; font-weight: 600; border-bottom: none; font-size: 0.8rem; padding: 12px 10px; }
        .table-custom-body td { font-size: 0.82rem; padding: 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .btn-custom-orange { background-color: #DE8958; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #C87443; color: white; }
        .summary-card { border-radius: 12px; border: 1px solid #DCD3CB; background: #ffffff; padding: 16px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .nav-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 2px solid #DCD3CB; padding-bottom: 2px; }
        .nav-tabs .nav-item { flex-shrink: 0; }
        .nav-tabs .nav-link { color: #64748b; font-weight: 600; font-size: 0.88rem; border: none; border-bottom: 3px solid transparent; padding: 10px 18px; white-space: nowrap; }
        .nav-tabs .nav-link.active { color: #DE8958; border-bottom: 3px solid #DE8958; background: transparent; font-weight: 700; }
        .action-btn { border-radius: 7px; padding: 6px 12px; font-size: 0.82rem; font-weight: 600; min-height: 36px; display: inline-flex; align-items: center; justify-content: center; }
    </style>

    <div class="container-fluid px-2 px-md-4 py-3 mb-5">

        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-dark mb-1">Produksi Cold Kitchen</h4>
                <p class="text-muted small mb-0">Manajemen terpadu Work Order (WO), Alokasi Bahan Baku &amp; Hasil Produksi Cold Kitchen</p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center w-100 w-md-auto justify-content-start justify-content-md-end">
                <form action="{{ route('produksi.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-grow-1 flex-md-grow-0 flex-wrap">
                    @if(request('tab'))
                        <input type="hidden" name="tab" value="{{ request('tab') }}">
                    @endif
                    <select name="customer_id" class="form-select form-select-sm" style="min-width: 170px; border-radius: 8px; border: 1px solid #DCD3CB;" onchange="this.form.submit()">
                        <option value="">-- Semua Customer / Outlet --</option>
                        @if(isset($customers))
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->nama }}</option>
                            @endforeach
                        @endif
                    </select>
                    <input type="text" name="search" class="form-control form-control-sm flex-grow-1" placeholder="Cari kode WO / Produksi..." value="{{ request('search') }}" style="min-width: 180px; border-radius: 8px; border: 1px solid #DCD3CB; padding: 8px 12px;">
                    <button type="submit" class="btn btn-sm btn-custom-orange" style="border-radius: 8px; padding: 8px 16px;">Cari</button>
                    @if(request('search') || request('customer_id'))
                        <a href="{{ route('produksi.index', request('tab') ? ['tab' => request('tab')] : []) }}" class="btn btn-sm btn-secondary" style="border-radius: 8px; padding: 8px 16px;">Reset</a>
                    @endif
                </form>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary text-white rounded-3 shadow-sm px-3 action-btn d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        {{-- SUMMARY CARDS --}}
        <div class="row mb-4 g-3">
            <div class="col-12 col-md-3">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Order Siap Dibuat WO</span>
                    <h4 class="fw-bold text-primary mb-0">{{ $pesananB2BPending->total() }} Pesanan</h4>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Work Order Aktif</span>
                    <h4 class="fw-bold text-dark mb-0">{{ $woList->total() }} WO</h4>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Draft Produksi</span>
                    <h4 class="fw-bold text-warning mb-0">{{ $totalDraft }} Draft</h4>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Produksi Selesai</span>
                    <h4 class="fw-bold text-success mb-0">{{ $totalApproved }} Selesai</h4>
                </div>
            </div>
        </div>

        {{-- TABS NAVIGATION --}}
        @php
            $activeTab = request('tab', 'pending');
        @endphp
        <ul class="nav nav-tabs mb-4" id="b2bProdTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'pending' ? 'active' : '' }}" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-orders" type="button">
                    <i class="bi bi-clock-history me-1"></i> 1. Permintaan Cold Kitchen (Siap Buat WO)
                    @if($pesananB2BPending->total() > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $pesananB2BPending->total() }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'wo' ? 'active' : '' }}" id="wo-tab" data-bs-toggle="tab" data-bs-target="#wo-list" type="button">
                    <i class="bi bi-file-earmark-text me-1"></i> 2. Work Orders (WO Cold Kitchen)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'prod' ? 'active' : '' }}" id="prod-tab" data-bs-toggle="tab" data-bs-target="#prod-history" type="button">
                    <i class="bi bi-check2-all me-1"></i> 3. Riwayat Hasil Produksi Cold Kitchen
                </button>
            </li>
        </ul>

        <div class="tab-content" id="b2bProdTabContent">

            {{-- TAB 1: ORDER COLD KITCHEN MASUK --}}
            <div class="tab-pane fade {{ $activeTab === 'pending' ? 'show active' : '' }}" id="pending-orders" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-dark">Daftar Permintaan Cold Kitchen yang Siap Diproduksi</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold shadow-sm" id="btnMassal" disabled onclick="openReviewMassalModal()">
                            <i class="bi bi-ui-checks me-1"></i> Buat WO Gabungan Terpilih
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <input class="form-check-input border-secondary" type="checkbox" id="checkAll">
                                    </th>
                                    <th style="width: 14%;">KODE PESANAN</th>
                                    <th style="width: 18%;">CUSTOMER</th>
                                    <th style="width: 12%;">ESTIMASI KIRIM</th>
                                    <th>DAFTAR ITEM & TARGET QTY</th>
                                    <th class="text-center" style="width: 12%;">STATUS BAYAR</th>
                                    <th class="text-center" style="width: 180px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body bg-white">
                                @forelse($pesananB2BPending as $index => $p)
                                    <tr class="{{ $p->is_fully_wo ? 'bg-light text-muted opacity-75' : '' }}">
                                        <td class="text-center">
                                            @if(!$p->is_fully_wo)
                                                @foreach($p->details as $d)
                                                    @if(($d->sisa_wo_qty ?? $d->qty) > 0)
                                                        <input class="form-check-input border-secondary checkItem d-none" type="checkbox" name="detail_ids[]" value="{{ $d->id }}"
                                                            data-detail-id="{{ $d->id }}"
                                                            data-pesanan-id="{{ $p->id }}"
                                                            data-pesanan-kode="{{ $p->kode_pesanan }}"
                                                            data-customer="{{ $p->customer->nama ?? ($p->customer->name ?? '-') }}"
                                                            data-produk-id="{{ $d->produk_id }}"
                                                            data-produk-nama="{{ $d->produk->nama ?? 'Produk' }}"
                                                            data-satuan="{{ $d->produk->satuan ?? 'pcs' }}"
                                                            data-sisa-qty="{{ $d->sisa_wo_qty ?? $d->qty }}">
                                                    @endif
                                                @endforeach
                                                <input class="form-check-input border-secondary parentCheck" type="checkbox" data-target="{{ $p->id }}">
                                            @else
                                                <i class="bi bi-check2-circle text-success" title="Semua item sudah dibuatkan WO"></i>
                                            @endif
                                        </td>
                                            <td class="fw-bold text-dark">{{ $p->kode_pesanan }}</td>
                                            <td>
                                                <span class="fw-semibold text-dark">{{ $p->customer->nama ?? ($p->customer->name ?? '-') }}</span>
                                            </td>
                                            <td>
                                                @php
                                                    $tglKirim = \Carbon\Carbon::parse($p->estimasi_kirim)->startOfDay();
                                                    $hariIni = \Carbon\Carbon::now()->startOfDay();
                                                    $selisih = $hariIni->diffInDays($tglKirim, false);
                                                @endphp
                                                @if($selisih < 0)
                                                    <span class="badge bg-danger">Terlambat</span>
                                                @elseif($selisih == 0)
                                                    <span class="badge bg-danger">Hari Ini</span>
                                                @elseif($selisih <= 2)
                                                    <span class="badge bg-warning text-dark">Mepet</span>
                                                @endif
                                                <div class="small text-muted">{{ $tglKirim->format('d M Y') }}</div>
                                            </td>
                                            <td>
                                                <ul class="list-unstyled mb-0 small">
                                                    @foreach($p->details as $d)
                                                        <li>
                                                            <i class="bi bi-dot"></i> {{ $d->produk->nama ?? 'Produk' }} : 
                                                            <strong class="text-primary">{{ number_format($d->qty, 0, ',', '.') }} {{ $d->produk->satuan ?? 'pcs' }}</strong>
                                                            @if(isset($d->qty_sudah_wo) && $d->qty_sudah_wo > 0)
                                                                <span class="badge bg-light text-muted border ms-1" style="font-size: 0.7rem;">
                                                                    (WO: {{ number_format($d->qty_sudah_wo, 0) }} / Sisa: {{ number_format($d->sisa_wo_qty, 0) }})
                                                                </span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                            <td class="text-center">
                                                @if($p->status_pembayaran === 'Lunas')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Lunas</span>
                                                @elseif($p->status_pembayaran === 'DP')
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">DP (Terbayar)</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">{{ $p->status_pembayaran }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" data-bs-toggle="modal" data-bs-target="#modalOrderB2B{{ $p->id }}">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </button>
                                                    @if($p->is_fully_wo)
                                                        <button type="button" class="btn btn-sm btn-secondary rounded-3 fw-semibold opacity-75" disabled title="Work Order sudah lengkap dibuat">
                                                            <i class="bi bi-check-circle me-1"></i> WO Sudah Dibuat
                                                        </button>
                                                    @else
                                                        <form action="{{ route('produksi.store-wo') }}" method="POST" class="d-inline" onsubmit="return confirm('Buat Work Order (WO) untuk pesanan {{ $p->kode_pesanan }}?')">
                                                            @csrf
                                                            <input type="hidden" name="pesanan_id" value="{{ $p->id }}">
                                                            @foreach($p->details as $d)
                                                                <input type="hidden" name="produk_id[]" value="{{ $d->produk_id }}">
                                                                <input type="hidden" name="qty_rencana[]" value="{{ $d->sisa_wo_qty ?? $d->qty }}">
                                                            @endforeach
                                                            <button type="submit" class="btn btn-sm btn-primary rounded-3 fw-semibold">
                                                                <i class="bi bi-gear-fill me-1"></i> Buat WO
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>

                                                {{-- MODAL DETAIL ORDER PENDING B2B --}}
                                                <div class="modal fade text-start" id="modalOrderB2B{{ $p->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                                            <div class="modal-header bg-dark text-white">
                                                                <h6 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i> Detail Pesanan B2B: {{ $p->kode_pesanan }}</h6>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body p-4">
                                                                <div class="mb-3 p-3 bg-light rounded-3">
                                                                    <div class="row g-2 small">
                                                                        <div class="col-6"><strong>Customer:</strong> {{ $p->customer->nama ?? ($p->customer->name ?? '-') }}</div>
                                                                        <div class="col-6"><strong>Estimasi Kirim:</strong> {{ date('d M Y', strtotime($p->estimasi_kirim)) }}</div>
                                                                        <div class="col-6"><strong>Status Pesanan:</strong> <span class="badge bg-warning text-dark">{{ ucfirst($p->status_pesanan) }}</span></div>
                                                                        <div class="col-6"><strong>Status Pembayaran:</strong> <span class="badge bg-success">{{ $p->status_pembayaran }}</span></div>
                                                                    </div>
                                                                </div>
                                                                <h6 class="fw-bold mb-2 small text-uppercase text-secondary">Rincian Item Pesanan</h6>
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered align-middle text-center mb-0">
                                                                        <thead class="bg-light font-weight-bold">
                                                                            <tr>
                                                                                <th class="text-start">Nama Produk</th>
                                                                                <th width="100">Qty Pesan</th>
                                                                                <th width="70">Satuan</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach($p->details as $d)
                                                                                <tr>
                                                                                    <td class="text-start fw-bold">{{ $d->produk->nama ?? 'N/A' }}</td>
                                                                                    <td class="fw-bold text-success">{{ number_format($d->qty, 0, ',', '.') }}</td>
                                                                                    <td>{{ $d->produk->satuan ?? 'pcs' }}</td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light py-2">
                                                                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                                Tidak ada pesanan B2B pending yang perlu dibuatkan Work Order.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                <div class="mt-2">
                    {{ $pesananB2BPending->links() }}
                </div>
            </div>

            {{-- TAB 2: WORK ORDERS LIST (DETAIL & INPUT PRODUKSI VIA POPUP) --}}
            <div class="tab-pane fade {{ $activeTab === 'wo' ? 'show active' : '' }}" id="wo-list" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-dark">Daftar Work Order (WO) B2B</h6>
                        <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm" id="btnBatchProduksiB2B" disabled onclick="openBatchProduksiB2BModal()">
                            <i class="bi bi-layers-fill me-1"></i> Produksi Batch WO Terpilih (<span id="countSelectedWoB2B">0</span>)
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <input class="form-check-input border-secondary" type="checkbox" id="checkAllWoB2B">
                                    </th>
                                    <th class="text-center" style="width: 50px;">NO</th>
                                    <th>KODE WO</th>
                                    <th>CUSTOMER / PESANAN</th>
                                    <th>TANGGAL WO</th>
                                    <th>TARGET & REALISASI</th>
                                    <th>STATUS</th>
                                    <th class="text-center" style="width: 260px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body bg-white">
                                @forelse($woList as $index => $wo)
                                    <tr>
                                        <td class="text-center">
                                            @if(!$wo->is_all_completed)
                                                <input class="form-check-input border-secondary wo-check-b2b" type="checkbox" 
                                                    value="{{ $wo->id }}" 
                                                    data-wo-json='@json($wo)'
                                                    onchange="updateWoBatchSelectionB2B()">
                                            @else
                                                <i class="bi bi-check2-circle text-success" title="WO Selesai"></i>
                                            @endif
                                        </td>
                                        <td class="text-center text-muted">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ $wo->kode_wo }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $wo->customer_nama }}</div>
                                            <div class="text-muted small">Ref: {{ $wo->pesanan_kode }}</div>
                                        </td>
                                        <td>{{ date('d M Y H:i', strtotime($wo->tanggal_wo)) }}</td>
                                        <td>
                                            <div class="small">
                                                <span class="fw-bold text-success">{{ number_format($wo->total_selesai, 0, ',', '.') }}</span> / 
                                                <span class="fw-bold text-dark">{{ number_format($wo->total_target, 0, ',', '.') }}</span>
                                                @if($wo->total_sisa > 0)
                                                    <span class="badge bg-warning text-dark ms-1">Kurang {{ number_format($wo->total_sisa, 0, ',', '.') }}</span>
                                                @else
                                                    <span class="badge bg-success ms-1">Lengkap</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if($wo->is_all_completed || strtolower($wo->status_wo) == 'selesai')
                                                <span class="badge bg-success">Selesai</span>
                                            @elseif($wo->is_bahan_sufficient || strtolower($wo->status_wo) == 'diproses')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Bahan Cukup (Siap)
                                                </span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" title="Bahan baku di Gudang B2B belum mencukupi">
                                                    <i class="bi bi-exclamation-circle me-1"></i> Draft (Bahan Kurang)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                                @if(!$wo->is_all_completed && !$wo->is_bahan_sufficient)
                                                    <form action="{{ route('wo.kirim_produksi', $wo->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Minta bahan baku dari Gudang Utama untuk WO {{ $wo->kode_wo }}?')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning rounded-3 action-btn fw-semibold" title="Minta Bahan Baku ke Gudang Utama">
                                                            <i class="bi bi-box-arrow-right"></i> Minta Bahan
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(!$wo->is_all_completed)
                                                    <button type="button" class="btn btn-sm btn-success rounded-3 action-btn fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalWoB2B{{ $wo->id }}">
                                                        <i class="bi bi-hammer me-1"></i> Input & Approve
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 action-btn" data-bs-toggle="modal" data-bs-target="#modalWoB2B{{ $wo->id }}">
                                                        <i class="bi bi-eye me-1"></i> Detail
                                                    </button>
                                                    <a href="{{ route('pengiriman.index', ['tipe' => 'b2b', 'search' => $wo->kode_wo]) }}" class="btn btn-sm btn-outline-primary rounded-3 action-btn fw-semibold" title="Kirim ke Logistik Outlet">
                                                        <i class="bi bi-truck me-1"></i> Kirim
                                                    </a>
                                                @endif

                                                <a href="{{ route('wo.cetak-pdf', $wo->id) }}" class="btn btn-sm btn-outline-dark rounded-3 action-btn" title="Cetak Surat WO">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>

                                            {{-- MODAL DETAIL & INPUT PRODUKSI WO B2B --}}
                                            <div class="modal fade text-start" id="modalWoB2B{{ $wo->id }}" tabindex="-1" aria-hidden="true">
                                                 <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header text-white" style="background-color: #715745;">
                                                            <h5 class="modal-title fw-bold">
                                                                <i class="bi bi-gear-wide-connected me-2"></i> Detail WO & Hasil Produksi: {{ $wo->kode_wo }}
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>

                                                        @if(!$wo->is_all_completed)
                                                        <form action="{{ route('produksi.store-and-approve') }}" method="POST" onsubmit="return confirm('Simpan hasil produksi & Approve HPP otomatis?')">
                                                            @csrf
                                                            <input type="hidden" name="work_order_id" value="{{ $wo->id }}">

                                                            <div class="modal-body p-4">
                                                                @if(!$wo->is_bahan_sufficient && !empty($wo->defisit_bahan))
                                                                    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                                                                        <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1"></i>
                                                                        <div>
                                                                            <strong>Perhatian Ketersediaan Bahan Baku di Gudang B2B:</strong>
                                                                            <ul class="mb-0 ps-3">
                                                                                @foreach($wo->defisit_bahan as $def)
                                                                                    <li>{{ $def['nama'] }}: Tersedia <strong>{{ $def['stok'] }} {{ $def['satuan'] }}</strong> / Butuh <strong>{{ $def['butuh'] }} {{ $def['satuan'] }}</strong> (Kurang <span class="text-danger fw-bold">{{ $def['kurang'] }} {{ $def['satuan'] }}</span>)</li>
                                                                                @endforeach
                                                                            </ul>
                                                                        </div>
                                                                    </div>
                                                                @else
                                                                    <div class="alert alert-success border-success d-flex align-items-center gap-2 p-2 rounded-3 mb-3 small">
                                                                        <i class="bi bi-check-circle-fill fs-6 text-success"></i>
                                                                        <span><strong>Bahan Baku Siap:</strong> Stok bahan baku di Gudang B2B mencukupi seluruh kebutuhan resep. Anda dapat langsung memproses produksi.</span>
                                                                    </div>
                                                                @endif

                                                                <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-primary">
                                                                    <div class="row g-2 small">
                                                                        <div class="col-md-4">
                                                                            <span class="text-muted d-block">Kode Work Order:</span>
                                                                            <strong class="text-dark">{{ $wo->kode_wo }}</strong>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <span class="text-muted d-block">Customer B2B:</span>
                                                                            <strong class="text-dark">{{ $wo->customer_nama }}</strong>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <span class="text-muted d-block">Tanggal WO:</span>
                                                                            <strong class="text-dark">{{ date('d M Y H:i', strtotime($wo->tanggal_wo)) }}</strong>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row g-3 mb-3">
                                                                    <div class="col-md-6">
                                                                        <label class="form-label fw-bold text-secondary small">Tanggal Hasil Produksi</label>
                                                                        <input type="date" name="tanggal_produksi" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label fw-bold text-secondary small">Gudang Penyimpanan</label>
                                                                        <input type="text" class="form-control bg-light" value="Gudang Produksi / Central Kitchen" readonly>
                                                                    </div>
                                                                </div>

                                                                <h6 class="fw-bold text-dark mb-2 small text-uppercase">Rincian Item & Input Qty Selesai</h6>
                                                                <div class="table-responsive mb-3">
                                                                    <table class="table table-bordered align-middle text-center mb-0">
                                                                        <thead class="bg-light font-weight-bold">
                                                                            <tr>
                                                                                <th style="width: 5%;">No</th>
                                                                                <th class="text-start">Nama Produk</th>
                                                                                <th style="width: 15%;">Target WO</th>
                                                                                <th style="width: 15%;">Sudah Jadi</th>
                                                                                <th style="width: 18%;">Sisa Kekurangan</th>
                                                                                <th style="width: 22%;">Input Qty Selesai</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach($wo->items_progress as $idx => $item)
                                                                                <tr>
                                                                                    <td>{{ $idx + 1 }}</td>
                                                                                    <td class="text-start">
                                                                                        <div class="fw-bold text-dark">{{ $item['nama_produk'] }}</div>
                                                                                        <div class="text-muted small">{{ $item['kode_barang'] }}</div>
                                                                                    </td>
                                                                                    <td class="fw-semibold">{{ number_format($item['target'], 0, ',', '.') }} {{ $item['satuan'] }}</td>
                                                                                    <td class="fw-bold text-success">{{ number_format($item['sudah'], 0, ',', '.') }} {{ $item['satuan'] }}</td>
                                                                                    <td class="fw-bold text-danger">
                                                                                        @if($item['sisa'] > 0)
                                                                                            {{ number_format($item['sisa'], 0, ',', '.') }} {{ $item['satuan'] }}
                                                                                        @else
                                                                                            <span class="badge bg-success">Tercapai</span>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td>
                                                                                        <input type="hidden" name="produk_id[]" value="{{ $item['produk_id'] }}">
                                                                                        @if($item['sisa'] > 0)
                                                                                            <div class="input-group input-group-sm">
                                                                                                <input type="number" name="qty_hasil[]" class="form-control text-end fw-bold" 
                                                                                                    min="0" max="{{ $item['sisa'] }}" step="any" value="{{ $item['sisa'] }}" required>
                                                                                                <span class="input-group-text">{{ $item['satuan'] }}</span>
                                                                                            </div>
                                                                                        @else
                                                                                            <input type="hidden" name="qty_hasil[]" value="0">
                                                                                            <span class="text-muted small">Sudah Selesai</span>
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>

                                                                <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center">
                                                                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                                                                    <div>
                                                                        Menekan tombol <strong>Simpan & Approve HPP</strong> akan menghitung HPP FIFO otomatis, memotong bahan baku, menambah stok jadi, dan mengalokasikan pesanan.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light">
                                                                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-success px-4 fw-bold">
                                                                    <i class="bi bi-check-circle-fill me-1"></i> Simpan & Approve HPP
                                                                </button>
                                                            </div>
                                                        </form>
                                                        @else
                                                            <div class="modal-body p-4">
                                                                @if(strtolower($wo->status_wo) == 'draft')
                                                                    <div class="alert alert-warning d-flex align-items-center mb-3">
                                                                        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                                                                        <div>Work Order ini masih berstatus <strong>Draft</strong>. Silakan klik tombol <strong>Minta Bahan</strong> terlebih dahulu agar bahan baku ditransfer ke bagian produksi.</div>
                                                                    </div>
                                                                @else
                                                                    <div class="alert alert-success d-flex align-items-center mb-3">
                                                                        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                                                                        <div>Seluruh target Work Order ini telah <strong>100% Selesai</strong> diproduksi dan siap dikirim.</div>
                                                                    </div>
                                                                @endif

                                                                <h6 class="fw-bold text-dark mb-2 small text-uppercase">Rincian Item Work Order</h6>
                                                                <div class="table-responsive">
                                                                    <table class="table table-bordered align-middle text-center mb-0">
                                                                        <thead class="bg-light font-weight-bold">
                                                                            <tr>
                                                                                <th style="width: 5%;">No</th>
                                                                                <th class="text-start">Nama Produk</th>
                                                                                <th>Target WO</th>
                                                                                <th>Sudah Jadi</th>
                                                                                <th>Status</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach($wo->items_progress as $idx => $item)
                                                                                <tr>
                                                                                    <td>{{ $idx + 1 }}</td>
                                                                                    <td class="text-start fw-bold text-dark">{{ $item['nama_produk'] }}</td>
                                                                                    <td>{{ number_format($item['target'], 0, ',', '.') }} {{ $item['satuan'] }}</td>
                                                                                    <td class="fw-bold text-success">{{ number_format($item['sudah'], 0, ',', '.') }} {{ $item['satuan'] }}</td>
                                                                                    <td>
                                                                                        @if($item['sisa'] <= 0)
                                                                                            <span class="badge bg-success">Lengkap</span>
                                                                                        @else
                                                                                            <span class="badge bg-warning text-dark">Kurang {{ number_format($item['sisa'], 0, ',', '.') }}</span>
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light">
                                                                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Belum ada Work Order B2B.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-2">
                    {{ $woList->links() }}
                </div>
            </div>

            {{-- TAB 3: RIWAYAT PRODUKSI --}}
            <div class="tab-pane fade {{ $activeTab === 'prod' ? 'show active' : '' }}" id="prod-history" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">Riwayat Hasil Produksi B2B</h6>
                        <a href="{{ route('produksi.create') }}" class="btn btn-sm btn-custom-orange shadow-sm">
                            <i class="bi bi-plus-circle me-1"></i> Input Manual (Custom)
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-custom-body">
                            <thead class="table-custom-header text-center">
                                <tr>
                                    <th width="5%" class="py-3">No</th>
                                    <th width="15%" class="text-start">Kode Produksi</th>
                                    <th width="14%" class="text-start">Customer / Ref</th>
                                    <th width="10%">Tanggal</th>
                                    <th class="text-start">Nama Produk</th>
                                    <th width="10%">Qty Hasil</th>
                                    <th width="12%" class="text-end">HPP Total</th>
                                    <th width="10%">Status</th>
                                    <th width="14%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @php $no = $riwayatProduksi->firstItem() ?? 1; @endphp
                                @forelse($riwayatProduksi as $p)
                                    @php
                                        $rowCount = $p->details->count() ?: 1;
                                    @endphp
                                    @foreach($p->details as $index => $detail)
                                        <tr>
                                            @if($index === 0)
                                                <td rowspan="{{ $rowCount }}" class="text-center text-secondary">{{ $no++ }}</td>
                                                <td rowspan="{{ $rowCount }}" class="text-start fw-bold text-dark">{{ $p->kode_produksi }}</td>
                                                <td rowspan="{{ $rowCount }}" class="text-start text-dark fw-medium">
                                                    {{ $p->pesanan->customer->nama ?? ($p->pesanan->customer->name ?? 'B2B Customer') }}
                                                    <div class="text-muted small">{{ $p->pesanan->kode_pesanan ?? '-' }}</div>
                                                </td>
                                                <td rowspan="{{ $rowCount }}" class="text-center text-secondary">
                                                    {{ $p->tanggal_mulai ? \Carbon\Carbon::parse($p->tanggal_mulai)->format('d/m/Y') : '-' }}
                                                </td>
                                            @endif

                                            <td class="text-start text-dark fw-semibold">{{ $detail->produk->nama ?? 'Produk' }}</td>
                                            <td class="text-center"><span class="badge bg-info-subtle text-info border px-2 py-1">{{ (int) $detail->qty }} Unit</span></td>
                                            <td class="text-end fw-medium text-success">
                                                @if($p->status_produksi === 'Draft')
                                                    <span class="text-muted fst-italic">Menunggu</span>
                                                @else
                                                    Rp {{ number_format($detail->hpp_total ?? 0, 0, ',', '.') }}
                                                @endif
                                            </td>

                                            @if($index === 0)
                                                <td rowspan="{{ $rowCount }}" class="text-center">
                                                    @if($p->status_produksi === 'Draft')
                                                        <span class="badge bg-warning-subtle text-warning border px-2 py-1"><i class="bi bi-file-earmark-text"></i> Draft</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success border px-2 py-1"><i class="bi bi-check-all"></i> Selesai</span>
                                                    @endif
                                                </td>
                                                <td rowspan="{{ $rowCount }}" class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <a href="{{ route('produksi.show', $p->id) }}" class="btn btn-sm btn-info text-white shadow-sm action-btn" title="Lihat Detail">
                                                            <i class="bi bi-eye"></i>
                                                        </a>

                                                        @if($p->status_produksi === 'Draft')
                                                            <a href="{{ route('produksi.edit', $p->id) }}" class="btn btn-sm btn-warning text-dark shadow-sm action-btn" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <form action="{{ route('produksi.approve', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve produksi ini?')">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-success text-white shadow-sm action-btn" title="Approve">
                                                                    <i class="bi bi-check-lg"></i>
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('produksi.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus draft produksi ini?')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger text-white shadow-sm action-btn" title="Hapus">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a href="{{ route('produksi.cetak-pdf', $p->id) }}" class="btn btn-sm btn-outline-dark shadow-sm action-btn" title="Cetak PDF">
                                                                <i class="bi bi-printer"></i>
                                                            </a>
                                                            <a href="{{ route('pengiriman.index', ['tipe' => 'b2b', 'search' => $p->pesanan->kode_pesanan ?? $p->kode_produksi]) }}" class="btn btn-sm btn-outline-primary shadow-sm action-btn fw-semibold" title="Kirim ke Logistik Outlet">
                                                                <i class="bi bi-truck"></i> Kirim
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Belum ada riwayat hasil produksi.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-2">
                    {{ $riwayatProduksi->links() }}
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL BATCH PRODUKSI B2B --}}
    <div class="modal fade text-start" id="modalBatchProduksiB2B" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header text-white" style="background-color: #715745;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-layers-fill me-2"></i> Produksi Batch Work Order (Cold Kitchen / B2B)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('produksi.store-and-approve') }}" method="POST" onsubmit="return confirm('Simpan hasil produksi batch & Approve HPP otomatis untuk semua WO terpilih?')">
                    @csrf
                    <div id="containerHiddenWoIdsB2B"></div>

                    <div class="modal-body p-4">
                        <div id="batchB2BDefisitAlert"></div>

                        <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-primary">
                            <div class="small">
                                <span class="text-muted d-block fw-semibold mb-1">Daftar Work Order Terpilih (<span id="batchB2BWoCount">0</span> WO):</span>
                                <div id="batchB2BWoListPills" class="d-flex flex-wrap gap-1"></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary small">Tanggal Hasil Produksi</label>
                                <input type="date" name="tanggal_produksi" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary small">Gudang Penyimpanan</label>
                                <input type="text" class="form-control bg-light" value="Gudang Produksi / Cold Kitchen" readonly>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-2 small text-uppercase">Rekapitulasi Item & Input Qty Selesai Batch</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle text-center mb-0">
                                <thead class="bg-light font-weight-bold">
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th class="text-start">Nama Produk</th>
                                        <th style="width: 15%;">Target Total</th>
                                        <th style="width: 15%;">Sudah Jadi</th>
                                        <th style="width: 18%;">Total Sisa</th>
                                        <th style="width: 22%;">Input Qty Selesai</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyBatchB2BItems">
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center">
                            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                            <div>
                                Menekan <strong>Simpan Batch & Approve HPP</strong> akan memotong stok bahan baku resep secara agregat (FIFO), mengalokasikan hasil produksi secara berurutan ke masing-masing WO/Pesanan terpilih, dan memperbarui status WO secara otomatis.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Simpan Batch & Approve HPP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL REVIEW & SESUAIKAN QTY WO GABUNGAN --}}
    <div class="modal fade text-start" id="modalReviewWoGabungan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header text-white" style="background-color: #715745;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-ui-checks me-2"></i> Review &amp; Sesuaikan Qty Work Order Gabungan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('wo.store_massal') }}" method="POST" onsubmit="return confirm('Simpan & Terbitkan Work Order Gabungan ini?')">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-primary">
                            <h6 class="fw-bold mb-1 text-primary">Daftar Item Pesanan Terpilih</h6>
                            <small class="text-muted">Periksa kembali item pesanan B2B dan sesuaikan Qty Rencana Produksi jika diperlukan.</small>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle text-center mb-0">
                                <thead class="table-custom-header">
                                    <tr>
                                        <th style="width: 15%;">KODE PESANAN</th>
                                        <th style="width: 25%;">CUSTOMER</th>
                                        <th class="text-start">PRODUK</th>
                                        <th style="width: 18%;">SISA KEBUTUHAN</th>
                                        <th style="width: 22%;">QTY PRODUKSI (EDIT)</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyReviewMassalItems">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Simpan Work Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- SCRIPT UNTUK CHECKBOX MASSAL, BATCH PRODUKSI & TAB HANDLING --}}
    <script>
        function openReviewMassalModal() {
            const checkedDetails = document.querySelectorAll('.checkItem:checked');
            if (checkedDetails.length === 0) return;

            const tbody = document.getElementById('tbodyReviewMassalItems');
            tbody.innerHTML = '';

            checkedDetails.forEach(chk => {
                const pId = chk.getAttribute('data-pesanan-id');
                const pKode = chk.getAttribute('data-pesanan-kode');
                const cust = chk.getAttribute('data-customer');
                const prdId = chk.getAttribute('data-produk-id');
                const prdNama = chk.getAttribute('data-produk-nama');
                const satuan = chk.getAttribute('data-satuan');
                const sisa = chk.getAttribute('data-sisa-qty');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-bold text-dark">${pKode}
                        <input type="hidden" name="pesanan_id[]" value="${pId}">
                        <input type="hidden" name="produk_id[]" value="${prdId}">
                    </td>
                    <td class="fw-semibold text-dark">${cust}</td>
                    <td class="text-start fw-bold text-dark">${prdNama}</td>
                    <td><span class="badge bg-info-subtle text-info border px-2 py-1 fw-bold">${parseFloat(sisa).toLocaleString('id-ID')} ${satuan}</span></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" name="qty_rencana[]" class="form-control text-end fw-bold" 
                                min="0.01" max="${sisa}" step="any" value="${sisa}" required>
                            <span class="input-group-text">${satuan}</span>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            const modal = new bootstrap.Modal(document.getElementById('modalReviewWoGabungan'));
            modal.show();
        }

        function updateWoBatchSelectionB2B() {
            const checks = document.querySelectorAll('.wo-check-b2b:checked');
            const btn = document.getElementById('btnBatchProduksiB2B');
            const countSpan = document.getElementById('countSelectedWoB2B');
            const checkAll = document.getElementById('checkAllWoB2B');

            if (countSpan) countSpan.textContent = checks.length;
            if (btn) btn.disabled = (checks.length === 0);

            const allChecks = document.querySelectorAll('.wo-check-b2b');
            if (checkAll && allChecks.length > 0) {
                checkAll.checked = (checks.length === allChecks.length);
            }
        }

        function openBatchProduksiB2BModal() {
            const selectedChecks = document.querySelectorAll('.wo-check-b2b:checked');
            if (selectedChecks.length === 0) return;

            const hiddenContainer = document.getElementById('containerHiddenWoIdsB2B');
            const woListPills = document.getElementById('batchB2BWoListPills');
            const woCountSpan = document.getElementById('batchB2BWoCount');
            const alertDiv = document.getElementById('batchB2BDefisitAlert');
            const tbody = document.getElementById('tbodyBatchB2BItems');

            hiddenContainer.innerHTML = '';
            woListPills.innerHTML = '';
            tbody.innerHTML = '';
            alertDiv.innerHTML = '';

            woCountSpan.textContent = selectedChecks.length;

            let consolidatedProducts = {};
            let defisitBahanMap = {};
            let hasDefisit = false;

            selectedChecks.forEach(chk => {
                const woData = JSON.parse(chk.getAttribute('data-wo-json'));
                
                // Hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'work_order_ids[]';
                input.value = woData.id;
                hiddenContainer.appendChild(input);

                // Badge pill
                const pill = document.createElement('span');
                pill.className = 'badge bg-secondary text-white me-1 mb-1 p-2 font-monospace';
                pill.textContent = woData.kode_wo + ' (' + (woData.customer_nama || '-') + ')';
                woListPills.appendChild(pill);

                // Check defisit bahan
                if (!woData.is_bahan_sufficient && woData.defisit_bahan && woData.defisit_bahan.length > 0) {
                    hasDefisit = true;
                    woData.defisit_bahan.forEach(def => {
                        const key = def.nama;
                        if (!defisitBahanMap[key]) {
                            defisitBahanMap[key] = { nama: def.nama, stok: def.stok, butuh: 0, kurang: 0, satuan: def.satuan };
                        }
                        defisitBahanMap[key].butuh += parseFloat(def.butuh || 0);
                        defisitBahanMap[key].kurang += parseFloat(def.kurang || 0);
                    });
                }

                // Consolidate items
                if (woData.items_progress) {
                    woData.items_progress.forEach(item => {
                        const pId = item.produk_id;
                        if (!consolidatedProducts[pId]) {
                            consolidatedProducts[pId] = {
                                produk_id: pId,
                                nama_produk: item.nama_produk,
                                kode_barang: item.kode_barang,
                                satuan: item.satuan,
                                target: 0,
                                sudah: 0,
                                sisa: 0
                            };
                        }
                        consolidatedProducts[pId].target += parseFloat(item.target || 0);
                        consolidatedProducts[pId].sudah += parseFloat(item.sudah || 0);
                        consolidatedProducts[pId].sisa += parseFloat(item.sisa || 0);
                    });
                }
            });

            // Render Alert Defisit
            if (hasDefisit) {
                let listHtml = '<ul class="mb-0 ps-3">';
                Object.values(defisitBahanMap).forEach(def => {
                    listHtml += `<li>${def.nama}: Tersedia <strong>${def.stok} ${def.satuan}</strong> / Combined Butuh <strong>${def.butuh} ${def.satuan}</strong> (Kurang <span class="text-danger fw-bold">${def.kurang} ${def.satuan}</span>)</li>`;
                });
                listHtml += '</ul>';
                alertDiv.innerHTML = `
                    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                        <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1"></i>
                        <div>
                            <strong>Perhatian Aggregat Ketersediaan Bahan Baku di Gudang Cold Kitchen:</strong>
                            ${listHtml}
                        </div>
                    </div>
                `;
            } else {
                alertDiv.innerHTML = `
                    <div class="alert alert-success border-success d-flex align-items-center gap-2 p-2 rounded-3 mb-3 small">
                        <i class="bi bi-check-circle-fill fs-6 text-success"></i>
                        <span><strong>Bahan Baku Siap:</strong> Stok bahan baku di Gudang Cold Kitchen mencukupi seluruh kebutuhan gabungan Work Order terpilih.</span>
                    </div>
                `;
            }

            // Render consolidated products table
            let idx = 1;
            Object.values(consolidatedProducts).forEach(item => {
                const tr = document.createElement('tr');
                const sisaDisplay = item.sisa > 0 ? item.sisa.toLocaleString('id-ID') + ' ' + item.satuan : '<span class="badge bg-success">Tercapai</span>';
                
                let inputCol = '';
                if (item.sisa > 0) {
                    inputCol = `
                        <input type="hidden" name="produk_id[]" value="${item.produk_id}">
                        <div class="input-group input-group-sm">
                            <input type="number" name="qty_hasil[]" class="form-control text-end fw-bold" 
                                min="0" max="${item.sisa}" step="any" value="${item.sisa}" required>
                            <span class="input-group-text">${item.satuan}</span>
                        </div>
                    `;
                } else {
                    inputCol = `
                        <input type="hidden" name="produk_id[]" value="${item.produk_id}">
                        <input type="hidden" name="qty_hasil[]" value="0">
                        <span class="text-muted small">Sudah Selesai</span>
                    `;
                }

                tr.innerHTML = `
                    <td>${idx++}</td>
                    <td class="text-start">
                        <div class="fw-bold text-dark">${item.nama_produk}</div>
                        <div class="text-muted small">${item.kode_barang || ''}</div>
                    </td>
                    <td class="fw-semibold">${item.target.toLocaleString('id-ID')} ${item.satuan}</td>
                    <td class="fw-bold text-success">${item.sudah.toLocaleString('id-ID')} ${item.satuan}</td>
                    <td class="fw-bold text-danger">${sisaDisplay}</td>
                    <td>${inputCol}</td>
                `;
                tbody.appendChild(tr);
            });

            const modal = new bootstrap.Modal(document.getElementById('modalBatchProduksiB2B'));
            modal.show();
        }

        document.addEventListener("DOMContentLoaded", function () {
            // Checkbox Massal WO
            const checkAll = document.getElementById("checkAll");
            const parentChecks = document.querySelectorAll(".parentCheck");
            const btnMassal = document.getElementById("btnMassal");

            function toggleBtnMassal() {
                const anyChecked = Array.from(parentChecks).some(c => c.checked);
                if (btnMassal) btnMassal.disabled = !anyChecked;
            }

            if (checkAll) {
                checkAll.addEventListener("change", function () {
                    parentChecks.forEach(pc => {
                        pc.checked = checkAll.checked;
                        const hiddenChecks = pc.closest('td').querySelectorAll('.checkItem');
                        hiddenChecks.forEach(hc => hc.checked = checkAll.checked);
                    });
                    toggleBtnMassal();
                });
            }

            parentChecks.forEach(pc => {
                pc.addEventListener("change", function () {
                    const hiddenChecks = pc.closest('td').querySelectorAll('.checkItem');
                    hiddenChecks.forEach(hc => hc.checked = pc.checked);
                    toggleBtnMassal();
                });
            });

            // Master checkbox batch WO B2B
            const checkAllWoB2B = document.getElementById('checkAllWoB2B');
            if (checkAllWoB2B) {
                checkAllWoB2B.addEventListener('change', function () {
                    const allChecks = document.querySelectorAll('.wo-check-b2b');
                    allChecks.forEach(c => c.checked = checkAllWoB2B.checked);
                    updateWoBatchSelectionB2B();
                });
            }

            // Handle active tab from URL query
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const triggerEl = document.querySelector(`#b2bProdTab button[data-bs-target="#${tabParam === 'wo' ? 'wo-list' : (tabParam === 'prod' ? 'prod-history' : 'pending-orders')}"]`);
                if (triggerEl) {
                    const tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                }
            }
        });
    </script>
</x-app-layout>