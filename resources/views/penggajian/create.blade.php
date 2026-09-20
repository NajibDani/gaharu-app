<x-app-layout>
    <style>
        .pg-form-wrap { max-width: 780px; margin: 0 auto; padding: 24px 16px 40px; }
        .pg-section {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .pg-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .pg-section-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            border: 1px solid #cbd5e1;
        }
        .pg-section-title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.2px;
        }
        .pg-section-body { padding: 20px; }
        .pg-field { margin-bottom: 16px; }
        .pg-field:last-child { margin-bottom: 0; }
        .pg-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .pg-label .req { color: #dc2626; margin-left: 2px; }
        .pg-input {
            width: 100%;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 9px 13px;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            box-sizing: border-box;
        }
        .pg-input:focus { outline: none; border-color: #7A4517; box-shadow: 0 0 0 3px rgba(122,69,23,.15); }
        .pg-input-readonly {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            cursor: not-allowed;
            border-color: #cbd5e1;
        }
        .pg-input-rupiah { text-align: right; font-weight: 800; color: #0f172a; }
        .pg-hint { font-size: 11.5px; color: #64748b; font-weight: 500; margin-top: 4px; }
        .pg-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .pg-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        @media(max-width:600px) { .pg-grid-2, .pg-grid-4 { grid-template-columns: 1fr; } }
        .pg-summary {
            background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%);
            border: 1.5px solid #fcd34d;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .pg-summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; }
        @media(max-width:600px) { .pg-summary-grid { grid-template-columns: 1fr; } }
        .pg-summary-item { text-align: center; padding: 8px 12px; }
        .pg-summary-item + .pg-summary-item { border-left: 1px solid #fcd34d; }
        @media(max-width:600px) { .pg-summary-item + .pg-summary-item { border-left: none; border-top: 1px solid #fcd34d; } }
        .pg-summary-label { font-size: 11px; color: #78350f; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; }
        .pg-summary-value { font-size: 20px; font-weight: 900; margin-top: 4px; }
        .pg-btn-save {
            width: 100%;
            background: #7A4517;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s;
            box-shadow: 0 2px 6px rgba(122,69,23,0.3);
        }
        .pg-btn-save:hover { background: #5a3416; }
        .pg-card-periode {
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all .15s;
            background: #fff;
        }
        .pg-card-periode:hover { border-color: #0284c7; background: #f0f9ff; }
        .d-none { display: none !important; }
    </style>

    @php
        $selectedOutlet = $selectedOutlet ?? ($payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu');
        $isLockKaryawan = request('lock_karyawan') == 1;
        $preKaryawanId  = request('karyawan_id', isset($payroll) ? $payroll->karyawan_id : null);
    @endphp

    <div class="pg-form-wrap">
        {{-- Header Outlet & Periode --}}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0;padding-bottom:14px;">
            <div>
                <a href="{{ route('penggajian.show-periode', ['periode' => $target_periode, 'outlet' => $selectedOutlet]) }}"
                   style="font-size:12px;font-weight:700;color:#475569;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:4px;" class="hover:text-[#7A4517]">
                    <span>&larr;</span> Kembali ke Detail Periode
                </a>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h1 style="font-size:18px;font-weight:900;color:#0f172a;margin:0;">
                        {{ isset($payroll) ? 'Ubah Gaji Pokok & Presensi' : 'Input Gaji Pokok & Presensi' }}
                    </h1>
                    <span style="display:inline-flex;align-items:center;gap:5px;background:#fef3c7;color:#78350f;border:1px solid #fcd34d;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:800;">
                        <span>&#127978;</span> Outlet {{ $selectedOutlet }}
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#1e293b;border:1px solid #cbd5e1;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;">
                        <span>&#128197;</span> {{ \App\Models\Penggajian::formatPeriode($target_periode) }}
                    </span>
                </div>
            </div>
            
            {{-- Outlet Selector Links --}}
            <div style="display:flex;align-items:center;gap:6px;">
                @foreach(['Gaharu', 'Kejingga'] as $o)
                    @php $isActiveOutlet = (strtolower($selectedOutlet) == strtolower($o)); @endphp
                    <a href="{{ request()->fullUrlWithQuery(['outlet' => $o]) }}"
                       style="font-size:11.5px;font-weight:800;padding:6px 14px;border-radius:8px;text-decoration:none;transition:all .15s;{{ $isActiveOutlet ? 'background:#7A4517;color:#fff;' : 'background:#fff;color:#334155;border:1.5px solid #cbd5e1;' }}">
                        {{ $o }}
                    </a>
                @endforeach
            </div>
        </div>

        @if($errors->any())
        <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:13px;color:#991b1b;font-weight:600;">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ isset($payroll) ? route('penggajian.update', $payroll->id) : route('penggajian.store') }}"
              method="POST" id="formPayroll">
            @csrf
            @if(isset($payroll)) @method('PUT') @endif
            <input type="hidden" name="periode" value="{{ $target_periode }}">

            {{-- SECTION 1: INFORMASI KARYAWAN & TANGGAL --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#eff6ff;color:#1d4ed8;">&#128100;</div>
                    <span class="pg-section-title">Informasi Karyawan &amp; Rentang Tanggal</span>
                </div>
                <div class="pg-section-body">

                    @php
                        $targetEmp = $lockedKaryawan ?? ($preKaryawanId ? ($karyawans->firstWhere('id', $preKaryawanId) ?? \App\Models\Karyawan::find($preKaryawanId)) : (isset($payroll) ? $payroll->karyawan : null));
                    @endphp

                    <div class="pg-grid-2" style="align-items:start;">
                        {{-- KOLOM KIRI: DETAIL KARYAWAN --}}
                        <div>
                            @if(($isLockKaryawan || request('lock_karyawan')) && $targetEmp)
                            {{-- LOCKED MODE --}}
                            <div class="pg-field" style="margin-bottom:0;">
                                <label class="pg-label">&#128274; Karyawan Terpilih <span class="req">*</span></label>
                                <div style="background: #fffbf5; border: 1.5px solid #fcd34d; border-radius: 12px; padding: 12px 14px;">
                                    <div style="font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.2;">{{ $targetEmp->nama_karyawan }}</div>
                                    <div style="font-size: 11.5px; color: #78350f; font-weight: 700; margin-top: 3px;">{{ $targetEmp->jabatan ?? '-' }}</div>
                                    <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 6px;">
                                        @if($targetEmp->departemen)
                                            <span style="font-size: 10.5px; font-weight: 800; background: #fef3c7; color: #78350f; border: 1px solid #fcd34d; border-radius: 4px; padding: 1px 6px;">
                                                {{ $targetEmp->departemen }}
                                            </span>
                                        @endif
                                        @if($targetEmp->no_rekening)
                                            <span style="font-size: 10.5px; font-weight: 700; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 4px; padding: 1px 6px;">
                                                Rek: {{ $targetEmp->no_rekening }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <input type="hidden" name="karyawan_id" value="{{ $targetEmp->id }}">
                            </div>
                            @elseif(isset($payroll))
                            {{-- EDIT MODE --}}
                            <div class="pg-field" style="margin-bottom:0;">
                                <label class="pg-label">Karyawan Terpilih <span class="req">*</span></label>
                                <div style="background: #fffbf5; border: 1.5px solid #fcd34d; border-radius: 12px; padding: 12px 14px;">
                                    <div style="font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.2;">{{ $payroll->karyawan->nama_karyawan }}</div>
                                    <div style="font-size: 11.5px; color: #78350f; font-weight: 700; margin-top: 3px;">{{ $payroll->karyawan->jabatan ?? '-' }}</div>
                                    <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 6px;">
                                        @if($payroll->karyawan->departemen)
                                            <span style="font-size: 10.5px; font-weight: 800; background: #fef3c7; color: #78350f; border: 1px solid #fcd34d; border-radius: 4px; padding: 1px 6px;">
                                                {{ $payroll->karyawan->departemen }}
                                            </span>
                                        @endif
                                        @if($payroll->karyawan->no_rekening)
                                            <span style="font-size: 10.5px; font-weight: 700; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 4px; padding: 1px 6px;">
                                                Rek: {{ $payroll->karyawan->no_rekening }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <input type="hidden" name="karyawan_id" value="{{ $payroll->karyawan_id }}">
                            </div>
                            @else
                            {{-- CREATE MODE: Pilih karyawan --}}
                            <div class="pg-field" style="margin-bottom:0;">
                                <label class="pg-label">Nama Karyawan <span class="req">*</span></label>
                                <input type="text" id="searchSelectKaryawan" onkeyup="filterKaryawanSelect()"
                                       placeholder="&#128269; Ketik untuk memfilter nama..."
                                       class="pg-input" style="margin-bottom:6px;font-size:12px;padding:6px 10px;">
                                <select name="karyawan_id" id="selectKaryawanId" required
                                        class="pg-input" onchange="updateMasterHarian(this)" style="font-size:13px;padding:8px 10px;">
                                    <option value="">-- Pilih Karyawan --</option>
                                    @foreach($karyawans as $k)
                                    @php
                                        $hasP2 = ($k->gaji_pokok_2 !== null);
                                        $p2Dates = ($hasP2 && $k->tanggal_mulai_2 && $k->tanggal_selesai_2)
                                            ? \Carbon\Carbon::parse($k->tanggal_mulai_2)->format('d/m') . ' s/d ' . \Carbon\Carbon::parse($k->tanggal_selesai_2)->format('d/m')
                                            : '';
                                    @endphp
                                    <option value="{{ $k->id }}"
                                            data-nama="{{ $k->nama_karyawan }}"
                                            data-jabatan="{{ $k->jabatan ?? '-' }}"
                                            data-departemen="{{ $k->departemen ?? '-' }}"
                                            data-outlet="{{ $k->outlet ?? $selectedOutlet }}"
                                            data-rekening="{{ $k->no_rekening ?? '-' }}"
                                            data-gapok="{{ $k->gaji_pokok ?? 0 }}"
                                            data-makan="{{ $k->uang_makan ?? 0 }}"
                                            data-transport="{{ $k->uang_transport ?? 0 }}"
                                            data-has-p2="{{ $hasP2 ? '1' : '0' }}"
                                            data-gapok2="{{ $k->gaji_pokok_2 ?? 0 }}"
                                            data-makan2="{{ $k->uang_makan_2 ?? 0 }}"
                                            data-transport2="{{ $k->uang_transport_2 ?? 0 }}"
                                            data-p2-mulai="{{ $k->tanggal_mulai_2 ?? '' }}"
                                            data-p2-selesai="{{ $k->tanggal_selesai_2 ?? '' }}"
                                            data-p2-dates="{{ $p2Dates }}"
                                            {{ (old('karyawan_id', $preKaryawanId) == $k->id) ? 'selected' : '' }}>
                                        {{ $k->nama_karyawan }} &mdash; {{ $k->jabatan ?? 'Karyawan' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>

                        {{-- KOLOM KANAN: TANGGAL MULAI & SELESAI SLIP SECARA HORIZONTAL --}}
                        <div>
                            <div class="pg-grid-2">
                                <div class="pg-field" style="margin-bottom:0;">
                                    <label class="pg-label">Tanggal Mulai Slip</label>
                                    <input type="date" name="tanggal_mulai" id="inputTanggalMulai"
                                           value="{{ old('tanggal_mulai', isset($payroll) && $payroll->tanggal_mulai ? \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d') : '') }}"
                                           class="pg-input" onchange="syncTanggalHariKerja()">
                                </div>
                                <div class="pg-field" style="margin-bottom:0;">
                                    <label class="pg-label">Tanggal Selesai Slip</label>
                                    <input type="date" name="tanggal_selesai" id="inputTanggalSelesai"
                                           value="{{ old('tanggal_selesai', isset($payroll) && $payroll->tanggal_selesai ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d') : '') }}"
                                           class="pg-input" onchange="syncTanggalHariKerja()">
                                </div>
                            </div>
                            <div class="pg-hint" style="margin-top:5px;">Isi rentang tanggal untuk menghitung hari kerja secara otomatis.</div>
                        </div>
                    </div>

                    {{-- DOKUMEN PREVIEW PERIODE 1 & PERIODE 2 --}}
                    <div id="containerPilihanPeriode" style="display:none;margin-top:14px;">
                        <label class="pg-label" style="margin-bottom:8px;">Pilih Periode Penggajian (Rentang Gaji Fluktuatif)</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div class="pg-card-periode" id="cardPeriodeA" onclick="selectPeriodeOption('A')">
                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <strong style="font-size:12.5px;color:#0f172a;">Periode 1 (Awal)</strong>
                                    <span id="badgeTarifA" style="font-size:11.5px;font-weight:800;color:#065f46;background:#ecfdf5;padding:2px 8px;border-radius:4px;border:1px solid #a7f3d0;">Rp 0/hr</span>
                                </div>
                                <div style="font-size:10.5px;color:#475569;margin-top:4px;font-weight:600;" id="lblDatesA">-</div>
                            </div>
                            <div class="pg-card-periode" id="cardPeriodeB" onclick="selectPeriodeOption('B')">
                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <strong style="font-size:12.5px;color:#0f172a;">Periode 2 (Penyesuaian)</strong>
                                    <span id="badgeTarifB" style="font-size:11.5px;font-weight:800;color:#1e40af;background:#eff6ff;padding:2px 8px;border-radius:4px;border:1px solid #bfdbfe;">Rp 0/hr</span>
                                </div>
                                <div style="font-size:10.5px;color:#475569;margin-top:4px;font-weight:600;" id="lblDatesB">-</div>
                            </div>
                        </div>
                    </div>

                    {{-- ALERT BATAS GAJI --}}
                    <div id="alertBatasGaji" style="display:none;margin-top:12px;background:#fffbeb;border:1.5px solid #fcd34d;border-radius:10px;padding:10px 12px;font-size:11.5px;color:#78350f;font-weight:600;">
                        <strong id="alertBatasGajiTitle" style="color:#78350f;">Peringatan!</strong><br>
                        <span id="alertBatasGajiMsg"></span>
                        <div style="margin-top:6px;">
                            <a href="{{ route('pengaturan-gaji.index') }}" target="_blank"
                                style="font-size:10.5px;background:#0f172a;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-weight:700;">
                                &#128279; Buka Pengaturan Gaji
                            </a>
                        </div>
                    </div>

                    {{-- NOTE FLUKTUATIF --}}
                    <div id="noteFluktuatifGaji" style="display:none;margin-top:10px;background:#e0f2fe;border:1px solid #7dd3fc;border-radius:8px;padding:8px 12px;font-size:11.5px;color:#0369a1;font-weight:600;"></div>

                    {{-- TARIF HARIAN MASTER --}}
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0;">
                        <label class="pg-label" style="margin-bottom:8px;">Rincian Tarif Harian Master</label>
                        <div class="pg-grid-4">
                            <div>
                                <div style="font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Gaji Pokok</div>
                                <input type="text" id="displayGajiPokok" readonly class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0">
                            </div>
                            <div>
                                <div style="font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Uang Makan</div>
                                <input type="text" id="displayUangMakan" readonly class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0">
                            </div>
                            <div>
                                <div style="font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Transport</div>
                                <input type="text" id="displayUangTransport" readonly class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0">
                            </div>
                            <div>
                                <div style="font-size:10px;color:#065f46;font-weight:800;text-transform:uppercase;margin-bottom:3px;">Total / Hari</div>
                                <input type="text" id="displayTarifHarianTotal" readonly class="pg-input pg-input-rupiah" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857;font-weight:900;cursor:not-allowed;" value="Rp 0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 2: PRESENSI & JUMLAH HARI KERJA --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#ecfdf5;color:#047857;">&#128197;</div>
                    <span class="pg-section-title">Presensi &amp; Jumlah Hari Kerja</span>
                </div>
                <div class="pg-section-body">
                    <div class="pg-grid-2">
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Jumlah Hari Kerja <span class="req">*</span></label>
                            <input type="number" name="hari_kerja" id="inputHariKerja" min="0" step="0.5"
                                   value="{{ isset($payroll) ? $payroll->hari_kerja : 0 }}"
                                   required class="pg-input" oninput="hitungKalkulasiGaji()">
                            <div class="pg-hint">Terisi otomatis dari rentang tanggal atau input manual.</div>
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Gaji Pokok Utama (Hari Kerja &times; Tarif)</label>
                            <input type="text" id="calcGajiUtama" readonly
                                   class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0"
                                   style="font-size:16px;color:#0f172a;font-weight:800;">
                        </div>
                    </div>
                </div>
            </div>

            {{-- RINGKASAN GAJI POKOK --}}
            <div class="pg-summary">
                <div class="pg-summary-grid">
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Tarif Harian Total</div>
                        <div class="pg-summary-value" style="color:#0284c7;" id="sumTarifHarian">Rp 0</div>
                    </div>
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Hari Kerja</div>
                        <div class="pg-summary-value" style="color:#0f172a;" id="sumHariKerja">0 Hari</div>
                    </div>
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Gaji Pokok Utama</div>
                        <div class="pg-summary-value" style="color:#7A4517;" id="sumGajiUtama">Rp 0</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="pg-btn-save">
                &#10003; {{ isset($payroll) ? 'Perbarui Gaji Pokok' : 'Simpan Gaji Pokok' }}
            </button>
        </form>
    </div>

    @push('scripts')
    <script>
        let currentGajiPokok = 0;
        let currentUangMakan = 0;
        let currentUangTransport = 0;
        let currentTarifHarianTotal = 0;

        function formatRupiah(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        function updateMasterHarian(selectEl) {
            if (!selectEl || selectEl.selectedIndex < 0) return;
            let option = selectEl.options[selectEl.selectedIndex];
            if (!option.value) return;

            let hasP2 = option.getAttribute('data-has-p2') === '1';
            let containerMulti = document.getElementById('containerPilihanPeriode');

            if (hasP2 && containerMulti) {
                containerMulti.style.display = 'block';

                let gp1 = parseFloat(option.getAttribute('data-gapok')) || 0;
                let um1 = parseFloat(option.getAttribute('data-makan')) || 0;
                let ut1 = parseFloat(option.getAttribute('data-transport')) || 0;
                let t1 = gp1 + um1 + ut1;

                let gp2 = parseFloat(option.getAttribute('data-gapok2')) || 0;
                let um2 = parseFloat(option.getAttribute('data-makan2')) || 0;
                let ut2 = parseFloat(option.getAttribute('data-transport2')) || 0;
                let t2 = gp2 + um2 + ut2;

                let p2Dates = option.getAttribute('data-p2-dates') || '-';

                document.getElementById('badgeTarifA').innerText = formatRupiah(t1) + '/hr';
                document.getElementById('lblDatesA').innerText = 'Tarif Dasar Awal';

                document.getElementById('badgeTarifB').innerText = formatRupiah(t2) + '/hr';
                document.getElementById('lblDatesB').innerText = 'Penyesuaian: ' + p2Dates;
            } else if (containerMulti) {
                containerMulti.style.display = 'none';
            }

            syncTanggalHariKerja();
        }

        function selectPeriodeOption(type) {
            const cardA = document.getElementById('cardPeriodeA');
            const cardB = document.getElementById('cardPeriodeB');
            if (cardA && cardB) {
                if (type === 'A') {
                    cardA.style.borderColor = '#0284c7';
                    cardA.style.backgroundColor = '#f0f9ff';
                    cardB.style.borderColor = '#cbd5e1';
                    cardB.style.backgroundColor = '#ffffff';
                } else {
                    cardB.style.borderColor = '#eab308';
                    cardB.style.backgroundColor = '#fefce8';
                    cardA.style.borderColor = '#cbd5e1';
                    cardA.style.backgroundColor = '#ffffff';
                }
            }
            syncTanggalHariKerja();
        }

        function syncTanggalHariKerja() {
            const selectEl = document.getElementById('selectKaryawanId');
            const option = selectEl && selectEl.selectedIndex >= 0 ? selectEl.options[selectEl.selectedIndex] : null;

            const tglMulaiInput = document.getElementById('inputTanggalMulai').value;
            const tglSelesaiInput = document.getElementById('inputTanggalSelesai').value;

            if (tglMulaiInput && tglSelesaiInput) {
                const d1 = new Date(tglMulaiInput);
                const d2 = new Date(tglSelesaiInput);
                if (d2 >= d1) {
                    const diffTime = Math.abs(d2 - d1);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    document.getElementById('inputHariKerja').value = diffDays;
                }
            }

            checkBatasGaji(option, tglMulaiInput, tglSelesaiInput);
            hitungKalkulasiGaji();
        }

        function checkBatasGaji(option, tglMulaiInput, tglSelesaiInput) {
            const alertBox = document.getElementById('alertBatasGaji');
            const noteBox = document.getElementById('noteFluktuatifGaji');

            @if(isset($payroll))
                let gp1 = {{ $payroll->karyawan->gaji_pokok ?? 0 }};
                let um1 = {{ $payroll->karyawan->uang_makan ?? 0 }};
                let ut1 = {{ $payroll->karyawan->uang_transport ?? 0 }};
                let hasP2 = {{ $payroll->karyawan->gaji_pokok_2 !== null ? 'true' : 'false' }};
                let gp2 = {{ $payroll->karyawan->gaji_pokok_2 ?? 0 }};
                let um2 = {{ $payroll->karyawan->uang_makan_2 ?? 0 }};
                let ut2 = {{ $payroll->karyawan->uang_transport_2 ?? 0 }};
                let p2Mulai = '{{ $payroll->karyawan->tanggal_mulai_2 ?? "" }}';
                let p2Selesai = '{{ $payroll->karyawan->tanggal_selesai_2 ?? "" }}';
            @else
                if (!option || !option.value) {
                    if (alertBox) alertBox.style.display = 'none';
                    if (noteBox) noteBox.style.display = 'none';
                    return;
                }

                let gp1 = parseFloat(option.getAttribute('data-gapok')) || 0;
                let um1 = parseFloat(option.getAttribute('data-makan')) || 0;
                let ut1 = parseFloat(option.getAttribute('data-transport')) || 0;
                let hasP2 = option.getAttribute('data-has-p2') === '1';
                let gp2 = parseFloat(option.getAttribute('data-gapok2')) || 0;
                let um2 = parseFloat(option.getAttribute('data-makan2')) || 0;
                let ut2 = parseFloat(option.getAttribute('data-transport2')) || 0;
                let p2Mulai = option.getAttribute('data-p2-mulai') || '';
                let p2Selesai = option.getAttribute('data-p2-selesai') || '';
            @endif

            let tarif1 = gp1 + um1 + ut1;
            let tarif2 = gp2 + um2 + ut2;

            let sStartStr = tglMulaiInput;
            let sEndStr = tglSelesaiInput;

            if (!sStartStr || !sEndStr) {
                const targetBulan = '{{ $target_periode }}';
                sStartStr = targetBulan + '-01';
                const dObj = new Date(targetBulan + '-01');
                const lastDay = new Date(dObj.getFullYear(), dObj.getMonth() + 1, 0).getDate();
                sEndStr = targetBulan + '-' + (lastDay < 10 ? '0' + lastDay : lastDay);
            }

            let n1 = 0;
            let n2 = 0;

            if (hasP2) {
                let curr = new Date(sStartStr);
                let end = new Date(sEndStr);

                while (curr <= end) {
                    let yyyy = curr.getFullYear();
                    let mm = String(curr.getMonth() + 1).padStart(2, '0');
                    let dd = String(curr.getDate()).padStart(2, '0');
                    let dStr = `${yyyy}-${mm}-${dd}`;

                    let isP2 = false;
                    if (p2Mulai && dStr >= p2Mulai) {
                        if (!p2Selesai || dStr <= p2Selesai) {
                            isP2 = true;
                        }
                    }

                    if (isP2) {
                        n2++;
                    } else {
                        n1++;
                    }

                    curr.setDate(curr.getDate() + 1);
                }
            } else {
                n1 = 1;
                n2 = 0;
            }

            let nTotal = n1 + n2;
            if (nTotal <= 0) nTotal = 1;

            window.currentN1 = n1;
            window.currentN2 = n2;
            window.currentNTotal = nTotal;
            window.currentTarif1 = tarif1;
            window.currentTarif2 = tarif2;
            window.currentHasP2 = hasP2;

            currentGajiPokok = (n1 * gp1 + n2 * gp2) / nTotal;
            currentUangMakan = (n1 * um1 + n2 * um2) / nTotal;
            currentUangTransport = (n1 * ut1 + n2 * ut2) / nTotal;
            currentTarifHarianTotal = currentGajiPokok + currentUangMakan + currentUangTransport;

            document.getElementById('displayGajiPokok').value = formatRupiah(currentGajiPokok);
            document.getElementById('displayUangMakan').value = formatRupiah(currentUangMakan);
            document.getElementById('displayUangTransport').value = formatRupiah(currentUangTransport);
            document.getElementById('displayTarifHarianTotal').value = formatRupiah(currentTarifHarianTotal);

            if (noteBox) {
                if (hasP2 && n1 > 0 && n2 > 0) {
                    noteBox.style.display = 'block';
                    noteBox.innerHTML = `Perhitungan Gaji Gabungan 2 Periode: <strong>${n1} hari @ ${formatRupiah(tarif1)} (P1)</strong> + <strong>${n2} hari @ ${formatRupiah(tarif2)} (P2)</strong>. Rata-rata Tarif: <strong>${formatRupiah(currentTarifHarianTotal)}/hari</strong>`;
                } else {
                    noteBox.style.display = 'none';
                }
            }
        }

        function hitungKalkulasiGaji() {
            let hariKerja = parseFloat(document.getElementById('inputHariKerja').value) || 0;
            let gajiUtama = 0;

            if (window.currentHasP2 && window.currentN1 > 0 && window.currentN2 > 0) {
                let prop1 = window.currentN1 / window.currentNTotal;
                let prop2 = window.currentN2 / window.currentNTotal;
                let hariP1 = hariKerja * prop1;
                let hariP2 = hariKerja * prop2;
                gajiUtama = (hariP1 * window.currentTarif1) + (hariP2 * window.currentTarif2);
            } else {
                gajiUtama = hariKerja * currentTarifHarianTotal;
            }

            document.getElementById('calcGajiUtama').value = formatRupiah(gajiUtama);
            document.getElementById('sumTarifHarian').innerText = formatRupiah(currentTarifHarianTotal);
            document.getElementById('sumHariKerja').innerText = hariKerja + ' Hari';
            document.getElementById('sumGajiUtama').innerText = formatRupiah(gajiUtama);
        }

        function filterKaryawanSelect() {
            const input = document.getElementById('searchSelectKaryawan').value.toLowerCase();
            const select = document.getElementById('selectKaryawanId');
            if (!select) return;
            const options = select.options;
            for (let i = 1; i < options.length; i++) {
                const text = options[i].text.toLowerCase();
                if (text.includes(input)) {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const selectKaryawan = document.getElementById('selectKaryawanId');
            if (selectKaryawan && selectKaryawan.value) {
                updateMasterHarian(selectKaryawan);
            } else {
                syncTanggalHariKerja();
            }
        });
    </script>
    @endpush
</x-app-layout>