<x-app-layout>

    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Laporan Perubahan Ekuitas
                </h2>
                <button type="button"
                        onclick="document.getElementById('info-modal').classList.remove('hidden')"
                        class="text-slate-400 hover:text-slate-600 transition-colors"
                        title="Apa itu laporan ini?">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <span class="text-sm text-gray-500">
                {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y') }}
                &ndash;
                {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- ============ FILTER ============ --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-4">

                    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                        <div class="flex-1">
                            <label for="start_date" class="block text-xs font-medium text-slate-500 mb-1.5">
                                Tanggal Mulai
                            </label>
                            <input type="date" id="start_date" name="start_date" value="{{ $startDate }}"
                                   max="{{ $endDate }}"
                                   class="w-full rounded-lg border-slate-300 text-sm text-slate-800
                                          focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div class="flex-1">
                            <label for="end_date" class="block text-xs font-medium text-slate-500 mb-1.5">
                                Tanggal Selesai
                            </label>
                            <input type="date" id="end_date" name="end_date" value="{{ $endDate }}"
                                   min="{{ $startDate }}"
                                   class="w-full rounded-lg border-slate-300 text-sm text-slate-800
                                          focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900
                                           px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700
                                           active:bg-slate-800 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                                Tampilkan
                            </button>
                            <a href="{{ url()->current() }}"
                               class="inline-flex items-center justify-center rounded-lg border border-slate-300
                                      px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                                Reset
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100">
                        <span class="text-xs text-slate-400 mr-1">Pilihan cepat</span>
                        @php
                            $presets = [
                                'Bulan Ini' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                                'Bulan Lalu' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                                'Tahun Ini' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
                                'Tahun Lalu' => [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
                            ];
                            $isActivePreset = fn($range) => $range[0] === $startDate && $range[1] === $endDate;
                        @endphp
                        @foreach ($presets as $label => $range)
                            <a href="{{ url()->current() }}?start_date={{ $range[0] }}&end_date={{ $range[1] }}"
                               class="text-xs px-3 py-1.5 rounded-full border transition-colors
                                      {{ $isActivePreset($range)
                                            ? 'bg-slate-900 border-slate-900 text-white'
                                            : 'border-slate-200 text-slate-600 hover:border-slate-400 hover:bg-slate-50' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </form>
            </div>

            @if ($modalAwal == 0 && $penambahanModal == 0 && $labaRugiBersih == 0 && $prive == 0)
                {{-- ============ STATE KOSONG ============ --}}
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                    <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             class="w-7 h-7 text-slate-300">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M3 3v18h18M7 15l4-4 3 3 5-6" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-slate-700">Belum ada data untuk periode ini</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">
                        Coba pilih rentang tanggal lain, atau pastikan transaksi pada periode ini sudah dicatat.
                    </p>
                </div>
            @else

                {{-- ============ RINGKASAN (3 kartu utama) ============ --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                        <div class="w-9 h-9 rounded-lg bg-slate-50 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5 text-slate-500">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7.5A1.5 1.5 0 014.5 6h13.5A1.5 1.5 0 0119.5 7.5v9a1.5 1.5 0 01-1.5 1.5H4.5A1.5 1.5 0 013 16.5v-9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 6V5a2 2 0 00-2-2H7a2 2 0 00-2 2v1M15 12h.01" />
                            </svg>
                        </div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Modal Awal</p>
                        <p class="text-2xl font-bold text-slate-900 tabular-nums break-words leading-tight">
                            Rp {{ number_format($modalAwal, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-slate-400 mt-1.5">Sebelum periode berjalan</p>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 relative overflow-hidden">
                        <span class="absolute left-0 top-0 bottom-0 w-1 {{ $perubahanEkuitas >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-3
                                    {{ $perubahanEkuitas >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }}">
                            @if ($perubahanEkuitas >= 0)
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5 text-emerald-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 17l6-6 4 4 7-8M20 9v6M20 9h-6" />
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5 text-rose-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7l6 6 4-4 7 8M20 11v6M20 17h-6" />
                                </svg>
                            @endif
                        </div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Perubahan Ekuitas</p>
                        <p class="text-2xl font-bold tabular-nums break-words leading-tight
                                  {{ $perubahanEkuitas >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $perubahanEkuitas >= 0 ? '+' : '–' }} Rp {{ number_format(abs($perubahanEkuitas), 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-slate-400 mt-1.5">Selama periode berjalan</p>
                    </div>

                    <div class="bg-slate-900 rounded-2xl shadow-sm p-5">
                        <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75l2.25 2.25 4.5-4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-xs font-medium text-slate-300 mb-1">Modal Akhir</p>
                        <p class="text-2xl font-bold text-white tabular-nums break-words leading-tight">
                            Rp {{ number_format($modalAkhir, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-slate-400 mt-1.5">Saldo akhir periode</p>
                    </div>

                </div>

                {{-- ============ RINCIAN — alur perubahan ekuitas ============ --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">Rincian Perubahan Ekuitas</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Alur dari modal awal hingga modal akhir periode</p>
                        </div>
                        <button type="button" onclick="window.print()"
                                class="print:hidden inline-flex items-center gap-1.5 text-xs text-slate-500
                                       hover:text-slate-800 transition-colors shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.097 1.126.153A2.212 2.212 0 0117 8.653v4.097A2.25 2.25 0 0114.75 15h-.241l.305 1.984A1.75 1.75 0 0113.084 19H6.916a1.75 1.75 0 01-1.73-2.016L5.491 15H5.25A2.25 2.25 0 013 12.75V8.653c0-1.082.775-2.034 1.874-2.198.374-.056.75-.107 1.126-.153V2.75zm8.5 3.397a41.533 41.533 0 00-7 0V2.75a.25.25 0 01.25-.25h6.5a.25.25 0 01.25.25v3.397z" clip-rule="evenodd" />
                            </svg>
                            Cetak
                        </button>
                    </div>

                    {{-- Timeline vertikal: tiap langkah terhubung garis, arah +/- terlihat dari warna & tanda --}}
                    <div class="px-6 py-5">
                        <div class="relative">
                            <div class="absolute left-4 top-4 bottom-4 w-px bg-slate-200"></div>

                            <div class="relative flex items-center gap-4 pb-6">
                                <div class="relative z-10 w-8 h-8 rounded-full bg-slate-100 border-2 border-white ring-1 ring-slate-200 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-slate-500">
                                        <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1 flex items-center justify-between min-w-0">
                                    <span class="text-sm text-slate-600">Modal Awal Periode</span>
                                    <span class="text-sm font-semibold text-slate-900 tabular-nums">
                                        Rp {{ number_format($modalAwal, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <div class="relative flex items-center gap-4 pb-6">
                                <div class="relative z-10 w-8 h-8 rounded-full bg-emerald-50 border-2 border-white ring-1 ring-emerald-100 flex items-center justify-center shrink-0">
                                    <span class="text-emerald-600 text-sm font-bold leading-none">+</span>
                                </div>
                                <div class="flex-1 flex items-center justify-between min-w-0">
                                    <span class="text-sm text-slate-600">Setoran Modal</span>
                                    <span class="text-sm font-semibold text-emerald-600 tabular-nums">
                                        Rp {{ number_format($penambahanModal, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <div class="relative flex items-center gap-4 pb-6">
                                <div class="relative z-10 w-8 h-8 rounded-full border-2 border-white ring-1 flex items-center justify-center shrink-0
                                            {{ $labaRugiBersih >= 0 ? 'bg-emerald-50 ring-emerald-100' : 'bg-rose-50 ring-rose-100' }}">
                                    <span class="text-sm font-bold leading-none {{ $labaRugiBersih >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $labaRugiBersih >= 0 ? '+' : '–' }}
                                    </span>
                                </div>
                                <div class="flex-1 flex items-center justify-between min-w-0">
                                    <span class="text-sm text-slate-600">
                                        {{ $labaRugiBersih >= 0 ? 'Laba Bersih Periode Berjalan' : 'Rugi Bersih Periode Berjalan' }}
                                    </span>
                                    <span class="text-sm font-semibold tabular-nums {{ $labaRugiBersih >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        Rp {{ number_format(abs($labaRugiBersih), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <div class="relative flex items-center gap-4">
                                <div class="relative z-10 w-8 h-8 rounded-full bg-rose-50 border-2 border-white ring-1 ring-rose-100 flex items-center justify-center shrink-0">
                                    <span class="text-rose-600 text-sm font-bold leading-none">–</span>
                                </div>
                                <div class="flex-1 flex items-center justify-between min-w-0">
                                    <span class="text-sm text-slate-600">Prive / Pengambilan Pemilik</span>
                                    <span class="text-sm font-semibold text-rose-600 tabular-nums">
                                        Rp {{ number_format($prive, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 pb-6 space-y-2">
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                            <span class="text-sm font-medium text-slate-600">Total Perubahan Ekuitas</span>
                            <span class="text-sm font-bold tabular-nums {{ $perubahanEkuitas >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $perubahanEkuitas >= 0 ? '+' : '–' }} Rp {{ number_format(abs($perubahanEkuitas), 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-slate-900 px-4 py-3.5">
                            <span class="text-sm font-semibold text-white">Modal Akhir Periode</span>
                            <span class="text-base font-bold text-white tabular-nums">
                                Rp {{ number_format($modalAkhir, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="text-center print:hidden pt-1">
                <button type="button"
                        onclick="document.getElementById('info-modal').classList.remove('hidden')"
                        class="text-xs text-slate-400 hover:text-slate-600 underline underline-offset-2">
                    Bagaimana angka-angka ini dihitung?
                </button>
            </div>

        </div>
    </div>

    {{-- ============ MODAL INFO / BANTUAN ============ --}}
    <div id="info-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40"
         onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-lg max-w-md w-full p-6">
            <div class="flex items-start justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-800">Tentang Laporan Perubahan Ekuitas</h3>
                <button type="button" onclick="document.getElementById('info-modal').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>
            <ul class="text-xs text-slate-500 leading-relaxed space-y-2 list-disc pl-4">
                <li><span class="font-medium text-slate-600">Modal Awal</span> — saldo kumulatif akun Modal (3101) dan Laba Ditahan (3103) sebelum tanggal mulai.</li>
                <li><span class="font-medium text-slate-600">Laba/Rugi Bersih</span> — total Pendapatan (kepala akun 4) dikurangi total HPP &amp; Beban (kepala akun 5 dan 6) selama periode berjalan.</li>
                <li><span class="font-medium text-slate-600">Prive</span> — pengambilan dana atau aset oleh pemilik untuk keperluan pribadi, di luar operasional usaha.</li>
            </ul>
        </div>
    </div>

</x-app-layout>