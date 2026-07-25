<x-app-layout>

    <div class="container mt-4 mb-5">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2"></i> Detail Produksi</h4>
                    <small class="text-white-50">Rincian data hasil produksi dan status approval.</small>
                </div>
                <div>
                    @if($produksi->status_produksi === 'Selesai')
                        <span class="badge bg-success fs-6 px-3 py-2 shadow-sm"><i class="bi bi-check-circle me-1"></i> Selesai</span>
                    @else
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2 shadow-sm"><i class="bi bi-hourglass-split me-1"></i> Draft</span>
                    @endif
                </div>
            </div>

            <div class="card-body p-4">
                
                <div class="row mb-4 bg-light p-3 rounded border">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td width="150" class="text-muted">Kode Produksi</td>
                                <td>: <strong class="text-dark fs-5">{{ $produksi->kode_produksi }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal Mulai</td>
                                <td>: {{ \Carbon\Carbon::parse($produksi->tanggal_mulai)->translatedFormat('d F Y') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal Selesai</td>
                                <td>: 
                                    @if($produksi->tanggal_selesai)
                                        {{ \Carbon\Carbon::parse($produksi->tanggal_selesai)->translatedFormat('d F Y') }}
                                    @else
                                        <span class="text-muted fst-italic">Belum Selesai</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 border-start">
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td width="150" class="text-muted">Kode Pesanan</td>
                                <td>: <span class="badge bg-secondary fs-6">{{ $produksi->pesanan->kode_pesanan ?? '-' }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nama Customer</td>
                                <td>: <strong>{{ $produksi->pesanan->customer->nama ?? 'Tidak Ada / Umum' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Lokasi Penyimpanan</td>
                                <td>: <strong>{{ $namaGudang }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <h5 class="fw-bold border-bottom pb-2 mb-3">Item Hasil Produksi</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th width="5%">No</th>
                                <th class="text-start ps-3">Nama Produk</th>
                                <th width="20%">Qty Hasil</th>
                                <th width="25%">Total HPP (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($produksi->details as $index => $detail)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-start ps-3 fw-bold">{{ $detail->produk->nama ?? 'Produk Tidak Diketahui' }}</td>
                                    <td class="text-center fw-bold text-primary fs-5">{{ number_format($detail->qty, 0, ',', '.') }} Unit</td>
                                    <td class="text-end text-success fw-bold pe-3">
                                        @if($produksi->status_produksi === 'Draft')
                                            <span class="text-muted fw-normal fst-italic small">Dihitung saat Approve</span>
                                        @else
                                            Rp {{ number_format($detail->hpp_total, 0, ',', '.') }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Tidak ada detail produk.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <hr class="text-muted my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('produksi.index') }}" class="btn btn-secondary px-4 shadow-sm">
                        <i class="bi bi-arrow-left-circle me-1"></i> Kembali
                    </a>

                    <div class="d-flex gap-2">
                        @if($produksi->status_produksi === 'Draft')
                            <a href="{{ route('produksi.edit', $produksi->id) }}" class="btn btn-warning text-dark fw-bold shadow-sm">
                                <i class="bi bi-pencil-square me-1"></i> Edit Draft
                            </a>
                            
                            <form action="{{ route('produksi.approve', $produksi->id) }}" method="POST" class="d-inline" id="form-approve">
                                @csrf
                                <button type="button" class="btn btn-success fw-bold shadow-sm" onclick="confirmApprove()">
                                    <i class="bi bi-check2-all me-1"></i> Setujui & Proses Stok (Approve)
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('produksi.cetak-pdf', $produksi->id) }}" class="btn btn-danger shadow-sm px-4 fw-bold">
                            <i class="bi bi-file-pdf me-1"></i> Cetak PDF
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function confirmApprove() {
            if (confirm('Apakah Anda yakin ingin menyetujui produksi ini?\n\nSetelah disetujui:\n- Stok bahan baku akan dipotong (FIFO)\n- HPP akan dihitung permanen\n- Data tidak dapat diedit/dihapus lagi.')) {
                document.getElementById('form-approve').submit();
            }
        }
    </script>

    <style>
        /* Sembunyikan elemen tidak penting saat diprint */
        @media print {
            body { background-color: #fff; }
            .btn, nav, header, footer, .alert { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            .card-header { background-color: #f8f9fa !important; color: #000 !important; }
        }
    </style>

</x-app-layout>