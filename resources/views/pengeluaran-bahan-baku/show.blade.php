
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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                Detail Permintaan / Transfer Bahan Baku
            </h1>
            <p class="text-muted mb-0">
                Informasi ketersediaan stok di Gudang Utama, jumlah diminta, kekurangan, dan kalkulasi FIFO.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('pengeluaran-bahan-baku.cetak-pdf', $pengeluaran->id) }}" target="_blank" class="btn btn-danger btn-sm fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i> Save PDF
            </a>
            <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm" onclick="downloadPageAsImage()">
                <i class="bi bi-image me-1"></i> Save Image (PNG)
            </button>
            <button type="button" class="btn btn-outline-dark btn-sm shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    </div>
</div>

<div id="printableContentArea">
    {{-- INFO CARDS --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 h-100 shadow-sm border">
                <small class="text-muted">Kode Pengeluaran / Transfer</small>
                <h5 class="fw-bold mb-0 text-dark font-monospace mt-1">
                    {{ $pengeluaran->kode_pengeluaran }}
                </h5>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 h-100 shadow-sm border">
                <small class="text-muted">Gudang Sumber (Penyedia)</small>
                <h6 class="fw-bold mb-0 text-primary mt-1">
                    <i class="bi bi-building me-1"></i>{{ $gudangUtama->nama ?? 'Gudang Utama' }}
                </h6>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 h-100 shadow-sm border">
                <small class="text-muted">Gudang & Divisi Tujuan</small>
                <h6 class="fw-bold mb-0 mt-1">
                    {{ $pengeluaran->gudang->nama ?? '-' }}
                    @if($pengeluaran->divisi)
                        <span class="badge bg-light text-primary border border-primary-subtle d-inline-block mt-1">
                            <i class="bi bi-diagram-3 me-1"></i>{{ $pengeluaran->divisi->nama }}
                        </span>
                    @endif
                </h6>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 h-100 shadow-sm border d-flex flex-column justify-content-between">
                <small class="text-muted">Tanggal & Status</small>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="small fw-semibold">{{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d M Y H:i') }}</span>
                    @if(in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']))
                        <span class="badge bg-success px-2 py-1">Approved</span>
                    @else
                        <span class="badge bg-warning text-dark px-2 py-1">Draft</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!empty($pengeluaran->keterangan) && $pengeluaran->keterangan !== '-')
        <div class="card shadow-sm border mb-4">
            <div class="card-body py-2 px-3">
                <small class="text-muted fw-bold d-block">Keterangan / Catatan:</small>
                <span class="text-dark small">{{ $pengeluaran->keterangan }}</span>
            </div>
        </div>
    @endif

    {{-- TABEL DETAIL BARANG --}}
    <div class="card shadow-sm border">
        <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center py-3" style="background:#7A4517;">
            <div>
                <i class="bi bi-box-seam me-2"></i>Rincian Bahan Baku & Ketersediaan Stok Gudang Utama
            </div>
            <span class="badge bg-light text-dark">{{ $pengeluaran->details->count() }} Item</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th width="45" class="text-center">No</th>
                            <th>Kode & Nama Bahan</th>
                            <th width="140" class="text-end">Jumlah Diminta</th>
                            <th width="150" class="text-end">Stok Gudang Utama</th>
                            <th width="140" class="text-end">Kekurangan</th>
                            <th width="140" class="text-center">Ketersediaan</th>
                            <th width="140" class="text-end">Harga Satuan</th>
                            <th width="160" class="text-end">Total HPP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php 
                            $totalDiminta = 0;
                            $totalKurang = 0;
                        @endphp
                        @forelse($pengeluaran->details as $detail)
                            @php
                                $qtyDiminta = (float) $detail->qty;
                                $stokUtama  = (float) ($detail->stok_gudang_utama ?? 0);
                                $kurang     = (float) ($detail->kekurangan ?? max(0, $qtyDiminta - $stokUtama));
                                $satuan     = $detail->barang->satuan ?? ($detail->satuan ?? 'pcs');

                                $hargaFIFO = $detail->qty > 0 ? $detail->hpp_total / $detail->qty : 0;
                                $grandTotal += $detail->hpp_total;
                                $totalDiminta += $qtyDiminta;
                                $totalKurang += $kurang;

                                if ($stokUtama >= $qtyDiminta) {
                                    $statusPill = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Tersedia Penuh</span>';
                                } elseif ($stokUtama > 0) {
                                    $statusPill = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Kurang Sebagian</span>';
                                } else {
                                    $statusPill = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Stok Habis (0)</span>';
                                }
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $detail->barang->nama ?? '-' }}</div>
                                    <small class="text-muted font-monospace">{{ $detail->barang->kode_barang ?? '-' }}</small>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    {{ number_format($qtyDiminta, 2, ',', '.') }} <span class="text-muted fw-normal small">{{ $satuan }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold {{ $stokUtama > 0 ? 'text-primary' : 'text-danger' }}">
                                        {{ number_format($stokUtama, 2, ',', '.') }}
                                    </span>
                                    <span class="text-muted fw-normal small">{{ $satuan }}</span>
                                </td>
                                <td class="text-end">
                                    @if($kurang > 0)
                                        <span class="text-danger fw-bold">-{{ number_format($kurang, 2, ',', '.') }} <small class="fw-normal">{{ $satuan }}</small></span>
                                    @else
                                        <span class="text-success fw-semibold"><i class="bi bi-check2"></i> 0,00</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    {!! $statusPill !!}
                                </td>
                                <td class="text-end text-muted small">
                                    Rp {{ number_format($hargaFIFO, 2, ',', '.') }}
                                    @if($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui')
                                        <small class="text-muted d-block" style="font-size: 9px;">(Estimasi)</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp {{ number_format($detail->hpp_total, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Tidak ada detail bahan baku.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light border-top fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">Total Diminta:</td>
                            <td class="text-end text-dark">{{ number_format($totalDiminta, 2, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end {{ $totalKurang > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $totalKurang > 0 ? '-' . number_format($totalKurang, 2, ',', '.') : '0,00' }}
                            </td>
                            <td colspan="2" class="text-end">Total Nilai HPP @if($pengeluaran->status !== 'approved' && $pengeluaran->status !== 'disetujui') (Estimasi) @endif:</td>
                            <td class="text-end fs-6" style="color:#7A4517;">
                                Rp {{ number_format($grandTotal, 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@if($pengeluaran->status == 'draft')
    @php
        $isWO = str_contains(strtolower($pengeluaran->keterangan ?? ''), 'permintaan bahan baku untuk');
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
        <div class="d-flex gap-2">
            <a href="{{ route('pengeluaran-bahan-baku.cetak-pdf', $pengeluaran->id) }}" target="_blank" class="btn btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i> Save PDF
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="downloadPageAsImage()">
                <i class="bi bi-image me-1"></i> Save Image (PNG)
            </button>
        </div>
        <div class="d-flex gap-2">
            @if(!$isWO)
                <a href="{{ route('pengeluaran-bahan-baku.edit', $pengeluaran->id) }}" class="btn btn-warning fw-semibold">
                    <i class="bi bi-pencil-square me-1"></i> Edit Pengeluaran
                </a>
            @endif
            <a href="{{ route('pengeluaran-bahan-baku.approve', $pengeluaran->id) }}" class="btn btn-success fw-semibold" onclick="return confirm('Approve pengeluaran ini dan potong stok gudang utama?')">
                <i class="bi bi-check-circle me-1"></i> Approve Pengeluaran
            </a>
        </div>
    </div>
@endif

{{-- HTML2CANVAS SCRIPT FOR SAVE IMAGE --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function downloadPageAsImage() {
    let now = new Date();
    let timestamp = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    let container = document.createElement('div');
    container.style.position = 'fixed';
    container.style.left = '-9999px';
    container.style.top = '0';
    container.style.width = '1000px';
    container.style.backgroundColor = '#ffffff';
    container.style.fontFamily = "'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    container.style.color = '#1e293b';
    container.style.padding = '35px 40px';
    container.style.boxSizing = 'border-box';
    container.style.zIndex = '999999';

    container.innerHTML = `
        <!-- HEADER KOP DOKUMEN -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #7A4517; padding-bottom:14px; margin-bottom:18px;">
            <div>
                <div style="font-size:20px; font-weight:800; color:#7A4517; letter-spacing:0.5px;">CV GAHARU AGUNG SEJAHTERA</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Surat Permintaan &amp; Transfer Bahan Baku - Distribusi Antar Gudang</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase;">Bukti Permintaan Bahan</div>
                <div style="font-size:11px; color:#64748b; margin-top:3px;">No. Dokumen: <strong style="font-family:monospace; color:#7A4517; font-size:12px;">{{ $pengeluaran->kode_pengeluaran }}</strong></div>
            </div>
        </div>

        <!-- INFO DETAIL GRID -->
        <table style="width:100%; border-collapse:collapse; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:18px; font-size:11.5px;">
            <tr>
                <td style="padding:10px 14px; width:50%; vertical-align:top; border-right:1px solid #e2e8f0;">
                    <div style="margin-bottom:6px;"><span style="color:#64748b; display:inline-block; width:135px;">Gudang Sumber:</span> <strong style="color:#0f172a;">{{ $gudangUtama->nama ?? 'Gudang Utama' }}</strong> <span style="font-size:10px; color:#64748b;">(Penyedia)</span></div>
                    <div><span style="color:#64748b; display:inline-block; width:135px;">Gudang Tujuan:</span> <strong style="color:#0f172a;">{{ $pengeluaran->gudang->nama ?? '-' }} @if($pengeluaran->divisi) (Divisi: {{ $pengeluaran->divisi->nama }}) @endif</strong></div>
                </td>
                <td style="padding:10px 14px; width:50%; vertical-align:top;">
                    <div style="margin-bottom:6px;"><span style="color:#64748b; display:inline-block; width:125px;">Tanggal Pengajuan:</span> <strong style="color:#0f172a;">{{ \Carbon\Carbon::parse($pengeluaran->tanggal)->format('d M Y H:i') }}</strong></div>
                    <div><span style="color:#64748b; display:inline-block; width:125px;">Status Dokumen:</span> 
                        @if(in_array(strtolower($pengeluaran->status), ['approved', 'disetujui']))
                            <span style="display:inline-block; padding:3px 12px; font-weight:bold; font-size:11px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:4px;">APPROVED / DISETUJUI</span>
                        @else
                            <span style="display:inline-block; padding:3px 12px; font-weight:bold; font-size:11px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; border-radius:4px;">DRAFT / PENGAJUAN</span>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        @if(!empty($pengeluaran->keterangan) && $pengeluaran->keterangan !== '-')
            <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; padding:8px 12px; margin-bottom:16px; font-size:11px; color:#92400e;">
                <strong>Catatan / Keterangan:</strong> {{ $pengeluaran->keterangan }}
            </div>
        @endif

        <!-- TABEL BARANG -->
        <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:10.5px;">
            <thead>
                <tr style="background:#7A4517; color:#ffffff;">
                    <th style="padding:8px 5px; text-align:center; width:30px; border:1px solid #7A4517; font-size:10px;">NO</th>
                    <th style="padding:8px 8px; text-align:left; border:1px solid #7A4517; font-size:10px;">NAMA BAHAN BAKU</th>
                    <th style="padding:8px 8px; text-align:right; width:105px; border:1px solid #7A4517; font-size:10px;">QTY DIMINTA</th>
                    <th style="padding:8px 8px; text-align:right; width:120px; border:1px solid #7A4517; font-size:10px;">STOK GDG UTAMA</th>
                    <th style="padding:8px 8px; text-align:right; width:100px; border:1px solid #7A4517; font-size:10px;">KEKURANGAN</th>
                    <th style="padding:8px 6px; text-align:center; width:100px; border:1px solid #7A4517; font-size:10px;">KETERSEDIAAN</th>
                    <th style="padding:8px 8px; text-align:right; width:95px; border:1px solid #7A4517; font-size:10px;">HARGA (RP)</th>
                    <th style="padding:8px 8px; text-align:right; width:115px; border:1px solid #7A4517; font-size:10px;">TOTAL HPP</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $docTotalDiminta = 0;
                    $docTotalKurang = 0;
                    $docGrandTotal = 0;
                @endphp
                @foreach($pengeluaran->details as $idx => $d)
                    @php
                        $dQty = (float) $d->qty;
                        $dStokUtama = (float) ($d->stok_gudang_utama ?? 0);
                        $dKurang = (float) ($d->kekurangan ?? max(0, $dQty - $dStokUtama));
                        $dSatuan = $d->barang->satuan ?? ($d->satuan ?? 'pcs');
                        $dHpp = (float) ($d->hpp_total ?? 0);
                        $dHargaSatuan = $dQty > 0 ? $dHpp / $dQty : 0;

                        $docTotalDiminta += $dQty;
                        $docTotalKurang += $dKurang;
                        $docGrandTotal += $dHpp;

                        $bgRow = $idx % 2 === 1 ? '#f8fafc' : '#ffffff';
                    @endphp
                    <tr style="background:{{ $bgRow }};">
                        <td style="padding:8px 5px; text-align:center; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px;">{{ $idx + 1 }}</td>
                        <td style="padding:8px 8px; border:1px solid #e2e8f0;">
                            <div style="font-weight:700; color:#0f172a; font-size:11px;">{{ $d->barang->nama ?? '-' }}</div>
                            <div style="font-family:monospace; font-size:9.5px; color:#64748b;">{{ $d->barang->kode_barang ?? '-' }}</div>
                        </td>
                        <td style="padding:8px 8px; text-align:right; font-weight:700; color:#0f172a; border:1px solid #e2e8f0; font-size:11px;">
                            {{ number_format($dQty, 2, ',', '.') }} <span style="font-size:9px; color:#64748b; font-weight:normal;">{{ $dSatuan }}</span>
                        </td>
                        <td style="padding:8px 8px; text-align:right; border:1px solid #e2e8f0; font-size:11px;">
                            <span style="font-weight:600; color:{{ $dStokUtama > 0 ? '#0284c7' : '#dc2626' }};">{{ number_format($dStokUtama, 2, ',', '.') }}</span>
                            <span style="font-size:9px; color:#64748b;">{{ $dSatuan }}</span>
                        </td>
                        <td style="padding:8px 8px; text-align:right; border:1px solid #e2e8f0; font-size:11px;">
                            @if($dKurang > 0)
                                <span style="color:#dc2626; font-weight:bold;">-{{ number_format($dKurang, 2, ',', '.') }} <span style="font-size:9px; color:#64748b; font-weight:normal;">{{ $dSatuan }}</span></span>
                            @else
                                <span style="color:#16a34a; font-weight:600;">0,00</span>
                            @endif
                        </td>
                        <td style="padding:8px 6px; text-align:center; border:1px solid #e2e8f0;">
                            @if($dStokUtama >= $dQty)
                                <span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:3px;">Tersedia</span>
                            @elseif($dStokUtama > 0)
                                <span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fffbeb; color:#b45309; border:1px solid #fde68a; border-radius:3px;">Kurang</span>
                            @else
                                <span style="display:inline-block; padding:2px 6px; font-size:9.5px; font-weight:bold; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:3px;">Habis (0)</span>
                            @endif
                        </td>
                        <td style="padding:8px 8px; text-align:right; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px;">
                            Rp {{ number_format($dHargaSatuan, 0, ',', '.') }}
                        </td>
                        <td style="padding:8px 8px; text-align:right; font-weight:700; color:#0f172a; border:1px solid #e2e8f0; font-size:11px;">
                            Rp {{ number_format($dHpp, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold; font-size:11px;">
                    <td colspan="2" style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1;">TOTAL KESELURUHAN:</td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; color:#0f172a;">{{ number_format($docTotalDiminta, 2, ',', '.') }}</td>
                    <td style="border:1px solid #cbd5e1;"></td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; color:{{ $docTotalKurang > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ $docTotalKurang > 0 ? '-' . number_format($docTotalKurang, 2, ',', '.') : '0,00' }}
                    </td>
                    <td colspan="2" style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1;">Total Nilai HPP:</td>
                    <td style="padding:9px 8px; text-align:right; border:1px solid #cbd5e1; color:#7A4517; font-size:11.5px;">
                        Rp {{ number_format($docGrandTotal, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- SIGNATURE BLOCK -->
        <table style="width:100%; border-collapse:collapse; margin-top:25px; font-size:11px; text-align:center;">
            <tr>
                <td style="width:33.33%; padding:0 15px; vertical-align:top;">
                    <div style="color:#64748b; margin-bottom:50px;">Pemohon / Peminta</div>
                    <div style="font-weight:700; border-top:1px solid #94a3b8; padding-top:4px; color:#0f172a;">{{ $pengeluaran->gudang->nama ?? 'Gudang Tujuan' }}</div>
                </td>
                <td style="width:33.33%; padding:0 15px; vertical-align:top;">
                    <div style="color:#64748b; margin-bottom:50px;">Kepala Gudang</div>
                    <div style="font-weight:700; border-top:1px solid #94a3b8; padding-top:4px; color:#0f172a;">{{ $gudangUtama->nama ?? 'Gudang Utama' }}</div>
                </td>
                <td style="width:33.33%; padding:0 15px; vertical-align:top;">
                    <div style="color:#64748b; margin-bottom:50px;">Management</div>
                    <div style="font-weight:700; border-top:1px solid #94a3b8; padding-top:4px; color:#0f172a;">CV Gaharu Agung Sejahtera</div>
                </td>
            </tr>
        </table>

        <!-- WATERMARK / TIMESTAMP -->
        <div style="margin-top:25px; padding-top:8px; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; font-size:9.5px; color:#94a3b8;">
            <div>Sistem ERP Gaharu - Dokumen Bukti Permintaan &amp; Transfer Bahan Baku</div>
            <div>Dicetak otomatis pada: ${timestamp}</div>
        </div>
    `;

    document.body.appendChild(container);

    html2canvas(container, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
    }).then(canvas => {
        document.body.removeChild(container);
        let link = document.createElement('a');
        link.download = `Transfer-Bahan-{{ $pengeluaran->kode_pengeluaran }}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }).catch(err => {
        if (container.parentNode) document.body.removeChild(container);
        console.error('Error generating image:', err);
        alert('Gagal mendownload gambar: ' + err.message);
    });
}
</script>

</x-app-layout>
