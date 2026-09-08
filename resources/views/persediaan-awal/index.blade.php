<x-app-layout>
    <x-slot name="header">
        Persediaan Awal
    </x-slot>

    @if(session('import_result'))
        @php $res = session('import_result'); @endphp
        <div class="alert alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 p-3" 
             style="background-color: #ffffff; border-left: 5px solid {{ $res['gagal'] > 0 ? ($res['berhasil'] > 0 ? '#DE8958' : '#dc3545') : '#198754' }} !important;" 
             role="alert">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                     style="width: 42px; height: 42px; background-color: {{ $res['gagal'] > 0 ? ($res['berhasil'] > 0 ? '#DE8958' : '#dc3545') : '#198754' }};">
                    <i class="bi {{ $res['gagal'] > 0 ? ($res['berhasil'] > 0 ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill') : 'bi-check-circle-fill' }} fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                        <h6 class="mb-0 fw-bold text-dark">Hasil Import Persediaan Awal</h6>
                        @if(!empty($res['kode_transaksi']) && !empty($res['persediaan_awal_id']))
                            <a href="{{ route('persediaan-awal.show', $res['persediaan_awal_id']) }}" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold" style="font-size: 12px; border-radius: 6px;">
                                <i class="bi bi-eye me-1"></i> Lihat Transaksi {{ $res['kode_transaksi'] }}
                            </a>
                        @endif
                    </div>
                    
                    {{-- Tanda ringkasan import --}}
                    <div class="fs-6 fw-bold text-dark my-1">
                        {{ $res['pesan'] }}
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1.5 rounded-2">
                            <i class="bi bi-check2 me-1"></i> <strong>{{ $res['berhasil'] }}</strong> persediaan awal berhasil masuk
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 rounded-2">
                            <i class="bi bi-plus-circle me-1"></i> <strong>{{ $res['item_baru'] }}</strong> item baru
                        </span>
                        <span class="badge {{ $res['gagal'] > 0 ? 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25' : 'bg-secondary bg-opacity-10 text-muted' }} px-2.5 py-1.5 rounded-2">
                            <i class="bi {{ $res['gagal'] > 0 ? 'bi-exclamation-circle' : 'bi-dash-circle' }} me-1"></i> <strong>{{ $res['gagal'] }}</strong> gagal
                        </span>
                    </div>

                    @if(!empty($res['new_items']) && count($res['new_items']) > 0)
                        <div class="small text-muted mt-2 pt-1">
                            <i class="bi bi-box-seam me-1 text-primary"></i> <strong>Item baru didaftarkan ke Master Barang:</strong> {{ implode(', ', array_slice($res['new_items'], 0, 10)) }}{{ count($res['new_items']) > 10 ? ' dan ' . (count($res['new_items']) - 10) . ' lainnya' : '' }}
                        </div>
                    @endif

                    @if(!empty($res['failed_rows']) && count($res['failed_rows']) > 0)
                        <div class="mt-2 pt-2 border-top">
                            <a class="text-danger small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1" data-bs-toggle="collapse" href="#collapseFailedRows" role="button" aria-expanded="false" aria-controls="collapseFailedRows">
                                <i class="bi bi-chevron-down"></i> Lihat rincian {{ count($res['failed_rows']) }} baris yang gagal
                            </a>
                            <div class="collapse mt-2" id="collapseFailedRows">
                                <div class="table-responsive bg-light rounded border" style="max-height: 220px; overflow-y: auto;">
                                    <table class="table table-sm table-striped mb-0 small">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th style="width: 90px;">Baris Excel</th>
                                                <th>Nama / Kode Barang</th>
                                                <th>Alasan Gagal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($res['failed_rows'] as $fr)
                                                <tr>
                                                    <td class="text-muted fw-bold">Baris {{ $fr['baris'] ?? '-' }}</td>
                                                    <td>{{ $fr['item'] ?? '-' }}</td>
                                                    <td class="text-danger">{{ $fr['alasan'] ?? 'Format data tidak valid' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #DE8958 0%, #B86230 100%); color: white;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-white-50 small text-uppercase fw-bold">Total Transaksi</div>
                        <div class="fs-4 fw-bold mt-1">{{ number_format($totalTransaksi) }}</div>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-journal-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Kuantitas</div>
                        <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($totalQty, 2, ',', '.') }}</div>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-boxes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Nilai Saldo Awal</div>
                        <div class="fs-4 fw-bold text-success mt-1">Rp {{ number_format($totalNilai, 0, ',', '.') }}</div>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold">Persediaan Awal Master Barang</h5>
                    <small class="text-muted">Pencatatan saldo awal persediaan barang ke gudang dan pembentukan batch FIFO</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Tombol Download Template Excel -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-2 px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i> Template Excel
                        </button>
                        <ul class="dropdown-menu shadow-sm border-0 rounded-3">
                            <li>
                                <a class="dropdown-item small py-2" href="{{ route('persediaan-awal.template', ['format' => 'simple']) }}">
                                    <i class="bi bi-file-earmark-text me-2 text-primary"></i> Template Simpel (Nama, Satuan, Qty)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item small py-2" href="{{ route('persediaan-awal.template', ['format' => 'full']) }}">
                                    <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Template Lengkap (Dengan Kode Barang)
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Tombol Import Excel -->
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-2 px-3" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Import Excel
                    </button>

                    <!-- Tombol Input Baru -->
                    <a href="{{ route('persediaan-awal.create') }}" class="btn btn-sm text-white rounded-2 px-3 min-hitbox d-inline-flex align-items-center justify-content-center" style="background-color: #DE8958; border: none;">
                        <i class="bi bi-plus-lg me-1"></i> Input Persediaan Awal
                    </a>
                </div>
            </div>

            <!-- Filter Toolbar -->
            <form action="{{ route('persediaan-awal.index') }}" method="GET" class="row g-2 mt-3 pt-3 border-top">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Gudang</label>
                    <select name="gudang_id" class="form-select form-select-sm rounded-2" id="filterGudang">
                        <option value="">-- Semua Gudang --</option>
                        @foreach($gudangs as $g)
                            <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>{{ $g->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm rounded-2" value="{{ request('start_date') }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm rounded-2" value="{{ request('end_date') }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Pencarian</label>
                    <input type="text" name="search" class="form-control form-control-sm rounded-2" placeholder="Kode transaksi / keterangan..." value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-sm text-white flex-grow-1 rounded-2 min-hitbox" style="background-color: #DE8958; border: none;">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['gudang_id', 'divisi_id', 'start_date', 'end_date', 'search']))
                        <a href="{{ route('persediaan-awal.index') }}" class="btn btn-sm btn-secondary rounded-2">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show m-3 d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error') || $errors->has('error'))
                <div class="alert alert-danger alert-dismissible fade show m-3 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>{{ session('error') ?? $errors->first('error') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead style="background-color: #715745; color: white;">
                        <tr>
                            <th class="text-center text-white" style="width: 50px;">No</th>
                            <th class="text-start text-white">Kode Transaksi</th>
                            <th class="text-white">Tanggal</th>
                            <th class="text-start text-white">Gudang / Lokasi</th>
                            <th class="text-white">Total Item</th>
                            <th class="text-white">Total Qty</th>
                            <th class="text-end text-white">Total Nilai (Rp)</th>
                            <th class="text-start text-white">Keterangan</th>
                            <th class="text-white">Petugas</th>
                            <th class="text-white" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $index => $item)
                            <tr>
                                <td class="text-center text-muted">{{ $data->firstItem() + $index }}</td>
                                <td class="text-start">
                                    <a href="{{ route('persediaan-awal.show', $item->id) }}" class="fw-bold text-decoration-none" style="color: #DE8958;">
                                        {{ $item->kode_transaksi }}
                                    </a>
                                </td>
                                <td>{{ $item->tanggal->format('d/m/Y') }}</td>
                                <td class="text-start">
                                    <span class="fw-semibold text-dark">{{ $item->gudang->nama ?? '-' }}</span>
                                    @if($item->divisi)
                                        <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $item->divisi->nama }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">{{ number_format($item->total_item) }} item</span>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ number_format($item->total_qty, 2, ',', '.') }}</span>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rp {{ number_format($item->total_nilai, 0, ',', '.') }}
                                </td>
                                <td class="text-start small text-muted text-truncate" style="max-width: 200px;" title="{{ $item->keterangan }}">
                                    {{ $item->keterangan ?? '-' }}
                                </td>
                                <td class="small">{{ $item->user->nama_karyawan ?? $item->user->name ?? '-' }}</td>
                                <td>
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('persediaan-awal.show', $item->id) }}" class="btn btn-sm btn-info text-white rounded-2 px-2 py-1" title="Lihat Rincian">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if(auth()->user() && auth()->user()->isSuperAdmin())
                                            <a href="{{ route('persediaan-awal.edit', $item->id) }}" class="btn btn-sm btn-warning text-white rounded-2 px-2 py-1" title="Edit Transaksi (Super Admin)">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-danger rounded-2 px-2 py-1" title="Hapus Transaksi" data-bs-toggle="modal" data-bs-target="#modalHapus{{ $item->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Modal Konfirmasi Hapus -->
                                    <div class="modal fade" id="modalHapus{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start border-0 shadow">
                                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                    <h5 class="modal-title fw-bold text-danger">Konfirmasi Hapus</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('persediaan-awal.destroy', $item->id) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="modal-body px-4 py-3">
                                                        <p class="mb-0 text-secondary">
                                                            Apakah Anda yakin ingin menghapus transaksi persediaan awal <strong>{{ $item->kode_transaksi }}</strong>?
                                                            <br><br>
                                                            <span class="text-danger small">
                                                                <i class="bi bi-exclamation-triangle me-1"></i> Tindakan ini akan mengembalikan stok di gudang, menghapus batch FIFO, dan membatalkan jurnal penyesuaian terkait.
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                                                        <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-danger px-3">Ya, Hapus</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada data transaksi persediaan awal.
                                    <div class="mt-2">
                                        <a href="{{ route('persediaan-awal.create') }}" class="btn btn-sm text-white rounded-2 px-3 min-hitbox d-inline-flex align-items-center justify-content-center" style="background-color: #DE8958;">
                                            <i class="bi bi-plus-lg me-1"></i> Buat Persediaan Awal Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($data->hasPages())
                <div class="p-3 border-top">
                    {{ $data->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Import Excel -->
    <div class="modal fade" id="modalImportExcel" tabindex="-1" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-3">
                <div class="modal-header text-white" style="background-color: #715745;">
                    <h5 class="modal-title fw-bold" id="modalImportExcelLabel">
                        <i class="bi bi-file-earmark-excel me-2"></i> Import Persediaan Awal dari Excel
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('persediaan-awal.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body p-4 text-start">
                        <div class="alert alert-info py-2 px-3 small mb-3 border-0 rounded-3" style="background-color: #f0f7ff; border-left: 4px solid #0d6efd !important;">
                            <div class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-excel-fill text-primary me-1"></i> Format Fleksibel Import:</div>
                            <div class="text-muted mb-2">Anda dapat mengunggah file Excel berisi kolom <strong>nama, satuan, dan qty</strong> (tanpa kode barang), maupun format template lengkap.</div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('persediaan-awal.template', ['format' => 'simple']) }}" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-2 text-decoration-none" style="font-size: 11px;">
                                    <i class="bi bi-download me-1"></i> Unduh Template Simpel (Nama, Satuan, Qty)
                                </a>
                                <a href="{{ route('persediaan-awal.template', ['format' => 'full']) }}" class="btn btn-sm btn-outline-secondary py-1 px-2 rounded-2 text-decoration-none" style="font-size: 11px;">
                                    <i class="bi bi-download me-1"></i> Unduh Template Lengkap
                                </a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Pilih Gudang Target <span class="text-danger">*</span></label>
                            <select name="gudang_id" class="form-select custom-input" required id="importGudangSelect">
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($gudangs as $g)
                                    <option value="{{ $g->id }}" 
                                        data-kategori="{{ strtolower($g->kategori) }}" 
                                        data-divisi="{{ strtolower($g->kategori) === 'operasional' ? json_encode($g->divisi) : '[]' }}">
                                        {{ $g->nama }} ({{ $g->kategori }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="importDivisiWrapper" style="display: none;">
                            <label class="form-label small fw-bold text-muted">Divisi <span class="text-danger">*</span></label>
                            <select name="divisi_id" class="form-select custom-input" id="importDivisiSelect">
                                <option value="">-- Pilih Divisi --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Tanggal Saldo Awal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control custom-input" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Pilih File Excel (.xlsx, .xls) <span class="text-danger">*</span></label>
                            <input type="file" name="file_excel" class="form-control custom-input" accept=".xlsx,.xls,.csv" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Keterangan / Catatan</label>
                            <textarea name="keterangan" class="form-control custom-input" rows="2" placeholder="Contoh: Saldo awal cut-off migrasi sistem"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn text-white px-4 min-hitbox" style="background-color: #DE8958;">Import & Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .custom-input {
            border-radius: 8px !important;
            padding: 9px 12px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 14px !important;
        }
        .custom-input:focus {
            border-color: #DE8958 !important;
            box-shadow: 0 0 0 3px rgba(222, 137, 88, 0.15) !important;
        }
        .btn-close-white {
            filter: invert(1) grayscale(1) brightness(2);
        }
    </style>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const importGudang = document.getElementById('importGudangSelect');
            const importDivisiWrapper = document.getElementById('importDivisiWrapper');
            const importDivisiSelect = document.getElementById('importDivisiSelect');

            function updateImportDivisi() {
                if (!importGudang || !importDivisiWrapper || !importDivisiSelect) return;
                const selectedOpt = importGudang.options[importGudang.selectedIndex];
                if (!selectedOpt || !selectedOpt.value) {
                    importDivisiWrapper.style.display = 'none';
                    importDivisiSelect.removeAttribute('required');
                    importDivisiSelect.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                    importDivisiSelect.value = '';
                    return;
                }

                const kategori = (selectedOpt.getAttribute('data-kategori') || '').toLowerCase();
                const divisiData = selectedOpt.getAttribute('data-divisi') ? JSON.parse(selectedOpt.getAttribute('data-divisi')) : [];

                // Hanya gudang kategori 'operasional' (seperti Gudang Gaharu & Gudang KeJingga) yang memiliki divisi
                // Gudang Central Kitchen, Cold Kitchen, dan Gudang Utama TIDAK memiliki divisi
                if (kategori === 'operasional' && divisiData && divisiData.length > 0) {
                    importDivisiWrapper.style.display = 'block';
                    importDivisiSelect.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                    divisiData.forEach(d => {
                        importDivisiSelect.innerHTML += `<option value="${d.id}">${d.nama}</option>`;
                    });
                    importDivisiSelect.setAttribute('required', 'required');
                } else {
                    importDivisiWrapper.style.display = 'none';
                    importDivisiSelect.removeAttribute('required');
                    importDivisiSelect.innerHTML = '<option value="">-- Pilih Divisi --</option>';
                    importDivisiSelect.value = '';
                }
            }

            if (importGudang) {
                importGudang.addEventListener('change', updateImportDivisi);
                updateImportDivisi();
            }
        });
    </script>
    @endpush
</x-app-layout>
