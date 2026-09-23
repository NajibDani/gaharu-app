<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1">Master Data Konsumen &amp; Pelanggan</h4>
                <p class="text-muted small mb-0">Kelola daftar konsumen Cold Kitchen (Luar/Mitra) dan Outlet Internal. Transaksi konsumen akan otomatis tercatat pada Buku Pembantu Piutang Usaha.</p>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid px-2 px-md-4 py-3">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <button type="button" class="btn btn-custom-orange shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="bi bi-person-plus-fill"></i> Tambah Konsumen / Pelanggan Baru
                    </button>

                    <form action="{{ route('customer.index') }}" method="GET" class="d-flex gap-2">
                        <div class="input-group input-group-sm" style="min-width: 250px;">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama/jenis/hp..." value="{{ request('search') }}" style="border-radius: 8px 0 0 8px; border: 1px solid #DCD3CB;">
                            <button type="submit" class="btn btn-custom-orange" style="border-radius: 0 8px 8px 0; padding: 0 14px; font-weight: 600;">
                                <i class="bi bi-search"></i> Cari
                            </button>
                        </div>
                        @if(request('search'))
                            <a href="{{ route('customer.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center" style="border-radius: 8px;" title="Reset Filter">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </form>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3 small mb-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive rounded-3 border">
                    <table class="table table-hover align-middle mb-0 text-center" style="font-size: 13px;">
                        <thead style="background-color: #715745; color: white;">
                            <tr>
                                <th width="50" style="background-color: #715745; color: white;">No</th>
                                <th class="text-start" style="background-color: #715745; color: white;">Nama Konsumen / Outlet</th>
                                <th style="background-color: #715745; color: white;">Jenis Pelanggan</th>
                                <th style="background-color: #715745; color: white;">No HP / Kontak</th>
                                <th class="text-start" style="background-color: #715745; color: white;">Alamat</th>
                                <th width="140" style="background-color: #715745; color: white;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $item)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration + ($data->currentPage() - 1) * $data->perPage() }}</td>
                                <td class="text-start fw-bold text-dark">
                                    <i class="bi bi-person-circle me-1 text-secondary"></i> {{ $item->nama }}
                                </td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                        if (str_contains(strtolower($item->jenis), 'internal')) {
                                            $badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                        } elseif (str_contains(strtolower($item->jenis), 'cold') || str_contains(strtolower($item->jenis), 'luar')) {
                                            $badgeClass = 'bg-warning-subtle text-dark border border-warning-subtle fw-bold';
                                        }
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2.5 py-1">
                                        {{ $item->jenis }}
                                    </span>
                                </td>
                                <td>{{ $item->no_hp ?? '-' }}</td>
                                <td class="text-start text-muted">{{ $item->alamat ?? '-' }}</td>
                                <td>
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info btn-detil"
                                            data-bs-toggle="modal" data-bs-target="#showModal"
                                            data-nama="{{ $item->nama }}"
                                            data-jenis="{{ $item->jenis }}"
                                            data-no_hp="{{ $item->no_hp }}"
                                            data-alamat="{{ $item->alamat }}" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-edit"
                                            data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="{{ $item->id }}"
                                            data-nama="{{ $item->nama }}"
                                            data-jenis="{{ $item->jenis }}"
                                            data-no_hp="{{ $item->no_hp }}"
                                            data-alamat="{{ $item->alamat }}" title="Edit Data">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <form action="{{ route('customer.destroy', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data konsumen ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                    Belum ada data konsumen / pelanggan.
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
    </div>

    {{-- ==================== MODAL: TAMBAH CUSTOMER ==================== --}}
    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form action="{{ route('customer.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="form_type" value="create">

                    <div class="modal-header text-white" style="background-color: #DE8958;">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i> Tambah Konsumen / Pelanggan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4 bg-white">
                        @if (isset($errors) && $errors->any() && old('form_type') === 'create')
                            <div class="alert alert-danger small">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Nama Konsumen / Outlet <span class="text-danger">*</span></label>
                            <input type="text" name="nama" value="{{ old('form_type') === 'create' ? old('nama') : '' }}" class="form-control rounded-3" placeholder="Contoh: Konsumen Cold Kitchen / Cafe ABC / Outlet XYZ" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Jenis Pelanggan <span class="text-danger">*</span></label>
                            <select name="jenis" class="form-select rounded-3" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="Konsumen Cold Kitchen" {{ old('form_type') === 'create' && old('jenis') == 'Konsumen Cold Kitchen' ? 'selected' : '' }}>Konsumen Cold Kitchen (Luar)</option>
                                <option value="Konsumen Luar" {{ old('form_type') === 'create' && old('jenis') == 'Konsumen Luar' ? 'selected' : '' }}>Konsumen Luar / Mitra Bisnis</option>
                                <option value="Outlet Internal" {{ old('form_type') === 'create' && old('jenis') == 'Outlet Internal' ? 'selected' : '' }}>Outlet Internal</option>
                                <option value="Reseller" {{ old('form_type') === 'create' && old('jenis') == 'Reseller' ? 'selected' : '' }}>Reseller</option>
                                <option value="Horeca" {{ old('form_type') === 'create' && old('jenis') == 'Horeca' ? 'selected' : '' }}>Horeca (Hotel / Resto / Cafe)</option>
                                <option value="Corporate" {{ old('form_type') === 'create' && old('jenis') == 'Corporate' ? 'selected' : '' }}>Corporate</option>
                                <option value="Umum" {{ old('form_type') === 'create' && old('jenis') == 'Umum' ? 'selected' : '' }}>Umum</option>
                            </select>
                            <small class="text-muted" style="font-size: 11px;">Pilih <strong>Konsumen Cold Kitchen</strong> untuk pelanggan di luar gudang Gaharu/KeJingga/CK.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">No. HP / Kontak</label>
                            <input type="text" name="no_hp" value="{{ old('form_type') === 'create' ? old('no_hp') : '' }}" class="form-control rounded-3" placeholder="08xxxxxxxxxx">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control rounded-3" rows="3" placeholder="Alamat pengiriman / lokasi pelanggan...">{{ old('form_type') === 'create' ? old('alamat') : '' }}</textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light py-3">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-custom-orange px-4 fw-bold">Simpan Konsumen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL: EDIT CUSTOMER ==================== --}}
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form action="" method="POST" id="editForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_type" value="edit">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="modal-header text-white" style="background-color: #DE8958;">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Data Konsumen / Pelanggan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4 bg-white">
                        @if (isset($errors) && $errors->any() && old('form_type') === 'edit')
                            <div class="alert alert-danger small">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Nama Konsumen / Outlet <span class="text-danger">*</span></label>
                            <input type="text" name="nama" id="edit_nama" class="form-control rounded-3" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Jenis Pelanggan <span class="text-danger">*</span></label>
                            <select name="jenis" id="edit_jenis" class="form-select rounded-3" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="Konsumen Cold Kitchen">Konsumen Cold Kitchen (Luar)</option>
                                <option value="Konsumen Luar">Konsumen Luar / Mitra Bisnis</option>
                                <option value="Outlet Internal">Outlet Internal</option>
                                <option value="Reseller">Reseller</option>
                                <option value="Horeca">Horeca (Hotel / Resto / Cafe)</option>
                                <option value="Corporate">Corporate</option>
                                <option value="Umum">Umum</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">No. HP / Kontak</label>
                            <input type="text" name="no_hp" id="edit_no_hp" class="form-control rounded-3">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Alamat Lengkap</label>
                            <textarea name="alamat" id="edit_alamat" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light py-3">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-custom-orange px-4 fw-bold">Update Konsumen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL: DETIL CUSTOMER ==================== --}}
    <div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header text-white" style="background-color: #715745;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard me-2"></i> Informasi Konsumen / Pelanggan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="fw-bold text-muted small text-uppercase">Nama Konsumen / Outlet</label>
                        <p class="fs-5 text-dark fw-bold mb-0" id="show_nama"></p>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-muted small text-uppercase">Jenis Pelanggan</label>
                        <p class="fs-6 mb-0"><span class="badge bg-warning-subtle text-dark border px-3 py-1 fw-bold" id="show_jenis"></span></p>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-muted small text-uppercase">Nomor HP / Kontak</label>
                        <p class="fs-6 text-dark mb-0" id="show_no_hp"></p>
                    </div>

                    <div class="mb-0">
                        <label class="fw-bold text-muted small text-uppercase">Alamat</label>
                        <p class="fs-6 text-dark mb-0" id="show_alamat"></p>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // Isi modal Detil dari data-* pada tombol yang diklik
    document.querySelectorAll('.btn-detil').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('show_nama').innerText = this.dataset.nama;
            document.getElementById('show_jenis').innerText = this.dataset.jenis;
            document.getElementById('show_no_hp').innerText = this.dataset.no_hp;
            document.getElementById('show_alamat').innerText = this.dataset.alamat;
        });
    });

    // Isi modal Edit dari data-* pada tombol yang diklik + set action form sesuai id
    var editUrlTemplate = "{{ route('customer.update', ':id') }}";

    document.querySelectorAll('.btn-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('editForm').action = editUrlTemplate.replace(':id', this.dataset.id);
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_nama').value = this.dataset.nama;
            document.getElementById('edit_jenis').value = this.dataset.jenis;
            document.getElementById('edit_no_hp').value = this.dataset.no_hp;
            document.getElementById('edit_alamat').value = this.dataset.alamat;
        });
    });

    // Kalau validasi gagal, buka kembali modal yang sesuai secara otomatis
    document.addEventListener('DOMContentLoaded', function () {
        @if (isset($errors) && $errors->any() && old('form_type') === 'create')
            new bootstrap.Modal(document.getElementById('createModal')).show();
        @elseif (isset($errors) && $errors->any() && old('form_type') === 'edit')
            document.getElementById('editForm').action = editUrlTemplate.replace(':id', "{{ old('id') }}");
            document.getElementById('edit_nama').value = "{{ old('nama') }}";
            document.getElementById('edit_jenis').value = "{{ old('jenis') }}";
            document.getElementById('edit_no_hp').value = "{{ old('no_hp') }}";
            document.getElementById('edit_alamat').value = "{{ old('alamat') }}";
            new bootstrap.Modal(document.getElementById('editModal')).show();
        @endif
    });
</script>

</x-app-layout>