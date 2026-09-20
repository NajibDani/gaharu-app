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
                            <th class="text-center">Satuan Gaji</th>
                            <th>Periode Berlaku</th>
                            <th class="text-end">Gaji Pokok</th>
                            <th class="text-end">Uang Makan</th>
                            <th class="text-end">Uang Transport</th>
                            <th class="text-end">Tarif Total Master</th>
                            <th width="100" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($karyawans as $index => $k)
                            @php
                                $satuan1 = $k->satuan_gaji ?? 'Harian';
                                $satuan2 = $k->satuan_gaji_2 ?? $satuan1;
                                $suffix1 = $satuan1 === 'Bulanan' ? '/bln' : ($satuan1 === 'Per Jam' ? '/jam' : '/hari');
                                $suffix2 = $satuan2 === 'Bulanan' ? '/bln' : ($satuan2 === 'Per Jam' ? '/jam' : '/hari');
                            @endphp
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
                                <td class="text-center">
                                    <div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1" style="font-size:9.5px;">P1</span>
                                        @if($satuan1 === 'Bulanan')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 font-bold">Bulanan</span>
                                        @elseif($satuan1 === 'Per Jam')
                                            <span class="badge px-2 py-0.5 font-bold" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe;">Per Jam</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 font-bold">Harian</span>
                                        @endif
                                    </div>
                                    @if($k->tanggal_mulai_2 || $k->gaji_pokok_2 !== null)
                                    <div class="mt-1">
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle me-1" style="font-size:9.5px;">P2</span>
                                        @if($satuan2 === 'Bulanan')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 font-bold">Bulanan</span>
                                        @elseif($satuan2 === 'Per Jam')
                                            <span class="badge px-2 py-0.5 font-bold" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe;">Per Jam</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 font-bold">Harian</span>
                                        @endif
                                    </div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $now = \Carbon\Carbon::now();
                                        $p1End = $k->tanggal_selesai ? \Carbon\Carbon::parse($k->tanggal_selesai) : null;
                                        $p2End = $k->tanggal_selesai_2 ? \Carbon\Carbon::parse($k->tanggal_selesai_2) : null;
                                        
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
                                    <div>Rp {{ number_format($k->tarif_harian_total, 0, ',', '.') }} <small class="text-muted fw-normal" style="font-size:11px;">{{ $suffix1 }}</small></div>
                                    @if($k->gaji_pokok_2 !== null)
                                        <small class="text-muted d-block fw-semibold">P2: Rp {{ number_format($k->tarif_harian_total_2, 0, ',', '.') }} {{ $suffix2 }}</small>
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
                                <td colspan="10" class="text-center py-4 text-muted">
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
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
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

                        {{-- PERIODE 1 --}}
                        <div class="card p-3 border rounded-3 mb-3" style="background:#f8fafc;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;"><i class="bi bi-1-circle-fill text-primary me-1"></i> Periode Gaji 1</h6>
                            </div>

                            {{-- Satuan Gaji P1 --}}
                            <div class="mb-2 p-2 border rounded-2 bg-white">
                                <label class="form-label fw-bold text-dark d-block mb-1" style="font-size:11px;">
                                    Satuan / Basis Gaji Periode 1 <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-2">
                                    <label class="p-1.5 border rounded-2 flex-fill text-center cursor-pointer mb-0" id="labelOptHarian1" style="cursor: pointer; font-size:12px;">
                                        <input class="form-check-input me-1" type="radio" name="satuan_gaji" id="satuanHarian1" value="Harian" checked onchange="updateSatuanGajiLabels1()">
                                        <span class="fw-bold text-dark">Harian</span>
                                    </label>
                                    <label class="p-1.5 border rounded-2 flex-fill text-center cursor-pointer mb-0" id="labelOptBulanan1" style="cursor: pointer; font-size:12px;">
                                        <input class="form-check-input me-1" type="radio" name="satuan_gaji" id="satuanBulanan1" value="Bulanan" onchange="updateSatuanGajiLabels1()">
                                        <span class="fw-bold text-dark">Bulanan</span>
                                    </label>
                                    <label class="p-1.5 border rounded-2 flex-fill text-center cursor-pointer mb-0" id="labelOptPerJam1" style="cursor: pointer; font-size:12px;">
                                        <input class="form-check-input me-1" type="radio" name="satuan_gaji" id="satuanPerJam1" value="Per Jam" onchange="updateSatuanGajiLabels1()">
                                        <span class="fw-bold text-dark">Per Jam</span>
                                    </label>
                                </div>
                            </div>

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
                                    <label class="form-label fw-semibold" style="font-size:11px;" id="lblGajiPokok1">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="gaji_pokok" id="inputGajiPokok" class="form-control form-control-sm" min="0" step="100" required oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;" id="lblUangMakan1">Uang Makan (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="uang_makan" id="inputUangMakan" class="form-control form-control-sm" min="0" step="100" required oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;" id="lblTransport1">Transport (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="uang_transport" id="inputUangTransport" class="form-control form-control-sm" min="0" step="100" required oninput="hitungTarifHarianModal()">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="small fw-semibold text-muted" id="lblTotal1">Total Tarif Master 1:</span>
                                <span class="fw-bold text-success small" id="modalTarifHarianTotal">Rp 0</span>
                            </div>
                        </div>

                        {{-- PERIODE 2 --}}
                        <div class="card p-3 border rounded-3 mb-2" style="background:#fffcf7; border-color:#fed7aa !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;"><i class="bi bi-2-circle-fill text-warning me-1"></i> Periode Gaji 2 <small class="text-muted fw-normal">(Opsional)</small></h6>
                            </div>

                            {{-- Satuan Gaji P2 --}}
                            <div class="mb-2 p-2 border rounded-2 bg-white">
                                <label class="form-label fw-bold text-dark d-block mb-1" style="font-size:11px;">
                                    Satuan / Basis Gaji Periode 2
                                </label>
                                <div class="d-flex gap-2">
                                    <label class="p-1.5 border rounded-2 flex-fill text-center cursor-pointer mb-0" id="labelOptHarian2" style="cursor: pointer; font-size:12px;">
                                        <input class="form-check-input me-1" type="radio" name="satuan_gaji_2" id="satuanHarian2" value="Harian" checked onchange="updateSatuanGajiLabels2()">
                                        <span class="fw-bold text-dark">Harian</span>
                                    </label>
                                    <label class="p-1.5 border rounded-2 flex-fill text-center cursor-pointer mb-0" id="labelOptBulanan2" style="cursor: pointer; font-size:12px;">
                                        <input class="form-check-input me-1" type="radio" name="satuan_gaji_2" id="satuanBulanan2" value="Bulanan" onchange="updateSatuanGajiLabels2()">
                                        <span class="fw-bold text-dark">Bulanan</span>
                                    </label>
                                    <label class="p-1.5 border rounded-2 flex-fill text-center cursor-pointer mb-0" id="labelOptPerJam2" style="cursor: pointer; font-size:12px;">
                                        <input class="form-check-input me-1" type="radio" name="satuan_gaji_2" id="satuanPerJam2" value="Per Jam" onchange="updateSatuanGajiLabels2()">
                                        <span class="fw-bold text-dark">Per Jam</span>
                                    </label>
                                </div>
                            </div>

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
                                    <label class="form-label fw-semibold" style="font-size:11px;" id="lblGajiPokok2">Gaji Pokok (Rp)</label>
                                    <input type="number" name="gaji_pokok_2" id="inputGajiPokok2" class="form-control form-control-sm" min="0" step="100" oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;" id="lblUangMakan2">Uang Makan (Rp)</label>
                                    <input type="number" name="uang_makan_2" id="inputUangMakan2" class="form-control form-control-sm" min="0" step="100" oninput="hitungTarifHarianModal()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:11px;" id="lblTransport2">Transport (Rp)</label>
                                    <input type="number" name="uang_transport_2" id="inputUangTransport2" class="form-control form-control-sm" min="0" step="100" oninput="hitungTarifHarianModal()">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="small fw-semibold text-muted" id="lblTotal2">Total Tarif Master 2:</span>
                                <span class="fw-bold text-success small" id="modalTarifHarianTotal2">Rp 0</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn text-white px-4 fw-bold" style="background:#7A4517;">
                            <i class="bi bi-save me-1"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    let currentSatuan1 = 'Harian';
    let currentSatuan2 = 'Harian';

    function getSelectedSatuan1() {
        const radios = document.getElementsByName('satuan_gaji');
        for (let r of radios) {
            if (r.checked) return r.value;
        }
        return 'Harian';
    }

    function getSelectedSatuan2() {
        const radios = document.getElementsByName('satuan_gaji_2');
        for (let r of radios) {
            if (r.checked) return r.value;
        }
        return 'Harian';
    }

    function updateSatuanGajiLabels1() {
        currentSatuan1 = getSelectedSatuan1();

        ['Harian', 'Bulanan', 'Per Jam'].forEach(sat => {
            const key = sat.replace(' ', '');
            const labelEl = document.getElementById('labelOpt' + key + '1');
            const radioEl = document.getElementById('satuan' + key + '1');
            if (labelEl && radioEl) {
                if (radioEl.checked) {
                    labelEl.style.backgroundColor = '#dbeafe';
                    labelEl.style.borderColor = '#3b82f6';
                } else {
                    labelEl.style.backgroundColor = '#f8fafc';
                    labelEl.style.borderColor = '#e2e8f0';
                }
            }
        });

        if (currentSatuan1 === 'Bulanan') {
            document.getElementById('lblGajiPokok1').innerHTML = 'Gaji Pokok (Bln) <span class="text-danger">*</span>';
            document.getElementById('lblUangMakan1').innerHTML = 'Uang Makan (Bln) <span class="text-danger">*</span>';
            document.getElementById('lblTransport1').innerHTML = 'Transport (Bln) <span class="text-danger">*</span>';
            document.getElementById('lblTotal1').innerText = 'Total Tarif Bulanan 1:';
        } else if (currentSatuan1 === 'Per Jam') {
            document.getElementById('lblGajiPokok1').innerHTML = 'Gaji / Jam (Rp) <span class="text-danger">*</span>';
            document.getElementById('lblUangMakan1').innerHTML = 'Makan / Jam <span class="text-danger">*</span>';
            document.getElementById('lblTransport1').innerHTML = 'Transport / Jam <span class="text-danger">*</span>';
            document.getElementById('lblTotal1').innerText = 'Total Tarif Per Jam 1:';
        } else {
            document.getElementById('lblGajiPokok1').innerHTML = 'Gaji Pokok (Harian) <span class="text-danger">*</span>';
            document.getElementById('lblUangMakan1').innerHTML = 'Uang Makan (Harian) <span class="text-danger">*</span>';
            document.getElementById('lblTransport1').innerHTML = 'Transport (Harian) <span class="text-danger">*</span>';
            document.getElementById('lblTotal1').innerText = 'Total Tarif Harian 1:';
        }

        hitungTarifHarianModal();
    }

    function updateSatuanGajiLabels2() {
        currentSatuan2 = getSelectedSatuan2();

        ['Harian', 'Bulanan', 'Per Jam'].forEach(sat => {
            const key = sat.replace(' ', '');
            const labelEl = document.getElementById('labelOpt' + key + '2');
            const radioEl = document.getElementById('satuan' + key + '2');
            if (labelEl && radioEl) {
                if (radioEl.checked) {
                    labelEl.style.backgroundColor = '#fef3c7';
                    labelEl.style.borderColor = '#f59e0b';
                } else {
                    labelEl.style.backgroundColor = '#f8fafc';
                    labelEl.style.borderColor = '#e2e8f0';
                }
            }
        });

        if (currentSatuan2 === 'Bulanan') {
            document.getElementById('lblGajiPokok2').innerText = 'Gaji Pokok (Bln)';
            document.getElementById('lblUangMakan2').innerText = 'Uang Makan (Bln)';
            document.getElementById('lblTransport2').innerText = 'Transport (Bln)';
            document.getElementById('lblTotal2').innerText = 'Total Tarif Bulanan 2:';
        } else if (currentSatuan2 === 'Per Jam') {
            document.getElementById('lblGajiPokok2').innerText = 'Gaji / Jam (Rp)';
            document.getElementById('lblUangMakan2').innerText = 'Makan / Jam';
            document.getElementById('lblTransport2').innerText = 'Transport / Jam';
            document.getElementById('lblTotal2').innerText = 'Total Tarif Per Jam 2:';
        } else {
            document.getElementById('lblGajiPokok2').innerText = 'Gaji Pokok (Harian)';
            document.getElementById('lblUangMakan2').innerText = 'Uang Makan (Harian)';
            document.getElementById('lblTransport2').innerText = 'Transport (Harian)';
            document.getElementById('lblTotal2').innerText = 'Total Tarif Harian 2:';
        }

        hitungTarifHarianModal();
    }

    function editPengaturanGaji(karyawan) {
        document.getElementById('modalNamaKaryawan').innerText = karyawan.nama_karyawan;
        document.getElementById('modalJabatan').innerText = (karyawan.jabatan || '') + ' - ' + (karyawan.departemen || '');
        
        // Satuan Gaji P1
        let sat1 = karyawan.satuan_gaji || 'Harian';
        if (sat1 === 'Bulanan') {
            document.getElementById('satuanBulanan1').checked = true;
        } else if (sat1 === 'Per Jam') {
            document.getElementById('satuanPerJam1').checked = true;
        } else {
            document.getElementById('satuanHarian1').checked = true;
        }
        updateSatuanGajiLabels1();

        // Satuan Gaji P2
        let sat2 = karyawan.satuan_gaji_2 || sat1 || 'Harian';
        if (sat2 === 'Bulanan') {
            document.getElementById('satuanBulanan2').checked = true;
        } else if (sat2 === 'Per Jam') {
            document.getElementById('satuanPerJam2').checked = true;
        } else {
            document.getElementById('satuanHarian2').checked = true;
        }
        updateSatuanGajiLabels2();

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
        let suffix1 = currentSatuan1 === 'Bulanan' ? ' / bulan' : (currentSatuan1 === 'Per Jam' ? ' / jam' : ' / hari');
        let suffix2 = currentSatuan2 === 'Bulanan' ? ' / bulan' : (currentSatuan2 === 'Per Jam' ? ' / jam' : ' / hari');
        
        let gp = parseFloat(document.getElementById('inputGajiPokok').value) || 0;
        let um = parseFloat(document.getElementById('inputUangMakan').value) || 0;
        let ut = parseFloat(document.getElementById('inputUangTransport').value) || 0;
        let total = gp + um + ut;
        document.getElementById('modalTarifHarianTotal').innerText = 'Rp ' + total.toLocaleString('id-ID') + suffix1;

        let gp2 = parseFloat(document.getElementById('inputGajiPokok2').value) || 0;
        let um2 = parseFloat(document.getElementById('inputUangMakan2').value) || 0;
        let ut2 = parseFloat(document.getElementById('inputUangTransport2').value) || 0;
        let total2 = gp2 + um2 + ut2;
        document.getElementById('modalTarifHarianTotal2').innerText = 'Rp ' + total2.toLocaleString('id-ID') + suffix2;
    }
    </script>
    @endpush
</x-app-layout>
