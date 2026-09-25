<x-app-layout>
    <div class="py-4" x-data="payrollManager()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            @php
                $totalGajiNettPeriode = $payrolls->sum('take_home_pay');
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 mb-3">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-3">
                    {{-- Left Title & Info --}}
                    <div class="flex items-center gap-3 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight m-0">
                                    Hitung Gaji Pokok
                                </h1>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200/70">
                                    Outlet {{ $selectedOutlet }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium m-0 mt-0.5">
                                Periode: <strong class="text-slate-800">{{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}</strong>
                            </p>
                        </div>

                        {{-- Total Gaji Bersih / Nett Badge --}}
                        <div class="flex items-center gap-2 ms-0 sm:ms-2">
                            <div id="headerTotalGajiNettBadge"
                                 style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac; color: #166534; padding: 6px 14px; border-radius: 10px; box-shadow: 0 1px 3px rgba(22, 101, 52, 0.08);"
                                 class="flex items-center gap-2">
                                <span style="font-size: 16px;">💰</span>
                                <div class="text-left">
                                    <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #15803d; line-height: 1;">Total Gaji Bersih (Nett)</div>
                                    <div style="font-size: 14px; font-weight: 900; color: #14532d; line-height: 1.2;" id="headerTotalGajiNettValue">
                                        Rp {{ number_format($totalGajiNettPeriode, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Action Buttons --}}
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        @if($currentStatus == 'draft' || $currentStatus == 'waiting approval')
                        <button type="button" onclick="submitBatchGajiPokok(this)" id="btnBatchSaveGajiPokok"
                                style="background-color: #7A4517; color: #ffffff; border: none; padding: 7px 14px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(122,69,23,0.25); transition: background .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'"
                                title="Simpan seluruh perubahan hari kerja / waktu kerja di halaman ini sekaligus">
                            <span>💾</span> Simpan Semua Gaji Pokok
                        </button>

                        <form action="{{ route('penggajian.auto-fill') }}" method="POST" class="inline m-0 p-0">
                            @csrf
                            <input type="hidden" name="periode" value="{{ $periode }}">
                            <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                            <button type="submit"
                                    onclick="return confirm('Tambahkan seluruh karyawan aktif Outlet {{ $selectedOutlet }} yang belum terdaftar ke periode {{ \App\Models\Penggajian::formatPeriode($periode) }} secara otomatis?')"
                                    style="background-color: #ffffff; color: #0f172a; border: 1.5px solid #cbd5e1; padding: 7px 13px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background .15s; white-space: nowrap;"
                                    onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'"
                                    title="Tambahkan otomatis semua karyawan aktif yang belum terdaftar di periode ini">
                                <span>⚡</span> Auto-Fill
                            </button>
                        </form>

                        <button type="button" @click="openCreateModal()"
                                style="background-color: #ffffff; color: #334155; border: 1.5px solid #cbd5e1; padding: 7px 14px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'"
                                title="Input slip gaji baru secara manual via pop-up">
                            <span style="font-size: 14px; line-height: 1;">+</span> Input Gaji
                        </button>
                        @endif

                        <form action="{{ route('penggajian.bayar-semua', $periode) }}" method="POST" class="inline m-0 p-0"
                              onsubmit="return confirm('Proses pembayaran dan jurnal untuk SELURUH karyawan di periode {{ \App\Models\Penggajian::formatPeriode($periode) }}?')">
                            @csrf
                            <button type="submit"
                                    style="background-color: #059669; color: #ffffff; border: none; padding: 7px 14px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(5,150,105,0.25); transition: background .15s; white-space: nowrap;"
                                    onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'">
                                <span>💳</span> Bayar Semua
                            </button>
                        </form>

                        {{-- EXPORT EXCEL BUTTON --}}
                        <a href="{{ route('penggajian.export-excel', ['periode' => $periode, 'outlet' => $selectedOutlet]) }}"
                           style="background-color: #166534; color: #ffffff; border: none; padding: 7px 14px; border-radius: 8px; font-weight: 800; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(22,101,52,0.25); transition: background .15s; white-space: nowrap; text-decoration: none;"
                           onmouseover="this.style.background='#14532d'" onmouseout="this.style.background='#166534'"
                           title="Unduh data transfer gaji ke rekening (format Excel payroll bank)">
                            <span>📄</span> Export Excel
                        </a>
                    </div>
                </div>
            </div>

            @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-2.5 rounded-xl mb-3 text-xs font-bold flex items-center gap-2 shadow-sm">
                <span class="text-emerald-600 text-sm">&#10003;</span> {{ session('success') }}
            </div>
            @endif
            @if(session('info'))
            <div class="bg-blue-50 border border-blue-300 text-blue-900 px-4 py-2.5 rounded-xl mb-3 text-xs font-bold flex items-center gap-2 shadow-sm">
                <span class="text-blue-600 text-sm">&#9432;</span> {{ session('info') }}
            </div>
            @endif
            @if($errors->any())
            <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-2.5 rounded-xl mb-3 text-xs font-bold shadow-sm">
                <ul class="list-disc pl-5 space-y-1">
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

            {{-- TOOLBAR FILTER & PENCARIAN --}}
            <div class="flex justify-between items-center gap-2.5 mb-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap flex-1">
                    {{-- Pilihan Bulan / Periode --}}
                    <div class="relative">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 14px; pointer-events: none; z-index: 1;">📅</span>
                        <select style="padding: 6px 36px 6px 32px; border: 1.5px solid #7A4517; border-radius: 8px; font-size: 12px; font-weight: 700; color: #0f172a; background: #fffaf7; outline: none; cursor: pointer; appearance: none; -webkit-appearance: none; min-width: 175px; box-shadow: 0 1px 3px rgba(122,69,23,0.1);"
                                onchange="window.location.href='{{ route('penggajian.show-periode') }}?periode=' + this.value + '&outlet={{ $selectedOutlet }}'">
                            @foreach($periodes as $p)
                                @php $carbonP = \Carbon\Carbon::parse($p . '-01'); @endphp
                                <option value="{{ $p }}" {{ $periode == $p ? 'selected' : '' }}>
                                    {{ $carbonP->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>
                        <svg style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #7A4517;" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </div>

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

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm" style="overflow: visible;">
                <div class="overflow-x-auto pb-8" style="overflow-y: visible; min-height: 220px;">
                    <table class="w-full min-w-[1060px] text-xs text-left divide-y divide-slate-200" id="tableKaryawan">
                        <thead class="text-[11px] font-bold text-slate-700 uppercase tracking-wider bg-slate-100/90 border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-3 w-10 text-center whitespace-nowrap">#</th>
                                <th class="px-4 py-3 min-w-[200px] whitespace-nowrap">Karyawan</th>
                                <th class="px-3 py-3 text-center whitespace-nowrap min-w-[115px]">Hari Kerja</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[130px]">Tarif Satuan</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[135px]">Gaji Pokok</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[125px]">Total Bonus</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[135px]">Total Pengurangan</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap min-w-[140px]">Gaji Bersih</th>
                                <th class="px-4 py-3 text-center min-w-[170px] whitespace-nowrap">Aksi</th>
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

                                $hasMultiplePeriods = ($payroll->items && $payroll->items->count() > 1);

                                // Rincian per periode (satuan, unit suffix, tarif, dsb)
                                $itemBreakdowns = ($hasMultiplePeriods ? $payroll->items->sortBy('pilihan_periode') : collect([$payroll]))->map(function($it) use ($payroll) {
                                    $pNum = $it->pilihan_periode ?? 1;
                                    $sat = ($pNum == 2 && ($it->satuan_gaji_2 || ($payroll->karyawan->satuan_gaji_2 ?? null)))
                                        ? ($it->satuan_gaji_2 ?? $it->satuan_gaji ?? $payroll->karyawan->satuan_gaji_2 ?? $payroll->karyawan->satuan_gaji ?? 'Harian')
                                        : ($it->satuan_gaji ?? $payroll->karyawan->satuan_gaji ?? 'Harian');

                                    $suffix = $sat === 'Per Jam' ? 'jam' : ($sat === 'Bulanan' ? 'bln' : 'hr');
                                    $fullUnit = $sat === 'Per Jam' ? 'Jam' : ($sat === 'Bulanan' ? 'Bulan' : 'Hari');
                                    $perLabel = $sat === 'Per Jam' ? '/jam' : ($sat === 'Bulanan' ? '/bln' : '/hari');

                                    $tar = $it->tarif_harian_total > 0
                                        ? $it->tarif_harian_total
                                        : (($it->gaji_pokok ?? 0) + ($it->tunjangan_makan ?? 0) + ($it->tunjangan_transport ?? 0));

                                    return [
                                        'id' => $it->id,
                                        'periode' => $pNum,
                                        'hari_kerja' => $it->hari_kerja,
                                        'satuan' => $sat,
                                        'suffix' => $suffix,
                                        'full_unit' => $fullUnit,
                                        'per_label' => $perLabel,
                                        'tarif' => $tar,
                                        'gaji_utama' => $it->gaji_utama,
                                        'tanggal_mulai' => $it->tanggal_mulai,
                                        'tanggal_selesai' => $it->tanggal_selesai,
                                    ];
                                })->values();

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
                                data-bonus-total="{{ (float)$totalBonus }}"
                                data-deductions-total="{{ (float)$totalPotongan }}"
                                data-take-home-pay="{{ (float)$payroll->take_home_pay }}"
                                data-has-multiple="{{ $hasMultiplePeriods ? '1' : '0' }}"
                                data-nama="{{ strtolower($payroll->karyawan->nama_karyawan ?? '') }}"
                                data-departemen="{{ strtolower($payroll->karyawan->departemen ?? '') }}"
                                data-jabatan="{{ strtolower($payroll->karyawan->jabatan ?? '') }}">
                                <td class="px-3 py-3 text-center text-xs text-slate-400 font-bold whitespace-nowrap">{{ $index + 1 }}</td>

                                {{-- KARYAWAN --}}
                                <td class="px-4 py-3 min-w-[200px]">
                                    <div class="font-extrabold text-slate-900 text-sm nama-karyawan leading-snug flex items-center gap-1.5 flex-wrap">
                                        <span>{{ $payroll->karyawan->nama_karyawan ?? '-' }}</span>
                                        @if($hasMultiplePeriods)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-200 whitespace-nowrap" title="Akumulasi seluruh periode dalam bulan ini">
                                                &#10003; {{ $payroll->items->count() }} Periode
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] font-medium mt-1 flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-700">{{ $payroll->karyawan->jabatan ?? '-' }}</span>
                                        @if($payroll->karyawan->departemen)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 leading-normal">{{ $payroll->karyawan->departemen }}</span>
                                        @endif
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 leading-normal">{{ $badgeSatuanText }}</span>
                                    </div>
                                </td>

                                {{-- 1. HARI KERJA (BISA DIISI LANGSUNG) --}}
                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col items-center justify-center gap-0.5 whitespace-nowrap">
                                            <span class="text-xs font-black text-slate-900">{{ $waktuDisplay }}</span>
                                            <div class="flex items-center justify-center gap-1 text-[10px] text-indigo-700 font-semibold flex-wrap">
                                                @foreach($itemBreakdowns as $ib)
                                                    <span class="bg-indigo-50 border border-indigo-200/80 px-1 py-0.2 rounded text-[9.5px]">P{{ $ib['periode'] }}: {{ $ib['hari_kerja'] }}{{ $ib['suffix'] }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        @if(!$isPaid && ($currentStatus == 'draft' || $currentStatus == 'waiting approval'))
                                            <div class="inline-flex items-center gap-1.5 justify-center whitespace-nowrap">
                                                <input type="number" step="0.5" min="0"
                                                       class="batch-hari-kerja w-16 text-center bg-slate-50/80 hover:bg-white border border-slate-200 hover:border-slate-300 focus:bg-white rounded-md px-2 py-1 text-xs font-black text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-300 placeholder:font-normal"
                                                       value="{{ ($payroll->hari_kerja && $payroll->hari_kerja > 0) ? (float)$payroll->hari_kerja : '' }}"
                                                       placeholder="0"
                                                       oninput="onGajiPokokRowInput(this)">
                                                <span class="text-[11px] text-slate-600 font-bold whitespace-nowrap">
                                                    {{ $satuanRow === 'Per Jam' ? 'jam' : ($satuanRow === 'Bulanan' ? 'bln' : 'hr') }}
                                                </span>
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

                                {{-- 2. REKAP TARIF SATUAN --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($hasMultiplePeriods && ($itemBreakdowns->pluck('tarif')->unique()->count() > 1 || !$isSameUnit))
                                        <div class="flex flex-col items-end gap-1">
                                            @foreach($itemBreakdowns as $ib)
                                                <span class="inline-flex items-center gap-1 text-[10.5px] whitespace-nowrap">
                                                    <span class="text-[9px] font-black text-indigo-700 bg-indigo-50 border border-indigo-200 px-1 py-0.2 rounded">P{{ $ib['periode'] }}</span>
                                                    <span class="font-bold text-slate-800 whitespace-nowrap">Rp&nbsp;{{ number_format($ib['tarif'], 0, ',', '.') }}</span><span class="text-[10px] text-slate-500 font-normal">{{ $ib['per_label'] }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="whitespace-nowrap">
                                            <span class="text-xs text-slate-800 font-bold whitespace-nowrap">Rp&nbsp;{{ number_format($tarifHarian, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-slate-500 font-medium block whitespace-nowrap">
                                                {{ $satuanRow === 'Per Jam' ? '/jam' : ($satuanRow === 'Bulanan' ? '/bln' : '/hari') }}
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                {{-- 3. REKAP GAJI POKOK --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="whitespace-nowrap">
                                        <span class="text-xs font-extrabold text-slate-900 row-gaji-pokok-cell whitespace-nowrap">Rp&nbsp;{{ number_format($gajiPokok, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col items-end gap-0.5 mt-0.5">
                                            @foreach($itemBreakdowns as $ib)
                                                <span class="text-[10px] text-indigo-700 font-semibold whitespace-nowrap">P{{ $ib['periode'] }}: Rp&nbsp;{{ number_format($ib['gaji_utama'], 0, ',', '.') }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- 4. REKAP TOTAL BONUS (DARI MENU BONUS & LEMBUR) --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($totalBonus > 0)
                                        <div class="whitespace-nowrap">
                                            <span class="text-xs font-bold text-amber-700 whitespace-nowrap">+&nbsp;Rp&nbsp;{{ number_format($totalBonus, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 font-semibold text-xs whitespace-nowrap">-</span>
                                    @endif
                                </td>

                                {{-- 5. REKAP TOTAL PENGURANGAN (DARI MENU POTONGAN) --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($totalPotongan > 0)
                                        <div class="whitespace-nowrap">
                                            <span class="text-xs font-bold text-rose-700 whitespace-nowrap">-&nbsp;Rp&nbsp;{{ number_format($totalPotongan, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 font-semibold text-xs whitespace-nowrap">-</span>
                                    @endif
                                </td>

                                {{-- 6. GAJI BERSIH (TAKE HOME PAY) --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="whitespace-nowrap">
                                        <span class="text-sm font-black text-slate-900 row-take-home-pay-cell whitespace-nowrap">Rp&nbsp;{{ number_format($payroll->take_home_pay, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <span class="block text-[9.5px] font-bold text-indigo-600 mt-0.5 whitespace-nowrap">Semua Periode</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">

                                        {{-- TOMBOL DETAIL POPUP --}}
                                        <button type="button"
                                                @click="activeDetail = {{ json_encode($detailPayload) }}; openDetailModal = true;"
                                                class="inline-flex items-center gap-1 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold px-2.5 py-1.5 rounded-lg transition-colors border border-slate-300 cursor-pointer whitespace-nowrap"
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
                                <td colspan="8" class="px-6 py-16 text-center">
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
        <div x-show="openGajiPokokModal" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
            <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
                 @click="openGajiPokokModal = false"></div>

            {{-- MODAL CONTAINER CENTERED --}}
            <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
                <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 620px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
                    
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
        </div>


        {{-- ========================================================================= --}}
        {{-- POPUP MODAL 2: DETAIL RINCIAN GAJI BERSIH KARYAWAN --}}
        {{-- ========================================================================= --}}
        <div x-show="openDetailModal" style="display: none;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
            <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
                 @click="openDetailModal = false"></div>

            {{-- MODAL CONTAINER CENTERED --}}
            <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
                <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden; transform: translateY(0);">
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
        </div>
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
        // BATCH EDIT GAJI POKOK (INLINE HARI KERJA)
        // =========================================================================
        function recalculateHeaderTotalNett() {
            let total = 0;
            document.querySelectorAll('.payroll-row').forEach(row => {
                total += parseFloat(row.getAttribute('data-take-home-pay')) || 0;
            });
            const badgeEl = document.getElementById('headerTotalGajiNettValue');
            if (badgeEl) {
                badgeEl.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
            }
        }

        function onGajiPokokRowInput(el) {
            const row = el.closest('.payroll-row');
            if (!row) return;

            const hk = parseFloat(el.value) || 0;
            const satuan = row.getAttribute('data-satuan') || 'Harian';
            const tarif = parseFloat(row.getAttribute('data-tarif')) || 0;
            const totalBonus = parseFloat(row.getAttribute('data-bonus-total')) || 0;
            const totalDeductions = parseFloat(row.getAttribute('data-deductions-total')) || 0;

            let gajiPokok = (satuan === 'Bulanan') ? tarif : (hk * tarif);
            let takeHomePay = gajiPokok + totalBonus - totalDeductions;

            row.setAttribute('data-take-home-pay', takeHomePay);

            const pokokEl = row.querySelector('.row-gaji-pokok-cell');
            const thpEl = row.querySelector('.row-take-home-pay-cell');

            if (pokokEl) pokokEl.textContent = 'Rp ' + Math.round(gajiPokok).toLocaleString('id-ID');
            if (thpEl) thpEl.textContent = 'Rp ' + Math.round(takeHomePay).toLocaleString('id-ID');

            recalculateHeaderTotalNett();
        }

        async function submitBatchGajiPokok(btn) {
            const rows = document.querySelectorAll('.payroll-row');
            if (!rows.length) return;

            const items = [];
            rows.forEach(row => {
                const id = row.getAttribute('data-id');
                const karyawanId = row.getAttribute('data-karyawan-id');
                const hkInput = row.querySelector('.batch-hari-kerja');

                if (hkInput) {
                    items.push({
                        id: id,
                        karyawan_id: karyawanId,
                        hari_kerja: parseFloat(hkInput.value) || 0,
                    });
                }
            });

            if (!items.length) {
                alert('Tidak ada input waktu kerja yang dapat diedit langsung.');
                return;
            }

            const origContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>&#8987;</span> Menyimpan...';

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
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan!',
                            text: res.message || 'Waktu kerja & gaji pokok berhasil diperbarui.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(res.message || 'Waktu kerja & gaji pokok berhasil disimpan!');
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
    </script>
</x-app-layout>
