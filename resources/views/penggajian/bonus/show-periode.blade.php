<x-app-layout>
    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 mb-3">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-3 flex-wrap">
                        <a href="{{ route('penggajian.bonus.index', ['outlet' => $selectedOutlet]) }}"
                           style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: background .15s;"
                           onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'"
                           title="Kembali">
                            <span>&larr;</span> Kembali
                        </a>
                        <div>
                            <h1 class="text-sm sm:text-base font-extrabold text-slate-900 tracking-tight leading-tight inline">
                                Kelola Bonus &amp; Lembur
                            </h1>
                            <span class="text-xs text-slate-600 font-semibold ms-2">
                                Periode <strong class="text-slate-900">{{ \App\Models\Penggajian::formatPeriode($targetPeriode) }}</strong>
                                &middot; Outlet <strong class="text-slate-900">{{ $selectedOutlet }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span style="font-size: 12px; font-weight: 800; background-color: #fffbf5; border: 1.5px solid #fcd34d; padding: 6px 14px; border-radius: 8px; color: #78350f; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            Total Bonus: Rp {{ number_format($payrolls->sum('total_bonus'), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-2.5 rounded-xl mb-3 text-xs font-bold flex items-center gap-2 shadow-sm">
                <span class="text-emerald-600 text-sm">&#10003;</span> {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-2.5 rounded-xl mb-3 text-xs font-bold flex items-center gap-2 shadow-sm">
                <span class="text-rose-600 text-sm">&#9888;</span> {{ session('error') }}
            </div>
            @endif

            @php
                $depts = $payrolls->pluck('karyawan.departemen')->filter()->unique()->sort();
                $jbtns = $payrolls->pluck('karyawan.jabatan')->filter()->unique()->sort();
            @endphp

            {{-- TOOLBAR FILTER & PENCARIAN --}}
            <div class="flex justify-between items-center gap-2.5 mb-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap flex-1">
                    {{-- Search Input --}}
                    <div class="relative">
                        <input type="text" id="searchKaryawan" onkeyup="filterKaryawanTable()"
                               placeholder="&#128269; Cari nama karyawan..."
                               style="width: 220px; padding: 6px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none;">
                    </div>

                    {{-- Filter Departemen --}}
                    <select id="filterDepartemen" onchange="filterKaryawanTable()"
                            style="padding: 6px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;">
                        <option value="">Semua Departemen</option>
                        @foreach($depts as $dept)
                            <option value="{{ strtolower($dept) }}">{{ $dept }}</option>
                        @endforeach
                    </select>

                    {{-- Filter Jabatan --}}
                    <select id="filterJabatan" onchange="filterKaryawanTable()"
                            style="padding: 6px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;">
                        <option value="">Semua Jabatan</option>
                        @foreach($jbtns as $jbtn)
                            <option value="{{ strtolower($jbtn) }}">{{ $jbtn }}</option>
                        @endforeach
                    </select>

                    <button type="button" onclick="resetTableFilter()"
                            style="padding: 6px 14px; background-color: #f1f5f9; color: #334155; font-weight: 800; border-radius: 8px; font-size: 12px; border: 1.5px solid #cbd5e1; cursor: pointer; transition: background .15s;"
                            onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                        Reset
                    </button>
                </div>

                <div class="text-xs text-slate-700 font-bold bg-white border border-slate-200 shadow-sm px-3 py-1.5 rounded-lg">
                    <strong class="text-slate-900 font-black" id="visibleCount">{{ count($payrolls) }}</strong> karyawan terdaftar
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-[11px] font-bold text-slate-700 uppercase tracking-wider bg-slate-100 border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-2.5 w-10 text-center">#</th>
                                <th class="px-4 py-2.5 min-w-[240px]">Karyawan</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Lembur (Jam)</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Target</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Tgl Merah</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Birthday</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Bonus Lain</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Total Bonus</th>
                                <th class="px-3.5 py-2.5 text-center w-28 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($payrolls as $index => $payroll)
                            <tr class="payroll-row hover:bg-slate-50/80 transition-colors"
                                data-nama="{{ strtolower($payroll->karyawan->nama_karyawan ?? '') }}"
                                data-departemen="{{ strtolower($payroll->karyawan->departemen ?? '') }}"
                                data-jabatan="{{ strtolower($payroll->karyawan->jabatan ?? '') }}">
                                <td class="px-3.5 py-2.5 text-center text-xs text-slate-500 font-bold">{{ $index + 1 }}</td>
                                <td class="px-4 py-2.5 min-w-[240px]">
                                    <div class="font-extrabold text-slate-900 text-sm nama-karyawan leading-snug">
                                        {{ $payroll->karyawan->nama_karyawan ?? '-' }}
                                    </div>
                                    <div class="text-[11px] text-slate-600 font-medium mt-0.5 flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-800">{{ $payroll->karyawan->jabatan ?? '-' }}</span>
                                        @if($payroll->karyawan->departemen)
                                            <span class="bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded text-[10px] font-bold text-slate-700">{{ $payroll->karyawan->departemen }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->jam_lembur > 0)
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->lembur, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->jam_lembur }} jam</div>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->bonus_target > 0)
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_target, 0, ',', '.') }}</div>
                                        @if(($payroll->satuan_gaji ?? 'Harian') === 'Harian')
                                            <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->banyak_target }}x target</div>
                                        @else
                                            <div class="text-[10.5px] text-amber-800 font-bold max-w-[140px] truncate ml-auto" title="{{ $payroll->catatan_bonus_target ?: 'Manual' }}">
                                                {{ $payroll->catatan_bonus_target ?: 'Rincian Manual' }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->bonus_tanggal_merah > 0)
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_tanggal_merah, 0, ',', '.') }}</div>
                                        @if(($payroll->satuan_gaji ?? 'Harian') === 'Harian')
                                            <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->banyak_tanggal_merah }}x hadir</div>
                                        @else
                                            <div class="text-[10.5px] text-amber-800 font-bold max-w-[140px] truncate ml-auto" title="{{ $payroll->catatan_bonus_tanggal_merah ?: 'Manual' }}">
                                                {{ $payroll->catatan_bonus_tanggal_merah ?: 'Rincian Manual' }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->banyak_birthday_service > 0)
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_birthday, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->banyak_birthday_service }}x</div>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->bonus_dll > 0)
                                        <span class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_dll, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-black text-amber-800 text-xs whitespace-nowrap">
                                    Rp {{ number_format($payroll->total_bonus, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    @if(!$payroll->is_paid)
                                        <button type="button"
                                                onclick="openModalEditBonus({{ json_encode([
                                                    'id' => $payroll->id,
                                                    'nama' => $payroll->karyawan->nama_karyawan ?? '-',
                                                    'jabatan' => $payroll->karyawan->jabatan ?? '-',
                                                    'departemen' => $payroll->karyawan->departemen ?? '-',
                                                    'satuan_gaji' => $payroll->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian',
                                                    'hari_kerja' => $payroll->hari_kerja ?? 0,
                                                    'tarif_harian' => $payroll->tarif_harian_total ?? 0,
                                                    'jam_lembur' => $payroll->jam_lembur ?? 0,
                                                    'banyak_target' => $payroll->banyak_target ?? 0,
                                                    'bonus_target' => $payroll->bonus_target ?? 0,
                                                    'catatan_bonus_target' => $payroll->catatan_bonus_target ?? '',
                                                    'banyak_tanggal_merah' => $payroll->banyak_tanggal_merah ?? 0,
                                                    'bonus_tanggal_merah' => $payroll->bonus_tanggal_merah ?? 0,
                                                    'catatan_bonus_tanggal_merah' => $payroll->catatan_bonus_tanggal_merah ?? '',
                                                    'banyak_birthday_service' => $payroll->banyak_birthday_service ?? 0,
                                                    'bonus_dll' => $payroll->bonus_dll ?? 0,
                                                    'update_url' => route('penggajian.bonus.update', $payroll->id),
                                                ]) }})"
                                                style="background-color: #fffbf5; border: 1.5px solid #fcd34d; color: #78350f; font-weight: 800; font-size: 11.5px; padding: 5px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all .15s;"
                                                onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fffbf5'"
                                                title="Edit Komponen Bonus via Pop-up">
                                            <span>&#9999;</span> Edit
                                        </button>
                                    @else
                                        <span class="text-[10.5px] text-slate-400 italic font-semibold">Terkunci</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-500 font-medium">
                                    Belum ada data karyawan di periode ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- POPUP MODAL: EDIT BONUS & LEMBUR KARYAWAN --}}
    {{-- ========================================================================= --}}
    <div id="modalEditBonus" style="display: none;">
        {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
             onclick="closeModalEditBonus()"></div>

        {{-- MODAL CONTAINER CENTERED --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
            <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden;">
                
                {{-- MODAL HEADER --}}
                <div style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                                Edit Bonus &amp; Lembur Karyawan
                            </h3>
                            <span style="font-size: 10.5px; font-weight: 800; background: #fef3c7; color: #78350f; border: 1px solid #fcd34d; border-radius: 20px; padding: 2px 8px;">
                                Outlet {{ $selectedOutlet }}
                            </span>
                            <span style="font-size: 10.5px; font-weight: 700; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 20px; padding: 2px 8px;">
                                {{ \App\Models\Penggajian::formatPeriode($targetPeriode) }}
                            </span>
                        </div>
                        <p style="font-size: 11px; color: #475569; font-weight: 600; margin: 2px 0 0 0;">
                            Sesuaikan variabel lembur, target, tanggal merah, birthday, dan bonus lainnya.
                        </p>
                    </div>
                    <button type="button" onclick="closeModalEditBonus()"
                            style="background: none; border: none; color: #475569; font-size: 24px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0 4px;"
                            onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#475569'">
                        &times;
                    </button>
                </div>

                {{-- MODAL BODY (Scrollable) --}}
                <div style="padding: 16px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px;">
                    
                    {{-- INFO KARYAWAN CARD --}}
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <div class="flex items-center gap-2">
                                <span style="font-size: 14px; font-weight: 800; color: #0f172a;" id="modalBonusNama">-</span>
                                <span id="modalBonusSatuanBadge" style="font-size: 10px; font-weight: 800; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; border-radius: 6px; padding: 1px 6px;">Harian</span>
                            </div>
                            <div style="font-size: 11.5px; color: #7A4517; font-weight: 700; margin-top: 1px;" id="modalBonusSub">-</div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">
                                Hari Kerja: <strong class="text-slate-800" id="modalBonusHariKerja">0 Hari</strong>
                            </div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px 12px; text-align: right;">
                            <div style="font-size: 9.5px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.3px;" id="modalBonusTarifLabel">Tarif Harian Terhitung</div>
                            <div style="font-size: 14px; font-weight: 800; color: #059669; margin-top: 1px;" id="modalBonusTarifHarian">Rp 0/hari</div>
                        </div>
                    </div>

                    {{-- FORM INPUTS --}}
                    <form action="" method="POST" id="formEditBonusModal">
                        @csrf
                        @method('PUT')

                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff;">
                            <div style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                                <span>&#11088;</span> Komponen Variabel Bonus &amp; Lembur
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                {{-- 1. LEMBUR --}}
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Jam Lembur <span style="font-size: 10px; color: #64748b; text-transform: none;">(Rp 10.000 / jam)</span>
                                    </label>
                                    <input type="number" name="jam_lembur" id="mInputJamLembur" min="0" step="0.5"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                           oninput="recalcModalBonus()">
                                    <div style="font-size: 10.5px; color: #64748b; text-align: right; margin-top: 2px; font-weight: 600;" id="mSubLembur">Upah: Rp 0</div>
                                </div>

                                {{-- 2. TARGET (HARIAN vs BULANAN/PER JAM) --}}
                                <div>
                                    {{-- Wrapper Harian --}}
                                    <div id="wrapperTargetHarian">
                                        <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                            Banyak Target <span style="font-size: 10px; color: #64748b; text-transform: none;">(&times; Tarif Harian)</span>
                                        </label>
                                        <input type="number" name="banyak_target" id="mInputBanyakTarget" min="0" step="1"
                                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                               oninput="recalcModalBonus()">
                                        <div style="font-size: 10.5px; color: #64748b; text-align: right; margin-top: 2px; font-weight: 600;" id="mSubTarget">Bonus: Rp 0</div>
                                    </div>

                                    {{-- Wrapper Non-Harian (Bulanan / Per Jam) --}}
                                    <div id="wrapperTargetNonHarian" style="display: none;">
                                        <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                            Bonus Target (Rp)
                                        </label>
                                        <input type="text" name="manual_bonus_target" id="mInputManualBonusTarget"
                                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-amber-500/20 modal-rupiah-bonus"
                                               oninput="recalcModalBonus()" placeholder="0">
                                        <div style="margin-top: 4px;">
                                            <label style="display: block; font-size: 10px; font-weight: 700; color: #64748b; margin-bottom: 2px;">
                                                Rincian / Keterangan Target
                                            </label>
                                            <input type="text" name="catatan_bonus_target" id="mInputCatatanBonusTarget"
                                                   class="w-full border border-slate-300 rounded-lg px-2.5 py-1 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                                   placeholder="Contoh: Pencapaian target 120%">
                                        </div>
                                    </div>
                                </div>

                                {{-- 3. TANGGAL MERAH (HARIAN vs BULANAN/PER JAM) --}}
                                <div>
                                    {{-- Wrapper Harian --}}
                                    <div id="wrapperMerahHarian">
                                        <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                            Hadir Tgl Merah <span style="font-size: 10px; color: #64748b; text-transform: none;">(&times; Tarif Harian)</span>
                                        </label>
                                        <input type="number" name="banyak_tanggal_merah" id="mInputBanyakTanggalMerah" min="0" step="1"
                                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                               oninput="recalcModalBonus()">
                                        <div style="font-size: 10.5px; color: #64748b; text-align: right; margin-top: 2px; font-weight: 600;" id="mSubMerah">Bonus: Rp 0</div>
                                    </div>

                                    {{-- Wrapper Non-Harian (Bulanan / Per Jam) --}}
                                    <div id="wrapperMerahNonHarian" style="display: none;">
                                        <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                            Bonus Tgl Merah (Rp)
                                        </label>
                                        <input type="text" name="manual_bonus_tanggal_merah" id="mInputManualBonusMerah"
                                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-amber-500/20 modal-rupiah-bonus"
                                               oninput="recalcModalBonus()" placeholder="0">
                                        <div style="margin-top: 4px;">
                                            <label style="display: block; font-size: 10px; font-weight: 700; color: #64748b; margin-bottom: 2px;">
                                                Rincian / Keterangan Tgl Merah
                                            </label>
                                            <input type="text" name="catatan_bonus_tanggal_merah" id="mInputCatatanBonusMerah"
                                                   class="w-full border border-slate-300 rounded-lg px-2.5 py-1 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                                   placeholder="Contoh: Lembur libur nasional 1 hari">
                                        </div>
                                    </div>
                                </div>

                                {{-- 4. BIRTHDAY SERVICE --}}
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Birthday Service <span style="font-size: 10px; color: #64748b; text-transform: none;">(&times; Rp 5.000)</span>
                                    </label>
                                    <input type="number" name="banyak_birthday_service" id="mInputBanyakBirthday" min="0" step="1"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                           oninput="recalcModalBonus()">
                                    <div style="font-size: 10.5px; color: #64748b; text-align: right; margin-top: 2px; font-weight: 600;" id="mSubBirthday">Bonus: Rp 0</div>
                                </div>

                                {{-- 5. BONUS LAIN-LAIN --}}
                                <div style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Bonus Lain-lain (Rp)
                                    </label>
                                    <input type="text" name="bonus_dll" id="mInputBonusDll"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-amber-500/20 modal-rupiah-bonus"
                                           oninput="recalcModalBonus()">
                                </div>
                            </div>
                        </div>

                        {{-- SUMMARY BONUS --}}
                        <div style="background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%); border: 1.5px solid #fcd34d; border-radius: 12px; padding: 12px 16px; margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                            <div style="text-align: center;">
                                <div style="font-size: 10px; color: #92400e; font-weight: 700; text-transform: uppercase;">Upah Lembur</div>
                                <div style="font-size: 13px; font-weight: 800; margin-top: 2px; color: #b45309;" id="mSumUpahLembur">Rp 0</div>
                            </div>
                            <div style="text-align: center; border-left: 1px solid #fcd34d; border-right: 1px solid #fcd34d;">
                                <div style="font-size: 10px; color: #92400e; font-weight: 700; text-transform: uppercase;">Bonus Kinerja</div>
                                <div style="font-size: 13px; font-weight: 800; margin-top: 2px; color: #b45309;" id="mSumBonusKinerja">Rp 0</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 10px; color: #92400e; font-weight: 800; text-transform: uppercase;">Total Bonus</div>
                                <div style="font-size: 14px; font-weight: 900; margin-top: 2px; color: #7A4517;" id="mSumTotalBonus">Rp 0</div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- MODAL FOOTER --}}
                <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-shrink: 0;">
                    <button type="button" onclick="closeModalEditBonus()"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                        <span>&times;</span> Batal
                    </button>
                    <button type="submit" form="formEditBonusModal"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 18px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #7A4517; border: none; cursor: pointer; transition: all .15s; box-shadow: 0 2px 4px rgba(122,69,23,0.25);"
                            onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                        <span style="font-size: 14px;">&#10003;</span> Simpan Data Bonus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentModalTarifHarian = 0;
        let currentModalSatuanGaji = 'Harian';

        function formatRupiahJs(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        function parseRupiahVal(val) {
            if (!val) return 0;
            let clean = String(val).replace(/[^0-9]/g, '');
            return parseFloat(clean) || 0;
        }

        function openModalEditBonus(data) {
            currentModalTarifHarian = parseFloat(data.tarif_harian) || 0;
            currentModalSatuanGaji = data.satuan_gaji || 'Harian';
            
            document.getElementById('modalBonusNama').textContent = data.nama;
            document.getElementById('modalBonusSatuanBadge').textContent = currentModalSatuanGaji;
            document.getElementById('modalBonusSub').textContent = (data.jabatan || '-') + ' · ' + (data.departemen || '-');
            document.getElementById('modalBonusHariKerja').textContent = (data.hari_kerja || 0) + ' Hari';

            const tarifLabel = document.getElementById('modalBonusTarifLabel');
            if (currentModalSatuanGaji === 'Bulanan') {
                tarifLabel.textContent = 'Gaji Bulanan Master';
                document.getElementById('modalBonusTarifHarian').textContent = formatRupiahJs(currentModalTarifHarian) + '/bulan';
            } else if (currentModalSatuanGaji === 'Per Jam') {
                tarifLabel.textContent = 'Upah Per Jam Master';
                document.getElementById('modalBonusTarifHarian').textContent = formatRupiahJs(currentModalTarifHarian) + '/jam';
            } else {
                tarifLabel.textContent = 'Tarif Harian Terhitung';
                document.getElementById('modalBonusTarifHarian').textContent = formatRupiahJs(currentModalTarifHarian) + '/hari';
            }

            // Atur tampilan form sesuai Satuan Gaji (Harian vs Non-Harian)
            if (currentModalSatuanGaji === 'Harian') {
                document.getElementById('wrapperTargetHarian').style.display = 'block';
                document.getElementById('wrapperTargetNonHarian').style.display = 'none';
                document.getElementById('wrapperMerahHarian').style.display = 'block';
                document.getElementById('wrapperMerahNonHarian').style.display = 'none';

                document.getElementById('mInputBanyakTarget').value = data.banyak_target || 0;
                document.getElementById('mInputBanyakTanggalMerah').value = data.banyak_tanggal_merah || 0;
            } else {
                document.getElementById('wrapperTargetHarian').style.display = 'none';
                document.getElementById('wrapperTargetNonHarian').style.display = 'block';
                document.getElementById('wrapperMerahHarian').style.display = 'none';
                document.getElementById('wrapperMerahNonHarian').style.display = 'block';

                let bTarget = parseFloat(data.bonus_target) || 0;
                document.getElementById('mInputManualBonusTarget').value = bTarget ? Math.round(bTarget).toLocaleString('id-ID') : '0';
                document.getElementById('mInputCatatanBonusTarget').value = data.catatan_bonus_target || '';

                let bMerah = parseFloat(data.bonus_tanggal_merah) || 0;
                document.getElementById('mInputManualBonusMerah').value = bMerah ? Math.round(bMerah).toLocaleString('id-ID') : '0';
                document.getElementById('mInputCatatanBonusMerah').value = data.catatan_bonus_tanggal_merah || '';
            }

            document.getElementById('mInputJamLembur').value = data.jam_lembur || 0;
            document.getElementById('mInputBanyakBirthday').value = data.banyak_birthday_service || 0;
            
            let bDll = parseFloat(data.bonus_dll) || 0;
            document.getElementById('mInputBonusDll').value = bDll ? Math.round(bDll).toLocaleString('id-ID') : '0';

            document.getElementById('formEditBonusModal').action = data.update_url;

            recalcModalBonus();

            document.getElementById('modalEditBonus').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModalEditBonus() {
            document.getElementById('modalEditBonus').style.display = 'none';
            document.body.style.overflow = '';
        }

        function recalcModalBonus() {
            let jamLembur = parseFloat(document.getElementById('mInputJamLembur').value) || 0;
            let upahLembur = jamLembur * 10000;
            document.getElementById('mSubLembur').innerText = 'Upah: ' + formatRupiahJs(upahLembur);

            let bonusTarget = 0;
            let bonusMerah = 0;

            if (currentModalSatuanGaji === 'Harian') {
                let banyakTarget = parseInt(document.getElementById('mInputBanyakTarget').value) || 0;
                bonusTarget = banyakTarget * currentModalTarifHarian;
                document.getElementById('mSubTarget').innerText = 'Bonus: ' + formatRupiahJs(bonusTarget);

                let banyakMerah = parseInt(document.getElementById('mInputBanyakTanggalMerah').value) || 0;
                bonusMerah = banyakMerah * currentModalTarifHarian;
                document.getElementById('mSubMerah').innerText = 'Bonus: ' + formatRupiahJs(bonusMerah);
            } else {
                bonusTarget = parseRupiahVal(document.getElementById('mInputManualBonusTarget').value);
                bonusMerah = parseRupiahVal(document.getElementById('mInputManualBonusMerah').value);
            }

            let banyakBirthday = parseInt(document.getElementById('mInputBanyakBirthday').value) || 0;
            let bonusBirthday = banyakBirthday * 5000;
            document.getElementById('mSubBirthday').innerText = 'Bonus: ' + formatRupiahJs(bonusBirthday);

            let bonusDll = parseRupiahVal(document.getElementById('mInputBonusDll').value);

            let bonusKinerja = bonusTarget + bonusMerah + bonusBirthday + bonusDll;
            let totalBonus = upahLembur + bonusKinerja;

            document.getElementById('mSumUpahLembur').innerText = formatRupiahJs(upahLembur);
            document.getElementById('mSumBonusKinerja').innerText = formatRupiahJs(bonusKinerja);
            document.getElementById('mSumTotalBonus').innerText = formatRupiahJs(totalBonus);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const rupiahInputs = document.querySelectorAll('.modal-rupiah-bonus');
            rupiahInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let raw = this.value.replace(/[^0-9]/g, '');
                    if (raw) {
                        this.value = Math.round(parseFloat(raw)).toLocaleString('id-ID');
                    } else {
                        this.value = '0';
                    }
                    recalcModalBonus();
                });
            });

            document.getElementById('formEditBonusModal').addEventListener('submit', function() {
                rupiahInputs.forEach(input => {
                    input.value = input.value.replace(/[^0-9]/g, '') || '0';
                });
            });
        });

        function filterKaryawanTable() {
            const searchVal = (document.getElementById('searchKaryawan').value || '').toLowerCase().trim();
            const deptVal = (document.getElementById('filterDepartemen').value || '').toLowerCase().trim();
            const jabVal = (document.getElementById('filterJabatan').value || '').toLowerCase().trim();

            let visible = 0;
            document.querySelectorAll('.payroll-row').forEach(row => {
                const rowNama = row.getAttribute('data-nama') || '';
                const rowDept = row.getAttribute('data-departemen') || '';
                const rowJab = row.getAttribute('data-jabatan') || '';

                const matchSearch = !searchVal || rowNama.includes(searchVal);
                const matchDept = !deptVal || rowDept === deptVal || rowDept.includes(deptVal);
                const matchJab = !jabVal || rowJab === jabVal || rowJab.includes(jabVal);

                if (matchSearch && matchDept && matchJab) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            const countEl = document.getElementById('visibleCount');
            if (countEl) countEl.textContent = visible;
        }

        function resetTableFilter() {
            document.getElementById('searchKaryawan').value = '';
            document.getElementById('filterDepartemen').value = '';
            document.getElementById('filterJabatan').value = '';
            filterKaryawanTable();
        }
    </script>
</x-app-layout>
