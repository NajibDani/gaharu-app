<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modul Jurnal Khusus Penjualan B2B</h2>
    </x-slot>

    <div class="container py-4">
        @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
        @endif

        <div class="card shadow border-0 rounded-3 mb-5">
            <div class="card-header bg-warning text-dark py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold"><i class="fas fa-clock me-2"></i>1. Antrean Transaksi Penjualan B2B (Belum Dijurnal)</h5>
                <span class="badge bg-dark text-white">{{ count($pesananBelum) }} Transaksi</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-bold">
                            <tr>
                                <th class="py-3 ps-4">Tanggal Transaksi</th>
                                <th class="py-3">Tipe Antrean</th>
                                <th class="py-3">No. Referensi / Kode</th>
                                <th class="py-3">Nama Customer</th>
                                <th class="py-3 text-end">Nilai Transaksi (DPP)</th>
                                <th class="py-3 text-center" style="width: 180px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pesananBelum as $p)
                            <tr>
                                <td class="py-3 ps-4 text-secondary">
                                    {{ \Carbon\Carbon::parse($p->tanggal_antrean)->format('d/m/Y') }}
                                </td>

                                <td>
                                    <span class="badge {{ $p->antrean_type === 'pembayaran' ? 'bg-info text-dark' : 'bg-dark text-white' }} px-2 py-1">
                                        {{ $p->label_antrean }}
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border font-monospace px-2.5 py-1.5 fs-6">
                                        {{ $p->no_transaksi }}
                                    </span>
                                </td>

                                <td class="fw-semibold text-dark">
                                    {{ $p->antrean_type === 'pembayaran' ? ($p->pesanan->customer->nama ?? 'Customer B2B') : ($p->nama_customer ?? 'Customer B2B') }}
                                </td>

                                <td class="text-end fw-bold text-dark">
                                    Rp {{ number_format($p->nominal_display, 2, ',', '.') }}
                                </td>

                                <td class="text-center">
                                    <form action="{{ route('jurnal-penjualanb2b.post-auto', $p->id) }}?type={{ $p->antrean_type }}" method="POST" class="d-inline" onsubmit="return confirm('Posting jurnal khusus B2B untuk {{ $p->no_transaksi }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success fw-bold px-3 shadow-sm">
                                            <i class="fas fa-check-circle me-1"></i> Posting Jurnal
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Semua antrean transaksi pembayaran dan pengiriman penjualan B2B telah selesai dijurnal.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-folder-open me-2"></i>2. Riwayat Buku Jurnal Khusus Penjualan B2B (Sudah Disimpan)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-bold">
                            <tr>
                                <th class="py-3 ps-4" style="width: 15%">Tanggal</th>
                                <th class="py-3" style="width: 20%">No. Ref</th>
                                <th class="py-3" style="width: 35%">Deskripsi</th>
                                <th class="py-3 text-end" style="width: 12%">Total Debit</th>
                                <th class="py-3 text-end" style="width: 12%">Total Kredit</th>
                                <th class="py-3 text-center" style="width: 6%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jurnalsSudah as $j)
                            <tr>
                                <td class="py-3 ps-4 text-secondary">
                                    {{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/Y') }}
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1 small fw-bold">
                                        {{ $j->no_ref }}
                                    </span>
                                </td>

                                <td class="text-muted small">
                                    {{ Str::limit($j->deskripsi, 55, '...') }}
                                </td>

                                <td class="text-end font-monospace text-success fw-semibold">
                                    Rp {{ number_format($j->total_debit, 2, ',', '.') }}
                                </td>

                                <td class="text-end font-monospace text-danger fw-semibold">
                                    Rp {{ number_format($j->total_kredit, 2, ',', '.') }}
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('jurnal-penjualanb2b.show', $j->id) }}" class="btn btn-sm btn-outline-info fw-bold px-2.5 py-1">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Belum ada riwayat transaksi penjualan B2B yang tersimpan di dalam buku jurnal khusus.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($jurnalsSudah->hasPages())
            <div class="card-footer bg-white py-3 d-flex justify-content-center">
                {{ $jurnalsSudah->links() }}
            </div>
            @endif
        </div>
    </div>
</x-app-layout>