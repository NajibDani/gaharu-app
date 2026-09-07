<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F7F5; }

        /* ===== TABLE ===== */
        .table-custom-header th {
            background-color: #715745 !important;
            color: #ffffff !important;
            font-weight: 600;
            border-bottom: none;
            font-size: 0.8rem;
            padding: 12px 10px;
            white-space: nowrap;
        }
        .table-custom-body td {
            font-size: 0.82rem;
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            line-height: 1.4;
        }
        .table-custom-body tr:hover td { background-color: #fcfbfa; }

        .btn-custom-orange { background-color: #DE8958; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 9px 18px; border-radius: 8px; transition: all 0.2s; }
        .btn-custom-orange:hover { background-color: #C87443; color: white; }
        .summary-card { border-radius: 12px; border: 1px solid #DCD3CB; background: #ffffff; padding: 16px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

        /* ===== BADGE ===== */
        .badge-subtle { border-radius: 6px; padding: 4px 10px; font-weight: 600; font-size: 0.72rem; display: inline-block; text-transform: capitalize; line-height: 1.4; }
        .badge-status-pending { background-color: #FFF8E1; color: #E65100; }
        .badge-status-proses { background-color: #E3F2FD; color: #0D47A1; }
        .badge-status-ready { background-color: #EDE7F6; color: #4A148C; }
        .badge-status-selesai { background-color: #E8F5E9; color: #1B5E20; }
        .badge-status-batal { background-color: #FFEBEE; color: #B71C1C; }

        /* ===== ACTION BUTTONS - Proportional compact 32px ===== */
        .action-btn-group { display: inline-flex; justify-content: center; align-items: center; gap: 5px; flex-wrap: nowrap; }
        .btn-action-base {
            border-radius: 7px; width: 32px; height: 32px; font-size: 0.82rem;
            border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: all 0.15s ease-in-out; background-color: transparent; flex-shrink: 0;
        }

        .btn-action-eye { background-color: #f0f9ff; color: #0369a1 !important; border-color: #e0f2fe; }
        .btn-action-eye:hover { background-color: #0284c7; color: white !important; border-color: #0284c7; }

        .btn-action-edit { background-color: #fffbec; color: #b45309 !important; border-color: #fef3c7; }
        .btn-action-edit:hover { background-color: #b45309; color: white !important; border-color: #b45309; }

        .btn-action-delete { background-color: #fef2f2; color: #b91c1c !important; border-color: #fee2e2; cursor: pointer; }
        .btn-action-delete:hover { background-color: #b91c1c; color: white !important; border-color: #b91c1c; }

        .btn-action-print { background-color: #f0fdf4; color: #15803d !important; border-color: #dcfce7; }
        .btn-action-print:hover { background-color: #15803d; color: white !important; border-color: #15803d; }

        .btn-action-pdf { background-color: #fee2e2; color: #b91c1c !important; border-color: #fee2e2; }
        .btn-action-pdf:hover { background-color: #dc2626; color: white !important; border-color: #dc2626; }

        /* Kebab / dropdown "aksi lain" */
        .btn-action-more { background-color: #f8fafc; color: #64748b !important; border-color: #e2e8f0; }
        .btn-action-more:hover, .btn-action-more.show { background-color: #475569; color: white !important; border-color: #475569; }
        .dropdown-menu-actions {
            min-width: 195px;
            padding: 6px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
            z-index: 1080;
        }
        .dropdown-menu-actions .dropdown-item {
            border-radius: 6px;
            font-size: 0.81rem;
            padding: 7px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.15s;
        }
        .dropdown-menu-actions .dropdown-item i {
            font-size: 0.9rem;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        .dropdown-menu-actions .dropdown-item:hover:not(.disabled) { background-color: #f1f5f9; }
        .dropdown-menu-actions .dropdown-item.text-danger:hover:not(.disabled) { background-color: #fef2f2; color: #dc2626 !important; }
        .dropdown-menu-actions .dropdown-item.text-success:hover:not(.disabled) { background-color: #f0fdf4; color: #16a34a !important; }
        .dropdown-menu-actions .dropdown-item.disabled { opacity: 0.65; cursor: not-allowed; background-color: transparent; }

        /* Tombol Bayar - touch target friendly */
        .btn-pay-small {
            background-color: #fff7ed;
            color: #DE8958;
            font-weight: 700;
            font-size: 0.78rem;
            border-radius: 7px;
            padding: 6px 12px;
            border: 1px solid #DE8958;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            min-height: 34px;
        }
        .btn-pay-small:hover {
            background-color: #DE8958;
            color: white !important;
            border-color: #DE8958;
        }
    </style>

    <div class="container-fluid px-2 px-md-4 py-3">

        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark" style="font-weight: 800; letter-spacing: -0.5px;">Permintaan Cold Kitchen</h4>
                <p class="text-muted mb-0 small"><i class="bi bi-info-circle me-1"></i> Manajemen pengajuan permintaan barang ke Cold Kitchen &amp; pencatatan harga/pembayaran.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form action="{{ route('pesanan.index') }}" method="GET" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                    {{-- Dropdown Outlet --}}
                    <div style="width: 200px;">
                        <select name="customer_id" class="form-select form-select-sm" style="border-radius: 8px; border: 1px solid #DCD3CB; height: 36px;" onchange="this.form.submit()">
                            <option value="">-- Semua Outlet --</option>
                            @if(isset($customers))
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->nama }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    {{-- Search Input Group Terintegrasi --}}
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari permintaan/customer..." value="{{ request('search') }}" style="border-radius: 8px 0 0 8px; border: 1px solid #DCD3CB; height: 36px;">
                        <button type="submit" class="btn text-white d-inline-flex align-items-center gap-1" style="background-color: #DE8958; border-radius: 0 8px 8px 0; height: 36px; padding: 0 14px; font-weight: 600;">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>

                    {{-- Tombol Reset --}}
                    @if(request('search') || request('customer_id'))
                        <a href="{{ route('pesanan.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; height: 36px; width: 36px;" title="Reset Filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </form>

                <button type="button" class="btn btn-outline-success btn-sm d-none align-items-center gap-1 shadow-sm" id="btnBulkBayarPesanan" data-bs-toggle="modal" data-bs-target="#modalBulkBayarPesanan" style="border-radius: 8px; font-weight: 600; height: 36px; padding: 0 14px;">
                    <i class="bi bi-wallet2"></i> Bayar Terpilih (<span id="countSelectedPesanan">0</span>)
                </button>

                <a href="{{ route('pesanan.create') }}" class="btn btn-custom-orange shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="height: 36px; padding: 0 16px; border-radius: 8px; white-space: nowrap;">
                    <i class="bi bi-plus-circle-fill"></i> Tambah Permintaan Baru
                </a>
            </div>
        </div>

        {{-- DEFINISI VARIABEL --}}
        @php
            $dataPesanan = $pesanans ?? $pesanan ?? collect();

            $totalPesanan = $totalPesanan ?? $dataPesanan->count();
            $totalProses = $totalProses ?? $dataPesanan->whereIn('status_pesanan', ['Draft', 'Proses', 'Siap kirim', 'pending', 'ready'])->count();
            $totalSelesai = $totalSelesai ?? $dataPesanan->where('status_pesanan', 'Selesai')->count();
        @endphp

        {{-- SUMMARY CARDS --}}
        <div class="row mb-4 g-3">
            <div class="col-12 col-md-4">
                <div class="summary-card d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background-color: #eff6ff; color: #2563eb; font-size: 1.25rem;">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <div>
                        <span class="text-secondary mb-1 d-block fw-medium small">Total Permintaan Masuk</span>
                        <h4 class="fw-bold text-dark mb-0">{{ number_format($totalPesanan) }} <small class="text-muted fw-normal" style="font-size: 0.75rem;">Permintaan</small></h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background-color: #fffbeb; color: #d97706; font-size: 1.25rem;">
                        <i class="bi bi-gear-fill"></i>
                    </div>
                    <div>
                        <span class="text-secondary mb-1 d-block fw-medium small">Dalam Pengerjaan / Produksi</span>
                        <h4 class="fw-bold text-warning mb-0">{{ number_format($totalProses) }} <small class="text-muted fw-normal" style="font-size: 0.75rem;">Transaksi</small></h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background-color: #f0fdf4; color: #16a34a; font-size: 1.25rem;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <span class="text-secondary mb-1 d-block fw-medium small">Selesai Produksi</span>
                        <h4 class="fw-bold text-success mb-0">{{ number_format($totalSelesai) }} <small class="text-muted fw-normal" style="font-size: 0.75rem;">Selesai</small></h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- MAIN TABLE CARD --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5" style="background: white;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom-body w-100">
                    <thead class="table-custom-header text-center">
                        <tr>
                            <th width="40px" class="text-center">
                                <input type="checkbox" id="checkAllPesanan" class="form-check-input" title="Pilih Semua">
                            </th>
                            <th width="45px">No</th>
                            <th class="text-start text-nowrap" style="min-width: 145px;">Kode Permintaan</th>
                            <th class="text-start text-nowrap">Pemesan / Outlet</th>
                            <th class="text-nowrap">Tanggal</th>
                            <th class="text-end text-nowrap">Total Nilai</th>
                            <th class="text-center text-nowrap">Status</th>
                            <th class="text-center text-nowrap">Bayar</th>
                            <th class="text-center text-nowrap" style="width: 125px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @php $no = 1; @endphp
                        @forelse($dataPesanan as $item)
                            @php
                                $totalNilaiItem = $item->total_harga ?? $item->total_pesanan ?? 0;
                                $sudahBayarItem = isset($item->pembayaran) ? $item->pembayaran->sum('jumlah_bayar') : 0;
                                $sisaTagihanItem = max(0, $totalNilaiItem - $sudahBayarItem);
                                $isLunasOrBatal = ($item->status_pembayaran == 'Lunas' || in_array(strtolower($item->status_pesanan ?? ''), ['batal', 'dibatalkan']));
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input check-pesanan" 
                                           value="{{ $item->id }}" 
                                           data-kode="{{ $item->kode_pesanan }}" 
                                           data-customer="{{ $item->customer->nama ?? '-' }}" 
                                           data-total="{{ $totalNilaiItem }}" 
                                           data-sisa="{{ $sisaTagihanItem }}"
                                           {{ ($isLunasOrBatal || $sisaTagihanItem <= 0) ? 'disabled' : '' }}>
                                </td>
                                <td class="text-center text-secondary fw-medium">{{ $no++ }}</td>
                                <td class="text-start fw-bold text-nowrap" style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.82rem; color: #6a4126;">#{{ $item->kode_pesanan }}</td>
                                <td class="text-start">
                                    <div class="fw-semibold text-dark mb-0">{{ $item->customer->nama ?? 'Umum / Tanpa Nama' }}</div>
                                    @if(isset($item->customer->no_hp))
                                        <div class="text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem; margin-top: 1px;">
                                            <i class="bi bi-telephone text-secondary" style="font-size: 0.68rem;"></i> {{ $item->customer->no_hp }}
                                        </div>
                                    @endif
                                    @if(isset($item->customer->alamat))
                                        <div class="text-muted text-truncate d-block" style="font-size: 0.72rem; max-width: 250px; margin-top: 1px;" title="{{ $item->customer->alamat }}">
                                            <i class="bi bi-geo-alt text-secondary" style="font-size: 0.68rem;"></i> {{ $item->customer->alamat }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center text-secondary text-nowrap">
                                    {{ date('d M Y', strtotime($item->tanggal ?? $item->tanggal_pesanan ?? $item->created_at)) }}
                                </td>
                                <td class="text-end fw-bold text-dark text-nowrap">
                                    Rp {{ number_format($totalNilaiItem, 0, ',', '.') }}
                                </td>
                                <td class="text-center text-nowrap">
                                    @php
                                        $statusStr = strtolower($item->status_pesanan ?? 'pending');
                                        $statusClass = match($statusStr) {
                                            'pending', 'draft' => 'badge-status-pending',
                                            'proses' => 'badge-status-proses',
                                            'ready', 'siap kirim' => 'badge-status-ready',
                                            'selesai' => 'badge-status-selesai',
                                            'batal', 'dibatalkan' => 'badge-status-batal',
                                            default => 'badge-status-pending'
                                        };
                                    @endphp
                                    <span class="badge-subtle {{ $statusClass }}">
                                        {{ $item->status_pesanan }}
                                    </span>
                                </td>

                                {{-- KOLOM STATUS BAYAR --}}
                                <td class="text-center text-nowrap">
                                    @if(isset($item->status_pembayaran))
                                        @if($item->status_pembayaran == 'Belum Bayar')
                                            <span class="badge-subtle badge-status-batal">Belum</span>
                                        @elseif($item->status_pembayaran == 'DP')
                                            <span class="badge-subtle badge-status-pending">DP 60%</span>
                                        @else
                                            <span class="badge-subtle badge-status-selesai">Lunas</span>
                                        @endif
                                    @else
                                        <span class="badge-subtle badge-status-batal">Belum</span>
                                    @endif
                                </td>

                                {{-- PANEL AKSI --}}
                                <td class="text-center text-nowrap">
                                    <div class="action-btn-group">

                                        {{-- 1. TOMBOL DETAIL --}}
                                        <a href="{{ route('pesanan.show', $item->id) }}" class="btn-action-base btn-action-eye" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>

                                        {{-- 2. TOMBOL CETAK SALES ORDER (PDF) --}}
                                        <a href="{{ route('pesanan.cetak-pdf', $item->id) }}" target="_blank" class="btn-action-base btn-action-pdf" title="Cetak Sales Order (PDF)">
                                            <i class="bi bi-file-earmark-pdf-fill"></i>
                                        </a>

                                        {{-- 3. DROPDOWN MENU OPSI --}}
                                        <div class="dropdown">
                                            <button class="btn-action-base btn-action-more" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed" data-bs-boundary="viewport" aria-expanded="false" title="Menu Opsi">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-actions">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalSetHarga{{ $item->id }}">
                                                        <i class="bi bi-tag-fill text-success"></i> Input / Ubah Harga
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('pesanan.kwitansi', $item->id) }}" target="_blank">
                                                        <i class="bi bi-printer-fill text-primary"></i> Cetak Kwitansi
                                                    </a>
                                                </li>

                                                @if(!isset($item->wo_status) || $item->wo_status === null)
                                                    <li>
                                                        <a class="dropdown-item text-dark" href="{{ route('pesanan.edit', $item->id) }}">
                                                            <i class="bi bi-pencil-fill text-warning"></i> Edit Pesanan
                                                        </a>
                                                    </li>
                                                @else
                                                    <li>
                                                        <span class="dropdown-item disabled text-muted py-2" title="Terkunci: sudah masuk Work Order">
                                                            <i class="bi bi-lock-fill text-secondary"></i>
                                                            <span>
                                                                Edit Terkunci
                                                                <small class="d-block text-muted" style="font-size: 0.68rem;">WO: {{ ucfirst($item->wo_status) }}</small>
                                                            </span>
                                                        </span>
                                                    </li>
                                                @endif

                                                @if(isset($item->status_pembayaran) && $item->status_pembayaran != 'Lunas' && strtolower($item->status_pesanan ?? '') != 'dibatalkan' && strtolower($item->status_pesanan ?? '') != 'batal')
                                                    <li>
                                                        <a class="dropdown-item text-success" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalBayar{{ $item->id }}">
                                                            <i class="bi bi-wallet2"></i> Bayar Tagihan
                                                        </a>
                                                    </li>
                                                @endif

                                                <li><hr class="dropdown-divider my-1"></li>

                                                @if(strtolower($item->status_pesanan ?? '') !== 'dibatalkan' && strtolower($item->status_pesanan ?? '') !== 'batal')
                                                    @if(!isset($item->wo_status) || $item->wo_status === null || $item->wo_status === 'draft')
                                                        <li>
                                                            <form action="{{ route('pesanan.batal', $item->id) }}" method="POST" class="m-0 p-0" onsubmit="return confirm('Batalkan pesanan ini?')">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger w-100">
                                                                    <i class="bi bi-x-circle-fill"></i> Batalkan Transaksi
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                @endif

                                                @if(!isset($item->wo_status) || $item->wo_status === null)
                                                    <li>
                                                        <form action="{{ route('pesanan.destroy', $item->id) }}" method="POST" class="m-0 p-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger w-100">
                                                                <i class="bi bi-trash3-fill"></i> Hapus Permanen
                                                            </button>
                                                        </form>
                                                    </li>
                                                @else
                                                    <li>
                                                        <span class="dropdown-item disabled text-muted py-2" title="Terkunci: sudah masuk Work Order">
                                                            <i class="bi bi-lock-fill text-secondary"></i>
                                                            <span>
                                                                Hapus Terkunci
                                                                <small class="d-block text-muted" style="font-size: 0.68rem;">WO aktif</small>
                                                            </span>
                                                        </span>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>

                                    </div>

                                    {{-- MODAL PEMBAYARAN (utuh, tidak diubah) --}}
                                    @if(isset($item->status_pembayaran) && $item->status_pembayaran != 'Lunas' && strtolower($item->status_pesanan ?? '') != 'dibatalkan' && strtolower($item->status_pesanan ?? '') != 'batal')
                                    <div class="modal fade text-start" id="modalBayar{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form action="{{ route('pesanan.bayar', $item->id) }}" method="POST" class="w-100" enctype="multipart/form-data">
                                                @csrf
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header text-white border-0 p-4" style="background-color: #715745;">
                                                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2"><i class="bi bi-shield-check"></i> Form Input Pembayaran</h5>
                                                        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 bg-white">
                                                        @php
                                                            $totalNilai = $item->total_harga ?? $item->total_pesanan ?? 0;
                                                            $sudahDibayar = isset($item->pembayaran) ? $item->pembayaran->sum('jumlah_bayar') : 0;
                                                            $sisaTagihan = max(0, $totalNilai - $sudahDibayar);
                                                            $minInput = 1;
                                                        @endphp

                                                        <div class="p-3 rounded-3 mb-4" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block small mb-1">Invoice: <strong class="text-dark">#{{ $item->kode_pesanan }}</strong></span>
                                                            <span class="text-muted d-block small">Total Nilai Pesanan:</span>
                                                            <h3 class="fw-bold text-dark mb-2">Rp {{ number_format($totalNilai, 0, ',', '.') }}</h3>
                                                            <div class="text-muted small">Sisa Tagihan / Pelunasan: <strong class="text-success">Rp {{ number_format($sisaTagihan, 0, ',', '.') }}</strong></div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Jumlah Bayar (Rp)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light border-end-0 fw-semibold text-muted">Rp</span>
                                                                <input type="number" name="jumlah_bayar" class="form-control border-start-0" min="{{ $minInput }}" max="{{ $sisaTagihan > 0 ? $sisaTagihan : $totalNilai }}" placeholder="Masukkan nominal pembayaran" required style="outline: none; box-shadow: none;">
                                                            </div>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label fw-semibold small text-secondary">Tanggal Bayar</label>
                                                                <input type="date" name="tanggal_bayar" class="form-control input-tanggal-bayar" value="{{ date('Y-m-d') }}" required>
                                                                <div class="d-flex gap-1 flex-wrap mt-1">
                                                                    <button type="button" class="btn btn-xs btn-outline-primary btn-quick-akhir-bulan py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'ini')">
                                                                        <i class="bi bi-calendar-check me-1"></i> Akhir Bulan Ini
                                                                    </button>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'depan')">
                                                                        <i class="bi bi-calendar-plus me-1"></i> Akhir Bulan Depan
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label fw-semibold small text-secondary">Metode Pembayaran</label>
                                                                <select name="metode_pembayaran" class="form-select text-secondary" required onchange="handleMetodePembayaranChange(this)">
                                                                    <option value="Cash">Cash / Tunai</option>
                                                                    <option value="Transfer">Transfer Bank</option>
                                                                    <option value="QRIS">QRIS</option>
                                                                    <option value="COD">COD (Cash on Delivery)</option>
                                                                    <option value="Termin">Termin / Piutang</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Catatan Tambahan</label>
                                                            <textarea name="catatan" class="form-control" rows="2" placeholder="Nama bank pengirim, nomor referensi..."></textarea>
                                                        </div>

                                                        <div class="mb-0">
                                                            <label class="form-label fw-semibold small text-secondary">Upload Bukti Pembayaran <span class="text-muted">(bisa >1 gambar)</span></label>
                                                            <input type="file" name="bukti_file[]" class="form-control" accept="image/*" multiple>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 p-4 pt-0 bg-white">
                                                        <button type="button" class="btn btn-light px-4 rounded-3 text-secondary" data-bs-dismiss="modal" style="font-size:0.85rem; font-weight:600;">Kembali</button>
                                                        <button type="submit" class="btn btn-custom-orange px-4 rounded-3 fw-semibold border-0" style="background-color: #6a4126; color: white;">Simpan Bayar</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- MODAL INPUT/UPDATE HARGA JUAL PER PCS --}}
                                    <div class="modal fade text-start" id="modalSetHarga{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <form action="{{ route('pesanan.update-harga-jual', $item->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header text-white border-0 p-4" style="background-color: #715745;">
                                                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                                                            <i class="bi bi-tags-fill"></i> Atur Harga Jual per Pcs - #{{ $item->kode_pesanan }}
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 bg-white">
                                                        <div class="alert alert-warning py-2 px-3 small mb-3">
                                                            <i class="bi bi-info-circle me-1"></i> Masukkan harga jual per pcs untuk pesanan Cold Kitchen ini. Total tagihan akan otomatis diperbarui sesuai harga yang diinput.
                                                        </div>
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered table-sm align-middle mb-0">
                                                                <thead class="table-light small">
                                                                    <tr>
                                                                        <th>Produk</th>
                                                                        <th width="12%" class="text-center">Qty Pesan</th>
                                                                        <th width="12%" class="text-center">Satuan</th>
                                                                        <th width="30%">Harga Satuan (Rp)</th>
                                                                        <th width="20%" class="text-end">Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="small">
                                                                    @php $totModal = 0; @endphp
                                                                    @foreach($item->details as $d)
                                                                        @php
                                                                            $dHarga = floatval($d->harga ?? 0);
                                                                            $dSubtotal = floatval($d->subtotal ?? ($d->qty * $dHarga));
                                                                            $totModal += $dSubtotal;
                                                                        @endphp
                                                                        <tr>
                                                                            <td>
                                                                                <input type="hidden" name="detail_id[]" value="{{ $d->id }}">
                                                                                <span class="fw-semibold text-dark">{{ $d->produk->nama_produk ?? $d->nama_produk ?? 'Produk' }}</span>
                                                                            </td>
                                                                            <td class="text-center fw-bold">{{ $d->qty }}</td>
                                                                            <td class="text-center text-muted">{{ $d->satuan ?? ($d->produk->satuan ?? 'pcs') }}</td>
                                                                            <td>
                                                                                <div class="input-group input-group-sm">
                                                                                    <span class="input-group-text bg-light">Rp</span>
                                                                                    <input type="number" name="harga[]" class="form-control form-control-sm text-end input-modal-harga" value="{{ (int)$dHarga }}" min="0" step="100" data-qty="{{ $d->qty }}" required oninput="recalcModalSubtotal(this)">
                                                                                </div>
                                                                            </td>
                                                                            <td class="text-end fw-bold text-dark subtotal-cell">
                                                                                Rp {{ number_format($dSubtotal, 0, ',', '.') }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="table-light fw-bold">
                                                                        <td colspan="4" class="text-end">Estimasi Total Tagihan:</td>
                                                                        <td class="text-end text-success modal-total-preview">Rp {{ number_format($totModal, 0, ',', '.') }}</td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 p-4 pt-0 bg-white">
                                                        <button type="button" class="btn btn-light px-4 rounded-3 text-secondary" data-bs-dismiss="modal" style="font-size:0.85rem; font-weight:600;">Batal</button>
                                                        <button type="submit" class="btn btn-success px-4 rounded-3 fw-semibold border-0">
                                                            <i class="bi bi-check-circle-fill me-1"></i> Simpan Harga Jual
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 bg-white">
                                    <div class="py-4">
                                        <i class="bi bi-folder-x text-muted opacity-40 display-4 d-block mb-3"></i>
                                        <span class="fw-semibold text-dark d-block">Belum Ada Data Permintaan Cold Kitchen</span>
                                        <span class="small text-muted">Seluruh pesanan baru akan muncul di sini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $pesanan->links() }}
            </div>
        </div>

        {{-- MODAL BAYAR MASSAL / MULTI-NOTA --}}
        <div class="modal fade text-start" id="modalBulkBayarPesanan" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form action="{{ route('pesanan.pembayaran-massal') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-header text-white border-0 p-4" style="background-color: #715745;">
                            <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                                <i class="bi bi-wallet2"></i> Pelunasan Massal Multi-Nota (Cold Kitchen)
                            </h5>
                            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <div class="alert alert-info py-2 px-3 small mb-3">
                                <i class="bi bi-info-circle me-1"></i> Anda memilih <strong id="bulkSelectedCount">0</strong> nota untuk dilunasi sekaligus pada akhir termin / periode.
                            </div>

                            <div id="bulkPesananHiddenInputs"></div>

                            <div class="table-responsive mb-3" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Kode Permintaan</th>
                                            <th>Outlet / Pemesan</th>
                                            <th class="text-end">Sisa Tagihan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkPesananTableBody" class="small">
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-warning fw-bold">
                                            <td colspan="2" class="text-end">Total Pelunasan:</td>
                                            <td class="text-end text-success" id="bulkTotalBayarDisplay">Rp 0</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold small text-secondary">Tanggal Pembayaran</label>
                                    <input type="date" name="tanggal_bayar" class="form-control" value="{{ date('Y-m-d') }}" required>
                                    <div class="d-flex gap-1 flex-wrap mt-1">
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'ini')">
                                            <i class="bi bi-calendar-check me-1"></i> Akhir Bulan Ini
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'depan')">
                                            <i class="bi bi-calendar-plus me-1"></i> Akhir Bulan Depan
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold small text-secondary">Metode Pembayaran</label>
                                    <select name="metode_pembayaran" class="form-select text-secondary" required>
                                        <option value="Transfer">Transfer Bank</option>
                                        <option value="Cash">Cash / Tunai</option>
                                        <option value="QRIS">QRIS</option>
                                        <option value="Termin">Termin / Piutang</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-secondary">Catatan Pembayaran</label>
                                <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Pelunasan termin pesanan Cold Kitchen outlet akhir bulan..."></textarea>
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-semibold small text-secondary">Upload Bukti Pembayaran <span class="text-muted">(bisa >1 gambar)</span></label>
                                <input type="file" name="bukti_file[]" class="form-control" accept="image/*" multiple>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0 bg-white">
                            <button type="button" class="btn btn-light px-4 rounded-3 text-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success px-4 rounded-3 fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Proses Pelunasan Massal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });

        // Pindahkan dropdown-menu aksi ke <body> saat dibuka, supaya tidak
        // ke-crop oleh "overflow-hidden" pada card pembungkus tabel.
        // Dikembalikan ke posisi semula saat ditutup agar struktur DOM tetap rapi.
        document.addEventListener('show.bs.dropdown', function (e) {
            var button = e.target;
            var menu = button.nextElementSibling;
            if (!menu || !menu.classList.contains('dropdown-menu-actions')) return;
            menu._originalNextSibling = menu.nextSibling;
            menu._originalParent = menu.parentNode;
            button._dropdownMenuRef = menu;
            document.body.appendChild(menu);
        });

        function setTanggalAkhirBulan(btn, mode) {
            var container = btn.closest('.modal-body') || btn.closest('form');
            if (!container) return;
            var input = container.querySelector('input[name="tanggal_bayar"]');
            if (!input) return;

            var now = new Date();
            var year = now.getFullYear();
            var month = now.getMonth() + (mode === 'depan' ? 1 : 0);
            var lastDay = new Date(year, month + 1, 0);

            var yyyy = lastDay.getFullYear();
            var mm = String(lastDay.getMonth() + 1).padStart(2, '0');
            var dd = String(lastDay.getDate()).padStart(2, '0');
            input.value = yyyy + '-' + mm + '-' + dd;
        }

        function handleMetodePembayaranChange(selectEl) {
            var container = selectEl.closest('.modal-body') || selectEl.closest('form');
            var inputBayar = container ? container.querySelector('input[name="jumlah_bayar"]') : null;

            if (selectEl.value === 'Termin' || selectEl.value === 'COD') {
                if (inputBayar) {
                    inputBayar.min = "0";
                    inputBayar.required = false;
                    if (!inputBayar.value || inputBayar.value === "") {
                        inputBayar.value = "0";
                    }
                    inputBayar.placeholder = "0 (Termin / Piutang)";
                }

                if (selectEl.value === 'Termin') {
                    var btnQuick = container ? container.querySelector('.btn-quick-akhir-bulan') : null;
                    if (btnQuick) {
                        btnQuick.click();
                    } else {
                        setTanggalAkhirBulan(selectEl, 'ini');
                    }
                }
            } else {
                if (inputBayar) {
                    inputBayar.min = "1";
                    inputBayar.required = true;
                    if (inputBayar.value === "0") {
                        inputBayar.value = "";
                    }
                    inputBayar.placeholder = "Masukkan nominal pembayaran";
                }
            }
        }

        document.addEventListener('hidden.bs.dropdown', function (e) {
            var button = e.target;
            var menu = button._dropdownMenuRef;
            if (!menu || !menu._originalParent) return;
            menu._originalParent.insertBefore(menu, menu._originalNextSibling);
        });

        // Recalculate subtotal in Set Harga Modal
        function recalcModalSubtotal(input) {
            var row = input.closest('tr');
            var qty = parseFloat(input.getAttribute('data-qty')) || 0;
            var harga = parseFloat(input.value) || 0;
            var subtotal = qty * harga;
            
            var subtotalCell = row.querySelector('.subtotal-cell');
            if (subtotalCell) {
                subtotalCell.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
            }

            var modal = input.closest('.modal');
            if (modal) {
                var total = 0;
                var inputs = modal.querySelectorAll('.input-modal-harga');
                inputs.forEach(function(inp) {
                    var q = parseFloat(inp.getAttribute('data-qty')) || 0;
                    var h = parseFloat(inp.value) || 0;
                    total += (q * h);
                });
                var preview = modal.querySelector('.modal-total-preview');
                if (preview) {
                    preview.textContent = 'Rp ' + total.toLocaleString('id-ID');
                }
            }
        }

        // Bulk Selection & Payment Logic
        document.addEventListener('DOMContentLoaded', function() {
            var checkAll = document.getElementById('checkAllPesanan');
            var checkboxes = document.querySelectorAll('.check-pesanan');
            var btnBulk = document.getElementById('btnBulkBayarPesanan');
            var countBadge = document.getElementById('countSelectedPesanan');

            function updateBulkState() {
                var selected = document.querySelectorAll('.check-pesanan:checked');
                var count = selected.length;
                if (countBadge) countBadge.textContent = count;

                if (count > 0) {
                    btnBulk.classList.remove('d-none');
                    btnBulk.classList.add('d-inline-flex');
                } else {
                    btnBulk.classList.add('d-none');
                    btnBulk.classList.remove('d-inline-flex');
                }

                if (checkAll) {
                    var enabledCount = document.querySelectorAll('.check-pesanan:not([disabled])').length;
                    checkAll.checked = (enabledCount > 0 && count === enabledCount);
                }
            }

            if (checkAll) {
                checkAll.addEventListener('change', function() {
                    var isChecked = this.checked;
                    checkboxes.forEach(function(cb) {
                        if (!cb.disabled) {
                            cb.checked = isChecked;
                        }
                    });
                    updateBulkState();
                });
            }

            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', updateBulkState);
            });

            // Populate bulk modal on show
            var modalBulk = document.getElementById('modalBulkBayarPesanan');
            if (modalBulk) {
                modalBulk.addEventListener('show.bs.modal', function() {
                    var selected = document.querySelectorAll('.check-pesanan:checked');
                    var containerInputs = document.getElementById('bulkPesananHiddenInputs');
                    var tbody = document.getElementById('bulkPesananTableBody');
                    var countEl = document.getElementById('bulkSelectedCount');
                    var totalEl = document.getElementById('bulkTotalBayarDisplay');

                    containerInputs.innerHTML = '';
                    tbody.innerHTML = '';
                    var totalBayar = 0;

                    selected.forEach(function(cb) {
                        var id = cb.value;
                        var kode = cb.getAttribute('data-kode');
                        var customer = cb.getAttribute('data-customer');
                        var sisa = parseFloat(cb.getAttribute('data-sisa')) || 0;
                        totalBayar += sisa;

                        // Hidden input
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'pesanan_ids[]';
                        hidden.value = id;
                        containerInputs.appendChild(hidden);

                        // Table row
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td><strong>#' + kode + '</strong></td>' +
                                       '<td>' + customer + '</td>' +
                                       '<td class="text-end text-success fw-semibold">Rp ' + sisa.toLocaleString('id-ID') + '</td>';
                        tbody.appendChild(tr);
                    });

                    countEl.textContent = selected.length;
                    totalEl.textContent = 'Rp ' + totalBayar.toLocaleString('id-ID');
                });
            }
        });
    </script>
</x-app-layout>