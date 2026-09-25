<x-app-layout>
    <div class="py-4" x-data="{ openModalPeriode: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 mb-3">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h1 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">
                            Sistem Pencatatan Gaji Pokok &amp; Jurnal - Outlet {{ $selectedOutlet }}
                        </h1>
                        <p class="text-xs text-slate-600 font-medium mt-0.5">
                            Kelola data gaji pokok &amp; hari kerja per periode dan integrasi jurnal umum untuk Outlet {{ $selectedOutlet }}.
                        </p>
                    </div>

                    <div class="flex gap-2 items-center flex-wrap">
                        {{-- Dropdown Pilih Bulan / Periode (sama seperti Keterlambatan) --}}
                        <select class="form-select form-select-sm" style="width: auto; min-width: 160px; padding: 6px 28px 6px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;"
                                onchange="if(this.value) window.location.href='{{ route('penggajian.show-periode') }}?periode=' + this.value + '&outlet={{ $selectedOutlet }}'">
                            <option value="">-- Pilih Bulan Gaji --</option>
                            @foreach($periodes as $p)
                                @php $carbonP = \Carbon\Carbon::parse($p . '-01'); @endphp
                                <option value="{{ $p }}">
                                    {{ $carbonP->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>

                        <form action="{{ route('penggajian.index') }}" method="GET" class="flex gap-2">
                            <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                            <input type="text" name="search" class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-900 placeholder:text-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-[#7A4517]/20" placeholder="Cari periode..." value="{{ request('search') }}" style="width: 200px;">
                            <button type="submit" style="background-color: #0f172a; color: #ffffff; font-weight: 700; padding: 6px 14px; border-radius: 8px; font-size: 12px; border: none; cursor: pointer; transition: background .15s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#0f172a'">
                                Cari
                            </button>
                            @if(request('search'))
                                <a href="{{ route('penggajian.index', ['outlet' => $selectedOutlet]) }}" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 700; padding: 6px 12px; border-radius: 8px; font-size: 12px; text-decoration: none;">
                                    Reset
                                </a>
                            @endif
                        </form>
                        <button @click="openModalPeriode = true"
                                style="background-color: #7A4517; color: #ffffff; padding: 6px 14px; border-radius: 8px; font-weight: 800; font-size: 12px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: background .15s;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                            <span style="font-size: 14px; line-height: 1;">+</span> Buat Periode Baru
                        </button>
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

            <div class="overflow-x-auto border border-slate-200 rounded-xl shadow-sm bg-white">
                <table class="w-full text-xs text-left">
                    <thead class="text-[11px] font-bold text-slate-700 uppercase tracking-wider bg-slate-100 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5 text-center w-12">No</th>
                            <th class="px-4 py-2.5 min-w-[200px]">Periode Bulan-Tahun</th>
                            <th class="px-4 py-2.5 text-center">Jumlah Karyawan</th>
                            <th class="px-4 py-2.5 text-right">Total Gaji Kolektif</th>
                            <th class="px-4 py-2.5 text-center">Status Approval</th>
                            <th class="px-4 py-2.5 text-center w-56">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @php
                        $groupedPayrolls = $payrolls->groupBy('periode_bulan_tahun');
                        $no = 1;
                        @endphp

                        @forelse($groupedPayrolls as $periode => $items)
                        @php
                        $currentStatus = $items->first()->status;
                        $statusJurnal = $items->first()->status_jurnal;
                        $totalGajiPeriode = $items->sum('total_gaji_bersih');
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-4 py-2.5 text-center font-bold text-slate-500">{{ $no++ }}</td>
                            <td class="px-4 py-2.5 font-extrabold text-slate-900">
                                <span>&#128197;</span> {{ \App\Models\Penggajian::formatPeriode($periode) }}
                            </td>
                            <td class="px-4 py-2.5 text-center font-bold text-slate-700">{{ $items->count() }} Orang</td>
                            <td class="px-4 py-2.5 text-right font-black text-slate-900">Rp {{ number_format($totalGajiPeriode, 0, ',', '.') }}</td>

                            <td class="px-4 py-2.5 text-center">
                                @if($currentStatus == 'draft')
                                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-300">Draft</span>
                                @elseif($currentStatus == 'waiting approval')
                                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-900 border border-amber-300">Waiting Approval</span>
                                @elseif($currentStatus == 'approved')
                                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300">Approved</span>
                                @endif
                            </td>

                            <td class="px-4 py-2.5 text-center">
                                <div class="flex justify-center items-center gap-1.5 flex-wrap">

                                    <a href="{{ route('penggajian.show-periode', ['periode' => $periode, 'outlet' => $selectedOutlet]) }}"
                                       style="background-color: #0284c7; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; text-decoration: none; transition: background .15s;"
                                       onmouseover="this.style.background='#0369a1'" onmouseout="this.style.background='#0284c7'">
                                        Detail
                                    </a>

                                    @if($currentStatus == 'draft')
                                    <form action="{{ route('penggajian.ajukanApproval') }}" method="POST" class="inline m-0 p-0">
                                        @csrf
                                        <input type="hidden" name="periode" value="{{ $periode }}">
                                        <button type="submit" style="background-color: #d97706; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer; transition: background .15s;"
                                                onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#d97706'">
                                            Ajukan
                                        </button>
                                    </form>
                                    @endif

                                    @if($currentStatus == 'waiting approval')
                                    <form action="{{ route('penggajian.approve') }}" method="POST" class="inline m-0 p-0">
                                        @csrf
                                        <input type="hidden" name="periode" value="{{ $periode }}">
                                        <button type="submit" style="background-color: #16a34a; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer; transition: background .15s;"
                                                onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                                            Approve
                                        </button>
                                    </form>
                                    @endif

                                    @if($currentStatus == 'approved' && !$statusJurnal)
                                    <form action="{{ route('penggajian.kirimJurnalUmum') }}" method="POST" class="inline m-0 p-0">
                                        @csrf
                                        <input type="hidden" name="periode" value="{{ $periode }}">
                                        <button type="submit" style="background-color: #7e22ce; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer; transition: background .15s;"
                                                onmouseover="this.style.background='#6b21a8'" onmouseout="this.style.background='#7e22ce'">
                                            Jurnal
                                        </button>
                                    </form>
                                    @endif

                                    @if($statusJurnal)
                                    <span class="bg-slate-100 text-slate-700 border border-slate-300 font-bold px-2 py-0.5 rounded text-[11px]">
                                        ✓ Dijurnal
                                    </span>
                                    @endif

                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-medium">
                                Belum ada data penggajian. Silakan klik "+ Buat Periode Baru" untuk memulai.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($periods->hasPages())
            <div class="mt-4">
                {{ $periods->links() }}
            </div>
            @endif
        </div>

        <div x-show="openModalPeriode" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
                 @click="openModalPeriode = false"></div>
            <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
                <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 440px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; padding: 24px; position: relative;">
                    <div class="mb-4">
                        <h3 class="text-base font-extrabold text-slate-900">Inisiasi Periode Gaji Baru</h3>
                        <p class="text-xs text-slate-600 mt-1 font-medium">Pilih bulan dan tahun untuk membuat penampung data penggajian baru.</p>
                    </div>

                    <form action="{{ route('penggajian.show-periode') }}" method="GET">
                        <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                        <div class="mb-4">
                            <label for="periode_baru" class="block text-xs font-bold text-slate-800 mb-1.5 uppercase tracking-wider">Pilih Bulan &amp; Tahun</label>
                            <input type="month" id="periode_baru" name="periode" required
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-[#7A4517] focus:ring-[#7A4517] text-sm py-2 px-3 font-bold text-slate-900">
                        </div>

                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" @click="openModalPeriode = false"
                                    style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 700; padding: 6px 14px; border-radius: 8px; font-size: 12px; cursor: pointer; transition: background .15s;"
                                    onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                Batal
                            </button>
                            <button type="submit"
                                    style="background-color: #7A4517; color: #ffffff; font-weight: 800; padding: 6px 16px; border-radius: 8px; font-size: 12px; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(122,69,23,0.25); transition: background .15s;"
                                    onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                                Lanjut Buka Periode
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>