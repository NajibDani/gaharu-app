<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <div class="container">
        <h4>Tambah Pembelian</h4>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('pembelian.store') }}" method="POST">
            @csrf

            <div class="row mb-3">

                {{-- SUPPLIER --}}
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label>Supplier</label>

                    <select
                        name="supplier_id"
                        id="supplier_id"
                        class="form-control"
                        required>

                        <option value="">
                            -- Pilih Supplier --
                        </option>

                        @foreach($suppliers as $supplier)
                            <option
                                value="{{ $supplier->id }}"
                                data-nama="{{ strtoupper($supplier->nama) }}">

                                {{ $supplier->nama }}

                            </option>
                        @endforeach

                    </select>
                </div>

                {{-- GUDANG --}}
                <div class="col-12 col-md-4 mb-3 mb-md-0">

                    <label>Gudang Tujuan</label>

                    @php
                        $gudangUtama = $gudangs->firstWhere('nama', 'Gudang Utama');
                    @endphp

                    <input
                        type="text"
                        class="form-control"
                        value="Gudang Utama"
                        readonly>

                    <input
                        type="hidden"
                        name="gudang_id"
                        value="{{ $gudangUtama?->id }}">

                </div>

                {{-- TANGGAL --}}
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label>Tanggal</label>

                    <input
                        type="date"
                        name="tanggal"
                        id="tanggal"
                        class="form-control"
                        value="{{ date('Y-m-d') }}"
                        required>
                </div>
            </div>

            <hr>

            {{-- TOMBOL TAMPILKAN SARAN --}}
            <div id="toggle-suggestion-container" class="mb-3" style="display: none;">
                <button type="button" class="btn btn-outline-warning text-dark fw-bold shadow-sm" id="btn-toggle-suggestions" style="border-radius: 8px;">
                    <i class="bi bi-lightbulb-fill text-warning me-1"></i> Tampilkan Saran Restock
                </button>
            </div>

            {{-- SARAN RESTOCK (CONDITIONAL) --}}
            <div id="suggestion-box" class="card p-3 mb-4 bg-light border-warning shadow-sm" style="display: none; border-left: 5px solid #f59e0b !important; border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <strong class="text-dark small d-flex align-items-center">
                            <i class="bi bi-lightbulb-fill text-warning fs-6 me-2"></i>
                            Saran Restock Bahan Baku Gudang Utama
                        </strong>
                        <span class="text-muted small" style="font-size: 0.75rem;">Bahan baku yang stoknya sudah atau hampir mencapai batas minimum stok</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm" id="btn-apply-all-suggestions">
                        <i class="bi bi-plus-circle-fill me-1"></i> Gunakan Semua Saran Restock
                    </button>
                </div>
                <div id="suggestion-list" class="d-flex flex-wrap gap-2 pt-1">
                    <!-- Dynamic suggestion pills -->
                </div>
            </div>

            <h5>Detail Barang</h5>

            <div class="table-responsive">
                <table class="table table-bordered align-middle" id="table-items">
    
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th width="120">Qty</th>
                            <th width="180">Total Harga</th>
                            <th width="180">Harga / Qty</th>
                            <th width="220">Batch Number</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
    
                    <tbody>
    
                        <tr class="item-row">
    
                            {{-- BARANG --}}
                            <td>
                                <select
                                    name="items[0][barang_id]"
                                    class="form-control barang-select"
                                    required>
    
                                    <option value="">
                                        -- Pilih Barang --
                                    </option>
    
                                    @foreach($barangs as $barang)
                                        <option
                                            value="{{ $barang->id }}"
                                            data-kode="{{ $barang->kode_barang }}"
                                            data-satuan-pembelian="{{ $barang->satuan_pembelian }}"
                                            data-konversi-pembelian="{{ $barang->konversi_pembelian }}"
                                            data-satuan-utama="{{ $barang->satuan }}">
    
                                            {{ $barang->kode_barang }} - {{ $barang->nama }}
    
                                        </option>
                                    @endforeach
    
                                </select>
                            </td>
    
                            {{-- QTY --}}
                            <td>
                                <input
                                    type="text"
                                    name="items[0][qty]"
                                    class="form-control qty-input mask-number"
                                    required>
                                <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;"></small>
                            </td>
    
                            {{-- TOTAL HARGA --}}
                            <td>
                                <input
                                    type="text"
                                    name="items[0][harga]"
                                    class="form-control harga-input mask-number"
                                    required>
                            </td>
    
                            {{-- HARGA PER QTY (display only, tidak dikirim ke server) --}}
                            <td>
                                <input
                                    type="text"
                                    class="form-control harga-per-qty"
                                    readonly
                                    tabindex="-1">
                            </td>
    
                            {{-- BATCH (display only, digenerate ulang di controller) --}}
                            <td>
                                <input
                                    type="text"
                                    class="form-control batch-number"
                                    readonly
                                    tabindex="-1">
                            </td>
    
                            {{-- AKSI --}}
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm btn-remove">
    
                                    X
    
                                </button>
                            </td>
    
                        </tr>
    
                    </tbody>
                </table>
            <div class="row mt-3">
                <div class="col-12 col-md-4 offset-md-8">
                    <div class="card border-0 shadow-sm p-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <label class="form-label fw-bold text-secondary small">Biaya Tambahan (Tax / Service / Ongkir)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-semibold text-muted">Rp</span>
                            <input type="text" name="tax_service" id="tax_service" class="form-control mask-number fw-bold text-end" value="{{ old('tax_service', 0) }}" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                <button
                    type="button"
                    class="btn btn-secondary"
                    id="btn-add">
                    Tambah Baris
                </button>
                <button
                    type="submit"
                    class="btn btn-primary">
                    Simpan Pembelian
                </button>
                <a
                    href="{{ route('pembelian.index') }}"
                    class="btn btn-light border">
                    Kembali
                </a>
            </div>
        </form>
    </div>

    <script>

        let rowIndex = 1;

        /*
        |--------------------------------------------------------------------------
        | TAMBAH ROW
        |--------------------------------------------------------------------------
        */

        document.getElementById('btn-add')
            .addEventListener('click', function () {

            const tbody =
                document.querySelector('#table-items tbody');

            const row = `
                <tr class="item-row">

                    <td>
                        <select
                            name="items[${rowIndex}][barang_id]"
                            class="form-control barang-select"
                            required>

                            <option value="">
                                -- Pilih Barang --
                            </option>

                            @foreach($barangs as $barang)
                                <option
                                    value="{{ $barang->id }}"
                                    data-kode="{{ $barang->kode_barang }}"
                                    data-satuan-pembelian="{{ $barang->satuan_pembelian }}"
                                    data-konversi-pembelian="{{ $barang->konversi_pembelian }}"
                                    data-satuan-utama="{{ $barang->satuan }}">
                                    {{ $barang->kode_barang }} - {{ $barang->nama }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input
                            type="text"
                            name="items[${rowIndex}][qty]"
                            class="form-control qty-input mask-number"
                            required>
                        <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;"></small>
                    </td>

                    <td>
                        <input
                            type="text"
                            name="items[${rowIndex}][harga]"
                            class="form-control harga-input mask-number"
                            required>
                    </td>

                    <td>
                        <input
                            type="text"
                            name="items[${rowIndex}][harga_per_qty]"
                            class="form-control harga-per-qty"
                            readonly
                            tabindex="-1">
                    </td>

                    <td>
                        <input
                            type="text"
                            name="items[${rowIndex}][batch_number]"
                            class="form-control batch-number"
                            readonly
                            tabindex="-1">
                    </td>

                    <td>
                        <button
                            type="button"
                            class="btn btn-danger btn-sm btn-remove">

                            X

                        </button>
                    </td>

                </tr>
            `;

            tbody.insertAdjacentHTML('beforeend', row);

            rowIndex++;
        });

        /*
        |--------------------------------------------------------------------------
        | REMOVE ROW + REINDEX
        |--------------------------------------------------------------------------
        */

        document.addEventListener('click', function (e) {

            if (e.target.classList.contains('btn-remove')) {

                const rows =
                    document.querySelectorAll('#table-items tbody tr');

                if (rows.length > 1) {
                    const row = e.target.closest('tr');
                    const select = row.querySelector('.barang-select');
                    const barangId = select ? select.value : '';

                    row.remove();

                    // Reindex semua baris agar tidak ada gap di array
                    reindexRows();

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

        function reindexRows() {
            document.querySelectorAll('#table-items tbody tr').forEach((row, i) => {
                row.querySelector('[name*="[barang_id]"]').name = `items[${i}][barang_id]`;
                row.querySelector('[name*="[qty]"]').name       = `items[${i}][qty]`;
                row.querySelector('[name*="[harga]"]').name     = `items[${i}][harga]`;
                // rowIndex selalu lebih besar dari jumlah row yang ada
                rowIndex = document.querySelectorAll('#table-items tbody tr').length;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE BATCH NUMBER
        |--------------------------------------------------------------------------
        */

        function generateBatchNumber(row)
        {
            const tanggal =
                document.getElementById('tanggal').value;

            const supplierSelect =
                document.getElementById('supplier_id');

            const selectedVal = supplierSelect.value;
            const supplierOption = supplierSelect.querySelector(`option[value="${selectedVal}"]`);

            const supplier =
                supplierOption ? (supplierOption.dataset.nama ?? '') : '';

            const barangSelect =
                row.querySelector('.barang-select');

            const barangOption =
                barangSelect.options[barangSelect.selectedIndex];

            const kodeBarang =
                barangOption.dataset.kode ?? '';

            if (
                !tanggal ||
                !supplier ||
                !kodeBarang
            ) {
                return;
            }

            const tanggalFormat =
                tanggal.replaceAll('-', '');

            const batch =
                `${tanggalFormat}-${supplier}-${kodeBarang}`;

            row.querySelector('.batch-number').value =
                batch;
        }

        /*
        |--------------------------------------------------------------------------
        | HITUNG HARGA PER QTY
        |--------------------------------------------------------------------------
        */

        function getCleanNumber(val) {
            if (!val) return 0;
            let clean = val.replace(/\./g, '').replace(/,/g, '.');
            return parseFloat(clean) || 0;
        }

        function formatNumberIndonesian(value) {
            let parts = value.replace(/[^0-9,]/g, '').split(',');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            if (parts.length > 2) {
                parts = [parts[0], parts.slice(1).join('')];
            }
            return parts.join(',');
        }

        function calculateHargaPerQty(row)
        {
            const qtyInput =
                row.querySelector('.qty-input');

            const hargaInput =
                row.querySelector('.harga-input');

            const hargaPerQtyInput =
                row.querySelector('.harga-per-qty');

            const qty =
                getCleanNumber(qtyInput.value);

            const harga =
                getCleanNumber(hargaInput.value);

            let hasil = 0;

            if (qty > 0) {
                hasil = harga / qty;
            }

            // Tampilkan dengan desimal 2 digit dan format ribuan
            hargaPerQtyInput.value =
                formatNumberIndonesian(hasil.toFixed(2).replace('.', ','));
        }

        /*
        |--------------------------------------------------------------------------
        | AUTO GENERATE BATCH
        |--------------------------------------------------------------------------
        */

        function updateQtyHint(row) {
            const select = row.querySelector('.barang-select');
            const opt = select.options[select.selectedIndex];
            const hint = row.querySelector('.qty-hint');
            if (!opt || opt.value === '') {
                hint.textContent = '';
                return;
            }
            const satuanPembelian = opt.dataset.satuanPembelian || '';
            const konversi = parseFloat(opt.dataset.konversiPembelian) || 1.00;
            const satuanUtama = opt.dataset.satuanUtama || '';

            if (satuanPembelian && konversi > 1) {
                hint.textContent = `Satuan: ${satuanPembelian} (1 ${satuanPembelian} = ${Number(konversi).toLocaleString('id-ID')} ${satuanUtama})`;
            } else {
                hint.textContent = `Satuan: ${satuanUtama || 'Pcs'}`;
            }
        }

        document.addEventListener('change', function(e) {

            if (e.target.classList.contains('barang-select')) {
                const row = e.target.closest('.item-row');
                updateQtyHint(row);
            }

            if (
                e.target.classList.contains('barang-select') ||
                e.target.id === 'supplier_id' ||
                e.target.id === 'tanggal'
            ) {

                document.querySelectorAll('.item-row')
                    .forEach(row => {

                        generateBatchNumber(row);
                    });
            }
        });

        /*
        |--------------------------------------------------------------------------
        | AUTO HITUNG HARGA PER QTY
        |--------------------------------------------------------------------------
        */

        document.addEventListener('input', function(e) {

            if (
                e.target.classList.contains('qty-input') ||
                e.target.classList.contains('harga-input')
            ) {

                const row =
                    e.target.closest('.item-row');

                calculateHargaPerQty(row);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | MASK INDONESIAN NUMBER FORMAT ON TYPING
        |--------------------------------------------------------------------------
        */

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('mask-number')) {
                let cursorPosition = e.target.selectionStart;
                let originalLength = e.target.value.length;
                
                let formatted = formatNumberIndonesian(e.target.value);
                e.target.value = formatted;
                
                let newLength = formatted.length;
                e.target.selectionStart = cursorPosition + (newLength - originalLength);
                e.target.selectionEnd = cursorPosition + (newLength - originalLength);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | CLEAN MASK BEFORE SUBMIT
        |--------------------------------------------------------------------------
        */

        document.querySelector('form[action*="pembelian"]').addEventListener('submit', function (e) {
            document.querySelectorAll('.mask-number').forEach(input => {
                input.value = getCleanNumber(input.value);
            });
        });

        /*
        |--------------------------------------------------------------------------
        | SUGGESTION RESTOCK LOGIC
        |--------------------------------------------------------------------------
        */
        let currentSuggestions = [];

        function addBarisWithItem(barangId, qty, hppRef) {
            const tbody = document.querySelector('#table-items tbody');
            const rows = tbody.querySelectorAll('tr.item-row');

            // Cek apakah ada baris kosong pertama
            let targetRow = null;
            for (let r of rows) {
                let select = r.querySelector('.barang-select');
                let qtyInp = r.querySelector('.qty-input');
                if ((!select.value || select.value === '') && (!qtyInp.value || qtyInp.value === '')) {
                    targetRow = r;
                    break;
                }
            }

            if (!targetRow) {
                // Buat baris baru dengan menstimulasi tombol add
                document.getElementById('btn-add').click();
                const updatedRows = tbody.querySelectorAll('tr.item-row');
                targetRow = updatedRows[updatedRows.length - 1];
            }

            const barangSelect = targetRow.querySelector('.barang-select');
            barangSelect.value = barangId;
            updateQtyHint(targetRow);
            generateBatchNumber(targetRow);

            const qtyInput = targetRow.querySelector('.qty-input');
            qtyInput.value = formatNumberIndonesian(String(qty));

            // Jika ada HPP referensi, isi estimasi harga
            const hargaInput = targetRow.querySelector('.harga-input');
            if (hppRef && hppRef > 0) {
                const opt = barangSelect.options[barangSelect.selectedIndex];
                const konversi = parseFloat(opt.dataset.konversiPembelian) || 1;
                const totalHargaEstimasi = qty * hppRef * konversi;
                hargaInput.value = formatNumberIndonesian(String(Math.round(totalHargaEstimasi)));
            }

            calculateHargaPerQty(targetRow);
        }

        function fetchPembelianSuggestions() {
            const suggestionBox = document.getElementById('suggestion-box');
            const suggestionList = document.getElementById('suggestion-list');
            const toggleContainer = document.getElementById('toggle-suggestion-container');

            fetch("{{ route('pembelian.suggestions') }}")
                .then(r => r.json())
                .then(data => {
                    currentSuggestions = data.suggestions || [];
                    if (currentSuggestions.length > 0) {
                        toggleContainer.style.display = 'block';
                        suggestionBox.style.display = 'none'; // Keep hidden initially
                        suggestionList.innerHTML = '';
                        currentSuggestions.forEach(item => {
                            const pill = document.createElement('div');
                            pill.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded-3';
                            pill.dataset.barangId = item.barang_id;
                            pill.innerHTML = `
                                <div class="text-start">
                                    <div class="fw-bold">${item.nama} <span class="text-muted small">(${item.kode_barang})</span></div>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        Stok Utama: <span class="text-danger fw-bold">${item.current_stock}</span> / Min: <span class="fw-bold">${item.min_stock}</span> ${item.satuan}
                                        <span class="text-success fw-bold ms-1">&rarr; Saran: ${item.suggested_qty_pembelian} ${item.satuan_pembelian}</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold btn-add-single-suggest py-1 px-2" style="font-size: 0.75rem;" title="Tambah item ini">
                                    <i class="bi bi-plus-circle-fill"></i> Tambah
                                </button>
                            `;
                            pill.querySelector('.btn-add-single-suggest').addEventListener('click', function () {
                                addBarisWithItem(item.barang_id, item.suggested_qty_pembelian, item.hpp_referensi);
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

        document.getElementById('btn-apply-all-suggestions').addEventListener('click', function () {
            currentSuggestions.forEach(item => {
                addBarisWithItem(item.barang_id, item.suggested_qty_pembelian, item.hpp_referensi);
            });
            document.querySelectorAll('#suggestion-list .btn-add-single-suggest').forEach(btn => {
                btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Ditambahkan';
                btn.disabled = true;
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            fetchPembelianSuggestions();
            new TomSelect('#supplier_id', {
                create: false,
                placeholder: '-- Pilih Supplier --',
                allowEmptyOption: true,
            });
        });

    </script>
</x-app-layout>