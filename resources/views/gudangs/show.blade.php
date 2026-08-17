<x-app-layout>
    <x-slot name="header">
        Detil Gudang: {{ $gudang->nama }}
    </x-slot>

    <div class="container py-4">
        <div class="mb-3 d-flex justify-content-between align-items-center" style="max-width: 650px;">
            <a href="{{ route('gudangs.index') }}" class="btn btn-secondary btn-sm">
                &larr; Kembali ke Daftar Gudang
            </a>
            <a href="{{ route('gudangs.edit', $gudang->id) }}" class="btn btn-warning btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Gudang
            </a>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden; max-width: 650px;">
            <div class="card-header text-white" style="background-color: #d88656; padding: 16px 20px;">
                <h5 class="mb-0 fw-bold">Informasi Gudang</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="fw-bold text-muted small text-uppercase">Nama Gudang</label>
                        <p class="fs-5 text-dark fw-semibold mb-0">{{ $gudang->nama }}</p>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="fw-bold text-muted small text-uppercase">Kategori</label>
                        <div>
                            @if(strtolower($gudang->kategori) === 'operasional')
                                <span class="badge bg-primary fs-6 px-3 py-2">Operasional (Outlet / Cabang)</span>
                            @elseif(strtolower($gudang->kategori) === 'utama')
                                <span class="badge bg-success fs-6 px-3 py-2">Utama (Pusat Pembelian)</span>
                            @else
                                <span class="badge bg-info fs-6 px-3 py-2">{{ $gudang->kategori }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="fw-bold text-muted small text-uppercase">Daftar Divisi</label>
                        @if(strtolower($gudang->kategori) === 'operasional')
                            @if($gudang->divisi->count() > 0)
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    @foreach($gudang->divisi as $div)
                                        <span class="badge bg-light text-dark border border-primary px-3 py-2 fs-6">
                                            <i class="bi bi-diagram-3-fill text-primary me-1"></i> {{ $div->nama }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Belum ada divisi yang didaftarkan.</p>
                            @endif
                        @else
                            <p class="text-muted fst-italic mb-0">Tidak berlaku untuk gudang kategori {{ $gudang->kategori }}.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
