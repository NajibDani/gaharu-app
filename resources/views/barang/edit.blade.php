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
         id="group-min-stock" style="display: none;">

        <label class="text-danger">Minimum Stock (Batas Kritis)</label>

        <input type="number"
               name="minimum_stock"
               id="minimum_stock"
               value="{{ $data->minimum_stock }}"
               class="form-control">

    </div>

    @php
        $minStockMap = $data->minimumStocks ? $data->minimumStocks->mapWithKeys(fn($m) => [($m->gudang_id . '_' . ($m->divisi_id ?? 'none')) => ['qty' => (float)$m->minimum_stock, 'is_active' => (bool)$m->is_active]])->toArray() : [];
    @endphp

    <div class="col-12 mb-3" id="group-min-stock-bb" style="display: none;">
        <div class="p-3 rounded-3" style="background-color: #f8fafc; border: 1.5px dashed #cbd5e1;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="fw-bold small text-danger mb-0">
                    <i class="bi bi-shield-exclamation me-1"></i> Minimum Stock per Outlet &amp; Divisi (Bahan Baku - Opsional)
                </label>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">Tidak Wajib Diisi</span>
            </div>
            <p class="text-muted small mb-3" style="font-size: 0.78rem;">
                Tentukan batas minimum stok di setiap outlet dan divisi. Kosongkan jika tidak ada batas minimum.
            </p>
            <div class="row g-3">
                @foreach($gudangList as $g)
                    <div class="col-md-6">
                        <div class="card h-100 rounded-3 shadow-sm" style="border: 1.5px solid #e2e8f0; background: #ffffff;">
                            <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between" style="background-color: #f1f5f9; border-bottom: 1.5px solid #e2e8f0;">
                                <span class="fw-bold text-dark small">
                                    <i class="bi bi-geo-alt-fill me-1" style="color: #d88656;"></i>{{ $g->nama }}
                                </span>
                                <span class="badge bg-white text-secondary border shadow-xs" style="font-size: 0.68rem; font-weight: 600;">{{ $g->kategori }}</span>
                            </div>
                            <div class="card-body p-3">
                                @if($g->divisi && $g->divisi->count() > 0)
                                    <div class="row g-2">
                                        @foreach($g->divisi as $div)
                                            @php
                                                $key = $g->id . '_' . $div->id;
                                                $savedData = $minStockMap[$key] ?? null;
                                                $val = old('min_stock_outlet.' . $g->id . '.' . $div->id, ($savedData && $savedData['qty'] > 0 ? $savedData['qty'] : null));
                                                $isActive = old('min_stock_active.' . $g->id . '.' . $div->id, ($savedData ? $savedData['is_active'] : true));
                                            @endphp
                                            <div class="col-4">
                                                <div class="outlet-col-box p-2 rounded-3 border h-100" id="box_edit_page_{{ $g->id }}_{{ $div->id }}" style="background: {{ $isActive ? '#ffffff' : '#f1f5f9' }}; border-color: {{ $isActive ? '#e2e8f0' : '#cbd5e1' }}; opacity: {{ $isActive ? '1' : '0.65' }}; transition: all 0.2s ease;">
                                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                                        <label class="small fw-semibold mb-0 text-truncate label-outlet-name" title="{{ $div->nama }}" style="font-size: 0.75rem; color: {{ $isActive ? '#212529' : '#94a3b8' }}; text-decoration: {{ $isActive ? 'none' : 'line-through' }};">
                                                            {{ $div->nama }}
                                                        </label>
                                                        <div class="form-check form-switch m-0 p-0 d-flex align-items-center" title="Status Aktif/Non-Aktif di Divisi ini">
                                                            <input type="hidden" name="min_stock_active[{{ $g->id }}][{{ $div->id }}]" value="0">
                                                            <input class="form-check-input ms-0 outlet-active-toggle" type="checkbox" role="switch"
                                                                name="min_stock_active[{{ $g->id }}][{{ $div->id }}]" 
                                                                value="1" 
                                                                id="edit_page_active_{{ $g->id }}_{{ $div->id }}"
                                                                {{ $isActive ? 'checked' : '' }}
                                                                style="cursor: pointer; width: 1.8em; height: 0.9em;">
                                                        </div>
                                                    </div>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="any" min="0" 
                                                            name="min_stock_outlet[{{ $g->id }}][{{ $div->id }}]" 
                                                            id="edit_page_min_stock_{{ $g->id }}_{{ $div->id }}"
                                                            class="form-control form-control-sm text-center fw-semibold" 
                                                            style="border-radius: 6px; border: 1px solid #cbd5e1; background: {{ $isActive ? '#fafafa' : '#e2e8f0' }};"
                                                            placeholder="Opsional"
                                                            value="{{ $val }}"
                                                            {{ !$isActive ? 'disabled' : '' }}>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    @php
                                        $keyNone = $g->id . '_none';
                                        $savedDataNone = $minStockMap[$keyNone] ?? null;
                                        $valNone = old('min_stock_outlet.' . $g->id . '.none', ($savedDataNone && $savedDataNone['qty'] > 0 ? $savedDataNone['qty'] : null));
                                        $isActiveNone = old('min_stock_active.' . $g->id . '.none', ($savedDataNone ? $savedDataNone['is_active'] : true));
                                    @endphp
                                    <div class="outlet-col-box p-2 rounded-3 border" id="box_edit_page_{{ $g->id }}_none" style="background: {{ $isActiveNone ? '#ffffff' : '#f1f5f9' }}; border-color: {{ $isActiveNone ? '#e2e8f0' : '#cbd5e1' }}; opacity: {{ $isActiveNone ? '1' : '0.65' }}; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <label class="small fw-semibold mb-0 label-outlet-name" style="font-size: 0.75rem; color: {{ $isActiveNone ? '#212529' : '#94a3b8' }}; text-decoration: {{ $isActiveNone ? 'none' : 'line-through' }};">
                                                Min Stock
                                            </label>
                                            <div class="form-check form-switch m-0 p-0 d-flex align-items-center" title="Status Aktif/Non-Aktif di Outlet ini">
                                                <input type="hidden" name="min_stock_active[{{ $g->id }}][none]" value="0">
                                                <input class="form-check-input ms-0 outlet-active-toggle" type="checkbox" role="switch"
                                                    name="min_stock_active[{{ $g->id }}][none]" 
                                                    value="1" 
                                                    id="edit_page_active_{{ $g->id }}_none"
                                                    {{ $isActiveNone ? 'checked' : '' }}
                                                    style="cursor: pointer; width: 1.8em; height: 0.9em;">
                                            </div>
                                        </div>
                                        <input type="number" step="any" min="0" 
                                            name="min_stock_outlet[{{ $g->id }}][none]" 
                                            id="edit_page_min_stock_{{ $g->id }}_none"
                                            class="form-control form-control-sm fw-semibold" 
                                            style="border-radius: 6px; border: 1px solid #cbd5e1; background: {{ $isActiveNone ? '#fafafa' : '#e2e8f0' }};"
                                            placeholder="Opsional"
                                            value="{{ $valNone }}"
                                            {{ !$isActiveNone ? 'disabled' : '' }}>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
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
    const groupBb=document.getElementById("group-min-stock-bb");
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
            if(groupBb) groupBb.style.display="none";
            groupBsj.style.display="block";
        }else if(jenis.value==="BAHAN_BAKU"){
            group.style.display="none";
            if(groupBb) groupBb.style.display="block";
            groupBsj.style.display="none";
            if(minStockCk) minStockCk.value="";
            if(minStockKejingga) minStockKejingga.value="";
            if(minStockGaharu) minStockGaharu.value="";
        }else{
            group.style.display="none";
            if(groupBb) groupBb.style.display="none";
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

    // Event listener untuk toggle aktif/nonaktif input enable/disable & visual styling
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('outlet-active-toggle')) {
            var boxId = e.target.id.replace('edit_page_active_', 'box_edit_page_');
            var targetId = e.target.id.replace('edit_page_active_', 'edit_page_min_stock_');
            var box = document.getElementById(boxId);
            var input = document.getElementById(targetId);

            if (box) {
                var label = box.querySelector('.label-outlet-name');
                if (e.target.checked) {
                    box.style.background = '#ffffff';
                    box.style.borderColor = '#e2e8f0';
                    box.style.opacity = '1';
                    if (label) {
                        label.style.color = '#212529';
                        label.style.textDecoration = 'none';
                    }
                    if (input) {
                        input.disabled = false;
                        input.style.background = '#fafafa';
                    }
                } else {
                    box.style.background = '#f1f5f9';
                    box.style.borderColor = '#cbd5e1';
                    box.style.opacity = '0.65';
                    if (label) {
                        label.style.color = '#94a3b8';
                        label.style.textDecoration = 'line-through';
                    }
                    if (input) {
                        input.disabled = true;
                        input.value = '';
                        input.style.background = '#e2e8f0';
                    }
                }
            }
        }
    });

});

</script>

</x-app-layout>
