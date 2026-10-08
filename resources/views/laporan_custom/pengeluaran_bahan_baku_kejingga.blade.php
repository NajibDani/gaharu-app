<x-app-layout>
    <x-slot name="header">Pengeluaran Bahan Baku Gudang Utama ke Kejingga</x-slot>

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
                <form method="GET" action="{{ route('laporan.laporan-custom.pbk-kejingga') }}" class="row g-3 align-items-end">
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
                        <button type="submit" class="btn text-white btn-sm px-4 fw-semibold shadow-sm" style="background-color: #d88656; border: none; height: 38px;">
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
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Pengeluaran ({{ $data->count() }} Transaksi)</span>
                    <span class="fs-5 fw-bold text-dark">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded-3 border shadow-sm" style="border-left: 4px solid #fd7e14 !important;">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Belum Dibayar (Tagihan Kejingga)</span>
                    <span class="fs-5 fw-bold text-warning">Rp {{ number_format($totalBelumDibayar, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded-3 border shadow-sm" style="border-left: 4px solid #198754 !important;">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Sudah Dibayar (Lunas)</span>
                    <span class="fs-5 fw-bold text-success">Rp {{ number_format($totalLunas, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-0">
                <!-- TOOLBAR BULK ACTIONS -->
                <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-light rounded-top-3 flex-wrap gap-2" style="border-bottom:1px solid #eadfd4;">
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold" style="color:#d88656;">
                            <i class="bi bi-journal-text me-1"></i> Data Pengeluaran Bahan Baku
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary py-1.5 px-3 rounded-pill" style="font-size:12px;">
                            {{ $data->count() }} Transaksi
                        </span>
                    </div>

                    <!-- BULK ACTION BUTTONS (APPEARS WHEN CHECKED) -->
                    <div class="d-flex align-items-center gap-2" id="bulkActionContainer">
                        <span class="text-muted small me-2" id="selectedInfo">Pilih transaksi untuk membayar/cetak invoice</span>

                        <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" id="btnBayarSelected" disabled data-bs-toggle="modal" data-bs-target="#modalBayarBulk">
                            <i class="bi bi-credit-card-fill me-1"></i> Bayar Transaksi Terpilih
                        </button>

                        <button type="button" class="btn btn-sm text-white fw-bold px-3 shadow-sm" style="background-color: #d88656;" id="btnCetakInvoiceSelected" disabled>
                            <i class="bi bi-printer-fill me-1"></i> Cetak Invoice Terpilih
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;" id="tablePbk">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white; width: 40px;" class="text-center ps-3">
                                    <input type="checkbox" class="form-check-input" id="checkAll" style="cursor: pointer;">
                                </th>
                                <th style="background-color: #d88656; color: white;">Kode Transaksi</th>
                                <th style="background-color: #d88656; color: white;">Tanggal</th>
                                <th style="background-color: #d88656; color: white;">Gudang Asal</th>
                                <th style="background-color: #d88656; color: white;">Tujuan (Gudang/Divisi)</th>
                                <th style="background-color: #d88656; color: white;">Keterangan</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Status Transaksi</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Status Pembayaran</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Total Nilai</th>
                                <th style="background-color: #d88656; color: white;" class="text-center pe-4" style="width: 220px;">Aksi</th>
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
                                <tr data-id="{{ $row->id }}" data-total="{{ $rowTotal }}" data-lunas="{{ $isLunas ? '1' : '0' }}">
                                    <td class="text-center ps-3">
                                        <input type="checkbox" class="form-check-input cb-item" value="{{ $row->id }}" data-total="{{ $rowTotal }}" data-lunas="{{ $isLunas ? '1' : '0' }}" {{ $isLunas ? 'disabled' : '' }} style="cursor: {{ $isLunas ? 'not-allowed' : 'pointer' }};">
                                    </td>
                                    <td class="fw-semibold font-monospace" style="color:#d88656;">
                                        @if($isLunas)
                                            <span class="badge bg-secondary-subtle text-muted border text-decoration-line-through me-1 opacity-75" title="Nomor PBK ini disabled karena sudah dibayar (Lunas)" style="cursor: not-allowed; font-size: 0.85rem;">
                                                <i class="bi bi-lock-fill me-1 text-secondary"></i>{{ $row->kode_pengeluaran ?? '-' }}
                                            </span>
                                            <span class="badge bg-secondary text-white" style="font-size: 9px; vertical-align: middle;">DISABLED</span>
                                        @else
                                            {{ $row->kode_pengeluaran ?? '-' }}
                                        @endif
                                        @if($row->no_invoice)
                                            <div class="text-muted small" style="font-size: 10px;">Inv: {{ $row->no_invoice }}</div>
                                        @endif
                                    </td>
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
                                    <td class="text-center">
                                        @if($isLunas)
                                            <span class="badge bg-success py-1.5 px-2.5 rounded-pill shadow-xs">
                                                <i class="bi bi-check-circle-fill me-1"></i> Lunas
                                            </span>
                                            <div class="text-muted" style="font-size: 10px;">{{ $row->tanggal_pembayaran ? $row->tanggal_pembayaran->format('d/m/Y') : '' }} ({{ $row->metode_pembayaran ?? 'Bank' }})</div>
                                        @else
                                            <span class="badge bg-warning text-dark py-1.5 px-2.5 rounded-pill border border-warning-subtle fw-bold">
                                                <i class="bi bi-clock-history me-1"></i> Belum Dibayar
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold">Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
                                    <td class="text-center pe-4">
                                        @php
                                            $authUser = auth()->user();
                                            $isSuperAdminUser = $authUser && ($authUser->isSuperAdmin() || $authUser->username === 'superadmin');
                                        @endphp
                                        <div class="d-flex justify-content-center gap-1">
                                            <!-- DETAIL BUTTON -->
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 text-dark"
                                                    data-bs-toggle="modal" data-bs-target="#modalDetail{{ $row->id }}" title="Lihat Rincian Barang">
                                                <i class="bi bi-eye"></i> Detail
                                            </button>

                                            <!-- BAYAR SINGLE BUTTON -->
                                            @if(!$isLunas)
                                                <button type="button" class="btn btn-sm btn-success py-1 px-2 fw-semibold btn-pay-single"
                                                        data-id="{{ $row->id }}" data-kode="{{ $row->kode_pengeluaran }}" data-total="{{ $rowTotal }}" title="Bayar Transaksi Ini">
                                                    <i class="bi bi-credit-card me-1"></i> Bayar
                                                </button>
                                            @endif

                                            <!-- CETAK INVOICE BUTTON -->
                                            <a href="{{ route('laporan.laporan-custom.pbk-kejingga.cetak-invoice', ['ids' => $row->id]) }}" target="_blank" class="btn btn-sm btn-outline-warning text-dark py-1 px-2 fw-semibold" title="Cetak Invoice">
                                                <i class="bi bi-printer me-1"></i> Invoice
                                            </a>

                                            <!-- SUPERADMIN EDIT & HAPUS AKSI -->
                                            @if($isSuperAdminUser)
                                                @if($isLunas)
                                                    <a href="{{ route('pengeluaran-bahan-baku.edit', $row->id) }}"
                                                       class="btn btn-sm btn-warning py-1 px-2 text-dark"
                                                       title="Edit Nota (Super Admin)"
                                                       onclick="return confirm('PERINGATAN SUPER ADMIN!\n\nNota PBK {{ $row->kode_pengeluaran }} ini SUDAH DIBAYAR (LUNAS). Barang yang sudah dibayar harganya tidak boleh berubah lagi.\n\nApakah Anda yakin ingin melanjutkan edit nota yang sudah dibayar ini?')">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <form action="{{ route('pengeluaran-bahan-baku.destroy', $row->id) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('PERINGATAN SANGAT PENTING SUPER ADMIN!\n\nNota PBK {{ $row->kode_pengeluaran }} ini SUDAH DIBAYAR (LUNAS).\n\nMenghapus nota yang sudah dibayar ini akan mempengaruhi laporan dan pencatatan keuangan!\n\nApakah Anda BENAR-BENAR YAKIN ingin menghapus nota yang sudah dibayar ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger py-1 px-2 text-white" title="Hapus Nota Lunas (Super Admin)">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <a href="{{ route('pengeluaran-bahan-baku.edit', $row->id) }}" class="btn btn-sm btn-warning py-1 px-2 text-dark" title="Edit Nota">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form action="{{ route('pengeluaran-bahan-baku.destroy', $row->id) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('Yakin ingin menghapus nota pengeluaran {{ $row->kode_pengeluaran }} ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger py-1 px-2 text-white" title="Hapus Nota">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>

                                        {{-- MODAL DETAIL --}}
                                        <div class="modal fade text-start" id="modalDetail{{ $row->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header text-white" style="background-color: #d88656;">
                                                        <h5 class="modal-title">Rincian Pengeluaran #{{ $row->kode_pengeluaran }}</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-0">
                                                        <div class="bg-light px-4 py-3 border-bottom text-muted" style="font-size:13px;">
                                                            <strong>Tanggal:</strong> {{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d M Y') : '-' }} &nbsp;|&nbsp;
                                                            <strong>Tujuan:</strong> {{ $row->gudang->nama ?? 'Kejingga' }} {{ $row->divisi ? '('.$row->divisi->nama.')' : '' }} &nbsp;|&nbsp;
                                                            <strong>Status Op:</strong> 
                                                            <span class="badge {{ ($row->status === 'approved' || $row->status === 'disetujui') ? 'bg-success' : 'bg-secondary' }}">
                                                                {{ ucfirst($row->status ?? 'Draft') }}
                                                            </span> &nbsp;|&nbsp;
                                                            <strong>Pembayaran:</strong> 
                                                            <span class="badge {{ $isLunas ? 'bg-success' : 'bg-warning text-dark' }}">
                                                                {{ $isLunas ? 'Lunas' : 'Belum Dibayar' }}
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
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        Tidak ada data pengeluaran bahan baku.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot style="background-color: #f8f9fa;">
                            <tr>
                                <th colspan="8" class="text-end px-4 py-3 fw-bold" style="color: #d88656;">Grand Total Keseluruhan</th>
                                <th class="text-end fw-bold">Rp {{ number_format($grandTotal, 0, ',', '.') }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PEMBAYARAN BULK / SINGLE -->
    <div class="modal fade" id="modalBayarBulk" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('laporan.laporan-custom.pbk-kejingga.bayar') }}" method="POST" id="formBayarBulk">
                    @csrf
                    <div class="modal-header text-white" style="background-color: #198754;">
                        <h5 class="modal-title fw-bold"><i class="bi bi-credit-card-fill me-2"></i>Konfirmasi Pembayaran Transaksi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="containerModalBayarIds"></div>

                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Transaksi Terpilih:</span>
                                <span class="fw-bold text-dark" id="modalCountSelected">0 Transaksi</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold">Total Pembayaran:</span>
                                <span class="fs-5 fw-bold text-success" id="modalTotalAmount">Rp 0</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pembayaran" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="metode_pembayaran" class="form-select" required>
                                <option value="Transfer Bank BCA">Transfer Bank BCA</option>
                                <option value="Transfer Bank Mandiri">Transfer Bank Mandiri</option>
                                <option value="Kas / Tunai">Kas / Tunai</option>
                                <option value="Saldo Antar Gudang">Saldo Antar Gudang (Internal Settlement)</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Catatan Pembayaran / Keterangan</label>
                            <textarea name="catatan_pembayaran" class="form-control" rows="2" placeholder="Catatan pembayaran (opsional)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Diproses & Tandai Lunas
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
        const cbItems = document.querySelectorAll('.cb-item');
        const btnBayarSelected = document.getElementById('btnBayarSelected');
        const btnCetakInvoiceSelected = document.getElementById('btnCetakInvoiceSelected');
        const selectedInfo = document.getElementById('selectedInfo');
        const containerModalBayarIds = document.getElementById('containerModalBayarIds');
        const modalCountSelected = document.getElementById('modalCountSelected');
        const modalTotalAmount = document.getElementById('modalTotalAmount');

        function updateSelectionState() {
            let selectedIds = [];
            let totalAmount = 0;

            cbItems.forEach(cb => {
                if (cb.checked) {
                    selectedIds.push(cb.value);
                    totalAmount += parseFloat(cb.getAttribute('data-total')) || 0;
                }
            });

            const count = selectedIds.length;

            if (count > 0) {
                selectedInfo.innerHTML = `<strong class="text-dark">${count} transaksi dipilih</strong> (Total: <strong class="text-success">Rp ${totalAmount.toLocaleString('id-ID')}</strong>)`;
                btnBayarSelected.disabled = false;
                btnCetakInvoiceSelected.disabled = false;
            } else {
                selectedInfo.textContent = 'Pilih transaksi untuk membayar/cetak invoice';
                btnBayarSelected.disabled = true;
                btnCetakInvoiceSelected.disabled = true;
            }

            // Populate Modal Inputs
            containerModalBayarIds.innerHTML = '';
            selectedIds.forEach(id => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'ids[]';
                hiddenInput.value = id;
                containerModalBayarIds.appendChild(hiddenInput);
            });

            modalCountSelected.textContent = count + ' Transaksi';
            modalTotalAmount.textContent = 'Rp ' + totalAmount.toLocaleString('id-ID');
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                cbItems.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = this.checked;
                    }
                });
                updateSelectionState();
            });
        }

        cbItems.forEach(cb => {
            cb.addEventListener('change', function () {
                if (!this.checked && checkAll) {
                    checkAll.checked = false;
                }
                updateSelectionState();
            });
        });

        // Single Pay Button Handler
        document.querySelectorAll('.btn-pay-single').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const total = parseFloat(this.getAttribute('data-total')) || 0;

                // Check only this item
                cbItems.forEach(cb => {
                    cb.checked = (cb.value === id);
                });
                if (checkAll) checkAll.checked = false;

                updateSelectionState();

                // Open modal
                const modalElement = document.getElementById('modalBayarBulk');
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            });
        });

        // Cetak Invoice Selected Handler
        if (btnCetakInvoiceSelected) {
            btnCetakInvoiceSelected.addEventListener('click', function () {
                let selectedIds = [];
                cbItems.forEach(cb => {
                    if (cb.checked) {
                        selectedIds.push(cb.value);
                    }
                });

                if (selectedIds.length === 0) {
                    alert('Pilih minimal 1 transaksi untuk mencetak invoice.');
                    return;
                }

                const url = "{{ route('laporan.laporan-custom.pbk-kejingga.cetak-invoice') }}?ids=" + selectedIds.join(',');
                window.open(url, '_blank');
            });
        }
    });
    </script>
    @endpush

    <style>
        .btn-close-white {
            filter: invert(1) grayscale(1) brightness(2);
        }
    </style>
</x-app-layout>
