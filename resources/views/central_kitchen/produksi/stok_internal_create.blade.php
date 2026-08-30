<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: "Plus Jakarta Sans", sans-serif; background-color: #f8fafc; }
        .card-form { border-radius: 16px; border: 1px solid #eaeaea; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .btn-custom-orange { background-color: #db7946; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
        .bahan-info { font-size: 0.78rem; color: #64748b; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important; max-width: 960px;">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">Produksi Stok Internal Central Kitchen</h4>
                <p class="text-muted small mb-0">Produksi Bahan Setengah Jadi untuk stok buffer di gudang CK — tanpa order dari outlet</p>
            </div>
            <a href="{{ route("ck-produksi.index") }}" class="btn btn-outline-secondary btn-sm rounded-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>

        @if(session("error"))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 small mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session("error") }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 small mb-4">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route("ck-produksi.store-stok-internal") }}" method="POST">
            @csrf

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Produksi</h6>
                <div class="row g-3">
                    <input type="hidden" name="divisi_id" value="1">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Tanggal Produksi <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_produksi" class="form-control rounded-3" value="{{ old("tanggal_produksi", date("Y-m-d")) }}" required>
                    </div>
                </div>
            </div>

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Daftar Bahan Setengah Jadi yang Akan Diproduksi</h6>
                <p class="text-muted small mb-3">Isi qty di kolom "Qty Produksi". Kosongkan atau isi 0 jika tidak perlu diproduksi.</p>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr class="text-secondary small">
                                <th>NAMA BSJ</th>
                                <th style="width:150px;" class="text-center">QTY PRODUKSI</th>
                                <th style="width:80px;" class="text-center">SATUAN</th>
                                <th>BAHAN BAKU (per unit BSJ) &amp; STOK CK</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($produkWithBahan as $idx => $p)
                                <tr>
                                    <td>
                                        <input type="hidden" name="produk_id[]" value="{{ $p->id }}">
                                        <div class="fw-bold text-dark small">{{ $p->nama }}</div>
                                        <div class="text-muted" style="font-size:0.72rem;">{{ $p->kode }}</div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="qty_hasil[]"
                                               class="form-control form-control-sm text-end fw-bold qty-input"
                                               data-produk-idx="{{ $idx }}"
                                               value="{{ old("qty_hasil.".$idx, 0) }}"
                                               placeholder="0">
                                    </td>
                                    <td class="text-center text-muted small">{{ $p->satuan }}</td>
                                    <td>
                                        @if(count((array)$p->bahan))
                                            <div class="bahan-info">
                                                @foreach($p->bahan as $bIdx => $b)
                                                    <div class="d-flex justify-content-between align-items-center py-1 {{ !$loop->last ? "border-bottom" : "" }}">
                                                        <span>{{ $b["nama"] }}</span>
                                                        <span class="ms-3 text-end">
                                                            <span class="fw-bold bahan-qty-{{ $idx }}-{{ $bIdx }}" data-per-unit="{{ $b["qty_per_unit"] }}">
                                                                {{ $b["qty_per_unit"] }}
                                                            </span>
                                                            {{ $b["satuan"] }}
                                                            &nbsp;|&nbsp;
                                                            Stok: <strong class="{{ $b["stok"] <= 0 ? "text-danger" : "text-success" }}">{{ number_format($b["stok"], 0, ",", ".") }}</strong>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted small fst-italic">Tidak ada resep</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
                                        Belum ada Bahan Setengah Jadi yang terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route("ck-produksi.index") }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-custom-orange shadow-sm px-4">
                    <i class="bi bi-check-circle-fill me-1"></i> Simpan &amp; Selesaikan Produksi
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".qty-input").forEach(function (input) {
                input.addEventListener("input", function () {
                    var qty = parseFloat(this.value) || 0;
                    var idx = this.getAttribute("data-produk-idx");
                    document.querySelectorAll("[class*=\"bahan-qty-" + idx + "-\"]").forEach(function (el) {
                        var perUnit = parseFloat(el.getAttribute("data-per-unit")) || 0;
                        el.textContent = (perUnit * qty).toFixed(2);
                    });
                });
            });
        });
    </script>
</x-app-layout>
