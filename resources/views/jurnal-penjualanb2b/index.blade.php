<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modul Jurnal Khusus Penjualan B2B</h2>
    </x-slot>

    <div class="container py-4">
        @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
        @endif

        <!-- FILTER & SEARCH BAR -->
        <div class="card shadow border-0 rounded-3 mb-4">
            <div class="card-body">
                <form action="{{ route('jurnal-penjualanb2b.index') }}" method="GET" class="row g-3 align-items-end">
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
                            <input type="text" name="search" id="search" class="form-control border-start-0 border" placeholder="No. Ref atau Deskripsi..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="{{ route('jurnal-penjualanb2b.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-folder-open me-2"></i>Buku Jurnal Khusus Penjualan B2B</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-bold">
                            <tr>
                                <th class="py-3 ps-4" style="width: 15%">Tanggal</th>
                                <th class="py-3" style="width: 20%">No. Ref</th>
                                <th class="py-3" style="width: 35%">Deskripsi</th>
                                <th class="py-3 text-end" style="width: 12%">Total Debit</th>
                                <th class="py-3 text-end" style="width: 12%">Total Kredit</th>
                                <th class="py-3 text-center" style="width: 6%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jurnalsSudah as $j)
                            <tr>
                                <td class="py-3 ps-4 text-secondary">
                                    {{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/Y') }}
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1 small fw-bold">
                                        {{ $j->no_ref }}
                                    </span>
                                </td>

                                <td class="text-muted small">
                                    {{ Str::limit($j->deskripsi, 55, '...') }}
                                </td>

                                <td class="text-end font-monospace text-success fw-semibold">
                                    Rp {{ number_format($j->total_debit, 2, ',', '.') }}
                                </td>

                                <td class="text-end font-monospace text-danger fw-semibold">
                                    Rp {{ number_format($j->total_kredit, 2, ',', '.') }}
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('jurnal-penjualanb2b.show', $j->id) }}" class="btn btn-sm btn-outline-info fw-bold px-2.5 py-1">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Belum ada riwayat transaksi penjualan B2B yang tersimpan di dalam buku jurnal khusus.
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