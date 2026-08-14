@php
    $userRole = auth()->user()->role->nama ?? '';
    $tipePenjualanOptions = [];
    if (in_array($userRole, ['Super Admin', 'Administrator'])) {
        $tipePenjualanOptions = ['POS Gaharu', 'POS Kejingga', 'B2B'];
    } elseif ($userRole === 'Kepala Outlet Gaharu') {
        $tipePenjualanOptions = ['POS Gaharu', 'B2B'];
    } elseif ($userRole === 'Kepala Outlet Kejingga') {
        $tipePenjualanOptions = ['POS Kejingga'];
    } elseif ($userRole === 'Kepala Gudang') {
        $tipePenjualanOptions = ['B2B'];
    }
@endphp
<x-app-layout>
<x-slot name="header">

        Master Barang

    </x-slot>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-bold">Master Data Barang</h5>
            <small class="text-muted">Kelola data barang perusahaan</small>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form action="{{ route('barang.index') }}" method="GET" class="d-flex gap-2">
                <select name="kategori_id" class="form-select form-select-sm" style="width: 170px; border-radius: 6px;">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($kategori as $k)
                        <option value="{{ $k->id }}" {{ request('kategori_id') == $k->id ? 'selected' : '' }}>{{ $k->nama }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama/kode..." value="{{ request('search') }}" style="width: 180px; border-radius: 6px;">
                <button type="submit" class="btn btn-sm text-white" style="background-color: #d88656; border-radius: 6px; border: none; padding: 5px 15px;">Cari</button>
                @if(request('kategori_id') || request('search'))
                    <a href="{{ route('barang.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px; padding: 5px 15px;">Reset</a>
                @endif
            </form>

            {{-- Tombol Import Excel membuka modal import --}}
            <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius: 6px; padding: 5px 15px;" data-bs-toggle="modal" data-bs-target="#modalImportBarang">
                <i class="bi bi-upload"></i> Import Excel
            </button>

            {{-- Tombol Tambah membuka modal --}}
            <button type="button" class="btn btn-sm text-white" style="background-color: #d88656; border: none; border-radius: 6px; padding: 5px 15px;" data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                + Tambah Barang
            </button>
        </div>
    </div>

    @if (session('import_result_barang'))
        @php $ir = session('import_result_barang'); @endphp
        <div class="alert alert-info mx-3 mt-3 mb-0">
            <strong>Hasil Import:</strong>
            {{ $ir['created'] }} barang berhasil ditambahkan,
            {{ $ir['skipped'] }} dilewati (kode barang sudah ada).
            @if (!empty($ir['errors']))
                <div class="mt-2">
                    <strong class="text-danger">{{ count($ir['errors']) }} baris bermasalah:</strong>
                    <ul class="mb-0 small">
                        @foreach ($ir['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (!empty($ir['skippedRows']))
                <details class="mt-2 small">
                    <summary>Lihat detail baris yang dilewati</summary>
                    <ul class="mb-0">
                        @foreach ($ir['skippedRows'] as $sk)
                            <li>{{ $sk }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error') || $errors->has('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') ?? $errors->first('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th class="text-start">Nama</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Jenis</th>
                        <th>Min. Stock</th>
                        <th>Min. Order</th>
                        <th style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $d)
                        <tr class="{{ !$d->is_active ? 'table-secondary' : '' }}">
                            <td class="font-monospace">{{ $d->kode_barang }}</td>
                            <td class="text-start fw-semibold">
                                {{ $d->nama }}
                                @if(!$d->is_active) <span class="badge bg-secondary ms-2">Non-Aktif</span> @endif
                            </td>
                            <td>{{ $d->kategori->nama ?? '-' }}</td>
                            <td>{{ $d->satuan }}</td>
                            <td>
                                @if($d->is_bahan_baku)
                                    <span class="badge bg-primary-subtle text-primary px-3 py-2">Bahan Baku</span>
                                @elseif($d->is_bahan_setengah_jadi)
                                    <span class="badge bg-info-subtle text-info px-3 py-2">Bahan Setengah Jadi</span>
                                @elseif($d->is_barang_jadi)
                                    <span class="badge bg-success-subtle text-success px-3 py-2">Barang Jadi</span>
                                @elseif($d->is_operational)
                                    <span class="badge bg-warning-subtle text-dark px-3 py-2">Operational</span>
                                @endif
                            </td>
                            <td>
                                @if($d->is_bahan_setengah_jadi)
                                    @if($d->minimum_stock_ck !== null || $d->minimum_stock_kejingga !== null || $d->minimum_stock_gaharu !== null)
                                        <div class="small lh-sm text-start" style="font-size: 0.75rem;">
                                            <div><span class="text-secondary">CK:</span> <strong class="text-dark">{{ $d->minimum_stock_ck !== null ? number_format($d->minimum_stock_ck) : '—' }}</strong></div>
                                            <div><span class="text-secondary">Kejingga:</span> <strong class="text-dark">{{ $d->minimum_stock_kejingga !== null ? number_format($d->minimum_stock_kejingga) : '—' }}</strong></div>
                                            <div><span class="text-secondary">Gaharu:</span> <strong class="text-dark">{{ $d->minimum_stock_gaharu !== null ? number_format($d->minimum_stock_gaharu) : '—' }}</strong></div>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                @else
                                    @if($d->minimum_stock !== null)
                                        <span class="fw-bold text-dark">{{ number_format($d->minimum_stock) }}</span>
                                        <small class="text-muted">{{ $d->satuan }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ number_format($d->minimum_order ?? 1) }}</span>
                                <small class="text-muted">{{ $d->satuan }}</small>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center flex-nowrap gap-1">
                                    {{-- Tombol Detil membuka modal detail --}}
                                    <button type="button"
                                            class="btn btn-icon-action btn-info text-white"
                                            title="Detil"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDetailBarang"
                                            data-kode="{{ $d->kode_barang }}"
                                            data-nama="{{ $d->nama }}"
                                            data-kategori="{{ $d->kategori->nama ?? '-' }}"
                                            data-satuan="{{ $d->satuan }}"
                                            data-satuan-pembelian="{{ $d->satuan_pembelian }}"
                                            data-konversi-pembelian="{{ $d->konversi_pembelian }}"
                                            data-jenis="{{ $d->jenis_utama }}"
                                            data-active="{{ $d->is_active ? '1' : '0' }}"
                                            data-min-stock="{{ $d->minimum_stock !== null ? number_format($d->minimum_stock) . ' ' . $d->satuan : '—' }}"
                                            data-min-stock-ck="{{ $d->minimum_stock_ck !== null ? number_format($d->minimum_stock_ck) . ' ' . $d->satuan : '—' }}"
                                            data-min-stock-kejingga="{{ $d->minimum_stock_kejingga !== null ? number_format($d->minimum_stock_kejingga) . ' ' . $d->satuan : '—' }}"
                                            data-min-stock-gaharu="{{ $d->minimum_stock_gaharu !== null ? number_format($d->minimum_stock_gaharu) . ' ' . $d->satuan : '—' }}"
                                            data-min-order="{{ number_format($d->minimum_order ?? 1) }} {{ $d->satuan }}"
                                            data-tipe-penjualan="{{ $d->tipe_penjualan ?: 'Belum Diatur' }}">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>

                                    <form action="{{ route('barang.toggle', $d->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-icon-action btn-{{ $d->is_active ? 'secondary' : 'success' }} text-white"
                                                title="{{ $d->is_active ? 'Non-Aktifkan' : 'Aktifkan' }}">
                                            <i class="bi {{ $d->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    </form>

                                    {{-- Tombol Edit membuka modal edit, terisi otomatis --}}
                                    <button type="button"
                                            class="btn btn-icon-action btn-warning"
                                            title="Edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditBarang"
                                            data-id="{{ $d->id }}"
                                            data-kategori-id="{{ $d->kategori_id }}"
                                            data-kode="{{ $d->kode_barang }}"
                                            data-nama="{{ $d->nama }}"
                                            data-satuan="{{ $d->satuan }}"
                                            data-satuan-pembelian="{{ $d->satuan_pembelian }}"
                                            data-konversi-pembelian="{{ $d->konversi_pembelian }}"
                                            data-jenis="{{ $d->jenis_utama }}"
                                            data-min-stock="{{ $d->minimum_stock }}"
                                            data-min-stock-ck="{{ $d->minimum_stock_ck }}"
                                            data-min-stock-kejingga="{{ $d->minimum_stock_kejingga }}"
                                            data-min-stock-gaharu="{{ $d->minimum_stock_gaharu }}"
                                            data-min-order="{{ $d->minimum_order ?? 1 }}"
                                            data-tipe-penjualan="{{ $d->tipe_penjualan }}"
                                            data-action="{{ route('barang.update', $d->id) }}">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>

                                    {{-- Tombol Hapus membuka modal konfirmasi --}}
                                    <button type="button"
                                            class="btn btn-icon-action btn-danger"
                                            title="Hapus"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalHapusBarang"
                                            data-nama="{{ $d->nama }}"
                                            data-action="{{ route('barang.destroy', $d->id) }}">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Data barang belum tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $data->links() }}
        </div>

    </div>
</div>


{{-- ================= MODAL TAMBAH BARANG ================= --}}
<div class="modal fade" id="modalTambahBarang" tabindex="-1" aria-labelledby="modalTambahBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: #d88656;">
                <h5 class="modal-title fw-bold" id="modalTambahBarangLabel">Tambah Barang Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('barang.store') }}" method="POST" id="formTambahBarang">
                @csrf
                <input type="hidden" name="_form" value="create">

                <div class="modal-body text-start">

                    <div class="mb-3">
                        <label class="custom-label">Kategori</label>
                        <select name="kategori_id" id="kategori_id" class="form-control custom-input @error('kategori_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($kategori as $k)
                                <option value="{{ $k->id }}" {{ old('kategori_id') == $k->id ? 'selected' : '' }}>{{ $k->nama }}</option>
                            @endforeach
                        </select>
                        @error('kategori_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Kode Barang</label>
                            <input type="text" name="kode_barang" id="kode_barang" class="form-control custom-input @error('kode_barang') is-invalid @enderror" value="{{ old('kode_barang') }}" readonly required>
                            @error('kode_barang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Nama Barang</label>
                            <input type="text" name="nama" id="nama_barang" class="form-control custom-input @error('nama') is-invalid @enderror" value="{{ old('nama') }}" required autocomplete="off">
                            @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Satuan Utama</label>
                            <input type="text" name="satuan" class="form-control custom-input @error('satuan') is-invalid @enderror" value="{{ old('satuan') }}" required placeholder="Contoh: kg, pcs, liter, ml">
                            @error('satuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Satuan Pembelian (Opsional)</label>
                            <input type="text" name="satuan_pembelian" id="satuan_pembelian" class="form-control custom-input @error('satuan_pembelian') is-invalid @enderror" value="{{ old('satuan_pembelian') }}" placeholder="Contoh: botol, dus, karton">
                            @error('satuan_pembelian') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Konversi Satuan Pembelian (Opsional)</label>
                            <input type="number" name="konversi_pembelian" id="konversi_pembelian" class="form-control custom-input @error('konversi_pembelian') is-invalid @enderror" value="{{ old('konversi_pembelian', 1) }}" placeholder="Contoh: 1000" min="1" step="0.01">
                            @error('konversi_pembelian') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Jenis Barang</label>
                            <select name="jenis_utama" id="jenis" class="form-control custom-input @error('jenis_utama') is-invalid @enderror" required>
                                <option value="">-- Pilih Jenis --</option>
                                @if($gudangRole === 'Kepala Gudang')
                                    <option value="BAHAN_BAKU" {{ old('jenis_utama') == 'BAHAN_BAKU' ? 'selected' : '' }}>
                                        Bahan Baku
                                    </option>
                                    <option value="BAHAN_SETENGAH_JADI" {{ old('jenis_utama') == 'BAHAN_SETENGAH_JADI' ? 'selected' : '' }}>
                                        Bahan Setengah Jadi
                                    </option>
                                    <option value="OPERATIONAL" {{ old('jenis_utama') == 'OPERATIONAL' ? 'selected' : '' }}>
                                        Operational
                                    </option>
                                @else
                                    <option value="BAHAN_BAKU" {{ old('jenis_utama') == 'BAHAN_BAKU' ? 'selected' : '' }}>
                                        Bahan Baku
                                    </option>
                                    <option value="BAHAN_SETENGAH_JADI" {{ old('jenis_utama') == 'BAHAN_SETENGAH_JADI' ? 'selected' : '' }}>
                                        Bahan Setengah Jadi
                                    </option>
                                    <option value="BARANG_JADI" {{ old('jenis_utama') == 'BARANG_JADI' ? 'selected' : '' }}>
                                        Barang Jadi
                                    </option>
                                    <option value="OPERATIONAL" {{ old('jenis_utama') == 'OPERATIONAL' ? 'selected' : '' }}>
                                        Operational
                                    </option>
                                @endif
                            </select>
                            @error('jenis_utama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3" id="group-min-stock">
                            <label class="custom-label text-danger">Minimum Stock (Batas Kritis)</label>
                            <input type="number" name="minimum_stock" id="minimum_stock" class="form-control custom-input" value="{{ old('minimum_stock') }}" placeholder="Contoh: 10" min="0">
                        </div>

                        <div class="col-12 mb-3" id="group-min-stock-bsj" style="display: none;">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="custom-label text-danger fw-bold d-block mb-2">
                                    <i class="bi bi-shield-exclamation me-1"></i> Minimum Stock per Lokasi (Bahan Setengah Jadi - Opsional)
                                </label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-semibold">Central Kitchen</label>
                                        <input type="number" name="minimum_stock_ck" id="minimum_stock_ck" class="form-control custom-input" placeholder="Opsional" min="0" value="{{ old('minimum_stock_ck') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-semibold">Outlet Kejingga</label>
                                        <input type="number" name="minimum_stock_kejingga" id="minimum_stock_kejingga" class="form-control custom-input" placeholder="Opsional" min="0" value="{{ old('minimum_stock_kejingga') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-semibold">Outlet Gaharu</label>
                                        <input type="number" name="minimum_stock_gaharu" id="minimum_stock_gaharu" class="form-control custom-input" placeholder="Opsional" min="0" value="{{ old('minimum_stock_gaharu') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3" id="group-tipe-penjualan">
                            <label class="custom-label">Tipe Penjualan</label>
                            <select name="tipe_penjualan" id="tipe_penjualan" class="form-control custom-input">
                                <option value="">-- Pilih Tipe Penjualan --</option>
                                @foreach($tipePenjualanOptions as $opt)
                                    <option value="{{ $opt }}" {{ old('tipe_penjualan') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label text-primary">Minimum Order (Batas Order)</label>
                            <input type="number" name="minimum_order" id="minimum_order" class="form-control custom-input" placeholder="Default: 1" min="1" value="{{ old('minimum_order', 1) }}" step="0.01">
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn custom-btn-batal" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn text-white custom-btn-simpan">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ================= MODAL IMPORT EXCEL BARANG ================= --}}
<div class="modal fade" id="modalImportBarang" tabindex="-1" aria-labelledby="modalImportBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: #d88656;">
                <h5 class="modal-title fw-bold" id="modalImportBarangLabel">Import Master Barang dari Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('barang.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body text-start">
                    <p class="text-muted small">
                        Baris dengan <strong>kode_barang</strong> yang sudah ada di sistem akan otomatis dilewati (tidak menimpa data lama).
                        Jenis barang yang didukung: Bahan Baku, Bahan Setengah Jadi, Barang Jadi, Operational.
                    </p>

                    <div class="mb-3">
                        <a href="{{ route('barang.import.template') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download"></i> Download Template Excel
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="custom-label">Pilih File Excel (.xlsx)</label>
                        <input type="file" name="file" class="form-control custom-input @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn custom-btn-batal" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn text-white custom-btn-simpan">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ================= MODAL DETAIL BARANG ================= --}}
<div class="modal fade" id="modalDetailBarang" tabindex="-1" aria-labelledby="modalDetailBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: #d88656;">
                <h5 class="modal-title fw-bold" id="modalDetailBarangLabel">Informasi Barang</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Nama Barang</label>
                        <p class="fs-5 text-dark fw-semibold mb-0" id="detailNama"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Kode Barang</label>
                        <p class="fs-5 text-dark fw-semibold font-monospace mb-0" id="detailKode"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Kategori</label>
                        <p class="fs-6 text-dark mb-0" id="detailKategori"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Satuan Utama</label>
                        <p class="fs-6 text-dark mb-0" id="detailSatuan"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Satuan Pembelian</label>
                        <p class="fs-6 text-dark mb-0" id="detailSatuanPembelian"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Konversi Satuan Pembelian</label>
                        <p class="fs-6 text-dark mb-0" id="detailKonversiPembelian"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Jenis Barang</label>
                        <p class="fs-6 mb-0" id="detailJenis"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Status</label>
                        <p class="fs-6 mb-0" id="detailStatus"></p>
                    </div>
                    <div class="col-md-6 mb-3" id="detailTipePenjualanWrap">
                        <label class="custom-label">Tipe Penjualan</label>
                        <p class="fs-6 mb-0"><span class="badge bg-info text-dark" id="detailTipePenjualan"></span></p>
                    </div>
                    <div class="col-md-6 mb-3" id="detailMinStockWrap">
                        <label class="custom-label">Batas Minimum Stock</label>
                        <p class="fs-6 text-danger fw-bold mb-0" id="detailMinStock"></p>
                    </div>
                    <div class="col-12 mb-3" id="detailMinStockBsjWrap" style="display: none;">
                        <label class="custom-label text-danger fw-bold">Batas Minimum Stock per Lokasi</label>
                        <div class="p-2 bg-light rounded border small">
                            <div class="row text-center">
                                <div class="col-4 border-end">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Central Kitchen</span>
                                    <strong class="text-dark" id="detailMinStockCk">—</strong>
                                </div>
                                <div class="col-4 border-end">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Outlet Kejingga</span>
                                    <strong class="text-dark" id="detailMinStockKejingga">—</strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Outlet Gaharu</span>
                                    <strong class="text-dark" id="detailMinStockGaharu">—</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-label">Batas Minimum Order</label>
                        <p class="fs-6 text-primary fw-bold mb-0" id="detailMinOrder"></p>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn custom-btn-batal w-100" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


{{-- ================= MODAL EDIT BARANG ================= --}}
<div class="modal fade" id="modalEditBarang" tabindex="-1" aria-labelledby="modalEditBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: #d88656;">
                <h5 class="modal-title fw-bold" id="modalEditBarangLabel">Edit Barang</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="formEditBarang" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="edit">

                <div class="modal-body text-start">

                    <div class="mb-3">
                        <label class="custom-label">Kategori</label>
                        <select name="kategori_id" id="editKategoriId" class="form-control custom-input @error('kategori_id') is-invalid @enderror" required>
                            @foreach($kategori as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                        @error('kategori_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Kode Barang</label>
                            <input type="text" name="kode_barang" id="editKodeBarang" class="form-control custom-input @error('kode_barang') is-invalid @enderror" required>
                            @error('kode_barang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Nama Barang</label>
                            <input type="text" name="nama" id="editNama" class="form-control custom-input @error('nama') is-invalid @enderror" required autocomplete="off">
                            @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Satuan Utama</label>
                            <input type="text" name="satuan" id="editSatuan" class="form-control custom-input @error('satuan') is-invalid @enderror" required placeholder="Contoh: kg, pcs, liter, ml">
                            @error('satuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Satuan Pembelian (Opsional)</label>
                            <input type="text" name="satuan_pembelian" id="editSatuanPembelian" class="form-control custom-input @error('satuan_pembelian') is-invalid @enderror" placeholder="Contoh: botol, dus, karton">
                            @error('satuan_pembelian') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Konversi Satuan Pembelian (Opsional)</label>
                            <input type="number" name="konversi_pembelian" id="editKonversiPembelian" class="form-control custom-input @error('konversi_pembelian') is-invalid @enderror" placeholder="Contoh: 1000" min="1" step="0.01">
                            @error('konversi_pembelian') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label">Jenis Barang</label>
                            <select name="jenis_utama" id="editJenis" class="form-control custom-input @error('jenis_utama') is-invalid @enderror" required>
                                <option value="BAHAN_BAKU">Bahan Baku</option>
                                <option value="BAHAN_SETENGAH_JADI">Bahan Setengah Jadi</option>
                                <option value="BARANG_JADI">Barang Jadi</option>
                                <option value="OPERATIONAL">Operational</option>
                            </select>
                            @error('jenis_utama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mb-3" id="editGroupMinStock">
                            <label class="custom-label text-danger">Minimum Stock (Batas Kritis)</label>
                            <input type="number" name="minimum_stock" id="editMinimumStock" class="form-control custom-input" min="0">
                        </div>

                        <div class="col-12 mb-3" id="editGroupMinStockBsj" style="display: none;">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="custom-label text-danger fw-bold d-block mb-2">
                                    <i class="bi bi-shield-exclamation me-1"></i> Minimum Stock per Lokasi (Bahan Setengah Jadi - Opsional)
                                </label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-semibold">Central Kitchen</label>
                                        <input type="number" name="minimum_stock_ck" id="editMinimumStockCk" class="form-control custom-input" placeholder="Opsional" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-semibold">Outlet Kejingga</label>
                                        <input type="number" name="minimum_stock_kejingga" id="editMinimumStockKejingga" class="form-control custom-input" placeholder="Opsional" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-semibold">Outlet Gaharu</label>
                                        <input type="number" name="minimum_stock_gaharu" id="editMinimumStockGaharu" class="form-control custom-input" placeholder="Opsional" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3" id="editGroupTipePenjualan">
                            <label class="custom-label">Tipe Penjualan</label>
                            <select name="tipe_penjualan" id="editTipePenjualan" class="form-control custom-input">
                                <option value="">-- Pilih Tipe Penjualan --</option>
                                @foreach($tipePenjualanOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="custom-label text-primary">Minimum Order (Batas Order)</label>
                            <input type="number" name="minimum_order" id="editMinimumOrder" class="form-control custom-input" min="1" step="0.01">
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn custom-btn-batal" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white custom-btn-simpan">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ================= MODAL HAPUS BARANG ================= --}}
<div class="modal fade" id="modalHapusBarang" tabindex="-1" aria-labelledby="modalHapusBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="modalHapusBarangLabel" style="color: #2d3748;">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="formHapusBarang" method="POST">
                @csrf
                @method('DELETE')

                <div class="modal-body px-4 pt-2 pb-4">
                    <p class="mb-0 text-secondary">
                        Yakin ingin menghapus barang
                        <strong id="hapusNama" class="text-dark"></strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn custom-btn-batal" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
    .custom-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #718096;
        font-weight: 700;
        display: block;
        margin-bottom: 6px;
    }
    .custom-input {
        border-radius: 8px !important;
        padding: 10px 12px !important;
        border: 1px solid #e2e8f0 !important;
        font-size: 14px !important;
    }
    .custom-input:focus {
        border-color: #d88656 !important;
        box-shadow: 0 0 0 3px rgba(216, 134, 86, 0.15) !important;
    }
    .custom-btn-batal {
        background-color: #f7fafc;
        border: 1px solid #e2e8f0;
        color: #4a5568;
        border-radius: 8px;
        padding: 8px 18px;
        font-size: 14px;
    }
    .custom-btn-batal:hover {
        background-color: #edf2f7;
    }
    .custom-btn-simpan {
        background-color: #d88656;
        border-radius: 8px;
        padding: 8px 18px;
        font-size: 14px;
        font-weight: 500;
        border: none;
    }
    .custom-btn-simpan:hover {
        background-color: #c87443;
    }
    .btn-close-white {
        filter: invert(1) grayscale(1) brightness(2);
    }

    /* ── TOMBOL AKSI IKON KOMPAK ── */
    .btn-icon-action {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 13px;
        border: none;
        flex-shrink: 0;
    }
</style>

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {

    /* ============ MODAL TAMBAH: toggle field & generate kode & cek nama duplikat ============ */
    const jenis = document.getElementById('jenis');
    const groupMinStock = document.getElementById('group-min-stock');
    const groupMinStockBsj = document.getElementById('group-min-stock-bsj');
    const minStockInput = document.getElementById('minimum_stock');
    const minStockCk = document.getElementById('minimum_stock_ck');
    const minStockKejingga = document.getElementById('minimum_stock_kejingga');
    const minStockGaharu = document.getElementById('minimum_stock_gaharu');
    const groupTipePenjualan = document.getElementById('group-tipe-penjualan');
    const tipePenjualanSelect = document.getElementById('tipe_penjualan');

    function toggleForm() {
        if (jenis.value === "BAHAN_SETENGAH_JADI") {
            groupMinStock.style.display = "none";
            groupMinStockBsj.style.display = "block";
            minStockInput.value = "";
        } else if (jenis.value === "BAHAN_BAKU") {
            groupMinStock.style.display = "block";
            groupMinStockBsj.style.display = "none";
            if (minStockCk) minStockCk.value = "";
            if (minStockKejingga) minStockKejingga.value = "";
            if (minStockGaharu) minStockGaharu.value = "";
        } else {
            groupMinStock.style.display = "none";
            groupMinStockBsj.style.display = "none";
            minStockInput.value = "";
            if (minStockCk) minStockCk.value = "";
            if (minStockKejingga) minStockKejingga.value = "";
            if (minStockGaharu) minStockGaharu.value = "";
        }

        if (jenis.value === "BARANG_JADI") {
            groupTipePenjualan.style.display = "block";
            tipePenjualanSelect.setAttribute('required', 'required');
        } else {
            groupTipePenjualan.style.display = "none";
            tipePenjualanSelect.removeAttribute('required');
            tipePenjualanSelect.value = "";
        }
    }
    jenis.addEventListener('change', toggleForm);
    toggleForm();

    const kategoriSelect = document.getElementById('kategori_id');
    kategoriSelect.addEventListener('change', function () {
        let kategoriId = this.value;
        if (kategoriId == '') {
            document.getElementById('kode_barang').value = '';
            return;
        }
        fetch("{{ route('barang.generate-kode', ':kategori') }}".replace(':kategori', kategoriId))
            .then(response => response.json())
            .then(data => {
                document.getElementById('kode_barang').value = data.kode_barang;
            });
    });
    if (kategoriSelect.value != '') {
        kategoriSelect.dispatchEvent(new Event('change'));
    }

    const formTambah = document.getElementById('formTambahBarang');
    let bypassCheck = false;
    formTambah.addEventListener('submit', function (e) {
        if (bypassCheck) return;
        e.preventDefault();

        const namaInput = document.getElementById('nama_barang');
        const namaVal = namaInput.value.trim();
        if (!namaVal) { bypassFormSubmit(); return; }

        fetch("{{ route('barang.check-nama') }}?nama=" + encodeURIComponent(namaVal))
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    if (confirm("Nama Barang ini sudah terdaftar, apakah tetap ingin diinput?")) {
                        bypassFormSubmit();
                    }
                } else {
                    bypassFormSubmit();
                }
            })
            .catch(() => bypassFormSubmit());

        function bypassFormSubmit() {
            bypassCheck = true;
            formTambah.submit();
        }
    });

    /* ============ MODAL DETAIL: isi dari data-attribute ============ */
    var modalDetail = document.getElementById('modalDetailBarang');
    modalDetail.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var jenisVal = button.getAttribute('data-jenis');

        document.getElementById('detailNama').innerText = button.getAttribute('data-nama');
        document.getElementById('detailKode').innerText = button.getAttribute('data-kode');
        document.getElementById('detailKategori').innerText = button.getAttribute('data-kategori');
        document.getElementById('detailSatuan').innerText = button.getAttribute('data-satuan');
        document.getElementById('detailSatuanPembelian').innerText = button.getAttribute('data-satuan-pembelian') || '—';
        document.getElementById('detailKonversiPembelian').innerText = button.getAttribute('data-konversi-pembelian') 
            ? (Number(button.getAttribute('data-konversi-pembelian')).toLocaleString('id-ID') + ' ' + button.getAttribute('data-satuan'))
            : '—';
        document.getElementById('detailMinOrder').innerText = button.getAttribute('data-min-order');

        var jenisLabel = { BAHAN_BAKU: ['Bahan Baku', 'primary'], BAHAN_SETENGAH_JADI: ['Bahan Setengah Jadi', 'info'], BARANG_JADI: ['Barang Jadi', 'success'], OPERATIONAL: ['Operational', 'warning'] };
        var info = jenisLabel[jenisVal] || ['Umum', 'secondary'];
        document.getElementById('detailJenis').innerHTML = '<span class="badge bg-' + info[1] + '-subtle text-' + info[1] + ' px-3 py-2">' + info[0] + '</span>';

        var isActive = button.getAttribute('data-active') === '1';
        document.getElementById('detailStatus').innerHTML = isActive
            ? '<span class="badge bg-success">Aktif</span>'
            : '<span class="badge bg-danger">Non-Aktif</span>';

        var minStockWrap = document.getElementById('detailMinStockWrap');
        var minStockBsjWrap = document.getElementById('detailMinStockBsjWrap');
        var tipeWrap = document.getElementById('detailTipePenjualanWrap');

        if (jenisVal === 'BAHAN_SETENGAH_JADI') {
            minStockWrap.style.display = 'none';
            minStockBsjWrap.style.display = 'block';
            document.getElementById('detailMinStockCk').innerText = button.getAttribute('data-min-stock-ck') || '—';
            document.getElementById('detailMinStockKejingga').innerText = button.getAttribute('data-min-stock-kejingga') || '—';
            document.getElementById('detailMinStockGaharu').innerText = button.getAttribute('data-min-stock-gaharu') || '—';
        } else if (jenisVal === 'BAHAN_BAKU') {
            minStockWrap.style.display = 'block';
            minStockBsjWrap.style.display = 'none';
            document.getElementById('detailMinStock').innerText = button.getAttribute('data-min-stock');
        } else {
            minStockWrap.style.display = 'none';
            minStockBsjWrap.style.display = 'none';
        }

        if (jenisVal === 'BARANG_JADI') {
            tipeWrap.style.display = 'block';
            document.getElementById('detailTipePenjualan').innerText = button.getAttribute('data-tipe-penjualan');
        } else {
            tipeWrap.style.display = 'none';
        }
    });

    /* ============ MODAL EDIT: isi dari data-attribute + toggle field ============ */
    var editJenis = document.getElementById('editJenis');
    var editGroupMinStock = document.getElementById('editGroupMinStock');
    var editGroupMinStockBsj = document.getElementById('editGroupMinStockBsj');
    var editMinimumStock = document.getElementById('editMinimumStock');
    var editMinimumStockCk = document.getElementById('editMinimumStockCk');
    var editMinimumStockKejingga = document.getElementById('editMinimumStockKejingga');
    var editMinimumStockGaharu = document.getElementById('editMinimumStockGaharu');
    var editGroupTipePenjualan = document.getElementById('editGroupTipePenjualan');
    var editTipePenjualan = document.getElementById('editTipePenjualan');

    function toggleEditForm() {
        if (editJenis.value === "BAHAN_SETENGAH_JADI") {
            editGroupMinStock.style.display = "none";
            editGroupMinStockBsj.style.display = "block";
            editMinimumStock.value = "";
        } else if (editJenis.value === "BAHAN_BAKU") {
            editGroupMinStock.style.display = "block";
            editGroupMinStockBsj.style.display = "none";
            if (editMinimumStockCk) editMinimumStockCk.value = "";
            if (editMinimumStockKejingga) editMinimumStockKejingga.value = "";
            if (editMinimumStockGaharu) editMinimumStockGaharu.value = "";
        } else {
            editGroupMinStock.style.display = "none";
            editGroupMinStockBsj.style.display = "none";
            editMinimumStock.value = "";
            if (editMinimumStockCk) editMinimumStockCk.value = "";
            if (editMinimumStockKejingga) editMinimumStockKejingga.value = "";
            if (editMinimumStockGaharu) editMinimumStockGaharu.value = "";
        }

        if (editJenis.value === "BARANG_JADI") {
            editGroupTipePenjualan.style.display = "block";
            editTipePenjualan.setAttribute('required', 'required');
        } else {
            editGroupTipePenjualan.style.display = "none";
            editTipePenjualan.removeAttribute('required');
        }
    }
    editJenis.addEventListener('change', toggleEditForm);

    var modalEdit = document.getElementById('modalEditBarang');
    modalEdit.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;

        document.getElementById('editKategoriId').value = button.getAttribute('data-kategori-id');
        document.getElementById('editKodeBarang').value = button.getAttribute('data-kode');
        document.getElementById('editNama').value = button.getAttribute('data-nama');
        document.getElementById('editSatuan').value = button.getAttribute('data-satuan');
        document.getElementById('editSatuanPembelian').value = button.getAttribute('data-satuan-pembelian') || '';
        document.getElementById('editKonversiPembelian').value = button.getAttribute('data-konversi-pembelian') || '1';
        editJenis.value = button.getAttribute('data-jenis');
        editMinimumStock.value = button.getAttribute('data-min-stock') || '';
        if (editMinimumStockCk) editMinimumStockCk.value = button.getAttribute('data-min-stock-ck') || '';
        if (editMinimumStockKejingga) editMinimumStockKejingga.value = button.getAttribute('data-min-stock-kejingga') || '';
        if (editMinimumStockGaharu) editMinimumStockGaharu.value = button.getAttribute('data-min-stock-gaharu') || '';
        editTipePenjualan.value = button.getAttribute('data-tipe-penjualan');
        document.getElementById('editMinimumOrder').value = button.getAttribute('data-min-order');
        document.getElementById('formEditBarang').action = button.getAttribute('data-action');

        toggleEditForm();
    });

    /* ============ MODAL HAPUS ============ */
    var modalHapus = document.getElementById('modalHapusBarang');
    modalHapus.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('hapusNama').innerText = button.getAttribute('data-nama');
        document.getElementById('formHapusBarang').action = button.getAttribute('data-action');
    });

    /* ============ Auto-buka kembali modal Tambah/Edit jika ada error validasi ============ */
    @if ($errors->any())
        @if (old('_form') === 'edit')
            new bootstrap.Modal(document.getElementById('modalEditBarang')).show();
        @else
            new bootstrap.Modal(document.getElementById('modalTambahBarang')).show();
        @endif
    @endif
});
</script>
@endpush

</x-app-layout>