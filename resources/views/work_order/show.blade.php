<x-app-layout>

<div class="container py-2">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
</div>

<div class="container py-4">
    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('wo.index') }}">Work Order</a></li>
                    <li class="breadcrumb-item active">Detail</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark">Detail Work Order: <span class="text-primary">{{ $wo->kode_wo }}</span></h4>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('wo.cetak-pdf', $wo->id) }}" class="btn btn-danger btn-sm shadow-sm fw-bold">
                <i class="bi bi-file-pdf me-1"></i> Cetak PDF
            </a>
            <a href="{{ route('wo.index') }}" class="btn btn-secondary btn-sm shadow-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <div class="row">
        {{-- PANEL INFORMASI KIRI --}}
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold border-bottom py-3">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Informasi WO
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Tanggal Dibuat</label>
                        <span class="fw-bold">{{ \Carbon\Carbon::parse($wo->tanggal_wo)->format('d M Y H:i') }}</span>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Status Produksi</label>
                        @if($wo->status_wo == 'Draft')
                            <span class="badge bg-secondary px-3">Draft</span>
                        @else
                            <span class="badge bg-success px-3">{{ $wo->status_wo }}</span>
                        @endif
                    </div>

                    <hr>
                    @if($wo->status_wo == 'Draft')
                        {{-- FORM KIRIM PERMINTAAN BAHAN --}}
                        <form action="{{ route('wo.kirim_produksi', $wo->id) }}" method="POST" id="formKirim">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 shadow-sm fw-bold" 
                                    onclick="return confirm('Kirim kalkulasi resep sebagai permintaan bahan ke gudang?')">
                                <i class="bi bi-send-check me-2"></i>Kirim Permintaan Bahan
                            </button>
                        </form>
                    @else
                        <div class="alert alert-success border-0 py-2 small mb-0">
                            <i class="bi bi-check-circle-fill me-1"></i> Permintaan bahan sudah diproses ke gudang utama
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- TABEL ITEM PRODUKSI KANAN --}}
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold py-3">
                    <i class="bi bi-list-check me-2"></i>Item Produksi
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Pesanan / Customer</th>
                                    <th>Produk</th>
                                    <th class="text-center pe-4">Total Qty Rencana</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Dikelompokkan per produk: kalau produk yang sama dipesan --}}
                                {{-- oleh beberapa customer/pesanan berbeda, tetap 1 baris & --}}
                                {{-- 1 kalkulasi resep dengan qty gabungan. --}}
                                @foreach($groupedDetails as $produkId => $items)
                                @php
                                    $produk = $items->first()->produk;
                                    $totalQty = $items->sum('qty_rencana');
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        @foreach($items as $item)
                                            <div class="{{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                                                <span class="fw-bold text-dark d-block">{{ $item->pesanan->customer->nama ?? 'Customer' }}</span>
                                                <small class="text-muted">
                                                    {{ $item->pesanan->kode_pesanan ?? '-' }}
                                                    &middot; {{ number_format($item->qty_rencana, 0) }} {{ $produk->satuan ?? '' }}
                                                </small>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary">{{ $produk->nama ?? 'Produk' }}</div>
                                        @if($items->count() > 1)
                                            <span class="badge bg-light text-dark border small mt-1">{{ $items->count() }} pesanan digabung</span>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-info text-white py-0 px-2 mt-1 shadow-sm" 
                                                data-bs-toggle="modal" data-bs-target="#modalResep{{ $produkId }}">
                                            <i class="bi bi-journal-text me-1"></i> Resep
                                        </button>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="fs-5 fw-bold text-dark">{{ number_format($totalQty, 0) }}</span>
                                        <small class="text-muted d-block">{{ $produk->satuan ?? 'Unit' }}</small>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL RESEP (per produk, qty sudah digabung dari semua pesanan) --}}
@foreach($groupedDetails as $produkId => $items)
@php
    $produk = $items->first()->produk;
    $totalQty = $items->sum('qty_rencana');
@endphp
<div class="modal fade" id="modalResep{{ $produkId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-2"></i>Resep: {{ $produk->nama ?? '' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <small class="text-muted d-block">Estimasi Bahan untuk Produksi (gabungan {{ $items->count() }} pesanan):</small>
                    <span class="fw-bold fs-5 text-dark">{{ number_format($totalQty, 0) }} {{ $produk->satuan }}</span>

                    @if($items->count() > 1)
                        <ul class="small text-muted mb-0 mt-2 ps-3">
                            @foreach($items as $item)
                                <li>
                                    {{ $item->pesanan->customer->nama ?? 'Customer' }}
                                    ({{ $item->pesanan->kode_pesanan ?? '-' }})
                                    &mdash; {{ number_format($item->qty_rencana, 0) }} {{ $produk->satuan }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($produk->resep as $r)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <span class="fw-bold d-block text-dark">{{ $r->bahan->nama ?? 'Bahan' }}</span>
                                <small class="text-muted">Standar: {{ $r->qty_bahan }} {{ $r->satuan }}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary fs-6 rounded-pill">
                                    {{ number_format($r->qty_bahan * $totalQty, 2) }} {{ $r->satuan }}
                                </span>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center py-5 text-muted">
                            Data resep belum diatur.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endforeach
</x-app-layout>