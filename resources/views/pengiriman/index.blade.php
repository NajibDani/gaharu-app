<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8f9fa; }
        .table-custom-header th { background-color: #6a4126 !important; color: #ffffff !important; font-weight: 600; border-bottom: none; font-size: 0.85rem; padding: 12px; }
        .table-custom-body td { font-size: 0.85rem; padding: 12px; vertical-align: middle; }
        .btn-custom-orange { background-color: #db7946; color: white; border: none; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
        .summary-card { border-radius: 12px; border: 1px solid #eaeaea; background: #ffffff; padding: 16px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        
        /* Badge Styling persis gambar */
        .badge-draft { background-color: #ffc107; color: #000; border-radius: 6px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; }
        .badge-approved { background-color: #d97745; color: #fff; border-radius: 6px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; }
        
        /* Action Buttons Styling */
        .action-btn { border-radius: 4px; padding: 4px 10px; font-size: 0.85rem; border: none; font-weight: 500;}
        .btn-eye { background-color: #17a2b8; color: white; }
        .btn-edit { background-color: #ffc107; color: black; }
        .btn-approve { background-color: #198754; color: white; } /* Diubah dari btn-check agar tidak bentrok dengan Bootstrap */
        
        .btn-eye:hover { background-color: #138496; color: white; }
        .btn-edit:hover { background-color: #e0a800; color: black; }
        .btn-approve:hover { background-color: #157347; color: white; }
    </style>

    <div class="container-fluid py-4 mb-5">
        
        {{-- HEADER SECTION --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Pengiriman Surat Jalan</h4>
                <p class="text-muted mb-0 small">Manajemen pengiriman stok barang dan surat jalan</p>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
                <form action="{{ route('pengiriman.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no pengiriman..." value="{{ request('search') }}" style="width: 200px; border-radius: 8px;">
                    <button type="submit" class="btn btn-sm text-white btn-custom-orange action-btn" style="border-radius: 8px; border: none; padding: 5px 15px; font-weight: 600;">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('pengiriman.index') }}" class="btn btn-sm btn-secondary action-btn" style="border-radius: 8px; padding: 5px 15px; text-decoration: none;">Reset</a>
                    @endif
                </form>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary text-white rounded-3 shadow-sm px-3 action-btn d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
                <a href="{{ route('pengiriman.create') }}" class="btn btn-custom-orange rounded-3 shadow-sm px-3 action-btn d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle"></i> Tambah
                </a>
            </div>
        </div>

        {{-- SUMMARY CARDS --}}
        @php
            $totalData = $totalData ?? $pengirimans->count();
            $totalDraft = $totalDraft ?? $pengirimans->where('status_pengiriman', 'Draft')->count();
            $totalApproved = $totalApproved ?? $pengirimans->where('status_pengiriman', 'Selesai')->count();
        @endphp
        <div class="row mb-4 g-3">
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Total Pengiriman</span>
                    <h4 class="fw-bold text-dark mb-0">{{ $totalData }} Transaksi</h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Draft Surat Jalan</span>
                    <h4 class="fw-bold text-warning mb-0">{{ $totalDraft }} Surat Jalan</h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card">
                    <span class="text-secondary mb-1 d-block fw-medium small">Terkirim & Approved</span>
                    <h4 class="fw-bold text-success mb-0">{{ $totalApproved }} Pengiriman</h4>
                </div>
            </div>
        </div>

        {{-- ALERTS (Notifikasi) --}}
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
                <span style="font-size: 0.85rem;">Harap segera lakukan penginputan dan penyelesaian (Approval) seluruh pengiriman barang sebelum akhir bulan ini. Transaksi pada periode bulan berjalan tidak akan bisa diinput atau diubah setelah penutupan buku (Closing Jurnal) dilakukan.</span>
            </div>
        </div>

        {{-- MAIN TABLE CARD --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom-body">
                    <thead class="table-custom-header text-center">
                        <tr>
                            <th width="5%" class="py-3">No</th>
                            <th width="18%" class="text-start">No Pengiriman</th>
                            <th width="15%" class="text-start">Kode Pesanan</th>
                            <th width="15%">Tanggal Kirim</th>
                            <th class="text-start">Kurir / Armada</th>
                            <th width="10%">Status</th>
                            <th width="20%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @php $no = 1; @endphp
                        @forelse($pengirimans as $kirim)
                            <tr>
                                <td class="text-center text-secondary">{{ $no++ }}</td>
                                <td class="text-start fw-bold text-dark">{{ $kirim->no_pengiriman }}</td>
                                <td class="text-start text-dark fw-medium">{{ $kirim->pesanan->kode_pesanan ?? '-' }}</td>
                                <td class="text-center text-secondary">
                                    {{ \Carbon\Carbon::parse($kirim->tanggal_pengiriman)->format('Y-m-d') }}
                                </td>
                                <td class="text-start text-dark fw-medium">{{ $kirim->kurir }}</td>
                                <td class="text-center">
                                    @if($kirim->status_pengiriman === 'Draft')
                                        <span class="badge-draft">Draft</span>
                                    @else
                                        <span class="badge-approved">Approved</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                                        
                                        {{-- Tombol Detail (Mata Cyan) --}}
                                        <a href="{{ route('pengiriman.show', $kirim->id) }}" class="btn action-btn btn-eye shadow-sm" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        @if($kirim->status_pengiriman === 'Draft')
                                            
                                            {{-- Tombol Edit (Kuning) --}}
                                            <a href="{{ route('pengiriman.edit', $kirim->id) }}" class="btn action-btn btn-edit shadow-sm text-dark d-flex align-items-center" title="Edit">
                                                Edit
                                            </a>

                                            {{-- Tombol Approve (Centang Hijau) --}}
                                            <form action="{{ route('pengiriman.approve', $kirim->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve surat jalan ini? Stok barang akan dipotong secara permanen.')">
                                                @csrf
                                                <button type="submit" class="btn action-btn btn-approve shadow-sm" title="Approve">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>

                                            {{-- Tombol Hapus (Merah) --}}
                                            <form action="{{ route('pengiriman.destroy', $kirim->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus draft ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn action-btn bg-danger text-white shadow-sm" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                        
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        Belum ada riwayat pengiriman logistik.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">
            {{ $pengirimans->links() }}
        </div>
    </div>
</x-app-layout>