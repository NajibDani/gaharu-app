<x-app-layout>
    @php
        $sudahWO = \App\Models\WorkOrderDetail::where('pesanan_id', $pesanan->id)->exists();
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
        $isSent = \App\Models\Pengiriman::where('pesanan_id', $pesanan->id)->where('status_pengiriman', 'Selesai')->exists() || ($pesanan->total_qty_terkirim ?? 0) > 0;
    @endphp

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0 text-dark">Detail Permintaan Cold Kitchen</h3>
                <small class="text-muted">Informasi lengkap pesanan &amp; status produksi: <span class="fw-bold text-primary">#{{ $pesanan->kode_pesanan }}</span></small>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <button type="button" class="btn btn-success btn-sm shadow-sm fw-bold px-3" onclick="downloadSoAsJpg()">
                    <i class="bi bi-file-image me-1"></i> Download JPG
                </button>
                <a href="{{ route('pesanan.cetak-pdf', $pesanan->id) }}" class="btn btn-danger btn-sm shadow-sm fw-bold px-3" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak SO (PDF)
                </a>
                <a href="{{ route('pesanan.kwitansi', $pesanan->id) }}" class="btn btn-outline-primary btn-sm shadow-sm px-3" target="_blank">
                    <i class="bi bi-printer me-1"></i> Cetak Kwitansi
                </a>
                @if(!$sudahWO)
                    <a href="{{ route('pesanan.edit', $pesanan->id) }}" class="btn btn-warning btn-sm shadow-sm text-dark fw-bold px-3">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </a>
                    <form action="{{ route('pesanan.destroy', $pesanan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus permintaan #{{ $pesanan->kode_pesanan }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm shadow-sm fw-bold px-3">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </button>
                    </form>
                @elseif($isSuperAdmin && !$isSent)
                    <form action="{{ route('pesanan.destroy', $pesanan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('PERHATIAN (Superadmin):\nApakah Anda yakin ingin menghapus Purchase Order / Permintaan #{{ $pesanan->kode_pesanan }}?\n\nSemua relasi Work Order dan data produksi terkait yang belum terkirim akan dibatalkan/dihapus secara bersih.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm shadow-sm fw-bold px-3">
                            <i class="bi bi-trash3-fill me-1"></i> Hapus PO (Superadmin)
                        </button>
                    </form>
                @endif
                <a href="{{ route('pesanan.index') }}" class="btn btn-secondary btn-sm shadow-sm px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        {{-- DOCUMENT CONTAINER (UNTUK TAMPILAN RESMI & DOWNLOAD JPG) --}}
        <div id="so-document-container" class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white" style="max-width: 1000px; margin: 0 auto;">
            {{-- HEADER BLOCK - WARNA BIRU (PRODUKSI / COLD KITCHEN) --}}
            <div class="p-3 rounded-3 mb-3 text-white" style="background-color: #1d4ed8;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold fs-5 text-uppercase" style="letter-spacing: 0.5px;">CV GAHARU AGUNG SEJAHTERA</div>
                        <div class="small opacity-75">Cold Kitchen Production &amp; Sales Order Management</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-6 text-uppercase">PERMINTAAN COLD KITCHEN (SO)</div>
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
                            <td class="fw-bold text-dark" style="width: 32%;">PERMINTAAN COLD KITCHEN (SO)</td>
                            <td class="fw-bold text-secondary text-uppercase" style="width: 18%; font-size: 11px;">Tanggal Order</td>
                            <td class="fw-bold text-dark" style="width: 32%;">{{ \Carbon\Carbon::parse($pesanan->tanggal)->format('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Outlet Pemesan</td>
                            <td><strong class="text-primary fs-6">{{ $pesanan->customer->nama ?? $pesanan->customer->name ?? '-' }}</strong></td>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Gudang Sumber</td>
                            <td><strong>{{ $pesanan->gudang->nama ?? 'Gudang Cold Kitchen' }}</strong> <span class="text-muted small">(Penyedia)</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Status Dokumen</td>
                            <td>
                                @php
                                    $st = strtolower($pesanan->status_pesanan ?? 'pending');
                                    $badgeBg = 'bg-warning text-dark';
                                    if(in_array($st, ['selesai', 'approved', 'disetujui'])) $badgeBg = 'bg-success text-white';
                                    elseif(in_array($st, ['diproses', 'proses', 'dikirim', 'siap kirim'])) $badgeBg = 'bg-info text-dark';
                                    elseif($st == 'batal' || $st == 'dibatalkan') $badgeBg = 'bg-danger text-white';
                                @endphp
                                <span class="badge {{ $badgeBg }} px-2 py-1 text-uppercase">{{ $pesanan->status_pesanan ?? 'PENDING' }}</span>
                            </td>
                            <td class="fw-bold text-secondary text-uppercase" style="font-size: 11px;">Estimasi Kirim</td>
                            <td><strong>{{ \Carbon\Carbon::parse($pesanan->estimasi_kirim)->format('d F Y') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- TABEL PRODUK --}}
            <h6 class="fw-bold text-dark mb-2">Daftar Produk yang Diminta</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 40px;">No</th>
                            <th>Nama Produk</th>
                            <th class="text-center" style="width: 140px;">Jumlah (Qty)</th>
                            <th class="text-end" style="width: 160px;">Harga Satuan</th>
                            <th class="text-end" style="width: 170px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalSubDokumen = 0; @endphp
                        @foreach($pesanan->details as $idx => $detail)
                            @php 
                                $subtotal = $detail->subtotal ?? ($detail->qty * $detail->harga);
                                $totalSubDokumen += $subtotal;
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $detail->produk->nama ?? 'Produk' }}</div>
                                    @if(isset($detail->produk->kode_barang))
                                        <div class="font-monospace text-muted small">{{ $detail->produk->kode_barang }}</div>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-dark">{{ number_format($detail->qty, 0, ',', '.') }} {{ $detail->produk->satuan ?? 'Pcs' }}</td>
                                <td class="text-end">Rp {{ number_format($detail->harga, 0, ',', '.') }}</td>
                                <td class="text-end fw-bold">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-bold">Total Nilai Order:</td>
                            <td class="text-end fw-bold text-primary fs-6">Rp {{ number_format($pesanan->total_pesanan ?? $totalSubDokumen, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- TANDA TANGAN 3 PIHAK --}}
            <div class="row text-center mt-4 pt-3 border-top" style="font-size: 11px;">
                <div class="col-4">
                    <div class="text-muted">Pemesan (Outlet / Customer):</div>
                    <div style="height: 40px;"></div>
                    <div class="fw-bold text-dark">({{ $pesanan->customer->nama ?? $pesanan->customer->name ?? 'Pemesan' }})</div>
                    <div class="text-muted small">Unit Pemesan</div>
                </div>
                <div class="col-4">
                    <div class="text-muted">Penyedia (Cold Kitchen):</div>
                    <div style="height: 40px;"></div>
                    <div class="fw-bold text-dark">( Tim Produksi Cold Kitchen )</div>
                    <div class="text-muted small">Gudang Cold Kitchen</div>
                </div>
                <div class="col-4">
                    <div class="text-muted">Management / Otorisasi:</div>
                    <div style="height: 40px;"></div>
                    <div class="fw-bold text-dark">( Manajer Operasional )</div>
                    <div class="text-muted small">CV Gaharu Agung Sejahtera</div>
                </div>
            </div>

            <div class="mt-4 pt-2 border-top d-flex justify-content-between text-muted" style="font-size: 10px;">
                <div>Dokumen Resmi Sistem ERP - CV Gaharu Agung Sejahtera</div>
                <div>Diunduh pada: {{ date('d M Y H:i:s') }}</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Kode Pesanan</label>
                        <h5>{{ $pesanan->kode_pesanan }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Customer</label>
                        <h5>{{ $pesanan->customer->nama ?? '-' }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Tanggal</label>
                        <h5>{{ date('d M Y H:i', strtotime($pesanan->tanggal)) }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Status</label>
                        <br>
                        {{-- PERBAIKAN LOGIKA STATUS DI SINI --}}
                        @if($pesanan->status_pesanan == 'pending')
                            <span class="badge bg-warning text-dark">Pending</span>
                        @elseif($pesanan->status_pesanan == 'diproses')
                            <span class="badge bg-info">Diproses</span>
                        @elseif($pesanan->status_pesanan == 'siap kirim')
                            <span class="badge bg-success">Siap Dikirim</span>
                        @elseif($pesanan->status_pesanan == 'dikirim')
                            <span class="badge bg-primary">Dikirim</span>
                        @elseif($pesanan->status_pesanan == 'selesai')
                            <span class="badge bg-success">Selesai</span>
                        @elseif($pesanan->status_pesanan == 'batal')
                            <span class="badge bg-danger">Batal</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($pesanan->status_pesanan) }}</span>
                        @endif
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">No. HP Customer</label>
                        <h5>{{ $pesanan->customer->no_hp ?? '-' }}</h5>
                    </div>

                    <div class="col-6 col-md-3 mb-3">
                        <label class="text-muted">Tipe Customer</label>
                        <h5>{{ $pesanan->customer->jenis ?? '-' }}</h5>
                    </div>

                    <div class="col-12 col-md-6 mb-3">
                        <label class="text-muted">Alamat Customer</label>
                        <h5>{{ $pesanan->customer->alamat ?? '-' }}</h5>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label class="text-muted fw-bold">Estimasi Tanggal Kirim</label>
                        <h6 class="text-dark fw-semibold">{{ date('d M Y H:i', strtotime($pesanan->estimasi_kirim)) }}</h6>
                    </div>

                    <div class="col-12 col-md-6 mb-3">
                        <label class="text-muted fw-bold">Estimasi Tanggal Produksi</label>
                        <h6 class="text-primary fw-bold">{{ $pesanan->estimasi_produksi ? date('d M Y', strtotime($pesanan->estimasi_produksi)) : '— (Tidak Perlu Produksi / Sudah Siap)' }}</h6>
                    </div>
                </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Detail Produk</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Produk</th>
                                <th width="150">Qty</th>
                                <th width="200">Harga</th>
                                <th width="200">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pesanan->details as $detail)
                            <tr>
                                <td>{{ $detail->produk->nama ?? 'Produk Tidak Ditemukan' }}</td>
                                <td>{{ number_format($detail->qty, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($detail->harga, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end text-secondary fw-normal">Subtotal (DPP)</th>
                                <th class="text-secondary fw-normal">Rp {{ number_format($pesanan->total_pesanan - $pesanan->tax_service, 0, ',', '.') }}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end text-secondary fw-normal">Tax/Service ({{ number_format($pesanan->tax_percentage, 2) }}%)</th>
                                <th class="text-secondary fw-normal">Rp {{ number_format($pesanan->tax_service, 0, ',', '.') }}</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end text-primary fw-bold">Total (Nett)</th>
                                <th class="text-primary fw-bold">Rp {{ number_format($pesanan->total_pesanan, 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0 fw-bold">Bukti Pembayaran</h5>
            </div>
            <div class="card-body">
                @php
                    $pembayaranList = $pesanan->pembayaran ?? collect();
                @endphp

                @if($pembayaranList->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nominal</th>
                                    <th>Metode</th>
                                    <th>Catatan</th>
                                    <th>Bukti Upload</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pembayaranList as $p)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($p->tanggal_bayar)->format('d M Y') }}</td>
                                        <td class="fw-semibold text-success">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
                                        <td>{{ $p->metode_pembayaran }}</td>
                                        <td>{{ $p->catatan ?? '-' }}</td>
                                        <td>
                                            @if($p->bukti_pembayaran && is_array($p->bukti_pembayaran))
                                                <div class="d-flex gap-2 flex-wrap">
                                                    @foreach($p->bukti_pembayaran as $img)
                                                        <a href="{{ asset('storage/' . $img) }}" target="_blank">
                                                            <img src="{{ asset('storage/' . $img) }}" class="img-thumbnail" style="width: 70px; height: 70px; object-fit: cover;" alt="Bukti">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">Belum ada bukti pembayaran yang diupload.</p>
                @endif
            </div>
        </div>
    </div>

    <script>
        function downloadSoAsJpg() {
            const el = document.getElementById('so-document-container');
            if (!el) return;
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Memproses JPG...';
            btn.disabled = true;

            html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Permintaan-ColdKitchen-{{ $pesanan->kode_pesanan }}.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(err => {
                console.error(err);
                alert('Gagal mendownload gambar: ' + err.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</x-app-layout>