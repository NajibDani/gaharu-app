<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <div class="container">
        <h4>Edit Pembelian</h4>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('pembelian.update', $pembelian->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row mb-3">
                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label>Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-control" required>
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                {{ $pembelian->supplier_id == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label>Gudang Tujuan</label>
                    <select name="gudang_id" class="form-control" required>
                        <option value="">-- Pilih Gudang --</option>
                        @foreach($gudangs as $gudang)
                            <option value="{{ $gudang->id }}"
                                {{ $pembelian->gudang_id == $gudang->id ? 'selected' : '' }}>
                                {{ $gudang->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 mb-3 mb-md-0">
                    <label>Tanggal</label>
                    <input 
                        type="date" 
                        name="tanggal" 
                        class="form-control" 
                        value="{{ \Carbon\Carbon::parse($pembelian->tanggal)->format('Y-m-d') }}" 
                        required
                    >
                </div>
            </div>

            <hr>

            <h5>Detail Barang</h5>

            <div class="table-responsive">
                <table class="table table-bordered align-middle" id="table-items">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th width="140">Qty</th>
                            <th width="160">Harga</th>
                            <th width="160">Batch Number</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pembelian->details as $index => $detail)
                            @php
                                $bItem = $detail->barang;
                                $sPembelian = $detail->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                                $konv = (float)($detail->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                                $sUtama = $bItem->satuan ?? 'Pcs';
                                $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);
                                $totalUtama = $detail->qty * $konv;
                            @endphp
                            <tr class="item-row">
                                <td>
                                    <select name="items[{{ $index }}][barang_id]" class="form-control barang-select" required>
                                        <option value="">-- Pilih Barang --</option>
                                        @foreach($barangs as $barang)
                                            <option value="{{ $barang->id }}"
                                                data-kode="{{ $barang->kode_barang }}"
                                                data-satuan-pembelian="{{ $barang->satuan_pembelian }}"
                                                data-konversi-pembelian="{{ $barang->konversi_pembelian }}"
                                                data-satuan-utama="{{ $barang->satuan }}"
                                                {{ $detail->barang_id == $barang->id ? 'selected' : '' }}>
                                                {{ $barang->kode_barang }} - {{ $barang->nama }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <td>
                                    <input 
                                        type="text" 
                                        name="items[{{ $index }}][qty]" 
                                        class="form-control qty-input mask-number" 
                                        value="{{ number_format($detail->qty, 2, ',', '.') }}"
                                        required
                                    >
                                    <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;">
                                        @if($hasKonv)
                                            Satuan: <strong>{{ $sPembelian }}</strong><br>
                                            <span class="text-primary">= {{ number_format($totalUtama, 2, ',', '.') }} {{ $sUtama }}</span> (1 {{ $sPembelian }} = {{ number_format($konv, 0, ',', '.') }} {{ $sUtama }})
                                        @else
                                            Satuan: <strong>{{ $sUtama }}</strong>
                                        @endif
                                    </small>
                                </td>

                                <td>
                                    <input 
                                        type="text" 
                                        name="items[{{ $index }}][harga]" 
                                        class="form-control harga-input mask-number" 
                                        value="{{ number_format($detail->harga, 2, ',', '.') }}"
                                        required
                                    >
                                </td>

                                <td>
                                    <input 
                                        type="text" 
                                        name="items[{{ $index }}][batch_number]" 
                                        class="form-control"
                                        value="{{ $detail->batch_number }}"
                                    >
                                </td>

                                <td>
                                    <button type="button" class="btn btn-danger btn-sm btn-remove">
                                        X
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-md-4 offset-md-8">
                    <div class="card border-0 shadow-sm p-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <label class="form-label fw-bold text-secondary small">Biaya Tambahan (Tax / Service / Ongkir)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-semibold text-muted">Rp</span>
                            <input type="text" name="tax_service" id="tax_service" class="form-control mask-number fw-bold text-end" value="{{ number_format($pembelian->tax_service ?? 0, 0, ',', '.') }}" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                <button type="button" class="btn btn-secondary" id="btn-add">
                    Tambah Baris
                </button>
                <button type="submit" class="btn btn-primary">
                    Update Pembelian
                </button>
                <a href="{{ route('pembelian.index') }}" class="btn btn-light border">
                    Kembali
                </a>
            </div>
        </form>
    </div>

    <script>
        let rowIndex = {{ $pembelian->details->count() }};

        function initBarangSelect(selectEl) {
            if (!selectEl || selectEl.tomselect) return;
            new TomSelect(selectEl, {
                create: false,
                placeholder: '-- Pilih Barang --',
                allowEmptyOption: true,
                maxOptions: 500,
                onChange: function(value) {
                    selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }

        document.getElementById('btn-add').addEventListener('click', function () {
            const tbody = document.querySelector('#table-items tbody');

            const row = `
                <tr class="item-row">
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
                        <input 
                            type="text" 
                            name="items[${rowIndex}][qty]" 
                            class="form-control qty-input mask-number" 
                            required
                        >
                        <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;"></small>
                    </td>

                    <td>
                        <input 
                            type="text" 
                            name="items[${rowIndex}][harga]" 
                            class="form-control harga-input mask-number" 
                            required
                        >
                    </td>

                    <td>
                        <input 
                            type="text" 
                            name="items[${rowIndex}][batch_number]" 
                            class="form-control"
                        >
                    </td>

                    <td>
                        <button type="button" class="btn btn-danger btn-sm btn-remove">
                            X
                        </button>
                    </td>
                </tr>
            `;

            tbody.insertAdjacentHTML('beforeend', row);

            const newRow = tbody.lastElementChild;
            const newSelect = newRow.querySelector('.barang-select');
            initBarangSelect(newSelect);

            rowIndex++;
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-remove')) {
                const rows = document.querySelectorAll('#table-items tbody tr');

                if (rows.length > 1) {
                    e.target.closest('tr').remove();
                } else {
                    alert('Minimal harus ada 1 barang.');
                }
            }
        });

        function updateQtyHint(row) {
            const select = row.querySelector('.barang-select');
            const qtyInput = row.querySelector('.qty-input');
            const hint = row.querySelector('.qty-hint');
            if (!select || !hint) return;

            const opt = select.querySelector(`option[value="${select.value}"]`);
            if (!opt || select.value === '') {
                hint.textContent = '';
                return;
            }

            const satuanPembelian = opt.dataset.satuanPembelian || '';
            const konversi = parseFloat(opt.dataset.konversiPembelian) || 1.00;
            const satuanUtama = opt.dataset.satuanUtama || 'Pcs';
            const qtyVal = getCleanNumber(qtyInput ? qtyInput.value : 0);

            if (satuanPembelian && konversi > 1 && satuanPembelian !== satuanUtama) {
                const totalUtama = qtyVal * konversi;
                hint.innerHTML = `Satuan: <strong>${satuanPembelian}</strong><br><span class="text-primary">= ${Number(totalUtama).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2})} ${satuanUtama}</span> (1 ${satuanPembelian} = ${Number(konversi).toLocaleString('id-ID')} ${satuanUtama})`;
            } else {
                hint.innerHTML = `Satuan: <strong>${satuanUtama}</strong>`;
            }
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('barang-select')) {
                const row = e.target.closest('.item-row');
                if (row) updateQtyHint(row);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | MASK INDONESIAN NUMBER FORMAT
        |--------------------------------------------------------------------------
        */

        function getCleanNumber(val) {
            if (!val) return 0;
            let clean = String(val).replace(/\./g, '').replace(/,/g, '.');
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

            if (e.target.classList.contains('qty-input')) {
                const row = e.target.closest('.item-row');
                if (row) updateQtyHint(row);
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

        document.addEventListener('DOMContentLoaded', function () {
            new TomSelect('#supplier_id', {
                create: false,
                placeholder: '-- Pilih Supplier --',
                allowEmptyOption: true,
            });
            document.querySelectorAll('.barang-select').forEach(select => {
                initBarangSelect(select);
            });
            document.querySelectorAll('.item-row').forEach(row => {
                updateQtyHint(row);
            });
        });
    </script>
</x-app-layout>