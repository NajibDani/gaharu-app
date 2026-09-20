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
                        <span style="font-size: 12px; font-weight: 800; background-color: #fff1f2; border: 1.5px solid #fecdd3; padding: 6px 14px; border-radius: 8px; color: #9f1239; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            Total Potongan: Rp {{ number_format($payrolls->sum('total_potongan'), 0, ',', '.') }}
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
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Denda Terlambat</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Kerusakan Inventaris</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Kasbon</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Potongan Lain</th>
                                <th class="px-3.5 py-2.5 text-right whitespace-nowrap">Total Potongan</th>
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
                                    @if($payroll->potongan_terlambat > 0)
                                        <span class="font-bold text-rose-700 text-xs">- Rp {{ number_format($payroll->potongan_terlambat, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->potongan_inventaris > 0)
                                        <span class="font-bold text-rose-700 text-xs">- Rp {{ number_format($payroll->potongan_inventaris, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->potongan_kasbon > 0)
                                        <span class="font-bold text-rose-700 text-xs">- Rp {{ number_format($payroll->potongan_kasbon, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($payroll->potongan_dll > 0)
                                        <span class="font-bold text-rose-700 text-xs">- Rp {{ number_format($payroll->potongan_dll, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-black text-rose-700 text-xs whitespace-nowrap">
                                    @if($payroll->total_potongan > 0)
                                        - Rp {{ number_format($payroll->total_potongan, 0, ',', '.') }}
                                    @else
                                        <span class="text-slate-400">Rp 0</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    @if(!$payroll->is_paid)
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
                                                    'potongan_dll' => $payroll->potongan_dll ?? 0,
                                                    'update_url' => route('penggajian.potongan.update', $payroll->id),
                                                    'keterlambatan_url' => route('keterlambatan.index', ['periode' => $targetPeriode]),
                                                ]) }})"
                                                style="background-color: #fff1f2; border: 1.5px solid #fecdd3; color: #9f1239; font-weight: 800; font-size: 11.5px; padding: 5px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: background .15s;"
                                                onmouseover="this.style.background='#ffe4e6'" onmouseout="this.style.background='#fff1f2'"
                                                title="Edit Komponen Potongan via Pop-up">
                                            <span>&#9999;</span> Edit
                                        </button>
                                    @else
                                        <span class="text-[10.5px] text-slate-400 italic font-semibold">Terkunci</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500 font-medium">
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
            <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 620px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden;">
                
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
                            Kelola denda keterlambatan, kasbon, inventaris, dan potongan lainnya.
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
                                        <span>Denda Keterlambatan</span>
                                    </label>
                                    <input type="text" name="potongan_terlambat" id="mInputPotTerlambat"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-rose-500/20 modal-rupiah-potongan"
                                           oninput="recalcModalPotongan()">
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
                                        Potongan Lain-lain (Rp)
                                    </label>
                                    <input type="text" name="potongan_dll" id="mInputPotDll"
                                           class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-black text-slate-900 text-right focus:outline-none focus:ring-2 focus:ring-rose-500/20 modal-rupiah-potongan"
                                           oninput="recalcModalPotongan()">
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

        function openModalEditPotongan(data) {
            document.getElementById('modalPotonganNama').textContent = data.nama;
            document.getElementById('modalPotonganSub').textContent = (data.jabatan || '-') + ' · ' + (data.departemen || '-');
            document.getElementById('modalPotonganHariKerja').textContent = (data.hari_kerja || 0) + ' Hari';
            
            let terlambatSum = parseFloat(data.terlambat_sum) || 0;
            document.getElementById('modalPotonganTerlambatSum').textContent = formatRupiahPot(terlambatSum);
            document.getElementById('modalPotonganLinkAbsensi').href = data.keterlambatan_url || '#';

            let pTerlambat = parseFloat(data.potongan_terlambat) || 0;
            let pInventaris = parseFloat(data.potongan_inventaris) || 0;
            let pKasbon = parseFloat(data.potongan_kasbon) || 0;
            let pDll = parseFloat(data.potongan_dll) || 0;

            document.getElementById('mInputPotTerlambat').value = pTerlambat ? Math.round(pTerlambat).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotInventaris').value = pInventaris ? Math.round(pInventaris).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotKasbon').value = pKasbon ? Math.round(pKasbon).toLocaleString('id-ID') : '0';
            document.getElementById('mInputPotDll').value = pDll ? Math.round(pDll).toLocaleString('id-ID') : '0';

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
            let potDll        = parseRupiahPotVal(document.getElementById('mInputPotDll').value);

            let totalDeductions = potTerlambat + potInventaris + potKasbon + potDll;
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
