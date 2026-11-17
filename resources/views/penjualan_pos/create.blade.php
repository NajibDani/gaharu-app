<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <style>
        .ts-dropdown {
            z-index: 9999 !important;
        }
        .table-responsive {
            overflow: visible !important;
        }
    </style>

<div class="container mt-4">

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {!! session('error') !!}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <h3 class="mb-3">Tambah Penjualan POS</h3>

    <div class="card">
        <div class="card-body">

            <form action="{{ route('penjualan_pos.store') }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="form-label font-weight-bold">Tanggal Transaksi</label>
                        <input type="datetime-local"
                               name="tanggal"
                               id="input-tanggal"
                               class="form-control"
                               value="{{ now()->format('Y-m-d\TH:i') }}"
                               required>
                    </div>

                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="form-label font-weight-bold">Gudang Operasional</label>
                        <select name="gudang_id" id="select-gudang" class="form-control" required>
                            <option value="">-- Pilih Gudang Operasional Cafe --</option>
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}" {{ (old('gudang_id') == $g->id || count($gudang) === 1 || (auth()->check() && auth()->user()->gudang_id == $g->id)) ? 'selected' : '' }}>
                                    {{ $g->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="form-label font-weight-bold">Divisi (Opsional)</label>
                        <select name="divisi_id" id="select-divisi" class="form-control">
                            <option value="">-- Pilih Divisi (Kitchen / Barista / dll) --</option>
                            @if(isset($divisis))
                                @foreach($divisis as $d)
                                    <option value="{{ $d->id }}" data-gudang-id="{{ $d->gudang_id }}" {{ old('divisi_id') == $d->id ? 'selected' : '' }}>
                                        {{ $d->nama }} ({{ $d->gudang->nama ?? '' }})
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <small class="text-muted" style="font-size: 0.75rem;">Pilih divisi operasional jika rekap khusus per divisi</small>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3">Rincian Produk Terjual (Rekap Akhir Hari)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="table-item">
                        <thead class="table-light">
                            <tr>
                                <th>Produk</th>
                                <th width="150">Qty</th>
                                <th width="200">Harga Jual</th>
                                <th width="200">Subtotal</th>
                                <th width="50">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="produk_id[]" class="form-control select-produk" required>
                                        <option value="">-- Pilih / Cari Produk --</option>
                                        @foreach($produk as $p)
                                            <option value="{{ $p->id }}" data-harga="{{ $p->harga_jual }}">
                                                {{ $p->nama }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number"
                                           step="0.01"
                                           name="qty[]"
                                           class="form-control qty"
                                           placeholder="0.00"
                                           required>
                                </td>
                                <td>
                                    <input type="number"
                                           step="0.01"
                                           name="harga[]"
                                           class="form-control harga"
                                           readonly>
                                </td>
                                <td>
                                    <input type="number"
                                           class="form-control subtotal"
                                           readonly>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm btn-remove">
                                        X
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Template Baris Baru untuk Penambahan Item dengan TomSelect --}}
                <template id="row-template">
                    <tr>
                        <td>
                            <select name="produk_id[]" class="form-control select-produk" required>
                                <option value="">-- Pilih / Cari Produk --</option>
                                @foreach($produk as $p)
                                    <option value="{{ $p->id }}" data-harga="{{ $p->harga_jual }}">
                                        {{ $p->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number"
                                   step="0.01"
                                   name="qty[]"
                                   class="form-control qty"
                                   placeholder="0.00"
                                   required>
                        </td>
                        <td>
                            <input type="number"
                                   step="0.01"
                                   name="harga[]"
                                   class="form-control harga"
                                   readonly>
                        </td>
                        <td>
                            <input type="number"
                                   class="form-control subtotal"
                                   readonly>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm btn-remove">
                                X
                            </button>
                        </td>
                    </tr>
                </template>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary" id="btn-add">
                        + Tambah Item Menu
                    </button>
                    
                    <h4 class="mb-0 text-primary">Total Omzet: Rp <span id="grand-total">0</span></h4>
                </div>

                <hr class="mt-4">

                <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                    <button type="submit" class="btn btn-primary px-4">
                        Simpan Data Rekap
                    </button>
                    <a href="{{ route('penjualan_pos.index') }}" class="btn btn-light border px-4">
                        Kembali
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // Inisialisasi TomSelect untuk searchable dropdown produk
    function initTomSelect(el) {
        if (!el || el.tomselect) return;
        return new TomSelect(el, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "-- Pilih / Cari Produk --",
            allowEmptyOption: true,
            dropdownParent: 'body',
            maxOptions: 1000,
            onChange: function(val) {
                fetchHarga(el);
            }
        });
    }

    // Inisialisasi produk select awal
    document.querySelectorAll('.select-produk').forEach(initTomSelect);

    // 1. TAMBAH BARIS ITEM BARU
    document.getElementById('btn-add').addEventListener('click', function(){
        const template = document.getElementById('row-template');
        const clone = template.content.cloneNode(true);
        const tbody = document.querySelector('#table-item tbody');
        tbody.appendChild(clone);

        const newRow = tbody.lastElementChild;
        const newSelect = newRow.querySelector('.select-produk');
        if (newSelect) {
            initTomSelect(newSelect);
        }
    });

    // 2. FUNGSI HITUNG GRAND TOTAL OMZET
    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('.subtotal').forEach(function(item){
            total += parseFloat(item.value || 0);
        });
        document.getElementById('grand-total').innerText = total.toLocaleString('id-ID');
    }

    // 3. FUNGSI HITUNG SUBTOTAL PER BARIS MENU
    function hitungSubtotal(row) {
        let qty = parseFloat(row.querySelector('.qty').value || 0);
        let harga = parseFloat(row.querySelector('.harga').value || 0);
        let subtotal = qty * harga;
        
        row.querySelector('.subtotal').value = subtotal;
        hitungTotal();
    }

    // 4. FUNGSI PANGGIL API HARGA AKTIF PERIODE
    function fetchHarga(selectElement) {
        const row = selectElement.closest('tr');
        if (!row) return;
        const inputHarga = row.querySelector('.harga'); 
        
        const produkId = selectElement.value;
        const tanggalInput = document.getElementById('input-tanggal').value; 

        if (produkId) {
            inputHarga.value = '...'; 

            const urlHarga = '/penjualan_pos/get-harga/' + produkId + '?tanggal=' + encodeURIComponent(tanggalInput);

            fetch(urlHarga)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Route salah atau Server Error (Status: ' + response.status + ')');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.has_resep === false) {
                        alert('Gagal! Produk "' + data.nama + '" belum memiliki resep bahan baku. Silakan atur resep terlebih dahulu.');
                        if (selectElement.tomselect) {
                            selectElement.tomselect.setValue('', true);
                        } else {
                            selectElement.selectedIndex = 0;
                        }
                        inputHarga.value = 0;
                        hitungSubtotal(row);
                        return;
                    }
                    if (!data || parseFloat(data.harga_pos) <= 0) {
                        alert('Gagal! Produk "' + (data ? data.nama : '') + '" belum memiliki harga jual POS yang aktif. Silakan atur harga jual terlebih dahulu.');
                        if (selectElement.tomselect) {
                            selectElement.tomselect.setValue('', true);
                        } else {
                            selectElement.selectedIndex = 0;
                        }
                        inputHarga.value = 0;
                        hitungSubtotal(row);
                        return;
                    }
                    inputHarga.value = data.harga_pos;
                    hitungSubtotal(row);
                })
                .catch(error => {
                    console.error('Error fetching price:', error);
                    alert('Gagal mengambil harga aktif: ' + error.message);
                    if (selectElement.tomselect) {
                        selectElement.tomselect.setValue('', true);
                    } else {
                        selectElement.selectedIndex = 0;
                    }
                    inputHarga.value = 0;
                    hitungSubtotal(row);
                });
        } else {
            inputHarga.value = 0;
            hitungSubtotal(row);
        }
    }

    // 5. EVENT DELEGATION (Menangani baris dinamis secara otomatis)
    const table = document.querySelector('#table-item');

    // Deteksi input pada Qty atau Harga Jual
    table.addEventListener('input', function(e) {
        if (e.target.classList.contains('qty') || e.target.classList.contains('harga')) {
            let row = e.target.closest('tr');
            hitungSubtotal(row);
        }
    });

    // Deteksi penghapusan baris menu
    table.addEventListener('click', function(e) {
        const btnRemove = e.target.closest('.btn-remove');
        if (btnRemove) {
            let rows = document.querySelectorAll('#table-item tbody tr');
            if(rows.length > 1) {
                let tr = btnRemove.closest('tr');
                let select = tr.querySelector('.select-produk');
                if (select && select.tomselect) {
                    select.tomselect.destroy();
                }
                tr.remove();
            } else {
                alert('Minimal harus ada 1 item produk dalam rekap penjualan!');
            }
            hitungTotal();
        }
    });

    // 6. DETEKSI PERUBAHAN TANGGAL REKAP (Untuk update harga otomatis)
    document.getElementById('input-tanggal').addEventListener('change', function() {
        document.querySelectorAll('.select-produk').forEach(function(selectElement) {
            if (selectElement.value !== "") {
                fetchHarga(selectElement);
            }
        });
    });

    // 7. FILTER PILIHAN DIVISI BERDASARKAN GUDANG OPERASIONAL
    const selectGudang = document.getElementById('select-gudang');
    const selectDivisi = document.getElementById('select-divisi');

    function filterDivisi() {
        if (!selectGudang || !selectDivisi) return;
        const selectedGudangId = selectGudang.value;
        const currentDivisiVal = selectDivisi.value;
        let hasSelectedVisible = false;

        Array.from(selectDivisi.options).forEach((opt, idx) => {
            if (idx === 0) return;
            const optGudangId = opt.getAttribute('data-gudang-id');
            if (!selectedGudangId || optGudangId === selectedGudangId) {
                opt.style.display = '';
                opt.disabled = false;
                if (opt.value === currentDivisiVal) hasSelectedVisible = true;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });

        if (!hasSelectedVisible && currentDivisiVal !== '') {
            selectDivisi.value = '';
        }
    }

    if (selectGudang) {
        selectGudang.addEventListener('change', filterDivisi);
        filterDivisi();
    }

});
</script>

</x-app-layout>