<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Riwayat Jurnal Umum
        </h2>
    </x-slot>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">Riwayat Jurnal Umum</h2>
            <a href="{{ route('jurnal.create') }}" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus"></i> Tambah Jurnal
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

        <div class="card mb-4 border-0 shadow-sm bg-light">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="fas fa-mail-bulk me-2 text-primary"></i> Posting Jurnal Massal</h5>
                <form action="{{ route('jurnal.approve_batch') }}" method="POST" class="row g-3 align-items-end" onsubmit="return confirm('Posting semua jurnal draft pada rentang tanggal ini ke Buku Besar?')">
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

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>No. Ref</th>
                                <th>Deskripsi</th>
                                <th class="text-end">Total Debit</th>
                                <th class="text-end">Total Kredit</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jurnals as $j)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/Y') }}</td>
                                <td><span class="badge bg-light text-dark border font-monospace">{{ $j->no_ref }}</span></td>
                                <td>{{ Str::limit($j->deskripsi, 50) }}</td>
                                <td class="text-end fw-semibold text-success">
                                    Rp {{ number_format($j->details->sum('debit'), 0, ',', '.') }}
                                </td>
                                <td class="text-end fw-semibold text-danger">
                                    Rp {{ number_format($j->details->sum('kredit'), 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    @if($j->status === 'approved')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Terposting</span>
                                    @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Draft</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('jurnal.show', $j->id) }}" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        <form action="{{ route('jurnal.destroy', $j->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Jurnal Umum No. Ref {{ $j->no_ref }}? Hapus jurnal akan langsung memperbarui laporan akuntansi.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash-alt"></i> Hapus
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