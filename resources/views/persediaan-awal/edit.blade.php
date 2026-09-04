<x-app-layout>
    <x-slot name="header">
        Edit Persediaan Awal
    </x-slot>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Transaksi: {{ $persediaanAwal->kode_transaksi }}
                    </h5>
                    <small class="text-muted">Koreksi kuantitas dan harga saldo awal persediaan barang (Khusus Super Admin)</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('persediaan-awal.show', $persediaanAwal->id) }}" class="btn btn-sm btn-outline-secondary rounded-2 px-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('persediaan-awal.update', $persediaanAwal->id) }}" method="POST" id="formEditPersediaanAwal">
            @csrf
            @method('PUT')
            <div class="card-body p-4">
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <strong>Terjadi Kesalahan:</strong>
                        <ul class="mb-0 mt-1 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="alert alert-warning py-2 px-3 mb-4 rounded-3 small d-flex align-items-center">
                    <i class="bi bi-shield-lock-fill text-warning me-2 fs-5"></i>
                    <div>
                        <strong>Mode Koreksi Super Admin:</strong> Anda dapat mengubah Qty dan Harga Satuan. Setelah disimpan, sistem akan secara otomatis menyesuaikan kembali posisi fisik stok gudang, batch FIFO, dan jurnal penyesuaian terkait.
                    </div>
                </div>

                <!-- SECTION 1: HEADER TRANSAKSI -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-geo-alt-fill me-1 text-primary"></i> 1. Informasi Gudang & Tanggal Transaksi
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Gudang</label>
                            <input type="text" class="form-control bg-white" value="{{ $persediaanAwal->gudang->nama ?? '-' }} ({{ $persediaanAwal->gudang->kategori ?? '-' }})" readonly disabled>
                            <small class="text-muted" style="font-size: 11px;">Gudang tidak dapat dipindah pada form edit.</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Divisi Gudang</label>
                            <input type="text" class="form-control bg-white" value="{{ $persediaanAwal->divisi->nama ?? 'Tanpa Divisi (Umum)' }}" readonly disabled>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Tanggal Saldo Awal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" id="tanggalInput" class="form-control custom-input" value="{{ old('tanggal', $persediaanAwal->tanggal->format('Y-m-d')) }}" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Keterangan / Catatan</label>
                            <input type="text" name="keterangan" class="form-control custom-input" placeholder="Keterangan transaksi" value="{{ old('keterangan', $persediaanAwal->keterangan) }}">
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: TOOLBAR & TAMBAH BARANG -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="dropdown">
                            <button class="btn btn-sm text-white dropdown-toggle rounded-2 px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #d88656; border: none;">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Item Barang Lain
                            </button>
                            <div class="dropdown-menu p-3 shadow-lg" style="width: 320px; max-height: 380px; overflow-y: auto;">
                                <h6 class="dropdown-header px-0 fw-bold text-dark">Pilih Barang Master:</h6>
                                <input type="text" id="searchDropdownBarang" class="form-control form-control-sm mb-2" placeholder="Cari nama barang...">
                                <div id="dropdownBarangList">
                                    @foreach($allBarang as $b)
                                        <button type="button" class="dropdown-item py-2 px-2 border-bottom btn-add-item" 
                                            data-id="{{ $b->id }}"
                                            data-kode="{{ $b->kode_barang }}"
                                            data-nama="{{ $b->nama }}"
                                            data-kategori="{{ $b->kategori->nama ?? '-' }}"
                                            data-kategori-id="{{ $b->kategori_id }}"
                                            data-satuan-stok="{{ $b->satuan ?: 'pcs' }}"
                                            data-satuan-beli="{{ $b->satuan_pembelian ?: ($b->satuan ?: 'pcs') }}"
                                            data-konversi="{{ (float)($b->konversi_pembelian ?: 1.00) }}"
                                            data-harga-stok-utama="{{ (float)($hargaUtamaMap[$b->id] ?? ($b->hpp_referensi ?? 0)) }}"
                                            data-harga-beli-utama="{{ (float)(($hargaUtamaMap[$b->id] ?? ($b->hpp_referensi ?? 0)) * ((float)($b->konversi_pembelian ?: 1.00))) }}">
                                            <div class="fw-bold small text-truncate">{{ $b->nama }}</div>
                                            <div class="text-muted" style="font-size: 11px;">{{ $b->kode_barang }} &bull; {{ $b->kategori->nama ?? '-' }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3 rounded-pill" id="badgeTotalRows">
                            {{ count($detailsData) }} barang di daftar
                        </span>
                    </div>

                    <!-- Search Filter in Table -->
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchBarangTabel" class="form-control" placeholder="Cari di tabel edit...">
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: TABEL DAFTAR BARANG YANG DIEDIT -->
                <div class="table-responsive border rounded-3 mb-4" style="max-height: 560px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 text-center" id="tableBarang">
                        <thead class="table-light sticky-top" style="z-index: 2;">
                            <tr>
                                <th style="width: 45px;">No</th>
                                <th class="text-start" style="width: 110px;">Kode</th>
                                <th class="text-start" style="min-width: 160px;">Nama Barang</th>
                                <th style="width: 110px;">Kategori</th>
                                <th style="width: 130px;">Satuan & Konversi</th>
                                <th style="width: 130px;">Qty Input <span class="text-danger">*</span></th>
                                <th style="width: 135px;">Satuan Input <span class="text-danger">*</span></th>
                                <th style="width: 165px;">Harga per Satuan Input (Rp) <span class="text-danger">*</span></th>
                                <th style="width: 155px;">Masuk ke Stok Utama</th>
                                <th class="text-end" style="width: 140px;">Subtotal Nilai (Rp)</th>
                                <th style="width: 45px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyBarang">
                            @forelse($detailsData as $idx => $item)
                                @php
                                    $konversi = (float)($item['konversi_pembelian'] ?: 1.00);
                                    if ($konversi <= 0) $konversi = 1.00;
                                    $hasKonversi = $item['satuan_pembelian'] && $konversi > 1 && ($item['satuan_pembelian'] !== $item['satuan']);
                                    $selectedUnit = $item['satuan_tipe'] ?? ($hasKonversi ? 'pembelian' : 'utama');
                                    $subtotal = $item['qty_input'] * $item['harga_input'];
                                @endphp
                                <tr data-id="{{ $item['barang_id'] }}"
                                    data-kategori-id="{{ $item['kategori_id'] ?? '' }}"
                                    data-nama="{{ strtolower($item['nama']) }}"
                                    data-kode="{{ strtolower($item['kode_barang']) }}"
                                    data-satuan-stok="{{ $item['satuan'] }}"
                                    data-satuan-beli="{{ $item['satuan_pembelian'] }}"
                                    data-konversi="{{ $konversi }}"
                                    data-harga-stok-utama="{{ $item['harga_stok_utama'] }}"
                                    data-harga-beli-utama="{{ $item['harga_beli_utama'] }}"
                                    class="{{ $item['qty_input'] > 0 ? 'table-success bg-opacity-10' : '' }}">
                                    <td class="text-center text-muted row-number">{{ $idx + 1 }}</td>
                                    <td class="text-start font-monospace fw-bold">{{ $item['kode_barang'] }}</td>
                                    <td class="text-start">
                                        <div class="fw-semibold text-dark">{{ $item['nama'] }}</div>
                                        <input type="hidden" name="barang_id[]" value="{{ $item['barang_id'] }}">
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $item['kategori_nama'] }}</span></td>
                                    <td>
                                        @if($hasKonversi)
                                            <div><span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold mb-1">{{ $item['satuan_pembelian'] }}</span></div>
                                            <small class="text-muted d-block" style="font-size: 11px;">1 {{ $item['satuan_pembelian'] }} = {{ number_format($konversi, 0, ',', '.') }} {{ $item['satuan'] }}</small>
                                        @else
                                            <span class="badge bg-light text-dark border">{{ $item['satuan'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="number" name="qty[]" class="form-control text-center input-qty fw-bold" step="any" min="0" value="{{ $item['qty_input'] }}" placeholder="0">
                                    </td>
                                    <td>
                                        <select name="satuan_tipe[]" class="form-select form-select-sm input-satuan fw-semibold" style="border-radius: 6px; font-size: 12px;">
                                            @if($hasKonversi)
                                                <option value="pembelian" {{ $selectedUnit === 'pembelian' ? 'selected' : '' }}>{{ $item['satuan_pembelian'] }} ({{ number_format($konversi, 0, ',', '.') }} {{ $item['satuan'] }})</option>
                                            @endif
                                            <option value="utama" {{ $selectedUnit === 'utama' ? 'selected' : '' }}>{{ $item['satuan'] }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light text-muted small">Rp</span>
                                            <input type="number" name="harga_satuan[]" class="form-control text-end input-harga fw-bold" step="any" min="0" value="{{ $item['harga_input'] }}" placeholder="0">
                                        </div>
                                    </td>
                                    <td class="conversion-cell text-center">
                                        @if($item['qty_input'] > 0)
                                            @php
                                                $multiplier = ($selectedUnit === 'pembelian') ? $konversi : 1.00;
                                                $qtyStok = $item['qty_input'] * $multiplier;
                                                $hargaStok = $multiplier > 0 ? ($item['harga_input'] / $multiplier) : $item['harga_input'];
                                            @endphp
                                            <div class="small fw-bold text-primary">{{ number_format($qtyStok, 2, ',', '.') }} {{ $item['satuan'] }}</div>
                                            <div class="text-muted" style="font-size: 11px;">@ Rp {{ number_format($hargaStok, 2, ',', '.') }} / {{ $item['satuan'] }}</div>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold text-success subtotal-cell">
                                        Rp {{ number_format($subtotal, 0, ',', '.') }}
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 btn-remove-row" title="Hapus baris ini">
                                            <i class="bi bi-x-circle fs-5"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr id="rowEmpty">
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        Tidak ada data barang di transaksi ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- SECTION 4: SUMMARY & SUBMIT -->
                <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 border-end">
                            <span class="text-muted small text-uppercase fw-bold d-block">Barang Terisi (Qty > 0)</span>
                            <span class="fs-4 fw-bold text-primary" id="summaryTotalFilled">0</span>
                            <span class="text-muted small"> dari <span id="summaryTotalLoaded">{{ count($detailsData) }}</span> item</span>
                        </div>
                        <div class="col-md-3 border-end">
                            <span class="text-muted small text-uppercase fw-bold d-block">Total Kuantitas Masuk Stok</span>
                            <span class="fs-4 fw-bold text-dark" id="summaryTotalQty">0,00</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small text-uppercase fw-bold d-block">Total Nilai Persediaan Awal</span>
                            <span class="fs-3 fw-bold text-success" id="summaryTotalNilai">Rp 0</span>
                        </div>
                        <div class="col-md-2 text-end">
                            <button type="submit" class="btn text-white fw-bold px-4 py-2 w-100 rounded-3 shadow-sm" style="background-color: #d88656; border: none;" id="btnSubmit">
                                <i class="bi bi-check-circle-fill me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        .custom-input {
            border-radius: 8px !important;
            padding: 8px 12px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 14px !important;
        }
        .custom-input:focus {
            border-color: #d88656 !important;
            box-shadow: 0 0 0 3px rgba(216, 134, 86, 0.15) !important;
        }
        .input-qty, .input-harga {
            font-weight: 600;
            border-radius: 6px;
        }
        .input-qty:focus, .input-harga:focus {
            background-color: #fff9f5;
        }
    </style>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tbodyBarang       = document.getElementById('tbodyBarang');
        const badgeTotalRows    = document.getElementById('badgeTotalRows');
        const searchBarangTabel = document.getElementById('searchBarangTabel');
        const searchDropdown    = document.getElementById('searchDropdownBarang');
        const formEdit          = document.getElementById('formEditPersediaanAwal');

        const summaryTotalFilled = document.getElementById('summaryTotalFilled');
        const summaryTotalLoaded = document.getElementById('summaryTotalLoaded');
        const summaryTotalQty    = document.getElementById('summaryTotalQty');
        const summaryTotalNilai  = document.getElementById('summaryTotalNilai');

        // Search in dropdown barang master
        if (searchDropdown) {
            searchDropdown.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();
                const items = document.querySelectorAll('.btn-add-item');
                items.forEach(el => {
                    const text = el.innerText.toLowerCase();
                    el.style.display = (!term || text.includes(term)) ? '' : 'none';
                });
            });
        }

        // Event listener tambah item barang dari dropdown
        document.querySelectorAll('.btn-add-item').forEach(btn => {
            btn.addEventListener('click', function () {
                const id        = this.getAttribute('data-id');
                const kode      = this.getAttribute('data-kode');
                const nama      = this.getAttribute('data-nama');
                const kat       = this.getAttribute('data-kategori');
                const katId     = this.getAttribute('data-kategori-id');
                const satStok   = this.getAttribute('data-satuan-stok') || 'pcs';
                const satBeli   = this.getAttribute('data-satuan-beli') || satStok;
                const konversi  = parseFloat(this.getAttribute('data-konversi')) || 1.00;
                const hrgStok   = parseFloat(this.getAttribute('data-harga-stok-utama')) || 0;
                const hrgBeli   = parseFloat(this.getAttribute('data-harga-beli-utama')) || 0;

                // Cek apakah barang sudah ada di tabel
                const existing = tbodyBarang.querySelector(`tr[data-id="${id}"]`);
                if (existing) {
                    existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const qtyInput = existing.querySelector('.input-qty');
                    if (qtyInput) {
                        qtyInput.focus();
                        qtyInput.select();
                    }
                    return;
                }

                // Hapus row kosong jika ada
                const rowEmpty = document.getElementById('rowEmpty');
                if (rowEmpty) rowEmpty.remove();

                const hasKonversi = satBeli && konversi > 1 && (satBeli !== satStok);
                const defaultUnit = hasKonversi ? 'pembelian' : 'utama';
                const defaultHarga = hasKonversi ? hrgBeli : hrgStok;

                let unitOptionsHtml = `<option value="utama" ${defaultUnit === 'utama' ? 'selected' : ''}>${satStok}</option>`;
                if (hasKonversi) {
                    unitOptionsHtml = `<option value="pembelian" ${defaultUnit === 'pembelian' ? 'selected' : ''}>${satBeli} (${Number(konversi).toLocaleString('id-ID')} ${satStok})</option>` + unitOptionsHtml;
                }

                const satuanBadge = hasKonversi
                    ? `<div><span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold mb-1">${satBeli}</span></div>
                       <small class="text-muted d-block" style="font-size: 11px;">1 ${satBeli} = ${Number(konversi).toLocaleString('id-ID')} ${satStok}</small>`
                    : `<span class="badge bg-light text-dark border">${satStok}</span>`;

                const tr = document.createElement('tr');
                tr.setAttribute('data-id', id);
                tr.setAttribute('data-kategori-id', katId);
                tr.setAttribute('data-nama', nama.toLowerCase());
                tr.setAttribute('data-kode', kode.toLowerCase());
                tr.setAttribute('data-satuan-stok', satStok);
                tr.setAttribute('data-satuan-beli', satBeli);
                tr.setAttribute('data-konversi', konversi);
                tr.setAttribute('data-harga-stok-utama', hrgStok);
                tr.setAttribute('data-harga-beli-utama', hrgBeli);

                tr.innerHTML = `
                    <td class="text-center text-muted row-number">0</td>
                    <td class="text-start font-monospace fw-bold">${kode}</td>
                    <td class="text-start">
                        <div class="fw-semibold text-dark">${nama}</div>
                        <input type="hidden" name="barang_id[]" value="${id}">
                    </td>
                    <td><span class="badge bg-light text-dark border">${kat}</span></td>
                    <td>${satuanBadge}</td>
                    <td>
                        <input type="number" name="qty[]" class="form-control text-center input-qty fw-bold" step="any" min="0" value="0" placeholder="0">
                    </td>
                    <td>
                        <select name="satuan_tipe[]" class="form-select form-select-sm input-satuan fw-semibold" style="border-radius: 6px; font-size: 12px;">
                            ${unitOptionsHtml}
                        </select>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted small">Rp</span>
                            <input type="number" name="harga_satuan[]" class="form-control text-end input-harga fw-bold" step="any" min="0" value="${defaultHarga}" placeholder="0">
                        </div>
                    </td>
                    <td class="conversion-cell text-center">
                        <span class="text-muted small">-</span>
                    </td>
                    <td class="text-end fw-bold text-success subtotal-cell">
                        Rp 0
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 btn-remove-row" title="Hapus baris ini">
                            <i class="bi bi-x-circle fs-5"></i>
                        </button>
                    </td>
                `;

                tbodyBarang.appendChild(tr);
                bindRowEvents(tr);
                renumberRows();
                updateSummary();

                const newQty = tr.querySelector('.input-qty');
                if (newQty) {
                    newQty.focus();
                    newQty.select();
                }
            });
        });

        // Perhitungan baris
        function bindRowEvents(row) {
            const qtyInputEl     = row.querySelector('.input-qty');
            const satuanSelectEl = row.querySelector('.input-satuan');
            const hargaInputEl   = row.querySelector('.input-harga');
            const conversionCell = row.querySelector('.conversion-cell');
            const subtotalCell   = row.querySelector('.subtotal-cell');
            const btnRemove      = row.querySelector('.btn-remove-row');

            const calcRow = () => {
                const konversi       = parseFloat(row.getAttribute('data-konversi')) || 1.00;
                const satuanStok     = row.getAttribute('data-satuan-stok') || 'pcs';
                const selectedUnit   = satuanSelectEl ? satuanSelectEl.value : 'pembelian';
                const isPembelian    = selectedUnit === 'pembelian';

                const qtyInput   = parseFloat(qtyInputEl.value) || 0;
                const hargaInput = parseFloat(hargaInputEl.value) || 0;

                const multiplier = isPembelian ? konversi : 1.00;
                const qtyStok    = qtyInput * multiplier;
                const hargaStok  = multiplier > 0 ? (hargaInput / multiplier) : hargaInput;
                const subtotal   = qtyInput * hargaInput;

                if (qtyInput > 0) {
                    conversionCell.innerHTML = `
                        <div class="small fw-bold text-primary">
                            ${Number(qtyStok).toLocaleString('id-ID', { maximumFractionDigits: 2 })} ${satuanStok}
                        </div>
                        <div class="text-muted" style="font-size: 11px;">
                            @ Rp ${Number(hargaStok).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} / ${satuanStok}
                        </div>
                    `;
                    subtotalCell.innerText = 'Rp ' + subtotal.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                    row.classList.add('table-success', 'bg-opacity-10');
                } else {
                    conversionCell.innerHTML = `<span class="text-muted small">-</span>`;
                    subtotalCell.innerText = 'Rp 0';
                    row.classList.remove('table-success', 'bg-opacity-10');
                }

                updateSummary();
            };

            if (qtyInputEl)   qtyInputEl.addEventListener('input', calcRow);
            if (hargaInputEl) hargaInputEl.addEventListener('input', calcRow);
            if (satuanSelectEl) satuanSelectEl.addEventListener('change', calcRow);

            if (btnRemove) {
                btnRemove.addEventListener('click', function () {
                    row.remove();
                    renumberRows();
                    updateSummary();
                });
            }
        }

        // Bind semua baris yang sudah ada saat muat halaman
        tbodyBarang.querySelectorAll('tr:not(#rowEmpty)').forEach(r => bindRowEvents(r));

        function renumberRows() {
            const rows = tbodyBarang.querySelectorAll('tr:not(#rowEmpty)');
            rows.forEach((r, idx) => {
                const numCell = r.querySelector('.row-number');
                if (numCell) numCell.innerText = idx + 1;
            });
            summaryTotalLoaded.innerText = rows.length;
            badgeTotalRows.innerText = `${rows.length} barang di daftar`;
        }

        function updateSummary() {
            const rows = tbodyBarang.querySelectorAll('tr:not(#rowEmpty)');
            let totalFilled  = 0;
            let grandQtyStok = 0;
            let grandNilai   = 0;

            rows.forEach(r => {
                const qtyInputEl     = r.querySelector('.input-qty');
                const satuanSelectEl = r.querySelector('.input-satuan');
                const hargaInputEl   = r.querySelector('.input-harga');
                const konversi       = parseFloat(r.getAttribute('data-konversi')) || 1.00;

                if (qtyInputEl && hargaInputEl) {
                    const selectedUnit = satuanSelectEl ? satuanSelectEl.value : 'pembelian';
                    const multiplier   = selectedUnit === 'pembelian' ? konversi : 1.00;
                    const qtyInput     = parseFloat(qtyInputEl.value) || 0;
                    const hargaInput   = parseFloat(hargaInputEl.value) || 0;

                    if (qtyInput > 0) {
                        totalFilled++;
                        grandQtyStok += (qtyInput * multiplier);
                        grandNilai   += (qtyInput * hargaInput);
                    }
                }
            });

            summaryTotalFilled.innerText = totalFilled;
            summaryTotalQty.innerText    = grandQtyStok.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            summaryTotalNilai.innerText  = 'Rp ' + grandNilai.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        // Filter pencarian tabel edit
        searchBarangTabel.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            const rows = tbodyBarang.querySelectorAll('tr:not(#rowEmpty)');
            rows.forEach(r => {
                const nama = r.getAttribute('data-nama') || '';
                const kode = r.getAttribute('data-kode') || '';
                r.style.display = (!term || nama.includes(term) || kode.includes(term)) ? '' : 'none';
            });
        });

        // Validasi sebelum submit
        formEdit.addEventListener('submit', function (e) {
            const rows = tbodyBarang.querySelectorAll('tr:not(#rowEmpty)');
            let hasValidQty = false;

            rows.forEach(r => {
                const qtyInput = r.querySelector('.input-qty');
                if (qtyInput && parseFloat(qtyInput.value) > 0) {
                    hasValidQty = true;
                }
            });

            if (!hasValidQty) {
                e.preventDefault();
                alert('Harap isi minimal 1 barang dengan Qty Persediaan Awal lebih dari 0.');
            }
        });

        updateSummary();
    });
    </script>
    @endpush
</x-app-layout>
