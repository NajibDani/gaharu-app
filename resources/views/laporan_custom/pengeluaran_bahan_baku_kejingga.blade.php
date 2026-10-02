<x-app-layout>
    <x-slot name="header">Pengeluaran Bahan Baku Gudang Utama ke Kejingga</x-slot>

    <div class="container-fluid">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.laporan-custom.pbk-kejingga') }}" class="row g-3 align-items-end">
                    <x-date-range-picker-custom startName="start_date" endName="end_date" />
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn text-white px-4" style="background-color: #d88656; border: none;">
                            <i class="bi bi-search me-1"></i> Tampilkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                @php
                    $grandTotal = $data->sum(function($row) {
                        return $row->details->sum(function($d) {
                            return ($d->hpp_total && $d->hpp_total > 0) ? $d->hpp_total : ($d->total_harga ?? 0);
                        });
                    });
                @endphp
                <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-bottom:1px solid #eadfd4;">
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Data Pengeluaran Bahan Baku</span>
                        <span class="text-muted ms-2" style="font-size:12px;">({{ $data->count() }} Transaksi)</span>
                    </div>
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Total Nilai: Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Kode Transaksi</th>
                                <th style="background-color: #d88656; color: white;">Tanggal</th>
                                <th style="background-color: #d88656; color: white;">Gudang Asal</th>
                                <th style="background-color: #d88656; color: white;">Tujuan (Gudang/Divisi)</th>
                                <th style="background-color: #d88656; color: white;">Keterangan</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Status</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Total Nilai</th>
                                <th style="background-color: #d88656; color: white;" class="text-center pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                @php
                                    $rowTotal = $row->details->sum(function($d) {
                                        return ($d->hpp_total && $d->hpp_total > 0) ? $d->hpp_total : ($d->total_harga ?? 0);
                                    });
                                @endphp
                                <tr>
                                    <td class="px-4 fw-semibold" style="color:#d88656;">{{ $row->kode_pengeluaran ?? '-' }}</td>
                                    <td>{{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d M Y') : '-' }}</td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border">Gudang Utama</span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">{{ $row->gudang->nama ?? 'Kejingga' }}</div>
                                        @if($row->divisi)
                                            <span class="badge bg-light text-primary border border-primary-subtle" style="font-size: 0.72rem;">
                                                <i class="bi bi-diagram-3 me-1"></i>{{ $row->divisi->nama }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $row->keterangan ?? '-' }}</td>
                                    <td class="text-center">
                                        @if($row->status === 'approved' || $row->status === 'disetujui')
                                            <span class="badge bg-success">Approved</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($row->status ?? 'Draft') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
                                    <td class="text-center pe-4">
                                        <button type="button" class="btn btn-sm text-white" style="background-color: #d88656; border: none;"
                                                data-bs-toggle="modal" data-bs-target="#modalDetail{{ $row->id }}">
                                            <i class="bi bi-eye"></i> Detail
                                        </button>

                                        {{-- MODAL DETAIL --}}
                                        <div class="modal fade" id="modalDetail{{ $row->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg text-start">
                                                <div class="modal-content">
                                                    <div class="modal-header text-white" style="background-color: #d88656;">
                                                        <h5 class="modal-title">Rincian Pengeluaran #{{ $row->kode_pengeluaran }}</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-0">
                                                        <div class="bg-light px-4 py-3 border-bottom text-muted" style="font-size:13px;">
                                                            <strong>Tanggal:</strong> {{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d M Y') : '-' }} &nbsp;|&nbsp;
                                                            <strong>Tujuan:</strong> {{ $row->gudang->nama ?? 'Kejingga' }} {{ $row->divisi ? '('.$row->divisi->nama.')' : '' }} &nbsp;|&nbsp;
                                                            <strong>Status:</strong> 
                                                            <span class="badge {{ ($row->status === 'approved' || $row->status === 'disetujui') ? 'bg-success' : 'bg-secondary' }}">
                                                                {{ ucfirst($row->status ?? 'Draft') }}
                                                            </span>
                                                        </div>
                                                        <div class="table-responsive">
                                                            <table class="table align-middle mb-0" style="font-size:13px;">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th class="px-4">Nama Barang</th>
                                                                        <th class="text-center">Qty</th>
                                                                        <th>Satuan</th>
                                                                        <th class="text-end pe-4">Total Nilai</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @forelse($row->details as $detail)
                                                                        @php
                                                                            $detailNilai = ($detail->hpp_total && $detail->hpp_total > 0) ? $detail->hpp_total : ($detail->total_harga ?? 0);
                                                                        @endphp
                                                                        <tr>
                                                                            <td class="px-4 fw-semibold" style="color: #d88656;">{{ $detail->barang->nama ?? '-' }}</td>
                                                                            <td class="text-center">{{ number_format($detail->qty ?? $detail->jumlah ?? 0, 2, ',', '.') }}</td>
                                                                            <td class="text-muted">{{ $detail->barang->satuan ?? '-' }}</td>
                                                                            <td class="text-end pe-4">Rp {{ number_format($detailNilai, 0, ',', '.') }}</td>
                                                                        </tr>
                                                                    @empty
                                                                        <tr>
                                                                            <td colspan="4" class="text-center py-3 text-muted">Tidak ada rincian barang</td>
                                                                        </tr>
                                                                    @endforelse
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr style="background:#fdf3ec; font-weight:bold;">
                                                                        <td colspan="3" class="text-end" style="color:#d88656;">Total Nilai:</td>
                                                                        <td class="text-end pe-4" style="color:#d88656;">
                                                                            Rp {{ number_format($rowTotal, 0, ',', '.') }}
                                                                        </td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn text-white btn-sm px-4" style="background-color: #606060; border: none;" data-bs-dismiss="modal">Tutup</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        Tidak ada data pengeluaran bahan baku.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot style="background-color: #f8f9fa;">
                            <tr>
                                <th colspan="6" class="text-end px-4 py-3 fw-bold" style="color: #d88656;">Grand Total</th>
                                <th class="text-end fw-bold">Rp {{ number_format($grandTotal, 0, ',', '.') }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .btn-close-white {
            filter: invert(1) grayscale(1) brightness(2);
        }
    </style>
</x-app-layout>
