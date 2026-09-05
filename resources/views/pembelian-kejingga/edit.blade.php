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
        .form-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            background: #fff;
        }
    </style>

    <div class="container-fluid px-4 py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="m-0 fw-bold text-dark">Edit Pembelian Kejingga ({{ $pembelian->kode_pembelian }})</h4>
                <p class="text-muted small mb-0">Ubah item barang, supplier, tanggal, atau harga pembelian.</p>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">Gudang Tujuan: Gudang KeJingga</span>
        </div>

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ route('pembelian-kejingga.update', $pembelian->id) }}" method="POST" id="formEditPO">
            @csrf
            @method('PUT')

            <div class="card form-card p-3 mb-4">
                <div class="row g-3">
                    {{-- SUPPLIER --}}
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Supplier / Pemasok Luar 
                            <span class="text-muted font-normal">(Opsional - Kosongkan jika Draft Permintaan Staff)</span>
                        </label>
                        <select name="supplier_id" id="supplier_id" class="form-control">
                            <option value="">-- Kosongkan (Draft Permintaan) --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id', $pembelian->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->nama }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                            <i class="bi bi-info-circle me-1"></i>Tim Purchasing dapat melengkapi supplier di sini.
                        </small>
                    </div>

                    {{-- GUDANG --}}
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold small text-dark mb-1">Gudang Tujuan Stok</label>
                        <input type="text" class="form-control bg-light fw-bold text-warning" value="{{ $gudangKejingga->nama ?? 'Gudang KeJingga' }}" readonly>
                        <input type="hidden" name="gudang_id" value="5">
                    </div>

                    {{-- TANGGAL --}}
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold small text-dark mb-1">Tanggal Transaksi <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" id="tanggal" class="form-control" value="{{ old('tanggal', \Carbon\Carbon::parse($pembelian->tanggal)->format('Y-m-d')) }}" required>
                    </div>
                </div>
            </div>

            <div class="card form-card p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0 text-dark">Detail Barang Pembelian &amp; Konversi Satuan</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btn-add">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Baris Barang
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="table-items">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Barang &amp; Stok Gudang Kejingga</th>
                                <th width="140">Qty Input</th>
                                <th width="180">Pilihan Satuan</th>
                                <th width="160">Total Qty (Utama)</th>
                                <th width="170">Total Harga (Rp)</th>
                                <th width="150">Harga / Satuan</th>
                                <th width="70" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pembelian->details as $idx => $detail)
                                @php
                                    $bItem = $detail->barang;
                                    $sPembelian = $detail->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                                    $konv = (float)($detail->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                                    $sUtama = $bItem->satuan ?? 'Pcs';
                                    $stokKejinggaVal = (float)($stokKejinggaMap[$detail->barang_id] ?? 0);
                                @endphp
                                <tr class="item-row">
                                    {{-- BARANG --}}
                                    <td>
                                        <select name="items[{{ $idx }}][barang_id]" class="form-control barang-select" required>
                                            <option value="">-- Pilih / Cari Barang --</option>
                                            @foreach($barangs as $barang)
                                                <option value="{{ $barang->id }}"
                                                        data-kode="{{ $barang->kode_barang }}"
                                                        data-nama="{{ $barang->nama }}"
                                                        data-satuan-utama="{{ $barang->satuan }}"
                                                        data-satuan-pembelian="{{ $barang->satuan_pembelian }}"
                                                        data-konversi-pembelian="{{ $barang->konversi_pembelian ?? 1 }}"
                                                        data-stok-kejingga="{{ $barang->stok_kejingga }}"
                                                        {{ $detail->barang_id == $barang->id ? 'selected' : '' }}>
                                                    {{ $barang->kode_barang }} - {{ $barang->nama }} (Stok Kejingga: {{ number_format($barang->stok_kejingga, 2, ',', '.') }} {{ $barang->satuan }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="stok-info-badge mt-1" style="font-size: 11px;">
                                            <span class="badge bg-warning text-dark border">
                                                <i class="bi bi-box-seam me-1"></i>Persediaan Terkini Gudang Kejingga: 
                                                <strong>{{ number_format($stokKejinggaVal, 2, ',', '.') }} {{ $sUtama }}</strong>
                                            </span>
                                        </div>
                                    </td>

                                    {{-- QTY INPUT --}}
                                    <td>
                                        <input type="text" name="items[{{ $idx }}][qty]" class="form-control qty-input mask-number" value="{{ number_format($detail->qty, 0, ',', '.') }}" placeholder="0" required>
                                    </td>

                                    {{-- PILIHAN SATUAN --}}
                                    <td>
                                        <select name="items[{{ $idx }}][satuan_pembelian]" class="form-select satuan-select">
                                            <option value="{{ $sUtama }}" data-konversi="1" {{ $sPembelian === $sUtama ? 'selected' : '' }}>
                                                {{ $sUtama }} (Satuan Utama)
                                            </option>
                                            @if($bItem->satuan_pembelian && $bItem->konversi_pembelian > 1 && $bItem->satuan_pembelian !== $sUtama)
                                                <option value="{{ $bItem->satuan_pembelian }}" data-konversi="{{ $bItem->konversi_pembelian }}" {{ $sPembelian === $bItem->satuan_pembelian ? 'selected' : '' }}>
                                                    {{ $bItem->satuan_pembelian }} (1 {{ $bItem->satuan_pembelian }} = {{ number_format($bItem->konversi_pembelian, 0, ',', '.') }} {{ $sUtama }})
                                                </option>
                                            @endif
                                        </select>
                                        <input type="hidden" name="items[{{ $idx }}][konversi_pembelian]" class="konversi-input" value="{{ $konv }}">
                                    </td>

                                    {{-- TOTAL QTY UTAMA & CONVERSION INFO --}}
                                    <td>
                                        <div class="fw-bold text-dark total-qty-display">
                                            {{ number_format($detail->qty * $konv, 2, ',', '.') }} {{ $sUtama }}
                                        </div>
                                        <small class="text-muted konversi-info-text d-block" style="font-size: 10px;">
                                            @if($konv > 1)
                                                ({{ number_format($detail->qty, 0, ',', '.') }} {{ $sPembelian }} @ {{ number_format($konv, 0, ',', '.') }} {{ $sUtama }})
                                            @else
                                                ({{ number_format($detail->qty, 0, ',', '.') }} {{ $sUtama }})
                                            @endif
                                        </small>
                                    </td>

                                    {{-- TOTAL HARGA --}}
                                    <td>
                                        <input type="text" name="items[{{ $idx }}][harga]" class="form-control harga-input mask-number" value="{{ number_format($detail->harga, 0, ',', '.') }}" placeholder="0 (Opsional)">
                                    </td>

                                    {{-- HARGA PER QTY --}}
                                    <td>
                                        <input type="text" class="form-control harga-per-qty bg-light" readonly tabindex="-1" value="{{ $detail->harga > 0 ? 'Rp ' . number_format($detail->harga_per_qty, 0, ',', '.') . ' / ' . ($sPembelian ?: $sUtama) : '—' }}">
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove" title="Hapus Baris"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-12 col-md-5 offset-md-7">
                        <div class="card border-0 bg-light p-3" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                            <label class="form-label fw-bold text-secondary small mb-1">Biaya Tambahan (Tax / Service / Ongkir)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white fw-semibold text-muted">Rp</span>
                                <input type="text" name="tax_service" id="tax_service" class="form-control mask-number fw-bold text-end" value="{{ old('tax_service', number_format($pembelian->tax_service ?? 0, 0, ',', '.')) }}" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 mb-4">
                <button type="submit" class="btn btn-warning text-dark px-4 py-2 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Perbarui Pembelian Kejingga
                </button>
                <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-light border px-4 py-2">Batal</a>
            </div>
        </form>
    </div>

    <script>
        const barangsMap = {
            @foreach($barangs as $b)
                "{{ $b->id }}": {
                    id: {{ $b->id }},
                    kode: "{{ addslashes($b->kode_barang) }}",
                    nama: "{{ addslashes($b->nama) }}",
                    satuan_utama: "{{ addslashes($b->satuan) }}",
                    satuan_pembelian: "{{ addslashes($b->satuan_pembelian ?? '') }}",
                    konversi_pembelian: {{ (float)($b->konversi_pembelian ?? 1) }},
                    stok_kejingga: {{ (float)($b->stok_kejingga ?? 0) }}
                },
            @endforeach
        };

        let rowIndex = {{ count($pembelian->details) }};

        function formatNumberDisplay(num) {
            if (!num && num !== 0) return '';
            const parts = num.toString().split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return parts.join(',');
        }

        function unformatNumber(str) {
            if (!str) return 0;
            return parseFloat(str.toString().replace(/\./g, '').replace(',', '.')) || 0;
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
            const ts = new TomSelect(el, {
                create: false,
                sortField: { field: "text", direction: "asc" },
                placeholder: "-- Pilih / Cari Barang --",
                allowEmptyOption: true,
                dropdownParent: 'body',
                maxOptions: 500,
                onChange: function(val) {
                    let tr = el.closest('tr');
                    updateRowBarang(tr, val);
                }
            });
        }

        function updateRowBarang(tr, barangId) {
            const b = barangsMap[barangId];
            const badgeDiv = tr.querySelector('.stok-info-badge');
            const selectSatuan = tr.querySelector('.satuan-select');
            
            if (!b) {
                badgeDiv.innerHTML = '';
                selectSatuan.innerHTML = '<option value="">-- Pilih Satuan --</option>';
                tr.querySelector('.konversi-input').value = 1;
                calcRow(tr);
                return;
            }

            badgeDiv.innerHTML = `
                <span class="badge bg-warning text-dark border">
                    <i class="bi bi-box-seam me-1"></i>Persediaan Terkini Gudang Kejingga: 
                    <strong>${b.stok_kejingga.toLocaleString('id-ID')} ${b.satuan_utama}</strong>
                </span>
            `;

            let opts = `<option value="${b.satuan_utama}" data-konversi="1">${b.satuan_utama} (Satuan Utama)</option>`;
            if (b.satuan_pembelian && b.konversi_pembelian > 1 && b.satuan_pembelian !== b.satuan_utama) {
                opts += `<option value="${b.satuan_pembelian}" data-konversi="${b.konversi_pembelian}">${b.satuan_pembelian} (1 ${b.satuan_pembelian} = ${b.konversi_pembelian.toLocaleString('id-ID')} ${b.satuan_utama})</option>`;
            }

            selectSatuan.innerHTML = opts;
            selectSatuan.selectedIndex = (b.satuan_pembelian && b.konversi_pembelian > 1) ? 1 : 0;
            
            let selectedOpt = selectSatuan.options[selectSatuan.selectedIndex];
            let konvVal = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-konversi')) || 1 : 1;
            tr.querySelector('.konversi-input').value = konvVal;

            calcRow(tr);
        }

        function calcRow(tr) {
            const barangSelect = tr.querySelector('.barang-select');
            const barangId = barangSelect ? (barangSelect.tomselect ? barangSelect.tomselect.getValue() : barangSelect.value) : '';
            const b = barangsMap[barangId];

            const qtyInput = tr.querySelector('.qty-input');
            const qtyVal = unformatNumber(qtyInput.value);

            const selectSatuan = tr.querySelector('.satuan-select');
            const selectedOpt = selectSatuan ? selectSatuan.options[selectSatuan.selectedIndex] : null;
            const konvVal = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-konversi')) || 1 : 1;
            tr.querySelector('.konversi-input').value = konvVal;

            const hargaInput = tr.querySelector('.harga-input');
            const hargaVal = unformatNumber(hargaInput.value);

            const totalQtyDisplay = tr.querySelector('.total-qty-display');
            const konversiInfoText = tr.querySelector('.konversi-info-text');
            const perQtyInput = tr.querySelector('.harga-per-qty');

            if (b && qtyVal > 0) {
                const totalMainQty = qtyVal * konvVal;
                const unitChosen = selectSatuan ? selectSatuan.value : b.satuan_utama;
                
                totalQtyDisplay.innerHTML = `${formatNumberDisplay(totalMainQty)} ${b.satuan_utama}`;

                if (konvVal > 1) {
                    konversiInfoText.innerHTML = `(${formatNumberDisplay(qtyVal)} ${unitChosen} @ ${formatNumberDisplay(konvVal)} ${b.satuan_utama})`;
                } else {
                    konversiInfoText.innerHTML = `(${formatNumberDisplay(qtyVal)} ${b.satuan_utama})`;
                }

                if (hargaVal > 0) {
                    let perQty = hargaVal / qtyVal;
                    perQtyInput.value = 'Rp ' + formatNumberDisplay(Math.round(perQty)) + ' / ' + unitChosen;
                } else {
                    perQtyInput.value = '—';
                }
            } else {
                totalQtyDisplay.innerHTML = '—';
                konversiInfoText.innerHTML = '';
                perQtyInput.value = '—';
            }
        }

        document.getElementById('supplier_id') && initTomSelect(document.getElementById('supplier_id'));
        document.querySelectorAll('.barang-select').forEach(initTomSelect);

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input')) {
                calcRow(e.target.closest('tr'));
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('satuan-select')) {
                let tr = e.target.closest('tr');
                let selectedOpt = e.target.options[e.target.selectedIndex];
                let konvVal = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-konversi')) || 1 : 1;
                tr.querySelector('.konversi-input').value = konvVal;
                calcRow(tr);
            }
        });

        document.getElementById('btn-add').addEventListener('click', function() {
            let tbody = document.querySelector('#table-items tbody');
            let tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
                <td>
                    <select name="items[${rowIndex}][barang_id]" class="form-control barang-select" required>
                        <option value="">-- Pilih / Cari Barang --</option>
                        @foreach($barangs as $barang)
                            <option value="{{ $barang->id }}"
                                    data-kode="{{ $barang->kode_barang }}"
                                    data-nama="{{ $barang->nama }}"
                                    data-satuan-utama="{{ $barang->satuan }}"
                                    data-satuan-pembelian="{{ $barang->satuan_pembelian }}"
                                    data-konversi-pembelian="{{ $barang->konversi_pembelian ?? 1 }}"
                                    data-stok-kejingga="{{ $barang->stok_kejingga }}">
                                {{ $barang->kode_barang }} - {{ $barang->nama }} (Stok Kejingga: {{ number_format($barang->stok_kejingga, 2, ',', '.') }} {{ $barang->satuan }})
                            </option>
                        @endforeach
                    </select>
                    <div class="stok-info-badge mt-1" style="font-size: 11px;"></div>
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][qty]" class="form-control qty-input mask-number" placeholder="0" required>
                </td>
                <td>
                    <select name="items[${rowIndex}][satuan_pembelian]" class="form-select satuan-select">
                        <option value="">-- Pilih Satuan --</option>
                    </select>
                    <input type="hidden" name="items[${rowIndex}][konversi_pembelian]" class="konversi-input" value="1">
                </td>
                <td>
                    <div class="fw-bold text-dark total-qty-display">—</div>
                    <small class="text-muted konversi-info-text d-block" style="font-size: 10px;"></small>
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][harga]" class="form-control harga-input mask-number" placeholder="0 (Opsional)">
                    <small class="text-muted" style="font-size: 10px;">Bisa diisi nanti oleh Purchasing</small>
                </td>
                <td>
                    <input type="text" class="form-control harga-per-qty bg-light" readonly tabindex="-1" placeholder="—">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove" title="Hapus Baris"><i class="bi bi-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
            tr.querySelectorAll('.mask-number').forEach(maskInput);
            initTomSelect(tr.querySelector('.barang-select'));
            rowIndex++;
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove')) {
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
