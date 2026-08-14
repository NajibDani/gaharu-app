<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .card-custom { border-radius: 16px; border: 1px solid #eaeaea; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .table-custom-header th { background-color: #6a4126 !important; color: #ffffff !important; font-size: 0.78rem; padding: 10px; }
        .table-custom-body td { font-size: 0.82rem; padding: 10px; border-bottom: 1px solid #f1f5f9; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important; max-width: 960px;">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Detail Central Kitchen Order</h4>
                <p class="text-muted small mb-0">Kode Pesanan: <span class="fw-bold text-primary">{{ $pesanan->kode_pesanan }}</span></p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('ck-orders.cetak-pdf', $pesanan->id) }}" target="_blank" class="btn btn-outline-danger btn-sm rounded-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
                </a>
                <a href="{{ route('ck-orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        <div class="card card-custom p-4 mb-4">
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Order</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <span class="text-muted small d-block">Outlet Pemesan</span>
                    <span class="fw-bold text-dark fs-6">{{ $pesanan->customer->nama ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Tanggal Order</span>
                    <span class="fw-semibold text-dark">{{ date('d F Y', strtotime($pesanan->tanggal)) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Estimasi Kirim</span>
                    <span class="fw-semibold text-dark">{{ date('d F Y', strtotime($pesanan->estimasi_kirim)) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Status Pesanan</span>
                    <span class="badge bg-info text-dark">{{ ucfirst($pesanan->status_pesanan) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Penagihan / Harga</span>
                    <span class="badge bg-success">HPP Internal (Rp 0 Penagihan)</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Dibuat Oleh</span>
                    <span class="fw-semibold text-dark">{{ $pesanan->creator->name ?? 'Sistem' }}</span>
                </div>
            </div>
        </div>

        <div class="card card-custom p-4">
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Detail Item Pesanan CK</h6>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="text-center" style="width: 50px;">NO</th>
                            <th>KODE BARANG</th>
                            <th>NAMA ITEM / BAHAN</th>
                            <th class="text-center">QTY TARGET</th>
                            <th class="text-center">SATUAN</th>
                        </tr>
                    </thead>
                    <tbody class="table-custom-body">
                        @foreach($pesanan->details as $index => $detail)
                            <tr>
                                <td class="text-center fw-semibold text-muted">{{ $index + 1 }}</td>
                                <td class="fw-bold text-dark">{{ $detail->produk->kode_barang ?? '-' }}</td>
                                <td>{{ $detail->produk->nama ?? 'Item Hapus' }}</td>
                                <td class="text-center fw-bold text-dark">{{ number_format($detail->qty, 2) }}</td>
                                <td class="text-center text-muted">{{ $detail->produk->satuan ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
