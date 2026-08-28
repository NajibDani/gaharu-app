<x-app-layout>
    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <a href="{{ route('penggajian.index', ['outlet' => $selectedOutlet]) }}"
                           class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-blue-600 transition-colors mb-2">
                            &larr; Kembali ke Ringkasan Utama
                        </a>
                        <h1 class="text-lg font-bold text-gray-800">Detail Karyawan &amp; Gaji</h1>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Periode <span class="font-semibold text-gray-700">{{ \App\Models\Penggajian::formatPeriode($periode) }}</span>
                            &nbsp;&middot;&nbsp; Outlet <span class="font-semibold text-gray-700">{{ $selectedOutlet }}</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($currentStatus == 'draft' || $currentStatus == 'waiting approval')
                        <form action="{{ route('penggajian.auto-fill') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="periode" value="{{ $periode }}">
                            <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                            <button type="submit"
                                    onclick="return confirm('Tambahkan seluruh karyawan aktif Outlet {{ $selectedOutlet }} yang belum terdaftar ke periode {{ \App\Models\Penggajian::formatPeriode($periode) }} secara otomatis?')"
                                    class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200 px-3.5 py-2 rounded-lg text-sm font-medium transition-all"
                                    title="Tambahkan otomatis semua karyawan aktif yang belum terdaftar di periode ini">
                                &#9889; Auto-Fill Karyawan
                            </button>
                        </form>

                        <a href="{{ route('penggajian.create', ['target_periode' => $periode, 'outlet' => $selectedOutlet]) }}"
                           class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 rounded-lg text-sm font-semibold transition-all"
                           title="Input slip gaji baru secara manual">
                            + Input Gaji Manual
                        </a>
                        @endif

                        <form action="{{ route('penggajian.bayar-semua', $periode) }}" method="POST" class="inline"
                              onsubmit="return confirm('Proses pembayaran dan jurnal untuk SELURUH karyawan di periode {{ \App\Models\Penggajian::formatPeriode($periode) }}?')">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition-all border border-emerald-700">
                                &#128179; Bayar Semua
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
                <span class="text-emerald-500">&#10003;</span> {{ session('success') }}
            </div>
            @endif
            @if(session('info'))
            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
                <span class="text-blue-500">&#9432;</span> {{ session('info') }}
            </div>
            @endif

            <div class="flex justify-between items-center gap-3 mb-3 flex-wrap">
                <div class="relative w-full sm:w-72">
                    <input type="text" id="searchKaryawan" onkeyup="filterKaryawan()"
                           placeholder="&#128269; Cari nama karyawan..."
                           class="w-full px-3.5 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                </div>
                <span class="text-xs text-gray-400">
                    <strong class="text-gray-600">{{ count($payrolls) }}</strong> karyawan terdaftar
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left" id="tableKaryawan">
                        <thead class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 w-10 text-center">#</th>
                                <th class="px-4 py-3">Karyawan</th>
                                <th class="px-4 py-3 text-center">Hari</th>
                                <th class="px-4 py-3 text-right">Tarif/Hari</th>
                                <th class="px-4 py-3 text-right">Pendapatan</th>
                                <th class="px-4 py-3 text-right">Potongan</th>
                                <th class="px-4 py-3 text-right">Gaji Bersih</th>
                                <th class="px-4 py-3 text-center w-52">Periode &amp; Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 bg-white">
                            @forelse($payrolls as $index => $payroll)
                            @php
                                $tarifHarian = $payroll->tarif_harian_total > 0
                                    ? $payroll->tarif_harian_total
                                    : (($payroll->gaji_pokok ?? 0) + ($payroll->tunjangan_makan ?? 0) + ($payroll->tunjangan_transport ?? 0));
                                $earnings = $payroll->total_earnings > 0
                                    ? $payroll->total_earnings
                                    : (($payroll->gaji_utama ?? 0) + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) + ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->bonus_dll ?? 0));
                                $deductions = $payroll->total_deductions > 0
                                    ? $payroll->total_deductions
                                    : (($payroll->potongan_terlambat ?? 0) + ($payroll->potongan_inventaris ?? 0) + ($payroll->potongan_kasbon ?? 0) + ($payroll->potongan_dll ?? 0));
                                $isPaid = $payroll->status_jurnal || $payroll->status == 'approved';
                                $labelPeriode = ($payroll->tanggal_mulai && $payroll->tanggal_selesai)
                                    ? \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m') . ' - ' . \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m')
                                    : null;
                                $confirmMsg = 'Bayar gaji ' . $payroll->karyawan->nama_karyawan . ($labelPeriode ? ' periode ' . $labelPeriode : '') . ' dan buat Jurnal Umum?';
                            @endphp
                            <tr class="payroll-row hover:bg-gray-50/60 transition-colors">
                                <td class="px-4 py-4 text-center text-xs text-gray-400 font-medium">{{ $index + 1 }}</td>

                                <td class="px-4 py-4">
                                    <div class="font-semibold text-gray-800 text-sm nama-karyawan leading-tight">
                                        {{ $payroll->karyawan->nama_karyawan }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        {{ $payroll->karyawan->jabatan ?? '-' }}
                                        @if($payroll->karyawan->departemen)
                                            <span class="mx-1 text-gray-200">&middot;</span>{{ $payroll->karyawan->departemen }}
                                        @endif
                                    </div>
                                    <a href="{{ route('penggajian.create', ['target_periode' => $periode, 'outlet' => $selectedOutlet, 'karyawan_id' => $payroll->karyawan_id, 'lock_karyawan' => 1]) }}"
                                       class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-blue-500 hover:text-blue-700 hover:underline transition-colors"
                                       title="Tambah periode gaji baru — nama karyawan ter-lock otomatis">
                                        + Tambah Periode Gaji
                                    </a>
                                </td>

                                <td class="px-4 py-4 text-center">
                                    <span class="text-sm font-bold text-gray-700">{{ $payroll->hari_kerja }}</span>
                                    <span class="text-xs text-gray-400"> hr</span>
                                </td>

                                <td class="px-4 py-4 text-right">
                                    <span class="text-xs text-gray-500">Rp {{ number_format($tarifHarian, 0, ',', '.') }}</span>
                                </td>

                                <td class="px-4 py-4 text-right">
                                    <span class="text-sm font-semibold text-emerald-600">Rp {{ number_format($earnings, 0, ',', '.') }}</span>
                                </td>

                                <td class="px-4 py-4 text-right">
                                    @if($deductions > 0)
                                        <span class="text-sm font-semibold text-rose-500">- Rp {{ number_format($deductions, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-xs text-gray-300">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-4 text-right">
                                    <span class="text-sm font-bold" style="color: #7A4517;">
                                        Rp {{ number_format($payroll->total_gaji_bersih, 0, ',', '.') }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex flex-col items-center gap-2">

                                        @if($labelPeriode)
                                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-100 whitespace-nowrap">
                                            &#128197; {{ $labelPeriode }}
                                        </span>
                                        @endif

                                        @if($isPaid)
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                &#10003; Terbayar
                                            </span>
                                        @else
                                            <form action="{{ route('penggajian.bayar', $payroll->id) }}" method="POST"
                                                  onsubmit="return confirm('{{ addslashes($confirmMsg) }}')">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-sm border border-emerald-700 transition-all whitespace-nowrap">
                                                    &#128179; Bayar
                                                </button>
                                            </form>
                                        @endif

                                        {{-- KEBAB DROPDOWN --}}
                                        <div class="relative kebab-wrapper" style="position:relative;">
                                            <button onclick="toggleKebab(this)"
                                                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 px-2.5 py-1 rounded-lg text-xs font-bold transition-colors border border-transparent hover:border-gray-200"
                                                    title="Aksi lain (Slip, Edit, Hapus)">
                                                &bull;&bull;&bull;
                                            </button>
                                            <div class="kebab-menu hidden absolute right-0 mt-1 w-38 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 text-left" style="min-width:140px;">
                                                <a href="{{ route('penggajian.show', $payroll->id) }}"
                                                   class="flex items-center gap-2 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors">
                                                    &#129534; Cetak Slip
                                                </a>
                                                @if(!$isPaid)
                                                <a href="{{ route('penggajian.edit', $payroll->id) }}"
                                                   class="flex items-center gap-2 px-3 py-2 text-xs text-amber-600 hover:bg-amber-50 transition-colors">
                                                    &#9999; Edit Presensi
                                                </a>
                                                @if($currentStatus == 'draft')
                                                <form action="{{ route('penggajian.destroy', $payroll->id) }}" method="POST"
                                                      onsubmit="return confirm('Hapus data penggajian karyawan ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="w-full flex items-center gap-2 px-3 py-2 text-xs text-red-500 hover:bg-red-50 transition-colors">
                                                        &#128465; Hapus
                                                    </button>
                                                </form>
                                                @endif
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="text-3xl mb-2">&#128203;</div>
                                    <div class="font-medium text-gray-500 text-sm">Belum ada data karyawan</div>
                                    <div class="text-xs text-gray-400 mt-1">Klik "Auto-Fill Karyawan" atau "+ Input Gaji Manual" di atas.</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        function filterKaryawan() {
            const input = document.getElementById('searchKaryawan').value.toLowerCase();
            document.querySelectorAll('.payroll-row').forEach(row => {
                const name = row.querySelector('.nama-karyawan').textContent.toLowerCase();
                row.style.display = name.includes(input) ? '' : 'none';
            });
        }

        function toggleKebab(btn) {
            const menu = btn.nextElementSibling;
            const isHidden = menu.classList.contains('hidden');
            // Close all other open kebabs
            document.querySelectorAll('.kebab-menu').forEach(m => m.classList.add('hidden'));
            if (isHidden) {
                menu.classList.remove('hidden');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.kebab-wrapper')) {
                document.querySelectorAll('.kebab-menu').forEach(m => m.classList.add('hidden'));
            }
        });
    </script>
</x-app-layout>
