<x-app-layout>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('pengiriman.index') }}">Pengiriman</a></li>
                    <li class="breadcrumb-item active">Form Surat Jalan</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark">Buat Surat Jalan Baru</h4>
        </div>
        <a href="{{ route('pengiriman.index') }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    {{-- --- DITAMBAHKAN: NOTIFIKASI ERROR DAN SUCCESS --- --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    {{-- -------------------------------------------------- --}}

    {{-- NOTIFIKASI AKHIR BULAN --}}
    <div class="alert alert-warning d-flex align-items-center rounded-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #fff3cd; color: #664d03; border-left: 5px solid #ffc107 !important; padding: 16px 20px;">
        <i class="bi bi-exclamation-circle-fill me-3 fs-5 flex-shrink-0" style="color: #ffc107;"></i>
        <div>
            <strong class="d-block" style="font-size: 0.95rem;">Pemberitahuan Batas Akhir Bulan</strong>
            <span style="font-size: 0.85rem;">Harap segera lakukan penginputan dan penyelesaian (Approval) seluruh pengiriman barang sebelum akhir bulan ini. Transaksi pada periode bulan berjalan tidak akan bisa diinput atau diubah setelah penutupan buku (Closing Jurnal) dilakukan.</span>
        </div>
    </div>

    <div class="card shadow-sm border-0 p-4">
        <form action="{{ route('pengiriman.store') }}" method="POST">
            @csrf

            <div class="row g-3 mb-4">
                {{-- DROPDOWN PILIH PESANAN --}}
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold text-dark small">Pilih Nomor Pesanan (B2B)</label>
                    <select name="pesanan_id" id="pesanan_select" class="form-select" required>
                        <option value="">-- Pilih Nomor Pesanan --</option>
                        @foreach($pesanans as $p)
                            <option value="{{ $p->id }}">{{ $p->kode_pesanan }} ({{ $p->customer->nama ?? 'N/A' }})</option>
                        @endforeach
                    </select>
                    <div id="pesanan_help" class="form-text text-muted" style="font-size: 11px;">
                        <i class="bi bi-info-circle me-1"></i> Hanya pesanan yang <strong>sudah lunas</strong> yang muncul di daftar ini.
                    </div>
                </div>
                
                {{-- DIISI OTOMATIS OLEH JAVASCRIPT --}}
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold text-muted small">Nama Customer</label>
                    <input type="text" id="customer_nama" class="form-control bg-light" placeholder="Pilih pesanan terlebih dahulu" readonly>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold text-dark small">Tanggal Pengiriman</label>
                    <input type="date" name="tanggal_pengiriman" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold text-dark small">Kurir / Sopir Ekspedisi</label>
                    <input type="text" name="kurir" class="form-control" placeholder="Contoh: Budi - Mobil Box" required>
                </div>
            </div>

            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-box-seam me-2 text-primary"></i>Detail Item Barang Kiriman</h5>
            
            {{-- TABEL YANG AKAN DIISI OTOMATIS OLEH JAVASCRIPT --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle border text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Produk</th>
                            <th>Jumlah Pesanan</th>
                            <th style="width: 220px;">Jumlah Dikirim Riil</th>
                        </tr>
                    </thead>
                    <tbody id="tabel_item_body">
                        <tr>
                            <td colspan="3" class="text-muted py-4">Silakan pilih Nomor Pesanan di atas untuk memuat item produk.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-sm-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" onclick="return confirm('Konfirmasi rilis surat jalan?')">
                    <i class="bi bi-file-earmark-check me-2"></i>Rilis & Cetak Surat Jalan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- JAVASCRIPT OTOMATISASI --}}
<script>
    document.getElementById('pesanan_select').addEventListener('change', function() {
        const pesananId = this.value;
        const customerInput = document.getElementById('customer_nama');
        const tabelBody = document.getElementById('tabel_item_body');

        if (!pesananId) {
            customerInput.value = '';
            tabelBody.innerHTML = '<tr><td colspan="3" class="text-muted py-4">Silakan pilih Nomor Pesanan di atas untuk memuat item produk.</td></tr>';
            return;
        }

        // Ambil data detail pesanan dari route pembantu menggunakan Fetch API
        fetch(`/pengiriman/pesanan-detail/${pesananId}`)
            .then(response => response.json())
            .then(data => {
                // 1. Isi nama customer otomatis
                customerInput.value = data.customer ? data.customer.nama : 'N/A';

                // 2. Kosongkan tabel default, lalu render item-itemnya
                tabelBody.innerHTML = '';

                if(data.details.length === 0) {
                    tabelBody.innerHTML = '<tr><td colspan="3" class="text-danger py-4">Pesanan ini tidak memiliki item produk.</td></tr>';
                    return;
                }

                data.details.forEach((detail, index) => {
                    const namaProduk = detail.barang ? detail.barang.nama : 'Produk Tidak Diketahui';
                    const satuan = detail.barang ? detail.barang.satuan : 'Unit';
                    const qtyPesanan = detail.qty;

                    const row = `
                        <tr>
                            <td class="text-start ps-4">
                                <div class="fw-bold text-dark">${namaProduk}</div>
                                <input type="hidden" name="details[${index}][barang_id]" value="${detail.barang_id}">
                            </td>
                            <td>
                                <span class="fw-bold text-secondary">${qtyPesanan}</span> ${satuan}
                            </td>
                            <td>
                                <input type="number" name="details[${index}][qty_kirim]" class="form-control text-center mx-auto w-75" value="${qtyPesanan}" min="1" required>
                            </td>
                        </tr>
                    `;
                    tabelBody.insertAdjacentHTML('beforeend', row);
                });
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Gagal mengambil data detail pesanan.');
            });
    });
</script>
</x-app-layout>