<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Modul Jurnal Khusus Pembelian
        </h2>
    </x-slot>

    <div class="container py-4">

        @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        </div>
        @endif

        <div class="card shadow border-0 rounded-3 mb-5">
            <div class="card-header bg-warning text-dark py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold"><i class="fas fa-clock me-2"></i>1. Antrean Invoice Pembelian (Belum Dijurnal)</h5>
                <span class="badge bg-dark text-white">{{ count($pembeliansBelum) }} Invoice</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-bold">
                            <tr>
                                <th class="py-3 ps-4">Tanggal Nota</th>
                                <th class="py-3">No. Invoice (Kode)</th>
                                <th class="py-3">Nama Supplier</th>
                                <th class="py-3">Tahap Berikutnya</th>
                                <th class="py-3 text-end">Total Keluar ke Supplier</th>
                                <th class="py-3 text-center" style="width: 180px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pembeliansBelum as $p)
                            <tr>
                                <td class="py-3 ps-4 text-secondary">{{ \Carbon\Carbon::parse($p->tanggal)->format('d/m/Y') }}</td>
                                <td><span class="badge bg-light text-dark border font-monospace px-2.5 py-1.5 fs-6">{{ $p->label_no_invoice ?? $p->kode_pembelian }}</span></td>
                                <td class="fw-semibold text-dark">{{ $p->supplier->nama ?? 'Supplier Tidak Terdaftar' }}</td>
                                <td>
                                    @php
                                    $labelTahap = [
                                    'dp' => ['DP', 'bg-info text-white'],
                                    'pelunasan' => ['Pelunasan', 'bg-primary text-white'],
                                    'reklas_lunas' => ['Reklas Persediaan', 'bg-purple text-white'], // Disesuaikan ke reklas_lunas
                                    'gabungan' => ['Pelunasan + Terima Barang', 'bg-success text-white'],
                                    'cod' => ['COD (Lunas)', 'bg-dark text-white'],
                                    ];
                                    [$label, $class] = $labelTahap[$p->tahap_selanjutnya] ?? ['-', 'bg-secondary text-white'];
                                    @endphp
                                    <span class="badge {{ $class }} px-2 py-1">{{ $label }}</span>
                                </td>
                                <td class="text-end fw-bold text-dark">Rp {{ number_format($p->total_keluar, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <form action="{{ route('jurnal-pembelian.post-auto', $p->id) }}?tahap={{ $p->tahap_selanjutnya }}" method="POST" class="d-inline" onsubmit="return confirm('Posting jurnal khusus pembelian untuk {{ $p->label_no_invoice ?? $p->kode_pembelian }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success fw-bold px-3 shadow-sm">
                                            <i class="fas fa-check-circle me-1"></i> Posting Jurnal
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-check-double text-success me-2"></i>Semua transaksi pembelian dari gudang sudah selesai dijurnal.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-folder-open me-2"></i>2. Riwayat Buku Jurnal Khusus Pembelian (Sudah Disimpan)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-bold">
                            <tr>
                                <th class="py-3 ps-4" style="width: 13%">Tanggal Jurnal</th>
                                <th class="py-3" style="width: 17%">No. Jurnal (Ref)</th>
                                <th class="py-3" style="width: 13%">Tahap</th>
                                <th class="py-3" style="width: 27%">Keterangan / Deskripsi</th>
                                <th class="py-3 text-end" style="width: 15%">Total Transaksi</th>
                                <th class="py-3 text-center" style="width: 15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jurnalsSudah as $j)
                            <tr>
                                <td class="py-3 ps-4 fw-medium text-secondary">
                                    {{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/Y') }}
                                </td>

                                <td class="font-monospace text-dark small fw-bold">
                                    {{ $j->no_ref }}
                                </td>

                                <td>
                                    @php
                                    $labelTahap = [
                                    'dp' => ['DP', 'bg-info text-white'],
                                    'pelunasan' => ['Pelunasan', 'bg-primary text-white'],
                                    'reklas_lunas' => ['Reklas Persediaan', 'bg-purple text-white'], // Disesuaikan ke reklas_lunas dengan warna badge purple agar konsisten
                                    'gabungan' => ['Pelunasan + Terima Barang', 'bg-success text-white'],
                                    'cod' => ['COD (Lunas)', 'bg-dark text-white'],
                                    ];
                                    [$label, $class] = $labelTahap[$j->tahap] ?? ['-', 'bg-secondary text-white'];
                                    @endphp
                                    <span class="badge {{ $class }} px-2 py-1">{{ $label }}</span>
                                </td>

                                <td class="text-muted small">
                                    {{ $j->deskripsi }}
                                </td>

                                <td class="text-end font-monospace text-success fw-bold">
                                    Rp {{ number_format($j->total_transaksi, 0, ',', '.') }}
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('jurnal-pembelian.show', $j->id) }}" class="btn btn-sm btn-outline-secondary fw-bold px-3">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Belum ada riwayat transaksi pembelian yang tersimpan di dalam buku jurnal khusus.
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