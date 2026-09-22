<x-app-layout>
    <div class="container-fluid px-2 px-md-4 py-3">
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">Buku Pembantu Persediaan</h4>
                <p class="text-muted mb-0" style="font-size: 14px;">Memantau riwayat mutasi masuk, keluar, dan saldo akhir persediaan barang per periode secara detail.</p>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="card border-0 shadow-sm mb-4 rounded-3">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('stok-gudang.buku-pembantu.index') }}" id="formFilter">
                    <div class="row g-3">
                        <!-- Gudang -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Gudang</label>
                            <select name="gudang_id" id="gudangSelect" class="form-select border-2" style="border-radius: 8px;">
                                <option value="">-- Semua Gudang --</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" {{ $gudangId == $g->id ? 'selected' : '' }}>
                                        {{ $g->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Divisi -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Divisi / Depo</label>
                            <select name="divisi_id" id="divisiSelect" class="form-select border-2" style="border-radius: 8px;">
                                <option value="">-- Semua Divisi --</option>
                                @foreach($divisis as $d)
                                    <option value="{{ $d->id }}" data-gudang-id="{{ $d->gudang_id }}" {{ $divisiId == $d->id ? 'selected' : '' }}>
                                        {{ $d->nama }} {{ $d->gudang ? '('.$d->gudang->nama.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Jenis Barang -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Jenis Barang</label>
                            <select name="jenis_barang" id="jenisBarangSelect" class="form-select border-2" style="border-radius: 8px;">
                                <option value="">-- Semua Jenis --</option>
                                <option value="bahan_baku" {{ ($jenisBarang ?? '') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                                <option value="bahan_setengah_jadi" {{ ($jenisBarang ?? '') === 'bahan_setengah_jadi' ? 'selected' : '' }}>Bahan Setengah Jadi</option>
                                <option value="barang_jadi" {{ ($jenisBarang ?? '') === 'barang_jadi' ? 'selected' : '' }}>Barang Jadi</option>
                                <option value="operational" {{ ($jenisBarang ?? '') === 'operational' ? 'selected' : '' }}>Operasional</option>
                            </select>
                        </div>

                        <!-- Kategori Barang -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Kategori</label>
                            <select name="kategori_id" id="kategoriSelect" class="form-select border-2" style="border-radius: 8px;">
                                <option value="">-- Semua Kategori --</option>
                                @foreach($kategoris as $kat)
                                    <option value="{{ $kat->id }}" {{ ($kategoriId ?? '') == $kat->id ? 'selected' : '' }}>
                                        {{ $kat->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Dari Tanggal -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Dari Tanggal</label>
                            <input type="date" name="start_date" class="form-control border-2" style="border-radius: 8px;" value="{{ $startDate }}" required>
                        </div>

                        <!-- Sampai Tanggal -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Sampai Tanggal</label>
                            <input type="date" name="end_date" class="form-control border-2" style="border-radius: 8px;" value="{{ $endDate }}" required>
                        </div>

                        <!-- Search -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-secondary" style="font-size: 13px;">Cari Barang</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control border-2" style="border-radius: 8px 0 0 8px;" placeholder="Cari kode atau nama barang..." value="{{ $search }}">
                                <button type="submit" class="btn text-white px-3 min-hitbox" style="background-color: #DE8958; border: none; border-radius: 0 8px 8px 0;">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3 gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-success fw-semibold px-3 min-hitbox d-inline-flex align-items-center justify-content-center shadow-sm" id="btnSyncAllPage" style="border-radius: 8px;">
                            <i class="bi bi-arrow-repeat me-1"></i> Sinkronkan & Refresh
                        </button>
                        <a href="{{ route('stok-gudang.buku-pembantu.index') }}" class="btn btn-light border fw-semibold px-4 min-hitbox d-inline-flex align-items-center justify-content-center" style="border-radius: 8px;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </a>
                        <button type="submit" class="btn text-white fw-semibold px-4 min-hitbox" style="border-radius: 8px; background-color: #DE8958; border: none;">
                            <i class="bi bi-funnel me-1"></i> Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ITEMS TABLE CARD -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background-color: #715745; color: white;" class="text-uppercase" style="font-size: 12px; letter-spacing: 0.8px;">
                        <tr>
                            <th class="ps-4 py-3 text-white">Kode Barang</th>
                            <th class="py-3 text-white">Nama Barang</th>
                            <th class="py-3 text-white">Satuan & Konversi Beli</th>
                            <th class="py-3 text-white">Jenis Barang</th>
                            <th class="text-end py-3 text-white">Stok Akhir Periodik</th>
                            <th class="text-center py-3 text-white" width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark" style="font-size: 14px;">
                        @forelse($items as $item)
                            @php
                                $hasKonversi = !empty($item->satuan_pembelian) && (float)($item->konversi_pembelian ?? 1) > 1;
                                $konversiFaktor = (float)($item->konversi_pembelian ?? 1);
                                $stokAkhirBeli = $hasKonversi ? ($item->stok_akhir / $konversiFaktor) : null;
                            @endphp
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-primary">{{ $item->kode_barang }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->nama }}</div>
                                    <small class="text-muted" style="font-size: 11px;">Kategori: {{ $item->kategori->nama ?? '-' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis px-2.5 py-1.5" style="font-size: 12px;">{{ $item->satuan }}</span>
                                    @if($hasKonversi)
                                        <div class="mt-1">
                                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;" title="1 {{ $item->satuan_pembelian }} = {{ number_format($konversiFaktor, 0, ',', '.') }} {{ $item->satuan }}">
                                                <i class="bi bi-box-seam me-1 text-primary"></i>1 {{ $item->satuan_pembelian }} = {{ number_format($konversiFaktor, 0, ',', '.') }} {{ $item->satuan }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->is_bahan_baku)
                                        <span class="badge bg-primary-subtle text-primary px-3 py-1.5">Bahan Baku</span>
                                    @elseif($item->is_bahan_setengah_jadi)
                                        <span class="badge bg-info-subtle text-info px-3 py-1.5">Bahan Setengah Jadi</span>
                                    @elseif($item->is_barang_jadi)
                                        <span class="badge bg-success-subtle text-success px-3 py-1.5">Barang Jadi</span>
                                    @elseif($item->is_operational)
                                        <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-1.5">Operational</span>
                                    @else
                                        <span class="badge bg-light text-dark px-3 py-1.5">Umum</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark pe-4">
                                    <div>{{ number_format($item->stok_akhir, 2, ',', '.') }} {{ $item->satuan }}</div>
                                    @if($hasKonversi && $stokAkhirBeli !== null)
                                        <small class="text-primary fw-normal d-block mt-0.5" style="font-size: 12px;">
                                            &asymp; {{ number_format($stokAkhirBeli, 2, ',', '.') }} {{ $item->satuan_pembelian }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-center py-3">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary fw-semibold px-3 btn-detail-mutasi"
                                            style="border-radius: 6px;"
                                            data-barang-id="{{ $item->id }}"
                                            data-barang-nama="{{ $item->nama }}"
                                            data-barang-kode="{{ $item->kode_barang }}"
                                            data-barang-satuan="{{ $item->satuan }}"
                                            data-satuan-pembelian="{{ $item->satuan_pembelian }}"
                                            data-konversi-pembelian="{{ $item->konversi_pembelian }}">
                                        <i class="bi bi-clock-history me-1"></i> Rincian Mutasi
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary" style="opacity: 0.5;"></i>
                                    Tidak ada data barang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- DYNAMIC MUTATION MODAL -->
    <div class="modal fade" id="modalMutasi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
                <div class="modal-header text-white border-0 px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background-color: #715745; border-radius: 14px 14px 0 0;">
                    <div>
                        <h5 class="modal-title fw-bold" id="modalBarangTitle">Rincian Buku Pembantu</h5>
                        <p class="text-white-50 mb-0 font-monospace" style="font-size: 12px;" id="modalBarangSubtitle"></p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-light btn-sm text-white fw-semibold d-inline-flex align-items-center" id="btnRefreshMutasiModal" style="font-size: 11px;">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm text-white fw-semibold d-none d-md-inline-flex align-items-center" onclick="document.getElementById('btnResetPembelianModal').click();" style="font-size: 11px;">
                            <i class="bi bi-trash3 me-1 text-danger-emphasis bg-white rounded-circle p-0.5"></i> Hapus Pembelian
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm text-white fw-semibold d-none d-md-inline-flex align-items-center" onclick="document.getElementById('btnResetPermintaanModal').click();" style="font-size: 11px;">
                            <i class="bi bi-arrow-counterclockwise me-1 text-warning-emphasis bg-white rounded-circle p-0.5"></i> Refresh Permintaan
                        </button>
                        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-4" style="background-color: #f8f9fa;">
                    <!-- Filter Info Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-white border border-2 border-primary-subtle rounded-3 h-100">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Gudang</small>
                                <span class="fw-bold text-dark fs-6" id="infoGudangText">Semua Gudang</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-white border border-2 border-primary-subtle rounded-3 h-100">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Periode</small>
                                <span class="fw-bold text-dark fs-6" id="infoPeriodeText">-</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-white border border-2 border-primary-subtle rounded-3 h-100">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Satuan Stok Dasar</small>
                                <span class="fw-bold text-primary fs-6" id="infoSatuanText">-</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-white border border-2 border-primary-subtle rounded-3 h-100">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Satuan Pembelian</small>
                                <span class="fw-bold text-success fs-6" id="infoSatuanBeliText">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- SPINNER LOADING -->
                    <div id="loadingState" class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted fw-semibold">Memuat transaksi mutasi...</p>
                    </div>

                    <!-- TABLE CONTENT -->
                    <div id="tableState" class="table-responsive d-none bg-white rounded-3 shadow-sm border border-light">
                        <table class="table table-bordered align-middle mb-0" style="min-width: 980px; font-size: 13px;">
                            <thead class="table-light text-secondary text-uppercase fw-bold" style="font-size: 11px;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle" width="100">Tanggal</th>
                                    <th rowspan="2" class="align-middle">Keterangan Sumber</th>
                                    <th colspan="3" class="text-center table-success py-2">Masuk (IN)</th>
                                    <th colspan="3" class="text-center table-danger py-2">Keluar (OUT)</th>
                                    <th colspan="2" class="text-center table-primary py-2">Saldo Persediaan</th>
                                    <th rowspan="2" class="text-center align-middle text-danger" width="60">Aksi</th>
                                </tr>
                                <tr>
                                    <!-- Masuk -->
                                    <th class="text-end table-success py-1.5" width="100">Qty</th>
                                    <th class="text-end table-success py-1.5" width="130">Harga Satuan</th>
                                    <th class="text-end table-success py-1.5" width="120">Total</th>
                                    <!-- Keluar -->
                                    <th class="text-end table-danger py-1.5" width="100">Qty</th>
                                    <th class="text-end table-danger py-1.5" width="130">Harga Satuan</th>
                                    <th class="text-end table-danger py-1.5" width="120">Total</th>
                                    <!-- Saldo -->
                                    <th class="text-end table-primary py-1.5" width="110">Qty</th>
                                    <th class="text-end table-primary py-1.5" width="130">Total Nilai</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyMutasi">
                                <!-- Dynamic Rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 py-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-success btn-sm fw-semibold shadow-sm px-3" id="btnRefreshMutasiModalFooter" style="border-radius: 8px;">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data Mutasi
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold shadow-sm px-3" id="btnResetPembelianModal" style="border-radius: 8px;">
                            <i class="bi bi-trash3-fill me-1"></i> Hapus Semua Pembelian
                        </button>
                        <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-semibold shadow-sm px-3" id="btnResetPermintaanModal" style="border-radius: 8px;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Refresh / Hapus Semua Permintaan
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary fw-semibold px-4" style="border-radius: 8px;" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AJAX LOGIC -->
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('modalMutasi');
            const modal = new bootstrap.Modal(modalEl);
            const modalTitle = document.getElementById('modalBarangTitle');
            const modalSubtitle = document.getElementById('modalBarangSubtitle');
            const infoGudang = document.getElementById('infoGudangText');
            const infoPeriode = document.getElementById('infoPeriodeText');
            const infoSatuan = document.getElementById('infoSatuanText');
            const infoSatuanBeli = document.getElementById('infoSatuanBeliText');

            const loadingState = document.getElementById('loadingState');
            const tableState = document.getElementById('tableState');
            const tbodyMutasi = document.getElementById('tbodyMutasi');

            let activeBarangId = null;
            let activeBarangNama = '';
            let activeBarangKode = '';
            let activeBarangSatuan = '';
            let activeSatBeli = '';
            let activeKonversi = 1;

            // Format Currency
            function formatIDR(num) {
                if (num === null || num === undefined) return 'Rp 0';
                const val = Number(num);
                if (val === 0) return 'Rp 0';
                const maxDecimals = (Math.abs(val) < 1 || (val % 1 !== 0 && Math.abs(val) < 100)) ? 4 : 2;
                return 'Rp ' + val.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: maxDecimals });
            }

            // Format Number Decimal
            function formatNumber(num, maxDec = 2) {
                if (num === null || num === undefined) return '0';
                return Number(num).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: maxDec });
            }

            function escapeHtml(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            function loadMutasiData(showSpinner = true) {
                if (!activeBarangId) return;

                const form = document.getElementById('formFilter');
                const gudangSelect = form.querySelector('[name="gudang_id"]');
                const divisiSelect = form.querySelector('[name="divisi_id"]');
                const startDateInput = form.querySelector('[name="start_date"]');
                const endDateInput = form.querySelector('[name="end_date"]');

                const gudangId = gudangSelect ? gudangSelect.value : '';
                const divisiId = divisiSelect ? divisiSelect.value : '';
                const start_date = startDateInput.value;
                const end_date = endDateInput.value;

                // Update Teks Info Header Modal
                modalTitle.textContent = activeBarangNama;
                modalSubtitle.textContent = 'Kode Barang: ' + activeBarangKode;
                
                let lokasiText = 'Semua Gudang';
                if (gudangSelect && gudangSelect.value) {
                    lokasiText = gudangSelect.options[gudangSelect.selectedIndex].text;
                }
                if (divisiSelect && divisiSelect.value) {
                    const divText = divisiSelect.options[divisiSelect.selectedIndex].text;
                    if (gudangSelect && gudangSelect.value) {
                        lokasiText += ' - ' + divText;
                    } else {
                        lokasiText = divText;
                    }
                }
                infoGudang.textContent = lokasiText;
                
                const formatTgl = (tgl) => {
                    const parts = tgl.split('-');
                    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
                    return tgl;
                };
                infoPeriode.textContent = formatTgl(start_date) + ' s/d ' + formatTgl(end_date);
                infoSatuan.textContent = activeBarangSatuan;

                if (activeSatBeli && activeKonversi > 1) {
                    infoSatuanBeli.innerHTML = `${activeSatBeli} <br><small class="text-muted fw-normal" style="font-size:11px;">(1 ${activeSatBeli} = ${formatNumber(activeKonversi, 0)} ${activeBarangSatuan})</small>`;
                } else {
                    infoSatuanBeli.textContent = activeSatBeli ? activeSatBeli : activeBarangSatuan;
                }

                if (showSpinner) {
                    loadingState.classList.remove('d-none');
                    tableState.classList.add('d-none');
                }

                const url = `{{ route('stok-gudang.buku-pembantu.mutasi') }}?barang_id=${activeBarangId}&gudang_id=${gudangId}&divisi_id=${divisiId}&start_date=${start_date}&end_date=${end_date}`;

                fetch(url)
                    .then(response => {
                        if (!response.ok) throw new Error('Gagal mengambil data mutasi.');
                        return response.json();
                    })
                    .then(data => {
                        loadingState.classList.add('d-none');
                        tableState.classList.remove('d-none');

                        const bInfo = data.barang || {};
                        const satuanDasar = bInfo.satuan || activeBarangSatuan;
                        const satuanBeliRes = bInfo.satuan_pembelian || activeSatBeli;
                        const konvFaktor = parseFloat(bInfo.konversi_pembelian) || activeKonversi;
                        const isConverted = satuanBeliRes && konvFaktor > 1;

                        if (isConverted) {
                            infoSatuanBeli.innerHTML = `${satuanBeliRes} <br><small class="text-muted fw-normal" style="font-size:11px;">(1 ${satuanBeliRes} = ${formatNumber(konvFaktor, 0)} ${satuanDasar})</small>`;
                        }

                        // Helper Qty Cell
                        const renderQtyCell = (qtyVal, qtyBeliVal, colorClass = '') => {
                            let res = `<span class="${colorClass}">${formatNumber(qtyVal)} ${satuanDasar}</span>`;
                            if (isConverted && qtyBeliVal !== undefined && qtyBeliVal !== null) {
                                res += `<div class="text-muted fw-normal" style="font-size: 11px;">&asymp; ${formatNumber(qtyBeliVal, 2)} ${satuanBeliRes}</div>`;
                            }
                            return res;
                        };

                        // Helper Price Cell
                        const renderPriceCell = (priceVal, priceBeliVal, colorClass = '') => {
                            let res = `<span class="${colorClass}">${formatIDR(priceVal)}<span class="text-muted fw-normal" style="font-size: 10px;">/${satuanDasar}</span></span>`;
                            if (isConverted && priceBeliVal !== undefined && priceBeliVal !== null) {
                                res += `<div class="text-primary fw-semibold" style="font-size: 11px;">&asymp; ${formatIDR(priceBeliVal)}<span class="text-muted fw-normal" style="font-size: 10px;">/${satuanBeliRes}</span></div>`;
                            }
                            return res;
                        };

                        // 1. Baris Saldo Awal
                        const saQty = Number(data.saldo_awal.qty);
                        const saQtyBeli = data.saldo_awal.qty_pembelian !== undefined ? Number(data.saldo_awal.qty_pembelian) : (isConverted ? saQty / konvFaktor : null);
                        const saNilai = Number(data.saldo_awal.nilai);

                        let html = `
                            <tr class="table-info fw-semibold">
                                <td class="text-center">—</td>
                                <td><strong>SALDO AWAL PERIODE</strong></td>
                                <!-- Masuk -->
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <!-- Keluar -->
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <!-- Saldo -->
                                <td class="text-end">${renderQtyCell(saQty, saQtyBeli)}</td>
                                <td class="text-end">${formatIDR(saNilai)}</td>
                                <td class="text-center text-muted">—</td>
                            </tr>
                        `;

                        // 2. Baris-Baris Mutasi Berjalan
                        if (data.mutasi && data.mutasi.length > 0) {
                            data.mutasi.forEach(m => {
                                const qty = Number(m.qty);
                                const qtyBeli = m.qty_pembelian !== undefined ? Number(m.qty_pembelian) : (isConverted ? qty / konvFaktor : null);
                                const total = Number(m.total_harga);
                                const sat = Number(m.harga_satuan);
                                const satBeliPrice = m.harga_satuan_pembelian !== undefined ? Number(m.harga_satuan_pembelian) : (isConverted ? sat * konvFaktor : null);
                                const saldoQ = Number(m.saldo_qty);
                                const saldoQBeli = m.saldo_qty_pembelian !== undefined ? Number(m.saldo_qty_pembelian) : (isConverted ? saldoQ / konvFaktor : null);

                                html += `
                                    <tr>
                                        <td class="text-center font-monospace">${m.tanggal_formatted}</td>
                                        <td>${m.keterangan}</td>
                                        
                                        <!-- MASUK -->
                                        <td class="text-end">${m.is_masuk ? renderQtyCell(qty, qtyBeli, 'text-success fw-bold') : '—'}</td>
                                        <td class="text-end">${m.is_masuk ? renderPriceCell(sat, satBeliPrice, 'text-success') : '—'}</td>
                                        <td class="text-end text-success fw-semibold">${m.is_masuk ? formatIDR(total) : '—'}</td>
                                        
                                        <!-- KELUAR -->
                                        <td class="text-end">${!m.is_masuk ? renderQtyCell(qty, qtyBeli, 'text-danger fw-bold') : '—'}</td>
                                        <td class="text-end">${!m.is_masuk ? renderPriceCell(sat, satBeliPrice, 'text-danger') : '—'}</td>
                                        <td class="text-end text-danger fw-semibold">${!m.is_masuk ? formatIDR(total) : '—'}</td>
                                        
                                        <!-- SALDO BERJALAN -->
                                        <td class="text-end fw-bold">${renderQtyCell(saldoQ, saldoQBeli)}</td>
                                        <td class="text-end fw-bold text-primary">${formatIDR(m.saldo_nilai)}</td>

                                        <!-- AKSI HAPUS -->
                                        <td class="text-center">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger p-1 px-2 btn-delete-single-mutasi shadow-sm" 
                                                    data-mutasi-id="${m.id}" 
                                                    data-source-type="${m.source_type || ''}"
                                                    data-keterangan="${escapeHtml(m.keterangan)}"
                                                    title="Hapus mutasi transaksi ini">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                        } else {
                            html += `
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="bi bi-info-circle me-1"></i> Tidak ada transaksi mutasi pada rentang periode ini.
                                    </td>
                                </tr>
                            `;
                        }

                        // 3. Baris Saldo Akhir
                        const sfQty = Number(data.saldo_akhir.qty);
                        const sfQtyBeli = data.saldo_akhir.qty_pembelian !== undefined ? Number(data.saldo_akhir.qty_pembelian) : (isConverted ? sfQty / konvFaktor : null);
                        const sfNilai = Number(data.saldo_akhir.nilai);

                        html += `
                            <tr class="table-primary fw-bold text-dark">
                                <td class="text-center">—</td>
                                <td><strong>SALDO AKHIR PERIODE</strong></td>
                                <!-- Masuk -->
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <!-- Keluar -->
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <!-- Saldo -->
                                <td class="text-end">${renderQtyCell(sfQty, sfQtyBeli, 'fw-bold')}</td>
                                <td class="text-end text-primary">${formatIDR(sfNilai)}</td>
                                <td class="text-center text-muted">—</td>
                            </tr>
                        `;

                        tbodyMutasi.innerHTML = html;

                        // Pasang Event Listener Hapus untuk Setiap Baris Mutasi
                        tbodyMutasi.querySelectorAll('.btn-delete-single-mutasi').forEach(delBtn => {
                            delBtn.addEventListener('click', function() {
                                const mutasiId = this.dataset.mutasiId;
                                const sourceType = (this.dataset.sourceType || '').toLowerCase();
                                const ket = this.dataset.keterangan || 'Transaksi ini';

                                const isBeli = sourceType.includes('pembelian') || ket.toLowerCase().includes('pembelian');
                                const confirmMsg = isBeli ? "yakin untuk menghapus pembelian ?" : `Apakah Anda yakin ingin menghapus mutasi transaksi "${ket}"?`;

                                if (!confirm(confirmMsg)) return;

                                const originalBtnHtml = this.innerHTML;
                                this.disabled = true;
                                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                                fetch("{{ route('stok-gudang.buku-pembantu.delete-mutasi') }}", {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        mutasi_id: mutasiId,
                                        barang_id: activeBarangId
                                    })
                                })
                                .then(res => res.json())
                                .then(resData => {
                                    if (resData.success) {
                                        alert(resData.message || 'Transaksi berhasil dihapus.');
                                        loadMutasiData(false);
                                    } else {
                                        this.disabled = false;
                                        this.innerHTML = originalBtnHtml;
                                        alert('Gagal: ' + (resData.message || 'Terjadi kesalahan sistem.'));
                                    }
                                })
                                .catch(err => {
                                    this.disabled = false;
                                    this.innerHTML = originalBtnHtml;
                                    alert('Terjadi kesalahan jaringan: ' + err.message);
                                });
                            });
                        });
                    })
                    .catch(err => {
                        loadingState.classList.add('d-none');
                        tbodyMutasi.innerHTML = `
                            <tr>
                                <td colspan="11" class="text-center py-4 text-danger fw-semibold">
                                    <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
                                    ${err.message}
                                </td>
                            </tr>
                        `;
                    });
            }

            // BUKA MODAL DETAIL MUTASI
            document.querySelectorAll('.btn-detail-mutasi').forEach(btn => {
                btn.addEventListener('click', function () {
                    activeBarangId = this.dataset.barangId;
                    activeBarangNama = this.dataset.barangNama;
                    activeBarangKode = this.dataset.barangKode;
                    activeBarangSatuan = this.dataset.barangSatuan;
                    activeSatBeli = this.dataset.satuanPembelian || '';
                    activeKonversi = parseFloat(this.dataset.konversiPembelian) || 1;

                    modal.show();
                    loadMutasiData(true);
                });
            });

            // REFRESH DATA MUTASI DALAM MODAL
            const btnRefreshMutasi = document.getElementById('btnRefreshMutasiModal');
            const btnRefreshMutasiFooter = document.getElementById('btnRefreshMutasiModalFooter');

            const handleModalRefresh = function() {
                if (!activeBarangId) return;
                const form = document.getElementById('formFilter');
                const gudangSelect = form.querySelector('[name="gudang_id"]');
                const divisiSelect = form.querySelector('[name="divisi_id"]');
                const gudangId = gudangSelect ? gudangSelect.value : '';
                const divisiId = divisiSelect ? divisiSelect.value : '';

                const btn = this;
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sinkronisasi...';

                fetch("{{ route('stok-gudang.buku-pembantu.sync-refresh') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        barang_id: activeBarangId,
                        gudang_id: gudangId,
                        divisi_id: divisiId
                    })
                })
                .then(res => res.json())
                .then(resData => {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    loadMutasiData(false);
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    loadMutasiData(false);
                });
            };

            if (btnRefreshMutasi) btnRefreshMutasi.addEventListener('click', handleModalRefresh);
            if (btnRefreshMutasiFooter) btnRefreshMutasiFooter.addEventListener('click', handleModalRefresh);

            // SINKRONKAN & REFRESH SELURUH BUKU PEMBANTU (HALAMAN UTAMA)
            const btnSyncAllPage = document.getElementById('btnSyncAllPage');
            if (btnSyncAllPage) {
                btnSyncAllPage.addEventListener('click', function() {
                    const orig = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyinkronkan...';

                    fetch("{{ route('stok-gudang.buku-pembantu.sync-refresh') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({})
                    })
                    .then(res => res.json())
                    .then(resData => {
                        this.disabled = false;
                        this.innerHTML = orig;
                        if (resData.success) {
                            alert(resData.message || 'Buku pembantu persediaan berhasil disinkronkan.');
                            window.location.reload();
                        } else {
                            alert('Gagal: ' + (resData.message || 'Terjadi kesalahan.'));
                        }
                    })
                    .catch(err => {
                        this.disabled = false;
                        this.innerHTML = orig;
                        alert('Terjadi kesalahan jaringan: ' + err.message);
                    });
                });
            }

            // RESET PEMBELIAN UNTUK ITEM AKTIF
            const btnResetPembelian = document.getElementById('btnResetPembelianModal');
            if (btnResetPembelian) {
                btnResetPembelian.addEventListener('click', function() {
                    if (!activeBarangId) return;

                    const msg = `Apakah Anda YAKIN ingin MENGHAPUS SEMUA transaksi PEMBELIAN untuk item:\n\n"${activeBarangNama}"?\n\nIni akan:\n- Menghapus batch pembelian item ini\n- Mengembalikan / mengurangi penambahan stok gudang terkait\n- Menghapus transaksi penerimaan & mutasi stok pembelian\n- Menghitung ulang HPP FIFO\n\nTindakan ini berguna untuk mengulang pembelian dengan harga / kuantitas yang benar sejak awal. Lanjutkan?`;

                    if (!confirm(msg)) return;

                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menghapus...';

                    fetch("{{ route('stok-gudang.buku-pembantu.reset-pembelian') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ barang_id: activeBarangId })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.disabled = false;
                        this.innerHTML = originalText;

                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert('Gagal: ' + (data.message || 'Terjadi kesalahan sistem.'));
                        }
                    })
                    .catch(err => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                        alert('Terjadi kesalahan jaringan: ' + err.message);
                    });
                });
            }

            // RESET PERMINTAAN / PENGELUARAN UNTUK ITEM AKTIF
            const btnResetPermintaan = document.getElementById('btnResetPermintaanModal');
            if (btnResetPermintaan) {
                btnResetPermintaan.addEventListener('click', function() {
                    if (!activeBarangId) return;

                    const msg = `Apakah Anda YAKIN ingin MENGHAPUS / REFRESH SEMUA transaksi PERMINTAAN & PENGELUARAN untuk item:\n\n"${activeBarangNama}"?\n\nIni akan:\n- Mengembalikan kuantitas stok yang keluar ke gudang asal (Gudang Utama)\n- Mengembalikan sisa batch FIFO yang terpakai\n- Menghapus mutasi pengeluaran barang ini\n- Menghitung ulang HPP FIFO\n\nTindakan ini berguna untuk mereset mutasi pengeluaran/permintaan yang salah. Lanjutkan?`;

                    if (!confirm(msg)) return;

                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

                    fetch("{{ route('stok-gudang.buku-pembantu.reset-permintaan') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ barang_id: activeBarangId })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.disabled = false;
                        this.innerHTML = originalText;

                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert('Gagal: ' + (data.message || 'Terjadi kesalahan sistem.'));
                        }
                    })
                    .catch(err => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                        alert('Terjadi kesalahan jaringan: ' + err.message);
                    });
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
