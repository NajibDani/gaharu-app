<x-app-layout>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold text-dark m-0">Daftar Resep Produk</h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-3 px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImportResep">
                <i class="fas fa-file-upload me-2"></i>Import Excel
            </button>
            <button type="button" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold" id="btn-tambah-resep">
                <i class="fas fa-plus me-2"></i>Tambah Resep
            </button>
        </div>
    </div>

    @if (session('import_result_resep'))
        @php $ir = session('import_result_resep'); @endphp
        <div class="alert alert-info border-0 shadow-sm mb-4">
            <strong>Hasil Import Resep:</strong>
            {{ $ir['createdRecipes'] }} resep dibuat ({{ $ir['createdIngredients'] }} bahan),
            {{ $ir['skippedRecipes'] }} dilewati (produk sudah punya resep).
            @if (!empty($ir['skippedRows']))
                <div class="mt-1 small text-muted">
                    <strong>Catatan:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach ($ir['skippedRows'] as $skipNote)
                            <li>{{ $skipNote }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (!empty($ir['errors']))
                <div class="mt-2">
                    <strong class="text-danger">{{ count($ir['errors']) }} pesan perhatian:</strong>
                    <ul class="mb-0 small text-danger">
                        @foreach ($ir['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <strong class="me-2"><i class="fas fa-check-circle"></i> Berhasil!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error') || (isset($errors) && $errors->any()))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <strong class="d-block mb-1"><i class="fas fa-exclamation-triangle"></i> Terjadi Kesalahan:</strong>
        <ul class="mb-0 ps-3">
            @if(session('error')) <li>{{ session('error') }}</li> @endif
            @if(isset($errors))
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            @endif
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-0">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-dark">Data Resep</h5>
                <form action="{{ route('resep.index') }}" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama/kode..." value="{{ request('search') }}" style="width: 220px; border-radius: 6px;">
                    <button type="submit" class="btn btn-sm text-white" style="background-color: #d88656; border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('resep.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
                    @endif
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase fs-7 text-center">
                        <tr>
                            <th class="text-start ps-4 py-3">Nama Produk</th>
                            <th>Output / Batch</th>
                            <th style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @forelse($data as $r)
                        <tr>
                            <td class="text-start ps-4 fw-semibold text-dark">
                                {{ $r->produk->nama ?? 'Produk Tidak Diketahui' }}
                                @if($r->produk && $r->produk->is_bahan_setengah_jadi)
                                    <span class="badge bg-info-subtle text-info ms-1" style="font-size: 11px;">Bahan Setengah Jadi</span>
                                @elseif($r->produk && $r->produk->is_barang_jadi)
                                    <span class="badge bg-success-subtle text-success ms-1" style="font-size: 11px;">Barang Jadi</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-3 py-2 fw-medium">
                                    {{ (int) $r->output_qty }} {{ $r->satuan_output }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('resep.show', $r->id) }}" class="btn btn-info btn-sm text-white rounded-2 px-2">
                                        Lihat
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn btn-warning btn-sm btn-edit-resep rounded-2 px-2"
                                            data-id="{{ $r->id }}"
                                            data-produk_id="{{ $r->produk_id }}"
                                            data-output_qty="{{ (int) $r->output_qty }}"
                                            data-satuan_output="{{ $r->satuan_output }}"
                                            data-btkl="{{ (int) $r->btkl_per_batch }}"
                                            data-bop="{{ (int) $r->bop_per_batch }}"
                                            data-bahanbaku="{{ json_encode($r->bahanbaku) }}"
                                            data-page="{{ $data->currentPage() }}">
                                        Edit
                                    </button>
 
                                    <form action="{{ route('resep.destroy', $r->id) }}" method="POST" class="d-inline m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus resep ini?')">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5 fs-6">
                                <i class="fas fa-folder-open d-block mb-2 fs-3 opacity-50"></i>
                                Belum ada data resep yang tersimpan.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($data->hasPages())
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                {{ $data->links() }}
            </div>
        @endif
    </div>
</div>

<form id="form-resep" action="{{ route('resep.store') }}" method="POST">
    @csrf
    <input type="hidden" name="_method" id="form-method" value="POST">

    <div class="modal fade" id="modalResep" data-bs-backdrop="static" tabindex="-1" aria-labelledby="modalResepTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 14px; overflow: visible;">
                
                <div class="modal-header bg-light py-3" style="border-top-left-radius: 14px; border-top-right-radius: 14px;">
                    <h5 class="modal-title fw-bold text-dark" id="modalResepTitle">Tambah Resep Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body px-4 py-3" style="overflow: visible;">
                    
                    {{-- PILIH MASTER PRODUK BARANG JADI / BSJ --}}
                    <div class="mb-3 position-relative" style="z-index: 1050;">
                        <label class="form-label fw-bold small text-secondary">Produk</label>
                        <select name="produk_id" id="produk_id" class="form-select produk-select" required>
                            <option value="" disabled selected>-- Pilih Produk --</option>
                            @foreach($produk as $p)
                                <option value="{{ $p->id }}" data-satuan="{{ $p->satuan }}">
                                    {{ $p->nama }} {{ $p->is_bahan_setengah_jadi ? '(Bahan Setengah Jadi)' : '(Barang Jadi)' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-danger d-none mt-1 d-block" id="edit-produk-warning">
                            <i class="fas fa-info-circle me-1"></i> Produk tidak dapat diganti saat mengedit resep.
                        </small>
                    </div>

                    {{-- TARGET OUTPUT PROSES PRODUKSI --}}
                    <div class="row position-relative" style="z-index: 100;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small text-secondary">Output per Batch</label>
                            <input type="number" name="output_qty" id="output_qty" class="form-control" min="1" placeholder="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small text-secondary">Satuan Output (Auto)</label>
                            <input type="text" name="satuan_output" id="satuan_output" class="form-control bg-light text-center fw-semibold" readonly placeholder="-">
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">
                    <h6 class="fw-bold text-primary mb-3"><i class="fas fa-flask me-2"></i>Komposisi Komponen Bahan Baku</h6>

                    {{-- TABEL INPUT DATA BAHAN BAKU DINAMIS --}}
                    <div class="table-responsive" style="min-height: 180px; max-height: 280px; overflow-y: auto; overflow-x: visible; border: 1px solid #dee2e6; border-radius: 8px;">
                        <table class="table table-bordered table-sm align-middle mb-0" id="table-bahan">
                            <thead class="table-light text-center small text-secondary">
                                <tr>
                                    <th class="py-2">Nama Bahan Baku</th>
                                    <th style="width: 22%;" class="py-2">Qty / Produk</th>
                                    <th style="width: 20%;" class="py-2">Satuan</th>
                                    <th style="width: 10%;" class="py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="p-1">
                                        <select name="bahan_id[]" class="form-select form-select-sm bahan-select" required>
                                            <option value="" disabled selected>-- Pilih Bahan --</option>
                                            @foreach($bahan as $b)
                                                <option value="{{ $b->id }}" data-satuan="{{ $b->satuan }}">
                                                    {{ $b->nama }} (Rp {{ number_format($b->hpp_referensi) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-1">
                                        <input type="number" step="any" name="qty_bahan[]" class="form-control form-control-sm text-center" min="0.001" placeholder="0" required>
                                    </td>
                                    <td class="p-1">
                                        <input type="text" name="satuan[]" class="form-control form-control-sm text-center bg-light satuan-input" readonly placeholder="-">
                                    </td>
                                    <td class="text-center p-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row border-0 px-2">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-light text-primary btn-sm fw-bold border mt-2 shadow-sm" id="btn-add-row">
                        <i class="fas fa-plus me-1"></i> Tambah Baris Bahan
                    </button>
                </div>
                
                <div class="modal-footer bg-light border-top py-2" style="border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;">
                    <button type="button" class="btn btn-secondary px-4 shadow-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm" id="btn-submit-form">Simpan Resep</button>
                </div>

            </div>
        </div>
    </div>
</form>

{{-- ================= MODAL IMPORT EXCEL RESEP ================= --}}
<div class="modal fade" id="modalImportResep" tabindex="-1" aria-labelledby="modalImportResepLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: #7A4517;">
                <h5 class="modal-title fw-bold" id="modalImportResepLabel">
                    <i class="fas fa-file-excel me-2"></i>Import Resep dari Excel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('resep.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body text-start">
                    <p class="text-muted small mb-3">
                        Gunakan file Excel untuk mengunggah resep secara massal. Produk yang sudah memiliki resep akan otomatis dilewati agar tidak terjadi duplikasi.
                    </p>

                    <div class="mb-3">
                        <a href="{{ route('resep.import.template') }}" class="btn btn-sm btn-outline-secondary rounded-3">
                            <i class="fas fa-download me-1"></i> Unduh Template Excel
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Pilih File Excel (.xlsx)</label>
                        <input type="file" name="file" class="form-control rounded-3 @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-secondary px-3 rounded-3" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn text-white px-4 rounded-3" style="background-color: #7A4517;">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Styling & Z-Index Choices.js Dropdown di dalam Modal agar tidak bertabrakan */
    .choices {
        margin-bottom: 0;
        position: relative;
    }
    .choices.is-open {
        z-index: 1060 !important;
    }
    .choices__list--dropdown {
        z-index: 1060 !important;
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.15) !important;
        border-radius: 8px !important;
    }
    .choices__list--dropdown .choices__item--selectable {
        padding: 8px 12px !important;
        font-size: 14px !important;
    }
    .choices__list--dropdown .choices__item--selectable.is-highlighted {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
    }
    .choices[data-type*="select-one"] .choices__inner {
        padding: 5px 10px;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        min-height: 38px;
        font-size: 14px;
    }
    #table-bahan thead {
        position: relative;
        z-index: 1 !important;
    }
    #table-bahan tr {
        position: relative;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const elemenModal = document.getElementById('modalResep');
    const bsModalInstance = new bootstrap.Modal(elemenModal);

    const formResep = document.getElementById('form-resep');
    const formMethod = document.getElementById('form-method');
    const modalTitle = document.getElementById('modalResepTitle');
    const btnSubmit = document.getElementById('btn-submit-form');
    
    const selectProduk = document.getElementById('produk_id');
    const warningProduk = document.getElementById('edit-produk-warning');
    const inputSatuanOutput = document.getElementById('satuan_output');
    
    const tbodyBahan = document.querySelector('#table-bahan tbody');
    const rowBlueprint = tbodyBahan.querySelector('tr').cloneNode(true); // RAW, sebelum Choices ikut campur

    // =======================================================
    // CHOICES.JS — Produk & Bahan Baku jadi bisa diketik
    // =======================================================
    const produkChoices = new Choices(selectProduk, {
        searchEnabled: true,
        itemSelectText: '',
        shouldSort: false, // sudah diurutkan abjad dari controller
    });

    function initBahanChoices(row) {
        let select = row.querySelector('.bahan-select');
        if (select.choicesInstance) return select.choicesInstance;
        select.choicesInstance = new Choices(select, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
        });
        return select.choicesInstance;
    }

    // init baris pertama yang sudah ada di HTML sejak awal load
    initBahanChoices(tbodyBahan.querySelector('tr'));

    // Sinkronisasi otomatis kolom 'Satuan' bahan baku
    function sinkronkanSatuanBahan(row) {
        let select = row.querySelector('.bahan-select');
        let optionTerpilih = select.options[select.selectedIndex];
        let satuan = optionTerpilih ? optionTerpilih.dataset.satuan : '';
        row.querySelector('.satuan-input').value = satuan ?? '';
    }

    selectProduk.addEventListener('change', function() {
        let opt = this.options[this.selectedIndex];
        inputSatuanOutput.value = opt ? (opt.dataset.satuan ?? '') : '';
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('bahan-select')) {
            sinkronkanSatuanBahan(e.target.closest('tr'));
        }
    });

    // Tambah baris baru
    document.getElementById('btn-add-row').addEventListener('click', function() {
        let barisBaru = rowBlueprint.cloneNode(true);
        barisBaru.querySelectorAll('input').forEach(input => input.value = '');
        barisBaru.querySelector('.bahan-select').selectedIndex = 0;
        tbodyBahan.appendChild(barisBaru);
        initBahanChoices(barisBaru);
    });

    // Hapus baris
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-row')) {
            if (tbodyBahan.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
            } else {
                alert('Sistem Keamanan: Resep minimal wajib memiliki 1 baris komponen bahan baku!');
            }
        }
    });

    // ============ MODAL TAMBAH ============
    document.getElementById('btn-tambah-resep').addEventListener('click', function() {
        modalTitle.innerText = "Tambah Resep Baru";
        btnSubmit.innerText = "Simpan Resep";
        btnSubmit.className = "btn btn-primary px-4 shadow-sm";
        formResep.action = "{{ route('resep.store') }}";
        formMethod.value = "POST";
        
        formResep.reset();
        produkChoices.enable();
        produkChoices.removeActiveItems(); // reset tampilan balik ke placeholder
        warningProduk.classList.add('d-none');
        
        tbodyBahan.innerHTML = '';
        let barisAwal = rowBlueprint.cloneNode(true);
        tbodyBahan.appendChild(barisAwal);
        initBahanChoices(barisAwal);

        bsModalInstance.show();
    });

    // ============ MODAL EDIT ============
    document.querySelectorAll('.btn-edit-resep').forEach(tombol => {
        tombol.addEventListener('click', function() {
            modalTitle.innerText = "Edit Resep Produk";
            btnSubmit.innerText = "Update Resep";
            btnSubmit.className = "btn btn-warning px-4 text-dark shadow-sm fw-semibold";
            
            const idResep = this.dataset.id;
            const pageNum = this.dataset.page || 1;
            formResep.action = `/resep/${idResep}?page=${pageNum}`; 
            formMethod.value = "PUT";

            produkChoices.setChoiceByValue(this.dataset.produk_id);
            produkChoices.disable();
            warningProduk.classList.remove('d-none');

            document.getElementById('output_qty').value = this.dataset.output_qty;
            inputSatuanOutput.value = this.dataset.satuan_output;

            tbodyBahan.innerHTML = '';
            const arrayBahanBaku = JSON.parse(this.dataset.bahanbaku);

            if (arrayBahanBaku && arrayBahanBaku.length > 0) {
                arrayBahanBaku.forEach(item => {
                    let barisEdit = rowBlueprint.cloneNode(true);
                    barisEdit.querySelector('.bahan-select').value = item.bahan_id;
                    barisEdit.querySelector('input[name="qty_bahan[]"]').value = parseFloat(item.qty_bahan);
                    
                    tbodyBahan.appendChild(barisEdit);
                    initBahanChoices(barisEdit);
                    sinkronkanSatuanBahan(barisEdit);
                });
            } else {
                let barisKosong = rowBlueprint.cloneNode(true);
                tbodyBahan.appendChild(barisKosong);
                initBahanChoices(barisKosong);
            }

            bsModalInstance.show();
        });
    });

    formResep.addEventListener('submit', function() {
        produkChoices.enable(); // buka gembok agar produk_id ikut terkirim
    });

    // =======================================================
    // FIX: dropdown bahan baku / produk tidak terpotong / tertabrak
    // =======================================================
    const bahanScrollContainer = document.querySelector('#table-bahan').closest('.table-responsive');

    document.addEventListener('showDropdown', function(e) {
        if (bahanScrollContainer && bahanScrollContainer.contains(e.target)) {
            bahanScrollContainer.style.overflow = 'visible';
        }
        const choicesElem = e.target.closest('.choices');
        if (choicesElem) {
            choicesElem.style.zIndex = '1060';
        }
        const trElem = e.target.closest('tr');
        if (trElem) {
            trElem.style.zIndex = '1060';
        }
    });
    document.addEventListener('hideDropdown', function(e) {
        if (bahanScrollContainer && bahanScrollContainer.contains(e.target)) {
            bahanScrollContainer.style.overflow = 'auto';
        }
        const choicesElem = e.target.closest('.choices');
        if (choicesElem) {
            choicesElem.style.zIndex = '';
        }
        const trElem = e.target.closest('tr');
        if (trElem) {
            trElem.style.zIndex = '';
        }
    });
});
</script>
</x-app-layout>