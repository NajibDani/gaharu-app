<x-app-layout>
    <x-slot name="header">Detail Purchase Order Kejingga - {{ $pembelian->kode_pembelian }}</x-slot>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <div class="container-fluid px-4 py-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="m-0 fw-bold text-dark">Detail Purchase Order Kejingga</h4>
                <div class="text-muted small">Kode Transaksi: <strong class="font-monospace text-primary">{{ $pembelian->kode_pembelian }}</strong></div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm fw-semibold px-3" onclick="downloadPOAsJPG()">
                    <i class="bi bi-file-image me-1"></i> Download JPG
                </button>
                <a href="{{ route('pembelian.cetak-pdf', $pembelian->id) }}" target="_blank" class="btn btn-danger btn-sm fw-semibold px-3">
                    <i class="bi bi-printer me-1"></i> Cetak PDF
                </a>
                @if(!$pembelian->isTerkunci())
                    <a href="{{ route('pembelian-kejingga.edit', $pembelian->id) }}" class="btn btn-warning btn-sm text-dark fw-semibold px-3">
                        <i class="bi bi-pencil-square me-1"></i> Edit PO
                    </a>
                @endif
                <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        {{-- DOCUMENT CONTAINER (WILL BE RENDERED TO JPG) --}}
        <div id="po-document-container" class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white" style="max-width: 1000px; margin: 0 auto;">
            {{-- HEADER DOKUMEN --}}
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div>
                    <h3 class="fw-bold text-dark mb-1" style="color: #1e293b;">KEJINGGA</h3>
                    <div class="text-muted small">
                        Layanan Pengadaan &amp; Logistik Operasional Kejingga<br>
                        <strong>Gudang:</strong> {{ $pembelian->gudang->nama ?? 'Gudang KeJingga' }}
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold mb-2">PURCHASE ORDER (PO)</span>
                    <div class="font-monospace fw-bold text-dark fs-5">#{{ $pembelian->kode_pembelian }}</div>
                    <div class="text-muted small">Tanggal: <strong>{{ \Carbon\Carbon::parse($pembelian->tanggal)->format('d F Y') }}</strong></div>
                </div>
            </div>

            {{-- INFORMASI SUPPLIER & PEMBAYARAN --}}
            <div class="row mb-4">
                <div class="col-6">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Supplier / Pemasok:</small>
                        @if($pembelian->supplier)
                            <div class="fw-bold text-dark fs-6">{{ $pembelian->supplier->nama }}</div>
                            <div class="text-muted small">No. Telp: {{ $pembelian->supplier->telepon ?? '-' }}</div>
                            <div class="text-muted small">Alamat: {{ $pembelian->supplier->alamat ?? '-' }}</div>
                        @else
                            <div class="badge bg-secondary px-2 py-1 fs-6">Draft Permintaan (Belum Ada Supplier)</div>
                            <div class="text-muted small mt-1">Dibuat oleh staff operasional, menunggu pengisian supplier oleh tim Purchasing.</div>
                        @endif
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Status &amp; Pembayaran:</small>
                        <div class="d-flex flex-column gap-1 small">
                            <div>Metode Pembayaran: <strong class="text-uppercase">{{ $pembelian->metode_pembayaran ?: 'Belum Dicatat' }}</strong></div>
                            <div>
                                Status Pelunasan: 
                                @if($pembelian->is_lunas)
                                    <span class="badge bg-success">✓ LUNAS</span>
                                @else
                                    <span class="badge bg-danger">BELUM LUNAS</span>
                                @endif
                            </div>
                            <div>
                                Status Penerimaan Barang: 
                                @if($pembelian->is_diterima)
                                    <span class="badge bg-success">✓ DITERIMA DENGAN LENGKAP</span>
                                @else
                                    <span class="badge bg-warning text-dark">PROSES PENERIMAAN</span>
                                @endif
                            </div>
                            <div>Dibuat Oleh: <strong>{{ $pembelian->user->nama ?? ($pembelian->user->username ?? 'Staff Operasional') }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABEL BARANG --}}
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-dark">
                        <tr>
                            <th width="40" class="text-center">No</th>
                            <th>Nama Barang &amp; Kode</th>
                            <th width="150" class="text-center">Stok Gudang Kejingga</th>
                            <th width="140" class="text-center">Qty Dipesan</th>
                            <th width="130" class="text-center">Qty Diterima</th>
                            <th width="150" class="text-end">Harga / Satuan</th>
                            <th width="160" class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalItemsCalculated = 0; @endphp
                        @foreach($pembelian->details as $idx => $detail)
                            @php
                                $bItem = $detail->barang;
                                $sPembelian = $detail->satuan_pembelian ?: ($bItem->satuan_pembelian ?? '');
                                $konv = (float)($detail->konversi_pembelian ?: ($bItem->konversi_pembelian ?? 1));
                                $sUtama = $bItem->satuan ?? 'Unit';
                                $hasKonv = ($sPembelian && $konv > 1 && $sPembelian !== $sUtama);
                                $unitDisplay = $sPembelian ?: $sUtama;
                                $stokKejinggaVal = (float)($stokKejinggaMap[$detail->barang_id] ?? 0);

                                $subtotal = (float)$detail->harga;
                                $totalItemsCalculated += $subtotal;
                            @endphp
                            <tr>
                                <td class="text-center">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $bItem->nama ?? 'Barang' }}</div>
                                    <div class="font-monospace text-muted small">{{ $bItem->kode_barang ?? '-' }}</div>
                                </td>
                                <td class="text-center bg-light">
                                    <span class="fw-semibold text-dark">{{ number_format($stokKejinggaVal, 2, ',', '.') }} {{ $sUtama }}</span>
                                </td>
                                <td class="text-center fw-bold">
                                    {{ number_format($detail->qty, 2, ',', '.') }} {{ $unitDisplay }}
                                    @if($hasKonv)
                                        <div class="text-primary small" style="font-size: 11px;">
                                            = {{ number_format($detail->qty * $konv, 2, ',', '.') }} {{ $sUtama }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    {{ number_format($detail->qty_diterima ?? 0, 2, ',', '.') }} {{ $unitDisplay }}
                                </td>
                                <td class="text-end">
                                    @if($detail->harga > 0)
                                        Rp {{ number_format($detail->harga_per_qty, 0, ',', '.') }} / {{ $unitDisplay }}
                                        @if($hasKonv && $konv > 0)
                                            <div class="text-muted small" style="font-size: 10px;">
                                                (~Rp {{ number_format($detail->harga_per_qty / $konv, 2, ',', '.') }} / {{ $sUtama }})
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">
                                    @if($detail->harga > 0)
                                        Rp {{ number_format($subtotal, 0, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- RINGKASAN TOTAL --}}
            <div class="row mb-4">
                <div class="col-6 offset-6">
                    <div class="table-responsive">
                        <table class="table table-borderless table-sm text-end">
                            @if($pembelian->tax_service > 0)
                            <tr>
                                <td class="text-muted">Subtotal Barang:</td>
                                <td class="fw-bold">Rp {{ number_format($totalItemsCalculated, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Biaya Tambahan / Tax Service:</td>
                                <td class="fw-bold">Rp {{ number_format($pembelian->tax_service, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr class="border-top">
                                <td class="fs-5 fw-bold text-dark">Total Purchase Order:</td>
                                <td class="fs-5 fw-bold text-primary">Rp {{ number_format($pembelian->total, 0, ',', '.') }}</td>
                            </tr>
                            @if($pembelian->nominal_dp > 0)
                            <tr>
                                <td class="text-muted">Uang Muka (DP):</td>
                                <td class="fw-bold text-success">Rp {{ number_format($pembelian->nominal_dp, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-danger">Sisa Tagihan:</td>
                                <td class="fw-bold text-danger">Rp {{ number_format($pembelian->total - $pembelian->nominal_dp, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- TANDA TANGAN / OTORISASI --}}
            <div class="row text-center mt-4 pt-3 border-top">
                <div class="col-4">
                    <div class="text-muted small">Dibuat Oleh (Operasional):</div>
                    <div style="height: 50px;"></div>
                    <div class="fw-bold text-dark">({{ $pembelian->user->nama ?? ($pembelian->user->username ?? 'Staff Operasional') }})</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Disetujui Purchasing / Management:</div>
                    <div style="height: 50px;"></div>
                    <div class="fw-bold text-dark">( Tim Purchasing )</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Diterima Oleh Gudang:</div>
                    <div style="height: 50px;"></div>
                    <div class="fw-bold text-dark">({{ $pembelian->penerimaDiterima->nama ?? 'Gudang Kejingga' }})</div>
                </div>
            </div>

            <div class="mt-4 pt-2 border-top d-flex justify-content-between text-muted" style="font-size: 10px;">
                <div>Dokumen Resmi Sistem ERP - KeJingga Bakehouse &amp; Resto</div>
                <div>Dicetak pada: {{ date('d M Y H:i:s') }}</div>
            </div>
        </div>
    </div>

    <script>
        function downloadPOAsJPG() {
            const container = document.getElementById('po-document-container');
            if (!container) return;

            // Show loading Toast or feedback
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Memproses JPG...';
            btn.disabled = true;

            html2canvas(container, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            }).then(canvas => {
                let link = document.createElement('a');
                link.download = 'PO-Kejingga-{{ $pembelian->kode_pembelian }}.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();

                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(err => {
                console.error('Gagal membuat gambar JPG:', err);
                alert('Gagal mendownload gambar JPG: ' + err.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</x-app-layout>
