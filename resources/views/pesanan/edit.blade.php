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
                <h4 class="fw-bold text-dark mb-1">Edit Permintaan Cold Kitchen</h4>
                <p class="text-muted small mb-0">Perbarui data pengajuan permintaan: <span class="fw-bold text-primary">#{{ $pesanan->kode_pesanan }}</span></p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('pesanan.show', $pesanan->id) }}" class="btn btn-outline-info btn-sm rounded-3">
                    <i class="bi bi-eye me-1"></i> Lihat Detail
                </a>
                <a href="{{ route('pesanan.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
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

        <form action="{{ route('pesanan.update', $pesanan->id) }}" method="POST" id="form-cold-order">
            @csrf
            @method('PUT')

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Permintaan</h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Kode Permintaan</label>
                        <input type="text" class="form-control text-sm rounded-3 bg-light fw-bold text-dark" value="{{ $pesanan->kode_pesanan }}" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Outlet / Pemesan <span class="text-danger">*</span></label>
                        <select name="customer_id" id="select-customer" class="form-select text-sm rounded-3" required>
                            <option value="">-- Pilih Outlet / Pemesan --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ (old('customer_id', $pesanan->customer_id) == $c->id) ? 'selected' : '' }}>
                                    {{ $c->nama }} ({{ $c->gudang_nama ?? $c->nama }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Tanggal Permintaan <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control text-sm rounded-3" value="{{ old('tanggal', date('Y-m-d', strtotime($pesanan->tanggal))) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Estimasi Kirim <span class="text-danger">*</span></label>
                        <input type="date" name="estimasi_kirim" class="form-control text-sm rounded-3" value="{{ old('estimasi_kirim', $pesanan->estimasi_kirim ? date('Y-m-d', strtotime($pesanan->estimasi_kirim)) : '') }}" required>
                    </div>
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
                            Saran Restock Bahan Cold Kitchen (<span id="suggest-outlet-name"></span>)
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
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Daftar Item Barang (Bahan Setengah Jadi &amp; Bahan Jadi)</h6>
                        <small class="text-muted">Pilih produk dan masukkan kuantitas dalam satuan Resep, Konversi Box/Pack/Ember, atau Satuan standar</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btn-add-item">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Item
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="table-items">
                        <thead class="table-light">
                            <tr class="text-secondary small text-center">
                                <th class="text-start" style="width: 32%;">Nama Barang / Produk</th>
                                <th style="width: 15%;">Qty</th>
                                <th style="width: 20%;">Satuan / Mode</th>
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
                                    $pSatuanKonversi = $itemObj->satuan_pembelian ? strtoupper($itemObj->satuan_pembelian) : '';
                                    $pKonversiVal = floatval($itemObj->konversi_pembelian ?? 1);
                                    
                                    $initMode = 'satuan';
                                    $initQty = (float)$detail->qty;
                                    if ($pSatuanKonversi && $pKonversiVal > 1 && fmod($detail->qty, $pKonversiVal) == 0 && $detail->qty >= $pKonversiVal) {
                                        $initMode = 'konversi';
                                        $initQty = $detail->qty / $pKonversiVal;
                                    } elseif ($outQty > 0 && fmod($detail->qty, $outQty) == 0 && $detail->qty >= $outQty) {
                                        $initMode = 'resep';
                                        $initQty = $detail->qty / $outQty;
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <select name="produk_id[]" class="form-select text-sm select-produk" required>
                                            <option value="">-- Cari / Pilih Barang Cold Kitchen --</option>
                                            @foreach($produk as $item)
                                                @php
                                                    $pOutQty = floatval($item->resepBtklBop->output_qty ?? 0);
                                                    $pOutSatuan = $item->resepBtklBop->satuan_output ?? ($item->satuan ?? '');
                                                    $satuanKonv = $item->satuan_pembelian ? strtoupper($item->satuan_pembelian) : '';
                                                    $konvVal = floatval($item->konversi_pembelian ?? 1);
                                                    $tipeBadge = $item->is_bahan_setengah_jadi ? 'BSJ' : 'Barang Jadi';
                                                @endphp
                                                <option value="{{ $item->id }}" 
                                                        data-satuan="{{ $item->satuan }}"
                                                        data-output-qty="{{ $pOutQty }}"
                                                        data-satuan-output="{{ $pOutSatuan }}"
                                                        data-satuan-konversi="{{ $satuanKonv }}"
                                                        data-konversi="{{ $konvVal }}"
                                                        {{ $detail->produk_id == $item->id ? 'selected' : '' }}>
                                                    [{{ $tipeBadge }}] {{ $item->kode_barang }} - {{ $item->nama }}
                                                    @if($satuanKonv && $konvVal > 1)
                                                        (1 {{ $satuanKonv }} = {{ number_format($konvVal, 0, ',', '.') }} {{ $item->satuan }})
                                                    @elseif($pOutQty > 0)
                                                        (1 Resep = {{ number_format($pOutQty, 0, ',', '.') }} {{ $pOutSatuan }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="0.01" name="qty[]" class="form-control text-sm input-qty text-end fw-bold" value="{{ $initQty }}" placeholder="0" required>
                                        <input type="hidden" name="harga[]" value="{{ (float)($detail->harga ?? 0) }}">
                                        <input type="hidden" name="subtotal[]" value="{{ (float)($detail->subtotal ?? 0) }}">
                                    </td>
                                    <td>
                                        <select name="order_mode[]" class="form-select text-sm select-mode text-center fw-bold" data-init-mode="{{ $initMode }}">
                                            @if($pSatuanKonversi && $pKonversiVal > 1)
                                                <option value="konversi" {{ $initMode === 'konversi' ? 'selected' : '' }}>{{ $pSatuanKonversi }} ({{ number_format($pKonversiVal, 0, ',', '.') }} {{ $itemObj->satuan }})</option>
                                            @endif
                                            @if($outQty > 0)
                                                <option value="resep" {{ $initMode === 'resep' ? 'selected' : '' }}>Resep</option>
                                            @endif
                                            <option value="satuan" {{ $initMode === 'satuan' ? 'selected' : '' }}>{{ strtoupper($itemObj->satuan ?? 'SATUAN') }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="konversi-info small text-start p-1 px-2 bg-light rounded border">
                                            <span class="text-muted">-</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" {{ count($pesanan->details) <= 1 ? 'disabled' : '' }}>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td>
                                        <select name="produk_id[]" class="form-select text-sm select-produk" required>
                                            <option value="">-- Cari / Pilih Barang Cold Kitchen --</option>
                                            @foreach($produk as $item)
                                                @php
                                                    $outQty = floatval($item->resepBtklBop->output_qty ?? 0);
                                                    $outSatuan = $item->resepBtklBop->satuan_output ?? ($item->satuan ?? '');
                                                    $satuanKonversi = $item->satuan_pembelian ? strtoupper($item->satuan_pembelian) : '';
                                                    $konversiVal = floatval($item->konversi_pembelian ?? 1);
                                                    $tipeBadge = $item->is_bahan_setengah_jadi ? 'BSJ' : 'Barang Jadi';
                                                @endphp
                                                <option value="{{ $item->id }}" 
                                                        data-satuan="{{ $item->satuan }}"
                                                        data-output-qty="{{ $outQty }}"
                                                        data-satuan-output="{{ $outSatuan }}"
                                                        data-satuan-konversi="{{ $satuanKonversi }}"
                                                        data-konversi="{{ $konversiVal }}">
                                                    [{{ $tipeBadge }}] {{ $item->kode_barang }} - {{ $item->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="0.01" name="qty[]" class="form-control text-sm input-qty text-end fw-bold" placeholder="0" required>
                                        <input type="hidden" name="harga[]" value="0">
                                        <input type="hidden" name="subtotal[]" value="0">
                                    </td>
                                    <td>
                                        <select name="order_mode[]" class="form-select text-sm select-mode text-center fw-bold">
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

            <div class="d-flex justify-content-end gap-2 mb-5">
                <a href="{{ route('pesanan.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-custom-orange shadow-sm px-4">
                    <i class="bi bi-check-circle-fill me-1"></i> Simpan Perubahan Permintaan
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
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

            if (customerSelect) {
                new TomSelect('#select-customer', {
                    create: false,
                    placeholder: '-- Pilih Outlet / Pemesan --',
                    allowEmptyOption: true
                });
            }

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
                    placeholder: '-- Cari / Pilih Barang Cold Kitchen --',
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

            function updateModeOptions(row, preserveSelection = false) {
                if (!row) return;
                const selectEl = row.querySelector('.select-produk');
                const modeEl = row.querySelector('.select-mode');
                if (!selectEl || !modeEl) return;

                const selected = selectEl.options[selectEl.selectedIndex];
                const satuanUtama = (selected && selectEl.value) ? (selected.getAttribute('data-satuan') || 'Satuan') : 'Satuan';
                const outputQty = (selected && selectEl.value) ? parseFloat(selected.getAttribute('data-output-qty') || 0) : 0;
                const satuanKonversi = (selected && selectEl.value) ? (selected.getAttribute('data-satuan-konversi') || '') : '';
                const konversiVal = (selected && selectEl.value) ? parseFloat(selected.getAttribute('data-konversi') || 1) : 1;

                const currentVal = preserveSelection ? (modeEl.getAttribute('data-init-mode') || modeEl.value) : modeEl.value;
                modeEl.innerHTML = '';

                if (satuanKonversi && konversiVal > 1) {
                    const optKonversi = new Option(satuanKonversi + ' (' + konversiVal.toLocaleString('id-ID') + ' ' + satuanUtama + ')', 'konversi');
                    modeEl.add(optKonversi);
                }

                if (outputQty > 0) {
                    const optResep = new Option('Resep', 'resep');
                    modeEl.add(optResep);
                }

                const optSatuan = new Option(satuanUtama.toUpperCase(), 'satuan');
                modeEl.add(optSatuan);

                if (currentVal === 'konversi' && satuanKonversi && konversiVal > 1) {
                    modeEl.value = 'konversi';
                } else if (currentVal === 'resep' && outputQty > 0) {
                    modeEl.value = 'resep';
                } else if (currentVal === 'satuan') {
                    modeEl.value = 'satuan';
                } else if (satuanKonversi && konversiVal > 1) {
                    modeEl.value = 'konversi';
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
                const satuanKonversi = selected.getAttribute('data-satuan-konversi') || '';
                const konversiVal = parseFloat(selected.getAttribute('data-konversi') || 1);
                const mode = modeEl.value;
                const qtyInput = parseFloat(qtyEl.value || 0);

                if (mode === 'konversi' && konversiVal > 1) {
                    const totalGramasi = qtyInput > 0 ? (qtyInput * konversiVal) : 0;
                    infoEl.innerHTML = `
                        <div class="fw-bold text-primary" style="font-size: 0.85rem;">${totalGramasi.toLocaleString('id-ID')} ${satuanUtama}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">(1 ${satuanKonversi} = ${konversiVal.toLocaleString('id-ID')} ${satuanUtama})</div>
                    `;
                } else if (mode === 'resep' && outputQty > 0) {
                    const totalTarget = qtyInput > 0 ? (qtyInput * outputQty) : 0;
                    infoEl.innerHTML = `
                        <div class="fw-bold text-success" style="font-size: 0.85rem;">${totalTarget.toLocaleString('id-ID')} ${outputSatuan}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">(1 Resep = ${outputQty.toLocaleString('id-ID')} ${outputSatuan})</div>
                    `;
                } else {
                    let helperText = '';
                    if (satuanKonversi && konversiVal > 1 && qtyInput > 0) {
                        const packEquivalent = qtyInput / konversiVal;
                        const packFmt = (packEquivalent % 1 === 0) ? packEquivalent.toFixed(0) : packEquivalent.toFixed(2);
                        helperText = `<div class="text-primary" style="font-size: 0.72rem;">(= ${packFmt} ${satuanKonversi})</div>`;
                    } else if (outputQty > 0 && qtyInput > 0) {
                        const resepEquivalent = qtyInput / outputQty;
                        const resepFmt = (resepEquivalent % 1 === 0) ? resepEquivalent.toFixed(0) : resepEquivalent.toFixed(2);
                        helperText = `<div class="text-primary" style="font-size: 0.72rem;">(= ${resepFmt} Resep)</div>`;
                    }
                    infoEl.innerHTML = `
                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">${qtyInput.toLocaleString('id-ID')} ${satuanUtama || '-'}</div>
                        ${helperText}
                    `;
                }
            }

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
                    if (modeSelect) modeSelect.value = 'satuan';
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

            // Inisialisasi awal baris edit
            document.querySelectorAll('#item-rows tr').forEach(row => {
                const select = row.querySelector('.select-produk');
                if (select) {
                    initTomSelectOnSelect(select);
                }
                updateKonversi(row);
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

                fetch("{{ route('pesanan.suggestions') }}?customer_id=" + customerId)
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

            if (customerSelect) {
                customerSelect.addEventListener('change', function() {
                    fetchSuggestions(this.value);
                });
            }

            btnApplyAll.addEventListener('click', applyAllSuggestions);

            btnAdd.addEventListener('click', function() {
                addItemRow();
            });

            if (customerSelect && customerSelect.value) {
                fetchSuggestions(customerSelect.value);
            }
        });
    </script>
    @endpush
</x-app-layout>