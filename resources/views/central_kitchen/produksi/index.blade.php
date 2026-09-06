<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F7F5; }
        .table-custom-header th { background-color: #715745 !important; color: #ffffff !important; font-weight: 600; border-bottom: none; font-size: 0.8rem; padding: 12px 10px; }
        .table-custom-body td { font-size: 0.82rem; padding: 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .btn-custom-orange { background-color: #DE8958; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #C87443; color: white; }
        .nav-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 2px solid #DCD3CB; padding-bottom: 2px; }
        .nav-tabs .nav-item { flex-shrink: 0; }
        .nav-tabs .nav-link { color: #64748b; font-weight: 600; font-size: 0.85rem; border: none; border-bottom: 3px solid transparent; padding: 10px 16px; white-space: nowrap; }
        .nav-tabs .nav-link.active { color: #DE8958; border-bottom: 3px solid #DE8958; background: transparent; font-weight: 700; }
    </style>

    <div class="container-fluid px-2 px-md-4 py-3">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Central Kitchen Production</h4>
                <p class="text-muted small mb-0">Manajemen Work Order (WO) &amp; Hasil Produksi Central Kitchen</p>
            </div>
            <form action="{{ route('ck-produksi.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <select name="customer_id" class="form-select form-select-sm" style="min-width: 180px; border-radius: 8px; border: 1px solid #DCD3CB;" onchange="this.form.submit()">
                    <option value="">-- Semua Outlet Pemesan --</option>
                    @if(isset($customers))
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->nama }}</option>
                        @endforeach
                    @endif
                </select>
                @if(request('customer_id'))
                    <a href="{{ route('ck-produksi.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 8px; padding: 6px 12px;">Reset</a>
                @endif
            </form>
        </div>

        {{-- TABS NAVIGATION --}}
        <ul class="nav nav-tabs mb-4" id="ckTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-orders" type="button">
                    <i class="bi bi-clock-history me-1"></i> Order CK Masuk (Siap Buat WO)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="wo-tab" data-bs-toggle="tab" data-bs-target="#wo-list" type="button">
                    <i class="bi bi-file-earmark-text me-1"></i> Work Orders (WO CK)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="prod-tab" data-bs-toggle="tab" data-bs-target="#prod-history" type="button">
                    <i class="bi bi-check2-all me-1"></i> Riwayat Produksi CK
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="stok-tab" data-bs-toggle="tab" data-bs-target="#stok-divisi" type="button">
                    <i class="bi bi-layers-half me-1"></i> Stok BSJ per Divisi
                </button>
            </li>
        </ul>

        <div class="tab-content" id="ckTabContent">

            {{-- TAB 1: ORDER CK MASUK --}}
            <div class="tab-pane fade show active" id="pending-orders" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4">
                        <h6 class="fw-bold mb-0 text-dark">Pesanan Central Kitchen yang Siap Diproduksi</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center" style="width: 50px;">NO</th>
                                    <th>KODE ORDER</th>
                                    <th>OUTLET PEMESAN</th>
                                    <th>ESTIMASI KIRIM</th>
                                    <th class="text-center">TOTAL ITEM</th>
                                    <th class="text-center" style="width: 220px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body">
                                @forelse($pesananCkPending as $index => $p)
                                    <tr>
                                        <td class="text-center fw-semibold text-muted">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ $p->kode_pesanan }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $p->customer->nama ?? '-' }}</span></td>
                                        <td>{{ date('d M Y', strtotime($p->estimasi_kirim)) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-dark border px-3 py-2 fw-bold" style="font-size: 12px;">
                                                <i class="bi bi-boxes me-1 text-primary"></i> {{ $p->details->count() }} Item Pesanan
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $isAllSufficient = true;
                                                foreach($p->details as $d) {
                                                    if (($d->qty_kurang ?? 0) > 0) {
                                                        $isAllSufficient = false;
                                                    }
                                                }
                                            @endphp
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" data-bs-toggle="modal" data-bs-target="#modalOrder{{ $p->id }}">
                                                    <i class="bi bi-eye"></i> Detail
                                                </button>
                                                <form action="{{ route('ck-produksi.store-wo') }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $isAllSufficient ? 'Seluruh stok barang sudah tersedia. Alokasikan stok untuk pesanan ini?' : 'Buat Work Order (WO) untuk sisa kekurangan pesanan ini?' }}')">
                                                    @csrf
                                                    <input type="hidden" name="pesanan_id" value="{{ $p->id }}">
                                                    @foreach($p->details as $d)
                                                        <input type="hidden" name="produk_id[]" value="{{ $d->produk_id }}">
                                                        <input type="hidden" name="qty_rencana[]" value="{{ $d->qty_kurang }}">
                                                    @endforeach
                                                    
                                                    @if($isAllSufficient)
                                                        <button type="submit" class="btn btn-sm btn-success rounded-3">
                                                            <i class="bi bi-check-circle-fill me-1"></i> Alokasikan Stok
                                                        </button>
                                                    @else
                                                        <button type="submit" class="btn btn-sm btn-primary rounded-3">
                                                            <i class="bi bi-gear-fill me-1"></i> Buat WO CK
                                                        </button>
                                                    @endif
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
                </div>
            </div>

            {{-- TAB 2: WO LIST (DETAIL & INPUT PRODUKSI VIA POPUP) --}}
            <div class="tab-pane fade" id="wo-list" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-dark">Daftar Work Order Central Kitchen</h6>
                        <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm" id="btnBatchProduksiCk" disabled onclick="openBatchProduksiCkModal()">
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
                                    <th class="text-center" style="width: 50px;">NO</th>
                                    <th>KODE WO</th>
                                    <th>OUTLET PEMESAN</th>
                                    <th>TANGGAL WO</th>
                                    <th>TARGET & REALISASI</th>
                                    <th>STATUS</th>
                                    <th class="text-center" style="width: 250px;">AKSI</th>
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
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ $wo->kode_wo }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $wo->customer_nama }}</span></td>
                                        <td>{{ date('d M Y H:i', strtotime($wo->tanggal_wo)) }}</td>
                                        <td>
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
                                        <td>
                                            @if($wo->is_all_completed || strtolower($wo->status_wo) == 'selesai')
                                                <span class="badge bg-success">Selesai</span>
                                            @elseif($wo->is_bahan_sufficient || strtolower($wo->status_wo) == 'diproses')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Bahan Cukup (Siap)
                                                </span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" title="Bahan baku di Gudang CK belum mencukupi">
                                                    <i class="bi bi-exclamation-circle me-1"></i> Draft (Bahan Kurang)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                                @if(!$wo->is_all_completed && !$wo->is_bahan_sufficient)
                                                    <form action="{{ route('ck-produksi.kirim-bahan', $wo->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Minta bahan baku dari Gudang Utama untuk WO ini?')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning rounded-3 fw-semibold" title="Minta Bahan Baku ke Gudang Utama">
                                                            <i class="bi bi-box-arrow-right"></i> Minta Bahan
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(!$wo->is_all_completed)
                                                    <button type="button" class="btn btn-sm btn-success rounded-3 px-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalWo{{ $wo->id }}">
                                                        <i class="bi bi-hammer me-1"></i> Input & Approve
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2" data-bs-toggle="modal" data-bs-target="#modalWo{{ $wo->id }}">
                                                        <i class="bi bi-eye me-1"></i> Detail (Selesai)
                                                    </button>
                                                    <a href="{{ route('pengiriman.index', ['tipe' => 'central_kitchen', 'search' => $wo->kode_wo]) }}" class="btn btn-sm btn-outline-primary rounded-3 px-2 fw-semibold" title="Kirim ke Logistik Outlet">
                                                        <i class="bi bi-truck me-1"></i> Kirim
                                                    </a>
                                                @endif
                                            </div>

                                            {{-- MODAL DETAIL & INPUT PRODUKSI WO --}}
                                            <div class="modal fade text-start" id="modalWo{{ $wo->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title fw-bold">
                                                                <i class="bi bi-gear-wide-connected me-2"></i> Detail Work Order & Input Hasil Produksi: {{ $wo->kode_wo }}
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>

                                                        @if(!$wo->is_all_completed)
                                                        <form action="{{ route('ck-produksi.store-and-approve') }}" method="POST" onsubmit="return confirm('Simpan hasil produksi & Approve HPP otomatis?')">
                                                            @csrf
                                                            <input type="hidden" name="work_order_id" value="{{ $wo->id }}">

                                                            <div class="modal-body p-4">
                                                                @if(!$wo->is_bahan_sufficient && !empty($wo->defisit_bahan))
                                                                    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                                                                        <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1"></i>
                                                                        <div>
                                                                            <strong>Perhatian Ketersediaan Bahan Baku di Gudang Central Kitchen:</strong>
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
                                                                        <span><strong>Bahan Baku Siap:</strong> Stok bahan baku di Gudang Central Kitchen mencukupi seluruh kebutuhan resep. Anda dapat langsung memproses produksi.</span>
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
                                                                        Menekan tombol <strong>Simpan & Approve HPP</strong> akan menghitung HPP FIFO otomatis, memotong bahan baku CK, menambah stok jadi di Central Kitchen, dan mengalokasikan untuk pesanan outlet.
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
                                                            {{-- JIKA SUDAH SELESAI SEMUA --}}
                                                            <div class="modal-body p-4">
                                                                <div class="alert alert-success d-flex align-items-center mb-3">
                                                                    <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                                                                    <div>Seluruh target Work Order ini telah <strong>100% Selesai</strong> diproduksi dan siap dikirim ke outlet pemesan.</div>
                                                                </div>
                                                                <div class="table-responsive">
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
                                                            </div>
                                                            <div class="modal-footer bg-light py-2">
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
                                        <td colspan="7" class="text-center py-4 text-muted">Belum ada Work Order CK.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 3: RIWAYAT PRODUKSI CK (DETAIL POPUP) --}}
            <div class="tab-pane fade" id="prod-history" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4">
                        <h6 class="fw-bold mb-0 text-dark">Riwayat Produksi Central Kitchen</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
                                    <th class="text-center" style="width: 50px;">NO</th>
                                    <th>KODE PRODUKSI</th>
                                    <th>OUTLET / SUMBER</th>
                                    <th>DIVISI CK</th>
                                    <th>TANGGAL PRODUKSI</th>
                                    <th class="text-end">TOTAL HPP</th>
                                    <th>STATUS</th>
                                    <th class="text-center" style="width: 220px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="table-custom-body">
                                @forelse($riwayatProduksi as $index => $prod)
                                    @php
                                        $totalHppProd = $prod->details->sum('hpp_total');
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ $prod->kode_produksi }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $prod->pesanan->customer->nama ?? 'Stok Internal CK' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($prod->divisi)
                                                <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;font-size:0.72rem;font-weight:600;">
                                                    <i class="bi bi-layers-half me-1"></i>{{ $prod->divisi->nama }}
                                                </span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td>{{ date('d M Y', strtotime($prod->tanggal_mulai)) }}</td>
                                        <td class="text-end fw-bold text-danger">
                                            Rp {{ number_format($totalHppProd, 2, ',', '.') }}
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ strtolower($prod->status_produksi) == 'selesai' ? 'success' : 'warning text-dark' }}">
                                                {{ $prod->status_produksi }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-sm btn-info text-white rounded-3" data-bs-toggle="modal" data-bs-target="#modalProd{{ $prod->id }}">
                                                    <i class="bi bi-eye me-1"></i> Detail
                                                </button>

                                                @if(strtolower($prod->status_produksi) == 'selesai')
                                                    <a href="{{ route('pengiriman.index', ['tipe' => 'central_kitchen', 'search' => $prod->pesanan->kode_pesanan ?? $prod->kode_produksi]) }}" class="btn btn-sm btn-outline-primary rounded-3 px-2 fw-semibold" title="Kirim ke Logistik Outlet">
                                                        <i class="bi bi-truck me-1"></i> Kirim
                                                    </a>
                                                @endif

                                                 @if(strtolower($prod->status_produksi) == 'draft')
                                                    <form action="{{ route('ck-produksi.approve', $prod->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve Produksi CK? HPP per unit akan dihitung otomatis & barang masuk stok CK.')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success rounded-3" {{ (isset($prod->is_bahan_sufficient) && !$prod->is_bahan_sufficient) ? 'disabled' : '' }} title="{{ (isset($prod->is_bahan_sufficient) && !$prod->is_bahan_sufficient) ? 'Stok bahan baku di Gudang CK belum mencukupi' : 'Approve' }}">
                                                            <i class="bi bi-check-circle me-1"></i> Approve
                                                        </button>
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
                                                            @if(strtolower($prod->status_produksi) == 'draft' && isset($prod->is_bahan_sufficient) && !$prod->is_bahan_sufficient && !empty($prod->defisit_bahan))
                                                                <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                                                                    <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1"></i>
                                                                    <div>
                                                                        <strong>Perhatian Ketersediaan Bahan Baku di Gudang Central Kitchen:</strong>
                                                                        <ul class="mb-0 ps-3">
                                                                            @foreach($prod->defisit_bahan as $def)
                                                                                <li>{{ $def['nama'] }}: Tersedia <strong>{{ $def['stok'] }} {{ $def['satuan'] }}</strong> / Butuh <strong>{{ $def['butuh'] }} {{ $def['satuan'] }}</strong> (Kurang <span class="text-danger fw-bold">{{ $def['kurang'] }} {{ $def['satuan'] }}</span>)</li>
                                                                            @endforeach
                                                                        </ul>
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
                                                                            @endphp
                                                                            <tr>
                                                                                <td>{{ $idx + 1 }}</td>
                                                                                <td class="text-start fw-bold">{{ $d->produk->nama ?? 'N/A' }}</td>
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
                                                                <form action="{{ route('ck-produksi.approve', $prod->id) }}" method="POST" onsubmit="return confirm('Approve Produksi CK sekarang?')">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-success btn-sm px-3" {{ (isset($prod->is_bahan_sufficient) && !$prod->is_bahan_sufficient) ? 'disabled' : '' }}>
                                                                        <i class="bi bi-check-circle me-1"></i> Approve & Hitung HPP
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
                </div>
            </div>

            {{-- TAB 4: STOK BSJ PER DIVISI CK --}}
            <div class="tab-pane fade" id="stok-divisi" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white py-3 px-4">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Stok Bahan Setengah Jadi per Divisi Central Kitchen</h6>
                            <small class="text-muted">Pantau ketersediaan stok BSJ sebelum memutuskan produksi baru</small>
                        </div>
                    </div>

                    @if(!empty($stokBsjPerDivisi))
                        @foreach($stokBsjPerDivisi as $divisiNama => $items)
                            <div class="px-4 pt-3 pb-2">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge rounded-pill fs-6 px-3 py-2" style="background:#ede9fe;color:#6d28d9;">
                                        <i class="bi bi-layers-half me-1"></i>{{ $divisiNama }}
                                    </span>
                                    <span class="text-muted small">{{ count($items) }} jenis BSJ</span>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr class="text-secondary small">
                                                <th>NAMA BARANG (BSJ)</th>
                                                <th class="text-center" style="width:100px;">STOK SAAT INI</th>
                                                <th class="text-center" style="width:80px;">SATUAN</th>
                                                <th class="text-center" style="width:120px;">STATUS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($items as $item)
                                                <tr>
                                                    <td class="fw-semibold">{{ $item['nama'] }}</td>
                                                    <td class="text-center fw-bold {{ $item['jumlah'] <= 0 ? 'text-danger' : ($item['jumlah'] < 500 ? 'text-warning' : 'text-success') }}">
                                                        {{ number_format($item['jumlah'], 0, ',', '.') }}
                                                    </td>
                                                    <td class="text-center text-muted">{{ $item['satuan'] }}</td>
                                                    <td class="text-center">
                                                        @if($item['jumlah'] <= 0)
                                                            <span class="badge bg-danger">Habis</span>
                                                        @elseif($item['jumlah'] < 500)
                                                            <span class="badge bg-warning text-dark">Menipis</span>
                                                        @else
                                                            <span class="badge bg-success">Cukup</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                            Belum ada stok Bahan Setengah Jadi di Gudang Central Kitchen.
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL BATCH PRODUKSI CK --}}
    <div class="modal fade text-start" id="modalBatchProduksiCk" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-layers-fill me-2"></i> Produksi Batch Work Order (Central Kitchen)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('ck-produksi.store-and-approve') }}" method="POST" onsubmit="return confirm('Simpan hasil produksi batch CK & Approve HPP otomatis untuk semua WO terpilih?')">
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
                                <tbody id="tbodyBatchCkItems">
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center">
                            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                            <div>
                                Menekan <strong>Simpan Batch & Approve HPP</strong> akan memotong stok bahan baku resep CK secara agregat (FIFO), mengalokasikan hasil produksi secara berurutan ke masing-masing WO terpilih, dan memperbarui status WO/Pesanan outlet secara otomatis.
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
                            <strong>Perhatian Aggregat Ketersediaan Bahan Baku di Gudang Central Kitchen:</strong>
                            ${listHtml}
                        </div>
                    </div>
                `;
            } else {
                alertDiv.innerHTML = `
                    <div class="alert alert-success border-success d-flex align-items-center gap-2 p-2 rounded-3 mb-3 small">
                        <i class="bi bi-check-circle-fill fs-6 text-success"></i>
                        <span><strong>Bahan Baku Siap:</strong> Stok bahan baku di Gudang Central Kitchen mencukupi seluruh kebutuhan gabungan Work Order terpilih.</span>
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

            const modal = new bootstrap.Modal(document.getElementById('modalBatchProduksiCk'));
            modal.show();
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
        });
    </script>
</x-app-layout>
