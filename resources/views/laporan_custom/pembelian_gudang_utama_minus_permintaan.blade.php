<x-app-layout>
    <x-slot name="header">Pembelian Gudang Utama - Permintaan</x-slot>

    <div class="container-fluid">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.laporan-custom.pembelian-minus-permintaan') }}" class="row g-3 align-items-end">
                    <x-date-range-picker-custom startName="start_date" endName="end_date" />
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">Jenis Supplier</label>
                        <select name="jenis_supplier" class="form-control">
                            <option value="nota_pasar" {{ $jenisSupplier == 'nota_pasar' ? 'selected' : '' }}>Supplier Nota Pasar</option>
                            <option value="selain_nota_pasar" {{ $jenisSupplier == 'selain_nota_pasar' ? 'selected' : '' }}>Selain Nota Pasar</option>
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
                        <span class="fw-bold" style="color:#d88656;">Data Pembelian Gudang Utama</span>
                    </div>
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Total Keseluruhan: Rp {{ number_format($pembelianData->sum('total'), 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Kode</th>
                                <th style="background-color: #d88656; color: white;">Tanggal</th>
                                <th style="background-color: #d88656; color: white;">Supplier</th>
                                <th style="background-color: #d88656; color: white;">Total Pembelian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pembelianData as $row)
                                <tr>
                                    <td class="px-4 fw-semibold" style="color:#d88656;">{{ $row->kode_pembelian ?? '-' }}</td>
                                    <td>{{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d M Y') : '-' }}</td>
                                    <td>{{ $row->supplier->nama ?? '-' }}</td>
                                    <td>Rp {{ number_format($row->total ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">Tidak ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot style="background-color: #f8f9fa;">
                            <tr>
                                <th colspan="3" class="text-end px-4 py-3 fw-bold" style="color: #d88656;">Grand Total</th>
                                <th class="fw-bold">Rp {{ number_format($pembelianData->sum('total'), 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
