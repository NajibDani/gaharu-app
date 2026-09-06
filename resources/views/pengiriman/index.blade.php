<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F7F5; }
        .table-custom-header th { background-color: #715745 !important; color: #ffffff !important; font-weight: 600; border-bottom: none; font-size: 0.85rem; padding: 12px; }
        .table-custom-body td { font-size: 0.85rem; padding: 12px; vertical-align: middle; }
        .btn-custom-orange { background-color: #DE8958; color: white; border: none; }
        .btn-custom-orange:hover { background-color: #C87443; color: white; }
        .summary-card { border-radius: 12px; border: 1px solid #DCD3CB; background: #ffffff; padding: 16px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .filter-pill { border-radius: 20px; padding: 7px 18px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s ease; border: 1px solid #DCD3CB; color: #495057; background: #ffffff; }
        .filter-pill:hover { background: #f1f3f5; color: #212529; }
        .filter-pill.active { background: #DE8958; color: #ffffff; border-color: #DE8958; }
        .action-btn { border-radius: 7px; padding: 6px 12px; font-size: 0.85rem; font-weight: 600; min-height: 36px; display: inline-flex; align-items: center; justify-content: center; }
    </style>

    <div class="container-fluid px-2 px-md-4 py-3 mb-5">
        
        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Pengiriman & Logistik</h4>
                <p class="text-muted mb-0 small">Daftar seluruh pesanan B2B dan Central Kitchen yang siap dan sudah dikirim</p>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
                <form action="{{ route('pengiriman.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    @if(request('tipe'))
                        <input type="hidden" name="tipe" value="{{ request('tipe') }}">
                    @endif
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kode pesanan / tujuan..." value="{{ request('search') }}" style="width: 220px; border-radius: 8px;">
                    <button type="submit" class="btn btn-sm text-white btn-custom-orange action-btn" style="border-radius: 8px; border: none; padding: 5px 15px; font-weight: 600;">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('pengiriman.index', request('tipe') ? ['tipe' => request('tipe')] : []) }}" class="btn btn-sm btn-secondary action-btn" style="border-radius: 8px; padding: 5px 15px; text-decoration: none;">Reset</a>
                    @endif
                </form>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary text-white rounded-3 shadow-sm px-3 action-btn d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        {{-- SUMMARY CARDS --}}
        <div class="row mb-4 g-3">
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Total Semua Pesanan</span>
                    <h4 class="fw-bold text-dark mb-0">{{ $totalData }} Pesanan</h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Belum Dikirim</span>
                    <h4 class="fw-bold text-danger mb-0">{{ $totalBelum }} Pesanan</h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Sudah Terkirim</span>
                    <h4 class="fw-bold text-success mb-0">{{ $totalTerkirim }} Selesai</h4>
                </div>
            </div>
        </div>

        {{-- FILTER TABS / PILLS --}}
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <a href="{{ route('pengiriman.index', request('search') ? ['search' => request('search')] : []) }}" 
               class="filter-pill {{ empty(request('tipe')) ? 'active' : '' }}">
                <i class="bi bi-grid-fill me-1"></i> Semua Pengiriman
            </a>
            <a href="{{ route('pengiriman.index', array_merge(request()->query(), ['tipe' => 'central_kitchen'])) }}" 
               class="filter-pill {{ request('tipe') === 'central_kitchen' ? 'active' : '' }}">
                <i class="bi bi-shop me-1"></i> Central Kitchen (Outlet)
            </a>
            <a href="{{ route('pengiriman.index', array_merge(request()->query(), ['tipe' => 'b2b'])) }}" 
               class="filter-pill {{ request('tipe') === 'b2b' ? 'active' : '' }}">
                <i class="bi bi-briefcase me-1"></i> B2B Customer
            </a>
        </div>

        {{-- ALERTS --}}
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm border-0 mb-4 small" role="alert" style="background-color: #d1e7dd; color: #0f5132;">
                <i class="bi bi-check-circle-fill me-2 flex-shrink-0"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm border-0 mb-4 small" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- NOTIFIKASI AKHIR BULAN --}}
        <div class="alert alert-warning d-flex align-items-center rounded-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #fff3cd; color: #664d03; border-left: 5px solid #ffc107 !important; padding: 16px 20px;">
            <i class="bi bi-exclamation-circle-fill me-3 fs-5 flex-shrink-0" style="color: #ffc107;"></i>
            <div>
                <strong class="d-block" style="font-size: 0.95rem;">Pemberitahuan Batas Akhir Bulan</strong>
                <span style="font-size: 0.85rem;">Harap segera lakukan penyelesaian seluruh pengiriman barang sebelum akhir bulan ini. Transaksi pada periode bulan berjalan tidak akan bisa diinput atau diubah setelah penutupan buku (Closing Jurnal) dilakukan.</span>
            </div>
        </div>

        {{-- MAIN TABLE CARD --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom-body">
                    <thead class="table-custom-header text-center">
                        <tr>
                            <th width="5%" class="py-3">No</th>
                            <th width="16%" class="text-start">Kode Pesanan</th>
                            <th width="12%">Kategori</th>
                            <th class="text-start">Tujuan / Outlet / Customer</th>
                            <th width="12%">Tgl Pesanan</th>
                            <th width="14%">Status Pengiriman</th>
                            <th width="16%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @php $no = $pesanans->firstItem() ?? 1; @endphp
                        @forelse($pesanans as $pesanan)
                            @php
                                $isCK = ($pesanan->tipe_pesanan ?? 'b2b') === 'central_kitchen';
                                $selesaiPengirimans = $pesanan->pengirimans->where('status_pengiriman', 'Selesai');
                                $isFullyShipped = $pesanan->is_fully_shipped;
                                $isPartiallyShipped = $pesanan->is_partially_shipped;
                                $hasAnyShipment = $selesaiPengirimans->isNotEmpty();
                            @endphp
                            <tr class="{{ !$pesanan->is_active ? 'table-secondary' : '' }}">
                                <td class="text-center text-secondary">{{ $no++ }}</td>
                                <td class="text-start fw-bold text-dark">{{ $pesanan->kode_pesanan }}</td>
                                <td class="text-center">
                                    @if($isCK)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small rounded-pill">Central Kitchen</span>
                                    @else
                                        <span class="badge bg-dark-subtle text-dark border border-secondary-subtle px-2 py-1 small rounded-pill">B2B Order</span>
                                    @endif
                                </td>
                                <td class="text-start text-dark fw-medium">
                                    {{ $pesanan->customer->nama ?? ($pesanan->gudang->nama ?? '-') }}
                                </td>
                                <td class="text-center text-secondary">
                                    {{ \Carbon\Carbon::parse($pesanan->tanggal)->format('Y-m-d') }}
                                </td>
                                <td class="text-center">
                                    @if($isFullyShipped)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-3">Terkirim Semua</span>
                                    @elseif($isPartiallyShipped)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-3">Sebagian Terkirim</span>
                                        <div class="text-muted small mt-1" style="font-size: 0.72rem;">{{ number_format($pesanan->total_qty_terkirim, 0, ',', '.') }} / {{ number_format($pesanan->total_qty_pesan, 0, ',', '.') }} pcs</div>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-3">Belum Kirim</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                                        @if(!$isFullyShipped)
                                            {{-- Tombol Kirim (untuk Belum Kirim & Sebagian Terkirim) --}}
                                            <button type="button" class="btn action-btn btn-custom-orange shadow-sm d-flex align-items-center gap-1 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalKirimPesanan{{ $pesanan->id }}" title="Kirim Sisa Pesanan">
                                                <i class="bi bi-send-fill"></i> Kirim
                                            </button>
                                        @endif

                                        @if($hasAnyShipment)
                                            {{-- Tombol Lihat Riwayat Surat Jalan --}}
                                            <button type="button" class="btn action-btn btn-outline-primary shadow-sm d-flex align-items-center gap-1 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalRiwayatKirim{{ $pesanan->id }}" title="Lihat Riwayat Surat Jalan">
                                                <i class="bi bi-file-earmark-check"></i> Surat Jalan
                                            </button>
                                        @endif
                                    </div>

                                    {{-- MODAL PROSES PENGIRIMAN POP-UP (DIRECT DISPATCH) --}}
                                    @if(!$isFullyShipped)
                                        <div class="modal fade text-start" id="modalKirimPesanan{{ $pesanan->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <form action="{{ route('pengiriman.store') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="pesanan_id" value="{{ $pesanan->id }}">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title fw-bold">
                                                                <i class="bi bi-truck me-2"></i> Proses Pengiriman: {{ $pesanan->kode_pesanan }}
                                                                @if($isPartiallyShipped)
                                                                    <span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">Pengiriman Lanjutan</span>
                                                                @endif
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 border-warning">
                                                                <div class="row g-2 small">
                                                                    <div class="col-md-6">
                                                                        <span class="text-muted d-block">Tujuan Pengiriman:</span>
                                                                        <strong class="text-dark fs-6">{{ $pesanan->customer->nama ?? ($pesanan->gudang->nama ?? '-') }}</strong>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <span class="text-muted d-block">Kategori:</span>
                                                                        <strong class="text-dark fs-6">{{ $isCK ? 'Central Kitchen Transfer' : 'Penjualan B2B Customer' }}</strong>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            @if($isPartiallyShipped)
                                                                <div class="alert alert-info d-flex align-items-center mb-3 py-2 small" role="alert">
                                                                    <i class="bi bi-info-circle-fill me-2"></i>
                                                                    <span>Pesanan ini sudah ada pengiriman sebelumnya. Qty di bawah otomatis menampilkan <strong>sisa yang belum terkirim</strong>.</span>
                                                                </div>
                                                            @endif

                                                            <div class="row mb-3 g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-bold small text-secondary">Tanggal Pengiriman <span class="text-danger">*</span></label>
                                                                    <input type="date" name="tanggal_pengiriman" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-bold small text-secondary">Kurir / Driver / Plat Nomor <span class="text-danger">*</span></label>
                                                                    <input type="text" name="kurir" class="form-control" placeholder="Contoh: Budi (B 1234 XYZ)" required>
                                                                </div>
                                                            </div>

                                                            <h6 class="fw-bold text-dark mb-2 small text-uppercase">Daftar Barang yang Dikirim</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered align-middle text-center mb-0">
                                                                    <thead class="bg-light font-weight-bold">
                                                                        <tr>
                                                                            <th style="width: 5%;">No</th>
                                                                            <th class="text-start">Nama Produk</th>
                                                                            <th style="width: 15%;">Qty Pesan</th>
                                                                            <th style="width: 15%;">Sudah Kirim</th>
                                                                            <th style="width: 20%;">Qty Kirim Sekarang</th>
                                                                            <th style="width: 10%;">Satuan</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @php $formIdx = 0; @endphp
                                                                        @forelse($pesanan->details as $idx => $d)
                                                                            @php
                                                                                $qtySisa = $d->qty_sisa;
                                                                                $qtyTerkirim = $d->qty_terkirim;
                                                                            @endphp
                                                                            <tr class="{{ $qtySisa <= 0 ? 'table-success' : '' }}">
                                                                                <td>{{ $idx + 1 }}</td>
                                                                                <td class="text-start fw-bold text-dark">
                                                                                    {{ $d->produk->nama ?? 'N/A' }}
                                                                                    @if($qtySisa <= 0)
                                                                                        <span class="badge bg-success-subtle text-success ms-1" style="font-size: 0.65rem;">Lengkap</span>
                                                                                    @endif
                                                                                    <input type="hidden" name="details[{{ $formIdx }}][barang_id]" value="{{ $d->produk_id }}">
                                                                                </td>
                                                                                <td class="text-muted">{{ number_format($d->qty, 0, ',', '.') }}</td>
                                                                                <td class="fw-bold {{ $qtyTerkirim > 0 ? 'text-primary' : 'text-muted' }}">{{ number_format($qtyTerkirim, 0, ',', '.') }}</td>
                                                                                <td>
                                                                                    @if($qtySisa > 0)
                                                                                        <input type="number" step="any" name="details[{{ $formIdx }}][qty_kirim]" class="form-control text-center py-1 fw-bold text-primary" value="{{ $qtySisa }}" min="0" max="{{ $qtySisa }}" required>
                                                                                    @else
                                                                                        <input type="hidden" name="details[{{ $formIdx }}][qty_kirim]" value="0">
                                                                                        <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> 0</span>
                                                                                    @endif
                                                                                </td>
                                                                                <td>
                                                                                    <span class="badge bg-light text-dark border">{{ $d->produk->satuan ?? 'pcs' }}</span>
                                                                                </td>
                                                                            </tr>
                                                                            @php $formIdx++; @endphp
                                                                        @empty
                                                                            <tr>
                                                                                <td colspan="6" class="text-muted py-3">Tidak ada rincian item pesanan.</td>
                                                                            </tr>
                                                                        @endforelse
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light py-2 justify-content-between">
                                                            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-success btn-sm px-4 fw-bold" onclick="return confirm('Konfirmasi pengiriman sekarang? Stok gudang akan dipotong sesuai qty yang diinput.')">
                                                                <i class="bi bi-check2-circle me-1"></i> Konfirmasi & Kirim
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- MODAL RIWAYAT SURAT JALAN POP-UP --}}
                                    @if($hasAnyShipment)
                                        <div class="modal fade text-start" id="modalRiwayatKirim{{ $pesanan->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header bg-dark text-white">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="bi bi-truck me-2"></i> Riwayat Pengiriman: {{ $pesanan->kode_pesanan }}
                                                            <span class="badge bg-light text-dark ms-2" style="font-size: 0.7rem;">{{ $selesaiPengirimans->count() }} Pengiriman</span>
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        {{-- Ringkasan Progress Pengiriman --}}
                                                        <div class="p-3 mb-3 bg-light rounded-3 border-start border-4 {{ $isFullyShipped ? 'border-success' : 'border-warning' }}">
                                                            <div class="row g-2 small">
                                                                <div class="col-md-4">
                                                                    <span class="text-muted d-block">Tujuan / Outlet:</span>
                                                                    <strong class="text-dark">{{ $pesanan->customer->nama ?? ($pesanan->gudang->nama ?? '-') }}</strong>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <span class="text-muted d-block">Total Pengiriman:</span>
                                                                    <strong class="text-dark">{{ $selesaiPengirimans->count() }} kali pengiriman</strong>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <span class="text-muted d-block">Status Keseluruhan:</span>
                                                                    @if($isFullyShipped)
                                                                        <span class="badge bg-success">Terkirim Semua</span>
                                                                    @else
                                                                        <span class="badge bg-warning text-dark">Sebagian Terkirim ({{ number_format($pesanan->total_qty_terkirim, 0, ',', '.') }}/{{ number_format($pesanan->total_qty_pesan, 0, ',', '.') }})</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Progress Per Item --}}
                                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase">Progress Per Produk</h6>
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered align-middle text-center mb-0 small">
                                                                <thead class="bg-light">
                                                                    <tr>
                                                                        <th class="text-start">Produk</th>
                                                                        <th>Qty Pesan</th>
                                                                        <th>Terkirim</th>
                                                                        <th>Sisa</th>
                                                                        <th>Progress</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($pesanan->details as $d)
                                                                        @php
                                                                            $persen = $d->qty > 0 ? round(($d->qty_terkirim / $d->qty) * 100) : 0;
                                                                        @endphp
                                                                        <tr>
                                                                            <td class="text-start fw-bold">{{ $d->produk->nama ?? 'N/A' }}</td>
                                                                            <td>{{ number_format($d->qty, 0, ',', '.') }}</td>
                                                                            <td class="fw-bold text-primary">{{ number_format($d->qty_terkirim, 0, ',', '.') }}</td>
                                                                            <td class="{{ $d->qty_sisa > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}">{{ number_format($d->qty_sisa, 0, ',', '.') }}</td>
                                                                            <td>
                                                                                <div class="progress" style="height: 18px;">
                                                                                    <div class="progress-bar {{ $persen >= 100 ? 'bg-success' : 'bg-warning' }}" style="width: {{ min($persen, 100) }}%;" role="progressbar">{{ $persen }}%</div>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        <hr>

                                                        {{-- Daftar Setiap Surat Jalan --}}
                                                        @foreach($selesaiPengirimans as $sjIdx => $sj)
                                                            <div class="mb-3">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <h6 class="fw-bold text-dark mb-0 small">
                                                                        <i class="bi bi-file-earmark-text me-1"></i> {{ $sj->no_pengiriman }}
                                                                    </h6>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <span class="text-muted small">{{ \Carbon\Carbon::parse($sj->tanggal_pengiriman)->format('d M Y') }} &middot; {{ $sj->kurir }}</span>
                                                                        <a href="{{ route('pengiriman.show', $sj->id) }}" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;">
                                                                            <i class="bi bi-printer"></i> Cetak
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                                <div class="table-responsive">
                                                                    <table class="table table-bordered align-middle text-center mb-0 small">
                                                                        <thead class="bg-light">
                                                                            <tr>
                                                                                <th style="width: 5%;">No</th>
                                                                                <th class="text-start">Nama Produk</th>
                                                                                <th style="width: 20%;">Qty Dikirim</th>
                                                                                <th style="width: 15%;">Satuan</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @forelse($sj->details as $dIdx => $sd)
                                                                                <tr>
                                                                                    <td>{{ $dIdx + 1 }}</td>
                                                                                    <td class="text-start fw-bold text-dark">
                                                                                        {{ $sd->barang->nama ?? ($sd->produk->nama ?? 'N/A') }}
                                                                                    </td>
                                                                                    <td class="fw-bold text-success">{{ number_format($sd->qty_kirim, 0, ',', '.') }}</td>
                                                                                    <td><span class="badge bg-light text-dark border">{{ $sd->barang->satuan ?? ($sd->produk->satuan ?? 'pcs') }}</span></td>
                                                                                </tr>
                                                                            @empty
                                                                                <tr>
                                                                                    <td colspan="4" class="text-muted py-2">Tidak ada rincian.</td>
                                                                                </tr>
                                                                            @endforelse
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="modal-footer bg-light py-2">
                                                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        Belum ada data pesanan.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">
            {{ $pesanans->links() }}
        </div>
    </div>
</x-app-layout>