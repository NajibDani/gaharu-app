<x-app-layout>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="fw-bold mb-0">Penjualan POS</h3>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form action="{{ route('penjualan_pos.index') }}" method="GET" class="d-flex gap-2 flex-wrap">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no transaksi..." value="{{ request('search') }}" style="width: 200px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('penjualan_pos.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
                @endif
            </form>
            <a href="{{ route('penjualan_pos.laporan') }}"
               class="btn btn-success px-4 shadow-sm">
               📊 Lihat Laporan
            </a>

            <button type="button" class="btn btn-outline-success px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#importMokaModal">
               📥 Import Moka POS
            </button>

            <a href="{{ route('penjualan_pos.create') }}"
               class="btn btn-primary px-4 shadow-sm">
               + Tambah Transaksi
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle text-nowrap mb-0">

                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Kode Transaksi</th>
                            <th>Tanggal</th>
                            <th>Gudang / Outlet</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($data as $item)
                        <tr>
                            <td class="ps-3 fw-bold text-primary">{{ $item->kode_transaksi }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d M Y, H:i') }}</td>
                            <td>{{ $item->gudang->nama ?? '-' }}</td>
                            
                            <td class="text-end fw-bold text-dark">
                                Rp {{ number_format($item->total, 0, ',', '.') }}
                            </td>

                            <td class="text-center">
                                @if(($item->status ?? 'Draft') === 'Draft')
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Draft</span>
                                @else
                                    <span class="badge bg-success px-3 py-2 rounded-pill">Approved</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1 flex-wrap">
                                    {{-- Tombol Detail selalu muncul --}}
                                    <a href="{{ route('penjualan_pos.show', $item->id) }}"
                                       class="btn btn-info btn-sm text-white shadow-sm" title="Lihat Detail">
                                        Detail
                                    </a>
                                    <a href="{{ route('penjualan_pos.cetak-pdf', $item->id) }}"
                                       class="btn btn-danger btn-sm text-white shadow-sm" title="Cetak Struk / Nota PDF" target="_blank">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> Cetak Struk
                                    </a>

                                    @if(($item->status ?? 'Draft') === 'Draft')
                                        {{-- Tombol Approve --}}
                                        <form action="{{ route('penjualan_pos.approve', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin menyetujui transaksi ini? Stok Bahan Baku akan dipotong permanen berdasarkan FIFO.')">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm shadow-sm" title="Approve Transaksi">
                                                Approve
                                            </button>
                                        </form>

                                        {{-- Tombol Edit --}}
                                        <a href="{{ route('penjualan_pos.edit', $item->id) }}"
                                           class="btn btn-warning btn-sm text-dark shadow-sm" title="Edit Transaksi">
                                            Edit
                                        </a>

                                        {{-- Tombol Hapus --}}
                                        <form action="{{ route('penjualan_pos.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus draft transaksi ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm shadow-sm" title="Hapus Transaksi">
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada data penjualan POS.</td>
                        </tr>
                        @endforelse

                    </tbody>

                </table>
            </div>
            
        </div>
        <div class="mt-3 px-3 pb-3">
            {{ $data->links() }}
        </div>
    </div>
</div>

<!-- Modal Import Moka POS -->
<div class="modal fade" id="importMokaModal" tabindex="-1" aria-labelledby="importMokaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('penjualan_pos.import-moka') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="importMokaModalLabel">Import Excel Moka POS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tanggal_transaksi" class="form-label fw-bold text-secondary">Tanggal Transaksi Jurnal</label>
                        <input type="date" name="tanggal_transaksi" id="tanggal_transaksi" class="form-control" value="{{ date('Y-m-d') }}" required>
                        <div class="form-text mt-1 small text-muted">Tanggal pembukuan transaksi & jurnal yang di-import.</div>
                    </div>
                    <div class="mb-3">
                        <label for="moka_file" class="form-label fw-bold text-secondary">Pilih File Excel / CSV Moka POS</label>
                        <input type="file" name="moka_file" id="moka_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text mt-2 small text-muted">
                            Mendukung dua format ekspor Moka POS:
                            <br>1. **Item Sales Report (Ringkasan Penjualan Barang)**: Memiliki kolom <code>Item Name</code>, <code>Item Variant Name</code>, <code>Item Sold</code>, dan <code>Net Sales</code>.
                            <br>2. **Transactions List (Daftar Struk)**: Memiliki kolom <code>Receipt Number / No. Transaksi</code>, <code>Item Name</code>, <code>Quantity</code>, dll.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold">Mulai Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

</x-app-layout>