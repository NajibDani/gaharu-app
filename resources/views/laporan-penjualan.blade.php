<x-app-layout>
<div class="container py-4">
    {{-- JUDUL HALAMAN --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Penjualan B2B</h4>
            <p class="text-muted small mb-0">Pantau ringkasan omzet dan performa penjualan berkala.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('laporan.penjualan', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success">
                📊 Export Excel
            </a>
            <a href="{{ route('laporan.penjualan', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-danger">
                📕 Export PDF
            </a>
        </div>
    </div>

    {{-- KARTU FILTER TANGGAL --}}
    <div class="card shadow-sm border-0 p-4 mb-4">
        <form action="{{ route('laporan.penjualan') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold text-dark small">Dari Tanggal</label>
                <input type="date" name="tanggal_mulai" class="form-control" value="{{ $tanggal_mulai }}">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold text-dark small">Sampai Tanggal</label>
                <input type="date" name="tanggal_selesai" class="form-control" value="{{ $tanggal_selesai }}">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                    <i class="bi bi-filter me-1"></i> Filter Data
                </button>
                <a href="{{ route('laporan.penjualan') }}" class="btn btn-light border w-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- KOTAK STATISTIK / WIDGET RINGKASAN --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 p-3 bg-primary text-white">
                <div class="small opacity-75 fw-bold text-uppercase">Total Omzet Penjualan</div>
                <div class="fs-3 fw-bold mt-1">Rp {{ number_format($total_omzet, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 p-3 bg-white border-start border-success border-4">
                <div class="small text-muted fw-bold text-uppercase">Pesanan Selesai</div>
                <div class="fs-3 fw-bold text-success mt-1">{{ $pesanan_selesai }} <span class="fs-6 text-muted fw-normal">Transaksi</span></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 p-3 bg-white border-start border-warning border-4">
                <div class="small text-muted fw-bold text-uppercase">Pesanan Pending / Proses</div>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $pesanan_pending }} <span class="fs-6 text-muted fw-normal">Transaksi</span></div>
            </div>
        </div>
    </div>

    {{-- TABEL DETAIL TRANSAKSI --}}
    <div class="card shadow-sm border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-stars me-2 text-primary"></i>Daftar Transaksi Masuk</h6>
            <span class="badge bg-light text-dark border py-2 px-3 small shadow-sm">Total: {{ $pesanans->count() }} Pesanan</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle border text-center">
                <thead class="table-light text-uppercase small font-monospace">
                    <tr>
                        <th>Kode</th>
                        <th>Customer</th>
                        <th>Tanggal</th>
                        <th>Status Pesanan</th>
                        <th>Status Bayar</th>
                        <th class="text-end">Total HPP</th>
                        <th class="text-end pe-4">Total Omzet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pesanans as $p)
                        <tr>
                            <td class="fw-bold text-dark">{{ $p->kode_pesanan }}</td>
                            <td>{{ $p->customer->nama ?? 'N/A' }}</td>
                            <td>{{ date('d M Y', strtotime($p->tanggal)) }}</td>
                            <td>
                                {{-- Pengecekan status menggunakan strtolower agar aman dari beda huruf kapital --}}
                                @if(strtolower($p->status_pesanan) == 'selesai')
                                    <span class="badge bg-success rounded-pill px-3 py-1">Selesai</span>
                                @elseif(strtolower($p->status_pesanan) == 'siap kirim' || strtolower($p->status_pesanan) == 'siap_kirim')
                                    <span class="badge bg-info text-white rounded-pill px-3 py-1">Siap kirim</span>
                                @else
                                    <span class="badge bg-warning rounded-pill px-3 py-1 text-dark">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($p->status_bayar) && strtolower($p->status_bayar) == 'lunas')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-1">Lunas</span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning rounded-pill px-3 py-1 text-dark">
                                        {{ $p->status_bayar ?? 'DP 30%' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end text-muted">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <span>Rp {{ number_format($p->total_hpp ?? 0, 0, ',', '.') }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 btn-detail-hpp" data-id="{{ $p->id }}" data-type="b2b" title="Detail HPP">
                                        <i class="bi bi-info-circle text-primary" style="font-size: 14px;"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="text-end pe-4 fw-bold text-dark">
                                Rp {{ number_format($p->details_sum_subtotal ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted py-5 text-center">
                                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data penjualan ditemukan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL DETAIL HPP -->
<div class="modal fade" id="modalDetailHpp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-dark text-white border-0 px-4 py-3" style="border-radius: 12px 12px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold">Detail Komponen HPP</h5>
                    <p class="text-white-50 mb-0 small" id="lblNoPesanan"></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- LOADING STATE -->
                <div id="loadingHpp" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 small">Memuat detail HPP...</p>
                </div>

                <!-- DATA STATE -->
                <div id="contentHpp" class="d-none">
                    <!-- RINGKASAN TOTAL -->
                    <div class="card border-0 shadow-sm mb-4 rounded-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-secondary text-uppercase mb-3 small" style="font-size: 11px; letter-spacing: 0.5px;">Ringkasan HPP Keseluruhan</h6>
                            <div class="row g-3">
                                <div class="col-4">
                                    <div class="p-3 bg-primary bg-opacity-10 rounded text-center border-start border-primary border-4">
                                        <span class="d-block text-muted small text-uppercase" style="font-size: 10px;">Bahan Baku (BBB)</span>
                                        <strong class="text-primary fs-6" id="lblTotalBbb">Rp 0</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-warning bg-opacity-10 rounded text-center border-start border-warning border-4">
                                        <span class="d-block text-muted small text-uppercase" style="font-size: 10px;">BTKL (20%)</span>
                                        <strong class="text-warning-emphasis fs-6" id="lblTotalBtkl">Rp 0</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-info bg-opacity-10 rounded text-center border-start border-info border-4">
                                        <span class="d-block text-muted small text-uppercase" style="font-size: 10px;">BOP (10%)</span>
                                        <strong class="text-info-emphasis fs-6" id="lblTotalBop">Rp 0</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark small">TOTAL HPP PESANAN</span>
                                <strong class="text-success fs-5" id="lblTotalHpp">Rp 0</strong>
                            </div>
                        </div>
                    </div>

                    <!-- RINCIAN PER BARANG -->
                    <h6 class="fw-bold text-secondary text-uppercase mb-2 small" style="font-size: 11px; letter-spacing: 0.5px;">Rincian Per Item Barang</h6>
                    <div class="table-responsive bg-white rounded-3 shadow-sm border border-light">
                        <table class="table align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-dark small text-uppercase" style="font-size: 11px;">
                                <tr>
                                    <th>Nama Barang</th>
                                    <th class="text-center" width="80">Qty</th>
                                    <th class="text-end" width="120">HPP Satuan</th>
                                    <th class="text-end" width="120">Bahan Baku</th>
                                    <th class="text-end" width="110">BTKL</th>
                                    <th class="text-end" width="110">BOP</th>
                                    <th class="text-end" width="130">Total HPP</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetailHpp">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3 bg-light">
                <button type="button" class="btn btn-secondary fw-semibold px-4" style="border-radius: 8px;" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalDetailHpp');
        if (!modalEl) return;
        
        const modal = new bootstrap.Modal(modalEl);
        const lblNoPesanan = document.getElementById('lblNoPesanan');
        const lblTotalBbb = document.getElementById('lblTotalBbb');
        const lblTotalBtkl = document.getElementById('lblTotalBtkl');
        const lblTotalBop = document.getElementById('lblTotalBop');
        const lblTotalHpp = document.getElementById('lblTotalHpp');
        const tbodyDetailHpp = document.getElementById('tbodyDetailHpp');
        const loadingHpp = document.getElementById('loadingHpp');
        const contentHpp = document.getElementById('contentHpp');

        function formatIDR(num) {
            return 'Rp ' + Number(num).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-detail-hpp');
            if (!btn) return;

            const id = btn.dataset.id;
            const type = btn.dataset.type;

            lblNoPesanan.textContent = 'Memuat...';
            loadingHpp.classList.remove('d-none');
            contentHpp.classList.add('d-none');
            tbodyDetailHpp.innerHTML = '';

            modal.show();

            fetch(`{{ route('laporan.detail-hpp') }}?type=${type}&id=${id}`)
                .then(res => {
                    if (!res.ok) throw new Error('Gagal mengambil rincian HPP');
                    return res.json();
                })
                .then(data => {
                    lblNoPesanan.textContent = 'Nomor Transaksi: ' + data.kode;
                    lblTotalBbb.textContent = formatIDR(data.summary.bbb);
                    lblTotalBtkl.textContent = formatIDR(data.summary.btkl);
                    lblTotalBop.textContent = formatIDR(data.summary.bop);
                    lblTotalHpp.textContent = formatIDR(data.summary.total_hpp);

                    let html = '';
                    data.items.forEach(it => {
                        html += `
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark text-start">${it.nama_barang}</div>
                                    <div class="text-muted font-monospace text-start" style="font-size: 11px;">${it.kode_barang}</div>
                                </td>
                                <td class="text-center fw-bold">${Number(it.qty).toLocaleString('id-ID')} ${it.satuan}</td>
                                <td class="text-end">${formatIDR(it.hpp_satuan)}</td>
                                <td class="text-end text-primary">${formatIDR(it.bbb)}</td>
                                <td class="text-end text-warning-emphasis">${formatIDR(it.btkl)}</td>
                                <td class="text-end text-info-emphasis">${formatIDR(it.bop)}</td>
                                <td class="text-end fw-bold text-success">${formatIDR(it.total_hpp)}</td>
                            </tr>
                        `;
                    });

                    tbodyDetailHpp.innerHTML = html;
                    loadingHpp.classList.add('d-none');
                    contentHpp.classList.remove('d-none');
                })
                .catch(err => {
                    lblNoPesanan.textContent = 'Error';
                    tbodyDetailHpp.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-danger fw-semibold py-4">
                                <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>
                                ${err.message}
                            </td>
                        </tr>
                    `;
                    loadingHpp.classList.add('d-none');
                    contentHpp.classList.remove('d-none');
                });
        });
    });
</script>
@endpush
</x-app-layout>