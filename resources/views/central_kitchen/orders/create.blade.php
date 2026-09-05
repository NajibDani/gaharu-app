<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <style>
        .ts-dropdown { z-index: 99999 !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .card-form { border-radius: 16px; border: 1px solid #eaeaea; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .form-label-custom { font-weight: 600; font-size: 0.82rem; color: #334155; }
        .btn-custom-orange { background-color: #db7946; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important; max-width: 1000px;">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">Buat Central Kitchen Order</h4>
                <p class="text-muted small mb-0">Input pesanan barang / bahan setengah jadi untuk disetor ke Outlet</p>
            </div>
            <a href="{{ route('ck-orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 text-sm mb-4" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('ck-orders.store') }}" method="POST" id="form-ck-order">
            @csrf

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Order</h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Outlet Pemesan <span class="text-danger">*</span></label>
                        <select name="customer_id" id="select-customer" class="form-select text-sm rounded-3" required>
                            <option value="">-- Pilih Outlet Pemesan --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->nama }} ({{ $c->gudang_nama ?? $c->nama }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Tanggal Order <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control text-sm rounded-3" value="{{ old('tanggal', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Estimasi Kirim <span class="text-danger">*</span></label>
                        <input type="date" name="estimasi_kirim" class="form-control text-sm rounded-3" value="{{ old('estimasi_kirim', date('Y-m-d', strtotime('+1 day'))) }}" required>
                    </div>

                    <input type="hidden" name="divisi_id" value="1">
                </div>
            </div>

            {{-- TOMBOL TAMPILKAN SARAN --}}
            <div id="toggle-suggestion-container" class="mb-3" style="display: none;">
                <button type="button" class="btn btn-outline-warning text-dark fw-bold shadow-sm" id="btn-toggle-suggestions" style="border-radius: 8px;">
                    <i class="bi bi-lightbulb-fill text-warning me-1"></i> Tampilkan Saran Restock
                </button>
            </div>

            {{-- SUGGESTION RESTOCK BOX --}}
            <div id="suggestion-box" class="card card-form p-3 mb-4 bg-light border-warning" style="display: none; border-left: 5px solid #f59e0b !important;">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <strong class="text-dark small d-flex align-items-center">
                            <i class="bi bi-lightbulb-fill text-warning fs-6 me-2"></i>
                            Saran Restock Bahan Setengah Jadi (<span id="suggest-outlet-name"></span>)
                        </strong>
                        <span class="text-muted small" style="font-size: 0.75rem;">Item di bawah batas minimum stock gudang outlet</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm" id="btn-apply-all-suggestions">
                        <i class="bi bi-plus-circle-fill me-1"></i> Gunakan Semua Saran Restock
                    </button>
                </div>
                <div id="suggestion-list" class="d-flex flex-wrap gap-2 pt-1">
                    <!-- Dynamic suggestion pills -->
                </div>
            </div>

            <div class="card card-form p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h6 class="fw-bold text-dark mb-0">Daftar Item Barang / Bahan Setengah Jadi</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btn-add-item">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Item
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="table-items">
                        <thead class="table-light">
                            <tr class="text-secondary small text-center">
                                <th class="text-start" style="width: 32%;">Nama Bahan</th>
                                <th style="width: 15%;">Qty</th>
                                <th style="width: 20%;">Satuan</th>
                                <th style="width: 28%;">Total Qty (Konversi)</th>
                                <th style="width: 5%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="item-rows">
                            <tr>
                                <td>
                                    <select name="produk_id[]" class="form-select text-sm select-produk" required>
                                        <option value="">-- Cari / Pilih Barang BSJ --</option>
                                        @foreach($produk as $item)
                                            @php
                                                $outQty = floatval($item->resepBtklBop->output_qty ?? 0);
                                                $outSatuan = $item->resepBtklBop->satuan_output ?? ($item->satuan ?? '');
                                            @endphp
                                            <option value="{{ $item->id }}" 
                                                    data-satuan="{{ $item->satuan }}"
                                                    data-output-qty="{{ $outQty }}"
                                                    data-satuan-output="{{ $outSatuan }}">
                                                {{ $item->kode_barang }} - {{ $item->nama }}
                                                @if($outQty > 0)
                                                    (1 Resep = {{ number_format($outQty, 0, ',', '.') }} {{ $outSatuan }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="any" min="0.01" name="qty[]" class="form-control text-sm input-qty text-end fw-bold" placeholder="0" required>
                                </td>
                                <td>
                                    <select name="order_mode[]" class="form-select text-sm select-mode text-center fw-bold">
                                        <option value="resep">Resep</option>
                                        <option value="satuan">Satuan</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="konversi-info small text-start p-1 px-2 bg-light rounded border">
                                        <span class="text-muted">-</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" disabled>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('ck-orders.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-custom-orange shadow-sm px-4">Simpan Central Kitchen Order</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('item-rows');
            const btnAdd = document.getElementById('btn-add-item');
            const customerSelect = document.getElementById('select-customer');
            const suggestionBox = document.getElementById('suggestion-box');
            const suggestionList = document.getElementById('suggestion-list');
            const suggestionOutletName = document.getElementById('suggest-outlet-name');
            const btnApplyAll = document.getElementById('btn-apply-all-suggestions');

            let currentSuggestions = [];
            const tomSelectInstances = new Map();

            function initTomSelectOnSelect(selectEl) {
                if (!selectEl) return null;
                if (tomSelectInstances.has(selectEl)) {
                    return tomSelectInstances.get(selectEl);
                }
                if (typeof TomSelect === 'undefined') return null;

                // Clean up TomSelect attributes if cloned from an existing row
                delete selectEl.tomselect;
                selectEl.classList.remove('tomselected', 'ts-hidden-accessible');
                selectEl.removeAttribute('id');
                selectEl.removeAttribute('tabindex');
                selectEl.removeAttribute('aria-hidden');
                selectEl.style.display = '';

                const ts = new TomSelect(selectEl, {
                    create: false,
                    placeholder: '-- Cari / Pilih Barang BSJ --',
                    allowEmptyOption: true,
                    dropdownParent: 'body',
                    onChange: function() {
                        const row = selectEl.closest('tr');
                        updateModeOptions(row);
                        updateKonversi(row);
                    }
                });
                tomSelectInstances.set(selectEl, ts);
                return ts;
            }

            function updateModeOptions(row) {
                if (!row) return;
                const selectEl = row.querySelector('.select-produk');
                const modeEl = row.querySelector('.select-mode');
                if (!selectEl || !modeEl) return;

                const selected = selectEl.options[selectEl.selectedIndex];
                const satuanUtama = (selected && selectEl.value) ? (selected.getAttribute('data-satuan') || 'Satuan') : 'Satuan';
                const outputQty = (selected && selectEl.value) ? parseFloat(selected.getAttribute('data-output-qty') || 0) : 0;

                const currentVal = modeEl.value;
                modeEl.innerHTML = '';

                if (outputQty > 0) {
                    const optResep = new Option('Resep', 'resep');
                    modeEl.add(optResep);
                }

                const optSatuan = new Option(satuanUtama.toUpperCase(), 'satuan');
                modeEl.add(optSatuan);

                if (currentVal === 'resep' && outputQty > 0) {
                    modeEl.value = 'resep';
                } else {
                    modeEl.value = 'satuan';
                }
            }

            function updateKonversi(row) {
                if (!row) return;
                const selectEl = row.querySelector('.select-produk');
                const modeEl = row.querySelector('.select-mode');
                const qtyEl = row.querySelector('.input-qty');
                const infoEl = row.querySelector('.konversi-info');

                if (!selectEl || !modeEl || !qtyEl || !infoEl) return;

                const selected = selectEl.options[selectEl.selectedIndex];
                if (!selected || !selectEl.value) {
                    infoEl.innerHTML = '<span class="text-muted">-</span>';
                    return;
                }

                const outputQty = parseFloat(selected.getAttribute('data-output-qty') || 0);
                const outputSatuan = selected.getAttribute('data-satuan-output') || '';
                const satuanUtama = selected.getAttribute('data-satuan') || '';
                const mode = modeEl.value;
                const qtyInput = parseFloat(qtyEl.value || 0);

                if (mode === 'resep' && outputQty > 0) {
                    const totalTarget = qtyInput > 0 ? (qtyInput * outputQty) : 0;
                    infoEl.innerHTML = `
                        <div class="fw-bold text-success" style="font-size: 0.85rem;">${totalTarget.toLocaleString('id-ID')} ${outputSatuan}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">(1 Resep = ${outputQty.toLocaleString('id-ID')} ${outputSatuan})</div>
                    `;
                } else {
                    const resepEquivalent = outputQty > 0 && qtyInput > 0 ? (qtyInput / outputQty) : 0;
                    const resepFmt = (resepEquivalent % 1 === 0) ? resepEquivalent.toFixed(0) : resepEquivalent.toFixed(2);
                    infoEl.innerHTML = `
                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">${qtyInput.toLocaleString('id-ID')} ${satuanUtama || '-'}</div>
                        ${outputQty > 0 && qtyInput > 0 ? `<div class="text-primary" style="font-size: 0.72rem;">(= ${resepFmt} Resep)</div>` : ''}
                    `;
                }
            }

            // Delegasi Event untuk Hapus Baris & Update Satuan/Konversi
            tableBody.addEventListener('click', function(e) {
                const btnRemove = e.target.closest('.btn-remove-row');
                if (btnRemove && !btnRemove.disabled) {
                    const row = btnRemove.closest('tr');
                    if (row) {
                        const selectEl = row.querySelector('.select-produk');
                        const barangId = selectEl ? selectEl.value : '';

                        if (selectEl && tomSelectInstances.has(selectEl)) {
                            tomSelectInstances.get(selectEl).destroy();
                            tomSelectInstances.delete(selectEl);
                        }

                        row.remove();
                        checkRows();

                        if (barangId) {
                            const pill = document.querySelector(`#suggestion-list [data-barang-id="${barangId}"]`);
                            if (pill) {
                                pill.classList.remove('bg-warning-subtle');
                                pill.classList.add('bg-white');
                                const btn = pill.querySelector('.btn-add-single-suggest');
                                if (btn) {
                                    btn.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Tambah';
                                    btn.disabled = false;
                                }
                            }
                        }
                    }
                }
            });

            tableBody.addEventListener('change', function(e) {
                if (e.target.classList.contains('select-produk') || e.target.classList.contains('select-mode')) {
                    updateKonversi(e.target.closest('tr'));
                }
            });

            tableBody.addEventListener('input', function(e) {
                if (e.target.classList.contains('input-qty')) {
                    updateKonversi(e.target.closest('tr'));
                }
            });

            function checkRows() {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((r) => {
                    const btnDel = r.querySelector('.btn-remove-row');
                    if (rows.length === 1) {
                        btnDel.setAttribute('disabled', 'disabled');
                    } else {
                        btnDel.removeAttribute('disabled');
                    }
                });
            }

            function addItemRow(produkId = '', qty = '', satuan = '') {
                const rows = tableBody.querySelectorAll('tr');
                let targetRow = null;

                if (rows.length === 1) {
                    const firstSelect = rows[0].querySelector('.select-produk');
                    const firstQty = rows[0].querySelector('.input-qty');
                    if (!firstSelect.value && !firstQty.value) {
                        targetRow = rows[0];
                    }
                }

                if (!targetRow) {
                    const firstRow = rows[0];
                    targetRow = firstRow.cloneNode(true);
                    
                    const tsWrapper = targetRow.querySelector('.ts-wrapper');
                    if (tsWrapper) tsWrapper.remove();
                    const oldSelect = targetRow.querySelector('select.select-produk');
                    if (oldSelect) {
                        delete oldSelect.tomselect;
                        oldSelect.classList.remove('tomselected', 'ts-hidden-accessible');
                        oldSelect.removeAttribute('id');
                        oldSelect.removeAttribute('tabindex');
                        oldSelect.removeAttribute('aria-hidden');
                        oldSelect.style.display = '';
                        oldSelect.value = '';
                    }

                    targetRow.querySelector('.btn-remove-row').removeAttribute('disabled');
                    
                    const modeSelect = targetRow.querySelector('.select-mode');
                    if (modeSelect) modeSelect.value = 'resep';
                    const qtyInput = targetRow.querySelector('.input-qty');
                    if (qtyInput) qtyInput.value = '';
                    const infoBox = targetRow.querySelector('.konversi-info');
                    if (infoBox) infoBox.innerHTML = '<span class="text-muted">-</span>';

                    tableBody.appendChild(targetRow);
                }

                const select = targetRow.querySelector('.select-produk');
                const inputQty = targetRow.querySelector('.input-qty');

                if (inputQty && qty !== '') {
                    inputQty.value = qty;
                }

                const ts = initTomSelectOnSelect(select);
                if (ts) {
                    if (produkId) {
                        ts.setValue(produkId);
                    } else {
                        ts.setValue('', true);
                    }
                } else if (select) {
                    select.value = produkId || '';
                    updateModeOptions(targetRow);
                    updateKonversi(targetRow);
                }

                checkRows();
                return targetRow;
            }

            // Init TomSelect di baris awal
            document.querySelectorAll('.select-produk').forEach(select => {
                initTomSelectOnSelect(select);
            });

            function fetchSuggestions(customerId) {
                const toggleContainer = document.getElementById('toggle-suggestion-container');
                if (!customerId) {
                    suggestionBox.style.display = 'none';
                    toggleContainer.style.display = 'none';
                    suggestionList.innerHTML = '';
                    currentSuggestions = [];
                    return;
                }

                fetch("{{ route('ck-orders.suggestions') }}?customer_id=" + customerId)
                    .then(res => res.json())
                    .then(data => {
                        currentSuggestions = data.suggestions || [];
                        suggestionOutletName.innerText = data.outlet_name || '';

                        if (currentSuggestions.length > 0) {
                            toggleContainer.style.display = 'block';
                            suggestionBox.style.display = 'none';
                            suggestionList.innerHTML = '';

                            currentSuggestions.forEach(item => {
                                const pill = document.createElement('div');
                                pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded-3';
                                pill.dataset.barangId = item.barang_id;
                                pill.innerHTML = `
                                    <div class="text-start">
                                        <div class="fw-bold">${item.nama}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            Stok: <span class="text-danger fw-bold">${item.current_stock}</span> / Min: <span class="fw-bold">${item.min_stock}</span> ${item.satuan}
                                            <span class="text-success fw-bold ms-1">(Saran: ${item.suggested_qty} ${item.satuan})</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold btn-add-single-suggest py-1 px-2" style="font-size: 0.75rem;" title="Tambah item ini">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </button>
                                `;

                                pill.querySelector('.btn-add-single-suggest').addEventListener('click', function() {
                                    addItemRow(item.barang_id, item.suggested_qty, item.satuan);
                                    pill.classList.remove('bg-white');
                                    pill.classList.add('bg-warning-subtle');
                                    this.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                                    this.disabled = true;
                                });

                                suggestionList.appendChild(pill);
                            });
                        } else {
                            toggleContainer.style.display = 'none';
                            suggestionBox.style.display = 'none';
                            suggestionList.innerHTML = '';
                        }
                    })
                    .catch(() => {
                        toggleContainer.style.display = 'none';
                        suggestionBox.style.display = 'none';
                    });
            }

            document.getElementById('btn-toggle-suggestions').addEventListener('click', function () {
                const box = document.getElementById('suggestion-box');
                if (box.style.display === 'none') {
                    box.style.display = 'block';
                    this.innerHTML = '<i class="bi bi-lightbulb-fill text-warning me-1"></i> Sembunyikan Saran Restock';
                    this.classList.remove('btn-outline-warning');
                    this.classList.add('btn-warning');
                } else {
                    box.style.display = 'none';
                    this.innerHTML = '<i class="bi bi-lightbulb-fill text-warning me-1"></i> Tampilkan Saran Restock';
                    this.classList.remove('btn-warning');
                    this.classList.add('btn-outline-warning');
                }
            });

            function applyAllSuggestions() {
                if (!currentSuggestions.length) return;
                
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((r, idx) => {
                    if (idx > 0) r.remove();
                });
                const firstRow = tableBody.querySelector('tr');
                const firstSelect = firstRow.querySelector('.select-produk');
                if (tomSelectInstances.has(firstSelect)) {
                    tomSelectInstances.get(firstSelect).setValue('');
                } else {
                    firstSelect.value = '';
                }
                firstRow.querySelector('.input-qty').value = '';

                currentSuggestions.forEach(item => {
                    addItemRow(item.barang_id, item.suggested_qty, item.satuan);
                });

                suggestionList.querySelectorAll('.btn-add-single-suggest').forEach(btn => {
                    btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                    btn.disabled = true;
                });
            }

            customerSelect.addEventListener('change', function() {
                fetchSuggestions(this.value);
            });

            btnApplyAll.addEventListener('click', applyAllSuggestions);

            btnAdd.addEventListener('click', function() {
                addItemRow();
            });

            if (customerSelect.value) {
                fetchSuggestions(customerSelect.value);
            }
        });
    </script>
</x-app-layout>
