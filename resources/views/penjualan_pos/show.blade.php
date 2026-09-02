<x-app-layout>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-gray-800 m-0">Detail Penjualan POS</h3>
        
        <div>
            <a href="{{ route('penjualan_pos.index') }}" class="btn btn-outline-secondary me-2 px-4">
                Kembali
            </a>

            <a href="{{ route('penjualan_pos.cetak-pdf', $penjualan->id) }}" class="btn btn-danger me-2 px-4 text-white" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Struk PDF
            </a>

            @php
                $user = auth()->user();
                $isSuperAdmin = $user && $user->isSuperAdmin();
            @endphp

            {{-- TOMBOL EDIT DAN APPROVE HANYA MUNCUL JIKA STATUS MASIH DRAFT ATAU SUPER ADMIN --}}
            @if(($penjualan->status ?? 'Draft') === 'Draft')
                <a href="{{ route('penjualan_pos.edit', $penjualan->id) }}" class="btn btn-warning px-4 text-dark fw-medium me-2">
                    Edit Transaksi
                </a>

                <form action="{{ route('penjualan_pos.approve', $penjualan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin menyetujui transaksi ini? Stok Bahan Baku akan dipotong permanen berdasarkan FIFO.')">
                    @csrf
                    <button type="submit" class="btn btn-success px-4 fw-medium">
                        <i class="bi bi-check-circle me-1"></i> Approve
                    </button>
                </form>
            @elseif($isSuperAdmin && ($penjualan->status ?? '') !== 'VOID')
                <a href="{{ route('penjualan_pos.edit', $penjualan->id) }}" class="btn btn-warning px-4 text-dark fw-medium me-2" title="Koreksi Transaksi (Khusus Super Admin)">
                    <i class="bi bi-pencil-square me-1"></i> Koreksi Transaksi
                </a>

                <form action="{{ route('penjualan_pos.destroy', $penjualan->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('PERINGATAN SUPER ADMIN:\n\nTransaksi {{ $penjualan->kode_transaksi }} sudah di-Approve. Menghapus transaksi ini akan MENGEMBALIKAN stok bahan baku ke gudang dan menghapus jurnal akuntansi terkait.\n\nYakin ingin menghapus?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger px-4 text-white fw-medium">
                        <i class="bi bi-trash-fill me-1"></i> Hapus & Rollback
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $totalHpp = $penjualan->details ? $penjualan->details->sum(fn($d) => $d->hpp_satuan * $d->qty) : 0;
        $labaKotor = $penjualan->total - $totalHpp;
    @endphp

    <div class="row mb-4 align-items-stretch">
        
        <div class="col-md-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-muted mb-3 border-bottom pb-2">Informasi Transaksi</h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td width="130" class="text-muted">Kode Transaksi</td>
                            <td class="fw-semibold">: {{ $penjualan->kode_transaksi }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tanggal</td>
                            <td>: {{ \Carbon\Carbon::parse($penjualan->tanggal)->format('d-m-Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gudang</td>
                            <td>: {{ $penjualan->gudang->nama }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Input Oleh</td>
                            <td>: {{ $penjualan->creator->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>: 
                                @if(($penjualan->status ?? 'Draft') === 'Draft')
                                    <span class="badge bg-warning text-dark">Draft</span>
                                @else
                                    <span class="badge bg-success">Approved</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
                <div class="card-body d-flex flex-column justify-content-center">
                    
                    <div class="row text-center align-items-center">
                        <div class="col-12 mb-4">
                            <span class="text-muted text-uppercase" style="font-size: 0.85rem; letter-spacing: 1px;">Total Omzet (Penjualan)</span>
                            <h2 class="text-primary fw-bold mt-1 mb-0">
                                Rp {{ number_format($penjualan->total, 0, ',', '.') }}
                            </h2>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <hr class="m-0 text-muted" style="opacity: 0.15;">
                        </div>

                        <div class="col-6 border-end">
                            <span class="text-muted" style="font-size: 0.85rem;">Total HPP</span>
                            <h5 class="fw-medium text-secondary mt-1 mb-0">Rp {{ number_format($totalHpp, 0, ',', '.') }}</h5>
                        </div>
                        <div class="col-6">
                            <span class="text-muted" style="font-size: 0.85rem;">Laba Kotor</span>
                            <h5 class="text-success fw-bold mt-1 mb-0">Rp {{ number_format($labaKotor, 0, ',', '.') }}</h5>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold">Rincian Produk Terjual</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-nowrap mb-0">

                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4" width="50">No</th>
                            <th>Nama Item</th>
                            <th class="text-center" width="80">Qty</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-end">HPP / Unit</th>
                            <th class="text-end pe-4">Total Harga Jual</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($penjualan->details as $key => $d)
                        <tr>
                            <td class="ps-4 text-muted">{{ $key + 1 }}</td>
                            <td class="fw-medium">{{ $d->produk->nama ?? 'Item' }}</td>
                            <td class="text-center bg-light">{{ $d->qty }}</td>
                            <td class="text-end">Rp {{ number_format($d->harga, 0, ',', '.') }}</td>
                            <td class="text-end text-muted">
                                @if(($penjualan->status ?? '') === 'SUKSES' || $d->hpp_satuan > 0)
                                    Rp {{ number_format($d->hpp_satuan, 0, ',', '.') }}
                                @else
                                    <span class="text-muted small"><em>(Draft)</em></span>
                                @endif
                            </td>
                            <td class="text-end fw-medium pe-4">Rp {{ number_format($d->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>


</div>

</x-app-layout>