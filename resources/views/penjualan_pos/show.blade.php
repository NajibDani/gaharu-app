<x-app-layout>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-gray-800 m-0">Detail Penjualan POS</h3>
        
        <div>
            <a href="{{ route('penjualan_pos.index') }}" class="btn btn-outline-secondary me-2 px-4">
                Kembali
            </a>

            <a href="{{ route('penjualan_pos.cetak-pdf', $penjualan->id) }}" class="btn btn-danger me-2 px-4 text-white" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Struk PDF
            </a>

            @php
                $user = auth()->user();
                $isSuperAdmin = $user && $user->isSuperAdmin();
            @endphp

            {{-- TOMBOL EDIT DAN APPROVE HANYA MUNCUL JIKA STATUS MASIH DRAFT ATAU SUPER ADMIN --}}
            @if(($penjualan->status ?? 'Draft') === 'Draft')
                <form action="{{ route('penjualan_pos.refresh-resep', $penjualan->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary px-3 fw-medium me-2" title="Perbarui status resep produk & estimasi HPP terbaru">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Resep
                    </button>
                </form>

                <a href="{{ route('penjualan_pos.edit', $penjualan->id) }}" class="btn btn-warning px-4 text-dark fw-medium me-2">
                    Edit Transaksi
                </a>

                <form action="{{ route('penjualan_pos.approve', $penjualan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin menyetujui transaksi ini? Stok Bahan Baku akan dipotong permanen berdasarkan FIFO. Item yang belum memiliki resep akan otomatis dipisahkan ke transaksi Draft baru.')">
                    @csrf
                    <button type="submit" class="btn btn-success px-4 fw-medium">
                        <i class="bi bi-check-circle me-1"></i> Approve
                    </button>
                </form>
            @elseif($isSuperAdmin && ($penjualan->status ?? '') !== 'VOID')
                <a href="{{ route('penjualan_pos.edit', $penjualan->id) }}" class="btn btn-warning px-4 text-dark fw-medium me-2" title="Koreksi Transaksi (Khusus Super Admin)">
                    <i class="bi bi-pencil-square me-1"></i> Koreksi Transaksi
                </a>

                <form action="{{ route('penjualan_pos.destroy', $penjualan->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('PERINGATAN SUPER ADMIN:\n\nTransaksi {{ $penjualan->kode_transaksi }} sudah di-Approve. Menghapus transaksi ini akan MENGEMBALIKAN stok bahan baku ke gudang dan menghapus jurnal akuntansi terkait.\n\nYakin ingin menghapus?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger px-4 text-white fw-medium">
                        <i class="bi bi-trash-fill me-1"></i> Hapus & Rollback
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $isDraft = ($penjualan->status ?? 'Draft') === 'Draft';
        $totalHpp = $penjualan->details ? $penjualan->details->sum(function($d) use ($isDraft) {
            $hpp = ($isDraft && ($d->hpp_satuan === null || $d->hpp_satuan <= 0))
                ? ($d->estimated_hpp ?? 0)
                : floatval($d->hpp_satuan);
            return $hpp * $d->qty;
        }) : 0;
        $labaKotor = $penjualan->total - $totalHpp;

        $unconfiguredRecipeCount = $penjualan->details ? $penjualan->details->filter(function($d) {
            return !($d->has_resep ?? ($d->produk ? $d->produk->hasResep() : false));
        })->count() : 0;
    @endphp

    <div class="row mb-4 align-items-stretch">
        
        <div class="col-md-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-muted mb-3 border-bottom pb-2">Informasi Transaksi</h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td width="130" class="text-muted">Kode Transaksi</td>
                            <td class="fw-semibold">: {{ $penjualan->kode_transaksi }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tanggal</td>
                            <td>: {{ \Carbon\Carbon::parse($penjualan->tanggal)->format('d-m-Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gudang</td>
                            <td>: {{ $penjualan->gudang->nama }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Input Oleh</td>
                            <td>: {{ $penjualan->creator->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>: 
                                @if(($penjualan->status ?? 'Draft') === 'Draft')
                                    <span class="badge bg-warning text-dark">Draft</span>
                                @else
                                    <span class="badge bg-success">Approved</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
                <div class="card-body d-flex flex-column justify-content-center">
                    
                    <div class="row text-center align-items-center">
                        <div class="col-12 mb-4">
                            <span class="text-muted text-uppercase" style="font-size: 0.85rem; letter-spacing: 1px;">Total Omzet (Penjualan)</span>
                            <h2 class="text-primary fw-bold mt-1 mb-0">
                                Rp {{ number_format($penjualan->total, 0, ',', '.') }}
                            </h2>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <hr class="m-0 text-muted" style="opacity: 0.15;">
                        </div>

                        <div class="col-6 border-end">
                            <span class="text-muted" style="font-size: 0.85rem;">
                                Total HPP @if($isDraft)<span class="badge bg-secondary-subtle text-secondary small">Estimasi</span>@endif
                            </span>
                            <h5 class="fw-medium text-secondary mt-1 mb-0">Rp {{ number_format($totalHpp, 0, ',', '.') }}</h5>
                        </div>
                        <div class="col-6">
                            <span class="text-muted" style="font-size: 0.85rem;">
                                Laba Kotor @if($isDraft)<span class="badge bg-secondary-subtle text-secondary small">Estimasi</span>@endif
                            </span>
                            <h5 class="text-success fw-bold mt-1 mb-0">Rp {{ number_format($labaKotor, 0, ',', '.') }}</h5>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    @if($unconfiguredRecipeCount > 0)
        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4 shadow-sm border border-warning" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2 fs-5 text-warning"></i>
                <div>
                    <strong>Perhatian:</strong> Terdapat <strong>{{ $unconfiguredRecipeCount }}</strong> produk terjual yang <strong>Belum Memiliki Resep</strong>.
                    @if($isDraft)
                        Saat di-Approve, item yang belum memiliki resep akan otomatis tertinggal (dipisahkan ke transaksi Draft baru) sampai resepnya selesai dibuat.
                    @else
                        HPP produk tersebut dihitung menggunakan harga beli terbaru di gudang / harga referensi.
                    @endif
                </div>
            </div>
            @if($isDraft)
                <div class="ms-3 flex-shrink-0">
                    <form action="{{ route('penjualan_pos.refresh-resep', $penjualan->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dark fw-semibold" title="Perbarui status resep produk">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Resep
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Rincian Produk Terjual</h6>
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Klik header kolom untuk mengurutkan (naik/turun)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-nowrap mb-0" id="table-rincian-produk">

                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4 sortable-th" style="cursor: pointer; user-select: none;" data-col="0" width="70" title="Klik untuk mengurutkan No">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span>No</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="sortable-th" style="cursor: pointer; user-select: none;" data-col="1" title="Klik untuk mengurutkan Nama Item">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span>Nama Item</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-center sortable-th" style="cursor: pointer; user-select: none;" data-col="2" width="100" title="Klik untuk mengurutkan Qty">
                                <div class="d-flex align-items-center justify-content-center">
                                    <span>Qty</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-end sortable-th" style="cursor: pointer; user-select: none;" data-col="3" title="Klik untuk mengurutkan Harga Jual">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span>Harga Jual</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-end sortable-th" style="cursor: pointer; user-select: none;" data-col="4" title="Klik untuk mengurutkan HPP / Unit">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span>HPP / Unit</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                            <th class="text-end pe-4 sortable-th" style="cursor: pointer; user-select: none;" data-col="5" title="Klik untuk mengurutkan Total Harga Jual">
                                <div class="d-flex align-items-center justify-content-end">
                                    <span>Total Harga Jual</span>
                                    <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon"></i>
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($penjualan->details as $key => $d)
                        @php
                            $itemHasResep = $d->has_resep ?? ($d->produk ? $d->produk->hasResep() : false);
                            $unitHpp = ($penjualan->status === 'Draft' && ($d->hpp_satuan === null || $d->hpp_satuan <= 0))
                                ? ($d->estimated_hpp ?? 0)
                                : floatval($d->hpp_satuan);
                        @endphp
                        <tr>
                            <td class="ps-4 text-muted" data-value="{{ $key + 1 }}">{{ $key + 1 }}</td>
                            <td class="fw-medium" data-value="{{ strtolower($d->produk->nama ?? 'Item') }}">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span>{{ $d->produk->nama ?? 'Item' }}</span>
                                    @if(!$itemHasResep)
                                        <span class="badge bg-warning text-dark border border-warning" style="font-size: 0.72rem;">
                                            <i class="bi bi-journal-x me-1"></i>Belum Memiliki Resep
                                        </span>
                                        @if($isDraft)
                                            <a href="{{ route('resep.index', ['search' => $d->produk->nama ?? '']) }}" target="_blank" class="btn btn-sm btn-outline-warning py-0 px-2 fw-medium text-dark d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; border-radius: 4px;" title="Buka menu resep untuk membuat formulasi produk ini">
                                                <i class="bi bi-plus-circle"></i> Buat Resep
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="text-center bg-light fw-semibold" data-value="{{ floatval($d->qty) }}">{{ $d->qty }}</td>
                            <td class="text-end" data-value="{{ floatval($d->harga) }}">Rp {{ number_format($d->harga, 0, ',', '.') }}</td>
                            <td class="text-end text-muted" data-value="{{ floatval($unitHpp) }}">
                                @if(($penjualan->status ?? '') === 'SUKSES' || $d->hpp_satuan > 0)
                                    <div class="fw-semibold text-dark">Rp {{ number_format($d->hpp_satuan, 0, ',', '.') }}</div>
                                    @if(!$itemHasResep)
                                        <div class="mt-1">
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                                                Belum Memiliki Resep
                                            </span>
                                        </div>
                                    @endif
                                @elseif($isDraft)
                                    @if($unitHpp > 0)
                                        <div class="fw-semibold text-dark">
                                            Rp {{ number_format($unitHpp, 0, ',', '.') }}
                                            <span class="badge bg-secondary-subtle text-secondary small" style="font-size: 0.65rem;">Estimasi</span>
                                        </div>
                                    @endif
                                    @if(!$itemHasResep)
                                        <div class="mt-1">
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                                                Belum Memiliki Resep
                                            </span>
                                        </div>
                                    @elseif($unitHpp <= 0)
                                        <span class="text-muted small"><em>(Draft)</em></span>
                                    @endif
                                @else
                                    <span class="text-muted small"><em>(Draft)</em></span>
                                @endif

                                <div class="mt-1">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary py-0 px-2 btn-cek-hpp d-inline-flex align-items-center gap-1"
                                            style="font-size: 0.72rem; border-radius: 4px;"
                                            data-detail-id="{{ $d->id }}"
                                            title="Lihat Rincian Resep & Harga Bahan">
                                        <i class="bi bi-receipt"></i> Cek Resep & Bahan
                                    </button>
                                </div>
                            </td>
                            <td class="text-end fw-medium pe-4" data-value="{{ floatval($d->subtotal) }}">Rp {{ number_format($d->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    </div>

</div>

<!-- MODAL DETAIL RESEP & HARGA BAHAN HPP -->
<div class="modal fade" id="modalRincianHpp" tabindex="-1" aria-labelledby="modalRincianHppLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-dark text-white border-0 px-4 py-3" style="border-radius: 12px 12px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modalRincianHppLabel">
                        <i class="bi bi-journal-text me-2 text-warning"></i>Rincian Resep & Komponen HPP
                    </h5>
                    <small class="text-white-50" id="modalSubTitle">Item Produk</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @if(($penjualan->status ?? '') === 'SUKSES')
            <div class="alert alert-info border-0 rounded-0 m-0 px-4 py-2" style="font-size: 0.8rem;">
                <i class="bi bi-info-circle-fill me-1"></i> <strong>Informasi:</strong> Tabel bahan baku di bawah menampilkan estimasi harga <strong>saat ini (Live)</strong>. Total HPP pada kotak biru di bawah menggunakan <strong>harga aktual (Historis)</strong> saat stok FIFO dipotong pada waktu transaksi disetujui.
            </div>
            @endif
            <div class="modal-body p-4 bg-light">
                <!-- INFO HEADER BOX -->
                <div class="card border-0 shadow-sm mb-3 rounded-3">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6 border-end">
                                <span class="text-muted text-uppercase d-block" style="font-size: 11px; letter-spacing: 0.5px;">Informasi Produk</span>
                                <h6 class="fw-bold text-dark mb-1" id="mInfoNamaProduk">-</h6>
                                <span class="text-muted small d-block">Kode: <span id="mInfoKodeProduk" class="fw-semibold">-</span> | Gudang: <span class="fw-semibold">{{ $penjualan->gudang->nama }}</span></span>
                            </div>
                            <div class="col-md-3 col-6 text-center border-end">
                                <span class="text-muted text-uppercase d-block" style="font-size: 11px; letter-spacing: 0.5px;">Qty Terjual</span>
                                <h5 class="fw-bold text-primary mb-0" id="mInfoQtyTerjual">0</h5>
                            </div>
                            <div class="col-md-3 col-6 text-center">
                                <span class="text-muted text-uppercase d-block" style="font-size: 11px; letter-spacing: 0.5px;">HPP / Unit</span>
                                <h5 class="fw-bold text-success mb-0" id="mInfoHppUnit">Rp 0</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STATE: HAS RECIPE -->
                <div id="mStateHasResep" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-secondary text-uppercase mb-0 small" style="font-size: 11px; letter-spacing: 0.5px;">
                            <i class="bi bi-list-check me-1"></i>Daftar Bahan Baku & Harga Satuan di Gudang
                        </h6>
                        <span class="badge bg-light text-muted border" id="mInfoOutputResep">Output: 1 Porsi</span>
                    </div>

                    <div class="table-responsive bg-white rounded-3 shadow-sm border border-light mb-3">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-dark small text-uppercase" style="font-size: 11px;">
                                <tr>
                                    <th class="ps-3" width="40">No</th>
                                    <th>Nama Bahan Baku</th>
                                    <th class="text-center" width="130">Kebutuhan Resep</th>
                                    <th class="text-end" width="150">Harga Bahan di Gudang</th>
                                    <th class="text-end" width="130">Biaya Bahan / Unit</th>
                                    <th class="text-center pe-3" width="150">Sumber Harga</th>
                                </tr>
                            </thead>
                            <tbody id="mTbodyBahan">
                                <!-- Dynamic rows -->
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end ps-3 fw-semibold text-muted">Biaya Bahan Baku (BBB):</td>
                                    <td class="text-end fw-bold text-dark" id="mTotalBiayaBahan">Rp 0</td>
                                    <td class="pe-3"></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end ps-3 fw-semibold text-muted">Biaya Tenaga Kerja & Overhead (BTKL & BOP 30%):</td>
                                    <td class="text-end fw-bold text-primary" id="mTotalBtklBop">Rp 0</td>
                                    <td class="pe-3"></td>
                                </tr>
                                <tr class="table-warning table-opacity-25 border-top border-2">
                                    <td colspan="4" class="text-end ps-3 fw-bold text-dark fs-6">Estimasi HPP / Unit Saat Ini (BBB + BTKL/BOP):</td>
                                    <td class="text-end fw-bold text-success fs-6" id="mGrandTotalHppUnit">Rp 0</td>
                                    <td class="pe-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 border-start border-primary border-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="d-block text-muted small text-uppercase" style="font-size: 10px;">Total HPP Transaksi untuk Item Ini</span>
                            <strong class="text-primary fs-6" id="mTotalHppDetail">Rp 0</strong>
                            <span class="text-muted small ms-1" id="mFormulaDetail">(Qty Terjual x HPP/Unit)</span>
                        </div>
                        <a href="#" id="mBtnLinkResep" target="_blank" class="btn btn-sm btn-outline-primary fw-medium">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Manajemen Resep
                        </a>
                    </div>
                </div>

                <!-- STATE: NO RECIPE -->
                <div id="mStateNoResep" class="d-none">
                    <div class="alert alert-warning border border-warning shadow-sm mb-3">
                        <div class="d-flex">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Menu Ini Belum Memiliki Formulasi Resep</h6>
                                <p class="mb-0 small text-muted">
                                    Item ini terjual tanpa daftar bahan baku (resep) yang terdaftar di sistem. Perhitungan HPP menggunakan harga acuan barang berikut:
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-body p-3">
                            <table class="table table-sm table-borderless mb-0" style="font-size: 13px;">
                                <tr>
                                    <td width="230" class="text-muted">Harga Beli Terbaru di Gudang:</td>
                                    <td class="fw-bold" id="mNoResepHargaTerbaru">Rp 0</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">HPP Referensi Master Barang:</td>
                                    <td class="fw-bold" id="mNoResepHppRef">Rp 0</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="text-dark fw-semibold pt-2">Nilai HPP / Unit yang Diterapkan:</td>
                                    <td class="text-success fw-bold fs-6 pt-2" id="mNoResepHppFinal">Rp 0</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('resep.index') }}" id="mBtnKelolaResepNoState" target="_blank" class="btn btn-warning text-dark fw-semibold btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Kelola Formulasi Resep di Menu Resep
                        </a>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 px-4 py-3 bg-light">
                <button type="button" class="btn btn-secondary fw-semibold px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .sortable-th:hover {
        background-color: #343a40 !important;
    }
    .sortable-th .sort-icon {
        transition: transform 0.15s ease-in-out;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('table-rincian-produk');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('th.sortable-th');

    let currentSortCol = null;
    let currentSortAsc = true;

    headers.forEach(th => {
        th.addEventListener('click', function () {
            const colIndex = parseInt(this.getAttribute('data-col'));

            if (currentSortCol === colIndex) {
                currentSortAsc = !currentSortAsc;
            } else {
                currentSortCol = colIndex;
                currentSortAsc = true;
            }

            // Reset all icons
            headers.forEach(h => {
                const icon = h.querySelector('.sort-icon');
                if (icon) {
                    icon.className = 'bi bi-arrow-down-up text-white-50 ms-1 sort-icon';
                }
            });

            // Update active icon
            const activeIcon = this.querySelector('.sort-icon');
            if (activeIcon) {
                activeIcon.className = currentSortAsc 
                    ? 'bi bi-sort-up text-warning ms-1 sort-icon' 
                    : 'bi bi-sort-down text-warning ms-1 sort-icon';
            }

            // Sort rows
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((rowA, rowB) => {
                const cellA = rowA.children[colIndex];
                const cellB = rowB.children[colIndex];

                let valA = cellA.hasAttribute('data-value') ? cellA.getAttribute('data-value') : cellA.textContent.trim();
                let valB = cellB.hasAttribute('data-value') ? cellB.getAttribute('data-value') : cellB.textContent.trim();

                const numA = parseFloat(valA);
                const numB = parseFloat(valB);

                let cmp = 0;
                if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                    cmp = numA - numB;
                } else {
                    cmp = valA.localeCompare(valB, 'id', { numeric: true, sensitivity: 'base' });
                }

                return currentSortAsc ? cmp : -cmp;
            });

            // Re-append sorted rows to tbody
            rows.forEach(r => tbody.appendChild(r));
        });
    });

    // ==========================================
    // MODAL RINCIAN HPP & RESEP
    // ==========================================
    const rincianHppData = {!! $rincianHppJson !!};

    const modalEl = document.getElementById('modalRincianHpp');
    const modalRincianHpp = modalEl ? new bootstrap.Modal(modalEl) : null;

    document.querySelectorAll('.btn-cek-hpp').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!modalRincianHpp) return;

            const detailId = this.getAttribute('data-detail-id');
            const data = rincianHppData[detailId];
            if (!data) return;

            // Set Header Box Info
            document.getElementById('modalSubTitle').textContent = data.nama_produk + ' (Kode: ' + data.kode_produk + ')';
            document.getElementById('mInfoNamaProduk').textContent = data.nama_produk;
            document.getElementById('mInfoKodeProduk').textContent = data.kode_produk;
            document.getElementById('mInfoQtyTerjual').textContent = Number(data.qty_terjual).toLocaleString('id-ID');
            document.getElementById('mInfoHppUnit').textContent = 'Rp ' + Number(data.hpp_satuan).toLocaleString('id-ID', { maximumFractionDigits: 2 });

            const rincian = data.rincian;
            const stateHasResep = document.getElementById('mStateHasResep');
            const stateNoResep = document.getElementById('mStateNoResep');

            if (rincian && rincian.has_resep && rincian.bahan && rincian.bahan.length > 0) {
                stateHasResep.classList.remove('d-none');
                stateNoResep.classList.add('d-none');

                document.getElementById('mInfoOutputResep').textContent = 'Kebutuhan Resep per 1 ' + (rincian.satuan_output || 'Porsi');

                const tbody = document.getElementById('mTbodyBahan');
                tbody.innerHTML = '';

                rincian.bahan.forEach((b, idx) => {
                    const tr = document.createElement('tr');
                    
                    let bsjBadge = '';
                    if (b.is_bsj) {
                        bsjBadge = '<span class="badge border ms-1" style="font-size: 10px; background-color: #f3e8ff; color: #7e22ce; border-color: #d8b4fe !important;"><i class="bi bi-layers me-1"></i>Bahan Setengah Jadi</span>';
                    }

                    let sumberBadge = '';
                    if (b.sumber_harga === 'Resep BSJ') {
                        sumberBadge = '<span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 10px;"><i class="bi bi-diagram-3 me-1"></i>Resep BSJ</span>';
                    } else if (b.sumber_harga === 'HPP Referensi') {
                        sumberBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 10px;"><i class="bi bi-bookmark me-1"></i>HPP Referensi</span>';
                    } else {
                        sumberBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;"><i class="bi bi-check-circle me-1"></i>Stok / Beli Gudang</span>';
                    }

                    const komposisiInfo = b.komposisi_resep 
                        ? `<div class="text-muted small mt-1 py-1 px-2 rounded-2 bg-light border border-light-subtle" style="font-size: 11px;">
                            <i class="bi bi-arrow-return-right text-info me-1"></i><span class="text-secondary fw-semibold">Komposisi:</span> ${b.komposisi_resep}
                           </div>` 
                        : '';

                    tr.innerHTML = `
                        <td class="ps-3 text-muted align-top pt-3">${idx + 1}</td>
                        <td class="align-top pt-3">
                            <div class="fw-semibold text-dark">${b.nama_bahan} ${bsjBadge}</div>
                            <small class="text-muted d-block">${b.kode_bahan}</small>
                            ${komposisiInfo}
                        </td>
                        <td class="text-center fw-medium align-top pt-3">${Number(b.qty_resep).toLocaleString('id-ID', { maximumFractionDigits: 2 })} ${b.satuan}</td>
                        <td class="text-end align-top pt-3">Rp ${Number(b.harga_satuan).toLocaleString('id-ID', { maximumFractionDigits: 2 })} <small class="text-muted">/${b.satuan}</small></td>
                        <td class="text-end fw-semibold text-dark align-top pt-3">Rp ${Number(b.biaya_per_unit).toLocaleString('id-ID', { maximumFractionDigits: 2 })}</td>
                        <td class="text-center pe-3 align-top pt-3">${sumberBadge}</td>
                    `;
                    tbody.appendChild(tr);
                });

                const totalBbb = Number(rincian.total_biaya_bahan || 0);
                const totalBtklBop = Number(rincian.total_btkl_bop || (totalBbb * 0.3));
                const totalHppPerUnit = Number(rincian.total_hpp || (totalBbb + totalBtklBop));

                document.getElementById('mTotalBiayaBahan').textContent = 'Rp ' + totalBbb.toLocaleString('id-ID', { maximumFractionDigits: 2 });
                document.getElementById('mTotalBtklBop').textContent = 'Rp ' + totalBtklBop.toLocaleString('id-ID', { maximumFractionDigits: 2 });
                document.getElementById('mGrandTotalHppUnit').textContent = 'Rp ' + totalHppPerUnit.toLocaleString('id-ID', { maximumFractionDigits: 2 });

                const hppPerUnitFinal = data.hpp_satuan > 0 ? data.hpp_satuan : totalHppPerUnit;
                document.getElementById('mInfoHppUnit').textContent = 'Rp ' + Number(hppPerUnitFinal).toLocaleString('id-ID', { maximumFractionDigits: 2 });

                const totalHppDetail = data.qty_terjual * hppPerUnitFinal;
                document.getElementById('mTotalHppDetail').textContent = 'Rp ' + Number(totalHppDetail).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                document.getElementById('mFormulaDetail').textContent = `(${Number(data.qty_terjual).toLocaleString('id-ID')} x Rp ${Number(hppPerUnitFinal).toLocaleString('id-ID', { maximumFractionDigits: 2 })})`;

                const linkResep = document.getElementById('mBtnLinkResep');
                if (rincian.resep_id) {
                    linkResep.href = '/resep/' + rincian.resep_id;
                    linkResep.classList.remove('d-none');
                } else {
                    linkResep.classList.add('d-none');
                }
            } else {
                stateHasResep.classList.add('d-none');
                stateNoResep.classList.remove('d-none');

                document.getElementById('mNoResepHargaTerbaru').textContent = 'Rp ' + Number(rincian ? (rincian.harga_terbaru || 0) : 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                document.getElementById('mNoResepHppRef').textContent = 'Rp ' + Number(rincian ? (rincian.hpp_referensi || 0) : 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                document.getElementById('mNoResepHppFinal').textContent = 'Rp ' + Number(data.hpp_satuan).toLocaleString('id-ID', { maximumFractionDigits: 2 });

                const btnKelola = document.getElementById('mBtnKelolaResepNoState');
                if (btnKelola) {
                    btnKelola.href = "{{ route('resep.index') }}?search=" + encodeURIComponent(data.nama_produk || '');
                }
            }

            modalRincianHpp.show();
        });
    });
});
</script>

</x-app-layout>