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
                            <select name="gudang_id" id="filter_gudang_id" class="form-select">
                                <option value="">-- Semua Gudang --</option>
                                @foreach($gudangs as $gudang)
                                    <option value="{{ $gudang->id }}" {{ $gudangId == $gudang->id ? 'selected' : '' }}>
                                        {{ $gudang->nama }} ({{ $gudang->kategori }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3" id="filter_divisi_container" style="{{ empty($divisiId) && empty($gudangId) ? 'display:none;' : '' }}">
                            <label class="form-label fw-semibold small">Filter Divisi</label>
                            <select name="divisi_id" id="filter_divisi_id" class="form-select">
                                <option value="">-- Semua Divisi --</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Filter Barang</label>
                            <select name="barang_id" class="form-select">
                                <option value="">-- Semua Barang --</option>
                                @foreach($barangs as $barang)
                                    <option value="{{ $barang->id }}" {{ $barangId == $barang->id ? 'selected' : '' }}>
                                        {{ $barang->kode_barang }} - {{ $barang->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <a href="{{ route('stok-gudang.index') }}" class="btn btn-secondary">
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
                            <th width="220">Gudang & Divisi</th>
                            <th width="150">Kode Barang</th>
                            <th>Nama Barang</th>
                            <th width="180">Jumlah Stok</th>
                            <th width="150">Status</th>
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
                                <td>{{ $stok->kode_barang }}</td>
                                <td>{{ $stok->nama }}</td>
                                <td>
                                    <span class="fw-bold">{{ number_format($stok->qty, 2, ',', '.') }}</span>
                                    <span class="text-muted small">{{ $stok->satuan }}</span>
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
                                    Belum ada data stok.
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