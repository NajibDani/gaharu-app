<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }

        /* ===== TABLE - lebih padat ===== */
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

        /* ===== BADGE - lebih tipis ===== */
        .badge-subtle { border-radius: 6px; padding: 3px 9px; font-weight: 600; font-size: 0.7rem; display: inline-block; text-transform: capitalize; line-height: 1.4; }
        .badge-status-pending { background-color: #fef3c7; color: #d97706; }
        .badge-status-proses { background-color: #e0f2fe; color: #0369a1; }
        .badge-status-ready { background-color: #e0e7ff; color: #4338ca; }
        .badge-status-selesai { background-color: #dcfce7; color: #15803d; }
        .badge-status-batal { background-color: #fee2e2; color: #b91c1c; }

        /* ===== ACTION BUTTONS - dipadatkan, dropdown untuk aksi sekunder ===== */
        .action-btn-group { display: flex; justify-content: center; align-items: center; gap: 4px; flex-wrap: nowrap; }
        .btn-action-base {
            border-radius: 7px; width: 26px; height: 26px; font-size: 0.78rem;
            border: none; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: all 0.2s; background-color: transparent; flex-shrink: 0;
        }

        .btn-action-eye { background-color: #f0f9ff; color: #0369a1 !important; border: 1px solid #e0f2fe; }
        .btn-action-eye:hover { background-color: #0369a1; color: white !important; }

        .btn-action-edit { background-color: #fffbec; color: #b45309 !important; border: 1px solid #fef3c7; }
        .btn-action-edit:hover { background-color: #b45309; color: white !important; }

        .btn-action-delete { background-color: #fef2f2; color: #b91c1c !important; border: 1px solid #fee2e2; cursor: pointer; }
        .btn-action-delete:hover { background-color: #b91c1c; color: white !important; }

        .btn-action-print { background-color: #f0fdf4; color: #15803d !important; border: 1px solid #dcfce7; }
        .btn-action-print:hover { background-color: #15803d; color: white !important; }

        .btn-action-pdf { background-color: #fee2e2; color: #b91c1c !important; border: 1px solid #fca5a5; }
        .btn-action-pdf:hover { background-color: #b91c1c; color: white !important; }

        /* Kebab / dropdown "aksi lain" */
        .btn-action-more { background-color: #f8fafc; color: #64748b !important; border: 1px solid #e2e8f0; }
        .btn-action-more:hover, .btn-action-more.show { background-color: #64748b; color: white !important; }
        .dropdown-menu-actions { min-width: 170px; padding: 6px; border-radius: 10px; border: 1px solid #eaeaea; box-shadow: 0 8px 24px rgba(0,0,0,0.08); z-index: 1080; }
        .dropdown-menu-actions .dropdown-item { border-radius: 6px; font-size: 0.8rem; padding: 7px 10px; display: flex; align-items: center; gap: 8px; }
        .dropdown-menu-actions .dropdown-item i { width: 16px; text-align: center; }
        .dropdown-menu-actions .dropdown-item:hover { background-color: #f8fafc; }
        .dropdown-menu-actions .dropdown-item.text-danger:hover { background-color: #fef2f2; }

        /* Tombol Bayar - kecil, kompak, muat di satu baris */
        .btn-pay-small {
            background-color: #fff7ed;
            color: #db7946;
            font-weight: 700;
            font-size: 0.7rem;
            border-radius: 6px;
            padding: 4px 8px;
            border: 1px solid #fdba74;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-pay-small:hover {
            background-color: #db7946;
            color: white !important;
            border-color: #db7946;
        }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important;">

        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark" style="font-weight: 800; letter-spacing: -0.5px;">Data Pesanan & Transaksi</h4>
                <p class="text-muted mb-0 small"><i class="bi bi-info-circle me-1"></i> Validasi otomatis tombol aksi berdasarkan status alur kerja produksi (Work Order).</p>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
                <form action="{{ route('pesanan.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no pesanan/customer..." value="{{ request('search') }}" style="width: 220px; border-radius: 8px; border: 1px solid #eaeaea; padding: 7px 12px;">
                    <button type="submit" class="btn btn-sm text-white" style="background-color: #db7946; border-radius: 8px; border: none; padding: 7px 15px; font-weight: 600;">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('pesanan.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 8px; padding: 7px 15px; text-decoration: none; display: inline-flex; align-items: center;">Reset</a>
                    @endif
                </form>
                <a href="{{ route('pesanan.create') }}" class="btn btn-custom-orange shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle-fill"></i> Tambah Pesanan Baru
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
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Total Order Masuk</span>
                    <h4 class="fw-bold text-dark mb-0">{{ $totalPesanan }} Pesanan</h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Dalam Pengerjaan / Pengiriman</span>
                    <h4 class="fw-bold text-warning mb-0">{{ $totalProses }} Transaksi</h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Selesai & Lunas</span>
                    <h4 class="fw-bold text-success mb-0">{{ $totalSelesai }} Selesai</h4>
                </div>
            </div>
        </div>

        {{-- NOTIFIKASI ALERTS --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 p-3 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important;">
                <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
                <div>
                    <span class="fw-bold text-success d-block">Transaksi Berhasil</span>
                    <span class="small text-secondary">{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 p-3 d-flex align-items-center" role="alert" style="background-color: #fef2f2; border-left: 4px solid #ef4444 !important;">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-danger"></i>
                <div>
                    <span class="fw-bold text-danger d-block">Sistem Terkunci</span>
                    <span class="small text-secondary">{{ session('error') }}</span>
                </div>
                <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- MAIN TABLE CARD --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5" style="background: white;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom-body w-100">
                    <thead class="table-custom-header text-center">
                        <tr>
                            <th width="4%">No</th>
                            <th width="13%" class="text-start">Kode Pesanan</th>
                            <th width="18%" class="text-start">Pelanggan</th>
                            <th width="10%">Tanggal</th>
                            <th width="13%" class="text-end">Total Nilai</th>
                            <th width="10%">Status</th>
                            <th width="9%">Bayar</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @php $no = 1; @endphp
                        @forelse($dataPesanan as $item)
                            <tr>
                                <td class="text-center text-secondary fw-medium">{{ $no++ }}</td>
                                <td class="text-start fw-bold" style="color: #6a4126;">#{{ $item->kode_pesanan }}</td>
                                <td class="text-start">
                                    <div class="fw-semibold text-dark mb-0">{{ $item->customer->nama ?? $item->customer->name ?? 'Umum / Tanpa Nama' }}</div>
                                    @if(isset($item->customer->telepon))
                                        <span class="text-muted d-inline-flex align-items-center gap-1" style="font-size: 0.7rem;"><i class="bi bi-telephone text-secondary"></i> {{ $item->customer->telepon }}</span>
                                    @endif
                                </td>
                                <td class="text-center text-secondary">
                                    {{ date('d M Y', strtotime($item->tanggal ?? $item->tanggal_pesanan ?? $item->created_at)) }}
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp {{ number_format($item->total_harga ?? $item->total_pesanan ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="text-center">
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
                                <td class="text-center">
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

                                {{-- PANEL AKSI - dipadatkan: Eye + Bayar selalu tampil, sisanya masuk dropdown --}}
                                <td class="text-center">
                                    <div class="action-btn-group">

                                        {{-- 1. TOMBOL DETAIL (Selalu Ada) --}}
                                        <a href="{{ route('pesanan.show', $item->id) }}" class="btn-action-base btn-action-eye" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>

                                        {{-- 2. TOMBOL BAYAR CEPAT (jika belum lunas) --}}
                                        @if(isset($item->status_pembayaran) && $item->status_pembayaran != 'Lunas' && strtolower($item->status_pesanan ?? '') != 'dibatalkan' && strtolower($item->status_pesanan ?? '') != 'batal')
                                            <button type="button" class="btn-pay-small" data-bs-toggle="modal" data-bs-target="#modalBayar{{ $item->id }}">
                                                <i class="bi bi-wallet2"></i> Bayar
                                            </button>
                                        @endif

                                        {{-- 3. DROPDOWN AKSI LAIN (print, pdf, edit, batal, hapus) --}}
                                        <div class="dropdown dropup">
                                            <button class="btn-action-base btn-action-more" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed" data-bs-boundary="viewport" aria-expanded="false" title="Aksi lainnya">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-actions">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('pesanan.kwitansi', $item->id) }}" target="_blank">
                                                        <i class="bi bi-printer-fill text-success"></i> Cetak Kwitansi
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('pesanan.cetak-pdf', $item->id) }}" target="_blank">
                                                        <i class="bi bi-file-earmark-pdf-fill text-danger"></i> Cetak Sales Order
                                                    </a>
                                                </li>

                                                @if(!isset($item->wo_status) || $item->wo_status === null)
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('pesanan.edit', $item->id) }}">
                                                            <i class="bi bi-pencil-fill text-warning"></i> Edit Pesanan
                                                        </a>
                                                    </li>
                                                @endif

                                                @if(strtolower($item->status_pesanan ?? '') !== 'dibatalkan' && strtolower($item->status_pesanan ?? '') !== 'batal')
                                                    @if(!isset($item->wo_status) || $item->wo_status === null || $item->wo_status === 'draft')
                                                        <li>
                                                            <form action="{{ route('pesanan.batal', $item->id) }}" method="POST" class="m-0 p-0" onsubmit="return confirm('Batalkan pesanan ini?')">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-danger">
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
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash3-fill"></i> Hapus Permanen
                                                            </button>
                                                        </form>
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
                                                    <div class="modal-header text-white border-0 p-4" style="background-color: #6a4126;">
                                                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2"><i class="bi bi-shield-check"></i> Form Input Pembayaran</h5>
                                                        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 bg-white">
                                                        @php
                                                            $totalNilai = $item->total_harga ?? $item->total_pesanan ?? 0;
                                                            $sudahDibayar = isset($item->pembayaran) ? $item->pembayaran->sum('jumlah_bayar') : 0;
                                                            $sisaTagihan = $totalNilai - $sudahDibayar;
                                                            $minimalDP = $totalNilai * 0.60;
                                                            $minInput = ($item->status_pembayaran == 'Belum Bayar') ? $minimalDP : 1;
                                                        @endphp

                                                        <div class="p-3 rounded-3 mb-4" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block small mb-1">Invoice: <strong class="text-dark">#{{ $item->kode_pesanan }}</strong></span>
                                                            <span class="text-muted d-block small">Total Piutang Kontrak:</span>
                                                            <h3 class="fw-bold text-dark mb-2">Rp {{ number_format($totalNilai, 0, ',', '.') }}</h3>
                                                            @if($item->status_pembayaran == 'Belum Bayar')
                                                                <div class="text-danger small fw-medium"><i class="bi bi-info-circle-fill"></i> Pembayaran awal (DP minimal 60%): <strong>Rp {{ number_format($minimalDP, 0, ',', '.') }}</strong></div>
                                                            @else
                                                                <div class="text-muted small">Sisa Pelunasan: <strong class="text-success">Rp {{ number_format($sisaTagihan, 0, ',', '.') }}</strong></div>
                                                            @endif
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Jumlah Bayar (Rp)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light border-end-0 fw-semibold text-muted">Rp</span>
                                                                <input type="number" name="jumlah_bayar" class="form-control border-start-0" min="{{ $minInput }}" max="{{ $sisaTagihan }}" placeholder="Maksimal Rp {{ number_format($sisaTagihan, 0, '', '') }}" required style="outline: none; box-shadow: none;">
                                                            </div>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label fw-semibold small text-secondary">Tanggal Bayar</label>
                                                                <input type="date" name="tanggal_bayar" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label fw-semibold small text-secondary">Metode</label>
                                                                <select name="metode_pembayaran" class="form-select text-secondary" required>
                                                                    <option value="Cash">Cash / Tunai</option>
                                                                    <option value="Transfer">Transfer Bank</option>
                                                                    <option value="QRIS">QRIS</option>
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
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 bg-white">
                                    <div class="py-4">
                                        <i class="bi bi-folder-x text-muted opacity-40 display-4 d-block mb-3"></i>
                                        <span class="fw-semibold text-dark d-block">Belum Ada Data Kontrak</span>
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

        document.addEventListener('hidden.bs.dropdown', function (e) {
            var button = e.target;
            var menu = button._dropdownMenuRef;
            if (!menu || !menu._originalParent) return;
            menu._originalParent.insertBefore(menu, menu._originalNextSibling);
        });
    </script>
</x-app-layout>