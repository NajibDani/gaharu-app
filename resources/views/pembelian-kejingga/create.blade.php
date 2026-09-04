<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <style>
        .table-responsive {
            overflow: visible !important;
        }
        .ts-dropdown {
            z-index: 9999 !important;
        }
    </style>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0 fw-bold">Tambah Pembelian Kejingga (Luar Gaharu)</h4>
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">Gudang Tujuan: Gudang KeJingga</span>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('pembelian-kejingga.store') }}" method="POST">
            @csrf

            <div class="row mb-3">
                {{-- SUPPLIER --}}
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label class="form-label fw-bold small text-muted">Supplier / Pemasok Luar</label>
                    <select name="supplier_id" id="supplier_id" class="form-control" required>
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" data-nama="{{ strtoupper($supplier->nama) }}">
                                {{ $supplier->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- GUDANG --}}
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label class="form-label fw-bold small text-muted">Gudang Tujuan Stok</label>
                    <input type="text" class="form-control bg-light fw-bold text-warning" value="{{ $gudangKejingga->nama ?? 'Gudang KeJingga' }}" readonly>
                    <input type="hidden" name="gudang_id" value="5">
                </div>

                {{-- TANGGAL --}}
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label class="form-label fw-bold small text-muted">Tanggal Transaksi</label>
                    <input type="date" name="tanggal" id="tanggal" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <hr>

            <h5 class="fw-bold">Detail Barang Pembelian</h5>

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
                                <select name="items[0][barang_id]" class="form-control barang-select" required>
                                    <option value="">-- Pilih Barang --</option>
                                    @foreach($barangs as $barang)
                                        <option value="{{ $barang->id }}"
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
                                <input type="text" name="items[0][qty]" class="form-control qty-input mask-number" required>
                                <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;"></small>
                            </td>

                            {{-- TOTAL HARGA --}}
                            <td>
                                <input type="text" name="items[0][harga]" class="form-control harga-input mask-number" required>
                            </td>

                            {{-- HARGA PER QTY --}}
                            <td>
                                <input type="text" class="form-control harga-per-qty" readonly tabindex="-1">
                            </td>

                            {{-- BATCH --}}
                            <td>
                                <input type="text" class="form-control batch-number" readonly tabindex="-1">
                            </td>

                            {{-- AKSI --}}
                            <td>
                                <button type="button" class="btn btn-danger btn-sm btn-remove">X</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

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

            <div class="d-flex flex-column flex-sm-row gap-2 mt-3 mb-4">
                <button type="button" class="btn btn-secondary" id="btn-add">Tambah Baris</button>
                <button type="submit" class="btn btn-primary">Simpan Pembelian Kejingga</button>
                <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-light border">Kembali</a>
            </div>
        </form>
    </div>

    <script>
        let rowIndex = 1;

        function formatNumberDisplay(num) {
            if (!num && num !== 0) return '';
            const parts = num.toString().split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return parts.join(',');
        }

        function unformatNumber(str) {
            if (!str) return 0;
            return parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0;
        }

        function maskInput(input) {
            input.addEventListener('input', function() {
                let cursor = this.selectionStart;
                let originalLen = this.value.length;
                let raw = this.value.replace(/[^0-9,]/g, '');
                let parts = raw.split(',');
                if (parts.length > 2) raw = parts[0] + ',' + parts.slice(1).join('');
                if (parts[0]) parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                this.value = parts.join(',');
                let newLen = this.value.length;
                this.setSelectionRange(cursor + (newLen - originalLen), cursor + (newLen - originalLen));
            });
        }

        document.querySelectorAll('.mask-number').forEach(maskInput);

        function initTomSelect(el) {
            if (el.tomselect) return;
            new TomSelect(el, {
                create: false,
                sortField: { field: "text", direction: "asc" },
                placeholder: "-- Pilih --",
                allowEmptyOption: true,
                dropdownParent: 'body',
                maxOptions: 500,
            });
        }

        initTomSelect(document.getElementById('supplier_id'));
        document.querySelectorAll('.barang-select').forEach(initTomSelect);

        function calcRow(tr) {
            let qtyVal = unformatNumber(tr.querySelector('.qty-input').value);
            let hargaVal = unformatNumber(tr.querySelector('.harga-input').value);
            let perQtyInput = tr.querySelector('.harga-per-qty');
            if (qtyVal > 0 && hargaVal > 0) {
                let perQty = hargaVal / qtyVal;
                perQtyInput.value = 'Rp ' + formatNumberDisplay(Math.round(perQty));
            } else {
                perQtyInput.value = '';
            }
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input')) {
                calcRow(e.target.closest('tr'));
            }
        });

        document.getElementById('btn-add').addEventListener('click', function() {
            let tbody = document.querySelector('#table-items tbody');
            let tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
                <td>
                    <select name="items[${rowIndex}][barang_id]" class="form-control barang-select" required>
                        <option value="">-- Pilih Barang --</option>
                        @foreach($barangs as $barang)
                            <option value="{{ $barang->id }}"
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
                    <input type="text" name="items[${rowIndex}][qty]" class="form-control qty-input mask-number" required>
                    <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;"></small>
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][harga]" class="form-control harga-input mask-number" required>
                </td>
                <td>
                    <input type="text" class="form-control harga-per-qty" readonly tabindex="-1">
                </td>
                <td>
                    <input type="text" class="form-control batch-number" readonly tabindex="-1">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm btn-remove">X</button>
                </td>
            `;
            tbody.appendChild(tr);
            tr.querySelectorAll('.mask-number').forEach(maskInput);
            initTomSelect(tr.querySelector('.barang-select'));
            rowIndex++;
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove')) {
                let trs = document.querySelectorAll('#table-items tbody tr');
                if (trs.length > 1) {
                    e.target.closest('tr').remove();
                } else {
                    alert('Minimal 1 baris barang pembelian.');
                }
            }
        });
    </script>
</x-app-layout>

