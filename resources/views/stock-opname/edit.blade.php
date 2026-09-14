<x-app-layout>

<x-slot name="header">
    Edit Stock Opname
</x-slot>

<div class="container-fluid">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                Edit Stock Opname Gudang
            </h4>
            <p class="text-muted mb-0">
                Ubah penyesuaian stok fisik untuk dokumen <span class="fw-bold text-dark font-monospace">{{ $opname->kode_opname }}</span>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('stock-opname.show', $opname->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-eye me-1"></i> Lihat Detail
            </a>
            <a href="{{ route('stock-opname.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <form id="formOpname" method="POST" action="{{ route('stock-opname.update', $opname->id) }}">
        @csrf
        @method('PUT')

        <input type="hidden" id="gudang_id" name="gudang_id" value="{{ $gudang->id }}">
        <input type="hidden" id="divisi_id" name="divisi_id" value="{{ $divisiId ?? '' }}">
        <input type="hidden" id="items_json" name="items_json" value="">

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <small class="text-muted">Gudang & Divisi</small>
                        <h5 class="fw-bold mb-0 text-dark">
                            {{ $gudang->nama }}
                        </h5>
                        @if($divisi)
                            <span class="badge bg-light text-primary border border-primary-subtle mt-1 fs-6">
                                <i class="bi bi-diagram-3 me-1"></i>{{ $divisi->nama }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <label for="tanggal" class="small text-muted fw-bold mb-1">
                            Tanggal Opname
                        </label>
                        <input
                            type="date"
                            id="tanggal"
                            name="tanggal"
                            class="form-control form-control-sm fw-bold"
                            value="{{ old('tanggal', date('Y-m-d', strtotime($opname->tanggal))) }}"
                            required>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <small class="text-muted">Status</small>
                        <h5 class="fw-bold text-warning mb-0">
                            Draft (Dalam Edit)
                        </h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <small class="text-muted">Total Item</small>
                        <h5 class="fw-bold mb-0" id="totalItem">
                            0
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        {{-- DETAIL OPNAME --}}
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2"
                 style="background:#7A4517;">
                <span><i class="bi bi-box-seam me-1"></i> Penyesuaian Fisik Barang</span>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    {{-- Filter Kategori --}}
                    <select id="filterKategoriInput" class="form-select form-select-sm bg-white text-dark" style="width: 170px; border-radius: 6px;" onchange="applyFilters()">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($kategoris as $kat)
                            <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                        @endforeach
                    </select>

                    {{-- Filter Jenis Barang --}}
                    <select id="filterJenisInput" class="form-select form-select-sm bg-white text-dark" style="width: 155px; border-radius: 6px;" onchange="applyFilters()">
                        <option value="">-- Semua Jenis --</option>
                        <option value="bahan_baku">Bahan Baku</option>
                        <option value="bahan_setengah_jadi">Bahan Setengah Jadi</option>
                        <option value="barang_jadi">Barang Jadi</option>
                        <option value="operational">Operational</option>
                    </select>

                    {{-- Filter Status Stok --}}
                    <select id="filterStokInput" class="form-select form-select-sm bg-white text-dark" style="width: 165px; border-radius: 6px;" onchange="applyFilters()">
                        <option value="">-- Semua Stok --</option>
                        <option value="ada_stok">Ada Stok Saja (> 0)</option>
                        <option value="tanpa_stok">Stok Kosong (0)</option>
                        <option value="minus">Stok Minus (< 0)</option>
                    </select>

                    {{-- Search Keyword --}}
                    <input type="text" 
                           id="searchBarangInput" 
                           class="form-control form-control-sm bg-white text-dark" 
                           placeholder="Cari kode / nama..." 
                           style="width: 170px; border-radius: 6px;"
                           oninput="applyFilters()">

                    {{-- Button Sync Stok --}}
                    <button type="button" 
                            class="btn btn-sm btn-light text-dark fw-semibold" 
                            onclick="syncStokSistemTerkini()" 
                            title="Perbarui nilai stok sistem dengan stok gudang saat ini tanpa menghapus stok fisik yang sudah diinput">
                        <i class="bi bi-arrow-clockwise me-1 text-primary"></i> Sinkronkan Stok
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="tableBarang">
                        <thead>
                            <tr style="background:#7A4517;color:white">
                                <th width="100">Kode</th>
                                <th>Nama Barang</th>
                                <th width="120">Satuan</th>
                                <th width="150">Stok Sistem</th>
                                <th width="160">Stok Fisik</th>
                                <th width="140">Selisih</th>
                                <th width="170">Nilai Selisih</th>
                            </tr>
                        </thead>

                        <tbody id="tbodyBarang">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Memuat data barang...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PAGINATION BAR --}}
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap py-2 px-3 border-top" id="paginationFooter">
                <div class="text-muted small" id="paginationInfo">
                    Menampilkan 0 dari 0 barang
                </div>
                <nav aria-label="Navigasi Halaman Barang">
                    <ul class="pagination pagination-sm mb-0" id="paginationNav"></ul>
                </nav>
            </div>
        </div>

        {{-- KETERANGAN --}}
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body">
                <label class="form-label fw-semibold">
                    Keterangan
                </label>
                <textarea
                    name="keterangan"
                    rows="3"
                    class="form-control"
                    placeholder="Catatan perubahan stock opname...">{{ old('keterangan', $opname->keterangan) }}</textarea>
            </div>
        </div>

        {{-- TOTAL & SUBMIT --}}
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-1 text-muted">
                            Total Selisih Nilai Persediaan
                        </h6>
                        <h3 class="fw-bold text-danger mb-0" id="grandTotal">
                            Rp 0
                        </h3>
                    </div>

                    <div class="col-md-6 text-end">
                        <a href="{{ route('stock-opname.show', $opname->id) }}" class="btn btn-outline-secondary me-2 px-4">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-warning text-dark fw-bold px-5">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan Stock Opname
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
let rawItems = [];
let filteredItems = [];
let currentPage = 1;
const rowsPerPage = 20;
let userValues = {}; 
const savedDetails = @json($existingDetails);

function loadBarang() {
    let gudangId = document.getElementById('gudang_id').value;
    let divisiId = document.getElementById('divisi_id').value;

    let tbody = document.getElementById('tbodyBarang');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                Memuat data barang dari gudang...
            </td>
        </tr>
    `;

    fetch("{{ route('stock-opname.load-barang') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            gudang_id: gudangId,
            divisi_id: divisiId || null
        })
    })
    .then(response => response.json())
    .then(data => {
        rawItems = data || [];
        document.getElementById('totalItem').innerText = rawItems.length;

        // Inisialisasi data: dahulukan data yang sudah tersimpan pada draft
        rawItems.forEach(item => {
            let saved = savedDetails[item.id];
            let currentStok = parseFloat(item.stok || 0);

            if (saved) {
                let fis = parseFloat(saved.stok_fisik);
                let sist = (saved.stok_sistem !== undefined && saved.stok_sistem !== null)
                    ? parseFloat(saved.stok_sistem)
                    : currentStok;
                let sel = fis - sist;
                userValues[item.id] = {
                    stok_fisik: fis,
                    stok_sistem: sist,
                    selisih: sel,
                    nilai: parseFloat(saved.nilai) || 0,
                    harga_fifo: parseFloat(item.harga_fifo || 0)
                };
            } else {
                userValues[item.id] = {
                    stok_fisik: currentStok,
                    stok_sistem: currentStok,
                    selisih: 0,
                    nilai: 0,
                    harga_fifo: parseFloat(item.harga_fifo || 0)
                };
            }
        });

        filteredItems = [...rawItems];
        currentPage = 1;
        renderPagination();
        hitungGrandTotal();

        checkLocalStorageCache();
    })
    .catch(function(error) {
        console.error(error);
        alert('Gagal memuat data barang');
    });
}

const storageKey = 'so_cache_edit_{{ $opname->id }}';

function saveCache() {
    try {
        localStorage.setItem(storageKey, JSON.stringify(userValues));
    } catch(e) {}
}

function clearCache() {
    try {
        localStorage.removeItem(storageKey);
    } catch(e) {}
}

function checkLocalStorageCache() {
    try {
        let cached = localStorage.getItem(storageKey);
        if (!cached) return;

        let parsed = JSON.parse(cached);
        let hasDifference = false;

        Object.keys(parsed).forEach(bId => {
            if (userValues[bId] && parsed[bId].stok_fisik !== undefined) {
                if (parseFloat(parsed[bId].stok_fisik) !== parseFloat(userValues[bId].stok_fisik)) {
                    hasDifference = true;
                }
            }
        });

        if (hasDifference) {
            let banner = document.getElementById('restoreBanner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'restoreBanner';
                banner.className = 'alert alert-info alert-dismissible fade show d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 shadow-sm';
                banner.innerHTML = `
                    <div>
                        <i class="bi bi-clock-history text-primary me-2 fs-5 align-middle"></i>
                        <strong>Ditemukan data input sementara di browser Anda dari sesi sebelumnya.</strong>
                        <span class="d-block small text-muted">Apakah Anda ingin memulihkan angka perhitungan fisik yang belum sempat tersimpan?</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="restoreFromCache()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Pulihkan Input Saya
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="discardCache()">
                            Abaikan & Hapus Cache
                        </button>
                    </div>
                `;
                let form = document.getElementById('formOpname');
                if (form) {
                    form.insertBefore(banner, form.firstChild);
                }
            }
        }
    } catch(e) {
        console.error(e);
    }
}

function restoreFromCache() {
    try {
        let cached = localStorage.getItem(storageKey);
        if (!cached) return;

        let parsed = JSON.parse(cached);
        Object.keys(parsed).forEach(bId => {
            if (userValues[bId] && parsed[bId].stok_fisik !== undefined) {
                userValues[bId].stok_fisik = parseFloat(parsed[bId].stok_fisik);
                userValues[bId].selisih = userValues[bId].stok_fisik - userValues[bId].stok_sistem;
                userValues[bId].nilai = parseFloat(parsed[bId].nilai || 0);
            }
        });

        renderPagination();
        hitungGrandTotal();

        let banner = document.getElementById('restoreBanner');
        if (banner) banner.remove();

        alert('Data input perhitungan fisik berhasil dipulihkan ke tabel!');
    } catch(e) {
        console.error(e);
    }
}

function discardCache() {
    clearCache();
    let banner = document.getElementById('restoreBanner');
    if (banner) banner.remove();
}

function applyFilters() {
    let kw = (document.getElementById('searchBarangInput')?.value || '').toLowerCase().trim();
    let kategoriId = document.getElementById('filterKategoriInput')?.value || '';
    let jenis = document.getElementById('filterJenisInput')?.value || '';
    let stokStatus = document.getElementById('filterStokInput')?.value || '';

    filteredItems = rawItems.filter(item => {
        if (kw) {
            let kode = (item.kode_barang || '').toLowerCase();
            let nama = (item.nama || '').toLowerCase();
            if (!kode.includes(kw) && !nama.includes(kw)) {
                return false;
            }
        }

        if (kategoriId && String(item.kategori_id) !== String(kategoriId)) {
            return false;
        }

        if (jenis) {
            if (jenis === 'bahan_baku' && !item.is_bahan_baku) return false;
            if (jenis === 'bahan_setengah_jadi' && !item.is_bahan_setengah_jadi) return false;
            if (jenis === 'barang_jadi' && !item.is_barang_jadi) return false;
            if (jenis === 'operational' && !item.is_operational) return false;
        }

        if (stokStatus) {
            let uv = userValues[item.id];
            let stok = uv ? uv.stok_sistem : parseFloat(item.stok || 0);
            if (stokStatus === 'ada_stok' && stok <= 0) return false;
            if (stokStatus === 'tanpa_stok' && stok !== 0) return false;
            if (stokStatus === 'minus' && stok >= 0) return false;
        }

        return true;
    });

    currentPage = 1;
    renderPagination();
}

function changePage(page) {
    let totalPages = Math.ceil(filteredItems.length / rowsPerPage) || 1;
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentPage = page;
    renderPagination();
}

function renderPagination() {
    let tbody = document.getElementById('tbodyBarang');
    tbody.innerHTML = '';

    let totalItems = filteredItems.length;
    let totalPages = Math.ceil(totalItems / rowsPerPage) || 1;

    if (totalItems === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    Tidak ada barang yang sesuai dengan filter pencarian / kategori / jenis.
                </td>
            </tr>
        `;
        document.getElementById('paginationInfo').innerText = `Menampilkan 0 dari 0 barang`;
        document.getElementById('paginationNav').innerHTML = '';
        renderHiddenInputsContainer();
        return;
    }

    let startIndex = (currentPage - 1) * rowsPerPage;
    let endIndex = Math.min(startIndex + rowsPerPage, totalItems);
    let pageItems = filteredItems.slice(startIndex, endIndex);

    pageItems.forEach((item) => {
        let uv = userValues[item.id] || { 
            stok_fisik: parseFloat(item.stok || 0), 
            stok_sistem: parseFloat(item.stok || 0), 
            selisih: 0, 
            nilai: 0 
        };
        let stokSistem = uv.stok_sistem !== undefined ? uv.stok_sistem : parseFloat(item.stok || 0);
        let selisih = uv.stok_fisik - stokSistem;
        let konversi = parseFloat(item.konversi_pembelian || 1);
        let hasKonversi = item.satuan_pembelian && konversi > 1;

        let selisihClass = 'text-secondary';
        if (selisih > 0) selisihClass = 'text-success fw-bold';
        else if (selisih < 0) selisihClass = 'text-danger fw-bold';

        let satuanHtml = `
            <div>${item.satuan || 'pcs'}</div>
            ${hasKonversi ? `<small class="text-primary d-block font-monospace" style="font-size:0.72rem;">1 ${item.satuan_pembelian} = ${konversi.toLocaleString('id-ID')} ${item.satuan}</small>` : ''}
        `;

        let stokColor = stokSistem > 0 ? 'text-success fw-bold' : (stokSistem < 0 ? 'text-danger fw-bold' : 'text-muted');
        let stokSistemHtml = `
            <div class="${stokColor}">${stokSistem.toLocaleString('id-ID')} <span class="small">${item.satuan || 'pcs'}</span></div>
            ${hasKonversi ? `<div class="small text-primary mt-1" style="font-size:0.75rem;"><i class="bi bi-arrow-repeat me-1"></i>${(stokSistem / konversi).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${item.satuan_pembelian}</div>` : ''}
        `;

        let konvFisikHtml = hasKonversi
            ? `<span id="konv_fisik_${item.id}" class="small text-primary d-block mt-1" style="font-size:0.75rem;"><i class="bi bi-arrow-repeat me-1"></i>${(uv.stok_fisik / konversi).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${item.satuan_pembelian}</span>`
            : '';

        let konvSelisihHtml = hasKonversi
            ? `<div id="konv_selisih_${item.id}" class="small ${selisih > 0 ? 'text-success' : (selisih < 0 ? 'text-danger' : 'text-muted')}" style="font-size:0.75rem;">(${(selisih / konversi).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${item.satuan_pembelian})</div>`
            : '';

        let badgeJenis = '';
        if (item.is_bahan_setengah_jadi) {
            badgeJenis = '<span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:10px;">Setengah Jadi</span>';
        } else if (item.is_barang_jadi) {
            badgeJenis = '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:10px;">Barang Jadi</span>';
        } else if (item.is_operational) {
            badgeJenis = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1" style="font-size:10px;">Operasional</span>';
        }

        let badgeStok = '';
        if (stokSistem > 0) {
            badgeStok = '<span class="badge bg-success text-white ms-1" style="font-size:9.5px; padding: 2px 5px;"><i class="bi bi-check-circle-fill me-0.5"></i>Ada Stok</span>';
        } else if (stokSistem < 0) {
            badgeStok = '<span class="badge bg-danger text-white ms-1" style="font-size:9.5px; padding: 2px 5px;"><i class="bi bi-exclamation-circle-fill me-0.5"></i>Stok Minus</span>';
        }

        let kategoriHtml = item.kategori_nama 
            ? `<div class="text-muted small" style="font-size:11px;"><i class="bi bi-tag me-1"></i>${item.kategori_nama} ${badgeJenis} ${badgeStok}</div>` 
            : `${badgeJenis} ${badgeStok}`;

        let rowHighlight = stokSistem > 0 ? 'style="background-color: #f8fafc;"' : (stokSistem < 0 ? 'style="background-color: #fff7ed;"' : '');

        tbody.innerHTML += `
            <tr data-barang-id="${item.id}" ${rowHighlight}>
                <td class="fw-semibold text-muted font-monospace small">
                    ${item.kode_barang}
                </td>
                <td class="fw-semibold">
                    <div>${item.nama}</div>
                    ${kategoriHtml}
                </td>
                <td class="text-muted">
                    ${satuanHtml}
                </td>
                <td class="fw-semibold">
                    ${stokSistemHtml}
                </td>
                <td>
                    <input
                        type="number"
                        step="0.01"
                        class="form-control form-control-sm stok-fisik fw-bold text-center"
                        data-barang-id="${item.id}"
                        data-stok="${stokSistem}"
                        data-konversi="${konversi}"
                        data-satuan-beli="${item.satuan_pembelian || ''}"
                        value="${uv.stok_fisik}">
                    ${konvFisikHtml}
                </td>
                <td>
                    <span class="selisih ${selisihClass}" id="selisih_${item.id}">
                        ${selisih > 0 ? '+' : ''}${selisih.toLocaleString('id-ID')}
                    </span>
                    ${konvSelisihHtml}
                </td>
                <td>
                    <span class="nilai fw-bold" id="nilai_${item.id}">
                        Rp ${uv.nilai.toLocaleString('id-ID')}
                    </span>
                </td>
            </tr>
        `;
    });

    document.getElementById('paginationInfo').innerText = `Menampilkan ${startIndex + 1} - ${endIndex} dari ${totalItems} barang (Halaman ${currentPage} dari ${totalPages})`;

    let navHtml = '';
    navHtml += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(1)" title="Halaman Pertama">&laquo;</a>
        </li>
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage - 1})">Prev</a>
        </li>
    `;

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let p = startPage; p <= endPage; p++) {
        navHtml += `
            <li class="page-item ${p === currentPage ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="changePage(${p})">${p}</a>
            </li>
        `;
    }

    navHtml += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage + 1})">Next</a>
        </li>
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(${totalPages})" title="Halaman Terakhir">&raquo;</a>
        </li>
    `;

    document.getElementById('paginationNav').innerHTML = navHtml;
    renderHiddenInputsContainer();
}

function renderHiddenInputsContainer() {
    // Digantikan dengan items_json pada submit event untuk menghindari batasan PHP max_input_vars (1000 variabel)
}

document.addEventListener('DOMContentLoaded', function(){
    loadBarang();

    const formOpname = document.getElementById('formOpname');
    if (formOpname) {
        formOpname.addEventListener('submit', function(e) {
            if (rawItems.length === 0) {
                e.preventDefault();
                alert('Data barang belum selesai dimuat atau kosong.');
                return;
            }

            let submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan Perubahan...';
            }

            let itemsData = rawItems.map(item => {
                let uv = userValues[item.id] || { 
                    stok_fisik: parseFloat(item.stok || 0), 
                    stok_sistem: parseFloat(item.stok || 0) 
                };
                let stokSistem = uv.stok_sistem !== undefined ? uv.stok_sistem : parseFloat(item.stok || 0);
                let stokFisik = parseFloat(uv.stok_fisik);
                if (isNaN(stokFisik)) stokFisik = 0;

                return {
                    barang_id: item.id,
                    stok_sistem: stokSistem,
                    stok_fisik: stokFisik
                };
            });

            document.getElementById('items_json').value = JSON.stringify(itemsData);
            clearCache();
        });
    }
});

document.addEventListener('input', function(e){
    if(!e.target.classList.contains('stok-fisik')){
        return;
    }

    let barangId = e.target.dataset.barangId;
    let stokSistem = parseFloat(e.target.dataset.stok) || 0;
    let konversi = parseFloat(e.target.dataset.konversi) || 1;
    let satuanBeli = e.target.dataset.satuanBeli || '';
    let stokFisik = parseFloat(e.target.value);
    if (isNaN(stokFisik)) stokFisik = 0;

    let selisih = stokFisik - stokSistem;

    if (!userValues[barangId]) {
        userValues[barangId] = {};
    }
    userValues[barangId].stok_fisik = stokFisik;
    userValues[barangId].stok_sistem = stokSistem;
    userValues[barangId].selisih = selisih;

    saveCache();

    let konvFisikEl = document.getElementById(`konv_fisik_${barangId}`);
    if (konvFisikEl && satuanBeli && konversi > 1) {
        konvFisikEl.innerHTML = `<i class="bi bi-arrow-repeat me-1"></i>${(stokFisik / konversi).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${satuanBeli}`;
    }

    let konvSelisihEl = document.getElementById(`konv_selisih_${barangId}`);
    if (konvSelisihEl && satuanBeli && konversi > 1) {
        konvSelisihEl.innerHTML = `(${(selisih / konversi).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})} ${satuanBeli})`;
        konvSelisihEl.className = `small ${selisih > 0 ? 'text-success' : (selisih < 0 ? 'text-danger' : 'text-muted')}`;
    }

    let hiddenInput = document.getElementById(`hidden_fisik_${barangId}`);
    if (hiddenInput) {
        hiddenInput.value = stokFisik;
    }

    let gudangId = document.getElementById('gudang_id').value;
    let divisiId = document.getElementById('divisi_id').value;

    fetch("{{ route('stock-opname.hitung-fifo') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            gudang_id: gudangId,
            divisi_id: divisiId || null,
            barang_id: barangId,
            selisih: Math.abs(selisih)
        })
    })
    .then(res => res.json())
    .then(result => {
        let nilai = parseFloat(result.nilai || 0);
        userValues[barangId].nilai = nilai;

        let nilaiEl = document.getElementById(`nilai_${barangId}`);
        if (nilaiEl) {
            nilaiEl.innerHTML = 'Rp ' + nilai.toLocaleString('id-ID');
        }
        hitungGrandTotal();
    })
    .catch(err => {
        console.error(err);
    });

    let selisihElement = document.getElementById(`selisih_${barangId}`);
    if (selisihElement) {
        selisihElement.innerHTML = (selisih > 0 ? '+' : '') + selisih.toLocaleString('id-ID');
        if(selisih > 0) {
            selisihElement.className = 'selisih text-success fw-bold';
        } else if(selisih < 0) {
            selisihElement.className = 'selisih text-danger fw-bold';
        } else {
            selisihElement.className = 'selisih text-secondary';
        }
    }

    hitungGrandTotal();
});

function hitungGrandTotal() {
    let total = 0;
    Object.values(userValues).forEach(uv => {
        total += (uv.nilai || 0);
    });

    let grandTotalEl = document.getElementById('grandTotal');
    if (grandTotalEl) {
        grandTotalEl.innerHTML = 'Rp ' + total.toLocaleString('id-ID');
    }
}

function syncStokSistemTerkini() {
    if (!confirm('Perbarui nilai stok sistem untuk seluruh barang dengan data gudang terkini? Jumlah stok fisik yang sudah Anda ubah akan tetap dipertahankan.')) {
        return;
    }

    let gudangId = document.getElementById('gudang_id').value;
    let divisiId = document.getElementById('divisi_id').value;

    rawItems.forEach(item => {
        let currentStok = parseFloat(item.stok || 0);
        if (userValues[item.id]) {
            userValues[item.id].stok_sistem = currentStok;
            let selisih = userValues[item.id].stok_fisik - currentStok;
            userValues[item.id].selisih = selisih;

            fetch("{{ route('stock-opname.hitung-fifo') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    gudang_id: gudangId,
                    divisi_id: divisiId || null,
                    barang_id: item.id,
                    selisih: Math.abs(selisih)
                })
            })
            .then(res => res.json())
            .then(result => {
                userValues[item.id].nilai = parseFloat(result.nilai || 0);
                let nilaiEl = document.getElementById(`nilai_${item.id}`);
                if (nilaiEl) {
                    nilaiEl.innerHTML = 'Rp ' + userValues[item.id].nilai.toLocaleString('id-ID');
                }
                hitungGrandTotal();
            });
        }
    });

    renderPagination();
    hitungGrandTotal();
    alert('Nilai stok sistem telah diperbarui dengan data gudang terkini.');
}
</script>

</x-app-layout>
