<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <x-slot name="header">Pembelian Kejingga (Luar Gaharu)</x-slot>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0 fw-bold">Data Pembelian Kejingga (Luar Gaharu)</h4>
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">Khusus Gudang KeJingga</span>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i> {{ session('error') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="{{ route('pembelian-kejingga.create') }}" class="btn btn-primary mb-0">
                Tambah Pembelian Kejingga
            </a>

            <form action="{{ route('pembelian-kejingga.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kode/supplier..." value="{{ request('search') }}" style="width: 220px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('pembelian-kejingga.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Gudang</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Kekurangan</th>
                        <th class="text-center">Pembayaran</th>
                        <th class="text-center">Barang Diterima</th>
                        <th class="text-center" style="min-width:160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembelian as $item)
                        @php
                            $total = (float) $item->total;
                            if ($item->metode_pembayaran === 'dp') {
                                if ($item->nominal_dp && $item->nominal_dp > 0) {
                                    $nominalDp = (float) $item->nominal_dp;
                                } else {
                                    $persenDp   = (int) ($item->persen_dp ?? 0);
                                    $nominalDp  = $persenDp > 0 ? round($total * $persenDp / 100) : 0;
                                }
                            } else {
                                $nominalDp = 0;
                            }

                            $kekurangan = match(true) {
                                $item->metode_pembayaran === 'cod' => 0,
                                $item->is_lunas                    => 0,
                                $item->metode_pembayaran === 'dp'  => $total - $nominalDp,
                                $item->metode_pembayaran === 'termin' => $total,
                                default => 0,
                            };

                            $adaKekurangan = $kekurangan > 0 && !$item->is_lunas;
                        @endphp
                        <tr>
                            <td class="font-monospace" style="font-size:12px;">{{ $item->kode_pembelian }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d M Y') }}</td>
                            <td>{{ $item->supplier->nama ?? '-' }}</td>
                            <td><span class="badge bg-warning text-dark">{{ $item->gudang->nama ?? 'Gudang KeJingga' }}</span></td>

                            {{-- TOTAL --}}
                            <td class="text-end fw-semibold">
                                Rp {{ number_format($item->total, 0, ',', '.') }}
                            </td>

                            {{-- KEKURANGAN --}}
                            <td class="text-end">
                                @if(!$item->metode_pembayaran)
                                    <span class="text-muted" style="font-size:11px;">—</span>
                                @elseif($item->metode_pembayaran === 'cod' || $item->is_lunas)
                                    <span class="badge bg-success" style="font-size:11px;">Lunas</span>
                                @elseif($adaKekurangan)
                                    <span class="fw-semibold text-danger">
                                        Rp {{ number_format($kekurangan, 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-muted" style="font-size:11px;">—</span>
                                @endif
                            </td>

                            {{-- PEMBAYARAN --}}
                            <td class="text-center">
                                @if($item->metode_pembayaran)
                                    @php
                                        $labelMetode = match($item->metode_pembayaran) {
                                            'cod'    => ['text' => 'COD', 'class' => 'bg-success'],
                                            'termin' => ['text' => 'Termin', 'class' => 'bg-warning text-dark'],
                                            'dp'     => ['text' => ($item->nominal_dp && $item->nominal_dp > 0 ? 'DP Rp ' . number_format($item->nominal_dp, 0, ',', '.') : 'DP ' . $item->persen_dp . '%'), 'class' => 'bg-info'],
                                            default  => ['text' => '-', 'class' => 'bg-secondary'],
                                        };
                                    @endphp
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <span class="badge {{ $labelMetode['class'] }}">
                                            {{ $labelMetode['text'] }}
                                        </span>

                                        @if($adaKekurangan)
                                            <button type="button"
                                                    class="btn btn-sm mt-1"
                                                    style="background:#dd7045; color:#fff; font-size:11px; padding:2px 10px; border-radius:6px;"
                                                    onclick="bukaModalLunasi({{ $item->id }}, '{{ $item->kode_pembelian }}', {{ $kekurangan }}, '{{ $item->supplier->nama ?? '' }}')">
                                                <i class="bi bi-cash me-1"></i>Lunasi
                                            </button>
                                        @elseif($item->is_lunas && $item->metode_pembayaran !== 'cod')
                                            <span class="badge bg-success" style="font-size:10px;">✓ Lunas</span>
                                        @endif
                                    </div>
                                @else
                                    <button type="button"
                                            class="btn btn-sm"
                                            style="background:#606060; color:#fff; font-size:11px; padding:2px 10px;"
                                            onclick="bukaPembayaran({{ $item->id }}, '{{ $item->kode_pembelian }}', {{ $item->total }})">
                                        + Catat
                                    </button>
                                @endif
                            </td>

                            {{-- BARANG DITERIMA --}}
                            <td class="text-center">
                                @php
                                    $totalQty = $item->details->sum('qty');
                                    $totalReceived = $item->details->sum('qty_diterima');
                                    $isPartiallyReceived = $totalReceived > 0 && $totalReceived < $totalQty;
                                @endphp

                                @if($item->is_diterima)
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge bg-success">✓ Diterima</span>
                                        <small class="text-muted mt-1" style="font-size:10px;">
                                            {{ \Carbon\Carbon::parse($item->diterima_at)->format('d M Y') }}
                                        </small>
                                    </div>
                                @else
                                    @if($isPartiallyReceived)
                                        <div class="mb-1">
                                            <span class="badge bg-info text-white">Parsial ({{ number_format($totalReceived, 0) }}/{{ number_format($totalQty, 0) }})</span>
                                        </div>
                                    @endif

                                    @if(!$item->metode_pembayaran)
                                        <button type="button"
                                                class="btn btn-sm"
                                                disabled
                                                title="Metode pembayaran belum dicatat."
                                                style="background:#d0d0d0; color:#888; font-size:11px; padding:2px 10px; cursor:not-allowed;">
                                            Terima Barang
                                        </button>
                                    @else
                                        <button type="button"
                                                class="btn btn-sm text-white"
                                                style="background:#0284c7; font-size:11px; padding:2px 10px;"
                                                onclick="bukaModalTerimaBarang({{ $item->id }})">
                                            Terima Barang
                                        </button>
                                    @endif
                                @endif
                            </td>

                            {{-- AKSI --}}
                            <td class="text-center" style="width: 140px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                    {{-- Detail --}}
                                    <button type="button"
                                            class="btn btn-sm btn-info text-white rounded-2 px-2 py-1"
                                            onclick="bukaModalDetail({{ $item->id }})"
                                            title="Lihat Detail Pembelian">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    {{-- Cetak PO --}}
                                    <a href="{{ route('pembelian.cetak-pdf', $item->id) }}"
                                       class="btn btn-sm btn-danger text-white rounded-2 px-2 py-1"
                                       target="_blank" title="Cetak PO (PDF)">
                                        <i class="bi bi-printer"></i>
                                    </a>

                                    @if(!$item->isTerkunci())
                                        {{-- Edit --}}
                                        <button type="button"
                                                class="btn btn-sm btn-warning text-white rounded-2 px-2 py-1"
                                                onclick="bukaModalEdit({{ $item->id }})"
                                                title="Edit Pembelian">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        {{-- Hapus --}}
                                        <form action="{{ route('pembelian-kejingga.destroy', $item->id) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Yakin ingin menghapus transaksi pembelian {{ $item->kode_pembelian }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger rounded-2 px-2 py-1"
                                                    title="Hapus Pembelian">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Belum ada data pembelian Kejingga.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pembelian->hasPages())
            <div class="mt-3">
                {{ $pembelian->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL CATAT PEMBAYARAN -->
    <div class="modal fade" id="modalPembayaran" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Catat Metode Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formPembayaran" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <input type="hidden" id="pembelian_id_bayar" name="pembelian_id">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Kode Pembelian</label>
                            <input type="text" id="kode_pembelian_bayar" class="form-control font-monospace fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Metode Pembayaran</label>
                            <select name="metode_pembayaran" id="metode_pembayaran_select" class="form-select" required onchange="toggleMetodePembayaran(this.value)">
                                <option value="cod">COD (Bayar Lunas Saat Terima)</option>
                                <option value="dp">DP (Uang Muka)</option>
                                <option value="termin">Termin / Kredit</option>
                            </select>
                        </div>
                        <div id="section_dp" class="mb-3" style="display:none;">
                            <label class="form-label small text-muted">Nominal DP (Rp)</label>
                            <input type="number" name="nominal_dp" id="nominal_dp" class="form-control" placeholder="0">
                        </div>
                        <div id="section_termin" class="mb-3" style="display:none;">
                            <label class="form-label small text-muted">Tanggal Jatuh Tempo</label>
                            <input type="date" name="tanggal_jatuh_tempo" id="tanggal_jatuh_tempo" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL LUNASI -->
    <div class="modal fade" id="modalLunasi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Pelunasan Pembelian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formLunasi" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <input type="hidden" id="pembelian_id_lunasi" name="pembelian_id">
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-0">Kode Pembelian</label>
                            <input type="text" id="kode_pembelian_lunasi" class="form-control font-monospace fw-bold" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-0">Total Kekurangan</label>
                            <input type="text" id="kekurangan_text" class="form-control fw-bold text-danger" readonly>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm">Konfirmasi Pelunasan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TERIMA BARANG -->
    <div class="modal fade" id="modalTerimaBarang" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Konfirmasi Penerimaan Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formTerimaBarang" method="POST">
                    @csrf
                    <div class="modal-body pt-3">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th class="text-center" width="100">Pesanan</th>
                                        <th class="text-center" width="100">Diterima</th>
                                        <th class="text-center" width="120">Input Terima</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyTerimaBarang"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Proses Terima Stok</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Detail Transaksi Pembelian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3" id="contentDetail"></div>
            </div>
        </div>
    </div>

    <script>
    const dataPembayaranMap = @json($dataPembayaran);

    function bukaPembayaran(id, kode, total) {
        document.getElementById('pembelian_id_bayar').value = id;
        document.getElementById('kode_pembelian_bayar').value = kode;
        document.getElementById('formPembayaran').action = `/pembelian-kejingga/${id}/catat-pembayaran`;
        new bootstrap.Modal(document.getElementById('modalPembayaran')).show();
    }

    function toggleMetodePembayaran(val) {
        document.getElementById('section_dp').style.display = (val === 'dp') ? 'block' : 'none';
        document.getElementById('section_termin').style.display = (val === 'termin') ? 'block' : 'none';
    }

    function bukaModalLunasi(id, kode, sisa, supplier) {
        document.getElementById('pembelian_id_lunasi').value = id;
        document.getElementById('kode_pembelian_lunasi').value = kode;
        document.getElementById('kekurangan_text').value = 'Rp ' + sisa.toLocaleString('id-ID');
        document.getElementById('formLunasi').action = `/pembelian-kejingga/${id}/lunasi`;
        new bootstrap.Modal(document.getElementById('modalLunasi')).show();
    }

    function bukaModalTerimaBarang(id) {
        const item = dataPembayaranMap[id];
        if (!item) return;
        const tbody = document.getElementById('tbodyTerimaBarang');
        tbody.innerHTML = '';
        item.details.forEach(d => {
            const sisa = d.qty - d.qty_diterima;
            tbody.innerHTML += `
                <tr>
                    <td><strong>${d.nama}</strong> (${d.kode_barang})</td>
                    <td class="text-center">${d.qty} ${d.satuan}</td>
                    <td class="text-center">${d.qty_diterima} ${d.satuan}</td>
                    <td>
                        <input type="number" name="qty_diterima[${d.id}]" class="form-control form-control-sm text-center" value="${sisa > 0 ? sisa : 0}" min="0" max="${sisa}">
                    </td>
                </tr>
            `;
        });
        document.getElementById('formTerimaBarang').action = `/pembelian-kejingga/${id}/terima`;
        new bootstrap.Modal(document.getElementById('modalTerimaBarang')).show();
    }

    function bukaModalDetail(id) {
        const item = dataPembayaranMap[id];
        if (!item) return;
        let html = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Kode:</strong> ${item.kode}</p>
                    <p class="mb-1"><strong>Supplier:</strong> ${item.supplier_nama}</p>
                    <p class="mb-1"><strong>Tanggal:</strong> ${item.tanggal}</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-1"><strong>Gudang:</strong> ${item.gudang_nama}</p>
                    <p class="mb-1"><strong>Metode:</strong> ${item.label}</p>
                    <p class="mb-1"><strong>Total:</strong> Rp ${item.total.toLocaleString('id-ID')}</p>
                </div>
            </div>
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Barang</th>
                        <th class="text-center">Ordered</th>
                        <th class="text-center">Received</th>
                        <th class="text-end">Harga/Qty</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
        `;
        item.details.forEach(d => {
            html += `
                <tr>
                    <td>${d.nama} (${d.kode_barang})</td>
                    <td class="text-center">${d.qty} ${d.satuan}</td>
                    <td class="text-center">${d.qty_diterima} ${d.satuan}</td>
                    <td class="text-end">Rp ${d.harga_per_qty.toLocaleString('id-ID')}</td>
                    <td class="text-end">Rp ${d.harga.toLocaleString('id-ID')}</td>
                </tr>
            `;
        });
        html += `</tbody></table>`;
        document.getElementById('contentDetail').innerHTML = html;
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }
    </script>
</x-app-layout>

