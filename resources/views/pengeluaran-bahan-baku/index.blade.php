<x-app-layout>

<div class="container-fluid">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-0">
                Pengeluaran Bahan Baku
            </h2>

            <small class="text-muted">
                Manajemen pengeluaran stok bahan baku produksi
            </small>

        </div>

        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('pengeluaran-bahan-baku.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no pengeluaran..." value="{{ request('search') }}" style="width: 200px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
                @endif
            </form>
            <a href="{{ route('dashboard') }}"
               class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Dashboard
            </a>
            <a href="{{ route('pengeluaran-bahan-baku.create') }}"
               class="btn btn-primary">
                <i class="bi bi-plus-circle"></i>
                Tambah
            </a>
        </div>

    </div>

    <!-- STATISTIK -->
    <div class="row mb-4">

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Total Pengeluaran</h6>

                    <h2 class="fw-bold">
                        {{ $data->total() }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Draft</h6>

                    <h2 class="fw-bold text-warning">
                        {{ $data->where('status','draft')->count() }}
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h6>Approved</h6>

                    <h2 class="fw-bold text-success">
                        {{
                            $data->whereIn(
                                'status',
                                ['approved','disetujui']
                            )->count()
                        }}
                    </h2>

                </div>

            </div>

        </div>

    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Berhasil!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Gagal!</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ALERT SARAN RESTOCK BAHAN BAKU OUTLET --}}
    @if(!empty($outletSuggestionsSummary))
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-left: 5px solid #f97316 !important;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; font-size: 20px;">
                            <i class="bi bi-shield-exclamation"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-1">
                                Saran Restock Bahan Baku di Outlet / Cabang
                            </h6>
                            <p class="text-muted small mb-0">
                                Terdapat stok Bahan Baku yang berada di bawah batas minimum stock gudang outlet. Klik tombol gudang tujuan untuk langsung membuat pengeluaran bahan baku:
                            </p>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($outletSuggestionsSummary as $sum)
                            <a href="{{ route('pengeluaran-bahan-baku.create', ['gudang_id' => $sum['gudang_id']]) }}" 
                               class="btn btn-sm btn-outline-dark bg-white fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-box-arrow-up-right text-warning"></i>
                                <span>{{ $sum['gudang_nama'] }}</span>
                                <span class="badge bg-danger text-white rounded-pill">{{ $sum['count'] }} item</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- TABEL -->
    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>No</th>
                            <th>Kode</th>
                            <th>Gudang</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th width="180">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    @forelse($data as $item)

                        <tr>

                            <td>
                                {{ $loop->iteration }}
                            </td>

                            <td class="fw-semibold">
                                {{ $item->kode_pengeluaran }}
                            </td>

                            <td>
                                {{ $item->nama_gudang }}
                            </td>

                            <td>
                                {{ $item->tanggal }}
                            </td>

                            <td>

                                @if(
                                    strtolower($item->status) == 'approved'
                                    ||
                                    strtolower($item->status) == 'disetujui'
                                )

                                    <span class="badge bg-success">
                                        Approved
                                    </span>

                                @else

                                    <span class="badge bg-warning text-dark">
                                        Draft
                                    </span>

                                @endif

                            </td>

                            <td>

                                <div class="btn-group">

                                    <a
                                        href="{{ route('pengeluaran-bahan-baku.show',$item->id) }}"
                                        class="btn btn-info btn-sm">

                                        <i class="bi bi-eye"></i>

                                    </a>

                                    @if(strtolower($item->status) == 'draft')
                                        <a href="{{ route('pengeluaran-bahan-baku.edit', $item->id) }}"
                                           class="btn btn-warning btn-sm" title="Edit Permintaan / Pengeluaran">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="{{ route('pengeluaran-bahan-baku.approve', $item->id) }}"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Approve pengeluaran ini?')">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6"
                                class="text-center text-muted">

                                Belum ada data pengeluaran bahan baku.

                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>
            </div>
        </div>
        <div class="mt-3">
            {{ $data->links() }}
        </div>
    </div>

</div>

</x-app-layout>