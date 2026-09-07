<x-app-layout>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Edit Permintaan Cold Kitchen</h3>
                <small class="text-muted">Perbarui data pengajuan permintaan barang / produk ke Cold Kitchen: <span class="fw-bold text-primary">#{{ $pesanan->kode_pesanan }}</span></small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('pesanan.show', $pesanan->id) }}" class="btn btn-outline-info rounded-3 px-3">
                    <i class="bi bi-eye me-1"></i> Lihat Detail
                </a>
                <a href="{{ route('pesanan.index') }}" class="btn btn-secondary rounded-3 px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
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

                <form action="{{ route('pesanan.update', $pesanan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary">Kode Permintaan</label>
                            <input type="text" name="kode_pesanan" class="form-control bg-light fw-bold text-dark" value="{{ $pesanan->kode_pesanan }}" readonly>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary">Pemesan / Outlet Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">-- Pilih Customer / Outlet --</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ (old('customer_id', $pesanan->customer_id) == $customer->id) ? 'selected' : '' }}>
                                        {{ $customer->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary">Tanggal Permintaan <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="tanggal" class="form-control" value="{{ old('tanggal', date('Y-m-d\TH:i', strtotime($pesanan->tanggal))) }}" required>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold text-secondary">Estimasi Kirim</label>
                            <input type="datetime-local" name="estimasi_kirim" class="form-control" value="{{ old('estimasi_kirim', $pesanan->estimasi_kirim ? date('Y-m-d\TH:i', strtotime($pesanan->estimasi_kirim)) : '') }}">
                        </div>
                    </div>

                    <div class="alert alert-info border-info d-flex align-items-center p-3 rounded-3 mb-4">
                        <i class="bi bi-info-circle-fill fs-5 text-info me-3"></i>
                        <div class="small">
                            <strong>Informasi Pengajuan:</strong> Anda dapat menambah, mengubah kuantitas, atau menghapus item produk dalam permintaan ini. Harga per item akan dipertahankan atau disesuaikan melalui menu Atur Harga Jual.
                        </div>
                    </div>

                    <hr class="mb-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark">Detail Item Permintaan</h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 fw-semibold" id="tambah-baris">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Baris Produk
                        </button>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle" id="table-detail">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Produk / Barang Jadi</th>
                                    <th width="240" class="text-center">Qty Permintaan</th>
                                    <th width="60" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pesanan->details as $detail)
                                    @php
                                        $satuanItem = $detail->produk->satuan ?? 'pcs';
                                    @endphp
                                    <tr>
                                        <td>
                                            <select name="produk_id[]" class="form-select produk select2" required>
                                                <option value="">-- Pilih Produk --</option>
                                                @foreach($produk as $p)
                                                    <option value="{{ $p->id }}" data-satuan="{{ $p->satuan ?? 'pcs' }}" {{ $detail->produk_id == $p->id ? 'selected' : '' }}>
                                                        {{ $p->nama }} ({{ $p->satuan ?? 'pcs' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>

                                        <td>
                                            <div class="input-group">
                                                <input type="number" name="qty[]" class="form-control qty text-end fw-bold" min="0.01" step="any" value="{{ $detail->qty }}" placeholder="0" required>
                                                <span class="input-group-text label-satuan">{{ $satuanItem }}</span>
                                            </div>
                                            <input type="hidden" name="harga[]" value="{{ $detail->harga ?? 0 }}">
                                            <input type="hidden" name="subtotal[]" value="{{ $detail->subtotal ?? 0 }}">
                                        </td>

                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm hapus-baris rounded-3" title="Hapus baris">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
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
                                                <input type="number" name="qty[]" class="form-control qty text-end fw-bold" min="0.01" step="any" placeholder="0" required>
                                                <span class="input-group-text label-satuan">pcs</span>
                                            </div>
                                            <input type="hidden" name="harga[]" value="0">
                                            <input type="hidden" name="subtotal[]" value="0">
                                        </td>

                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm hapus-baris rounded-3" title="Hapus baris" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('pesanan.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                        <button type="submit" class="btn btn-primary rounded-3 px-5 py-2 fw-bold" style="background-color: #DE8958; border-color: #DE8958;">
                            <i class="bi bi-check-lg me-1"></i> Update Permintaan Cold Kitchen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function () {
            // Inisialisasi select2
            function initSelect2(el) {
                $(el).select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }

            $('.select2').each(function() {
                initSelect2(this);
            });

            // Update satuan saat produk dipilih
            $(document).on('change', '.produk', function() {
                const selectedOpt = $(this).find(':selected');
                const satuan = selectedOpt.data('satuan') || 'pcs';
                const row = $(this).closest('tr');
                if (row.length) {
                    row.find('.label-satuan').text(satuan);
                }
            });

            // Cek jumlah baris untuk status tombol hapus
            function checkRowCounts() {
                const rows = $('#table-detail tbody tr');
                if (rows.length <= 1) {
                    rows.find('.hapus-baris').prop('disabled', true);
                } else {
                    rows.find('.hapus-baris').prop('disabled', false);
                }
            }
            checkRowCounts();

            // Tambah baris baru
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
                                <input type="number" name="qty[]" class="form-control qty text-end fw-bold" min="0.01" step="any" placeholder="0" required>
                                <span class="input-group-text label-satuan">pcs</span>
                            </div>
                            <input type="hidden" name="harga[]" value="0">
                            <input type="hidden" name="subtotal[]" value="0">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm hapus-baris rounded-3" title="Hapus baris">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);

                tableBody.append(newRow);
                initSelect2(newRow.find('.select2-dynamic'));
                checkRowCounts();
            });

            // Hapus baris
            $(document).on('click', '.hapus-baris', function () {
                if ($('#table-detail tbody tr').length > 1) {
                    $(this).closest('tr').remove();
                    checkRowCounts();
                }
            });
        });
    </script>
    @endpush
</x-app-layout>