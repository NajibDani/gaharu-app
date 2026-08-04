<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Riwayat Jurnal Penyesuaian
        </h2>
    </x-slot>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0" style="color: #9c4f18; font-size: 1.5rem;">Riwayat Jurnal Penyesuaian</h2>
            <a href="{{ route('adjustment.create') }}" class="btn btn-primary shadow-sm" style="background-color: #9c4f18; border-color: #9c4f18;">
                <i class="fas fa-plus me-1"></i> Tambah Jurnal
            </a>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        {{-- BATCH POSTING CARD (POSTING MASSAL) --}}
        <div class="card mb-4 border-0 shadow-sm bg-light">
            <div class="card-body">
                <h5 class="fw-bold mb-3" style="color: #9c4f18;"><i class="fas fa-mail-bulk me-2 text-primary"></i> Posting Jurnal Massal</h5>
                <form action="{{ route('adjustment.approve_batch') }}" method="POST" class="row g-3 align-items-end" onsubmit="return confirm('Posting semua jurnal penyesuaian draft pada rentang tanggal ini ke Buku Besar?')">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control" required value="{{ request('start_date', date('Y-m-01')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control" required value="{{ request('end_date', date('Y-m-t')) }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success w-100 shadow-sm">
                            <i class="fas fa-check-double me-1"></i> Posting Terpilih
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- TABLE CARD --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tanggal</th>
                                <th>No. Ref</th>
                                <th>Deskripsi</th>
                                <th class="text-end">Total Debit</th>
                                <th class="text-end">Total Kredit</th>
                                <th class="text-center">Status</th>
                                <th class="text-center pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- PROSES GROUPING DI LEVEL BLADE --}}
                            @php
                                $groupedAdjustments = $adjustments->groupBy(function($item) {
                                    if ($item->source_type === 'pengeluaran_bahan_baku' && $item->source_id) {
                                        return 'so_' . $item->source_id;
                                    }
                                    return 'manual_' . $item->id;
                                });
                            @endphp

                            @forelse($groupedAdjustments as $groupKey => $groupItems)
                                @php
                                    $firstItem = $groupItems->first();
                                    
                                    // Menghitung akumulasi total debit & kredit dari seluruh item dalam grup
                                    $totalDebit = $groupItems->sum(function($adj) {
                                        return $adj->details->sum('debit');
                                    });
                                    $totalKredit = $groupItems->sum(function($adj) {
                                        return $adj->details->sum('kredit');
                                    });
                                    
                                    $isOtomatis = $firstItem->source_type === 'pengeluaran_bahan_baku';
                                @endphp
                                <tr>
                                    <td class="ps-3">{{ \Carbon\Carbon::parse($firstItem->tanggal)->format('d/m/Y') }}</td>
                                    <td>
                                        @if($isOtomatis)
                                            <span class="badge bg-light text-dark border font-monospace">
                                                {{ Str::beforeLast($firstItem->no_ref, '-') }}
                                            </span>
                                            <div class="mt-1">
                                                <a href="{{ route('pengeluaran-bahan-baku.show', $firstItem->source_id) }}" 
                                                   class="badge text-decoration-none bg-info-subtle text-info border border-info-subtle small shadow-sm" 
                                                   target="_blank" 
                                                   title="Klik untuk melihat dokumen Stock Opname asli">
                                                    <i class="fas fa-boxes me-1"></i> Stock Opname ({{ $groupItems->count() }} Item)
                                                </a>
                                            </div>
                                        @else
                                            <span class="badge bg-light text-dark border font-monospace">{{ $firstItem->no_ref }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isOtomatis)
                                            [AJP] Penyesuaian Gabungan Stock Opname Gudang
                                        @else
                                            {{ Str::limit($firstItem->deskripsi, 50) }}
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-success">
                                        Rp {{ number_format($totalDebit, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-semibold text-danger">
                                        Rp {{ number_format($totalKredit, 0, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        @if($firstItem->status === 'approved')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Posted</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Draft</span>
                                        @endif
                                    </td>
                                    <td class="text-center pe-3">
                                        <div class="d-inline-flex gap-1 align-items-center">
                                            {{-- Tombol Detail: Selalu Mengarah ke adjustment.show --}}
                                            <a href="{{ route('adjustment.show', $firstItem->id) }}" class="btn btn-sm btn-info text-white py-1 px-2" style="font-size: 0.75rem;" title="Lihat Detail Jurnal Penyesuaian">
                                                <i class="fas fa-eye me-1"></i> Detail
                                            </a>

                                            {{-- Tombol Edit (Hanya jika manual & masih Draft) --}}
                                            @if($firstItem->status !== 'approved' && !$isOtomatis)
                                                <a href="{{ route('adjustment.edit', $firstItem->id) }}" class="btn btn-sm btn-warning text-dark py-1 px-2" style="font-size: 0.75rem;" title="Edit Jurnal">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                            @endif

                                            {{-- Tombol Post (Hanya jika manual & masih Draft) --}}
                                            @if($firstItem->status !== 'approved' && !$isOtomatis)
                                                <form action="{{ route('adjustment.approve', $firstItem->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Posting jurnal penyesuaian ini ke Buku Besar?')">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2" style="font-size: 0.75rem;" title="Posting ke Buku Besar">
                                                        <i class="fas fa-check me-1"></i> Post
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Tombol Hapus (Dapat menghapus baik Draft maupun Posted) --}}
                                            <form action="{{ route('adjustment.destroy', $firstItem->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Jurnal Penyesuaian ini? Hapus jurnal akan langsung memperbarui laporan akuntansi.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger py-1 px-2" style="font-size: 0.75rem;" title="Hapus Jurnal Penyesuaian">
                                                    <i class="fas fa-trash-alt me-1"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada transaksi jurnal yang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>