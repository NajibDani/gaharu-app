<x-app-layout>
    <x-slot name="header">Total Permintaan Bahan Baku per Divisi</x-slot>

    <div class="container-fluid">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.laporan-custom.total-permintaan-divisi') }}" class="row g-3 align-items-end">
                    <x-date-range-picker-custom startName="start_date" endName="end_date" />
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Divisi</label>
                        <select name="divisi_id" class="form-control">
                            <option value="">-- Semua Divisi --</option>
                            @foreach($divisis as $div)
                                <option value="{{ $div->id }}" {{ $divisi_id == $div->id ? 'selected' : '' }}>{{ $div->nama }}</option>
                            @endforeach
                        </select>
                    </div>
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
                <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-bottom:1px solid #eadfd4;">
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Data Permintaan Bahan Baku</span>
                    </div>
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Total Keseluruhan: Rp {{ number_format($data->sum(function($q) { return $q->details->sum('total_harga'); }), 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Kode</th>
                                <th style="background-color: #d88656; color: white;">Tanggal</th>
                                <th style="background-color: #d88656; color: white;">Divisi</th>
                                <th style="background-color: #d88656; color: white;">Status</th>
                                <th style="background-color: #d88656; color: white;">Keterangan</th>
                                <th style="background-color: #d88656; color: white;">Total Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                <tr>
                                    <td class="px-4 fw-semibold" style="color:#d88656;">{{ $row->kode_pengeluaran ?? '-' }}</td>
                                    <td>{{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d M Y') : '-' }}</td>
                                    <td>{{ $row->divisi->nama ?? '-' }}</td>
                                    <td>{{ $row->status ?? '-' }}</td>
                                    <td>{{ $row->keterangan ?? '-' }}</td>
                                    <td>Rp {{ number_format($row->details->sum('total_harga'), 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">Tidak ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot style="background-color: #f8f9fa;">
                            <tr>
                                <th colspan="5" class="text-end px-4 py-3 fw-bold" style="color: #d88656;">Grand Total</th>
                                <th class="fw-bold">Rp {{ number_format($data->sum(function($q) { return $q->details->sum('total_harga'); }), 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
