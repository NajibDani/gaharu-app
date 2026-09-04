<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
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
                                @php
                                    $user = auth()->user();
                                    $isSuperAdmin = $user && $user->isSuperAdmin();
                                @endphp

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
    
                                        {{-- Tombol Lunasi (Hanya Super Admin) --}}
                                        @if($adaKekurangan)
                                            @if($isSuperAdmin)
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
                                            @else
                                                <button type="button"
                                                        class="btn btn-sm mt-1"
                                                        disabled
                                                        title="Hanya Super Admin yang dapat menginput pembayaran pelunasan."
                                                        style="background:#d0d0d0; color:#888; font-size:11px; padding:2px 10px; cursor:not-allowed;">
                                                    <i class="bi bi-lock-fill me-1"></i>Lunasi
                                                </button>
                                            @endif
                                        @elseif($item->is_lunas && $item->metode_pembayaran !== 'cod')
                                            <span class="badge bg-success" style="font-size:10px;">✓ Lunas</span>
                                        @endif
                                    </div>
                                @else
                                    {{-- Tombol Catat Pembayaran (Super Admin & Gudang) --}}
                                    @php $isGudangUser = $user && $user->isGudang(); @endphp
                                    @if($isSuperAdmin || $isGudangUser)
                                        <button type="button"
                                                class="btn btn-sm"
                                                style="background:#606060; color:#fff; font-size:11px; padding:2px 10px;"
                                                onclick="bukaPembayaran({{ $item->id }}, '{{ $item->kode_pembelian }}', {{ $item->total }})">
                                            + Catat
                                        </button>
                                    @else
                                        <button type="button"
                                                class="btn btn-sm"
                                                disabled
                                                title="Hanya Super Admin atau pengguna Gudang yang dapat mencatat pembayaran pembelian."
                                                style="background:#d0d0d0; color:#888; font-size:11px; padding:2px 10px; cursor:not-allowed;">
                                            <i class="bi bi-lock-fill me-1"></i>+ Catat
                                        </button>
                                    @endif
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

                                    @php
                                        $user = auth()->user();
                                        $isSuperAdmin = $user && $user->isSuperAdmin();
                                        $isGudangUser = $user && $user->isGudang();
                                        $bisaHapusBelumBayar = $isSuperAdmin || $isGudangUser;
                                    @endphp

                                    @if(!$item->isTerkunci())
                                        {{-- Edit --}}
                                        <button type="button"
                                                class="btn btn-sm btn-warning text-white rounded-2 px-2 py-1"
                                                onclick="bukaModalEdit({{ $item->id }})"
                                                title="Edit Pembelian">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        {{-- Hapus --}}
                                        @if($bisaHapusBelumBayar)
                                            <form action="{{ route('pembelian.destroy', $item->id) }}"
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
                                    @else
                                        @if($isSuperAdmin)
                                            <form action="{{ route('pembelian.destroy', $item->id) }}"
                                                  method="POST" class="d-inline"
                                                  onsubmit="return confirm('PERINGATAN SUPER ADMIN:\n\nTransaksi {{ $item->kode_pembelian }} sudah diterima/lunas. Menghapus transaksi ini akan ME-ROLLBACK / MENGURANGI stok gudang, menghapus batch FIFO terkait, dan menghapus jurnal akuntansi pembelian.\n\nApakah Anda yakin ingin melanjutkan penghapusan?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger rounded-2 px-2 py-1"
                                                        title="Hapus & Rollback Stok (Super Admin)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
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

    {{-- ══════════════════ MODAL: DETAIL PEMBELIAN (MINIMALIST POP UP) ══════════════════ --}}
    <div class="modal fade" id="modalDetailPembelian" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-light py-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-receipt fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="detail_modal_title">Detail Pembelian</h5>
                            <small class="text-muted" id="detail_modal_subtitle">Rincian pesanan dan status transaksi</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="modalDetailPembelianBody">
                    {{-- Diisi secara dinamis via JS --}}
                </div>
                <div class="modal-footer bg-light py-2 border-top d-flex justify-content-between">
                    <div id="modalDetailLeftActions">
                        <a href="#" id="detail_btn_cetak_po" target="_blank" class="btn btn-sm btn-danger text-white">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak PO (PDF)
                        </a>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════ MODAL: EDIT PEMBELIAN (MINIMALIST POP UP) ══════════════════ --}}
    <div class="modal fade" id="modalEditPembelian" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-light py-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-pencil-square fs-5 text-dark"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0">Edit Pembelian — <span id="edit_modal_kode" class="text-primary"></span></h5>
                            <small class="text-muted">Perbarui informasi supplier, tanggal, atau rincian item barang</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditPembelian" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold text-dark small">Supplier <span class="text-danger">*</span></label>
                                <select name="supplier_id" id="edit_supplier_id" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Supplier --</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold text-dark small">Gudang Tujuan <span class="text-danger">*</span></label>
                                <select name="gudang_id" id="edit_gudang_id" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Gudang --</option>
                                    @foreach($gudangs as $gudang)
                                        <option value="{{ $gudang->id }}">{{ $gudang->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold text-dark small">Tanggal Transaksi <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" id="edit_tanggal" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 pt-2 border-top">
                            <h6 class="fw-bold mb-0 text-dark small"><i class="bi bi-boxes me-1 text-primary"></i> Daftar Barang Pembelian</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item-modal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Baris Barang
                            </button>
                        </div>

                        <div class="table-responsive border rounded-3 mb-3" style="max-height: 380px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0 text-center" id="table-items-modal" style="font-size: 13px;">
                                <thead class="table-light sticky-top" style="z-index: 2;">
                                    <tr>
                                        <th class="text-start" style="min-width: 250px;">Barang <span class="text-danger">*</span></th>
                                        <th style="width: 170px;">Qty <span class="text-danger">*</span></th>
                                        <th style="width: 170px;">Harga Total Item (Rp) <span class="text-danger">*</span></th>
                                        <th style="width: 160px;">Nomor Batch</th>
                                        <th style="width: 50px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyEditItems">
                                    {{-- Diisi via JS --}}
                                </tbody>
                            </table>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-12 col-md-5">
                                <div class="card border-0 rounded-3 p-3 bg-light">
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold text-secondary small mb-1">Biaya Tambahan (Tax / Service / Ongkir)</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white fw-semibold text-muted">Rp</span>
                                            <input type="text" name="tax_service" id="edit_tax_service" class="form-control mask-number fw-bold text-end bg-white" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span class="fw-bold text-dark small">Grand Total Estimasi:</span>
                                        <span class="fw-bold text-success fs-6" id="edit_grand_total">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 border-top">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary px-3">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════ SCRIPT ══════════════════ --}}
    <script>
        const dataPembayaran = @json($dataPembayaran);
        const masterBarangs = @json($barangs);
        let totalAktif = 0;
        let editRowIndex = 0;
        let modalTomSelectInstances = [];

        // ── Modal Detail Pembelian ──
        function bukaModalDetail(id) {
            const data = dataPembayaran[id];
            if (!data) return;

            document.getElementById('detail_modal_title').textContent = data.kode;
            document.getElementById('detail_btn_cetak_po').href = '/pembelian/' + id + '/cetak-pdf';

            const container = document.getElementById('modalDetailPembelianBody');
            
            let paymentStatusBadge = data.is_lunas 
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check2-circle me-1"></i>Lunas</span>'
                : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-clock me-1"></i>Belum Lunas</span>';
            
            let receiveStatusBadge = data.is_diterima
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-box-seam me-1"></i>Diterima</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Menunggu Diterima</span>';

            let metodeDisplay = data.label || 'Belum Dicatat';
            let infoPayment = '';
            if (data.metode === 'dp') {
                infoPayment = `<div class="text-muted small mt-1">DP: Rp ${Number(data.nominal_dp || 0).toLocaleString('id-ID')} (${data.persen_dp}%) | Est. Pelunasan: ${data.tanggal_pelunasan || '-'}</div>`;
            } else if (data.metode === 'termin') {
                infoPayment = `<div class="text-muted small mt-1">Jatuh Tempo: ${data.tanggal_jatuh_tempo || 'Fleksibel'}</div>`;
            }

            let itemsTableRows = '';
            let subtotalItems = 0;
            if (data.details && data.details.length > 0) {
                data.details.forEach((det, idx) => {
                    const hasKonv = det.has_konversi;
                    const konv = Number(det.konversi_pembelian || 1);
                    const sUtama = det.satuan_utama || det.satuan;
                    const sPembelian = det.satuan_pembelian || det.satuan;
                    const totalUtama = det.qty * konv;
                    const hargaPerUnit = Number(det.harga_per_qty || 0);
                    const subtotal = det.qty * hargaPerUnit;
                    subtotalItems += subtotal;

                    itemsTableRows += `
                        <tr>
                            <td class="text-center text-muted">${idx + 1}</td>
                            <td class="text-start">
                                <div class="fw-semibold text-dark">${det.nama}</div>
                                <small class="text-muted font-monospace">${det.kode_barang || ''}</small>
                                ${hasKonv ? `<div class="text-muted" style="font-size:10.5px;">1 ${sPembelian} = ${konv.toLocaleString('id-ID')} ${sUtama}</div>` : ''}
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-dark">${Number(det.qty).toLocaleString('id-ID')} ${det.satuan}</span>
                                ${hasKonv ? `<div class="text-primary small" style="font-size:11px;">= ${Number(totalUtama).toLocaleString('id-ID')} ${sUtama}</div>` : ''}
                            </td>
                            <td class="text-end">
                                Rp ${Number(hargaPerUnit).toLocaleString('id-ID')}
                                ${hasKonv && konv > 0 ? `<div class="text-muted small" style="font-size:10.5px;">(~Rp ${Number(hargaPerUnit / konv).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2})} / ${sUtama})</div>` : ''}
                            </td>
                            <td class="text-end fw-semibold text-dark">
                                Rp ${Number(subtotal).toLocaleString('id-ID')}
                            </td>
                            <td class="text-center font-monospace small text-muted">
                                <span class="badge bg-light text-dark border">${det.batch_number || '-'}</span>
                            </td>
                        </tr>
                    `;
                });
            }

            let taxServiceVal = Number(data.tax_service || 0);
            let grandTotalVal = Number(data.total || (subtotalItems + taxServiceVal));

            container.innerHTML = `
                <!-- Ringkasan Info Header -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <span class="text-muted small text-uppercase fw-bold d-block mb-1">Supplier</span>
                            <span class="fs-6 fw-bold text-dark d-block">${data.supplier_nama}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <span class="text-muted small text-uppercase fw-bold d-block mb-1">Gudang Tujuan</span>
                            <span class="fs-6 fw-bold text-dark d-block">${data.gudang_nama}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <span class="text-muted small text-uppercase fw-bold d-block mb-1">Tanggal Transaksi</span>
                            <span class="fs-6 fw-bold text-dark d-block">${data.tanggal}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <span class="text-muted small text-uppercase fw-bold d-block mb-1">Status Transaksi</span>
                            <div class="d-flex flex-column gap-1 align-items-start">
                                ${paymentStatusBadge}
                                ${receiveStatusBadge}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Detail Barang -->
                <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-box-seam me-1 text-primary"></i> Rincian Barang</h6>
                <div class="table-responsive border rounded-3 mb-3">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">No</th>
                                <th class="text-start">Barang</th>
                                <th class="text-center" style="width: 140px;">Qty Dipesan</th>
                                <th class="text-end" style="width: 140px;">Harga Satuan</th>
                                <th class="text-end" style="width: 140px;">Subtotal</th>
                                <th class="text-center" style="width: 150px;">Batch Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsTableRows}
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Subtotal Barang</th>
                                <th class="text-end">Rp ${Number(subtotalItems).toLocaleString('id-ID')}</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end text-muted font-normal">Biaya Tambahan (Tax / Service / Ongkir)</th>
                                <th class="text-end">Rp ${Number(taxServiceVal).toLocaleString('id-ID')}</th>
                                <th></th>
                            </tr>
                            <tr class="table-primary fw-bold">
                                <th colspan="4" class="text-end">Grand Total</th>
                                <th class="text-end text-primary fs-6">Rp ${Number(grandTotalVal).toLocaleString('id-ID')}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Info Pembayaran -->
                <div class="card border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block">Metode Pembayaran</span>
                            <span class="fw-bold text-dark">${metodeDisplay}</span>
                            ${infoPayment}
                        </div>
                        <div class="text-end">
                            <span class="text-muted small fw-semibold text-uppercase d-block">Kekurangan / Sisa Bayar</span>
                            <span class="fw-bold ${data.is_lunas ? 'text-success' : 'text-danger'} fs-6">
                                ${data.is_lunas ? 'Lunas (Rp 0)' : 'Rp ' + Number(data.kekurangan || 0).toLocaleString('id-ID')}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            new bootstrap.Modal(document.getElementById('modalDetailPembelian')).show();
        }

        // ── Helper TomSelect Modal Edit ──
        function initModalBarangSelect(selectEl) {
            if (!selectEl || selectEl.tomselect) return;
            const ts = new TomSelect(selectEl, {
                create: false,
                placeholder: '-- Pilih Barang --',
                allowEmptyOption: true,
                maxOptions: 500,
                onChange: function(value) {
                    selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            modalTomSelectInstances.push(ts);
        }

        function destroyModalTomSelects() {
            modalTomSelectInstances.forEach(ts => {
                if (ts) ts.destroy();
            });
            modalTomSelectInstances = [];
        }

        function updateModalQtyHint(row) {
            const select = row.querySelector('.barang-select');
            const qtyInput = row.querySelector('.qty-input');
            const hint = row.querySelector('.qty-hint');
            if (!select || !hint) return;

            const opt = select.querySelector(`option[value="${select.value}"]`);
            if (!opt || select.value === '') {
                hint.textContent = '';
                return;
            }

            const satuanPembelian = opt.dataset.satuanPembelian || '';
            const konversi = parseFloat(opt.dataset.konversiPembelian) || 1.00;
            const satuanUtama = opt.dataset.satuanUtama || 'Pcs';
            const qtyVal = getCleanNumber(qtyInput ? qtyInput.value : 0);

            if (satuanPembelian && konversi > 1 && satuanPembelian !== satuanUtama) {
                const totalUtama = qtyVal * konversi;
                hint.innerHTML = `Satuan: <strong>${satuanPembelian}</strong><br><span class="text-primary">= ${Number(totalUtama).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2})} ${satuanUtama}</span> (1 ${satuanPembelian} = ${Number(konversi).toLocaleString('id-ID')} ${satuanUtama})`;
            } else {
                hint.innerHTML = `Satuan: <strong>${satuanUtama}</strong>`;
            }
        }

        function calcModalGrandTotal() {
            let total = 0;
            document.querySelectorAll('#tbodyEditItems .harga-input').forEach(input => {
                total += getCleanNumber(input.value);
            });
            const taxService = getCleanNumber(document.getElementById('edit_tax_service').value);
            total += taxService;
            document.getElementById('edit_grand_total').textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
        }

        function generateBarangOptionsHtml(selectedId = '') {
            let html = '<option value="">-- Pilih Barang --</option>';
            masterBarangs.forEach(b => {
                const selected = String(b.id) === String(selectedId) ? 'selected' : '';
                html += `
                    <option value="${b.id}"
                        data-kode="${b.kode_barang}"
                        data-satuan-pembelian="${b.satuan_pembelian || ''}"
                        data-konversi-pembelian="${b.konversi_pembelian || 1}"
                        data-satuan-utama="${b.satuan || 'Pcs'}"
                        ${selected}>
                        ${b.kode_barang} - ${b.nama}
                    </option>
                `;
            });
            return html;
        }

        function addModalItemRow(barangId = '', qty = '', harga = '', batch = '') {
            const tbody = document.getElementById('tbodyEditItems');
            const tr = document.createElement('tr');
            tr.className = 'item-row';

            tr.innerHTML = `
                <td class="text-start">
                    <select name="items[${editRowIndex}][barang_id]" class="form-select form-select-sm barang-select" required>
                        ${generateBarangOptionsHtml(barangId)}
                    </select>
                </td>
                <td>
                    <input type="text" name="items[${editRowIndex}][qty]" class="form-control form-control-sm text-center qty-input mask-number fw-bold" value="${qty}" required placeholder="0">
                    <small class="text-muted qty-hint d-block mt-1" style="font-size: 10px;"></small>
                </td>
                <td>
                    <input type="text" name="items[${editRowIndex}][harga]" class="form-control form-control-sm text-end harga-input mask-number fw-bold" value="${harga}" required placeholder="0">
                </td>
                <td>
                    <input type="text" name="items[${editRowIndex}][batch_number]" class="form-control form-control-sm" value="${batch}" placeholder="Otomatis">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 btn-remove-item" title="Hapus">
                        <i class="bi bi-x-circle fs-5"></i>
                    </button>
                </td>
            `;

            tbody.appendChild(tr);

            const selectEl = tr.querySelector('.barang-select');
            initModalBarangSelect(selectEl);
            updateModalQtyHint(tr);

            editRowIndex++;
            calcModalGrandTotal();
        }

        // ── Modal Edit Pembelian ──
        function bukaModalEdit(id) {
            const data = dataPembayaran[id];
            if (!data) return;

            destroyModalTomSelects();

            document.getElementById('edit_modal_kode').textContent = data.kode;
            document.getElementById('formEditPembelian').action = '/pembelian/' + id;

            document.getElementById('edit_supplier_id').value = data.supplier_id || '';
            document.getElementById('edit_gudang_id').value = data.gudang_id || '';
            document.getElementById('edit_tanggal').value = data.tanggal_raw || '';
            document.getElementById('edit_tax_service').value = Number(data.tax_service || 0).toLocaleString('id-ID');

            const tbody = document.getElementById('tbodyEditItems');
            tbody.innerHTML = '';
            editRowIndex = 0;

            if (data.details && data.details.length > 0) {
                data.details.forEach(det => {
                    const formattedQty = Number(det.qty).toLocaleString('id-ID');
                    const formattedHarga = Number(det.harga || (det.qty * det.harga_per_qty)).toLocaleString('id-ID');
                    addModalItemRow(det.barang_id, formattedQty, formattedHarga, det.batch_number || '');
                });
            } else {
                addModalItemRow();
            }

            new bootstrap.Modal(document.getElementById('modalEditPembelian')).show();
        }

        document.getElementById('btn-add-item-modal').addEventListener('click', function () {
            addModalItemRow();
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.btn-remove-item')) {
                const rows = document.querySelectorAll('#tbodyEditItems .item-row');
                if (rows.length > 1) {
                    e.target.closest('.item-row').remove();
                    calcModalGrandTotal();
                } else {
                    alert('Minimal harus ada 1 barang pada pesanan.');
                }
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('barang-select')) {
                const row = e.target.closest('.item-row');
                if (row) updateModalQtyHint(row);
            }
        });

        document.getElementById('edit_tax_service').addEventListener('input', calcModalGrandTotal);

        // ── Number Masking (Indonesian Format) ──
        function getCleanNumber(val) {
            if (!val) return 0;
            let clean = String(val).replace(/\./g, '').replace(/,/g, '.');
            return parseFloat(clean) || 0;
        }

        function formatNumberIndonesian(value) {
            let parts = value.replace(/[^0-9,]/g, '').split(',');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            if (parts.length > 2) {
                parts = [parts[0], parts.slice(1).join('')];
            }
            return parts.join(',');
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('mask-number')) {
                let cursorPosition = e.target.selectionStart;
                let originalLength = e.target.value.length;
                
                let formatted = formatNumberIndonesian(e.target.value);
                e.target.value = formatted;
                
                let newLength = formatted.length;
                e.target.selectionStart = cursorPosition + (newLength - originalLength);
                e.target.selectionEnd = cursorPosition + (newLength - originalLength);

                if (e.target.classList.contains('harga-input')) {
                    calcModalGrandTotal();
                }
            }

            if (e.target.classList.contains('qty-input')) {
                const row = e.target.closest('.item-row');
                if (row) updateModalQtyHint(row);
            }
        });

        document.getElementById('formEditPembelian').addEventListener('submit', function (e) {
            document.querySelectorAll('#modalEditPembelian .mask-number').forEach(input => {
                input.value = getCleanNumber(input.value);
            });
        });

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
            const fieldTermin = document.getElementById('field_termin');
            if (fieldDp) fieldDp.classList.toggle('d-none', metode !== 'dp');
            if (fieldTermin) fieldTermin.classList.toggle('d-none', metode !== 'termin');
        }

        function toggleTanpaJatuhTempo(checked) {
            const input = document.getElementById('inputJatuhTempoTermin');
            const hint = document.getElementById('hintTanpaJatuhTempo');
            if (input) {
                input.disabled = checked;
                if (checked) input.value = '';
            }
            if (hint) {
                hint.style.display = checked ? 'block' : 'none';
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
                const hasKonv = det.has_konversi;
                const konv = Number(det.konversi_pembelian || 1);
                
                let orderedText = `<strong>${qtyOrdered} ${det.satuan}</strong>`;
                if (hasKonv) {
                    orderedText += `<div class="text-primary small" style="font-size:10.5px;">= ${(qtyOrdered * konv).toLocaleString('id-ID')} ${det.satuan_utama}</div>`;
                }

                let receivedText = `<span class="text-success fw-semibold">${qtyReceivedSoFar} ${det.satuan}</span>`;
                if (hasKonv && qtyReceivedSoFar > 0) {
                    receivedText += `<div class="text-muted small" style="font-size:10px;">= ${(qtyReceivedSoFar * konv).toLocaleString('id-ID')} ${det.satuan_utama}</div>`;
                }

                let remainingText = `<span class="text-danger fw-semibold">${qtyRemaining} ${det.satuan}</span>`;
                if (hasKonv && qtyRemaining > 0) {
                    remainingText += `<div class="text-danger small" style="font-size:10px;">= ${(qtyRemaining * konv).toLocaleString('id-ID')} ${det.satuan_utama}</div>`;
                }

                tr.innerHTML = `
                    <td>
                        <strong>${det.nama}</strong>
                        ${hasKonv ? `<div class="text-muted" style="font-size:10px;">1 ${det.satuan_pembelian} = ${konv.toLocaleString('id-ID')} ${det.satuan_utama}</div>` : ''}
                    </td>
                    <td class="text-center">${orderedText}</td>
                    <td class="text-center">${receivedText}</td>
                    <td class="text-center">${remainingText}</td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" max="${qtyRemaining}" 
                                   class="form-control text-center fw-bold" 
                                   name="qty_diterima[${det.id}]" 
                                   value="${qtyRemaining}" 
                                   required>
                            <span class="input-group-text">${det.satuan}</span>
                        </div>
                        ${hasKonv ? `<small class="text-muted d-block text-center mt-1" style="font-size:10px;">Stok masuk: <strong>${(qtyRemaining * konv).toLocaleString('id-ID')} ${det.satuan_utama}</strong></small>` : ''}
                    </td>
                    <td class="text-end">Rp ${Number(det.harga_per_qty).toLocaleString('id-ID')}</td>
                `;
                tbody.appendChild(tr);
            });

            new bootstrap.Modal(document.getElementById('modalTerimaBarang')).show();
        }
    </script>

</x-app-layout>