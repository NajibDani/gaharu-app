<x-app-layout>
<div class="container">

<h3>Edit Resep</h3>

<form action="{{ route('resep.update', $data->id) }}" method="POST">
@csrf
@method('PUT')

{{-- PRODUK --}}
<div class="mb-3">
<label>Produk</label>
<select name="produk_id" class="form-control">
@foreach($produk as $p)
<option value="{{ $p->id }}"
    {{ $data->produk_id == $p->id ? 'selected' : '' }}>
    {{ $p->nama }} {{ $p->is_bahan_setengah_jadi ? '(Bahan Setengah Jadi)' : '(Barang Jadi)' }}
</option>
@endforeach
</select>
</div>

{{-- OUTPUT --}}
<div class="mb-3">
<label>Output</label>
<input type="number" name="output_qty"
value="{{ (int) $data->output_qty }}" class="form-control">
</div>

<div class="mb-3">
<label>Satuan Output</label>

<input type="text"
value="{{ $data->satuan_output }}"
class="form-control" readonly>

<input type="hidden"
name="satuan_output"
value="{{ $data->satuan_output }}">
</div>

{{-- BTKL --}}
<div class="mb-3">
<label>BTKL</label>
<input type="integer" name="btkl_per_batch"
value="{{ (int) $data->btkl_per_batch }}" class="form-control">
</div>

{{-- BOP --}}
<div class="mb-3">
<label>BOP</label>
<input type="integer" name="bop_per_batch"
value="{{ (int) $data->bop_per_batch }}" class="form-control">
</div>

<hr>

<h5>Bahan Baku</h5>

<table class="table" id="table-bahan">
<tr>
<th>Bahan</th>
<th>Qty</th>
<th>Satuan</th>
<th>Aksi</th>
</tr>

@foreach($data->bahanbaku as $b)
<tr>
<td>
<select name="bahan_id[]" class="form-control bahan-select">
@foreach($bahan as $bb)
<option value="{{ $bb->id }}" 
        data-satuan="{{ $bb->satuan }}"
        {{ $b->bahan_id == $bb->id ? 'selected' : '' }}>
    {{ $bb->nama }}
</option>
@endforeach
</select>
</td>

<td>
<input type="number" name="qty_bahan[]"
value="{{ (int) $b->qty_bahan }}" class="form-control">
</td>

<td>
<input type="text" name="satuan[]" 
value="{{ $b->satuan }}" 
class="form-control" readonly>
</td>

<td>
<button type="button" class="btn btn-danger remove">X</button>
</td>
</tr>
@endforeach

</table>

<div class="mt-3">
    <button type="button" class="btn btn-secondary" id="add-row"> + Tambah Bahan</button> 
    <button class="btn btn-success">Update</button> 
    <a href="{{ route('resep.index') }}" class="btn btn-primary">Kembali</a>
</div>
<script>
// tambah baris
document.getElementById('add-row').onclick = function() {
    let table = document.getElementById('table-bahan');
    let row = table.rows[1].cloneNode(true);

    // reset input (kecuali satuan)
    row.querySelectorAll('input').forEach(e => {
        if (e.name !== 'satuan[]') {
            e.value = '';
        }
    });

    table.appendChild(row);

    setSatuan(row); // langsung isi satuan
};

// hapus baris
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove')) {
        e.target.closest('tr').remove();
    }
});

//FUNCTION
function setSatuan(row) {
    let select = row.querySelector('.bahan-select');
    let satuan = select.options[select.selectedIndex].dataset.satuan;

    row.querySelector('input[name="satuan[]"]').value = satuan ?? '';
}

// saat pilih bahan
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('bahan-select')) {
        let row = e.target.closest('tr');
        setSatuan(row);
    }
});

// set saat load awal
document.querySelectorAll('#table-bahan tr').forEach(row => {
    if (row.querySelector('.bahan-select')) {
        setSatuan(row);
    }
});
</script>
</x-app-layout>