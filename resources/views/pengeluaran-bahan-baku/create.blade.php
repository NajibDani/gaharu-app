<x-app-layout>

<x-slot name="header">
    Pengeluaran Bahan Baku
</x-slot>

<div class="page-header mb-4">

    <div class="d-flex justify-content-between align-items-center">

        <div>

            <h1 class="page-header-title">
                Tambah Pengeluaran Bahan Baku
            </h1>

            <p class="text-muted mb-0">
                Buat permintaan pengeluaran bahan baku dari Gudang Utama ke gudang tujuan.
            </p>

        </div>

        <a href="{{ route('pengeluaran-bahan-baku.index') }}"
           class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>
            Kembali

        </a>

    </div>

</div>

@if($barang->count() == 0)

<div class="alert alert-danger">

    <strong>
        Stok bahan baku tidak tersedia.
    </strong>

    Silakan lakukan pembelian terlebih dahulu.

</div>

@endif

<div class="card">

    <div
        class="card-header text-white fw-bold"
        style="
            background:#9c4f18;
            border-radius:24px 24px 0 0;
        ">

        <i class="bi bi-box-seam me-2"></i>

        Informasi Pengeluaran

    </div>

    <div class="card-body p-4">

        <form
            method="POST"
            action="{{ route('pengeluaran-bahan-baku.store') }}">

            @csrf

            <div class="mb-4">

                <label class="form-label fw-bold">
                    Gudang Tujuan
                </label>

                <select
                    name="gudang_id"
                    id="select-gudang"
                    class="form-select"
                    required>

                    <option value="">
                        -- Pilih Gudang Tujuan --
                    </option>

                    @foreach($gudang as $g)

                    <option value="{{ $g->id }}" {{ (old('gudang_id', $selectedGudangId ?? '') == $g->id) ? 'selected' : '' }}>

                        {{ $g->nama }}
                        -
                        {{ $g->kategori }}

                    </option>

                    @endforeach

                </select>

                <small class="text-muted">

                    Bahan baku akan dipindahkan dari Gudang Utama
                    ke gudang tujuan yang dipilih.

                </small>

            </div>

            {{-- SUGGESTION RESTOCK BOX --}}
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

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="fw-bold mb-0">

                    Detail Bahan Baku

                </h5>

                <button
                    type="button"
                    onclick="tambahBaris()"
                    class="btn btn-sm"
                    style="
                        background:#f7f3ee;
                        border:1px solid #d88656;
                        color:#9c4f18;
                        border-radius:10px;
                    "
                    {{ $barang->count() == 0 ? 'disabled' : '' }}>

                    <i class="bi bi-plus-circle"></i>

                    Tambah Barang

                </button>

            </div>

            <div class="table-responsive">

                <table
                    class="table align-middle"
                    id="table-detail">

                    <thead
                        style="
                            background:#5a3416;
                            color:white;
                        ">

                        <tr>

                            <th>Barang</th>

                            <th width="200">
                                Qty Keluar
                            </th>

                            <th width="120">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td>

                                <select
                                    name="barang_id[]"
                                    class="form-select barang-select"
                                    required
                                    {{ $barang->count() == 0 ? 'disabled' : '' }}>

                                    <option value="">
                                        -- Pilih Bahan Baku --
                                    </option>

                                    @foreach($barang as $b)

                                    <option
                                        value="{{ $b->id }}"
                                        data-stok="{{ $b->stok }}">

                                        {{ $b->kode_barang }}
                                        -
                                        {{ $b->nama }}
                                        ({{ $b->satuan }})

                                        @if($b->stok <= 0)
                                        - STOK HABIS
                                        @endif

                                    </option>

                                    @endforeach

                                </select>

                            </td>

                            <td>

                                <input
                                    type="number"
                                    name="qty[]"
                                    class="form-control qty-input"
                                    min="0.01"
                                    step="any"
                                    placeholder="Qty"
                                    required>
                                <small class="text-danger stok-warning d-block mt-1" style="display:none;"></small>

                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm"
                                    onclick="hapusBaris(this)">

                                    Hapus

                                </button>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <div class="mt-4">

                <label class="form-label fw-bold">

                    Keterangan

                </label>

                <textarea
                    name="keterangan"
                    rows="4"
                    class="form-control"
                    placeholder="Contoh: Pengeluaran bahan baku untuk produksi / restock outlet"></textarea>

            </div>

            <div
                class="p-3 rounded mt-4"
                style="
                    background:#fff8e8;
                    border:1px solid #f2d28c;
                    color:#7a5a00;
                ">

                <i class="bi bi-exclamation-triangle me-2"></i>

                Stok belum berpindah saat data disimpan.

                Pengurangan stok FIFO baru dilakukan
                setelah pengeluaran disetujui.

            </div>

            <div class="mt-4">

                <button
                    id="btnSimpan"
                    type="submit"
                    class="btn"
                    style="
                        background:#d88656;
                        color:white;
                        font-weight:600;
                        padding:12px 24px;
                        border-radius:12px;
                    "
                    {{ $barang->count() == 0 ? 'disabled' : '' }}>

                    <i class="bi bi-save me-2"></i>

                    Simpan Pengeluaran

                </button>

            </div>

        </form>

    </div>

</div>

<script>

function tambahBaris(barangId = '', qty = '')
{
    let tbody =
        document.querySelector(
            '#table-detail tbody'
        );

    let rows = tbody.querySelectorAll('tr');
    if (rows.length === 1 && barangId !== '') {
        let firstSelect = rows[0].querySelector('.barang-select');
        let firstQty = rows[0].querySelector('.qty-input');
        if (!firstSelect.value && !firstQty.value) {
            firstSelect.value = barangId;
            if (qty !== '') firstQty.value = qty;
            checkStok(rows[0]);
            return rows[0];
        }
    }

    let row = `
        <tr>

            <td>

                <select
                    name="barang_id[]"
                    class="form-select barang-select"
                    required>

                    <option value="">
                        -- Pilih Bahan Baku --
                    </option>

                    @foreach($barang as $b)

                    <option
                        value="{{ $b->id }}"
                        data-stok="{{ $b->stok }}">

                        {{ $b->kode_barang }}
                        -
                        {{ $b->nama }}
                        ({{ $b->satuan }})

                        @if($b->stok <= 0)
                        - STOK HABIS
                        @endif

                    </option>

                    @endforeach

                </select>

            </td>

            <td>

                <input
                    type="number"
                    name="qty[]"
                    class="form-control qty-input"
                    min="0.01"
                    step="any"
                    placeholder="Qty"
                    required>
                <small class="text-danger stok-warning d-block mt-1" style="display:none;"></small>

            </td>

            <td>

                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    onclick="hapusBaris(this)">

                    Hapus

                </button>

            </td>

        </tr>
    `;

    tbody.insertAdjacentHTML(
        'beforeend',
        row
    );

    let newRow = tbody.lastElementChild;
    if (barangId) {
        newRow.querySelector('.barang-select').value = barangId;
    }
    if (qty !== '') {
        newRow.querySelector('.qty-input').value = qty;
    }
    checkStok(newRow);
    return newRow;
}

function hapusBaris(button)
{
    let row = button.closest('tr');

    if(document.querySelectorAll('#table-detail tbody tr').length > 1)
    {
        row.remove();
    } else {
        row.querySelector('.barang-select').value = '';
        row.querySelector('.qty-input').value = '';
        checkStok(row);
    }
}

function checkStok(row) {
    let select = row.querySelector('.barang-select');
    let qtyInput = row.querySelector('.qty-input');
    let warning = row.querySelector('.stok-warning');

    if (!select || !qtyInput || !warning) return;

    let selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || select.value === "") {
        warning.style.display = "none";
        return;
    }

    let stok = parseFloat(selectedOption.getAttribute('data-stok')) || 0;
    let qty = parseFloat(qtyInput.value) || 0;

    if (qty > stok) {
        warning.innerHTML = `⚠️ Stok Gudang Utama tidak mencukupi! Tersedia: <strong>${stok}</strong>`;
        warning.style.display = "block";
    } else {
        warning.style.display = "none";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const selectGudang = document.getElementById('select-gudang');
    const suggestionBox = document.getElementById('suggestion-box');
    const suggestionList = document.getElementById('suggestion-list');
    const suggestionGudangName = document.getElementById('suggest-gudang-name');
    const btnApplyAll = document.getElementById('btn-apply-all-suggestions');

    let currentSuggestions = [];

    function fetchSuggestions(gudangId) {
        if (!gudangId) {
            suggestionBox.style.display = 'none';
            suggestionList.innerHTML = '';
            currentSuggestions = [];
            return;
        }

        fetch("{{ route('pengeluaran-bahan-baku.suggestions') }}?gudang_id=" + gudangId)
            .then(res => res.json())
            .then(data => {
                currentSuggestions = data.suggestions || [];
                suggestionGudangName.innerText = data.gudang_name || '';

                if (currentSuggestions.length > 0) {
                    suggestionBox.style.display = 'block';
                    suggestionList.innerHTML = '';

                    currentSuggestions.forEach(item => {
                        const pill = document.createElement('div');
                        pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded-3';
                        pill.innerHTML = `
                            <div class="text-start">
                                <div class="fw-bold">${item.nama} <span class="text-muted small">(${item.kode_barang})</span></div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    Stok Outlet: <span class="text-danger fw-bold">${item.current_stock}</span> / Min: <span class="fw-bold">${item.min_stock}</span> ${item.satuan}
                                    <span class="text-secondary ms-1">(Utama: ${item.stok_utama} ${item.satuan})</span>
                                    <span class="text-success fw-bold ms-1">&rarr; Saran: ${item.suggested_qty} ${item.satuan}</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold btn-add-single-suggest py-1 px-2" style="font-size: 0.75rem;" title="Tambah item ini">
                                <i class="bi bi-plus-circle-fill"></i> Tambah
                            </button>
                        `;

                        pill.querySelector('.btn-add-single-suggest').addEventListener('click', function() {
                            tambahBaris(item.barang_id, item.suggested_qty);
                            pill.classList.remove('bg-white');
                            pill.classList.add('bg-warning-subtle');
                            this.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                            this.disabled = true;
                        });

                        suggestionList.appendChild(pill);
                    });
                } else {
                    suggestionBox.style.display = 'none';
                    suggestionList.innerHTML = '';
                }
            })
            .catch(() => {
                suggestionBox.style.display = 'none';
            });
    }

    function applyAllSuggestions() {
        if (!currentSuggestions.length) return;

        const tbody = document.querySelector('#table-detail tbody');
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((r, idx) => {
            if (idx > 0) r.remove();
        });
        const firstRow = tbody.querySelector('tr');
        firstRow.querySelector('.barang-select').value = '';
        firstRow.querySelector('.qty-input').value = '';

        currentSuggestions.forEach(item => {
            tambahBaris(item.barang_id, item.suggested_qty);
        });

        suggestionList.querySelectorAll('.btn-add-single-suggest').forEach(btn => {
            btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
            btn.disabled = true;
        });
    }

    if (selectGudang) {
        selectGudang.addEventListener('change', function() {
            fetchSuggestions(this.value);
        });

        if (selectGudang.value) {
            fetchSuggestions(selectGudang.value);
        }
    }

    if (btnApplyAll) {
        btnApplyAll.addEventListener('click', applyAllSuggestions);
    }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('barang-select')) {
        let row = e.target.closest('tr');
        checkStok(row);
    }
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('qty-input')) {
        let row = e.target.closest('tr');
        checkStok(row);
    }
});

</script>

</x-app-layout>
