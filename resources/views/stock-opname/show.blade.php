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
            <a href="{{ route('stock-opname.export-excel', $stockOpname->id) }}" class="btn btn-success text-white fw-bold">
                <i class="bi bi-file-earmark-excel me-1"></i> Download Excel
            </a>
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
            <div class="d-flex align-items-center flex-wrap gap-2 w-100 w-md-auto">
                <div style="min-width: 220px; max-width: 280px;" class="flex-grow-1 flex-md-grow-0">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="showOpnameSearchInput" class="form-control border-0 shadow-none" placeholder="Cari nama atau kode barang..." oninput="applyShowOpnameFilterSort()">
                        <button class="btn btn-outline-light border-0 btn-sm" type="button" onclick="document.getElementById('showOpnameSearchInput').value=''; applyShowOpnameFilterSort();" title="Reset">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <div style="min-width: 170px;">
                    <select id="showOpnameSelisihFilter" class="form-select form-select-sm" onchange="applyShowOpnameFilterSort()" title="Filter berdasarkan selisih">
                        <option value="all">Semua Status Selisih</option>
                        <option value="minus">Selisih Minus (Shortage)</option>
                        <option value="plus">Selisih Plus (Surplus)</option>
                        <option value="selisih">Ada Selisih (&ne; 0)</option>
                        <option value="zero">Selisih Nol (Cocok)</option>
                        <option value="extreme">Selisih Ekstrem (&ge; 20% / &le; -20%)</option>
                    </select>
                </div>
                <div style="min-width: 225px;">
                    <select id="showOpnameQuickSort" class="form-select form-select-sm" onchange="handleShowQuickSortChange(this.value)" title="Urutkan dari besar ke terkecil atau sebaliknya">
                        <option value="">Urutkan Kolom (Besar &rarr; Kecil)...</option>
                        <option value="persen_desc">% Selisih: Terbesar (+ Surplus)</option>
                        <option value="persen_asc">% Selisih: Terkecil (- Shortage)</option>
                        <option value="nilai_desc">Nilai Selisih: Besar &rarr; Kecil</option>
                        <option value="nilai_asc">Nilai Selisih: Kecil &rarr; Besar</option>
                        <option value="selisih_desc">Selisih Qty: Terbesar (+ Surplus)</option>
                        <option value="selisih_asc">Selisih Qty: Terkecil (- Shortage)</option>
                        <option value="sistem_desc">Stok Sistem: Besar &rarr; Kecil</option>
                        <option value="sistem_asc">Stok Sistem: Kecil &rarr; Besar</option>
                        <option value="fisik_desc">Stok Fisik: Besar &rarr; Kecil</option>
                        <option value="fisik_asc">Stok Fisik: Kecil &rarr; Besar</option>
                        <option value="nama_asc">Nama Barang: A &rarr; Z</option>
                        <option value="nama_desc">Nama Barang: Z &rarr; A</option>
                        <option value="default">Reset ke Urutan Awal</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table align-middle mb-0" id="showOpnameTable">

                    <thead>

                    <tr style="background:#7A4517;color:white">

                        <th class="text-white text-center show-sortable-th" data-col="0" data-type="num" onclick="sortShowOpnameTable(0, 'num')" style="width: 60px; cursor: pointer; user-select: none;" title="Klik untuk mengurutkan No">
                            <span>No</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>
                        <th class="text-white show-sortable-th" data-col="1" data-type="str" onclick="sortShowOpnameTable(1, 'str')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Nama Barang (A-Z / Z-A)">
                            <span>Barang</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>
                        <th class="text-white show-sortable-th" data-col="2" data-type="num" onclick="sortShowOpnameTable(2, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Stok Sistem (Besar ke Kecil / Kecil ke Besar)">
                            <span>Stok Sistem</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>
                        <th class="text-white show-sortable-th" data-col="3" data-type="num" onclick="sortShowOpnameTable(3, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Stok Fisik (Besar ke Kecil / Kecil ke Besar)">
                            <span>Stok Fisik</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>
                        <th class="text-white show-sortable-th" data-col="4" data-type="num" onclick="sortShowOpnameTable(4, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Selisih (Besar ke Kecil / Kecil ke Besar)">
                            <span>Selisih</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>
                        <th class="text-white text-center show-sortable-th" data-col="5" data-type="num" onclick="sortShowOpnameTable(5, 'num')" style="width: 105px; cursor: pointer; user-select: none;" title="Klik untuk mengurutkan % Selisih (Besar ke Kecil / Kecil ke Besar)">
                            <span>% Selisih</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>
                        <th class="text-white show-sortable-th" data-col="6" data-type="num" onclick="sortShowOpnameTable(6, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Nilai Selisih (Besar ke Kecil / Kecil ke Besar)">
                            <span>Nilai Selisih</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                        </th>

                    </tr>

                    </thead>

                    <tbody id="showOpnameTableBody">

                    @php
                        $grandTotal = 0;
                    @endphp

                    @foreach($stockOpname->details as $detail)

                        @php
                            // nilai_selisih di DB selalu positif (abs * harga)
                            // Gunakan tanda dari kolom selisih agar surplus & shortage saling mengurangi
                            $nilai = (float) $detail->nilai_selisih;
                            if ((float) $detail->selisih >= 0) {
                                $grandTotal += $nilai;   // surplus = +
                            } else {
                                $grandTotal -= $nilai;   // shortage = -
                            }
                            $konversi = (float) ($detail->barang->konversi_pembelian ?? 1);
                            $hasKonversi = !empty($detail->barang->satuan_pembelian) && $konversi > 1;
                            $satuan = $detail->barang->satuan ?? 'pcs';
                            $satuanBeli = $detail->barang->satuan_pembelian ?? '';

                            $stokSistemVal = (float) $detail->stok_sistem;
                            $selisihVal = (float) $detail->selisih;
                            if (abs($stokSistemVal) > 0.0001) {
                                $persenSelisih = round(($selisihVal / abs($stokSistemVal)) * 100, 2);
                            } else {
                                $persenSelisih = $selisihVal > 0 ? 100 : ($selisihVal < 0 ? -100 : 0);
                            }
                        @endphp

                        <tr class="opname-show-row"
                            data-name="{{ strtolower($detail->barang->nama ?? '') }}"
                            data-code="{{ strtolower($detail->barang->kode_barang ?? '') }}"
                            data-index="{{ $loop->iteration }}"
                            data-stok-sistem="{{ (float)$detail->stok_sistem }}"
                            data-stok-fisik="{{ (float)$detail->stok_fisik }}"
                            data-selisih="{{ (float)$detail->selisih }}"
                            data-persen="{{ $persenSelisih }}"
                            data-nilai-selisih="{{ (float)$detail->nilai_selisih }}">

                            <td class="text-muted row-index text-center" data-val="{{ $loop->iteration }}">
                                {{ $loop->iteration }}
                            </td>

                            <td data-val="{{ strtolower($detail->barang->nama ?? '') }}">
                                <div class="fw-bold">{{ $detail->barang->nama }}</div>
                                <small class="text-muted font-monospace">{{ $detail->barang->kode_barang ?? '-' }}</small>
                                @if($hasKonversi)
                                    <small class="text-primary d-block font-monospace" style="font-size:0.72rem;">1 {{ $satuanBeli }} = {{ number_format($konversi, 0, ',', '.') }} {{ $satuan }}</small>
                                @endif
                            </td>

                            <td data-val="{{ (float)$detail->stok_sistem }}">
                                <div><span class="fw-semibold">{{ number_format($detail->stok_sistem, 2, ',', '.') }}</span> <span class="text-muted small">{{ $satuan }}</span></div>
                                @if($hasKonversi)
                                    <div class="small text-primary mt-1" style="font-size:0.75rem;">
                                        <i class="bi bi-arrow-repeat me-1"></i>{{ number_format($detail->stok_sistem / $konversi, 2, ',', '.') }} {{ $satuanBeli }}
                                    </div>
                                @endif
                            </td>

                            <td data-val="{{ (float)$detail->stok_fisik }}">
                                <div><span class="fw-bold">{{ number_format($detail->stok_fisik, 2, ',', '.') }}</span> <span class="text-muted small">{{ $satuan }}</span></div>
                                @if($hasKonversi)
                                    <div class="small text-primary mt-1" style="font-size:0.75rem;">
                                        <i class="bi bi-arrow-repeat me-1"></i>{{ number_format($detail->stok_fisik / $konversi, 2, ',', '.') }} {{ $satuanBeli }}
                                    </div>
                                @endif
                            </td>

                            <td data-val="{{ (float)$detail->selisih }}">
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

                            <td class="text-center" data-val="{{ $persenSelisih }}">
                                @if($persenSelisih > 0)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">
                                        +{{ number_format($persenSelisih, 1, ',', '.') }}%
                                    </span>
                                @elseif($persenSelisih < 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">
                                        {{ number_format($persenSelisih, 1, ',', '.') }}%
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        0%
                                    </span>
                                @endif
                            </td>

                            <td class="fw-bold" data-val="{{ (float)$detail->nilai_selisih }}">
                                Rp {{ number_format($detail->nilai_selisih, 0, ',', '.') }}
                            </td>

                        </tr>

                    @endforeach

                        <tr id="showOpnameNoResults" style="display: none;">
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> Tidak ada barang yang cocok dengan filter / pencarian.
                            </td>
                        </tr>

                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="6" class="text-end py-3">
                                TOTAL NILAI SELISIH <small class="text-muted fw-normal">(Surplus - Shortage)</small>
                            </th>
                            <th id="showOpnameGrandTotal" class="py-3 {{ $grandTotal > 0 ? 'text-success' : ($grandTotal < 0 ? 'text-danger' : 'text-muted') }}">
                                {{ $grandTotal > 0 ? '+' : '' }}Rp {{ number_format($grandTotal, 0, ',', '.') }}
                                @if($grandTotal > 0)
                                    <div class="fw-normal small text-success"><i class="bi bi-arrow-up-circle me-1"></i>Surplus bersih</div>
                                @elseif($grandTotal < 0)
                                    <div class="fw-normal small text-danger"><i class="bi bi-arrow-down-circle me-1"></i>Shortage bersih</div>
                                @endif
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
let showOpnameSortState = {
    col: -1,
    dir: 'desc'
};

function sortShowOpnameTable(colIndex, type) {
    if (showOpnameSortState.col === colIndex) {
        showOpnameSortState.dir = (showOpnameSortState.dir === 'desc') ? 'asc' : 'desc';
    } else {
        showOpnameSortState.col = colIndex;
        // Kolom numerik default dari BESAR KE KECIL ('desc')
        showOpnameSortState.dir = (type === 'num' && colIndex !== 0) ? 'desc' : 'asc';
    }

    applyShowOpnameFilterSort();
}

function handleShowQuickSortChange(val) {
    switch (val) {
        case 'persen_desc':
            showOpnameSortState.col = 5;
            showOpnameSortState.dir = 'desc';
            break;
        case 'persen_asc':
            showOpnameSortState.col = 5;
            showOpnameSortState.dir = 'asc';
            break;
        case 'nilai_desc':
            showOpnameSortState.col = 6;
            showOpnameSortState.dir = 'desc';
            break;
        case 'nilai_asc':
            showOpnameSortState.col = 6;
            showOpnameSortState.dir = 'asc';
            break;
        case 'selisih_desc':
            showOpnameSortState.col = 4;
            showOpnameSortState.dir = 'desc';
            break;
        case 'selisih_asc':
            showOpnameSortState.col = 4;
            showOpnameSortState.dir = 'asc';
            break;
        case 'sistem_desc':
            showOpnameSortState.col = 2;
            showOpnameSortState.dir = 'desc';
            break;
        case 'sistem_asc':
            showOpnameSortState.col = 2;
            showOpnameSortState.dir = 'asc';
            break;
        case 'fisik_desc':
            showOpnameSortState.col = 3;
            showOpnameSortState.dir = 'desc';
            break;
        case 'fisik_asc':
            showOpnameSortState.col = 3;
            showOpnameSortState.dir = 'asc';
            break;
        case 'nama_asc':
            showOpnameSortState.col = 1;
            showOpnameSortState.dir = 'asc';
            break;
        case 'nama_desc':
            showOpnameSortState.col = 1;
            showOpnameSortState.dir = 'desc';
            break;
        case 'default':
            showOpnameSortState.col = 0;
            showOpnameSortState.dir = 'asc';
            break;
        default:
            return;
    }

    applyShowOpnameFilterSort();
}

function applyShowOpnameFilterSort() {
    const searchInput = document.getElementById('showOpnameSearchInput');
    const selisihFilter = document.getElementById('showOpnameSelisihFilter');
    const tbody = document.getElementById('showOpnameTableBody');
    if (!tbody) return;

    const q = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
    const sFilter = selisihFilter ? (selisihFilter.value || 'all') : 'all';

    const colIndex = showOpnameSortState.col;
    const isAsc = (showOpnameSortState.dir === 'asc');

    // 1. Sort semua baris jika kolom sort aktif
    let rows = Array.from(tbody.querySelectorAll('.opname-show-row'));
    if (colIndex >= 0) {
        rows.sort((rowA, rowB) => {
            const cellA = rowA.children[colIndex];
            const cellB = rowB.children[colIndex];

            const valA = cellA ? (cellA.getAttribute('data-val') ?? cellA.textContent.trim()) : '';
            const valB = cellB ? (cellB.getAttribute('data-val') ?? cellB.textContent.trim()) : '';

            const numA = parseFloat(valA);
            const numB = parseFloat(valB);

            let cmp = 0;
            if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                cmp = numA - numB;
            } else {
                cmp = String(valA).localeCompare(String(valB), 'id', { numeric: true, sensitivity: 'base' });
            }

            return isAsc ? cmp : -cmp;
        });

        // Masukkan kembali ke tbody dalam urutan baru
        const noResults = document.getElementById('showOpnameNoResults');
        rows.forEach(r => tbody.appendChild(r));
        if (noResults) tbody.appendChild(noResults);
    }

    // 2. Filter baris berdasarkan pencarian & status selisih
    let visibleCount = 0;
    let netGrandTotal = 0;

    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const code = row.getAttribute('data-code') || '';
        const selisih = parseFloat(row.getAttribute('data-selisih') || 0);
        const persen = parseFloat(row.getAttribute('data-persen') || 0);
        const nilaiSelisih = parseFloat(row.getAttribute('data-nilai-selisih') || 0);

        let matchSearch = (!q || name.includes(q) || code.includes(q));
        let matchFilter = true;

        if (sFilter === 'minus') {
            matchFilter = (selisih < 0);
        } else if (sFilter === 'plus') {
            matchFilter = (selisih > 0);
        } else if (sFilter === 'selisih') {
            matchFilter = (Math.abs(selisih) > 0.0001);
        } else if (sFilter === 'zero') {
            matchFilter = (Math.abs(selisih) < 0.0001);
        } else if (sFilter === 'extreme') {
            matchFilter = (Math.abs(persen) >= 20);
        }

        if (matchSearch && matchFilter) {
            row.style.display = '';
            visibleCount++;
            const idxCell = row.querySelector('.row-index');
            if (idxCell) idxCell.innerText = visibleCount;

            if (selisih >= 0) {
                netGrandTotal += nilaiSelisih;
            } else {
                netGrandTotal -= nilaiSelisih;
            }
        } else {
            row.style.display = 'none';
        }
    });

    // 3. Update counter & empty message
    const noResults = document.getElementById('showOpnameNoResults');
    if (noResults) {
        noResults.style.display = visibleCount === 0 ? '' : 'none';
    }

    const countBadge = document.getElementById('showOpnameItemCount');
    if (countBadge) {
        if (q || sFilter !== 'all') {
            countBadge.textContent = visibleCount + ' dari {{ $stockOpname->details->count() }} Item';
        } else {
            countBadge.textContent = '{{ $stockOpname->details->count() }} Item';
        }
    }

    // 4. Update grand total display
    const grandTotalEl = document.getElementById('showOpnameGrandTotal');
    if (grandTotalEl) {
        grandTotalEl.className = `py-3 ${netGrandTotal > 0 ? 'text-success' : (netGrandTotal < 0 ? 'text-danger' : 'text-muted')}`;
        let subText = '';
        if (netGrandTotal > 0) {
            subText = '<div class="fw-normal small text-success"><i class="bi bi-arrow-up-circle me-1"></i>Surplus bersih</div>';
        } else if (netGrandTotal < 0) {
            subText = '<div class="fw-normal small text-danger"><i class="bi bi-arrow-down-circle me-1"></i>Shortage bersih</div>';
        }
        grandTotalEl.innerHTML = `${netGrandTotal > 0 ? '+' : ''}Rp ${netGrandTotal.toLocaleString('id-ID')} ${subText}`;
    }

    // 5. Update icon dan highlight pada header
    const headers = document.querySelectorAll('#showOpnameTable .show-sortable-th');
    headers.forEach(h => {
        const col = parseInt(h.getAttribute('data-col'));
        const icon = h.querySelector('.sort-icon');
        const badgeOld = h.querySelector('.sort-badge');
        if (badgeOld) badgeOld.remove();

        if (col === colIndex) {
            h.style.backgroundColor = '#5c3210';
            if (icon) {
                icon.className = isAsc
                    ? 'bi bi-sort-up text-warning ms-1 sort-icon'
                    : 'bi bi-sort-down-alt text-warning ms-1 sort-icon';
            }
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning text-dark py-0 px-1 ms-1 sort-badge';
            badge.style.fontSize = '0.62rem';
            badge.innerText = isAsc ? 'Kecil→Besar' : 'Besar→Kecil';
            h.appendChild(badge);
        } else {
            h.style.backgroundColor = '';
            if (icon) {
                icon.className = 'bi bi-arrow-down-up text-white-50 ms-1 sort-icon';
            }
        }
    });
}
</script>

</x-app-layout>