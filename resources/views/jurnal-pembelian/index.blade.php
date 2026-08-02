<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Modul Jurnal Khusus Pembelian
        </h2>
    </x-slot>

    <div class="container py-4">

        @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        </div>
        @endif

        <!-- FILTER & SEARCH BAR -->
        <div class="card shadow border-0 rounded-3 mb-4">
            <div class="card-body">
                <form action="{{ route('jurnal-pembelian.index') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label small fw-bold text-secondary">Tanggal Mulai</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label small fw-bold text-secondary">Tanggal Selesai</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label small fw-bold text-secondary">Cari Transaksi</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 border"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" id="search" class="form-control border-start-0 border" placeholder="No. Jurnal atau Keterangan..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="{{ route('jurnal-pembelian.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-folder-open me-2"></i>Buku Jurnal Khusus Pembelian</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-bold">
                            <tr>
                                <th class="py-3 ps-4" style="width: 13%">Tanggal Jurnal</th>
                                <th class="py-3" style="width: 17%">No. Jurnal (Ref)</th>
                                <th class="py-3" style="width: 13%">Tahap</th>
                                <th class="py-3" style="width: 27%">Keterangan / Deskripsi</th>
                                <th class="py-3 text-end" style="width: 15%">Total Transaksi</th>
                                <th class="py-3 text-center" style="width: 15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jurnalsSudah as $j)
                            <tr>
                                <td class="py-3 ps-4 fw-medium text-secondary">
                                    {{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/Y') }}
                                </td>

                                <td class="font-monospace text-dark small fw-bold">
                                    {{ $j->no_ref }}
                                </td>

                                <td>
                                    @php
                                    $labelTahap = [
                                    'dp' => ['DP', 'bg-info text-white'],
                                    'pelunasan' => ['Pelunasan', 'bg-primary text-white'],
                                    'reklas_lunas' => ['Reklas Persediaan', 'bg-purple text-white'], 
                                    'gabungan' => ['Pelunasan + Terima Barang', 'bg-success text-white'],
                                    'cod' => ['COD (Lunas)', 'bg-dark text-white'],
                                    ];
                                    [$label, $class] = $labelTahap[$j->tahap] ?? ['-', 'bg-secondary text-white'];
                                    @endphp
                                    <span class="badge {{ $class }} px-2 py-1">{{ $label }}</span>
                                </td>

                                <td class="text-muted small">
                                    {{ $j->deskripsi }}
                                </td>

                                <td class="text-end font-monospace text-success fw-bold">
                                    Rp {{ number_format($j->total_transaksi, 0, ',', '.') }}
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('jurnal-pembelian.show', $j->id) }}" class="btn btn-sm btn-outline-secondary fw-bold px-3">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Belum ada riwayat transaksi pembelian yang tersimpan di dalam buku jurnal khusus.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($jurnalsSudah->hasPages())
            <div class="card-footer bg-white py-3 d-flex justify-content-center">
                {{ $jurnalsSudah->appends(request()->query())->links() }}
            </div>
            @endif
        </div>

    </div>
</x-app-layout>