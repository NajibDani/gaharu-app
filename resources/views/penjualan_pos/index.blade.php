<x-app-layout>

<div class="container-fluid px-2 px-md-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h3 class="fw-bold mb-0 text-dark">Penjualan POS</h3>

        <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-md-auto justify-content-start justify-content-md-end">
            <form action="{{ route('penjualan_pos.index') }}" method="GET" class="d-flex gap-2 flex-wrap flex-grow-1 flex-md-grow-0">
                <input type="text" name="search" class="form-control form-control-sm flex-grow-1" placeholder="Cari no transaksi..." value="{{ request('search') }}" style="min-width: 170px; border-radius: 8px; border: 1px solid #DCD3CB; padding: 8px 12px;">
                <button type="submit" class="btn btn-sm text-white" style="background-color: #DE8958; border-radius: 8px; border: none; padding: 8px 16px; font-weight: 600;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('penjualan_pos.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 8px; padding: 8px 16px;">Reset</a>
                @endif
            </form>
            <a href="{{ route('penjualan_pos.laporan') }}"
               class="btn btn-sm btn-outline-secondary px-3 shadow-sm rounded-3 fw-semibold d-inline-flex align-items-center gap-1" style="min-height: 38px;">
               📊 Laporan
            </a>

            <button type="button" class="btn btn-sm btn-outline-dark px-3 shadow-sm rounded-3 fw-semibold d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#importMokaModal" style="min-height: 38px;">
               📥 Import Moka
            </button>

            <a href="{{ route('penjualan_pos.create') }}"
               class="btn btn-sm text-white px-3 shadow-sm rounded-3 fw-semibold d-inline-flex align-items-center gap-1" style="background-color: #DE8958; min-height: 38px;">
               <i class="bi bi-plus-circle-fill"></i> Tambah Transaksi
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-custom-header">
                        <tr>
                            <th class="ps-3" style="background-color: #715745 !important;">Kode Transaksi</th>
                            <th style="background-color: #715745 !important;">Tanggal</th>
                            <th style="background-color: #715745 !important;">Gudang / Outlet</th>
                            <th class="text-end" style="background-color: #715745 !important;">Total Omzet</th>
                            <th class="text-center" style="background-color: #715745 !important;">Status</th>
                            <th class="text-center" style="background-color: #715745 !important;">Aksi</th>
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

                                    @php
                                        $user = auth()->user();
                                        $isSuperAdmin = $user && $user->isSuperAdmin();
                                    @endphp

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
                                    @else
                                        {{-- Jika status Approved / SUKSES --}}
                                        @if($isSuperAdmin && ($item->status ?? '') !== 'VOID')
                                            {{-- Tombol Edit Koreksi (Khusus Super Admin) --}}
                                            <a href="{{ route('penjualan_pos.edit', $item->id) }}"
                                               class="btn btn-outline-warning btn-sm shadow-sm" title="Koreksi Transaksi & HPP (Khusus Super Admin)">
                                                <i class="bi bi-pencil-square"></i> Koreksi
                                            </a>

                                            {{-- Tombol Hapus / Rollback (Khusus Super Admin) --}}
                                            <form action="{{ route('penjualan_pos.destroy', $item->id) }}" method="POST" class="d-inline" 
                                                  onsubmit="return confirm('PERINGATAN SUPER ADMIN:\n\nTransaksi {{ $item->kode_transaksi }} sudah di-Approve. Menghapus transaksi ini akan MENGEMBALIKAN / ME-ROLLBACK stok bahan baku ke gudang, menghapus riwayat pemotongan, dan menghapus jurnal akuntansi terkait.\n\nApakah Anda yakin ingin menghapus transaksi ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm shadow-sm" title="Hapus & Rollback Stok (Khusus Super Admin)">
                                                    <i class="bi bi-trash-fill"></i> Hapus
                                                </button>
                                            </form>
                                        @endif
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
                        <label for="gudang_id" class="form-label fw-bold text-secondary">Pilih Outlet / Gudang Asal Bahan Baku <span class="text-danger">*</span></label>
                        <select name="gudang_id" id="gudang_id" class="form-select" required>
                            <option value="">-- Pilih Outlet / Gudang --</option>
                            @foreach($gudangList as $g)
                                <option value="{{ $g->id }}" {{ (auth()->user()->gudang_id == $g->id || (is_null(auth()->user()->gudang_id) && $g->id == 2)) ? 'selected' : '' }}>
                                    {{ $g->nama }} ({{ $g->kategori ?? 'Outlet' }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text mt-1 small text-muted">
                            Stok bahan baku (FIFO) akan dipotong dari gudang/outlet yang dipilih ini.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_transaksi" class="form-label fw-bold text-secondary">Tanggal Transaksi Jurnal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_transaksi" id="tanggal_transaksi" class="form-control" value="{{ date('Y-m-d') }}" required>
                        <div class="form-text mt-1 small text-muted">Tanggal pembukuan transaksi & jurnal yang di-import.</div>
                    </div>

                    <div class="mb-3">
                        <label for="moka_file" class="form-label fw-bold text-secondary">Pilih File Excel / CSV Moka POS <span class="text-danger">*</span></label>
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