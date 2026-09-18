<x-app-layout>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold text-dark m-0">Daftar Resep Produk</h3>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-warning text-dark border-warning rounded-3 px-3 shadow-sm fw-semibold position-relative" data-bs-toggle="modal" data-bs-target="#modalTanpaResep">
                <i class="fas fa-exclamation-circle text-warning me-1"></i>Belum Ada Resep
                @php
                    $totalTanpaResep = ($bsjTanpaResep ? $bsjTanpaResep->count() : 0) + ($posTanpaResep ? $posTanpaResep->count() : 0);
                @endphp
                <span class="badge bg-warning text-dark ms-1 rounded-pill">{{ $totalTanpaResep }}</span>
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-3 px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImportResep">
                <i class="fas fa-file-upload me-2"></i>Import Excel
            </button>
            <button type="button" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold" id="btn-tambah-resep">
                <i class="fas fa-plus me-2"></i>Tambah Resep
            </button>
        </div>
    </div>

    @if (session('import_result_resep'))
        @php $ir = session('import_result_resep'); @endphp
        <div class="alert alert-info border-0 shadow-sm mb-4">
            <strong>Hasil Import Resep:</strong>
            {{ $ir['createdRecipes'] }} resep dibuat ({{ $ir['createdIngredients'] }} bahan),
            {{ $ir['skippedRecipes'] }} dilewati (produk sudah punya resep).
            @if (!empty($ir['skippedRows']))
                <div class="mt-1 small text-muted">
                    <strong>Catatan:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach ($ir['skippedRows'] as $skipNote)
                            <li>{{ $skipNote }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (!empty($ir['errors']))
                <div class="mt-2">
                    <strong class="text-danger">{{ count($ir['errors']) }} pesan perhatian:</strong>
                    <ul class="mb-0 small text-danger">
                        @foreach ($ir['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <strong class="me-2"><i class="fas fa-check-circle"></i> Berhasil!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error') || (isset($errors) && $errors->any()))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <strong class="d-block mb-1"><i class="fas fa-exclamation-triangle"></i> Terjadi Kesalahan:</strong>
        <ul class="mb-0 ps-3">
            @if(session('error')) <li>{{ session('error') }}</li> @endif
            @if(isset($errors))
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            @endif
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-0">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-dark">Data Resep</h5>
                <form action="{{ route('resep.index') }}" method="GET" class="d-flex align-items-center gap-2 flex-wrap" id="formFilterResep">
                    <select name="kategori_id" class="form-select form-select-sm" style="width: 155px; border-radius: 6px;" onchange="this.form.submit()">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($kategori as $k)
                            <option value="{{ $k->id }}" {{ request('kategori_id') == $k->id ? 'selected' : '' }}>{{ $k->nama }}</option>
                        @endforeach
                    </select>

                    <select name="jenis" class="form-select form-select-sm" style="width: 155px; border-radius: 6px;" onchange="this.form.submit()">
                        <option value="">-- Semua Jenis --</option>
                        <option value="bsj" {{ request('jenis') == 'bsj' ? 'selected' : '' }}>Bahan Setengah Jadi</option>
                        <option value="pos" {{ request('jenis') == 'pos' ? 'selected' : '' }}>Barang Jadi / POS</option>
                    </select>

                    <div class="filter-choices-wrapper" style="width: 210px;">
                        <select name="search" id="filter_search_produk" class="form-select form-select-sm search-select-filter" onchange="this.form.submit()">
                            <option value="">-- Cari Produk --</option>
                            @if(isset($listProdukResep))
                                @foreach($listProdukResep as $pr)
                                    <option value="{{ $pr->nama }}" {{ request('search') == $pr->nama ? 'selected' : '' }}>
                                        {{ $pr->nama }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="filter-choices-wrapper" style="width: 220px;">
                        <select name="search_bahan" id="filter_search_bahan" class="form-select form-select-sm search-select-filter" onchange="this.form.submit()">
                            <option value="">-- Cari Bahan Baku --</option>
                            @if(isset($listBahanResep))
                                @foreach($listBahanResep as $br)
                                    <option value="{{ $br->nama }}" {{ request('search_bahan') == $br->nama ? 'selected' : '' }}>
                                        {{ $br->nama }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <button type="submit" class="btn btn-sm text-white" style="background-color: #d88656; border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                    @if(request('search') || request('search_bahan') || request('kategori_id') || request('jenis'))
                        <a href="{{ route('resep.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
                    @endif
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase fs-7 text-center">
                        <tr>
                            <th class="text-start ps-4 py-3">Nama Produk</th>
                            <th>Output / Batch</th>
                            <th style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @forelse($data as $r)
                        <tr>
                            <td class="text-start ps-4 fw-semibold text-dark">
                                <div>
                                    {{ $r->produk->nama ?? 'Produk Tidak Diketahui' }}
                                    @if($r->produk)
                                        @if($r->produk->is_bahan_setengah_jadi)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size: 11px;">
                                                <i class="bi bi-gear me-1"></i>Bahan Setengah Jadi
                                            </span>
                                        @elseif($r->produk->is_barang_jadi)
                                            @if($r->produk->tipe_penjualan === 'POS Kejingga')
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size: 11px;">
                                                    <i class="bi bi-shop me-1"></i>POS Kejingga
                                                </span>
                                            @elseif($r->produk->tipe_penjualan === 'POS Gaharu')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 11px;">
                                                    <i class="bi bi-shop me-1"></i>POS Gaharu
                                                </span>
                                            @elseif($r->produk->tipe_penjualan === 'B2B')
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 11px;">
                                                    <i class="bi bi-building me-1"></i>B2B
                                                </span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 11px;">
                                                    Barang Jadi
                                                </span>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                                @if(request('search_bahan'))
                                    @php
                                        $sbLower = strtolower(request('search_bahan'));
                                        $matchedBahans = [];
                                        foreach ($r->bahanbaku as $bb) {
                                            if ($bb->bahan && (str_contains(strtolower($bb->bahan->nama), $sbLower) || str_contains(strtolower($bb->bahan->kode_barang), $sbLower))) {
                                                $matchedBahans[] = $bb->bahan->nama . ' (' . (float)$bb->qty_bahan . ' ' . $bb->satuan . ')';
                                            }
                                            if ($bb->alternatif) {
                                                foreach ($bb->alternatif as $alt) {
                                                    if ($alt->bahan && (str_contains(strtolower($alt->bahan->nama), $sbLower) || str_contains(strtolower($alt->bahan->kode_barang), $sbLower))) {
                                                        $matchedBahans[] = '[Alt] ' . $alt->bahan->nama;
                                                    }
                                                }
                                            }
                                        }
                                    @endphp
                                    @if(!empty($matchedBahans))
                                        <div class="mt-1 small text-muted font-monospace" style="font-size: 11.5px;">
                                            <span class="text-warning-emphasis fw-semibold"><i class="fas fa-check-circle me-1"></i>Menggunakan:</span>
                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle">{{ implode(', ', array_unique($matchedBahans)) }}</span>
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-3 py-2 fw-medium">
                                    {{ (int) $r->output_qty }} {{ $r->produk->satuan ?? $r->satuan_output }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <button type="button" 
                                            class="btn btn-info btn-sm text-white rounded-2 px-2 btn-lihat-resep"
                                            data-id="{{ $r->id }}"
                                            data-produk-nama="{{ $r->produk->nama ?? 'Produk Tidak Diketahui' }}"
                                            data-produk-kode="{{ $r->produk->kode_barang ?? '-' }}"
                                            data-tipe-badge="{{ $r->produk ? ($r->produk->is_bahan_setengah_jadi ? 'BSJ' : ($r->produk->tipe_penjualan ?: 'Barang Jadi')) : '' }}"
                                            data-output-qty="{{ (int) $r->output_qty }}"
                                            data-satuan-output="{{ $r->produk->satuan ?? $r->satuan_output }}"
                                            data-bahanbaku="{{ json_encode($r->bahanbaku) }}">
                                        Lihat
                                    </button>
                                    
                                    <button type="button" 
                                            class="btn btn-warning btn-sm btn-edit-resep rounded-2 px-2"
                                            data-id="{{ $r->id }}"
                                            data-produk_id="{{ $r->produk_id }}"
                                            data-output_qty="{{ (int) $r->output_qty }}"
                                            data-satuan_output="{{ $r->produk->satuan ?? $r->satuan_output }}"
                                            data-btkl="{{ (int) $r->btkl_per_batch }}"
                                            data-bop="{{ (int) $r->bop_per_batch }}"
                                            data-bahanbaku="{{ json_encode($r->bahanbaku) }}"
                                            data-page="{{ $data->currentPage() }}">
                                        Edit
                                    </button>
 
                                    <form action="{{ route('resep.destroy', $r->id) }}" method="POST" class="d-inline m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus resep ini?')">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-5 fs-6">
                                <i class="fas fa-folder-open d-block mb-2 fs-3 opacity-50"></i>
                                Belum ada data resep yang sesuai dengan filter atau tersimpan.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($data->hasPages())
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                {{ $data->links() }}
            </div>
        @endif
    </div>
</div>

<form id="form-resep" action="{{ route('resep.store') }}" method="POST">
    @csrf
    <input type="hidden" name="_method" id="form-method" value="POST">

    <div class="modal fade" id="modalResep" data-bs-backdrop="static" tabindex="-1" aria-labelledby="modalResepTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 14px; overflow: visible;">
                
                <div class="modal-header bg-light py-3" style="border-top-left-radius: 14px; border-top-right-radius: 14px;">
                    <h5 class="modal-title fw-bold text-dark" id="modalResepTitle">Tambah Resep Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body px-4 py-3" style="overflow: visible;">
                    
                    {{-- PILIH MASTER PRODUK BARANG JADI / BSJ --}}
                    <div class="mb-3 position-relative" style="z-index: 1050;">
                        <label class="form-label fw-bold small text-secondary">Produk</label>
                        <select name="produk_id" id="produk_id" class="form-select produk-select" required>
                            <option value="" disabled selected>-- Pilih Produk --</option>
                            @foreach($produk as $p)
                                @php
                                    $tipeLabel = '';
                                    if ($p->is_bahan_setengah_jadi) {
                                        $tipeLabel = 'Bahan Setengah Jadi';
                                    } elseif ($p->is_barang_jadi) {
                                        $tipeLabel = $p->tipe_penjualan ?: 'Barang Jadi';
                                    } else {
                                        $tipeLabel = 'Produk';
                                    }
                                    $pSatuanKonv = $p->satuan_pembelian ? strtoupper($p->satuan_pembelian) : '';
                                    $pKonv = floatval($p->konversi_pembelian ?? 1);
                                @endphp
                                <option value="{{ $p->id }}" 
                                        data-satuan="{{ $p->satuan }}" 
                                        data-satuan-konversi="{{ $pSatuanKonv }}"
                                        data-konversi="{{ $pKonv }}"
                                        data-has-resep="{{ $p->resep_id ? 'true' : 'false' }}">
                                    {{ $p->nama }} ({{ $tipeLabel }})
                                </option>
                            @endforeach
                        </select>
                        <div id="resep-konversi-info" class="small text-primary mt-1 d-none font-monospace fw-semibold">
                            <i class="bi bi-info-circle me-1"></i> <span id="resep-konversi-text"></span>
                        </div>
                        <small class="text-danger d-none mt-1 d-block" id="edit-produk-warning">
                            <i class="fas fa-info-circle me-1"></i> Produk tidak dapat diganti saat mengedit resep.
                        </small>
                    </div>

                    {{-- TARGET OUTPUT PROSES PRODUKSI --}}
                    <div class="row position-relative" style="z-index: 100;">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small text-secondary mb-0">Output per Batch</label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="auto_output_qty" style="cursor: pointer;">
                                    <label class="form-check-label text-muted" for="auto_output_qty" style="cursor: pointer; font-size: 11px;">Otomatis dari Berat Bahan</label>
                                </div>
                            </div>
                            <input type="number" name="output_qty" id="output_qty" class="form-control" min="1" placeholder="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small text-secondary">Satuan Output (Auto)</label>
                            <input type="text" name="satuan_output" id="satuan_output" class="form-control bg-light text-center fw-semibold" readonly placeholder="-">
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">
                    <h6 class="fw-bold text-primary mb-3"><i class="fas fa-flask me-2"></i>Komposisi Komponen Bahan Baku</h6>

                      <div class="table-responsive" style="min-height: 220px; overflow: visible;">
                          <table class="table-resep-clean align-middle mb-0" id="table-bahan">
                              <thead>
                                  <tr>
                                      <th style="width: 57%; text-align: left;">Bahan baku dan alternatif</th>
                                      <th style="width: 20%; text-align: center;">Qty / produk</th>
                                      <th style="width: 15%; text-align: center;">Satuan</th>
                                      <th style="width: 8%; text-align: center;"></th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <tr>
                                      <td class="p-2 align-middle position-relative">
                                          <div class="d-flex flex-wrap align-items-center gap-2">
                                              <div class="selected-alternatives-container d-flex flex-wrap gap-2 align-items-center">
                                                  <!-- Badges here -->
                                              </div>
                                              <!-- Hidden initially when no ingredients -->
                                              <div class="add-alt-trigger-container d-none">
                                                  <a href="javascript:void(0)" class="btn-link-add-alt">
                                                      <i class="fas fa-plus" style="font-size: 11px;"></i> tambah bahan alternatif
                                                  </a>
                                              </div>
                                          </div>

                                          <!-- Shown inline initially, becomes absolute popover when triggered later -->
                                          <div class="select-alt-wrapper">
                                              <div class="d-flex gap-2">
                                                  <div class="flex-grow-1">
                                                      <select class="form-select form-select-sm search-select-alternatif">
                                                          <option value="" disabled selected>Pilih Bahan...</option>
                                                          @foreach($bahan as $b)
                                                              <option value="{{ $b->id }}" data-satuan="{{ $b->satuan }}" data-nama="{{ $b->nama }}">
                                                                  {{ $b->nama }}
                                                              </option>
                                                          @endforeach
                                                      </select>
                                                  </div>
                                                  <button type="button" class="btn btn-light border btn-sm btn-cancel-alternative px-3 d-none" title="Batal"><i class="fas fa-times text-secondary"></i></button>
                                              </div>
                                          </div>

                                          <div class="hidden-inputs-container">
                                              <!-- Hidden inputs -->
                                          </div>
                                      </td>
                                      <td class="p-2 align-middle">
                                          <input type="number" step="any" name="qty_bahan[]" class="form-control form-control-sm text-center" min="0.001" placeholder="0" required style="border-radius: 6px; padding: 6px 12px; font-size: 14px;">
                                      </td>
                                      <td class="p-2 align-middle">
                                          <input type="text" name="satuan[]" class="form-control form-control-sm text-center bg-light satuan-input" readonly placeholder="-" style="border-radius: 6px; padding: 6px 12px; font-size: 14px;">
                                      </td>
                                      <td class="text-center p-2 align-middle">
                                          <button type="button" class="btn btn-remove-row text-danger border-0 bg-transparent px-2" style="font-size: 16px;"><i class="bi bi-trash"></i></button>
                                      </td>
                                  </tr>
                              </tbody>
                          </table>
                      </div>

                      <div class="border-top pt-3 mt-3">
                          <small class="text-muted d-block mb-3" style="font-size: 12px;">
                              Angka pada bahan menandakan urutan prioritas — nomor 1 dipakai otomatis selama stok cukup.
                          </small>
                          <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold shadow-none" id="btn-add-row" style="border-radius: 8px; padding: 8px 16px; border: 1px solid #cbd5e1; color: #0f172a;">
                              <i class="fas fa-plus me-1" style="font-size: 11px;"></i> Tambah baris bahan
                          </button>
                      </div>
                </div>
                
                <div class="modal-footer bg-white border-0 px-4 pb-4 pt-0 justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 500;">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 py-2" id="btn-submit-form" style="border-radius: 8px; font-weight: 500; background-color: #2563eb; border-color: #2563eb;">Simpan resep</button>
                </div>

            </div>
        </div>
    </div>
</form>

{{-- ================= MODAL BARANG TANPA RESEP ================= --}}
<div class="modal fade" id="modalTanpaResep" tabindex="-1" aria-labelledby="modalTanpaResepLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background-color: #fef3c7; color: #d97706;">
                        <i class="fas fa-clipboard-list fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="modalTanpaResepLabel">Produk Belum Memiliki Resep</h5>
                        <small class="text-muted">Daftar Bahan Setengah Jadi (BSJ) dan Barang Jadi (POS) yang siap dikonfigurasi resepnya</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                {{-- Quick Filter / Nav Tabs --}}
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <ul class="nav nav-pills gap-2" id="tanpaResepTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-3 py-1 fw-semibold small shadow-sm" id="tab-bsj-btn" data-bs-toggle="pill" data-bs-target="#tab-bsj-content" type="button" role="tab">
                                <i class="bi bi-gear-fill me-1"></i>Bahan Setengah Jadi
                                <span class="badge bg-white text-dark ms-1 rounded-pill">{{ $bsjTanpaResep ? $bsjTanpaResep->count() : 0 }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-3 py-1 fw-semibold small shadow-sm" id="tab-pos-btn" data-bs-toggle="pill" data-bs-target="#tab-pos-content" type="button" role="tab">
                                <i class="bi bi-shop me-1"></i>Barang Jadi / POS
                                <span class="badge bg-white text-dark ms-1 rounded-pill">{{ $posTanpaResep ? $posTanpaResep->count() : 0 }}</span>
                            </button>
                        </li>
                    </ul>

                    {{-- Search bar in modal --}}
                    <div style="width: 250px;">
                        <input type="text" id="search-modal-tanpa-resep" class="form-control form-control-sm rounded-pill" placeholder="Cari di daftar ini...">
                    </div>
                </div>

                {{-- Tab Contents --}}
                <div class="tab-content" id="tanpaResepTabContent">
                    {{-- TAB BSJ --}}
                    <div class="tab-pane fade show active" id="tab-bsj-content" role="tabpanel">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="table-responsive" style="max-height: 420px;">
                                <table class="table table-hover align-middle mb-0" id="table-tanpa-resep-bsj">
                                    <thead class="table-light text-secondary text-uppercase fs-7 text-center sticky-top">
                                        <tr>
                                            <th width="50">No</th>
                                            <th width="120">Kode</th>
                                            <th class="text-start">Nama Bahan Setengah Jadi</th>
                                            <th width="120">Satuan</th>
                                            <th width="150">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        @forelse($bsjTanpaResep as $idx => $b)
                                        <tr class="item-tanpa-resep-row" data-search="{{ strtolower($b->kode_barang . ' ' . $b->nama) }}">
                                            <td class="text-muted small">{{ $idx + 1 }}</td>
                                            <td><span class="badge bg-light text-dark border font-monospace">{{ $b->kode_barang }}</span></td>
                                            <td class="text-start fw-semibold text-dark">{{ $b->nama }}</td>
                                            <td><span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">{{ $b->satuan }}</span></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-semibold btn-buat-resep-langsung" data-id="{{ $b->id }}">
                                                    <i class="fas fa-plus me-1"></i>Buat Resep
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="fas fa-check-circle text-success fs-4 d-block mb-2"></i>
                                                Semua Bahan Setengah Jadi sudah memiliki resep!
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- TAB POS / BARANG JADI --}}
                    <div class="tab-pane fade" id="tab-pos-content" role="tabpanel">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="table-responsive" style="max-height: 420px;">
                                <table class="table table-hover align-middle mb-0" id="table-tanpa-resep-pos">
                                    <thead class="table-light text-secondary text-uppercase fs-7 text-center sticky-top">
                                        <tr>
                                            <th width="50">No</th>
                                            <th width="120">Kode</th>
                                            <th class="text-start">Nama Produk / Barang Jadi</th>
                                            <th>Kategori / Tipe</th>
                                            <th width="120">Satuan</th>
                                            <th width="150">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        @forelse($posTanpaResep as $idx => $p)
                                        <tr class="item-tanpa-resep-row" data-search="{{ strtolower($p->kode_barang . ' ' . $p->nama . ' ' . ($p->kategori->nama ?? '') . ' ' . $p->tipe_penjualan) }}">
                                            <td class="text-muted small">{{ $idx + 1 }}</td>
                                            <td><span class="badge bg-light text-dark border font-monospace">{{ $p->kode_barang }}</span></td>
                                            <td class="text-start fw-semibold text-dark">{{ $p->nama }}</td>
                                            <td>
                                                @if($p->tipe_penjualan === 'POS Kejingga')
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                        <i class="bi bi-shop me-1"></i>POS Kejingga
                                                    </span>
                                                @elseif($p->tipe_penjualan === 'POS Gaharu')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-shop me-1"></i>POS Gaharu
                                                    </span>
                                                @elseif($p->tipe_penjualan === 'B2B')
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                        <i class="bi bi-building me-1"></i>B2B
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-secondary border">
                                                        {{ $p->kategori->nama ?? 'Makanan & Minuman' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-light text-dark border px-2 py-1">{{ $p->satuan }}</span></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-semibold btn-buat-resep-langsung" data-id="{{ $p->id }}">
                                                    <i class="fas fa-plus me-1"></i>Buat Resep
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-check-circle text-success fs-4 d-block mb-2"></i>
                                                Semua Barang Jadi / POS sudah memiliki resep!
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm px-3 rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
{{-- ================= MODAL LIHAT DETAIL RESEP ================= --}}
<div class="modal fade" id="modalDetailResep" tabindex="-1" aria-labelledby="modalDetailResepTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-light py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 36px; height: 36px; background-color: #0ea5e9;">
                        <i class="fas fa-receipt fs-6"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="modalDetailResepTitle">Detail Resep Produk</h5>
                        <small class="text-muted" id="modalDetailResepSubtitle">Informasi komposisi dan takaran bahan baku</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-white">
                {{-- HEADER INFO PRODUK --}}
                <div class="p-3 rounded-3 mb-3 border bg-light">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">Nama Produk</span>
                            <h4 class="fw-bold text-dark mb-1" id="detail-produk-nama">-</h4>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="badge bg-white text-dark border font-monospace" id="detail-produk-kode">-</span>
                                <span id="detail-produk-badge"></span>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">Output per Batch</span>
                            <div class="fs-4 fw-bold text-primary" id="detail-output-qty">-</div>
                        </div>
                    </div>
                </div>

                {{-- ATURAN KONVERSI / INFO BIAYA --}}
                <div class="alert alert-light border py-2 px-3 rounded-3 mb-3 d-flex align-items-center gap-2" style="font-size: 12.5px;">
                    <i class="fas fa-info-circle text-primary fs-5"></i>
                    <div>
                        <strong>Biaya Konversi (BTKL & BOP):</strong> Otomatis dihitung sebesar <strong>30%</strong> dari total nilai penggunaan bahan baku menggunakan harga FIFO riil.
                    </div>
                </div>

                {{-- TABEL KOMPOSISI BAHAN --}}
                <div class="card border rounded-3 overflow-hidden shadow-none mb-0">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px;">
                            <i class="fas fa-flask text-primary me-2"></i>Komposisi Bahan Baku &amp; Takaran
                        </span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" id="detail-total-bahan-badge">0 Bahan</span>
                    </div>
                    <div class="table-responsive" style="max-height: 350px;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light text-secondary text-uppercase fs-7">
                                <tr>
                                    <th width="40" class="text-center py-2">No</th>
                                    <th class="py-2">Bahan Baku &amp; Alternatif</th>
                                    <th width="140" class="text-center py-2">Qty / Batch</th>
                                    <th width="110" class="text-center py-2">Satuan</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-detail-resep">
                                <!-- Dynamic items -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-4 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-warning btn-sm text-dark fw-semibold px-3 rounded-3" id="btn-edit-dari-detail">
                    <i class="fas fa-pencil-alt me-1"></i>Edit Resep Ini
                </button>
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- ================= MODAL IMPORT EXCEL RESEP ================= --}}
<div class="modal fade" id="modalImportResep" tabindex="-1" aria-labelledby="modalImportResepLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: #7A4517;">
                <h5 class="modal-title fw-bold" id="modalImportResepLabel">
                    <i class="fas fa-file-excel me-2"></i>Import Resep dari Excel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('resep.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body text-start">
                    <p class="text-muted small mb-3">
                        Gunakan file Excel untuk mengunggah resep secara massal. Produk yang sudah memiliki resep akan otomatis dilewati agar tidak terjadi duplikasi.
                    </p>

                    <div class="mb-3">
                        <a href="{{ route('resep.import.template') }}" class="btn btn-sm btn-outline-secondary rounded-3">
                            <i class="fas fa-download me-1"></i> Unduh Template Excel
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Pilih File Excel (.xlsx)</label>
                        <input type="file" name="file" class="form-control rounded-3 @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-secondary px-3 rounded-3" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn text-white px-4 rounded-3" style="background-color: #7A4517;">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Styling & Z-Index Choices.js Dropdown di dalam Modal agar tidak bertabrakan */
    .choices {
        margin-bottom: 0;
        position: relative;
    }
    .choices.is-open {
        z-index: 1060 !important;
    }
    .choices__list--dropdown {
        z-index: 1060 !important;
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.15) !important;
        border-radius: 8px !important;
        max-height: 200px !important;
    }
    .choices__list--dropdown .choices__item--selectable {
        padding: 8px 12px !important;
        font-size: 14px !important;
    }
    .choices__list--dropdown .choices__item--selectable.is-highlighted {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
    }
    .choices[data-type*="select-one"] .choices__inner {
        padding: 5px 10px;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        min-height: 38px;
        font-size: 14px;
    }
    .filter-choices-wrapper .choices[data-type*="select-one"] .choices__inner {
        min-height: 31px !important;
        padding: 2px 8px !important;
        font-size: 12.5px !important;
        border-radius: 6px !important;
    }
    .filter-choices-wrapper .choices__list--single {
        padding: 0 !important;
    }
    .filter-choices-wrapper .choices__input {
        font-size: 12.5px !important;
        padding: 4px 8px !important;
    }
    #table-bahan thead {
        position: relative;
        z-index: 1 !important;
    }
    #table-bahan tr {
        position: relative;
    }

    /* Custom Chip / Tag untuk Bahan */
    .alt-chip {
        display: inline-flex;
        align-items: center;
        background-color: #f1f5f9 !important; /* neutral gray light */
        color: #475569 !important; /* neutral gray dark */
        font-weight: 500;
        font-size: 13px !important;
        padding: 4px 10px !important;
        border-radius: 50px !important;
        gap: 6px;
        border: 1px solid #cbd5e1 !important;
        margin: 2px 0;
    }
    .alt-chip[data-priority="1"] {
        background-color: #dbeafe !important; /* blue light */
        color: #1d4ed8 !important; /* blue dark */
        border: 1px solid #bfdbfe !important;
    }
    .alt-chip[data-priority="1"] .chip-num {
        background-color: #1d4ed8 !important;
    }
    .alt-chip .chip-num {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        color: #fff;
        background-color: #475569; /* gray */
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: bold;
    }
    .alt-chip .remove-alt-badge {
        color: inherit;
        opacity: 0.6;
        text-decoration: none;
        font-size: 12px;
        line-height: 1;
        margin-left: 2px;
        transition: opacity 0.2s;
    }
    .alt-chip .remove-alt-badge:hover {
        opacity: 1;
    }

    /* Polos + Tambah Bahan Alternatif */
    .btn-link-add-alt {
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
        color: #475569 !important;
        font-weight: 500 !important;
        font-size: 13px !important;
        padding: 5px 14px !important;
        background-color: #fff !important;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none !important;
    }
    .btn-link-add-alt:hover {
        background-color: #f8fafc !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }

    /* Input Styling agar mirip referensi */
    .table-resep-clean {
        width: 100%;
        border-collapse: collapse;
    }
    .table-resep-clean td, .table-resep-clean th {
        border: none !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 16px 8px !important;
    }
    .table-resep-clean thead th {
        color: #64748b;
        font-weight: 500;
        font-size: 12px;
        border-bottom: 2px solid #f1f5f9 !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const elemenModal = document.getElementById('modalResep');
    const bsModalInstance = new bootstrap.Modal(elemenModal);

    const formResep = document.getElementById('form-resep');
    const formMethod = document.getElementById('form-method');
    const modalTitle = document.getElementById('modalResepTitle');
    const btnSubmit = document.getElementById('btn-submit-form');
    
    const selectProduk = document.getElementById('produk_id');
    const warningProduk = document.getElementById('edit-produk-warning');
    const inputSatuanOutput = document.getElementById('satuan_output');
    
    const tbodyBahan = document.querySelector('#table-bahan tbody');
    const rowBlueprint = tbodyBahan.querySelector('tr').cloneNode(true);

    const checkboxAuto = document.getElementById('auto_output_qty');
    const inputOutputQty = document.getElementById('output_qty');

    function recalculateAutoOutput() {
        if (!checkboxAuto || !inputOutputQty) return;

        if (checkboxAuto.checked) {
            inputOutputQty.readOnly = true;
            let sum = 0;
            tbodyBahan.querySelectorAll('input[name="qty_bahan[]"]').forEach(input => {
                const val = parseFloat(input.value) || 0;
                sum += val;
            });
            inputOutputQty.value = sum > 0 ? Number(sum.toFixed(4)) : '';
        } else {
            inputOutputQty.readOnly = false;
        }
    }

    if (checkboxAuto) {
        checkboxAuto.addEventListener('change', recalculateAutoOutput);
    }

    tbodyBahan.addEventListener('input', function(e) {
        if (e.target.name === 'qty_bahan[]') {
            recalculateAutoOutput();
        }
    });

    const produkChoices = new Choices(selectProduk, {
        searchEnabled: true,
        itemSelectText: '',
        shouldSort: false,
    });

    // Inisialisasi Choices.js untuk filter dropdown search produk & search bahan
    const filterProdukEl = document.getElementById('filter_search_produk');
    if (filterProdukEl) {
        new Choices(filterProdukEl, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
            placeholder: true,
            placeholderValue: '-- Cari Produk --',
            allowHTML: false,
        });
    }

    const filterBahanEl = document.getElementById('filter_search_bahan');
    if (filterBahanEl) {
        new Choices(filterBahanEl, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
            placeholder: true,
            placeholderValue: '-- Cari Bahan Baku --',
            allowHTML: false,
        });
    }

    // Helper: update all hidden inputs inside a row based on selected items
    function updateHiddenInputs(row, rowIndex) {
        const hiddenContainer = row.querySelector('.hidden-inputs-container');
        hiddenContainer.innerHTML = '';
        
        const badges = row.querySelectorAll('.selected-alternatives-container .alt-chip');
        badges.forEach(badge => {
            const val = badge.dataset.value;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `bahan_ids[${rowIndex}][]`;
            input.value = val;
            hiddenContainer.appendChild(input);
        });

        // Sync satuan based on the 1st (priority 1) material
        if (badges.length > 0) {
            const firstSatuan = badges[0].dataset.satuan;
            row.querySelector('.satuan-input').value = firstSatuan || '-';
        } else {
            row.querySelector('.satuan-input').value = '-';
        }
    }

    // Helper: update UI visibility based on whether chips exist
    function updateRowUIState(row) {
        const container = row.querySelector('.selected-alternatives-container');
        const count = container.querySelectorAll('.alt-chip').length;
        const selectWrapper = row.querySelector('.select-alt-wrapper');
        const triggerBtnContainer = row.querySelector('.add-alt-trigger-container');
        const cancelBtn = row.querySelector('.btn-cancel-alternative');
        
        if (count === 0) {
            // Show inline select wrapper
            triggerBtnContainer.classList.add('d-none');
            selectWrapper.classList.remove('d-none', 'position-absolute', 'shadow-sm', 'border', 'rounded', 'bg-white', 'p-2');
            selectWrapper.style.top = '';
            selectWrapper.style.left = '';
            selectWrapper.style.right = '';
            selectWrapper.style.zIndex = '';
            cancelBtn.classList.add('d-none');
        } else {
            // Show chips + add trigger, hide select wrapper unless triggered
            triggerBtnContainer.classList.remove('d-none');
            
            // Re-apply absolute positioning classes so when triggered it acts like a popover
            selectWrapper.classList.add('d-none', 'position-absolute', 'shadow-sm', 'border', 'rounded', 'bg-white', 'p-2');
            selectWrapper.style.top = '100%';
            selectWrapper.style.left = '8px';
            selectWrapper.style.right = '8px';
            selectWrapper.style.zIndex = '1065';
            cancelBtn.classList.remove('d-none');
        }
    }

    // Helper: rebuild badges and numbers for a row
    function rebuildBadges(row, rowIndex) {
        const container = row.querySelector('.selected-alternatives-container');
        const badges = container.querySelectorAll('.alt-chip');
        badges.forEach((badge, idx) => {
            const num = idx + 1;
            const nama = badge.dataset.nama;
            badge.setAttribute('data-priority', num);
            badge.innerHTML = `<span class="chip-num">${num}</span> ${nama} <a href="#" class="remove-alt-badge">✕</a>`;
        });
        updateHiddenInputs(row, rowIndex);
        updateRowUIState(row);
    }

    // Helper: update all rows indexes (name="bahan_ids[rowIndex][]")
    function updateAllRowIndexes() {
        const rows = tbodyBahan.querySelectorAll('tr');
        rows.forEach((row, rowIndex) => {
            updateHiddenInputs(row, rowIndex);
        });
    }

    // Setup events for a row
    function setupRowEvents(row) {
        const select = row.querySelector('.search-select-alternatif');
        const btnCancel = row.querySelector('.btn-cancel-alternative');
        const btnLinkAddAlt = row.querySelector('.btn-link-add-alt');
        const selectWrapper = row.querySelector('.select-alt-wrapper');
        const container = row.querySelector('.selected-alternatives-container');

        // Initialize choices search for this specific select
        const choice = new Choices(select, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
            placeholder: true,
            placeholderValue: 'Pilih Bahan...',
        });
        select.choicesInstance = choice;

        // Toggle dropdown open
        btnLinkAddAlt.addEventListener('click', function(e) {
            e.preventDefault();
            selectWrapper.classList.remove('d-none');
            // Allow DOM to update before showing dropdown
            setTimeout(() => {
                choice.showDropdown();
            }, 50);
        });

        // Cancel / Hide dropdown
        btnCancel.addEventListener('click', function(e) {
            e.preventDefault();
            choice.setChoiceByValue('');
            selectWrapper.classList.add('d-none');
        });

        // Auto add when item is selected
        select.addEventListener('change', function() {
            const val = select.value;
            if (!val) return;

            const activeOption = select.options[select.selectedIndex];
            const name = activeOption.dataset.nama;
            const satuan = activeOption.dataset.satuan;

            // Check if already selected in the entire recipe (any row)
            let exists = false;
            tbodyBahan.querySelectorAll('.alt-chip').forEach(b => {
                if (b.dataset.value == val) exists = true;
            });

            if (exists) {
                alert('Bahan ini sudah terpilih di resep ini.');
                choice.setChoiceByValue('');
                return;
            }

            // Create badge/chip
            const badge = document.createElement('span');
            badge.className = 'alt-chip';
            badge.dataset.value = val;
            badge.dataset.nama = name;
            badge.dataset.satuan = satuan;

            container.appendChild(badge);
            
            // Clear selection and hide select wrapper
            choice.setChoiceByValue('');
            selectWrapper.classList.add('d-none');

            // Rebuild
            const rows = Array.from(tbodyBahan.querySelectorAll('tr'));
            const rowIndex = rows.indexOf(row);
            rebuildBadges(row, rowIndex);
        });

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-alt-badge')) {
                e.preventDefault();
                e.target.closest('.alt-chip').remove();
                const rows = Array.from(tbodyBahan.querySelectorAll('tr'));
                const rowIndex = rows.indexOf(row);
                rebuildBadges(row, rowIndex);
            }
        });
        
        // Initialize state for this row on setup
        updateRowUIState(row);
    }

    selectProduk.addEventListener('change', function() {
        let opt = this.options[this.selectedIndex];
        inputSatuanOutput.value = opt ? (opt.dataset.satuan ?? '') : '';

        const konvInfoBox = document.getElementById('resep-konversi-info');
        const konvInfoText = document.getElementById('resep-konversi-text');
        if (opt && opt.dataset.satuanKonversi && parseFloat(opt.dataset.konversi || 1) > 1) {
            konvInfoText.textContent = `Konversi: 1 ${opt.dataset.satuanKonversi} = ${Number(opt.dataset.konversi).toLocaleString('id-ID')} ${opt.dataset.satuan}. Anda dapat menyusun resep per 1 ${opt.dataset.satuanKonversi} (Output: ${Number(opt.dataset.konversi).toLocaleString('id-ID')} ${opt.dataset.satuan}) atau per batch.`;
            konvInfoBox.classList.remove('d-none');
        } else if (konvInfoBox) {
            konvInfoBox.classList.add('d-none');
        }

        // Prevent selecting product with existing recipe during creation (POST method)
        if (formMethod.value === "POST" && opt && opt.dataset.hasResep === 'true') {
            alert('Produk ini sudah memiliki resep! Silakan pilih produk lain.');
            produkChoices.setChoiceByValue('');
            inputSatuanOutput.value = '';
            if (konvInfoBox) konvInfoBox.classList.add('d-none');
        }
    });

    // Tambah baris baru
    document.getElementById('btn-add-row').addEventListener('click', function() {
        let barisBaru = rowBlueprint.cloneNode(true);
        barisBaru.querySelector('.selected-alternatives-container').innerHTML = '';
        barisBaru.querySelector('.hidden-inputs-container').innerHTML = '';
        barisBaru.querySelector('input[name="qty_bahan[]"]').value = '';
        barisBaru.querySelector('.satuan-input').value = '-';
        
        tbodyBahan.appendChild(barisBaru);
        setupRowEvents(barisBaru);
        updateAllRowIndexes();
        recalculateAutoOutput();
    });

    // Hapus baris
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-row')) {
            if (tbodyBahan.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
                updateAllRowIndexes();
                recalculateAutoOutput();
            } else {
                alert('Resep minimal wajib memiliki 1 baris komponen bahan baku!');
            }
        }
    });

    // ============ MODAL TAMBAH HELPER ============
    function openModalTambahDenganProduk(preselectedProdukId = null) {
        modalTitle.innerText = "Tambah Resep Baru";
        btnSubmit.innerText = "Simpan Resep";
        btnSubmit.className = "btn btn-primary px-4 shadow-sm";
        formResep.action = "{{ route('resep.store') }}";
        formMethod.value = "POST";
        
        formResep.reset();
        produkChoices.enable();
        produkChoices.removeActiveItems();
        warningProduk.classList.add('d-none');

        if (checkboxAuto) {
            checkboxAuto.checked = false;
            inputOutputQty.readOnly = false;
        }
        
        tbodyBahan.innerHTML = '';
        let barisAwal = rowBlueprint.cloneNode(true);
        barisAwal.querySelector('.selected-alternatives-container').innerHTML = '';
        barisAwal.querySelector('.hidden-inputs-container').innerHTML = '';
        tbodyBahan.appendChild(barisAwal);
        
        setupRowEvents(barisAwal);
        updateAllRowIndexes();

        if (preselectedProdukId) {
            produkChoices.setChoiceByValue(String(preselectedProdukId));
            const selectedOpt = selectProduk.querySelector(`option[value="${preselectedProdukId}"]`);
            if (selectedOpt) {
                inputSatuanOutput.value = selectedOpt.dataset.satuan || '';
                const konvInfoBox = document.getElementById('resep-konversi-info');
                const konvInfoText = document.getElementById('resep-konversi-text');
                if (selectedOpt.dataset.satuanKonversi && parseFloat(selectedOpt.dataset.konversi || 1) > 1) {
                    konvInfoText.textContent = `Konversi: 1 ${selectedOpt.dataset.satuanKonversi} = ${Number(selectedOpt.dataset.konversi).toLocaleString('id-ID')} ${selectedOpt.dataset.satuan}. Anda dapat menyusun resep per 1 ${selectedOpt.dataset.satuanKonversi} (Output: ${Number(selectedOpt.dataset.konversi).toLocaleString('id-ID')} ${selectedOpt.dataset.satuan}) atau per batch.`;
                    konvInfoBox.classList.remove('d-none');
                } else if (konvInfoBox) {
                    konvInfoBox.classList.add('d-none');
                }
            }
        }

        bsModalInstance.show();
    }

    // ============ MODAL TAMBAH ============
    document.getElementById('btn-tambah-resep').addEventListener('click', function() {
        openModalTambahDenganProduk();
    });

    // ============ EVENT BUAT RESEP LANGSUNG DARI MODAL TANPA RESEP ============
    const modalTanpaResepEl = document.getElementById('modalTanpaResep');
    document.querySelectorAll('.btn-buat-resep-langsung').forEach(btn => {
        btn.addEventListener('click', function() {
            const barangId = this.dataset.id;
            if (modalTanpaResepEl) {
                const modalTanpaResepInst = bootstrap.Modal.getInstance(modalTanpaResepEl);
                if (modalTanpaResepInst) {
                    modalTanpaResepEl.addEventListener('hidden.bs.modal', function onTanpaResepHidden() {
                        modalTanpaResepEl.removeEventListener('hidden.bs.modal', onTanpaResepHidden);
                        openModalTambahDenganProduk(barangId);
                    });
                    modalTanpaResepInst.hide();
                    return;
                }
            }
            openModalTambahDenganProduk(barangId);
        });
    });

    // ============ SEARCH FILTER DI MODAL TANPA RESEP ============
    const searchModalTanpaResep = document.getElementById('search-modal-tanpa-resep');
    if (searchModalTanpaResep) {
        searchModalTanpaResep.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll('.item-tanpa-resep-row').forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                if (searchData.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // ============ MODAL EDIT ============
    document.querySelectorAll('.btn-edit-resep').forEach(tombol => {
        tombol.addEventListener('click', function() {
            modalTitle.innerText = "Edit Resep Produk";
            btnSubmit.innerText = "Update Resep";
            btnSubmit.className = "btn btn-warning px-4 text-dark shadow-sm fw-semibold";
            
            const idResep = this.dataset.id;
            const pageNum = this.dataset.page || 1;
            formResep.action = `/resep/${idResep}?page=${pageNum}`; 
            formMethod.value = "PUT";

            produkChoices.setChoiceByValue(this.dataset.produk_id);
            produkChoices.disable();
            warningProduk.classList.remove('d-none');

            document.getElementById('output_qty').value = this.dataset.output_qty;
            const selectedProdOption = selectProduk.querySelector(`option[value="${this.dataset.produk_id}"]`);
            const currentProdSatuan = (selectedProdOption && selectedProdOption.dataset.satuan) ? selectedProdOption.dataset.satuan : this.dataset.satuan_output;
            inputSatuanOutput.value = currentProdSatuan || this.dataset.satuan_output || '-';

            tbodyBahan.innerHTML = '';
            
            const arrayBahanBaku = JSON.parse(this.dataset.bahanbaku);

            const getMasterSatuan = (bahanId, fallback) => {
                if (!bahanId) return fallback || '-';
                const opt = rowBlueprint.querySelector(`select.search-select-alternatif option[value="${bahanId}"]`);
                return (opt && opt.dataset.satuan) ? opt.dataset.satuan : (fallback || '-');
            };

            if (arrayBahanBaku && arrayBahanBaku.length > 0) {
                arrayBahanBaku.forEach((item, rowIndex) => {
                    let barisEdit = rowBlueprint.cloneNode(true);
                    const container = barisEdit.querySelector('.selected-alternatives-container');
                    container.innerHTML = '';
                    
                    // Add Primary Ingredient
                    const pBadge = document.createElement('span');
                    pBadge.className = 'alt-chip';
                    pBadge.dataset.value = item.bahan_id;
                    pBadge.dataset.nama = item.bahan ? item.bahan.nama : 'Bahan';
                    const primarySatuan = (item.bahan && item.bahan.satuan) ? item.bahan.satuan : getMasterSatuan(item.bahan_id, item.satuan);
                    pBadge.dataset.satuan = primarySatuan;
                    container.appendChild(pBadge);

                    // Add Alternatives
                    if (item.alternatif && item.alternatif.length > 0) {
                        item.alternatif.forEach(alt => {
                            const aBadge = document.createElement('span');
                            aBadge.className = 'alt-chip';
                            aBadge.dataset.value = alt.bahan_id;
                            aBadge.dataset.nama = alt.bahan ? alt.bahan.nama : 'Bahan';
                            const altSatuan = (alt.bahan && alt.bahan.satuan) ? alt.bahan.satuan : getMasterSatuan(alt.bahan_id, primarySatuan);
                            aBadge.dataset.satuan = altSatuan;
                            container.appendChild(aBadge);
                        });
                    }

                    barisEdit.querySelector('input[name="qty_bahan[]"]').value = parseFloat(item.qty_bahan);
                    
                    tbodyBahan.appendChild(barisEdit);
                    setupRowEvents(barisEdit);
                    rebuildBadges(barisEdit, rowIndex);
                });
            } else {
                let barisKosong = rowBlueprint.cloneNode(true);
                barisKosong.querySelector('.selected-alternatives-container').innerHTML = '';
                tbodyBahan.appendChild(barisKosong);
                setupRowEvents(barisKosong);
                updateAllRowIndexes();
            }

            // Check if output_qty equals sum of ingredients to automatically check the toggle
            let sumBahan = 0;
            if (arrayBahanBaku && arrayBahanBaku.length > 0) {
                arrayBahanBaku.forEach(item => {
                    sumBahan += parseFloat(item.qty_bahan) || 0;
                });
            }
            if (checkboxAuto) {
                if (Math.abs(sumBahan - parseFloat(this.dataset.output_qty)) < 0.0001 && sumBahan > 0) {
                    checkboxAuto.checked = true;
                    inputOutputQty.readOnly = true;
                } else {
                    checkboxAuto.checked = false;
                    inputOutputQty.readOnly = false;
                }
            }

            bsModalInstance.show();
        });
    });

    // ============ MODAL LIHAT DETAIL RESEP ============
    const modalDetailEl = document.getElementById('modalDetailResep');
    let activeResepIdForEdit = null;

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-lihat-resep');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        activeResepIdForEdit = btn.getAttribute('data-id') || btn.dataset.id;
        const produkNama = btn.getAttribute('data-produk-nama') || btn.dataset.produkNama || '-';
        const produkKode = btn.getAttribute('data-produk-kode') || btn.dataset.produkKode || '-';
        const tipeBadge = btn.getAttribute('data-tipe-badge') || btn.dataset.tipeBadge || '';
        const outputQty = btn.getAttribute('data-output-qty') || btn.dataset.outputQty || '0';
        const satuanOutput = btn.getAttribute('data-satuan-output') || btn.dataset.satuanOutput || '-';

        const elemNama = document.getElementById('detail-produk-nama');
        const elemKode = document.getElementById('detail-produk-kode');
        const elemOutput = document.getElementById('detail-output-qty');

        if (elemNama) elemNama.innerText = produkNama;
        if (elemKode) elemKode.innerText = produkKode;
        if (elemOutput) elemOutput.innerText = `${outputQty} ${satuanOutput}`;

        // Tipe badge
        const badgeContainer = document.getElementById('detail-produk-badge');
        if (badgeContainer) {
            if (tipeBadge === 'BSJ') {
                badgeContainer.innerHTML = `<span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 11px;"><i class="bi bi-gear me-1"></i>Bahan Setengah Jadi</span>`;
            } else if (tipeBadge === 'POS Kejingga') {
                badgeContainer.innerHTML = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 11px;"><i class="bi bi-shop me-1"></i>POS Kejingga</span>`;
            } else if (tipeBadge === 'POS Gaharu') {
                badgeContainer.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 11px;"><i class="bi bi-shop me-1"></i>POS Gaharu</span>`;
            } else if (tipeBadge === 'B2B') {
                badgeContainer.innerHTML = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 11px;"><i class="bi bi-building me-1"></i>B2B</span>`;
            } else if (tipeBadge) {
                badgeContainer.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 11px;">${tipeBadge}</span>`;
            } else {
                badgeContainer.innerHTML = '';
            }
        }

        // Render daftar bahan baku
        const tbodyDetail = document.getElementById('tbody-detail-resep');
        if (tbodyDetail) {
            tbodyDetail.innerHTML = '';

            let bahanList = [];
            try {
                const rawBahan = btn.getAttribute('data-bahanbaku') || btn.dataset.bahanbaku || '[]';
                bahanList = typeof rawBahan === 'string' ? JSON.parse(rawBahan) : (rawBahan || []);
            } catch (err) {
                console.error('Failed to parse data-bahanbaku:', err);
                bahanList = [];
            }

            const elemTotalBadge = document.getElementById('detail-total-bahan-badge');
            if (elemTotalBadge) elemTotalBadge.innerText = `${bahanList.length} Bahan`;

            if (bahanList.length === 0) {
                tbodyDetail.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">Belum ada bahan baku yang terdaftar.</td></tr>`;
            } else {
                bahanList.forEach((b, idx) => {
                    const primaryName = b.bahan ? b.bahan.nama : 'Bahan Tidak Diketahui';
                    const primaryKode = b.bahan ? b.bahan.kode_barang : '';
                    const satuan = b.satuan || (b.bahan ? b.bahan.satuan : '-');
                    const qty = parseFloat(b.qty_bahan) || 0;

                    let altHtml = '';
                    if (b.alternatif && b.alternatif.length > 0) {
                        b.alternatif.forEach(alt => {
                            const altName = alt.bahan ? alt.bahan.nama : 'Bahan Alternatif';
                            const prio = alt.prioritas || 2;
                            altHtml += `
                                <div class="ms-3 mt-1 d-flex align-items-center gap-1 text-muted small" style="font-size: 11.5px;">
                                    <span class="badge bg-secondary rounded-pill" style="font-size: 9px; padding: 2px 6px;">${prio}</span>
                                    <span>${altName}</span>
                                    <span class="text-warning-emphasis fst-italic" style="font-size: 11px;">(substitusi)</span>
                                </div>
                            `;
                        });
                    }

                    const rowHtml = `
                        <tr>
                            <td class="text-center text-muted fw-semibold">${idx + 1}</td>
                            <td>
                                <div class="fw-semibold text-dark d-flex align-items-center gap-1">
                                    <span class="badge bg-primary rounded-pill" style="font-size: 10px; padding: 3px 7px;">1</span>
                                    <span>${primaryName}</span>
                                    ${primaryKode ? `<span class="font-monospace text-muted small ms-1" style="font-size: 11px;">(${primaryKode})</span>` : ''}
                                </div>
                                ${altHtml}
                            </td>
                            <td class="text-center fw-bold text-dark fs-6">${qty.toLocaleString('id-ID')}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-secondary border px-2 py-1">${satuan}</span>
                            </td>
                        </tr>
                    `;
                    tbodyDetail.insertAdjacentHTML('beforeend', rowHtml);
                });
            }
        }

        if (modalDetailEl && typeof bootstrap !== 'undefined') {
            const modalDetailInst = bootstrap.Modal.getOrCreateInstance(modalDetailEl);
            modalDetailInst.show();
        }
    });

    // Tombol Edit langsung dari dalam Modal Detail
    const btnEditDariDetail = document.getElementById('btn-edit-dari-detail');
    if (btnEditDariDetail) {
        btnEditDariDetail.addEventListener('click', function() {
            if (!activeResepIdForEdit) return;
            const targetBtnEdit = document.querySelector(`.btn-edit-resep[data-id="${activeResepIdForEdit}"]`);
            if (targetBtnEdit) {
                if (modalDetailEl && typeof bootstrap !== 'undefined') {
                    const inst = bootstrap.Modal.getInstance(modalDetailEl);
                    modalDetailEl.addEventListener('hidden.bs.modal', function onDetailHidden() {
                        modalDetailEl.removeEventListener('hidden.bs.modal', onDetailHidden);
                        targetBtnEdit.click();
                    });
                    if (inst) {
                        inst.hide();
                    } else {
                        targetBtnEdit.click();
                    }
                } else {
                    targetBtnEdit.click();
                }
            }
        });
    }

    formResep.addEventListener('submit', function(e) {
        // Validation check
        let valid = true;
        tbodyBahan.querySelectorAll('tr').forEach((row, idx) => {
            const badges = row.querySelectorAll('.selected-alternatives-container .alt-chip');
            if (badges.length === 0) {
                valid = false;
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Harap pilih minimal 1 bahan baku utama untuk setiap baris.');
            return false;
        }

        produkChoices.enable();
    });
});
</script>
</x-app-layout>