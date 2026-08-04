<x-app-layout>
<div class="container mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold mb-0">Laporan Penjualan POS</h3>
        <div class="d-print-none d-flex gap-2">
            <a href="{{ route('penjualan_pos.laporan', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success">
                📊 Export Excel
            </a>
            <a href="{{ route('penjualan_pos.laporan', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-danger">
                📕 Export PDF
            </a>
        </div>
    </div>

    <div class="card mb-4 d-print-none shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('penjualan_pos.laporan') }}" method="GET">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Dari Tanggal</label>
                        <input type="date" name="tanggal_mulai" class="form-control" value="{{ $tanggal_mulai }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Sampai Tanggal</label>
                        <input type="date" name="tanggal_selesai" class="form-control" value="{{ $tanggal_selesai }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Gudang / Outlet</label>
                        <select name="gudang_id" class="form-select">
                            <option value="">-- Semua Gudang --</option>
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}" {{ $gudang_id == $g->id ? 'selected' : '' }}>
                                    {{ $g->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            🔍 Filter Laporan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white shadow-sm border-0">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-1 opacity-75">Total Omzet (Penjualan)</h6>
                    <h3 class="mb-0 fw-bold">Rp {{ number_format($total_omzet, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white shadow-sm border-0">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-1 opacity-75">Total HPP (Bahan Baku)</h6>
                    <h3 class="mb-0 fw-bold">Rp {{ number_format($total_hpp, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white shadow-sm border-0">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-1 opacity-75">Total Laba Kotor</h6>
                    <h3 class="mb-0 fw-bold">Rp {{ number_format($total_laba, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <span class="fw-bold text-secondary">Daftar Riwayat Transaksi POS</span>
            <span class="float-end text-muted small">Periode: {{ date('d M Y', strtotime($tanggal_mulai)) }} s/d {{ date('d M Y', strtotime($tanggal_selesai)) }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-dark">
                        <tr>
                            <th width="50" class="text-center">No</th>
                            <th>No. Transaksi</th>
                            <th>Tanggal & Waktu</th>
                            <th>Gudang / Outlet</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-end">Total HPP</th>
                            <th class="text-end">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data_penjualan as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td><strong class="text-secondary">{{ $item->kode_transaksi }}</strong></td>
                            <td>{{ date('d-m-Y H:i', strtotime($item->tanggal)) }}</td>
                            
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $item->gudang->nama ?? '-' }}
                                </span>
                            </td>
                            
                            <td class="text-end fw-medium text-primary">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <span>Rp {{ number_format($item->total, 0, ',', '.') }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 btn-detail-harga-jual d-print-none" data-id="{{ $item->id }}" data-type="pos" title="Detail Harga Jual">
                                        <i class="bi bi-info-circle text-primary" style="font-size: 14px;"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="text-end text-muted">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <span>Rp {{ number_format($item->calculated_hpp, 0, ',', '.') }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 btn-detail-hpp d-print-none" data-id="{{ $item->id }}" data-type="pos" title="Detail HPP">
                                        <i class="bi bi-info-circle text-primary" style="font-size: 14px;"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="text-end fw-bold text-success">
                                Rp {{ number_format($item->calculated_laba, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Tidak ada data transaksi POS ditemukan pada periode ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
                                <span class="fw-bold text-dark small">TOTAL HPP TRANSAKSI</span>
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

<!-- MODAL DETAIL HARGA JUAL -->
<div class="modal fade" id="modalDetailHargaJual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-dark text-white border-0 px-4 py-3" style="border-radius: 12px 12px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold">Detail Harga Jual</h5>
                    <p class="text-white-50 mb-0 small" id="lblNoPesananJual"></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- LOADING STATE -->
                <div id="loadingHargaJual" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 small">Memuat detail harga jual...</p>
                </div>

                <!-- DATA STATE -->
                <div id="contentHargaJual" class="d-none">
                    <!-- RINCIAN PER BARANG -->
                    <h6 class="fw-bold text-secondary text-uppercase mb-2 small" style="font-size: 11px; letter-spacing: 0.5px;">Rincian Per Item Barang</h6>
                    <div class="table-responsive bg-white rounded-3 shadow-sm border border-light">
                        <table class="table align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-dark small text-uppercase" style="font-size: 11px;">
                                <tr>
                                    <th>Nama Item</th>
                                    <th class="text-center" width="80">Qty</th>
                                    <th class="text-end" width="140">Harga Jual</th>
                                    <th class="text-end" width="150">Total Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetailHargaJual">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark small">TOTAL OMZET TRANSAKSI</span>
                        <strong class="text-primary fs-5" id="lblTotalOmzetModal">Rp 0</strong>
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

        // ===== MODAL DETAIL HARGA JUAL =====
        const modalHargaJualEl = document.getElementById('modalDetailHargaJual');
        if (!modalHargaJualEl) return;

        const modalHargaJual = new bootstrap.Modal(modalHargaJualEl);
        const lblNoPesananJual = document.getElementById('lblNoPesananJual');
        const lblTotalOmzetModal = document.getElementById('lblTotalOmzetModal');
        const tbodyDetailHargaJual = document.getElementById('tbodyDetailHargaJual');
        const loadingHargaJual = document.getElementById('loadingHargaJual');
        const contentHargaJual = document.getElementById('contentHargaJual');

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-detail-harga-jual');
            if (!btn) return;

            const id = btn.dataset.id;
            const type = btn.dataset.type;

            lblNoPesananJual.textContent = 'Memuat...';
            loadingHargaJual.classList.remove('d-none');
            contentHargaJual.classList.add('d-none');
            tbodyDetailHargaJual.innerHTML = '';

            modalHargaJual.show();

            fetch(`{{ route('laporan.detail-harga-jual') }}?type=${type}&id=${id}`)
                .then(res => {
                    if (!res.ok) throw new Error('Gagal mengambil rincian harga jual');
                    return res.json();
                })
                .then(data => {
                    lblNoPesananJual.textContent = 'Nomor Transaksi: ' + data.kode;
                    lblTotalOmzetModal.textContent = formatIDR(data.summary.total_omzet);

                    let html = '';
                    data.items.forEach(it => {
                        html += `
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark text-start">${it.nama_barang}</div>
                                    <div class="text-muted font-monospace text-start" style="font-size: 11px;">${it.kode_barang}</div>
                                </td>
                                <td class="text-center fw-bold">${Number(it.qty).toLocaleString('id-ID')} ${it.satuan}</td>
                                <td class="text-end">${formatIDR(it.harga)}</td>
                                <td class="text-end fw-bold text-primary">${formatIDR(it.subtotal)}</td>
                            </tr>
                        `;
                    });

                    tbodyDetailHargaJual.innerHTML = html;
                    loadingHargaJual.classList.add('d-none');
                    contentHargaJual.classList.remove('d-none');
                })
                .catch(err => {
                    lblNoPesananJual.textContent = 'Error';
                    tbodyDetailHargaJual.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-danger fw-semibold py-4">
                                <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>
                                ${err.message}
                            </td>
                        </tr>
                    `;
                    loadingHargaJual.classList.add('d-none');
                    contentHargaJual.classList.remove('d-none');
                });
        });
    });
</script>
@endpush

<style>
    /* Mengatur tampilan agar rapi saat dicetak/print ke PDF */
    @media print {
        body { background-color: #fff; }
        .container { width: 100% !important; max-width: 100% !important; margin: 0; padding: 0; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { background-color: transparent !important; font-weight: bold; padding-left: 0; padding-right: 0; }
        .table th { background-color: #333 !important; color: #fff !important; -webkit-print-color-adjust: exact; }
        .table td { border-bottom: 1px solid #ddd; }
    }
</style>
</x-app-layout>