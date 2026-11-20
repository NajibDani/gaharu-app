<x-app-layout>

    <div class="container mt-4 mb-5">
        
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Gagal!</strong> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Terjadi Kesalahan:</strong>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-dark py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Draft Produksi</h4>
                    <small class="text-dark-50">Mengubah kuantitas hasil produksi sebelum divalidasi ke gudang.</small>
                </div>
                <span class="badge bg-dark text-white fs-6">{{ $produksi->kode_produksi }}</span>
            </div>

            <div class="card-body p-4">
                <form action="{{ route('produksi.update', $produksi->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="tanggal_produksi" class="form-label fw-bold text-dark">Tanggal Produksi</label>
                            <input type="date" name="tanggal_produksi" id="tanggal_produksi" class="form-control" value="{{ \Carbon\Carbon::parse($produksi->tanggal_mulai)->format('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th class="text-start ps-3">Nama Produk</th>
                                    <th width="35%">Qty Hasil Produksi (Edit)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($produksi->details as $detail)
                                    <tr>
                                        <td class="text-start ps-3">
                                            <strong class="text-primary">{{ $detail->produk->nama ?? 'Produk Tidak Diketahui' }}</strong>
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-info-circle"></i> Pastikan jumlah tidak melebihi sisa target pesanan.
                                            </div>
                                            <input type="hidden" name="produk_id[]" value="{{ $detail->produk_id }}">
                                        </td>
                                        <td>
                                            <div class="input-group mx-auto" style="max-width: 200px;">
                                                <input type="number" step="any" name="qty_hasil[]" 
                                                       class="form-control text-center fw-bold text-dark form-control-lg" 
                                                       value="{{ (int) $detail->qty }}" min="1" required>
                                                <span class="input-group-text bg-light">{{ strtoupper($detail->produk->satuan ?? 'PCS') }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <hr class="text-muted my-4">

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('produksi.index') }}" class="btn btn-secondary px-4 shadow-sm">
                            <i class="bi bi-arrow-left-circle me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-warning text-dark fw-bold px-5 shadow-sm">
                            <i class="bi bi-check2-all me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>