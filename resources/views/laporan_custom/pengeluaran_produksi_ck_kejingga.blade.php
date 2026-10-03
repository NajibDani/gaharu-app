<x-app-layout>
    <x-slot name="header">Pengeluaran Produksi Central Kitchen ke Kejingga</x-slot>

    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4 d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4 d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- FILTER CARD -->
        <div class="card mb-4 shadow-sm border-0 rounded-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('laporan.laporan-custom.produksi-ck-kejingga') }}" class="row g-3 align-items-end">
                    <x-date-range-picker-custom startName="start_date" endName="end_date" />
                    
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold text-muted">Status Pembayaran</label>
                        <select name="status_pembayaran" class="form-select form-select-sm">
                            <option value="semua" {{ ($statusPembayaran ?? 'semua') === 'semua' ? 'selected' : '' }}>Semua Status Pembayaran</option>
                            <option value="belum_dibayar" {{ ($statusPembayaran ?? '') === 'belum_dibayar' ? 'selected' : '' }}>Belum Dibayar</option>
                            <option value="lunas" {{ ($statusPembayaran ?? '') === 'lunas' ? 'selected' : '' }}>Lunas</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn text-white btn-sm px-4 fw-semibold shadow-sm" style="background-color: #2563eb; border: none; height: 38px;">
                            <i class="bi bi-search me-1"></i> Tampilkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $grandTotal = $data->sum(function($row) {
                return $row->details->sum(function($d) {
                    return ($d->hpp_total && $d->hpp_total > 0) ? $d->hpp_total : ($d->total_harga ?? 0);
                });
            });

            $totalBelumDibayar = $data->filter(fn($r) => strtolower($r->status_pembayaran ?? '') !== 'lunas')->sum(function($row) {
                return $row->details->sum(function($d) {
                    return ($d->hpp_total && $d->hpp_total > 0) ? $d->hpp_total : ($d->total_harga ?? 0);
                });
            });

            $totalLunas = $data->filter(fn($r) => strtolower($r->status_pembayaran ?? '') === 'lunas')->sum(function($row) {
                return $row->details->sum(function($d) {
                    return ($d->hpp_total && $d->hpp_total > 0) ? $d->hpp_total : ($d->total_harga ?? 0);
                });
            });
        @endphp

        <!-- RINGKASAN REKAPITULASI -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 bg-white rounded-3 border shadow-sm">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Produksi ({{ $data->count() }} Transaksi)</span>
                    <span class="fs-5 fw-bold text-dark">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded-3 border shadow-sm" style="border-left: 4px solid #2563eb !important;">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Belum Dibayar (Tagihan Kejingga)</span>
                    <span class="fs-5 fw-bold text-primary">Rp {{ number_format($totalBelumDibayar, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded-3 border shadow-sm" style="border-left: 4px solid #16a34a !important;">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Sudah Dibayar (Lunas)</span>
                    <span class="fs-5 fw-bold text-success">Rp {{ number_format($totalLunas, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-0">
                <!-- TOOLBAR BULK ACTIONS -->
                <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-light rounded-top-3 flex-wrap gap-2" style="border-bottom:1px solid #e2e8f0;">
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold" style="color:#2563eb;">
                            <i class="bi bi-box-seam me-1"></i> Data Produksi CK ke Kejingga
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary py-1.5 px-3 rounded-pill" style="font-size:12px;">
                            {{ $data->count() }} Transaksi
                        </span>
                    </div>

                    <!-- BULK ACTION BUTTONS (APPEARS WHEN CHECKED) -->
                    <div class="d-flex align-items-center gap-2" id="bulkActionContainer">
                        <span class="text-muted small me-2" id="selectedInfo">Pilih produksi untuk membayar/cetak invoice</span>

                        <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" id="btnBayarSelected" disabled data-bs-toggle="modal" data-bs-target="#modalBayarBulk">
                            <i class="bi bi-credit-card-fill me-1"></i> Bayar Produksi Terpilih
                        </button>

                        <button type="button" class="btn btn-sm text-white fw-bold px-3 shadow-sm" style="background-color: #2563eb;" id="btnCetakInvoiceSelected" disabled>
                            <i class="bi bi-printer-fill me-1"></i> Cetak Invoice Terpilih
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;" id="tableProduksi">
                        <thead style="background-color: #2563eb; color: white;">
                            <tr>
                                <th style="background-color: #2563eb; color: white; width: 40px;" class="text-center ps-3">
                                    <input type="checkbox" class="form-check-input" id="checkAll" style="cursor: pointer;">
                                </th>
                                <th style="background-color: #2563eb; color: white;">Kode Produksi</th>
                                <th style="background-color: #2563eb; color: white;">Tanggal Mulai</th>
                                <th style="background-color: #2563eb; color: white;">Gudang Bahan</th>
                                <th style="background-color: #2563eb; color: white;">Gudang Hasil / Divisi</th>
                                <th style="background-color: #2563eb; color: white;" class="text-center">Status Produksi</th>
                                <th style="background-color: #2563eb; color: white;" class="text-center">Status Pembayaran</th>
                                <th style="background-color: #2563eb; color: white;" class="text-end">Total Nilai</th>
                                <th style="background-color: #2563eb; color: white;" class="text-center pe-4" style="width: 220px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                @php
                                    $rowTotal = $row->details->sum(function($d) {
                                        return ($d->hpp_total && $d->hpp_total > 0) ? $d->hpp_total : ($d->total_harga ?? 0);
                                    });
                                    $isLunas = strtolower($row->status_pembayaran ?? '') === 'lunas';
                                @endphp
                                <tr>
                                    <td class="text-center ps-3">
                                        <input type="checkbox" class="form-check-input check-item" value="{{ $row->id }}" data-total="{{ $rowTotal }}" data-lunas="{{ $isLunas ? '1' : '0' }}" style="cursor: pointer;">
                                    </td>
                                    <td class="fw-semibold" style="color:#2563eb;">{{ $row->kode_produksi ?? '-' }}</td>
                                    <td>{{ $row->tanggal_mulai ? \Carbon\Carbon::parse($row->tanggal_mulai)->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $row->gudangBahan->nama ?? 'Central Kitchen' }}</td>
                                    <td>{{ $row->gudangHasil->nama ?? 'Gudang KeJingga' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-subtle text-dark border px-2 py-1" style="font-size: 11px;">
                                            {{ $row->status_produksi ?? 'Selesai' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($isLunas)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold px-2 py-1 rounded-pill" style="font-size:11px;">
                                                <i class="bi bi-check-circle-fill me-1"></i> LUNAS
                                            </span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold px-2 py-1 rounded-pill" style="font-size:11px;">
                                                <i class="bi bi-clock-history me-1"></i> BELUM DIBAYAR
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold text-dark">Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-1">
                                            @if(!$isLunas)
                                                <button type="button" class="btn btn-sm btn-outline-success btn-single-bayar px-2 py-1" style="font-size: 12px;" data-id="{{ $row->id }}" data-kode="{{ $row->kode_produksi }}" data-total="{{ number_format($rowTotal, 0, ',', '.') }}">
                                                    <i class="bi bi-credit-card me-1"></i> Bayar
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1 disabled" style="font-size: 12px;">
                                                    <i class="bi bi-check2-all me-1"></i> Lunas
                                                </button>
                                            @endif

                                            <a href="{{ route('laporan.laporan-custom.produksi-ck-kejingga.cetak-invoice', ['ids' => $row->id]) }}" class="btn btn-sm text-white px-2 py-1" style="background-color:#2563eb; font-size: 12px;" target="_blank">
                                                <i class="bi bi-file-earmark-text me-1"></i> Invoice
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">Tidak ada data produksi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot style="background-color: #f8f9fa;">
                            <tr>
                                <th colspan="7" class="text-end px-4 py-3 fw-bold" style="color: #2563eb;">Grand Total</th>
                                <th class="text-end fw-bold text-dark fs-6 py-3">Rp {{ number_format($grandTotal, 0, ',', '.') }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PEMBAYARAN BULK & SINGLE -->
    <div class="modal fade" id="modalBayarBulk" tabindex="-1" aria-labelledby="modalBayarBulkLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background-color: #2563eb;">
                    <h5 class="modal-title fw-bold" id="modalBayarBulkLabel">
                        <i class="bi bi-cash-stack me-2"></i> Proses Pembayaran Produksi CK
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('laporan.laporan-custom.produksi-ck-kejingga.bayar') }}" method="POST" id="formBayarBulk">
                    @csrf
                    <div id="selectedIdsContainer"></div>

                    <div class="modal-body p-4">
                        <div class="alert alert-primary border-0 shadow-sm d-flex align-items-center mb-4">
                            <i class="bi bi-info-circle-fill me-3 fs-4 text-primary"></i>
                            <div>
                                <div class="fw-bold" id="summaryModalTitle">Anda akan memproses pembayaran produksi terpilih.</div>
                                <div class="small text-muted" id="summaryModalDetail">Total tagihan: Rp 0</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pembayaran" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="metode_pembayaran" class="form-select form-select-sm" required>
                                <option value="Transfer Bank" selected>Transfer Bank (Rekening Utama)</option>
                                <option value="Kas / Tunai">Kas / Tunai</option>
                                <option value="Giro / Cek">Giro / Cek</option>
                                <option value="Sistem Antar Gudang">Sistem Antar Gudang (Internal)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Catatan Pembayaran (Opsional)</label>
                            <textarea name="catatan_pembayaran" class="form-control form-control-sm" rows="3" placeholder="Contoh: Pembayaran produksi via Transfer BCA Rekening Gudang..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkAll = document.getElementById('checkAll');
            const checkItems = document.querySelectorAll('.check-item');
            const btnBayarSelected = document.getElementById('btnBayarSelected');
            const btnCetakInvoiceSelected = document.getElementById('btnCetakInvoiceSelected');
            const selectedInfo = document.getElementById('selectedInfo');
            const selectedIdsContainer = document.getElementById('selectedIdsContainer');
            const summaryModalTitle = document.getElementById('summaryModalTitle');
            const summaryModalDetail = document.getElementById('summaryModalDetail');

            function updateSelection() {
                const checked = Array.from(checkItems).filter(item => item.checked);
                const count = checked.length;

                let totalNilai = 0;
                checked.forEach(item => {
                    totalNilai += parseFloat(item.dataset.total || 0);
                });

                if (count > 0) {
                    btnBayarSelected.disabled = false;
                    btnCetakInvoiceSelected.disabled = false;
                    selectedInfo.innerHTML = `Terpilih <strong>${count}</strong> transaksi (Total: <strong>Rp ${totalNilai.toLocaleString('id-ID')}</strong>)`;
                } else {
                    btnBayarSelected.disabled = true;
                    btnCetakInvoiceSelected.disabled = true;
                    selectedInfo.innerHTML = 'Pilih produksi untuk membayar/cetak invoice';
                }

                if (checkAll) {
                    checkAll.checked = count > 0 && count === checkItems.length;
                }
            }

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    checkItems.forEach(item => {
                        item.checked = this.checked;
                    });
                    updateSelection();
                });
            }

            checkItems.forEach(item => {
                item.addEventListener('change', updateSelection);
            });

            // CETAK INVOICE MASSAL
            btnCetakInvoiceSelected.addEventListener('click', function () {
                const checked = Array.from(checkItems).filter(item => item.checked);
                const ids = checked.map(item => item.value);

                if (ids.length === 0) {
                    alert('Pilih minimal 1 produksi untuk mencetak invoice.');
                    return;
                }

                const url = "{{ route('laporan.laporan-custom.produksi-ck-kejingga.cetak-invoice') }}?ids=" + ids.join(',');
                window.open(url, '_blank');
            });

            // PREPARE BULK BAYAR MODAL
            btnBayarSelected.addEventListener('click', function () {
                const checked = Array.from(checkItems).filter(item => item.checked);
                const ids = checked.map(item => item.value);

                let totalNilai = 0;
                checked.forEach(item => {
                    totalNilai += parseFloat(item.dataset.total || 0);
                });

                selectedIdsContainer.innerHTML = '';
                ids.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    selectedIdsContainer.appendChild(input);
                });

                summaryModalTitle.textContent = `Anda akan memproses pembayaran untuk ${ids.length} transaksi produksi.`;
                summaryModalDetail.textContent = `Total Nilai Tagihan: Rp ${totalNilai.toLocaleString('id-ID')}`;
            });

            // PREPARE SINGLE BAYAR BUTTON
            document.querySelectorAll('.btn-single-bayar').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.dataset.id;
                    const kode = this.dataset.kode;
                    const total = this.dataset.total;

                    selectedIdsContainer.innerHTML = '';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    selectedIdsContainer.appendChild(input);

                    summaryModalTitle.textContent = `Proses Pembayaran Produksi ${kode}`;
                    summaryModalDetail.textContent = `Total Nilai Tagihan: Rp ${total}`;

                    const modal = new bootstrap.Modal(document.getElementById('modalBayarBulk'));
                    modal.show();
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
