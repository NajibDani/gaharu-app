<x-app-layout>

<x-slot name="header">
    Edit Pengeluaran Bahan Baku
</x-slot>

{{-- TomSelect CSS for searchable select --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .ts-wrapper .ts-control {
        border-radius: 8px;
        border-color: #dee2e6;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    .ts-wrapper.focus .ts-control {
        border-color: #d88656;
        box-shadow: 0 0 0 0.25rem rgba(216, 134, 86, 0.25);
    }
    .ts-dropdown .active {
        background-color: #f7f3ee;
        color: #9c4f18;
        font-weight: 600;
    }
</style>

<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-header-title">
                Edit Pengeluaran Bahan Baku
            </h1>
            <p class="text-muted mb-0">
                Ubah informasi gudang tujuan, tambah/hapus bahan baku, atau sesuaikan kuantitas permintaan.
            </p>
        </div>
        <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header text-white fw-bold py-3"
        style="background:#9c4f18; border-radius:24px 24px 0 0;">
        <i class="bi bi-pencil-square me-2"></i> Form Edit Pengeluaran: {{ $pengeluaran->kode_pengeluaran }}
    </div>

    <div class="card-body p-4">
        <form action="{{ route('pengeluaran-bahan-baku.update', $pengeluaran->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="form-label fw-bold">Gudang Tujuan</label>
                <select name="gudang_id" class="form-select" required>
                    @foreach($gudang as $g)
                        <option value="{{ $g->id }}" {{ $pengeluaran->gudang_id == $g->id ? 'selected' : '' }}>
                            {{ $g->nama }} - {{ $g->kategori }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">
                    Bahan baku akan dipindahkan dari Gudang Utama ke gudang tujuan yang dipilih.
                </small>
            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Detail Bahan Baku</h5>
                <button type="button" onclick="tambahBaris()" class="btn btn-sm"
                    style="background:#f7f3ee; border:1px solid #d88656; color:#9c4f18; border-radius:10px;">
                    <i class="bi bi-plus-circle"></i> Tambah Barang
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" id="table-detail">
                    <thead style="background:#5a3416; color:white;">
                        <tr>
                            <th>Barang (Ketik Nama / Kode untuk Mencari)</th>
                            <th width="200">Qty Keluar</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pengeluaran->details as $detail)
                            <tr>
                                <td>
                                    <select name="barang_id[]" class="form-select barang-select" required>
                                        <option value="">-- Pilih / Cari Bahan Baku --</option>
                                        @foreach($barang as $b)
                                            <option value="{{ $b->id }}" data-stok="{{ $b->stok }}"
                                                {{ $detail->barang_id == $b->id ? 'selected' : '' }}>
                                                {{ $b->kode_barang }} - {{ $b->nama }} ({{ $b->satuan }}) - Stok Utama: {{ $b->stok }}
                                                @if($b->stok <= 0) [HABIS] @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="qty[]" value="{{ $detail->qty }}"
                                        class="form-control qty-input" min="0.01" step="any" placeholder="Qty" required>
                                    <small class="text-danger stok-warning d-block mt-1" style="display:none;"></small>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="hapusBaris(this)">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td>
                                    <select name="barang_id[]" class="form-select barang-select" required>
                                        <option value="">-- Pilih / Cari Bahan Baku --</option>
                                        @foreach($barang as $b)
                                            <option value="{{ $b->id }}" data-stok="{{ $b->stok }}">
                                                {{ $b->kode_barang }} - {{ $b->nama }} ({{ $b->satuan }}) - Stok Utama: {{ $b->stok }}
                                                @if($b->stok <= 0) [HABIS] @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="qty[]" class="form-control qty-input" min="0.01" step="any" placeholder="Qty" required>
                                    <small class="text-danger stok-warning d-block mt-1" style="display:none;"></small>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="hapusBaris(this)">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <label class="form-label fw-bold">Keterangan</label>
                <textarea name="keterangan" rows="4" class="form-control"
                    placeholder="Contoh: Pengeluaran bahan baku untuk produksi / restock outlet">{{ $pengeluaran->keterangan }}</textarea>
            </div>

            <div class="p-3 rounded mt-4" style="background:#fff8e8; border:1px solid #f2d28c; color:#7a5a00;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Stok belum berpindah saat data disimpan. Pengurangan stok FIFO baru dilakukan setelah pengeluaran disetujui (Approved).
            </div>

            <div class="mt-4">
                <button type="submit" class="btn"
                    style="background:#d88656; color:white; font-weight:600; padding:12px 24px; border-radius:12px;">
                    <i class="bi bi-save me-2"></i> Update Pengeluaran
                </button>
            </div>
        </form>
    </div>
</div>

{{-- TomSelect JS for searchable select --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<script>
function initTomSelect(selectEl) {
    if (!selectEl || selectEl.tomselect) return selectEl.tomselect;
    return new TomSelect(selectEl, {
        create: false,
        allowEmptyOption: true,
        placeholder: '-- Ketik / Cari Bahan Baku --',
        maxOptions: 500,
        sortField: {
            field: "text",
            direction: "asc"
        },
        onChange: function(value) {
            let row = selectEl.closest('tr');
            if (row) checkStok(row);
        }
    });
}

function tambahBaris(barangId = '', qty = '')
{
    let tbody = document.querySelector('#table-detail tbody');

    let tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="barang_id[]" class="form-select barang-select" required>
                <option value="">-- Ketik / Cari Bahan Baku --</option>
                @foreach($barang as $b)
                    <option value="{{ $b->id }}" data-stok="{{ $b->stok }}">
                        {{ $b->kode_barang }} - {{ $b->nama }} ({{ $b->satuan }}) - Stok Utama: {{ $b->stok }}
                        @if($b->stok <= 0) [HABIS] @endif
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="qty[]" class="form-control qty-input" min="0.01" step="any" placeholder="Qty" required>
            <small class="text-danger stok-warning d-block mt-1" style="display:none;"></small>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="hapusBaris(this)">
                Hapus
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    let newSelect = tr.querySelector('.barang-select');
    let tsInstance = initTomSelect(newSelect);

    if (barangId && tsInstance) {
        tsInstance.setValue(barangId);
    } else if (barangId) {
        newSelect.value = barangId;
    }

    if (qty !== '') {
        tr.querySelector('.qty-input').value = qty;
    }

    checkStok(tr);
    return tr;
}

function hapusBaris(button)
{
    let row = button.closest('tr');
    let select = row.querySelector('.barang-select');

    if (document.querySelectorAll('#table-detail tbody tr').length > 1) {
        if (select && select.tomselect) {
            select.tomselect.destroy();
        }
        row.remove();
    } else {
        if (select && select.tomselect) {
            select.tomselect.clear();
        } else if (select) {
            select.value = '';
        }
        row.querySelector('.qty-input').value = '';
        checkStok(row);
    }
}

function checkStok(row) {
    let select = row.querySelector('.barang-select');
    let qtyInput = row.querySelector('.qty-input');
    let warning = row.querySelector('.stok-warning');

    if (!select || !qtyInput || !warning) return;

    let selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || select.value === "") {
        warning.style.display = "none";
        return;
    }

    let stok = parseFloat(selectedOption.getAttribute('data-stok')) || 0;
    let qty = parseFloat(qtyInput.value) || 0;

    if (qty > stok) {
        warning.innerHTML = `⚠️ Stok Gudang Utama tidak mencukupi! Tersedia: <strong>${stok}</strong>`;
        warning.style.display = "block";
    } else {
        warning.style.display = "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.barang-select').forEach(function(el) {
        initTomSelect(el);
    });

    document.querySelectorAll('#table-detail tbody tr').forEach(function(row) {
        checkStok(row);
    });
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('qty-input')) {
        let row = e.target.closest('tr');
        checkStok(row);
    }
});
</script>

</x-app-layout>