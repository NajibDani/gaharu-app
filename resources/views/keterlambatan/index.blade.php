<x-app-layout>
    <x-slot name="header">
        Rekap & Perhitungan Keterlambatan Karyawan
    </x-slot>

    <div class="container-fluid px-4 py-3">
        <x-outlet-selector :selectedOutlet="$selectedOutlet" />

        <!-- HEADER & FILTER PERIODE -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1" style="color: #3d1f0a;">Rekap Keterlambatan Karyawan - Outlet {{ $selectedOutlet }}</h4>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">
                    Pencatatan dan akumulasi potongan denda keterlambatan yang <strong>otomatis terhubung (linked)</strong> ke Penggajian Karyawan Outlet {{ $selectedOutlet }}.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form action="{{ route('keterlambatan.index') }}" method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                    <select name="periode" class="form-select form-select-sm" style="border-radius: 8px; width: 150px;" onchange="this.form.submit()">
                        @foreach($periodes as $p)
                            @php
                                $carbonP = \Carbon\Carbon::parse($p . '-01');
                            @endphp
                            <option value="{{ $p }}" {{ $periode == $p ? 'selected' : '' }}>
                                {{ $carbonP->translatedFormat('F Y') }}
                            </option>
                        @endforeach
                    </select>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama karyawan..." value="{{ $search }}" style="width: 200px; border-radius: 8px;">
                    <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 8px;">Filter</button>
                    @if($search)
                        <a href="{{ route('keterlambatan.index', ['periode' => $periode, 'outlet' => $selectedOutlet]) }}" class="btn btn-sm btn-secondary" style="border-radius: 8px;">Reset</a>
                    @endif
                </form>

                <button type="button" class="btn btn-sm text-white fw-bold px-3 py-2 shadow-sm" style="background:#7A4517; border-radius: 8px;" onclick="bukaModalTambah()">
                    <i class="bi bi-plus-circle me-1"></i> + Catat Keterlambatan
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- STATISTIK RINGKASAN -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-semibold">Total Kejadian Terlambat</small>
                    <h3 class="fw-bold text-dark mb-0 mt-1">{{ number_format($totalKejadian) }} <small class="fs-6 text-muted font-normal">kali</small></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-semibold">Total Denda Periode Ini</small>
                    <h3 class="fw-bold text-danger mb-0 mt-1">Rp {{ number_format($totalPotonganBulanIni, 0, ',', '.') }}</h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3" style="background: #eef6ff; border-left: 4px solid #3b82f6 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-link-45deg fs-4 text-primary"></i>
                        <div>
                            <strong class="text-primary small d-block">Status Sinkronisasi Penggajian:</strong>
                            <span class="text-muted small">
                                Akumulasi potongan terlambat bulan ini (<strong>{{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}</strong>) otomatis ter-sync ke field <code>potongan_terlambat</code> pada Slip Penggajian Karyawan.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ATURAN POTONGAN KETERLAMBATAN (GAMBAR 2 SPREADSHEET) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-shield-exclamation text-warning me-1"></i> Ketentuan Skala Potongan Keterlambatan
                </h6>
                <small class="text-muted">Skala kelipatan Rp 10.000 per 10 menit keterlambatan</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 text-center" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2">Durasi Keterlambatan (Menit)</th>
                                <th class="py-2">Besaran Potongan</th>
                                <th class="py-2 text-start px-4">Contoh Kasus dalam Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">1 s.d. 10 Menit</td>
                                <td class="fw-bold text-danger">Rp 10.000</td>
                                <td class="text-start px-4 text-muted">Telat 1 menit (11:01:00) / Telat 5 menit (08:05:00) / Telat 9 menit (07:09:00)</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">11 s.d. 20 Menit</td>
                                <td class="fw-bold text-danger">Rp 20.000</td>
                                <td class="text-start px-4 text-muted">Telat 12 menit (11:12:00) / Telat 18 menit (15:18:00)</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">21 s.d. 30 Menit</td>
                                <td class="fw-bold text-danger">Rp 30.000</td>
                                <td class="text-start px-4 text-muted">Telat 26 menit (08:26:00)</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">31 s.d. 40 Menit</td>
                                <td class="fw-bold text-danger">Rp 40.000</td>
                                <td class="text-start px-4 text-muted">Telat 35 menit (10:35:00)</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">41 s.d. 50 Menit</td>
                                <td class="fw-bold text-danger">Rp 50.000</td>
                                <td class="text-start px-4 text-muted">Telat 44 menit (10:44:00)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TABEL REKAP KETERLAMBATAN (GAMBAR 1 SPREADSHEET) -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">Data Presensi Keterlambatan</h6>
                <span class="badge bg-light text-dark border">Periode {{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="tabelKeterlambatan" style="font-size: 0.9rem;">
                    <thead style="background:#7A4517; color:white;">
                        <tr>
                            <th width="40" class="text-center">No</th>
                            <th>Nama Karyawan</th>
                            <th>Tanggal Presensi</th>
                            <th>Periode Gaji</th>
                            <th>Shift</th>
                            <th class="text-center">Jam Shift</th>
                            <th class="text-center">Jam Datang</th>
                            <th class="text-center">Telat (Menit)</th>
                            <th class="text-end">Potongan Keterlambatan</th>
                            <th class="text-end" style="background:#5a3416;">Akumulasi Potongan</th>
                            <th width="90" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($listKeterlambatan as $index => $item)
                            @php
                                $pKey = $item->periode_info['key'] ?? 'REG';
                                $isLastRowOfPeriod = isset($lastRowPerKaryawanPeriode[$item->karyawan_id][$pKey]) && $lastRowPerKaryawanPeriode[$item->karyawan_id][$pKey] == $item->id;
                                $totalAkumulasiPeriode = $akumulasiPerKaryawanPeriode[$item->karyawan_id][$pKey] ?? $item->potongan;
                            @endphp
                            <tr class="hover-row">
                                <td class="text-center text-muted small">{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $item->karyawan->nama_karyawan ?? '-' }}</div>
                                    <small class="text-muted">{{ $item->karyawan->jabatan ?? '-' }}</small>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d F Y') }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $item->periode_info['badge_class'] ?? 'bg-light text-dark border' }}" style="font-size: 11px; font-weight: 600;">
                                        📅 {{ $item->periode_info['label'] ?? 'Reguler' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $item->shift ?: 'Shift' }}</span>
                                </td>
                                <td class="text-center font-monospace">{{ $item->jam_shift }}</td>
                                <td class="text-center font-monospace text-danger fw-semibold">{{ $item->jam_datang }}</td>
                                <td class="text-center">
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2">
                                        +{{ $item->durasi_menit }} min
                                    </span>
                                </td>
                                <td class="text-end fw-semibold text-danger">
                                    Rp {{ number_format($item->potongan, 0, ',', '.') }}
                                </td>
                                <td class="text-end fw-bold" style="background:#fdfbf7;">
                                    @if($isLastRowOfPeriod)
                                        <span class="text-danger fs-6 d-block">Rp {{ number_format($totalAkumulasiPeriode, 0, ',', '.') }}</span>
                                        <small class="text-muted" style="font-size: 10px; font-weight: 500;">Subtotal {{ $item->periode_info['label'] ?? '' }}</small>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark py-0 px-2" onclick="editKeterlambatan({{ json_encode($item) }})" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form action="{{ route('keterlambatan.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Hapus catatan keterlambatan ini?')" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-check-circle fs-3 d-block text-success mb-2"></i>
                                    Tidak ada catatan keterlambatan pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL CATAT / EDIT KETERLAMBATAN -->
    <div class="modal fade" id="modalKeterlambatan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                <div class="modal-header text-white px-4 py-3" style="background:#7A4517;">
                    <h5 class="modal-title fw-bold" id="modalTitle">Catat Keterlambatan Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formKeterlambatan" method="POST" action="{{ route('keterlambatan.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nama Karyawan <span class="text-danger">*</span></label>
                            <select name="karyawan_id" id="modalKaryawanId" class="form-select" required>
                                <option value="">-- Pilih Karyawan --</option>
                                @foreach($karyawans as $k)
                                    <option value="{{ $k->id }}">{{ $k->nama_karyawan }} ({{ $k->jabatan }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Tanggal Presensi <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" id="modalTanggal" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold small mb-0">Shift</label>
                                    <button type="button" class="btn btn-link p-0 text-primary small text-decoration-none fw-semibold" onclick="bukaModalKelolaShift()" style="font-size: 11.5px;">
                                        <i class="bi bi-gear-fill me-1"></i>Atur Pilihan Shift
                                    </button>
                                </div>
                                <select name="shift" id="modalShift" class="form-select" onchange="onShiftSelectChange(this)">
                                    @foreach($shifts ?? [] as $s)
                                        <option value="{{ $s->nama }}" data-time="{{ $s->jam_shift }}">{{ $s->nama }}</option>
                                    @endforeach
                                    <option value="__add_new__" class="text-primary fw-bold">+ Tambah Pilihan Shift Baru...</option>
                                </select>
                                <div class="mt-1 text-end">
                                    <small class="text-muted" style="font-size: 11px;">
                                        Ingin ubah daftar jam di atas? Klik <a href="javascript:void(0)" onclick="bukaModalKelolaShift()" class="text-primary fw-bold text-decoration-underline">Atur Pilihan Shift</a>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Jam Shift JADWAL <span class="text-danger">*</span></label>
                                <input type="time" step="1" name="jam_shift" id="modalJamShift" class="form-control" value="08:00:00" required onchange="onJamShiftManualChange()" oninput="onJamShiftManualChange()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Jam DATANG Aktual <span class="text-danger">*</span></label>
                                <input type="time" step="1" name="jam_datang" id="modalJamDatang" class="form-control" value="08:05:00" required onchange="hitungLivePotongan()" oninput="hitungLivePotongan()">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Keterangan / Alasan (Opsional)</label>
                            <input type="text" name="keterangan" id="modalKeterangan" class="form-control" placeholder="Contoh: Ban bocor di jalan...">
                        </div>

                        <!-- LIVE PREVIEW KALKULASI -->
                        <div class="p-3 rounded-3" style="background:#fff8f0; border:1px solid #f2d28c;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">Durasi Terlambat:</span>
                                <strong class="text-dark" id="previewDurasi">0 Menit</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted fw-bold">Potongan Denda:</span>
                                <strong class="text-danger fs-6" id="previewPotongan">Rp 0</strong>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan & Link ke Penggajian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL KELOLA / EDIT PILIHAN SHIFT (MASTER SHIFT) -->
    <div class="modal fade" id="modalKelolaShift" tabindex="-1" aria-labelledby="modalKelolaShiftTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                <div class="modal-header text-white px-4 py-3" style="background-color: #593b22;">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalKelolaShiftTitle"><i class="bi bi-clock-history me-2"></i>Kelola Pilihan Shift Dropdown</h5>
                        <small class="text-white-50" style="font-size: 11.5px;">Atur, edit nama, jam shift, atau tambah opsi shift baru untuk pilihan dropdown.</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <!-- FORM TAMBAH SHIFT BARU -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
                        <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;"><i class="bi bi-plus-circle-fill text-success me-1"></i> Tambah Pilihan Shift Baru</h6>
                        <form id="formTambahShift" onsubmit="handleTambahShift(event)">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold small mb-1">Nama Shift <span class="text-danger">*</span></label>
                                    <input type="text" id="tambahNamaShift" class="form-control form-control-sm" placeholder="Contoh: Morning 08.00 / Shift Siang" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small mb-1">Jam Shift JADWAL <span class="text-danger">*</span></label>
                                    <input type="time" step="1" id="tambahJamShift" class="form-control form-control-sm" value="08:00:00" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold" id="btnSubmitTambahShift">
                                        <i class="bi bi-plus-lg me-1"></i> Tambah Shift
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- DAFTAR PILIHAN SHIFT SAAT INI -->
                    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
                        <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark small"><i class="bi bi-list-ul me-1 text-primary"></i> Daftar Pilihan Shift di Dropdown</span>
                            <span class="badge bg-secondary-subtle text-secondary px-2 py-1" id="badgeTotalShift" style="font-size: 11px;">0 Shift</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                                    <tr>
                                        <th class="ps-3 py-2" width="40">#</th>
                                        <th class="py-2">Nama Shift</th>
                                        <th class="py-2" width="160">Jam Shift</th>
                                        <th class="text-center py-2" width="130">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDaftarShift">
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">Memuat data shift...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-2.5 border-top d-flex justify-content-between">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Perubahan akan langsung terupdate pada dropdown keterlambatan.</small>
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Selesai</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    let globalShifts = [];

    // Load Master Shifts dari Server
    function loadMasterShifts(selectedToSelect = null) {
        fetch("{{ route('keterlambatan.shifts.index') }}")
            .then(res => res.json())
            .then(shifts => {
                globalShifts = shifts;
                renderShiftDropdown(shifts, selectedToSelect);
                renderShiftTable(shifts);
            })
            .catch(err => {
                console.error("Gagal memuat master shift:", err);
            });
    }

    function renderShiftDropdown(shifts, selectedVal = null) {
        const selectEl = document.getElementById('modalShift');
        if (!selectEl) return;

        const currentVal = selectedVal || selectEl.value;
        let html = '';

        shifts.forEach(s => {
            const isSelected = (currentVal === s.nama) ? 'selected' : '';
            html += `<option value="${escapeHtml(s.nama)}" data-time="${s.jam_shift}" ${isSelected}>${escapeHtml(s.nama)}</option>`;
        });

        // Opsi jika custom / tidak ada di list
        if (currentVal && currentVal !== '__add_new__' && !shifts.some(s => s.nama === currentVal)) {
            html += `<option value="${escapeHtml(currentVal)}" data-time="" selected>${escapeHtml(currentVal)}</option>`;
        }

        html += `<option value="__add_new__" class="text-primary fw-bold">+ Tambah / Edit Pilihan Shift...</option>`;
        selectEl.innerHTML = html;

        // Update jam shift input jika ada yang terpilih
        const activeOpt = selectEl.options[selectEl.selectedIndex];
        if (activeOpt && activeOpt.getAttribute('data-time')) {
            document.getElementById('modalJamShift').value = activeOpt.getAttribute('data-time');
            hitungLivePotongan();
        }
    }

    function renderShiftTable(shifts) {
        const tbody = document.getElementById('tbodyDaftarShift');
        const badge = document.getElementById('badgeTotalShift');
        if (badge) badge.innerText = shifts.length + ' Shift';

        if (!shifts || shifts.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted">Belum ada pilihan shift. Silakan tambah di atas.</td></tr>`;
            return;
        }

        let html = '';
        shifts.forEach((s, idx) => {
            html += `
                <tr id="shift-row-${s.id}">
                    <td class="ps-3 text-muted font-monospace">${idx + 1}</td>
                    <td>
                        <span class="fw-semibold text-dark shift-view-nama">${escapeHtml(s.nama)}</span>
                        <input type="text" class="form-control form-control-sm d-none shift-edit-nama" value="${escapeHtml(s.nama)}">
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border font-monospace shift-view-jam">${s.jam_shift}</span>
                        <input type="time" step="1" class="form-control form-control-sm d-none shift-edit-jam" value="${s.jam_shift}">
                    </td>
                    <td class="text-center">
                        <div class="shift-view-actions">
                            <button type="button" class="btn btn-outline-primary btn-sm py-0.5 px-2 me-1" onclick="mulaiEditShiftRow(${s.id})" title="Edit Shift">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0.5 px-2" onclick="hapusShiftItem(${s.id}, '${escapeHtml(s.nama)}')" title="Hapus Shift">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                        <div class="shift-edit-actions d-none">
                            <button type="button" class="btn btn-success btn-sm py-0.5 px-2 me-1" onclick="simpanEditShiftRow(${s.id})" title="Simpan">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm py-0.5 px-2" onclick="batalEditShiftRow(${s.id})" title="Batal">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function onShiftSelectChange(selectEl) {
        let val = selectEl.value;
        if (val === '__add_new__') {
            bukaModalKelolaShift();
            return;
        }

        let selectedOpt = selectEl.options[selectEl.selectedIndex];
        let presetTime = selectedOpt.getAttribute('data-time');
        let jamShiftInput = document.getElementById('modalJamShift');

        if (presetTime) {
            jamShiftInput.value = presetTime;
        }

        hitungLivePotongan();
    }

    function onJamShiftManualChange() {
        let jamShiftInput = document.getElementById('modalJamShift');
        let selectEl = document.getElementById('modalShift');
        let val = jamShiftInput.value;

        if (!val) return;

        // Cari apakah ada shift di master yang jamnya sama
        let matchedShift = globalShifts.find(s => s.jam_shift === val || s.jam_shift.substring(0, 5) === val.substring(0, 5));
        if (matchedShift && selectEl.querySelector(`option[value="${matchedShift.nama}"]`)) {
            selectEl.value = matchedShift.nama;
        }

        hitungLivePotongan();
    }

    function bukaModalKelolaShift() {
        loadMasterShifts();
        let modalEl = document.getElementById('modalKelolaShift');
        let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function handleTambahShift(e) {
        e.preventDefault();
        const namaInput = document.getElementById('tambahNamaShift');
        const jamInput = document.getElementById('tambahJamShift');
        const submitBtn = document.getElementById('btnSubmitTambahShift');

        const nama = namaInput.value.trim();
        const jam = jamInput.value;

        if (!nama || !jam) return;

        const origBtn = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menambah...';

        fetch("{{ route('keterlambatan.shifts.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                nama: nama,
                jam_shift: jam
            })
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtn;

            if (data.success) {
                namaInput.value = '';
                globalShifts = data.shifts;
                renderShiftTable(data.shifts);
                renderShiftDropdown(data.shifts, data.shift.nama);
            } else {
                alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtn;
            alert('Kesalahan jaringan: ' + err.message);
        });
    }

    function mulaiEditShiftRow(id) {
        const row = document.getElementById(`shift-row-${id}`);
        if (!row) return;

        row.querySelector('.shift-view-nama').classList.add('d-none');
        row.querySelector('.shift-edit-nama').classList.remove('d-none');

        row.querySelector('.shift-view-jam').classList.add('d-none');
        row.querySelector('.shift-edit-jam').classList.remove('d-none');

        row.querySelector('.shift-view-actions').classList.add('d-none');
        row.querySelector('.shift-edit-actions').classList.remove('d-none');
    }

    function batalEditShiftRow(id) {
        const row = document.getElementById(`shift-row-${id}`);
        if (!row) return;

        row.querySelector('.shift-view-nama').classList.remove('d-none');
        row.querySelector('.shift-edit-nama').classList.add('d-none');

        row.querySelector('.shift-view-jam').classList.remove('d-none');
        row.querySelector('.shift-edit-jam').classList.add('d-none');

        row.querySelector('.shift-view-actions').classList.remove('d-none');
        row.querySelector('.shift-edit-actions').classList.add('d-none');
    }

    function simpanEditShiftRow(id) {
        const row = document.getElementById(`shift-row-${id}`);
        if (!row) return;

        const nama = row.querySelector('.shift-edit-nama').value.trim();
        const jam = row.querySelector('.shift-edit-jam').value;

        if (!nama || !jam) {
            alert('Nama dan jam shift tidak boleh kosong.');
            return;
        }

        fetch(`/keterlambatan/shifts/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                nama: nama,
                jam_shift: jam
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                globalShifts = data.shifts;
                renderShiftTable(data.shifts);
                renderShiftDropdown(data.shifts, data.shift.nama);
            } else {
                alert('Gagal memperbarui shift: ' + (data.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => {
            alert('Kesalahan jaringan: ' + err.message);
        });
    }

    function hapusShiftItem(id, nama) {
        if (!confirm(`Apakah Anda yakin ingin menghapus pilihan shift "${nama}" dari dropdown?`)) return;

        fetch(`/keterlambatan/shifts/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                globalShifts = data.shifts;
                renderShiftTable(data.shifts);
                renderShiftDropdown(data.shifts);
            } else {
                alert('Gagal menghapus shift: ' + (data.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => {
            alert('Kesalahan jaringan: ' + err.message);
        });
    }

    function hitungLivePotongan() {
        let jamShift = document.getElementById('modalJamShift').value;
        let jamDatang = document.getElementById('modalJamDatang').value;
        let karyawanId = document.getElementById('modalKaryawanId').value;
        let tanggal = document.getElementById('modalTanggal').value;

        if (!jamShift || !jamDatang) return;

        let url = `/keterlambatan/hitung-ajax?jam_shift=${jamShift}&jam_datang=${jamDatang}`;
        if (karyawanId) {
            url += `&karyawan_id=${karyawanId}`;
        }
        if (tanggal) {
            url += `&tanggal=${tanggal}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                document.getElementById('previewDurasi').innerText = data.durasi_menit + ' Menit';
                document.getElementById('previewPotongan').innerText = data.potongan_formatted;
            })
            .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadMasterShifts();

        const triggers = ['modalKaryawanId', 'modalTanggal', 'modalJamShift', 'modalJamDatang'];
        triggers.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', hitungLivePotongan);
                el.addEventListener('input', hitungLivePotongan);
            }
        });
    });

    function bukaModalTambah() {
        document.getElementById('modalTitle').innerText = 'Catat Keterlambatan Baru';
        document.getElementById('formKeterlambatan').action = "{{ route('keterlambatan.store') }}";
        document.getElementById('formMethod').value = 'POST';

        document.getElementById('modalKaryawanId').value = '';
        document.getElementById('modalTanggal').value = "{{ date('Y-m-d') }}";
        
        if (globalShifts.length > 0) {
            document.getElementById('modalShift').value = globalShifts[0].nama;
            document.getElementById('modalJamShift').value = globalShifts[0].jam_shift;
        } else {
            document.getElementById('modalJamShift').value = '08:00:00';
        }

        document.getElementById('modalJamDatang').value = '08:05:00';
        document.getElementById('modalKeterangan').value = '';

        hitungLivePotongan();

        let modalEl = document.getElementById('modalKeterlambatan');
        let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function editKeterlambatan(item) {
        document.getElementById('modalTitle').innerText = 'Edit Data Keterlambatan';
        document.getElementById('formKeterlambatan').action = `/keterlambatan/${item.id}`;
        document.getElementById('formMethod').value = 'PUT';

        document.getElementById('modalKaryawanId').value = item.karyawan_id;
        document.getElementById('modalTanggal').value = item.tanggal.substring(0, 10);
        
        let itemShift = item.shift || (globalShifts.length > 0 ? globalShifts[0].nama : 'Morning 08.00');
        renderShiftDropdown(globalShifts, itemShift);

        document.getElementById('modalJamShift').value = item.jam_shift;
        document.getElementById('modalJamDatang').value = item.jam_datang;
        document.getElementById('modalKeterangan').value = item.keterangan || '';

        hitungLivePotongan();

        let modalEl = document.getElementById('modalKeterlambatan');
        let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
    </script>
    @endpush
</x-app-layout>
