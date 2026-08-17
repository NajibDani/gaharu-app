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

        <a href="{{ route('stock-opname.index') }}"
           class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>
            Kembali

        </a>

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

                    @if($stockOpname->status === 'draft')
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

        <div class="card-header text-white fw-bold"
             style="background:#7A4517;">

            Detail Stock Opname

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                    <tr style="background:#7A4517;color:white">

                        <th>No</th>
                        <th>Barang</th>
                        <th>Stok Sistem</th>
                        <th>Stok Fisik</th>
                        <th>Selisih</th>
                        <th>Nilai Selisih</th>

                    </tr>

                    </thead>

                    <tbody>

                    @php
                        $grandTotal = 0;
                    @endphp

                    @foreach($stockOpname->details as $detail)

                        @php
                            $grandTotal += abs($detail->nilai_selisih);
                        @endphp

                        <tr>

                            <td>
                                {{ $loop->iteration }}
                            </td>

                            <td>
                                {{ $detail->barang->nama }}
                            </td>

                            <td>
                                {{ number_format($detail->stok_sistem,2,',','.') }}
                            </td>

                            <td>
                                {{ number_format($detail->stok_fisik,2,',','.') }}
                            </td>

                            <td>

                                @if($detail->selisih < 0)

                                    <span class="badge bg-danger">

                                        {{ number_format($detail->selisih,2) }}

                                    </span>

                                @elseif($detail->selisih > 0)

                                    <span class="badge bg-success">

                                        +{{ number_format($detail->selisih,2) }}

                                    </span>

                                @else

                                    <span class="badge bg-secondary">

                                        0

                                    </span>

                                @endif

                            </td>

                            <td>

                                Rp
                                {{ number_format($detail->nilai_selisih,0,',','.') }}

                            </td>

                        </tr>

                    @endforeach

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

    <div class="mt-4">

        <a href="{{ route('stock-opname.approve',$stockOpname->id) }}"
           class="btn btn-success"
           onclick="return confirm('Approve stock opname ini?')">

            <i class="bi bi-check-circle"></i>
            Approve Stock Opname

        </a>

    </div>

    @endif

</div>

</x-app-layout>