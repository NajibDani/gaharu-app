<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .card-custom { border-radius: 16px; border: 1px solid #eaeaea; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .table-custom-header th { background-color: #6a4126 !important; color: #ffffff !important; font-size: 0.78rem; padding: 10px; }
        .table-custom-body td { font-size: 0.82rem; padding: 10px; border-bottom: 1px solid #f1f5f9; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important; max-width: 960px;">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Detail Central Kitchen Order</h4>
                <p class="text-muted small mb-0">Kode Pesanan: <span class="fw-bold text-primary">{{ $pesanan->kode_pesanan }}</span></p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm rounded-3 fw-bold px-3" onclick="downloadJpgPO()">
                    <i class="bi bi-file-image me-1"></i> Download JPG
                </button>
                <a href="{{ route('ck-orders.cetak-pdf', $pesanan->id) }}" target="_blank" class="btn btn-outline-danger btn-sm rounded-3 px-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
                </a>
                <a href="{{ route('ck-orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-3 px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        {{-- DOCUMENT CONTAINER UNTUK CROP / DOWNLOAD JPG --}}
        <div id="po-document-container" class="card card-custom p-4 mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-1">PURCHASE ORDER CENTRAL KITCHEN</h4>
                    <h6 class="fw-bold text-primary mb-1">CENTRAL KITCHEN CV GAHARU AGUNG SEJAHTERA</h6>
                    <div class="text-muted small">
                        Layanan Pengadaan &amp; Logistik Dapur Pusat CV Gaharu<br>
                        <strong>Divisi CK:</strong> {{ $pesanan->divisi->nama ?? 'Gudang Central Kitchen' }}
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold mb-2">PURCHASE ORDER</span>
                    <div class="font-monospace fw-bold text-dark fs-5">#{{ $pesanan->kode_pesanan }}</div>
                    <div class="text-muted small">Tanggal: <strong>{{ date('d F Y', strtotime($pesanan->tanggal)) }}</strong></div>
                </div>
            </div>

            <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                <div class="col-md-4">
                    <span class="text-muted small d-block">Outlet Pemesan</span>
                    <span class="fw-bold text-dark fs-6">{{ $pesanan->customer->nama ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Tanggal Order</span>
                    <span class="fw-semibold text-dark">{{ date('d F Y', strtotime($pesanan->tanggal)) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Estimasi Kirim</span>
                    <span class="fw-semibold text-dark">{{ date('d F Y', strtotime($pesanan->estimasi_kirim)) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Status Pesanan</span>
                    <span class="badge bg-info text-dark">{{ ucfirst($pesanan->status_pesanan) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Penagihan / Harga</span>
                    <span class="badge bg-success">HPP Internal (Rp 0 Penagihan)</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Dibuat Oleh</span>
                    <span class="fw-semibold text-dark">{{ $pesanan->creator->name ?? 'Sistem' }}</span>
                </div>
            </div>

            <h6 class="fw-bold text-dark mb-3">Detail Item Pesanan CK (Bahan Setengah Jadi)</h6>

            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 40px;">NO</th>
                            <th style="width: 120px;">KODE ITEM</th>
                            <th>NAMA BAHAN SETENGAH JADI</th>
                            <th style="width: 220px;" class="text-center">KONVERSI RESEP / BATCH</th>
                            <th style="width: 150px;" class="text-end">TARGET QTY</th>
                        </tr>
                    </thead>
                    <tbody class="table-custom-body">
                        @foreach($pesanan->details as $index => $detail)
                            @php
                                $resepObj = $detail->produk->resepBtklBop ?? null;
                                $outQty = floatval($resepObj->output_qty ?? 0);
                                $outSatuan = $resepObj->satuan_output ?? ($detail->produk->satuan ?? '');
                                $resepText = '-';
                                if ($outQty > 0) {
                                    $resepCount = $detail->qty / $outQty;
                                    $resepCountFmt = (fmod($resepCount, 1) == 0) ? number_format($resepCount, 0) : number_format($resepCount, 2, ',', '.');
                                    $resepText = $resepCountFmt . ' Resep (@ ' . number_format($outQty, 0, ',', '.') . ' ' . $outSatuan . ')';
                                }
                            @endphp
                            <tr>
                                <td class="text-center fw-semibold text-muted">{{ $index + 1 }}</td>
                                <td class="font-monospace fw-bold text-dark">{{ $detail->produk->kode_barang ?? '-' }}</td>
                                <td><strong class="text-dark">{{ $detail->produk->nama ?? 'Item Hapus' }}</strong></td>
                                <td class="text-center">
                                    @if($outQty > 0)
                                        <span class="badge bg-warning-subtle text-dark border px-2 py-1 fs-6">
                                            <i class="bi bi-journal-bookmark me-1"></i>{{ $resepText }}
                                        </span>
                                    @else
                                        <span class="text-muted small">Standard (Non-Resep)</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    {{ (fmod($detail->qty, 1) == 0) ? number_format($detail->qty, 0, ',', '.') : number_format($detail->qty, 2, ',', '.') }} {{ $detail->produk->satuan ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row text-center mt-4 pt-3 border-top" style="font-size: 11px;">
                <div class="col-4">
                    <div class="text-muted">Pemesan (Outlet):</div>
                    <div style="height: 40px;"></div>
                    <div class="fw-bold text-dark">({{ $pesanan->customer->nama ?? 'Kepala Outlet' }})</div>
                </div>
                <div class="col-4">
                    <div class="text-muted">Central Kitchen:</div>
                    <div style="height: 40px;"></div>
                    <div class="fw-bold text-dark">( Dapur Pusat CV Gaharu )</div>
                </div>
                <div class="col-4">
                    <div class="text-muted">Gudang &amp; Logistik:</div>
                    <div style="height: 40px;"></div>
                    <div class="fw-bold text-dark">( Tim Warehouse CK )</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadJpgPO() {
            const el = document.getElementById('po-document-container');
            if (!el) return;
            html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'PO-CentralKitchen-{{ $pesanan->kode_pesanan }}.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();
            });
        }
    </script>
</x-app-layout>
