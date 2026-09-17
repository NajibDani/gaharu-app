<x-app-layout>

<x-slot name="header">
    Detail Stock Opname
</x-slot>

<div class="container-fluid">

    {{-- HEADER --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="fw-bold mb-1">
                Detail Stock Opname
            </h4>

            <p class="text-muted mb-0">
                Informasi hasil stock opname gudang
            </p>

        </div>

        <div class="d-flex align-items-center gap-2">
            @if($stockOpname->status === 'draft' || ($stockOpname->status === 'approved' && $isSuperAdmin))
                <form action="{{ route('stock-opname.destroy', $stockOpname->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Stock Opname {{ $stockOpname->kode_opname }}? Seluruh efek penyesuaian stok, FIFO, dan jurnal terkait akan di-rollback kembali ke kondisi semula.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger text-white fw-bold">
                        <i class="bi bi-trash me-1"></i> Hapus & Rollback Stock
                    </button>
                </form>
            @endif
            <a href="{{ route('stock-opname.index') }}"
               class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>

    </div>

    {{-- INFO HEADER --}}

    <div class="row mb-4">

        <div class="col-md-3">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body">

                    <small class="text-muted">
                        Kode Opname
                    </small>

                    <h6 class="fw-bold mt-2">
                        {{ $stockOpname->kode_opname }}
                    </h6>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body">

                    <small class="text-muted">
                        Gudang & Divisi
                    </small>

                    <h6 class="fw-bold mt-2 mb-0">
                        {{ $stockOpname->gudang->nama }}
                        @if($stockOpname->divisi)
                            <span class="badge bg-light text-primary border border-primary-subtle d-inline-block mt-1">
                                <i class="bi bi-diagram-3 me-1"></i>{{ $stockOpname->divisi->nama }}
                            </span>
                        @endif
                    </h6>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-0 shadow-sm rounded-4 h-100">

                <div class="card-body">

                    <small class="text-muted d-block mb-1">
                        Tanggal Opname
                    </small>

                    @if($stockOpname->status === 'draft' || ($stockOpname->status === 'approved' && $isSuperAdmin))
                        <form action="{{ route('stock-opname.update', $stockOpname->id) }}" method="POST" class="d-flex align-items-center gap-1">
                            @csrf
                            @method('PUT')
                            <input type="date" name="tanggal" class="form-control form-control-sm fw-bold" value="{{ date('Y-m-d', strtotime($stockOpname->tanggal)) }}" required>
                            <button type="submit" class="btn btn-sm btn-primary" title="Simpan Tanggal"><i class="bi bi-check-lg"></i> Ubah</button>
                        </form>
                    @else
                        <h6 class="fw-bold m-0 mt-1">
                            {{ date('d M Y H:i', strtotime($stockOpname->tanggal)) }}
                        </h6>
                    @endif

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-0 shadow-sm rounded-4">

                <div class="card-body">

                    <small class="text-muted">
                        Status
                    </small>

                    <div class="mt-2">

                        @if($stockOpname->status == 'draft')

                            <span class="badge bg-warning">
                                Draft
                            </span>

                        @else

                            <span class="badge bg-success">
                                Approved
                            </span>

                        @endif

                    </div>

                </div>

            </div>

        </div>

    </div>

    {{-- KETERANGAN --}}

    <div class="card border-0 shadow-sm rounded-4 mb-4">

        <div class="card-header text-white fw-bold"
             style="background:#A55A1A;">

            Keterangan

        </div>

        <div class="card-body">

            {{ $stockOpname->keterangan ?: '-' }}

        </div>

    </div>

    {{-- DETAIL BARANG --}}

    <div class="card border-0 shadow-sm rounded-4">

        <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2"
             style="background:#7A4517;">
            <div class="d-flex align-items-center gap-2">
                <span><i class="bi bi-boxes me-1"></i> Detail Stock Opname</span>
                <span class="badge bg-white text-dark" id="showOpnameItemCount">{{ $stockOpname->details->count() }} Item</span>
            </div>
            <div style="min-width: 260px; max-width: 360px;" class="w-100 w-md-auto">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="showOpnameSearchInput" class="form-control border-0 shadow-none" placeholder="Cari nama atau kode barang..." onkeyup="filterShowOpname(this.value)">
                </div>
            </div>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table align-middle mb-0" id="showOpnameTable">

                    <thead>

                    <tr style="background:#7A4517;color:white">

                        <th style="width: 50px;">No</th>
                        <th>Barang</th>
                        <th>Stok Sistem</th>
                        <th>Stok Fisik</th>
                        <th>Selisih</th>
                        <th>Nilai Selisih</th>

                    </tr>

                    </thead>

                    <tbody id="showOpnameTableBody">

                    @php
                        $grandTotal = 0;
                    @endphp

                    @foreach($stockOpname->details as $detail)

                        @php
                            $grandTotal += abs($detail->nilai_selisih);
                            $konversi = (float) ($detail->barang->konversi_pembelian ?? 1);
                            $hasKonversi = !empty($detail->barang->satuan_pembelian) && $konversi > 1;
                            $satuan = $detail->barang->satuan ?? 'pcs';
                            $satuanBeli = $detail->barang->satuan_pembelian ?? '';
                        @endphp

                        <tr class="opname-show-row"
                            data-name="{{ strtolower($detail->barang->nama ?? '') }}"
                            data-code="{{ strtolower($detail->barang->kode_barang ?? '') }}">

                            <td class="text-muted row-index">
                                {{ $loop->iteration }}
                            </td>

                            <td>
                                <div class="fw-bold">{{ $detail->barang->nama }}</div>
                                <small class="text-muted font-monospace">{{ $detail->barang->kode_barang ?? '-' }}</small>
                                @if($hasKonversi)
                                    <small class="text-primary d-block font-monospace" style="font-size:0.72rem;">1 {{ $satuanBeli }} = {{ number_format($konversi, 0, ',', '.') }} {{ $satuan }}</small>
                                @endif
                            </td>

                            <td>
                                <div><span class="fw-semibold">{{ number_format($detail->stok_sistem, 2, ',', '.') }}</span> <span class="text-muted small">{{ $satuan }}</span></div>
                                @if($hasKonversi)
                                    <div class="small text-primary mt-1" style="font-size:0.75rem;">
                                        <i class="bi bi-arrow-repeat me-1"></i>{{ number_format($detail->stok_sistem / $konversi, 2, ',', '.') }} {{ $satuanBeli }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div><span class="fw-bold">{{ number_format($detail->stok_fisik, 2, ',', '.') }}</span> <span class="text-muted small">{{ $satuan }}</span></div>
                                @if($hasKonversi)
                                    <div class="small text-primary mt-1" style="font-size:0.75rem;">
                                        <i class="bi bi-arrow-repeat me-1"></i>{{ number_format($detail->stok_fisik / $konversi, 2, ',', '.') }} {{ $satuanBeli }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                @if($detail->selisih < 0)
                                    <span class="badge bg-danger">
                                        {{ number_format($detail->selisih, 2, ',', '.') }} {{ $satuan }}
                                    </span>
                                    @if($hasKonversi)
                                        <div class="small text-danger mt-1 font-monospace" style="font-size:0.75rem;">
                                            ({{ number_format($detail->selisih / $konversi, 2, ',', '.') }} {{ $satuanBeli }})
                                        </div>
                                    @endif
                                @elseif($detail->selisih > 0)
                                    <span class="badge bg-success">
                                        +{{ number_format($detail->selisih, 2, ',', '.') }} {{ $satuan }}
                                    </span>
                                    @if($hasKonversi)
                                        <div class="small text-success mt-1 font-monospace" style="font-size:0.75rem;">
                                            (+{{ number_format($detail->selisih / $konversi, 2, ',', '.') }} {{ $satuanBeli }})
                                        </div>
                                    @endif
                                @else
                                    <span class="badge bg-secondary">
                                        0 {{ $satuan }}
                                    </span>
                                @endif
                            </td>

                            <td class="fw-bold">
                                Rp {{ number_format($detail->nilai_selisih, 0, ',', '.') }}
                            </td>

                        </tr>

                    @endforeach

                        <tr id="showOpnameNoResults" style="display: none;">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> Tidak ada barang yang cocok dengan pencarian.
                            </td>
                        </tr>

                    </tbody>

                    <tfoot>

                        <tr>

                            <th colspan="5" class="text-end">

                                TOTAL NILAI SELISIH

                            </th>

                            <th>

                                Rp
                                {{ number_format($grandTotal,0,',','.') }}

                            </th>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

    {{-- ACTION --}}

    @if($stockOpname->status == 'draft')

    <div class="mt-4 d-flex gap-2 align-items-center">

        <form action="{{ route('stock-opname.refresh-stok', $stockOpname->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin memperbarui dan menyinkronkan stok sistem dengan kondisi data gudang terkini?')">
            @csrf
            <button type="submit" class="btn btn-outline-primary fw-semibold">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Refresh / Sinkronkan Stok
            </button>
        </form>

        <a href="{{ route('stock-opname.edit', $stockOpname->id) }}"
           class="btn btn-warning text-dark fw-bold">
            <i class="bi bi-pencil-square me-1"></i>
            Edit Stock Opname
        </a>

        <a href="{{ route('stock-opname.approve',$stockOpname->id) }}"
           class="btn btn-success"
           onclick="return confirm('Approve stock opname ini?')">

            <i class="bi bi-check-circle me-1"></i>
            Approve Stock Opname

        </a>

    </div>

    @elseif($stockOpname->status == 'approved' && $isSuperAdmin)

    <div class="mt-4 d-flex gap-2 align-items-center">

        <a href="{{ route('stock-opname.edit', $stockOpname->id) }}"
           class="btn btn-warning text-dark fw-bold">
            <i class="bi bi-pencil-square me-1"></i>
            Edit Stock Opname (Super Admin)
        </a>

    </div>

    @endif

</div>

<script>
function filterShowOpname(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.opname-show-row');
    const noResults = document.getElementById('showOpnameNoResults');
    const countBadge = document.getElementById('showOpnameItemCount');
    let visibleCount = 0;

    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const code = row.getAttribute('data-code') || '';

        if (!q || name.includes(q) || code.includes(q)) {
            row.style.display = '';
            visibleCount++;
            const idxCol = row.querySelector('.row-index');
            if (idxCol) idxCol.textContent = visibleCount;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 ? '' : 'none';
    }

    if (countBadge) {
        if (q) {
            countBadge.textContent = visibleCount + ' dari {{ $stockOpname->details->count() }} Item';
        } else {
            countBadge.textContent = '{{ $stockOpname->details->count() }} Item';
        }
    }
}
</script>

</x-app-layout>