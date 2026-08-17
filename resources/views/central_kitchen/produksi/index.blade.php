<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .table-custom-header th { background-color: #6a4126 !important; color: #ffffff !important; font-weight: 600; border-bottom: none; font-size: 0.78rem; padding: 10px; }
        .table-custom-body td { font-size: 0.8rem; padding: 8px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .btn-custom-orange { background-color: #db7946; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
        .nav-tabs .nav-link { color: #64748b; font-weight: 600; font-size: 0.85rem; border: none; border-bottom: 2px solid transparent; padding: 10px 16px; }
        .nav-tabs .nav-link.active { color: #db7946; border-bottom: 2px solid #db7946; background: transparent; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important;">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 text-sm mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 text-sm mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Central Kitchen Production</h4>
                <p class="text-muted small mb-0">Manajemen Work Order (WO) & Hasil Produksi Dapur Pusat</p>
            </div>
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
                                    <th>DAFTAR ITEM & TARGET QTY</th>
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
                                        <td>
                                            <ul class="list-unstyled mb-0 small">
                                                @foreach($p->details as $d)
                                                    <li><i class="bi bi-dot"></i> {{ $d->produk->nama ?? '-' }} : <strong>{{ number_format($d->qty, 0, ',', '.') }} {{ $d->produk->satuan ?? '' }}</strong></li>
                                                @endforeach
                                            </ul>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" data-bs-toggle="modal" data-bs-target="#modalOrder{{ $p->id }}">
                                                    <i class="bi bi-eye"></i> Detail
                                                </button>
                                                <form action="{{ route('ck-produksi.store-wo') }}" method="POST" class="d-inline" onsubmit="return confirm('Buat Work Order (WO) untuk pesanan ini?')">
                                                    @csrf
                                                    <input type="hidden" name="pesanan_id" value="{{ $p->id }}">
                                                    @foreach($p->details as $d)
                                                        <input type="hidden" name="produk_id[]" value="{{ $d->produk_id }}">
                                                        <input type="hidden" name="qty_rencana[]" value="{{ $d->qty }}">
                                                    @endforeach
                                                    <button type="submit" class="btn btn-sm btn-primary rounded-3">
                                                        <i class="bi bi-gear-fill me-1"></i> Buat WO CK
                                                    </button>
                                                </form>
                                            </div>

                                            {{-- MODAL DETAIL ORDER PENDING --}}
                                            <div class="modal fade text-start" id="modalOrder{{ $p->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h6 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i> Detail Pesanan CK: {{ $p->kode_pesanan }}</h6>
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
                                                            <h6 class="fw-bold mb-2 small text-uppercase text-secondary">Daftar Produk Pesanan</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered align-middle text-center mb-0">
                                                                    <thead class="bg-light font-weight-bold">
                                                                        <tr>
                                                                            <th class="text-start">Nama Produk</th>
                                                                            <th width="100">Qty Pesanan</th>
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
                    <div class="card-header bg-white py-3 px-4">
                        <h6 class="fw-bold mb-0 text-dark">Daftar Work Order Central Kitchen</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-custom-header">
                                <tr>
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
                                                                <button type="submit" class="btn btn-success px-4 fw-bold" {{ !$wo->is_bahan_sufficient ? 'disabled' : '' }} title="{{ !$wo->is_bahan_sufficient ? 'Stok bahan baku di Gudang Central Kitchen belum mencukupi' : '' }}">
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
                                    <th>OUTLET PEMESAN</th>
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
                                        <td><span class="badge bg-light text-dark border">{{ $prod->pesanan->customer->nama ?? '-' }}</span></td>
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
                                        <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat produksi CK.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
