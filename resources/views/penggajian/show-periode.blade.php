<x-app-layout>
    <div class="py-4" x-data="payrollManager()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            {{-- PAGE HEADER --}}
            @php
                $totalGajiNettPeriode = $payrolls->sum('take_home_pay');
                $totalGajiPokokPeriode = $payrolls->sum('gaji_utama');
                $totalBonusPeriode = $payrolls->sum(function($p) {
                    return (float)(($p->lembur ?? 0) + ($p->bonus_target ?? 0) + ($p->bonus_tanggal_merah ?? 0) + ($p->bonus_birthday ?? 0) + ($p->pengembalian_deposit ?? 0) + ($p->bonus_dll ?? 0));
                });
                $totalPotonganPeriode = $payrolls->sum(function($p) {
                    return (float)($p->total_deductions ?? (($p->potongan_terlambat ?? 0) + ($p->potongan_inventaris ?? 0) + ($p->potongan_kasbon ?? 0) + ($p->potongan_deposit ?? 0) + ($p->potongan_dll ?? 0)));
                });
            @endphp
            {{-- PAGE HEADER & TOOLBAR --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-2.5 sm:py-3 mb-2.5">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-2.5">
                    {{-- Left Title & Outlet --}}
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-snug m-0">
                                Formulir Hitung Gaji Karyawan
                            </h1>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-bold bg-amber-50 text-amber-900 border border-amber-300">
                                🏢 Outlet {{ $selectedOutlet }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium m-0 mt-1 flex items-center gap-1.5">
                            <span>📅 Periode:</span> <strong class="text-slate-800 font-bold">{{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}</strong>
                        </p>
                    </div>

                    {{-- Right Action Buttons --}}
                    <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                        {{-- TOMBOL SINKRONISASI / REFRESH DATA DARI SERVER --}}
                        <button type="button" onclick="refreshPayrollData(this)" id="btnRefreshPayrollData"
                                style="background-color: #f1f5f9; color: #334155; border: 1.5px solid #cbd5e1; padding: 5px 10px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'"
                                title="Muat ulang data terbaru dari database (berguna jika ada user lain yang baru menginput)">
                            <span id="refreshIcon">🔄</span> Sinkronkan Data
                        </button>

                        @if($currentStatus == 'draft' || $currentStatus == 'waiting approval')
                        <button type="button" onclick="submitBatchUnifiedPayroll(this)" id="btnBatchSaveUnifiedPayroll"
                                style="background-color: #7A4517; color: #ffffff; border: none; padding: 5px 12px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(122,69,23,0.2); transition: background .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'"
                                title="Simpan formulir gaji ke database">
                            <span>💾</span> <span id="btnBatchSaveText">Simpan Formulir Gaji</span>
                        </button>

                        <form action="{{ route('penggajian.auto-fill') }}" method="POST" class="inline m-0 p-0">
                            @csrf
                            <input type="hidden" name="periode" value="{{ $periode }}">
                            <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                            <button type="submit"
                                    onclick="return confirm('Tambahkan seluruh karyawan aktif Outlet {{ $selectedOutlet }} yang belum terdaftar ke periode {{ \App\Models\Penggajian::formatPeriode($periode) }} secara otomatis?')"
                                    style="background-color: #ffffff; color: #0f172a; border: 1.5px solid #cbd5e1; padding: 5px 11px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: background .15s; white-space: nowrap;"
                                    onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'"
                                    title="Tambahkan otomatis semua karyawan aktif yang belum terdaftar di periode ini">
                                <span>⚡</span> Auto-Fill
                            </button>
                        </form>

                        <button type="button" @click="openCreateModal()"
                                style="background-color: #ffffff; color: #334155; border: 1.5px solid #cbd5e1; padding: 5px 11px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: background .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'"
                                title="Input slip gaji baru secara manual via pop-up">
                            <span style="font-size: 13px; line-height: 1;">+</span> Input Gaji
                        </button>
                        @endif

                        <form action="{{ route('penggajian.bayar-semua', $periode) }}" method="POST" class="inline m-0 p-0"
                              onsubmit="return confirm('Proses pembayaran dan jurnal untuk SELURUH karyawan di periode {{ \App\Models\Penggajian::formatPeriode($periode) }}?')">
                            @csrf
                            <button type="submit"
                                    style="background-color: #059669; color: #ffffff; border: none; padding: 5px 12px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(5,150,105,0.2); transition: background .15s; white-space: nowrap;"
                                    onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'">
                                <span>💳</span> Bayar Semua
                            </button>
                        </form>

                        {{-- EXPORT EXCEL BUTTON --}}
                        <a href="{{ route('penggajian.export-excel', ['periode' => $periode, 'outlet' => $selectedOutlet]) }}"
                           style="background-color: #166534; color: #ffffff; border: none; padding: 5px 12px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(22,101,52,0.2); transition: background .15s; white-space: nowrap; text-decoration: none;"
                           onmouseover="this.style.background='#14532d'" onmouseout="this.style.background='#166534'"
                           title="Unduh data transfer gaji ke rekening (format Excel payroll bank)">
                            <span>📄</span> Export Excel
                        </a>

                        {{-- MAXIMIZE / FULLSCREEN BUTTON --}}
                        <button type="button" id="btnToggleMaximizePayroll" onclick="toggleMaximizePayroll()"
                                style="background-color: #0f172a; color: #ffffff; border: 1.5px solid #334155; padding: 5px 12px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(15,23,42,0.25); transition: all .15s; white-space: nowrap;"
                                onmouseover="if(!document.body.classList.contains('payroll-maximized')) this.style.background='#334155'"
                                onmouseout="if(!document.body.classList.contains('payroll-maximized')) this.style.background='#0f172a'"
                                title="Layar Penuh: Sembunyikan sidebar dan maksimalkan ruang kerja tabel hitung gaji (Tekan ESC untuk keluar)">
                            <span id="maximizeIcon" style="font-size: 13px;">⛶</span>
                            <span id="maximizeText">Maximize</span>
                        </button>
                    </div>
                </div>

                {{-- 4 KARTU RINGKASAN: COMPACT & TIPIS, 1 BARIS MENYAMPING DARI KIRI KE KANAN --}}
                <div class="summary-cards-row" style="display: flex !important; flex-direction: row !important; align-items: stretch !important; gap: 8px !important; width: 100% !important; margin-top: 8px !important; padding-top: 8px !important; border-top: 1px solid #f1f5f9 !important; box-sizing: border-box !important;">
                    {{-- 1. Gaji Pokok Badge --}}
                    <div style="flex: 1 1 0% !important; width: 25% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 10px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 8px !important; box-sizing: border-box !important;"
                         title="Total Gaji Pokok Seluruh Karyawan">
                        <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #475569; flex-shrink: 0;">
                            💼
                        </div>
                        <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                            <div style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total Pokok</div>
                            <div style="font-size: 13px; font-weight: 900; color: #0f172a; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalGajiPokokValue">
                                Rp {{ number_format($totalGajiPokokPeriode, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    {{-- 2. Bonus & Lembur Badge --}}
                    <div style="flex: 1 1 0% !important; width: 25% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 10px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 8px !important; box-sizing: border-box !important;"
                         title="Total Bonus & Lembur Seluruh Karyawan">
                        <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #fefce8; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #ca8a04; flex-shrink: 0;">
                            ⭐
                        </div>
                        <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                            <div style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total Bonus</div>
                            <div style="font-size: 13px; font-weight: 900; color: #d97706; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalBonusValue">
                                Rp {{ number_format($totalBonusPeriode, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    {{-- 3. Potongan Badge --}}
                    <div style="flex: 1 1 0% !important; width: 25% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 10px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 8px !important; box-sizing: border-box !important;"
                         title="Total Pengurangan & Potongan Seluruh Karyawan">
                        <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #fff1f2; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #e11d48; flex-shrink: 0;">
                            ✂️
                        </div>
                        <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                            <div style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total Potongan</div>
                            <div style="font-size: 13px; font-weight: 900; color: #e11d48; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalPotonganValue">
                                Rp {{ number_format($totalPotonganPeriode, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    {{-- 4. Total Gaji Bersih / Nett Badge --}}
                    <div id="headerTotalGajiNettBadge"
                         style="flex: 1 1 0% !important; width: 25% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #cbd5e1 !important; padding: 5px 10px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 8px !important; box-sizing: border-box !important;"
                         title="Total Take Home Pay Seluruh Karyawan">
                        <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #ecfdf5; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #059669; flex-shrink: 0;">
                            💰
                        </div>
                        <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                            <div style="font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #475569; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total Bersih (THP)</div>
                            <div style="font-size: 13px; font-weight: 900; color: #16a34a; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalGajiNettValue">
                                Rp {{ number_format($totalGajiNettPeriode, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-3.5 py-1.5 rounded-lg mb-2 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <span class="text-emerald-600 text-sm">&#10003;</span> {{ session('success') }}
            </div>
            @endif
            @if(session('info'))
            <div class="bg-blue-50 border border-blue-300 text-blue-900 px-3.5 py-1.5 rounded-lg mb-2 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <span class="text-blue-600 text-sm">&#9432;</span> {{ session('info') }}
            </div>
            @endif
            @if($errors->any())
            <div class="bg-rose-50 border border-rose-300 text-rose-900 px-3.5 py-1.5 rounded-lg mb-2 text-xs font-bold shadow-2xs">
                <ul class="list-disc pl-5 m-0 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @php
                $depts = $payrolls->pluck('karyawan.departemen')->filter()->unique()->sort();
                $jbtns = $payrolls->pluck('karyawan.jabatan')->filter()->unique()->sort();
            @endphp

            {{-- TOOLBAR FILTER & PENCARIAN (COMPACT) --}}
            <div class="flex justify-between items-center gap-2 mb-2 flex-wrap">
                <div class="flex items-center gap-1.5 flex-wrap flex-1">
                    {{-- Pilihan Bulan / Periode --}}
                    <div class="relative">
                        <span style="position: absolute; left: 9px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none; z-index: 1;">📅</span>
                        <select style="padding: 4.5px 30px 4.5px 28px; border: 1.5px solid #7A4517; border-radius: 7px; font-size: 11.5px; font-weight: 700; color: #0f172a; background: #fffaf7; outline: none; cursor: pointer; appearance: none; -webkit-appearance: none; min-width: 160px; box-shadow: 0 1px 2px rgba(122,69,23,0.08);"
                                onchange="window.location.href='{{ route('penggajian.show-periode') }}?periode=' + this.value + '&outlet={{ $selectedOutlet }}'">
                            @foreach($periodes as $p)
                                @php $carbonP = \Carbon\Carbon::parse($p . '-01'); @endphp
                                <option value="{{ $p }}" {{ $periode == $p ? 'selected' : '' }}>
                                    {{ $carbonP->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>
                        <svg style="position: absolute; right: 9px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #7A4517;" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </div>

                    {{-- Search Input --}}
                    <div class="relative">
                        <input type="text" id="searchKaryawan" onkeyup="filterKaryawanTable()"
                               placeholder="&#128269; Cari nama..."
                               style="width: 200px; padding: 4.5px 10px; border: 1.5px solid #cbd5e1; border-radius: 7px; font-size: 11.5px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none;">
                    </div>

                    {{-- Filter Departemen --}}
                    <select id="filterDepartemen" onchange="filterKaryawanTable()"
                            style="padding: 4.5px 10px; border: 1.5px solid #cbd5e1; border-radius: 7px; font-size: 11.5px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;">
                        <option value="">Semua Departemen</option>
                        @foreach($depts as $dept)
                            <option value="{{ strtolower($dept) }}">{{ $dept }}</option>
                        @endforeach
                    </select>

                    {{-- Filter Jabatan --}}
                    <select id="filterJabatan" onchange="filterKaryawanTable()"
                            style="padding: 4.5px 10px; border: 1.5px solid #cbd5e1; border-radius: 7px; font-size: 11.5px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;">
                        <option value="">Semua Jabatan</option>
                        @foreach($jbtns as $jbtn)
                            <option value="{{ strtolower($jbtn) }}">{{ $jbtn }}</option>
                        @endforeach
                    </select>

                    <button type="button" onclick="resetTableFilter()"
                            style="padding: 4.5px 11px; background-color: #f1f5f9; color: #334155; font-weight: 800; border-radius: 7px; font-size: 11.5px; border: 1.5px solid #cbd5e1; cursor: pointer; transition: background .15s;"
                            onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                        Reset
                    </button>
                </div>

                <div class="text-[11.5px] text-slate-700 font-bold bg-white border border-slate-200 shadow-2xs px-2.5 py-1 rounded-md">
                    <strong class="text-slate-900 font-black" id="visibleCount">{{ count($payrolls) }}</strong> karyawan terdaftar
                </div>
            </div>

            <style>
                /* Sticky Freeze Columns */
                .sticky-col-num {
                    position: sticky !important;
                    left: 0px !important;
                    width: 48px !important;
                    min-width: 48px !important;
                    max-width: 48px !important;
                    z-index: 25 !important;
                    background-color: #ffffff !important; /* Solid putih netral */
                    color: #334155 !important;
                    border-right: 1px solid #e2e8f0 !important;
                }
                .sticky-col-karyawan {
                    position: sticky !important;
                    left: 48px !important;
                    width: 245px !important;
                    min-width: 245px !important;
                    max-width: 245px !important;
                    z-index: 25 !important;
                    background-color: #ffffff !important; /* Solid putih netral */
                    color: #0f172a !important;
                    border-right: 2px solid #cbd5e1 !important; /* Garis batas tegas kanan identitas */
                    box-shadow: 4px 0 10px -2px rgba(15, 23, 42, 0.12) !important;
                }
                thead th.sticky-col-num {
                    position: sticky !important;
                    left: 0px !important;
                    z-index: 55 !important;
                    background-color: #f1f5f9 !important; /* Header abu sangat terang */
                    color: #0f172a !important;
                    border-right: 1px solid #cbd5e1 !important;
                }
                thead th.sticky-col-karyawan {
                    position: sticky !important;
                    left: 48px !important;
                    z-index: 55 !important;
                    background-color: #f1f5f9 !important; /* Header abu sangat terang */
                    color: #0f172a !important;
                    border-right: 2px solid #cbd5e1 !important;
                    box-shadow: 4px 0 10px -2px rgba(15, 23, 42, 0.12) !important;
                }

                /* 1. SKEMA WARNA HEADER TABEL: PASTEL EKSKLUSIF PADA HEADER KATEGORI UTAMA (TIER 1) */
                thead th {
                    background-color: #f8fafc !important; /* Abu-Abu Terang Seragam untuk Tier 2 */
                    color: #334155 !important;           /* Abu-Abu Gelap Kehitaman Maksimal Keterbacaan */
                    border-color: #cbd5e1 !important;
                }

                /* Header Pastel Eksklusif Kategori Utama (Tier 1) */
                thead th.th-cat-pokok {
                    background-color: #dcfce7 !important; /* Hijau Muda Pastel */
                    color: #1e293b !important;           /* Teks Abu-Abu Gelap */
                }
                thead th.th-cat-bonus {
                    background-color: #fef3c7 !important; /* Kuning Muda Pastel */
                    color: #1e293b !important;           /* Teks Abu-Abu Gelap */
                }
                thead th.th-cat-potongan {
                    background-color: #ffe4e6 !important; /* Merah Muda / Rose Pastel */
                    color: #1e293b !important;           /* Teks Abu-Abu Gelap */
                }
                thead th.th-cat-nett {
                    background-color: #f1f5f9 !important; /* Netral Abu Terang */
                    color: #1e293b !important;
                }

                /* 2. SELURUH ISI DATA (td) DI DALAM tbody BERSIH NETRAL DENGAN ZEBRA-STRIPING */
                tbody tr.payroll-row td {
                    background-color: transparent;
                }
                tbody tr.payroll-row:nth-child(even) td:not(.sticky-col-num):not(.sticky-col-karyawan) {
                    background-color: #ffffff !important; /* Putih Bersih */
                }
                tbody tr.payroll-row:nth-child(odd) td:not(.sticky-col-num):not(.sticky-col-karyawan) {
                    background-color: #f8fafc !important; /* Abu-Abu Sangat Terang / Muda */
                }
                tbody tr.payroll-row:hover td:not(.sticky-col-num):not(.sticky-col-karyawan) {
                    background-color: #f1f5f9 !important; /* Hover abu lembut */
                }

                /* Freeze column identitas karyawan putih netral dengan zebra-striping halus */
                tbody tr.payroll-row:nth-child(even) .sticky-col-num,
                tbody tr.payroll-row:nth-child(even) .sticky-col-karyawan {
                    background-color: #ffffff !important;
                }
                tbody tr.payroll-row:nth-child(odd) .sticky-col-num,
                tbody tr.payroll-row:nth-child(odd) .sticky-col-karyawan {
                    background-color: #fbfcfe !important;
                }
                tbody tr.payroll-row:hover .sticky-col-num,
                tbody tr.payroll-row:hover .sticky-col-karyawan {
                    background-color: #f1f5f9 !important;
                }

                /* Subtotal kolom tetap netral */
                .col-cat-pokok, .col-cat-pokok-subtotal,
                .col-cat-bonus, .col-cat-bonus-subtotal,
                .col-cat-potongan, .col-cat-potongan-subtotal,
                .col-cat-nett {
                    background-color: transparent !important;
                }

                /* 3. GARIS PEMBATAS VERTIKAL TEGAS ABU-ABU TUA SERAGAM KE BAWAH */
                .cat-divider-pokok,
                .cat-divider-bonus,
                .cat-divider-potongan,
                .cat-divider-nett {
                    border-right: 2px solid #64748b !important; /* Abu-abu tua seragam (#64748b) */
                }
                thead th.cat-divider-pokok,
                thead th.cat-divider-bonus,
                thead th.cat-divider-potongan,
                thead th.cat-divider-nett {
                    border-right: 2px solid #64748b !important;
                }
                thead th.sticky-col-karyawan,
                tbody td.sticky-col-karyawan {
                    border-right: 2px solid #64748b !important;
                }

                /* Format Akuntansi: Rp rata kiri, Angka rata kanan */
                .accounting-cell {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    width: 100% !important;
                    gap: 6px !important;
                    white-space: nowrap !important;
                }
                .accounting-cell .ac-curr {
                    text-align: left !important;
                    font-weight: 700 !important;
                    color: inherit !important;
                    opacity: 0.85 !important;
                    user-select: none !important;
                    flex-shrink: 0 !important;
                }
                .accounting-cell .ac-val {
                    text-align: right !important;
                    font-weight: inherit !important;
                    color: inherit !important;
                    flex-grow: 1 !important;
                    font-variant-numeric: tabular-nums !important;
                }
                .accounting-input-wrap {
                    display: flex !important;
                    align-items: center !important;
                    background-color: #ffffff !important;
                    border: 1.5px solid #cbd5e1 !important;
                    border-radius: 6px !important;
                    padding: 2px 6px !important;
                    width: 100% !important;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
                }
                .accounting-input-wrap:focus-within {
                    border-color: #6366f1 !important;
                    outline: 2px solid rgba(99, 102, 241, 0.2) !important;
                }
                .accounting-input-wrap .ac-input-prefix {
                    font-size: 11px !important;
                    font-weight: 700 !important;
                    color: #64748b !important;
                    user-select: none !important;
                    margin-right: 4px !important;
                    flex-shrink: 0 !important;
                }
                .accounting-input-wrap input {
                    border: none !important;
                    outline: none !important;
                    background: transparent !important;
                    width: 100% !important;
                    text-align: right !important;
                    font-size: 12px !important;
                    font-weight: 700 !important;
                    color: #1e293b !important;
                    padding: 0 !important;
                    box-shadow: none !important;
                }
                thead th {
                    position: sticky !important;
                    z-index: 30 !important;
                }
                thead tr.thead-tier-1 th {
                    top: 0 !important;
                    height: 34px;
                }
                thead tr.thead-tier-2 th {
                    top: 34px !important;
                    height: 32px;
                }
                thead th.sticky-col-num,
                thead th.sticky-col-karyawan {
                    z-index: 55 !important;
                    top: 0 !important;
                }

                /* Highlight baris yang sedang diedit tapi belum disimpan */
                tr.payroll-row.row-modified {
                    background-color: #fffbeb !important;
                    box-shadow: inset 4px 0 0 #f59e0b;
                }
                tr.payroll-row.row-modified td {
                    background-color: #fffbeb !important;
                }
                tr.payroll-row.row-modified td.sticky-col-karyawan {
                    background-color: #fef3c7 !important;
                }

                @keyframes spin-sync {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .animate-spin {
                    display: inline-block;
                    animation: spin-sync 0.8s linear infinite;
                }

                /* ========================================================================= */
                /* MODE MAXIMIZE / FULLSCREEN KHUSUS FORM HITUNG GAJI                        */
                /* ========================================================================= */
                body.payroll-maximized {
                    overflow-x: hidden !important;
                }

                /* Sembunyikan sidebar, backdrop, topbar, page-header, dan selector outlet */
                body.payroll-maximized .sidebar,
                body.payroll-maximized .sidebar-backdrop,
                body.payroll-maximized .topbar,
                body.payroll-maximized .page-header-container,
                body.payroll-maximized .outlet-selector-card {
                    display: none !important;
                }

                /* Lebarkan container utama ke seluruh layar (100vw) tanpa margin sisa */
                body.payroll-maximized .content-wrapper {
                    width: 100vw !important;
                    max-width: 100vw !important;
                    min-width: 100vw !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    flex: 1 1 100% !important;
                }

                body.payroll-maximized main {
                    padding: 10px 16px 14px 16px !important;
                    max-width: 100vw !important;
                    width: 100vw !important;
                    margin: 0 !important;
                }

                body.payroll-maximized .py-4 {
                    padding-top: 0 !important;
                    padding-bottom: 0 !important;
                }

                body.payroll-maximized .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    padding-left: 0 !important;
                    padding-right: 0 !important;
                }

                /* Maksimalkan tinggi container scroll tabel hitung gaji */
                body.payroll-maximized .payroll-table-container {
                    max-height: calc(100vh - 142px) !important;
                    height: calc(100vh - 142px) !important;
                }
            </style>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm" style="overflow: visible;">
                <div id="payrollTableScrollContainer" class="overflow-x-auto pb-8 payroll-table-container" style="overflow-y: auto; max-height: 75vh; min-height: 260px; max-width: 100%;">
                    <table class="w-full text-xs text-left divide-y divide-slate-200 border-separate border-spacing-0" id="tableKaryawan" style="min-width: 2280px;">
                        <thead class="text-[11px] font-bold uppercase tracking-wider">
                            {{-- TIER 1: KATEGORI UTAMA DENGAN WARNA LATAR PASTEL EKSKLUSIF & PEMBATAS TEGAS --}}
                            <tr class="thead-tier-1 divide-x divide-slate-200 border-b border-slate-300">
                                {{-- 1 & 2. PROFIL KARYAWAN (FREEZE - ROWSPAN 2) --}}
                                <th rowspan="2" class="sticky-col-num px-3 py-2 text-center whitespace-nowrap border-b-2 border-slate-300">#</th>
                                <th rowspan="2" class="sticky-col-karyawan px-4 py-2 text-center whitespace-nowrap border-b-2 border-slate-300">
                                    <div class="text-[9.5px] text-slate-500 font-extrabold tracking-wider">IDENTITAS</div>
                                    <div class="text-xs font-black text-slate-800">NAMA &amp; JABATAN</div>
                                </th>

                                {{-- GAJI POKOK (3 KOLOM: Hari Kerja, Tarif Satuan, Subtotal Pokok) --}}
                                <th colspan="3" class="th-cat-pokok cat-divider-pokok text-center py-2 px-3 font-black tracking-wider border-b border-slate-300">
                                    GAJI POKOK
                                </th>

                                {{-- BONUS (7 KOLOM: Jam Lembur, Target, Tgl Merah, Birthday, Deposit Balik, Bonus Lain, Total Bonus) --}}
                                <th colspan="7" class="th-cat-bonus cat-divider-bonus text-center py-2 px-3 font-black tracking-wider border-b border-slate-300">
                                    BONUS
                                </th>

                                {{-- POTONGAN (6 KOLOM: Terlambat, Inventaris, Kasbon, Pot. Deposit, Potongan Lain, Total Potongan) --}}
                                <th colspan="6" class="th-cat-potongan cat-divider-potongan text-center py-2 px-3 font-black tracking-wider border-b border-slate-300">
                                    POTONGAN
                                </th>

                                {{-- GAJI BERSIH & AKSI (2 KOLOM: Gaji Bersih (THP), Aksi) --}}
                                <th colspan="2" class="th-cat-nett text-center py-2 px-3 font-black tracking-wider border-b border-slate-300">
                                    GAJI BERSIH &amp; AKSI
                                </th>
                            </tr>

                            {{-- TIER 2: RINCIAN DETAIL SEL / KOLOM TEKNIS --}}
                            <tr class="thead-tier-2 border-b-2 border-slate-300 text-[10px] text-slate-700">
                                {{-- Under GAJI POKOK --}}
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[125px] border-b border-slate-200">Hari Kerja</th>
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[130px] border-b border-slate-200">Tarif Satuan</th>
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[140px] border-b border-slate-200 font-extrabold cat-divider-pokok">Subtotal Pokok</th>

                                {{-- Under BONUS --}}
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[110px] border-b border-slate-200" title="10.000 / Jam">Jam Lembur</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[125px] border-b border-slate-200" title="Harian: Target x Tarif | Bulanan: Nominal">Target</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[120px] border-b border-slate-200" title="Harian: Hadir x Tarif | Bulanan: Nominal">Tgl Merah</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[110px] border-b border-slate-200" title="5.000 / Service">Birthday</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[125px] border-b border-slate-200">Deposit Balik</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[120px] border-b border-slate-200">Bonus Lain</th>
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[130px] border-b border-slate-200 font-extrabold cat-divider-bonus">Total Bonus</th>

                                {{-- Under POTONGAN --}}
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[125px] border-b border-slate-200">Terlambat</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[120px] border-b border-slate-200">Inventaris</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[120px] border-b border-slate-200">Kasbon</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[120px] border-b border-slate-200">Pot. Deposit</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap min-w-[130px] border-b border-slate-200">Potongan Lain</th>
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[130px] border-b border-slate-200 font-extrabold cat-divider-potongan">Total Potongan</th>

                                {{-- Under GAJI BERSIH & AKSI --}}
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[145px] border-b border-slate-200 font-black">Gaji Bersih (THP)</th>
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[155px] border-b border-slate-300 font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white" id="tbodyKaryawan">
                            @forelse($payrolls as $index => $payroll)
                            @php
                                $tarifHarian = $payroll->tarif_harian_total > 0
                                    ? $payroll->tarif_harian_total
                                    : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));
                                $totalBonus = (float)(($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) + ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->pengembalian_deposit ?? 0) + ($payroll->bonus_dll ?? 0));
                                $totalPotongan = (float)($payroll->total_deductions ?? (($payroll->potongan_terlambat ?? 0) + ($payroll->potongan_inventaris ?? 0) + ($payroll->potongan_kasbon ?? 0) + ($payroll->potongan_deposit ?? 0) + ($payroll->potongan_dll ?? 0)));
                                $gajiPokok = (float)($payroll->gaji_utama ?? 0);
                                $earnings = (float)($payroll->total_earnings > 0 ? $payroll->total_earnings : ($gajiPokok + $totalBonus));
                                $isPaid = $payroll->is_paid;
                                $confirmMsg = 'Bayar gaji ' . ($payroll->karyawan->nama_karyawan ?? 'Karyawan') . ' dan buat Jurnal Umum?';
                                
                                $pilihanPeriodeRow = $payroll->pilihan_periode ?? 1;
                                $satuanRow = ($pilihanPeriodeRow == 2 && ($payroll->satuan_gaji_2 || ($payroll->karyawan->satuan_gaji_2 ?? null)))
                                    ? ($payroll->satuan_gaji_2 ?? $payroll->karyawan->satuan_gaji_2 ?? 'Harian')
                                    : ($payroll->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian');

                                $hasMasterMultiplePeriods = !is_null($payroll->karyawan->gaji_pokok_2 ?? null);
                                $hasMultiplePeriods = ($payroll->items && $payroll->items->count() > 1) || $hasMasterMultiplePeriods;

                                // Rincian per periode (satuan, unit suffix, tarif, dsb)
                                if ($hasMultiplePeriods) {
                                    $existingItemsByPeriode = $payroll->items ? $payroll->items->keyBy(function($item) {
                                        return (int)($item->pilihan_periode ?? 1);
                                    }) : collect();

                                    // Filter periode berdasarkan tanggal berlaku vs bulan penggajian
                                    $kw = $payroll->karyawan;
                                    $periodeMonthStart = \Carbon\Carbon::parse($periode . '-01');
                                    $periodeMonthEnd   = $periodeMonthStart->copy()->endOfMonth();

                                    $candidatePeriodes = [];

                                    // Cek P1: overlap jika tanggal_mulai..tanggal_selesai beririsan dengan bulan ini
                                    $p1Mulai   = $kw->tanggal_mulai ? \Carbon\Carbon::parse($kw->tanggal_mulai) : null;
                                    $p1Selesai = $kw->tanggal_selesai ? \Carbon\Carbon::parse($kw->tanggal_selesai) : null;
                                    $p1Active  = true; // default aktif jika tidak ada tanggal
                                    if ($p1Mulai && $p1Selesai) {
                                        // P1 aktif jika rentang P1 beririsan dengan bulan penggajian
                                        $p1Active = $p1Mulai->lte($periodeMonthEnd) && $p1Selesai->gte($periodeMonthStart);
                                    } elseif ($p1Mulai) {
                                        $p1Active = $p1Mulai->lte($periodeMonthEnd);
                                    } elseif ($p1Selesai) {
                                        $p1Active = $p1Selesai->gte($periodeMonthStart);
                                    }
                                    if ($p1Active) $candidatePeriodes[] = 1;

                                    // Cek P2: overlap jika tanggal_mulai_2..tanggal_selesai_2 beririsan dengan bulan ini
                                    $p2Mulai   = $kw->tanggal_mulai_2 ? \Carbon\Carbon::parse($kw->tanggal_mulai_2) : null;
                                    $p2Selesai = $kw->tanggal_selesai_2 ? \Carbon\Carbon::parse($kw->tanggal_selesai_2) : null;
                                    $p2Active  = false; // default tidak aktif jika tidak ada tanggal P2
                                    if ($p2Mulai && $p2Selesai) {
                                        $p2Active = $p2Mulai->lte($periodeMonthEnd) && $p2Selesai->gte($periodeMonthStart);
                                    } elseif ($p2Mulai) {
                                        $p2Active = $p2Mulai->lte($periodeMonthEnd);
                                    } elseif ($kw->gaji_pokok_2 !== null) {
                                        $p2Active = true; // P2 punya tarif tapi tanpa tanggal, anggap aktif
                                    }
                                    if ($p2Active) $candidatePeriodes[] = 2;

                                    // Hanya tampilkan periode yang tanggal berlakunya beririsan dengan bulan penggajian ini.
                                    // Jika karyawan memiliki tanggal di master, filter secara ketat.
                                    $hasConfiguredDates = ($p1Mulai || $p1Selesai || $p2Mulai || $p2Selesai);
                                    if ($hasConfiguredDates) {
                                        // Jangan masukkan periode kadaluarsa meskipun ada di items DB
                                        // Jika ada item di DB yang periode-nya aktif, pastikan masuk
                                    } else {
                                        // Jika tidak ada konfigurasi tanggal sama sekali di master data, tampilkan periode default yang ada
                                        if (empty($candidatePeriodes)) {
                                            $candidatePeriodes = [1, 2];
                                        }
                                        if ($payroll->items) {
                                            foreach ($payroll->items as $it) {
                                                $pNum = (int)($it->pilihan_periode ?? 1);
                                                if (!in_array($pNum, $candidatePeriodes)) {
                                                    $candidatePeriodes[] = $pNum;
                                                }
                                            }
                                        }
                                    }
                                    sort($candidatePeriodes);
                                    if (empty($candidatePeriodes)) {
                                        $candidatePeriodes = [1];
                                    }

                                    // Jika setelah filter hanya 1 periode aktif, perlakukan sebagai single-period
                                    $targetPeriodes = $candidatePeriodes;
                                    if (count($targetPeriodes) <= 1) {
                                        $hasMultiplePeriods = false;
                                        // Tentukan periode tunggal yang aktif
                                        $activePNum = $targetPeriodes[0] ?? 1;
                                        $activeItem = $existingItemsByPeriode->get($activePNum);

                                        // Update satuanRow dan tarifHarian sesuai periode aktif
                                        if ($activePNum === 2) {
                                            $satuanRow = $kw->satuan_gaji_2 ?? $kw->satuan_gaji ?? 'Harian';
                                            $gpActive = ($kw->gaji_pokok_2 !== null) ? (float)$kw->gaji_pokok_2 : (float)($kw->gaji_pokok ?? 0);
                                            $umActive = ($kw->uang_makan_2 !== null) ? (float)$kw->uang_makan_2 : (float)($kw->uang_makan ?? 0);
                                            $utActive = ($kw->uang_transport_2 !== null) ? (float)$kw->uang_transport_2 : (float)($kw->uang_transport ?? 0);
                                        } else {
                                            $satuanRow = $kw->satuan_gaji ?? 'Harian';
                                            $gpActive = (float)($kw->gaji_pokok ?? 0);
                                            $umActive = (float)($kw->uang_makan ?? 0);
                                            $utActive = (float)($kw->uang_transport ?? 0);
                                        }
                                        $tarActive = ($activeItem && $activeItem->tarif_harian_total > 0)
                                            ? (float)$activeItem->tarif_harian_total
                                            : ($gpActive + $umActive + $utActive);
                                        $tarifHarian = $tarActive;

                                        $sat = $satuanRow;
                                        $suffix = $sat === 'Per Jam' ? 'jam' : ($sat === 'Bulanan' ? 'bln' : 'hr');
                                        $fullUnit = $sat === 'Per Jam' ? 'Jam' : ($sat === 'Bulanan' ? 'Bulan' : 'Hari');
                                        $perLabel = $sat === 'Per Jam' ? '/jam' : ($sat === 'Bulanan' ? '/bln' : '/hari');

                                        $activeHk = $activeItem ? $activeItem->hari_kerja : ($payroll->hari_kerja ?? null);
                                        $activeGu = $activeItem ? (float)$activeItem->gaji_utama : (float)($payroll->gaji_utama ?? 0);

                                        // Jika ada activeItem khusus (misal P2), sesuaikan tampilan single-period agar tidak menampilkan data gabungan periode kadaluarsa
                                        if ($activeItem) {
                                            $payroll->hari_kerja = $activeHk;
                                            $gajiPokok = $activeGu;
                                            $earnings = (float)($activeItem->total_earnings > 0 ? $activeItem->total_earnings : ($gajiPokok + $totalBonus));
                                            $payroll->take_home_pay = $earnings - $totalPotongan;
                                        }

                                        $itemBreakdowns = collect([[
                                            'id' => $activeItem ? $activeItem->id : ($payroll->id ?? ''),
                                            'periode' => $activePNum,
                                            'hari_kerja' => $activeHk,
                                            'satuan' => $sat,
                                            'suffix' => $suffix,
                                            'full_unit' => $fullUnit,
                                            'per_label' => $perLabel,
                                            'tarif' => $tarActive,
                                            'gaji_utama' => $activeGu,
                                            'tanggal_mulai' => $activeItem->tanggal_mulai ?? $payroll->tanggal_mulai ?? null,
                                            'tanggal_selesai' => $activeItem->tanggal_selesai ?? $payroll->tanggal_selesai ?? null,
                                        ]]);
                                    } else {

                                    $itemBreakdowns = collect($targetPeriodes)->map(function($pNum) use ($existingItemsByPeriode, $payroll) {
                                        $kw = $payroll->karyawan;
                                        $it = $existingItemsByPeriode->get($pNum);

                                        if ($pNum === 2) {
                                            $sat = $it->satuan_gaji_2 ?? $it->satuan_gaji ?? $kw->satuan_gaji_2 ?? $kw->satuan_gaji ?? 'Harian';
                                            $gp = ($kw->gaji_pokok_2 !== null) ? (float)$kw->gaji_pokok_2 : (float)($kw->gaji_pokok ?? 0);
                                            $um = ($kw->uang_makan_2 !== null) ? (float)$kw->uang_makan_2 : (float)($kw->uang_makan ?? 0);
                                            $ut = ($kw->uang_transport_2 !== null) ? (float)$kw->uang_transport_2 : (float)($kw->uang_transport ?? 0);
                                        } else {
                                            $sat = $it->satuan_gaji ?? $kw->satuan_gaji ?? 'Harian';
                                            $gp = (float)($kw->gaji_pokok ?? 0);
                                            $um = (float)($kw->uang_makan ?? 0);
                                            $ut = (float)($kw->uang_transport ?? 0);
                                        }

                                        $tar = ($it && $it->tarif_harian_total > 0)
                                            ? (float)$it->tarif_harian_total
                                            : ($gp + $um + $ut);

                                        $hk = $it ? $it->hari_kerja : null;
                                        $gu = $it ? (float)$it->gaji_utama : 0;
                                        $itemId = $it ? $it->id : '';

                                        $suffix = $sat === 'Per Jam' ? 'jam' : ($sat === 'Bulanan' ? 'bln' : 'hr');
                                        $fullUnit = $sat === 'Per Jam' ? 'Jam' : ($sat === 'Bulanan' ? 'Bulan' : 'Hari');
                                        $perLabel = $sat === 'Per Jam' ? '/jam' : ($sat === 'Bulanan' ? '/bln' : '/hari');

                                        return [
                                            'id' => $itemId,
                                            'periode' => $pNum,
                                            'hari_kerja' => $hk,
                                            'satuan' => $sat,
                                            'suffix' => $suffix,
                                            'full_unit' => $fullUnit,
                                            'per_label' => $perLabel,
                                            'tarif' => $tar,
                                            'gaji_utama' => $gu,
                                            'tanggal_mulai' => $it->tanggal_mulai ?? null,
                                            'tanggal_selesai' => $it->tanggal_selesai ?? null,
                                        ];
                                    });
                                    } // end multi-period branch
                                } else {
                                    $tar = $payroll->tarif_harian_total > 0
                                        ? $payroll->tarif_harian_total
                                        : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));
                                    $sat = $satuanRow;
                                    $suffix = $sat === 'Per Jam' ? 'jam' : ($sat === 'Bulanan' ? 'bln' : 'hr');
                                    $fullUnit = $sat === 'Per Jam' ? 'Jam' : ($sat === 'Bulanan' ? 'Bulan' : 'Hari');
                                    $perLabel = $sat === 'Per Jam' ? '/jam' : ($sat === 'Bulanan' ? '/bln' : '/hari');

                                    $itemBreakdowns = collect([[
                                        'id' => $payroll->id,
                                        'periode' => $payroll->pilihan_periode ?? 1,
                                        'hari_kerja' => $payroll->hari_kerja,
                                        'satuan' => $sat,
                                        'suffix' => $suffix,
                                        'full_unit' => $fullUnit,
                                        'per_label' => $perLabel,
                                        'tarif' => $tar,
                                        'gaji_utama' => $payroll->gaji_utama,
                                        'tanggal_mulai' => $payroll->tanggal_mulai,
                                        'tanggal_selesai' => $payroll->tanggal_selesai,
                                    ]]);
                                }

                                $allUnits = $itemBreakdowns->pluck('satuan')->unique();
                                $isSameUnit = $allUnits->count() <= 1;
                                $primaryUnit = $itemBreakdowns->first()['satuan'] ?? $satuanRow;
                                $primarySuffix = $itemBreakdowns->first()['suffix'] ?? ($satuanRow === 'Per Jam' ? 'jam' : ($satuanRow === 'Bulanan' ? 'bln' : 'hr'));

                                if ($hasMultiplePeriods) {
                                    if ($isSameUnit) {
                                        $badgeSatuanText = $primaryUnit;
                                    } else {
                                        $badgeSatuanText = $itemBreakdowns->map(fn($ib) => 'P' . $ib['periode'] . ': ' . $ib['satuan'])->implode(' · ');
                                    }
                                } else {
                                    $badgeSatuanText = $satuanRow;
                                }

                                $waktuDisplay = $hasMultiplePeriods
                                    ? ($isSameUnit
                                        ? $payroll->hari_kerja . ' ' . $primarySuffix
                                        : $itemBreakdowns->map(fn($ib) => $ib['hari_kerja'] . ' ' . $ib['suffix'])->implode(' + '))
                                    : ($payroll->hari_kerja . ' ' . ($satuanRow === 'Per Jam' ? 'jam' : ($satuanRow === 'Bulanan' ? 'bln' : 'hr')));

                                // WhatsApp preparation
                                $cleanName = $payroll->karyawan->nama_karyawan ?? 'Karyawan';
                                $rawPhone = preg_replace('/\D/', '', $payroll->karyawan->whatsapp ?? '');
                                if ($rawPhone !== '' && substr($rawPhone, 0, 1) === '0') {
                                    $cleanPhone = '62' . substr($rawPhone, 1);
                                } elseif ($rawPhone !== '' && substr($rawPhone, 0, 2) !== '62') {
                                    $cleanPhone = '62' . $rawPhone;
                                } else {
                                    $cleanPhone = $rawPhone;
                                }
                                $hasWa = !empty($cleanPhone);
                                $periodeFmt = \App\Models\Penggajian::formatPeriode($periode);

                                $brandName = (strtolower($payroll->outlet ?? $payroll->karyawan->outlet ?? $selectedOutlet ?? 'Gaharu') === 'kejingga') ? 'Kejingga' : 'Gaharu';

                                // WA Gabungan
                                $nomGabungan = number_format($payroll->take_home_pay, 0, ',', '.');
                                $publicLinkGabungan = route('penggajian.slip.public', ['id' => $payroll->id, 'periode' => 'all', 'token' => \App\Models\Penggajian::generateSlipToken($payroll->id, 'all')]);
                                $msgGabungan = "Halo, {$cleanName}! \n\n" .
                                    "Terlampir kami sampaikan dokumen resmi Slip Gaji untuk periode {$periodeFmt} ({$brandName}).\n" .
                                    "*Total Gaji Bersih (Take Home Pay): Rp {$nomGabungan}*\n\n" .
                                    "Link Slip Gaji: {$publicLinkGabungan}\n" .
                                    "⚠️ Catatan: Jangan lupa untuk langsung unduh/simpan slip gajinya, ya, karena tautan di atas hanya aktif selama 14 hari ke depan.\n\n" .
                                    "Terima kasih atas dedikasi dan kerja keras yang telah Anda berikan untuk tim. Semoga berkah dan memotivasi kinerja ke depan.\n\n" .
                                    "Salam hangat,\n" .
                                    "Manajemen {$brandName}";
                                $waUrlGabungan = $hasWa ? 'https://api.whatsapp.com/send/?phone=' . $cleanPhone . '&text=' . rawurlencode($msgGabungan) . '&type=phone_number&app_absent=0' : '';
                                $pdfUrlGabungan = route('penggajian.pdf', ['id' => $payroll->id, 'periode' => 'all']);
                                $fileNameGabungan = 'Slip_Gaji_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cleanName) . '_' . str_replace(' ', '_', $periodeFmt) . '_Gabungan.pdf';

                                // WA Items per periode
                                $waItems = [];
                                if ($hasMultiplePeriods) {
                                    foreach ($payroll->items->sortBy('pilihan_periode') as $pItem) {
                                        $pNum = $pItem->pilihan_periode ?? 1;
                                        $takeHomePItem = $pItem->total_gaji_bersih > 0 ? $pItem->total_gaji_bersih : ($pItem->total_earnings - $pItem->total_deductions);
                                        $nomItem = number_format($takeHomePItem, 0, ',', '.');
                                        $publicLinkItem = route('penggajian.slip.public', ['id' => $pItem->id, 'periode' => $pNum, 'token' => \App\Models\Penggajian::generateSlipToken($pItem->id, $pNum)]);
                                        $msgPItem = "Halo, {$cleanName}! \n\n" .
                                            "Terlampir kami sampaikan dokumen resmi Slip Gaji untuk periode {$periodeFmt} ({$brandName}) - Periode {$pNum}.\n" .
                                            "*Total Gaji Bersih (Take Home Pay): Rp {$nomItem}*\n\n" .
                                            "Link Slip Gaji: {$publicLinkItem}\n" .
                                            "⚠️ Catatan: Jangan lupa untuk langsung unduh/simpan slip gajinya, ya, karena tautan di atas hanya aktif selama 14 hari ke depan.\n\n" .
                                            "Terima kasih atas dedikasi dan kerja keras yang telah Anda berikan untuk tim. Semoga berkah dan memotivasi kinerja ke depan.\n\n" .
                                            "Salam hangat,\n" .
                                            "Manajemen {$brandName}";
                                        $waItems[] = [
                                            'periode' => $pNum,
                                            'waUrl' => $hasWa ? 'https://api.whatsapp.com/send/?phone=' . $cleanPhone . '&text=' . rawurlencode($msgPItem) . '&type=phone_number&app_absent=0' : '',
                                            'pdfUrl' => route('penggajian.pdf', ['id' => $pItem->id, 'periode' => $pNum]),
                                            'fileName' => 'Slip_Gaji_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cleanName) . '_' . str_replace(' ', '_', $periodeFmt) . '_P' . $pNum . '.pdf',
                                        ];
                                    }
                                } else {
                                    $nomSingle = number_format($payroll->take_home_pay, 0, ',', '.');
                                    $publicLinkSingle = route('penggajian.slip.public', ['id' => $payroll->id, 'periode' => ($payroll->pilihan_periode ?? 1), 'token' => \App\Models\Penggajian::generateSlipToken($payroll->id, ($payroll->pilihan_periode ?? 1))]);
                                    $msgSingle = "Halo, {$cleanName}! \n\n" .
                                        "Terlampir kami sampaikan dokumen resmi Slip Gaji untuk periode {$periodeFmt} ({$brandName}).\n" .
                                        "*Total Gaji Bersih (Take Home Pay): Rp {$nomSingle}*\n\n" .
                                        "Link Slip Gaji: {$publicLinkSingle}\n" .
                                        "⚠️ Catatan: Jangan lupa untuk langsung unduh/simpan slip gajinya, ya, karena tautan di atas hanya aktif selama 14 hari ke depan.\n\n" .
                                        "Terima kasih atas dedikasi dan kerja keras yang telah Anda berikan untuk tim. Semoga berkah dan memotivasi kinerja ke depan.\n\n" .
                                        "Salam hangat,\n" .
                                        "Manajemen {$brandName}";
                                    $waUrlSingle = $hasWa ? 'https://api.whatsapp.com/send/?phone=' . $cleanPhone . '&text=' . rawurlencode($msgSingle) . '&type=phone_number&app_absent=0' : '';
                                    $pdfUrlSingle = route('penggajian.pdf', ['id' => $payroll->id, 'periode' => ($payroll->pilihan_periode ?? 1)]);
                                    $fileNameSingle = 'Slip_Gaji_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cleanName) . '_' . str_replace(' ', '_', $periodeFmt) . '.pdf';
                                }

                                $detailPayload = [
                                    'nama' => $payroll->karyawan->nama_karyawan ?? '-',
                                    'jabatan' => $payroll->karyawan->jabatan ?? '-',
                                    'departemen' => $payroll->karyawan->departemen ?? '-',
                                    'outlet' => $payroll->outlet ?? $selectedOutlet,
                                    'no_rekening' => $payroll->karyawan->no_rekening ?? '-',
                                    'periode' => \App\Models\Penggajian::formatPeriode($periode),
                                    'hari_kerja' => $payroll->hari_kerja,
                                    'satuan_gaji' => $satuanRow,
                                    'satuan_badge' => $badgeSatuanText,
                                    'satuan_label_header' => $hasMultiplePeriods
                                        ? ($isSameUnit ? ($primaryUnit === 'Per Jam' ? 'Jam Kerja' : ($primaryUnit === 'Bulanan' ? 'Bulan Kerja' : 'Hari Kerja')) : 'Total Kehadiran / Waktu')
                                        : ($satuanRow === 'Per Jam' ? 'Jam Kerja' : ($satuanRow === 'Bulanan' ? 'Bulan Kerja' : 'Hari Kerja')),
                                    'waktu_kerja_display' => $waktuDisplay,
                                    'tarif_harian' => number_format($tarifHarian, 0, ',', '.') ,
                                    'tarif_display' => $hasMultiplePeriods
                                        ? ($itemBreakdowns->pluck('tarif')->unique()->count() > 1 || !$isSameUnit
                                            ? $itemBreakdowns->map(fn($ib) => 'P' . $ib['periode'] . ': Rp ' . number_format($ib['tarif'], 0, ',', '.') . $ib['per_label'])->implode(' · ')
                                            : 'Rp ' . number_format($tarifHarian, 0, ',', '.') . ($satuanRow === 'Per Jam' ? '/jam' : ($satuanRow === 'Bulanan' ? '/bln' : '/hari')))
                                        : ('Rp ' . number_format($tarifHarian, 0, ',', '.') . ($satuanRow === 'Per Jam' ? '/jam' : ($satuanRow === 'Bulanan' ? '/bln' : '/hari'))),
                                    'gaji_utama' => number_format($gajiPokok, 0, ',', '.'),
                                    'jam_lembur' => $payroll->jam_lembur,
                                    'lembur' => number_format($payroll->lembur, 0, ',', '.'),
                                    'banyak_target' => $payroll->banyak_target,
                                    'bonus_target' => number_format($payroll->bonus_target, 0, ',', '.'),
                                    'banyak_merah' => $payroll->banyak_tanggal_merah,
                                    'bonus_merah' => number_format($payroll->bonus_tanggal_merah, 0, ',', '.'),
                                    'banyak_birthday' => $payroll->banyak_birthday_service,
                                    'bonus_birthday' => number_format($payroll->bonus_birthday, 0, ',', '.'),
                                    'pengembalian_deposit' => number_format($payroll->pengembalian_deposit ?? 0, 0, ',', '.'),
                                    'bonus_dll' => number_format($payroll->bonus_dll, 0, ',', '.'),
                                    'total_bonus' => number_format($totalBonus, 0, ',', '.'),
                                    'total_earnings' => number_format($earnings, 0, ',', '.'),
                                    'potongan_terlambat' => number_format($payroll->potongan_terlambat, 0, ',', '.'),
                                    'potongan_inventaris' => number_format($payroll->potongan_inventaris, 0, ',', '.'),
                                    'potongan_kasbon' => number_format($payroll->potongan_kasbon, 0, ',', '.'),
                                    'potongan_deposit' => number_format($payroll->potongan_deposit ?? 0, 0, ',', '.'),
                                    'potongan_dll' => number_format($payroll->potongan_dll, 0, ',', '.'),
                                    'catatan_potongan_dll' => $payroll->catatan_potongan_dll ?? '',
                                    'total_deductions' => number_format($totalPotongan, 0, ',', '.'),
                                    'take_home_pay' => number_format($payroll->take_home_pay, 0, ',', '.'),
                                    'is_paid' => $isPaid,
                                    'slip_url' => route('penggajian.show', $payroll->id),
                                    'has_multiple_periods' => $hasMultiplePeriods,
                                    'is_same_unit' => $isSameUnit,
                                    'item_breakdowns' => $itemBreakdowns->toArray(),
                                    'slip_gabungan_url' => route('penggajian.show', ['penggajian' => $payroll->id, 'periode' => 'all']),
                                    'slip_items' => $hasMultiplePeriods
                                        ? $payroll->items->sortBy('pilihan_periode')->map(function($it) {
                                            return [
                                                'periode' => $it->pilihan_periode ?? 1,
                                                'url' => route('penggajian.show', ['penggajian' => $it->id, 'periode' => $it->pilihan_periode ?? 1])
                                            ];
                                        })->values()->toArray()
                                        : [],
                                    'has_wa' => $hasWa,
                                    'wa_url_gabungan' => $waUrlGabungan,
                                    'pdf_url_gabungan' => $pdfUrlGabungan,
                                    'file_name_gabungan' => $fileNameGabungan,
                                    'wa_url_single' => $waUrlSingle ?? '',
                                    'pdf_url_single' => $pdfUrlSingle ?? '',
                                    'file_name_single' => $fileNameSingle ?? '',
                                    'wa_items' => $waItems,
                                ];

                                $editPayload = [
                                    'id' => $payroll->id,
                                    'karyawan_id' => $payroll->karyawan_id,
                                    'karyawan' => $payroll->karyawan,
                                    'tanggal_mulai' => $payroll->tanggal_mulai,
                                    'tanggal_selesai' => $payroll->tanggal_selesai,
                                    'hari_kerja' => $payroll->hari_kerja,
                                    'pilihan_periode' => $payroll->pilihan_periode ?? 1,
                                ];
                            @endphp
                            <tr class="payroll-row hover:bg-slate-50/80 transition-colors"
                                data-id="{{ $payroll->id }}"
                                data-karyawan-id="{{ $payroll->karyawan_id }}"
                                data-satuan="{{ $satuanRow }}"
                                data-tarif="{{ (float)$tarifHarian }}"
                                data-gaji-pokok="{{ (float)$gajiPokok }}"
                                data-bonus-total="{{ (float)$totalBonus }}"
                                data-deductions-total="{{ (float)$totalPotongan }}"
                                data-take-home-pay="{{ (float)$payroll->take_home_pay }}"
                                data-has-multiple="{{ $hasMultiplePeriods ? '1' : '0' }}"
                                data-active-periode="{{ $itemBreakdowns->first()['periode'] ?? ($payroll->pilihan_periode ?? 1) }}"
                                data-active-item-id="{{ $itemBreakdowns->first()['id'] ?? $payroll->id }}"
                                data-nama="{{ strtolower($payroll->karyawan->nama_karyawan ?? '') }}"
                                data-departemen="{{ strtolower($payroll->karyawan->departemen ?? '') }}"
                                data-jabatan="{{ strtolower($payroll->karyawan->jabatan ?? '') }}">
                                
                                {{-- 1. NO (FREEZE - ABU MUDA) --}}
                                <td class="sticky-col-num px-3 py-3 text-center text-xs font-bold whitespace-nowrap border-b border-slate-200">
                                    <span class="row-index text-slate-700 font-extrabold">{{ $index + 1 }}</span>
                                </td>

                                {{-- 2. KARYAWAN (FREEZE - HANYA NAMA & JABATAN) --}}
                                <td class="sticky-col-karyawan px-3.5 py-2.5 border-b border-slate-200">
                                    <div class="flex items-center gap-2">
                                        <i class="bi bi-grip-vertical grip-handle select-none text-slate-400 hover:text-slate-800 cursor-grab shrink-0" title="Tahan dan geser (drag & drop) untuk mengatur urutan"></i>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-extrabold text-slate-900 text-sm nama-karyawan leading-tight truncate" title="{{ $payroll->karyawan->nama_karyawan ?? '-' }}">
                                                {{ $payroll->karyawan->nama_karyawan ?? '-' }}
                                            </div>
                                            <div class="text-[11.5px] text-slate-500 font-semibold mt-1 leading-tight truncate" title="{{ $payroll->karyawan->jabatan ?? '-' }}">
                                                {{ $payroll->karyawan->jabatan ?? '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- 3. HARI KERJA (POKOK - INPUT TERPADU DENGAN P1/P2 BERTUMPUK) --}}
                                <td class="col-cat-pokok px-3 py-3 text-center whitespace-nowrap border-b border-emerald-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        @if($hasMultiplePeriods)
                                            {{-- RINCIAN P1 DAN P2 DITAMPILKAN LANGSUNG 2 BARIS BERTUMPUK DI DALAM SEL --}}
                                            <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                                @foreach($itemBreakdowns as $ib)
                                                    <div class="flex items-center gap-1 justify-center">
                                                        <span class="px-1 py-0.5 rounded text-[9px] font-black {{ $ib['periode'] == 1 ? 'bg-indigo-100 text-indigo-800' : 'bg-purple-100 text-purple-800' }} border border-slate-200">P{{ $ib['periode'] }}</span>
                                                        <input type="number" step="0.5" min="0"
                                                               class="batch-hari-kerja batch-sub-item input-hk-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-hk-item-' . $ib['id'] : '' }} hr-data-input w-14 px-1.5 py-0.5 text-xs text-center !bg-white"
                                                               data-item-id="{{ $ib['id'] }}"
                                                               data-periode="{{ $ib['periode'] }}"
                                                               data-satuan="{{ $ib['satuan'] }}"
                                                               data-tarif="{{ (float)$ib['tarif'] }}"
                                                               value="{{ ($ib['hari_kerja'] && $ib['hari_kerja'] > 0) ? (float)$ib['hari_kerja'] : '' }}"
                                                               placeholder="0"
                                                               oninput="onPayrollUnifiedRowInput(this)">
                                                        <span class="text-[10px] text-slate-600 font-bold whitespace-nowrap">{{ $ib['suffix'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1.5 justify-center whitespace-nowrap">
                                                <input type="number" step="0.5" min="0"
                                                       class="batch-hari-kerja hr-data-input w-16 px-2 py-1 text-xs"
                                                       value="{{ ($payroll->hari_kerja && $payroll->hari_kerja > 0) ? (float)$payroll->hari_kerja : '' }}"
                                                       placeholder="0"
                                                       oninput="onPayrollUnifiedRowInput(this)">
                                                <span class="text-[11px] text-slate-600 font-bold whitespace-nowrap">
                                                     {{ $satuanRow === 'Per Jam' ? 'jam' : ($satuanRow === 'Bulanan' ? 'bln' : 'hr') }}
                                                </span>
                                            </div>
                                        @endif
                                    @else
                                        @if($hasMultiplePeriods)
                                            <div class="inline-flex flex-col gap-1 justify-center whitespace-nowrap w-full">
                                                @foreach($itemBreakdowns as $ib)
                                                    <div class="flex items-center gap-1.5 justify-center text-xs">
                                                        <span class="px-1 py-0.5 rounded text-[9px] font-black {{ $ib['periode'] == 1 ? 'bg-indigo-50 text-indigo-700' : 'bg-purple-50 text-purple-700' }} border border-slate-200">P{{ $ib['periode'] }}</span>
                                                        <span class="font-extrabold text-slate-900">{{ (float)$ib['hari_kerja'] }}</span>
                                                        <span class="text-[10.5px] text-slate-500 font-medium">{{ $ib['suffix'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="whitespace-nowrap">
                                                <span class="text-xs font-black text-slate-900">{{ $payroll->hari_kerja }}</span>
                                                <span class="text-[11px] text-slate-600 font-semibold">
                                                    {{ $satuanRow === 'Per Jam' ? ' jam' : ($satuanRow === ' Bulanan' ? ' bln' : ' hr') }}
                                                </span>
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 4. TARIF SATUAN (POKOK - ACCOUNTING STYLE - BERTUMPUK JIKA MULTI-PERIODE) --}}
                                <td class="col-cat-pokok px-3 py-3 border-b border-emerald-100">
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-1.5 w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <div class="accounting-cell text-[11px] font-bold text-slate-800">
                                                        <span class="ac-curr">
                                                            <span class="px-1 py-0.2 rounded text-[8.5px] font-black {{ $ib['periode'] == 1 ? 'bg-indigo-50 text-indigo-700' : 'bg-purple-50 text-purple-700' }} border border-slate-200 mr-1">P{{ $ib['periode'] }}</span>Rp
                                                        </span>
                                                        <span class="ac-val">{{ number_format($ib['tarif'], 0, ',', '.') }}</span>
                                                    </div>
                                                    <div class="text-[9px] text-slate-400 font-medium text-right">{{ $ib['per_label'] }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-cell text-xs font-bold text-slate-800">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($tarifHarian, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="text-[9.5px] text-slate-500 font-medium text-right mt-0.5">
                                            {{ $satuanRow === 'Per Jam' ? '/jam' : ($satuanRow === 'Bulanan' ? '/bln' : '/hari') }}
                                        </div>
                                    @endif
                                </td>

                                {{-- 5. SUBTOTAL POKOK (POKOK SUBTOTAL - CAT DIVIDER POKOK + RINCIAN P1/P2 BERTUMPUK) --}}
                                <td class="col-cat-pokok-subtotal px-3 py-3 border-b border-emerald-200 cat-divider-pokok">
                                    <div class="accounting-cell text-xs font-extrabold text-emerald-950 row-gaji-pokok-cell">
                                        <span class="ac-curr">Rp</span>
                                        <span class="ac-val">{{ number_format($gajiPokok, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-0.5 mt-1 pt-1 border-t border-emerald-100/70">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-cell text-[10px] text-slate-600 font-bold">
                                                    <span class="ac-curr">P{{ $ib['periode'] }}: Rp</span>
                                                    <span class="ac-val sub-gp-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'sub-gp-item-' . $ib['id'] : '' }}">{{ number_format($ib['gaji_utama'], 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- 6. JAM LEMBUR (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus px-2 py-2 border-b border-amber-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="flex items-center gap-1 justify-center">
                                            <input type="number" step="0.5" min="0"
                                                   class="batch-jam-lembur hr-data-input w-full px-2 py-1 text-xs text-center !bg-white"
                                                   value="{{ ($payroll->jam_lembur && $payroll->jam_lembur > 0) ? (float)$payroll->jam_lembur : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                            <span class="text-[10px] text-amber-900 font-bold">jam</span>
                                        </div>
                                        <div class="sub-lembur-text accounting-cell text-[10px] text-amber-900 font-bold mt-1 px-1 {{ ($payroll->jam_lembur ?? 0) > 0 ? '' : 'hidden' }}">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format(($payroll->jam_lembur ?? 0) * 10000, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <div class="accounting-cell font-bold text-slate-800 text-xs">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($payroll->lembur, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 font-medium text-right">{{ $payroll->jam_lembur }} jam</div>
                                    @endif
                                </td>

                                {{-- 7. BONUS TARGET (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus px-2 py-2 border-b border-amber-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        @if($satuanRow === 'Harian')
                                            <div class="flex items-center gap-1 justify-center">
                                                <input type="number" step="1" min="0"
                                                       class="batch-banyak-target hr-data-input w-full px-2 py-1 text-xs text-center !bg-white"
                                                       value="{{ ($payroll->banyak_target && $payroll->banyak_target > 0) ? (int)$payroll->banyak_target : '' }}"
                                                       placeholder="0"
                                                       oninput="onPayrollUnifiedRowInput(this)">
                                                <span class="text-[10px] text-amber-900 font-bold">kali</span>
                                            </div>
                                            <div class="sub-target-text accounting-cell text-[10px] text-amber-900 font-bold mt-1 px-1 {{ ($payroll->banyak_target ?? 0) > 0 ? '' : 'hidden' }}">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format(($payroll->banyak_target ?? 0) * $tarifHarian, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="accounting-input-wrap">
                                                <span class="ac-input-prefix">Rp</span>
                                                <input type="text"
                                                       class="batch-input-rupiah batch-bonus-target hr-data-input"
                                                       value="{{ $payroll->bonus_target > 0 ? number_format($payroll->bonus_target, 0, ',', '.') : '' }}"
                                                       placeholder="0"
                                                       oninput="onPayrollUnifiedRowInput(this)">
                                            </div>
                                            <input type="hidden" class="batch-catatan-target" value="{{ $payroll->catatan_bonus_target ?? '' }}">
                                        @endif
                                    @else
                                        <div class="accounting-cell font-bold text-slate-800 text-xs">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($payroll->bonus_target, 0, ',', '.') }}</span>
                                        </div>
                                        @if($satuanRow === 'Harian')
                                            <div class="text-[10px] text-slate-500 font-medium text-right">{{ $payroll->banyak_target }}x target</div>
                                        @else
                                            <div class="text-[10px] text-slate-500 font-medium truncate max-w-[100px] ml-auto text-right">{{ !empty($payroll->catatan_bonus_target) ? $payroll->catatan_bonus_target : 'Manual' }}</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 8. TANGGAL MERAH (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus px-2 py-2 border-b border-amber-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        @if($satuanRow === 'Harian')
                                            <div class="flex items-center gap-1 justify-center">
                                                <input type="number" step="1" min="0"
                                                       class="batch-banyak-merah hr-data-input w-full px-2 py-1 text-xs text-center !bg-white"
                                                       value="{{ ($payroll->banyak_tanggal_merah && $payroll->banyak_tanggal_merah > 0) ? (int)$payroll->banyak_tanggal_merah : '' }}"
                                                       placeholder="0"
                                                       oninput="onPayrollUnifiedRowInput(this)">
                                                <span class="text-[10px] text-amber-900 font-bold">hari</span>
                                            </div>
                                            <div class="sub-merah-text accounting-cell text-[10px] text-amber-900 font-bold mt-1 px-1 {{ ($payroll->banyak_tanggal_merah ?? 0) > 0 ? '' : 'hidden' }}">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format(($payroll->banyak_tanggal_merah ?? 0) * $tarifHarian, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="accounting-input-wrap">
                                                <span class="ac-input-prefix">Rp</span>
                                                <input type="text"
                                                       class="batch-input-rupiah batch-bonus-merah hr-data-input"
                                                       value="{{ $payroll->bonus_tanggal_merah > 0 ? number_format($payroll->bonus_tanggal_merah, 0, ',', '.') : '' }}"
                                                       placeholder="0"
                                                       oninput="onPayrollUnifiedRowInput(this)">
                                            </div>
                                            <input type="hidden" class="batch-catatan-merah" value="{{ $payroll->catatan_bonus_tanggal_merah ?? '' }}">
                                        @endif
                                    @else
                                        <div class="accounting-cell font-bold text-slate-800 text-xs">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($payroll->bonus_tanggal_merah, 0, ',', '.') }}</span>
                                        </div>
                                        @if($satuanRow === 'Harian')
                                            <div class="text-[10px] text-slate-500 font-medium text-right">{{ $payroll->banyak_tanggal_merah }}x hadir</div>
                                        @else
                                            <div class="text-[10px] text-slate-500 font-medium truncate max-w-[100px] ml-auto text-right">{{ !empty($payroll->catatan_bonus_tanggal_merah) ? $payroll->catatan_bonus_tanggal_merah : 'Manual' }}</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 9. BIRTHDAY SERVICE (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus px-2 py-2 border-b border-amber-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="flex items-center gap-1 justify-center">
                                            <input type="number" step="1" min="0"
                                                   class="batch-banyak-birthday hr-data-input w-full px-2 py-1 text-xs text-center !bg-white"
                                                   value="{{ ($payroll->banyak_birthday_service && $payroll->banyak_birthday_service > 0) ? (int)$payroll->banyak_birthday_service : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                            <span class="text-[10px] text-amber-900 font-bold">srv</span>
                                        </div>
                                        <div class="sub-birthday-text accounting-cell text-[10px] text-amber-900 font-bold mt-1 px-1 {{ ($payroll->banyak_birthday_service ?? 0) > 0 ? '' : 'hidden' }}">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format(($payroll->banyak_birthday_service ?? 0) * 5000, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <div class="accounting-cell font-bold text-slate-800 text-xs">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($payroll->bonus_birthday, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 font-medium text-right">{{ $payroll->banyak_birthday_service }}x</div>
                                    @endif
                                </td>

                                {{-- 10. PENGEMBALIAN DEPOSIT (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus px-2 py-2 border-b border-amber-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-pengembalian-deposit hr-data-input !text-emerald-800"
                                                   value="{{ $payroll->pengembalian_deposit > 0 ? number_format($payroll->pengembalian_deposit, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                        @if(($payroll->saldo_deposit ?? 0) > 0)
                                            <button type="button"
                                                    onclick="isiDepositOtomatis(this, {{ (float)$payroll->saldo_deposit }})"
                                                    class="mt-1 text-[9px] font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 rounded cursor-pointer transition-all inline-flex items-center gap-1 w-full justify-end"
                                                    title="Klik untuk mengisi otomatis dari saldo deposit tersimpan">
                                                <span>&#8629; Saldo: {{ number_format($payroll->saldo_deposit, 0, ',', '.') }}</span>
                                            </button>
                                        @endif
                                    @else
                                        @if($payroll->pengembalian_deposit > 0)
                                            <div class="accounting-cell font-bold text-emerald-800 text-xs">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->pengembalian_deposit, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="text-center font-bold text-slate-400 text-xs">-</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 11. BONUS LAIN (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus px-2 py-2 border-b border-amber-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-bonus-dll hr-data-input"
                                                   value="{{ $payroll->bonus_dll > 0 ? number_format($payroll->bonus_dll, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @else
                                        @if($payroll->bonus_dll > 0)
                                            <div class="accounting-cell font-bold text-slate-700 text-xs">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->bonus_dll, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="text-center font-bold text-slate-400 text-xs">-</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 12. SUBTOTAL BONUS (BONUS SUBTOTAL - KUNING PENEGAS + BATAS TEGAS + ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus-subtotal px-3 py-3 font-black text-amber-900 text-xs row-total-bonus-cell border-b border-amber-200 cat-divider-bonus">
                                    @if($totalBonus > 0)
                                        <div class="accounting-cell">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($totalBonus, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <div class="text-center font-bold text-amber-700/60 text-xs">-</div>
                                    @endif
                                </td>

                                {{-- 13. KETERLAMBATAN (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan px-3 py-3 border-b border-rose-100">
                                    <input type="hidden" class="potongan-terlambat-raw" value="{{ (float)$payroll->potongan_terlambat }}">
                                    @if($payroll->potongan_terlambat > 0)
                                        <div class="accounting-cell font-bold text-rose-700 text-xs">
                                            <span class="ac-curr">- Rp</span>
                                            <span class="ac-val">{{ number_format($payroll->potongan_terlambat, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="text-[9px] text-slate-500 font-semibold mt-0.5 text-right">Otomatis Absensi</div>
                                    @else
                                        <div class="text-center text-slate-400 font-bold text-xs">-</div>
                                    @endif
                                </td>

                                {{-- 14. KERUSAKAN INVENTARIS (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan px-2 py-2 border-b border-rose-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-inventaris hr-data-input"
                                                   value="{{ $payroll->potongan_inventaris > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @else
                                        @if($payroll->potongan_inventaris > 0)
                                            <div class="accounting-cell font-bold text-slate-700 text-xs">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->potongan_inventaris, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="text-center font-bold text-slate-400 text-xs">-</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 15. KASBON / PINJAMAN (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan px-2 py-2 border-b border-rose-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-kasbon hr-data-input"
                                                   value="{{ $payroll->potongan_kasbon > 0 ? number_format($payroll->potongan_kasbon, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @else
                                        @if($payroll->potongan_kasbon > 0)
                                            <div class="accounting-cell font-bold text-slate-700 text-xs">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->potongan_kasbon, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="text-center font-bold text-slate-400 text-xs">-</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 16. POTONGAN DEPOSIT (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan px-2 py-2 border-b border-rose-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-deposit hr-data-input"
                                                   value="{{ $payroll->potongan_deposit > 0 ? number_format($payroll->potongan_deposit, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   title="Potongan deposit untuk karyawan baru"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @else
                                        @if($payroll->potongan_deposit > 0)
                                            <div class="accounting-cell font-bold text-slate-700 text-xs">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->potongan_deposit, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="text-center font-bold text-slate-400 text-xs">-</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 17. POTONGAN LAIN & KETERANGAN (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan px-2 py-2 border-b border-rose-100 {{ !$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval') ? 'hr-input-cell' : '' }}">
                                    @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-dll hr-data-input"
                                                   value="{{ $payroll->potongan_dll > 0 ? number_format($payroll->potongan_dll, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                        <input type="text"
                                               class="batch-catatan-potongan-dll w-full text-left bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[10px] font-medium text-slate-700 focus:bg-white focus:outline-none focus:ring-1 focus:ring-rose-400 mt-1 placeholder:text-slate-400 placeholder:italic"
                                               value="{{ $payroll->catatan_potongan_dll ?? '' }}"
                                               placeholder="Keterangan..."
                                               title="Keterangan / rincian potongan lain-lain">
                                    @else
                                        @if($payroll->potongan_dll > 0)
                                            <div class="accounting-cell font-bold text-slate-700 text-xs">
                                                <span class="ac-curr">Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->potongan_dll, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <div class="text-center font-bold text-slate-400 text-xs">-</div>
                                        @endif
                                        @if($payroll->catatan_potongan_dll)
                                            <div class="text-[9.5px] text-slate-500 font-medium truncate max-w-[110px] ml-auto text-right">{{ $payroll->catatan_potongan_dll }}</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 18. SUBTOTAL POTONGAN (POTONGAN SUBTOTAL - MERAH PENEGAS + BATAS TEGAS + ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan-subtotal px-3 py-3 font-black text-rose-900 text-xs row-total-deductions-cell border-b border-rose-200 cat-divider-potongan">
                                    @if($totalPotongan > 0)
                                        <div class="accounting-cell">
                                            <span class="ac-curr">- Rp</span>
                                            <span class="ac-val">{{ number_format($totalPotongan, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <div class="text-center font-bold text-rose-700/60 text-xs">-</div>
                                    @endif
                                </td>

                                {{-- 19. GAJI BERSIH (TAKE HOME PAY - MINT/EMERALD + ACCOUNTING STYLE) --}}
                                <td class="col-cat-nett px-3 py-3 border-b border-slate-100">
                                    <div class="accounting-cell text-sm font-black text-slate-900 row-take-home-pay-cell">
                                        <span class="ac-curr">Rp</span>
                                        <span class="ac-val">{{ number_format($payroll->take_home_pay, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <span class="block text-[9.5px] font-bold text-teal-800 mt-0.5 text-right">Semua Periode</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap border-b border-slate-100">
                                    <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">

                                        {{-- TOMBOL DETAIL POPUP --}}
                                        <button type="button"
                                                @click="activeDetail = {{ json_encode($detailPayload) }}; openDetailModal = true;"
                                                class="inline-flex items-center gap-1.5 bg-white hover:bg-slate-100 text-slate-800 text-xs font-black px-3 py-1.5 rounded-lg transition-all border-2 border-slate-300 hover:border-slate-400 shadow-sm cursor-pointer whitespace-nowrap"
                                                title="Lihat rincian lengkap gaji bersih">
                                            &#128065; Detail
                                        </button>

                                        @if($isPaid)
                                            <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 border border-emerald-300 text-xs font-bold px-2.5 py-1 rounded-full whitespace-nowrap">
                                                &#10003; Terbayar
                                            </span>
                                        @else
                                            <form action="{{ route('penggajian.bayar', $payroll->id) }}" method="POST"
                                                  onsubmit="return confirm('{{ addslashes($confirmMsg) }}')">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-sm transition-all whitespace-nowrap cursor-pointer">
                                                    &#128179; Bayar
                                                </button>
                                            </form>
                                        @endif

                                        {{-- KEBAB DROPDOWN --}}
                                        <div class="relative kebab-wrapper" style="position:relative;">
                                            <button onclick="toggleKebab(this)"
                                                    class="text-slate-600 hover:text-slate-900 hover:bg-slate-100 px-2 py-1 rounded-lg text-xs font-bold transition-colors border border-slate-200 cursor-pointer"
                                                    title="Aksi lain (Slip, Edit, Hapus)">
                                                &bull;&bull;&bull;
                                            </button>
                                            <div class="kebab-menu hidden absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-50 text-left" style="min-width:190px;">
                                                @if($hasMultiplePeriods)
                                                    <div class="px-3 py-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider bg-slate-50 border-b border-slate-100">
                                                        Cetak Slip Gaji
                                                    </div>
                                                    <a href="{{ route('penggajian.show', ['penggajian' => $payroll->id, 'periode' => 'all']) }}"
                                                       class="flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-50 transition-colors">
                                                        <span>&#128220;</span> Cetak Slip (Gabungan)
                                                    </a>
                                                    @foreach($payroll->items->sortBy('pilihan_periode') as $pItem)
                                                        <a href="{{ route('penggajian.show', ['penggajian' => $pItem->id, 'periode' => $pItem->pilihan_periode ?? 1]) }}"
                                                           class="flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors pl-6">
                                                            <span>&#129534;</span> Slip Periode {{ $pItem->pilihan_periode ?? 1 }}
                                                        </a>
                                                    @endforeach
                                                    <div class="border-t border-slate-100 my-1"></div>
                                                @else
                                                    <a href="{{ route('penggajian.show', $payroll->id) }}"
                                                       class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                                        <span>&#129534;</span> Cetak Slip
                                                    </a>
                                                @endif

                                                {{-- KIRIM SLIP KE WHATSAPP --}}
                                                <div class="border-t border-slate-100 my-1"></div>
                                                <div class="px-3 py-1 text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider bg-emerald-50 border-b border-emerald-100">
                                                    Kirim ke WhatsApp
                                                </div>
                                                @if(!$hasWa)
                                                    <div class="px-3.5 py-1.5 text-[11px] text-slate-400 italic">
                                                        Nomor WA belum diisi
                                                    </div>
                                                @elseif($hasMultiplePeriods)
                                                    <button type="button"
                                                            onclick="sendSlipWa('{{ $pdfUrlGabungan }}', '{{ $waUrlGabungan }}', '{{ $fileNameGabungan }}')"
                                                            class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-colors text-left cursor-pointer">
                                                        <span>📲</span> Kirim Slip (Gabungan)
                                                    </button>
                                                    @foreach($waItems as $wi)
                                                        <button type="button"
                                                                onclick="sendSlipWa('{{ $wi['pdfUrl'] }}', '{{ $wi['waUrl'] }}', '{{ $wi['fileName'] }}')"
                                                                class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors text-left cursor-pointer pl-6">
                                                            <span>📲</span> Kirim Periode {{ $wi['periode'] }}
                                                        </button>
                                                    @endforeach
                                                @else
                                                    <button type="button"
                                                            onclick="sendSlipWa('{{ $pdfUrlSingle }}', '{{ $waUrlSingle }}', '{{ $fileNameSingle }}')"
                                                            class="w-full flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-colors text-left cursor-pointer">
                                                        <span>📲</span> Kirim Slip ke WA
                                                    </button>
                                                @endif
                                                <div class="border-t border-slate-100 my-1"></div>

                                                @if(!$isPaid)
                                                    @if($hasMultiplePeriods)
                                                        <div class="px-3 py-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider bg-slate-50 border-b border-slate-100">
                                                            Edit Gaji Pokok
                                                        </div>
                                                        @foreach($payroll->items->sortBy('pilihan_periode') as $pItem)
                                                            @php
                                                                $pItemPayload = [
                                                                    'id' => $pItem->id,
                                                                    'karyawan_id' => $pItem->karyawan_id,
                                                                    'karyawan' => $payroll->karyawan,
                                                                    'tanggal_mulai' => $pItem->tanggal_mulai,
                                                                    'tanggal_selesai' => $pItem->tanggal_selesai,
                                                                    'hari_kerja' => $pItem->hari_kerja,
                                                                    'pilihan_periode' => $pItem->pilihan_periode ?? 1,
                                                                ];
                                                            @endphp
                                                            <button type="button" @click="openEditModal({{ json_encode($pItemPayload) }})"
                                                                    class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-50 transition-colors text-left cursor-pointer pl-6">
                                                                <span>&#128197;</span> Edit Periode {{ $pItem->pilihan_periode ?? 1 }}
                                                            </button>
                                                        @endforeach
                                                        <div class="border-t border-slate-100 my-1"></div>
                                                    @else
                                                        <button type="button" @click="openEditModal({{ json_encode($editPayload) }})"
                                                                class="w-full flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-amber-800 hover:bg-amber-50 transition-colors text-left cursor-pointer">
                                                            <span>&#128197;</span> Edit Gaji Pokok
                                                        </button>
                                                        <button type="button" @click="openCreateModalForKaryawan({{ $payroll->karyawan_id }}, {{ ($payroll->pilihan_periode ?? 1) == 1 ? 2 : 1 }})"
                                                                class="w-full flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50 transition-colors text-left cursor-pointer">
                                                            <span>&#43;</span> Tambah Periode Lain
                                                        </button>
                                                    @endif

                                                    <a href="{{ route('penggajian.bonus.edit', $payroll->id) }}"
                                                       class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-amber-800 hover:bg-amber-50 transition-colors">
                                                        <span>&#11088;</span> Edit Bonus &amp; Lembur
                                                    </a>
                                                    <a href="{{ route('penggajian.potongan.edit', $payroll->id) }}"
                                                       class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50 transition-colors">
                                                        <span>&#9986;</span> Edit Potongan
                                                    </a>

                                                    @if($currentStatus == 'draft')
                                                        @if($hasMultiplePeriods)
                                                            <div class="border-t border-slate-100 my-1"></div>
                                                            @foreach($payroll->items->sortBy('pilihan_periode') as $pItem)
                                                                <form action="{{ route('penggajian.destroy', $pItem->id) }}" method="POST"
                                                                      onsubmit="return confirm('Hapus data penggajian Periode {{ $pItem->pilihan_periode ?? 1 }} untuk karyawan ini?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit"
                                                                            class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                                                        <span>&#128465;</span> Hapus Periode {{ $pItem->pilihan_periode ?? 1 }}
                                                                    </button>
                                                                </form>
                                                            @endforeach
                                                        @else
                                                            <form action="{{ route('penggajian.destroy', $payroll->id) }}" method="POST"
                                                                  onsubmit="return confirm('Hapus data penggajian karyawan ini?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                        class="w-full flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                                                    <span>&#128465;</span> Hapus
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="20" class="px-6 py-16 text-center">
                                    <div class="text-3xl mb-2">&#128203;</div>
                                    <div class="font-bold text-slate-700 text-xs">Belum ada data karyawan</div>
                                    <div class="text-[11px] text-slate-500 mt-1 font-medium">Klik "Auto-Fill Karyawan" atau "+ Input Gaji Manual" di atas.</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- ========================================================================= --}}
        {{-- POPUP MODAL 1: INPUT & EDIT GAJI POKOK (MODAL POP-UP) --}}
        {{-- ========================================================================= --}}
        <template x-if="openGajiPokokModal">
            <div x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99998; display: flex; align-items: center; justify-content: center; padding: 16px;">
                
                {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); cursor: pointer;"
                     @click="openGajiPokokModal = false"></div>

                {{-- MODAL BOX CENTERED --}}
                <div style="position: relative; background: #ffffff; border-radius: 16px; width: 100%; max-width: 620px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; z-index: 99999; max-height: 100%; display: flex; flex-direction: column; overflow: hidden;"
                     @click.stop>
                    
                    {{-- MODAL HEADER --}}
                    <div style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                        <div>
                            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;" x-text="modalTitle"></h3>
                            <p style="font-size: 11.5px; color: #475569; font-weight: 600; margin: 2px 0 0 0;">
                                Periode {{ \App\Models\Penggajian::formatPeriode($periode) }} &bull; Outlet {{ $selectedOutlet }}
                            </p>
                        </div>
                        <button type="button" @click="openGajiPokokModal = false"
                                style="background: none; border: none; color: #475569; font-size: 24px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0 4px;"
                                onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#475569'">
                            &times;
                        </button>
                    </div>

                    {{-- MODAL BODY (Scrollable) --}}
                    <div style="padding: 18px 20px; font-size: 12px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto;">
                        <form :action="formAction" method="POST" id="formGajiPokokModal">
                            @csrf
                            <template x-if="isEditMode">
                                <input type="hidden" name="_method" value="PUT">
                            </template>
                            <input type="hidden" name="periode" value="{{ $periode }}">

                            {{-- 1. INFORMASI KARYAWAN & RENTANG TANGGAL --}}
                            <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff; margin-bottom: 12px;">
                                <div style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                    <span>&#128100;</span> Informasi Karyawan &amp; Tanggal
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                                    {{-- Mode Edit: Info Karyawan Terpilih --}}
                                    <template x-if="isEditMode && selectedKaryawanObj">
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; margin-bottom: 5px;">
                                                Karyawan Terpilih
                                            </label>
                                            <div style="background: #fffbf5; border: 1.5px solid #fcd34d; border-radius: 10px; padding: 10px 14px;">
                                                <div style="font-size: 14px; font-weight: 800; color: #0f172a;" x-text="selectedKaryawanObj.nama_karyawan"></div>
                                                <div style="font-size: 11.5px; color: #78350f; font-weight: 700; margin-top: 2px;" x-text="selectedKaryawanObj.jabatan || '-'"></div>
                                                <div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
                                                    <span style="font-size: 10.5px; font-weight: 700; background: #fef3c7; color: #78350f; border: 1px solid #fcd34d; border-radius: 4px; padding: 1px 6px;"
                                                          x-text="selectedKaryawanObj.departemen || 'Umum'"></span>
                                                    <span style="font-size: 10.5px; font-weight: 700; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 4px; padding: 1px 6px;"
                                                          x-text="'Rek: ' + (selectedKaryawanObj.no_rekening || '-')"></span>
                                                </div>
                                            </div>
                                            <input type="hidden" name="karyawan_id" :value="karyawanId">
                                        </div>
                                    </template>

                                    {{-- Mode Create: Dropdown Karyawan --}}
                                    <template x-if="!isEditMode">
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; margin-bottom: 5px;">
                                                Pilih Karyawan <span style="color: #dc2626;">*</span>
                                            </label>
                                            <select name="karyawan_id" x-model="karyawanId" @change="onKaryawanChange()" required
                                                    style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; font-weight: 600; color: #0f172a; background: #ffffff;">
                                                <option value="">-- Pilih Karyawan (Outlet {{ $selectedOutlet }}) --</option>
                                                @foreach($availableKaryawans as $ak)
                                                    <option value="{{ $ak->id }}">{{ $ak->nama_karyawan }} &mdash; {{ $ak->jabatan ?? 'Karyawan' }} ({{ $ak->departemen ?? 'Umum' }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </template>

                                    {{-- Rentang Tanggal Mulai & Selesai --}}
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; margin-bottom: 5px;">
                                                Tanggal Mulai Slip
                                            </label>
                                            <input type="date" name="tanggal_mulai" x-model="tanggalMulai" @change="onDateChange()"
                                                   style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 7px 10px; font-size: 12.5px; font-weight: 600; color: #0f172a; background: #ffffff;">
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; margin-bottom: 5px;">
                                                Tanggal Selesai Slip
                                            </label>
                                            <input type="date" name="tanggal_selesai" x-model="tanggalSelesai" @change="onDateChange()"
                                                   style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 7px 10px; font-size: 12.5px; font-weight: 600; color: #0f172a; background: #ffffff;">
                                        </div>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; font-weight: 500;"
                                         x-text="currentSatuan() === 'Per Jam' ? 'Rentang tanggal slip gaji karyawan (untuk satuan Per Jam, input jam kerja pada kolom di bawah).' : (currentSatuan() === 'Bulanan' ? 'Rentang tanggal slip gaji karyawan untuk periode bulanan.' : 'Isi rentang tanggal untuk menghitung hari kerja secara otomatis.')">
                                        Isi rentang tanggal untuk menghitung hari kerja secara otomatis.
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="pilihan_periode" :value="pilihanPeriode">

                            {{-- NOTE PENYESUAIAN TARIF (PERIODE SELECTOR) --}}
                            <div x-show="hasP2" style="display: none; margin-bottom: 12px;">
                                <div style="font-size: 11px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 6px;">
                                    Pilih Tarif Periode
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                    {{-- Kartu Periode 1 --}}
                                    <div @click="selectPeriode(1)"
                                         :style="pilihanPeriode == 1 ? 'border: 2px solid #7A4517; background: #fffbf5; box-shadow: 0 0 0 3px rgba(122,69,23,0.1);' : 'border: 1.5px solid #cbd5e1; background: #f8fafc;'"
                                         style="border-radius: 10px; padding: 10px 12px; cursor: pointer; transition: all 0.15s;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span style="font-size: 11px; font-weight: 800; color: #334155; text-transform: uppercase;">Periode 1</span>
                                            <span x-show="pilihanPeriode == 1" style="font-size: 9px; font-weight: 800; color: #7A4517; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 4px; padding: 1px 5px;">✓ DIPILIH</span>
                                        </div>
                                        <div style="font-size: 11.5px; font-weight: 800; color: #0f172a;" x-text="formatRupiah(tarif1) + satuanUnit(satuanGaji1)"></div>
                                        <div style="font-size: 10px; color: #64748b; font-weight: 600; margin-top: 2px;" x-text="satuanGaji1"></div>
                                    </div>
                                    {{-- Kartu Periode 2 --}}
                                    <div @click="selectPeriode(2)"
                                         :style="pilihanPeriode == 2 ? 'border: 2px solid #0369a1; background: #f0f9ff; box-shadow: 0 0 0 3px rgba(3,105,161,0.1);' : 'border: 1.5px solid #cbd5e1; background: #f8fafc;'"
                                         style="border-radius: 10px; padding: 10px 12px; cursor: pointer; transition: all 0.15s;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span style="font-size: 11px; font-weight: 800; color: #334155; text-transform: uppercase;">Periode 2</span>
                                            <span x-show="pilihanPeriode == 2" style="font-size: 9px; font-weight: 800; color: #0369a1; background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 4px; padding: 1px 5px;">✓ DIPILIH</span>
                                        </div>
                                        <div style="font-size: 11.5px; font-weight: 800; color: #0f172a;" x-text="formatRupiah(tarif2) + satuanUnit(satuanGaji2)"></div>
                                        <div style="font-size: 10px; color: #64748b; font-weight: 600; margin-top: 2px;" x-text="satuanGaji2"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- 2. MASTER TARIF (GRID 4) --}}
                            <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #f8fafc; margin-bottom: 12px;">
                                <div style="font-size: 11px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 8px;"
                                     x-text="'Rincian Tarif ' + currentSatuanLabel() + ' Master'">
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
                                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; text-align: right;">
                                        <div style="font-size: 9.5px; font-weight: 700; color: #475569; text-transform: uppercase;">Gaji Pokok</div>
                                        <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin-top: 2px;" x-text="formatRupiah(currentGp)"></div>
                                    </div>
                                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; text-align: right;">
                                        <div style="font-size: 9.5px; font-weight: 700; color: #475569; text-transform: uppercase;">Uang Makan</div>
                                        <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin-top: 2px;" x-text="formatRupiah(currentMakan)"></div>
                                    </div>
                                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; text-align: right;">
                                        <div style="font-size: 9.5px; font-weight: 700; color: #475569; text-transform: uppercase;">Transport</div>
                                        <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin-top: 2px;" x-text="formatRupiah(currentTransport)"></div>
                                    </div>
                                    <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 8px 10px; text-align: right;">
                                        <div style="font-size: 9.5px; font-weight: 800; color: #065f46; text-transform: uppercase;"
                                             x-text="'Total ' + satuanUnit(currentSatuan()).replace('/', '/ ')"></div>
                                        <div style="font-size: 12.5px; font-weight: 900; color: #047857; margin-top: 2px;" x-text="formatRupiah(currentTarifTotal)"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- 3. PRESENSI & JUMLAH KERJA --}}
                            <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff; margin-bottom: 12px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; margin-bottom: 5px;"
                                               x-text="currentSatuan() === 'Per Jam' ? 'Jumlah Jam Kerja' : (currentSatuan() === 'Bulanan' ? 'Jumlah Bulan' : 'Jumlah Hari Kerja')">
                                            Jumlah Hari Kerja
                                        </label>
                                        <span style="color: #dc2626; margin-left: 2px;">*</span>
                                        <input type="number" name="hari_kerja" x-model="hariKerja" @input="onHariKerjaChange()" min="0" step="0.5" required
                                               style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 15px; font-weight: 800; color: #0f172a; background: #ffffff;">
                                        <div style="font-size: 10.5px; color: #64748b; font-weight: 500; margin-top: 3px;"
                                             x-text="currentSatuan() === 'Per Jam' ? 'Otomatis / input manual (jam)' : (currentSatuan() === 'Bulanan' ? 'Gaji bulanan penuh' : 'Otomatis / input manual')">
                                            Otomatis / input manual
                                        </div>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 700; color: #1e293b; text-transform: uppercase; margin-bottom: 5px;"
                                               x-text="currentSatuan() === 'Per Jam' ? 'Gaji Utama (Jam × Tarif)' : (currentSatuan() === 'Bulanan' ? 'Gaji Utama (Bulanan)' : 'Gaji Pokok Utama (Hari × Tarif)')">
                                            Gaji Pokok Utama
                                        </label>
                                        <input type="text" readonly :value="formatRupiah(gajiUtama)"
                                               style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 15px; font-weight: 800; color: #0f172a; background: #f1f5f9; text-align: right;">
                                    </div>
                                </div>
                            </div>

                            {{-- 4. SUMMARY GAJI POKOK --}}
                            <div style="background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%); border: 1.5px solid #fcd34d; border-radius: 12px; padding: 12px 16px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; text-align: center;">
                                    <div>
                                        <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase;"
                                             x-text="'Tarif ' + currentSatuanLabel() + ' Total'">
                                            Tarif Total
                                        </div>
                                        <div style="font-size: 14px; font-weight: 800; color: #0284c7; margin-top: 2px;" x-text="formatRupiah(currentTarifTotal)"></div>
                                    </div>
                                    <div style="border-left: 1px solid #fcd34d; border-right: 1px solid #fcd34d;">
                                        <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase;"
                                             x-text="satuanKerjaLabel()">
                                            Hari Kerja
                                        </div>
                                        <div style="font-size: 14px; font-weight: 800; color: #1e293b; margin-top: 2px;"
                                             x-text="(hariKerja || 0) + (currentSatuan() === 'Per Jam' ? ' Jam' : (currentSatuan() === 'Bulanan' ? ' Bulan' : ' Hari'))"></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase;">Gaji Pokok Utama</div>
                                        <div style="font-size: 16px; font-weight: 900; color: #7A4517; margin-top: 2px;" x-text="formatRupiah(gajiUtama)"></div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- MODAL FOOTER --}}
                    <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-shrink: 0;">
                        <button type="button" @click="openGajiPokokModal = false"
                                style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s;"
                                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                            <span>&times;</span> Batal
                        </button>
                        <button type="submit" form="formGajiPokokModal"
                                style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #7A4517; border: none; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12); transition: all .15s;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                            <span>&#10003;</span> <span x-text="isEditMode ? 'Perbarui Gaji Pokok' : 'Simpan Gaji Pokok'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>


        {{-- ========================================================================= --}}
        {{-- POPUP MODAL 2: DETAIL RINCIAN GAJI BERSIH KARYAWAN --}}
        {{-- ========================================================================= --}}
        <template x-if="openDetailModal">
            <div x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99998; display: flex; align-items: center; justify-content: center; padding: 16px;">
                
                {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); cursor: pointer;"
                     @click="openDetailModal = false"></div>

                {{-- MODAL BOX CENTERED --}}
                <div style="position: relative; background: #ffffff; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; overflow-y: auto; max-height: 100%; z-index: 99999;" 
                     @click.stop>
                    <template x-if="activeDetail">
                        <div>
                            {{-- MODAL HEADER --}}
                            <div style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0;" x-text="'Rincian Gaji: ' + activeDetail.nama"></h3>
                                    <p style="font-size: 11px; color: #475569; font-weight: 600; margin: 2px 0 0 0;" x-text="activeDetail.jabatan + ' · ' + activeDetail.outlet + ' · ' + activeDetail.periode"></p>
                                </div>
                                <button type="button" @click="openDetailModal = false"
                                        style="background: none; border: none; color: #475569; font-size: 22px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0 4px;"
                                        onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#475569'">
                                    &times;
                                </button>
                            </div>

                            {{-- MODAL BODY --}}
                            <div style="padding: 18px 20px; font-size: 12px; display: flex; flex-direction: column; gap: 12px;">
                                {{-- INFORMASI DASAR (Hari/Jam Kerja & Tarif) --}}
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #fffbf5; border: 1.5px solid #fcd34d; padding: 10px 14px; border-radius: 10px;">
                                    <div>
                                        <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase; letter-spacing: 0.5px;"
                                             x-text="activeDetail.satuan_label_header || (activeDetail.satuan_gaji === 'Per Jam' ? 'Jam Kerja' : (activeDetail.satuan_gaji === 'Bulanan' ? 'Bulan Kerja' : 'Hari Kerja'))">
                                            Waktu Kerja
                                        </div>
                                        <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px;"
                                             x-text="activeDetail.waktu_kerja_display || (activeDetail.hari_kerja + ' Hari')"></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Tarif
                                        </div>
                                        <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;"
                                             x-text="activeDetail.tarif_display || ('Rp ' + activeDetail.tarif_harian)"></div>
                                    </div>
                                </div>

                                {{-- PENERIMAAN / PENDAPATAN --}}
                                <div style="border-top: 1px solid #e2e8f0; padding-top: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                        <span style="font-weight: 800; color: #0f172a; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.3px;">Penerimaan / Pendapatan</span>
                                        <span style="font-weight: 800; color: #047857; font-size: 13.5px;" x-text="'Rp ' + activeDetail.total_earnings"></span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px; color: #334155; padding-left: 4px; font-size: 11.5px;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span x-text="activeDetail.has_multiple_periods ? 'Gaji Pokok (Akumulasi Semua Periode)' : (activeDetail.satuan_gaji === 'Per Jam' ? 'Gaji Pokok (Jam Kerja × Tarif)' : (activeDetail.satuan_gaji === 'Bulanan' ? 'Gaji Pokok Bulanan' : 'Gaji Pokok (Hari Kerja × Tarif)'))">Gaji Pokok</span>
                                            <span style="font-weight: 700; color: #0f172a;" x-text="'Rp ' + activeDetail.gaji_utama"></span>
                                        </div>
                                        <template x-if="activeDetail.has_multiple_periods && activeDetail.item_breakdowns">
                                            <div style="display: flex; flex-direction: column; gap: 2px; padding-left: 8px; font-size: 11px; color: #4338ca;">
                                                <template x-for="ib in activeDetail.item_breakdowns" :key="ib.periode">
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span x-text="'Periode ' + ib.periode + ' (' + ib.hari_kerja + ' ' + ib.suffix + ' · ' + ib.satuan + ')'"></span>
                                                        <span style="font-weight: 700;" x-text="'Rp ' + Number(ib.gaji_utama || 0).toLocaleString('id-ID')"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.jam_lembur > 0">
                                            <span>Lembur (<span x-text="activeDetail.jam_lembur"></span> jam)</span>
                                            <span style="font-weight: 700; color: #0f172a;" x-text="'Rp ' + activeDetail.lembur"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.banyak_target > 0">
                                            <span>Bonus Target (<span x-text="activeDetail.banyak_target"></span>x)</span>
                                            <span style="font-weight: 700; color: #0f172a;" x-text="'Rp ' + activeDetail.bonus_target"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.banyak_merah > 0">
                                            <span>Tanggal Merah (<span x-text="activeDetail.banyak_merah"></span>x)</span>
                                            <span style="font-weight: 700; color: #0f172a;" x-text="'Rp ' + activeDetail.bonus_merah"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.banyak_birthday > 0">
                                            <span>Birthday Service (<span x-text="activeDetail.banyak_birthday"></span>x)</span>
                                            <span style="font-weight: 700; color: #0f172a;" x-text="'Rp ' + activeDetail.bonus_birthday"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.pengembalian_deposit != '0'">
                                            <span>Pengembalian Deposit</span>
                                            <span style="font-weight: 700; color: #059669;" x-text="'Rp ' + activeDetail.pengembalian_deposit"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.bonus_dll != '0'">
                                            <span>Bonus Lain-lain</span>
                                            <span style="font-weight: 700; color: #0f172a;" x-text="'Rp ' + activeDetail.bonus_dll"></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- POTONGAN & PENGURANGAN --}}
                                <div style="border-top: 1px solid #e2e8f0; padding-top: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                        <span style="font-weight: 800; color: #0f172a; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.3px;">Potongan &amp; Pengurangan</span>
                                        <span style="font-weight: 800; color: #dc2626; font-size: 13.5px;" x-text="'- Rp ' + activeDetail.total_deductions"></span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px; color: #334155; padding-left: 4px; font-size: 11.5px;">
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.potongan_terlambat != '0'">
                                            <span>Denda Keterlambatan</span>
                                            <span style="font-weight: 700; color: #dc2626;" x-text="'- Rp ' + activeDetail.potongan_terlambat"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.potongan_inventaris != '0'">
                                            <span>Kerusakan Inventaris</span>
                                            <span style="font-weight: 700; color: #dc2626;" x-text="'- Rp ' + activeDetail.potongan_inventaris"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.potongan_kasbon != '0'">
                                            <span>Potongan Kasbon</span>
                                            <span style="font-weight: 700; color: #dc2626;" x-text="'- Rp ' + activeDetail.potongan_kasbon"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.potongan_deposit != '0'">
                                            <span>Pengurangan Deposit</span>
                                            <span style="font-weight: 700; color: #dc2626;" x-text="'- Rp ' + activeDetail.potongan_deposit"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;" x-show="activeDetail.potongan_dll != '0'">
                                            <span>
                                                Potongan Lain-lain
                                                <span class="text-[10px] text-slate-500 italic font-normal block" x-show="activeDetail.catatan_potongan_dll" x-text="'(' + activeDetail.catatan_potongan_dll + ')'"></span>
                                            </span>
                                            <span style="font-weight: 700; color: #dc2626;" x-text="'- Rp ' + activeDetail.potongan_dll"></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; color: #64748b; font-style: italic; font-weight: 500;" x-show="activeDetail.total_deductions == '0'">
                                            <span>Tidak ada potongan</span>
                                            <span>Rp 0</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- GAJI BERSIH (Nominal Diperbesar & Bold, Label Sejajar Harmonis) --}}
                                <div style="background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%); border: 1.5px solid #fcd34d; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <div style="font-size: 11px; font-weight: 800; color: #78350f; text-transform: uppercase; letter-spacing: 0.5px;">GAJI BERSIH</div>
                                        <div style="font-size: 10.5px; color: #78350f; font-weight: 600; margin-top: 2px;" x-text="activeDetail.no_rekening ? 'Rek: ' + activeDetail.no_rekening : 'Rekening: -'"></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 24px; font-weight: 900; color: #7A4517; line-height: 1;" x-text="'Rp ' + activeDetail.take_home_pay"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- TOMBOL FOOTER --}}
                            <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <button type="button" @click="openDetailModal = false"
                                        style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s;"
                                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                                    <span>&times;</span> Tutup
                                </button>

                                <template x-if="activeDetail.has_multiple_periods">
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                        <template x-for="item in activeDetail.slip_items" :key="item.periode">
                                            <a :href="item.url"
                                               style="display: inline-flex; align-items: center; gap: 4px; padding: 8px 12px; border-radius: 8px; font-size: 11.5px; font-weight: 700; color: #1e293b; background: #f1f5f9; border: 1px solid #cbd5e1; text-decoration: none; cursor: pointer;">
                                                <span>&#129534;</span> <span x-text="'Slip P' + item.periode"></span>
                                            </a>
                                        </template>
                                        <a :href="activeDetail.slip_gabungan_url"
                                           style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #4338ca; border: none; text-decoration: none; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                                            <span>&#128220;</span> Slip Gabungan
                                        </a>
                                        <template x-if="activeDetail.has_wa">
                                            <button type="button"
                                                    @click="sendSlipWa(activeDetail.pdf_url_gabungan, activeDetail.wa_url_gabungan, activeDetail.file_name_gabungan)"
                                                    style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #16a34a; border: none; cursor: pointer; box-shadow: 0 1px 3px rgba(22,163,74,0.25);">
                                                <span>📲</span> Kirim WA (Gabungan)
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="!activeDetail.has_multiple_periods">
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                        <a :href="activeDetail.slip_url"
                                           style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #7A4517; border: none; text-decoration: none; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12); transition: all .15s;"
                                           onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                                            <span>&#129534;</span> Buka Slip Gaji
                                        </a>
                                        <template x-if="activeDetail.has_wa">
                                            <button type="button"
                                                    @click="sendSlipWa(activeDetail.pdf_url_single, activeDetail.wa_url_single, activeDetail.file_name_single)"
                                                    style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #16a34a; border: none; cursor: pointer; box-shadow: 0 1px 3px rgba(22,163,74,0.25);">
                                                <span>📲</span> Kirim Slip ke WA
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <script>
        function payrollManager() {
            return {
                openDetailModal: false,
                activeDetail: null,
                
                // Gaji Pokok Modal State
                openGajiPokokModal: false,
                isEditMode: false,
                formAction: '',
                modalTitle: '',
                
                // Form Fields
                karyawanId: '',
                selectedKaryawanObj: null,
                tanggalMulai: '',
                tanggalSelesai: '',
                hariKerja: 0,
                
                // Period selection
                pilihanPeriode: 1,
                
                // Master tariff & calculations
                currentGp: 0,
                currentMakan: 0,
                currentTransport: 0,
                currentTarifTotal: 0,
                gajiUtama: 0,
                
                // Period data
                hasP2: false,
                tarif1: 0,
                tarif2: 0,
                satuanGaji1: 'Harian',
                satuanGaji2: 'Harian',
                gp1: 0, um1: 0, ut1: 0,
                gp2: 0, um2: 0, ut2: 0,
                
                // Karyawan lists passed from Controller
                allKaryawans: @json($allKaryawans),
                availableKaryawans: @json($availableKaryawans),
                
                openCreateModal() {
                    this.isEditMode = false;
                    this.formAction = '{{ route("penggajian.store") }}';
                    this.modalTitle = 'Input Gaji Pokok & Presensi';
                    this.karyawanId = '';
                    this.selectedKaryawanObj = null;
                    this.tanggalMulai = '';
                    this.tanggalSelesai = '';
                    this.hariKerja = 0;
                    this.pilihanPeriode = 1;
                    this.resetCalculations();
                    this.openGajiPokokModal = true;
                },
                
                openEditModal(payroll) {
                    this.isEditMode = true;
                    this.formAction = '/penggajian/' + payroll.id;
                    this.modalTitle = 'Ubah Gaji Pokok & Presensi';
                    this.karyawanId = payroll.karyawan_id;
                    this.selectedKaryawanObj = payroll.karyawan;
                    this.tanggalMulai = payroll.tanggal_mulai ? payroll.tanggal_mulai.substring(0, 10) : '';
                    this.tanggalSelesai = payroll.tanggal_selesai ? payroll.tanggal_selesai.substring(0, 10) : '';
                    this.hariKerja = payroll.hari_kerja || 0;
                    this.pilihanPeriode = payroll.pilihan_periode || 1;
                    this.setupTariffData();
                    this.applyPeriodeSelection();
                    this.openGajiPokokModal = true;
                },
                
                openCreateModalForKaryawan(karyawanId, defaultPeriode) {
                    this.isEditMode = false;
                    this.formAction = '{{ route("penggajian.store") }}';
                    this.modalTitle = 'Tambah Gaji Periode ' + defaultPeriode;
                    this.karyawanId = String(karyawanId);
                    this.selectedKaryawanObj = this.allKaryawans.find(k => k.id == karyawanId) || null;
                    this.tanggalMulai = '';
                    this.tanggalSelesai = '';
                    this.hariKerja = 0;
                    this.pilihanPeriode = defaultPeriode || 1;
                    this.setupTariffData();
                    this.applyPeriodeSelection();
                    this.openGajiPokokModal = true;
                },
                
                onKaryawanChange() {
                    if (!this.karyawanId) {
                        this.selectedKaryawanObj = null;
                        this.resetCalculations();
                        return;
                    }
                    this.selectedKaryawanObj = this.allKaryawans.find(k => k.id == this.karyawanId) || null;
                    this.pilihanPeriode = 1;
                    this.setupTariffData();
                    this.applyPeriodeSelection();
                },
                
                onDateChange() {
                    if (this.tanggalMulai && this.tanggalSelesai) {
                        const d1 = new Date(this.tanggalMulai);
                        const d2 = new Date(this.tanggalSelesai);
                        if (d2 >= d1) {
                            const diffDays = Math.ceil(Math.abs(d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
                            if (this.currentSatuan() === 'Harian') {
                                this.hariKerja = diffDays;
                            } else if (this.currentSatuan() === 'Bulanan') {
                                this.hariKerja = 1;
                            }
                            // Untuk Per Jam: pertahankan input jam dari user atau 0 jika belum diisi
                        }
                    }
                    this.calculateGajiUtama();
                },
                
                onHariKerjaChange() {
                    this.calculateGajiUtama();
                },
                
                // Load tariff data for both periods from the selected karyawan object
                setupTariffData() {
                    if (!this.selectedKaryawanObj) {
                        this.resetCalculations();
                        return;
                    }
                    const k = this.selectedKaryawanObj;
                    this.gp1 = parseFloat(k.gaji_pokok) || 0;
                    this.um1 = parseFloat(k.uang_makan) || 0;
                    this.ut1 = parseFloat(k.uang_transport) || 0;
                    this.tarif1 = this.gp1 + this.um1 + this.ut1;
                    this.satuanGaji1 = k.satuan_gaji || 'Harian';
                    
                    const hasP2 = (k.gaji_pokok_2 !== null && k.gaji_pokok_2 !== undefined && k.gaji_pokok_2 !== '');
                    this.hasP2 = hasP2;
                    
                    if (hasP2) {
                        this.gp2 = parseFloat(k.gaji_pokok_2) || 0;
                        this.um2 = parseFloat(k.uang_makan_2) || 0;
                        this.ut2 = parseFloat(k.uang_transport_2) || 0;
                        this.tarif2 = this.gp2 + this.um2 + this.ut2;
                        this.satuanGaji2 = k.satuan_gaji_2 || 'Harian';
                    } else {
                        this.gp2 = 0; this.um2 = 0; this.ut2 = 0;
                        this.tarif2 = 0;
                        this.satuanGaji2 = 'Harian';
                        this.pilihanPeriode = 1; // force P1 if no P2
                    }
                },
                
                // Select which period's rate to use
                selectPeriode(p) {
                    if (p == 2 && !this.hasP2) return; // guard
                    this.pilihanPeriode = p;
                    this.applyPeriodeSelection();
                },
                
                // Apply the currently selected period's rates to display fields
                applyPeriodeSelection() {
                    if (this.pilihanPeriode == 2 && this.hasP2) {
                        this.currentGp = this.gp2;
                        this.currentMakan = this.um2;
                        this.currentTransport = this.ut2;
                    } else {
                        this.currentGp = this.gp1;
                        this.currentMakan = this.um1;
                        this.currentTransport = this.ut1;
                    }
                    this.currentTarifTotal = this.currentGp + this.currentMakan + this.currentTransport;
                    this.calculateGajiUtama();
                },
                
                calculateGajiUtama() {
                    const hk = parseFloat(this.hariKerja) || 0;
                    if (this.currentSatuan() === 'Bulanan') {
                        this.gajiUtama = this.currentTarifTotal;
                    } else {
                        this.gajiUtama = hk * this.currentTarifTotal;
                    }
                },
                
                currentSatuan() {
                    if (this.pilihanPeriode == 2 && this.hasP2) {
                        return this.satuanGaji2 || 'Harian';
                    }
                    return this.satuanGaji1 || 'Harian';
                },

                satuanUnit(satuan) {
                    if (satuan === 'Per Jam') return '/jam';
                    if (satuan === 'Bulanan') return '/bulan';
                    return '/hari';
                },

                currentSatuanLabel() {
                    const s = this.currentSatuan();
                    if (s === 'Per Jam') return 'Per Jam';
                    if (s === 'Bulanan') return 'Bulanan';
                    return 'Harian';
                },

                satuanKerjaLabel() {
                    const s = this.currentSatuan();
                    if (s === 'Per Jam') return 'Jam Kerja';
                    if (s === 'Bulanan') return 'Bulan Kerja';
                    return 'Hari Kerja';
                },
                
                resetCalculations() {
                    this.currentGp = 0;
                    this.currentMakan = 0;
                    this.currentTransport = 0;
                    this.currentTarifTotal = 0;
                    this.gajiUtama = 0;
                    this.hasP2 = false;
                    this.tarif1 = 0;
                    this.tarif2 = 0;
                    this.gp1 = 0; this.um1 = 0; this.ut1 = 0;
                    this.gp2 = 0; this.um2 = 0; this.ut2 = 0;
                    this.satuanGaji1 = 'Harian';
                    this.satuanGaji2 = 'Harian';
                    this.pilihanPeriode = 1;
                },
                
                formatRupiah(val) {
                    return 'Rp ' + Math.round(val || 0).toLocaleString('id-ID');
                }
            };
        }

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

        function toggleKebab(btn) {
            const menu = btn.nextElementSibling;
            const isHidden = menu.classList.contains('hidden');
            
            // Tutup semua kebab menu lain
            document.querySelectorAll('.kebab-menu').forEach(m => {
                m.classList.add('hidden');
                m.style.position = '';
                m.style.top = '';
                m.style.left = '';
                m.style.bottom = '';
            });

            if (isHidden) {
                const rect = btn.getBoundingClientRect();
                const menuWidth = 185;
                const menuHeight = 230; // estimasi tinggi menu
                
                // Gunakan position fixed agar tembus dari segala parent overflow
                menu.style.position = 'fixed';
                menu.style.zIndex = '99999';
                menu.style.width = menuWidth + 'px';
                
                // Posisikan horizontal: sejajar kanan tombol
                let leftPos = rect.right - menuWidth;
                if (leftPos < 10) leftPos = 10;
                menu.style.left = leftPos + 'px';

                // Posisikan vertikal: cek ruang bawah vs atas
                const spaceBelow = window.innerHeight - rect.bottom;
                if (spaceBelow < menuHeight && rect.top > menuHeight) {
                    // Muncul ke atas tombol
                    menu.style.top = 'auto';
                    menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                } else {
                    // Muncul ke bawah tombol
                    menu.style.bottom = 'auto';
                    menu.style.top = (rect.bottom + 4) + 'px';
                }

                menu.classList.remove('hidden');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.kebab-wrapper')) {
                document.querySelectorAll('.kebab-menu').forEach(m => {
                    m.classList.add('hidden');
                    m.style.position = '';
                    m.style.top = '';
                    m.style.left = '';
                    m.style.bottom = '';
                });
            }
        });

        // Kirim Slip ke WhatsApp: Langsung Buka Chat WhatsApp
        function sendSlipWa(pdfUrl, waUrl, fileName) {
            if (!waUrl) {
                alert('Nomor WhatsApp karyawan belum terdaftar.');
                return;
            }

            // Buka tab / aplikasi WhatsApp langsung dengan pesan berisi link slip gaji publik
            window.open(waUrl, '_blank');
        }

        // =========================================================================
        // FORMULIR GAJI TERPADU (LIVE CALCULATION & BATCH SAVE)
        // =========================================================================
        function cleanNumber(val) {
            if (val === null || val === undefined) return 0;
            if (typeof val === 'number') return val;
            const clean = String(val).replace(/[^0-9]/g, '');
            return parseFloat(clean) || 0;
        }

        function isiDepositOtomatis(btn, saldo) {
            const row = btn.closest('.payroll-row');
            if (!row) return;
            const depInput = row.querySelector('.batch-pengembalian-deposit');
            if (depInput) {
                depInput.value = Math.round(saldo).toLocaleString('id-ID');
                onPayrollUnifiedRowInput(depInput);
            }
        }

        function onPayrollUnifiedRowInput(el) {
            const row = el.closest('.payroll-row');
            if (!row) return;

            // Auto format Rupiah dengan pemisah ribuan
            if (el.classList.contains('batch-input-rupiah')) {
                let numOnly = el.value.replace(/\D/g, '');
                if (numOnly) {
                    el.value = parseInt(numOnly, 10).toLocaleString('id-ID');
                } else {
                    el.value = '';
                }
            }

            const satuan = row.getAttribute('data-satuan') || 'Harian';
            const tarif = parseFloat(row.getAttribute('data-tarif')) || 0;

            // 1. GAJI POKOK UTAMA
            let totalGajiPokok = 0;
            const subInputs = row.querySelectorAll('.batch-sub-item');
            if (subInputs.length > 0) {
                subInputs.forEach(input => {
                    const hk = parseFloat(input.value) || 0;
                    const subSat = input.getAttribute('data-satuan') || 'Harian';
                    const subTar = parseFloat(input.getAttribute('data-tarif')) || 0;
                    const itemId = input.getAttribute('data-item-id');
                    const pNum = input.getAttribute('data-periode');
                    const subGp = (subSat === 'Bulanan') ? subTar : (hk * subTar);
                    totalGajiPokok += subGp;

                    let subDisplayEl = itemId ? row.querySelector('.sub-gp-item-' + itemId) : null;
                    if (!subDisplayEl && pNum) subDisplayEl = row.querySelector('.sub-gp-p-' + pNum);
                    if (subDisplayEl) subDisplayEl.textContent = Math.round(subGp).toLocaleString('id-ID');
                });
                let sumHk = 0;
                subInputs.forEach(inp => sumHk += (parseFloat(inp.value) || 0));
                const displayHkEl = row.querySelector('.display-total-hk');
                if (displayHkEl) displayHkEl.textContent = sumHk;
            } else {
                const hkInput = row.querySelector('.batch-hari-kerja');
                const hk = hkInput ? (parseFloat(hkInput.value) || 0) : 0;
                totalGajiPokok = (satuan === 'Bulanan') ? tarif : (hk * tarif);
            }
            row.setAttribute('data-gaji-pokok', totalGajiPokok);
            const pokokCell = row.querySelector('.row-gaji-pokok-cell');
            if (pokokCell) {
                const valEl = pokokCell.querySelector('.ac-val');
                if (valEl) {
                    valEl.textContent = Math.round(totalGajiPokok).toLocaleString('id-ID');
                } else {
                    pokokCell.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(totalGajiPokok).toLocaleString('id-ID') + '</span>';
                }
            }

            // 2. BONUS & LEMBUR
            const jamLemburInput = row.querySelector('.batch-jam-lembur');
            const jamLembur = jamLemburInput ? (parseFloat(jamLemburInput.value) || 0) : 0;
            const upahLembur = jamLembur * 10000;
            const subLemburText = row.querySelector('.sub-lembur-text');
            if (subLemburText) {
                const valEl = subLemburText.querySelector('.ac-val');
                if (valEl) valEl.textContent = Math.round(upahLembur).toLocaleString('id-ID');
                else subLemburText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahLembur).toLocaleString('id-ID') + '</span>';
                subLemburText.classList.toggle('hidden', jamLembur <= 0);
            }

            let upahTarget = 0;
            const banyakTargetInput = row.querySelector('.batch-banyak-target');
            const bonusTargetInput = row.querySelector('.batch-bonus-target');
            if (banyakTargetInput) {
                const banyakTarget = parseFloat(banyakTargetInput.value) || 0;
                upahTarget = banyakTarget * tarif;
                const subTargetText = row.querySelector('.sub-target-text');
                if (subTargetText) {
                    const valEl = subTargetText.querySelector('.ac-val');
                    if (valEl) valEl.textContent = Math.round(upahTarget).toLocaleString('id-ID');
                    else subTargetText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahTarget).toLocaleString('id-ID') + '</span>';
                    subTargetText.classList.toggle('hidden', banyakTarget <= 0);
                }
            } else if (bonusTargetInput) {
                upahTarget = cleanNumber(bonusTargetInput.value);
            }

            let upahMerah = 0;
            const banyakMerahInput = row.querySelector('.batch-banyak-merah');
            const bonusMerahInput = row.querySelector('.batch-bonus-merah');
            if (banyakMerahInput) {
                const banyakMerah = parseFloat(banyakMerahInput.value) || 0;
                upahMerah = banyakMerah * tarif;
                const subMerahText = row.querySelector('.sub-merah-text');
                if (subMerahText) {
                    const valEl = subMerahText.querySelector('.ac-val');
                    if (valEl) valEl.textContent = Math.round(upahMerah).toLocaleString('id-ID');
                    else subMerahText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahMerah).toLocaleString('id-ID') + '</span>';
                    subMerahText.classList.toggle('hidden', banyakMerah <= 0);
                }
            } else if (bonusMerahInput) {
                upahMerah = cleanNumber(bonusMerahInput.value);
            }

            const banyakBirthdayInput = row.querySelector('.batch-banyak-birthday');
            const banyakBirthday = banyakBirthdayInput ? (parseFloat(banyakBirthdayInput.value) || 0) : 0;
            const upahBirthday = banyakBirthday * 5000;
            const subBirthdayText = row.querySelector('.sub-birthday-text');
            if (subBirthdayText) {
                const valEl = subBirthdayText.querySelector('.ac-val');
                if (valEl) valEl.textContent = Math.round(upahBirthday).toLocaleString('id-ID');
                else subBirthdayText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahBirthday).toLocaleString('id-ID') + '</span>';
                subBirthdayText.classList.toggle('hidden', banyakBirthday <= 0);
            }

            const depKembaliInput = row.querySelector('.batch-pengembalian-deposit');
            const depKembali = depKembaliInput ? cleanNumber(depKembaliInput.value) : 0;

            const bonusDllInput = row.querySelector('.batch-bonus-dll');
            const bonusDll = bonusDllInput ? cleanNumber(bonusDllInput.value) : 0;

            const totalBonus = upahLembur + upahTarget + upahMerah + upahBirthday + depKembali + bonusDll;
            row.setAttribute('data-bonus-total', totalBonus);
            const bonusCell = row.querySelector('.row-total-bonus-cell');
            if (bonusCell) {
                if (totalBonus > 0) {
                    bonusCell.innerHTML = '<div class="accounting-cell"><span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(totalBonus).toLocaleString('id-ID') + '</span></div>';
                } else {
                    bonusCell.innerHTML = '<div class="text-center font-bold text-amber-700/60 text-xs">-</div>';
                }
            }

            // 3. POTONGAN & PENGURANGAN
            const potTerlambatRaw = row.querySelector('.potongan-terlambat-raw');
            const potTerlambat = potTerlambatRaw ? (parseFloat(potTerlambatRaw.value) || 0) : 0;

            const potInvInput = row.querySelector('.batch-potongan-inventaris');
            const potInv = potInvInput ? cleanNumber(potInvInput.value) : 0;

            const potKasbonInput = row.querySelector('.batch-potongan-kasbon');
            const potKasbon = potKasbonInput ? cleanNumber(potKasbonInput.value) : 0;

            const potDepositInput = row.querySelector('.batch-potongan-deposit');
            const potDeposit = potDepositInput ? cleanNumber(potDepositInput.value) : 0;

            const potDllInput = row.querySelector('.batch-potongan-dll');
            const potDll = potDllInput ? cleanNumber(potDllInput.value) : 0;

            const totalPotongan = potTerlambat + potInv + potKasbon + potDeposit + potDll;
            row.setAttribute('data-deductions-total', totalPotongan);
            const potCell = row.querySelector('.row-total-deductions-cell');
            if (potCell) {
                if (totalPotongan > 0) {
                    potCell.innerHTML = '<div class="accounting-cell"><span class="ac-curr">- Rp</span><span class="ac-val">' + Math.round(totalPotongan).toLocaleString('id-ID') + '</span></div>';
                } else {
                    potCell.innerHTML = '<div class="text-center font-bold text-rose-700/60 text-xs">-</div>';
                }
            }

            // 4. TAKE HOME PAY (GAJI BERSIH)
            const takeHomePay = totalGajiPokok + totalBonus - totalPotongan;
            row.setAttribute('data-take-home-pay', takeHomePay);
            const thpCell = row.querySelector('.row-take-home-pay-cell');
            if (thpCell) {
                const valEl = thpCell.querySelector('.ac-val');
                if (valEl) {
                    valEl.textContent = Math.round(takeHomePay).toLocaleString('id-ID');
                } else {
                    thpCell.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(takeHomePay).toLocaleString('id-ID') + '</span>';
                }
            }

            // Tandai baris ini sebagai modified / dirty (mencegah overwrite data user lain saat batch save)
            row.setAttribute('data-dirty', 'true');
            row.classList.add('row-modified');
            updateDirtyCounter();

            recalculateAllHeaderTotals();
        }

        // Hitung berapa baris yang telah diedit oleh user ini
        function updateDirtyCounter() {
            const dirtyCount = document.querySelectorAll('.payroll-row[data-dirty="true"]').length;
            const btnSave = document.getElementById('btnBatchSaveUnifiedPayroll');
            const txtSave = document.getElementById('btnBatchSaveText');
            if (!btnSave) return;

            if (dirtyCount > 0) {
                btnSave.style.backgroundColor = '#b45309'; // Warna amber tua lebih tegas
                if (txtSave) txtSave.textContent = `Simpan (${dirtyCount} diubah)`;
                btnSave.title = `Ada ${dirtyCount} baris yang diubah dan belum disimpan ke database`;
            } else {
                btnSave.style.backgroundColor = '#7A4517';
                if (txtSave) txtSave.textContent = 'Simpan Formulir Gaji';
                btnSave.title = 'Simpan formulir gaji ke database';
            }
        }

        // Peringatan jika user meninggalkan halaman saat ada perubahan yang belum disimpan
        window.addEventListener('beforeunload', function (e) {
            const dirtyCount = document.querySelectorAll('.payroll-row[data-dirty="true"]').length;
            if (dirtyCount > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Muat ulang data terbaru dari database
        function refreshPayrollData(btn) {
            const dirtyCount = document.querySelectorAll('.payroll-row[data-dirty="true"]').length;
            if (dirtyCount > 0) {
                if (!confirm(`Ada ${dirtyCount} baris data yang belum Anda simpan. Jika disinkronkan sekarang, perubahan Anda akan hilang. Tetap muat ulang data dari server?`)) {
                    return;
                }
            }
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="inline-block animate-spin">🔄</span> Memuat...';
            }
            window.location.reload();
        }

        function recalculateAllHeaderTotals() {
            let grandNett = 0;
            let grandPokok = 0;
            let grandBonus = 0;
            let grandPotongan = 0;

            document.querySelectorAll('.payroll-row').forEach(row => {
                grandNett += parseFloat(row.getAttribute('data-take-home-pay')) || 0;
                grandPokok += parseFloat(row.getAttribute('data-gaji-pokok')) || 0;
                grandBonus += parseFloat(row.getAttribute('data-bonus-total')) || 0;
                grandPotongan += parseFloat(row.getAttribute('data-deductions-total')) || 0;
            });

            const nettEl = document.getElementById('headerTotalGajiNettValue');
            if (nettEl) nettEl.textContent = 'Rp ' + Math.round(grandNett).toLocaleString('id-ID');

            const pokokEl = document.getElementById('headerTotalGajiPokokValue');
            if (pokokEl) pokokEl.textContent = 'Rp ' + Math.round(grandPokok).toLocaleString('id-ID');

            const bonusEl = document.getElementById('headerTotalBonusValue');
            if (bonusEl) bonusEl.textContent = 'Rp ' + Math.round(grandBonus).toLocaleString('id-ID');

            const potEl = document.getElementById('headerTotalPotonganValue');
            if (potEl) potEl.textContent = 'Rp ' + Math.round(grandPotongan).toLocaleString('id-ID');
        }

        async function submitBatchUnifiedPayroll(btn) {
            const allRows = document.querySelectorAll('.payroll-row');
            if (!allRows.length) return;

            // OPTIMASI: Simpan HANYA baris yang diedit (dirty) agar tidak menimpa editan user lain!
            const dirtyRows = document.querySelectorAll('.payroll-row[data-dirty="true"]');
            const rowsToProcess = (dirtyRows.length > 0) ? dirtyRows : allRows;

            const items = [];
            rowsToProcess.forEach(row => {
                const id = row.getAttribute('data-id');
                const karyawanId = row.getAttribute('data-karyawan-id');
                const subInputs = row.querySelectorAll('.batch-sub-item');

                const jamLembur = row.querySelector('.batch-jam-lembur') ? (parseFloat(row.querySelector('.batch-jam-lembur').value) || 0) : 0;
                
                const banyakTargetInput = row.querySelector('.batch-banyak-target');
                const bonusTargetInput = row.querySelector('.batch-bonus-target');
                const banyakTarget = banyakTargetInput ? (parseFloat(banyakTargetInput.value) || 0) : 0;
                const bonusTarget = bonusTargetInput ? cleanNumber(bonusTargetInput.value) : 0;
                const catatanTarget = row.querySelector('.batch-catatan-target') ? row.querySelector('.batch-catatan-target').value : null;

                const banyakMerahInput = row.querySelector('.batch-banyak-merah');
                const bonusMerahInput = row.querySelector('.batch-bonus-merah');
                const banyakMerah = banyakMerahInput ? (parseFloat(banyakMerahInput.value) || 0) : 0;
                const bonusMerah = bonusMerahInput ? cleanNumber(bonusMerahInput.value) : 0;
                const catatanMerah = row.querySelector('.batch-catatan-merah') ? row.querySelector('.batch-catatan-merah').value : null;

                const banyakBirthday = row.querySelector('.batch-banyak-birthday') ? (parseFloat(row.querySelector('.batch-banyak-birthday').value) || 0) : 0;
                const depKembali = row.querySelector('.batch-pengembalian-deposit') ? cleanNumber(row.querySelector('.batch-pengembalian-deposit').value) : 0;
                const bonusDll = row.querySelector('.batch-bonus-dll') ? cleanNumber(row.querySelector('.batch-bonus-dll').value) : 0;

                const potInv = row.querySelector('.batch-potongan-inventaris') ? cleanNumber(row.querySelector('.batch-potongan-inventaris').value) : 0;
                const potKasbon = row.querySelector('.batch-potongan-kasbon') ? cleanNumber(row.querySelector('.batch-potongan-kasbon').value) : 0;
                const potDeposit = row.querySelector('.batch-potongan-deposit') ? cleanNumber(row.querySelector('.batch-potongan-deposit').value) : 0;
                const potDll = row.querySelector('.batch-potongan-dll') ? cleanNumber(row.querySelector('.batch-potongan-dll').value) : 0;
                const catPotDll = row.querySelector('.batch-catatan-potongan-dll') ? row.querySelector('.batch-catatan-potongan-dll').value : '';

                if (subInputs.length > 0) {
                    subInputs.forEach((subInput, sIdx) => {
                        const itemId = subInput.getAttribute('data-item-id');
                        const pNum = subInput.getAttribute('data-periode');
                        const hkVal = parseFloat(subInput.value) || 0;

                        if (sIdx === 0) {
                            items.push({
                                id: itemId,
                                karyawan_id: karyawanId,
                                pilihan_periode: pNum,
                                hari_kerja: hkVal,
                                jam_lembur: jamLembur,
                                banyak_target: banyakTarget,
                                bonus_target: bonusTarget,
                                catatan_bonus_target: catatanTarget,
                                banyak_tanggal_merah: banyakMerah,
                                bonus_tanggal_merah: bonusMerah,
                                catatan_bonus_tanggal_merah: catatanMerah,
                                banyak_birthday_service: banyakBirthday,
                                pengembalian_deposit: depKembali,
                                bonus_dll: bonusDll,
                                potongan_inventaris: potInv,
                                potongan_kasbon: potKasbon,
                                potongan_deposit: potDeposit,
                                potongan_dll: potDll,
                                catatan_potongan_dll: catPotDll,
                            });
                        } else {
                            items.push({
                                id: itemId,
                                karyawan_id: karyawanId,
                                pilihan_periode: pNum,
                                hari_kerja: hkVal,
                            });
                        }
                    });
                } else {
                    const hkInput = row.querySelector('.batch-hari-kerja');
                    const activeItemId = row.getAttribute('data-active-item-id') || id;
                    const activePeriode = row.getAttribute('data-active-periode') || 1;
                    const hkVal = hkInput ? (parseFloat(hkInput.value) || 0) : 0;

                    items.push({
                        id: activeItemId,
                        karyawan_id: karyawanId,
                        pilihan_periode: activePeriode,
                        hari_kerja: hkVal,
                        jam_lembur: jamLembur,
                        banyak_target: banyakTarget,
                        bonus_target: bonusTarget,
                        catatan_bonus_target: catatanTarget,
                        banyak_tanggal_merah: banyakMerah,
                        bonus_tanggal_merah: bonusMerah,
                        catatan_bonus_tanggal_merah: catatanMerah,
                        banyak_birthday_service: banyakBirthday,
                        pengembalian_deposit: depKembali,
                        bonus_dll: bonusDll,
                        potongan_inventaris: potInv,
                        potongan_kasbon: potKasbon,
                        potongan_deposit: potDeposit,
                        potongan_dll: potDll,
                        catatan_potongan_dll: catPotDll,
                    });
                }
            });

            if (!items.length) {
                alert('Tidak ada data gaji yang dapat disimpan.');
                return;
            }

            const origContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span>⏳</span> Menyimpan (${items.length} baris)...`;

            try {
                const response = await fetch("{{ route('penggajian.periode.batch-update') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        items: items,
                        periode: "{{ $periode }}",
                        outlet: "{{ $selectedOutlet }}"
                    })
                });

                const res = await response.json();
                if (response.ok && res.success) {
                    // Bersihkan tanda dirty karena sudah tersimpan ke server
                    document.querySelectorAll('.payroll-row[data-dirty="true"]').forEach(r => {
                        r.removeAttribute('data-dirty');
                        r.classList.remove('row-modified');
                    });
                    updateDirtyCounter();

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan!',
                            text: res.message || `${items.length} baris data berhasil disimpan ke server.`,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert(res.message || `${items.length} baris data berhasil disimpan ke server.`);
                        window.location.reload();
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

        // Aliases for compatibility
        const submitBatchGajiPokok = submitBatchUnifiedPayroll;
        const onGajiPokokRowInput = onPayrollUnifiedRowInput;
        const onGajiPokokMultiRowInput = onPayrollUnifiedRowInput;
        const recalculateHeaderTotalNett = recalculateAllHeaderTotals;

        // =========================================================================
        // FITUR MAXIMIZE / FULLSCREEN KHUSUS FORM HITUNG GAJI
        // =========================================================================
        function toggleMaximizePayroll(forceState = null) {
            const isCurrentlyMax = document.body.classList.contains('payroll-maximized');
            const targetState = (forceState !== null) ? forceState : !isCurrentlyMax;

            const btn = document.getElementById('btnToggleMaximizePayroll');
            const icon = document.getElementById('maximizeIcon');
            const text = document.getElementById('maximizeText');

            if (targetState) {
                document.body.classList.add('payroll-maximized');
                if (icon) icon.textContent = '🗗';
                if (text) text.textContent = 'Keluar Maximize';
                if (btn) {
                    btn.style.backgroundColor = '#334155';
                    btn.style.borderColor = '#64748b';
                    btn.title = 'Kembalikan tampilan normal dengan sidebar (atau tekan tombol ESC)';
                }
                sessionStorage.setItem('payroll_maximized', '1');

                // Opsional: Coba masuk ke browser fullscreen jika diizinkan browser
                if (document.documentElement.requestFullscreen && !document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(() => {});
                }
            } else {
                document.body.classList.remove('payroll-maximized');
                if (icon) icon.textContent = '⛶';
                if (text) text.textContent = 'Maximize';
                if (btn) {
                    btn.style.backgroundColor = '#0f172a';
                    btn.style.borderColor = '#334155';
                    btn.title = 'Layar Penuh: Sembunyikan sidebar dan maksimalkan ruang kerja tabel hitung gaji (Tekan ESC untuk keluar)';
                }
                sessionStorage.removeItem('payroll_maximized');

                if (document.exitFullscreen && document.fullscreenElement) {
                    document.exitFullscreen().catch(() => {});
                }
            }
        }

        // Sinkronisasi jika keluar via tombol ESC browser
        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement && document.body.classList.contains('payroll-maximized')) {
                toggleMaximizePayroll(false);
            }
        });

        // Dukungan tombol keyboard ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.body.classList.contains('payroll-maximized')) {
                toggleMaximizePayroll(false);
            }
        });

        // Pulihkan status maximize jika sebelumnya aktif dalam sesi ini
        document.addEventListener('DOMContentLoaded', function() {
            if (sessionStorage.getItem('payroll_maximized') === '1') {
                toggleMaximizePayroll(true);
            }
        });
    </script>

    <!-- Reorder Notification Toast -->
    <div id="reorderToast" style="display: none; position: fixed; bottom: 28px; right: 28px; z-index: 99999; background: #0f172a; color: #ffffff; padding: 12px 20px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); font-size: 13px; font-weight: 700; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.15); transition: opacity 0.2s ease;">
        <span id="reorderToastIcon" style="font-size: 15px;">⏳</span>
        <span id="reorderToastMsg">Menyimpan urutan...</span>
    </div>

    <!-- SortableJS CDN with Fallback -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tbody = document.getElementById('tbodyKaryawan');
            const toast = document.getElementById('reorderToast');
            const toastIcon = document.getElementById('reorderToastIcon');
            const toastMsg = document.getElementById('reorderToastMsg');
            let toastTimeout;

            function showToast(msg, icon = '⏳', isError = false) {
                if (!toast) return;
                clearTimeout(toastTimeout);
                toastIcon.textContent = icon;
                toastMsg.textContent = msg;
                toast.style.background = isError ? '#991b1b' : '#0f172a';
                toast.style.display = 'inline-flex';
                if (!isError && icon === '✓') {
                    toastTimeout = setTimeout(() => {
                        toast.style.display = 'none';
                    }, 2500);
                }
            }

            function updateRowIndexes() {
                if (!tbody) return;
                const rows = tbody.querySelectorAll('.payroll-row');
                rows.forEach((row, idx) => {
                    const idxSpan = row.querySelector('.row-index');
                    if (idxSpan) idxSpan.textContent = idx + 1;
                });
            }

            function saveOrder() {
                if (!tbody) return;
                const rows = tbody.querySelectorAll('.payroll-row');
                const ids = Array.from(rows).map(r => r.getAttribute('data-karyawan-id')).filter(Boolean);

                if (ids.length === 0) return;

                showToast('Menyimpan urutan posisi...', '⏳');

                fetch('{{ route("karyawan.reorder") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids: ids })
                })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP error ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Urutan posisi berhasil disimpan!', '✓');
                    } else {
                        showToast(data.message || 'Gagal menyimpan urutan.', '⚠', true);
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Gagal terhubung ke server untuk simpan urutan.', '⚠', true);
                });
            }

            if (tbody && typeof Sortable !== 'undefined') {
                Sortable.create(tbody, {
                    handle: '.grip-handle',
                    animation: 180,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function () {
                        updateRowIndexes();
                        saveOrder();
                    }
                });
            } else if (tbody) {
                // Native HTML5 Drag and Drop fallback
                let draggedRow = null;
                const rows = tbody.querySelectorAll('.payroll-row');
                rows.forEach(row => {
                    const handle = row.querySelector('.grip-handle') || row;
                    handle.setAttribute('draggable', 'true');
                    handle.addEventListener('dragstart', (e) => {
                        draggedRow = row;
                        e.dataTransfer.effectAllowed = 'move';
                        row.classList.add('opacity-50');
                    });
                    handle.addEventListener('dragend', () => {
                        draggedRow = null;
                        row.classList.remove('opacity-50');
                        updateRowIndexes();
                        saveOrder();
                    });
                    row.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        const targetRow = e.target.closest('.payroll-row');
                        if (targetRow && targetRow !== draggedRow) {
                            const rect = targetRow.getBoundingClientRect();
                            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                            tbody.insertBefore(draggedRow, next ? targetRow.nextSibling : targetRow);
                        }
                    });
                });
            }
        });
    </script>
</x-app-layout>
