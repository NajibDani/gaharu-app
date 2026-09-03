<x-app-layout>
    <x-slot name="header">
        Detail Persediaan Awal
    </x-slot>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('persediaan-awal.index') }}" class="btn btn-sm btn-outline-secondary rounded-2 px-2">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            {{ $persediaanAwal->kode_transaksi }}
                            <span class="badge bg-success-subtle text-success fs-6 ms-2">Tercatat di Stok & FIFO</span>
                        </h5>
                        <small class="text-muted">Transaksi input saldo awal persediaan master barang</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-2 px-3" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Cetak / Print
                    </button>
                    @if(auth()->user() && auth()->user()->isSuperAdmin())
                        <a href="{{ route('persediaan-awal.edit', $persediaanAwal->id) }}" class="btn btn-sm btn-warning text-white rounded-2 px-3">
                            <i class="bi bi-pencil-square me-1"></i> Edit Transaksi
                        </a>
                    @endif
                    <a href="{{ route('persediaan-awal.create') }}" class="btn btn-sm text-white rounded-2 px-3" style="background-color: #d88656; border: none;">
                        <i class="bi bi-plus-lg me-1"></i> Input Baru
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4 d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Info Header -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Gudang / Lokasi</span>
                        <span class="fs-6 fw-bold text-dark d-block">{{ $persediaanAwal->gudang->nama ?? '-' }}</span>
                        @if($persediaanAwal->divisi)
                            <span class="badge bg-primary-subtle text-primary mt-1">Divisi: {{ $persediaanAwal->divisi->nama }}</span>
                        @else
                            <span class="text-muted small">Tanpa Divisi (Umum)</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Tanggal Saldo Awal</span>
                        <span class="fs-6 fw-bold text-dark d-block">{{ $persediaanAwal->tanggal->format('d F Y') }}</span>
                        <span class="text-muted small">Dicatat oleh: {{ $persediaanAwal->user->nama_karyawan ?? $persediaanAwal->user->name ?? '-' }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Item & Kuantitas</span>
                        <span class="fs-6 fw-bold text-dark d-block">{{ number_format($persediaanAwal->total_item) }} Barang</span>
                        <span class="text-muted small">Total Qty: <strong>{{ number_format($persediaanAwal->total_qty, 2, ',', '.') }}</strong></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Nilai Persediaan Awal</span>
                        <span class="fs-5 fw-bold text-success d-block">Rp {{ number_format($persediaanAwal->total_nilai, 0, ',', '.') }}</span>
                        <span class="text-muted small">{{ $persediaanAwal->keterangan ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- TABEL DAFTAR BARANG -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-boxes me-1 text-primary"></i> Rincian Barang & Batch FIFO Terbentuk
                </h6>
                <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill">
                    {{ $persediaanAwal->details->count() }} item barang
                </span>
            </div>

            <div class="table-responsive border rounded-3 mb-4">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45px;">No</th>
                            <th class="text-start">Kode Barang</th>
                            <th class="text-start">Nama Barang</th>
                            <th>Kategori</th>
                            <th>Input Pembelian</th>
                            <th>Satuan & Konversi</th>
                            <th class="text-center">Qty Masuk Stok</th>
                            <th class="text-end">HPP Satuan Stok (Rp)</th>
                            <th class="text-end">Total Nilai (Rp)</th>
                            <th class="text-start">Nomor Batch FIFO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($persediaanAwal->details as $index => $detail)
                            @php
                                $hasKonv = $detail->satuan_pembelian && (float)$detail->konversi_pembelian > 1 && $detail->satuan_pembelian !== $detail->satuan;
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $index + 1 }}</td>
                                <td class="text-start font-monospace fw-bold">
                                    {{ $detail->barang->kode_barang ?? '-' }}
                                </td>
                                <td class="text-start">
                                    <span class="fw-semibold text-dark">{{ $detail->barang->nama ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $detail->barang->kategori->nama ?? '-' }}</span>
                                </td>
                                <td>
                                    @if($hasKonv && $detail->qty_pembelian)
                                        <span class="fw-bold text-dark">{{ number_format($detail->qty_pembelian, 2, ',', '.') }}</span> {{ $detail->satuan_pembelian }}
                                        <div class="text-muted small" style="font-size: 11px;">@ Rp {{ number_format($detail->harga_pembelian, 0, ',', '.') }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($hasKonv)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            1 {{ $detail->satuan_pembelian }} = {{ number_format($detail->konversi_pembelian, 0, ',', '.') }} {{ $detail->satuan }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark border">{{ $detail->satuan }}</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-primary">
                                    {{ number_format($detail->qty, 2, ',', '.') }} <span class="small fw-normal text-muted">{{ $detail->satuan }}</span>
                                </td>
                                <td class="text-end">
                                    Rp {{ number_format($detail->harga_satuan, 2, ',', '.') }} <span class="small text-muted">/ {{ $detail->satuan }}</span>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rp {{ number_format($detail->total_nilai, 0, ',', '.') }}
                                </td>
                                <td class="text-start font-monospace small">
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        {{ $detail->batch_number }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">Tidak ada rincian item barang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Total Masuk Stok:</td>
                            <td class="text-center text-primary">{{ number_format($persediaanAwal->total_qty, 2, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end text-success">Rp {{ number_format($persediaanAwal->total_nilai, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- JURNAL PENYESUAIAN SECTION -->
            @if($jurnal)
                <div class="card border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-journal-text me-1 text-success"></i> Jurnal Penyesuaian Terkait (Otomatis)
                            </h6>
                            <small class="text-muted">No. Ref: <strong>{{ $jurnal->no_ref }}</strong> | Tanggal: {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}</small>
                        </div>
                        <span class="badge bg-success px-3 py-2">Approved</span>
                    </div>

                    <div class="table-responsive bg-white rounded-2 border">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light text-center small">
                                <tr>
                                    <th class="text-start">Kode Akun</th>
                                    <th class="text-start">Nama Akun</th>
                                    <th class="text-end" style="width: 180px;">Debit (Rp)</th>
                                    <th class="text-end" style="width: 180px;">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                @php $totalDebit = 0; $totalKredit = 0; @endphp
                                @foreach($jurnal->details as $jd)
                                    @php
                                        $totalDebit += (float) $jd->debit;
                                        $totalKredit += (float) $jd->kredit;
                                    @endphp
                                    <tr>
                                        <td class="font-monospace fw-bold">{{ $jd->account->kode ?? '-' }}</td>
                                        <td>
                                            @if($jd->kredit > 0)
                                                <span class="ms-4 text-secondary">{{ $jd->account->nama ?? '-' }}</span>
                                            @else
                                                <span class="fw-semibold text-dark">{{ $jd->account->nama ?? '-' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ $jd->debit > 0 ? number_format($jd->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ $jd->kredit > 0 ? number_format($jd->kredit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold small">
                                <tr>
                                    <td colspan="2" class="text-end">Total Jurnal:</td>
                                    <td class="text-end text-success">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td>
                                    <td class="text-end text-success">Rp {{ number_format($totalKredit, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
