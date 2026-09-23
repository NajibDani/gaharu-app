<x-app-layout>
    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 mb-3">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-3 flex-wrap">
                        <a href="{{ route('penggajian.potongan.index', ['outlet' => $selectedOutlet]) }}"
                           style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 800; font-size: 12px; padding: 6px 12px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: background .15s;"
                           onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'"
                           title="Kembali">
                            <span>&larr;</span> Kembali
                        </a>
                        <div>
                            <h1 class="text-sm sm:text-base font-extrabold text-slate-900 tracking-tight leading-tight inline">
                                Kelola Potongan &amp; Pengurangan
                            </h1>
                            <span class="text-xs text-slate-600 font-semibold ms-2">
                                Periode <strong class="text-slate-900">{{ \App\Models\Penggajian::formatPeriode($targetPeriode) }}</strong>
                                &middot; Outlet <strong class="text-slate-900">{{ $selectedOutlet }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span id="headerTotalPotonganBadge" style="font-size: 12px; font-weight: 800; background-color: #fff1f2; border: 1.5px solid #fecdd3; padding: 6px 14px; border-radius: 8px; color: #9f1239; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            Total Potongan: Rp {{ number_format($payrolls->sum('total_potongan'), 0, ',', '.') }}
                        </span>
                        @if(($currentStatus ?? 'draft') !== 'approved' && $payrolls->isNotEmpty())
                        <button type="button" onclick="submitBatchPotongan(this)" id="btnBatchSavePotongan"
                                style="background-color: #7A4517; color: #ffffff; border: none; padding: 6px 16px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(122,69,23,0.25); transition: background .15s;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'"
                                title="Simpan seluruh perubahan input potongan di halaman ini sekaligus">
                            <span>&#128190;</span> Simpan Semua Potongan
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
                    <table class="w-full min-w-[1100px] text-xs text-left divide-y divide-slate-200" id="tablePotongan">
                        <thead class="text-[11px] font-bold text-slate-700 uppercase tracking-wider bg-slate-100/90 border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-3 w-10 text-center whitespace-nowrap">#</th>
                                <th class="px-4 py-3 min-w-[200px] whitespace-nowrap">Karyawan</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[130px]">Denda Terlambat</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[135px]">Kerusakan Inventaris</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[130px]">Kasbon</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[135px]">Pengurangan Deposit</th>
                                <th class="px-3 py-3 text-right whitespace-nowrap min-w-[145px]">Potongan Lain</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[135px]">Total Potongan</th>
                                <th class="px-3 py-3 text-center min-w-[90px] whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($payrolls as $index => $payroll)
                            @php
                                $isRowLocked = $payroll->is_paid || $payroll->status === 'approved';
                                $satuanRow = $payroll->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian';
                            @endphp
                            <tr class="payroll-row hover:bg-slate-50/80 transition-colors"
                                data-id="{{ $payroll->id }}"
                                data-karyawan-id="{{ $payroll->karyawan_id }}"
                                data-nama="{{ strtolower($payroll->karyawan->nama_karyawan ?? '') }}"
                                data-departemen="{{ strtolower($payroll->karyawan->departemen ?? '') }}"
                                data-jabatan="{{ strtolower($payroll->karyawan->jabatan ?? '') }}">
                                <td class="px-3.5 py-3 text-center text-xs text-slate-400 font-bold whitespace-nowrap">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 min-w-[200px]">
                                    <div class="font-extrabold text-slate-900 text-sm nama-karyawan leading-tight">
                                        {{ $payroll->karyawan->nama_karyawan ?? '-' }}
                                    </div>
                                    <div class="text-[11px] font-medium mt-1 flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-700">{{ $payroll->karyawan->jabatan ?? '-' }}</span>
                                        @if($payroll->karyawan->departemen)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 leading-normal">
                                                {{ $payroll->karyawan->departemen }}
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 leading-normal">
                                            {{ $satuanRow }}
                                        </span>
                                    </div>
                                </td>
                                
                                {{-- DENDA TERLAMBAT (Otomatis dari menu Keterlambatan) --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <input type="hidden" class="potongan-terlambat-raw" value="{{ (float)$payroll->potongan_terlambat }}">
                                    @if($payroll->potongan_terlambat > 0)
                                        <div class="font-bold text-rose-700 text-xs whitespace-nowrap">-&nbsp;Rp&nbsp;{{ number_format($payroll->potongan_terlambat, 0, ',', '.') }}</div>
                                        <div class="text-[9.5px] text-slate-500 font-semibold mt-0.5 whitespace-nowrap">Otomatis Absensi</div>
                                    @else
                                        <span class="text-slate-400 font-bold text-xs whitespace-nowrap">-</span>
                                    @endif
                                </td>

                                {{-- KERUSAKAN INVENTARIS --}}
                                <td class="px-2.5 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="text"
                                               class="batch-input-rupiah batch-potongan-inventaris w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ $payroll->potongan_inventaris > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               oninput="onPotonganRowInput(this)">
                                    @else
                                        <span class="font-bold text-slate-700 text-xs">{{ $payroll->potongan_inventaris > 0 ? 'Rp ' . number_format($payroll->potongan_inventaris, 0, ',', '.') : '-' }}</span>
                                    @endif
                                </td>

                                {{-- KASBON --}}
                                <td class="px-2.5 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="text"
                                               class="batch-input-rupiah batch-potongan-kasbon w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ $payroll->potongan_kasbon > 0 ? number_format($payroll->potongan_kasbon, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               oninput="onPotonganRowInput(this)">
                                    @else
                                        <span class="font-bold text-slate-700 text-xs">{{ $payroll->potongan_kasbon > 0 ? 'Rp ' . number_format($payroll->potongan_kasbon, 0, ',', '.') : '-' }}</span>
                                    @endif
                                </td>

                                {{-- PENGURANGAN DEPOSIT (KARYAWAN BARU) --}}
                                <td class="px-2.5 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="text"
                                               class="batch-input-rupiah batch-potongan-deposit w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ $payroll->potongan_deposit > 0 ? number_format($payroll->potongan_deposit, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               title="Pengurangan deposit untuk karyawan baru"
                                               oninput="onPotonganRowInput(this)">
                                    @else
                                        <span class="font-bold text-slate-700 text-xs">{{ $payroll->potongan_deposit > 0 ? 'Rp ' . number_format($payroll->potongan_deposit, 0, ',', '.') : '-' }}</span>
                                    @endif
                                </td>

                                {{-- POTONGAN LAIN & KETERANGAN --}}
                                <td class="px-2.5 py-2 text-right">
                                    @if(!$isRowLocked)
                                        <input type="text"
                                               class="batch-input-rupiah batch-potongan-dll w-full text-right bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2.5 py-1 text-xs font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                               value="{{ $payroll->potongan_dll > 0 ? number_format($payroll->potongan_dll, 0, ',', '.') : '' }}"
                                               placeholder="0"
                                               oninput="onPotonganRowInput(this)">
                                        <input type="text"
                                               class="batch-catatan-potongan-dll w-full text-left bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-md px-2 py-0.5 text-[10.5px] font-medium text-slate-700 focus:bg-white focus:outline-none focus:ring-1 focus:ring-rose-400 mt-1 placeholder:text-slate-300 placeholder:italic"
                                               value="{{ $payroll->catatan_potongan_dll ?? '' }}"
                                               placeholder="Keterangan..."
                                               title="Keterangan / rincian potongan lain-lain">
                                    @else
                                        <div class="font-bold text-slate-700 text-xs">{{ $payroll->potongan_dll > 0 ? 'Rp ' . number_format($payroll->potongan_dll, 0, ',', '.') : '-' }}</div>
                                        @if($payroll->catatan_potongan_dll)
                                            <div class="text-[10px] text-slate-500 italic font-medium truncate max-w-[130px] ml-auto" title="{{ $payroll->catatan_potongan_dll }}">
                                                {{ $payroll->catatan_potongan_dll }}
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                {{-- TOTAL POTONGAN (LIVE CALCULATED) --}}
                                <td class="px-4 py-3 text-right font-black text-rose-700 text-xs whitespace-nowrap row-total-potongan-cell">
                                    @if($payroll->total_potongan > 0)
                                        -&nbsp;Rp&nbsp;{{ number_format($payroll->total_potongan, 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400 font-bold">Rp&nbsp;0</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if(!$isRowLocked)
                                        <button type="button"
                                                onclick="openModalEditPotongan({{ json_encode([
                                                    'id' => $payroll->id,
                                                    'nama' => $payroll->karyawan->nama_karyawan ?? '-',
                                                    'jabatan' => $payroll->karyawan->jabatan ?? '-',
                                                    'departemen' => $payroll->karyawan->departemen ?? '-',
                                                    'hari_kerja' => $payroll->hari_kerja ?? 0,
                                                    'terlambat_sum' => $payroll->terlambat_sum ?? 0,
                                                    'potongan_terlambat' => $payroll->potongan_terlambat ?? 0,
                                                    'potongan_inventaris' => $payroll->potongan_inventaris ?? 0,
                                                    'potongan_kasbon' => $payroll->potongan_kasbon ?? 0,
                                                    'potongan_deposit' => $payroll->potongan_deposit ?? 0,
                                                    'potongan_dll' => $payroll->potongan_dll ?? 0,
                                                    'catatan_potongan_dll' => $payroll->catatan_potongan_dll ?? '',
                                                    'update_url' => route('penggajian.potongan.update', $payroll->id),
                                                    'keterlambatan_url' => route('keterlambatan.index', ['periode' => $targetPeriode]),
                                                ]) }})"
                                                style="background-color: #fff1f2; border: 1.5px solid #fecdd3; color: #9f1239; font-weight: 800; font-size: 11.5px; padding: 4px 10px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background .15s;"
                                                onmouseover="this.style.background='#ffe4e6'" onmouseout="this.style.background='#fff1f2'"
                                                title="Edit Komponen Potongan via Pop-up">
                                            <span>&#9999;</span> Detail
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
    {{-- POPUP MODAL: EDIT POTONGAN & PENGURANGAN KARYAWAN --}}
    {{-- ========================================================================= --}}
    <div id="modalEditPotongan" style="display: none;">
        {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
             onclick="closeModalEditPotongan()"></div>

        {{-- MODAL CONTAINER CENTERED --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
            <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden;">
                
                {{-- MODAL HEADER --}}
                <div style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                                Edit Potongan &amp; Pengurangan Karyawan
                            </h3>
                            <span style="font-size: 10.5px; font-weight: 800; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 20px; padding: 2px 8px;">
                                Outlet {{ $selectedOutlet }}
                            </span>
                            <span style="font-size: 10.5px; font-weight: 700; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 20px; padding: 2px 8px;">
                                {{ \App\Models\Penggajian::formatPeriode($targetPeriode) }}
                            </span>
                        </div>
                        <p style="font-size: 11px; color: #475569; font-weight: 600; margin: 2px 0 0 0;">
                            Kelola denda keterlambatan, kasbon, pengurangan deposit, inventaris, dan potongan lainnya.
                        </p>
                    </div>
                    <button type="button" onclick="closeModalEditPotongan()"
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
                            <div style="font-size: 14px; font-weight: 800; color: #0f172a;" id="modalPotonganNama">-</div>
                            <div style="font-size: 11.5px; color: #7A4517; font-weight: 700; margin-top: 1px;" id="modalPotonganSub">-</div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">
                                Hari Kerja: <strong class="text-slate-800" id="modalPotonganHariKerja">0 Hari</strong>
                            </div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px 12px; text-align: right;">
                            <div style="font-size: 9.5px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.3px;">Rekap Keterlambatan Bulan Ini</div>
                            <div style="font-size: 13.5px; font-weight: 800; color: #dc2626; margin-top: 1px;" id="modalPotonganTerlambatSum">Rp 0</div>
                            <a href="#" target="_blank" id="modalPotonganLinkAbsensi"
                               style="font-size: 10px; color: #2563eb; text-decoration: none; font-weight: 700; display: inline-block; margin-top: 2px;">
                                Lihat Detail Absensi &rarr;
                            </a>
                        </div>
                    </div>

                    {{-- FORM INPUTS --}}
                    <form action="" method="POST" id="formEditPotonganModal">
                        @csrf
                        @method('PUT')

                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff;">
                            <div style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                                <span>&#9986;</span> Komponen Potongan &amp; Pengurangan
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        <span>Denda Keterlambatan (Otomatis)</span>
                                        <a href="#" target="_blank" id="mLinkKeterlambatanInput"
                                           style="font-size: 10px; color: #2563eb; text-decoration: none; font-weight: 700; text-transform: none;">
                                            Menu Keterlambatan &rarr;
                                        </a>
                                    </label>
                                    <input type="text" name="potongan_terlambat" id="mInputPotTerlambat" readonly
                                           class="w-full border border-slate-200 bg-slate-100 rounded-lg px-3 py-1.5 text-xs font-black text-slate-700 text-right cursor-not-allowed modal-rupiah-potongan"
                                           title="Denda keterlambatan terisi otomatis melalui Menu Keterlambatan">
                                    <div style="font-size: 9.5px; color: #64748b; margin-top: 3px;">
                                        * Diisi &amp; dihitung otomatis dari menu <strong>Keterlambatan</strong>
                                    </div>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Kerusakan Inventaris (Rp)
                                    </label>
                                    <input type="text" name="potongan_inventaris" id="mInputPotInventaris"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-rose-500/20 modal-rupiah-potongan"
                                           oninput="recalcModalPotongan()">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Potongan Kasbon (Rp)
                                    </label>
                                    <input type="text" name="potongan_kasbon" id="mInputPotKasbon"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-rose-500/20 modal-rupiah-potongan"
                                           oninput="recalcModalPotongan()">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Pengurangan Deposit (Karyawan Baru) (Rp)
                                    </label>
                                    <input type="text" name="potongan_deposit" id="mInputPotDeposit"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-rose-500/20 modal-rupiah-potongan"
                                           oninput="recalcModalPotongan()">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Potongan Lain-lain (Rp)
                                    </label>
                                    <input type="text" name="potongan_dll" id="mInputPotDll"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-rose-500/20 modal-rupiah-potongan"
                                           oninput="recalcModalPotongan()">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 4px;">
                                        Keterangan Potongan Lain-lain
                                    </label>
                                    <input type="text" name="catatan_potongan_dll" id="mInputCatatanPotDll"
                                           placeholder="Contoh: Seragam, ID Card, dll..."
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                                </div>
                            </div>
                        </div>

                        {{-- SUMMARY POTONGAN --}}
                        <div style="background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%); border: 1.5px solid #fca5a5; border-radius: 12px; padding: 12px 16px; margin-top: 12px; text-align: center;">
                            <div style="font-size: 10px; color: #991b1b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px;">Total Potongan &amp; Pengurangan</div>
                            <div style="font-size: 16px; font-weight: 900; margin-top: 2px; color: #dc2626;" id="mSumTotalPotongan">Rp 0</div>
                        </div>
                    </form>
                </div>

                {{-- MODAL FOOTER --}}
                <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-shrink: 0;">
                    <button type="button" onclick="closeModalEditPotongan()"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                        <span>&times;</span> Batal
                    </button>
                    <button type="submit" form="formEditPotonganModal"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 18px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #7A4517; border: none; cursor: pointer; transition: all .15s; box-shadow: 0 2px 4px rgba(122,69,23,0.25);"
                            onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                        <span style="font-size: 14px;">&#10003;</span> Simpan Data Potongan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatRupiahPot(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        function parseRupiahPotVal(val) {
            if (!val) return 0;
            let clean = String(val).replace(/[^0-9]/g, '');
            return parseFloat(clean) || 0;
        }

        function onPotonganRowInput(el) {
            // Format input as thousands separator
            let raw = String(el.value).replace(/[^0-9]/g, '');
            if (raw) {
                el.value = Math.round(parseFloat(raw)).toLocaleString('id-ID');
            } else {
                el.value = '';
            }

            const row = el.closest('.payroll-row');
            if (!row) return;

            recalcRowPotongan(row);
            recalcGrandTotalPotongan();
        }

        function recalcRowPotongan(row) {
            const terlambatEl = row.querySelector('.potongan-terlambat-raw');
            const inventarisEl = row.querySelector('.batch-potongan-inventaris');
            const kasbonEl = row.querySelector('.batch-potongan-kasbon');
            const depositEl = row.querySelector('.batch-potongan-deposit');
            const dllEl = row.querySelector('.batch-potongan-dll');
            const totalCell = row.querySelector('.row-total-potongan-cell');

            let pTerlambat  = terlambatEl ? parseFloat(terlambatEl.value) || 0 : 0;
            let pInventaris = inventarisEl ? parseRupiahPotVal(inventarisEl.value) : 0;
            let pKasbon     = kasbonEl ? parseRupiahPotVal(kasbonEl.value) : 0;
            let pDeposit    = depositEl ? parseRupiahPotVal(depositEl.value) : 0;
            let pDll        = dllEl ? parseRupiahPotVal(dllEl.value) : 0;

            let totalRow = pTerlambat + pInventaris + pKasbon + pDeposit + pDll;

            if (totalCell) {
                if (totalRow > 0) {
                    totalCell.innerHTML = '- ' + formatRupiahPot(totalRow);
                } else {
                    totalCell.innerHTML = '<span class="text-slate-400 font-bold">Rp 0</span>';
                }
            }
        }

        function recalcGrandTotalPotongan() {
            let grandTotal = 0;
            document.querySelectorAll('.payroll-row').forEach(row => {
                const terlambatEl = row.querySelector('.potongan-terlambat-raw');
                const inventarisEl = row.querySelector('.batch-potongan-inventaris');
                const kasbonEl = row.querySelector('.batch-potongan-kasbon');
                const depositEl = row.querySelector('.batch-potongan-deposit');
                const dllEl = row.querySelector('.batch-potongan-dll');

                let pTerlambat  = terlambatEl ? parseFloat(terlambatEl.value) || 0 : 0;
                let pInventaris = inventarisEl ? parseRupiahPotVal(inventarisEl.value) : 0;
                let pKasbon     = kasbonEl ? parseRupiahPotVal(kasbonEl.value) : 0;
                let pDeposit    = depositEl ? parseRupiahPotVal(depositEl.value) : 0;
                let pDll        = dllEl ? parseRupiahPotVal(dllEl.value) : 0;

                grandTotal += (pTerlambat + pInventaris + pKasbon + pDeposit + pDll);
            });

            const badge = document.getElementById('headerTotalPotonganBadge');
            if (badge) {
                badge.textContent = 'Total Potongan: ' + formatRupiahPot(grandTotal);
            }
        }

        async function submitBatchPotongan(btn) {
            const rows = document.querySelectorAll('.payroll-row');
            if (!rows.length) return;

            const items = [];
            rows.forEach(row => {
                const id = row.getAttribute('data-id');
                const karyawanId = row.getAttribute('data-karyawan-id');
                if (!id && !karyawanId) return;

                const terlambatEl = row.querySelector('.potongan-terlambat-raw');
                const inventarisEl = row.querySelector('.batch-potongan-inventaris');
                const kasbonEl = row.querySelector('.batch-potongan-kasbon');
                const depositEl = row.querySelector('.batch-potongan-deposit');
                const dllEl = row.querySelector('.batch-potongan-dll');
                const catatanDllEl = row.querySelector('.batch-catatan-potongan-dll');

                items.push({
                    id: id,
                    karyawan_id: karyawanId,
                    potongan_terlambat: terlambatEl ? parseFloat(terlambatEl.value) || 0 : 0,
                    potongan_inventaris: inventarisEl ? parseRupiahPotVal(inventarisEl.value) : 0,
                    potongan_kasbon: kasbonEl ? parseRupiahPotVal(kasbonEl.value) : 0,
                    potongan_deposit: depositEl ? parseRupiahPotVal(depositEl.value) : 0,
                    potongan_dll: dllEl ? parseRupiahPotVal(dllEl.value) : 0,
                    catatan_potongan_dll: catatanDllEl ? catatanDllEl.value : '',
                });
            });

            const origContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>&#8987;</span> Menyimpan...';

            try {
                const response = await fetch("{{ route('penggajian.potongan.batch-update') }}", {
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
                            text: res.message || 'Seluruh data potongan berhasil diperbarui.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert(res.message || 'Seluruh data potongan berhasil disimpan!');
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

        function openModalEditPotongan(data) {
            document.getElementById('modalPotonganNama').textContent = data.nama;
            document.getElementById('modalPotonganSub').textContent = (data.jabatan || '-') + ' · ' + (data.departemen || '-');
            document.getElementById('modalPotonganHariKerja').textContent = (data.hari_kerja || 0) + ' Hari';
            
            let terlambatSum = parseFloat(data.terlambat_sum) || 0;
            document.getElementById('modalPotonganTerlambatSum').textContent = formatRupiahPot(terlambatSum);
            document.getElementById('modalPotonganLinkAbsensi').href = data.keterlambatan_url || '#';
            if (document.getElementById('mLinkKeterlambatanInput')) {
                document.getElementById('mLinkKeterlambatanInput').href = data.keterlambatan_url || '#';
            }

            let pTerlambat = parseFloat(data.potongan_terlambat) || 0;
            let pInventaris = parseFloat(data.potongan_inventaris) || 0;
            let pKasbon = parseFloat(data.potongan_kasbon) || 0;
            let pDeposit = parseFloat(data.potongan_deposit) || 0;
            let pDll = parseFloat(data.potongan_dll) || 0;

            document.getElementById('mInputPotTerlambat').value = pTerlambat ? Math.round(pTerlambat).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotInventaris').value = pInventaris ? Math.round(pInventaris).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotKasbon').value = pKasbon ? Math.round(pKasbon).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotDeposit').value = pDeposit ? Math.round(pDeposit).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotDll').value = pDll ? Math.round(pDll).toLocaleString('id-ID') : '0';
            document.getElementById('mInputCatatanPotDll').value = data.catatan_potongan_dll || '';

            document.getElementById('formEditPotonganModal').action = data.update_url;

            recalcModalPotongan();

            document.getElementById('modalEditPotongan').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModalEditPotongan() {
            document.getElementById('modalEditPotongan').style.display = 'none';
            document.body.style.overflow = '';
        }

        function recalcModalPotongan() {
            let potTerlambat  = parseRupiahPotVal(document.getElementById('mInputPotTerlambat').value);
            let potInventaris = parseRupiahPotVal(document.getElementById('mInputPotInventaris').value);
            let potKasbon     = parseRupiahPotVal(document.getElementById('mInputPotKasbon').value);
            let potDeposit    = parseRupiahPotVal(document.getElementById('mInputPotDeposit').value);
            let potDll        = parseRupiahPotVal(document.getElementById('mInputPotDll').value);

            let totalDeductions = potTerlambat + potInventaris + potKasbon + potDeposit + potDll;
            if (totalDeductions > 0) {
                document.getElementById('mSumTotalPotongan').innerText = '- ' + formatRupiahPot(totalDeductions);
            } else {
                document.getElementById('mSumTotalPotongan').innerText = 'Rp 0';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const rupiahInputs = document.querySelectorAll('.modal-rupiah-potongan');
            rupiahInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let raw = this.value.replace(/[^0-9]/g, '');
                    if (raw) {
                        this.value = Math.round(parseFloat(raw)).toLocaleString('id-ID');
                    } else {
                        this.value = '0';
                    }
                    recalcModalPotongan();
                });
            });

            document.getElementById('formEditPotonganModal').addEventListener('submit', function() {
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
