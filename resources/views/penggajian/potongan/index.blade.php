<x-app-layout>
    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 mb-3">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h1 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">
                            Daftar Potongan &amp; Pengurangan Karyawan - Outlet {{ $selectedOutlet }}
                        </h1>
                        <p class="text-xs text-slate-600 font-medium mt-0.5">
                            Kelola komponen denda keterlambatan, kerusakan inventaris, kasbon, dan potongan lainnya per periode.
                        </p>
                    </div>

                    <div class="flex gap-2 items-center flex-wrap">
                        {{-- Dropdown Pilih Bulan / Periode (sama seperti Keterlambatan) --}}
                        <select class="form-select form-select-sm" style="width: auto; min-width: 160px; padding: 6px 28px 6px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #ffffff; outline: none; cursor: pointer;"
                                onchange="if(this.value) window.location.href='{{ route('penggajian.potongan.periode') }}?periode=' + this.value + '&outlet={{ $selectedOutlet }}'">
                            <option value="">-- Pilih Bulan Potongan --</option>
                            @foreach($periodes as $p)
                                @php $carbonP = \Carbon\Carbon::parse($p . '-01'); @endphp
                                <option value="{{ $p }}">
                                    {{ $carbonP->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>

                        <form action="{{ route('penggajian.potongan.index') }}" method="GET" class="flex gap-2">
                            <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                            <input type="text" name="search" class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-900 placeholder:text-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-[#7A4517]/20" placeholder="Cari periode..." value="{{ request('search') }}" style="width: 200px;">
                            <button type="submit" style="background-color: #0f172a; color: #ffffff; font-weight: 700; padding: 6px 14px; border-radius: 8px; font-size: 12px; border: none; cursor: pointer; transition: background .15s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#0f172a'">
                                Cari
                            </button>
                            @if(request('search'))
                                <a href="{{ route('penggajian.potongan.index', ['outlet' => $selectedOutlet]) }}" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 700; padding: 6px 12px; border-radius: 8px; font-size: 12px; text-decoration: none;">
                                    Reset
                                </a>
                            @endif
                        </form>
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
                            <th class="px-4 py-2.5 text-right">Total Potongan &amp; Denda</th>
                            <th class="px-4 py-2.5 text-center">Status</th>
                            <th class="px-4 py-2.5 text-center w-44">Aksi</th>
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
                            $totalPotonganPeriode = $items->sum(function($p) {
                                return ($p->potongan_terlambat ?? 0) + ($p->potongan_inventaris ?? 0) + ($p->potongan_kasbon ?? 0) + ($p->potongan_deposit ?? 0) + ($p->potongan_dll ?? 0);
                            });
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-4 py-2.5 text-center font-bold text-slate-500">{{ $no++ }}</td>
                            <td class="px-4 py-2.5 font-extrabold text-slate-900">
                                <span>&#128197;</span> {{ \App\Models\Penggajian::formatPeriode($periode) }}
                            </td>
                            <td class="px-4 py-2.5 text-center font-bold text-slate-700">{{ $items->count() }} Orang</td>
                            <td class="px-4 py-2.5 text-right font-black text-rose-700">Rp {{ number_format($totalPotonganPeriode, 0, ',', '.') }}</td>
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
                                <a href="{{ route('penggajian.potongan.periode', ['periode' => $periode, 'outlet' => $selectedOutlet]) }}"
                                   style="background-color: #7A4517; color: #ffffff; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 11.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: background .15s;"
                                   onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                                    <span>&#9986;</span> Kelola Potongan
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500 font-medium">
                                Belum ada data periode penggajian aktif. Silakan buat periode di menu <a href="{{ route('penggajian.index', ['outlet' => $selectedOutlet]) }}" class="text-[#7A4517] font-bold underline">Hitung Gaji Pokok</a> terlebih dahulu.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $periods->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
