<x-app-layout>
    <x-slot name="header">Pembelian</x-slot>

    <div class="container">

        <h4>Data Pembelian</h4>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            </div>
        @endif

        {{-- ALERT SARAN RESTOCK BAHAN BAKU GUDANG UTAMA --}}
        @if(!empty($countLowStockUtama) && $countLowStockUtama > 0)
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-left: 5px solid #f97316 !important;">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; font-size: 20px;">
                                <i class="bi bi-lightbulb-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">
                                    Saran Restock Bahan Baku Gudang Utama
                                </h6>
                                <p class="text-muted small mb-0">
                                    Terdapat <strong>{{ $countLowStockUtama }} item</strong> bahan baku yang stoknya di Gudang Utama sudah atau hampir mencapai batas minimum stok.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('pembelian.create') }}" class="btn btn-warning text-dark fw-bold shadow-sm">
                            <i class="bi bi-plus-circle-fill me-1"></i> Buat Pembelian & Lihat Saran
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="{{ route('pembelian.create') }}" class="btn btn-primary mb-0">
                Tambah Pembelian
            </a>

            <form action="{{ route('pembelian.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kode/supplier..." value="{{ request('search') }}" style="width: 220px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                @if(request('search'))
                    <a href="{{ route('pembelian.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
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
                            // Hitung sisa/kekurangan pembayaran
                            $total      = (float) $item->total;
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
                                // COD atau sudah lunas → tidak ada kekurangan
                                $item->metode_pembayaran === 'cod' => 0,
                                $item->is_lunas                    => 0,
                                // DP: sisa = total - nominal DP
                                $item->metode_pembayaran === 'dp'  => $total - $nominalDp,
                                // Termin: full amount belum dibayar
                                $item->metode_pembayaran === 'termin' => $total,
                                // Belum dicatat
                                default => 0,
                            };
    
                            $adaKekurangan = $kekurangan > 0 && !$item->is_lunas;
                        @endphp
                        <tr>
                            <td class="font-monospace" style="font-size:12px;">{{ $item->kode_pembelian }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d M Y') }}</td>
                            <td>{{ $item->supplier->nama ?? '-' }}</td>
                            <td>{{ $item->gudang->nama ?? '-' }}</td>
    
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
                                        <span class="badge {{ $labelMetode['class'] }}"
                                              style="cursor:pointer;"
                                              onclick="lihatDetailPembayaran({{ $item->id }})">
                                            {{ $labelMetode['text'] }} ℹ️
                                        </span>
    
                                        {{-- Tombol Lunasi --}}
                                        @if($adaKekurangan)
                                            <button type="button"
                                                    class="btn btn-sm mt-1"
                                                    style="background:#dd7045; color:#fff; font-size:11px; padding:2px 10px; border-radius:6px;"
                                                    onclick="bukaModalLunasi(
                                                        {{ $item->id }},
                                                        '{{ $item->kode_pembelian }}',
                                                        {{ $kekurangan }},
                                                        '{{ $item->supplier->nama ?? '' }}'
                                                    )">
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
                                                title="Metode pembayaran belum dicatat. Silakan catat metode pembayaran terlebih dahulu."
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
                            <td>
                                <div class="d-flex gap-1 flex-wrap justify-content-center">
                                    <a href="{{ route('pembelian.show', $item->id) }}"
                                       class="btn btn-sm"
                                       style="background:#606060; color:#fff; font-size:11px;">
                                        Detail
                                    </a>
                                    <a href="{{ route('pembelian.cetak-pdf', $item->id) }}"
                                       class="btn btn-sm btn-danger text-white"
                                       style="font-size:11px;" target="_blank" title="Cetak Purchase Order (PDF)">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> Cetak PO
                                    </a>
    
                                    @if(!$item->isTerkunci())
                                        <a href="{{ route('pembelian.edit', $item->id) }}"
                                           class="btn btn-sm"
                                           style="background:#606060; color:#fff; font-size:11px;">
                                            Edit
                                        </a>
                                        <form action="{{ route('pembelian.destroy', $item->id) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Yakin ingin menghapus {{ $item->kode_pembelian }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm"
                                                    style="background:#606060; color:#fff; font-size:11px;">
                                                Hapus
                                            </button>
                                        </form>
                                    @else
                                        <button class="btn btn-sm" disabled
                                                style="background:#d0d0d0; color:#888; font-size:11px;">Edit</button>
                                        <button class="btn btn-sm" disabled
                                                style="background:#d0d0d0; color:#888; font-size:11px;">Hapus</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Belum ada data pembelian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $pembelian->links() }}</div>

    </div>

    {{-- ══════════════════ MODAL: DETAIL PEMBAYARAN ══════════════════ --}}
    <div class="modal fade" id="modalDetailPembayaran" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" width="40%">Kode Pembelian</td><td><strong id="dp_kode"></strong></td></tr>
                        <tr><td class="text-muted">Total</td><td id="dp_total"></td></tr>
                        <tr><td class="text-muted">Metode</td><td id="dp_metode_badge"></td></tr>
                        <tr id="row_jatuh_tempo" class="d-none"><td class="text-muted">Jatuh Tempo</td><td id="dp_jatuh_tempo"></td></tr>
                        <tr id="row_nominal_dp" class="d-none"><td class="text-muted">Nominal DP</td><td id="dp_nominal"></td></tr>
                        <tr id="row_sisa_dp" class="d-none"><td class="text-muted">Sisa Pelunasan</td><td id="dp_sisa" class="fw-semibold text-danger"></td></tr>
                        <tr id="row_pelunasan_tgl" class="d-none"><td class="text-muted">Est. Pelunasan</td><td id="dp_pelunasan"></td></tr>
                        <tr id="row_catatan" class="d-none"><td class="text-muted">Catatan</td><td id="dp_catatan" class="fst-italic"></td></tr>
                        <tr><td class="text-muted">Dicatat Pada</td><td id="dp_dicatat_pada" class="text-muted small"></td></tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════ MODAL: CATAT PEMBAYARAN ══════════════════ --}}
    <div class="modal fade" id="modalPembayaran" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Catat Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formPembayaran" method="POST" action="" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted small" id="infoPembelian"></p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Metode Pembayaran</label>
                            <div class="d-flex gap-2">
                                <input type="radio" class="btn-check" name="metode_pembayaran" id="opt_cod" value="cod" onchange="toggleFieldPembayaran('cod')">
                                <label class="btn btn-outline-success" for="opt_cod">COD</label>
                                <input type="radio" class="btn-check" name="metode_pembayaran" id="opt_dp" value="dp" onchange="toggleFieldPembayaran('dp')">
                                <label class="btn btn-outline-info" for="opt_dp">DP</label>
                                <input type="radio" class="btn-check" name="metode_pembayaran" id="opt_termin" value="termin" onchange="toggleFieldPembayaran('termin')">
                                <label class="btn btn-outline-warning" for="opt_termin">Termin / Tempo</label>
                            </div>
                        </div>
                        <div id="field_termin" class="d-none">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold mb-0">Tanggal Jatuh Tempo</label>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" id="checkTanpaJatuhTempo" onchange="toggleTanpaJatuhTempo(this.checked)">
                                        <label class="form-check-label text-muted small" for="checkTanpaJatuhTempo">Tidak ada jatuh tempo (Fleksibel)</label>
                                    </div>
                                </div>
                                <input type="date" name="tanggal_jatuh_tempo" id="inputJatuhTempoTermin" class="form-control">
                                <small class="text-muted" id="hintTanpaJatuhTempo" style="display:none;">Tempo pembayaran fleksibel / kesepakatan personal.</small>
                            </div>
                        </div>
                        <div id="field_dp" class="d-none">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Persentase DP</label>
                                    <div class="input-group">
                                        <input type="number" name="persen_dp" id="inputPersenDP" class="form-control" min="1" max="99" placeholder="cth: 30" oninput="updateDariPersen()">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Nominal DP</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" name="nominal_dp" id="inputNominalDP" class="form-control" min="1" placeholder="cth: 150000" oninput="updateDariNominal()">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted" id="keteranganDP"></small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Estimasi Pelunasan</label>
                                <input type="date" name="tanggal_pelunasan" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan <span class="text-muted">(opsional)</span></label>
                            <textarea name="catatan_pembayaran" class="form-control" rows="2" placeholder="Mis: Transfer ke BCA 1234567..."></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Upload Bukti Pembayaran <span class="text-muted">(bisa >1 gambar)</span></label>
                            <input type="file" name="bukti_file[]" class="form-control" accept="image/*" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════ MODAL: LUNASI ══════════════════ --}}
    <div class="modal fade" id="modalLunasi" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#fff8f5; border-bottom:1px solid #f0ddd4;">
                    <h5 class="modal-title" style="color:#dd7045;">
                        <i class="bi bi-cash-coin me-2"></i>Catat Pelunasan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formLunasi" method="POST" action="" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">

                        {{-- Info ringkas --}}
                        <div class="p-3 mb-3 rounded" style="background:#f8f4f0; border:1px solid #eadfd4;">
                            <div class="row g-2" style="font-size:13px;">
                                <div class="col-6">
                                    <div class="text-muted" style="font-size:11px;">KODE PEMBELIAN</div>
                                    <div class="fw-semibold" id="lunasi_kode">—</div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted" style="font-size:11px;">SUPPLIER</div>
                                    <div class="fw-semibold" id="lunasi_supplier">—</div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted" style="font-size:11px;">SISA YANG HARUS DIBAYAR</div>
                                    <div class="fw-bold text-danger" id="lunasi_sisa" style="font-size:15px;">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nominal Pelunasan</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="nominal_pelunasan" id="inputNominalLunasi"
                                       class="form-control" min="1" placeholder="0">
                            </div>
                            <small class="text-muted">Masukkan jumlah yang dibayarkan untuk pelunasan</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan <span class="text-muted">(opsional)</span></label>
                            <textarea name="catatan_pelunasan" class="form-control" rows="2"
                                      placeholder="Mis: Transfer BCA tgl 29 Jun..."></textarea>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-semibold">Upload Bukti Pembayaran <span class="text-muted">(bisa >1 gambar)</span></label>
                            <input type="file" name="bukti_file[]" class="form-control" accept="image/*" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Tandai Lunas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════ MODAL TERIMA BARANG (QTY INPUT) ══════════════════ --}}
    <div class="modal fade" id="modalTerimaBarang" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="formTerimaBarang" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold">Konfirmasi Penerimaan Barang — <span id="terima_kode" class="text-primary"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="font-size:13px;">
                        <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:12px;">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <div>
                                Periksa dan sesuaikan <strong>Qty Barang Diterima</strong> di bawah ini.
                                Stok gudang dan batch FIFO akan ditambahkan secara proporsional sesuai Qty yang diterima.
                            </div>
                        </div>
                        <table class="table table-bordered align-middle mb-0" style="font-size:13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Barang</th>
                                    <th class="text-center" style="width:110px;">Qty Dipesan</th>
                                    <th class="text-center" style="width:110px;">Diterima Seb.</th>
                                    <th class="text-center" style="width:110px;">Sisa</th>
                                    <th class="text-center" style="width:150px;">Qty Diterima Baru</th>
                                    <th class="text-end" style="width:120px;">Harga/Unit</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyTerimaBarang">
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-success px-3">
                            <i class="bi bi-check-lg me-1"></i> Simpan & Terima Barang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════ SCRIPT ══════════════════ --}}
    <script>
        const dataPembayaran = @json($dataPembayaran);
        let totalAktif = 0;

        // ── Catat Pembayaran ──
        function bukaPembayaran(id, kode, total) {
            totalAktif = total;
            document.getElementById('formPembayaran').action = '/pembelian/' + id + '/catat-pembayaran';
            document.getElementById('infoPembelian').textContent = kode + ' · Total: Rp ' + Number(total).toLocaleString('id-ID');
            document.querySelectorAll('input[name=metode_pembayaran]').forEach(r => r.checked = false);
            
            const fieldDp = document.getElementById('field_dp');
            if (fieldDp) fieldDp.classList.add('d-none');

            const fieldTermin = document.getElementById('field_termin');
            if (fieldTermin) fieldTermin.classList.add('d-none');
            
            document.getElementById('inputPersenDP').value = '';
            document.getElementById('inputNominalDP').value = '';
            document.getElementById('keteranganDP').textContent = '';
            const inputJt = document.getElementById('inputJatuhTempoTermin');
            if (inputJt) {
                inputJt.value = '';
                inputJt.disabled = false;
            }
            const checkTjt = document.getElementById('checkTanpaJatuhTempo');
            if (checkTjt) checkTjt.checked = false;
            const hintTjt = document.getElementById('hintTanpaJatuhTempo');
            if (hintTjt) hintTjt.style.display = 'none';

            // Reset required
            const tglPelunasan = document.querySelector('#formPembayaran input[name=tanggal_pelunasan]');
            if (tglPelunasan) tglPelunasan.removeAttribute('required');
            new bootstrap.Modal(document.getElementById('modalPembayaran')).show();
        }

        function toggleTanpaJatuhTempo(isTanpaJatuhTempo) {
            const inputJt = document.getElementById('inputJatuhTempoTermin');
            const hintTjt = document.getElementById('hintTanpaJatuhTempo');
            if (inputJt) {
                if (isTanpaJatuhTempo) {
                    inputJt.value = '';
                    inputJt.disabled = true;
                    if (hintTjt) hintTjt.style.display = 'block';
                } else {
                    inputJt.disabled = false;
                    if (hintTjt) hintTjt.style.display = 'none';
                }
            }
        }

        function toggleFieldPembayaran(metode) {
            const fieldDp = document.getElementById('field_dp');
            if (fieldDp) fieldDp.classList.toggle('d-none', metode !== 'dp');

            const fieldTermin = document.getElementById('field_termin');
            if (fieldTermin) fieldTermin.classList.toggle('d-none', metode !== 'termin');

            const tglPelunasan = document.querySelector('#formPembayaran input[name=tanggal_pelunasan]');
            if (tglPelunasan) {
                if (metode === 'dp') {
                    tglPelunasan.setAttribute('required', 'required');
                } else {
                    tglPelunasan.removeAttribute('required');
                }
            }
        }

        function updateDariPersen() {
            const persen = parseFloat(document.getElementById('inputPersenDP').value) || 0;
            const nominal = Math.round(totalAktif * persen / 100);
            document.getElementById('inputNominalDP').value = nominal > 0 ? nominal : '';
            hitungKeteranganDP(nominal);
        }

        function updateDariNominal() {
            const nominal = parseFloat(document.getElementById('inputNominalDP').value) || 0;
            const persen = totalAktif > 0 ? Math.round((nominal / totalAktif) * 100) : 0;
            document.getElementById('inputPersenDP').value = persen > 0 ? persen : '';
            hitungKeteranganDP(nominal);
        }

        function hitungKeteranganDP(nominal) {
            const sisa = totalAktif - nominal;
            document.getElementById('keteranganDP').innerHTML =
                'DP = Rp ' + nominal.toLocaleString('id-ID') + ' · Sisa = <strong class="text-danger">Rp ' + sisa.toLocaleString('id-ID') + '</strong>';
        }

        // ── Detail Pembayaran ──
        function lihatDetailPembayaran(id) {
            const data = dataPembayaran[id];
            if (!data) return;
            const total = parseFloat(data.total);
            document.getElementById('dp_kode').textContent         = data.kode;
            document.getElementById('dp_total').textContent        = 'Rp ' + total.toLocaleString('id-ID');
            document.getElementById('dp_dicatat_pada').textContent = data.dicatat_pada ?? '-';
            const badgeClass = { cod: 'bg-success', termin: 'bg-warning text-dark', dp: 'bg-info' };
            document.getElementById('dp_metode_badge').innerHTML =
                `<span class="badge ${badgeClass[data.metode]}">${data.label}</span>`;
            ['row_jatuh_tempo','row_nominal_dp','row_sisa_dp','row_pelunasan_tgl','row_catatan']
                .forEach(rowId => document.getElementById(rowId).classList.add('d-none'));
            if (data.metode === 'termin') {
                document.getElementById('row_jatuh_tempo').classList.remove('d-none');
                document.getElementById('dp_jatuh_tempo').textContent = data.tanggal_jatuh_tempo ? data.tanggal_jatuh_tempo : 'Fleksibel / Kesepakatan Personal';
                document.getElementById('row_sisa_dp').classList.remove('d-none');
                document.getElementById('dp_sisa').textContent = 'Rp ' + total.toLocaleString('id-ID');
                if (data.tanggal_pelunasan) {
                    document.getElementById('row_pelunasan_tgl').classList.remove('d-none');
                    document.getElementById('dp_pelunasan').textContent = data.tanggal_pelunasan;
                }
            }
            if (data.metode === 'dp') {
                const nominalDP = parseFloat(data.nominal_dp) || Math.round(total * (data.persen_dp || 0) / 100);
                const persenDP = data.persen_dp || (total > 0 ? Math.round((nominalDP / total) * 100) : 0);
                const sisa = total - nominalDP;
                document.getElementById('row_nominal_dp').classList.remove('d-none');
                document.getElementById('row_sisa_dp').classList.remove('d-none');
                document.getElementById('dp_nominal').textContent = 'Rp ' + nominalDP.toLocaleString('id-ID') + ' (' + persenDP + '%)';
                document.getElementById('dp_sisa').textContent   = 'Rp ' + sisa.toLocaleString('id-ID');
                if (data.tanggal_pelunasan) {
                    document.getElementById('row_pelunasan_tgl').classList.remove('d-none');
                    document.getElementById('dp_pelunasan').textContent = data.tanggal_pelunasan;
                }
            }
            if (data.catatan) {
                document.getElementById('row_catatan').classList.remove('d-none');
                document.getElementById('dp_catatan').textContent = data.catatan;
            }
            new bootstrap.Modal(document.getElementById('modalDetailPembayaran')).show();
        }

        // ── Modal Lunasi ──
        function bukaModalLunasi(id, kode, kekurangan, supplier) {
            document.getElementById('formLunasi').action = '/pembelian/' + id + '/lunasi';
            document.getElementById('lunasi_kode').textContent     = kode;
            document.getElementById('lunasi_supplier').textContent = supplier;
            document.getElementById('lunasi_sisa').textContent     = 'Rp ' + Number(kekurangan).toLocaleString('id-ID');
            document.getElementById('inputNominalLunasi').value    = kekurangan;
            new bootstrap.Modal(document.getElementById('modalLunasi')).show();
        }

        // ── Modal Terima Barang (Input Qty Diterima) ──
        function bukaModalTerimaBarang(id) {
            const data = dataPembayaran[id];
            if (!data || !data.details) return;

            document.getElementById('formTerimaBarang').action = '/pembelian/' + id + '/terima';
            document.getElementById('terima_kode').textContent = data.kode;

            const tbody = document.getElementById('tbodyTerimaBarang');
            tbody.innerHTML = '';

            data.details.forEach(det => {
                const tr = document.createElement('tr');
                const qtyOrdered = Number(det.qty);
                const qtyReceivedSoFar = Number(det.qty_diterima || 0);
                const qtyRemaining = Math.max(0, qtyOrdered - qtyReceivedSoFar);
                
                tr.innerHTML = `
                    <td><strong>${det.nama}</strong></td>
                    <td class="text-center">${qtyOrdered} ${det.satuan}</td>
                    <td class="text-center text-success fw-semibold">${qtyReceivedSoFar} ${det.satuan}</td>
                    <td class="text-center text-danger fw-semibold">${qtyRemaining} ${det.satuan}</td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" max="${qtyRemaining}" 
                                   class="form-control text-center fw-bold" 
                                   name="qty_diterima[${det.id}]" 
                                   value="${qtyRemaining}" 
                                   required>
                            <span class="input-group-text">${det.satuan}</span>
                        </div>
                    </td>
                    <td class="text-end">Rp ${Number(det.harga_per_qty).toLocaleString('id-ID')}</td>
                `;
                tbody.appendChild(tr);
            });

            new bootstrap.Modal(document.getElementById('modalTerimaBarang')).show();
        }
    </script>

</x-app-layout>