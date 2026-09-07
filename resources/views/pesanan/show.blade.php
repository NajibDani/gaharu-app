<x-app-layout>
    @php
        $sudahWO = \App\Models\WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
    @endphp

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0 text-dark">Detail Permintaan Cold Kitchen</h3>
                <small class="text-muted">Informasi lengkap pesanan &amp; status produksi: <span class="fw-bold text-primary">#{{ $pesanan->kode_pesanan }}</span></small>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                @if(!$sudahWO)
                    <a href="{{ route('pesanan.edit', $pesanan->id) }}" class="btn btn-warning btn-sm shadow-sm text-dark fw-bold px-3">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </a>
                    <form action="{{ route('pesanan.destroy', $pesanan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus permintaan #{{ $pesanan->kode_pesanan }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm shadow-sm fw-bold px-3">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </button>
                    </form>
                @endif
                <a href="{{ route('pesanan.kwitansi', $pesanan->id) }}" class="btn btn-outline-primary btn-sm shadow-sm px-3" target="_blank">
                    <i class="bi bi-printer me-1"></i> Cetak Kwitansi
                </a>
                <a href="{{ route('pesanan.cetak-pdf', $pesanan->id) }}" class="btn btn-outline-danger btn-sm shadow-sm px-3" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak SO
                </a>
                <a href="{{ route('pesanan.index') }}" class="btn btn-secondary btn-sm shadow-sm px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Kode Pesanan</label>
                        <h5>{{ $pesanan->kode_pesanan }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Customer</label>
                        <h5>{{ $pesanan->customer->nama ?? '-' }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Tanggal</label>
                        <h5>{{ date('d M Y H:i', strtotime($pesanan->tanggal)) }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Status</label>
                        <br>
                        {{-- PERBAIKAN LOGIKA STATUS DI SINI --}}
                        @if($pesanan->status_pesanan == 'pending')
                            <span class="badge bg-warning text-dark">Pending</span>
                        @elseif($pesanan->status_pesanan == 'diproses')
                            <span class="badge bg-info">Diproses</span>
                        @elseif($pesanan->status_pesanan == 'siap kirim')
                            <span class="badge bg-success">Siap Dikirim</span>
                        @elseif($pesanan->status_pesanan == 'dikirim')
                            <span class="badge bg-primary">Dikirim</span>
                        @elseif($pesanan->status_pesanan == 'selesai')
                            <span class="badge bg-success">Selesai</span>
                        @elseif($pesanan->status_pesanan == 'batal')
                            <span class="badge bg-danger">Batal</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($pesanan->status_pesanan) }}</span>
                        @endif
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">No. HP Customer</label>
                        <h5>{{ $pesanan->customer->no_hp ?? '-' }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Tipe Customer</label>
                        <h5>{{ $pesanan->customer->jenis ?? '-' }}</h5>
                    </div>

                    <div class="col-12 col-md-6 mb-3">
                        <label class="text-muted">Alamat Customer</label>
                        <h5>{{ $pesanan->customer->alamat ?? '-' }}</h5>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label class="text-muted fw-bold">Estimasi Tanggal Kirim</label>
                        <h6 class="text-dark fw-semibold">{{ date('d M Y H:i', strtotime($pesanan->estimasi_kirim)) }}</h6>
                    </div>

                    <div class="col-12 col-md-6 mb-3">
                        <label class="text-muted fw-bold">Estimasi Tanggal Produksi</label>
                        <h6 class="text-primary fw-bold">{{ $pesanan->estimasi_produksi ? date('d M Y', strtotime($pesanan->estimasi_produksi)) : '— (Tidak Perlu Produksi / Sudah Siap)' }}</h6>
                    </div>
                </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Detail Produk</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Produk</th>
                                <th width="150">Qty</th>
                                <th width="200">Harga</th>
                                <th width="200">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pesanan->details as $detail)
                            <tr>
                                <td>{{ $detail->produk->nama ?? 'Produk Tidak Ditemukan' }}</td>
                                <td>{{ number_format($detail->qty, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($detail->harga, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end text-secondary fw-normal">Subtotal (DPP)</th>
                                <th class="text-secondary fw-normal">Rp {{ number_format($pesanan->total_pesanan - $pesanan->tax_service, 0, ',', '.') }}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end text-secondary fw-normal">Tax/Service ({{ number_format($pesanan->tax_percentage, 2) }}%)</th>
                                <th class="text-secondary fw-normal">Rp {{ number_format($pesanan->tax_service, 0, ',', '.') }}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end text-primary fw-bold">Total (Nett)</th>
                                <th class="text-primary fw-bold">Rp {{ number_format($pesanan->total_pesanan, 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0 fw-bold">Bukti Pembayaran</h5>
            </div>
            <div class="card-body">
                @php
                    $pembayaranList = $pesanan->pembayaran ?? collect();
                @endphp

                @if($pembayaranList->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nominal</th>
                                    <th>Metode</th>
                                    <th>Catatan</th>
                                    <th>Bukti Upload</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pembayaranList as $p)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($p->tanggal_bayar)->format('d M Y') }}</td>
                                        <td class="fw-semibold text-success">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
                                        <td>{{ $p->metode_pembayaran }}</td>
                                        <td>{{ $p->catatan ?? '-' }}</td>
                                        <td>
                                            @if($p->bukti_pembayaran && is_array($p->bukti_pembayaran))
                                                <div class="d-flex gap-2 flex-wrap">
                                                    @foreach($p->bukti_pembayaran as $img)
                                                        <a href="{{ asset('storage/' . $img) }}" target="_blank">
                                                            <img src="{{ asset('storage/' . $img) }}" class="img-thumbnail" style="width: 70px; height: 70px; object-fit: cover;" alt="Bukti">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">Belum ada bukti pembayaran yang diupload.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>