<x-app-layout>
    <x-slot name="header">
        Input Persediaan Awal
    </x-slot>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold">Form Input Persediaan Awal Master Barang</h5>
                    <small class="text-muted">Isi kuantitas dan harga pokok saldo awal untuk seluruh item barang di master</small>
                </div>
                <div>
                    <a href="{{ route('persediaan-awal.index') }}" class="btn btn-sm btn-outline-secondary rounded-2 px-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('persediaan-awal.store') }}" method="POST" id="formPersediaanAwal">
            @csrf
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

                <!-- SECTION 1: HEADER TRANSAKSI -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-geo-alt-fill me-1 text-primary"></i> 1. Informasi Gudang & Tanggal Saldo Awal
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Gudang Target <span class="text-danger">*</span></label>
                            <select name="gudang_id" id="gudangSelect" class="form-select custom-input" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" 
                                        {{ old('gudang_id', $defaultGudangId) == $g->id ? 'selected' : '' }}
                                        data-kategori="{{ strtolower($g->kategori) }}" 
                                        data-divisi="{{ json_encode($g->divisi) }}">
                                        {{ $g->nama }} ({{ $g->kategori }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3" id="divisiWrapper" style="display: none;">
                            <label class="form-label small fw-bold text-muted">Divisi Gudang <span class="text-danger">*</span></label>
                            <select name="divisi_id" id="divisiSelect" class="form-select custom-input">
                                <option value="">-- Pilih Divisi --</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Tanggal Saldo Awal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" id="tanggalInput" class="form-control custom-input" value="{{ old('tanggal', date('Y-m-d')) }}" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-muted">Keterangan / Catatan</label>
                            <input type="text" name="keterangan" class="form-control custom-input" placeholder="Contoh: Saldo awal master barang cut-off pembukuan" value="{{ old('keterangan', 'Persediaan Awal Master Barang') }}">
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: TOOLBAR MUAT BARANG MASTER -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm text-white rounded-2 px-3" id="btnLoadAllBarang" style="background-color: #d88656; border: none;">
                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Muat Semua Barang Master
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-2 px-3" id="btnResetQty">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Nilai Qty
                        </button>
                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3 rounded-pill" id="badgeTotalLoaded">
                            0 barang termuat
                        </span>
                    </div>

                    <!-- Search & Kategori Filter in Table -->
                    <div class="d-flex align-items-center gap-2">
                        <select id="filterKategoriTabel" class="form-select form-select-sm rounded-2" style="width: 170px;">
                            <option value="">-- Semua Kategori --</option>
                            @foreach($kategoris as $kat)
                                <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                            @endforeach
                        </select>
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchBarangTabel" class="form-control" placeholder="Cari nama/kode barang...">
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: TABEL DAFTAR BARANG -->
                <div class="table-responsive border rounded-3 mb-4" style="max-height: 540px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 text-center" id="tableBarang">
                        <thead class="table-light sticky-top" style="z-index: 2;">
                            <tr>
                                <th style="width: 45px;">No</th>
                                <th class="text-start" style="width: 110px;">Kode</th>
                                <th class="text-start" style="min-width: 160px;">Nama Barang</th>
                                <th style="width: 110px;">Kategori</th>
                                <th style="width: 130px;">Satuan & Konversi</th>
                                <th style="width: 95px;">Stok Saat Ini</th>
                                <th style="width: 130px;">Qty Input <span class="text-danger">*</span></th>
                                <th style="width: 135px;">Satuan Input <span class="text-danger">*</span></th>
                                <th style="width: 155px;">Harga per Satuan Input (Rp) <span class="text-danger">*</span></th>
                                <th style="width: 155px;">Masuk ke Stok Utama</th>
                                <th class="text-end" style="width: 140px;">Subtotal Nilai (Rp)</th>
                                <th style="width: 45px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyBarang">
                            <tr id="rowEmpty">
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                                    Klik tombol <strong>"Muat Semua Barang Master"</strong> di atas untuk memuat daftar seluruh item barang.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- SECTION 4: SUMMARY & SUBMIT -->
                <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 border-end">
                            <span class="text-muted small text-uppercase fw-bold d-block">Barang Terisi (Qty > 0)</span>
                            <span class="fs-4 fw-bold text-primary" id="summaryTotalFilled">0</span>
                            <span class="text-muted small"> dari <span id="summaryTotalLoaded">0</span> item</span>
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
                                <i class="bi bi-check-circle-fill me-1"></i> Simpan
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
        const gudangSelect       = document.getElementById('gudangSelect');
        const divisiWrapper      = document.getElementById('divisiWrapper');
        const divisiSelect       = document.getElementById('divisiSelect');
        const btnLoadAllBarang   = document.getElementById('btnLoadAllBarang');
        const btnResetQty        = document.getElementById('btnResetQty');
        const tbodyBarang        = document.getElementById('tbodyBarang');
        const badgeTotalLoaded   = document.getElementById('badgeTotalLoaded');
        const searchBarangTabel  = document.getElementById('searchBarangTabel');
        const filterKategoriTabel = document.getElementById('filterKategoriTabel');

        const summaryTotalFilled = document.getElementById('summaryTotalFilled');
        const summaryTotalLoaded = document.getElementById('summaryTotalLoaded');
        const summaryTotalQty    = document.getElementById('summaryTotalQty');
        const summaryTotalNilai  = document.getElementById('summaryTotalNilai');
        const formPersediaanAwal = document.getElementById('formPersediaanAwal');

        let allLoadedItems = [];

        // 1. Tangani pemilihan gudang & update dropdown divisi
        function updateDivisi() {
            const selectedOpt = gudangSelect.options[gudangSelect.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) {
                divisiWrapper.style.display = 'none';
                divisiSelect.removeAttribute('required');
                divisiSelect.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                return;
            }

            const divisiData = selectedOpt.getAttribute('data-divisi') ? JSON.parse(selectedOpt.getAttribute('data-divisi')) : [];

            if (divisiData && divisiData.length > 0) {
                divisiWrapper.style.display = 'block';
                divisiSelect.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                divisiData.forEach(d => {
                    divisiSelect.innerHTML += `<option value="${d.id}">${d.nama}</option>`;
                });
                divisiSelect.setAttribute('required', 'required');
            } else {
                divisiWrapper.style.display = 'none';
                divisiSelect.removeAttribute('required');
                divisiSelect.innerHTML = '<option value="">-- Pilih Divisi --</option>';
            }
        }

        gudangSelect.addEventListener('change', function () {
            updateDivisi();
            if (allLoadedItems.length > 0) {
                loadMasterBarang();
            }
        });
        updateDivisi();

        divisiSelect.addEventListener('change', function () {
            if (allLoadedItems.length > 0) {
                loadMasterBarang();
            }
        });

        // 2. AJAX Load Seluruh Master Barang
        function loadMasterBarang() {
            const gudangId = gudangSelect.value;
            const divisiId = divisiSelect.value;

            btnLoadAllBarang.disabled = true;
            btnLoadAllBarang.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memuat...';

            fetch("{{ route('persediaan-awal.load-barang') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    gudang_id: gudangId,
                    divisi_id: divisiId
                })
            })
            .then(res => res.json())
            .then(response => {
                btnLoadAllBarang.disabled = false;
                btnLoadAllBarang.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Muat Semua Barang Master';

                if (response.status === 'success' && response.data) {
                    allLoadedItems = response.data;
                    renderTable(allLoadedItems);
                }
            })
            .catch(err => {
                btnLoadAllBarang.disabled = false;
                btnLoadAllBarang.innerHTML = '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Muat Semua Barang Master';
                alert('Gagal memuat master barang: ' + err.message);
            });
        }

        btnLoadAllBarang.addEventListener('click', loadMasterBarang);

        // 3. Render Tabel Barang
        function renderTable(items) {
            tbodyBarang.innerHTML = '';

            // Update mode alert banner if exists
            const selectedOpt = gudangSelect.options[gudangSelect.selectedIndex];
            const isGudangUtama = items.length > 0 ? (items[0].is_gudang_utama !== false) : true;

            let bannerEl = document.getElementById('gudangModeBanner');
            if (!bannerEl) {
                bannerEl = document.createElement('div');
                bannerEl.id = 'gudangModeBanner';
                document.querySelector('.table-responsive').parentNode.insertBefore(bannerEl, document.querySelector('.table-responsive'));
            }

            if (isGudangUtama) {
                bannerEl.innerHTML = `
                    <div class="alert alert-primary py-2 px-3 mb-3 rounded-3 small d-flex align-items-center">
                        <i class="bi bi-star-fill text-primary me-2"></i>
                        <div><strong>Mode Master Gudang Utama:</strong> Anda dapat menginput kuantitas stock dan menentukan <strong>Harga Beli Satuan</strong> yang menjadi acuan patokan harga HPP seluruh sistem.</div>
                    </div>
                `;
            } else {
                bannerEl.innerHTML = `
                    <div class="alert alert-warning py-2 px-3 mb-3 rounded-3 small d-flex align-items-center">
                        <i class="bi bi-lock-fill text-warning me-2 fs-6"></i>
                        <div><strong>Mode Input Stok Gudang Cabang / Operasional:</strong> Cukup isi <strong>Qty Saldo Awal</strong>. Kolom harga beli otomatis <span class="badge bg-warning text-dark">Terkunci</span> dan mengacu langsung pada harga referensi pertama dari <strong>Gudang Utama</strong>.</div>
                    </div>
                `;
            }

            if (!items || items.length === 0) {
                tbodyBarang.innerHTML = `
                    <tr id="rowEmpty">
                        <td colspan="11" class="text-center py-5 text-muted">
                            Tidak ada data barang ditemukan.
                        </td>
                    </tr>
                `;
                updateSummary();
                return;
            }

            items.forEach((item, idx) => {
                const tr = document.createElement('tr');
                tr.setAttribute('data-id', item.id);
                tr.setAttribute('data-kategori-id', item.kategori_id);
                tr.setAttribute('data-nama', item.nama.toLowerCase());
                tr.setAttribute('data-kode', item.kode_barang.toLowerCase());
                tr.setAttribute('data-satuan-stok', item.satuan || 'pcs');
                tr.setAttribute('data-satuan-beli', item.satuan_pembelian || item.satuan || 'pcs');
                tr.setAttribute('data-konversi', item.konversi_pembelian || 1.00);
                tr.setAttribute('data-harga-stok-utama', item.hpp_satuan_utama || (item.hpp_referensi || 0));
                tr.setAttribute('data-harga-beli-utama', item.harga_beli_utama || 0);

                const konversi = parseFloat(item.konversi_pembelian) || 1.00;
                const satuanStok = item.satuan || 'pcs';
                const satuanBeli = item.satuan_pembelian || satuanStok;
                const hasKonversi = satuanBeli && konversi > 1 && (satuanBeli !== satuanStok || konversi !== 1);

                // Default unit: jika ada konversi pembelian, default ke 'pembelian'
                const defaultUnit = hasKonversi ? 'pembelian' : 'utama';
                const defaultHargaInput = hasKonversi 
                    ? Number(item.harga_beli_utama || 0)
                    : Number(item.hpp_satuan_utama || (item.hpp_referensi || 0));

                const satuanBadge = hasKonversi
                    ? `<div><span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold mb-1">${satuanBeli}</span></div>
                       <small class="text-muted d-block" style="font-size: 11px;">1 ${satuanBeli} = ${Number(konversi).toLocaleString('id-ID')} ${satuanStok}</small>`
                    : `<span class="badge bg-light text-dark border">${satuanStok}</span>`;

                const isReadonlyHarga = !isGudangUtama;
                const hargaInputAttr = isReadonlyHarga
                    ? 'readonly style="background-color: #f1f5f9; cursor: not-allowed;" title="Harga terkunci mengacu pada harga Gudang Utama"'
                    : '';

                const lockIcon = isReadonlyHarga ? '<i class="bi bi-lock-fill text-muted me-1" style="font-size:10px;"></i>' : '';

                let unitOptionsHtml = `<option value="utama" ${defaultUnit === 'utama' ? 'selected' : ''}>${satuanStok}</option>`;
                if (hasKonversi) {
                    unitOptionsHtml = `<option value="pembelian" ${defaultUnit === 'pembelian' ? 'selected' : ''}>${satuanBeli} (${Number(konversi).toLocaleString('id-ID')} ${satuanStok})</option>` + unitOptionsHtml;
                }

                tr.innerHTML = `
                    <td class="text-center text-muted row-number">${idx + 1}</td>
                    <td class="text-start font-monospace fw-bold">${item.kode_barang}</td>
                    <td class="text-start">
                        <div class="fw-semibold text-dark">${item.nama}</div>
                        <small class="text-muted">${item.jenis}</small>
                        <input type="hidden" name="barang_id[]" value="${item.id}">
                    </td>
                    <td><span class="badge bg-light text-dark border">${item.kategori_nama}</span></td>
                    <td>${satuanBadge}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">${Number(item.stok_sekarang).toLocaleString('id-ID')} ${satuanStok}</span></td>
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
                            <span class="input-group-text bg-light text-muted small">${lockIcon}Rp</span>
                            <input type="number" name="harga_satuan[]" class="form-control text-end input-harga fw-bold" step="any" min="0" value="${defaultHargaInput}" placeholder="0" ${hargaInputAttr}>
                        </div>
                        ${isReadonlyHarga ? '<small class="text-muted d-block text-end" style="font-size: 9.5px;">(Ref. Gudang Utama)</small>' : ''}
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
            });

            badgeTotalLoaded.innerText = `${items.length} barang termuat`;
            summaryTotalLoaded.innerText = items.length;

            attachRowEvents();
            updateSummary();
        }

        // 4. Input Events & Row Calculations
        function attachRowEvents() {
            const rows = tbodyBarang.querySelectorAll('tr');

            rows.forEach(row => {
                const qtyInputEl     = row.querySelector('.input-qty');
                const satuanSelectEl = row.querySelector('.input-satuan');
                const hargaInputEl   = row.querySelector('.input-harga');
                const conversionCell = row.querySelector('.conversion-cell');
                const subtotalCell   = row.querySelector('.subtotal-cell');
                const btnRemove      = row.querySelector('.btn-remove-row');

                if (qtyInputEl && hargaInputEl) {
                    const calcRow = () => {
                        const konversi       = parseFloat(row.getAttribute('data-konversi')) || 1.00;
                        const satuanStok     = row.getAttribute('data-satuan-stok');
                        const satuanBeli     = row.getAttribute('data-satuan-beli');
                        const selectedUnit   = satuanSelectEl ? satuanSelectEl.value : 'pembelian';
                        const isPembelian    = selectedUnit === 'pembelian';

                        const qtyInput   = parseFloat(qtyInputEl.value) || 0;
                        const hargaInput = parseFloat(hargaInputEl.value) || 0;

                        const multiplier = isPembelian ? konversi : 1.00;
                        const qtyStok    = qtyInput * multiplier;
                        const hargaStok  = multiplier > 0 ? (hargaInput / multiplier) : hargaInput;
                        const subtotal   = qtyInput * hargaInput;

                        if (qtyInput > 0) {
                            let conversionText = `
                                <div class="small fw-bold text-primary">
                                    ${Number(qtyStok).toLocaleString('id-ID', { maximumFractionDigits: 2 })} ${satuanStok}
                                </div>
                                <div class="text-muted" style="font-size: 11px;">
                                    @ Rp ${Number(hargaStok).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} / ${satuanStok}
                                </div>
                            `;
                            conversionCell.innerHTML = conversionText;
                            subtotalCell.innerText = 'Rp ' + subtotal.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                            row.classList.add('table-success', 'bg-opacity-10');
                        } else {
                            conversionCell.innerHTML = `<span class="text-muted small">-</span>`;
                            subtotalCell.innerText = 'Rp 0';
                            row.classList.remove('table-success', 'bg-opacity-10');
                        }

                        updateSummary();
                    };

                    // Switch satuan -> auto recalculate & update reference price if locked
                    if (satuanSelectEl) {
                        satuanSelectEl.addEventListener('change', function () {
                            const isReadonly = hargaInputEl.hasAttribute('readonly');
                            const konversi = parseFloat(row.getAttribute('data-konversi')) || 1.00;
                            const hargaStokUtama = parseFloat(row.getAttribute('data-harga-stok-utama')) || 0;
                            const hargaBeliUtama = parseFloat(row.getAttribute('data-harga-beli-utama')) || 0;

                            if (isReadonly) {
                                hargaInputEl.value = this.value === 'pembelian' ? hargaBeliUtama : hargaStokUtama;
                            }
                            calcRow();
                        });
                    }

                    qtyInputEl.addEventListener('input', calcRow);
                    hargaInputEl.addEventListener('input', calcRow);
                }

                if (btnRemove) {
                    btnRemove.addEventListener('click', function () {
                        row.remove();
                        renumberRows();
                        updateSummary();
                    });
                }
            });
        }

        function renumberRows() {
            const rows = tbodyBarang.querySelectorAll('tr:not(#rowEmpty)');
            rows.forEach((r, idx) => {
                const numCell = r.querySelector('.row-number');
                if (numCell) numCell.innerText = idx + 1;
            });
            summaryTotalLoaded.innerText = rows.length;
            badgeTotalLoaded.innerText = `${rows.length} barang termuat`;
        }

        // 5. Update Grand Summary
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

        // 6. Reset Nilai Qty
        btnResetQty.addEventListener('click', function () {
            if (confirm('Apakah Anda ingin mereset seluruh nilai Qty ke 0?')) {
                const qtyInputs = tbodyBarang.querySelectorAll('.input-qty');
                qtyInputs.forEach(input => {
                    input.value = '0';
                    input.dispatchEvent(new Event('input'));
                });
            }
        });

        // 7. Filter & Search Realtime pada Tabel
        function filterTable() {
            const searchTerm = searchBarangTabel.value.trim().toLowerCase();
            const kategoriId = filterKategoriTabel.value;

            const rows = tbodyBarang.querySelectorAll('tr:not(#rowEmpty)');

            rows.forEach(row => {
                const nama = row.getAttribute('data-nama') || '';
                const kode = row.getAttribute('data-kode') || '';
                const kat  = row.getAttribute('data-kategori-id') || '';

                const matchSearch   = !searchTerm || nama.includes(searchTerm) || kode.includes(searchTerm);
                const matchKategori = !kategoriId || kat === kategoriId;

                if (matchSearch && matchKategori) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchBarangTabel.addEventListener('input', filterTable);
        filterKategoriTabel.addEventListener('change', filterTable);

        // 8. Form Validation Sebelum Submit
        formPersediaanAwal.addEventListener('submit', function (e) {
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

        // Otomatis load master barang saat pertama kali halaman terbuka jika gudang tersedia
        if (gudangSelect.value) {
            loadMasterBarang();
        }
    });
    </script>
    @endpush
</x-app-layout>
