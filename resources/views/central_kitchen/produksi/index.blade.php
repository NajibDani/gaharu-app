<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F7F5; }
        .table-custom-header th { background-color: #715745 !important; color: #ffffff !important; font-weight: 600; border-bottom: none; font-size: 0.8rem; padding: 12px 10px; white-space: nowrap; }
        .table-custom-body td { font-size: 0.82rem; padding: 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .btn-custom-orange { background-color: #DE8958; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #C87443; color: white; }
        .nav-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 2px solid #DCD3CB; padding-bottom: 2px; scrollbar-width: none; }
        .nav-tabs::-webkit-scrollbar { display: none; }
        .nav-tabs .nav-item { flex-shrink: 0; }
        .nav-tabs .nav-link { color: #64748b; font-weight: 600; font-size: 0.85rem; border: none; border-bottom: 3px solid transparent; padding: 10px 16px; white-space: nowrap; }
        .nav-tabs .nav-link.active { color: #DE8958; border-bottom: 3px solid #DE8958; background: transparent; font-weight: 700; }

        /* Action Box & Responsive Grid */
        .action-box { min-width: 175px; max-width: 220px; margin: 0 auto; }
        .action-box .btn-group .btn { border-radius: 6px; }
        .action-box .btn-group > .btn:not(:first-child) { margin-left: -1px; }

        @media (max-width: 767.98px) {
            .table-custom-header th { padding: 10px 8px; font-size: 0.75rem; }
            .table-custom-body td { padding: 8px 6px; font-size: 0.78rem; }
            .action-box { min-width: 160px; }
        }

        /* Reset white-space agar modal di dalam table cell tidak mewarisi text-nowrap */
        .modal, .modal-dialog, .modal-content, .modal-header, .modal-body, .modal-footer, .modal-body * {
            white-space: normal;
        }
        .modal-body .table th, .modal-body .badge, .modal-body .text-nowrap {
            white-space: nowrap !important;
        }
    </style>

    <div class="container-fluid px-2 px-md-4 py-3">

        <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 flex-column flex-sm-row gap-3">
            <div>
                <h4 class="fw-bold text-dark mb-1">Central Kitchen Production</h4>
                <p class="text-muted small mb-0">Manajemen Work Order (WO) &amp; Hasil Produksi Central Kitchen</p>
            </div>
            <form action="{{ route('ck-produksi.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-wrap w-100 w-sm-auto">
                <select name="customer_id" class="form-select form-select-sm flex-grow-1" style="min-width: 180px; border-radius: 8px; border: 1px solid #DCD3CB; height: 36px;" onchange="this.form.submit()">
                    <option value="">-- Semua Outlet Pemesan --</option>
                    @if(isset($customers))
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->nama }}</option>
                        @endforeach
                    @endif
                </select>
                @if(request('customer_id'))
                    <a href="{{ route('ck-produksi.index') }}" class="btn btn-sm btn-secondary d-inline-flex align-items-center" style="border-radius: 8px; height: 36px; padding: 0 14px;">Reset</a>
                @endif
            </form>
        </div>

        {{-- TABS NAVIGATION --}}
        @php
            $activeTab = request('tab', 'pending');
        @endphp
        <ul class="nav nav-tabs mb-4" id="ckTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'pending' ? 'active' : '' }}" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-orders" type="button">
                    <i class="bi bi-clock-history me-1"></i> Order CK Masuk (Siap Buat WO)
                    @if($pesananCkPending->total() > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $pesananCkPending->total() }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'wo' ? 'active' : '' }}" id="wo-tab" data-bs-toggle="tab" data-bs-target="#wo-list" type="button">
                    <i class="bi bi-file-earmark-text me-1"></i> Work Orders (WO CK)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'prod' ? 'active' : '' }}" id="prod-tab" data-bs-toggle="tab" data-bs-target="#prod-history" type="button">
                    <i class="bi bi-check2-all me-1"></i> Riwayat Produksi CK
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $activeTab === 'stok' ? 'active' : '' }}" id="stok-tab" data-bs-toggle="tab" data-bs-target="#stok-divisi" type="button">
                    <i class="bi bi-box-seam me-1"></i> Stok BSJ Central Kitchen
                </button>
            </li>
        </ul>

        <div class="tab-content" id="ckTabContent">

            {{-- TAB 1: ORDER CK MASUK --}}
            <div class="tab-pane fade {{ $activeTab === 'pending' ? 'show active' : '' }}" id="pending-orders" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4">
                        <h6 class="fw-bold mb-0 text-dark">Pesanan Central Kitchen yang Siap Diproduksi</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center text-nowrap" style="width: 45px;">NO</th>
                                    <th class="text-nowrap">KODE ORDER</th>
                                    <th class="text-nowrap">OUTLET PEMESAN</th>
                                    <th class="text-nowrap">ESTIMASI KIRIM</th>
                                    <th class="text-center text-nowrap">TOTAL ITEM</th>
                                    <th class="text-center text-nowrap" style="width: 200px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body">
                                @forelse($pesananCkPending as $index => $p)
                                    <tr>
                                        <td class="text-center fw-semibold text-muted text-nowrap">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark text-nowrap">{{ $p->kode_pesanan }}</td>
                                        <td class="text-nowrap"><span class="badge bg-light text-dark border">{{ $p->customer->nama ?? '-' }}</span></td>
                                        <td class="text-nowrap">{{ date('d M Y', strtotime($p->estimasi_kirim)) }}</td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge bg-secondary-subtle text-dark border px-3 py-2 fw-bold" style="font-size: 12px;">
                                                <i class="bi bi-boxes me-1 text-primary"></i> {{ $p->details->count() }} Item Pesanan
                                            </span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="action-box d-flex justify-content-center">
                                                <div class="btn-group btn-group-sm w-100 shadow-sm" role="group">
                                                    <button type="button" class="btn btn-outline-secondary fw-semibold d-flex align-items-center justify-content-center gap-1 py-1" style="height: 32px; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalOrder{{ $p->id }}">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </button>
                                                    <button type="button" class="btn btn-custom-orange fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 text-white" style="height: 32px; font-size: 0.8rem;" onclick="if(confirm('Buat Work Order (WO) untuk pesanan {{ $p->kode_pesanan }}?')) document.getElementById('formStoreWo{{ $p->id }}').submit();">
                                                        <i class="bi bi-gear-fill"></i> Buat WO
                                                    </button>
                                                </div>
                                                <form id="formStoreWo{{ $p->id }}" action="{{ route('ck-produksi.store-wo') }}" method="POST" class="d-none">
                                                    @csrf
                                                    <input type="hidden" name="pesanan_id" value="{{ $p->id }}">
                                                    @foreach($p->details as $d)
                                                        <input type="hidden" name="produk_id[]" value="{{ $d->produk_id }}">
                                                        <input type="hidden" name="qty_rencana[]" value="{{ $d->qty }}">
                                                    @endforeach
                                                </form>
                                            </div>

                                            {{-- MODAL DETAIL ORDER PENDING --}}
                                            <div class="modal fade text-start" id="modalOrder{{ $p->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-dark text-white py-3">
                                                            <h6 class="modal-title fw-bold mb-0"><i class="bi bi-receipt me-2"></i> Detail Pesanan CK: {{ $p->kode_pesanan }}</h6>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="mb-3 p-3 bg-light rounded-3">
                                                                <div class="row g-2 small">
                                                                    <div class="col-6"><strong>Outlet Pemesan:</strong> {{ $p->customer->nama ?? '-' }}</div>
                                                                    <div class="col-6"><strong>Estimasi Kirim:</strong> {{ date('d M Y', strtotime($p->estimasi_kirim)) }}</div>
                                                                    <div class="col-12"><strong>Status:</strong> <span class="badge bg-warning text-dark">{{ ucfirst($p->status_pesanan) }}</span></div>
                                                                </div>
                                                            </div>
                                                            <h6 class="fw-bold mb-2 small text-uppercase text-secondary"><i class="bi bi-boxes me-1 text-primary"></i> Rincian Barang / Item Pesanan</h6>
                                                            <div class="table-responsive border rounded-3">
                                                                <table class="table table-sm table-hover align-middle text-center mb-0" style="font-size: 13px;">
                                                                    <thead class="table-light font-weight-bold">
                                                                        <tr>
                                                                            <th class="text-start">Nama Produk</th>
                                                                            <th width="120" class="text-end">Qty (Gram / Dasar)</th>
                                                                            <th width="190" class="text-center">Konversi (Per Resep)</th>
                                                                            <th width="120" class="text-end">Stok Gudang</th>
                                                                            <th width="120" class="text-center">Kekurangan</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($p->details as $d)
                                                                            @php
                                                                                $outQty = floatval($d->produk->resepBtklBop->output_qty ?? 0);
                                                                                $outSatuan = $d->produk->resepBtklBop->satuan_output ?? ($d->produk->satuan ?? 'GR');
                                                                                $resepCount = $outQty > 0 ? ($d->qty / $outQty) : 0;
                                                                                $resepFmt = (fmod($resepCount, 1) == 0) ? number_format($resepCount, 0, ',', '.') : number_format($resepCount, 2, ',', '.');
                                                                            @endphp
                                                                            <tr>
                                                                                <td class="text-start fw-bold text-dark">{{ $d->produk->nama ?? 'N/A' }}</td>
                                                                                <td class="text-end fw-bold text-dark">
                                                                                    {{ (fmod($d->qty, 1) == 0) ? number_format($d->qty, 0, ',', '.') : number_format($d->qty, 2, ',', '.') }} {{ $d->produk->satuan ?? 'GR' }}
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    @if($outQty > 0)
                                                                                        <span class="badge bg-warning-subtle text-dark border px-2 py-1">
                                                                                            <i class="bi bi-journal-bookmark me-1"></i>{{ $resepFmt }} Resep (@ {{ number_format($outQty, 0, ',', '.') }} {{ $outSatuan }})
                                                                                        </span>
                                                                                    @else
                                                                                        <span class="text-muted small">Standard (Non-Resep)</span>
                                                                                    @endif
                                                                                </td>
                                                                                <td class="text-end text-muted">
                                                                                    {{ (fmod($d->stok_tersedia ?? 0, 1) == 0) ? number_format($d->stok_tersedia ?? 0, 0, ',', '.') : number_format($d->stok_tersedia ?? 0, 2, ',', '.') }} {{ $d->produk->satuan ?? 'GR' }}
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    @if(($d->qty_kurang ?? 0) > 0)
                                                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">
                                                                                            {{ (fmod($d->qty_kurang, 1) == 0) ? number_format($d->qty_kurang, 0, ',', '.') : number_format($d->qty_kurang, 2, ',', '.') }} {{ $d->produk->satuan ?? 'GR' }}
                                                                                        </span>
                                                                                    @else
                                                                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-lg me-1"></i>Stok Cukup</span>
                                                                                    @endif
                                                                                </td>
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
                                        <td colspan="6" class="text-center py-4 text-muted">Tidak ada Order CK pending.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($pesananCkPending->hasPages())
                        <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                            {{ $pesananCkPending->appends(array_merge(request()->query(), ['tab' => 'pending']))->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- TAB 2: WO LIST (DETAIL & INPUT PRODUKSI VIA POPUP) --}}
            <div class="tab-pane fade {{ $activeTab === 'wo' ? 'show active' : '' }}" id="wo-list" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-dark">Daftar Work Order Central Kitchen</h6>
                        <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm w-100 w-sm-auto" id="btnBatchProduksiCk" disabled onclick="openBatchProduksiCkModal()">
                            <i class="bi bi-layers-fill me-1"></i> Produksi Batch WO Terpilih (<span id="countSelectedWoCk">0</span>)
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <input class="form-check-input border-secondary" type="checkbox" id="checkAllWoCk">
                                    </th>
                                    <th class="text-center text-nowrap" style="width: 45px;">NO</th>
                                    <th class="text-nowrap">KODE WO</th>
                                    <th class="text-nowrap">OUTLET PEMESAN</th>
                                    <th class="text-nowrap">TANGGAL WO</th>
                                    <th class="text-nowrap">TARGET &amp; REALISASI</th>
                                    @if($isSuperAdmin)
                                        <th class="text-center text-nowrap" style="min-width: 140px;">REKAP BAHAN CK</th>
                                    @endif
                                    <th class="text-nowrap text-center">STATUS</th>
                                    <th class="text-center text-nowrap" style="width: 210px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body">
                                @forelse($woList as $index => $wo)
                                    <tr>
                                        <td class="text-center">
                                            @if(!$wo->is_all_completed)
                                                <input class="form-check-input border-secondary wo-check-ck" type="checkbox" 
                                                    value="{{ $wo->id }}" 
                                                    data-wo-json='@json($wo)'
                                                    onchange="updateWoBatchSelectionCk()">
                                            @else
                                                <i class="bi bi-check2-circle text-success" title="WO Selesai"></i>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark text-nowrap">{{ $wo->kode_wo }}</td>
                                        <td class="text-nowrap"><span class="badge bg-light text-dark border">{{ $wo->customer_nama }}</span></td>
                                        <td class="text-nowrap">{{ date('d M Y H:i', strtotime($wo->tanggal_wo)) }}</td>
                                        <td class="text-nowrap">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="small">
                                                    <span class="fw-bold text-success">{{ number_format($wo->total_selesai, 0, ',', '.') }}</span> / 
                                                    <span class="fw-bold text-dark">{{ number_format($wo->total_target, 0, ',', '.') }}</span>
                                                    @if($wo->total_sisa > 0)
                                                        <span class="badge bg-warning text-dark ms-1">Kurang {{ number_format($wo->total_sisa, 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="badge bg-success ms-1">Lengkap</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        @if($isSuperAdmin)
                                            <td class="text-center text-nowrap">
                                                @if(!empty($wo->rekap_bahan) && count($wo->rekap_bahan) > 0)
                                                    @if(($wo->total_bahan_kurang ?? 0) > 0)
                                                        <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" style="border-radius: 6px; font-size: 0.76rem;" data-bs-toggle="modal" data-bs-target="#modalRekapBahan{{ $wo->id }}" title="Klik untuk lihat detail rekap bahan baku & ketersediaan gudang CK">
                                                            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                                                            <span>{{ $wo->total_jenis_bahan }} Bahan</span>
                                                            <span class="badge bg-danger text-white ms-1">{{ $wo->total_bahan_kurang }} Kurang</span>
                                                        </button>
                                                    @else
                                                        <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" style="border-radius: 6px; font-size: 0.76rem;" data-bs-toggle="modal" data-bs-target="#modalRekapBahan{{ $wo->id }}" title="Klik untuk lihat detail rekap bahan baku & ketersediaan gudang CK">
                                                            <i class="bi bi-check-circle-fill text-success"></i>
                                                            <span>{{ $wo->total_jenis_bahan }} Bahan</span>
                                                            <span class="badge bg-success text-white ms-1">Cukup</span>
                                                        </button>
                                                    @endif
                                                @elseif($wo->has_missing_resep ?? false)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-journal-x me-1"></i> Resep Belum Ada
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-muted border py-1 px-2" style="font-size: 0.75rem;">-</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="text-center text-nowrap">
                                            @if($wo->is_all_completed || strtolower($wo->status_wo) == 'selesai')
                                                <span class="badge bg-success"><i class="bi bi-check-all me-1"></i> Selesai</span>
                                            @elseif(($wo->has_missing_resep ?? false) && !($wo->is_bahan_sufficient ?? true))
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="Menu belum ada resep &amp; stok bahan kurang">
                                                    <i class="bi bi-x-circle me-1"></i> Resep &amp; Bahan Belum Siap
                                                </span>
                                            @elseif($wo->has_missing_resep ?? false)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="Menu belum memiliki formulasi resep">
                                                    <i class="bi bi-journal-x me-1"></i> Belum Ada Resep
                                                </span>
                                            @elseif(!($wo->is_bahan_sufficient ?? true))
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" title="Bahan baku di Gudang CK belum mencukupi">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> Bahan Kurang
                                                </span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Siap Produksi
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="action-box d-flex flex-column gap-1">
                                                {{-- 1. TOMBOL UTAMA (PRIMARY) --}}
                                                @if(!$wo->is_all_completed)
                                                    @if($wo->can_approve ?? false)
                                                        <button type="button" class="btn btn-sm btn-success w-100 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1 py-1 px-2" style="border-radius: 7px; height: 32px; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalWo{{ $wo->id }}">
                                                            <i class="bi bi-hammer"></i> Input &amp; Approve
                                                        </button>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-secondary w-100 fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 px-2" style="border-radius: 7px; height: 32px; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalWo{{ $wo->id }}" title="Approval terkunci: Resep belum lengkap atau bahan baku kurang">
                                                            <i class="bi bi-lock-fill"></i> Input &amp; Approve
                                                        </button>
                                                    @endif
                                                @else
                                                    <div class="btn-group btn-group-sm w-100 shadow-sm" role="group">
                                                        <button type="button" class="btn btn-outline-secondary fw-semibold d-flex align-items-center justify-content-center gap-1 py-1" style="height: 32px; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalWo{{ $wo->id }}">
                                                            <i class="bi bi-eye"></i> Detail
                                                        </button>
                                                        <a href="{{ route('pengiriman.index', ['tipe' => 'central_kitchen', 'search' => $wo->kode_wo]) }}" class="btn btn-custom-orange fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 text-white" style="height: 32px; font-size: 0.8rem;" title="Kirim ke Logistik Outlet">
                                                            <i class="bi bi-truck"></i> Kirim
                                                        </a>
                                                    </div>
                                                @endif

                                                {{-- 2. TOMBOL PENDUKUNG / PRASYARAT (AUXILIARY) --}}
                                                @php
                                                    $needResep = !$wo->is_all_completed && ($wo->has_missing_resep ?? false);
                                                    $needBahan = !$wo->is_all_completed && !($wo->is_bahan_sufficient ?? true);
                                                    $canEditQty = auth()->user() && auth()->user()->canEditWoQty() && !($wo->is_terkirim ?? false);
                                                @endphp
                                                @if($needResep || $needBahan || $canEditQty)
                                                    <div class="btn-group btn-group-sm w-100" role="group">
                                                        @if($needResep)
                                                            <a href="{{ route('resep.create') }}" target="_blank" class="btn btn-outline-danger fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 px-1" style="font-size: 0.74rem;" title="Menu belum ada resep, klik untuk buat formulasi resep">
                                                                <i class="bi bi-journal-plus"></i> Resep
                                                            </a>
                                                        @endif

                                                        @if($needBahan)
                                                            <button type="button" class="btn btn-outline-warning text-dark fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 px-1" style="font-size: 0.74rem;" onclick="if(confirm('Minta bahan baku dari Gudang Utama untuk WO ini?')) document.getElementById('formMintaBahanCk{{ $wo->id }}').submit();" title="Minta Bahan Baku ke Gudang Utama">
                                                                <i class="bi bi-box-arrow-right"></i> Bahan
                                                            </button>
                                                        @endif

                                                        @if($canEditQty)
                                                            <button type="button" class="btn btn-outline-secondary fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 px-1" style="font-size: 0.74rem;" data-bs-toggle="modal" data-bs-target="#modalEditQty{{ $wo->id }}" title="Edit Kuantitas WO">
                                                                <i class="bi bi-pencil-square"></i> Edit Qty
                                                            </button>
                                                        @endif
                                                    </div>

                                                    @if($needBahan)
                                                        <form id="formMintaBahanCk{{ $wo->id }}" action="{{ route('ck-produksi.kirim-bahan', $wo->id) }}" method="POST" class="d-none">
                                                            @csrf
                                                        </form>
                                                    @endif
                                                @endif

                                                {{-- 3. TOMBOL REKAP BAHAN KHUSUS SUPERADMIN --}}
                                                @if($isSuperAdmin && !empty($wo->rekap_bahan) && count($wo->rekap_bahan) > 0)
                                                    <button type="button" class="btn btn-outline-info text-dark fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 w-100" style="font-size: 0.74rem; border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#modalRekapBahan{{ $wo->id }}" title="Lihat Rekap Total Bahan Baku & Ketersediaan di Gudang CK">
                                                        <i class="bi bi-card-checklist text-primary"></i> Rekap Bahan
                                                    </button>
                                                @endif

                                                {{-- 4. TOMBOL HAPUS WO (HANYA JIKA BELUM TERKIRIM - KARENA KESALAHAN PRODUKSI) --}}
                                                @if(($canDeleteWo ?? $isSuperAdmin) && ($wo->is_belum_terkirim ?? true))
                                                    <form action="{{ route('ck-produksi.destroy-wo', $wo->id) }}" method="POST" class="d-inline w-100" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Work Order {{ $wo->kode_wo }} karena kesalahan produksi? Status pesanan akan dikembalikan ke antrean Order Masuk (Pending). Tindakan ini tidak dapat dibatalkan.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 w-100" style="font-size: 0.74rem; border-radius: 6px;" title="Hapus WO yang belum terkirim (karena kesalahan produksi)">
                                                            <i class="bi bi-trash"></i> Hapus WO
                                                        </button>
                                                    </form>
                                                @endif
                                                </div>
                                            </div>

                                            {{-- MODAL DETAIL & INPUT PRODUKSI WO --}}
                                            <div class="modal fade text-start" id="modalWo{{ $wo->id }}" tabindex="-1" aria-hidden="true" style="white-space: normal !important;">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
                                                    <div class="modal-content border-0 shadow-lg rounded-4" style="white-space: normal !important;">
                                                        <div class="modal-header {{ ($wo->can_approve ?? false) ? 'bg-success' : 'bg-dark' }} text-white">
                                                            <h5 class="modal-title fw-bold">
                                                                <i class="bi bi-gear-wide-connected me-2"></i> Detail Work Order & Input Hasil Produksi: {{ $wo->kode_wo }}
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>

                                                        @if(!$wo->is_all_completed)
                                                        <form action="{{ route('ck-produksi.store-and-approve') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="work_order_id" value="{{ $wo->id }}">

                                                            <div class="modal-body p-4">
                                                                {{-- ALERT PERINGATAN: MENU BELUM MEMILIKI RESEP --}}
                                                                @if($wo->has_missing_resep ?? false)
                                                                    <div class="alert alert-danger border-danger d-flex align-items-start gap-2 p-3 rounded-3 mb-3 small">
                                                                        <i class="bi bi-x-circle-fill fs-5 text-danger mt-1 flex-shrink-0"></i>
                                                                        <div class="flex-grow-1">
                                                                            <strong class="d-block mb-1">Approval Dikunci - Menu Belum Memiliki Resep:</strong>
                                                                            <span>Menu berikut belum memiliki formulasi resep bahan baku sehingga HPP tidak dapat dihitung dan proses produksi tidak dapat di-approve:</span>
                                                                            <ul class="mb-2 mt-1 ps-3">
                                                                                @foreach($wo->produk_tanpa_resep ?? [] as $ptr)
                                                                                    <li><strong>{{ $ptr['nama_produk'] }}</strong> ({{ $ptr['kode_barang'] }})</li>
                                                                                @endforeach
                                                                            </ul>
                                                                            <a href="{{ route('resep.create') }}" target="_blank" class="btn btn-sm btn-danger fw-semibold">
                                                                                <i class="bi bi-journal-plus me-1"></i> Isi Resep Menu Sekarang
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                {{-- ALERT PERINGATAN: BAHAN BAKU KURANG --}}
                                                                @if(!($wo->is_bahan_sufficient ?? true) && !empty($wo->defisit_bahan))
                                                                    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-3 rounded-3 mb-3 small">
                                                                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning mt-1 flex-shrink-0"></i>
                                                                        <div class="flex-grow-1">
                                                                            <strong class="d-block mb-1">Perhatian - Bahan Baku di Central Kitchen Kurang:</strong>
                                                                            <span>Stok bahan baku di Gudang Central Kitchen belum mencukupi untuk memenuhi kebutuhan produksi WO ini:</span>
                                                                            <ul class="mb-2 mt-1 ps-3">
                                                                                @foreach($wo->defisit_bahan as $def)
                                                                                    <li>{{ $def['nama'] }}: Tersedia <strong>{{ number_format($def['stok'], 0, ',', '.') }} {{ $def['satuan'] }}</strong> / Butuh <strong>{{ number_format($def['butuh'], 0, ',', '.') }} {{ $def['satuan'] }}</strong> (Kurang <span class="text-danger fw-bold">{{ number_format($def['kurang'], 0, ',', '.') }} {{ $def['satuan'] }}</span>)</li>
                                                                                @endforeach
                                                                            </ul>
                                                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                                                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" onclick="if(confirm('Minta bahan baku dari Gudang Utama untuk WO ini?')) document.getElementById('formMintaBahanCk{{ $wo->id }}').submit();">
                                                                                    <i class="bi bi-box-arrow-right me-1"></i> Minta Bahan ke Gudang Utama
                                                                                </button>
                                                                            </div>

                                                                            @if($isSuperAdmin)
                                                                                <div class="form-check mt-2 pt-2 border-top border-warning-subtle">
                                                                                    <input class="form-check-input check-override-stok" type="checkbox" name="override_stok" value="1" id="overrideStokWo{{ $wo->id }}" data-wo-id="{{ $wo->id }}" data-has-missing-resep="{{ ($wo->has_missing_resep ?? false) ? '1' : '0' }}">
                                                                                    <label class="form-check-label fw-bold text-dark small" for="overrideStokWo{{ $wo->id }}">
                                                                                        <i class="bi bi-shield-check text-success me-1"></i> Setujui &amp; Lanjutkan Produksi (Override Stok Kurang - Khusus Super Admin)
                                                                                    </label>
                                                                                    <div class="text-muted mt-0" style="font-size: 11px;">
                                                                                        Centang opsi ini jika fisik bahan ada di dapur namun belum selesai di-stock opname pada sistem. HPP otomatis dihitung menggunakan <strong>harga terakhir bahan baku</strong>.
                                                                                    </div>
                                                                                </div>
                                                                            @else
                                                                                <div class="mt-2 pt-2 border-top border-warning-subtle text-muted" style="font-size: 11px;">
                                                                                    <i class="bi bi-info-circle me-1"></i> Jika stok fisik ada namun belum selesai stock opname, hubungi <strong>Super Admin</strong> untuk menyetujui produksi dengan override stok.
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                {{-- ALERT SUKSES: SIAP APPROVE --}}
                                                                @if(!($wo->has_missing_resep ?? false) && ($wo->is_bahan_sufficient ?? true))
                                                                    <div class="alert alert-success border-success d-flex align-items-center gap-2 p-2.5 rounded-3 mb-3 small">
                                                                        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                                                                        <span><strong>Resep & Bahan Baku Siap:</strong> Seluruh menu memiliki resep dan stok bahan baku di Gudang Central Kitchen mencukupi. Anda dapat langsung memproses approval produksi.</span>
                                                                    </div>
                                                                @endif

                                                                {{-- RINGKASAN WO --}}
                                                                <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-success">
                                                                    <div class="row g-2 small">
                                                                        <div class="col-md-4">
                                                                            <span class="text-muted d-block">Kode Work Order:</span>
                                                                            <strong class="text-dark">{{ $wo->kode_wo }}</strong>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <span class="text-muted d-block">Outlet Pemesan:</span>
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
                                                                        <label class="form-label fw-bold text-secondary small">Gudang Penyimpanan Hasil</label>
                                                                        <input type="text" class="form-control bg-light" value="Gudang Central Kitchen (Siap Kirim ke {{ $wo->customer_nama }})" readonly>
                                                                    </div>
                                                                </div>

                                                                {{-- TABEL INPUT PER PRODUK --}}
                                                                <h6 class="fw-bold text-dark mb-2 small text-uppercase">Rincian Item & Input Qty Selesai</h6>
                                                                <div class="table-responsive mb-3">
                                                                    <table class="table table-bordered align-middle text-center mb-0" style="min-width: 820px;">
                                                                        <thead class="bg-light font-weight-bold">
                                                                            <tr>
                                                                                <th style="width: 45px;">No</th>
                                                                                <th class="text-start" style="min-width: 200px;">Nama Produk</th>
                                                                                <th style="width: 155px; min-width: 135px;">Target WO</th>
                                                                                <th style="width: 110px; min-width: 90px;">Sudah Jadi</th>
                                                                                <th style="width: 155px; min-width: 135px;">Sisa Kekurangan</th>
                                                                                <th style="width: 230px; min-width: 210px;">Input Qty Selesai</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach($wo->items_progress as $idx => $item)
                                                                                <tr>
                                                                                    <td>{{ $idx + 1 }}</td>
                                                                                    <td class="text-start">
                                                                                        <div class="fw-bold text-dark">{{ $item['nama_produk'] }}</div>
                                                                                        <div class="text-muted small">{{ $item['kode_barang'] }}</div>
                                                                                        @if(!($item['has_resep'] ?? true))
                                                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 11px;">
                                                                                                <i class="bi bi-journal-x"></i> Belum ada resep
                                                                                            </span>
                                                                                            <a href="{{ route('resep.create') }}" target="_blank" class="text-danger small ms-1 fw-semibold text-decoration-underline" style="font-size: 11px;">
                                                                                                Isi Resep
                                                                                            </a>
                                                                                        @else
                                                                                            <span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 11px;">
                                                                                                <i class="bi bi-check2"></i> Resep Siap
                                                                                            </span>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="fw-semibold">
                                                                                        <div>{{ number_format($item['target'], 0, ',', '.') }} {{ $item['satuan'] }}</div>
                                                                                        @if(!empty($item['satuan_pembelian']) && floatval($item['konversi']) > 1)
                                                                                            @php $targetPack = $item['target'] / $item['konversi']; @endphp
                                                                                            <div class="small text-primary font-monospace" style="font-size: 11px;">
                                                                                                ({{ number_format($targetPack, ($targetPack == intval($targetPack) ? 0 : 2), ',', '.') }} {{ $item['satuan_pembelian'] }} @ {{ number_format($item['konversi'], 0, ',', '.') }} {{ $item['satuan'] }})
                                                                                            </div>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="fw-bold text-success">
                                                                                        <div>{{ number_format($item['sudah'], 0, ',', '.') }} {{ $item['satuan'] }}</div>
                                                                                        @if(!empty($item['satuan_pembelian']) && floatval($item['konversi']) > 1 && $item['sudah'] > 0)
                                                                                            @php $sudahPack = $item['sudah'] / $item['konversi']; @endphp
                                                                                            <div class="small text-muted font-monospace" style="font-size: 11px;">
                                                                                                ({{ number_format($sudahPack, ($sudahPack == intval($sudahPack) ? 0 : 2), ',', '.') }} {{ $item['satuan_pembelian'] }})
                                                                                            </div>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="fw-bold text-danger">
                                                                                        @if($item['sisa'] > 0)
                                                                                            <div>{{ number_format($item['sisa'], 0, ',', '.') }} {{ $item['satuan'] }}</div>
                                                                                            @if(!empty($item['satuan_pembelian']) && floatval($item['konversi']) > 1)
                                                                                                @php $sisaPack = $item['sisa'] / $item['konversi']; @endphp
                                                                                                <div class="small text-danger font-monospace" style="font-size: 11px;">
                                                                                                    ({{ number_format($sisaPack, ($sisaPack == intval($sisaPack) ? 0 : 2), ',', '.') }} {{ $item['satuan_pembelian'] }})
                                                                                                </div>
                                                                                            @endif
                                                                                        @else
                                                                                            <span class="badge bg-success">Tercapai</span>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td style="min-width: 210px; width: 230px;">
                                                                                        <input type="hidden" name="produk_id[]" value="{{ $item['produk_id'] }}">
                                                                                        @php
                                                                                            $hasKonversi = !empty($item['satuan_pembelian']) && floatval($item['konversi']) > 1;
                                                                                            $valSisa = $item['sisa'] > 0 ? floatval($item['sisa']) : floatval($item['target']);
                                                                                        @endphp
                                                                                        <div class="input-group input-group-sm flex-nowrap shadow-sm" style="min-width: 190px;">
                                                                                            <input type="number" name="qty_hasil[]" class="form-control text-end fw-bold input-qty-hasil-ck px-2" 
                                                                                                style="min-width: 100px;"
                                                                                                min="0" step="any" value="{{ $valSisa }}" 
                                                                                                data-konversi="{{ $hasKonversi ? floatval($item['konversi']) : 1 }}"
                                                                                                data-satuan-dasar="{{ $item['satuan'] }}"
                                                                                                data-satuan-konv="{{ $hasKonversi ? $item['satuan_pembelian'] : '' }}" required>
                                                                                            @if($hasKonversi)
                                                                                                <select name="satuan_input[]" class="form-select select-unit-hasil-ck fw-bold text-center bg-light text-primary" style="width: 85px; flex: 0 0 85px; padding-left: 6px; padding-right: 20px; font-size: 0.78rem;">
                                                                                                    <option value="dasar">{{ strtoupper($item['satuan']) }}</option>
                                                                                                    <option value="konversi">{{ strtoupper($item['satuan_pembelian']) }}</option>
                                                                                                </select>
                                                                                            @else
                                                                                                <input type="hidden" name="satuan_input[]" value="dasar">
                                                                                                <span class="input-group-text bg-light fw-bold text-muted" style="width: 58px; flex: 0 0 58px; justify-content: center; font-size: 0.78rem;">{{ strtoupper($item['satuan']) }}</span>
                                                                                            @endif
                                                                                        </div>
                                                                                        <div class="live-konversi-info small text-end mt-1 font-monospace" style="font-size: 11px; display: none;"></div>
                                                                                        @if($item['sisa'] <= 0)
                                                                                            <small class="text-success d-block text-end mt-1" style="font-size: 11px;">Target awal sudah tercapai</small>
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>

                                                                @if($isSuperAdmin && !empty($wo->rekap_bahan) && count($wo->rekap_bahan) > 0)
                                                                    {{-- REKAPITULASI BAHAN BAKU & KETERSEDIAAN DI GUDANG CK (SUPERADMIN) --}}
                                                                    <div class="card border border-primary-subtle rounded-3 mb-3 overflow-hidden shadow-sm">
                                                                        <div class="card-header bg-primary-subtle py-2 px-3 d-flex justify-content-between align-items-center">
                                                                            <h6 class="fw-bold text-primary mb-0 small text-uppercase d-flex align-items-center gap-2">
                                                                                <i class="bi bi-boxes"></i> Rekap Total Bahan Baku &amp; Ketersediaan Gudang CK
                                                                            </h6>
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <span class="badge bg-white text-dark border small fw-semibold">{{ $wo->total_jenis_bahan }} Jenis Bahan</span>
                                                                                @if(($wo->total_bahan_kurang ?? 0) > 0)
                                                                                    <span class="badge bg-danger text-white small fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $wo->total_bahan_kurang }} Kurang</span>
                                                                                @else
                                                                                    <span class="badge bg-success text-white small fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Stok Cukup</span>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        <div class="card-body p-0">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-sm table-hover align-middle mb-0 text-center" style="font-size: 12px;">
                                                                                    <thead class="table-light text-secondary">
                                                                                        <tr>
                                                                                            <th width="40">No</th>
                                                                                            <th class="text-start">Kode &amp; Nama Bahan Baku</th>
                                                                                            <th width="130" class="text-end">Target WO</th>
                                                                                            <th width="130" class="text-end">Sisa Butuh</th>
                                                                                            <th width="130" class="text-end">Stok Gudang CK</th>
                                                                                            <th width="160">Status Ketersediaan</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        @foreach($wo->rekap_bahan as $rbIdx => $rb)
                                                                                            <tr class="{{ !$rb['is_cukup'] ? 'table-warning' : '' }}">
                                                                                                <td class="text-muted">{{ $rbIdx + 1 }}</td>
                                                                                                <td class="text-start">
                                                                                                    <div class="fw-bold text-dark">{{ $rb['nama_bahan'] }}</div>
                                                                                                    <div class="text-muted font-monospace" style="font-size: 11px;">{{ $rb['kode_barang'] }}</div>
                                                                                                </td>
                                                                                                <td class="text-end fw-semibold">
                                                                                                    {{ (fmod($rb['total_butuh'], 1) == 0) ? number_format($rb['total_butuh'], 0, ',', '.') : number_format($rb['total_butuh'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                                </td>
                                                                                                <td class="text-end fw-bold {{ $rb['sisa_butuh'] > 0 ? 'text-primary' : 'text-muted' }}">
                                                                                                    {{ (fmod($rb['sisa_butuh'], 1) == 0) ? number_format($rb['sisa_butuh'], 0, ',', '.') : number_format($rb['sisa_butuh'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                                </td>
                                                                                                <td class="text-end fw-bold {{ $rb['stok_ck'] > 0 ? 'text-success' : 'text-danger' }}">
                                                                                                    {{ (fmod($rb['stok_ck'], 1) == 0) ? number_format($rb['stok_ck'], 0, ',', '.') : number_format($rb['stok_ck'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                                </td>
                                                                                                <td>
                                                                                                    @if($rb['is_cukup'])
                                                                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                                                                            <i class="bi bi-check-circle-fill me-1"></i> Cukup
                                                                                                        </span>
                                                                                                    @else
                                                                                                        <span class="badge bg-danger text-white px-2 py-1 fw-bold">
                                                                                                            Kurang {{ (fmod($rb['kurang'], 1) == 0) ? number_format($rb['kurang'], 0, ',', '.') : number_format($rb['kurang'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                                        </span>
                                                                                                    @endif
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endforeach
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                <div class="alert alert-info py-2.5 px-3 small mb-0 d-flex align-items-start gap-2 rounded-3 border border-info-subtle shadow-none w-100" style="background-color: #f0f9ff; white-space: normal !important;">
                                                                    <i class="bi bi-info-circle-fill text-info fs-5 flex-shrink-0 mt-0.5"></i>
                                                                    <div class="flex-grow-1" style="min-width: 0; line-height: 1.5; color: #0c5460; white-space: normal !important;">
                                                                        <strong>Catatan Input Produksi:</strong> Staff produksi dapat menginput Qty realisasi selesai sesuai total produksi riil (bisa lebih kecil atau lebih besar dari target WO). Sistem akan otomatis menghitung HPP FIFO dan memperbarui total Qty pesanan berdasarkan input selesai ini.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <span id="lockNoticeWo{{ $wo->id }}" class="text-danger small fw-semibold" style="{{ ($wo->can_approve ?? false) ? 'display: none;' : '' }}">
                                                                        <i class="bi bi-lock-fill me-1"></i> Tombol approval dinonaktifkan:
                                                                        @if(($wo->has_missing_resep ?? false) && !($wo->is_bahan_sufficient ?? true))
                                                                            Harap isi resep menu &amp; lakukan permintaan bahan.
                                                                        @elseif($wo->has_missing_resep ?? false)
                                                                            Harap isi resep menu terlebih dahulu.
                                                                        @else
                                                                            @if($isSuperAdmin)
                                                                                Centang opsi persetujuan override stok di atas atau lakukan permintaan bahan.
                                                                            @else
                                                                                Harap lakukan permintaan bahan atau minta persetujuan Super Admin.
                                                                            @endif
                                                                        @endif
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex gap-2">
                                                                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" name="action" value="draft" class="btn btn-outline-primary px-3 fw-semibold" onclick="return confirm('Simpan draft perubahan kuantitas Work Order ini?')">
                                                                        <i class="bi bi-save me-1"></i> Simpan Draft
                                                                    </button>
                                                                    <button type="submit" name="action" value="approve" id="btnApproveWo{{ $wo->id }}" class="btn btn-success px-4 fw-bold" @if(!($wo->can_approve ?? false)) disabled title="Approval dinonaktifkan" @endif onclick="return confirm('Simpan hasil produksi &amp; Approve HPP otomatis?')">
                                                                        <i class="bi bi-check-circle-fill me-1"></i> Simpan &amp; Approve HPP
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                        @else
                                                            {{-- JIKA SUDAH SELESAI SEMUA --}}
                                                            <div class="modal-body p-4">
                                                                <div class="alert alert-success d-flex align-items-center mb-3">
                                                                    <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                                                                    <div>Seluruh target Work Order ini telah <strong>100% Selesai</strong> diproduksi dan siap dikirim ke outlet pemesan.</div>
                                                                </div>
                                                                <div class="table-responsive mb-3">
                                                                    <table class="table table-bordered text-center align-middle mb-0">
                                                                        <thead class="bg-light font-weight-bold">
                                                                            <tr>
                                                                                <th>Nama Produk</th>
                                                                                <th>Total Target</th>
                                                                                <th>Total Realisasi</th>
                                                                                <th>Status</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach($wo->items_progress as $item)
                                                                                <tr>
                                                                                    <td class="text-start fw-bold">{{ $item['nama_produk'] }}</td>
                                                                                    <td>{{ number_format($item['target'], 0, ',', '.') }} {{ $item['satuan'] }}</td>
                                                                                    <td class="fw-bold text-success">{{ number_format($item['sudah'], 0, ',', '.') }} {{ $item['satuan'] }}</td>
                                                                                    <td><span class="badge bg-success">100% Selesai</span></td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>

                                                                @if($isSuperAdmin && !empty($wo->rekap_bahan) && count($wo->rekap_bahan) > 0)
                                                                    {{-- REKAPITULASI BAHAN BAKU & KETERSEDIAAN DI GUDANG CK (SUPERADMIN) --}}
                                                                    <div class="card border border-primary-subtle rounded-3 mb-0 overflow-hidden shadow-sm">
                                                                        <div class="card-header bg-primary-subtle py-2 px-3 d-flex justify-content-between align-items-center">
                                                                            <h6 class="fw-bold text-primary mb-0 small text-uppercase d-flex align-items-center gap-2">
                                                                                <i class="bi bi-boxes"></i> Rekap Total Bahan Baku &amp; Ketersediaan Gudang CK
                                                                            </h6>
                                                                            <span class="badge bg-white text-dark border small fw-semibold">{{ $wo->total_jenis_bahan }} Jenis Bahan</span>
                                                                        </div>
                                                                        <div class="card-body p-0">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-sm table-hover align-middle mb-0 text-center" style="font-size: 12px;">
                                                                                    <thead class="table-light text-secondary">
                                                                                        <tr>
                                                                                            <th width="40">No</th>
                                                                                            <th class="text-start">Kode &amp; Nama Bahan Baku</th>
                                                                                            <th width="140" class="text-end">Total Kebutuhan</th>
                                                                                            <th width="140" class="text-end">Stok Gudang CK</th>
                                                                                            <th width="160">Status Ketersediaan</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        @foreach($wo->rekap_bahan as $rbIdx => $rb)
                                                                                            <tr>
                                                                                                <td class="text-muted">{{ $rbIdx + 1 }}</td>
                                                                                                <td class="text-start">
                                                                                                    <div class="fw-bold text-dark">{{ $rb['nama_bahan'] }}</div>
                                                                                                    <div class="text-muted font-monospace" style="font-size: 11px;">{{ $rb['kode_barang'] }}</div>
                                                                                                </td>
                                                                                                <td class="text-end fw-semibold">
                                                                                                    {{ (fmod($rb['total_butuh'], 1) == 0) ? number_format($rb['total_butuh'], 0, ',', '.') : number_format($rb['total_butuh'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                                </td>
                                                                                                <td class="text-end fw-bold text-success">
                                                                                                    {{ (fmod($rb['stok_ck'], 1) == 0) ? number_format($rb['stok_ck'], 0, ',', '.') : number_format($rb['stok_ck'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                                </td>
                                                                                                <td>
                                                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                                                                        <i class="bi bi-check-circle-fill me-1"></i> Selesai Diproduksi
                                                                                                    </span>
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endforeach
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <div class="modal-footer bg-light py-2">
                                                                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            @if($isSuperAdmin)
                                                {{-- MODAL KHUSUS REKAP BAHAN BAKU WO (SUPERADMIN) --}}
                                                <div class="modal fade text-start" id="modalRekapBahan{{ $wo->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                                            <div class="modal-header bg-dark text-white py-3">
                                                                <div>
                                                                    <h5 class="modal-title fw-bold mb-0">
                                                                        <i class="bi bi-boxes me-2 text-warning"></i> Rekap Total Bahan Baku &amp; Ketersediaan Gudang CK
                                                                    </h5>
                                                                    <div class="small text-white-50 mt-1">
                                                                        Work Order: <span class="fw-bold text-white">{{ $wo->kode_wo }}</span> &bull; 
                                                                        Outlet Pemesan: <span class="badge bg-light text-dark border">{{ $wo->customer_nama }}</span> &bull; 
                                                                        Tanggal WO: {{ date('d M Y H:i', strtotime($wo->tanggal_wo)) }}
                                                                    </div>
                                                                </div>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body p-4">
                                                                {{-- KPI SUMMARY CARDS --}}
                                                                <div class="row g-3 mb-4">
                                                                    <div class="col-6 col-md-3">
                                                                        <div class="p-3 bg-light rounded-3 border">
                                                                            <span class="text-muted small d-block mb-1">Total Target WO</span>
                                                                            <span class="fw-bold fs-5 text-dark">{{ number_format($wo->total_target, 0, ',', '.') }}</span>
                                                                            <span class="small text-muted ms-1">unit</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6 col-md-3">
                                                                        <div class="p-3 bg-light rounded-3 border">
                                                                            <span class="text-muted small d-block mb-1">Total Jenis Bahan</span>
                                                                            <span class="fw-bold fs-5 text-primary">{{ $wo->total_jenis_bahan ?? count($wo->rekap_bahan ?? []) }}</span>
                                                                            <span class="small text-muted ms-1">item bahan</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6 col-md-3">
                                                                        <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                                                                            <span class="text-success small d-block mb-1 fw-semibold">Stok CK Mencukupi</span>
                                                                            <span class="fw-bold fs-5 text-success">{{ $wo->total_bahan_cukup ?? 0 }}</span>
                                                                            <span class="small text-success ms-1">item siap</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6 col-md-3">
                                                                        <div class="p-3 {{ ($wo->total_bahan_kurang ?? 0) > 0 ? 'bg-danger-subtle border-danger-subtle' : 'bg-light border' }} rounded-3 border">
                                                                            <span class="{{ ($wo->total_bahan_kurang ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-muted' }} small d-block mb-1">Stok CK Kurang</span>
                                                                            <span class="fw-bold fs-5 {{ ($wo->total_bahan_kurang ?? 0) > 0 ? 'text-danger' : 'text-muted' }}">{{ $wo->total_bahan_kurang ?? 0 }}</span>
                                                                            <span class="small {{ ($wo->total_bahan_kurang ?? 0) > 0 ? 'text-danger' : 'text-muted' }} ms-1">item defisit</span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                {{-- ALERT KETERANGAN STATUS --}}
                                                                @if(($wo->total_bahan_kurang ?? 0) > 0)
                                                                    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-3 rounded-3 mb-3 small flex-wrap">
                                                                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0 mt-0.5"></i>
                                                                        <div class="flex-grow-1">
                                                                            <div class="fw-bold text-dark mb-1">Perhatian: Sebagian Stok Bahan Baku di Gudang Central Kitchen Belum Mencukupi!</div>
                                                                            <div>Terdapat <strong>{{ $wo->total_bahan_kurang }} jenis bahan</strong> yang stoknya kurang dari kebutuhan sisa produksi WO ini. Anda dapat melakukan permintaan bahan ke Gudang Utama.</div>
                                                                        </div>
                                                                        <form action="{{ route('ck-produksi.kirim-bahan', $wo->id) }}" method="POST" class="d-inline ms-auto flex-shrink-0" onsubmit="return confirm('Minta bahan baku dari Gudang Utama untuk WO ini?')">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold">
                                                                                <i class="bi bi-box-arrow-right me-1"></i> Minta Bahan ke Gudang Utama
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                @else
                                                                    <div class="alert alert-success border-success d-flex align-items-center gap-2 p-2.5 rounded-3 mb-3 small">
                                                                        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                                                                        <div><strong>Stok Bahan Baku Lengkap:</strong> Seluruh bahan baku di Gudang Central Kitchen siap dan mencukupi untuk memenuhi kebutuhan produksi Work Order ini.</div>
                                                                    </div>
                                                                @endif

                                                                {{-- TABEL REKAP TOTAL BAHAN --}}
                                                                <div class="table-responsive border rounded-3 mb-3">
                                                                    <table class="table table-hover align-middle mb-0" style="font-size: 0.83rem;">
                                                                        <thead class="table-light text-secondary">
                                                                            <tr>
                                                                                <th class="text-center" style="width: 45px;">NO</th>
                                                                                <th style="min-width: 180px;">KODE &amp; NAMA BAHAN BAKU</th>
                                                                                <th class="text-end" style="width: 140px;">TOTAL KEBUTUHAN<br><span class="fw-normal small text-muted">(Target WO)</span></th>
                                                                                <th class="text-end" style="width: 140px;">SISA KEBUTUHAN<br><span class="fw-normal small text-muted">(Belum Selesai)</span></th>
                                                                                <th class="text-end" style="width: 140px;">STOK GUDANG CK<br><span class="fw-normal small text-muted">(Tersedia Fisik)</span></th>
                                                                                <th class="text-center" style="width: 160px;">STATUS KETERSEDIAAN</th>
                                                                                <th class="text-center" style="width: 110px;">RINCIAN MENU</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @forelse($wo->rekap_bahan ?? [] as $rbIdx => $rb)
                                                                                <tr class="{{ !$rb['is_cukup'] ? 'table-warning' : '' }}">
                                                                                    <td class="text-center text-muted fw-semibold">{{ $rbIdx + 1 }}</td>
                                                                                    <td>
                                                                                        <div class="fw-bold text-dark">{{ $rb['nama_bahan'] }}</div>
                                                                                        <div class="text-muted font-monospace small">{{ $rb['kode_barang'] }}</div>
                                                                                    </td>
                                                                                    <td class="text-end fw-semibold text-dark">
                                                                                        {{ (fmod($rb['total_butuh'], 1) == 0) ? number_format($rb['total_butuh'], 0, ',', '.') : number_format($rb['total_butuh'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                    </td>
                                                                                    <td class="text-end fw-bold {{ $rb['sisa_butuh'] > 0 ? 'text-primary' : 'text-muted' }}">
                                                                                        {{ (fmod($rb['sisa_butuh'], 1) == 0) ? number_format($rb['sisa_butuh'], 0, ',', '.') : number_format($rb['sisa_butuh'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                    </td>
                                                                                    <td class="text-end fw-bold {{ $rb['stok_ck'] > 0 ? 'text-success' : 'text-danger' }}">
                                                                                        {{ (fmod($rb['stok_ck'], 1) == 0) ? number_format($rb['stok_ck'], 0, ',', '.') : number_format($rb['stok_ck'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                    </td>
                                                                                    <td class="text-center">
                                                                                        @if($rb['is_cukup'])
                                                                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                                                                <i class="bi bi-check-circle-fill me-1"></i> Mencukupi
                                                                                            </span>
                                                                                            @if($rb['selisih'] > 0)
                                                                                                <div class="small text-muted font-monospace mt-1" style="font-size: 11px;">
                                                                                                    Surplus +{{ (fmod($rb['selisih'], 1) == 0) ? number_format($rb['selisih'], 0, ',', '.') : number_format($rb['selisih'], 2, ',', '.') }}
                                                                                                </div>
                                                                                            @endif
                                                                                        @else
                                                                                            <span class="badge bg-danger text-white px-2 py-1 fw-bold">
                                                                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Kurang {{ (fmod($rb['kurang'], 1) == 0) ? number_format($rb['kurang'], 0, ',', '.') : number_format($rb['kurang'], 2, ',', '.') }} {{ $rb['satuan'] }}
                                                                                            </span>
                                                                                            <div class="small text-danger font-monospace mt-1" style="font-size: 11px;">
                                                                                                Stok saat ini {{ (fmod($rb['stok_ck'], 1) == 0) ? number_format($rb['stok_ck'], 0, ',', '.') : number_format($rb['stok_ck'], 2, ',', '.') }}
                                                                                            </div>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="text-center">
                                                                                        <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBreakdown{{ $wo->id }}_{{ $rb['bahan_id'] }}" style="font-size: 11px; border-radius: 5px;">
                                                                                            {{ count($rb['breakdown']) }} Menu <i class="bi bi-chevron-down ms-1"></i>
                                                                                        </button>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr class="collapse bg-light" id="collapseBreakdown{{ $wo->id }}_{{ $rb['bahan_id'] }}">
                                                                                    <td colspan="7" class="p-3">
                                                                                        <div class="bg-white p-3 rounded-3 border shadow-sm">
                                                                                            <div class="fw-bold text-dark small mb-2"><i class="bi bi-diagram-3 me-1 text-primary"></i> Rincian Penggunaan "{{ $rb['nama_bahan'] }}" pada Menu WO ini:</div>
                                                                                            <div class="table-responsive">
                                                                                                <table class="table table-sm table-bordered text-center align-middle mb-0" style="font-size: 12px;">
                                                                                                    <thead class="table-light">
                                                                                                        <tr>
                                                                                                            <th class="text-start">Nama Produk</th>
                                                                                                            <th width="110">Target Produk</th>
                                                                                                            <th width="110">Sisa Produk</th>
                                                                                                            <th width="140">Standar per Unit</th>
                                                                                                            <th width="140">Kebutuhan Total</th>
                                                                                                            <th width="140">Kebutuhan Sisa</th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        @foreach($rb['breakdown'] as $bd)
                                                                                                            <tr>
                                                                                                                <td class="text-start fw-semibold">{{ $bd['nama_produk'] }} <span class="text-muted small">({{ $bd['kode_produk'] }})</span></td>
                                                                                                                <td>{{ number_format($bd['target_produk'], 0, ',', '.') }} {{ $bd['satuan_produk'] }}</td>
                                                                                                                <td class="fw-bold text-danger">{{ number_format($bd['sisa_produk'], 0, ',', '.') }} {{ $bd['satuan_produk'] }}</td>
                                                                                                                <td>{{ (fmod($bd['qty_per_unit'], 1) == 0) ? number_format($bd['qty_per_unit'], 0, ',', '.') : number_format($bd['qty_per_unit'], 4, ',', '.') }} {{ $rb['satuan'] }}</td>
                                                                                                                <td class="fw-semibold text-dark">{{ (fmod($bd['subtotal_total'], 1) == 0) ? number_format($bd['subtotal_total'], 0, ',', '.') : number_format($bd['subtotal_total'], 2, ',', '.') }} {{ $rb['satuan'] }}</td>
                                                                                                                <td class="fw-bold text-primary">{{ (fmod($bd['subtotal_sisa'], 1) == 0) ? number_format($bd['subtotal_sisa'], 0, ',', '.') : number_format($bd['subtotal_sisa'], 2, ',', '.') }} {{ $rb['satuan'] }}</td>
                                                                                                            </tr>
                                                                                                        @endforeach
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>
                                                                                </tr>
                                                                            @empty
                                                                                <tr>
                                                                                    <td colspan="7" class="text-center text-muted py-4">Tidak ada data resep bahan baku yang terdaftar untuk Work Order ini.</td>
                                                                                </tr>
                                                                            @endforelse
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
                                            @endif

                                            @if(auth()->user() && auth()->user()->canEditWoQty() && !($wo->is_terkirim ?? false))
                                                {{-- MODAL EDIT QTY WORK ORDER (SUPERADMIN & GAHARU) --}}
                                                <div class="modal fade text-start" id="modalEditQty{{ $wo->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                                            <div class="modal-header text-white" style="background-color: #854d0e;">
                                                                <h5 class="modal-title fw-bold">
                                                                    <i class="bi bi-pencil-square me-2"></i> Edit Qty Work Order: {{ $wo->kode_wo }}
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form action="{{ route('ck-produksi.edit-qty-wo', $wo->id) }}" method="POST" onsubmit="return confirm('Simpan perubahan kuantitas Work Order ini?')">
                                                                @csrf
                                                                <input type="hidden" name="tab" value="wo">
                                                                <input type="hidden" name="wo_page" value="{{ request('wo_page', 1) }}">
                                                                <input type="hidden" name="search" value="{{ request('search', '') }}">
                                                                <input type="hidden" name="customer_id" value="{{ request('customer_id', '') }}">
                                                                <div class="modal-body p-4">
                                                                    <div class="alert alert-warning border-warning d-flex align-items-center gap-2 p-2.5 rounded-3 mb-3 small">
                                                                        <i class="bi bi-shield-lock-fill fs-5 text-warning flex-shrink-0"></i>
                                                                        <div>
                                                                            <strong>Hak Akses:</strong> Anda dapat mengedit kuantitas item pada Work Order ini karena pesanan <strong>belum terkirim</strong>. Sistem akan otomatis menyesuaikan alokasi pesanan, stok jadi, dan perhitungan total HPP.
                                                                        </div>
                                                                    </div>

                                                                    <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-warning">
                                                                        <div class="row g-2 small">
                                                                            <div class="col-md-4">
                                                                                <span class="text-muted d-block">Kode Work Order:</span>
                                                                                <strong class="text-dark">{{ $wo->kode_wo }}</strong>
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <span class="text-muted d-block">Outlet Pemesan:</span>
                                                                                <strong class="text-dark">{{ $wo->customer_nama }}</strong>
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <span class="text-muted d-block">Status Saat Ini:</span>
                                                                                <span class="badge {{ strtolower($wo->status_wo) == 'selesai' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $wo->status_wo }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                        <h6 class="fw-bold text-dark mb-0 small text-uppercase">Daftar Item & Penyesuaian Kuantitas</h6>
                                                                        <button type="button" class="btn btn-sm btn-outline-success fw-semibold btn-add-item-wo" data-target-table="#tableEditWo{{ $wo->id }}">
                                                                            <i class="bi bi-plus-circle me-1"></i> Tambah Item Baru
                                                                        </button>
                                                                    </div>
                                                                    <div class="table-responsive mb-3">
                                                                        <table class="table table-bordered align-middle text-center mb-0" id="tableEditWo{{ $wo->id }}">
                                                                            <thead class="table-light">
                                                                                <tr>
                                                                                    <th style="width: 5%;">No</th>
                                                                                    <th class="text-start">Nama Produk</th>
                                                                                    <th style="width: 25%;">Qty Saat Ini</th>
                                                                                    <th style="width: 40%; min-width: 210px;">Qty Baru</th>
                                                                                    <th style="width: 8%;">Aksi</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @foreach($wo->details as $idx => $wod)
                                                                                    @php
                                                                                        $p = $wod->produk;
                                                                                        $satDasar = $p->satuan ?? 'pcs';
                                                                                        $hasKonv = !empty($p->satuan_pembelian) && floatval($p->konversi_pembelian ?? 1) > 1;
                                                                                        $satBeli = $hasKonv ? strtoupper($p->satuan_pembelian) : '';
                                                                                        $konvVal = $hasKonv ? floatval($p->konversi_pembelian) : 1;
                                                                                        $qtySaatIni = floatval($wod->qty_rencana);
                                                                                    @endphp
                                                                                    <tr class="row-item-wo">
                                                                                        <td class="row-number">{{ $idx + 1 }}</td>
                                                                                        <td class="text-start">
                                                                                            <div class="fw-bold text-dark">{{ $p->nama ?? 'Produk' }}</div>
                                                                                            <div class="text-muted small">{{ $p->kode_barang ?? '-' }}</div>
                                                                                        </td>
                                                                                        <td class="fw-semibold">
                                                                                            <div>{{ number_format($qtySaatIni, ($qtySaatIni == intval($qtySaatIni) ? 0 : 2), ',', '.') }} {{ $satDasar }}</div>
                                                                                            @if($hasKonv)
                                                                                                @php $packSaatIni = $qtySaatIni / $konvVal; @endphp
                                                                                                <div class="small text-primary font-monospace" style="font-size: 11px;">
                                                                                                    ({{ number_format($packSaatIni, ($packSaatIni == intval($packSaatIni) ? 0 : 2), ',', '.') }} {{ $satBeli }} @ {{ number_format($konvVal, 0, ',', '.') }} {{ $satDasar }})
                                                                                                </div>
                                                                                            @else
                                                                                                <div class="small text-muted font-monospace" style="font-size: 11px;">-</div>
                                                                                            @endif
                                                                                        </td>
                                                                                        <td>
                                                                                            <input type="hidden" name="detail_id[]" value="{{ $wod->id }}">
                                                                                            <input type="hidden" name="produk_id[]" value="{{ $wod->produk_id }}">
                                                                                            <div class="input-group input-group-sm flex-nowrap shadow-sm">
                                                                                                <input type="number" name="qty_baru[]" class="form-control text-end fw-bold input-qty-edit-wo px-2" 
                                                                                                    min="0.01" step="any" value="{{ $qtySaatIni }}" 
                                                                                                    data-konversi="{{ $konvVal }}"
                                                                                                    data-satuan-dasar="{{ $satDasar }}"
                                                                                                    data-satuan-konv="{{ $satBeli }}" required>
                                                                                                @if($hasKonv)
                                                                                                    <select name="satuan_input_edit[]" class="form-select select-unit-edit-wo fw-bold text-center bg-light text-primary" style="width: 100px; flex: 0 0 100px; padding-left: 8px; padding-right: 22px; font-size: 0.78rem;">
                                                                                                        <option value="dasar">{{ strtoupper($satDasar) }}</option>
                                                                                                        <option value="konversi">{{ $satBeli }}</option>
                                                                                                    </select>
                                                                                                @else
                                                                                                    <select name="satuan_input_edit[]" class="form-select select-unit-edit-wo fw-bold text-center bg-light text-primary" style="width: 100px; flex: 0 0 100px; padding-left: 8px; padding-right: 22px; font-size: 0.78rem;">
                                                                                                        <option value="dasar">{{ strtoupper($satDasar) }}</option>
                                                                                                    </select>
                                                                                                @endif
                                                                                            </div>
                                                                                            <div class="live-konversi-edit-info small text-end mt-1 font-monospace" style="font-size: 11px; min-height: 16.5px;">
                                                                                                @if(!$hasKonv)
                                                                                                    <span class="text-muted">-</span>
                                                                                                @endif
                                                                                            </div>
                                                                                        </td>
                                                                                        <td>
                                                                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-3 btn-delete-existing-row" data-detail-id="{{ $wod->id }}" title="Hapus item ini dari Work Order">
                                                                                                <i class="bi bi-trash"></i>
                                                                                            </button>
                                                                                        </td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div class="deleted-inputs-container"></div>

                                                                    <div class="mb-0">
                                                                        <label class="form-label fw-bold text-secondary small">Alasan / Catatan Penyesuaian (Opsional):</label>
                                                                        <input type="text" name="alasan_edit" class="form-control form-control-sm" placeholder="Contoh: Koreksi kuantitas atau penyesuaian menu sebelum pengiriman">
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer bg-light">
                                                                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-warning px-4 fw-bold text-dark">
                                                                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan WO
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="text-center py-4 text-muted">Belum ada Work Order CK.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($woList->hasPages())
                        <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                            {{ $woList->appends(array_merge(request()->query(), ['tab' => 'wo']))->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- TAB 3: RIWAYAT PRODUKSI CK (DETAIL POPUP) --}}
            <div class="tab-pane fade {{ $activeTab === 'prod' ? 'show active' : '' }}" id="prod-history" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4">
                        <h6 class="fw-bold mb-0 text-dark">Riwayat Produksi Central Kitchen</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center text-nowrap" style="width: 45px;">NO</th>
                                    <th class="text-nowrap">KODE PRODUKSI</th>
                                    <th class="text-nowrap">OUTLET / SUMBER</th>
                                    <th class="text-nowrap">DIVISI CK</th>
                                    <th class="text-nowrap">TANGGAL PRODUKSI</th>
                                    <th class="text-end text-nowrap">TOTAL HPP</th>
                                    <th class="text-center text-nowrap">STATUS</th>
                                    <th class="text-center text-nowrap" style="width: 180px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body">
                                @forelse($riwayatProduksi as $index => $prod)
                                    @php
                                        $totalHppProd = $prod->details->sum('hpp_total');
                                    @endphp
                                    <tr>
                                        <td class="text-center text-nowrap">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark text-nowrap">{{ $prod->kode_produksi }}</td>
                                        <td class="text-nowrap">
                                            <span class="badge bg-light text-dark border">
                                                {{ $prod->pesanan->customer->nama ?? 'Stok Internal CK' }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($prod->divisi)
                                                <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;font-size:0.72rem;font-weight:600;">
                                                    <i class="bi bi-layers-half me-1"></i>{{ $prod->divisi->nama }}
                                                </span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">{{ date('d M Y', strtotime($prod->tanggal_mulai)) }}</td>
                                        <td class="text-end fw-bold text-danger text-nowrap">
                                            Rp {{ number_format($totalHppProd, 2, ',', '.') }}
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge bg-{{ strtolower($prod->status_produksi) == 'selesai' ? 'success' : 'warning text-dark' }}">
                                                {{ $prod->status_produksi }}
                                            </span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="action-box d-flex justify-content-center">
                                                <div class="btn-group btn-group-sm w-100 shadow-sm" role="group">
                                                    <button type="button" class="btn btn-outline-secondary fw-semibold d-flex align-items-center justify-content-center gap-1 py-1" style="height: 32px; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalProd{{ $prod->id }}">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </button>

                                                    @if(strtolower($prod->status_produksi) == 'selesai')
                                                        <a href="{{ route('pengiriman.index', ['tipe' => 'central_kitchen', 'search' => $prod->pesanan->kode_pesanan ?? $prod->kode_produksi]) }}" class="btn btn-custom-orange fw-semibold d-flex align-items-center justify-content-center gap-1 py-1 text-white" style="height: 32px; font-size: 0.8rem;" title="Kirim ke Logistik Outlet">
                                                            <i class="bi bi-truck"></i> Kirim
                                                        </a>
                                                    @endif

                                                    @if(strtolower($prod->status_produksi) == 'draft')
                                                        @if($prod->has_missing_resep ?? false)
                                                            <a href="{{ route('resep.create') }}" target="_blank" class="btn btn-outline-danger fw-semibold d-flex align-items-center justify-content-center gap-1 py-1" style="font-size: 0.8rem;" title="Menu belum memiliki resep">
                                                                <i class="bi bi-journal-plus"></i> Resep
                                                            </a>
                                                        @endif
                                                        <button type="button" class="btn btn-success fw-semibold d-flex align-items-center justify-content-center gap-1 py-1" style="height: 32px; font-size: 0.8rem;" {{ (!($prod->can_approve ?? false)) ? 'disabled' : '' }} onclick="if(confirm('Approve Produksi CK? HPP per unit akan dihitung otomatis & barang masuk stok CK.')) document.getElementById('formApproveProd{{ $prod->id }}').submit();" title="{{ (!($prod->can_approve ?? false)) ? 'Approval dinonaktifkan: Menu belum memiliki resep atau bahan baku di CK belum mencukupi' : 'Approve' }}">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    @endif
                                                </div>
                                                @if(strtolower($prod->status_produksi) == 'draft')
                                                    <form id="formApproveProd{{ $prod->id }}" action="{{ route('ck-produksi.approve', $prod->id) }}" method="POST" class="d-none">
                                                        @csrf
                                                    </form>
                                                @endif
                                            </div>

                                            {{-- MODAL DETAIL RIWAYAT PRODUKSI CK --}}
                                            <div class="modal fade text-start" id="modalProd{{ $prod->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title fw-bold">
                                                                <i class="bi bi-receipt-cutoff me-2"></i> Detail Hasil Produksi CK: {{ $prod->kode_produksi }}
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            @if(strtolower($prod->status_produksi) == 'draft' && ($prod->has_missing_resep ?? false))
                                                                <div class="alert alert-danger border-danger d-flex align-items-start gap-2 p-3 rounded-3 mb-3 small">
                                                                    <i class="bi bi-x-circle-fill fs-5 text-danger mt-1 flex-shrink-0"></i>
                                                                    <div class="flex-grow-1">
                                                                        <strong class="d-block mb-1">Approval Dikunci - Menu Belum Memiliki Resep:</strong>
                                                                        <span>Menu berikut belum memiliki formulasi resep bahan baku sehingga HPP tidak dapat dihitung dan approval produksi tidak dapat diproses:</span>
                                                                        <ul class="mb-2 mt-1 ps-3">
                                                                            @foreach($prod->produk_tanpa_resep ?? [] as $ptr)
                                                                                <li><strong>{{ $ptr['nama_produk'] }}</strong> ({{ $ptr['kode_barang'] ?? '-' }})</li>
                                                                            @endforeach
                                                                        </ul>
                                                                        <a href="{{ route('resep.create') }}" target="_blank" class="btn btn-sm btn-danger fw-semibold">
                                                                            <i class="bi bi-journal-plus me-1"></i> Isi Resep Menu Sekarang
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @if(strtolower($prod->status_produksi) == 'draft' && isset($prod->is_bahan_sufficient) && !$prod->is_bahan_sufficient && !empty($prod->defisit_bahan))
                                                                <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                                                                    <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1 flex-shrink-0"></i>
                                                                    <div class="flex-grow-1">
                                                                        <strong class="d-block mb-1">Perhatian Ketersediaan Bahan Baku di Gudang Central Kitchen:</strong>
                                                                        <ul class="mb-2 ps-3">
                                                                            @foreach($prod->defisit_bahan as $def)
                                                                                <li>{{ $def['nama'] }}: Tersedia <strong>{{ $def['stok'] }} {{ $def['satuan'] }}</strong> / Butuh <strong>{{ $def['butuh'] }} {{ $def['satuan'] }}</strong> (Kurang <span class="text-danger fw-bold">{{ $def['kurang'] }} {{ $def['satuan'] }}</span>)</li>
                                                                            @endforeach
                                                                        </ul>
                                                                        @if($isSuperAdmin)
                                                                            <div class="form-check pt-2 border-top border-warning-subtle">
                                                                                <input class="form-check-input check-override-draft" type="checkbox" name="override_stok" value="1" id="overrideDraft{{ $prod->id }}" data-prod-id="{{ $prod->id }}" data-has-missing-resep="{{ ($prod->has_missing_resep ?? false) ? '1' : '0' }}" form="formApproveProd{{ $prod->id }}">
                                                                                <label class="form-check-label fw-bold text-dark small" for="overrideDraft{{ $prod->id }}">
                                                                                    <i class="bi bi-shield-check text-success me-1"></i> Setujui &amp; Lanjutkan Produksi (Override Stok Kurang - Khusus Super Admin)
                                                                                </label>
                                                                                <div class="text-muted" style="font-size: 11px;">HPP otomatis dihitung menggunakan <strong>harga terakhir bahan baku</strong>.</div>
                                                                            </div>
                                                                        @else
                                                                            <div class="pt-2 border-top border-warning-subtle text-muted" style="font-size: 11px;">
                                                                                <i class="bi bi-info-circle me-1"></i> Hubungi <strong>Super Admin</strong> untuk menyetujui produksi jika stok fisik ada namun belum opname di sistem.
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-primary">
                                                                <div class="row g-2 small">
                                                                    <div class="col-md-4">
                                                                        <span class="text-muted d-block">Outlet Tujuan:</span>
                                                                        <strong class="text-dark">{{ $prod->pesanan->customer->nama ?? '-' }}</strong>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <span class="text-muted d-block">Tanggal Produksi:</span>
                                                                        <strong class="text-dark">{{ date('d M Y', strtotime($prod->tanggal_mulai)) }}</strong>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <span class="text-muted d-block">Status:</span>
                                                                        <span class="badge bg-{{ strtolower($prod->status_produksi) == 'selesai' ? 'success' : 'warning text-dark' }}">
                                                                            {{ $prod->status_produksi }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <h6 class="fw-bold text-dark mb-2 small text-uppercase">Daftar Produk & Nilai HPP</h6>
                                                            <div class="table-responsive mb-3">
                                                                <table class="table table-bordered align-middle text-center mb-0">
                                                                    <thead class="bg-light font-weight-bold">
                                                                        <tr>
                                                                            <th style="width: 5%;">No</th>
                                                                            <th class="text-start">Nama Produk</th>
                                                                            <th style="width: 15%;">Qty Hasil</th>
                                                                            <th style="width: 25%;" class="text-end">Total Biaya HPP</th>
                                                                            <th style="width: 25%;" class="text-end">HPP / Unit</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($prod->details as $idx => $d)
                                                                            @php
                                                                                $hppUnit = $d->qty > 0 ? ($d->hpp_total / $d->qty) : 0;
                                                                                $resep = $d->produk ? ($d->produk->resepBtklBop ?? \App\Models\ResepBtklBop::with(['bahanbaku.bahan', 'bahanbaku.alternatif.bahan'])->where('produk_id', $d->produk_id)->first()) : \App\Models\ResepBtklBop::with(['bahanbaku.bahan', 'bahanbaku.alternatif.bahan'])->where('produk_id', $d->produk_id)->first();
                                                                                $hasResep = $resep && $resep->bahanbaku && $resep->bahanbaku->count() > 0;
                                                                                $outputQtyResep = $resep ? floatval($resep->output_qty) : 0;
                                                                                $batchCount = $outputQtyResep > 0 ? ($d->qty / $outputQtyResep) : 0;

                                                                                // Ambil data harga dari transaksi_stok (FIFO yang sudah dikonsumsi saat produksi ini)
                                                                                // Key: barang_id => ['qty'=>..., 'total_harga'=>..., 'harga_per_unit'=>...]
                                                                                $transaksiHarga = \DB::table('transaksi_stok')
                                                                                    ->where('source_type', 'produksi_ck')
                                                                                    ->where('source_id', $prod->id)
                                                                                    ->where('tipe', 'keluar')
                                                                                    ->get()
                                                                                    ->keyBy('barang_id')
                                                                                    ->map(function($t) {
                                                                                        $qty = floatval($t->qty);
                                                                                        $totalH = floatval($t->total_harga);
                                                                                        return [
                                                                                            'qty'          => $qty,
                                                                                            'total_harga'  => $totalH,
                                                                                            'harga_per_unit'=> $qty > 0 ? ($totalH / $qty) : 0,
                                                                                            'sumber'       => 'fifo',
                                                                                        ];
                                                                                    })->toArray();

                                                                                // Build JSON data for modal popup
                                                                                $resepJson = [];
                                                                                if ($hasResep) {
                                                                                    $resepJson = [
                                                                                        'produk'       => $d->produk->nama ?? ('Produk #' . $d->produk_id),
                                                                                        'output_qty'   => $outputQtyResep,
                                                                                        'satuan_output'=> $resep->satuan_output ?? ($d->produk->satuan ?? 'GR'),
                                                                                        'batch_count'  => round($batchCount, 4),
                                                                                        'bahanbaku'    => $resep->bahanbaku->map(function($b) use ($batchCount, $transaksiHarga, $prod) {
                                                                                            $qtyPerBatch = floatval($b->qty_bahan);
                                                                                            $totalQty    = round($qtyPerBatch * $batchCount, 4);
                                                                                            $bahanId     = $b->bahan_id;

                                                                                            // Harga dari transaksi_stok FIFO aktual
                                                                                            if (isset($transaksiHarga[$bahanId]) && floatval($transaksiHarga[$bahanId]['harga_per_unit']) > 0) {
                                                                                                $hData = $transaksiHarga[$bahanId];
                                                                                                $hargaPerUnit  = $hData['harga_per_unit'];
                                                                                                $totalHargaBahan = $hargaPerUnit * $totalQty;  // harga per unit × qty yg dipakai produk ini
                                                                                                $sumberHarga   = 'FIFO Aktual';
                                                                                            } else {
                                                                                                // Ambil harga terakhir via FifoService (stok_gudang_batch, pembelian_detail, resep, hpp_referensi)
                                                                                                $fifoService = app(\App\Services\FifoService::class);
                                                                                                $hargaPerUnit = $fifoService->getHargaTerakhirBahan($bahanId, $prod->gudang_bahan_id);
                                                                                                $totalHargaBahan = $hargaPerUnit * $totalQty;
                                                                                                $sumberHarga   = $hargaPerUnit > 0 ? 'Harga Terakhir' : '-';
                                                                                            }

                                                                                            return [
                                                                                                'nama'          => $b->bahan->nama ?? ('Bahan ID ' . $bahanId),
                                                                                                'kode'          => $b->bahan->kode_barang ?? null,
                                                                                                'qty_per_batch' => $qtyPerBatch,
                                                                                                'total_qty'     => $totalQty,
                                                                                                'satuan'        => $b->satuan ?? ($b->bahan->satuan ?? 'GR'),
                                                                                                'harga_per_unit'=> round($hargaPerUnit, 4),
                                                                                                'total_harga'   => round($totalHargaBahan, 2),
                                                                                                'sumber_harga'  => $sumberHarga,
                                                                                                'alternatif'    => $b->alternatif ? $b->alternatif->map(function($alt) {
                                                                                                    return ['prioritas' => $alt->prioritas, 'nama' => $alt->bahan->nama ?? '-'];
                                                                                                })->values()->toArray() : [],
                                                                                            ];
                                                                                        })->values()->toArray(),
                                                                                    ];
                                                                                }
                                                                            @endphp
                                                                            <tr>
                                                                                <td>{{ $idx + 1 }}</td>
                                                                                <td class="text-start fw-bold">
                                                                                    {{ $d->produk->nama ?? ('Produk #' . $d->produk_id) }}
                                                                                    @if($hasResep)
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-outline-info rounded-pill py-0 px-2 ms-2 shadow-none btn-show-resep-modal"
                                                                                            style="font-size: 11px;"
                                                                                            data-resep="{{ json_encode($resepJson) }}">
                                                                                            <i class="bi bi-journal-text me-1"></i> Rincian Resep &amp; Bahan
                                                                                        </button>
                                                                                    @endif
                                                                                </td>
                                                                                <td class="fw-bold text-dark">{{ number_format($d->qty, 0, ',', '.') }} {{ $d->produk->satuan ?? 'pcs' }}</td>
                                                                                <td class="text-end fw-bold text-danger">Rp {{ number_format($d->hpp_total, 2, ',', '.') }}</td>
                                                                                <td class="text-end fw-bold text-info">Rp {{ number_format($hppUnit, 2, ',', '.') }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                    <tfoot>
                                                                        <tr class="bg-light">
                                                                            <th colspan="3" class="text-end font-weight-bold">TOTAL HPP PRODUKSI:</th>
                                                                            <th class="text-end font-weight-bold text-danger">Rp {{ number_format($totalHppProd, 2, ',', '.') }}</th>
                                                                            <th></th>
                                                                        </tr>
                                                                    </tfoot>
                                                                </table>
                                                            </div>

                                                            @if(strtolower($prod->status_produksi) == 'selesai')
                                                                <div class="alert alert-success py-2 px-3 small mb-0">
                                                                    <i class="bi bi-check-circle-fill me-1"></i> HPP telah berhasil dihitung berdasarkan FIFO & stok telah tercatat di Gudang Central Kitchen.
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="modal-footer bg-light py-2">
                                                            @if(strtolower($prod->status_produksi) == 'draft')
                                                                <form id="formApproveProd{{ $prod->id }}" action="{{ route('ck-produksi.approve', $prod->id) }}" method="POST" onsubmit="return confirm('Approve Produksi CK sekarang?')">
                                                                    @csrf
                                                                    <button type="submit" id="btnApproveDraft{{ $prod->id }}" class="btn btn-success btn-sm px-3" {{ (!($prod->can_approve ?? false)) ? 'disabled' : '' }}>
                                                                        <i class="bi bi-check-circle me-1"></i> Approve &amp; Hitung HPP
                                                                    </button>
                                                                </form>
                                                            @endif
                                                            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Belum ada riwayat produksi CK.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($riwayatProduksi->hasPages())
                        <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                            {{ $riwayatProduksi->appends(array_merge(request()->query(), ['tab' => 'prod']))->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- MODAL: Rincian Resep & Bahan (reusable, fullscreen, diisi via JS) --}}
            <div class="modal fade" id="modalRincianResep" tabindex="-1" aria-labelledby="modalRincianResepLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-info bg-opacity-10 border-bottom py-3">
                            <div class="flex-grow-1">
                                <h5 class="modal-title fw-bold text-info mb-0" id="modalRincianResepLabel">
                                    <i class="bi bi-gear-wide-connected me-2"></i>
                                    Formulasi Resep: <span id="resepModalNamaProduk">-</span>
                                </h5>
                                <small class="text-muted" id="resepModalSubtitle"></small>
                            </div>
                            <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4" style="overflow-y: auto;">
                            <div id="resepModalBody">
                                <p class="text-muted text-center py-3">Memuat data resep...</p>
                            </div>
                        </div>
                        <div class="modal-footer py-2 bg-light justify-content-between">
                            <div id="resepModalFooterInfo" class="text-muted small fst-italic">
                                * HPP Total mencakup nilai bahan baku FIFO + <strong>30% Biaya Konversi (BTKL &amp; BOP)</strong>.
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-1"></i> Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 4: STOK BSJ CENTRAL KITCHEN (PRODUKSI TANPA DIVISI) --}}
            <div class="tab-pane fade {{ $activeTab === 'stok' ? 'show active' : '' }}" id="stok-divisi" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Monitoring Stok Bahan Setengah Jadi (BSJ) Central Kitchen</h6>
                            <small class="text-muted">Ketahui ketersediaan stok BSJ belum terpakai di Central Kitchen & kalkulasi kuantitas yang perlu diproduksi untuk memenuhi permintaan outlet.</small>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <form action="{{ route('ck-produksi.index') }}" method="GET" class="d-flex align-items-center gap-1">
                                <input type="hidden" name="tab" value="stok">
                                @if(request('customer_id'))
                                    <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
                                @endif
                                <div class="input-group input-group-sm" style="min-width: 220px;">
                                    <input type="text" name="search_bsj" class="form-control" placeholder="Cari nama / kode BSJ..." value="{{ request('search_bsj', $searchBsj ?? '') }}" style="border-radius: 6px 0 0 6px;">
                                    <button class="btn btn-outline-secondary" type="submit" title="Cari BSJ">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    @if(request('search_bsj'))
                                        <a href="{{ route('ck-produksi.index', array_merge(request()->except('search_bsj'), ['tab' => 'stok'])) }}" class="btn btn-outline-danger" title="Hapus Pencarian">
                                            <i class="bi bi-x"></i>
                                        </a>
                                    @endif
                                </div>
                            </form>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold">
                                <i class="bi bi-gear-wide-connected me-1"></i> Produksi Central Kitchen
                            </span>
                        </div>
                    </div>

                    @if(!empty($stokBsjCk) && $stokBsjCk->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 13px;">
                                <thead class="table-light text-secondary small text-uppercase fw-bold">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">NO</th>
                                        <th style="min-width: 120px;">KODE BARANG</th>
                                        <th style="min-width: 200px;">NAMA BAHAN SETENGAH JADI</th>
                                        <th class="text-end table-success" style="width: 140px;">STOK DI CK</th>
                                        <th class="text-start table-warning" style="min-width: 240px; width: 280px;">PERMINTAAN OUTLET</th>
                                        <th class="text-end table-danger" style="width: 170px;">PERLU DIPRODUKSI</th>
                                        <th class="text-center" style="width: 110px;">SATUAN</th>
                                        <th class="text-center" style="width: 130px;">STATUS STOK</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stokBsjCk as $index => $item)
                                        <tr>
                                            <td class="text-center text-muted fw-semibold">{{ $stokBsjCk->firstItem() + $index }}</td>
                                            <td class="font-monospace fw-bold text-primary">{{ $item['kode_barang'] }}</td>
                                            <td class="fw-semibold text-dark">{{ $item['nama'] }}</td>
                                            
                                            {{-- Stok Tersedia di CK --}}
                                            <td class="text-end fw-bold {{ $item['stok_tersedia'] > 0 ? 'text-success' : 'text-muted' }}">
                                                {{ number_format($item['stok_tersedia'], 0, ',', '.') }}
                                            </td>

                                            {{-- Permintaan Outlet: Total beserta Pemisahan Cabang/Outlet --}}
                                            <td class="text-start">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small text-muted fw-semibold">Total:</span>
                                                    <span class="fw-bold {{ $item['total_permintaan'] > 0 ? 'text-warning-emphasis' : 'text-muted' }}">
                                                        {{ number_format($item['total_permintaan'], 0, ',', '.') }} {{ $item['satuan'] }}
                                                    </span>
                                                </div>

                                                @if(!empty($item['outlet_breakdown']) && count($item['outlet_breakdown']) > 0)
                                                    <div class="d-flex flex-wrap gap-1 mt-1 pt-1 border-top border-warning-subtle">
                                                        @foreach($item['outlet_breakdown'] as $ob)
                                                            <span class="badge bg-white text-dark border border-warning-subtle shadow-xs py-1 px-1.5" style="font-size: 11px; font-weight: 500;">
                                                                <i class="bi bi-geo-alt-fill text-warning me-0.5"></i>
                                                                <strong>{{ $ob['customer_nama'] }}</strong>: 
                                                                <span class="text-primary font-monospace fw-bold">{{ number_format($ob['qty'], 0, ',', '.') }}</span>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @elseif($item['total_permintaan'] > 0)
                                                    <div class="small text-muted fst-italic" style="font-size: 11px;">
                                                        Belum ada alokasi outlet spesifik
                                                    </div>
                                                @else
                                                    <div class="small text-muted" style="font-size: 11px;">
                                                        Tidak ada permintaan aktif
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Rekomendasi yang Harus Diproduksi --}}
                                            <td class="text-end fw-bold {{ $item['rekomendasi_produksi'] > 0 ? 'text-danger' : 'text-secondary' }}">
                                                @if($item['rekomendasi_produksi'] > 0)
                                                    <span class="badge bg-danger fs-7 px-2 py-1">
                                                        +{{ number_format($item['rekomendasi_produksi'], 0, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">— (Tercukupi)</span>
                                                @endif
                                            </td>

                                            <td class="text-center text-muted">{{ $item['satuan'] }}</td>

                                            <td class="text-center">
                                                @if($item['stok_tersedia'] <= 0 && $item['total_permintaan'] > 0)
                                                    <span class="badge bg-danger">Kurang / Kosong</span>
                                                @elseif($item['rekomendasi_produksi'] > 0)
                                                    <span class="badge bg-warning text-dark">Perlu Tambah</span>
                                                @elseif($item['stok_tersedia'] > 0)
                                                    <span class="badge bg-success">Stok Cukup</span>
                                                @else
                                                    <span class="badge bg-light text-muted border">Kosong</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                            @if(request('search_bsj'))
                                Tidak ditemukan Bahan Setengah Jadi dengan kata kunci "<strong>{{ request('search_bsj') }}</strong>".
                                <div class="mt-2">
                                    <a href="{{ route('ck-produksi.index', array_merge(request()->except('search_bsj'), ['tab' => 'stok'])) }}" class="btn btn-sm btn-outline-secondary">Reset Pencarian</a>
                                </div>
                            @else
                                Belum ada master Bahan Setengah Jadi yang aktif untuk Central Kitchen.
                            @endif
                        </div>
                    @endif

                    @if(!empty($stokBsjCk) && $stokBsjCk->hasPages())
                        <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small text-muted">
                                Menampilkan {{ $stokBsjCk->firstItem() }} sampai {{ $stokBsjCk->lastItem() }} dari {{ $stokBsjCk->total() }} barang BSJ
                            </div>
                            <div>
                                {{ $stokBsjCk->appends(array_merge(request()->query(), ['tab' => 'stok']))->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL BATCH PRODUKSI CK --}}
    <div class="modal fade text-start" id="modalBatchProduksiCk" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-layers-fill me-2"></i> Produksi Batch Work Order (Central Kitchen)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('ck-produksi.store-and-approve') }}" method="POST">
                    @csrf
                    <div id="containerHiddenWoIdsCk"></div>

                    <div class="modal-body p-4">
                        <div id="batchCkDefisitAlert"></div>

                        <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-success">
                            <div class="small">
                                <span class="text-muted d-block fw-semibold mb-1">Daftar Work Order CK Terpilih (<span id="batchCkWoCount">0</span> WO):</span>
                                <div id="batchCkWoListPills" class="d-flex flex-wrap gap-1"></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary small">Tanggal Hasil Produksi</label>
                                <input type="date" name="tanggal_produksi" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary small">Gudang Penyimpanan</label>
                                <input type="text" class="form-control bg-light" value="Gudang Central Kitchen" readonly>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-2 small text-uppercase">Rekapitulasi Item & Input Qty Selesai Batch CK</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle text-center mb-0" style="min-width: 820px;">
                                <thead class="bg-light font-weight-bold">
                                    <tr>
                                        <th style="width: 45px;">No</th>
                                        <th class="text-start" style="min-width: 200px;">Nama Produk</th>
                                        <th style="width: 155px; min-width: 135px;">Target Total</th>
                                        <th style="width: 110px; min-width: 90px;">Sudah Jadi</th>
                                        <th style="width: 155px; min-width: 135px;">Total Sisa</th>
                                        <th style="width: 230px; min-width: 210px;">Input Qty Selesai</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyBatchCkItems">
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info py-2.5 px-3 small mb-0 d-flex align-items-start gap-2 rounded-3 border border-info-subtle shadow-none w-100" style="background-color: #f0f9ff; white-space: normal !important;">
                            <i class="bi bi-info-circle-fill text-info fs-5 flex-shrink-0 mt-0.5"></i>
                            <div class="flex-grow-1" style="min-width: 0; line-height: 1.5; color: #0c5460; white-space: normal !important;">
                                <strong>Catatan Approval Batch:</strong> Menekan <strong>Simpan Batch &amp; Approve HPP</strong> akan memotong stok bahan baku resep CK secara agregat (FIFO), mengalokasikan hasil produksi secara berurutan ke masing-masing WO terpilih, dan memperbarui status WO/Pesanan outlet secara otomatis.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light d-flex justify-content-between align-items-center">
                        <div id="batchCkFooterNotice"></div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="action" value="draft" id="btnDraftBatchCk" class="btn btn-outline-primary px-3 fw-semibold" onclick="return confirm('Simpan draft perubahan kuantitas batch CK ini?')">
                                <i class="bi bi-save me-1"></i> Simpan Draft Batch
                            </button>
                            <button type="submit" name="action" value="approve" id="btnSubmitBatchCk" class="btn btn-success px-4 fw-bold" onclick="return confirm('Simpan hasil produksi batch CK & Approve HPP otomatis untuk semua WO terpilih?')">
                                <i class="bi bi-check-circle-fill me-1"></i> Simpan Batch & Approve HPP
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- SCRIPT BATCH PRODUKSI CK --}}
    <script>
        function updateWoBatchSelectionCk() {
            const checks = document.querySelectorAll('.wo-check-ck:checked');
            const btn = document.getElementById('btnBatchProduksiCk');
            const countSpan = document.getElementById('countSelectedWoCk');
            const checkAll = document.getElementById('checkAllWoCk');

            if (countSpan) countSpan.textContent = checks.length;
            if (btn) btn.disabled = (checks.length === 0);

            const allChecks = document.querySelectorAll('.wo-check-ck');
            if (checkAll && allChecks.length > 0) {
                checkAll.checked = (checks.length === allChecks.length);
            }
        }

        function openBatchProduksiCkModal() {
            const selectedChecks = document.querySelectorAll('.wo-check-ck:checked');
            if (selectedChecks.length === 0) return;

            const hiddenContainer = document.getElementById('containerHiddenWoIdsCk');
            const woListPills = document.getElementById('batchCkWoListPills');
            const woCountSpan = document.getElementById('batchCkWoCount');
            const alertDiv = document.getElementById('batchCkDefisitAlert');
            const tbody = document.getElementById('tbodyBatchCkItems');

            hiddenContainer.innerHTML = '';
            woListPills.innerHTML = '';
            tbody.innerHTML = '';
            alertDiv.innerHTML = '';

            woCountSpan.textContent = selectedChecks.length;

            let consolidatedProducts = {};
            let defisitBahanMap = {};
            let missingResepMap = {};
            let hasDefisit = false;
            let hasMissingResep = false;

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

                // Check missing resep
                if (woData.has_missing_resep && woData.produk_tanpa_resep && woData.produk_tanpa_resep.length > 0) {
                    hasMissingResep = true;
                    woData.produk_tanpa_resep.forEach(ptr => {
                        missingResepMap[ptr.produk_id] = ptr;
                    });
                }

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
                                satuan_pembelian: item.satuan_pembelian || '',
                                konversi: parseFloat(item.konversi || 1),
                                target: 0,
                                sudah: 0,
                                sisa: 0,
                                has_resep: (item.has_resep !== false)
                            };
                        }
                        consolidatedProducts[pId].target += parseFloat(item.target || 0);
                        consolidatedProducts[pId].sudah += parseFloat(item.sudah || 0);
                        consolidatedProducts[pId].sisa += parseFloat(item.sisa || 0);
                        if (item.has_resep === false) {
                            consolidatedProducts[pId].has_resep = false;
                        }
                    });
                }
            });

            let alertsHtml = '';

            // Render Alert Missing Resep
            if (hasMissingResep) {
                let listHtml = '<ul class="mb-2 ps-3">';
                Object.values(missingResepMap).forEach(ptr => {
                    listHtml += `<li><strong>${ptr.nama_produk}</strong> (${ptr.kode_barang || '-'})</li>`;
                });
                listHtml += '</ul>';
                alertsHtml += `
                    <div class="alert alert-danger border-danger d-flex align-items-start gap-2 p-3 rounded-3 mb-3 small">
                        <i class="bi bi-x-circle-fill fs-5 text-danger mt-1 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1">Approval Batch Dikunci - Terdapat Menu Belum Memiliki Resep:</strong>
                            <span>Menu berikut belum memiliki formulasi resep sehingga HPP tidak dapat dihitung:</span>
                            ${listHtml}
                            <a href="{{ route('resep.create') }}" target="_blank" class="btn btn-sm btn-danger fw-semibold">
                                <i class="bi bi-journal-plus me-1"></i> Isi Resep Menu Sekarang
                            </a>
                        </div>
                    </div>
                `;
            }

            // Render Alert Defisit
            const isSuperAdminGlobal = {{ $isSuperAdmin ? 'true' : 'false' }};
            if (hasDefisit) {
                let listHtml = '<ul class="mb-0 ps-3">';
                Object.values(defisitBahanMap).forEach(def => {
                    listHtml += `<li>${def.nama}: Tersedia <strong>${def.stok.toLocaleString('id-ID')} ${def.satuan}</strong> / Combined Butuh <strong>${def.butuh.toLocaleString('id-ID')} ${def.satuan}</strong> (Kurang <span class="text-danger fw-bold">${def.kurang.toLocaleString('id-ID')} ${def.satuan}</span>)</li>`;
                });
                listHtml += '</ul>';

                let overrideSection = '';
                if (isSuperAdminGlobal) {
                    overrideSection = `
                        <div class="form-check mt-2 pt-2 border-top border-warning-subtle">
                            <input class="form-check-input" type="checkbox" name="override_stok" value="1" id="batchOverrideStok">
                            <label class="form-check-label fw-bold text-dark small" for="batchOverrideStok">
                                <i class="bi bi-shield-check text-success me-1"></i> Setujui &amp; Lanjutkan Produksi (Override Stok Kurang - Khusus Super Admin)
                            </label>
                            <div class="text-muted" style="font-size: 11px;">Centang opsi ini jika fisik bahan ada di dapur namun belum selesai di-stock opname. HPP otomatis dihitung menggunakan <strong>harga terakhir bahan baku</strong>.</div>
                        </div>
                    `;
                } else {
                    overrideSection = `
                        <div class="mt-2 pt-2 border-top border-warning-subtle text-muted" style="font-size: 11px;">
                            <i class="bi bi-info-circle me-1"></i> Jika stok fisik ada namun belum selesai stock opname, hubungi <strong>Super Admin</strong> untuk menyetujui produksi batch ini.
                        </div>
                    `;
                }

                alertsHtml += `
                    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                        <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1">Perhatian - Bahan Baku di Central Kitchen Kurang:</strong>
                            ${listHtml}
                            ${overrideSection}
                        </div>
                    </div>
                `;
            }

            if (!hasMissingResep && !hasDefisit) {
                alertsHtml = `
                    <div class="alert alert-success border-success d-flex align-items-center gap-2 p-2.5 rounded-3 mb-3 small">
                        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                        <span><strong>Resep & Bahan Baku Siap:</strong> Seluruh menu memiliki resep dan stok bahan baku di Gudang Central Kitchen mencukupi kebutuhan seluruh Work Order terpilih.</span>
                    </div>
                `;
            }

            alertDiv.innerHTML = alertsHtml;

            // Submit button lock logic helper
            function updateBatchSubmitButton() {
                const submitBtn = document.getElementById('btnSubmitBatchCk');
                const footerNotice = document.getElementById('batchCkFooterNotice');
                const overrideCheckbox = document.getElementById('batchOverrideStok');
                const isOverridden = overrideCheckbox ? overrideCheckbox.checked : false;

                const canApproveBatch = (!hasMissingResep && (!hasDefisit || isOverridden));

                if (submitBtn) {
                    submitBtn.disabled = !canApproveBatch;
                }

                if (footerNotice) {
                    if (!canApproveBatch) {
                        let msg = 'Harap lengkapi ';
                        if (hasMissingResep && hasDefisit) {
                            msg += 'resep menu dan lakukan permintaan bahan / persetujuan override terlebih dahulu.';
                        } else if (hasMissingResep) {
                            msg += 'resep menu terlebih dahulu.';
                        } else {
                            if (isSuperAdminGlobal) {
                                msg += 'persetujuan override stok (centang opsi di atas) atau lakukan permintaan bahan.';
                            } else {
                                msg += 'permintaan bahan baku atau hubungi Super Admin untuk persetujuan override.';
                            }
                        }
                        footerNotice.innerHTML = `<span class="text-danger small fw-semibold"><i class="bi bi-lock-fill me-1"></i> ${msg}</span>`;
                    } else {
                        footerNotice.innerHTML = '';
                    }
                }
            }

            updateBatchSubmitButton();

            const batchOverrideCb = document.getElementById('batchOverrideStok');
            if (batchOverrideCb) {
                batchOverrideCb.addEventListener('change', updateBatchSubmitButton);
            }

            // Render consolidated products table
            let idx = 1;
            Object.values(consolidatedProducts).forEach(item => {
                const tr = document.createElement('tr');
                const sisaVal = item.sisa > 0 ? item.sisa : item.target;
                const hasKonv = (item.satuan_pembelian && item.konversi > 1);

                let targetSub = '';
                if (hasKonv) {
                    const tPack = item.target / item.konversi;
                    targetSub = `<div class="small text-primary font-monospace" style="font-size: 11px;">(${tPack.toLocaleString('id-ID')} ${item.satuan_pembelian} @ ${item.konversi.toLocaleString('id-ID')} ${item.satuan})</div>`;
                }

                let sudahSub = '';
                if (hasKonv && item.sudah > 0) {
                    const sPack = item.sudah / item.konversi;
                    sudahSub = `<div class="small text-muted font-monospace" style="font-size: 11px;">(${sPack.toLocaleString('id-ID')} ${item.satuan_pembelian})</div>`;
                }

                let sisaDisplay = '';
                if (item.sisa > 0) {
                    let sPackSub = '';
                    if (hasKonv) {
                        const sPack = item.sisa / item.konversi;
                        sPackSub = `<div class="small text-danger font-monospace" style="font-size: 11px;">(${sPack.toLocaleString('id-ID')} ${item.satuan_pembelian})</div>`;
                    }
                    sisaDisplay = `<div>${item.sisa.toLocaleString('id-ID')} ${item.satuan}</div>${sPackSub}`;
                } else {
                    sisaDisplay = '<span class="badge bg-success">Tercapai</span>';
                }
                
                let inputCol = '';
                if (item.sisa > 0) {
                    let selectUnit = '';
                    if (hasKonv) {
                        selectUnit = `
                            <select name="satuan_input[]" class="form-select select-unit-hasil-ck fw-bold text-center bg-light text-primary" style="width: 85px; flex: 0 0 85px; padding-left: 6px; padding-right: 20px; font-size: 0.78rem;">
                                <option value="dasar">${item.satuan.toUpperCase()}</option>
                                <option value="konversi">${item.satuan_pembelian.toUpperCase()}</option>
                            </select>
                        `;
                    } else {
                        selectUnit = `
                            <input type="hidden" name="satuan_input[]" value="dasar">
                            <span class="input-group-text bg-light fw-bold text-muted" style="width: 58px; flex: 0 0 58px; justify-content: center; font-size: 0.78rem;">${item.satuan.toUpperCase()}</span>
                        `;
                    }

                    inputCol = `
                        <input type="hidden" name="produk_id[]" value="${item.produk_id}">
                        <div class="input-group input-group-sm flex-nowrap shadow-sm" style="min-width: 190px;">
                            <input type="number" name="qty_hasil[]" class="form-control text-end fw-bold input-qty-hasil-ck px-2" 
                                style="min-width: 100px;"
                                min="0" step="any" value="${item.sisa}" 
                                data-konversi="${hasKonv ? item.konversi : 1}"
                                data-satuan-dasar="${item.satuan}"
                                data-satuan-konv="${hasKonv ? item.satuan_pembelian : ''}" required>
                            ${selectUnit}
                        </div>
                        <div class="live-konversi-info small text-end mt-1 font-monospace" style="font-size: 11px; display: none;"></div>
                    `;
                } else {
                    inputCol = `
                        <input type="hidden" name="produk_id[]" value="${item.produk_id}">
                        <input type="hidden" name="qty_hasil[]" value="0">
                        <input type="hidden" name="satuan_input[]" value="dasar">
                        <span class="text-muted small">Sudah Selesai</span>
                    `;
                }

                const resepBadge = !item.has_resep 
                    ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 11px;"><i class="bi bi-journal-x"></i> Belum ada resep</span> <a href="{{ route('resep.create') }}" target="_blank" class="text-danger small ms-1 fw-semibold text-decoration-underline" style="font-size: 11px;">Isi Resep</a>`
                    : `<span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 11px;"><i class="bi bi-check2"></i> Resep Siap</span>`;

                tr.innerHTML = `
                    <td>${idx++}</td>
                    <td class="text-start">
                        <div class="fw-bold text-dark">${item.nama_produk}</div>
                        <div class="text-muted small">${item.kode_barang || ''}</div>
                        ${resepBadge}
                    </td>
                    <td class="fw-semibold"><div>${item.target.toLocaleString('id-ID')} ${item.satuan}</div>${targetSub}</td>
                    <td class="fw-bold text-success"><div>${item.sudah.toLocaleString('id-ID')} ${item.satuan}</div>${sudahSub}</td>
                    <td class="fw-bold text-danger">${sisaDisplay}</td>
                    <td style="min-width: 210px; width: 230px;">${inputCol}</td>
                `;
                tbody.appendChild(tr);
            });

            const modal = new bootstrap.Modal(document.getElementById('modalBatchProduksiCk'));
            modal.show();
        }

        // Live calculation helper untuk input hasil produksi CK (baik modal single WO maupun batch modal)
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('input-qty-hasil-ck')) {
                updateLiveKonversiOutput(e.target);
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('select-unit-hasil-ck')) {
                const row = e.target.closest('td');
                const inputQty = row.querySelector('.input-qty-hasil-ck');
                if (inputQty) {
                    updateLiveKonversiOutput(inputQty);
                }
            }
        });

        function updateLiveKonversiOutput(inputEl) {
            const container = inputEl.closest('td');
            if (!container) return;
            const unitSelect = container.querySelector('.select-unit-hasil-ck');
            const infoBox = container.querySelector('.live-konversi-info');
            if (!infoBox) return;

            const konversi = parseFloat(inputEl.getAttribute('data-konversi') || 1);
            const satuanDasar = inputEl.getAttribute('data-satuan-dasar') || '';
            const satuanKonv = inputEl.getAttribute('data-satuan-konv') || '';
            const qtyVal = parseFloat(inputEl.value || 0);
            const unitVal = unitSelect ? unitSelect.value : 'dasar';

            if (konversi > 1 && satuanKonv && qtyVal > 0) {
                if (unitVal === 'konversi') {
                    const totalGramasi = qtyVal * konversi;
                    infoBox.innerHTML = `= <strong class="text-primary">${totalGramasi.toLocaleString('id-ID')} ${satuanDasar}</strong> (@ ${konversi.toLocaleString('id-ID')} ${satuanDasar})`;
                    infoBox.style.display = 'block';
                } else {
                    const totalPack = qtyVal / konversi;
                    const packFmt = (totalPack % 1 === 0) ? totalPack.toFixed(0) : totalPack.toFixed(2);
                    infoBox.innerHTML = `= <strong class="text-success">${packFmt} ${satuanKonv}</strong> (@ ${konversi.toLocaleString('id-ID')} ${satuanDasar})`;
                    infoBox.style.display = 'block';
                }
            } else {
                infoBox.style.display = 'none';
            }
        }

        // Live calculation helper untuk Edit Qty WO Superadmin
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('input-qty-edit-wo')) {
                updateLiveKonversiEdit(e.target);
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('select-unit-edit-wo')) {
                const row = e.target.closest('td');
                const inputQty = row.querySelector('.input-qty-edit-wo');
                if (!inputQty) return;

                const konversi = parseFloat(inputQty.getAttribute('data-konversi') || 1);
                let val = parseFloat(inputQty.value || 0);

                if (konversi > 1 && val > 0) {
                    if (e.target.value === 'konversi') {
                        val = val / konversi;
                    } else {
                        val = val * konversi;
                    }
                    inputQty.value = (val % 1 === 0) ? val.toFixed(0) : parseFloat(val.toFixed(2));
                }
                updateLiveKonversiEdit(inputQty);
            }
        });

        function updateLiveKonversiEdit(inputEl) {
            const container = inputEl.closest('td');
            if (!container) return;
            const unitSelect = container.querySelector('.select-unit-edit-wo');
            const infoBox = container.querySelector('.live-konversi-edit-info');
            if (!infoBox) return;

            const konversi = parseFloat(inputEl.getAttribute('data-konversi') || 1);
            const satuanDasar = inputEl.getAttribute('data-satuan-dasar') || '';
            const satuanKonv = inputEl.getAttribute('data-satuan-konv') || '';
            const qtyVal = parseFloat(inputEl.value || 0);
            const unitVal = unitSelect ? unitSelect.value : 'dasar';

            if (konversi > 1 && satuanKonv && qtyVal > 0) {
                if (unitVal === 'konversi') {
                    const totalGramasi = qtyVal * konversi;
                    infoBox.innerHTML = `= <strong class="text-primary">${totalGramasi.toLocaleString('id-ID')} ${satuanDasar}</strong> (@ ${konversi.toLocaleString('id-ID')} ${satuanDasar})`;
                    infoBox.style.display = 'block';
                } else {
                    const totalPack = qtyVal / konversi;
                    const packFmt = (totalPack % 1 === 0) ? totalPack.toFixed(0) : totalPack.toFixed(2);
                    infoBox.innerHTML = `= <strong class="text-success">${packFmt} ${satuanKonv}</strong> (@ ${konversi.toLocaleString('id-ID')} ${satuanDasar})`;
                    infoBox.style.display = 'block';
                }
            } else {
                infoBox.innerHTML = `<span class="text-muted font-monospace">-</span>`;
                infoBox.style.display = 'block';
            }
        }

        // Tampilkan info konversi langsung saat modal Edit Qty terbuka
        document.addEventListener('shown.bs.modal', function(e) {
            const modalEl = e.target;
            if (modalEl && modalEl.id && modalEl.id.startsWith('modalEditQty')) {
                modalEl.querySelectorAll('.input-qty-edit-wo').forEach(input => {
                    updateLiveKonversiEdit(input);
                });
            }
        });

        // Produk list CK untuk baris baru
        const allProdukCkOptions = @json($allProdukCk ?? []);

        // Handler tombol Hapus Baris Eksisting
        document.addEventListener('click', function(e) {
            const btnDelExisting = e.target.closest('.btn-delete-existing-row');
            if (btnDelExisting) {
                const row = btnDelExisting.closest('tr');
                const table = row.closest('table');
                const modal = row.closest('.modal');
                const detailId = btnDelExisting.getAttribute('data-detail-id');
                
                // Hitung jumlah baris yang masih terlihat
                const visibleRows = table.querySelectorAll('tbody tr:not([style*="display: none"])');
                if (visibleRows.length <= 1) {
                    alert('Work Order harus memiliki minimal 1 item produk. Tidak dapat menghapus seluruh item.');
                    return;
                }

                if (confirm('Hapus item ini dari Work Order dan pesanan?')) {
                    // Sembunyikan baris
                    row.style.display = 'none';
                    // Nonaktifkan required di dalam baris agar form tetap valid
                    row.querySelectorAll('input, select').forEach(el => el.disabled = true);
                    
                    // Tambahkan hidden input deleted_detail_ids[]
                    const container = modal.querySelector('.deleted-inputs-container');
                    if (container) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'deleted_detail_ids[]';
                        hiddenInput.value = detailId;
                        container.appendChild(hiddenInput);
                    }
                    renumberRows(table);
                }
            }

            // Handler tombol Hapus Baris Baru
            const btnDelNew = e.target.closest('.btn-delete-new-row');
            if (btnDelNew) {
                const row = btnDelNew.closest('tr');
                const table = row.closest('table');
                row.remove();
                renumberRows(table);
            }

            // Handler tombol Tambah Baris Baru
            const btnAdd = e.target.closest('.btn-add-item-wo');
            if (btnAdd) {
                const targetTableSel = btnAdd.getAttribute('data-target-table');
                const table = document.querySelector(targetTableSel);
                if (!table) return;
                const tbody = table.querySelector('tbody');

                let optionsHtml = '<option value="">-- Pilih Produk / Item --</option>';
                allProdukCkOptions.forEach(p => {
                    const satDasar = p.satuan || 'pcs';
                    const hasKonv = p.satuan_pembelian && parseFloat(p.konversi_pembelian) > 1;
                    const satKonv = hasKonv ? p.satuan_pembelian.toUpperCase() : '';
                    const konvVal = hasKonv ? parseFloat(p.konversi_pembelian) : 1;
                    optionsHtml += `<option value="${p.id}" data-satuan-dasar="${satDasar}" data-satuan-konv="${satKonv}" data-konversi="${konvVal}">
                        ${p.nama} (${p.kode_barang || '-'})
                    </option>`;
                });

                const newRow = document.createElement('tr');
                newRow.className = 'row-item-wo table-success-subtle';
                newRow.innerHTML = `
                    <td class="row-number">#</td>
                    <td class="text-start">
                        <select name="new_produk_id[]" class="form-select form-select-sm select-new-produk-wo fw-bold text-dark" required>
                            ${optionsHtml}
                        </select>
                        <div class="small text-muted mt-1 produk-desc-label">Pilih produk yang ingin ditambahkan</div>
                    </td>
                    <td class="fw-semibold text-muted">
                        <span class="badge bg-info text-dark">Item Baru</span>
                    </td>
                    <td>
                        <div class="input-group input-group-sm flex-nowrap shadow-sm">
                            <input type="number" name="new_qty[]" class="form-control text-end fw-bold input-qty-edit-wo px-2" 
                                min="0.01" step="any" placeholder="0" 
                                data-konversi="1" data-satuan-dasar="pcs" data-satuan-konv="" required>
                            <select name="new_satuan_input[]" class="form-select select-unit-edit-wo fw-bold text-center bg-light text-primary" style="width: 100px; flex: 0 0 100px; padding-left: 8px; padding-right: 22px; font-size: 0.78rem;">
                                <option value="dasar">PCS</option>
                            </select>
                        </div>
                        <div class="live-konversi-edit-info small text-end mt-1 font-monospace" style="font-size: 11px; min-height: 16.5px;">
                            <span class="text-muted">-</span>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-3 btn-delete-new-row" title="Batalkan tambah item ini">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(newRow);
                renumberRows(table);
            }
        });

        // Handler ganti produk pada baris baru
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('select-new-produk-wo')) {
                const selectEl = e.target;
                const row = selectEl.closest('tr');
                const selectedOpt = selectEl.selectedOptions[0];
                const inputQty = row.querySelector('.input-qty-edit-wo');
                const unitSelect = row.querySelector('.select-unit-edit-wo');
                const descLabel = row.querySelector('.produk-desc-label');

                if (!selectedOpt || !selectedOpt.value) {
                    if (descLabel) descLabel.textContent = 'Pilih produk yang ingin ditambahkan';
                    return;
                }

                const satDasar = selectedOpt.getAttribute('data-satuan-dasar') || 'pcs';
                const satKonv = selectedOpt.getAttribute('data-satuan-konv') || '';
                const konvVal = parseFloat(selectedOpt.getAttribute('data-konversi') || 1);

                if (descLabel) {
                    if (konvVal > 1 && satKonv) {
                        descLabel.textContent = `1 ${satKonv} = ${konvVal.toLocaleString('id-ID')} ${satDasar}`;
                    } else {
                        descLabel.textContent = `Satuan dasar: ${satDasar}`;
                    }
                }

                inputQty.setAttribute('data-satuan-dasar', satDasar);
                inputQty.setAttribute('data-satuan-konv', satKonv);
                inputQty.setAttribute('data-konversi', konvVal);

                // Update opsi satuan
                let unitOptions = `<option value="dasar">${satDasar.toUpperCase()}</option>`;
                if (konvVal > 1 && satKonv) {
                    unitOptions += `<option value="konversi">${satKonv}</option>`;
                }
                unitSelect.innerHTML = unitOptions;

                updateLiveKonversiEdit(inputQty);
            }
        });

        function renumberRows(table) {
            let num = 1;
            table.querySelectorAll('tbody tr').forEach(row => {
                if (row.style.display !== 'none') {
                    const numCell = row.querySelector('.row-number');
                    if (numCell) numCell.textContent = num++;
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function () {
            const checkAllWoCk = document.getElementById('checkAllWoCk');
            if (checkAllWoCk) {
                checkAllWoCk.addEventListener('change', function () {
                    const allChecks = document.querySelectorAll('.wo-check-ck');
                    allChecks.forEach(c => c.checked = checkAllWoCk.checked);
                    updateWoBatchSelectionCk();
                });
            }

            // Aktifkan tab sesuai parameter URL (?tab=wo, ?tab=pending, dll)
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const triggerEl = document.querySelector(`#${tabParam}-tab`);
                if (triggerEl && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    const tabInstance = bootstrap.Tab.getOrCreateInstance(triggerEl);
                    tabInstance.show();
                }
            }

            // Listener untuk override persetujuan stok kurang (Single WO modal & Draft modal)
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('check-override-stok')) {
                    const woId = e.target.getAttribute('data-wo-id');
                    const hasMissingResep = e.target.getAttribute('data-has-missing-resep') === '1';
                    const btn = document.getElementById('btnApproveWo' + woId);
                    const lockNotice = document.getElementById('lockNoticeWo' + woId);

                    if (e.target.checked) {
                        if (!hasMissingResep && btn) {
                            btn.disabled = false;
                            btn.removeAttribute('title');
                            if (lockNotice) lockNotice.style.display = 'none';
                        }
                    } else {
                        if (btn) {
                            btn.disabled = true;
                            if (lockNotice) lockNotice.style.display = 'inline';
                        }
                    }
                }

                if (e.target.classList.contains('check-override-draft')) {
                    const prodId = e.target.getAttribute('data-prod-id');
                    const hasMissingResep = e.target.getAttribute('data-has-missing-resep') === '1';
                    const btn = document.getElementById('btnApproveDraft' + prodId);

                    if (e.target.checked) {
                        if (!hasMissingResep && btn) {
                            btn.disabled = false;
                        }
                    } else {
                        if (btn) {
                            btn.disabled = true;
                        }
                    }
                }
            });

            // Listener untuk Rincian Resep & Bahan — tampilkan modal popup
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-show-resep-modal');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();

                let resepData;
                try {
                    resepData = JSON.parse(btn.getAttribute('data-resep') || '{}');
                } catch(err) {
                    alert('Gagal memuat data resep.');
                    return;
                }

                const fmtNum = (v, dec) => parseFloat(v || 0).toLocaleString('id-ID', {minimumFractionDigits: dec, maximumFractionDigits: dec});
                const fmtRp  = (v) => 'Rp ' + fmtNum(v, 2);

                // Isi header modal
                document.getElementById('resepModalNamaProduk').textContent = resepData.produk || '-';
                document.getElementById('resepModalSubtitle').textContent =
                    'Standard Output: ' + fmtNum(resepData.output_qty, 0) + ' ' + (resepData.satuan_output || 'GR') +
                    '   ·   Total Produksi: ' + fmtNum(resepData.batch_count, 2) + ' Batch';

                // Bangun tabel bahan baku
                const bahanbaku = resepData.bahanbaku || [];
                let rows = '';
                let grandTotalHarga = 0;

                bahanbaku.forEach(function(b, idx) {
                    const altHtml = (b.alternatif || []).map(function(alt) {
                        return '<div class="ms-3 text-muted" style="font-size:10.5px;"><i class="bi bi-arrow-return-right me-1"></i> Substitusi (Prio ' + alt.prioritas + '): ' + alt.nama + '</div>';
                    }).join('');
                    const kodeHtml = b.kode ? '<span class="text-muted small font-monospace ms-1">(' + b.kode + ')</span>' : '';

                    const isFifo   = (b.sumber_harga === 'FIFO Aktual');
                    const isLast   = (b.sumber_harga === 'Harga Terakhir');
                    const badgeCls = isFifo ? 'bg-success-subtle text-success border border-success-subtle'
                                   : (isLast ? 'bg-warning-subtle text-warning border border-warning-subtle'
                                   : 'bg-secondary-subtle text-secondary');
                    const badgeLabel = b.sumber_harga || '-';
                    const sumberBadge = '<span class="badge ' + badgeCls + ' ms-1" style="font-size:9px;">' + badgeLabel + '</span>';

                    const totalHarga = parseFloat(b.total_harga || 0);
                    grandTotalHarga += totalHarga;

                    rows += '<tr>' +
                        '<td class="text-center align-middle">' + (idx + 1) + '</td>' +
                        '<td class="align-middle"><span class="fw-semibold text-dark">' + b.nama + '</span>' + kodeHtml + sumberBadge + altHtml + '</td>' +
                        '<td class="text-center align-middle">' + fmtNum(b.qty_per_batch, 2) + ' ' + b.satuan + '</td>' +
                        '<td class="text-center align-middle fw-semibold text-primary">' + fmtNum(b.total_qty, 2) + ' ' + b.satuan + '</td>' +
                        '<td class="text-end align-middle text-muted">' + fmtRp(b.harga_per_unit) + '/' + b.satuan + '</td>' +
                        '<td class="text-end align-middle fw-semibold text-danger">' + fmtRp(totalHarga) + '</td>' +
                    '</tr>';
                });

                // Baris total
                const totalRow = bahanbaku.length > 0
                    ? '<tr class="table-light fw-bold">' +
                        '<td colspan="5" class="text-end">Total Biaya Bahan Baku (BBB):</td>' +
                        '<td class="text-end text-danger">' + fmtRp(grandTotalHarga) + '</td>' +
                      '</tr>'
                    : '';

                const bodyHtml = bahanbaku.length === 0
                    ? '<p class="text-muted text-center py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada bahan baku dalam resep ini.</p>'
                    : '<table class="table table-sm table-hover table-bordered bg-white mb-0" style="font-size:13px;">' +
                        '<thead class="table-dark text-uppercase" style="font-size:11px;">' +
                          '<tr>' +
                            '<th style="width:4%;" class="text-center">No</th>' +
                            '<th>Bahan Baku / Alternatif</th>' +
                            '<th class="text-center" style="width:14%;">Takaran / Batch</th>' +
                            '<th class="text-center" style="width:14%;">Total Pemakaian</th>' +
                            '<th class="text-end" style="width:16%;">Harga / Unit</th>' +
                            '<th class="text-end" style="width:16%;">Total Harga Bahan</th>' +
                          '</tr>' +
                        '</thead>' +
                        '<tbody>' + rows + totalRow + '</tbody>' +
                      '</table>';

                document.getElementById('resepModalBody').innerHTML = bodyHtml;

                // Tampilkan modal
                const modalEl = document.getElementById('modalRincianResep');
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });
    </script>
</x-app-layout>
