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
        $isDraft = ($penjualan->status ?? 'Draft') === 'Draft';
        $totalHpp = $penjualan->details ? $penjualan->details->sum(function($d) use ($isDraft) {
            $hpp = ($isDraft && ($d->hpp_satuan === null || $d->hpp_satuan <= 0))
                ? ($d->estimated_hpp ?? 0)
                : floatval($d->hpp_satuan);
            return $hpp * $d->qty;
        }) : 0;
        $labaKotor = $penjualan->total - $totalHpp;

        $unconfiguredRecipeCount = $penjualan->details ? $penjualan->details->filter(function($d) {
            return !($d->has_resep ?? ($d->produk ? $d->produk->hasResep() : false));
        })->count() : 0;
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
                            <span class="text-muted" style="font-size: 0.85rem;">
                                Total HPP @if($isDraft)<span class="badge bg-secondary-subtle text-secondary small">Estimasi</span>@endif
                            </span>
                            <h5 class="fw-medium text-secondary mt-1 mb-0">Rp {{ number_format($totalHpp, 0, ',', '.') }}</h5>
                        </div>
                        <div class="col-6">
                            <span class="text-muted" style="font-size: 0.85rem;">
                                Laba Kotor @if($isDraft)<span class="badge bg-secondary-subtle text-secondary small">Estimasi</span>@endif
                            </span>
                            <h5 class="text-success fw-bold mt-1 mb-0">Rp {{ number_format($labaKotor, 0, ',', '.') }}</h5>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    @if($unconfiguredRecipeCount > 0)
        <div class="alert alert-warning d-flex align-items-center mb-4 shadow-sm border border-warning" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2 fs-5 text-warning"></i>
            <div>
                <strong>Perhatian:</strong> Terdapat <strong>{{ $unconfiguredRecipeCount }}</strong> produk terjual yang <strong>Belum Memiliki Resep</strong>. HPP produk tersebut dihitung menggunakan harga beli terbaru di gudang / harga referensi.
            </div>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Rincian Produk Terjual</h6>
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Klik header kolom untuk mengurutkan (naik/turun)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-nowrap mb-0" id="table-rincian-produk">

                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4 sortable-th" style="cursor: pointer; user-select: none;" data-col="0" width="70" title="Klik untuk mengurutkan No">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span>No</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="sortable-th" style="cursor: pointer; user-select: none;" data-col="1" title="Klik untuk mengurutkan Nama Item">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span>Nama Item</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-center sortable-th" style="cursor: pointer; user-select: none;" data-col="2" width="100" title="Klik untuk mengurutkan Qty">
                                <div class="d-flex align-items-center justify-content-center">
                                    <span>Qty</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-end sortable-th" style="cursor: pointer; user-select: none;" data-col="3" title="Klik untuk mengurutkan Harga Jual">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span>Harga Jual</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-end sortable-th" style="cursor: pointer; user-select: none;" data-col="4" title="Klik untuk mengurutkan HPP / Unit">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span>HPP / Unit</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-end pe-4 sortable-th" style="cursor: pointer; user-select: none;" data-col="5" title="Klik untuk mengurutkan Total Harga Jual">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span>Total Harga Jual</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($penjualan->details as $key => $d)
                        @php
                            $itemHasResep = $d->has_resep ?? ($d->produk ? $d->produk->hasResep() : false);
                            $unitHpp = ($penjualan->status === 'Draft' && ($d->hpp_satuan === null || $d->hpp_satuan <= 0))
                                ? ($d->estimated_hpp ?? 0)
                                : floatval($d->hpp_satuan);
                        @endphp
                        <tr>
                            <td class="ps-4 text-muted" data-value="{{ $key + 1 }}">{{ $key + 1 }}</td>
                            <td class="fw-medium" data-value="{{ strtolower($d->produk->nama ?? 'Item') }}">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span>{{ $d->produk->nama ?? 'Item' }}</span>
                                    @if(!$itemHasResep)
                                        <span class="badge bg-warning text-dark border border-warning" style="font-size: 0.72rem;">
                                            <i class="bi bi-journal-x me-1"></i>Belum Memiliki Resep
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center bg-light fw-semibold" data-value="{{ floatval($d->qty) }}">{{ $d->qty }}</td>
                            <td class="text-end" data-value="{{ floatval($d->harga) }}">Rp {{ number_format($d->harga, 0, ',', '.') }}</td>
                            <td class="text-end text-muted" data-value="{{ floatval($unitHpp) }}">
                                @if(($penjualan->status ?? '') === 'SUKSES' || $d->hpp_satuan > 0)
                                    <div>Rp {{ number_format($d->hpp_satuan, 0, ',', '.') }}</div>
                                    @if(!$itemHasResep)
                                        <div class="mt-1">
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                                                Belum Memiliki Resep
                                            </span>
                                        </div>
                                    @endif
                                @elseif($isDraft)
                                    @if($unitHpp > 0)
                                        <div>
                                            Rp {{ number_format($unitHpp, 0, ',', '.') }}
                                            <span class="badge bg-secondary-subtle text-secondary small" style="font-size: 0.65rem;">Estimasi</span>
                                        </div>
                                    @endif
                                    @if(!$itemHasResep)
                                        <div class="mt-1">
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                                                Belum Memiliki Resep
                                            </span>
                                        </div>
                                    @elseif($unitHpp <= 0)
                                        <span class="text-muted small"><em>(Draft)</em></span>
                                    @endif
                                @else
                                    <span class="text-muted small"><em>(Draft)</em></span>
                                @endif
                            </td>
                            <td class="text-end fw-medium pe-4" data-value="{{ floatval($d->subtotal) }}">Rp {{ number_format($d->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    </div>

</div>

<style>
    .sortable-th:hover {
        background-color: #343a40 !important;
    }
    .sortable-th .sort-icon {
        transition: transform 0.15s ease-in-out;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('table-rincian-produk');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('th.sortable-th');

    let currentSortCol = null;
    let currentSortAsc = true;

    headers.forEach(th => {
        th.addEventListener('click', function () {
            const colIndex = parseInt(this.getAttribute('data-col'));

            if (currentSortCol === colIndex) {
                currentSortAsc = !currentSortAsc;
            } else {
                currentSortCol = colIndex;
                currentSortAsc = true;
            }

            // Reset all icons
            headers.forEach(h => {
                const icon = h.querySelector('.sort-icon');
                if (icon) {
                    icon.className = 'bi bi-arrow-down-up text-white-50 ms-1 sort-icon';
                }
            });

            // Update active icon
            const activeIcon = this.querySelector('.sort-icon');
            if (activeIcon) {
                activeIcon.className = currentSortAsc 
                    ? 'bi bi-sort-up text-warning ms-1 sort-icon' 
                    : 'bi bi-sort-down text-warning ms-1 sort-icon';
            }

            // Sort rows
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((rowA, rowB) => {
                const cellA = rowA.children[colIndex];
                const cellB = rowB.children[colIndex];

                let valA = cellA.hasAttribute('data-value') ? cellA.getAttribute('data-value') : cellA.textContent.trim();
                let valB = cellB.hasAttribute('data-value') ? cellB.getAttribute('data-value') : cellB.textContent.trim();

                const numA = parseFloat(valA);
                const numB = parseFloat(valB);

                let cmp = 0;
                if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                    cmp = numA - numB;
                } else {
                    cmp = valA.localeCompare(valB, 'id', { numeric: true, sensitivity: 'base' });
                }

                return currentSortAsc ? cmp : -cmp;
            });

            // Re-append sorted rows to tbody
            rows.forEach(r => tbody.appendChild(r));
        });
    });
});
</script>

</x-app-layout>