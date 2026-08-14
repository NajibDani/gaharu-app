<x-app-layout>

<div class="container">

<h3 class="mb-3">Edit Barang</h3>

<div class="card">
<div class="card-body">

<form action="{{ route('barang.update',$data->id) }}" method="POST">

@csrf
@method('PUT')

<div class="mb-3">

    <label>Kategori</label>

    <select name="kategori_id" class="form-control">

        @foreach($kategori as $k)

        <option value="{{ $k->id }}"
            {{ $data->kategori_id==$k->id?'selected':'' }}>

            {{ $k->nama }}

        </option>

        @endforeach

    </select>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label>Kode Barang</label>

        <input type="text"
               name="kode_barang"
               value="{{ $data->kode_barang }}"
               class="form-control"
               required>

    </div>

    <div class="col-md-6 mb-3">

        <label>Nama Barang</label>

        <input type="text"
               name="nama"
               value="{{ $data->nama }}"
               class="form-control"
               required>

    </div>

    <div class="col-md-6 mb-3">

        <label>Satuan</label>

        <input type="text"
               name="satuan"
               value="{{ $data->satuan }}"
               class="form-control">

    </div>

    <div class="col-md-6 mb-3">

        <label>Jenis Barang</label>

        <select name="jenis_utama"
                id="jenis"
                class="form-control"
                required>

            <option value="BAHAN_BAKU"
                {{ $data->jenis_utama=='BAHAN_BAKU'?'selected':'' }}>
                Bahan Baku
            </option>

            <option value="BAHAN_SETENGAH_JADI"
                {{ $data->jenis_utama=='BAHAN_SETENGAH_JADI'?'selected':'' }}>
                Bahan Setengah Jadi
            </option>

            <option value="BARANG_JADI"
                {{ $data->jenis_utama=='BARANG_JADI'?'selected':'' }}>
                Barang Jadi
            </option>

            <option value="OPERATIONAL"
                {{ $data->jenis_utama=='OPERATIONAL'?'selected':'' }}>
                Operational
            </option>

        </select>

    </div>

    <div class="col-md-6 mb-3"
         id="group-min-stock">

        <label class="text-danger">Minimum Stock (Batas Kritis)</label>

        <input type="number"
               name="minimum_stock"
               id="minimum_stock"
               value="{{ $data->minimum_stock }}"
               class="form-control">

    </div>

    <div class="col-12 mb-3" id="group-min-stock-bsj" style="display: none;">
        <div class="p-3 bg-light rounded-3 border">
            <label class="fw-bold small text-danger d-block mb-2">
                <i class="bi bi-shield-exclamation me-1"></i> Minimum Stock per Lokasi (Bahan Setengah Jadi - Opsional)
            </label>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="small text-secondary fw-semibold">Central Kitchen</label>
                    <input type="number" name="minimum_stock_ck" id="minimum_stock_ck" class="form-control" placeholder="Opsional" min="0" value="{{ old('minimum_stock_ck', $data->minimum_stock_ck) }}">
                </div>
                <div class="col-md-4">
                    <label class="small text-secondary fw-semibold">Outlet Kejingga</label>
                    <input type="number" name="minimum_stock_kejingga" id="minimum_stock_kejingga" class="form-control" placeholder="Opsional" min="0" value="{{ old('minimum_stock_kejingga', $data->minimum_stock_kejingga) }}">
                </div>
                <div class="col-md-4">
                    <label class="small text-secondary fw-semibold">Outlet Gaharu</label>
                    <input type="number" name="minimum_stock_gaharu" id="minimum_stock_gaharu" class="form-control" placeholder="Opsional" min="0" value="{{ old('minimum_stock_gaharu', $data->minimum_stock_gaharu) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3"
         id="group-tipe-penjualan">

        <label>Tipe Penjualan</label>

        <select name="tipe_penjualan" id="tipe_penjualan" class="form-control">
            <option value="">-- Pilih Tipe Penjualan --</option>
            @foreach($tipePenjualanOptions as $opt)
                <option value="{{ $opt }}" {{ $data->tipe_penjualan == $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>

    </div>

    <div class="col-md-6 mb-3">

        <label class="text-primary">Minimum Order (Batas Order)</label>

        <input type="number"
               name="minimum_order"
               id="minimum_order"
               value="{{ $data->minimum_order ?? 1 }}"
               class="form-control">

    </div>

</div>

<div class="mt-4">

    <button type="submit"
            class="btn btn-warning">
        Update
    </button>

    <a href="{{ route('barang.index') }}"
       class="btn btn-secondary">
        Kembali
    </a>

</div>

</form>

</div>
</div>

</div>

<script>

document.addEventListener("DOMContentLoaded",function(){

    const jenis=document.getElementById("jenis");
    const group=document.getElementById("group-min-stock");
    const groupBsj=document.getElementById("group-min-stock-bsj");
    const minimum=document.getElementById("minimum_stock");
    const minStockCk=document.getElementById("minimum_stock_ck");
    const minStockKejingga=document.getElementById("minimum_stock_kejingga");
    const minStockGaharu=document.getElementById("minimum_stock_gaharu");
    const groupTipePenjualan=document.getElementById("group-tipe-penjualan");
    const tipePenjualanSelect=document.getElementById("tipe_penjualan");

    function toggle(){

        if(jenis.value==="BAHAN_SETENGAH_JADI"){
            group.style.display="none";
            groupBsj.style.display="block";
        }else if(jenis.value==="BAHAN_BAKU"){
            group.style.display="block";
            groupBsj.style.display="none";
            if(minStockCk) minStockCk.value="";
            if(minStockKejingga) minStockKejingga.value="";
            if(minStockGaharu) minStockGaharu.value="";
        }else{
            group.style.display="none";
            groupBsj.style.display="none";
            minimum.value="";
            if(minStockCk) minStockCk.value="";
            if(minStockKejingga) minStockKejingga.value="";
            if(minStockGaharu) minStockGaharu.value="";
        }

        if(jenis.value==="BARANG_JADI"){
            groupTipePenjualan.style.display="block";
            tipePenjualanSelect.setAttribute('required', 'required');
        }else{
            groupTipePenjualan.style.display="none";
            tipePenjualanSelect.removeAttribute('required');
            tipePenjualanSelect.value="";
        }

    }

    jenis.addEventListener("change",toggle);

    toggle();

});

</script>

</x-app-layout>
