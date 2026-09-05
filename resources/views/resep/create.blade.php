<x-app-layout>
<div class="container">

<h3>Tambah Resep</h3>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

<form action="{{ route('resep.store') }}" method="POST">
@csrf

{{-- PRODUK --}}
<div class="mb-3">
<label>Produk</label>
<select name="produk_id" class="form-control produk-select">
<option disabled selected>-- Pilih Produk --</option>
@foreach($produk as $p)
@php
    $tipeLabel = $p->is_bahan_setengah_jadi ? 'Bahan Setengah Jadi' : ($p->tipe_penjualan ?: 'Barang Jadi');
@endphp
<option value="{{ $p->id }}" data-satuan="{{ $p->satuan }}">
    {{ $p->nama }} ({{ $tipeLabel }})
</option>
@endforeach
</select>
</div>

{{-- OUTPUT --}}
<div class="mb-3">
<label>Output per Batch</label>
<input type="number" name="output_qty" class="form-control">
</div>

{{-- SATUAN OUTPUT (AUTO) --}}
<div class="mb-3">
<label>Satuan Output</label>
<input type="text" name="satuan_output" class="form-control satuan-output" readonly>
</div>

{{-- BTKL --}}
<div class="mb-3">
<label>BTKL per Batch</label>
<input type="integer" name="btkl_per_batch" class="form-control">
</div>

{{-- BOP --}}
<div class="mb-3">
<label>BOP per Batch</label>
<input type="integer" name="bop_per_batch" class="form-control">
</div>

<hr>

<h5>Bahan Baku</h5>

<table class="table" id="table-bahan">
<tr>
<th>Bahan</th>
<th>Qty / Produk</th>
<th>Satuan</th>
<th>Aksi</th>
</tr>

<tr>
<td>
<select name="bahan_id[]" class="form-control bahan-select">
@foreach($bahan as $b)
<option value="{{ $b->id }}" data-satuan="{{ $b->satuan }}">
    {{ $b->nama }} (Rp {{ number_format($b->hpp_referensi) }})
</option>
@endforeach
</select>
</td>

<td>
<input type="number" name="qty_bahan[]" class="form-control">
</td>

<td>
<input type="text" name="satuan[]" class="form-control satuan-input" readonly>
</td>

<td>
<button type="button" class="btn btn-danger btn-remove">X</button>
</td>
</tr>

</table>

<button type="button" class="btn btn-secondary mb-3" id="add-row">+ Tambah Bahan</button>


<div class="mt-3">
    <button class="btn btn-success">Simpan</button> 
    <a href="{{ route('resep.index') }}" class="btn btn-primary">Kembali</a>
</div>

</form>

</div>

{{-- SCRIPT --}}
<script>
// =======================
// AUTO SATUAN BAHAN
// =======================
function setSatuan(row) {
    let select = row.querySelector('.bahan-select');
    let satuan = select.options[select.selectedIndex].dataset.satuan;
    row.querySelector('.satuan-input').value = satuan ?? '';
}

// =======================
// TAMBAH BARIS
// =======================
document.getElementById('add-row').addEventListener('click', function() {
    let table = document.getElementById('table-bahan');
    let row = table.rows[1].cloneNode(true);

    row.querySelectorAll('input').forEach(el => el.value = '');

    let select = row.querySelector('.bahan-select');
    select.selectedIndex = 0;

    table.appendChild(row);

    setSatuan(row);
});

// =======================
// HAPUS BARIS
// =======================
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-remove')) {
        let row = e.target.closest('tr');
        let table = document.getElementById('table-bahan');

        if (table.rows.length > 2) {
            row.remove();
        }
    }
});

// =======================
// AUTO SAAT PILIH BAHAN
// =======================
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('bahan-select')) {
        let row = e.target.closest('tr');
        setSatuan(row);
    }
});

// =======================
// AUTO SAAT LOAD
// =======================
document.querySelectorAll('#table-bahan tr').forEach(row => {
    if (row.querySelector('.bahan-select')) {
        setSatuan(row);
    }
});

// =======================
// AUTO SATUAN OUTPUT PRODUK
// =======================
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('produk-select')) {
        let select = e.target;
        let satuan = select.options[select.selectedIndex].dataset.satuan;

        document.querySelector('.satuan-output').value = satuan ?? '';
    }
});

// set awal produk
let produkSelect = document.querySelector('.produk-select');

if (produkSelect && produkSelect.selectedIndex > 0) {
    let satuan = produkSelect.options[produkSelect.selectedIndex].dataset.satuan;
    document.querySelector('.satuan-output').value = satuan ?? '';
}
</script>

</x-app-layout>