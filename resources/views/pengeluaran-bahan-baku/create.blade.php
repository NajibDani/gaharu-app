<x-app-layout>

<x-slot name="header">Permintaan / Transfer Bahan Baku</x-slot>

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    /* ===== TomSelect Overrides ===== */
    .ts-wrapper .ts-control {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        padding: 0.5rem 0.8rem;
        font-size: 0.95rem;
        background: #fff;
        min-height: 42px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .ts-wrapper.focus .ts-control {
        border-color: #d88656;
        box-shadow: 0 0 0 3px rgba(216,134,86,0.15);
    }
    .ts-dropdown {
        border-radius: 10px;
        border: 1px solid #e5d3c0;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
        z-index: 999999 !important;
    }
    .ts-dropdown .option {
        padding: 0.65rem 0.9rem;
        font-size: 0.9rem;
        border-bottom: 1px solid #f8f6f3;
    }
    .ts-dropdown .option.active,
    .ts-dropdown .option:hover {
        background-color: #f7f3ee;
        color: #7A4517;
    }
    .ts-dropdown .ts-dropdown-content {
        max-height: 350px;
        overflow-y: auto;
    }

    /* ===== Overflow Fix for Table and Card ===== */
    .card-detail-wrapper {
        overflow: visible !important;
    }
    .table-container-visible {
        overflow: visible !important;
    }

    /* ===== Form Card & Header ===== */
    .form-card {
        border: 1px solid #ede6df;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        background: #fff;
        overflow: visible !important;
    }
    .form-card .card-header-custom {
        background: #7A4517;
        color: white;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* ===== Table Detail Styling ===== */
    #table-detail {
        margin-bottom: 0;
        overflow: visible !important;
    }
    #table-detail thead th {
        background: #7A4517;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.75rem 1rem;
        border: none;
        vertical-align: middle;
    }
    #table-detail tbody td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f2ece6;
    }
    #table-detail tbody tr:last-child td {
        border-bottom: none;
    }
</style>
@endpush

<div class="container-fluid px-4 py-3">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#3d1f0a;">
                @if(($jenis ?? '') === 'wasted')
                    <i class="bi bi-trash3-fill text-danger me-2"></i>Tambah Pengeluaran Wasted / Busuk / Rusak
                @else
                    Tambah Pengeluaran Bahan Baku
                @endif
            </h4>
            <p class="text-muted mb-0" style="font-size:0.85rem;">
                @if(($jenis ?? '') === 'wasted')
                    Pengeluaran stok yang terbuang, busuk, atau rusak langsung dari gudang lokasi tanpa transfer.
                @else
                    Buat permintaan pengeluaran bahan baku dari Gudang Utama ke gudang tujuan.
                @endif
            </p>
        </div>
        <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if($barang->count() == 0)
    <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span><strong>Stok tidak tersedia.</strong> Silakan lakukan penerimaan/pembelian barang terlebih dahulu.</span>
    </div>
    @endif

    <form method="POST" action="{{ route('pengeluaran-bahan-baku.store') }}" id="formPengeluaran">
        @csrf
        <input type="hidden" name="jenis_pengeluaran" value="{{ $jenis ?? 'transfer' }}">

        {{-- INFORMASI PENGELUARAN --}}
        <div class="form-card mb-4">
            <div class="card-header-custom" style="{{ ($jenis ?? '') === 'wasted' ? 'background: #dc3545;' : '' }}">
                <i class="bi bi-box-seam me-2"></i> Informasi Pengeluaran {{ ($jenis ?? '') === 'wasted' ? 'Wasted / Busuk' : '' }}
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            {{ ($jenis ?? '') === 'wasted' ? 'Gudang Asal (Lokasi Bahan Wasted)' : 'Gudang Tujuan' }} <span class="text-danger">*</span>
                        </label>
                        <select name="gudang_id" id="select-gudang"
                            class="form-select @error('gudang_id') is-invalid @enderror"
                            style="border-radius:8px;"
                            required>
                            <option value="">-- Pilih {{ ($jenis ?? '') === 'wasted' ? 'Gudang Lokasi Wasted' : 'Gudang Tujuan' }} --</option>
                            @foreach($gudang as $g)
                            <option value="{{ $g->id }}"
                                data-kategori="{{ strtolower($g->kategori) }}"
                                {{ (old('gudang_id', $selectedGudangId ?? '') == $g->id) ? 'selected' : '' }}>
                                {{ $g->nama }} ({{ $g->kategori }})
                            </option>
                            @endforeach
                        </select>
                        @error('gudang_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                            @if(($jenis ?? '') === 'wasted')
                                Stok barang akan dikurangi langsung dari gudang lokasi ini saat approved.
                            @else
                                Bahan baku akan dipindahkan dari Gudang Utama ke gudang tujuan.
                            @endif
                        </small>
                    </div>

                    <div class="col-md-6" id="divisi-wrapper" style="display: none;">
                        <label class="form-label fw-bold small text-dark mb-1">
                            <i class="bi bi-diagram-3-fill text-primary me-1"></i> Divisi Tujuan <span class="text-danger">*</span>
                        </label>
                        <select name="divisi_id" id="select-divisi"
                            class="form-select @error('divisi_id') is-invalid @enderror"
                            style="border-radius:8px;">
                            <option value="">-- Pilih Divisi --</option>
                        </select>
                        @error('divisi_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                            Pilih divisi operasional penerima bahan baku (Kitchen / Barista / Server / dll).
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- SARAN RESTOCK (CONDITIONAL) --}}
        <div id="suggestion-box" class="card p-3 mb-4 bg-light border-warning shadow-sm" style="display: none; border-left: 5px solid #f59e0b !important; border-radius: 12px;">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <strong class="text-dark small d-flex align-items-center">
                        <i class="bi bi-lightbulb-fill text-warning fs-6 me-2"></i>
                        Saran Restock Bahan Baku (<span id="suggest-gudang-name"></span>)
                    </strong>
                    <span class="text-muted small" style="font-size: 0.75rem;">Bahan baku di bawah batas minimum stock gudang outlet tujuan</span>
                </div>
                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm" id="btn-apply-all-suggestions">
                    <i class="bi bi-plus-circle-fill me-1"></i> Gunakan Semua Saran Restock
                </button>
            </div>
            <div id="suggestion-list" class="d-flex flex-wrap gap-2 pt-1">
                <!-- Dynamic suggestion pills -->
            </div>
        </div>

        {{-- DETAIL BAHAN BAKU --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 card-detail-wrapper">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-white border-bottom rounded-top-4">
                    <h5 class="fw-bold mb-0 text-dark">Detail Bahan Baku</h5>
                    <button type="button" onclick="tambahBaris()" class="btn btn-sm px-3 fw-bold"
                        style="background:#f7f3ee; border:1px solid #d88656; color:#9c4f18; border-radius:8px;"
                        {{ $barang->count() == 0 ? 'disabled' : '' }}>
                        <i class="bi bi-plus-circle me-1"></i> Tambah Barang
                    </button>
                </div>

                <div class="table-container-visible">
                    <table class="table align-middle mb-0" id="table-detail">
                        <thead>
                            <tr>
                                <th>Barang (Ketik Nama / Kode untuk Mencari)</th>
                                <th width="160">Qty Input</th>
                                <th width="170">Satuan Input</th>
                                <th width="200">Total Qty (Utama)</th>
                                <th width="80" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="barang_id[]" class="form-select barang-select" required
                                        {{ $barang->count() == 0 ? 'disabled' : '' }}>
                                        <option value="">-- Pilih / Cari Bahan Baku --</option>
                                        @foreach($barang as $b)
                                        <option value="{{ $b->id }}" data-stok="{{ $b->stok }}"
                                            data-kode="{{ $b->kode_barang }}" data-satuan="{{ $b->satuan }}"
                                            data-satuan-pembelian="{{ $b->satuan_pembelian }}"
                                            data-konversi-pembelian="{{ $b->konversi_pembelian }}">
                                            {{ $b->nama }}
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control qty-input-user"
                                        min="0.01" step="any" placeholder="Qty"
                                        style="border-radius:8px;" required>
                                </td>
                                <td>
                                    <select class="form-select satuan-select" style="border-radius:8px;">
                                        <option value="utama">Utama</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="number" name="qty[]" class="form-control qty-input fw-bold"
                                            min="0.01" step="any" placeholder="Total Qty"
                                            style="border-radius:8px; background:#f8fafc;" readonly required>
                                        <span class="stok-satuan text-muted fw-semibold small" style="min-width:35px;"></span>
                                    </div>
                                    <small class="text-muted stok-info d-block mt-1" style="font-size:0.75rem;"></small>
                                    <small class="text-danger stok-warning d-block mt-1" style="display:none; font-size:0.75rem;"></small>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-sm px-3 fw-semibold" style="border-radius:8px;" onclick="hapusBaris(this)">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KETERANGAN --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <label class="form-label fw-bold small text-dark mb-1">Keterangan</label>
                <textarea name="keterangan" rows="3" class="form-control"
                    placeholder="Contoh: Pengeluaran bahan baku untuk produksi / restock outlet"
                    style="border-radius:8px;"></textarea>
            </div>
        </div>

        <div class="p-3 rounded-3 mb-4" style="background:#fff8e8; border:1px solid #f2d28c; color:#7a5a00; font-size:0.85rem;">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Stok belum berpindah saat data disimpan. Pengurangan stok FIFO baru dilakukan setelah pengeluaran disetujui.
        </div>

        <div>
            <button id="btnSimpan" type="submit" class="btn fw-bold px-4 py-2 text-white"
                style="background:#7A4517; border-radius:10px;"
                {{ $barang->count() == 0 ? 'disabled' : '' }}>
                <i class="bi bi-save me-2"></i> Simpan Pengeluaran
            </button>
        </div>

    </form>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// =========================================================
// Data barang untuk TomSelect (Searchable)
// =========================================================
const barangData = [
    @foreach($barang as $b)
    {
        value: "{{ $b->id }}",
        text: "{{ addslashes($b->kode_barang . ' - ' . $b->nama . ' (' . $b->satuan . ')' . ($b->satuan_pembelian && $b->konversi_pembelian > 1 ? ' [' . $b->satuan_pembelian . ']' : '') . ' - Stok Utama: ' . $b->stok . ($b->stok <= 0 ? ' [HABIS]' : '')) }}",
        nama: "{{ addslashes($b->nama) }}",
        kode: "{{ $b->kode_barang }}",
        satuan: "{{ $b->satuan }}",
        satuan_pembelian: "{{ $b->satuan_pembelian ?: $b->satuan }}",
        konversi_pembelian: {{ (float)($b->konversi_pembelian ?: 1) }},
        stok: {{ (float)$b->stok }},
        habis: {{ $b->stok <= 0 ? 'true' : 'false' }}
    },
    @endforeach
];

function initTomSelect(selectEl) {
    if (!selectEl || selectEl.tomselect) return selectEl.tomselect;

    while (selectEl.options.length > 1) {
        selectEl.remove(1);
    }

    return new TomSelect(selectEl, {
        options: barangData,
        valueField: 'value',
        labelField: 'text',
        searchField: ['text', 'nama', 'kode'],
        create: false,
        allowEmptyOption: true,
        placeholder: '-- Pilih / Cari Bahan Baku --',
        maxOptions: 500,
        dropdownParent: 'body',
        render: {
            option: function(data, escape) {
                let badge = data.habis 
                    ? '<span class="badge bg-danger ms-2">Habis</span>'
                    : `<span class="badge bg-light text-dark border ms-2">Stok: ${escape(data.stok)} ${escape(data.satuan)}</span>`;
                return `<div class="d-flex justify-content-between align-items-center py-1">
                    <div>
                        <span class="fw-bold">${escape(data.kode)}</span> - ${escape(data.nama)}
                        <span class="text-muted small">(${escape(data.satuan)})</span>
                    </div>
                    ${badge}
                </div>`;
            },
            item: function(data, escape) {
                return `<div>${escape(data.kode)} - ${escape(data.nama)} (${escape(data.satuan)})</div>`;
            }
        },
        onChange: function(value) {
            let row = selectEl.closest('tr');
            if (row) updateRowInfo(row, value);
        }
    });
}

function calculateRowQty(row) {
    let select = row.querySelector('.barang-select');
    let userInput = row.querySelector('.qty-input-user');
    let satuanSelect = row.querySelector('.satuan-select');
    let qtyMainInput = row.querySelector('.qty-input');
    if (!select || !userInput || !satuanSelect || !qtyMainInput) return;

    let barangId = select.tomselect ? select.tomselect.getValue() : select.value;
    let found = barangData.find(b => b.value == barangId);
    let valUser = parseFloat(userInput.value) || 0;

    if (!found || valUser <= 0) {
        qtyMainInput.value = '';
        checkStok(row);
        return;
    }

    let multiplier = 1;
    if (satuanSelect.value === 'pembelian') {
        multiplier = found.konversi_pembelian || 1;
    }

    let totalQty = valUser * multiplier;
    qtyMainInput.value = totalQty;
    checkStok(row);
}

function updateRowInfo(row, barangId) {
    let found = barangData.find(b => b.value == barangId);
    let satuanSelect = row.querySelector('.satuan-select');
    let satuanEl = row.querySelector('.stok-satuan');
    let infoEl = row.querySelector('.stok-info');
    let warnEl = row.querySelector('.stok-warning');

    if (found) {
        if (satuanSelect) {
            let opts = `<option value="utama">${found.satuan}</option>`;
            if (found.satuan_pembelian && found.konversi_pembelian > 1 && found.satuan_pembelian !== found.satuan) {
                opts += `<option value="pembelian">${found.satuan_pembelian} (${found.konversi_pembelian} ${found.satuan})</option>`;
            }
            satuanSelect.innerHTML = opts;
        }
        if (satuanEl) satuanEl.textContent = found.satuan;
        if (infoEl) {
            infoEl.textContent = `Tersedia di Gudang Utama: ${found.stok} ${found.satuan}`;
            infoEl.style.color = found.habis ? '#dc3545' : '#6c757d';
        }
    } else {
        if (satuanSelect) satuanSelect.innerHTML = '<option value="utama">Utama</option>';
        if (satuanEl) satuanEl.textContent = '';
        if (infoEl) infoEl.textContent = '';
    }
    if (warnEl) warnEl.style.display = 'none';
    calculateRowQty(row);
}

function checkStok(row) {
    let select = row.querySelector('.barang-select');
    let qtyInput = row.querySelector('.qty-input');
    let warning = row.querySelector('.stok-warning');
    if (!select || !qtyInput || !warning) return;

    let barangId = select.tomselect ? select.tomselect.getValue() : select.value;
    let found = barangData.find(b => b.value == barangId);
    let qty = parseFloat(qtyInput.value) || 0;

    if (found && qty > 0) {
        if (qty > found.stok) {
            warning.innerHTML = `⚠️ Melebihi stok Gudang Utama! (Tersedia: <strong>${found.stok} ${found.satuan}</strong>)`;
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    } else {
        warning.style.display = 'none';
    }
}

function buatRowHtml(barangId = '', qty = '') {
    return `
    <tr>
        <td>
            <select name="barang_id[]" class="form-select barang-select" required>
                <option value="">-- Pilih / Cari Bahan Baku --</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control qty-input-user"
                min="0.01" step="any" placeholder="Qty"
                style="border-radius:8px;" required>
        </td>
        <td>
            <select class="form-select satuan-select" style="border-radius:8px;">
                <option value="utama">Utama</option>
            </select>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <input type="number" name="qty[]" class="form-control qty-input fw-bold"
                    min="0.01" step="any" placeholder="Total Qty"
                    style="border-radius:8px; background:#f8fafc;" value="${qty}" readonly required>
                <span class="stok-satuan text-muted fw-semibold small" style="min-width:35px;"></span>
            </div>
            <small class="text-muted stok-info d-block mt-1" style="font-size:0.75rem;"></small>
            <small class="text-danger stok-warning d-block mt-1" style="display:none; font-size:0.75rem;"></small>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm px-3 fw-semibold" style="border-radius:8px;" onclick="hapusBaris(this)">
                Hapus
            </button>
        </td>
    </tr>`;
}

function tambahBaris(barangId = '', qty = '', unit = 'utama') {
    let tbody = document.querySelector('#table-detail tbody');
    let rows = tbody.querySelectorAll('tr');

    // Coba isi baris pertama jika masih kosong
    if (barangId !== '') {
        for (let row of rows) {
            let sel = row.querySelector('.barang-select');
            let selValue = sel.tomselect ? sel.tomselect.getValue() : sel.value;
            if (!selValue) {
                if (sel.tomselect) sel.tomselect.setValue(barangId);
                else sel.value = barangId;
                updateRowInfo(row, barangId);
                let qInput = row.querySelector('.qty-input-user');
                if (qty !== '') qInput.value = qty;
                let sSelect = row.querySelector('.satuan-select');
                if (sSelect && unit) sSelect.value = unit;
                calculateRowQty(row);
                return row;
            }
        }
    }

    // Tambah baris baru
    tbody.insertAdjacentHTML('beforeend', buatRowHtml('', qty));
    let newRow = tbody.lastElementChild;
    let newSelect = newRow.querySelector('.barang-select');
    let ts = initTomSelect(newSelect);

    if (barangId && ts) {
        setTimeout(() => {
            ts.setValue(barangId);
            updateRowInfo(newRow, barangId);
            if (qty) {
                newRow.querySelector('.qty-input-user').value = qty;
                let sSelect = newRow.querySelector('.satuan-select');
                if (sSelect && unit) sSelect.value = unit;
                calculateRowQty(newRow);
            }
        }, 50);
    }
    return newRow;
}

function hapusBaris(button) {
    let row = button.closest('tr');
    let rows = document.querySelectorAll('#table-detail tbody tr');
    let select = row.querySelector('.barang-select');

    if (rows.length > 1) {
        if (select && select.tomselect) select.tomselect.destroy();
        row.remove();
    } else {
        if (select && select.tomselect) select.tomselect.clear();
        row.querySelector('.qty-input').value = '';
        let satuanEl = row.querySelector('.stok-satuan');
        let infoEl = row.querySelector('.stok-info');
        if (satuanEl) satuanEl.textContent = '';
        if (infoEl) infoEl.textContent = '';
        row.querySelector('.stok-warning').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Init TomSelect pada baris awal
    document.querySelectorAll('.barang-select').forEach(function (el) {
        initTomSelect(el);
    });

    // Input qty / ganti satuan → hitung ulang total qty utama & cek stok
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('qty-input-user')) {
            calculateRowQty(e.target.closest('tr'));
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('satuan-select')) {
            calculateRowQty(e.target.closest('tr'));
        }
    });

    // ===== Gudang & Divisi =====
    const selectGudang  = document.getElementById('select-gudang');
    const divisiWrapper = document.getElementById('divisi-wrapper');
    const selectDivisi  = document.getElementById('select-divisi');
    const oldDivisiId   = "{{ old('divisi_id') }}";

    // ===== Suggestion Box =====
    const suggestionBox     = document.getElementById('suggestion-box');
    const suggestionList    = document.getElementById('suggestion-list');
    const suggestionGudang  = document.getElementById('suggest-gudang-name');
    const btnApplyAll       = document.getElementById('btn-apply-all-suggestions');
    let currentSuggestions  = [];

    function fetchDivisi(gudangId, selectedId = null) {
        if (!gudangId) {
            divisiWrapper.style.display = 'none';
            selectDivisi.innerHTML = '<option value="">-- Pilih Divisi --</option>';
            selectDivisi.required = false;
            return;
        }
        fetch('/gudangs/' + gudangId + '/divisi')
            .then(r => r.json())
            .then(data => {
                if (data.is_operasional && data.divisi && data.divisi.length > 0) {
                    divisiWrapper.style.display = 'block';
                    selectDivisi.required = true;
                    let opts = '<option value="">-- Pilih Divisi Tujuan --</option>';
                    data.divisi.forEach(d => {
                        let sel = (selectedId && selectedId == d.id) ? 'selected' : '';
                        opts += `<option value="${d.id}" ${sel}>${d.nama}</option>`;
                    });
                    selectDivisi.innerHTML = opts;
                } else {
                    divisiWrapper.style.display = 'none';
                    selectDivisi.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                    selectDivisi.required = false;
                }
            })
            .catch(() => {
                divisiWrapper.style.display = 'none';
                selectDivisi.required = false;
            });
    }

    function fetchSuggestions(gudangId, divisiId = null) {
        if (!gudangId) {
            suggestionBox.style.display = 'none';
            currentSuggestions = [];
            return;
        }
        let url = "{{ route('pengeluaran-bahan-baku.suggestions') }}?gudang_id=" + encodeURIComponent(gudangId);
        if (divisiId) {
            url += "&divisi_id=" + encodeURIComponent(divisiId);
        }
        fetch(url)
            .then(r => r.json())
            .then(data => {
                currentSuggestions = data.suggestions || [];
                let labelGudang = data.gudang_name || '';
                if (data.divisi_name) {
                    labelGudang += ' - Divisi ' + data.divisi_name;
                }
                suggestionGudang.innerText = labelGudang;
                if (currentSuggestions.length > 0) {
                    suggestionBox.style.display = 'block';
                    suggestionList.innerHTML = '';
                    currentSuggestions.forEach(item => {
                        const pill = document.createElement('div');
                        pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded-3';
                        
                        let displaySaran = item.has_konversi 
                            ? `${item.suggested_qty_input} ${item.satuan_pembelian} (${item.suggested_qty} ${item.satuan})`
                            : `${item.suggested_qty} ${item.satuan}`;

                        pill.innerHTML = `
                            <div class="text-start">
                                <div class="fw-bold">${item.nama} <span class="text-muted small">(${item.kode_barang})</span></div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    Stok Outlet: <span class="text-danger fw-bold">${item.current_stock}</span> / Min: <span class="fw-bold">${item.min_stock}</span> ${item.satuan}
                                    <span class="text-secondary ms-1">(Utama: ${item.stok_utama} ${item.satuan})</span>
                                    <span class="text-success fw-bold ms-1">&rarr; Saran: ${displaySaran}</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold btn-add-single-suggest py-1 px-2" style="font-size: 0.75rem;" title="Tambah item ini">
                                <i class="bi bi-plus-circle-fill"></i> Tambah
                            </button>
                        `;
                        pill.querySelector('.btn-add-single-suggest').addEventListener('click', function () {
                            let qtyForm = item.has_konversi ? item.suggested_qty_input : item.suggested_qty;
                            let unitForm = item.has_konversi ? 'pembelian' : 'utama';
                            tambahBaris(item.barang_id, qtyForm, unitForm);
                            pill.classList.remove('bg-white');
                            pill.classList.add('bg-warning-subtle');
                            this.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                            this.disabled = true;
                        });
                        suggestionList.appendChild(pill);
                    });
                } else {
                    suggestionBox.style.display = 'none';
                }
            })
            .catch(() => { suggestionBox.style.display = 'none'; });
    }

    function applyAllSuggestions() {
        const tbody = document.querySelector('#table-detail tbody');
        const rows = [...tbody.querySelectorAll('tr')];
        rows.forEach((r, i) => {
            if (i > 0) {
                let s = r.querySelector('.barang-select');
                if (s && s.tomselect) s.tomselect.destroy();
                r.remove();
            }
        });
        let firstSel = tbody.querySelector('.barang-select');
        if (firstSel && firstSel.tomselect) firstSel.tomselect.clear();
        else if (firstSel) firstSel.value = '';
        let firstQty = tbody.querySelector('.qty-input-user');
        if (firstQty) firstQty.value = '';

        currentSuggestions.forEach(item => {
            let qtyForm = item.has_konversi ? item.suggested_qty_input : item.suggested_qty;
            let unitForm = item.has_konversi ? 'pembelian' : 'utama';
            tambahBaris(item.barang_id, qtyForm, unitForm);
        });

        suggestionList.querySelectorAll('.btn-add-single-suggest').forEach(btn => {
            btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
            btn.disabled = true;
        });
    }

    if (selectGudang) {
        selectGudang.addEventListener('change', function () {
            fetchDivisi(this.value);
            fetchSuggestions(this.value, selectDivisi ? selectDivisi.value : null);
        });
        if (selectGudang.value) {
            fetchDivisi(selectGudang.value, oldDivisiId);
            fetchSuggestions(selectGudang.value, oldDivisiId);
        }
    }

    if (selectDivisi) {
        selectDivisi.addEventListener('change', function () {
            fetchSuggestions(selectGudang ? selectGudang.value : null, this.value);
        });
    }

    if (btnApplyAll) {
        btnApplyAll.addEventListener('click', applyAllSuggestions);
    }
});
</script>
@endpush

</x-app-layout>
