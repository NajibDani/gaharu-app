<x-app-layout>
    <div class="py-12" x-data="{ openModalPeriode: false }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <x-outlet-selector :selectedOutlet="$selectedOutlet" />

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 border-b pb-4">
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            Sistem Pencatatan Gaji & Jurnal - Outlet {{ $selectedOutlet }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">Kelola data gaji kolektif per periode dan integrasi jurnal umum untuk Outlet {{ $selectedOutlet }}.</p>
                    </div>
                    <div class="flex gap-2 items-center">
                        <form action="{{ route('penggajian.index') }}" method="GET" class="flex gap-2">
                            <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                            <input type="text" name="search" class="border rounded px-3 py-1 text-sm" placeholder="Cari periode/karyawan..." value="{{ request('search') }}" style="width: 220px;">
                            <button type="submit" class="bg-gray-800 text-white px-3 py-1 rounded text-sm">Cari</button>
                            @if(request('search'))
                                <a href="{{ route('penggajian.index', ['outlet' => $selectedOutlet]) }}" class="bg-gray-200 text-gray-700 px-3 py-1 rounded text-sm">Reset</a>
                            @endif
                        </form>
                        <button @click="openModalPeriode = true" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg shadow-sm text-sm font-medium transition-all cursor-pointer">
                            + Buat Periode Baru
                        </button>
                    </div>
                </div>

                @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('success') }}
                </div>
                @endif

                @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('error') }}
                </div>
                @endif

                <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4 text-center w-16">No</th>
                                <th class="px-6 py-4">Periode Bulan-Tahun</th>
                                <th class="px-6 py-4 text-center">Jumlah Karyawan</th>
                                <th class="px-6 py-4 text-right">Total Gaji Kolektif</th>
                                <th class="px-6 py-4 text-center">Status Approval</th>
                                <th class="px-6 py-4 text-center w-64">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @php
                            // Mengelompokkan koleksi data $payrolls dari controller berdasarkan kolom periode_bulan_tahun
                            $groupedPayrolls = $payrolls->groupBy('periode_bulan_tahun');
                            $no = 1;
                            @endphp

                            @forelse($groupedPayrolls as $periode => $items)
                            @php
                            // Sinkronisasi status menggunakan huruf kecil (lowercase) sesuai controller kamu
                            $currentStatus = $items->first()->status;
                            $statusJurnal = $items->first()->status_jurnal;
                            $totalGajiPeriode = $items->sum('total_gaji_bersih');
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4 text-center font-medium text-gray-900">{{ $no++ }}</td>
                                <td class="px-6 py-4 font-bold text-gray-800 tracking-wide">{{ \App\Models\Penggajian::formatPeriode($periode) }}</td>
                                <td class="px-6 py-4 text-center font-medium text-gray-700">{{ $items->count() }} Orang</td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-900">Rp {{ number_format($totalGajiPeriode, 0, ',', '.') }}</td>

                                <td class="px-6 py-4 text-center">
                                    @if($currentStatus == 'draft')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 border border-gray-200">Draft</span>
                                    @elseif($currentStatus == 'waiting approval')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 border border-amber-200 animate-pulse">Waiting Approval</span>
                                    @elseif($currentStatus == 'approved')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 border border-green-200">Approved</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center items-center gap-2">

                                        <a href="{{ route('penggajian.show-periode', ['periode' => $periode, 'outlet' => $selectedOutlet]) }}"
                                            class="inline-block bg-cyan-500 hover:bg-cyan-600 text-white font-semibold px-4 py-2 rounded-lg text-xs shadow-sm transition-all text-center">
                                            Detail
                                        </a>

                                        @if($currentStatus == 'draft')
                                        <form action="{{ route('penggajian.ajukanApproval') }}" method="POST" class="inline m-0 p-0">
                                            @csrf
                                            <input type="hidden" name="periode" value="{{ $periode }}">
                                            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg text-xs shadow-sm transition-all cursor-pointer">
                                                Ajukan
                                            </button>
                                        </form>
                                        @endif

                                        @if($currentStatus == 'waiting approval')
                                        <form action="{{ route('penggajian.approve') }}" method="POST" class="inline m-0 p-0">
                                            @csrf
                                            <input type="hidden" name="periode" value="{{ $periode }}">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2 rounded-lg text-xs shadow-sm transition-all cursor-pointer">
                                                Approve
                                            </button>
                                        </form>
                                        @endif

                                        @if($currentStatus == 'approved' && !$statusJurnal)
                                        <form action="{{ route('penggajian.kirimJurnalUmum') }}" method="POST" class="inline m-0 p-0">
                                            @csrf
                                            <input type="hidden" name="periode" value="{{ $periode }}">
                                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold px-4 py-2 rounded-lg text-xs shadow-sm transition-all cursor-pointer">
                                                Jurnal
                                            </button>
                                        </form>
                                        @endif

                                        @if($statusJurnal)
                                        <span class="bg-gray-100 text-gray-600 border border-gray-200 font-medium px-3 py-2 rounded-lg text-xs italic">
                                            ✓ Dijurnal
                                        </span>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                    Belum ada data penggajian. Silakan klik "+ Buat Periode Baru" untuk memulai.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <div x-show="openModalPeriode" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="openModalPeriode = false"></div>
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Inisiasi Periode Gaji Baru</h3>
                        <p class="text-xs text-gray-500 mt-1">Pilih bulan dan tahun untuk membuat penampung data penggajian baru.</p>
                    </div>

                    <form action="{{ route('penggajian.show-periode') }}" method="GET">
                        <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                        <div class="mb-4">
                            <label for="periode_baru" class="block text-sm font-medium text-gray-700 mb-1">Pilih Bulan & Tahun</label>
                            <input type="month" id="periode_baru" name="periode" required
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        </div>

                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" @click="openModalPeriode = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium cursor-pointer">
                                Batal
                            </button>
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm cursor-pointer">
                                Lanjut Buka Periode
                            </button>
                        </div>
                    </form>
                </div>
                <div class="mt-4">
                    {{ $periods->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>