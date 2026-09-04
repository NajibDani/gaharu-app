<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        .text-gray-800 { color: #1e293b; }
        .text-gray-700 { color: #334155; }
        .text-gray-600 { color: #475569; }
        .table-responsive { border-radius: 12px; }
        .btn-primary-theme { background-color: #d88656; color: white; border: none; transition: all 0.2s; }
        .btn-primary-theme:hover { background-color: #c77545; color: white; }
    </style>

    <div class="container py-4" style="font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; min-height: 100vh; margin-top: 5.5rem !important;">
        <div class="row">
            {{-- Alert Notifikasi --}}
            <div class="col-md-12 mb-3">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important;">
                        <i class="bi bi-check-circle-fill me-3 fs-5 text-success"></i>
                        <div>
                            <span class="fw-bold text-success d-block">Berhasil</span>
                            <span class="small text-secondary">{{ session('success') }}</span>
                        </div>
                        <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>

            {{-- Header Section --}}
            <div class="col-md-12 mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold text-gray-800 m-0"><i class="bi bi-tags-fill me-2" style="color: #d88656;"></i>Manajemen Harga Jual (POS)</h3>
                    <p class="text-muted small m-0 mt-1">Kelola aturan masa berlaku rentang periode harga khusus untuk item kategori <strong>Barang Jadi</strong>.</p>
                </div>
            </div>

            {{-- Daftar Barang Jadi --}}
            <div class="col-md-12">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body bg-white p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="fw-bold text-gray-800 m-0"><i class="bi bi-list-stars me-2" style="color: #d88656;"></i>Daftar Barang Jadi</h5>
                            <div class="d-flex align-items-center gap-2">
                                <form action="{{ route('harga.index') }}" method="GET" class="d-flex gap-2 align-items-center">
                                    <select name="tipe_penjualan" class="form-select form-select-sm rounded-3" style="width: 180px;" onchange="this.form.submit()">
                                        <option value="">-- Semua Outlet --</option>
                                        <option value="POS Gaharu" {{ request('tipe_penjualan') == 'POS Gaharu' ? 'selected' : '' }}>POS Gaharu</option>
                                        <option value="POS Kejingga" {{ request('tipe_penjualan') == 'POS Kejingga' ? 'selected' : '' }}>POS Kejingga</option>
                                        <option value="B2B" {{ request('tipe_penjualan') == 'B2B' ? 'selected' : '' }}>B2B</option>
                                    </select>
                                    @if(request('tipe_penjualan'))
                                        <a href="{{ route('harga.index') }}" class="btn btn-sm btn-outline-secondary rounded-3">Reset</a>
                                    @endif
                                </form>
                                <span class="badge bg-light text-dark border px-3 py-2 fw-semibold rounded-pill">
                                    Total: <span style="color: #d88656;">{{ $listBarang->count() }} Produk</span>
                                </span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background-color: #f8fafc; color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                                    <tr>
                                        <th class="ps-4 py-3">Nama Barang</th>
                                        <th class="py-3 text-end">Harga Jual</th>
                                        <th class="py-3 text-end">HPP Referensi</th>
                                        <th class="py-3 text-center">Periode</th>
                                        <th class="py-3 text-center">Status</th>
                                        <th class="py-3 text-center pe-4" width="120">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($listBarang as $item)
                                        @php
                                            $hpp = $item->dynamic_hpp;
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-gray-800">{{ $item->nama }}</div>
                                                <div class="small text-muted" style="font-size: 0.75rem;">Kode: {{ $item->kode_barang }}</div>
                                            </td>
                                            <td class="text-end fw-bold">
                                                @if($item->hargaPosAktif)
                                                    <div class="text-success">Rp {{ number_format($item->hargaPosAktif->harga_pos, 0, ',', '.') }}</div>
                                                @else
                                                    <div class="text-dark">Rp {{ number_format($item->harga_jual_pos, 0, ',', '.') }} <span class="text-muted small" style="font-size: 10px; font-weight: normal;">(Base)</span></div>
                                                @endif
                                            </td>
                                            <td class="text-end text-gray-700 fw-semibold">
                                                Rp {{ number_format($hpp, 0, ',', '.') }}
                                            </td>
                                            <td class="text-center">
                                                @if($item->hargaPosAktif)
                                                    <div class="text-gray-800 small fw-semibold">
                                                        {{ date('d M Y', strtotime($item->hargaPosAktif->tgl_mulai)) }}
                                                    </div>
                                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                                        s/d {{ date('d M Y', strtotime($item->hargaPosAktif->tgl_selesai)) }}
                                                    </div>
                                                @else
                                                    <div class="text-muted small">—</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($item->hargaPosAktif)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill text-uppercase" style="font-size: 0.65rem; font-weight: 700;">
                                                        <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 rounded-pill text-uppercase" style="font-size: 0.65rem; font-weight: 700;">
                                                        Standar
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center pe-4">
                                                <a href="{{ route('harga.show', $item->id) }}" class="btn btn-sm btn-primary-theme rounded-3 px-3 fw-bold shadow-none" style="font-size: 0.8rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <div class="py-4">
                                                    <i class="bi bi-box-seam fs-1 text-secondary opacity-25 d-block mb-3"></i>
                                                    <h6 class="fw-bold text-gray-700">Belum ada barang jadi terdaftar.</h6>
                                                    <p class="small text-secondary mb-0">Pastikan data barang jadi sudah terinput di menu Master Barang.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>