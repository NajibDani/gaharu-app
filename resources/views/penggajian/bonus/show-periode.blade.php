<x-app-layout>
    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            @php
                $totalBonusPeriode = $payrolls->sum('total_bonus');
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 mb-3">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-3">
                    {{-- Left Title & Info --}}
                    <div class="flex items-center gap-3 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight m-0">
                                    Kelola Bonus &amp; Lembur
                                </h1>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200/70">
                                    Outlet {{ $selectedOutlet }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium m-0 mt-0.5">
                                Periode: <strong class="text-slate-800">{{ \Carbon\Carbon::parse($targetPeriode . '-01')->translatedFormat('F Y') }}</strong>
                            </p>
                        </div>

                        {{-- Total Bonus Badge --}}
                        <div class="flex items-center gap-2 ms-0 sm:ms-2">
                            <div id="headerTotalBonusBadgeContainer"
                                 style="background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%); border: 1.5px solid #fcd34d; color: #78350f; padding: 6px 14px; border-radius: 10px; box-shadow: 0 1px 3px rgba(120, 53, 15, 0.08);"
                                 class="flex items-center gap-2">
                                <span style="font-size: 16px;">⭐</span>
                                <div class="text-left">
                                    <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #92400e; line-height: 1;">Total Bonus &amp; Lembur</div>
                                    <div style="font-size: 14px; font-weight: 900; color: #78350f; line-height: 1.2;" id="headerTotalBonusBadge">
                                        Rp {{ number_format($totalBonusPeriode, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Action Buttons --}}
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        @if(($currentStatus ?? 'draft') !== 'approved' && $payrolls->isNotEmpty())
                        <button type="button" onclick="submitBatchBonus(this)" id="btnBatchSaveBonus"
                                style="background-color: #7A4517; color: #ffffff; border: none; padding: 7px 14px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(122,69,23,0.25); transition: background .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'"
                                title="Simpan seluruh perubahan input bonus & lembur di halaman ini sekaligus">
                            <span>💾</span> Simpan Semua Bonus
                        </button>
                        @endif
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
                    {{-- Pilihan Bulan / Periode (sama seperti Keterlambatan) --}}
                    <select class="form-select form-select-sm" style="width: auto; min-width: 155px; padding: 6px 28px 6px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;"
                            onchange="window.location.href='{{ route('penggajian.bonus.periode') }}?periode=' + this.value + '&outlet={{ $selectedOutlet }}'">
                        @foreach($periodes as $p)
                            @php $carbonP = \Carbon\Carbon::parse($p . '-01'); @endphp
                            <option value="{{ $p }}" {{ $targetPeriode == $p ? 'selected' : '' }}>
                                {{ $carbonP->translatedFormat('F Y') }}
                            </option>
                        @endforeach
                    </select>

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

                <div class="flex items-center gap-2">
                    <div class="text-xs text-slate-700 font-bold bg-white border border-slate-200 shadow-sm px-3 py-1.5 rounded-lg">
                        <strong class="text-slate-900 font-black" id="visibleCount">{{ count($payrolls) }}</strong> karyawan terdaftar
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] text-xs text-left divide-y divide-slate-200" id="tableBonus">
                        <thead class="text-[11px] font-bold text-slate-700 uppercase tracking-wider bg-slate-100/90 border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-3 w-10 text-center whitespace-nowrap">#</th>
                                <th class="px-4 py-3 min-w-[200px] whitespace-nowrap">Karyawan</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[120px]">Lembur (Jam)</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[130px]">Target</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[130px]">Tgl Merah</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[120px]">Birthday (x)</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[135px]">Kembali Deposit</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[130px]">Bonus Lain</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[135px]">Total Bonus</th>
                                <th class="px-3 py-3 text-center min-w-[90px] whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($payrolls as $index => $payroll)
                            @php
                                $isRowLocked = $payroll->is_paid || $payroll->status === 'approved';
                                $satuanRow = $payroll->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian';
                                $tarifHarian = $payroll->tarif_harian_total > 0
                                    ? $payroll->tarif_harian_total
                                    : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));
                            @endphp
                            <tr class="payroll-row hover:bg-slate-50/80 transition-colors"
                                data-id="{{ $payroll->id }}"
                                data-satuan="{{ $satuanRow }}"
                                data-tarif="{{ (float)$tarifHarian }}"
                                data-nama="{{ strtolower($payroll->karyawan->nama_karyawan ?? '') }}"
                                data-departemen="{{ strtolower($payroll->karyawan->departemen ?? '') }}"
                                data-jabatan="{{ strtolower($payroll->karyawan->jabatan ?? '') }}">
                                <td class="px-3.5 py-2.5 text-center text-xs text-slate-500 font-bold">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 min-w-[200px]">
                                    <div class="font-extrabold text-slate-900 text-sm nama-karyawan leading-tight">
                                        {{ $payroll->karyawan->nama_karyawan ?? '-' }}
                                    </div>
                                    <div class="text-[11px] font-medium mt-1 flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-700">{{ $payroll->karyawan->jabatan ?? '-' }}</span>
                                        @if($payroll->karyawan->departemen)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 leading-normal">{{ $payroll->karyawan->departemen }}</span>
                                        @endif
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 leading-normal">{{ $satuanRow }}</span>
                                    </div>
                                </td>

                                {{-- 1. JAM LEMBUR (x 10.000) --}}
                                <td class="px-2 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="number" step="0.5" min="0"
                                               class="batch-jam-lembur w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ ($payroll->jam_lembur && $payroll->jam_lembur > 0) ? (float)$payroll->jam_lembur : '' }}"
                                               placeholder="0"
                                               oninput="onBonusRowInput(this)">
                                        <div class="sub-lembur-text text-[10px] text-slate-500 font-semibold mt-0.5 text-right {{ ($payroll->jam_lembur ?? 0) > 0 ? '' : 'hidden' }}">
                                            Rp {{ number_format(($payroll->jam_lembur ?? 0) * 10000, 0, ',', '.') }}
                                        </div>
                                    @else
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->lembur, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->jam_lembur }} jam</div>
                                    @endif
                                </td>

                                {{-- 2. TARGET --}}
                                <td class="px-2 py-2 text-right">
                                    @if(!$isRowLocked)
                                        @if($satuanRow === 'Harian')
                                            <input type="number" step="1" min="0"
                                                   class="batch-banyak-target w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                                   value="{{ ($payroll->banyak_target && $payroll->banyak_target > 0) ? (int)$payroll->banyak_target : '' }}"
                                                   placeholder="0"
                                                   oninput="onBonusRowInput(this)">
                                            <div class="sub-target-text text-[10px] text-slate-500 font-semibold mt-0.5 text-right {{ ($payroll->banyak_target ?? 0) > 0 ? '' : 'hidden' }}">
                                                Rp {{ number_format(($payroll->banyak_target ?? 0) * $tarifHarian, 0, ',', '.') }}
                                            </div>
                                        @else
                                            <input type="text"
                                                   class="batch-input-rupiah batch-bonus-target w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                                   value="{{ $payroll->bonus_target > 0 ? number_format($payroll->bonus_target, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onBonusRowInput(this)">
                                            <input type="hidden" class="batch-catatan-target" value="{{ $payroll->catatan_bonus_target }}">
                                        @endif
                                    @else
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_target, 0, ',', '.') }}</div>
                                        @if($satuanRow === 'Harian')
                                            <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->banyak_target }}x target</div>
                                        @else
                                            <div class="text-[10px] text-slate-500 font-medium truncate max-w-[100px] ml-auto">{{ $payroll->catatan_bonus_target ?: 'Manual' }}</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 3. TANGGAL MERAH --}}
                                <td class="px-2 py-2 text-right">
                                    @if(!$isRowLocked)
                                        @if($satuanRow === 'Harian')
                                            <input type="number" step="1" min="0"
                                                   class="batch-banyak-merah w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                                   value="{{ ($payroll->banyak_tanggal_merah && $payroll->banyak_tanggal_merah > 0) ? (int)$payroll->banyak_tanggal_merah : '' }}"
                                                   placeholder="0"
                                                   oninput="onBonusRowInput(this)">
                                            <div class="sub-merah-text text-[10px] text-slate-500 font-semibold mt-0.5 text-right {{ ($payroll->banyak_tanggal_merah ?? 0) > 0 ? '' : 'hidden' }}">
                                                Rp {{ number_format(($payroll->banyak_tanggal_merah ?? 0) * $tarifHarian, 0, ',', '.') }}
                                            </div>
                                        @else
                                            <input type="text"
                                                   class="batch-input-rupiah batch-bonus-merah w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                                   value="{{ $payroll->bonus_tanggal_merah > 0 ? number_format($payroll->bonus_tanggal_merah, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onBonusRowInput(this)">
                                            <input type="hidden" class="batch-catatan-merah" value="{{ $payroll->catatan_bonus_tanggal_merah }}">
                                        @endif
                                    @else
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_tanggal_merah, 0, ',', '.') }}</div>
                                        @if($satuanRow === 'Harian')
                                            <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->banyak_tanggal_merah }}x hadir</div>
                                        @else
                                            <div class="text-[10px] text-slate-500 font-medium truncate max-w-[100px] ml-auto">{{ $payroll->catatan_bonus_tanggal_merah ?: 'Manual' }}</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 4. BIRTHDAY (x 5.000) --}}
                                <td class="px-2 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="number" step="1" min="0"
                                               class="batch-banyak-birthday w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ ($payroll->banyak_birthday_service && $payroll->banyak_birthday_service > 0) ? (int)$payroll->banyak_birthday_service : '' }}"
                                               placeholder="0"
                                               oninput="onBonusRowInput(this)">
                                        <div class="sub-birthday-text text-[10px] text-slate-500 font-semibold mt-0.5 text-right {{ ($payroll->banyak_birthday_service ?? 0) > 0 ? '' : 'hidden' }}">
                                            Rp {{ number_format(($payroll->banyak_birthday_service ?? 0) * 5000, 0, ',', '.') }}
                                        </div>
                                    @else
                                        <div class="font-bold text-slate-800 text-xs">Rp {{ number_format($payroll->bonus_birthday, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-500 font-medium">{{ $payroll->banyak_birthday_service }}x</div>
                                    @endif
                                </td>

                                {{-- 5. PENGEMBALIAN DEPOSIT --}}
                                <td class="px-2 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="text"
                                               class="batch-input-rupiah batch-pengembalian-deposit w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-emerald-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ $payroll->pengembalian_deposit > 0 ? number_format($payroll->pengembalian_deposit, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               oninput="onBonusRowInput(this)">
                                        @if(($payroll->saldo_deposit ?? 0) > 0)
                                            <button type="button"
                                                    onclick="isiDepositOtomatis(this, {{ (float)$payroll->saldo_deposit }})"
                                                    class="mt-1 text-[9.5px] font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 rounded cursor-pointer transition-all inline-flex items-center gap-1 w-full justify-end"
                                                    title="Klik untuk mengisi otomatis dari saldo deposit tersimpan">
                                                <span>&#8629; Saldo: Rp {{ number_format($payroll->saldo_deposit, 0, ',', '.') }}</span>
                                            </button>
                                        @endif
                                    @else
                                        <span class="font-bold text-emerald-800 text-xs">{{ $payroll->pengembalian_deposit > 0 ? 'Rp ' . number_format($payroll->pengembalian_deposit, 0, ',', '.') : '-' }}</span>
                                    @endif
                                </td>

                                {{-- 6. BONUS LAIN --}}
                                <td class="px-2 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="text"
                                               class="batch-input-rupiah batch-bonus-dll w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-black text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ $payroll->bonus_dll > 0 ? number_format($payroll->bonus_dll, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               oninput="onBonusRowInput(this)">
                                    @else
                                        <span class="font-bold text-slate-700 text-xs">{{ $payroll->bonus_dll > 0 ? 'Rp ' . number_format($payroll->bonus_dll, 0, ',', '.') : '-' }}</span>
                                    @endif
                                </td>

                                {{-- TOTAL BONUS (LIVE CALCULATED) --}}
                                <td class="px-4 py-3 text-right font-black text-amber-800 text-xs whitespace-nowrap row-total-bonus-cell">
                                    Rp&nbsp;{{ number_format($payroll->total_bonus, 0, ',', '.') }}
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if(!$isRowLocked)
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
                                                    'pengembalian_deposit' => $payroll->pengembalian_deposit ?? 0,
                                                    'saldo_deposit' => $payroll->saldo_deposit ?? 0,
                                                    'bonus_dll' => $payroll->bonus_dll ?? 0,
                                                    'update_url' => route('penggajian.bonus.update', $payroll->id),
                                                ]) }})"
                                                style="background-color: #fffbf5; border: 1.5px solid #fcd34d; color: #78350f; font-weight: 800; font-size: 11.5px; padding: 4px 10px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all .15s;"
                                                onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fffbf5'"
                                                title="Edit Komponen Bonus via Pop-up">
                                            <span>&#9999;</span> Detail
                                        </button>
                                    @else
                                        <span class="text-[10.5px] text-slate-400 italic font-semibold">Terkunci</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-slate-500 font-medium">
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

                                {{-- 5. PENGEMBALIAN DEPOSIT --}}
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <label style="font-size: 11px; font-weight: 700; color: #047857; text-transform: uppercase; margin: 0;">
                                            Pengembalian Deposit (Rp)
                                        </label>
                                        <button type="button" id="btnModalIsiDeposit" onclick="isiModalDepositOtomatis()"
                                                style="display: none; font-size: 9.5px; font-weight: 800; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 4px; padding: 1px 6px; cursor: pointer;"
                                                title="Isi otomatis dengan saldo deposit yang tersimpan">
                                            &#8629; Isi Saldo (Rp <span id="mTextSaldoDeposit">0</span>)
                                        </button>
                                    </div>
                                    <input type="text" name="pengembalian_deposit" id="mInputPengembalianDeposit"
                                           class="w-full border border-emerald-300 rounded-lg px-3 py-1.5 text-xs font-black text-emerald-900 text-right focus:outline-none focus:ring-2 focus:ring-emerald-500/20 modal-rupiah-bonus"
                                           oninput="recalcModalBonus()" placeholder="0">
                                </div>

                                {{-- 6. BONUS LAIN-LAIN --}}
                                <div style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Bonus Lain-lain (Rp)
                                    </label>
                                    <input type="text" name="bonus_dll" id="mInputBonusDll"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-amber-500/20 modal-rupiah-bonus"
                                           oninput="recalcModalBonus()" placeholder="0">
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
        let currentModalSaldoDeposit = 0;

        function isiDepositOtomatis(btn, saldo) {
            const row = btn.closest('.payroll-row');
            if (!row) return;
            const input = row.querySelector('.batch-pengembalian-deposit');
            if (!input) return;

            input.value = Math.round(saldo).toLocaleString('id-ID');
            onBonusRowInput(input);
        }

        function isiModalDepositOtomatis() {
            if (currentModalSaldoDeposit > 0) {
                document.getElementById('mInputPengembalianDeposit').value = Math.round(currentModalSaldoDeposit).toLocaleString('id-ID');
                recalcModalBonus();
            }
        }

        function formatRupiahJs(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        function parseRupiahVal(val) {
            if (!val) return 0;
            let clean = String(val).replace(/[^0-9]/g, '');
            return parseFloat(clean) || 0;
        }

        function onBonusRowInput(el) {
            if (el.classList.contains('batch-input-rupiah')) {
                let raw = String(el.value).replace(/[^0-9]/g, '');
                if (raw) {
                    el.value = Math.round(parseFloat(raw)).toLocaleString('id-ID');
                } else {
                    el.value = '';
                }
            }

            const row = el.closest('.payroll-row');
            if (!row) return;

            recalcRowBonus(row);
            recalcGrandTotalBonus();
        }

        function recalcRowBonus(row) {
            const satuan = row.getAttribute('data-satuan') || 'Harian';
            const tarifHarian = parseFloat(row.getAttribute('data-tarif')) || 0;

            const jamLemburEl = row.querySelector('.batch-jam-lembur');
            const targetEl    = row.querySelector('.batch-banyak-target') || row.querySelector('.batch-bonus-target');
            const merahEl     = row.querySelector('.batch-banyak-merah') || row.querySelector('.batch-bonus-merah');
            const birthdayEl  = row.querySelector('.batch-banyak-birthday');
            const depositEl   = row.querySelector('.batch-pengembalian-deposit');
            const dllEl       = row.querySelector('.batch-bonus-dll');
            const totalCell   = row.querySelector('.row-total-bonus-cell');

            // 1. Lembur
            let jamLembur = jamLemburEl ? parseFloat(jamLemburEl.value) || 0 : 0;
            let upahLembur = jamLembur * 10000;
            const subLembur = row.querySelector('.sub-lembur-text');
            if (subLembur) {
                subLembur.textContent = formatRupiahJs(upahLembur);
                if (upahLembur > 0) subLembur.classList.remove('hidden');
                else subLembur.classList.add('hidden');
            }

            // 2. Target
            let bonusTarget = 0;
            if (satuan === 'Harian') {
                let banyakTarget = targetEl ? parseInt(targetEl.value) || 0 : 0;
                bonusTarget = banyakTarget * tarifHarian;
                const subTarget = row.querySelector('.sub-target-text');
                if (subTarget) {
                    subTarget.textContent = formatRupiahJs(bonusTarget);
                    if (bonusTarget > 0) subTarget.classList.remove('hidden');
                    else subTarget.classList.add('hidden');
                }
            } else {
                bonusTarget = targetEl ? parseRupiahVal(targetEl.value) : 0;
            }

            // 3. Tgl Merah
            let bonusMerah = 0;
            if (satuan === 'Harian') {
                let banyakMerah = merahEl ? parseInt(merahEl.value) || 0 : 0;
                bonusMerah = banyakMerah * tarifHarian;
                const subMerah = row.querySelector('.sub-merah-text');
                if (subMerah) {
                    subMerah.textContent = formatRupiahJs(bonusMerah);
                    if (bonusMerah > 0) subMerah.classList.remove('hidden');
                    else subMerah.classList.add('hidden');
                }
            } else {
                bonusMerah = merahEl ? parseRupiahVal(merahEl.value) : 0;
            }

            // 4. Birthday
            let banyakBirthday = birthdayEl ? parseInt(birthdayEl.value) || 0 : 0;
            let bonusBirthday = banyakBirthday * 5000;
            const subBirthday = row.querySelector('.sub-birthday-text');
            if (subBirthday) {
                subBirthday.textContent = formatRupiahJs(bonusBirthday);
                if (bonusBirthday > 0) subBirthday.classList.remove('hidden');
                else subBirthday.classList.add('hidden');
            }

            // 5. Deposit
            let pengembalianDeposit = depositEl ? parseRupiahVal(depositEl.value) : 0;

            // 6. DLL
            let bonusDll = dllEl ? parseRupiahVal(dllEl.value) : 0;

            let totalRowBonus = upahLembur + bonusTarget + bonusMerah + bonusBirthday + pengembalianDeposit + bonusDll;
            if (totalCell) {
                totalCell.textContent = formatRupiahJs(totalRowBonus);
            }
        }

        function recalcGrandTotalBonus() {
            let grandTotal = 0;
            document.querySelectorAll('.payroll-row').forEach(row => {
                const satuan = row.getAttribute('data-satuan') || 'Harian';
                const tarifHarian = parseFloat(row.getAttribute('data-tarif')) || 0;

                const jamLemburEl = row.querySelector('.batch-jam-lembur');
                const targetEl    = row.querySelector('.batch-banyak-target') || row.querySelector('.batch-bonus-target');
                const merahEl     = row.querySelector('.batch-banyak-merah') || row.querySelector('.batch-bonus-merah');
                const birthdayEl  = row.querySelector('.batch-banyak-birthday');
                const depositEl   = row.querySelector('.batch-pengembalian-deposit');
                const dllEl       = row.querySelector('.batch-bonus-dll');

                let jamLembur = jamLemburEl ? parseFloat(jamLemburEl.value) || 0 : 0;
                let upahLembur = jamLembur * 10000;

                let bonusTarget = 0;
                if (satuan === 'Harian') {
                    let banyakTarget = targetEl ? parseInt(targetEl.value) || 0 : 0;
                    bonusTarget = banyakTarget * tarifHarian;
                } else {
                    bonusTarget = targetEl ? parseRupiahVal(targetEl.value) : 0;
                }

                let bonusMerah = 0;
                if (satuan === 'Harian') {
                    let banyakMerah = merahEl ? parseInt(merahEl.value) || 0 : 0;
                    bonusMerah = banyakMerah * tarifHarian;
                } else {
                    bonusMerah = merahEl ? parseRupiahVal(merahEl.value) : 0;
                }

                let banyakBirthday = birthdayEl ? parseInt(birthdayEl.value) || 0 : 0;
                let bonusBirthday = banyakBirthday * 5000;

                let pengembalianDeposit = depositEl ? parseRupiahVal(depositEl.value) : 0;
                let bonusDll = dllEl ? parseRupiahVal(dllEl.value) : 0;

                grandTotal += (upahLembur + bonusTarget + bonusMerah + bonusBirthday + pengembalianDeposit + bonusDll);
            });

            const badge = document.getElementById('headerTotalBonusBadge');
            if (badge) {
                badge.textContent = 'Total Bonus: ' + formatRupiahJs(grandTotal);
            }
        }

        async function submitBatchBonus(btn) {
            const rows = document.querySelectorAll('.payroll-row');
            if (!rows.length) return;

            const items = [];
            rows.forEach(row => {
                const id = row.getAttribute('data-id');
                if (!id) return;

                const satuan = row.getAttribute('data-satuan') || 'Harian';
                const jamLemburEl = row.querySelector('.batch-jam-lembur');
                const targetEl    = row.querySelector('.batch-banyak-target') || row.querySelector('.batch-bonus-target');
                const catatanTargetEl = row.querySelector('.batch-catatan-target');
                const merahEl     = row.querySelector('.batch-banyak-merah') || row.querySelector('.batch-bonus-merah');
                const catatanMerahEl = row.querySelector('.batch-catatan-merah');
                const birthdayEl  = row.querySelector('.batch-banyak-birthday');
                const depositEl   = row.querySelector('.batch-pengembalian-deposit');
                const dllEl       = row.querySelector('.batch-bonus-dll');

                let itemData = {
                    id: id,
                    jam_lembur: jamLemburEl ? parseFloat(jamLemburEl.value) || 0 : 0,
                    banyak_birthday_service: birthdayEl ? parseInt(birthdayEl.value) || 0 : 0,
                    pengembalian_deposit: depositEl ? parseRupiahVal(depositEl.value) : 0,
                    bonus_dll: dllEl ? parseRupiahVal(dllEl.value) : 0,
                };

                if (satuan === 'Harian') {
                    itemData.banyak_target = targetEl ? parseInt(targetEl.value) || 0 : 0;
                    itemData.banyak_tanggal_merah = merahEl ? parseInt(merahEl.value) || 0 : 0;
                } else {
                    itemData.bonus_target = targetEl ? parseRupiahVal(targetEl.value) : 0;
                    itemData.catatan_bonus_target = catatanTargetEl ? catatanTargetEl.value : '';
                    itemData.bonus_tanggal_merah = merahEl ? parseRupiahVal(merahEl.value) : 0;
                    itemData.catatan_bonus_tanggal_merah = catatanMerahEl ? catatanMerahEl.value : '';
                }

                items.push(itemData);
            });

            const origContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>&#8987;</span> Menyimpan...';

            try {
                const response = await fetch("{{ route('penggajian.bonus.batch-update') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        items: items,
                        periode: "{{ $targetPeriode }}",
                        outlet: "{{ $selectedOutlet }}"
                    })
                });

                const res = await response.json();
                if (response.ok && res.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan!',
                            text: res.message || 'Seluruh data bonus & lembur berhasil diperbarui.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(res.message || 'Seluruh data bonus & lembur berhasil disimpan!');
                    }
                } else {
                    alert('Gagal menyimpan: ' + (res.message || 'Terjadi kesalahan sistem.'));
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan jaringan atau server saat menyimpan data.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = origContent;
            }
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
            
            let pDeposit = parseFloat(data.pengembalian_deposit) || 0;
            document.getElementById('mInputPengembalianDeposit').value = pDeposit ? Math.round(pDeposit).toLocaleString('id-ID') : '0';

            currentModalSaldoDeposit = parseFloat(data.saldo_deposit) || 0;
            const btnIsi = document.getElementById('btnModalIsiDeposit');
            const txtSaldo = document.getElementById('mTextSaldoDeposit');
            if (currentModalSaldoDeposit > 0) {
                if (btnIsi) btnIsi.style.display = 'inline-block';
                if (txtSaldo) txtSaldo.textContent = Math.round(currentModalSaldoDeposit).toLocaleString('id-ID');
            } else {
                if (btnIsi) btnIsi.style.display = 'none';
            }

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

            let pengembalianDeposit = parseRupiahVal(document.getElementById('mInputPengembalianDeposit').value);
            let bonusDll = parseRupiahVal(document.getElementById('mInputBonusDll').value);

            let bonusKinerja = bonusTarget + bonusMerah + bonusBirthday + pengembalianDeposit + bonusDll;
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
