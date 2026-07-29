<x-app-layout>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold text-dark">Resep: {{ $resep->produk->nama ?? 'Produk Tidak Diketahui' }}</h3>
        <a href="{{ route('resep.index') }}" class="btn btn-secondary rounded-3">
            Kembali
        </a>
    </div>

    {{-- INFO OUTPUT & BIAYA OPERASIONAL --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body bg-info text-white rounded-3">
                    <small class="text-uppercase fw-bold opacity-75">Target Produksi</small>
                    <h4 class="mb-0 fw-bold">{{ (int) $resep->output_qty }} {{ $resep->satuan_output }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body bg-success text-white rounded-3 d-flex align-items-center">
                    <div>
                        <small class="text-uppercase fw-bold opacity-75 d-block">Aturan Biaya Konversi (BTKL & BOP)</small>
                        <span class="fw-bold">30% dari Total Biaya Bahan Baku</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border shadow-sm text-center mb-4">
        <i class="fas fa-info-circle text-primary me-2"></i>
        <span>Biaya Tenaga Kerja Langsung (BTKL) dan Biaya Operasional Pabrik (BOP) sekarang dihitung secara otomatis sebesar <strong>30%</strong> dari total nilai penggunaan bahan baku menggunakan harga FIFO riil.</span>
    </div>

    {{-- TABEL BAHAN BAKU (Fokus pada Kuantitas) --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-flask me-2 text-primary"></i>Komposisi Bahan</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Nama Bahan</th>
                        <th class="text-center py-3">Qty / Produk</th>
                        <th class="text-center py-3">Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resep->bahanbaku as $b)
                    <tr>
                        <td class="ps-4 fw-semibold text-dark">{{ $b->bahan->nama ?? 'Bahan Tidak Diketahui' }}</td>
                        <td class="text-center">{{ $b->qty_bahan }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary opacity-75 px-3">{{ $b->satuan ?? '-' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">Tidak ada komponen bahan baku yang terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
</x-app-layout>