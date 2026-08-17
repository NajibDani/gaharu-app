<x-app-layout>
<x-slot name="header">
    Master Gudang
</x-slot>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-0 fw-bold">Master Data Gudang</h5>
            <small class="text-muted">Kelola data gudang perusahaan dan divisi operasional</small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('gudangs.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama/kategori..." value="{{ request('search') }}" style="width: 200px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('gudangs.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
                @endif
            </form>

            <a href="{{ route('gudangs.create') }}" class="btn btn-primary btn-sm" style="border-radius: 6px;">
                <i class="bi bi-plus-circle me-1"></i> Tambah Gudang
            </a>
        </div>
    </div>

    <div class="card-body">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-dark text-center">
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th>Nama Gudang</th>
                        <th style="width: 150px;">Kategori</th>
                        <th>Divisi (Khusus Operasional)</th>
                        <th style="width: 180px;">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($gudangs as $gudang)
                        <tr>
                            <td class="text-center fw-semibold">
                                {{ $loop->iteration + ($gudangs->currentPage() - 1) * $gudangs->perPage() }}
                            </td>

                            <td class="fw-bold text-dark">
                                <i class="bi bi-shop text-muted me-1"></i> {{ $gudang->nama }}
                            </td>

                            <td class="text-center">
                                @if(strtolower($gudang->kategori) === 'operasional')
                                    <span class="badge bg-primary px-2 py-1">Operasional</span>
                                @elseif(strtolower($gudang->kategori) === 'utama')
                                    <span class="badge bg-success px-2 py-1">Utama</span>
                                @else
                                    <span class="badge bg-secondary px-2 py-1">{{ $gudang->kategori }}</span>
                                @endif
                            </td>

                            <td>
                                @if(strtolower($gudang->kategori) === 'operasional')
                                    @if($gudang->divisi && $gudang->divisi->count() > 0)
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($gudang->divisi as $div)
                                                <span class="badge bg-light text-primary border border-primary-subtle py-1 px-2">
                                                    <i class="bi bi-diagram-3 me-1"></i>{{ $div->nama }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Belum ada divisi</span>
                                    @endif
                                @else
                                    <span class="text-muted small fst-italic">- (Gudang Tunggal)</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <a href="{{ route('gudangs.show', $gudang->id) }}" class="btn btn-info btn-sm text-white" title="Lihat Detail">
                                    <i class="bi bi-eye"></i> Detil
                                </a>

                                <a href="{{ route('gudangs.edit', $gudang->id) }}" class="btn btn-warning btn-sm" title="Edit Gudang">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>

                                <button type="button"
                                        class="btn btn-danger btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalHapusGudang"
                                        data-nama="{{ $gudang->nama }}"
                                        data-action="{{ route('gudangs.destroy', $gudang->id) }}"
                                        title="Hapus Gudang">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Data gudang belum ada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $gudangs->links() }}
        </div>

    </div>
</div>

{{-- ================= MODAL HAPUS GUDANG ================= --}}
<div class="modal fade" id="modalHapusGudang" tabindex="-1" aria-labelledby="modalHapusGudangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <form id="formHapusGudang" method="POST">
                @csrf
                @method('DELETE')

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="modalHapusGudangLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <p class="mb-0">
                        Yakin ingin menghapus data gudang
                        <strong id="hapusNama"></strong>?
                        Tindakan ini juga akan menghapus seluruh data divisi terkait.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalHapus = document.getElementById('modalHapusGudang');
    if (modalHapus) {
        modalHapus.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('hapusNama').innerText = button.getAttribute('data-nama');
            document.getElementById('formHapusGudang').action = button.getAttribute('data-action');
        });
    }
});
</script>
@endpush

</x-app-layout>