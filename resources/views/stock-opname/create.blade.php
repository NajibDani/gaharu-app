<x-app-layout>

<x-slot name="header">
    Stock Opname
</x-slot>

<div class="container-fluid">

    {{-- HEADER --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="fw-bold mb-1">
                Stock Opname Gudang
            </h4>

            <p class="text-muted mb-0">
                Lakukan penyesuaian stok berdasarkan hasil perhitungan fisik gudang.
            </p>

        </div>

        <a href="{{ route('stock-opname.index') }}"
           class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>
            Kembali

        </a>

    </div>

<form
    id="formOpname"
    method="POST"
    action="{{ route('stock-opname.store') }}">

    @csrf

    <input
        type="hidden"
        id="gudang_id"
        name="gudang_id"
        value="{{ $gudang->id }}">

    <input
        type="hidden"
        id="divisi_id"
        name="divisi_id"
        value="{{ $divisiId ?? '' }}">

<div class="row mb-4">

    <div class="col-md-3">

        <div class="card border-0 shadow-sm rounded-4 h-100">

            <div class="card-body">

                <small class="text-muted">
                    Gudang & Divisi
                </small>

                <h5 class="fw-bold mb-0 text-dark">
                    {{ $gudang->nama }}
                </h5>
                @if($divisi)
                    <span class="badge bg-light text-primary border border-primary-subtle mt-1 fs-6">
                        <i class="bi bi-diagram-3 me-1"></i>{{ $divisi->nama }}
                    </span>
                @endif

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-0 shadow-sm rounded-4 h-100">

            <div class="card-body">

                <label for="tanggal" class="small text-muted fw-bold mb-1">
                    Tanggal Opname
                </label>

                <input
                    type="date"
                    id="tanggal"
                    name="tanggal"
                    class="form-control form-control-sm fw-bold"
                    value="{{ old('tanggal', date('Y-m-d')) }}"
                    required>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-0 shadow-sm rounded-4 h-100">

            <div class="card-body">

                <small class="text-muted">
                    Status
                </small>

                <h5 class="fw-bold text-warning mb-0">
                    Draft
                </h5>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-0 shadow-sm rounded-4 h-100">

            <div class="card-body">

                <small class="text-muted">
                    Total Item
                </small>

                <h5 class="fw-bold mb-0"
                    id="totalItem">

                    0

                </h5>

            </div>

        </div>

    </div>

</div>

        {{-- DETAIL OPNAME --}}

        <div class="card border-0 shadow-sm rounded-4">

            <div class="card-header text-white fw-bold"
                 style="background:#7A4517;">

                Detail Stock Opname

            </div>

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table align-middle mb-0"
                           id="tableBarang">

                        <thead>

                        <tr style="background:#7A4517;color:white">

                            <th width="80">
                                Kode
                            </th>

                            <th>
                                Nama Barang
                            </th>

                            <th width="140">
                                Satuan
                            </th>

                            <th width="160">
                                Stok Sistem
                            </th>

                            <th width="160">
                                Stok Fisik
                            </th>

                            <th width="160">
                                Selisih
                            </th>

                            <th width="180">
                                Nilai Selisih
                            </th>

                        </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td colspan="7"
                                    class="text-center py-4 text-muted">

                                    Memuat data barang...

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

        {{-- KETERANGAN --}}

        <div class="card border-0 shadow-sm rounded-4 mt-4">

            <div class="card-body">

                <label class="form-label fw-semibold">

                    Keterangan

                </label>

                <textarea
                    name="keterangan"
                    rows="3"
                    class="form-control"
                    placeholder="Catatan stock opname..."></textarea>

            </div>

        </div>

        {{-- TOTAL --}}

        <div class="card border-0 shadow-sm rounded-4 mt-4">

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">

                        <h6 class="mb-1">
                            Total Selisih Nilai Persediaan
                        </h6>

                        <h3 class="fw-bold text-danger"
                            id="grandTotal">

                            Rp 0

                        </h3>

                    </div>

                    <div class="col-md-6 text-end">

                        <button
                            type="submit"
                            class="btn btn-success px-5">

                            Simpan Draft Stock Opname

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </form>

</div>

<script>

function loadBarang()
{
    let gudangId =
        document.getElementById('gudang_id').value;
    let divisiId =
        document.getElementById('divisi_id').value;

    if(!gudangId)
    {
        alert('Pilih gudang terlebih dahulu');
        return;
    }

    fetch(
        "{{ route('stock-opname.load-barang') }}",
        {
            method:'POST',
            headers:{
                'X-CSRF-TOKEN':
                '{{ csrf_token() }}',
                'Content-Type':
                'application/json'
            },
            body:JSON.stringify({
                gudang_id: gudangId,
                divisi_id: divisiId || null
            })
        }
    )

    .then(response => response.json())

    .then(data => {

        let tbody =
            document.querySelector(
                '#tableBarang tbody'
            );

        tbody.innerHTML = '';

        if(data.length === 0)
        {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7"
                        class="text-center text-muted py-4">
                        Tidak ada barang pada gudang ini
                    </td>
                </tr>
            `;
            return;
        }

        data.forEach(function(item){

            tbody.innerHTML += `

            <tr>

                <td>

                    ${item.kode_barang}

                    <input
                        type="hidden"
                        name="barang_id[]"
                        value="${item.id}">

                </td>

                <td>
                    ${item.nama}
                </td>

                <td>
                    ${item.satuan}
                </td>

                <td>

                    ${parseFloat(item.stok || 0)
                        .toLocaleString('id-ID')}

                    <input
                        type="hidden"
                        name="stok_sistem[]"
                        value="${item.stok}">

                    <input
                        type="hidden"
                        class="harga-rata"
                        value="${item.harga_fifo || 0}">

                </td>

                <td>

                    <input
                        type="number"
                        step="0.01"
                        class="form-control stok-fisik"
                        name="stok_fisik[]"
                        data-stok="${item.stok}"
                        value="${item.stok}">

                </td>

                <td>

                    <span class="selisih text-secondary">
                        0
                    </span>

                </td>

                <td>

                    <span class="nilai">
                        Rp 0
                    </span>

                </td>

            </tr>

            `;

        });

        document
        .querySelectorAll('.stok-fisik')
        .forEach(function(input){

            input.dispatchEvent(
                new Event('input')
            );

        });

    })

    .catch(function(error){

    console.error(error);

    alert(
        'Gagal memuat data barang'
    );

});

}

document.addEventListener(
    'DOMContentLoaded',
    function(){

        loadBarang();

    }
);

document.addEventListener(
    'input',
    function(e){

        if(
            !e.target.classList.contains(
                'stok-fisik'
            )
        ){
            return;
        }

        let row =
            e.target.closest('tr');

        let stokSistem =
            parseFloat(
                e.target.dataset.stok
            ) || 0;

        let stokFisik =
            parseFloat(
                e.target.value
            ) || 0;


        let selisih =
            stokFisik -
            stokSistem;

        let barangId =
    row.querySelector(
        'input[name="barang_id[]"]'
    ).value;

let gudangId =
    document.getElementById(
        'gudang_id'
    ).value;
let divisiId =
    document.getElementById(
        'divisi_id'
    ).value;

    fetch(
    "{{ route('stock-opname.hitung-fifo') }}",
    {
        method:'POST',
        headers:{
            'X-CSRF-TOKEN':
            '{{ csrf_token() }}',
            'Content-Type':
            'application/json'
        },
        body:JSON.stringify({

            gudang_id:
                gudangId,

            divisi_id:
                divisiId || null,

            barang_id:
                barangId,

            selisih:
                Math.abs(selisih)

        })
    }
)

.then(res => res.json())

.then(result => {

});

        let selisihElement =
            row.querySelector(
                '.selisih'
            );

        selisihElement.innerHTML =
            selisih.toLocaleString(
                'id-ID'
            );

        if(selisih > 0)
        {
            selisihElement.className =
                'selisih text-success fw-bold';
        }
        else if(selisih < 0)
        {
            selisihElement.className =
                'selisih text-danger fw-bold';
        }
        else
        {
            selisihElement.className =
                'selisih text-secondary';
        }

        row.querySelector('.nilai')
        .innerHTML =
            'Rp ' +
            nilai.toLocaleString(
                'id-ID'
            );

        hitungGrandTotal();

    }
);

function hitungGrandTotal()
{
    let total = 0;

    document
    .querySelectorAll('.nilai')
    .forEach(function(el){

        let angka =
            el.innerText
            .replace('Rp','')
            .replace(/\./g,'')
            .replace(/,/g,'');

        total += Number(
            angka || 0
        );

    });

    document.getElementById(
        'grandTotal'
    ).innerHTML =
        'Rp ' +
        total.toLocaleString(
            'id-ID'
        );
}

</script>

</x-app-layout>