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
                                <label class="form-label fw-semibold small">Shift</label>
                                <select name="shift" id="modalShift" class="form-select" onchange="autoFillShiftTime(this)">
                                    <option value="Morning 08.00">Morning 08.00</option>
                                    <option value="Morning 07.00">Morning 07.00</option>
                                    <option value="Middle 10.00">Middle 10.00</option>
                                    <option value="Middle 11.00">Middle 11.00</option>
                                    <option value="Middle 12.00">Middle 12.00</option>
                                    <option value="Evening 15.00">Evening 15.00</option>
                                    <option value="Custom">Custom Shift</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Jam Shift JADWAL <span class="text-danger">*</span></label>
                                <input type="time" step="1" name="jam_shift" id="modalJamShift" class="form-control" value="08:00:00" required onchange="hitungLivePotongan()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Jam DATANG Aktual <span class="text-danger">*</span></label>
                                <input type="time" step="1" name="jam_datang" id="modalJamDatang" class="form-control" value="08:05:00" required onchange="hitungLivePotongan()">
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

    @push('scripts')
    <script>
    function autoFillShiftTime(selectEl) {
        let val = selectEl.value;
        let jamShiftInput = document.getElementById('modalJamShift');
        
        if (val.includes('08.00')) jamShiftInput.value = '08:00:00';
        else if (val.includes('07.00')) jamShiftInput.value = '07:00:00';
        else if (val.includes('10.00')) jamShiftInput.value = '10:00:00';
        else if (val.includes('11.00')) jamShiftInput.value = '11:00:00';
        else if (val.includes('12.00')) jamShiftInput.value = '12:00:00';
        else if (val.includes('15.00')) jamShiftInput.value = '15:00:00';

        hitungLivePotongan();
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
        document.getElementById('modalShift').value = 'Morning 08.00';
        document.getElementById('modalJamShift').value = '08:00:00';
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
        document.getElementById('modalShift').value = item.shift || 'Morning 08.00';
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
