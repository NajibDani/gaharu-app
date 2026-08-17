<x-app-layout>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0 fw-bold">Tambah Gudang</h5>
                    <small class="text-muted">Masukkan data gudang baru beserta divisinya</small>
                </div>

                <a href="{{ route('gudangs.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>

            <div class="card-body p-4">
                <form action="{{ route('gudangs.store') }}" method="POST" id="formGudang">
                    @csrf

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold">
                            Nama Gudang <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            name="nama"
                            id="nama"
                            class="form-control @error('nama') is-invalid @enderror"
                            value="{{ old('nama') }}"
                            placeholder="Contoh: Gudang Gaharu / Gudang KeJingga"
                            required
                        >

                        @error('nama')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="kategori" class="form-label fw-semibold">
                            Kategori Gudang <span class="text-danger">*</span>
                        </label>

                        <select name="kategori" id="kategoriSelect" class="form-select @error('kategori') is-invalid @enderror" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Operasional" {{ old('kategori') == 'Operasional' ? 'selected' : '' }}>Operasional (Outlet / Cabang dengan Divisi)</option>
                            <option value="Utama" {{ old('kategori') == 'Utama' ? 'selected' : '' }}>Utama (Pusat Penerimaan Pembelian)</option>
                            <option value="Produksi" {{ old('kategori') == 'Produksi' ? 'selected' : '' }}>Produksi (Central Kitchen / B2B)</option>
                        </select>

                        @error('kategori')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                        <div class="form-text text-muted">
                            Kategori <strong>Operasional</strong> akan memisahkan pencatatan stok, stock opname, dan pengeluaran bahan baku per divisi.
                        </div>
                    </div>

                    {{-- SECTION DIVISI (MUNCUL OTOMATIS JIKA KATEGORI = OPERASIONAL) --}}
                    <div id="sectionDivisi" class="mb-4 p-3 rounded border bg-light" style="display: {{ old('kategori') == 'Operasional' ? 'block' : 'none' }};">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold mb-0 text-primary">
                                    <i class="bi bi-diagram-3-fill me-1"></i> Daftar Divisi Gudang Operasional
                                </h6>
                                <small class="text-muted">Tambahkan divisi untuk gudang operasional ini (misal: Kitchen, Barista, Server, dll)</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" id="btnAddDivisi">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Divisi
                            </button>
                        </div>

                        {{-- Quick Suggestions --}}
                        <div class="mb-3">
                            <span class="small text-muted me-2">Template Cepat:</span>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-quick-divisi" data-name="Kitchen">+ Kitchen</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-quick-divisi" data-name="Barista">+ Barista</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-quick-divisi" data-name="Server">+ Server</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-quick-divisi" data-name="Service">+ Service</button>
                        </div>

                        <div id="divisiContainer" class="d-flex flex-column gap-2">
                            {{-- Baris Divisi dimasukkan via JS atau old input --}}
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="{{ route('gudangs.index') }}" class="btn btn-light border">
                            Batal
                        </a>

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Gudang
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kategoriSelect = document.getElementById('kategoriSelect');
    const sectionDivisi = document.getElementById('sectionDivisi');
    const divisiContainer = document.getElementById('divisiContainer');
    const btnAddDivisi = document.getElementById('btnAddDivisi');
    const quickButtons = document.querySelectorAll('.btn-quick-divisi');

    function createDivisiRow(value = '') {
        const row = document.createElement('div');
        row.className = 'input-group input-group-sm divisi-row';
        row.innerHTML = `
            <span class="input-group-text bg-white"><i class="bi bi-building"></i></span>
            <input type="text" name="divisi[]" class="form-control" placeholder="Nama Divisi (contoh: Kitchen / Barista / Server)" value="${value}">
            <button type="button" class="btn btn-outline-danger btn-remove-divisi" title="Hapus Divisi">
                <i class="bi bi-trash"></i>
            </button>
        `;

        row.querySelector('.btn-remove-divisi').addEventListener('click', function() {
            row.remove();
        });

        divisiContainer.appendChild(row);
        row.querySelector('input').focus();
    }

    function toggleDivisiSection() {
        if (kategoriSelect.value === 'Operasional') {
            sectionDivisi.style.display = 'block';
            if (divisiContainer.querySelectorAll('.divisi-row').length === 0) {
                // Beri default 3 divisi standar jika masih kosong
                ['Kitchen', 'Barista', 'Server'].forEach(name => createDivisiRow(name));
            }
        } else {
            sectionDivisi.style.display = 'none';
        }
    }

    kategoriSelect.addEventListener('change', toggleDivisiSection);

    btnAddDivisi.addEventListener('click', function() {
        createDivisiRow('');
    });

    quickButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            // Cek apakah sudah ada
            let exists = false;
            divisiContainer.querySelectorAll('input[name="divisi[]"]').forEach(inp => {
                if (inp.value.trim().toLowerCase() === name.toLowerCase()) {
                    exists = true;
                }
            });
            if (!exists) {
                createDivisiRow(name);
            }
        });
    });

    // Inisialisasi awal jika form gagal validasi (old input)
    @if(old('divisi'))
        @foreach(old('divisi') as $oldDiv)
            createDivisiRow("{{ $oldDiv }}");
        @endforeach
    @elseif(old('kategori') == 'Operasional')
        toggleDivisiSection();
    @endif
});
</script>

</x-app-layout>