<x-app-layout>

    <div class="container">

        <h4 class="mb-4 fw-bold">
            Stok Gudang Batch (FIFO)
        </h4>

        <!-- FILTER -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('stok-gudang-batch.index') }}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Filter Gudang</label>
                            <select name="gudang_id" id="filter_gudang_id" class="form-select">
                                <option value="">-- Semua Gudang --</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                        {{ $g->nama }} ({{ $g->kategori }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3" id="filter_divisi_container" style="{{ !request('divisi_id') && !request('gudang_id') ? 'display:none;' : '' }}">
                            <label class="form-label fw-semibold small">Filter Divisi</label>
                            <select name="divisi_id" id="filter_divisi_id" class="form-select">
                                <option value="">-- Semua Divisi --</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Filter Barang</label>
                            <select name="barang_id" class="form-select">
                                <option value="">-- Semua Barang --</option>
                                @foreach($barangs as $b)
                                    <option value="{{ $b->id }}" {{ request('barang_id') == $b->id ? 'selected' : '' }}>
                                        {{ $b->kode_barang }} - {{ $b->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 d-flex gap-1">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <a href="{{ route('stok-gudang-batch.index') }}" class="btn btn-secondary">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Gudang & Divisi</th>
                                <th>Supplier</th>
                                <th>Nama Barang</th>
                                <th>Qty Masuk</th>
                                <th>Qty Keluar</th>
                                <th>Qty Sisa</th>
                                <th>Harga / Qty</th>
                                <th>Batch Number</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($data as $item)
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $item->gudang->nama ?? '-' }}</div>
                                        @if($item->divisi)
                                            <span class="badge bg-light text-primary border border-primary-subtle mt-1" style="font-size: 0.75rem;">
                                                <i class="bi bi-diagram-3 me-1"></i>{{ $item->divisi->nama }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $item->supplier->nama ?? '-' }}</td>
                                    <td>{{ $item->barang->nama ?? '-' }}</td>
                                    <td>{{ $item->qty_masuk }}</td>
                                    <td>{{ $item->qty_keluar }}</td>
                                    <td class="fw-bold {{ $item->qty_sisa > 0 ? 'text-success' : 'text-muted' }}">{{ $item->qty_sisa }}</td>
                                    <td>Rp {{ number_format($item->harga_per_qty, 0, ',', '.') }}</td>
                                    <td><code>{{ $item->batch_number }}</code></td>
                                    <td>
                                        @if($item->is_habis || $item->qty_sisa <= 0)
                                            <span class="badge bg-danger">Habis</span>
                                        @else
                                            <span class="badge bg-success">Aktif</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">Tidak ada data batch stok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $data->links() }}
                </div>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gudangSelect = document.getElementById('filter_gudang_id');
        const divisiContainer = document.getElementById('filter_divisi_container');
        const divisiSelect = document.getElementById('filter_divisi_id');
        const currentDivisiId = "{{ request('divisi_id') }}";

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