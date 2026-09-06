<x-app-layout>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Tambah Permintaan Cold Kitchen</h3>
                <small class="text-muted">Form pengajuan permintaan barang / produk ke Cold Kitchen oleh tim operasional</small>
            </div>
            <a href="{{ route('pesanan.index') }}" class="btn btn-secondary rounded-3 px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Terjadi Kesalahan Input:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <form action="{{ route('pesanan.store') }}" method="POST">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-secondary">Kode Permintaan</label>
                            <input type="text" name="kode_pesanan" class="form-control bg-light fw-bold text-dark" value="P{{ rand(100,999) }}" readonly>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-secondary">Pemesan / Outlet Customer</label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">-- Pilih Customer / Outlet --</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->nama }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-secondary">Tanggal Permintaan</label>
                            <input type="datetime-local" name="tanggal" class="form-control" value="{{ date('Y-m-d\TH:i') }}" required>
                        </div>
                    </div>

                    <div class="alert alert-info border-info d-flex align-items-center p-3 rounded-3 mb-4">
                        <i class="bi bi-info-circle-fill fs-5 text-info me-3"></i>
                        <div class="small">
                            <strong>Informasi Pengajuan:</strong> Masukkan jenis produk dan Qty permintaan. Harga produk akan di-input oleh manajemen setelah proses produksi di Cold Kitchen selesai.
                        </div>
                    </div>

                    <hr class="mb-4">

                    <h5 class="fw-bold mb-3 text-dark">Detail Item Permintaan</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle" id="table-detail">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Produk / Barang Jadi</th>
                                    <th width="200" class="text-center">Qty Permintaan</th>
                                    <th width="60" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select name="produk_id[]" class="form-select produk select2" required>
                                            <option value="">-- Pilih Produk --</option>
                                            @foreach($produk as $p)
                                                <option value="{{ $p->id }}" data-satuan="{{ $p->satuan ?? 'pcs' }}">
                                                    {{ $p->nama }} ({{ $p->satuan ?? 'pcs' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <div class="input-group">
                                            <input type="number" name="qty[]" class="form-control qty text-end fw-bold" min="1" step="any" placeholder="0" required>
                                            <span class="input-group-text label-satuan">pcs</span>
                                        </div>
                                        <input type="hidden" name="harga[]" value="0">
                                        <input type="hidden" name="subtotal[]" value="0">
                                    </td>

                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm hapus-baris rounded-3">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-outline-secondary rounded-3 mb-4 fw-semibold" id="tambah-baris">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Baris Produk
                    </button>

                    <div class="d-flex flex-column flex-sm-row gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-primary rounded-3 px-5 py-2 fw-bold" style="background-color: #DE8958; border-color: #DE8958;">
                            <i class="bi bi-send-fill me-2"></i> Kirim Permintaan Cold Kitchen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function () {
            // Inisialisasi select2 awal
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            // Update satuan saat produk dipilih (menggunakan event delegation jQuery)
            $(document).on('change', '.produk', function() {
                const selectedOpt = $(this).find(':selected');
                const satuan = selectedOpt.data('satuan') || 'pcs';
                const row = $(this).closest('tr');
                if (row.length) {
                    row.find('.label-satuan').text(satuan);
                }
            });

            // Tambah baris baru dengan select2 aktif
            $('#tambah-baris').on('click', function () {
                let tableBody = $('#table-detail tbody');
                let newRow = $(`
                    <tr>
                        <td>
                            <select name="produk_id[]" class="form-select produk select2-dynamic" required>
                                <option value="">-- Pilih Produk --</option>
                                @foreach($produk as $p)
                                    <option value="{{ $p->id }}" data-satuan="{{ $p->satuan ?? 'pcs' }}">
                                        {{ $p->nama }} ({{ $p->satuan ?? 'pcs' }})
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="input-group">
                                <input type="number" name="qty[]" class="form-control qty text-end fw-bold" min="1" step="any" placeholder="0" required>
                                <span class="input-group-text label-satuan">pcs</span>
                            </div>
                            <input type="hidden" name="harga[]" value="0">
                            <input type="hidden" name="subtotal[]" value="0">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm hapus-baris rounded-3">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);

                tableBody.append(newRow);

                // Aktifkan select2 pada baris baru
                newRow.find('.select2-dynamic').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            });

            // Hapus baris
            $(document).on('click', '.hapus-baris', function() {
                let rows = $('#table-detail tbody tr');
                if (rows.length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert("Minimal harus ada satu produk dalam pengajuan permintaan.");
                }
            });
        });
    </script>
    @endpush
</x-app-layout>