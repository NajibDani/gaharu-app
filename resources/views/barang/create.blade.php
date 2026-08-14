<x-app-layout>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="container py-4">

    <h3 class="mb-3 fw-bold" style="color: #9c4f18;">Tambah Barang</h3>

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <form action="{{ route('barang.store') }}" method="POST" id="formTambahBarang">
                @csrf

                <div class="mb-3">
                    <label class="fw-semibold small text-muted">Kategori</label>
                    <select name="kategori_id" id="kategori_id" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($kategori as $k)
                            <option value="{{ $k->id }}">
                                {{ $k->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="fw-semibold small text-muted">Kode Barang</label>
                        <input type="text" name="kode_barang" id="kode_barang" class="form-control" readonly required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-semibold small text-muted">Nama Barang</label>
                        <input type="text" name="nama" id="nama_barang" class="form-control" required autocomplete="off">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-semibold small text-muted">Satuan</label>
                        <input type="text" name="satuan" class="form-control" required placeholder="Contoh: kg, pcs, liter">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-semibold small text-muted">Jenis Barang</label>
                        <select name="jenis_utama" id="jenis" class="form-control" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="BAHAN_BAKU">Bahan Baku</option>
                            <option value="BAHAN_SETENGAH_JADI">Bahan Setengah Jadi</option>
                            <option value="BARANG_JADI">Barang Jadi</option>
                            <option value="OPERATIONAL">Operational</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3" id="group-min-stock">
                        <label class="fw-semibold small text-danger">Minimum Stock (Batas Kritis)</label>
                        <input type="number" name="minimum_stock" id="minimum_stock" class="form-control" placeholder="Contoh: 10" min="0">
                    </div>

                    <div class="col-12 mb-3" id="group-min-stock-bsj" style="display: none;">
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="fw-bold small text-danger d-block mb-2">
                                <i class="bi bi-shield-exclamation me-1"></i> Minimum Stock per Lokasi (Bahan Setengah Jadi - Opsional)
                            </label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="small text-secondary fw-semibold">Central Kitchen</label>
                                    <input type="number" name="minimum_stock_ck" id="minimum_stock_ck" class="form-control" placeholder="Opsional" min="0" value="{{ old('minimum_stock_ck') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-secondary fw-semibold">Outlet Kejingga</label>
                                    <input type="number" name="minimum_stock_kejingga" id="minimum_stock_kejingga" class="form-control" placeholder="Opsional" min="0" value="{{ old('minimum_stock_kejingga') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-secondary fw-semibold">Outlet Gaharu</label>
                                    <input type="number" name="minimum_stock_gaharu" id="minimum_stock_gaharu" class="form-control" placeholder="Opsional" min="0" value="{{ old('minimum_stock_gaharu') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3" id="group-tipe-penjualan">
                        <label class="fw-semibold small text-muted">Tipe Penjualan</label>
                        <select name="tipe_penjualan" id="tipe_penjualan" class="form-control">
                            <option value="">-- Pilih Tipe Penjualan --</option>
                            <option value="POS Gaharu">POS Gaharu</option>
                            <option value="POS Kejingga">POS Kejingga</option>
                            <option value="B2B">B2B</option>
                        </select>
                    </div>

                </div>

                <div class="mt-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn text-white" style="background-color: #d88656; border: none;">Simpan</button>
                        <a href="{{ route('barang.index') }}" class="btn btn-secondary">Kembali</a>
                    </div>
                </div>

            </form>

        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const jenis = document.getElementById('jenis');
    const groupMinStock = document.getElementById('group-min-stock');
    const groupMinStockBsj = document.getElementById('group-min-stock-bsj');
    const minStockInput = document.getElementById('minimum_stock');
    const minStockCk = document.getElementById('minimum_stock_ck');
    const minStockKejingga = document.getElementById('minimum_stock_kejingga');
    const minStockGaharu = document.getElementById('minimum_stock_gaharu');
    const groupTipePenjualan = document.getElementById('group-tipe-penjualan');
    const tipePenjualanSelect = document.getElementById('tipe_penjualan');

    function toggleForm() {
        if (jenis.value === "BAHAN_SETENGAH_JADI") {
            groupMinStock.style.display = "none";
            groupMinStockBsj.style.display = "block";
            minStockInput.value = "";
        } else if (jenis.value === "BAHAN_BAKU") {
            groupMinStock.style.display = "block";
            groupMinStockBsj.style.display = "none";
            if (minStockCk) minStockCk.value = "";
            if (minStockKejingga) minStockKejingga.value = "";
            if (minStockGaharu) minStockGaharu.value = "";
        } else {
            groupMinStock.style.display = "none";
            groupMinStockBsj.style.display = "none";
            minStockInput.value = "";
            if (minStockCk) minStockCk.value = "";
            if (minStockKejingga) minStockKejingga.value = "";
            if (minStockGaharu) minStockGaharu.value = "";
        }

        if (jenis.value === "BARANG_JADI") {
            groupTipePenjualan.style.display = "block";
            tipePenjualanSelect.setAttribute('required', 'required');
        } else {
            groupTipePenjualan.style.display = "none";
            tipePenjualanSelect.removeAttribute('required');
            tipePenjualanSelect.value = "";
        }
    }

    jenis.addEventListener('change', toggleForm);
    toggleForm();

    const kategori = document.getElementById('kategori_id');
    kategori.addEventListener('change', function () {
        let kategoriId = this.value;
        if (kategoriId == "") {
            document.getElementById('kode_barang').value = "";
            return;
        }

        fetch("{{ route('barang.generate-kode', ':kategori') }}".replace(':kategori', kategoriId))
            .then(response => response.json())
            .then(data => {
                document.getElementById('kode_barang').value = data.kode_barang;
            });
    });

    if (kategori.value != "") {
        kategori.dispatchEvent(new Event('change'));
    }

    // VALIDASI NAMA BARANG DUPLIKAT DENGAN AJAX
    const form = document.getElementById('formTambahBarang');
    const namaInput = document.getElementById('nama_barang');
    let bypassCheck = false;

    form.addEventListener('submit', function (e) {
        if (bypassCheck) return;

        e.preventDefault();
        const namaVal = namaInput.value.trim();
        if (!namaVal) return;

        fetch("{{ route('barang.check-nama') }}?nama=" + encodeURIComponent(namaVal))
            .then(response => {
                if (!response.ok) {
                    throw new Error("HTTP error " + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.exists) {
                    const confirmSubmit = confirm("Nama Barang ini sudah terdaftar, apakah tetap ingin diinput?");
                    if (confirmSubmit) {
                        bypassCheck = true;
                        form.submit();
                    }
                } else {
                    bypassCheck = true;
                    form.submit();
                }
            })
            .catch(err => {
                console.error("Duplicate name check failed:", err);
                bypassCheck = true;
                form.submit();
            });
    });

});
</script>

</x-app-layout>