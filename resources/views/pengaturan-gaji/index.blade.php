<x-app-layout>
    <x-slot name="header">
        Pengaturan Gaji Karyawan
    </x-slot>

    <div class="container-fluid px-4 py-3">
        <x-outlet-selector :selectedOutlet="$selectedOutlet" />

        <!-- HEADER & PENCARIAN -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1" style="color: #3d1f0a;">Pengaturan Gaji Master - Outlet {{ $selectedOutlet }}</h4>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">
                    Kelola komponen dasar gaji harian karyawan (Gaji Pokok, Uang Makan, Uang Transport) sebagai dasar kalkulasi payroll Outlet {{ $selectedOutlet }}.
                </p>
            </div>
            <div class="d-flex gap-2">
                <form action="{{ route('pengaturan-gaji.index') }}" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / jabatan..." value="{{ request('search') }}" style="width: 220px; border-radius: 8px;">
                    <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 8px;">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('pengaturan-gaji.index', ['outlet' => $selectedOutlet]) }}" class="btn btn-sm btn-secondary" style="border-radius: 8px;">Reset</a>
                    @endif
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- CARDS INFO RUMUS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-semibold">Total Karyawan</small>
                    <h3 class="fw-bold text-dark mb-0 mt-1">{{ number_format($totalKaryawan) }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <small class="text-muted fw-semibold">Rata-rata Tarif Harian Total</small>
                    <h3 class="fw-bold text-success mb-0 mt-1">Rp {{ number_format($avgTarifHarian ?? 0, 0, ',', '.') }}</h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3" style="background: #fff8f0; border-left: 4px solid #d88656 !important;">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-calculator text-warning fs-5"></i>
                        <div>
                            <strong class="text-dark small d-block mb-1">Rumus Tarif Harian Total:</strong>
                            <div class="text-muted small">
                                <code>Tarif Harian Total</code> = <strong>Gaji Pokok Harian</strong> + <strong>Uang Makan</strong> + <strong>Uang Transport</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL PENGATURAN GAJI -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">Daftar Komponen Gaji Karyawan</h6>
                <span class="badge bg-light text-secondary border">Halaman {{ $karyawans->currentPage() }} dari {{ $karyawans->lastPage() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead style="background:#7A4517; color:white;">
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>Nama Karyawan</th>
                            <th>Jabatan / Departemen</th>
                            <th>Periode Berlaku</th>
                            <th class="text-end">Gaji Pokok Harian</th>
                            <th class="text-end">Uang Makan (Harian)</th>
                            <th class="text-end">Uang Transport (Harian)</th>
                            <th class="text-end">Tarif Harian Total</th>
                            <th width="100" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($karyawans as $index => $k)
                            <tr>
                                <td class="text-center text-muted small">{{ $karyawans->firstItem() + $index }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $k->nama_karyawan }}</div>
                                    <small class="text-muted">{{ $k->jenis_tenaga_kerja ?? 'Karyawan' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $k->jabatan }}</span>
                                    <small class="text-muted d-block mt-1">{{ $k->departemen }}</small>
                                </td>
                                <td>
                                    @php
                                        $now = \Carbon\Carbon::now();
                                        $p1End = $k->tanggal_selesai ? \Carbon\Carbon::parse($k->tanggal_selesai) : null;
                                        $p2End = $k->tanggal_selesai_2 ? \Carbon\Carbon::parse($k->tanggal_selesai_2) : null;
                                        
                                        // Cek apakah ada periode yang hampir selesai (dalam 7 hari ke depan atau sudah lewat)
                                        $warningText = null;
                                        $warningClass = 'warning';
                                        
                                        if ($p1End && !$k->tanggal_mulai_2) {
                                            $diffDays = $now->diffInDays($p1End, false);
                                            if ($diffDays < 0) {
                                                $warningText = 'Periode 1 Berakhir (' . abs((int)$diffDays) . ' hari lalu)';
                                                $warningClass = 'danger';
                                            } elseif ($diffDays <= 7) {
                                                $warningText = 'Selesai dalam ' . (int)$diffDays . ' hari';
                                                $warningClass = 'warning';
                                            }
                                        } elseif ($p2End) {
                                            $diffDays2 = $now->diffInDays($p2End, false);
                                            if ($diffDays2 < 0) {
                                                $warningText = 'Periode 2 Berakhir (' . abs((int)$diffDays2) . ' hari lalu)';
                                                $warningClass = 'danger';
                                            } elseif ($diffDays2 <= 7) {
                                                $warningText = 'P2 Selesai dalam ' . (int)$diffDays2 . ' hari';
                                                $warningClass = 'warning';
                                            }
                                        }
                                    @endphp

                                    {{-- Periode 1 --}}
                                    <div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:10px;">P1</span>
                                        @if($k->tanggal_mulai)
                                            <span style="font-size:11.5px;">{{ $k->tanggal_mulai->format('d/m/y') }} - {{ $k->tanggal_selesai ? $k->tanggal_selesai->format('d/m/y') : 'Seterusnya' }}</span>
                                        @else
                                            <span class="text-muted" style="font-size:11.5px;">Belum diatur</span>
                                        @endif
                                    </div>

                                    {{-- Periode 2 --}}
                                    @if($k->tanggal_mulai_2 || $k->gaji_pokok_2 !== null)
                                    <div class="mt-1">
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:10px;">P2</span>
                                        <span style="font-size:11.5px;">{{ $k->tanggal_mulai_2 ? $k->tanggal_mulai_2->format('d/m/y') : '-' }} - {{ $k->tanggal_selesai_2 ? $k->tanggal_selesai_2->format('d/m/y') : 'Seterusnya' }}</span>
                                    </div>
                                    @endif

                                    {{-- Warning Badge --}}
                                    @if($warningText)
                                        <div class="mt-1">
                                            <span class="badge bg-{{ $warningClass }} text-dark" style="font-size: 10px;">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $warningText }} - Perlu Atur Ulang!
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">
                                    <div>Rp {{ number_format($k->gaji_pokok, 0, ',', '.') }}</div>
                                    @if($k->gaji_pokok_2 !== null)
                                        <small class="text-muted d-block">P2: Rp {{ number_format($k->gaji_pokok_2, 0, ',', '.') }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">
                                    <div>Rp {{ number_format($k->uang_makan, 0, ',', '.') }}</div>
                                    @if($k->uang_makan_2 !== null)
                                        <small class="text-muted d-block">P2: Rp {{ number_format($k->uang_makan_2, 0, ',', '.') }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">
                                    <div>Rp {{ number_format($k->uang_transport, 0, ',', '.') }}</div>
                                    @if($k->uang_transport_2 !== null)
                                        <small class="text-muted d-block">P2: Rp {{ number_format($k->uang_transport_2, 0, ',', '.') }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success" style="font-size: 0.95rem;">
                                    <div>Rp {{ number_format($k->tarif_harian_total, 0, ',', '.') }}</div>
                                    @if($k->gaji_pokok_2 !== null)
                                        <small class="text-muted d-block fw-semibold">P2: Rp {{ number_format($k->tarif_harian_total_2, 0, ',', '.') }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-warning text-dark fw-semibold"
                                            onclick="editPengaturanGaji({{ json_encode($k) }})"
                                            title="Edit Gaji">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    Belum ada data karyawan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white py-3 px-4 border-top">
                {{ $karyawans->links() }}
            </div>
        </div>
    </div>

    <!-- MODAL EDIT PENGATURAN GAJI -->
    <div class="modal fade" id="modalEditGaji" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                <div class="modal-header text-white px-4 py-3" style="background:#7A4517;">
                    <h5 class="modal-title fw-bold" id="modalEditTitle">Edit Pengaturan Gaji</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditGaji" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-body p-4">
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <div class="text-muted small">Karyawan:</div>
                            <div class="fw-bold fs-6 text-dark" id="modalNamaKaryawan"></div>
                            <div class="text-muted small" id="modalJabatan"></div>
                        </div>

                        <div class="card p-3 border rounded-3 mb-3" style="background:#f8fafc;">
                            <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;"><i class="bi bi-1-circle-fill text-primary me-1"></i> Periode Gaji 1 (Masa Probation / Awal)</h6>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_mulai" id="inputTanggalMulai" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Tanggal Selesai</label>
                                    <input type="date" name="tanggal_selesai" id="inputTanggalSelesai" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="gaji_pokok" id="inputGajiPokok" class="form-control form-control-sm" min="0" step="100" required oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Uang Makan (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="uang_makan" id="inputUangMakan" class="form-control form-control-sm" min="0" step="100" required oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Transport (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="uang_transport" id="inputUangTransport" class="form-control form-control-sm" min="0" step="100" required oninput="hitungTarifHarianModal()">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="small fw-semibold text-muted">Total Tarif Harian 1:</span>
                                <span class="fw-bold text-success small" id="modalTarifHarianTotal">Rp 0</span>
                            </div>
                        </div>

                        <div class="card p-3 border rounded-3 mb-2" style="background:#fffcf7; border-color:#fed7aa !important;">
                            <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;"><i class="bi bi-2-circle-fill text-warning me-1"></i> Periode Gaji 2 (Setelah Probation / Lanjutan) <small class="text-muted fw-normal">(Opsional)</small></h6>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_mulai_2" id="inputTanggalMulai2" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Tanggal Selesai</label>
                                    <input type="date" name="tanggal_selesai_2" id="inputTanggalSelesai2" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Gaji Pokok (Rp)</label>
                                    <input type="number" name="gaji_pokok_2" id="inputGajiPokok2" class="form-control form-control-sm" min="0" step="100" oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Uang Makan (Rp)</label>
                                    <input type="number" name="uang_makan_2" id="inputUangMakan2" class="form-control form-control-sm" min="0" step="100" oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;">Transport (Rp)</label>
                                    <input type="number" name="uang_transport_2" id="inputUangTransport2" class="form-control form-control-sm" min="0" step="100" oninput="hitungTarifHarianModal()">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="small fw-semibold text-muted">Total Tarif Harian 2:</span>
                                <span class="fw-bold text-success small" id="modalTarifHarianTotal2">Rp 0</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function editPengaturanGaji(karyawan) {
        document.getElementById('modalNamaKaryawan').innerText = karyawan.nama_karyawan;
        document.getElementById('modalJabatan').innerText = (karyawan.jabatan || '') + ' - ' + (karyawan.departemen || '');
        
        // Periode 1
        document.getElementById('inputGajiPokok').value = karyawan.gaji_pokok || 0;
        document.getElementById('inputUangMakan').value = karyawan.uang_makan || 0;
        document.getElementById('inputUangTransport').value = karyawan.uang_transport || 0;
        document.getElementById('inputTanggalMulai').value = karyawan.tanggal_mulai ? karyawan.tanggal_mulai.substring(0, 10) : '';
        document.getElementById('inputTanggalSelesai').value = karyawan.tanggal_selesai ? karyawan.tanggal_selesai.substring(0, 10) : '';

        // Periode 2
        document.getElementById('inputGajiPokok2').value = karyawan.gaji_pokok_2 !== null ? karyawan.gaji_pokok_2 : '';
        document.getElementById('inputUangMakan2').value = karyawan.uang_makan_2 !== null ? karyawan.uang_makan_2 : '';
        document.getElementById('inputUangTransport2').value = karyawan.uang_transport_2 !== null ? karyawan.uang_transport_2 : '';
        document.getElementById('inputTanggalMulai2').value = karyawan.tanggal_mulai_2 ? karyawan.tanggal_mulai_2.substring(0, 10) : '';
        document.getElementById('inputTanggalSelesai2').value = karyawan.tanggal_selesai_2 ? karyawan.tanggal_selesai_2.substring(0, 10) : '';

        document.getElementById('formEditGaji').action = `/pengaturan-gaji/${karyawan.id}`;

        hitungTarifHarianModal();

        let modalEl = document.getElementById('modalEditGaji');
        let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function hitungTarifHarianModal() {
        let gp = parseFloat(document.getElementById('inputGajiPokok').value) || 0;
        let um = parseFloat(document.getElementById('inputUangMakan').value) || 0;
        let ut = parseFloat(document.getElementById('inputUangTransport').value) || 0;
        let total = gp + um + ut;
        document.getElementById('modalTarifHarianTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');

        let gp2 = parseFloat(document.getElementById('inputGajiPokok2').value) || 0;
        let um2 = parseFloat(document.getElementById('inputUangMakan2').value) || 0;
        let ut2 = parseFloat(document.getElementById('inputUangTransport2').value) || 0;
        let total2 = gp2 + um2 + ut2;
        document.getElementById('modalTarifHarianTotal2').innerText = 'Rp ' + total2.toLocaleString('id-ID');
    }
    </script>
    @endpush
</x-app-layout>
