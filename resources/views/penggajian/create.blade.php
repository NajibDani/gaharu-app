<x-app-layout>
    <style>
        .pg-form-wrap { max-width: 780px; margin: 0 auto; padding: 24px 16px 40px; }
        .pg-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .pg-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: #f9fafb;
            border-bottom: 1px solid #f3f4f6;
        }
        .pg-section-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .pg-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 0.2px;
        }
        .pg-section-body { padding: 20px; }
        .pg-field { margin-bottom: 16px; }
        .pg-field:last-child { margin-bottom: 0; }
        .pg-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .pg-label .req { color: #ef4444; margin-left: 2px; }
        .pg-input {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 9px 13px;
            font-size: 14px;
            color: #111827;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            box-sizing: border-box;
        }
        .pg-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
        .pg-input-readonly {
            background: #f8fafc;
            color: #4b5563;
            font-weight: 600;
            cursor: not-allowed;
        }
        .pg-input-rupiah { text-align: right; font-weight: 700; color: #0f172a; }
        .pg-hint { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .pg-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .pg-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        @media(max-width:600px) { .pg-grid-2, .pg-grid-4 { grid-template-columns: 1fr; } }
        .pg-sub-calc {
            font-size: 11px; color: #6b7280; text-align: right; margin-top: 4px;
        }
        .pg-summary {
            background: linear-gradient(135deg, #fffbf5 0%, #fff8ee 100%);
            border: 1.5px solid #f2d28c;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .pg-summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; }
        @media(max-width:600px) { .pg-summary-grid { grid-template-columns: 1fr; } }
        .pg-summary-item { text-align: center; padding: 8px 12px; }
        .pg-summary-item + .pg-summary-item { border-left: 1px solid #f2d28c; }
        @media(max-width:600px) { .pg-summary-item + .pg-summary-item { border-left: none; border-top: 1px solid #f2d28c; } }
        .pg-summary-label { font-size: 11px; color: #92400e; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; }
        .pg-summary-value { font-size: 20px; font-weight: 800; margin-top: 4px; }
        .pg-btn-save {
            width: 100%;
            background: #7A4517;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s;
        }
        .pg-btn-save:hover { background: #5a3416; }
        .pg-card-periode {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all .15s;
            background: #fff;
        }
        .pg-card-periode:hover { border-color: #93c5fd; background: #f0f9ff; }
        .pg-locked-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 11px;
            color: #92400e;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .d-none { display: none !important; }
    </style>

    @php
        $selectedOutlet = $selectedOutlet ?? ($payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu');
        $isLockKaryawan = request('lock_karyawan') == 1;
        $preKaryawanId  = request('karyawan_id', isset($payroll) ? $payroll->karyawan_id : null);
    @endphp



    <div class="pg-form-wrap">
        <x-outlet-selector :selectedOutlet="$selectedOutlet" />

        {{-- PAGE HEADER --}}
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
            <div>
                <a href="{{ route('penggajian.show-periode', ['periode' => $target_periode, 'outlet' => $selectedOutlet]) }}"
                   style="font-size:12px;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:6px;">
                    &larr; Kembali ke Detail Periode
                </a>
                <h1 style="font-size:18px;font-weight:800;color:#1e293b;margin:0;">
                    {{ isset($payroll) ? 'Ubah Data Penggajian' : 'Input Penggajian Karyawan' }}
                </h1>
                <p style="font-size:13px;color:#9ca3af;margin:4px 0 0;">
                    Outlet <strong style="color:#374151;">{{ $selectedOutlet }}</strong>
                    &nbsp;&middot;&nbsp; Periode <strong style="color:#374151;">{{ \App\Models\Penggajian::formatPeriode($target_periode) }}</strong>
                </p>
            </div>
        </div>

        @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:13px;color:#b91c1c;">
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

            {{-- ===================== SECTION 1: INFORMASI DASAR ===================== --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#eff6ff;">&#128100;</div>
                    <span class="pg-section-title">Informasi Dasar</span>
                </div>
                <div class="pg-section-body">

                    @php
                        $targetEmp = $lockedKaryawan ?? ($preKaryawanId ? ($karyawans->firstWhere('id', $preKaryawanId) ?? \App\Models\Karyawan::find($preKaryawanId)) : (isset($payroll) ? $payroll->karyawan : null));
                    @endphp

                    @if(($isLockKaryawan || request('lock_karyawan')) && $targetEmp)
                    {{-- LOCKED MODE: Dari tombol "+ Tambah Periode Gaji" --}}
                    <div class="pg-field">
                        <label class="pg-label">&#128274; Nama Karyawan <span class="req">*</span></label>
                        <div class="pg-locked-badge">&#128274; Karyawan ter-lock &mdash; Menambah Periode Gaji Baru</div>
                        <input type="text" class="pg-input pg-input-readonly"
                               value="{{ $targetEmp->nama_karyawan }} ({{ $targetEmp->jabatan }} - {{ $targetEmp->departemen }})"
                               readonly>
                        <input type="hidden" name="karyawan_id" value="{{ $targetEmp->id }}">
                    </div>
                    @elseif(isset($payroll))
                    {{-- EDIT MODE --}}
                    <div class="pg-field">
                        <label class="pg-label">Nama Karyawan <span class="req">*</span></label>
                        <input type="text" class="pg-input pg-input-readonly"
                               value="{{ $payroll->karyawan->nama_karyawan }} ({{ $payroll->karyawan->jabatan }} - {{ $payroll->karyawan->departemen }})"
                               readonly>
                        <input type="hidden" name="karyawan_id" value="{{ $payroll->karyawan_id }}">
                    </div>
                    @else
                    {{-- CREATE MODE: Pilih karyawan --}}
                    <div class="pg-field">
                        <label class="pg-label">Nama Karyawan <span class="req">*</span></label>
                        <input type="text" id="searchSelectKaryawan" onkeyup="filterKaryawanSelect()"
                               placeholder="&#128269; Ketik untuk memfilter..."
                               class="pg-input" style="margin-bottom:6px;">
                        <select name="karyawan_id" id="selectKaryawanId" required
                                class="pg-input" onchange="updateMasterHarian(this)">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                            @php
                                $terlambatInfo = $akumulasiTerlambatMap[$k->id] ?? null;
                                $totPotTerlambat = $terlambatInfo ? $terlambatInfo->total_potongan : 0;
                                $totKaliTerlambat = $terlambatInfo ? $terlambatInfo->total_kali : 0;
                            @endphp
                            <option value="{{ $k->id }}"
                                    data-gaji="{{ $k->gaji_pokok }}"
                                    data-makan="{{ $k->uang_makan }}"
                                    data-transport="{{ $k->uang_transport }}"
                                    data-tarif="{{ $k->tarif_harian_total }}"
                                    data-terlambat="{{ $totPotTerlambat }}"
                                    data-terlambat-kali="{{ $totKaliTerlambat }}"
                                    data-terlambat-json="{{ json_encode($keterlambatanRawMap[$k->id] ?? []) }}"
                                    data-mulai="{{ $k->tanggal_mulai ? $k->tanggal_mulai->format('Y-m-d') : '' }}"
                                    data-selesai="{{ $k->tanggal_selesai ? $k->tanggal_selesai->format('Y-m-d') : '' }}"
                                    data-mulai2="{{ $k->tanggal_mulai_2 ? $k->tanggal_mulai_2->format('Y-m-d') : '' }}"
                                    data-selesai2="{{ $k->tanggal_selesai_2 ? $k->tanggal_selesai_2->format('Y-m-d') : '' }}"
                                    data-gaji2="{{ $k->gaji_pokok_2 }}"
                                    data-makan2="{{ $k->uang_makan_2 }}"
                                    data-transport2="{{ $k->uang_transport_2 }}"
                                    data-tarif2="{{ $k->tarif_harian_total_2 }}"
                                    {{ $preKaryawanId == $k->id ? 'selected' : '' }}>
                                {{ $k->nama_karyawan }} ({{ $k->jabatan }} - {{ $k->departemen }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Jika locked/edit mode, sediakan select hidden untuk data attributes JavaScript --}}
                    @if(($isLockKaryawan || request('lock_karyawan') || isset($payroll)) && $targetEmp)
                    @php
                        $targetTerlambatInfo = $akumulasiTerlambatMap[$targetEmp->id] ?? null;
                        $tPotTerlambat = $targetTerlambatInfo ? $targetTerlambatInfo->total_potongan : 0;
                        $tKaliTerlambat = $targetTerlambatInfo ? $targetTerlambatInfo->total_kali : 0;
                    @endphp
                    <select id="selectKaryawanId" style="display:none;" onchange="updateMasterHarian(this)">
                        <option value="{{ $targetEmp->id }}"
                                data-gaji="{{ $targetEmp->gaji_pokok }}"
                                data-makan="{{ $targetEmp->uang_makan }}"
                                data-transport="{{ $targetEmp->uang_transport }}"
                                data-tarif="{{ $targetEmp->tarif_harian_total }}"
                                data-terlambat="{{ $tPotTerlambat }}"
                                data-terlambat-kali="{{ $tKaliTerlambat }}"
                                data-terlambat-json="{{ json_encode($keterlambatanRawMap[$targetEmp->id] ?? []) }}"
                                data-mulai="{{ $targetEmp->tanggal_mulai ? $targetEmp->tanggal_mulai->format('Y-m-d') : '' }}"
                                data-selesai="{{ $targetEmp->tanggal_selesai ? $targetEmp->tanggal_selesai->format('Y-m-d') : '' }}"
                                data-mulai2="{{ $targetEmp->tanggal_mulai_2 ? $targetEmp->tanggal_mulai_2->format('Y-m-d') : '' }}"
                                data-selesai2="{{ $targetEmp->tanggal_selesai_2 ? $targetEmp->tanggal_selesai_2->format('Y-m-d') : '' }}"
                                data-gaji2="{{ $targetEmp->gaji_pokok_2 }}"
                                data-makan2="{{ $targetEmp->uang_makan_2 }}"
                                data-transport2="{{ $targetEmp->uang_transport_2 }}"
                                data-tarif2="{{ $targetEmp->tarif_harian_total_2 }}"
                                selected>
                            {{ $targetEmp->nama_karyawan }}
                        </option>
                    </select>
                    @endif

                    {{-- PERIODE --}}
                    <div class="pg-field">
                        <label class="pg-label">Periode Penggajian</label>
                        <input type="text" class="pg-input pg-input-readonly"
                               value="{{ \App\Models\Penggajian::formatPeriode($target_periode) }}" readonly>
                    </div>

                    {{-- PILIH PERIODE A / B --}}
                    <div id="containerMultiPeriode" style="display:none;margin-bottom:16px;">
                        <label class="pg-label" style="margin-bottom:8px;">Pilih Periode Gaji untuk Slip Ini</label>
                        <div class="pg-grid-2">
                            <div class="pg-card-periode" id="cardPeriodeA" onclick="selectPeriodeOption('A')">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-size:11px;font-weight:700;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:20px;">Periode A</span>
                                    <span style="font-size:12px;font-weight:700;color:#059669;" id="lblTarifA">Rp 0/hr</span>
                                </div>
                                <div style="font-size:11px;color:#6b7280;margin-top:6px;" id="lblDatesA">-</div>
                            </div>
                            <div class="pg-card-periode" id="cardPeriodeB" onclick="selectPeriodeOption('B')">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-size:11px;font-weight:700;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;">Periode B</span>
                                    <span style="font-size:12px;font-weight:700;color:#059669;" id="lblTarifB">Rp 0/hr</span>
                                </div>
                                <div style="font-size:11px;color:#6b7280;margin-top:6px;" id="lblDatesB">-</div>
                            </div>
                        </div>
                    </div>

                    {{-- TANGGAL --}}
                    <div class="pg-grid-2">
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Tanggal Mulai Slip</label>
                            <input type="date" name="tanggal_mulai" id="inputTanggalMulai"
                                   value="{{ old('tanggal_mulai', isset($payroll) && $payroll->tanggal_mulai ? \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d') : '') }}"
                                   class="pg-input" onchange="syncTanggalHariKerja()">
                            <div class="pg-hint">Isi jika ini adalah slip untuk periode spesifik.</div>
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Tanggal Selesai Slip</label>
                            <input type="date" name="tanggal_selesai" id="inputTanggalSelesai"
                                   value="{{ old('tanggal_selesai', isset($payroll) && $payroll->tanggal_selesai ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d') : '') }}"
                                   class="pg-input" onchange="syncTanggalHariKerja()">
                            <div class="pg-hint">Batas akhir periode kerja untuk slip ini.</div>
                        </div>
                    </div>

                    {{-- ALERT BATAS GAJI --}}
                    <div id="alertBatasGaji" style="display:none;margin-top:12px;background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:12px 14px;font-size:12px;color:#92400e;">
                        <strong id="alertBatasGajiTitle">Peringatan!</strong><br>
                        <span id="alertBatasGajiMsg"></span>
                        <div style="margin-top:8px;">
                            <a href="{{ route('pengaturan-gaji.index') }}" target="_blank"
                               style="font-size:11px;background:#1e293b;color:#fff;padding:5px 12px;border-radius:6px;text-decoration:none;font-weight:600;">
                                &#128279; Buka Pengaturan Gaji
                            </a>
                        </div>
                    </div>

                    {{-- NOTE FLUKTUATIF --}}
                    <div id="noteFluktuatifGaji" style="display:none;margin-top:12px;background:#e0f2fe;border-radius:10px;padding:10px 14px;font-size:12px;color:#0369a1;"></div>

                    {{-- TARIF HARIAN MASTER --}}
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f3f4f6;">
                        <label class="pg-label" style="margin-bottom:10px;">Rincian Tarif Harian (dari Master Pengaturan Gaji)</label>
                        <div class="pg-grid-4">
                            <div>
                                <div style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;">Gaji Pokok</div>
                                <input type="text" id="displayGajiPokok" readonly class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0">
                            </div>
                            <div>
                                <div style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;">Uang Makan</div>
                                <input type="text" id="displayUangMakan" readonly class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0">
                            </div>
                            <div>
                                <div style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;">Transport</div>
                                <input type="text" id="displayUangTransport" readonly class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0">
                            </div>
                            <div>
                                <div style="font-size:10px;color:#059669;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Total / Hari</div>
                                <input type="text" id="displayTarifHarianTotal" readonly class="pg-input pg-input-rupiah" style="background:#ecfdf5;color:#059669;cursor:not-allowed;" value="Rp 0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== SECTION 2: PRESENSI & GAJI POKOK ===================== --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#ecfdf5;">&#128197;</div>
                    <span class="pg-section-title">Presensi &amp; Gaji Pokok</span>
                </div>
                <div class="pg-section-body">
                    <div class="pg-grid-2">
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Jumlah Hari Kerja <span class="req">*</span></label>
                            <input type="number" name="hari_kerja" id="inputHariKerja" min="0" step="0.5"
                                   value="{{ isset($payroll) ? $payroll->hari_kerja : 0 }}"
                                   required class="pg-input" oninput="hitungKalkulasiGaji()">
                            <div class="pg-hint">Terisi otomatis dari selisih tanggal mulai–selesai.</div>
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Gaji Utama (Hari Kerja × Tarif)</label>
                            <input type="text" id="calcGajiUtama" readonly
                                   class="pg-input pg-input-readonly pg-input-rupiah" value="Rp 0"
                                   style="font-size:16px;color:#111827;">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== SECTION 3: BONUS & LEMBUR ===================== --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#fefce8;">&#11088;</div>
                    <span class="pg-section-title">Bonus &amp; Lembur</span>
                </div>
                <div class="pg-section-body">
                    <div class="pg-grid-2">
                        <div class="pg-field">
                            <label class="pg-label">Jam Lembur <span style="font-size:10px;font-weight:400;text-transform:none;">(Rp 10.000 / jam)</span></label>
                            <input type="number" name="jam_lembur" id="inputJamLembur" min="0" step="0.5"
                                   value="{{ isset($payroll) ? $payroll->jam_lembur : 0 }}"
                                   class="pg-input" oninput="hitungKalkulasiGaji()">
                            <div class="pg-sub-calc" id="subLembur">Upah: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Banyak Target <span style="font-size:10px;font-weight:400;text-transform:none;">(Target × Tarif Harian)</span></label>
                            <input type="number" name="banyak_target" id="inputBanyakTarget" min="0" step="1"
                                   value="{{ isset($payroll) ? $payroll->banyak_target : 0 }}"
                                   class="pg-input" oninput="hitungKalkulasiGaji()">
                            <div class="pg-sub-calc" id="subTarget">Bonus: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Hadir Tanggal Merah <span style="font-size:10px;font-weight:400;text-transform:none;">(Merah × Tarif Harian)</span></label>
                            <input type="number" name="banyak_tanggal_merah" id="inputBanyakTanggalMerah" min="0" step="1"
                                   value="{{ isset($payroll) ? $payroll->banyak_tanggal_merah : 0 }}"
                                   class="pg-input" oninput="hitungKalkulasiGaji()">
                            <div class="pg-sub-calc" id="subMerah">Bonus: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Birthday Service <span style="font-size:10px;font-weight:400;text-transform:none;">(× Rp 5.000)</span></label>
                            <input type="number" name="banyak_birthday_service" id="inputBanyakBirthday" min="0" step="1"
                                   value="{{ isset($payroll) ? $payroll->banyak_birthday_service : 0 }}"
                                   class="pg-input" oninput="hitungKalkulasiGaji()">
                            <div class="pg-sub-calc" id="subBirthday">Bonus: Rp 0</div>
                        </div>
                    </div>
                    <div class="pg-field" style="margin-bottom:0;margin-top:4px;">
                        <label class="pg-label">Bonus Lain-lain (Rp)</label>
                        <input type="text" name="bonus_dll" id="inputBonusDll"
                               value="{{ isset($payroll) ? number_format($payroll->bonus_dll, 0, ',', '.') : '0' }}"
                               class="pg-input pg-input-rupiah input-rupiah" oninput="hitungKalkulasiGaji()">
                    </div>
                </div>
            </div>

            {{-- ===================== SECTION 4: POTONGAN ===================== --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#fff1f2;">&#9986;</div>
                    <span class="pg-section-title">Potongan &amp; Pengurangan</span>
                </div>
                <div class="pg-section-body">
                    <div class="pg-grid-2">
                        <div class="pg-field">
                            <label class="pg-label">
                                Denda Keterlambatan (Rp)
                                <a href="{{ route('keterlambatan.index', ['periode' => $target_periode]) }}" target="_blank"
                                   style="font-size:10px;color:#3b82f6;font-weight:600;text-transform:none;text-decoration:none;margin-left:4px;">
                                    &#9889; Lihat Rekap
                                </a>
                            </label>
                            <input type="text" name="potongan_terlambat" id="inputPotTerlambat"
                                   value="{{ isset($payroll) ? number_format($payroll->potongan_terlambat, 0, ',', '.') : '0' }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungKalkulasiGaji()">
                            <div class="pg-sub-calc" id="subTerlambat">Otomatis dari Rekap</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Kerusakan Inventaris (Rp)</label>
                            <input type="text" name="potongan_inventaris" id="inputPotInventaris"
                                   value="{{ isset($payroll) ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '0' }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungKalkulasiGaji()">
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Potongan Kasbon (Rp)</label>
                            <input type="text" name="potongan_kasbon" id="inputPotKasbon"
                                   value="{{ isset($payroll) ? number_format($payroll->potongan_kasbon, 0, ',', '.') : '0' }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungKalkulasiGaji()">
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Potongan Lain-lain (Rp)</label>
                            <input type="text" name="potongan_dll" id="inputPotDll"
                                   value="{{ isset($payroll) ? number_format($payroll->potongan_dll, 0, ',', '.') : '0' }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungKalkulasiGaji()">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== SUMMARY ===================== --}}
            <div class="pg-summary">
                <div class="pg-summary-grid">
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Total Pendapatan</div>
                        <div class="pg-summary-value" style="color:#059669;" id="sumTotalEarnings">Rp 0</div>
                    </div>
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Total Potongan</div>
                        <div class="pg-summary-value" style="color:#dc2626;" id="sumTotalDeductions">Rp 0</div>
                    </div>
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Gaji Bersih (THP)</div>
                        <div class="pg-summary-value" style="color:#7A4517;" id="sumTakeHomePay">Rp 0</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="pg-btn-save">
                &#10003; {{ isset($payroll) ? 'Perbaiki Data Gaji' : 'Simpan Data Gaji' }}
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

        function parseRupiahInput(elId) {
            let el = document.getElementById(elId);
            if (!el) return 0;
            let val = el.value.replace(/[^0-9]/g, '');
            return parseFloat(val) || 0;
        }

        function getPotonganTerlambatForSelectedRange(option, tglMulai, tglSelesai) {
            if (!option) return { total: 0, count: 0 };
            let rawJson = option.getAttribute('data-terlambat-json');
            if (!rawJson) {
                let defTot = parseFloat(option.getAttribute('data-terlambat')) || 0;
                let defCnt = parseInt(option.getAttribute('data-terlambat-kali')) || 0;
                return { total: defTot, count: defCnt };
            }
            let records = [];
            try {
                records = JSON.parse(rawJson);
            } catch(e) {
                records = [];
            }

            let total = 0;
            let count = 0;
            records.forEach(r => {
                let tgl = r.tanggal;
                if (tglMulai && tgl < tglMulai) return;
                if (tglSelesai && tgl > tglSelesai) return;
                total += parseFloat(r.potongan) || 0;
                count++;
            });
            return { total, count };
        }

        function updateMasterHarian(selectEl) {
            if (!selectEl || selectEl.selectedIndex < 0) return;
            let option = selectEl.options[selectEl.selectedIndex];
            if (!option.value) return;

            let gaji2Val = option.getAttribute('data-gaji2');
            let containerMulti = document.getElementById('containerMultiPeriode');

            if (gaji2Val !== null && gaji2Val !== '') {
                if (containerMulti) {
                    containerMulti.classList.remove('d-none');
                    containerMulti.style.display = 'block';
                }

                let p1M = option.getAttribute('data-mulai') || '';
                let p1S = option.getAttribute('data-selesai') || '';
                let t1 = parseFloat(option.getAttribute('data-tarif')) || 0;

                let p2M = option.getAttribute('data-mulai2') || '';
                let p2S = option.getAttribute('data-selesai2') || '';
                let t2 = parseFloat(option.getAttribute('data-tarif2')) || 0;

                document.getElementById('lblTarifA').innerText = formatRupiah(t1) + '/hr';
                document.getElementById('lblDatesA').innerText = 'Tgl: ' + formatDateId(p1M) + ' s/d ' + formatDateId(p1S);

                document.getElementById('lblTarifB').innerText = formatRupiah(t2) + '/hr';
                document.getElementById('lblDatesB').innerText = 'Tgl: ' + formatDateId(p2M) + ' s/d ' + formatDateId(p2S);
            } else {
                if (containerMulti) {
                    containerMulti.classList.add('d-none');
                    containerMulti.style.display = 'none';
                }
            }

            @if(!isset($payroll))
            // Jika ada lock_karyawan (menambah periode kedua) dan ada Periode B, defaultkan ke Periode B jika Periode A sudah lewat
            let p2Mulai = option.getAttribute('data-mulai2') || '';
            let p2Selesai = option.getAttribute('data-selesai2') || '';
            let tglMulai1 = option.getAttribute('data-mulai') || '';
            let tglSelesai1 = option.getAttribute('data-selesai') || '';

            @if(request('lock_karyawan'))
                if (gaji2Val !== null && gaji2Val !== '' && p2Mulai) {
                    document.getElementById('inputTanggalMulai').value = p2Mulai;
                    document.getElementById('inputTanggalSelesai').value = p2Selesai;
                    highlightCardOption('B');
                } else if (tglMulai1) {
                    document.getElementById('inputTanggalMulai').value = tglMulai1;
                    document.getElementById('inputTanggalSelesai').value = tglSelesai1;
                    highlightCardOption('A');
                }
            @else
                if (tglMulai1 && !document.getElementById('inputTanggalMulai').value) {
                    document.getElementById('inputTanggalMulai').value = tglMulai1;
                    document.getElementById('inputTanggalSelesai').value = tglSelesai1;
                    highlightCardOption('A');
                }
            @endif
            @endif

            syncTanggalHariKerja();
        }

        function selectPeriodeOption(type) {
            const selectEl = document.getElementById('selectKaryawanId');
            if (!selectEl || selectEl.selectedIndex < 0) return;
            const option = selectEl.options[selectEl.selectedIndex];

            if (type === 'A') {
                let tMulai = option.getAttribute('data-mulai') || '';
                let tSelesai = option.getAttribute('data-selesai') || '';
                document.getElementById('inputTanggalMulai').value = tMulai;
                document.getElementById('inputTanggalSelesai').value = tSelesai;
                highlightCardOption('A');
            } else if (type === 'B') {
                let tMulai = option.getAttribute('data-mulai2') || '';
                let tSelesai = option.getAttribute('data-selesai2') || '';
                document.getElementById('inputTanggalMulai').value = tMulai;
                document.getElementById('inputTanggalSelesai').value = tSelesai;
                highlightCardOption('B');
            }

            syncTanggalHariKerja();
        }

        function highlightCardOption(type) {
            const cardA = document.getElementById('cardPeriodeA');
            const cardB = document.getElementById('cardPeriodeB');
            if (!cardA || !cardB) return;

            if (type === 'A') {
                cardA.style.borderColor = '#0284c7';
                cardA.style.backgroundColor = '#f0f9ff';
                cardB.style.borderColor = '#e2e8f0';
                cardB.style.backgroundColor = '#ffffff';
            } else {
                cardB.style.borderColor = '#eab308';
                cardB.style.backgroundColor = '#fefce8';
                cardA.style.borderColor = '#e2e8f0';
                cardA.style.backgroundColor = '#ffffff';
            }
        }

        function syncTanggalHariKerja() {
            const selectEl = document.getElementById('selectKaryawanId');
            const option = selectEl && selectEl.selectedIndex >= 0 ? selectEl.options[selectEl.selectedIndex] : null;

            const tglMulaiInput = document.getElementById('inputTanggalMulai').value;
            const tglSelesaiInput = document.getElementById('inputTanggalSelesai').value;

            // 1. Integrasi otomatis jumlah hari kerja jika kedua tanggal terisi
            if (tglMulaiInput && tglSelesaiInput) {
                const d1 = new Date(tglMulaiInput);
                const d2 = new Date(tglSelesaiInput);
                if (d2 >= d1) {
                    // Hitung selisih hari inklusif (misal: 1 s/d 25 = 25 hari)
                    const diffTime = Math.abs(d2 - d1);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    document.getElementById('inputHariKerja').value = diffDays;
                }
            }

            // 2. Sinkronkan potongan keterlambatan khusus periode tanggal ini
            if (option) {
                let terlambatInfo = getPotonganTerlambatForSelectedRange(option, tglMulaiInput, tglSelesaiInput);
                @if(!isset($payroll))
                document.getElementById('inputPotTerlambat').value = formatRupiah(terlambatInfo.total);
                @endif
                let subTerlambat = document.getElementById('subTerlambat');
                if (subTerlambat) {
                    if (terlambatInfo.count > 0) {
                        subTerlambat.innerHTML = `Terdeteksi <strong>${terlambatInfo.count} kali</strong> terlambat (${formatRupiah(terlambatInfo.total)}) pada rentang periode ini`;
                    } else {
                        subTerlambat.innerHTML = `Tidak ada keterlambatan pada rentang periode ini (Rp 0)`;
                    }
                }
            }

            // 3. Evaluasi Tarif & Peringatan Batas Pengaturan Gaji
            checkBatasGaji(option, tglMulaiInput, tglSelesaiInput);

            hitungKalkulasiGaji();
        }

        function checkBatasGaji(option, tglMulaiInput, tglSelesaiInput) {
            const alertBox = document.getElementById('alertBatasGaji');
            const alertTitle = document.getElementById('alertBatasGajiTitle');
            const alertMsg = document.getElementById('alertBatasGajiMsg');
            const noteBox = document.getElementById('noteFluktuatifGaji');

            if (!option || !option.value) {
                if (alertBox) alertBox.classList.add('d-none');
                if (noteBox) noteBox.classList.add('d-none');
                return;
            }

            const p1Mulai = option.getAttribute('data-mulai') || '';
            const p1Selesai = option.getAttribute('data-selesai') || '';
            const p2Mulai = option.getAttribute('data-mulai2') || '';
            const p2Selesai = option.getAttribute('data-selesai2') || '';
            const gaji2Val = option.getAttribute('data-gaji2');

            const gp1 = parseFloat(option.getAttribute('data-gaji')) || 0;
            const um1 = parseFloat(option.getAttribute('data-makan')) || 0;
            const ut1 = parseFloat(option.getAttribute('data-transport')) || 0;
            const tarif1 = gp1 + um1 + ut1;

            const hasP2 = (gaji2Val !== null && gaji2Val !== '');
            const gp2 = parseFloat(option.getAttribute('data-gaji2')) || 0;
            const um2 = parseFloat(option.getAttribute('data-makan2')) || 0;
            const ut2 = parseFloat(option.getAttribute('data-transport2')) || 0;
            const tarif2 = gp2 + um2 + ut2;

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
                    noteBox.classList.remove('d-none');
                    noteBox.innerHTML = `<i class="bi bi-info-circle-fill me-1"></i> Perhitungan Gaji Gabungan 2 Periode: <strong>${n1} hari @ ${formatRupiah(tarif1)} (P1)</strong> + <strong>${n2} hari @ ${formatRupiah(tarif2)} (P2)</strong>. Rata-rata Tarif Total: <strong>${formatRupiah(currentTarifHarianTotal)}/hari</strong>`;
                } else {
                    noteBox.classList.add('d-none');
                }
            }

            // Validasi jika tanggal selesai slip melebihi tanggal selesai pengaturan gaji tanpa adanya periode lanjutan
            if (tglSelesaiInput && p1Selesai) {
                if (tglSelesaiInput > p1Selesai && (!p2Mulai || !hasP2)) {
                    if (alertBox) {
                        alertBox.classList.remove('d-none');
                        alertTitle.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Perhatian: Periode Gaji Karyawan Melebihi Batas Pengaturan!`;
                        alertMsg.innerHTML = `Di menu <strong>Pengaturan Gaji</strong>, masa berlaku gaji karyawan ini hanya sampai tanggal <strong>${formatDateId(p1Selesai)}</strong>. Sedangkan Anda menginput slip hingga tanggal <strong>${formatDateId(tglSelesaiInput)}</strong>.<br>Silakan atur Periode Gaji 2 / sesuaikan gaji karyawan di menu <strong>Pengaturan Gaji</strong> terlebih dahulu.`;
                    }
                    return;
                } else if (p2Selesai && tglSelesaiInput > p2Selesai) {
                    if (alertBox) {
                        alertBox.classList.remove('d-none');
                        alertTitle.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Perhatian: Periode Gaji Karyawan Melebihi Batas Pengaturan Lanjutan (P2)!`;
                        alertMsg.innerHTML = `Masa berlaku gaji periode 2 hanya sampai tanggal <strong>${formatDateId(p2Selesai)}</strong>, sedangkan slip sampai tanggal <strong>${formatDateId(tglSelesaiInput)}</strong>. Silakan perbarui Pengaturan Gaji terlebih dahulu.`;
                    }
                    return;
                }
            }

            if (alertBox) alertBox.classList.add('d-none');
        }

        function formatDateId(dateStr) {
            if (!dateStr) return '-';
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }
            return dateStr;
        }


        function hitungKalkulasiGaji() {
            // 1. Gaji Utama
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

            // 2. Bonus & Lembur
            let jamLembur = parseFloat(document.getElementById('inputJamLembur').value) || 0;
            let upahLembur = jamLembur * 10000;
            document.getElementById('subLembur').innerText = 'Upah: ' + formatRupiah(upahLembur);

            let banyakTarget = parseInt(document.getElementById('inputBanyakTarget').value) || 0;
            let bonusTarget = banyakTarget * currentTarifHarianTotal;
            document.getElementById('subTarget').innerText = 'Bonus: ' + formatRupiah(bonusTarget);

            let banyakMerah = parseInt(document.getElementById('inputBanyakTanggalMerah').value) || 0;
            let bonusMerah = banyakMerah * currentTarifHarianTotal;
            document.getElementById('subMerah').innerText = 'Bonus: ' + formatRupiah(bonusMerah);

            let banyakBirthday = parseInt(document.getElementById('inputBanyakBirthday').value) || 0;
            let bonusBirthday = banyakBirthday * 5000;
            document.getElementById('subBirthday').innerText = 'Bonus: ' + formatRupiah(bonusBirthday);

            let bonusDll = parseRupiahInput('inputBonusDll');

            let totalEarnings = gajiUtama + upahLembur + bonusTarget + bonusMerah + bonusBirthday + bonusDll;

            // 3. Deductions
            let potTerlambat  = parseRupiahInput('inputPotTerlambat');
            let potInventaris = parseRupiahInput('inputPotInventaris');
            let potKasbon     = parseRupiahInput('inputPotKasbon');
            let potDll        = parseRupiahInput('inputPotDll');

            let totalDeductions = potTerlambat + potInventaris + potKasbon + potDll;

            // 4. Take Home Pay
            let takeHomePay = totalEarnings - totalDeductions;

            // Display Summary
            document.getElementById('sumTotalEarnings').innerText = formatRupiah(totalEarnings);
            document.getElementById('sumTotalDeductions').innerText = formatRupiah(totalDeductions);
            document.getElementById('sumTakeHomePay').innerText = formatRupiah(takeHomePay);
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
            // Masking Rupiah pada inputan nominal
            const rupiahInputs = document.querySelectorAll('.input-rupiah:not([readonly])');
            rupiahInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let raw = this.value.replace(/[^0-9]/g, '');
                    if (raw) {
                        this.value = formatRupiah(parseFloat(raw));
                    } else {
                        this.value = 'Rp 0';
                    }
                    hitungKalkulasiGaji();
                });

                input.addEventListener('focus', function() {
                    if (this.value === 'Rp 0') this.value = '';
                });

                input.addEventListener('blur', function() {
                    if (!this.value) this.value = 'Rp 0';
                });
            });

            // Trigger awal jika sudah terpilih (misal saat Edit atau Create)
            const selectKaryawan = document.getElementById('selectKaryawanId');
            if (selectKaryawan && selectKaryawan.value) {
                updateMasterHarian(selectKaryawan);
            }

            // Unmask Rupiah sebelum submit form
            document.getElementById('formPayroll').addEventListener('submit', function() {
                rupiahInputs.forEach(input => {
                    input.value = input.value.replace(/[^0-9]/g, '') || '0';
                });
            });
        });
    </script>
    @endpush
</x-app-layout>