<x-app-layout>
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Terdapat kesalahan pada pengisian:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="background-color: #fffbeb; border-left: 4px solid #f59e0b !important;">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
            <div>{{ session('warning') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1 text-dark" style="font-size: 1.75rem;">Preparation Bahan</h2>
            <p class="text-muted mb-0" style="font-size: 0.95rem;">
                Catat pengolahan bahan baku menjadi bahan setengah jadi. Stok berubah setelah disetujui.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ request()->fullUrl() }}" class="btn btn-outline-brown rounded-3 fw-semibold px-3 py-2 shadow-sm d-flex align-items-center gap-1" 
               title="Hitung ulang & segarkan ketersediaan stok bahan baku saat ini">
                <i class="bi bi-arrow-repeat fs-6"></i> Refresh Stok
            </a>
            <button type="button" class="btn text-white fw-semibold px-4 py-2 shadow-sm rounded-3" 
                    style="background-color: #704828; font-size: 0.95rem;"
                    data-bs-toggle="modal" data-bs-target="#modalCreatePreparation">
                <i class="bi bi-plus-lg me-1"></i> Buat Preparation
            </button>
        </div>
    </div>

    {{-- MAIN CARD --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: #ffffff; border: 1px solid #e5e7eb !important;">
        
        {{-- FILTER TABS & SEARCH BAR --}}
        <div class="p-3 border-bottom bg-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                {{-- Tabs --}}
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('preparation-bahan.index', array_merge(request()->except('tab', 'page'), ['tab' => 'semua'])) }}" 
                       class="btn rounded-pill px-4 py-1 fw-semibold {{ $tab === 'semua' ? 'btn-brown-active text-white' : 'btn-tab-inactive' }}">
                        Semua ({{ $tabCounts['semua'] }})
                    </a>
                    <a href="{{ route('preparation-bahan.index', array_merge(request()->except('tab', 'page'), ['tab' => 'menunggu'])) }}" 
                       class="btn rounded-pill px-4 py-1 fw-semibold {{ $tab === 'menunggu' ? 'btn-brown-active text-white' : 'btn-tab-inactive' }}">
                        Menunggu ({{ $tabCounts['menunggu'] }})
                    </a>
                    <a href="{{ route('preparation-bahan.index', array_merge(request()->except('tab', 'page'), ['tab' => 'disetujui'])) }}" 
                       class="btn rounded-pill px-4 py-1 fw-semibold {{ $tab === 'disetujui' ? 'btn-brown-active text-white' : 'btn-tab-inactive' }}">
                        Disetujui ({{ $tabCounts['disetujui'] }})
                    </a>
                    <a href="{{ route('preparation-bahan.index', array_merge(request()->except('tab', 'page'), ['tab' => 'ditolak'])) }}" 
                       class="btn rounded-pill px-4 py-1 fw-semibold {{ $tab === 'ditolak' ? 'btn-brown-active text-white' : 'btn-tab-inactive' }}">
                        Ditolak ({{ $tabCounts['ditolak'] }})
                    </a>
                </div>

                {{-- Filter & Search Form --}}
                <form action="{{ route('preparation-bahan.index') }}" method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    
                    <select name="gudang_id" class="form-select form-select-sm rounded-3" style="width: 170px;" onchange="this.form.submit()">
                        <option value="">-- Semua Gudang --</option>
                        @foreach($gudangs as $g)
                            <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>
                                {{ $g->nama }}
                            </option>
                        @endforeach
                    </select>

                    <div class="input-group input-group-sm" style="width: 240px;">
                        <input type="text" name="search" class="form-control rounded-start-3" 
                               placeholder="Cari No / Bahan..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary rounded-end-3" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    @if(request()->hasAny(['search', 'gudang_id', 'divisi_id']))
                        <a href="{{ route('preparation-bahan.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger rounded-3" title="Reset Filter">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="min-width: 900px;">
                <thead style="background-color: #704828; color: #ffffff;">
                    <tr class="text-white" style="font-size: 0.92rem;">
                        <th class="ps-4 py-3" style="width: 14%;">No. / Tanggal</th>
                        <th class="py-3" style="width: 16%;">Gudang Asal</th>
                        <th class="py-3" style="width: 26%;">Bahan Baku Dipakai</th>
                        <th class="py-3" style="width: 18%;">Hasil</th>
                        <th class="py-3 text-center" style="width: 11%;">Status</th>
                        <th class="pe-4 py-3 text-center" style="width: 15%; min-width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="font-size: 0.92rem;">
                    @forelse($preparations as $prep)
                        @php
                            $stockCheck = $prep->isMenunggu() ? $prep->getStockCheckDetails() : null;
                            $isStockCukup = $stockCheck ? $stockCheck['is_sufficient'] : true;
                            $insufficientItems = $stockCheck ? $stockCheck['insufficient_items'] : [];
                            $kurangTooltip = '';
                            if (!$isStockCukup && !empty($insufficientItems)) {
                                $kurangTooltip = 'Stok belum cukup: ' . collect($insufficientItems)->map(function($item) {
                                    $kurangFmt = number_format($item['kekurangan'], $item['kekurangan'] == intval($item['kekurangan']) ? 0 : 2, ',', '.');
                                    return $item['nama'] . ' (kurang ' . $kurangFmt . ' ' . $item['satuan'] . ')';
                                })->implode(', ');
                            }
                        @endphp
                        <tr class="hover-bg-light">
                            {{-- No. / Tanggal --}}
                            <td class="ps-4 py-3 align-top">
                                <div class="fw-bold text-dark">{{ $prep->kode }}</div>
                                <small class="text-muted">{{ $prep->tanggal ? $prep->tanggal->format('d/m/Y') : '-' }}</small>
                            </td>

                            {{-- Gudang Asal --}}
                            <td class="py-3 align-top">
                                <div class="fw-semibold text-dark">{{ $prep->gudang->nama ?? '-' }}</div>
                                @if($prep->divisi)
                                    <small class="badge bg-light text-secondary border fw-normal">{{ $prep->divisi->nama }}</small>
                                @endif
                            </td>

                            {{-- Bahan Baku Dipakai --}}
                            <td class="py-3 align-top">
                                @if($prep->inputs->isNotEmpty())
                                    <div class="text-dark">
                                        @foreach($prep->inputs as $idx => $in)
                                            @php
                                                $itemCheck = $stockCheck ? collect($stockCheck['items'])->firstWhere('barang_id', $in->barang_id) : null;
                                                $isItemKurang = $itemCheck && !$itemCheck['is_cukup'];
                                            @endphp
                                            <span class="{{ $isItemKurang ? 'text-danger' : '' }}">
                                                {{ $in->barang->nama ?? '-' }} 
                                                <strong>{{ number_format($in->qty, $in->qty == intval($in->qty) ? 0 : 2, ',', '.') }} {{ $in->satuan }}</strong>
                                                @if($isItemKurang)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-1" style="font-size: 10px;" title="Stok gudang tidak cukup (Tersedia: {{ number_format($itemCheck['tersedia'], 2, ',', '.') }} {{ $itemCheck['satuan'] }})">
                                                        <i class="bi bi-exclamation-triangle-fill"></i> Kurang
                                                    </span>
                                                @endif
                                            </span>{{ !$loop->last ? ', ' : '' }}
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted fst-italic">-</span>
                                @endif
                            </td>

                            {{-- Hasil --}}
                            <td class="py-3 align-top">
                                @if($prep->outputs->isNotEmpty())
                                    <div class="text-dark">
                                        @foreach($prep->outputs as $out)
                                            <div>
                                                {{ $out->barang->nama ?? '-' }} 
                                                <strong>{{ number_format($out->qty, $out->qty == intval($out->qty) ? 0 : 2, ',', '.') }} {{ $out->satuan }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted fst-italic">-</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="py-3 align-top text-center">
                                @if($prep->status === 'menunggu')
                                    <span class="badge rounded-pill px-3 py-1 fw-semibold" 
                                          style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                        Menunggu
                                    </span>
                                    @if(!$isStockCukup)
                                        <div class="mt-1">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0 fw-normal" 
                                                  style="font-size: 11px;" title="{{ $kurangTooltip }}">
                                                <i class="bi bi-x-circle me-1"></i>Stok Kurang
                                            </span>
                                        </div>
                                    @else
                                        <div class="mt-1">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0 fw-normal" 
                                                  style="font-size: 11px;" title="Semua stok bahan baku tersedia cukup">
                                                <i class="bi bi-check-circle me-1"></i>Stok Cukup
                                            </span>
                                        </div>
                                    @endif
                                @elseif($prep->status === 'disetujui')
                                    <span class="badge rounded-pill px-3 py-1 fw-semibold" 
                                          style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                        Disetujui
                                    </span>
                                @elseif($prep->status === 'ditolak')
                                    <span class="badge rounded-pill px-3 py-1 fw-semibold" 
                                          style="background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                        Ditolak
                                    </span>
                                    @if($prep->alasan_tolak)
                                        <div class="text-muted small mt-1" style="font-size: 11px; max-width: 140px; margin: 0 auto; line-height: 1.2;">
                                            {{ $prep->alasan_tolak }}
                                        </div>
                                    @endif
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="pe-4 py-3 align-middle text-center" style="white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-center gap-1" style="white-space: nowrap;">
                                    {{-- 1. Lihat (Semua status) --}}
                                    @if($prep->isDisetujui())
                                        <button type="button" class="btn btn-sm btn-outline-brown rounded-2 px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1" 
                                                style="font-size: 12px; height: 32px;"
                                                onclick="openDetailModal({{ $prep->id }})"
                                                data-bs-toggle="tooltip" title="Lihat Detail Preparation">
                                            <i class="bi bi-eye"></i>
                                            <span>Lihat</span>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-brown rounded-2 p-0 d-inline-flex align-items-center justify-content-center" 
                                                style="width: 32px; height: 32px; font-size: 13px;"
                                                onclick="openDetailModal({{ $prep->id }})"
                                                data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    @endif

                                    @php
                                        $user = auth()->user();
                                        $isSuperAdmin = $user && $user->isSuperAdmin();
                                    @endphp

                                    {{-- 2. Edit (Menunggu, atau Super Admin untuk status apapun) --}}
                                    @if($prep->isMenunggu() || $isSuperAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-2 p-0 d-inline-flex align-items-center justify-content-center" 
                                                style="width: 32px; height: 32px; font-size: 13px; border-color: #f59e0b;"
                                                onclick="openEditModal({{ $prep->id }})"
                                                data-bs-toggle="tooltip" 
                                                title="{{ $prep->isDisetujui() ? 'Edit Dokumen (Super Admin - Koreksi Bahan)' : 'Edit Dokumen' }}">
                                            <i class="bi bi-pencil-square text-dark"></i>
                                        </button>
                                    @endif

                                    {{-- 3. Setujui (Hanya Menunggu & Permission) --}}
                                    @if($prep->isMenunggu() && auth()->user()->canApprovePreparation())
                                        @if($isStockCukup)
                                            <form action="{{ route('preparation-bahan.approve', $prep->id) }}" method="POST" 
                                                  onsubmit="return confirm('Apakah Anda yakin menyetujui Preparation Bahan {{ $prep->kode }}? Stok bahan baku akan otomatis dipotong secara FIFO dan stok BSJ akan ditambahkan.');" 
                                                  class="d-inline m-0 p-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm text-white rounded-2 p-0 d-inline-flex align-items-center justify-content-center shadow-sm" 
                                                        style="width: 32px; height: 32px; background-color: #247d4f; font-size: 14px;"
                                                        data-bs-toggle="tooltip" title="Setujui (Stok Cukup)">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-secondary opacity-50 rounded-2 p-0 d-inline-flex align-items-center justify-content-center" 
                                                    style="width: 32px; height: 32px; font-size: 14px; cursor: not-allowed;" 
                                                    disabled 
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    title="{{ $kurangTooltip }} (Gunakan tombol 'Refresh Stok' jika baru menambah stok persediaan)">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        @endif
                                    @endif

                                    {{-- 4. Tolak (Hanya Menunggu & Permission) --}}
                                    @if($prep->isMenunggu() && auth()->user()->canApprovePreparation())
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-2 p-0 d-inline-flex align-items-center justify-content-center" 
                                                style="width: 32px; height: 32px; font-size: 13px;"
                                                onclick="openRejectModal({{ $prep->id }}, '{{ $prep->kode }}')"
                                                data-bs-toggle="tooltip" title="Tolak Dokumen">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    @endif

                                    {{-- 5. Hapus (Menunggu, Ditolak, atau Super Admin untuk status apapun) --}}
                                    @if($prep->canDelete() || $isSuperAdmin)
                                        <form action="{{ route('preparation-bahan.destroy', $prep->id) }}" method="POST" 
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen Preparation Bahan {{ $prep->kode }}?{{ $prep->isDisetujui() ? ' PERINGATAN: Dokumen ini berstatus Disetujui, mutasi stok bahan baku dan bahan setengah jadi akan di-rollback ke kondisi semula!' : '' }}');" 
                                              class="d-inline m-0 p-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-2 p-0 d-inline-flex align-items-center justify-content-center" 
                                                    style="width: 32px; height: 32px; font-size: 13px;"
                                                    data-bs-toggle="tooltip" 
                                                    title="{{ $prep->isDisetujui() ? 'Hapus Dokumen (Super Admin - Rollback Stok)' : 'Hapus Dokumen' }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data preparation bahan pada tab ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        @if($preparations->hasPages())
            <div class="p-3 border-top d-flex justify-content-end bg-white">
                {{ $preparations->links() }}
            </div>
        @endif
    </div>

</div>

{{-- ========================================================================= --}}
{{-- MODAL 1: BUAT PREPARATION --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalCreatePreparation" tabindex="-1" aria-labelledby="modalCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form action="{{ route('preparation-bahan.store') }}" method="POST" id="formCreatePreparation" class="modal-content border-0 shadow-lg rounded-4">
            @csrf
            <div class="modal-header text-white" style="background-color: #704828;">
                    <h5 class="modal-title fw-bold" id="modalCreateLabel">
                        <i class="bi bi-plus-circle me-1"></i> Buat Preparation Bahan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">

                    {{-- Tanggal & Gudang --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1">Gudang / Outlet Asal <span class="text-danger">*</span></label>
                            <select name="gudang_id" id="create_gudang_id" class="form-select rounded-3" required onchange="handleGudangChange('create')">
                                <option value="">-- Pilih Gudang Asal --</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1">Divisi Asal (Opsional)</label>
                            <select name="divisi_id" id="create_divisi_id" class="form-select rounded-3" onchange="handleDivisiChange('create')" disabled>
                                <option value="">-- Pilih Gudang Asal Terlebih Dahulu --</option>
                            </select>
                        </div>
                    </div>

                    {{-- BAGIAN 1: BAHAN BAKU DIPAKAI --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                <i class="bi bi-box-arrow-right text-danger me-1"></i> Daftar Bahan Baku yang Dipakai <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-brown rounded-3 fw-semibold" onclick="addInputRow('create')">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Bahan Baku
                            </button>
                        </div>
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm align-top mb-0 bg-white" id="table-create-inputs">
                                <thead class="table-light">
                                    <tr style="font-size: 0.85rem;">
                                        <th style="width: 44%;">Bahan Baku</th>
                                        <th style="width: 26%;">Qty / Gramasi</th>
                                        <th style="width: 22%;">Satuan</th>
                                        <th style="width: 8%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Row will be added dynamically by JS --}}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- BAGIAN 2: BAHAN SETENGAH JADI DIHASILKAN --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                <i class="bi bi-box-arrow-in-left text-success me-1"></i> Daftar Bahan Setengah Jadi yang Dihasilkan <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-brown rounded-3 fw-semibold" onclick="addOutputRow('create')">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Hasil
                            </button>
                        </div>
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm align-top mb-0 bg-white" id="table-create-outputs">
                                <thead class="table-light">
                                    <tr style="font-size: 0.85rem;">
                                        <th style="width: 44%;">Bahan Setengah Jadi (BSJ)</th>
                                        <th style="width: 26%;">Qty Hasil</th>
                                        <th style="width: 22%;">Satuan</th>
                                        <th style="width: 8%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Row will be added dynamically by JS --}}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Keterangan --}}
                    <div>
                        <label class="form-label fw-semibold small mb-1">Keterangan (Opsional)</label>
                        <textarea name="keterangan" class="form-control rounded-3" rows="2" placeholder="Catatan proses preparation..."></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white rounded-3 fw-semibold px-4" style="background-color: #704828;">
                        <i class="bi bi-check-lg me-1"></i> Simpan Preparation
                    </button>
                </div>
            </form>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 2: EDIT PREPARATION (HANYA STATUS MENUNGGU) --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalEditPreparation" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form action="" method="POST" id="formEditPreparation" class="modal-content border-0 shadow-lg rounded-4">
            @csrf
            @method('PUT')
            <div class="modal-header text-white" style="background-color: #704828;">
                    <h5 class="modal-title fw-bold" id="modalEditLabel">
                        <i class="bi bi-pencil-square me-1"></i> Edit Preparation Bahan: <span id="edit-kode-badge"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="edit-approved-warning" class="alert alert-warning border border-warning-subtle rounded-3 py-2 px-3 mb-3 d-none">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5 me-2"></i>
                            <div>
                                <strong class="text-dark d-block">Perhatian Super Admin (Koreksi Bahan Disetujui)</strong>
                                <small class="text-secondary">Dokumen ini telah disetujui sebelumnya. Mengubah bahan baku atau hasil akan secara otomatis merevert pemotongan FIFO lama dan memotong ulang stok sesuai komposisi bahan terbaru.</small>
                            </div>
                        </div>
                    </div>

                    {{-- Tanggal & Gudang --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" id="edit_tanggal" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1">Gudang / Outlet Asal <span class="text-danger">*</span></label>
                            <select name="gudang_id" id="edit_gudang_id" class="form-select rounded-3" required onchange="handleGudangChange('edit')">
                                <option value="">-- Pilih Gudang Asal --</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1">Divisi Asal (Opsional)</label>
                            <select name="divisi_id" id="edit_divisi_id" class="form-select rounded-3" onchange="handleDivisiChange('edit')">
                                <option value="">-- Pilih Gudang Asal Terlebih Dahulu --</option>
                            </select>
                        </div>
                    </div>

                    {{-- BAGIAN 1: BAHAN BAKU DIPAKAI --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                <i class="bi bi-box-arrow-right text-danger me-1"></i> Daftar Bahan Baku yang Dipakai <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-brown rounded-3 fw-semibold" onclick="addInputRow('edit')">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Bahan Baku
                            </button>
                        </div>
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm align-top mb-0 bg-white" id="table-edit-inputs">
                                <thead class="table-light">
                                    <tr style="font-size: 0.85rem;">
                                        <th style="width: 44%;">Bahan Baku</th>
                                        <th style="width: 26%;">Qty / Gramasi</th>
                                        <th style="width: 22%;">Satuan</th>
                                        <th style="width: 8%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- BAGIAN 2: BAHAN SETENGAH JADI DIHASILKAN --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                <i class="bi bi-box-arrow-in-left text-success me-1"></i> Daftar Bahan Setengah Jadi yang Dihasilkan <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-brown rounded-3 fw-semibold" onclick="addOutputRow('edit')">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Hasil
                            </button>
                        </div>
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm align-top mb-0 bg-white" id="table-edit-outputs">
                                <thead class="table-light">
                                    <tr style="font-size: 0.85rem;">
                                        <th style="width: 44%;">Bahan Setengah Jadi (BSJ)</th>
                                        <th style="width: 26%;">Qty Hasil</th>
                                        <th style="width: 22%;">Satuan</th>
                                        <th style="width: 8%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Keterangan --}}
                    <div>
                        <label class="form-label fw-semibold small mb-1">Keterangan (Opsional)</label>
                        <textarea name="keterangan" id="edit_keterangan" class="form-control rounded-3" rows="2"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white rounded-3 fw-semibold px-4" style="background-color: #704828;">
                        <i class="bi bi-check-lg me-1"></i> Perbarui Dokumen
                    </button>
                </div>
            </form>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 3: LIHAT PREPARATION (READ-ONLY) --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalDetailPreparation" tabindex="-1" aria-labelledby="modalDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header text-white" style="background-color: #704828;">
                <h5 class="modal-title fw-bold" id="modalDetailLabel">
                    <i class="bi bi-eye me-1"></i> Detail Preparation: <span id="detail-kode"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detail-modal-body">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border text-brown" role="status"></div>
                    <div class="mt-2">Memuat detail preparation...</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 4: TOLAK PREPARATION --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalRejectPreparation" tabindex="-1" aria-labelledby="modalRejectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" id="formRejectPreparation" class="modal-content border-0 shadow-lg rounded-4">
            @csrf
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="modalRejectLabel">
                    <i class="bi bi-x-octagon me-1"></i> Tolak Preparation: <span id="reject-kode"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3">
                    <i class="bi bi-info-circle me-1"></i> Penolakan dokumen ini akan membatalkan antrean dan tidak memotong stok apapun.
                </div>
                <label class="form-label fw-semibold small mb-1">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea name="alasan_tolak" class="form-control rounded-3" rows="3" 
                          placeholder="Misal: Stok fisik belum dicek, takaran tidak sesuai, dsb..." required></textarea>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger rounded-3 fw-semibold px-4">
                    <i class="bi bi-x-circle me-1"></i> Tolak Dokumen
                </button>
            </div>
        </form>
    </div>
</div>

{{-- STYLES --}}
<style>
    .btn-brown-active {
        background-color: #704828 !important;
        border-color: #704828 !important;
    }
    .btn-tab-inactive {
        background-color: #ffffff !important;
        color: #4b5563 !important;
        border: 1px solid #d1d5db !important;
    }
    .btn-tab-inactive:hover {
        background-color: #f9fafb !important;
        color: #111827 !important;
    }
    .btn-outline-brown {
        border: 1.5px solid #704828 !important;
        color: #704828 !important;
        background: transparent !important;
    }
    .btn-outline-brown:hover {
        background-color: #704828 !important;
        color: #ffffff !important;
    }
    .hover-bg-light:hover {
        background-color: #fdfbf7;
    }
    /* Select2 Bootstrap-5 theme adjustments */
    .select2-container {
        width: 100% !important;
    }
    .select2-container--bootstrap-5 {
        width: 100% !important;
    }
    .select2-container--bootstrap-5 .select2-selection {
        font-size: 0.875rem !important;
        min-height: 38px !important;
        height: 38px !important;
        padding: 0.375rem 0.75rem !important;
        border-radius: 0.375rem !important;
        border-color: #dee2e6 !important;
        display: flex !important;
        align-items: center !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 0 !important;
        line-height: 1.5 !important;
        color: #212529 !important;
    }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    .select2-container--bootstrap-5.select2-container--open .select2-selection {
        border-color: #704828 !important;
        box-shadow: 0 0 0 0.25rem rgba(112, 72, 40, 0.25) !important;
    }
    .select2-dropdown {
        border-color: #704828 !important;
        z-index: 1065 !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted {
        background-color: #704828 !important;
        color: #ffffff !important;
    }
    #table-create-inputs td, #table-edit-inputs td,
    #table-create-outputs td, #table-edit-outputs td {
        vertical-align: top !important;
    }
    /* Modal Scrollable Enhancements */
    .modal-dialog-scrollable {
        max-height: calc(100vh - 2rem) !important;
    }
    .modal-dialog-scrollable .modal-content {
        max-height: calc(100vh - 2rem) !important;
        overflow: hidden !important;
    }
    .modal-dialog-scrollable .modal-body {
        overflow-y: auto !important;
        max-height: calc(100vh - 2rem - 130px) !important;
    }
</style>

@push('scripts')
<script>
    const RAW_MATERIALS = @json($rawMaterials);
    const BSJ_MATERIALS = @json($bsjMaterials);
    const DIVISIS = @json($divisis);

    const rawMaterialSelectData = RAW_MATERIALS.map(b => ({
        id: String(b.id),
        text: b.kode_barang ? `${b.nama} (${b.kode_barang})` : b.nama
    }));

    const bsjMaterialSelectData = BSJ_MATERIALS.map(b => ({
        id: String(b.id),
        text: b.kode_barang ? `${b.nama} (${b.kode_barang})` : b.nama
    }));

    // Populate Divisi options dynamically strictly based on chosen Gudang / Outlet
    function populateDivisiOptions(prefix, selectedDivisiId = null) {
        const gudangSelect = document.getElementById(prefix + '_gudang_id');
        const divisiSelect = document.getElementById(prefix + '_divisi_id');
        if (!gudangSelect || !divisiSelect) return;

        const selectedGudang = gudangSelect.value;
        const matchingDivisis = selectedGudang 
            ? DIVISIS.filter(d => d.gudang_id == selectedGudang)
            : [];

        if (!selectedGudang) {
            divisiSelect.innerHTML = '<option value="">-- Pilih Gudang Asal Terlebih Dahulu --</option>';
            divisiSelect.disabled = true;
            return;
        }

        divisiSelect.disabled = false;

        if (matchingDivisis.length > 0) {
            let html = '<option value="">-- Tanpa Divisi (Gudang Utama Outlet) --</option>';
            matchingDivisis.forEach(d => {
                const isSelected = (selectedDivisiId && selectedDivisiId == d.id) ? 'selected' : '';
                html += `<option value="${d.id}" ${isSelected}>${d.nama}</option>`;
            });
            divisiSelect.innerHTML = html;
        } else {
            divisiSelect.innerHTML = '<option value="">-- Tanpa Divisi (Gudang Utama) --</option>';
        }
    }

    // Filter Divisi based on chosen Gudang
    function handleGudangChange(prefix) {
        populateDivisiOptions(prefix);
        refreshAllStockLabels(prefix);
    }

    function handleDivisiChange(prefix) {
        refreshAllStockLabels(prefix);
    }

    // Initialize Select2 with search support inside Bootstrap Modal
    function initSelect2On(selectEl, prefix, isBsj = false) {
        if (typeof $ === 'undefined' || !$.fn.select2) return;
        const modalId = prefix === 'edit' ? '#modalEditPreparation' : '#modalCreatePreparation';
        const items = isBsj ? bsjMaterialSelectData : rawMaterialSelectData;
        const phText = isBsj ? '-- Cari / Pilih Bahan Setengah Jadi --' : '-- Cari / Pilih Bahan Baku --';

        if ($(selectEl).data('select2')) {
            $(selectEl).select2('destroy');
        }

        // Clean out any stale options before applying select2 data
        const currentVal = $(selectEl).val();
        selectEl.innerHTML = '<option value=""></option>';

        $(selectEl).select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $(modalId),
            placeholder: phText,
            allowClear: true,
            data: items
        }).on('change', function () {
            if (isBsj) {
                onBsjSelect(this, prefix);
            } else {
                onBarangSelect(this, prefix);
            }
        });

        if (currentVal) {
            $(selectEl).val(String(currentVal)).trigger('change.select2');
        }
    }

    // Generate available units strictly based on Master Barang
    function getSatuanOptionsForBarang(barang, isBsj = false) {
        if (!barang) return [];

        const baseUnit = (barang.satuan || 'gr').trim();
        const purchaseUnit = (barang.satuan_pembelian || '').trim();
        const purchaseKonv = parseFloat(barang.konversi_pembelian || 1);

        const options = [];

        // 1. Base Unit (Selalu paling awal dan utama)
        options.push({
            value: baseUnit,
            label: `${baseUnit} (Satuan Dasar)`,
            konversi: 1
        });

        const baseUpper = baseUnit.toUpperCase();

        // 2. Metric Pairs (GR <-> KG atau ML <-> LITER)
        if (['GR', 'GRAM', 'G'].includes(baseUpper)) {
            options.push({
                value: 'KG',
                label: 'KG (1.000 GR)',
                konversi: 1000
            });
        } else if (['KG', 'KILOGRAM'].includes(baseUpper)) {
            options.push({
                value: 'GR',
                label: 'GR (0,001 KG)',
                konversi: 0.001
            });
        } else if (['ML', 'MILILITER'].includes(baseUpper)) {
            options.push({
                value: 'LITER',
                label: 'LITER (1.000 ML)',
                konversi: 1000
            });
        } else if (['L', 'LITER', 'LTR'].includes(baseUpper)) {
            options.push({
                value: 'ML',
                label: 'ML (0,001 LITER)',
                konversi: 0.001
            });
        }

        // 3. Satuan Pembelian dari master_barang
        if (purchaseUnit && !options.some(o => o.value.toUpperCase() === purchaseUnit.toUpperCase())) {
            const konvStr = purchaseKonv > 1 
                ? ` (${new Intl.NumberFormat('id-ID').format(purchaseKonv)} ${baseUnit})` 
                : '';
            options.push({
                value: purchaseUnit,
                label: `${purchaseUnit}${konvStr}`,
                konversi: purchaseKonv
            });
        }

        // 4. Satuan standar untuk Bahan Setengah Jadi (BSJ)
        if (isBsj) {
            const bsjExtras = ['PORSI', 'PCS', 'PACK', 'CUP', 'JAR', 'BOTOL', 'BOX'];
            bsjExtras.forEach(u => {
                if (!options.some(o => o.value.toUpperCase() === u)) {
                    options.push({
                        value: u,
                        label: u,
                        konversi: 1
                    });
                }
            });
        }

        return options;
    }

    // Populate Satuan Select Dropdown
    function populateSatuanSelect(satuanSelect, barang, selectedSatuan = null, isBsj = false) {
        if (!satuanSelect) return;

        if (!barang) {
            satuanSelect.innerHTML = '<option value="">-- Satuan --</option>';
            satuanSelect.disabled = true;
            return;
        }

        satuanSelect.disabled = false;
        const opts = getSatuanOptionsForBarang(barang, isBsj);

        let html = '';
        let hasSelected = false;

        opts.forEach((opt, idx) => {
            let isSel = false;
            if (selectedSatuan) {
                isSel = (opt.value.toUpperCase() === selectedSatuan.toUpperCase());
            } else if (!hasSelected && idx === 0) {
                isSel = true;
            }
            if (isSel) hasSelected = true;

            html += `<option value="${opt.value}" data-konversi="${opt.konversi}" ${isSel ? 'selected' : ''}>${opt.label}</option>`;
        });

        satuanSelect.innerHTML = html;
    }

    // Refresh stock labels across all input rows in modal
    function refreshAllStockLabels(prefix) {
        const tbody = document.querySelector(`#table-${prefix}-inputs tbody`);
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(tr => {
            const select = tr.querySelector('.input-barang-select');
            if (select && select.value) {
                checkStock(select, prefix);
            }
        });
    }

    // Dynamic Row: Bahan Baku Dipakai
    function addInputRow(prefix, item = null) {
        const tbody = document.querySelector(`#table-${prefix}-inputs tbody`);
        const rowIdx = tbody.querySelectorAll('tr').length;
        const defaultQty = item ? item.qty : '';

        const tr = document.createElement('tr');
        tr.className = 'align-top';
        tr.innerHTML = `
            <td class="align-top py-2" style="width: 44%; vertical-align: top !important;">
                <select name="inputs[${rowIdx}][barang_id]" class="form-select rounded-3 input-barang-select select2-barang-input" 
                        required style="width: 100%; height: 38px;">
                    <option value=""></option>
                </select>
                <div class="mt-1 small text-muted stock-indicator" style="font-size: 11px; line-height: 1.2;">
                    <i class="bi bi-box-seam"></i> Stok: <span class="stock-val fw-semibold text-secondary">-</span>
                </div>
            </td>
            <td class="align-top py-2" style="width: 26%; vertical-align: top !important;">
                <input type="number" step="any" min="0.0001" name="inputs[${rowIdx}][qty]" 
                       class="form-control rounded-3" placeholder="0" 
                       style="height: 38px; font-size: 0.875rem;"
                       value="${defaultQty}" required oninput="checkStock(this.closest('tr').querySelector('.input-barang-select'), '${prefix}')">
            </td>
            <td class="align-top py-2" style="width: 22%; vertical-align: top !important;">
                <select name="inputs[${rowIdx}][satuan]" class="form-select rounded-3 input-satuan-select" 
                        style="height: 38px; font-size: 0.875rem;"
                        required onchange="onSatuanChange(this, '${prefix}')">
                    <option value="">-- Satuan --</option>
                </select>
            </td>
            <td class="align-top py-2 text-center" style="width: 8%; vertical-align: top !important;">
                <button type="button" class="btn btn-outline-danger btn-sm rounded-3 d-flex align-items-center justify-content-center mx-auto" 
                        style="height: 38px; width: 38px;" onclick="removeRow(this)" title="Hapus baris">
                    <i class="bi bi-trash fs-6"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        const selectEl = tr.querySelector('.input-barang-select');
        const satuanSelect = tr.querySelector('.input-satuan-select');

        initSelect2On(selectEl, prefix, false);

        if (item && item.barang_id) {
            $(selectEl).val(String(item.barang_id)).trigger('change.select2');
            const b = RAW_MATERIALS.find(x => x.id == item.barang_id);
            populateSatuanSelect(satuanSelect, b, item.satuan, false);
            checkStock(selectEl, prefix);
        } else {
            populateSatuanSelect(satuanSelect, null, null, false);
        }
    }

    // Dynamic Row: BSJ Dihasilkan
    function addOutputRow(prefix, item = null) {
        const tbody = document.querySelector(`#table-${prefix}-outputs tbody`);
        const rowIdx = tbody.querySelectorAll('tr').length;
        const defaultQty = item ? item.qty : '';

        const tr = document.createElement('tr');
        tr.className = 'align-top';
        tr.innerHTML = `
            <td class="align-top py-2" style="width: 44%; vertical-align: top !important;">
                <select name="outputs[${rowIdx}][barang_id]" class="form-select rounded-3 output-barang-select select2-bsj-output" 
                        required style="width: 100%; height: 38px;">
                    <option value=""></option>
                </select>
            </td>
            <td class="align-top py-2" style="width: 26%; vertical-align: top !important;">
                <input type="number" step="any" min="0.0001" name="outputs[${rowIdx}][qty]" 
                       class="form-control rounded-3" placeholder="0" 
                       style="height: 38px; font-size: 0.875rem;"
                       value="${defaultQty}" required>
            </td>
            <td class="align-top py-2" style="width: 22%; vertical-align: top !important;">
                <select name="outputs[${rowIdx}][satuan]" class="form-select rounded-3 output-satuan-select" 
                        style="height: 38px; font-size: 0.875rem;"
                        required>
                    <option value="">-- Satuan --</option>
                </select>
            </td>
            <td class="align-top py-2 text-center" style="width: 8%; vertical-align: top !important;">
                <button type="button" class="btn btn-outline-danger btn-sm rounded-3 d-flex align-items-center justify-content-center mx-auto" 
                        style="height: 38px; width: 38px;" onclick="removeRow(this)" title="Hapus baris">
                    <i class="bi bi-trash fs-6"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        const selectEl = tr.querySelector('.output-barang-select');
        const satuanSelect = tr.querySelector('.output-satuan-select');

        initSelect2On(selectEl, prefix, true);

        if (item && item.barang_id) {
            $(selectEl).val(String(item.barang_id)).trigger('change.select2');
            const b = BSJ_MATERIALS.find(x => x.id == item.barang_id);
            populateSatuanSelect(satuanSelect, b, item.satuan, true);
        } else {
            populateSatuanSelect(satuanSelect, null, null, true);
        }
    }

    function removeRow(btn) {
        const tr = btn.closest('tr');
        const tbody = tr.parentElement;
        if (tbody.querySelectorAll('tr').length <= 1) {
            alert('Minimal harus ada 1 baris bahan.');
            return;
        }
        if (typeof $ !== 'undefined') {
            $(tr).find('select').each(function () {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });
        }
        tr.remove();
    }

    // Auto-populate satuan dropdown and check stock on item selection
    function onBarangSelect(select, prefix) {
        const tr = select.closest('tr');
        if (!tr) return;
        const bId = select.value;
        const barang = RAW_MATERIALS.find(b => b.id == bId);
        const satuanSelect = tr.querySelector('.input-satuan-select');

        populateSatuanSelect(satuanSelect, barang, null, false);
        checkStock(select, prefix);
    }

    function onBsjSelect(select, prefix) {
        const tr = select.closest('tr');
        if (!tr) return;
        const bId = select.value;
        const barang = BSJ_MATERIALS.find(b => b.id == bId);
        const satuanSelect = tr.querySelector('.output-satuan-select');

        populateSatuanSelect(satuanSelect, barang, null, true);
    }

    function onSatuanChange(satuanSelect, prefix) {
        const tr = satuanSelect.closest('tr');
        if (!tr) return;
        const select = tr.querySelector('.input-barang-select');
        if (select && select.value) {
            checkStock(select, prefix);
        }
    }

    // Fetch real-time available stock and adapt to selected unit
    function checkStock(select, prefix) {
        const tr = select.closest('tr');
        if (!tr) return;
        const indicator = tr.querySelector('.stock-val');
        if (!indicator) return;
        const barangId = select.value;
        const gudangSelect = document.getElementById(prefix + '_gudang_id');
        const divisiSelect = document.getElementById(prefix + '_divisi_id');
        const gudangId = gudangSelect ? gudangSelect.value : '';
        const divisiId = divisiSelect ? divisiSelect.value : '';
        const satuanSelect = tr.querySelector('.input-satuan-select');
        const selectedSatuanOpt = satuanSelect && satuanSelect.selectedIndex >= 0 ? satuanSelect.options[satuanSelect.selectedIndex] : null;
        const konversi = selectedSatuanOpt ? parseFloat(selectedSatuanOpt.getAttribute('data-konversi') || 1) : 1;
        const selectedUnitName = selectedSatuanOpt ? selectedSatuanOpt.value : '';

        if (!barangId) {
            indicator.innerHTML = '<span class="text-muted">-</span>';
            return;
        }

        if (!gudangId) {
            indicator.innerHTML = '<span class="text-muted">Pilih gudang asal</span>';
            return;
        }

        indicator.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary" style="width: 10px; height: 10px;"></span>';

        fetch(`{{ route('preparation-bahan.stock') }}?barang_id=${barangId}&gudang_id=${gudangId}&divisi_id=${divisiId}`)
            .then(res => res.json())
            .then(data => {
                const rawStock = parseFloat(data.stok || 0);
                const baseUnit = data.satuan || 'gr';

                let stockDisplay = '';
                if (konversi > 1 && rawStock > 0) {
                    const stockInChosenUnit = rawStock / konversi;
                    const formattedChosen = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(stockInChosenUnit);
                    const formattedBase = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(rawStock);
                    stockDisplay = `${formattedBase} ${baseUnit} (~ ${formattedChosen} ${selectedUnitName})`;
                } else {
                    const formattedBase = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(rawStock);
                    stockDisplay = `${formattedBase} ${baseUnit}`;
                }

                const badgeClass = rawStock > 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
                indicator.innerHTML = `<span class="${badgeClass}">${stockDisplay}</span>`;
            })
            .catch(() => {
                indicator.innerHTML = '<span class="text-danger">Gagal ambil stok</span>';
            });
    }

    // Modal: Detail (Read-only)
    function openDetailModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('modalDetailPreparation'));
        const body = document.getElementById('detail-modal-body');
        document.getElementById('detail-kode').innerText = '...';

        body.innerHTML = `
            <div class="text-center py-4 text-muted">
                <div class="spinner-border text-brown" role="status"></div>
                <div class="mt-2">Memuat detail preparation...</div>
            </div>
        `;
        modal.show();

        fetch(`{{ url('preparation-bahan') }}/${id}/detail-json`)
            .then(res => res.json())
            .then(resp => {
                if (!resp.success) {
                    body.innerHTML = `<div class="alert alert-danger">${resp.message || 'Gagal memuat detail.'}</div>`;
                    return;
                }
                const d = resp.data;
                document.getElementById('detail-kode').innerText = d.kode;

                let statusBadge = '';
                if (d.status === 'menunggu') {
                    statusBadge = '<span class="badge rounded-pill px-3 py-1" style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">Menunggu</span>';
                } else if (d.status === 'disetujui') {
                    statusBadge = '<span class="badge rounded-pill px-3 py-1" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">Disetujui</span>';
                } else if (d.status === 'ditolak') {
                    statusBadge = '<span class="badge rounded-pill px-3 py-1" style="background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">Ditolak</span>';
                }

                let rejectionHtml = '';
                if (d.status === 'ditolak' && d.alasan_tolak) {
                    rejectionHtml = `
                        <div class="alert alert-danger rounded-3 py-2 px-3 mb-3">
                            <strong class="d-block"><i class="bi bi-x-circle me-1"></i> Alasan Penolakan:</strong>
                            <div>${d.alasan_tolak}</div>
                        </div>
                    `;
                }

                // Stock check banner for status 'menunggu'
                let stockCheckHtml = '';
                if (d.status === 'menunggu' && d.stock_check) {
                    const sc = d.stock_check;
                    if (sc.is_sufficient) {
                        stockCheckHtml = `
                            <div class="alert alert-success d-flex align-items-center justify-content-between rounded-3 py-2 px-3 mb-3 border border-success-subtle">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill text-success fs-4 me-2"></i>
                                    <div>
                                        <strong class="d-block text-dark">Stok Bahan Baku Mencukupi</strong>
                                        <small class="text-secondary">Semua bahan baku tersedia di gudang/divisi terkait. Dokumen siap untuk disetujui.</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1" onclick="refreshStockInDetailModal(${d.id})">
                                    <i class="bi bi-arrow-repeat me-1"></i> Cek Ulang Stok
                                </button>
                            </div>
                        `;
                    } else {
                        let kurangList = sc.insufficient_items.map(it => `
                            <li>
                                <strong>${it.nama}</strong>: Dibutuhkan <strong>${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(it.dibutuhkan)} ${it.satuan}</strong>, 
                                tersedia <span class="text-danger fw-bold">${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(it.tersedia)} ${it.satuan}</span> 
                                (Kurang <span class="badge bg-danger">${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(it.kekurangan)} ${it.satuan}</span>)
                            </li>
                        `).join('');

                        stockCheckHtml = `
                            <div class="alert alert-warning border border-warning-subtle rounded-3 py-2 px-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-2"></i>
                                        <div>
                                            <strong class="d-block text-dark">Stok Bahan Baku Tidak Mencukupi</strong>
                                            <small class="text-secondary">Persetujuan dinonaktifkan sampai stok bahan terpenuhi dari pembelian atau mutasi stok.</small>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-brown rounded-pill px-3 py-1" onclick="refreshStockInDetailModal(${d.id})">
                                        <i class="bi bi-arrow-repeat me-1"></i> Cek Ulang Stok
                                    </button>
                                </div>
                                <hr class="my-2 border-warning-subtle">
                                <div class="small">
                                    <strong class="text-dark">Rincian kekurangan bahan:</strong>
                                    <ul class="mb-0 mt-1 ps-3 text-dark">
                                        ${kurangList}
                                    </ul>
                                </div>
                            </div>
                        `;
                    }
                }

                // Table Input Rows
                let inputRows = '';
                d.inputs.forEach((inItem, idx) => {
                    if (d.status === 'menunggu' && d.stock_check) {
                        const itCheck = d.stock_check.items.find(i => i.barang_id === inItem.barang_id);
                        const availStr = itCheck 
                            ? `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(itCheck.tersedia)} ${itCheck.satuan}` 
                            : '-';
                        const statusStockBadge = itCheck && itCheck.is_cukup
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="bi bi-check-circle me-1"></i>Cukup</span>'
                            : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill"><i class="bi bi-x-circle me-1"></i>Kurang ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(itCheck ? itCheck.kekurangan : 0)}</span>`;

                        inputRows += `
                            <tr>
                                <td class="ps-3">${idx + 1}</td>
                                <td class="fw-semibold text-dark">${inItem.barang_nama}</td>
                                <td>${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(inItem.qty)} ${inItem.satuan}</td>
                                <td>${availStr}</td>
                                <td class="pe-3">${statusStockBadge}</td>
                            </tr>
                        `;
                    } else {
                        const hppSatuanStr = d.status === 'disetujui' 
                            ? 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(inItem.hpp_satuan)
                            : '<span class="text-muted">-</span>';
                        const totalBiayaStr = d.status === 'disetujui'
                            ? 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(inItem.total_biaya)
                            : '<span class="text-muted">-</span>';

                        inputRows += `
                            <tr>
                                <td class="ps-3">${idx + 1}</td>
                                <td class="fw-semibold text-dark">${inItem.barang_nama}</td>
                                <td>${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(inItem.qty)} ${inItem.satuan}</td>
                                <td>${hppSatuanStr}</td>
                                <td class="text-end pe-3 fw-semibold">${totalBiayaStr}</td>
                            </tr>
                        `;
                    }
                });

                // Table Output Rows
                let outputRows = '';
                d.outputs.forEach((outItem, idx) => {
                    const hppSatuanStr = d.status === 'disetujui'
                        ? 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(outItem.hpp_satuan)
                        : '<span class="text-muted">-</span>';
                    const totalNilaiStr = d.status === 'disetujui'
                        ? 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(outItem.total_nilai)
                        : '<span class="text-muted">-</span>';

                    outputRows += `
                        <tr>
                            <td class="ps-3">${idx + 1}</td>
                            <td class="fw-semibold text-dark">${outItem.barang_nama}</td>
                            <td>${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(outItem.qty)} ${outItem.satuan}</td>
                            <td>${hppSatuanStr}</td>
                            <td class="text-end pe-3 fw-semibold">${totalNilaiStr}</td>
                        </tr>
                    `;
                });

                // Summary cards if approved
                let summaryApproved = '';
                if (d.status === 'disetujui') {
                    const totalCostFormatted = 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(d.total_biaya);
                    summaryApproved = `
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                    <small class="text-muted d-block">Total HPP Bahan Baku Dipakai:</small>
                                    <span class="fs-5 fw-bold text-dark">${totalCostFormatted}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3" style="background-color: #f0fdf4; border: 1px solid #bbf7d0;">
                                    <small class="text-success d-block">Status Persediaan:</small>
                                    <span class="fs-6 fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i> Stok Masuk ke Gudang</span>
                                </div>
                            </div>
                        </div>
                    `;
                }

                // Table input columns header
                const inputHeaderCols = (d.status === 'menunggu' && d.stock_check)
                    ? `
                        <th class="ps-3" style="width: 5%;">#</th>
                        <th style="width: 35%;">Nama Bahan</th>
                        <th style="width: 20%;">Qty Dipakai</th>
                        <th style="width: 20%;">Stok Tersedia</th>
                        <th class="pe-3" style="width: 20%;">Status Stok</th>
                    `
                    : `
                        <th class="ps-3" style="width: 5%;">#</th>
                        <th style="width: 40%;">Nama Bahan</th>
                        <th style="width: 20%;">Qty Dipakai</th>
                        <th style="width: 20%;">HPP Satuan</th>
                        <th class="text-end pe-3" style="width: 15%;">Total Biaya</th>
                    `;

                body.innerHTML = `
                    ${rejectionHtml}
                    ${stockCheckHtml}
                    <div class="card border p-3 rounded-3 bg-light mb-3">
                        <div class="row g-2">
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block">No. Preparation</small>
                                <span class="fw-bold text-dark">${d.kode}</span>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block">Tanggal</small>
                                <span class="fw-semibold text-dark">${d.tanggal}</span>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block">Gudang / Divisi Asal</small>
                                <span class="fw-semibold text-dark">${d.gudang_nama} ${d.divisi_nama ? '(' + d.divisi_nama + ')' : ''}</span>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block">Status</small>
                                <div>${statusBadge}</div>
                            </div>
                            <div class="col-sm-6 col-md-6 mt-2">
                                <small class="text-muted d-block">Dibuat Oleh</small>
                                <span class="text-dark">${d.creator_nama}</span>
                            </div>
                            <div class="col-sm-6 col-md-6 mt-2">
                                <small class="text-muted d-block">Disetujui / Ditolak Oleh</small>
                                <span class="text-dark">${d.approver_nama ? d.approver_nama + ' (' + d.approved_at + ')' : '-'}</span>
                            </div>
                            <div class="col-12 mt-2">
                                <small class="text-muted d-block">Keterangan</small>
                                <span class="text-dark">${d.keterangan || '-'}</span>
                            </div>
                        </div>
                    </div>

                    ${summaryApproved}

                    {{-- Tabel Input --}}
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-box-arrow-right text-danger me-1"></i> Bahan Baku yang Dipakai</h6>
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr style="font-size: 0.85rem;">
                                        ${inputHeaderCols}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${inputRows}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tabel Output --}}
                    <div>
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-box-arrow-in-left text-success me-1"></i> Bahan Setengah Jadi yang Dihasilkan</h6>
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr style="font-size: 0.85rem;">
                                        <th class="ps-3" style="width: 5%;">#</th>
                                        <th style="width: 40%;">Nama Bahan Hasil</th>
                                        <th style="width: 20%;">Qty Hasil</th>
                                        <th style="width: 20%;">HPP per Unit</th>
                                        <th class="text-end pe-3" style="width: 15%;">Total Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${outputRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            })
            .catch(() => {
                body.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan saat memuat data.</div>`;
            });
    }

    function refreshStockInDetailModal(id) {
        openDetailModal(id);
    }

    // Modal: Edit
    function openEditModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('modalEditPreparation'));
        const form = document.getElementById('formEditPreparation');
        form.action = `{{ url('preparation-bahan') }}/${id}`;

        fetch(`{{ url('preparation-bahan') }}/${id}/edit-json`)
            .then(res => res.json())
            .then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Gagal memuat data edit.');
                    return;
                }
                const d = resp.data;
                document.getElementById('edit-kode-badge').innerText = d.kode;
                document.getElementById('edit_tanggal').value = d.tanggal;
                document.getElementById('edit_gudang_id').value = d.gudang_id;
                populateDivisiOptions('edit', d.divisi_id || null);
                document.getElementById('edit_keterangan').value = d.keterangan || '';

                const approvedWarning = document.getElementById('edit-approved-warning');
                if (approvedWarning) {
                    if (d.status === 'disetujui') {
                        approvedWarning.classList.remove('d-none');
                    } else {
                        approvedWarning.classList.add('d-none');
                    }
                }

                // Populate Inputs
                const inputTbody = document.querySelector('#table-edit-inputs tbody');
                inputTbody.innerHTML = '';
                if (d.inputs && d.inputs.length > 0) {
                    d.inputs.forEach(inItem => {
                        addInputRow('edit', inItem);
                    });
                } else {
                    addInputRow('edit');
                }

                // Populate Outputs
                const outputTbody = document.querySelector('#table-edit-outputs tbody');
                outputTbody.innerHTML = '';
                if (d.outputs && d.outputs.length > 0) {
                    d.outputs.forEach(outItem => {
                        addOutputRow('edit', outItem);
                    });
                } else {
                    addOutputRow('edit');
                }

                refreshAllStockLabels('edit');
                modal.show();
            })
            .catch(() => {
                alert('Gagal mengambil data untuk edit.');
            });
    }

    // Modal: Reject
    function openRejectModal(id, kode) {
        const modal = new bootstrap.Modal(document.getElementById('modalRejectPreparation'));
        const form = document.getElementById('formRejectPreparation');
        form.action = `{{ url('preparation-bahan') }}/${id}/reject`;
        document.getElementById('reject-kode').innerText = kode;
        form.querySelector('textarea[name="alasan_tolak"]').value = '';
        modal.show();
    }

    // DOM Ready initialization
    $(document).ready(function () {
        addInputRow('create');
        addOutputRow('create');
        populateDivisiOptions('create');

        const createModalEl = document.getElementById('modalCreatePreparation');
        if (createModalEl) {
            createModalEl.addEventListener('show.bs.modal', function () {
                populateDivisiOptions('create');
            });
        }

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Ensure Select2 adjusts width when modals are fully shown
        $(document).on('shown.bs.modal', '#modalCreatePreparation, #modalEditPreparation', function () {
            $(this).find('.select2-container').css('width', '100%');
        });
    });
</script>
@endpush
</x-app-layout>
