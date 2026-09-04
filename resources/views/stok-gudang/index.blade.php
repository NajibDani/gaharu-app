<x-app-layout>

    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold">
                Stok Gudang
            </h4>
        </div>

        <!-- FILTER -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('stok-gudang.index') }}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Filter Gudang</label>
                            <select name="gudang_id" id="filter_gudang_id" class="form-select form-select-sm">
                                <option value="">-- Semua Gudang --</option>
                                @foreach($gudangs as $gudang)
                                    <option value="{{ $gudang->id }}" {{ $gudangId == $gudang->id ? 'selected' : '' }}>
                                        {{ $gudang->nama }} ({{ $gudang->kategori }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2" id="filter_divisi_container" style="{{ empty($divisiId) && empty($gudangId) ? 'display:none;' : '' }}">
                            <label class="form-label fw-semibold small">Filter Divisi</label>
                            <select name="divisi_id" id="filter_divisi_id" class="form-select form-select-sm">
                                <option value="">-- Semua Divisi --</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Filter Jenis Barang</label>
                            <select name="jenis_barang" class="form-select form-select-sm">
                                <option value="">-- Semua Jenis --</option>
                                <option value="bahan_baku" {{ ($jenisBarang ?? '') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                                <option value="bahan_setengah_jadi" {{ ($jenisBarang ?? '') === 'bahan_setengah_jadi' ? 'selected' : '' }}>Bahan Setengah Jadi</option>
                                <option value="barang_jadi" {{ ($jenisBarang ?? '') === 'barang_jadi' ? 'selected' : '' }}>Barang Jadi</option>
                                <option value="operational" {{ ($jenisBarang ?? '') === 'operational' ? 'selected' : '' }}>Operasional</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Filter Barang</label>
                            <select name="barang_id" class="form-select form-select-sm">
                                <option value="">-- Semua Barang --</option>
                                @foreach($barangs as $barang)
                                    <option value="{{ $barang->id }}" {{ $barangId == $barang->id ? 'selected' : '' }}>
                                        {{ $barang->kode_barang }} - {{ $barang->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <a href="{{ route('stok-gudang.index') }}" class="btn btn-secondary btn-sm">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABEL -->
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th width="200">Gudang & Divisi</th>
                            <th width="140">Kode Barang</th>
                            <th>Nama & Jenis Barang</th>
                            <th width="180">Jumlah Stok</th>
                            <th width="140">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($stokGudang as $stok)
                            <tr class="{{ $stok->qty <= 0 ? 'table-danger' : '' }}">
                                <td>
                                    <div class="fw-bold">{{ $stok->nama_gudang }}</div>
                                    @if(!empty($stok->nama_divisi))
                                        <span class="badge bg-light text-primary border border-primary-subtle mt-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-diagram-3 me-1"></i>{{ $stok->nama_divisi }}
                                        </span>
                                    @endif
                                </td>
                                <td class="font-monospace text-muted small fw-semibold">{{ $stok->kode_barang }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $stok->nama }}</div>
                                    <div class="mt-1">
                                        @if($stok->is_bahan_baku)
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 0.72rem;">Bahan Baku</span>
                                        @elseif($stok->is_bahan_setengah_jadi)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.72rem;">Bahan Setengah Jadi</span>
                                        @elseif($stok->is_barang_jadi)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.72rem;">Barang Jadi</span>
                                        @elseif($stok->is_operational)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.72rem;">Operasional</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="fw-bold fs-6">{{ number_format($stok->qty, 2, ',', '.') }}</span>
                                        <span class="text-muted small fw-semibold">{{ $stok->satuan }}</span>
                                    </div>
                                    @if(!empty($stok->satuan_pembelian) && (float)($stok->konversi_pembelian ?? 1) > 1)
                                        <div class="small text-primary mt-1" style="font-size: 0.8rem;">
                                            <i class="bi bi-arrow-repeat me-1"></i><strong>{{ number_format($stok->qty / (float)$stok->konversi_pembelian, 2, ',', '.') }}</strong> {{ $stok->satuan_pembelian }}
                                            <span class="text-muted" style="font-size: 0.72rem;">(1 {{ $stok->satuan_pembelian }} = {{ number_format((float)$stok->konversi_pembelian, 0, ',', '.') }} {{ $stok->satuan }})</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($stok->qty <= 0)
                                        <span class="badge bg-danger">STOK HABIS</span>
                                    @else
                                        <span class="badge bg-success">TERSEDIA</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Belum ada data stok yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-3">
                    {{ $stokGudang->links() }}
                </div>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gudangSelect = document.getElementById('filter_gudang_id');
        const divisiContainer = document.getElementById('filter_divisi_container');
        const divisiSelect = document.getElementById('filter_divisi_id');
        const currentDivisiId = "{{ $divisiId ?? '' }}";

        function loadDivisi(gudangId, selectedId = null) {
            if (!gudangId) {
                divisiContainer.style.display = 'none';
                divisiSelect.innerHTML = '<option value="">-- Semua Divisi --</option>';
                return;
            }

            fetch('/gudangs/' + gudangId + '/divisi')
                .then(res => res.json())
                .then(data => {
                    if (data.is_operasional && data.divisi && data.divisi.length > 0) {
                        divisiContainer.style.display = 'block';
                        let opts = '<option value="">-- Semua Divisi --</option>';
                        data.divisi.forEach(d => {
                            let isSel = (selectedId && selectedId == d.id) ? 'selected' : '';
                            opts += `<option value="${d.id}" ${isSel}>${d.nama}</option>`;
                        });
                        divisiSelect.innerHTML = opts;
                    } else {
                        divisiContainer.style.display = 'none';
                        divisiSelect.innerHTML = '<option value="">-- Semua Divisi --</option>';
                    }
                })
                .catch(() => {
                    divisiContainer.style.display = 'none';
                });
        }

        if (gudangSelect) {
            gudangSelect.addEventListener('change', function() {
                loadDivisi(this.value);
            });

            if (gudangSelect.value) {
                loadDivisi(gudangSelect.value, currentDivisiId);
            }
        }
    });
    </script>

</x-app-layout>