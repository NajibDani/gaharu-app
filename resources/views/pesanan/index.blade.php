<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        .ts-dropdown { z-index: 99999 !important; }
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

        /* ===== ACTION BUTTONS ===== */
        .action-btn-group { display: inline-flex; justify-content: center; align-items: center; gap: 5px; flex-wrap: nowrap; }
        .btn-action-base {
            border-radius: 7px; width: 32px; height: 32px; font-size: 0.82rem;
            border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: all 0.15s ease-in-out; background-color: transparent; flex-shrink: 0;
        }

        .btn-action-eye { background-color: #f0f9ff; color: #0369a1 !important; border-color: #e0f2fe; }
        .btn-action-eye:hover { background-color: #0284c7; color: white !important; border-color: #0284c7; }

        .btn-action-edit { background-color: #fffbec; color: #b45309 !important; border-color: #fef3c7; cursor: pointer; }
        .btn-action-edit:hover { background-color: #b45309; color: white !important; border-color: #b45309; }

        .btn-action-delete { background-color: #fef2f2; color: #b91c1c !important; border-color: #fee2e2; cursor: pointer; }
        .btn-action-delete:hover { background-color: #b91c1c; color: white !important; border-color: #b91c1c; }

        .btn-action-pdf { background-color: #fee2e2; color: #b91c1c !important; border-color: #fee2e2; }
        .btn-action-pdf:hover { background-color: #dc2626; color: white !important; border-color: #dc2626; }

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
    </style>

    <div class="container-fluid px-2 px-md-4 py-3">

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
        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 text-sm mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Cold Kitchen Orders</h4>
                <p class="text-muted small mb-0">Kelola permintaan barang &amp; bahan setengah jadi dari Outlet &amp; Konsumen Cold Kitchen</p>
            </div>
            <div class="w-100 w-sm-auto">
                <button type="button" class="btn btn-custom-orange shadow-sm d-inline-flex align-items-center justify-content-center w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#modalCreateColdOrder">
                    <i class="bi bi-plus-circle me-2"></i> Buat Pesanan Cold Kitchen
                </button>
            </div>
        </div>

        {{-- SUMMARY CARDS --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="summary-card">
                    <span class="text-muted small d-block mb-1 fw-semibold">Total Order Cold Kitchen</span>
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
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
            <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom border-light d-flex justify-content-between align-items-center flex-column flex-md-row gap-2">
                <div class="d-flex align-items-center justify-content-between w-100 w-md-auto gap-2">
                    <h6 class="fw-bold mb-0 text-dark text-nowrap">Daftar Permintaan Cold Kitchen</h6>
                    <button type="button" class="btn btn-outline-success btn-sm d-none align-items-center gap-1 shadow-sm" id="btnBulkBayarPesanan" data-bs-toggle="modal" data-bs-target="#modalBulkBayarPesanan" style="border-radius: 8px; font-weight: 600; padding: 6px 14px; font-size: 0.8rem;">
                        <i class="bi bi-wallet2"></i> Bayar Terpilih (<span id="countSelectedPesanan">0</span>)
                    </button>
                </div>

                <form method="GET" action="{{ route('pesanan.index') }}" class="d-flex align-items-center gap-2 w-100 w-md-auto flex-wrap">
                    {{-- Dropdown Outlet / Konsumen --}}
                    <div style="min-width: 160px;">
                        <select name="customer_id" class="form-select form-select-sm" style="border-radius: 8px; border: 1px solid #DCD3CB; height: 36px;" onchange="this.form.submit()">
                            <option value="">-- Semua Konsumen / Outlet --</option>
                            @if(isset($customers))
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->nama }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    {{-- Search Input Group --}}
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control border-end-0" placeholder="Cari Kode / Pemesan..." value="{{ request('search') }}" style="height: 36px;">
                        <button class="btn btn-outline-secondary border-start-0" type="submit" style="height: 36px;">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    @if(request('search') || request('customer_id'))
                        <a href="{{ route('pesanan.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; height: 36px; width: 36px;" title="Reset Filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="text-center" style="width: 40px;">
                                <input type="checkbox" id="checkAllPesanan" class="form-check-input" title="Pilih Semua">
                            </th>
                            <th class="text-center" style="width: 45px;">NO</th>
                            <th class="text-nowrap" style="min-width: 145px;">KODE ORDER</th>
                            <th class="text-nowrap">OUTLET / KONSUMEN PEMESAN</th>
                            <th class="text-nowrap">TANGGAL PERMINTAAN</th>
                            <th class="text-nowrap">TANGGAL ORDER</th>
                            <th class="text-nowrap">ESTIMASI KIRIM</th>
                            <th class="text-end text-nowrap">TOTAL HPP</th>
                            <th class="text-center text-nowrap">STATUS PRODUKSI</th>
                            <th class="text-center text-nowrap">STATUS BAYAR</th>
                            <th class="text-center text-nowrap" style="width: 125px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="table-custom-body">
                        @forelse($pesanan as $index => $item)
                            @php
                                $totalNilaiItem = $item->total_pesanan > 0 ? (float)$item->total_pesanan : (float)$item->details->sum('subtotal');
                                $sudahBayarItem = isset($item->pembayaran) ? $item->pembayaran->sum('jumlah_bayar') : 0;
                                $sisaTagihanItem = max(0, $totalNilaiItem - $sudahBayarItem);
                                $isLunasOrBatal = ($item->status_pembayaran == 'Lunas' || in_array(strtolower($item->status_pesanan ?? ''), ['batal', 'dibatalkan']));

                                $orderItemsList = $item->details->map(function($d) {
                                    return [
                                        'barang_id'   => $d->produk_id,
                                        'kode_barang' => $d->produk->kode_barang ?? '-',
                                        'nama_barang' => $d->produk->nama ?? 'N/A',
                                        'qty'         => (float)$d->qty,
                                        'satuan'      => $d->produk->satuan ?? '',
                                        'harga'       => (float)($d->harga ?? 0),
                                        'subtotal'    => (float)($d->subtotal ?? 0),
                                    ];
                                })->values();
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input check-pesanan" 
                                           value="{{ $item->id }}" 
                                           data-kode="{{ $item->kode_pesanan }}" 
                                           data-customer="{{ $item->customer->nama ?? '-' }}" 
                                           data-tanggal="{{ date('d M Y', strtotime($item->tanggal ?? $item->created_at)) }}"
                                           data-status-produksi="{{ ucfirst($item->status_pesanan) }}"
                                           data-total="{{ $totalNilaiItem }}" 
                                           data-sisa="{{ $sisaTagihanItem }}"
                                           data-items="{{ json_encode($orderItemsList) }}"
                                           {{ ($isLunasOrBatal || $sisaTagihanItem <= 0) ? 'disabled' : '' }}>
                                </td>
                                <td class="text-center fw-semibold text-muted">{{ $pesanan->firstItem() + $index }}</td>
                                <td class="fw-bold text-dark text-nowrap" style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.82rem; letter-spacing: -0.2px;">
                                    #{{ $item->kode_pesanan }}
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge bg-light text-dark border">{{ $item->customer->nama ?? '-' }}</span>
                                    @if(isset($item->customer->no_hp) && $item->customer->no_hp !== '-')
                                        <div class="text-muted small" style="font-size: 0.7rem;">
                                            <i class="bi bi-telephone"></i> {{ $item->customer->no_hp }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ date('d M Y', strtotime($item->created_at ?? $item->tanggal)) }}
                                </td>
                                <td class="text-nowrap">{{ date('d M Y', strtotime($item->tanggal)) }}</td>
                                <td class="text-nowrap">
                                    <span class="fw-medium {{ $item->estimasi_kirim && strtotime($item->estimasi_kirim) < strtotime(date('Y-m-d')) ? 'text-danger' : 'text-dark' }}">
                                        {{ $item->estimasi_kirim ? date('d M Y', strtotime($item->estimasi_kirim)) : '-' }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark text-nowrap">
                                    Rp {{ number_format($totalNilaiItem, 0, ',', '.') }}
                                </td>
                                <td class="text-center text-nowrap">
                                    @php
                                        $statusClass = 'badge-status-pending';
                                        if (in_array(strtolower($item->status_pesanan), ['proses', 'diproses', 'ready', 'siap kirim'])) {
                                            $statusClass = 'badge-status-proses';
                                        } elseif (strtolower($item->status_pesanan) == 'selesai') {
                                            $statusClass = 'badge-status-selesai';
                                        } elseif (in_array(strtolower($item->status_pesanan), ['batal', 'dibatalkan'])) {
                                            $statusClass = 'badge-status-batal';
                                        }
                                    @endphp
                                    <span class="badge-subtle {{ $statusClass }}">
                                        {{ ucfirst($item->status_pesanan) }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    @if($item->status_pembayaran == 'Lunas')
                                        <span class="badge-subtle badge-status-selesai">Lunas</span>
                                    @elseif($item->status_pembayaran == 'DP')
                                        <span class="badge-subtle badge-status-pending">DP</span>
                                    @else
                                        <span class="badge-subtle badge-status-batal">Belum Bayar</span>
                                    @endif
                                </td>
                                <td class="text-center text-nowrap">
                                    <div class="action-btn-group">
                                        {{-- 1. Tombol Detail --}}
                                        <button type="button" class="btn-action-base btn-action-eye" data-bs-toggle="modal" data-bs-target="#modalDetailCold{{ $item->id }}" title="Lihat Detail Pesanan">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        {{-- 2. Tombol Cetak PDF --}}
                                        <a href="{{ route('pesanan.cetak-pdf', $item->id) }}" target="_blank" class="btn-action-base btn-action-pdf" title="Cetak PDF">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>

                                        {{-- 3. Dropdown Menu Opsi --}}
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
                                                            <i class="bi bi-pencil-square text-warning"></i> Edit Pesanan
                                                        </a>
                                                    </li>
                                                @else
                                                    <li>
                                                        <span class="dropdown-item disabled text-muted py-2" title="Terkunci: sudah masuk Work Order">
                                                            <i class="bi bi-lock text-secondary"></i>
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
                                                            <i class="bi bi-cash-stack"></i> Input Pembayaran
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
                                                                    <i class="bi bi-x-circle"></i> Batalkan Transaksi
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                @endif

                                                @php
                                                    $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
                                                    $isSent = $item->is_sent ?? false;
                                                @endphp

                                                @if(!isset($item->wo_status) || $item->wo_status === null)
                                                    <li>
                                                        <form action="{{ route('pesanan.destroy', $item->id) }}" method="POST" class="m-0 p-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger w-100">
                                                                <i class="bi bi-trash"></i> Hapus Pesanan
                                                            </button>
                                                        </form>
                                                    </li>
                                                @elseif($isSuperAdmin && !$isSent)
                                                    <li>
                                                        <form action="{{ route('pesanan.destroy', $item->id) }}" method="POST" class="m-0 p-0" onsubmit="return confirm('PERHATIAN (Superadmin):\nApakah Anda yakin ingin menghapus Permintaan #{{ $item->kode_pesanan }}?\n\nSemua relasi Work Order dan data produksi terkait yang belum terkirim akan dibatalkan/dihapus secara bersih.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger w-100 fw-semibold">
                                                                <i class="bi bi-trash3-fill text-danger"></i> Hapus PO (Superadmin)
                                                            </button>
                                                        </form>
                                                    </li>
                                                @else
                                                    <li>
                                                        <span class="dropdown-item disabled text-muted py-2" title="{{ $isSent ? 'Terkunci: pesanan sudah dikirim' : 'Terkunci: sudah masuk Work Order' }}">
                                                            <i class="bi bi-lock text-secondary"></i>
                                                            <span>
                                                                Hapus Terkunci
                                                                <small class="d-block text-muted" style="font-size: 0.68rem;">{{ $isSent ? 'Sudah Terkirim' : 'WO aktif' }}</small>
                                                            </span>
                                                        </span>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>

                                    {{-- MODAL DETAIL LENGKAP NOTA COLD KITCHEN --}}
                                    <div class="modal fade text-start" id="modalDetailCold{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                <div class="modal-header bg-dark text-white">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-receipt me-2"></i> Detail Pesanan Cold Kitchen: {{ $item->kode_pesanan }}
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-3 p-md-4 bg-white">
                                                    <div id="capture-cold-order-{{ $item->id }}" class="p-3 p-md-4 bg-white rounded-3 border">
                                                        {{-- HEADER BLOCK - COLD KITCHEN --}}
                                                        <div class="p-3 rounded-3 mb-3 text-white" style="background-color: #715745;">
                                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                <div>
                                                                    <div class="fw-bold fs-5 text-uppercase" style="letter-spacing: 0.5px;">CV GAHARU AGUNG SEJAHTERA</div>
                                                                    <div class="small opacity-75">Cold Kitchen Production &amp; Internal Order Management</div>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="fw-bold fs-6 text-uppercase">PURCHASE ORDER COLD KITCHEN</div>
                                                                    <div class="font-monospace fw-bold fs-5">#{{ $item->kode_pesanan }}</div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- STANDAR INFO GRID METADATA --}}
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered align-middle mb-0" style="font-size: 12px; background-color: #f8fafc;">
                                                                <tbody>
                                                                    <tr>
                                                                        <td class="fw-bold text-secondary text-uppercase" style="width: 18%; font-size: 11px;">Judul Dokumen</td>
                                                                        <td class="fw-bold text-dark" style="width: 32%;">PURCHASE ORDER COLD KITCHEN</td>
                                                                        <td class="fw-bold text-secondary text-uppercase" style="width: 18%; font-size: 11px;">Tanggal Order</td>
                                                                        <td class="fw-bold text-dark" style="width: 32%;">{{ \Carbon\Carbon::parse($item->tanggal)->format('d F Y') }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Outlet Pemesan</td>
                                                                        <td><strong class="text-primary fs-6">{{ $item->customer->nama ?? '-' }}</strong></td>
                                                                        <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Gudang Sumber</td>
                                                                        <td><strong>Gudang Cold Kitchen</strong> <span class="text-muted small">(Penyedia)</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Status Dokumen</td>
                                                                        <td>
                                                                            <span class="badge-subtle {{ $statusClass }} text-uppercase">
                                                                                {{ $item->status_pesanan }}
                                                                            </span>
                                                                        </td>
                                                                        <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Estimasi Kirim</td>
                                                                        <td><strong>{{ $item->estimasi_kirim ? \Carbon\Carbon::parse($item->estimasi_kirim)->format('d F Y') : '-' }}</strong></td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        <h6 class="fw-bold text-dark mb-2 small text-uppercase">Daftar Bahan Setengah Jadi / Barang</h6>
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered align-middle text-center mb-0" style="font-size: 12px;">
                                                                <thead class="table-dark">
                                                                    <tr>
                                                                        <th style="width: 40px;">No</th>
                                                                        <th style="width: 110px;">Kode Item</th>
                                                                        <th class="text-start">Nama Bahan / Barang</th>
                                                                        <th style="width: 200px;">Konversi Resep / Batch</th>
                                                                        <th style="width: 140px;" class="text-end">Total Target Qty</th>
                                                                        <th style="width: 130px;" class="text-end">Subtotal HPP</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($item->details as $idx => $d)
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
                                                                            <td class="text-end fw-bold text-dark">
                                                                                Rp {{ number_format($d->subtotal ?? 0, 0, ',', '.') }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                                <tfoot class="table-light fw-bold">
                                                                    <tr>
                                                                        <td colspan="5" class="text-end">Total HPP Tagihan:</td>
                                                                        <td class="text-end text-success">Rp {{ number_format($totalNilaiItem, 0, ',', '.') }}</td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>

                                                        <div class="row text-center mt-4 pt-3 border-top" style="font-size: 11px;">
                                                            <div class="col-4">
                                                                <div class="text-muted">Pemesan:</div>
                                                                <div style="height: 35px;"></div>
                                                                <div class="fw-bold text-dark">({{ $item->customer->nama ?? 'Konsumen / Outlet' }})</div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="text-muted">Cold Kitchen:</div>
                                                                <div style="height: 35px;"></div>
                                                                <div class="fw-bold text-dark">( Dapur Cold Kitchen CV Gaharu )</div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="text-muted">Gudang &amp; Logistik:</div>
                                                                <div style="height: 35px;"></div>
                                                                <div class="fw-bold text-dark">( Tim Warehouse CK )</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2 justify-content-between flex-wrap gap-2">
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <button type="button" class="btn btn-success btn-sm px-3 fw-bold" onclick="downloadColdOrderJpg({{ $item->id }}, '{{ $item->kode_pesanan }}')">
                                                            <i class="bi bi-file-image me-1"></i> Download JPG
                                                        </button>
                                                        <a href="{{ route('pesanan.cetak-pdf', $item->id) }}" target="_blank" class="btn btn-outline-danger btn-sm px-3 fw-bold">
                                                            <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
                                                        </a>
                                                        @if(!isset($item->wo_status) || $item->wo_status === null)
                                                            <a href="{{ route('pesanan.edit', $item->id) }}" class="btn btn-warning btn-sm px-3 fw-bold text-dark">
                                                                <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                                                            </a>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        @if(!isset($item->wo_status) || $item->wo_status === null)
                                                            <form action="{{ route('pesanan.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pesanan #{{ $item->kode_pesanan }}?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-outline-danger btn-sm px-3 fw-bold">
                                                                    <i class="bi bi-trash me-1"></i> Hapus Pesanan
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Tutup</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL SET HARGA --}}
                                    <div class="modal fade text-start" id="modalSetHarga{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <form action="{{ route('pesanan.update-harga', $item->id) }}" method="POST">
                                                @csrf
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header text-white border-0" style="background-color: #715745;">
                                                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                                                            <i class="bi bi-tag-fill"></i> Input / Ubah Harga Jual Permintaan #{{ $item->kode_pesanan }}
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 bg-white">
                                                        <div class="alert alert-info py-2 px-3 small mb-3">
                                                            <i class="bi bi-info-circle me-1"></i> Masukkan harga jual per pcs untuk pesanan Cold Kitchen ini. Total tagihan akan otomatis diperbarui sesuai harga yang diinput.
                                                        </div>
                                                        <div class="table-responsive rounded-3 border mb-3">
                                                            <table class="table table-sm align-middle mb-0" style="font-size: 13px;">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Nama Produk</th>
                                                                        <th class="text-center" width="100">Qty</th>
                                                                        <th class="text-center" width="160">Harga Jual (Rp)</th>
                                                                        <th class="text-end" width="150">Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($item->details as $det)
                                                                        <tr>
                                                                            <td class="fw-semibold">
                                                                                <input type="hidden" name="detail_id[]" value="{{ $det->id }}">
                                                                                {{ $det->produk->nama ?? 'Produk' }}
                                                                                <small class="text-muted d-block font-monospace">{{ $det->produk->kode_barang ?? '-' }}</small>
                                                                            </td>
                                                                            <td class="text-center fw-bold">{{ number_format($det->qty, 0, ',', '.') }} {{ $det->produk->satuan ?? 'pcs' }}</td>
                                                                            <td>
                                                                                <div class="input-group input-group-sm">
                                                                                    <span class="input-group-text">Rp</span>
                                                                                    <input type="number" step="any" min="0" name="harga[]" class="form-control text-end fw-bold input-modal-harga" 
                                                                                           value="{{ $det->harga > 0 ? (float)$det->harga : 0 }}" 
                                                                                           data-qty="{{ (float)$det->qty }}" 
                                                                                           oninput="recalcModalSubtotal(this)">
                                                                                </div>
                                                                            </td>
                                                                            <td class="text-end fw-bold subtotal-cell">
                                                                                Rp {{ number_format($det->subtotal > 0 ? $det->subtotal : 0, 0, ',', '.') }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                                <tfoot class="table-light fw-bold">
                                                                    <tr>
                                                                        <td colspan="3" class="text-end">Total Tagihan Baru:</td>
                                                                        <td class="text-end text-success modal-total-preview">
                                                                            Rp {{ number_format($totalNilaiItem, 0, ',', '.') }}
                                                                        </td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light py-2 justify-content-between">
                                                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">
                                                            <i class="bi bi-check-circle me-1"></i> Simpan Harga Jual
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- MODAL BAYAR SATUAN --}}
                                    <div class="modal fade text-start" id="modalBayar{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form action="{{ route('pesanan.simpan-pembayaran', $item->id) }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header text-white border-0" style="background-color: #715745;">
                                                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                                                            <i class="bi bi-wallet2"></i> Catat Pembayaran #{{ $item->kode_pesanan }}
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 bg-white">
                                                        <div class="p-3 bg-light rounded-3 mb-3 d-flex justify-content-between align-items-center">
                                                            <span class="text-secondary small">Sisa Tagihan:</span>
                                                            <h5 class="fw-bold text-danger mb-0">Rp {{ number_format($sisaTagihanItem, 0, ',', '.') }}</h5>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Tanggal Pembayaran</label>
                                                            <input type="date" name="tanggal_bayar" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                            <div class="d-flex gap-1 flex-wrap mt-1">
                                                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 btn-quick-akhir-bulan" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'ini')">
                                                                    <i class="bi bi-calendar-check me-1"></i> Akhir Bulan Ini
                                                                </button>
                                                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'depan')">
                                                                    <i class="bi bi-calendar-plus me-1"></i> Akhir Bulan Depan
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Metode Pembayaran</label>
                                                            <select name="metode_pembayaran" class="form-select text-secondary" required onchange="handleMetodePembayaranChange(this)">
                                                                <option value="Transfer">Transfer Bank</option>
                                                                <option value="Cash">Cash / Tunai</option>
                                                                <option value="QRIS">QRIS</option>
                                                                <option value="Termin">Termin / Piutang</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Jumlah Bayar (Rp)</label>
                                                            <input type="number" step="any" min="1" max="{{ $sisaTagihanItem }}" name="jumlah_bayar" class="form-control fw-bold text-success" value="{{ $sisaTagihanItem }}" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Catatan</label>
                                                            <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan pembayaran..."></textarea>
                                                        </div>

                                                        <div class="mb-0">
                                                            <label class="form-label fw-semibold small text-secondary">Upload Bukti Pembayaran <span class="text-muted">(bisa &gt;1 gambar)</span></label>
                                                            <input type="file" name="bukti_file[]" class="form-control" accept="image/*" multiple>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light py-2 justify-content-between">
                                                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">
                                                            <i class="bi bi-check-circle me-1"></i> Simpan Pembayaran
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
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                    <span class="fw-semibold text-dark d-block">Belum Ada Data Permintaan Cold Kitchen</span>
                                    <small>Silakan buat permintaan baru menggunakan tombol "+ Buat Pesanan Cold Kitchen" di atas.</small>
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

        {{-- MODAL BAYAR MASSAL DUAL TAB (COLD KITCHEN) --}}
        <div class="modal fade text-start" id="modalBulkBayarPesanan" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <form action="{{ route('pesanan.pembayaran-massal') }}" method="POST" enctype="multipart/form-data" class="w-100">
                    @csrf
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-header text-white border-0 p-3 px-4" style="background-color: #715745;">
                            <div>
                                <h5 class="modal-title fw-bold d-flex align-items-center gap-2 mb-0">
                                    <i class="bi bi-wallet2"></i> Pelunasan Massal Multi-Nota (Cold Kitchen)
                                </h5>
                                <small class="text-white-50">Pelunasan multi-nota permintaan Cold Kitchen beserta rincian akumulasi barang yang diminta</small>
                            </div>
                            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-3 p-md-4 bg-white">
                            {{-- BANNER RINGKASAN --}}
                            <div class="p-3 rounded-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 20px;">
                                        <i class="bi bi-receipt-cutoff"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <span id="bulkSelectedCountCold">0</span> Nota Terpilih
                                        </div>
                                        <div class="text-muted small">
                                            Total <span id="bulkTotalItemsSummaryCold" class="fw-semibold text-dark">0 Jenis Barang</span> (<span id="bulkTotalQtySummaryCold" class="fw-semibold text-dark">0</span> total kuantitas)
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="text-muted small d-block">Total Pelunasan HPP:</span>
                                    <h4 class="fw-bold text-success mb-0" id="bulkTotalBayarDisplayCold">Rp 0</h4>
                                </div>
                            </div>

                            <div id="bulkColdHiddenInputs"></div>

                            {{-- NAV TABS: RINCIAN NOTA vs REKAP BARANG --}}
                            <ul class="nav nav-tabs nav-fill mb-3" id="bulkColdTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active fw-semibold py-2" id="tab-cold-orders-link" data-bs-toggle="tab" data-bs-target="#tab-cold-orders-content" type="button" role="tab" aria-selected="true">
                                        <i class="bi bi-receipt me-1"></i> 1. Rincian Nota &amp; Tagihan (<span id="bulkTabColdOrderCount">0</span>)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link fw-semibold py-2" id="tab-cold-items-link" data-bs-toggle="tab" data-bs-target="#tab-cold-items-content" type="button" role="tab" aria-selected="false">
                                        <i class="bi bi-boxes me-1"></i> 2. Total Barang yang Diminta (<span id="bulkTabColdItemCount">0</span> Jenis)
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mb-4" id="bulkColdTabContent">
                                {{-- TAB 1: TABEL DAFTAR PERMINTAAN TERPILIH --}}
                                <div class="tab-pane fade show active" id="tab-cold-orders-content" role="tabpanel">
                                    <div class="table-responsive rounded-3 border" style="max-height: 260px; overflow-y: auto;">
                                        <table class="table table-sm table-hover align-middle mb-0 text-center" style="font-size: 12px;">
                                            <thead class="table-light text-secondary sticky-top">
                                                <tr>
                                                    <th width="40">No</th>
                                                    <th class="text-start">Kode Permintaan</th>
                                                    <th>Tanggal Order</th>
                                                    <th>Outlet / Pemesan</th>
                                                    <th>Status Produksi</th>
                                                    <th class="text-end" width="130">Total Tagihan</th>
                                                    <th class="text-end" width="140">Sisa Pelunasan</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bulkColdOrdersTableBody">
                                            </tbody>
                                            <tfoot class="table-light fw-bold sticky-bottom">
                                                <tr>
                                                    <td colspan="5" class="text-end text-uppercase">Total Keseluruhan Pelunasan:</td>
                                                    <td class="text-end" id="bulkColdTfootTotalTagihan">Rp 0</td>
                                                    <td class="text-end text-success fs-6" id="bulkColdTfootTotalSisa">Rp 0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- TAB 2: TABEL REKAPITULASI TOTAL BARANG YANG DIMINTA --}}
                                <div class="tab-pane fade" id="tab-cold-items-content" role="tabpanel">
                                    <div class="table-responsive rounded-3 border" style="max-height: 260px; overflow-y: auto;">
                                        <table class="table table-sm table-hover align-middle mb-0 text-center" style="font-size: 12px;">
                                            <thead class="table-light text-secondary sticky-top">
                                                <tr>
                                                    <th width="40">No</th>
                                                    <th width="110">Kode Barang</th>
                                                    <th class="text-start">Nama Produk / Barang</th>
                                                    <th width="80">Satuan</th>
                                                    <th width="130" class="text-end">Total Qty Diminta</th>
                                                    <th class="text-start" width="220">Rincian per Nota Permintaan</th>
                                                    <th width="120" class="text-end">Subtotal Tagihan</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bulkColdItemsTableBody">
                                            </tbody>
                                            <tfoot class="table-light fw-bold sticky-bottom">
                                                <tr>
                                                    <td colspan="4" class="text-end text-uppercase">Total Akumulasi Barang:</td>
                                                    <td class="text-end text-primary" id="bulkColdTfootTotalQty">0</td>
                                                    <td class="text-start text-muted small"><span id="bulkColdTfootItemTypes">0</span> jenis barang</td>
                                                    <td class="text-end text-success" id="bulkColdTfootTotalItemSubtotal">Rp 0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- FORM INPUT PEMBAYARAN --}}
                            <div class="card border rounded-3 p-3 bg-light">
                                <h6 class="fw-bold text-dark small text-uppercase mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-credit-card"></i> Form Pelunasan Pembayaran
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-secondary small">Tanggal Pembayaran <span class="text-danger">*</span></label>
                                        <input type="date" name="tanggal_bayar" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                                        <div class="d-flex gap-1 flex-wrap mt-1">
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'ini')">
                                                <i class="bi bi-calendar-check me-1"></i> Akhir Bulan Ini
                                            </button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem; border-radius: 6px;" onclick="setTanggalAkhirBulan(this, 'depan')">
                                                <i class="bi bi-calendar-plus me-1"></i> Akhir Bulan Depan
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-secondary small">Metode Pembayaran <span class="text-danger">*</span></label>
                                        <select name="metode_pembayaran" class="form-select rounded-3 text-secondary" required>
                                            <option value="Transfer">Transfer Bank</option>
                                            <option value="Cash">Cash / Tunai</option>
                                            <option value="QRIS">QRIS</option>
                                            <option value="Termin">Termin / Piutang</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-secondary small">Catatan Pelunasan</label>
                                        <textarea name="catatan" class="form-control rounded-3" rows="2" placeholder="Contoh: Pelunasan biaya HPP Cold Kitchen periode akhir bulan..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-secondary small">Upload Bukti Pembayaran <span class="text-muted fw-normal">(bisa &gt;1 gambar)</span></label>
                                        <input type="file" name="bukti_file[]" class="form-control rounded-3" accept="image/*" multiple>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-3 px-4 bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-secondary px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success px-4 rounded-3 fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="bi bi-check-circle-fill"></i> Proses Pelunasan (<span id="bulkColdSubmitTotalText">Rp 0</span>)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL BUAT PESANAN COLD KITCHEN BARU --}}
    <div class="modal fade text-start" id="modalCreateColdOrder" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-custom-orange text-white" style="background-color: #db7946;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Buat Pesanan Cold Kitchen Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form action="{{ route('pesanan.store') }}" method="POST" id="modal-form-cold-order">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-secondary">Outlet / Konsumen Pemesan <span class="text-danger">*</span></label>
                                <select name="customer_id" id="modal-select-customer-cold" class="form-select rounded-3" required>
                                    <option value="">-- Pilih Outlet / Konsumen Pemesan --</option>
                                    @if(isset($customers))
                                        @foreach($customers as $c)
                                            <option value="{{ $c->id }}">
                                                {{ $c->nama }} @if(isset($c->jenis_customer) && $c->jenis_customer) ({{ $c->jenis_customer }}) @endif
                                            </option>
                                        @endforeach
                                    @endif
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
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0 small text-uppercase">Daftar Barang / Item Pesanan Cold Kitchen</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="modal-btn-add-cold-item">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle text-center mb-0" id="modal-table-cold-items">
                                <thead class="bg-light font-weight-bold">
                                    <tr>
                                        <th class="text-start" style="width: 32%;">Nama Bahan / Barang</th>
                                        <th style="width: 15%;">Qty</th>
                                        <th style="width: 20%;">Satuan / Mode</th>
                                        <th style="width: 28%;">Total Qty (Konversi)</th>
                                        <th style="width: 5%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-cold-item-rows">
                                    <tr>
                                        <td class="text-start">
                                            <select name="produk_id[]" class="form-select form-select-sm modal-select-produk-cold" required>
                                                <option value="">-- Cari / Pilih BSJ / Barang Jadi --</option>
                                                @if(isset($produk))
                                                    @foreach($produk as $item)
                                                        @php
                                                            $outQty = floatval($item->resepBtklBop->output_qty ?? 0);
                                                            $outSatuan = $item->resepBtklBop->satuan_output ?? ($item->satuan ?? '');
                                                            $satuanKonversi = $item->satuan_pembelian ? strtoupper($item->satuan_pembelian) : '';
                                                            $konversiVal = floatval($item->konversi_pembelian ?? 1);
                                                        @endphp
                                                        <option value="{{ $item->id }}" 
                                                                data-satuan="{{ $item->satuan }}"
                                                                data-output-qty="{{ $outQty }}"
                                                                data-satuan-output="{{ $outSatuan }}"
                                                                data-satuan-konversi="{{ $satuanKonversi }}"
                                                                data-konversi="{{ $konversiVal }}">
                                                            {{ $item->kode_barang }} - {{ $item->nama }}
                                                            @if($satuanKonversi && $konversiVal > 1)
                                                                (1 {{ $satuanKonversi }} = {{ number_format($konversiVal, 0, ',', '.') }} {{ $item->satuan }})
                                                            @elseif($outQty > 0)
                                                                (1 Resep = {{ number_format($outQty, 0, ',', '.') }} {{ $outSatuan }})
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0.01" name="qty[]" class="form-control form-control-sm text-end modal-input-cold-qty fw-bold" placeholder="0" required>
                                        </td>
                                        <td>
                                            <select name="order_mode[]" class="form-select form-select-sm modal-select-cold-mode text-center fw-bold">
                                                <option value="resep">Resep</option>
                                                <option value="satuan">Satuan</option>
                                                <option value="konversi">Konversi</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="modal-cold-konversi-info small text-start p-1 px-2 bg-light rounded border">
                                                <span class="text-muted">-</span>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger modal-btn-remove-cold-row" disabled>
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
                            <i class="bi bi-check-circle-fill me-1"></i> Simpan Pesanan Cold Kitchen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Dropdown menu repositioning
        document.addEventListener('show.bs.dropdown', function (e) {
            var button = e.target;
            var menu = button.nextElementSibling;
            if (!menu || !menu.classList.contains('dropdown-menu-actions')) return;
            menu._originalNextSibling = menu.nextSibling;
            menu._originalParent = menu.parentNode;
            button._dropdownMenuRef = menu;
            document.body.appendChild(menu);
        });

        document.addEventListener('hidden.bs.dropdown', function (e) {
            var button = e.target;
            var menu = button._dropdownMenuRef;
            if (!menu || !menu._originalParent) return;
            menu._originalParent.insertBefore(menu, menu._originalNextSibling);
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

        function downloadColdOrderJpg(id, kode) {
            var el = document.getElementById('capture-cold-order-' + id);
            if (!el) return;

            html2canvas(el, {
                scale: 2,
                backgroundColor: '#ffffff'
            }).then(function (canvas) {
                var link = document.createElement('a');
                link.download = 'Permintaan-Cold-Kitchen-' + kode + '.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();
            }).catch(function (err) {
                alert('Gagal mendownload gambar: ' + err);
            });
        }

        // Bulk Selection & Dual-Tab Payment Logic
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

            // Populate dual-tab bulk modal on show
            var modalBulk = document.getElementById('modalBulkBayarPesanan');
            if (modalBulk) {
                modalBulk.addEventListener('show.bs.modal', function() {
                    var selected = document.querySelectorAll('.check-pesanan:checked');
                    var containerInputs = document.getElementById('bulkColdHiddenInputs');
                    var ordersTbody = document.getElementById('bulkColdOrdersTableBody');
                    var itemsTbody = document.getElementById('bulkColdItemsTableBody');

                    containerInputs.innerHTML = '';
                    ordersTbody.innerHTML = '';
                    itemsTbody.innerHTML = '';

                    var totalTagihanSemua = 0;
                    var totalSisaSemua = 0;
                    var aggregatedItems = {};
                    var totalQtyAllItems = 0;

                    selected.forEach(function(cb, index) {
                        var id = cb.value;
                        var kode = cb.getAttribute('data-kode');
                        var customer = cb.getAttribute('data-customer');
                        var tanggal = cb.getAttribute('data-tanggal') || '-';
                        var statusProd = cb.getAttribute('data-status-produksi') || '-';
                        var total = parseFloat(cb.getAttribute('data-total')) || 0;
                        var sisa = parseFloat(cb.getAttribute('data-sisa')) || 0;

                        totalTagihanSemua += total;
                        totalSisaSemua += sisa;

                        // Hidden input
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'pesanan_ids[]';
                        hidden.value = id;
                        containerInputs.appendChild(hidden);

                        // Tab 1 Row
                        var trOrder = document.createElement('tr');
                        trOrder.innerHTML = `
                            <td>${index + 1}</td>
                            <td class="text-start font-monospace fw-bold text-dark">#${kode}</td>
                            <td>${tanggal}</td>
                            <td><span class="badge bg-light text-dark border">${customer}</span></td>
                            <td><span class="badge bg-info-subtle text-dark border">${statusProd}</span></td>
                            <td class="text-end">Rp ${total.toLocaleString('id-ID')}</td>
                            <td class="text-end text-success fw-bold">Rp ${sisa.toLocaleString('id-ID')}</td>
                        `;
                        ordersTbody.appendChild(trOrder);

                        // Parse items for Tab 2
                        var itemsData = [];
                        try {
                            itemsData = JSON.parse(cb.getAttribute('data-items') || '[]');
                        } catch(e) {
                            itemsData = [];
                        }

                        itemsData.forEach(function(it) {
                            var bId = it.barang_id;
                            totalQtyAllItems += it.qty;

                            if (!aggregatedItems[bId]) {
                                aggregatedItems[bId] = {
                                    kode: it.kode_barang,
                                    nama: it.nama_barang,
                                    satuan: it.satuan,
                                    totalQty: 0,
                                    totalSubtotal: 0,
                                    ordersBreakdown: []
                                };
                            }
                            aggregatedItems[bId].totalQty += it.qty;
                            aggregatedItems[bId].totalSubtotal += it.subtotal;
                            aggregatedItems[bId].ordersBreakdown.push({
                                kode: kode,
                                customer: customer,
                                qty: it.qty,
                                satuan: it.satuan
                            });
                        });
                    });

                    // Render Tab 2 Rows
                    var itemKeys = Object.keys(aggregatedItems);
                    var itemIndex = 1;
                    var totalItemSubtotalAll = 0;

                    itemKeys.forEach(function(bId) {
                        var it = aggregatedItems[bId];
                        totalItemSubtotalAll += it.totalSubtotal;

                        var breakdownHtml = it.ordersBreakdown.map(function(ob) {
                            var qtyStr = (ob.qty % 1 === 0) ? ob.qty : ob.qty.toFixed(2);
                            return `<span class="badge bg-white text-dark border me-1 mb-1 font-monospace" style="font-size:10px;">#${ob.kode}: <strong>${qtyStr}</strong> ${ob.satuan}</span>`;
                        }).join(' ');

                        var totalQtyFmt = (it.totalQty % 1 === 0) ? it.totalQty.toLocaleString('id-ID') : it.totalQty.toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 2});

                        var trItem = document.createElement('tr');
                        trItem.innerHTML = `
                            <td>${itemIndex++}</td>
                            <td class="font-monospace fw-bold text-secondary">${it.kode}</td>
                            <td class="text-start fw-bold text-dark">${it.nama}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary">${it.satuan}</span></td>
                            <td class="text-end fw-bold text-primary">${totalQtyFmt}</td>
                            <td class="text-start">${breakdownHtml}</td>
                            <td class="text-end fw-bold text-success">Rp ${it.totalSubtotal.toLocaleString('id-ID')}</td>
                        `;
                        itemsTbody.appendChild(trItem);
                    });

                    // Summary counts
                    document.getElementById('bulkSelectedCountCold').textContent = selected.length;
                    document.getElementById('bulkTotalBayarDisplayCold').textContent = 'Rp ' + totalSisaSemua.toLocaleString('id-ID');
                    document.getElementById('bulkColdSubmitTotalText').textContent = 'Rp ' + totalSisaSemua.toLocaleString('id-ID');
                    document.getElementById('bulkTabColdOrderCount').textContent = selected.length;
                    document.getElementById('bulkTabColdItemCount').textContent = itemKeys.length;
                    document.getElementById('bulkTotalItemsSummaryCold').textContent = itemKeys.length + ' Jenis Barang';
                    document.getElementById('bulkTotalQtySummaryCold').textContent = totalQtyAllItems.toLocaleString('id-ID');

                    document.getElementById('bulkColdTfootTotalTagihan').textContent = 'Rp ' + totalTagihanSemua.toLocaleString('id-ID');
                    document.getElementById('bulkColdTfootTotalSisa').textContent = 'Rp ' + totalSisaSemua.toLocaleString('id-ID');

                    document.getElementById('bulkColdTfootTotalQty').textContent = totalQtyAllItems.toLocaleString('id-ID');
                    document.getElementById('bulkColdTfootItemTypes').textContent = itemKeys.length;
                    document.getElementById('bulkColdTfootTotalItemSubtotal').textContent = 'Rp ' + totalItemSubtotalAll.toLocaleString('id-ID');
                });
            }
        });

        // ==========================================
        // SCRIPT MODAL CREATE COLD KITCHEN ORDER
        // ==========================================
        var tomSelectCustomerCold = null;
        var coldItemTomSelects = [];

        function initTomSelectColdCustomer() {
            var el = document.getElementById('modal-select-customer-cold');
            if (el && !tomSelectCustomerCold) {
                tomSelectCustomerCold = new TomSelect(el, {
                    placeholder: '-- Cari / Pilih Outlet / Konsumen --',
                    allowEmptyOption: true,
                    maxItems: 1
                });
            }
        }

        function initTomSelectColdItem(selectElement) {
            if (!selectElement || selectElement.tomselect) return;
            var ts = new TomSelect(selectElement, {
                placeholder: '-- Cari BSJ / Barang Jadi --',
                allowEmptyOption: true,
                maxItems: 1,
                onChange: function() {
                    updateColdRowKonversiInfo(selectElement.closest('tr'));
                }
            });
            coldItemTomSelects.push(ts);
        }

        function updateColdRowKonversiInfo(row) {
            if (!row) return;
            var select = row.querySelector('.modal-select-produk-cold');
            var qtyInput = row.querySelector('.modal-input-cold-qty');
            var modeSelect = row.querySelector('.modal-select-cold-mode');
            var infoBox = row.querySelector('.modal-cold-konversi-info');

            var val = select ? select.value : '';
            var opt = select ? select.querySelector('option[value="' + val + '"]') : null;
            var qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
            var mode = modeSelect ? modeSelect.value : 'resep';

            if (!opt || !val) {
                if (infoBox) infoBox.innerHTML = '<span class="text-muted">-</span>';
                return;
            }

            var baseSatuan = opt.getAttribute('data-satuan') || '';
            var outQty = parseFloat(opt.getAttribute('data-output-qty')) || 0;
            var outSatuan = opt.getAttribute('data-satuan-output') || baseSatuan;
            var satuanKonv = opt.getAttribute('data-satuan-konversi') || '';
            var konvVal = parseFloat(opt.getAttribute('data-konversi')) || 1;

            var totalQtyTarget = qty;
            var textInfo = '';

            if (mode === 'resep') {
                if (outQty > 0) {
                    totalQtyTarget = qty * outQty;
                    var totalQtyFmt = (totalQtyTarget % 1 === 0) ? totalQtyTarget.toLocaleString('id-ID') : totalQtyTarget.toFixed(2);
                    textInfo = `<strong>${totalQtyFmt} ${outSatuan}</strong> <span class="text-muted">(${qty} Resep @ ${outQty.toLocaleString('id-ID')} ${outSatuan})</span>`;
                } else {
                    var totalQtyFmt = (qty % 1 === 0) ? qty.toLocaleString('id-ID') : qty.toFixed(2);
                    textInfo = `<strong>${totalQtyFmt} ${baseSatuan}</strong> <span class="text-muted">(Non-Resep)</span>`;
                }
            } else if (mode === 'konversi') {
                if (satuanKonv && konvVal > 1) {
                    totalQtyTarget = qty * konvVal;
                    var totalQtyFmt = (totalQtyTarget % 1 === 0) ? totalQtyTarget.toLocaleString('id-ID') : totalQtyTarget.toFixed(2);
                    textInfo = `<strong>${totalQtyFmt} ${baseSatuan}</strong> <span class="text-muted">(${qty} ${satuanKonv} @ ${konvVal.toLocaleString('id-ID')} ${baseSatuan})</span>`;
                } else {
                    var totalQtyFmt = (qty % 1 === 0) ? qty.toLocaleString('id-ID') : qty.toFixed(2);
                    textInfo = `<strong>${totalQtyFmt} ${baseSatuan}</strong>`;
                }
            } else {
                var totalQtyFmt = (qty % 1 === 0) ? qty.toLocaleString('id-ID') : qty.toFixed(2);
                textInfo = `<strong>${totalQtyFmt} ${baseSatuan}</strong>`;
            }

            if (infoBox) {
                infoBox.innerHTML = textInfo;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var modalCreate = document.getElementById('modalCreateColdOrder');
            if (modalCreate) {
                modalCreate.addEventListener('shown.bs.modal', function() {
                    initTomSelectColdCustomer();
                    document.querySelectorAll('.modal-select-produk-cold').forEach(function(sel) {
                        initTomSelectColdItem(sel);
                    });
                });
            }

            var tbody = document.getElementById('modal-cold-item-rows');
            var btnAddRow = document.getElementById('modal-btn-add-cold-item');

            if (btnAddRow && tbody) {
                btnAddRow.addEventListener('click', function() {
                    var firstRow = tbody.querySelector('tr');
                    if (!firstRow) return;

                    var newRow = firstRow.cloneNode(true);
                    
                    // Reset inputs
                    var qtyInp = newRow.querySelector('.modal-input-cold-qty');
                    if (qtyInp) qtyInp.value = '';

                    var infoBox = newRow.querySelector('.modal-cold-konversi-info');
                    if (infoBox) infoBox.innerHTML = '<span class="text-muted">-</span>';

                    // Bersihkan instance tomselect lama jika ter-clone
                    var tsWrapper = newRow.querySelector('.ts-wrapper');
                    if (tsWrapper) tsWrapper.remove();

                    var origSelect = newRow.querySelector('select.modal-select-produk-cold');
                    if (origSelect) {
                        origSelect.classList.remove('tomselected', 'ts-hidden-accessible');
                        origSelect.style.display = '';
                        origSelect.value = '';
                        origSelect.removeAttribute('id');
                    }

                    var btnRemove = newRow.querySelector('.modal-btn-remove-cold-row');
                    if (btnRemove) {
                        btnRemove.disabled = false;
                        btnRemove.addEventListener('click', function() {
                            if (tbody.querySelectorAll('tr').length > 1) {
                                newRow.remove();
                            }
                        });
                    }

                    tbody.appendChild(newRow);

                    // Re-init TomSelect pada row baru
                    if (origSelect) {
                        initTomSelectColdItem(origSelect);
                    }

                    // Attach event listener to new row elements
                    attachColdRowEvents(newRow);
                });
            }

            function attachColdRowEvents(row) {
                var qtyInput = row.querySelector('.modal-input-cold-qty');
                var modeSelect = row.querySelector('.modal-select-cold-mode');
                var btnRemove = row.querySelector('.modal-btn-remove-cold-row');

                if (qtyInput) {
                    qtyInput.addEventListener('input', function() {
                        updateColdRowKonversiInfo(row);
                    });
                }
                if (modeSelect) {
                    modeSelect.addEventListener('change', function() {
                        updateColdRowKonversiInfo(row);
                    });
                }
                if (btnRemove) {
                    btnRemove.addEventListener('click', function() {
                        if (tbody.querySelectorAll('tr').length > 1) {
                            row.remove();
                        }
                    });
                }
            }

            // Attach initial row events
            if (tbody) {
                tbody.querySelectorAll('tr').forEach(function(row) {
                    attachColdRowEvents(row);
                });
            }
        });
    </script>
</x-app-layout>