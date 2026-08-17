
<x-app-layout>

<x-slot name="header">
    Pengeluaran Bahan Baku
</x-slot>

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

@php
    $grandTotal = 0;
@endphp

<div class="page-header mb-4">

    <div class="d-flex justify-content-between align-items-center">

        <div>

            <h1 class="page-header-title">
                Detail Pengeluaran Bahan Baku
            </h1>

            <p class="text-muted mb-0">
                Informasi transaksi pengeluaran bahan baku dan perhitungan FIFO.
            </p>

        </div>

        <a href="{{ route('pengeluaran-bahan-baku.index') }}"
           class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>
            Kembali

        </a>

    </div>

</div>

<div class="row mb-4">

    <div class="col-md-3 mb-3">

        <div class="card p-3 h-100">

            <small class="text-muted">
                Kode Pengeluaran
            </small>

            <h5 class="fw-bold mb-0">
                {{ $pengeluaran->kode_pengeluaran }}
            </h5>

        </div>

    </div>

    <div class="col-md-3 mb-3">

        <div class="card p-3 h-100">

            <small class="text-muted">
                Status
            </small>

            <div class="mt-2">

                @if($pengeluaran->status == 'approved')

                    <span class="badge bg-success">
                        Approved
                    </span>

                @else

                    <span class="badge bg-warning text-dark">
                        Draft
                    </span>

                @endif

            </div>

        </div>

    </div>

    <div class="col-md-3 mb-3">

        <div class="card p-3 h-100">

            <small class="text-muted">
                Gudang & Divisi Tujuan
            </small>

            <h6 class="fw-bold mb-0">
                {{ $pengeluaran->gudang->nama ?? '-' }}
                @if($pengeluaran->divisi)
                    <span class="badge bg-light text-primary border border-primary-subtle d-inline-block mt-1">
                        <i class="bi bi-diagram-3 me-1"></i>{{ $pengeluaran->divisi->nama }}
                    </span>
                @endif
            </h6>

        </div>

    </div>

    <div class="col-md-3 mb-3">

        <div class="card p-3 h-100">

            <small class="text-muted">
                Tanggal
            </small>

            <h6 class="fw-bold mb-0">
                {{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d M Y H:i') }}
            </h6>

        </div>

    </div>

</div>

<div class="card mb-4">

    <div
        class="card-header text-white fw-bold"
        style="
            background:#9c4f18;
            border-radius:24px 24px 0 0;
        ">

        <i class="bi bi-info-circle me-2"></i>
        Informasi Pengeluaran

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-12">

                <label class="fw-bold">
                    Keterangan
                </label>

                <div class="mt-2 text-muted">

                    {{ $pengeluaran->keterangan ?? '-' }}

                </div>

            </div>

        </div>

    </div>

</div>

<div class="card">

    <div
        class="card-header text-white fw-bold"
        style="
            background:#5a3416;
            border-radius:24px 24px 0 0;
        ">

        <i class="bi bi-box-seam me-2"></i>
        Detail Barang

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead>

                    <tr>

                        <th width="70">No</th>
                        <th>Barang</th>
                        <th width="120">Qty</th>
                        <th width="120">Satuan</th>
                        <th width="180">Harga FIFO</th>
                        <th width="200">Total FIFO</th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($pengeluaran->details as $detail)

                        @php

                            $hargaFIFO =
                                $detail->qty > 0
                                    ? $detail->hpp_total / $detail->qty
                                    : 0;

                            $grandTotal += $detail->hpp_total;

                        @endphp

                        <tr>

                            <td>
                                {{ $loop->iteration }}
                            </td>

                            <td>

                                <div class="fw-semibold">
                                    {{ $detail->barang->nama ?? '-' }}
                                </div>

                            </td>

                            <td>

                                {{ number_format($detail->qty,2) }}

                            </td>

                            <td>

                                {{ $detail->barang->satuan ?? '-' }}

                            </td>

                            <td>
                                Rp {{ number_format($hargaFIFO,2,',','.') }}
                                @if($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui')
                                    <small class="text-muted d-block" style="font-size: 9px;">(Estimasi)</small>
                                @endif
                            </td>

                            <td>
                                <strong>
                                    Rp {{ number_format($detail->hpp_total,2,',','.') }}
                                </strong>
                                @if($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui')
                                    <small class="text-muted d-block" style="font-size: 9px;">(Estimasi)</small>
                                @endif
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6"
                                class="text-center text-muted py-4">

                                Tidak ada detail barang

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

<div class="row mt-4">

    <div class="col-md-4 ms-auto">

        <div class="card p-4 shadow-sm border-0">

            <small class="text-muted">
                Total Nilai FIFO @if($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui') (Estimasi) @endif
            </small>

            <h3
                class="fw-bold mt-2"
                style="color:#1e3a8a;">

                Rp {{ number_format($grandTotal,2,',','.') }}

            </h3>

        </div>

    </div>

</div>

@if($pengeluaran->status == 'draft')

    @php

        $isWO =
            str_contains(
                strtolower(
                    $pengeluaran->keterangan ?? ''
                ),
                'permintaan bahan baku untuk'
            );

    @endphp

<div class="d-flex gap-2 mt-4">

    @if(!$isWO)

        <a href="{{ route('pengeluaran-bahan-baku.edit', $pengeluaran->id) }}"
           class="btn"
           style="
                background:#d88656;
                color:white;
           ">

            <i class="bi bi-pencil-square"></i>
            Edit Pengeluaran

        </a>

    @endif

    <a href="{{ route('pengeluaran-bahan-baku.approve', $pengeluaran->id) }}"
       class="btn btn-success"
       onclick="return confirm('Approve pengeluaran ini?')">

        <i class="bi bi-check-circle"></i>
        Approve Pengeluaran

    </a>

</div>

@endif

</x-app-layout>
