<x-app-layout>
    <x-slot name="header">
        Detil Barang: {{ $barang->nama }}
    </x-slot>

    <div class="container py-4">
        <div class="mb-3">
            <a href="{{ route('barang.index') }}" class="btn btn-secondary">
                &larr; Kembali ke Daftar Barang
            </a>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header text-white" style="background-color: #d88656; padding: 16px 20px;">
                <h5 class="mb-0 fw-bold">Informasi Barang</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Nama Barang</label>
                        <p class="fs-5 text-dark fw-semibold">{{ $barang->nama }}</p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Kode Barang</label>
                        <p class="fs-5 text-dark fw-semibold font-monospace">{{ $barang->kode_barang }}</p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Kategori</label>
                        <p class="fs-6 text-dark">{{ $barang->kategori ? $barang->kategori->nama : '—' }}</p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Satuan</label>
                        <p class="fs-6 text-dark">{{ $barang->satuan }}</p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Jenis Barang</label>
                        <p class="fs-6">
                            @if($barang->is_bahan_baku)
                                <span class="badge bg-primary">Bahan Baku</span>
                            @elseif($barang->is_bahan_setengah_jadi)
                                <span class="badge bg-info text-dark">Bahan Setengah Jadi</span>
                            @elseif($barang->is_barang_jadi)
                                <span class="badge bg-success">Barang Jadi</span>
                            @elseif($barang->is_operational)
                                <span class="badge bg-warning text-dark">Operational</span>
                            @else
                                <span class="badge bg-secondary">Umum</span>
                            @endif
                        </p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Status</label>
                        <p class="fs-6">
                            @if($barang->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-danger">Non-Aktif</span>
                            @endif
                        </p>
                    </div>

                    @if($barang->is_barang_jadi)
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold text-muted small uppercase">Tipe Penjualan</label>
                            <p class="fs-6 text-dark fw-semibold">
                                <span class="badge bg-info text-dark">{{ $barang->tipe_penjualan ?: 'Belum Diatur' }}</span>
                            </p>
                        </div>
                    @endif

                    @if($barang->is_bahan_setengah_jadi)
                        <div class="col-12 mb-3">
                            <label class="fw-bold text-muted small uppercase d-block mb-1">Batas Minimum Stock per Lokasi (Bahan Setengah Jadi)</label>
                            <div class="p-3 bg-light rounded border">
                                <div class="row text-center g-2">
                                    <div class="col-4 border-end">
                                        <span class="text-muted d-block small">Central Kitchen</span>
                                        <strong class="text-danger fs-6">{{ $barang->minimum_stock_ck !== null ? number_format($barang->minimum_stock_ck) . ' ' . $barang->satuan : '—' }}</strong>
                                    </div>
                                    <div class="col-4 border-end">
                                        <span class="text-muted d-block small">Outlet Kejingga</span>
                                        <strong class="text-danger fs-6">{{ $barang->minimum_stock_kejingga !== null ? number_format($barang->minimum_stock_kejingga) . ' ' . $barang->satuan : '—' }}</strong>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-muted d-block small">Outlet Gaharu</span>
                                        <strong class="text-danger fs-6">{{ $barang->minimum_stock_gaharu !== null ? number_format($barang->minimum_stock_gaharu) . ' ' . $barang->satuan : '—' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif($barang->is_bahan_baku)
                        <div class="col-12 mb-3">
                            <label class="fw-bold text-muted small uppercase d-block mb-1">Batas Minimum Stock per Outlet &amp; Divisi (Bahan Baku)</label>
                            @if($barang->minimumStocks && $barang->minimumStocks->count() > 0)
                                <div class="p-3 bg-light rounded border">
                                    <div class="row g-2">
                                        @foreach($barang->minimumStocks as $ms)
                                            <div class="col-md-4 col-sm-6">
                                                <div class="p-2 bg-white rounded border d-flex justify-content-between align-items-center {{ !$ms->is_active ? 'opacity-75 bg-light' : '' }}">
                                                    <div>
                                                        <span class="text-secondary small fw-semibold">
                                                            <i class="bi bi-geo-alt me-1 text-primary"></i>{{ $ms->gudang->nama ?? 'Gudang' }}{{ $ms->divisi ? ' (' . $ms->divisi->nama . ')' : '' }}
                                                        </span>
                                                        @if($ms->is_active)
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:0.65rem;">Aktif</span>
                                                        @else
                                                            <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:0.65rem;">Non-Aktif</span>
                                                        @endif
                                                    </div>
                                                    <strong class="text-dark small">{{ $ms->minimum_stock > 0 ? (number_format($ms->minimum_stock) . ' ' . $barang->satuan) : '—' }}</strong>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif($barang->minimum_stock !== null)
                                <p class="fs-6 text-danger fw-bold">
                                    {{ number_format($barang->minimum_stock) }} {{ $barang->satuan }}
                                </p>
                            @else
                                <p class="text-muted fst-italic small">Belum diatur (tidak ada batas minimum)</p>
                            @endif
                        </div>
                    @endif

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small uppercase">Batas Minimum Order</label>
                        <p class="fs-6 text-primary fw-bold">
                            {{ number_format($barang->minimum_order ?? 1) }} {{ $barang->satuan }}
                        </p>
                    </div>
                </div>

                @if($barang->resep && $barang->resep->count() > 0)
                    <hr class="my-4">
                    <h5 class="fw-bold mb-3" style="color: #9c4f18;">Detail Resep Bahan Baku</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Bahan Baku</th>
                                    <th>Jumlah (Qty)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($barang->resep as $r)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $r->bahanBaku ? $r->bahanBaku->nama : '—' }}</td>
                                        <td>{{ number_format($r->qty, 2) }} {{ $r->bahanBaku ? $r->bahanBaku->satuan : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
