<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .card-form { border-radius: 16px; border: 1px solid #eaeaea; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .form-label-custom { font-weight: 600; font-size: 0.82rem; color: #334155; }
        .btn-custom-orange { background-color: #db7946; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important; max-width: 960px;">

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

                    <div class="col-md-3">
                        <label class="form-label form-label-custom">Tanggal Order <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control text-sm rounded-3" value="{{ old('tanggal', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-custom">Estimasi Kirim <span class="text-danger">*</span></label>
                        <input type="date" name="estimasi_kirim" class="form-control text-sm rounded-3" value="{{ old('estimasi_kirim', date('Y-m-d', strtotime('+1 day'))) }}" required>
                    </div>
                </div>
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
                    <h6 class="fw-bold text-dark mb-0">Daftar Item Barang / Bahan</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btn-add-item">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Item
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="table-items">
                        <thead class="table-light">
                            <tr class="text-secondary small">
                                <th>NAMA BARANG / ITEM</th>
                                <th style="width: 150px;">QTY TARGET</th>
                                <th style="width: 100px;">SATUAN</th>
                                <th style="width: 60px;" class="text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="item-rows">
                            <tr>
                                <td>
                                    <select name="produk_id[]" class="form-select text-sm select-produk" required>
                                        <option value="">-- Pilih Barang / Master Item --</option>
                                        @foreach($produk as $item)
                                            <option value="{{ $item->id }}" data-satuan="{{ $item->satuan }}">
                                                {{ $item->kode_barang }} - {{ $item->nama }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="any" min="0.01" name="qty[]" class="form-control text-sm input-qty text-end" placeholder="0" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control text-sm input-satuan bg-light text-center" readonly value="-">
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

            function updateSatuan(selectEl) {
                const selected = selectEl.options[selectEl.selectedIndex];
                const satuan = selected ? selected.getAttribute('data-satuan') : '-';
                const row = selectEl.closest('tr');
                row.querySelector('.input-satuan').value = satuan || '-';
            }

            function checkRows() {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((r, idx) => {
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
                    targetRow.querySelector('.btn-remove-row').removeAttribute('disabled');
                    
                    targetRow.querySelector('.select-produk').addEventListener('change', function() { updateSatuan(this); });
                    targetRow.querySelector('.btn-remove-row').addEventListener('click', function() { targetRow.remove(); checkRows(); });

                    tableBody.appendChild(targetRow);
                }

                const select = targetRow.querySelector('.select-produk');
                const inputQty = targetRow.querySelector('.input-qty');
                const inputSatuan = targetRow.querySelector('.input-satuan');

                if (produkId) {
                    select.value = produkId;
                    updateSatuan(select);
                }
                if (qty !== '') {
                    inputQty.value = qty;
                }
                if (satuan) {
                    inputSatuan.value = satuan;
                }

                checkRows();
                return targetRow;
            }

            function fetchSuggestions(customerId) {
                if (!customerId) {
                    suggestionBox.style.display = 'none';
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
                            suggestionBox.style.display = 'block';
                            suggestionList.innerHTML = '';

                            currentSuggestions.forEach(item => {
                                const pill = document.createElement('div');
                                pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded-3';
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
                
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((r, idx) => {
                    if (idx > 0) r.remove();
                });
                const firstRow = tableBody.querySelector('tr');
                firstRow.querySelector('.select-produk').value = '';
                firstRow.querySelector('.input-qty').value = '';
                firstRow.querySelector('.input-satuan').value = '-';

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

            document.querySelectorAll('.select-produk').forEach(select => {
                select.addEventListener('change', function() { updateSatuan(this); });
            });

            btnAdd.addEventListener('click', function() {
                addItemRow();
            });

            if (customerSelect.value) {
                fetchSuggestions(customerSelect.value);
            }
        });
    </script>
</x-app-layout>
