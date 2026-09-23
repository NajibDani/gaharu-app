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
                <h4 class="fw-bold text-dark mb-1">Edit Permintaan Cold Kitchen</h4>
                <p class="text-muted small mb-0">Perbarui data pengajuan permintaan: <span class="fw-bold text-primary">#{{ $pesanan->kode_pesanan }}</span></p>
            </div>
            <a href="{{ route('pesanan.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
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

        <form action="{{ route('pesanan.update', $pesanan->id) }}" method="POST" id="form-cold-order">
            @csrf
            @method('PUT')

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Permintaan</h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Outlet / Pemesan <span class="text-danger">*</span></label>
                        <select name="customer_id" id="select-customer" class="form-select text-sm rounded-3" required>
                            <option value="">-- Pilih Outlet / Pemesan --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ (old('customer_id', $pesanan->customer_id) == $c->id) ? 'selected' : '' }}>
                                    {{ $c->nama }}
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

                    <div class="col-md-6">
                        <label class="form-label form-label-custom">Kode Permintaan</label>
                        <input type="text" name="kode_pesanan" class="form-control text-sm rounded-3 bg-light" value="{{ $pesanan->kode_pesanan }}" readonly>
                    </div>
                </div>
            </div>

            <div class="card card-form p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Daftar Item Barang (Bahan Setengah Jadi &amp; Bahan Jadi)</h6>
                        <small class="text-muted">Pilih produk dan masukkan kuantitas dalam satuan Resep, Konversi Box/Pack, atau Satuan standar</small>
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
                                <th style="width: 20%;">Mode Satuan</th>
                                <th style="width: 28%;">Total Qty (Konversi)</th>
                                <th style="width: 5%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="item-rows">
                            @forelse($pesanan->details as $det)
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
                                                        data-konversi="{{ $konversiVal }}"
                                                        {{ $det->produk_id == $item->id ? 'selected' : '' }}>
                                                    [{{ $tipeBadge }}] {{ $item->kode_barang }} - {{ $item->nama }}
                                                    @if($satuanKonversi && $konversiVal > 1)
                                                        (1 {{ $satuanKonversi }} = {{ number_format($konversiVal, 0, ',', '.') }} {{ $item->satuan }})
                                                    @elseif($outQty > 0)
                                                        (1 Resep = {{ number_format($outQty, 0, ',', '.') }} {{ $outSatuan }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="0.01" name="qty[]" class="form-control text-sm input-qty text-end fw-bold" value="{{ (float)$det->qty }}" placeholder="0" required>
                                        <input type="hidden" name="harga[]" value="{{ (float)($det->harga ?? 0) }}">
                                        <input type="hidden" name="subtotal[]" value="{{ (float)($det->subtotal ?? 0) }}">
                                    </td>
                                    <td>
                                        <select name="order_mode[]" class="form-select text-sm select-mode text-center fw-bold">
                                            <option value="satuan" selected>Satuan</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="konversi-info small text-start p-1 px-2 bg-light rounded border">
                                            <span class="text-success"><strong>{{ number_format($det->qty, 0, ',', '.') }} {{ $det->produk->satuan ?? 'pcs' }}</strong> (Satuan Langsung)</span>
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
            if (document.getElementById('select-customer')) {
                new TomSelect('#select-customer', {
                    create: false,
                    placeholder: '-- Pilih Outlet / Pemesan --',
                    allowEmptyOption: true
                });
            }

            function initProductSelect(el) {
                if (el.tomselect) return;
                var ts = new TomSelect(el, {
                    create: false,
                    placeholder: '-- Cari / Pilih Barang Cold Kitchen --',
                    allowEmptyOption: true,
                    onChange: function() {
                        var row = el.closest('tr');
                        updateRowModeOptions(row);
                        updateRowCalculation(row);
                    }
                });
                el._tsInstance = ts;
            }

            function updateRowModeOptions(row) {
                var selectMode = row.querySelector('.select-mode');
                var selectProd = row.querySelector('.select-produk');
                if (!selectProd || !selectMode) return;

                var opt = selectProd.options[selectProd.selectedIndex];
                if (!opt || !opt.value) {
                    selectMode.innerHTML = '<option value="satuan">Satuan</option>';
                    return;
                }

                var outQty = parseFloat(opt.getAttribute('data-output-qty')) || 0;
                var outSat = opt.getAttribute('data-satuan-output') || '';
                var satUtama = opt.getAttribute('data-satuan') || 'pcs';
                var satKonv = opt.getAttribute('data-satuan-konversi') || '';
                var konvVal = parseFloat(opt.getAttribute('data-konversi')) || 1;

                var html = '';
                if (outQty > 0) {
                    html += `<option value="resep">Resep (@ ${outQty} ${outSat})</option>`;
                }
                if (satKonv && konvVal > 1) {
                    html += `<option value="konversi">${satKonv} (@ ${konvVal} ${satUtama})</option>`;
                }
                html += `<option value="satuan" selected>Satuan (${satUtama})</option>`;

                selectMode.innerHTML = html;
            }

            function updateRowCalculation(row) {
                var selectProd = row.querySelector('.select-produk');
                var selectMode = row.querySelector('.select-mode');
                var inputQty = row.querySelector('.input-qty');
                var infoDiv = row.querySelector('.konversi-info');

                if (!selectProd || !selectMode || !inputQty || !infoDiv) return;

                var opt = selectProd.options[selectProd.selectedIndex];
                var rawQty = parseFloat(inputQty.value) || 0;

                if (!opt || !opt.value || rawQty <= 0) {
                    infoDiv.innerHTML = '<span class="text-muted">-</span>';
                    return;
                }

                var satUtama = opt.getAttribute('data-satuan') || 'pcs';
                var mode = selectMode.value;
                var finalQty = rawQty;
                var desc = '';

                if (mode === 'resep') {
                    var outQty = parseFloat(opt.getAttribute('data-output-qty')) || 1;
                    var outSat = opt.getAttribute('data-satuan-output') || satUtama;
                    finalQty = rawQty * outQty;
                    desc = `<strong>${finalQty.toLocaleString('id-ID')} ${outSat}</strong> (${rawQty} Resep &times; ${outQty} ${outSat})`;
                } else if (mode === 'konversi') {
                    var konvVal = parseFloat(opt.getAttribute('data-konversi')) || 1;
                    var satKonv = opt.getAttribute('data-satuan-konversi') || '';
                    finalQty = rawQty * konvVal;
                    desc = `<strong>${finalQty.toLocaleString('id-ID')} ${satUtama}</strong> (${rawQty} ${satKonv} &times; ${konvVal} ${satUtama})`;
                } else {
                    desc = `<strong>${finalQty.toLocaleString('id-ID')} ${satUtama}</strong> (Satuan Langsung)`;
                }

                infoDiv.innerHTML = `<span class="text-success">${desc}</span>`;
            }

            document.querySelectorAll('.select-produk').forEach(function(el) {
                initProductSelect(el);
            });

            document.getElementById('table-items').addEventListener('input', function(e) {
                if (e.target.classList.contains('input-qty')) {
                    updateRowCalculation(e.target.closest('tr'));
                }
            });

            document.getElementById('table-items').addEventListener('change', function(e) {
                if (e.target.classList.contains('select-mode')) {
                    updateRowCalculation(e.target.closest('tr'));
                }
            });

            document.getElementById('btn-add-item').addEventListener('click', function() {
                var tbody = document.getElementById('item-rows');
                var firstRow = tbody.querySelector('tr');
                var newRow = firstRow.cloneNode(true);

                // Clear input values
                newRow.querySelector('.input-qty').value = '';
                newRow.querySelector('.konversi-info').innerHTML = '<span class="text-muted">-</span>';

                // Remove existing TomSelect container if present
                var tsControl = newRow.querySelector('.ts-wrapper');
                if (tsControl) tsControl.remove();

                var selectProd = newRow.querySelector('.select-produk');
                selectProd.style.display = '';
                selectProd.classList.remove('tomselected', 'ts-hidden-accessible');
                selectProd.selectedIndex = 0;

                tbody.appendChild(newRow);
                initProductSelect(selectProd);
                updateRowModeOptions(newRow);

                // Enable remove buttons if >1 rows
                var rows = tbody.querySelectorAll('tr');
                rows.forEach(function(r) {
                    var btn = r.querySelector('.btn-remove-row');
                    if (btn) btn.disabled = (rows.length === 1);
                });
            });

            document.getElementById('table-items').addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-remove-row');
                if (!btn) return;
                var tbody = document.getElementById('item-rows');
                var rows = tbody.querySelectorAll('tr');
                if (rows.length > 1) {
                    btn.closest('tr').remove();
                    var remaining = tbody.querySelectorAll('tr');
                    remaining.forEach(function(r) {
                        var b = r.querySelector('.btn-remove-row');
                        if (b) b.disabled = (remaining.length === 1);
                    });
                }
            });
        });
    </script>
    @endpush
</x-app-layout>