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
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <button type="button" class="btn btn-success btn-sm rounded-3 fw-bold px-3" onclick="downloadJpgPO()">
                    <i class="bi bi-file-image me-1"></i> Download JPG
                </button>
                <a href="{{ route('ck-orders.cetak-pdf', $pesanan->id) }}" target="_blank" class="btn btn-outline-danger btn-sm rounded-3 px-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF
                </a>
                @php
                    $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
                    $isSent = \App\Models\Pengiriman::where('pesanan_id', $pesanan->id)->where('status_pengiriman', 'Selesai')->exists() || ($pesanan->total_qty_terkirim ?? 0) > 0;
                @endphp
                @if(!$workOrder)
                    <a href="{{ route('ck-orders.edit', $pesanan->id) }}" class="btn btn-warning btn-sm rounded-3 fw-bold px-3 text-dark">
                        <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                    </a>
                    <form action="{{ route('ck-orders.destroy', $pesanan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pesanan #{{ $pesanan->kode_pesanan }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-3 fw-bold px-3">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </button>
                    </form>
                @elseif($isSuperAdmin && !$isSent)
                    <form action="{{ route('ck-orders.destroy', $pesanan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('PERHATIAN (Superadmin):\nApakah Anda yakin ingin menghapus Purchase Order #{{ $pesanan->kode_pesanan }}?\n\nSemua relasi Work Order dan data produksi terkait yang belum terkirim akan dibatalkan/dihapus secara bersih.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm rounded-3 fw-bold px-3">
                            <i class="bi bi-trash3-fill me-1"></i> Hapus PO (Superadmin)
                        </button>
                    </form>
                @endif
                <a href="{{ route('ck-orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-3 px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        {{-- DOCUMENT CONTAINER UNTUK CROP / DOWNLOAD JPG --}}
        <div id="po-document-container" class="card card-custom p-4 mb-4 bg-white border-0 shadow-sm rounded-4" style="max-width: 1000px; margin: 0 auto;">
            {{-- HEADER BLOCK - WARNA BIRU (PRODUKSI / CENTRAL KITCHEN) --}}
            <div class="p-3 rounded-3 mb-3 text-white" style="background-color: #1d4ed8;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold fs-5 text-uppercase" style="letter-spacing: 0.5px;">CV GAHARU AGUNG SEJAHTERA</div>
                        <div class="small opacity-75">Central Kitchen Production &amp; Internal Order Management</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-6 text-uppercase">PURCHASE ORDER CENTRAL KITCHEN</div>
                        <div class="font-monospace fw-bold fs-5">#{{ $pesanan->kode_pesanan }}</div>
                    </div>
                </div>
            </div>

            {{-- STANDAR INFO GRID METADATA --}}
            <div class="table-responsive mb-3">
                <table class="table table-bordered align-middle mb-0" style="font-size: 12px; background-color: #f8fafc;">
                    <tbody>
                        <tr>
                            <td class="fw-bold text-secondary text-uppercase" style="width: 18%; font-size: 11px;">Judul Dokumen</td>
                            <td class="fw-bold text-dark" style="width: 32%;">PURCHASE ORDER CENTRAL KITCHEN</td>
                            <td class="fw-bold text-secondary text-uppercase" style="width: 18%; font-size: 11px;">Tanggal Order</td>
                            <td class="fw-bold text-dark" style="width: 32%;">{{ \Carbon\Carbon::parse($pesanan->tanggal)->format('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Outlet Pemesan</td>
                            <td><strong class="text-primary fs-6">{{ $pesanan->customer->nama ?? '-' }}</strong></td>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Gudang Sumber</td>
                            <td><strong>{{ $pesanan->divisi->nama ?? 'Gudang Central Kitchen' }}</strong> <span class="text-muted small">(Penyedia)</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Status Dokumen</td>
                            <td>
                                @php
                                    $st = strtolower($pesanan->status_pesanan);
                                    $badgeBg = 'bg-warning text-dark';
                                    if(in_array($st, ['selesai', 'approved', 'disetujui'])) $badgeBg = 'bg-success text-white';
                                    elseif(in_array($st, ['diproses', 'proses', 'dikirim', 'siap kirim'])) $badgeBg = 'bg-info text-dark';
                                    elseif($st == 'batal' || $st == 'dibatalkan') $badgeBg = 'bg-danger text-white';
                                @endphp
                                <span class="badge {{ $badgeBg }} px-2 py-1 text-uppercase">{{ $pesanan->status_pesanan }}</span>
                            </td>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Estimasi Kirim</td>
                            <td><strong>{{ \Carbon\Carbon::parse($pesanan->estimasi_kirim)->format('d F Y') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
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
