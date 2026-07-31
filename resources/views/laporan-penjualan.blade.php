<x-app-layout>
<div class="container py-4">
    {{-- JUDUL HALAMAN --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Laporan Penjualan B2B</h4>
            <p class="text-muted small mb-0">Pantau ringkasan omzet dan performa penjualan berkala.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
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
            <div class="card shadow-sm border-0 p-3 bg-white border-start border-info border-4">
                <div class="small text-muted fw-bold text-uppercase">Jumlah Pelanggan</div>
                <div class="fs-3 fw-bold text-info-emphasis mt-1">{{ $jumlah_customer }} <span class="fs-6 text-muted fw-normal">Pelanggan</span></div>
            </div>
        </div>
    </div>

    {{-- TABEL DETAIL TRANSAKSI --}}
    @php
        // $pesanans yang dikirim controller sudah difilter hanya status 'selesai',
        // di sini tinggal dikelompokkan berdasarkan customer.
        $groupedPesanans = $pesanans->groupBy(function ($p) {
            return $p->customer->nama ?? 'N/A';
        })->sortKeys();
    @endphp
    <div class="card shadow-sm border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-stars me-2 text-primary"></i>Daftar Transaksi Selesai per Customer</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-light text-dark border py-2 px-3 small shadow-sm">Total: {{ $pesanans->count() }} Pesanan</span>
                <button type="button" id="btnToggleAll" class="btn btn-sm btn-outline-secondary" data-state="collapsed">
                    <i class="bi bi-arrows-expand me-1"></i> Buka Semua
                </button>
            </div>
        </div>

        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="inputCariTransaksi" class="form-control" placeholder="Cari kode pesanan atau nama pelanggan...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle border text-center" id="tabelLaporan">
                <thead class="table-light text-uppercase small font-monospace">
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th class="text-end">Total HPP</th>
                        <th class="text-end pe-4">Total Omzet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedPesanans as $namaCustomer => $items)
                        <tr class="table-secondary customer-group-header" role="button" data-group="group-{{ $loop->index }}" data-customer-name="{{ strtolower($namaCustomer) }}">
                            <td colspan="2" class="text-start fw-bold text-dark">
                                <i class="bi bi-chevron-right chevron-icon me-2 text-muted" style="font-size: 11px; transition: transform .15s ease;"></i>
                                <i class="bi bi-person-circle me-1 text-primary"></i>{{ $namaCustomer }}
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary ms-2">{{ $items->count() }} Transaksi</span>
                            </td>
                            <td class="text-end text-muted small">
                                Rp {{ number_format($items->sum('total_hpp'), 0, ',', '.') }}
                            </td>
                            <td class="text-end pe-4 fw-bold text-primary">
                                Rp {{ number_format($items->sum('details_sum_subtotal'), 0, ',', '.') }}
                            </td>
                        </tr>
                        @foreach($items as $p)
                            <tr class="customer-group-row group-{{ $loop->parent->index }} d-none" data-kode="{{ strtolower($p->kode_pesanan) }}">
                                <td class="fw-bold text-dark">{{ $p->kode_pesanan }}</td>
                                <td>{{ date('d M Y', strtotime($p->tanggal)) }}</td>
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
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted py-5 text-center">
                                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data penjualan selesai ditemukan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div id="noSearchResult" class="text-muted py-5 text-center d-none">
                <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                Tidak ada transaksi yang cocok dengan pencarian.
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
        // ===== Toggle buka/tutup per grup customer =====
        const tabel = document.getElementById('tabelLaporan');
        if (tabel) {
            tabel.querySelectorAll('.customer-group-header').forEach(function (header) {
                header.addEventListener('click', function () {
                    const groupId = header.dataset.group;
                    const chevron = header.querySelector('.chevron-icon');
                    const rows = tabel.querySelectorAll('.' + groupId);
                    const isOpen = rows.length && !rows[0].classList.contains('d-none');

                    rows.forEach(function (row) {
                        row.classList.toggle('d-none', isOpen);
                    });
                    if (chevron) {
                        chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
                    }
                });
            });

            // ===== Tombol Buka Semua / Tutup Semua =====
            const btnToggleAll = document.getElementById('btnToggleAll');
            if (btnToggleAll) {
                btnToggleAll.addEventListener('click', function () {
                    const shouldOpen = btnToggleAll.dataset.state === 'collapsed';
                    tabel.querySelectorAll('.customer-group-row').forEach(function (row) {
                        row.classList.toggle('d-none', !shouldOpen);
                    });
                    tabel.querySelectorAll('.chevron-icon').forEach(function (chevron) {
                        chevron.style.transform = shouldOpen ? 'rotate(90deg)' : 'rotate(0deg)';
                    });
                    btnToggleAll.dataset.state = shouldOpen ? 'expanded' : 'collapsed';
                    btnToggleAll.innerHTML = shouldOpen
                        ? '<i class="bi bi-arrows-collapse me-1"></i> Tutup Semua'
                        : '<i class="bi bi-arrows-expand me-1"></i> Buka Semua';
                });
            }

            // ===== Pencarian cepat (kode pesanan / nama pelanggan) =====
            const inputCari = document.getElementById('inputCariTransaksi');
            const noResult = document.getElementById('noSearchResult');
            if (inputCari) {
                inputCari.addEventListener('input', function () {
                    const keyword = inputCari.value.trim().toLowerCase();
                    const headers = tabel.querySelectorAll('.customer-group-header');
                    let adaHasil = false;

                    if (keyword === '') {
                        // Kosongkan pencarian: tampilkan semua header, tutup semua grup lagi
                        headers.forEach(function (header) {
                            header.classList.remove('d-none');
                            const groupId = header.dataset.group;
                            tabel.querySelectorAll('.' + groupId).forEach(function (row) {
                                row.classList.add('d-none');
                            });
                            const chevron = header.querySelector('.chevron-icon');
                            if (chevron) chevron.style.transform = 'rotate(0deg)';
                        });
                        tabel.style.display = '';
                        noResult.classList.add('d-none');
                        return;
                    }

                    headers.forEach(function (header) {
                        const groupId = header.dataset.group;
                        const customerCocok = header.dataset.customerName.includes(keyword);
                        const groupRows = tabel.querySelectorAll('.' + groupId);
                        let groupPunyaBarisCocok = false;

                        groupRows.forEach(function (row) {
                            const cocok = customerCocok || row.dataset.kode.includes(keyword);
                            row.classList.toggle('d-none', !cocok);
                            if (cocok) groupPunyaBarisCocok = true;
                        });

                        const tampilkanHeader = customerCocok || groupPunyaBarisCocok;
                        header.classList.toggle('d-none', !tampilkanHeader);
                        if (tampilkanHeader) adaHasil = true;

                        // buka otomatis grup yang cocok saat mencari
                        if (customerCocok) {
                            groupRows.forEach(function (row) { row.classList.remove('d-none'); });
                        }
                        const chevron = header.querySelector('.chevron-icon');
                        if (chevron && tampilkanHeader) chevron.style.transform = 'rotate(90deg)';
                    });

                    tabel.style.display = adaHasil ? '' : 'none';
                    noResult.classList.toggle('d-none', adaHasil);
                });
            }
        }

        // ===== Modal Detail HPP =====
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