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

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Edit Central Kitchen Order</h4>
                <p class="text-muted small mb-0">Perbarui pesanan barang / bahan setengah jadi: <span class="fw-bold text-primary">#{{ $pesanan->kode_pesanan }}</span></p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('ck-orders.show', $pesanan->id) }}" class="btn btn-outline-info btn-sm rounded-3">
                    <i class="bi bi-eye me-1"></i> Lihat Detail
                </a>
                <a href="{{ route('ck-orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
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

        <form action="{{ route('ck-orders.update', $pesanan->id) }}" method="POST" id="form-ck-order">
            @csrf
            @method('PUT')

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Order</h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Kode Order</label>
                        <input type="text" class="form-control text-sm rounded-3 bg-light fw-bold text-dark" value="{{ $pesanan->kode_pesanan }}" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Outlet Pemesan <span class="text-danger">*</span></label>
                        <select name="customer_id" id="select-customer" class="form-select text-sm rounded-3" required>
                            <option value="">-- Pilih Outlet Pemesan --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ (old('customer_id', $pesanan->customer_id) == $c->id) ? 'selected' : '' }}>
                                    {{ $c->nama }} ({{ $c->gudang_nama ?? $c->nama }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Tanggal Order <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control text-sm rounded-3" value="{{ old('tanggal', date('Y-m-d', strtotime($pesanan->tanggal))) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Estimasi Kirim <span class="text-danger">*</span></label>
                        <input type="date" name="estimasi_kirim" class="form-control text-sm rounded-3" value="{{ old('estimasi_kirim', date('Y-m-d', strtotime($pesanan->estimasi_kirim))) }}" required>
                    </div>

                    <input type="hidden" name="divisi_id" value="{{ $pesanan->divisi_id ?? 1 }}">
                </div>
            </div>

            {{-- TOMBOL TAMPILKAN SARAN RESTOCK --}}
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
                            @forelse($pesanan->details as $index => $detail)
                                @php
                                    $itemObj = $detail->produk;
                                    $outQty = floatval($itemObj->resepBtklBop->output_qty ?? 0);
                                    $outSatuan = $itemObj->resepBtklBop->satuan_output ?? ($itemObj->satuan ?? '');
                                    
                                    // Deteksi apakah awalnya diisi via resep atau satuan
                                    $isResep = false;
                                    $initQty = $detail->qty;
                                    if ($outQty > 0 && fmod($detail->qty, $outQty) == 0 && $detail->qty >= $outQty) {
                                        $isResep = true;
                                        $initQty = $detail->qty / $outQty;
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <select name="produk_id[]" class="form-select text-sm select-produk" required>
                                            <option value="">-- Cari / Pilih Barang BSJ --</option>
                                            @foreach($produk as $item)
                                                @php
                                                    $pOutQty = floatval($item->resepBtklBop->output_qty ?? 0);
                                                    $pOutSatuan = $item->resepBtklBop->satuan_output ?? ($item->satuan ?? '');
                                                @endphp
                                                <option value="{{ $item->id }}" 
                                                        data-satuan="{{ $item->satuan }}"
                                                        data-output-qty="{{ $pOutQty }}"
                                                        data-satuan-output="{{ $pOutSatuan }}"
                                                        {{ $detail->produk_id == $item->id ? 'selected' : '' }}>
                                                    {{ $item->kode_barang }} - {{ $item->nama }}
                                                    @if($pOutQty > 0)
                                                        (1 Resep = {{ number_format($pOutQty, 0, ',', '.') }} {{ $pOutSatuan }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="0.01" name="qty[]" class="form-control text-sm input-qty text-end fw-bold" value="{{ $initQty }}" placeholder="0" required>
                                    </td>
                                    <td>
                                        <select name="order_mode[]" class="form-select text-sm select-mode text-center fw-bold" data-init-mode="{{ $isResep ? 'resep' : 'satuan' }}">
                                            @if($outQty > 0)
                                                <option value="resep" {{ $isResep ? 'selected' : '' }}>Resep</option>
                                            @endif
                                            <option value="satuan" {{ !$isResep ? 'selected' : '' }}>{{ strtoupper($itemObj->satuan ?? 'SATUAN') }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="konversi-info small text-start p-1 px-2 bg-light rounded border">
                                            <span class="text-muted">-</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
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
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('ck-orders.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-custom-orange shadow-sm px-4">
                    <i class="bi bi-check-lg me-1"></i> Simpan Perubahan Order
                </button>
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

            function updateModeOptions(row, preserveMode = false) {
                if (!row) return;
                const selectEl = row.querySelector('.select-produk');
                const modeEl = row.querySelector('.select-mode');
                if (!selectEl || !modeEl) return;

                const selected = selectEl.options[selectEl.selectedIndex];
                const satuanUtama = (selected && selectEl.value) ? (selected.getAttribute('data-satuan') || 'Satuan') : 'Satuan';
                const outputQty = (selected && selectEl.value) ? parseFloat(selected.getAttribute('data-output-qty') || 0) : 0;

                const currentVal = preserveMode ? (modeEl.getAttribute('data-init-mode') || modeEl.value) : modeEl.value;
                modeEl.innerHTML = '';

                if (outputQty > 0) {
                    const optResep = new Option('Resep', 'resep');
                    modeEl.add(optResep);
                }

                const optSatuan = new Option(satuanUtama.toUpperCase(), 'satuan');
                modeEl.add(optSatuan);

                if (currentVal === 'resep' && outputQty > 0) {
                    modeEl.value = 'resep';
                } else if (currentVal === 'satuan') {
                    modeEl.value = 'satuan';
                } else {
                    modeEl.value = outputQty > 0 ? 'resep' : 'satuan';
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
                const rawQty = parseFloat(qtyEl.value || 0);

                if (rawQty <= 0) {
                    infoEl.innerHTML = '<span class="text-muted">Masukkan Qty</span>';
                    return;
                }

                if (mode === 'resep') {
                    if (outputQty > 0) {
                        const totalHasil = rawQty * outputQty;
                        infoEl.innerHTML = `<strong>${rawQty} Resep</strong> = <span class="text-primary fw-bold">${totalHasil.toLocaleString('id-ID')} ${outputSatuan}</span>`;
                    } else {
                        infoEl.innerHTML = `<strong>${rawQty}</strong> ${satuanUtama}`;
                    }
                } else {
                    if (outputQty > 0) {
                        const hitungResep = rawQty / outputQty;
                        const resepFmt = (hitungResep % 1 === 0) ? hitungResep : hitungResep.toFixed(2);
                        infoEl.innerHTML = `<strong>${rawQty} ${satuanUtama}</strong> (setara <span class="text-success fw-bold">${resepFmt} Resep</span>)`;
                    } else {
                        infoEl.innerHTML = `<strong>${rawQty}</strong> ${satuanUtama}`;
                    }
                }
            }

            function updateDeleteButtons() {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((row) => {
                    const btnDel = row.querySelector('.btn-remove-row');
                    if (btnDel) {
                        btnDel.disabled = (rows.length <= 1);
                    }
                });
            }

            function bindRowEvents(row, isInitial = false) {
                const selectEl = row.querySelector('.select-produk');
                const modeEl = row.querySelector('.select-mode');
                const qtyEl = row.querySelector('.input-qty');
                const btnDel = row.querySelector('.btn-remove-row');

                initTomSelectOnSelect(selectEl);

                if (isInitial) {
                    updateModeOptions(row, true);
                    updateKonversi(row);
                }

                if (modeEl) {
                    modeEl.addEventListener('change', function() {
                        updateKonversi(row);
                    });
                }

                if (qtyEl) {
                    qtyEl.addEventListener('input', function() {
                        updateKonversi(row);
                    });
                }

                if (btnDel) {
                    btnDel.addEventListener('click', function() {
                        if (tableBody.querySelectorAll('tr').length > 1) {
                            if (tomSelectInstances.has(selectEl)) {
                                tomSelectInstances.get(selectEl).destroy();
                                tomSelectInstances.delete(selectEl);
                            }
                            row.remove();
                            updateDeleteButtons();
                        }
                    });
                }
            }

            // Inisialisasi baris yang sudah ada
            tableBody.querySelectorAll('tr').forEach(row => {
                bindRowEvents(row, true);
            });
            updateDeleteButtons();

            // Tambah baris baru
            if (btnAdd) {
                btnAdd.addEventListener('click', function() {
                    const firstRow = tableBody.querySelector('tr');
                    const newRow = firstRow.cloneNode(true);

                    newRow.querySelectorAll('.ts-wrapper').forEach(w => w.remove());

                    const newSelect = newRow.querySelector('.select-produk');
                    if (newSelect) {
                        newSelect.value = '';
                    }

                    const newQty = newRow.querySelector('.input-qty');
                    if (newQty) {
                        newQty.value = '';
                    }

                    const newMode = newRow.querySelector('.select-mode');
                    if (newMode) {
                        newMode.innerHTML = '<option value="resep">Resep</option><option value="satuan">Satuan</option>';
                        newMode.removeAttribute('data-init-mode');
                    }

                    const newInfo = newRow.querySelector('.konversi-info');
                    if (newInfo) {
                        newInfo.innerHTML = '<span class="text-muted">-</span>';
                    }

                    tableBody.appendChild(newRow);
                    bindRowEvents(newRow, false);
                    updateDeleteButtons();
                });
            }

            // Fetch saran restock outlet
            function fetchOutletSuggestions(customerId) {
                if (!customerId) {
                    if (suggestionBox) suggestionBox.style.display = 'none';
                    const cont = document.getElementById('toggle-suggestion-container');
                    if (cont) cont.style.display = 'none';
                    return;
                }

                fetch(`{{ route('ck-orders.suggestions') }}?customer_id=${customerId}`)
                    .then(r => r.json())
                    .then(data => {
                        currentSuggestions = data.suggestions || [];
                        const cont = document.getElementById('toggle-suggestion-container');
                        if (currentSuggestions.length > 0) {
                            if (suggestionOutletName) suggestionOutletName.textContent = data.outlet_name || 'Outlet';
                            renderSuggestionPills(currentSuggestions);
                            if (cont) cont.style.display = 'block';
                            if (suggestionBox) suggestionBox.style.display = 'none';
                        } else {
                            if (cont) cont.style.display = 'none';
                            if (suggestionBox) suggestionBox.style.display = 'none';
                        }
                    })
                    .catch(err => console.error('Error fetching suggestions:', err));
            }

            const btnToggleSuggestions = document.getElementById('btn-toggle-suggestions');
            if (btnToggleSuggestions) {
                btnToggleSuggestions.addEventListener('click', function() {
                    if (!suggestionBox) return;
                    if (suggestionBox.style.display === 'none' || suggestionBox.style.display === '') {
                        suggestionBox.style.display = 'block';
                        btnToggleSuggestions.innerHTML = '<i class="bi bi-eye-slash-fill text-warning me-1"></i> Sembunyikan Saran Restock';
                    } else {
                        suggestionBox.style.display = 'none';
                        btnToggleSuggestions.innerHTML = '<i class="bi bi-lightbulb-fill text-warning me-1"></i> Tampilkan Saran Restock';
                    }
                });
            }

            function renderSuggestionPills(suggestions) {
                if (!suggestionList) return;
                suggestionList.innerHTML = '';
                suggestions.forEach(item => {
                    const pill = document.createElement('div');
                    pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm';
                    pill.style.fontSize = '0.78rem';
                    pill.innerHTML = `
                        <div>
                            <strong>${item.nama}</strong>: stok sisa <span class="text-danger fw-bold">${item.current_stock}</span> / min ${item.min_stock} ${item.satuan}
                            <span class="text-muted ms-1">(Saran: <strong class="text-success">+${item.suggested_qty} ${item.satuan}</strong>)</span>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 rounded-pill fw-bold btn-add-suggested" data-id="${item.barang_id}" data-qty="${item.suggested_qty}">
                            <i class="bi bi-plus"></i> Gunakan
                        </button>
                    `;
                    suggestionList.appendChild(pill);
                });

                suggestionList.querySelectorAll('.btn-add-suggested').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const bId = this.getAttribute('data-id');
                        const bQty = this.getAttribute('data-qty');
                        addItemRowWithData(bId, bQty);
                        this.disabled = true;
                        this.classList.replace('btn-outline-success', 'btn-secondary');
                        this.innerHTML = '<i class="bi bi-check"></i> Ditambahkan';
                    });
                });
            }

            function addItemRowWithData(barangId, qty) {
                let targetRow = null;
                const rows = tableBody.querySelectorAll('tr');
                for (let r of rows) {
                    const sel = r.querySelector('.select-produk');
                    if (sel && (!sel.value || sel.value === '')) {
                        targetRow = r;
                        break;
                    }
                }

                if (!targetRow) {
                    const firstRow = rows[0];
                    targetRow = firstRow.cloneNode(true);
                    targetRow.querySelectorAll('.ts-wrapper').forEach(w => w.remove());
                    tableBody.appendChild(targetRow);
                    bindRowEvents(targetRow, false);
                    updateDeleteButtons();
                }

                const sel = targetRow.querySelector('.select-produk');
                const qtyInput = targetRow.querySelector('.input-qty');
                const modeSelect = targetRow.querySelector('.select-mode');

                if (sel && tomSelectInstances.has(sel)) {
                    tomSelectInstances.get(sel).setValue(barangId);
                } else if (sel) {
                    sel.value = barangId;
                }

                if (modeSelect) modeSelect.value = 'satuan';
                if (qtyInput) qtyInput.value = qty;

                updateModeOptions(targetRow);
                if (modeSelect) modeSelect.value = 'satuan';
                updateKonversi(targetRow);
            }

            if (btnApplyAll) {
                btnApplyAll.addEventListener('click', function() {
                    if (!currentSuggestions.length) return;
                    currentSuggestions.forEach(item => {
                        addItemRowWithData(item.barang_id, item.suggested_qty);
                    });
                    this.disabled = true;
                    this.innerHTML = '<i class="bi bi-check-all me-1"></i> Semua Saran Telah Dimasukkan';
                });
            }

            if (customerSelect) {
                customerSelect.addEventListener('change', function() {
                    fetchOutletSuggestions(this.value);
                });
                if (customerSelect.value) {
                    fetchOutletSuggestions(customerSelect.value);
                }
            }
        });
    </script>
</x-app-layout>
