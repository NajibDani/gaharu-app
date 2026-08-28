<x-app-layout>
    <div class="container">
        <h4>Detail Pembelian</h4>

        <div class="card mb-3">
            <div class="card-body">
                <p>
                    <strong>Kode:</strong> 
                    {{ $pembelian->kode_pembelian }}
                </p>

                <p>
                    <strong>Tanggal:</strong> 
                    {{ $pembelian->tanggal }}
                </p>

                <p>
                    <strong>Supplier:</strong> 
                    {{ $pembelian->supplier->nama ?? '-' }}
                </p>

                <p>
                    <strong>Gudang:</strong> 
                    {{ $pembelian->gudang->nama ?? '-' }}
                </p>

                <p>
                    <strong>Total:</strong> 
                    Rp {{ number_format($pembelian->total, 0, ',', '.') }}
                </p>

                <p>
                    <strong>Metode Pembayaran:</strong> 
                    {{ strtoupper($pembelian->metode_pembayaran ?? 'Belum Dicatat') }}
                </p>

                @if($pembelian->metode_pembayaran === 'dp')
                    <p>
                        <strong>DP (Persentase / Nominal):</strong> 
                        {{ $pembelian->persen_dp }}% / Rp {{ number_format($pembelian->nominal_dp, 0, ',', '.') }}
                    </p>
                    <p>
                        <strong>Estimasi Pelunasan:</strong> 
                        {{ $pembelian->tanggal_pelunasan ? \Carbon\Carbon::parse($pembelian->tanggal_pelunasan)->format('d M Y') : '-' }}
                    </p>
                @endif

                @if($pembelian->metode_pembayaran === 'termin')
                    <p>
                        <strong>Jatuh Tempo:</strong> 
                        {{ $pembelian->tanggal_jatuh_tempo ? \Carbon\Carbon::parse($pembelian->tanggal_jatuh_tempo)->format('d M Y') : 'Fleksibel / Kesepakatan Personal' }}
                    </p>
                    <p>
                        <strong>Estimasi Pelunasan:</strong> 
                        {{ $pembelian->tanggal_pelunasan ? \Carbon\Carbon::parse($pembelian->tanggal_pelunasan)->format('d M Y') : '-' }}
                    </p>
                @endif

                <p>
                    <strong>Status Pembayaran:</strong> 
                    @if($pembelian->is_lunas)
                        <span class="badge bg-success">Lunas ({{ $pembelian->lunas_at ? \Carbon\Carbon::parse($pembelian->lunas_at)->format('d M Y') : '' }})</span>
                    @else
                        <span class="badge bg-warning text-dark">Belum Lunas</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                        <th>Batch</th>
                    </tr>
                </thead>
    
                <tbody>
                    @foreach($pembelian->details as $detail)
                        <tr>
                            <td>{{ $detail->barang->nama ?? '-' }}</td>
                            <td>{{ (float)$detail->qty }} {{ $detail->satuan_pembelian ?: ($detail->barang->satuan ?? '') }}</td>
                            <td>
                                Rp {{ number_format($detail->harga_per_qty, 0, ',', '.') }} / {{ $detail->satuan_pembelian ?: ($detail->barang->satuan ?? '') }}
                            </td>
                            <td>
                                Rp {{ number_format($detail->harga, 0, ',', '.') }}
                            </td>
                            <td>{{ $detail->batch_number }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Subtotal Barang</th>
                        <th colspan="2">Rp {{ number_format($pembelian->details->sum('harga'), 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Biaya Tambahan (Tax / Service / Ongkir)</th>
                        <th colspan="2">Rp {{ number_format($pembelian->tax_service ?? 0, 0, ',', '.') }}</th>
                    </tr>
                    <tr class="table-primary">
                        <th colspan="3" class="text-end">Grand Total</th>
                        <th colspan="2">Rp {{ number_format($pembelian->total, 0, ',', '.') }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card mt-4 mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">Bukti Pembayaran</h5>
            </div>
            <div class="card-body">
                @php
                    $pembayaranList = $pembelian->pembayaran ?? collect();
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
                                        <td class="fw-semibold">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
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
        
        <div class="mt-3">
            <a href="{{ route('pembelian.index') }}" class="btn btn-light border">
                Kembali
            </a>
        </div>
    </div>
</x-app-layout>