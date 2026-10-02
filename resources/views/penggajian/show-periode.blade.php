<x-app-layout>
    <div class="py-4" x-data="payrollManager()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            {{-- PAGE HEADER --}}
            {{-- PAGE HEADER --}}
            @php
                $totalGajiPokokPeriode = 0;
                $totalBonusPeriode = 0;
                $totalPotonganNonKasbon = 0;
                $totalKasbonPeriode = 0;
                $totalThpPeriodeKeseluruhan = 0;
                $totalThpAkhirBulan = 0; // Total nominal THP yang SUDAH DIBAYAR di akhir bulan
                $totalThpPertengahanBulan = 0; // Total nominal THP yang SUDAH DIBAYAR di tengah bulan

                foreach ($payrolls as $p) {
                    $kw = $p->karyawan;
                    $p2TotalMaster = (float)($kw->gaji_pokok_2 ?? 0) + (float)($kw->uang_makan_2 ?? 0) + (float)($kw->uang_transport_2 ?? 0);
                    $hasMasterMultiple = ($kw && $kw->gaji_pokok_2 !== null && $p2TotalMaster > 0);
                    $hasMulti = ($p->items && $p->items->where('pilihan_periode', 2)->count() > 0 && $p2TotalMaster > 0) || $hasMasterMultiple;

                    if ($hasMulti && $p->items && $p->items->count() > 0) {
                        foreach ($p->items as $it) {
                            $gu = (float)($it->gaji_utama ?? 0);
                            $bn = (float)($it->lembur ?? (($it->jam_lembur ?? 0) * 10000)) +
                                  (float)($it->bonus_target ?? 0) +
                                  (float)($it->bonus_tanggal_merah ?? 0) +
                                  (float)($it->bonus_birthday ?? (($it->banyak_birthday_service ?? 0) * 5000)) +
                                  (float)($it->pengembalian_deposit ?? 0) +
                                  (float)($it->bonus_dll ?? 0);
                            $kasbon = (float)($it->potongan_kasbon ?? 0);
                            $potNonKasbon = (float)($it->potongan_terlambat ?? 0) +
                                            (float)($it->potongan_inventaris ?? 0) +
                                            (float)($it->potongan_deposit ?? 0) +
                                            (float)($it->potongan_dll ?? 0);
                            $itEarnings = $it->total_earnings > 0 ? (float)$it->total_earnings : ($gu + $bn);
                            $itDeductions = $it->total_deductions > 0 ? (float)$it->total_deductions : ($potNonKasbon + $kasbon);
                            $itNett = $itEarnings - $itDeductions;

                            $totalGajiPokokPeriode += $gu;
                            $totalBonusPeriode += $bn;
                            $totalPotonganNonKasbon += $potNonKasbon;
                            $totalKasbonPeriode += $kasbon;
                            $totalThpPeriodeKeseluruhan += $itNett;

                            $isItemPaid = (bool)($it->status_jurnal);
                            $pNum = (int)($it->pilihan_periode ?? 1);
                            $itTipe = $it->tipe_pembayaran ?? '';

                            if ($isItemPaid) {
                                if ($itTipe === 'tengah_bulan' || ($pNum === 1 && empty($itTipe))) {
                                    $totalThpPertengahanBulan += $itNett;
                                }
                            }
                        }
                    } else {
                        // Karyawan single period / bulanan
                        $gu = (float)($p->gaji_utama ?? 0);
                        $bn = (float)(($p->lembur ?? 0) + ($p->bonus_target ?? 0) + ($p->bonus_tanggal_merah ?? 0) + ($p->bonus_birthday ?? 0) + ($p->pengembalian_deposit ?? 0) + ($p->bonus_dll ?? 0));
                        $kasbon = (float)($p->potongan_kasbon ?? 0);
                        $potNonKasbon = (float)($p->potongan_terlambat ?? 0) +
                                        (float)($p->potongan_inventaris ?? 0) +
                                        (float)($p->potongan_deposit ?? 0) +
                                        (float)($p->potongan_dll ?? 0);
                        $earnings = (float)($p->total_earnings > 0 ? $p->total_earnings : ($gu + $bn));
                        $deductions = (float)($p->total_deductions > 0 ? $p->total_deductions : ($potNonKasbon + $kasbon));
                        $nett = (float)($p->take_home_pay > 0 ? $p->take_home_pay : ($earnings - $deductions));

                        $totalGajiPokokPeriode += $gu;
                        $totalBonusPeriode += $bn;
                        $totalPotonganNonKasbon += $potNonKasbon;
                        $totalKasbonPeriode += $kasbon;
                        $totalThpPeriodeKeseluruhan += $nett;

                        $isSinglePaid = (bool)($p->status_jurnal || $p->is_paid);
                        $singleTipe = $p->tipe_pembayaran ?? '';

                        if ($isSinglePaid) {
                            if ($singleTipe === 'tengah_bulan') {
                                $totalThpPertengahanBulan += $nett;
                            }
                        }
                    }
                }

                $totalBebanGajiKeseluruhan = $totalThpPeriodeKeseluruhan + $totalKasbonPeriode;
                // Total THP Akhir Bulan otomatis terisi dari: total beban gaji - total THP tengah - kasbon
                $totalThpAkhirBulan = max(0, $totalBebanGajiKeseluruhan - $totalThpPertengahanBulan - $totalKasbonPeriode);
            @endphp
            {{-- PAGE HEADER & TOOLBAR (SESUAI DESAIN ASLI DI SCREENSHOT) --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-2.5 sm:py-3 mb-2.5">
                <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-3">
                    {{-- Left Title & Outlet --}}
                    <div class="shrink-0">
                        <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-none whitespace-nowrap m-0">
                            Formulir Hitung Gaji Karyawan
                        </h1>
                        <div class="flex items-center gap-2 flex-wrap mt-1.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-bold bg-amber-50 text-amber-900 border border-amber-300 whitespace-nowrap">
                                🏢 Outlet {{ $selectedOutlet }}
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300 whitespace-nowrap">
                                📅 Periode: <strong class="text-slate-800 font-bold">{{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}</strong>
                            </span>
                            @if(!empty($currentStatus))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider {{ $currentStatus == 'paid' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : ($currentStatus == 'approved' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-slate-100 text-slate-700 border border-slate-300') }} whitespace-nowrap">
                                    ● {{ $currentStatus }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Right Action Buttons --}}
                    <div class="flex items-center gap-1.5 flex-wrap">
                        {{-- TOMBOL SINKRONISASI / REFRESH DATA DARI SERVER --}}
                        <button type="button" onclick="refreshPayrollData(this)" id="btnRefreshPayrollData"
                                style="background-color: #f1f5f9; color: #334155; border: 1.5px solid #cbd5e1; padding: 5px 10px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'"
                                title="Muat ulang data terbaru dari database">
                            <span id="refreshIcon">🔄</span> Sinkronkan Data
                        </button>

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

                        {{-- TOMBOL BAYAR TENGAH BULAN (P1) --}}
                        <form action="{{ route('penggajian.bayar-semua', ['periode' => $periode, 'tipe' => 'tengah_bulan', 'outlet' => $selectedOutlet]) }}" method="POST" class="inline m-0 p-0"
                              onsubmit="return confirm('Proses pembayaran dan jurnal untuk SELURUH gaji TENGAH BULAN (P1 Karyawan 2 Periode) di periode {{ \App\Models\Penggajian::formatPeriode($periode) }}?')">
                            @csrf
                            <button type="submit"
                                    style="background-color: #0284c7; color: #ffffff; border: none; padding: 5px 11px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(2,132,199,0.25); transition: background .15s; white-space: nowrap;"
                                    onmouseover="this.style.background='#0369a1'" onmouseout="this.style.background='#0284c7'"
                                    title="Bayar & buat jurnal massal untuk seluruh slip Periode 1 (Tengah Bulan) milik karyawan 2 periode">
                                <span>🗓️</span> Bayar Tengah
                            </button>
                        </form>

                        {{-- TOMBOL BAYAR SEMUA / MASAL AKHIR BULAN --}}
                        <form action="{{ route('penggajian.bayar-semua', ['periode' => $periode, 'tipe' => 'akhir_bulan', 'outlet' => $selectedOutlet]) }}" method="POST" class="inline m-0 p-0"
                              onsubmit="return confirm('Proses pembayaran dan jurnal untuk SELURUH gaji MASAL AKHIR BULAN di periode {{ \App\Models\Penggajian::formatPeriode($periode) }}?')">
                            @csrf
                            <button type="submit"
                                    style="background-color: #059669; color: #ffffff; border: none; padding: 5px 11px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(5,150,105,0.25); transition: background .15s; white-space: nowrap;"
                                    onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'"
                                    title="Bayar & buat jurnal massal untuk seluruh gaji akhir bulan">
                                <span>💳</span> Bayar Semua
                            </button>
                        </form>

                        {{-- EXPORT EXCEL BUTTON --}}
                        <button type="button" onclick="openExportPayrollModal()"
                                style="background-color: #166534; color: #ffffff; border: none; padding: 5px 12px; border-radius: 7px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(22,101,52,0.2); transition: background .15s; white-space: nowrap;"
                                onmouseover="this.style.background='#14532d'" onmouseout="this.style.background='#166534'"
                                title="Buka pilihan seleksi data transfer gaji ke rekening (format Excel payroll bank)">
                            <span>📄</span> Export Excel
                        </button>

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

                {{-- 7 KARTU RINGKASAN REVISI (2 BARIS RAPI & INFORMATIF SEPERTI SCREENSHOT) --}}
                <div style="display: flex; flex-direction: column; gap: 7px; width: 100%; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; box-sizing: border-box;">
                    
                    {{-- BARIS 1: 4 KOMPONEN GAJI & POTONGAN --}}
                    <div class="summary-cards-row" style="display: flex !important; flex-direction: row !important; align-items: stretch !important; gap: 8px !important; width: 100% !important; box-sizing: border-box !important;">
                        {{-- 1. Gaji Pokok Badge --}}
                        <div style="flex: 1 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Gaji Pokok Seluruh Karyawan">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #475569; flex-shrink: 0;">
                                💼
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">TOTAL POKOK</div>
                                <div style="font-size: 12px; font-weight: 900; color: #0f172a; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalGajiPokokValue">
                                    Rp {{ number_format($totalGajiPokokPeriode, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- 2. Bonus & Lembur Badge --}}
                        <div style="flex: 1 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Bonus, Lembur, Target, Tanggal Merah, Birthday, Deposit Balik, dan Bonus Lainnya">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #fefce8; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #ca8a04; flex-shrink: 0;">
                                ⭐
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">TOTAL BONUS</div>
                                <div style="font-size: 12px; font-weight: 900; color: #d97706; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalBonusValue">
                                    Rp {{ number_format($totalBonusPeriode, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- 3. Potongan (Kecuali Kasbon) Badge --}}
                        <div style="flex: 1 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Potongan Selain Kasbon (Keterlambatan, Kerusakan Inventaris, Potongan Deposit, Potongan Lain)">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #fff1f2; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #e11d48; flex-shrink: 0;">
                                ✂️
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">POTONGAN (NON-KASBON)</div>
                                <div style="font-size: 12px; font-weight: 900; color: #e11d48; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalPotonganNonKasbonValue">
                                    Rp {{ number_format($totalPotonganNonKasbon, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- 4. Total Kasbon Badge --}}
                        <div style="flex: 1 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #e2e8f0 !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Pemotongan Kasbon Pinjaman Karyawan">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #f5f3ff; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #7c3aed; flex-shrink: 0;">
                                🏷️
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #64748b; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">TOTAL KASBON</div>
                                <div style="font-size: 12px; font-weight: 900; color: #7c3aed; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalKasbonValue">
                                    Rp {{ number_format($totalKasbonPeriode, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- BARIS 2: 3 KARTU ARUS TRANSFER GAJI & BEBAN --}}
                    <div style="display: flex !important; flex-direction: row !important; align-items: stretch !important; gap: 8px !important; width: 100% !important; box-sizing: border-box !important;">
                        {{-- 5. Total THP Akhir Bulan --}}
                        <div style="flex: 1.15 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #a7f3d0 !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(16, 185, 129, 0.08) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Gaji Bersih Transfer Akhir Bulan (P1 Karyawan 1 Periode/Bulanan + P2 Karyawan 2 Periode)">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #ecfdf5; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #059669; flex-shrink: 0; border: 1px solid #d1fae5;">
                                💳
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                                    <span style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #065f46; line-height: 1; white-space: nowrap;">THP AKHIR BULAN</span>
                                    <span style="font-size: 7.5px; font-weight: 800; background: #d1fae5; color: #065f46; border-radius: 3px; padding: 1px 4px; line-height: 1;">Transfer Now</span>
                                </div>
                                <div style="font-size: 12.5px; font-weight: 900; color: #059669; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalThpAkhirBulanValue">
                                    Rp {{ number_format($totalThpAkhirBulan, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- 6. Total Beban Gaji (THP Akhir + THP Pertengahan + Kasbon) --}}
                        <div style="flex: 1.15 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #bae6fd !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(14, 165, 233, 0.08) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Beban Gaji Keseluruhan (THP Akhir + THP Pertengahan + Total Kasbon)">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #f0f9ff; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0284c7; flex-shrink: 0; border: 1px solid #e0f2fe;">
                                📈
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                                    <span style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #0369a1; line-height: 1; white-space: nowrap;">TOTAL BEBAN GAJI</span>
                                    <span style="font-size: 7.5px; font-weight: 800; background: #e0f2fe; color: #0369a1; border-radius: 3px; padding: 1px 4px; line-height: 1;">THP + Kasbon</span>
                                </div>
                                <div style="font-size: 12.5px; font-weight: 900; color: #0284c7; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalBebanGajiValue">
                                    Rp {{ number_format($totalBebanGajiKeseluruhan, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- 7. Total THP Pertengahan Bulan (P1 Karyawan 2 Periode) --}}
                        <div style="flex: 1.15 1 0% !important; min-width: 0 !important; background: #ffffff !important; border: 1.5px solid #fed7aa !important; padding: 5px 9px !important; border-radius: 8px !important; box-shadow: 0 1px 2px rgba(249, 115, 22, 0.08) !important; display: flex !important; align-items: center !important; gap: 7px !important; box-sizing: border-box !important;"
                             title="Total Gaji Bersih P1 yang Sudah Ditransfer di Pertengahan Bulan (Khusus Karyawan 2 Periode)">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background-color: #fff7ed; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #ea580c; flex-shrink: 0; border: 1px solid #ffedd5;">
                                🗓️
                            </div>
                            <div style="flex: 1 1 0%; min-width: 0; text-align: left;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                                    <span style="font-size: 8.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; color: #9a3412; line-height: 1; white-space: nowrap;">THP PERTENGAHAN (P1)</span>
                                    <span style="font-size: 7.5px; font-weight: 800; background: #ffedd5; color: #9a3412; border-radius: 3px; padding: 1px 4px; line-height: 1;">Transfer Lalu</span>
                                </div>
                                <div style="font-size: 12.5px; font-weight: 900; color: #ea580c; line-height: 1.2; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="headerTotalThpPertengahanValue">
                                    Rp {{ number_format($totalThpPertengahanBulan, 0, ',', '.') }}
                                </div>
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
                                <th class="px-3 py-2 text-center whitespace-nowrap min-w-[125px] border-b border-slate-200" title="Otomatis dari data absensi keterlambatan">
                                    <div>Terlambat</div>
                                    <div class="text-[9px] font-normal text-slate-400 mt-0.5 lowercase">(otomatis absensi)</div>
                                </th>
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
                        @php $exportModalList = []; @endphp
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

                                $kw = $payroll->karyawan;
                                $p1TotalMaster = (float)($kw->gaji_pokok ?? 0) + (float)($kw->uang_makan ?? 0) + (float)($kw->uang_transport ?? 0);
                                $p2TotalMaster = (float)($kw->gaji_pokok_2 ?? 0) + (float)($kw->uang_makan_2 ?? 0) + (float)($kw->uang_transport_2 ?? 0);
                                $hasMasterMultiplePeriods = ($kw->gaji_pokok_2 !== null && $p2TotalMaster > 0);
                                $hasMultiplePeriods = ($payroll->items && $payroll->items->where('pilihan_periode', 2)->count() > 0 && $p2TotalMaster > 0) || $hasMasterMultiplePeriods;

                                // Rincian per periode (satuan, unit suffix, tarif, dsb)
                                if ($hasMultiplePeriods) {
                                    $existingItemsByPeriode = $payroll->items ? $payroll->items->keyBy(function($item) {
                                        return (int)($item->pilihan_periode ?? 1);
                                    }) : collect();

                                    // Filter periode berdasarkan tanggal berlaku vs bulan penggajian
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

                                    // Cek P2: overlap jika tanggal_mulai_2..tanggal_selesai_2 beririsan dengan bulan ini DAN gaji P2 > 0
                                    $p2Mulai   = $kw->tanggal_mulai_2 ? \Carbon\Carbon::parse($kw->tanggal_mulai_2) : null;
                                    $p2Selesai = $kw->tanggal_selesai_2 ? \Carbon\Carbon::parse($kw->tanggal_selesai_2) : null;
                                    $p2Active  = false;
                                    if ($p2TotalMaster > 0 && $kw->gaji_pokok_2 !== null) {
                                        if ($p2Mulai && $p2Selesai) {
                                            $p2Active = $p2Mulai->lte($periodeMonthEnd) && $p2Selesai->gte($periodeMonthStart);
                                        } elseif ($p2Mulai) {
                                            $p2Active = $p2Mulai->lte($periodeMonthEnd);
                                        } elseif ($p2Selesai) {
                                            $p2Active = $p2Selesai->gte($periodeMonthStart);
                                        } else {
                                            $p2Active = true;
                                        }
                                    }
                                    if ($p2Active && $p2TotalMaster > 0) $candidatePeriodes[] = 2;

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
                                            $gpActive = (float)($kw->gaji_pokok_2 ?? 0);
                                            $umActive = (float)($kw->uang_makan_2 ?? 0);
                                            $utActive = (float)($kw->uang_transport_2 ?? 0);
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
                                            'jam_lembur' => $activeItem ? (float)($activeItem->jam_lembur ?? 0) : (float)($payroll->jam_lembur ?? 0),
                                            'lembur' => $activeItem ? (float)($activeItem->lembur ?? 0) : (float)($payroll->lembur ?? 0),
                                            'banyak_target' => $activeItem ? (int)($activeItem->banyak_target ?? 0) : (int)($payroll->banyak_target ?? 0),
                                            'bonus_target' => $activeItem ? (float)($activeItem->bonus_target ?? 0) : (float)($payroll->bonus_target ?? 0),
                                            'catatan_bonus_target' => $activeItem ? ($activeItem->catatan_bonus_target ?? '') : ($payroll->catatan_bonus_target ?? ''),
                                            'banyak_tanggal_merah' => $activeItem ? (int)($activeItem->banyak_tanggal_merah ?? 0) : (int)($payroll->banyak_tanggal_merah ?? 0),
                                            'bonus_tanggal_merah' => $activeItem ? (float)($activeItem->bonus_tanggal_merah ?? 0) : (float)($payroll->bonus_tanggal_merah ?? 0),
                                            'catatan_bonus_tanggal_merah' => $activeItem ? ($activeItem->catatan_bonus_tanggal_merah ?? '') : ($payroll->catatan_bonus_tanggal_merah ?? ''),
                                            'banyak_birthday_service' => $activeItem ? (int)($activeItem->banyak_birthday_service ?? 0) : (int)($payroll->banyak_birthday_service ?? 0),
                                            'bonus_birthday' => $activeItem ? (float)($activeItem->bonus_birthday ?? 0) : (float)($payroll->bonus_birthday ?? 0),
                                            'pengembalian_deposit' => $activeItem ? (float)($activeItem->pengembalian_deposit ?? 0) : (float)($payroll->pengembalian_deposit ?? 0),
                                            'bonus_dll' => $activeItem ? (float)($activeItem->bonus_dll ?? 0) : (float)($payroll->bonus_dll ?? 0),
                                            'catatan_bonus_dll' => $activeItem ? ($activeItem->catatan_bonus_dll ?? '') : ($payroll->catatan_bonus_dll ?? ''),
                                            'total_bonus' => (float)$totalBonus,
                                            'potongan_terlambat' => $activeItem ? (float)($activeItem->potongan_terlambat ?? 0) : (float)($payroll->potongan_terlambat ?? 0),
                                            'potongan_inventaris' => $activeItem ? (float)($activeItem->potongan_inventaris ?? 0) : (float)($payroll->potongan_inventaris ?? 0),
                                            'potongan_kasbon' => $activeItem ? (float)($activeItem->potongan_kasbon ?? 0) : (float)($payroll->potongan_kasbon ?? 0),
                                            'potongan_deposit' => $activeItem ? (float)($activeItem->potongan_deposit ?? 0) : (float)($payroll->potongan_deposit ?? 0),
                                            'potongan_dll' => $activeItem ? (float)($activeItem->potongan_dll ?? 0) : (float)($payroll->potongan_dll ?? 0),
                                            'catatan_potongan_dll' => $activeItem ? ($activeItem->catatan_potongan_dll ?? '') : ($payroll->catatan_potongan_dll ?? ''),
                                            'total_deductions' => (float)$totalPotongan,
                                            'total_earnings' => (float)$earnings,
                                            'total_gaji_bersih' => (float)$payroll->take_home_pay,
                                            'tanggal_mulai' => ($activeItem && $activeItem->tanggal_mulai) ? $activeItem->tanggal_mulai : (($activePNum === 2) ? ($kw->tanggal_mulai_2 ?? null) : ($kw->tanggal_mulai ?? $payroll->tanggal_mulai ?? null)),
                                            'tanggal_selesai' => ($activeItem && $activeItem->tanggal_selesai) ? $activeItem->tanggal_selesai : (($activePNum === 2) ? ($kw->tanggal_selesai_2 ?? null) : ($kw->tanggal_selesai ?? $payroll->tanggal_selesai ?? null)),
                                        ]]);
                                    } else {

                                    $itemBreakdowns = collect($targetPeriodes)->map(function($pNum) use ($existingItemsByPeriode, $payroll) {
                                        $kw = $payroll->karyawan;
                                        $it = $existingItemsByPeriode->get($pNum);

                                        if ($pNum === 2) {
                                            $sat = $it->satuan_gaji_2 ?? $it->satuan_gaji ?? $kw->satuan_gaji_2 ?? $kw->satuan_gaji ?? 'Harian';
                                            $gp = (float)($kw->gaji_pokok_2 ?? 0);
                                            $um = (float)($kw->uang_makan_2 ?? 0);
                                            $ut = (float)($kw->uang_transport_2 ?? 0);
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

                                        $itLembur = $it ? (float)($it->lembur ?? (($it->jam_lembur ?? 0) * 10000)) : 0;
                                        $itTarget = $it ? (float)($it->bonus_target ?? 0) : 0;
                                        $itMerah = $it ? (float)($it->bonus_tanggal_merah ?? 0) : 0;
                                        $itBday = $it ? (float)($it->bonus_birthday ?? (($it->banyak_birthday_service ?? 0) * 5000)) : 0;
                                        $itDepKembali = $it ? (float)($it->pengembalian_deposit ?? 0) : 0;
                                        $itBonusDll = $it ? (float)($it->bonus_dll ?? 0) : 0;
                                        $itCatatanBonusDll = $it ? ($it->catatan_bonus_dll ?? '') : '';
                                        $itTotalBonus = $itLembur + $itTarget + $itMerah + $itBday + $itDepKembali + $itBonusDll;

                                        $itLate = $it ? (float)($it->potongan_terlambat ?? 0) : 0;
                                        $itInv = $it ? (float)($it->potongan_inventaris ?? 0) : 0;
                                        $itKasbon = $it ? (float)($it->potongan_kasbon ?? 0) : 0;
                                        $itDepPot = $it ? (float)($it->potongan_deposit ?? 0) : 0;
                                        $itPotDll = $it ? (float)($it->potongan_dll ?? 0) : 0;
                                        $itTotalPotongan = $itLate + $itInv + $itKasbon + $itDepPot + $itPotDll;

                                        $itEarnings = $gu + $itTotalBonus;
                                        $itNett = $itEarnings - $itTotalPotongan;

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
                                            'jam_lembur' => $it ? (float)($it->jam_lembur ?? 0) : 0,
                                            'lembur' => $itLembur,
                                            'banyak_target' => $it ? (int)($it->banyak_target ?? 0) : 0,
                                            'bonus_target' => $itTarget,
                                            'catatan_bonus_target' => $it ? ($it->catatan_bonus_target ?? '') : '',
                                            'banyak_tanggal_merah' => $it ? (int)($it->banyak_tanggal_merah ?? 0) : 0,
                                            'bonus_tanggal_merah' => $itMerah,
                                            'catatan_bonus_tanggal_merah' => $it ? ($it->catatan_bonus_tanggal_merah ?? '') : '',
                                            'banyak_birthday_service' => $it ? (int)($it->banyak_birthday_service ?? 0) : 0,
                                            'bonus_birthday' => $itBday,
                                            'pengembalian_deposit' => $itDepKembali,
                                            'bonus_dll' => $itBonusDll,
                                            'catatan_bonus_dll' => $itCatatanBonusDll,
                                            'total_bonus' => $itTotalBonus,
                                            'potongan_terlambat' => $itLate,
                                            'potongan_inventaris' => $itInv,
                                            'potongan_kasbon' => $itKasbon,
                                            'potongan_deposit' => $itDepPot,
                                            'potongan_dll' => $itPotDll,
                                            'catatan_potongan_dll' => $it ? ($it->catatan_potongan_dll ?? '') : '',
                                            'total_deductions' => $itTotalPotongan,
                                            'total_earnings' => $itEarnings,
                                            'total_gaji_bersih' => $itNett,
                                            'is_paid' => $it ? (bool)$it->status_jurnal : false,
                                            'tipe_pembayaran' => $it ? ($it->tipe_pembayaran ?? '') : '',
                                            'status_jurnal' => $it ? (bool)$it->status_jurnal : false,
                                            'status' => $it ? $it->status : 'draft',
                                            'tanggal_mulai' => ($it && $it->tanggal_mulai) ? $it->tanggal_mulai : (($pNum === 2) ? ($kw->tanggal_mulai_2 ?? null) : ($kw->tanggal_mulai ?? $payroll->tanggal_mulai ?? null)),
                                            'tanggal_selesai' => ($it && $it->tanggal_selesai) ? $it->tanggal_selesai : (($pNum === 2) ? ($kw->tanggal_selesai_2 ?? null) : ($kw->tanggal_selesai ?? $payroll->tanggal_selesai ?? null)),
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
                                        'jam_lembur' => (float)($payroll->jam_lembur ?? 0),
                                        'lembur' => (float)($payroll->lembur ?? 0),
                                        'banyak_target' => (int)($payroll->banyak_target ?? 0),
                                        'bonus_target' => (float)($payroll->bonus_target ?? 0),
                                        'catatan_bonus_target' => $payroll->catatan_bonus_target ?? '',
                                        'banyak_tanggal_merah' => (int)($payroll->banyak_tanggal_merah ?? 0),
                                        'bonus_tanggal_merah' => (float)($payroll->bonus_tanggal_merah ?? 0),
                                        'catatan_bonus_tanggal_merah' => $payroll->catatan_bonus_tanggal_merah ?? '',
                                        'banyak_birthday_service' => (int)($payroll->banyak_birthday_service ?? 0),
                                        'bonus_birthday' => (float)($payroll->bonus_birthday ?? 0),
                                        'pengembalian_deposit' => (float)($payroll->pengembalian_deposit ?? 0),
                                        'bonus_dll' => (float)($payroll->bonus_dll ?? 0),
                                        'catatan_bonus_dll' => $payroll->catatan_bonus_dll ?? '',
                                        'total_bonus' => (float)$totalBonus,
                                        'potongan_terlambat' => (float)($payroll->potongan_terlambat ?? 0),
                                        'potongan_inventaris' => (float)($payroll->potongan_inventaris ?? 0),
                                        'potongan_kasbon' => (float)($payroll->potongan_kasbon ?? 0),
                                        'potongan_deposit' => (float)($payroll->potongan_deposit ?? 0),
                                        'potongan_dll' => (float)($payroll->potongan_dll ?? 0),
                                        'catatan_potongan_dll' => $payroll->catatan_potongan_dll ?? '',
                                        'total_deductions' => (float)$totalPotongan,
                                        'total_earnings' => (float)$earnings,
                                        'total_gaji_bersih' => (float)$payroll->take_home_pay,
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

                                // Koleksi item untuk modal seleksi export excel payroll
                                if (isset($itemBreakdowns)) {
                                    foreach ($itemBreakdowns as $ibItem) {
                                        if (!empty($ibItem['id'])) {
                                            $exportModalList[] = [
                                                'id' => $ibItem['id'],
                                                'karyawan_id' => $payroll->karyawan_id,
                                                'nama' => $payroll->karyawan->nama_karyawan ?? '-',
                                                'jabatan' => $payroll->karyawan->jabatan ?? '-',
                                                'departemen' => $payroll->karyawan->departemen ?? '-',
                                                'no_rekening' => preg_replace('/\D/', '', $payroll->karyawan->no_rekening ?? ''),
                                                'no_rekening_display' => $payroll->karyawan->no_rekening ?? '',
                                                'bank' => $payroll->karyawan->bank ?? '',
                                                'email' => $payroll->karyawan->email ?? '',
                                                'periode_num' => (int)($ibItem['periode'] ?? 1),
                                                'periode_label' => $hasMultiplePeriods ? ('Periode ' . ($ibItem['periode'] ?? 1)) : ($satuanRow === 'Bulanan' ? 'Bulanan' : '1 Periode'),
                                                'is_multi' => $hasMultiplePeriods,
                                                'thp' => max(0, (float)($ibItem['total_gaji_bersih'] ?? 0)),
                                                'tipe_pembayaran' => $payroll->tipe_pembayaran ?? '',
                                            ];
                                        }
                                    }
                                }
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
                                    'catatan_bonus_dll' => $hasMultiplePeriods ? $itemBreakdowns->pluck('catatan_bonus_dll')->filter()->unique()->implode(', ') : ($payroll->catatan_bonus_dll ?? ''),
                                    'total_bonus' => number_format($totalBonus, 0, ',', '.'),
                                    'total_earnings' => number_format($earnings, 0, ',', '.'),
                                    'potongan_terlambat' => number_format($payroll->potongan_terlambat, 0, ',', '.'),
                                    'potongan_inventaris' => number_format($payroll->potongan_inventaris, 0, ',', '.'),
                                    'potongan_kasbon' => number_format($payroll->potongan_kasbon, 0, ',', '.'),
                                    'potongan_deposit' => number_format($payroll->potongan_deposit ?? 0, 0, ',', '.'),
                                    'potongan_dll' => number_format($payroll->potongan_dll, 0, ',', '.'),
                                    'catatan_potongan_dll' => $hasMultiplePeriods ? $itemBreakdowns->pluck('catatan_potongan_dll')->filter()->unique()->implode(', ') : ($payroll->catatan_potongan_dll ?? ''),
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
                                data-pot-non-kasbon="{{ (float)($totalPotongan - ($payroll->potongan_kasbon ?? 0)) }}"
                                data-kasbon-total="{{ (float)($payroll->potongan_kasbon ?? 0) }}"
                                data-take-home-pay="{{ (float)$payroll->take_home_pay }}"
                                data-has-multiple="{{ $hasMultiplePeriods ? '1' : '0' }}"
                                data-is-paid="{{ $isPaid ? '1' : '0' }}"
                                data-tipe-pembayaran="{{ $payroll->tipe_pembayaran ?? '' }}"
                                data-active-periode="{{ $itemBreakdowns->first()['periode'] ?? ($payroll->pilihan_periode ?? 1) }}"
                                data-active-item-id="{{ $itemBreakdowns->first()['id'] ?? $payroll->id }}"
                                data-nama="{{ strtolower($payroll->karyawan->nama_karyawan ?? '') }}"
                                data-departemen="{{ strtolower($payroll->karyawan->departemen ?? '') }}"
                                data-jabatan="{{ strtolower($payroll->karyawan->jabatan ?? '') }}">
                                
                                {{-- 1. NO (FREEZE - ABU MUDA) --}}
                                <td class="sticky-col-num px-3 py-3 text-center text-xs font-bold whitespace-nowrap border-b border-slate-200">
                                    <span class="row-index text-slate-700 font-extrabold">{{ $index + 1 }}</span>
                                </td>

                                {{-- 2. KARYAWAN (FREEZE - NAMA, JABATAN & PERIODE P1/P2) --}}
                                <td class="sticky-col-karyawan px-3.5 py-2.5 border-b border-slate-200">
                                    <div class="flex items-start gap-2">
                                        <i class="bi bi-grip-vertical grip-handle select-none text-slate-400 hover:text-slate-800 cursor-grab shrink-0 mt-0.5" title="Tahan dan geser (drag & drop) untuk mengatur urutan"></i>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-extrabold text-slate-900 text-sm nama-karyawan leading-tight truncate" title="{{ $payroll->karyawan->nama_karyawan ?? '-' }}">
                                                {{ $payroll->karyawan->nama_karyawan ?? '-' }}
                                            </div>
                                            <div class="text-[11px] text-slate-500 font-semibold mt-0.5 leading-tight truncate" title="{{ $payroll->karyawan->jabatan ?? '-' }}">
                                                {{ $payroll->karyawan->jabatan ?? '-' }}
                                            </div>
                                            @if($hasMultiplePeriods)
                                                <div class="mt-2 pt-1.5 border-t border-slate-200/80 flex flex-col gap-1.5">
                                                    @foreach($itemBreakdowns as $ib)
                                                        <div class="flex items-center gap-1.5 text-[10px] font-bold {{ $ib['periode'] == 1 ? 'text-indigo-800' : 'text-purple-800' }} h-6">
                                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black {{ $ib['periode'] == 1 ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-purple-100 text-purple-800 border border-purple-200' }}">
                                                                P{{ $ib['periode'] }}
                                                            </span>
                                                            <span class="truncate text-slate-600 font-medium text-[9.5px]">
                                                                @if(!empty($ib['tanggal_mulai']) && !empty($ib['tanggal_selesai']))
                                                                    {{ \Carbon\Carbon::parse($ib['tanggal_mulai'])->format('d M') }} - {{ \Carbon\Carbon::parse($ib['tanggal_selesai'])->format('d M') }}
                                                                @else
                                                                    Periode {{ $ib['periode'] }}
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- 3. HARI KERJA (POKOK - INPUT TERPADU DENGAN P1/P2 BERTUMPUK) --}}
                                <td class="col-cat-pokok hr-input-cell px-3 py-3 text-center whitespace-nowrap border-b border-emerald-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="flex items-center gap-1 justify-center {{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <input type="number" step="0.5" min="0"
                                                           class="batch-hari-kerja batch-sub-item input-hk-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-hk-item-' . $ib['id'] : '' }} hr-data-input w-full px-2 py-0.5 text-xs text-center !bg-white"
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
                                </td>

                                {{-- 4. TARIF SATUAN (POKOK - ACCOUNTING STYLE - BERTUMPUK JIKA MULTI-PERIODE) --}}
                                <td class="col-cat-pokok px-3 py-3 border-b border-emerald-100 whitespace-nowrap">
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-1.5 w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <div class="accounting-cell text-[11px] font-bold text-slate-800">
                                                        <span class="ac-curr">Rp</span>
                                                        <span class="ac-val">{{ number_format($ib['tarif'], 0, ',', '.') }}<span class="text-[10px] text-slate-500 font-medium ml-0.5">{{ $ib['per_label'] }}</span></span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-cell text-xs font-bold text-slate-800">
                                            <span class="ac-curr">Rp</span>
                                            <span class="ac-val">{{ number_format($tarifHarian, 0, ',', '.') }}<span class="text-[10px] text-slate-500 font-medium ml-0.5">{{ $satuanRow === 'Per Jam' ? '/jam' : ($satuanRow === 'Bulanan' ? '/bln' : '/hari') }}</span></span>
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
                                                <div class="accounting-cell text-[10px] text-slate-600 font-bold {{ !$loop->first ? 'pt-0.5' : '' }}">
                                                    <span class="ac-curr">Rp</span>
                                                    <span class="ac-val sub-gp-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'sub-gp-item-' . $ib['id'] : '' }}">{{ number_format($ib['gaji_utama'], 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- 6. JAM LEMBUR (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus hr-input-cell px-2 py-2 border-b border-amber-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <div class="flex items-center gap-1 justify-center">
                                                        <input type="number" step="0.5" min="0"
                                                               class="batch-jam-lembur batch-sub-item-lembur input-lembur-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-lembur-item-' . $ib['id'] : '' }} hr-data-input w-full px-1.5 py-0.5 text-xs text-center !bg-white"
                                                               data-item-id="{{ $ib['id'] }}"
                                                               data-periode="{{ $ib['periode'] }}"
                                                               value="{{ ($ib['jam_lembur'] && $ib['jam_lembur'] > 0) ? (float)$ib['jam_lembur'] : '' }}"
                                                               placeholder="0"
                                                               oninput="onPayrollUnifiedRowInput(this)">
                                                        <span class="text-[9.5px] text-amber-900 font-bold">jam</span>
                                                    </div>
                                                    <div class="sub-lembur-text-p-{{ $ib['periode'] }} accounting-cell text-[9px] text-amber-900 font-bold px-1 mt-0.5 {{ ($ib['jam_lembur'] ?? 0) > 0 ? '' : 'hidden' }}">
                                                        <span class="ac-curr">Rp</span>
                                                        <span class="ac-val">{{ number_format(($ib['jam_lembur'] ?? 0) * 10000, 0, ',', '.') }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
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
                                    @endif
                                </td>

                                {{-- 7. BONUS TARGET (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus hr-input-cell px-2 py-2 border-b border-amber-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    @if($ib['satuan'] === 'Harian')
                                                        <div class="flex items-center gap-1 justify-center">
                                                            <input type="number" step="1" min="0"
                                                                   class="batch-banyak-target batch-sub-item-target input-target-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-target-item-' . $ib['id'] : '' }} hr-data-input w-full px-1.5 py-0.5 text-xs text-center !bg-white"
                                                                   data-item-id="{{ $ib['id'] }}"
                                                                   data-periode="{{ $ib['periode'] }}"
                                                                   data-tarif="{{ (float)$ib['tarif'] }}"
                                                                   value="{{ ($ib['banyak_target'] && $ib['banyak_target'] > 0) ? (int)$ib['banyak_target'] : '' }}"
                                                                   placeholder="0"
                                                                   oninput="onPayrollUnifiedRowInput(this)">
                                                            <span class="text-[9.5px] text-amber-900 font-bold">kali</span>
                                                        </div>
                                                        <div class="sub-target-text-p-{{ $ib['periode'] }} accounting-cell text-[9px] text-amber-900 font-bold px-1 mt-0.5 {{ ($ib['banyak_target'] ?? 0) > 0 ? '' : 'hidden' }}">
                                                            <span class="ac-curr">Rp</span>
                                                            <span class="ac-val">{{ number_format(($ib['banyak_target'] ?? 0) * (float)$ib['tarif'], 0, ',', '.') }}</span>
                                                        </div>
                                                    @else
                                                        <div class="accounting-input-wrap flex-1">
                                                            <span class="ac-input-prefix">Rp</span>
                                                            <input type="text"
                                                                   class="batch-input-rupiah batch-bonus-target batch-sub-item-target-rp input-target-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-target-item-' . $ib['id'] : '' }} hr-data-input"
                                                                   data-item-id="{{ $ib['id'] }}"
                                                                   data-periode="{{ $ib['periode'] }}"
                                                                   value="{{ $ib['bonus_target'] > 0 ? number_format($ib['bonus_target'], 0, ',', '.') : '' }}"
                                                                   placeholder="0"
                                                                   oninput="onPayrollUnifiedRowInput(this)">
                                                        </div>
                                                        <input type="hidden" class="batch-catatan-target batch-sub-catatan-target input-catatan-target-p-{{ $ib['periode'] }}" data-item-id="{{ $ib['id'] }}" data-periode="{{ $ib['periode'] }}" value="{{ $ib['catatan_bonus_target'] ?? '' }}">
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
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
                                    @endif
                                </td>

                                {{-- 8. TANGGAL MERAH (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus hr-input-cell px-2 py-2 border-b border-amber-100">
                                        @if($hasMultiplePeriods)
                                            <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                                @foreach($itemBreakdowns as $ib)
                                                    <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                        @if($ib['satuan'] === 'Harian')
                                                            <div class="flex items-center gap-1 justify-center">
                                                                <input type="number" step="1" min="0"
                                                                       class="batch-banyak-merah batch-sub-item-merah input-merah-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-merah-item-' . $ib['id'] : '' }} hr-data-input w-full px-1.5 py-0.5 text-xs text-center !bg-white"
                                                                       data-item-id="{{ $ib['id'] }}"
                                                                       data-periode="{{ $ib['periode'] }}"
                                                                       data-tarif="{{ (float)$ib['tarif'] }}"
                                                                       value="{{ ($ib['banyak_tanggal_merah'] && $ib['banyak_tanggal_merah'] > 0) ? (int)$ib['banyak_tanggal_merah'] : '' }}"
                                                                       placeholder="0"
                                                                       oninput="onPayrollUnifiedRowInput(this)">
                                                                <span class="text-[9.5px] text-amber-900 font-bold">hari</span>
                                                            </div>
                                                            <div class="sub-merah-text-p-{{ $ib['periode'] }} accounting-cell text-[9px] text-amber-900 font-bold px-1 mt-0.5 {{ ($ib['banyak_tanggal_merah'] ?? 0) > 0 ? '' : 'hidden' }}">
                                                                <span class="ac-curr">Rp</span>
                                                                <span class="ac-val">{{ number_format(($ib['banyak_tanggal_merah'] ?? 0) * (float)$ib['tarif'], 0, ',', '.') }}</span>
                                                            </div>
                                                        @else
                                                            <div class="accounting-input-wrap flex-1">
                                                                <span class="ac-input-prefix">Rp</span>
                                                                <input type="text"
                                                                       class="batch-input-rupiah batch-bonus-merah batch-sub-item-merah-rp input-merah-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-merah-item-' . $ib['id'] : '' }} hr-data-input"
                                                                       data-item-id="{{ $ib['id'] }}"
                                                                       data-periode="{{ $ib['periode'] }}"
                                                                       value="{{ $ib['bonus_tanggal_merah'] > 0 ? number_format($ib['bonus_tanggal_merah'], 0, ',', '.') : '' }}"
                                                                       placeholder="0"
                                                                       oninput="onPayrollUnifiedRowInput(this)">
                                                            </div>
                                                            <input type="hidden" class="batch-catatan-merah batch-sub-catatan-merah input-catatan-merah-p-{{ $ib['periode'] }}" data-item-id="{{ $ib['id'] }}" data-periode="{{ $ib['periode'] }}" value="{{ $ib['catatan_bonus_tanggal_merah'] ?? '' }}">
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
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
                                        @endif
                                </td>

                                {{-- 9. BIRTHDAY SERVICE (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus hr-input-cell px-2 py-2 border-b border-amber-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <div class="flex items-center gap-1 justify-center">
                                                        <input type="number" step="1" min="0"
                                                               class="batch-banyak-birthday batch-sub-item-birthday input-birthday-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-birthday-item-' . $ib['id'] : '' }} hr-data-input w-full px-1.5 py-0.5 text-xs text-center !bg-white"
                                                               data-item-id="{{ $ib['id'] }}"
                                                               data-periode="{{ $ib['periode'] }}"
                                                               value="{{ ($ib['banyak_birthday_service'] && $ib['banyak_birthday_service'] > 0) ? (int)$ib['banyak_birthday_service'] : '' }}"
                                                               placeholder="0"
                                                               oninput="onPayrollUnifiedRowInput(this)">
                                                        <span class="text-[9.5px] text-amber-900 font-bold">srv</span>
                                                    </div>
                                                    <div class="sub-birthday-text-p-{{ $ib['periode'] }} accounting-cell text-[9px] text-amber-900 font-bold px-1 mt-0.5 {{ ($ib['banyak_birthday_service'] ?? 0) > 0 ? '' : 'hidden' }}">
                                                        <span class="ac-curr">Rp</span>
                                                        <span class="ac-val">{{ number_format(($ib['banyak_birthday_service'] ?? 0) * 5000, 0, ',', '.') }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
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
                                    @endif
                                </td>

                                {{-- 10. PENGEMBALIAN DEPOSIT (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus hr-input-cell px-2 py-2 border-b border-amber-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-input-wrap {{ !$loop->first ? 'mt-1' : '' }}">
                                                    <span class="ac-input-prefix">Rp</span>
                                                    <input type="text"
                                                           class="batch-input-rupiah batch-pengembalian-deposit batch-sub-item-dep-kembali input-deposit-kembali-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-deposit-kembali-item-' . $ib['id'] : '' }} hr-data-input !text-emerald-800"
                                                           data-item-id="{{ $ib['id'] }}"
                                                           data-periode="{{ $ib['periode'] }}"
                                                           value="{{ $ib['pengembalian_deposit'] > 0 ? number_format($ib['pengembalian_deposit'], 0, ',', '.') : '' }}"
                                                           placeholder="0"
                                                           oninput="onPayrollUnifiedRowInput(this)">
                                                </div>
                                                @if(($ib['periode'] == 2 || ($loop->last && !collect($itemBreakdowns)->contains('periode', 2))) && ($payroll->saldo_deposit ?? 0) > 0)
                                                    <button type="button"
                                                            onclick="isiDepositOtomatis(this, {{ (float)$payroll->saldo_deposit }}, {{ $ib['periode'] }})"
                                                            class="mt-0.5 text-[8.5px] font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 px-1 py-0.5 rounded cursor-pointer transition-all inline-flex items-center gap-1 w-full justify-end"
                                                            title="Klik untuk mengisi otomatis deposit ke periode {{ $ib['periode'] }}">
                                                        <span>&#8629; Saldo: {{ number_format($payroll->saldo_deposit, 0, ',', '.') }}</span>
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
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
                                    @endif
                                </td>

                                {{-- 11. BONUS LAIN (BONUS - KUNING MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus hr-input-cell px-2 py-2 border-b border-amber-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <div class="accounting-input-wrap">
                                                        <span class="ac-input-prefix">Rp</span>
                                                        <input type="text"
                                                               class="batch-input-rupiah batch-bonus-dll batch-sub-item-bonus-dll input-bonus-dll-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-bonus-dll-item-' . $ib['id'] : '' }} hr-data-input"
                                                               data-item-id="{{ $ib['id'] }}"
                                                               data-periode="{{ $ib['periode'] }}"
                                                               value="{{ $ib['bonus_dll'] > 0 ? number_format($ib['bonus_dll'], 0, ',', '.') : '' }}"
                                                               placeholder="0"
                                                               oninput="onPayrollUnifiedRowInput(this)">
                                                    </div>
                                                    <input type="text"
                                                           class="batch-catatan-bonus-dll batch-sub-cat-bonus-dll input-cat-bonus-dll-p-{{ $ib['periode'] }} w-full text-left bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200 focus:border-amber-400 rounded px-1 py-[1px] text-[7.5px] font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-amber-300/40 mt-0.5 placeholder:text-slate-400 placeholder:text-[7px] placeholder:italic transition-all"
                                                           data-item-id="{{ $ib['id'] }}"
                                                           data-periode="{{ $ib['periode'] }}"
                                                           value="{{ $ib['catatan_bonus_dll'] ?? '' }}"
                                                           placeholder="Ket..."
                                                           title="Keterangan bonus lain-lain P{{ $ib['periode'] }}">
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-bonus-dll hr-data-input"
                                                   value="{{ $payroll->bonus_dll > 0 ? number_format($payroll->bonus_dll, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                        <input type="text"
                                               class="batch-catatan-bonus-dll w-full text-left bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200 focus:border-amber-400 rounded px-1.5 py-[1px] text-[7.5px] font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-amber-300/40 mt-1 placeholder:text-slate-400 placeholder:text-[7px] placeholder:italic transition-all"
                                               value="{{ $payroll->catatan_bonus_dll ?? '' }}"
                                               placeholder="Ket..."
                                               title="Keterangan bonus lain-lain">
                                    @endif
                                </td>

                                {{-- 12. SUBTOTAL BONUS (BONUS SUBTOTAL - KUNING PENEGAS + BATAS TEGAS + ACCOUNTING STYLE) --}}
                                <td class="col-cat-bonus-subtotal px-3 py-3 font-black text-amber-900 text-xs row-total-bonus-cell border-b border-amber-200 cat-divider-bonus">
                                    <div class="accounting-cell">
                                        <span class="ac-curr">Rp</span>
                                        <span class="ac-val main-bonus-val">{{ number_format($totalBonus, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-0.5 mt-1 pt-1 border-t border-amber-200/70">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-cell text-[10px] text-amber-800 font-bold {{ !$loop->first ? 'pt-0.5' : '' }}">
                                                    <span class="ac-curr">Rp</span>
                                                    <span class="ac-val sub-bonus-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'sub-bonus-item-' . $ib['id'] : '' }}">{{ number_format($ib['total_bonus'], 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- 13. KETERLAMBATAN (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan px-3 py-3 border-b border-rose-100">
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-1 w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <input type="hidden" class="potongan-terlambat-raw-p-{{ $ib['periode'] }}" data-item-id="{{ $ib['id'] }}" data-periode="{{ $ib['periode'] }}" value="{{ (float)$ib['potongan_terlambat'] }}">
                                                    @if($ib['potongan_terlambat'] > 0)
                                                        <div class="accounting-cell font-bold text-rose-700 text-[11px]">
                                                            <span class="ac-curr">- Rp</span>
                                                            <span class="ac-val">{{ number_format($ib['potongan_terlambat'], 0, ',', '.') }}</span>
                                                        </div>
                                                    @else
                                                        <div class="text-right text-slate-400 font-bold text-[10px]">-</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        
                                    @else
                                        <input type="hidden" class="potongan-terlambat-raw" value="{{ (float)$payroll->potongan_terlambat }}">
                                        @if($payroll->potongan_terlambat > 0)
                                            <div class="accounting-cell font-bold text-rose-700 text-xs">
                                                <span class="ac-curr">- Rp</span>
                                                <span class="ac-val">{{ number_format($payroll->potongan_terlambat, 0, ',', '.') }}</span>
                                            </div>
                                            
                                        @else
                                            <div class="text-center text-slate-400 font-bold text-xs">-</div>
                                        @endif
                                    @endif
                                </td>

                                {{-- 14. KERUSAKAN INVENTARIS (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan hr-input-cell px-2 py-2 border-b border-rose-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-input-wrap {{ !$loop->first ? 'mt-1' : '' }}">
                                                    <span class="ac-input-prefix">Rp</span>
                                                    <input type="text"
                                                           class="batch-input-rupiah batch-potongan-inventaris batch-sub-item-pot-inv input-pot-inv-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-pot-inv-item-' . $ib['id'] : '' }} hr-data-input"
                                                           data-item-id="{{ $ib['id'] }}"
                                                           data-periode="{{ $ib['periode'] }}"
                                                           value="{{ $ib['potongan_inventaris'] > 0 ? number_format($ib['potongan_inventaris'], 0, ',', '.') : '' }}"
                                                           placeholder="0"
                                                           oninput="onPayrollUnifiedRowInput(this)">
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-inventaris hr-data-input"
                                                   value="{{ $payroll->potongan_inventaris > 0 ? number_format($payroll->potongan_inventaris, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @endif
                                </td>

                                {{-- 15. KASBON / PINJAMAN (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan hr-input-cell px-2 py-2 border-b border-rose-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-input-wrap {{ !$loop->first ? 'mt-1' : '' }}">
                                                    <span class="ac-input-prefix">Rp</span>
                                                    <input type="text"
                                                           class="batch-input-rupiah batch-potongan-kasbon batch-sub-item-pot-kasbon input-pot-kasbon-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-pot-kasbon-item-' . $ib['id'] : '' }} hr-data-input"
                                                           data-item-id="{{ $ib['id'] }}"
                                                           data-periode="{{ $ib['periode'] }}"
                                                           value="{{ $ib['potongan_kasbon'] > 0 ? number_format($ib['potongan_kasbon'], 0, ',', '.') : '' }}"
                                                           placeholder="0"
                                                           oninput="onPayrollUnifiedRowInput(this)">
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-kasbon hr-data-input"
                                                   value="{{ $payroll->potongan_kasbon > 0 ? number_format($payroll->potongan_kasbon, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @endif
                                </td>

                                {{-- 16. POTONGAN DEPOSIT (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan hr-input-cell px-2 py-2 border-b border-rose-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-input-wrap {{ !$loop->first ? 'mt-1' : '' }}">
                                                    <span class="ac-input-prefix">Rp</span>
                                                    <input type="text"
                                                           class="batch-input-rupiah batch-potongan-deposit batch-sub-item-pot-deposit input-pot-deposit-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-pot-deposit-item-' . $ib['id'] : '' }} hr-data-input"
                                                           data-item-id="{{ $ib['id'] }}"
                                                           data-periode="{{ $ib['periode'] }}"
                                                           value="{{ $ib['potongan_deposit'] > 0 ? number_format($ib['potongan_deposit'], 0, ',', '.') : '' }}"
                                                           placeholder="0"
                                                           title="Potongan deposit untuk karyawan baru"
                                                           oninput="onPayrollUnifiedRowInput(this)">
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-deposit hr-data-input"
                                                   value="{{ $payroll->potongan_deposit > 0 ? number_format($payroll->potongan_deposit, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   title="Potongan deposit untuk karyawan baru"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                    @endif
                                </td>

                                {{-- 17. POTONGAN LAIN & KETERANGAN (POTONGAN - MERAH MUDA - ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan hr-input-cell px-2 py-2 border-b border-rose-100">
                                    @if($hasMultiplePeriods)
                                        <div class="inline-flex flex-col gap-1.5 justify-center whitespace-nowrap w-full">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="{{ !$loop->first ? 'pt-1 border-t border-slate-100' : '' }}">
                                                    <div class="accounting-input-wrap">
                                                        <span class="ac-input-prefix">Rp</span>
                                                        <input type="text"
                                                               class="batch-input-rupiah batch-potongan-dll batch-sub-item-pot-dll input-pot-dll-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'input-pot-dll-item-' . $ib['id'] : '' }} hr-data-input"
                                                               data-item-id="{{ $ib['id'] }}"
                                                               data-periode="{{ $ib['periode'] }}"
                                                               value="{{ $ib['potongan_dll'] > 0 ? number_format($ib['potongan_dll'], 0, ',', '.') : '' }}"
                                                               placeholder="0"
                                                               oninput="onPayrollUnifiedRowInput(this)">
                                                    </div>
                                                    <input type="text"
                                                           class="batch-catatan-potongan-dll batch-sub-cat-pot-dll input-cat-pot-dll-p-{{ $ib['periode'] }} w-full text-left bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200 focus:border-rose-400 rounded px-1 py-[1px] text-[7.5px] font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-rose-300/40 mt-0.5 placeholder:text-slate-400 placeholder:text-[7px] placeholder:italic transition-all"
                                                           data-item-id="{{ $ib['id'] }}"
                                                           data-periode="{{ $ib['periode'] }}"
                                                           value="{{ $ib['catatan_potongan_dll'] ?? '' }}"
                                                           placeholder="Ket..."
                                                           title="Keterangan / rincian potongan lain-lain P{{ $ib['periode'] }}">
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="accounting-input-wrap">
                                            <span class="ac-input-prefix">Rp</span>
                                            <input type="text"
                                                   class="batch-input-rupiah batch-potongan-dll hr-data-input"
                                                   value="{{ $payroll->potongan_dll > 0 ? number_format($payroll->potongan_dll, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   oninput="onPayrollUnifiedRowInput(this)">
                                        </div>
                                        <input type="text"
                                               class="batch-catatan-potongan-dll w-full text-left bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200 focus:border-rose-400 rounded px-1.5 py-[1px] text-[7.5px] font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-rose-300/40 mt-1 placeholder:text-slate-400 placeholder:text-[7px] placeholder:italic transition-all"
                                               value="{{ $payroll->catatan_potongan_dll ?? '' }}"
                                               placeholder="Ket..."
                                               title="Keterangan / rincian potongan lain-lain">
                                    @endif
                                </td>

                                {{-- 18. SUBTOTAL POTONGAN (POTONGAN SUBTOTAL - MERAH PENEGAS + BATAS TEGAS + ACCOUNTING STYLE) --}}
                                <td class="col-cat-potongan-subtotal px-3 py-3 font-black text-rose-900 text-xs row-total-deductions-cell border-b border-rose-200 cat-divider-potongan">
                                    <div class="accounting-cell">
                                        <span class="ac-curr">- Rp</span>
                                        <span class="ac-val main-deductions-val">{{ number_format($totalPotongan, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-0.5 mt-1 pt-1 border-t border-rose-200/70">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-cell text-[10px] text-rose-800 font-bold {{ !$loop->first ? 'pt-0.5' : '' }}">
                                                    <span class="ac-curr">- Rp</span>
                                                    <span class="ac-val sub-potongan-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'sub-potongan-item-' . $ib['id'] : '' }}">{{ number_format($ib['total_deductions'], 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- 19. GAJI BERSIH (TAKE HOME PAY - MINT/EMERALD + ACCOUNTING STYLE) --}}
                                <td class="col-cat-nett px-3 py-3 border-b border-slate-100">
                                    <div class="accounting-cell text-sm font-black text-slate-900 row-take-home-pay-cell">
                                        <span class="ac-curr">Rp</span>
                                        <span class="ac-val main-thp-val">{{ number_format($payroll->take_home_pay, 0, ',', '.') }}</span>
                                    </div>
                                    @if($hasMultiplePeriods)
                                        <div class="flex flex-col gap-0.5 mt-1 pt-1 border-t border-slate-200">
                                            @foreach($itemBreakdowns as $ib)
                                                <div class="accounting-cell text-[10px] text-teal-900 font-extrabold {{ !$loop->first ? 'pt-0.5' : '' }}">
                                                    <span class="ac-curr">Rp</span>
                                                    <span class="ac-val sub-thp-p-{{ $ib['periode'] }} {{ $ib['id'] ? 'sub-thp-item-' . $ib['id'] : '' }}" data-is-paid="{{ $ib['is_paid'] ? '1' : '0' }}" data-tipe-pembayaran="{{ $ib['tipe_pembayaran'] ?? '' }}">{{ number_format($ib['total_gaji_bersih'], 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
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

                                                                                @if($hasMultiplePeriods)
                                            {{-- DUA TOMBOL BAYAR UNTUK KARYAWAN 2 PERIODE (P1 & P2 TERPISAH) --}}
                                            <div class="flex items-center gap-1.5">
                                                @foreach($itemBreakdowns as $ib)
                                                    @php
                                                        $ibId = $ib['id'];
                                                        $ibPaid = $ib['is_paid'] ?? false;
                                                        $ibPNum = $ib['periode'] ?? 1;
                                                        $ibConfirmMsg = 'Proses pembayaran dan jurnal gaji Periode ' . $ibPNum . ' atas nama ' . ($payroll->karyawan->nama_karyawan ?? 'Karyawan') . ' (Rp ' . number_format($ib['total_gaji_bersih'], 0, ',', '.') . ')?';
                                                    @endphp
                                                    @if($ibPaid)
                                                        {{-- TOMBOL DISABLED P1/P2 SUDAH DIBAYAR --}}
                                                        <button type="button" disabled
                                                                style="background-color: {{ $ibPNum == 1 ? '#e0f2fe' : '#ecfdf5' }}; color: {{ $ibPNum == 1 ? '#0369a1' : '#065f46' }}; border: 1px solid {{ $ibPNum == 1 ? '#bae6fd' : '#a7f3d0' }}; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 3px; cursor: not-allowed; opacity: 0.95; white-space: nowrap;"
                                                                title="Gaji Periode {{ $ibPNum }} sudah dibayar dan dijurnal">
                                                            <span>✓</span> P{{ $ibPNum }} Terbayar
                                                        </button>
                                                    @elseif($ibId)
                                                        {{-- TOMBOL AKTIF BAYAR P1 (BIRU) / BAYAR P2 (HIJAU) --}}
                                                        <form action="{{ route('penggajian.bayar', $ibId) }}" method="POST" class="inline m-0 p-0"
                                                              onsubmit="return confirm('{{ addslashes($ibConfirmMsg) }}')">
                                                            @csrf
                                                            <input type="hidden" name="tipe" value="{{ $ibPNum == 1 ? 'tengah_bulan' : 'akhir_bulan' }}">
                                                            <button type="submit"
                                                                    style="background-color: {{ $ibPNum == 1 ? '#0284c7' : '#059669' }}; color: #ffffff; border: none; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transition: background .15s; white-space: nowrap;"
                                                                    onmouseover="this.style.background='{{ $ibPNum == 1 ? '#0369a1' : '#047857' }}'"
                                                                    onmouseout="this.style.background='{{ $ibPNum == 1 ? '#0284c7' : '#059669' }}'"
                                                                    title="Bayar gaji Periode {{ $ibPNum }} ({{ $ibPNum == 1 ? 'Tengah Bulan' : 'Akhir Bulan' }})">
                                                                <span>{{ $ibPNum == 1 ? '🗓️' : '💳' }}</span> Bayar P{{ $ibPNum }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            {{-- TOMBOL BAYAR UNTUK KARYAWAN 1 PERIODE (PILIHAN TENGAH BULAN / AKHIR BULAN) --}}
                                            <div class="flex items-center gap-1.5">
                                                @if($isPaid)
                                                    @if(($payroll->tipe_pembayaran ?? '') === 'tengah_bulan')
                                                        {{-- Bayar Tengah: DISABLED (TERBAYAR) --}}
                                                        <button type="button" disabled
                                                                style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 3px; cursor: not-allowed; opacity: 0.95; white-space: nowrap;"
                                                                title="Gaji sudah dibayar di tengah bulan (Resign/Mangkir)">
                                                            <span>✓</span> Tengah Terbayar
                                                        </button>
                                                        {{-- Bayar Akhir: DISABLED --}}
                                                        <button type="button" disabled
                                                                style="background-color: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 3px; cursor: not-allowed; opacity: 0.6; white-space: nowrap;"
                                                                title="Karyawan telah dibayar di tengah bulan">
                                                            <span>💳</span> Bayar Akhir
                                                        </button>
                                                    @else
                                                        {{-- Bayar Tengah: DISABLED --}}
                                                        <button type="button" disabled
                                                                style="background-color: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 3px; cursor: not-allowed; opacity: 0.6; white-space: nowrap;"
                                                                title="Gaji telah dibayar di akhir bulan">
                                                            <span>🗓️</span> Bayar Tengah
                                                        </button>
                                                        {{-- Bayar Akhir: DISABLED (TERBAYAR) --}}
                                                        <button type="button" disabled
                                                                style="background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 3px; cursor: not-allowed; opacity: 0.95; white-space: nowrap;"
                                                                title="Gaji sudah dibayar di akhir bulan">
                                                            <span>✓</span> Terbayar
                                                        </button>
                                                    @endif
                                                @else
                                                    {{-- Bayar Tengah Bulan AKTIF --}}
                                                    <form action="{{ route('penggajian.bayar', $payroll->id) }}" method="POST" class="inline m-0 p-0"
                                                          onsubmit="return confirm('Proses pembayaran TENGAH BULAN (karyawan keluar/mangkir) atas nama {{ addslashes($payroll->karyawan->nama_karyawan ?? 'Karyawan') }} (Rp {{ number_format($payroll->take_home_pay ?: $payroll->total_gaji_bersih, 0, ',', '.') }})?')">
                                                        @csrf
                                                        <input type="hidden" name="tipe" value="tengah_bulan">
                                                        <button type="submit"
                                                                style="background-color: #0284c7; color: #ffffff; border: none; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(2,132,199,0.25); transition: background .15s; white-space: nowrap;"
                                                                onmouseover="this.style.background='#0369a1'"
                                                                onmouseout="this.style.background='#0284c7'"
                                                                title="Bayar Gaji Tengah Bulan (misal: karyawan keluar atau mangkir di tengah bulan)">
                                                            <span>🗓️</span> Bayar Tengah
                                                        </button>
                                                    </form>

                                                    {{-- Bayar Akhir Bulan AKTIF --}}
                                                    <form action="{{ route('penggajian.bayar', $payroll->id) }}" method="POST" class="inline m-0 p-0"
                                                          onsubmit="return confirm('Proses pembayaran AKHIR BULAN atas nama {{ addslashes($payroll->karyawan->nama_karyawan ?? 'Karyawan') }} (Rp {{ number_format($payroll->take_home_pay ?: $payroll->total_gaji_bersih, 0, ',', '.') }})?')">
                                                        @csrf
                                                        <input type="hidden" name="tipe" value="akhir_bulan">
                                                        <button type="submit"
                                                                style="background-color: #059669; color: #ffffff; border: none; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(5,150,105,0.25); transition: background .15s; white-space: nowrap;"
                                                                onmouseover="this.style.background='#047857'"
                                                                onmouseout="this.style.background='#059669'"
                                                                title="Bayar Gaji Akhir Bulan">
                                                            <span>💳</span> Bayar Akhir
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
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
                                                                $pNum = (int)($pItem->pilihan_periode ?? 1);
                                                                $pItemMulai = $pItem->tanggal_mulai ?: (($pNum === 2) ? ($payroll->karyawan->tanggal_mulai_2 ?? null) : ($payroll->karyawan->tanggal_mulai ?? null));
                                                                $pItemSelesai = $pItem->tanggal_selesai ?: (($pNum === 2) ? ($payroll->karyawan->tanggal_selesai_2 ?? null) : ($payroll->karyawan->tanggal_selesai ?? null));
                                                                $pItemPayload = [
                                                                    'id' => $pItem->id,
                                                                    'karyawan_id' => $pItem->karyawan_id,
                                                                    'karyawan' => $payroll->karyawan,
                                                                    'tanggal_mulai' => $pItemMulai,
                                                                    'tanggal_selesai' => $pItemSelesai,
                                                                    'hari_kerja' => $pItem->hari_kerja,
                                                                    'pilihan_periode' => $pNum,
                                                                ];
                                                            @endphp
                                                            <button type="button" @click="openEditModal({{ json_encode($pItemPayload) }})"
                                                                    class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-50 transition-colors text-left cursor-pointer pl-6">
                                                                <span>&#128197;</span> Edit Periode {{ $pNum }}
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
                                            <span>
                                                Bonus Lain-lain
                                                <span class="text-[10px] text-slate-500 italic font-normal block" x-show="activeDetail.catatan_bonus_dll" x-text="'(' + activeDetail.catatan_bonus_dll + ')'"></span>
                                            </span>
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
    
        {{-- ========================================================================= --}}
        {{-- POPUP MODAL EXPORT PAYROLL EXCEL (SELEKSI GAJI KARYAWAN & PERIODE) --}}
        {{-- ========================================================================= --}}
        <div id="modalExportPayrollExcel" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
            {{-- BACKDROP --}}
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); cursor: pointer;"
                 onclick="closeExportPayrollModal()"></div>

            {{-- MODAL CONTAINER --}}
            <div style="position: relative; background: #ffffff; border-radius: 16px; width: 100%; max-width: 820px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; z-index: 100000; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;"
                 onclick="event.stopPropagation()">
                
                {{-- HEADER --}}
                <div style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 1px solid #a7f3d0;">
                            📊
                        </div>
                        <div>
                            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">Export Excel Transfer Payroll Bank</h3>
                            <p style="font-size: 11.5px; color: #64748b; font-weight: 600; margin: 2px 0 0 0;">
                                Periode {{ \App\Models\Penggajian::formatPeriode($periode) }} &bull; Outlet {{ $selectedOutlet }}
                            </p>
                        </div>
                    </div>
                    <button type="button" onclick="closeExportPayrollModal()"
                            style="background: none; border: none; color: #64748b; font-size: 24px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0 4px;"
                            onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#64748b'">
                        &times;
                    </button>
                </div>

                {{-- FORM BODY --}}
                <form action="{{ route('penggajian.export-excel') }}" method="POST" id="formExportPayrollExcel" style="display: flex; flex-direction: column; overflow: hidden; flex: 1; margin: 0;">
                    @csrf
                    <input type="hidden" name="periode" value="{{ $periode }}">
                    <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                    <input type="hidden" name="filter_label" id="exportFilterLabel" value="">
                    <input type="hidden" name="ordered_karyawan_ids" id="exportOrderedKaryawanIds" value="">

                    {{-- PRESET SELECTION & SEARCH BAR --}}
                    <div style="padding: 12px 20px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 10px; flex-shrink: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div style="font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                                Filter Cepat Periode:
                            </div>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" onclick="setExportPreset('all')" id="btnPresetAll"
                                        class="btn-export-preset active"
                                        style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1.5px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer; transition: all .15s;">
                                    Semua Gaji (P1+P2)
                                </button>
                                <button type="button" onclick="setExportPreset('p2_monthly')" id="btnPresetP2Monthly"
                                        class="btn-export-preset"
                                        style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1.5px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer; transition: all .15s;"
                                        title="Pilih Periode 2 untuk karyawan 2 periode + gaji bulanan (abaikan P1 yang sudah ditransfer sebelumnya)">
                                    ✨ Hanya P2 &amp; Bulanan
                                </button>
                                <button type="button" onclick="setExportPreset('p1')" id="btnPresetP1"
                                        class="btn-export-preset"
                                        style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1.5px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer; transition: all .15s;">
                                    Hanya Periode 1 (P1)
                                </button>
                                <button type="button" onclick="setExportPreset('clear')"
                                        style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px dashed #cbd5e1; background: transparent; color: #64748b; cursor: pointer;">
                                    Reset / Kosongkan
                                </button>
                            </div>
                        </div>

                        {{-- SEARCH & LIVE SUMMARY BAR --}}
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <div style="position: relative; flex: 1; min-width: 220px;">
                                <input type="text" id="searchExportKaryawan" oninput="filterExportTableList()"
                                       placeholder="🔍 Cari nama karyawan / rekening..."
                                       style="width: 100%; padding: 6px 12px; font-size: 11.5px; border-radius: 7px; border: 1px solid #cbd5e1; background: #ffffff; outline: none; box-sizing: border-box;">
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 7px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px;">
                                    <span>Terpilih:</span>
                                    <span id="exportSelectedCount" style="color: #059669; font-weight: 800;">0</span> / <span id="exportTotalCount">{{ isset($exportModalList) ? count($exportModalList) : 0 }}</span>
                                </div>
                                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 7px; padding: 4px 10px; font-size: 11.5px; font-weight: 800; color: #065f46;">
                                    Total: <span id="exportSelectedNominalTotal">Rp 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TABLE OF ITEMS (SCROLLABLE) --}}
                    <div style="overflow-y: auto; max-height: 48vh; padding: 0 4px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
                            <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10; border-bottom: 1px solid #cbd5e1;">
                                <tr style="color: #64748b; font-size: 10.5px; font-weight: 800; text-transform: uppercase;">
                                    <th style="padding: 8px 10px; width: 36px; text-align: center;">
                                        <input type="checkbox" id="checkAllExportModal" onchange="toggleAllExportModal(this.checked)" style="cursor: pointer;">
                                    </th>
                                    <th style="padding: 8px 10px; text-align: left;">Karyawan</th>
                                    <th style="padding: 8px 10px; text-align: center; width: 100px;">Periode</th>
                                    <th style="padding: 8px 10px; text-align: left; width: 190px;">No. Rekening</th>
                                    <th style="padding: 8px 12px; text-align: right; width: 140px;">Nominal THP</th>
                                </tr>
                            </thead>
                            <tbody id="exportModalTbody">
                                @if(isset($exportModalList) && count($exportModalList) > 0)
                                    @foreach($exportModalList as $item)
                                    <tr class="export-item-row"
                                        data-karyawan-id="{{ $item['karyawan_id'] }}"
                                        data-nama="{{ strtolower($item['nama']) }}"
                                        data-rekening="{{ $item['no_rekening'] }}"
                                        data-is-multi="{{ $item['is_multi'] ? '1' : '0' }}"
                                        data-periode-num="{{ $item['periode_num'] }}"
                                        data-tipe-pembayaran="{{ $item['tipe_pembayaran'] ?? '' }}"
                                        data-has-rekening="{{ !empty($item['no_rekening']) ? '1' : '0' }}"
                                        style="border-bottom: 1px solid #f1f5f9; transition: background .1s;"
                                        onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                        
                                        {{-- CHECKBOX --}}
                                        <td style="padding: 8px 10px; text-align: center;">
                                            <input type="checkbox" name="payroll_ids[]" value="{{ $item['id'] }}"
                                                   class="check-export-item"
                                                   data-karyawan-id="{{ $item['karyawan_id'] }}"
                                                   data-thp="{{ $item['thp'] }}"
                                                   data-has-rekening="{{ !empty($item['no_rekening']) ? '1' : '0' }}"
                                                   data-is-multi="{{ $item['is_multi'] ? '1' : '0' }}"
                                                   data-periode-num="{{ $item['periode_num'] }}"
                                                   data-tipe-pembayaran="{{ $item['tipe_pembayaran'] ?? '' }}"
                                                   onchange="onExportItemChange()"
                                                   style="cursor: pointer;">
                                        </td>

                                        {{-- NAMA & JABATAN --}}
                                        <td style="padding: 8px 10px;">
                                            <div style="font-weight: 800; color: #0f172a;">{{ $item['nama'] }}</div>
                                            <div style="font-size: 10px; color: #64748b; font-weight: 600;">
                                                {{ $item['jabatan'] }} @if($item['departemen']) &bull; {{ $item['departemen'] }} @endif
                                            </div>
                                        </td>

                                        {{-- PERIODE BADGE --}}
                                        <td style="padding: 8px 10px; text-align: center;">
                                            @if($item['is_multi'])
                                                @if($item['periode_num'] == 1)
                                                    <span style="font-size: 10px; font-weight: 800; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; border-radius: 5px; padding: 2px 7px;">
                                                        Periode 1
                                                    </span>
                                                @else
                                                    <span style="font-size: 10px; font-weight: 800; background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; border-radius: 5px; padding: 2px 7px;">
                                                        Periode 2
                                                    </span>
                                                @endif
                                            @else
                                                <span style="font-size: 10px; font-weight: 800; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 5px; padding: 2px 7px;">
                                                    {{ $item['periode_label'] }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- NO REKENING & BANK --}}
                                        <td style="padding: 8px 10px;">
                                            @if(!empty($item['no_rekening']))
                                                <div style="font-family: monospace; font-weight: 700; color: #0f172a; font-size: 11.5px;">
                                                    {{ $item['no_rekening_display'] }}
                                                </div>
                                                <div style="font-size: 9.5px; color: #64748b; font-weight: 700;">
                                                    {{ $item['bank'] ?: 'BANK' }}
                                                </div>
                                            @else
                                                <span style="font-size: 10px; font-weight: 800; background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 5px; padding: 1px 6px;">
                                                    ⚠️ Rekening Belum Diisi
                                                </span>
                                            @endif
                                        </td>

                                        {{-- NOMINAL THP --}}
                                        <td style="padding: 8px 12px; text-align: right;">
                                            <span style="font-weight: 800; color: #047857; font-size: 12px; font-variant-numeric: tabular-nums;">
                                                Rp {{ number_format($item['thp'], 0, ',', '.') }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" style="padding: 24px; text-align: center; color: #64748b;">
                                            Tidak ada data penggajian untuk periode ini.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    {{-- FOOTER BUTTONS --}}
                    <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                        <button type="button" onclick="closeExportPayrollModal()"
                                style="padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #475569; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer;">
                            Batal
                        </button>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="submit" id="btnSubmitExportExcel"
                                    style="padding: 8px 20px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #166534; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(22,101,52,0.3); transition: all .15s;">
                                <span>📥</span> Unduh Excel Transfer (<span id="btnExportCountBadge">0</span> Item)
                            </button>
                        </div>
                    </div>
                </form>
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
                    this.pilihanPeriode = payroll.pilihan_periode || 1;
                    
                    const k = payroll.karyawan;
                    let tMulai = payroll.tanggal_mulai;
                    let tSelesai = payroll.tanggal_selesai;
                    if (!tMulai && k) {
                        tMulai = (this.pilihanPeriode == 2) ? (k.tanggal_mulai_2 || '') : (k.tanggal_mulai || '');
                    }
                    if (!tSelesai && k) {
                        tSelesai = (this.pilihanPeriode == 2) ? (k.tanggal_selesai_2 || '') : (k.tanggal_selesai || '');
                    }
                    this.tanggalMulai = tMulai ? tMulai.substring(0, 10) : '';
                    this.tanggalSelesai = tSelesai ? tSelesai.substring(0, 10) : '';
                    this.hariKerja = payroll.hari_kerja || 0;
                    this.setupTariffData();
                    this.applyPeriodeSelection();
                    this.openGajiPokokModal = true;
                },
                
                openCreateModalForKaryawan(karyawanId, defaultPeriode) {
                    this.isEditMode = false;
                    this.formAction = '{{ route("penggajian.store") }}';
                    this.modalTitle = 'Tambah Gaji Periode ' + (defaultPeriode || 1);
                    this.karyawanId = String(karyawanId);
                    this.selectedKaryawanObj = this.allKaryawans.find(k => k.id == karyawanId) || null;
                    this.pilihanPeriode = defaultPeriode || 1;
                    const k = this.selectedKaryawanObj;
                    let tMulai = '';
                    let tSelesai = '';
                    if (k) {
                        tMulai = (this.pilihanPeriode == 2) ? (k.tanggal_mulai_2 || '') : (k.tanggal_mulai || '');
                        tSelesai = (this.pilihanPeriode == 2) ? (k.tanggal_selesai_2 || '') : (k.tanggal_selesai || '');
                    }
                    this.tanggalMulai = tMulai ? tMulai.substring(0, 10) : '';
                    this.tanggalSelesai = tSelesai ? tSelesai.substring(0, 10) : '';
                    this.hariKerja = 0;
                    this.setupTariffData();
                    this.applyPeriodeSelection();
                    this.onDateChange();
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
                    const k = this.selectedKaryawanObj;
                    if (k) {
                        this.tanggalMulai = k.tanggal_mulai ? k.tanggal_mulai.substring(0, 10) : '';
                        this.tanggalSelesai = k.tanggal_selesai ? k.tanggal_selesai.substring(0, 10) : '';
                    }
                    this.setupTariffData();
                    this.applyPeriodeSelection();
                    this.onDateChange();
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
                    const k = this.selectedKaryawanObj;
                    if (k) {
                        const tMulai = (p == 2) ? (k.tanggal_mulai_2 || '') : (k.tanggal_mulai || '');
                        const tSelesai = (p == 2) ? (k.tanggal_selesai_2 || '') : (k.tanggal_selesai || '');
                        if (tMulai) this.tanggalMulai = tMulai.substring(0, 10);
                        if (tSelesai) this.tanggalSelesai = tSelesai.substring(0, 10);
                        this.onDateChange();
                    }
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
                m.style.maxHeight = '';
                m.style.overflowY = '';
                m.style.visibility = '';
            });

            if (isHidden) {
                // Tampilkan sementara secara tersembunyi agar bisa diukur dimensi aslinya
                menu.classList.remove('hidden');
                menu.style.position = 'fixed';
                menu.style.zIndex = '99999';
                menu.style.width = '210px';
                menu.style.overflowY = 'auto';
                menu.style.visibility = 'hidden';

                const rect = btn.getBoundingClientRect();
                const menuWidth = 210;
                const menuHeight = menu.scrollHeight || menu.offsetHeight || 380;
                
                // Posisikan horizontal: sejajar kanan tombol
                let leftPos = rect.right - menuWidth;
                if (leftPos < 10) leftPos = 10;
                menu.style.left = leftPos + 'px';

                // Posisikan vertikal: cek ruang bawah vs atas
                const spaceBelow = window.innerHeight - rect.bottom - 12;
                const spaceAbove = rect.top - 12;

                if (spaceBelow >= menuHeight) {
                    // Cukup ruang ke bawah
                    menu.style.bottom = 'auto';
                    menu.style.top = (rect.bottom + 4) + 'px';
                    menu.style.maxHeight = spaceBelow + 'px';
                } else if (spaceAbove >= menuHeight) {
                    // Cukup ruang ke atas
                    menu.style.top = 'auto';
                    menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                    menu.style.maxHeight = spaceAbove + 'px';
                } else {
                    // Jika kedua sisi kurang dari tinggi menu, pilih sisi dengan ruang terbesar dan batasi maxHeight dengan scroll internal
                    if (spaceBelow >= spaceAbove) {
                        menu.style.bottom = 'auto';
                        menu.style.top = (rect.bottom + 4) + 'px';
                        menu.style.maxHeight = Math.max(160, spaceBelow) + 'px';
                    } else {
                        menu.style.top = 'auto';
                        menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                        menu.style.maxHeight = Math.max(160, spaceAbove) + 'px';
                    }
                }

                menu.style.visibility = 'visible';
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
                    m.style.maxHeight = '';
                    m.style.overflowY = '';
                    m.style.visibility = '';
                });
            }
        });

        // Tutup menu saat halaman di-scroll agar posisi fixed tidak melayang
        window.addEventListener('scroll', function() {
            document.querySelectorAll('.kebab-menu:not(.hidden)').forEach(m => {
                m.classList.add('hidden');
            });
        }, true);

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

        function isiDepositOtomatis(btn, saldo, targetPeriode) {
            const row = btn.closest('.payroll-row');
            if (!row) return;
            let targetInput = null;
            if (targetPeriode) {
                targetInput = row.querySelector('.input-deposit-kembali-p-' + targetPeriode);
            }
            if (!targetInput) {
                const depInputs = row.querySelectorAll('.batch-pengembalian-deposit');
                targetInput = depInputs.length > 1 ? depInputs[depInputs.length - 1] : depInputs[0];
            }
            if (targetInput) {
                targetInput.value = Math.round(saldo).toLocaleString('id-ID');
                onPayrollUnifiedRowInput(targetInput);
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
            const subInputs = row.querySelectorAll('.batch-sub-item');
            const hasMultiple = (row.getAttribute('data-has-multiple') === '1') || (subInputs.length > 0);

            let totalGajiPokok = 0;
            let totalBonus = 0;
            let totalPotongan = 0;
            let totalTakeHomePay = 0;

            if (hasMultiple && subInputs.length > 0) {
                // MULTI-PERIOD CALCULATION (P1, P2)
                let sumHk = 0;
                subInputs.forEach(input => {
                    const hk = parseFloat(input.value) || 0;
                    sumHk += hk;
                    const subSat = input.getAttribute('data-satuan') || 'Harian';
                    const subTar = parseFloat(input.getAttribute('data-tarif')) || 0;
                    const itemId = input.getAttribute('data-item-id');
                    const pNum = input.getAttribute('data-periode');
                    
                    // 1. Gaji Pokok
                    const subGp = (subSat === 'Bulanan') ? subTar : (hk * subTar);
                    totalGajiPokok += subGp;

                    let subDisplayEl = itemId ? row.querySelector('.sub-gp-item-' + itemId) : null;
                    if (!subDisplayEl && pNum) subDisplayEl = row.querySelector('.sub-gp-p-' + pNum);
                    if (subDisplayEl) subDisplayEl.textContent = Math.round(subGp).toLocaleString('id-ID');

                    // 2. Lembur P[pNum]
                    const lemburInp = row.querySelector('.input-lembur-p-' + pNum);
                    const jamLembur = lemburInp ? (parseFloat(lemburInp.value) || 0) : 0;
                    const upahLembur = jamLembur * 10000;
                    const subLemburText = row.querySelector('.sub-lembur-text-p-' + pNum);
                    if (subLemburText) {
                        const valEl = subLemburText.querySelector('.ac-val');
                        if (valEl) valEl.textContent = Math.round(upahLembur).toLocaleString('id-ID');
                        else subLemburText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahLembur).toLocaleString('id-ID') + '</span>';
                        subLemburText.classList.toggle('hidden', jamLembur <= 0);
                    }

                    // 3. Target P[pNum]
                    let upahTarget = 0;
                    const targetInp = row.querySelector('.input-target-p-' + pNum);
                    if (targetInp) {
                        if (targetInp.classList.contains('batch-banyak-target')) {
                            const banyakTarget = parseFloat(targetInp.value) || 0;
                            upahTarget = banyakTarget * subTar;
                            const subTargetText = row.querySelector('.sub-target-text-p-' + pNum);
                            if (subTargetText) {
                                const valEl = subTargetText.querySelector('.ac-val');
                                if (valEl) valEl.textContent = Math.round(upahTarget).toLocaleString('id-ID');
                                else subTargetText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahTarget).toLocaleString('id-ID') + '</span>';
                                subTargetText.classList.toggle('hidden', banyakTarget <= 0);
                            }
                        } else {
                            upahTarget = cleanNumber(targetInp.value);
                        }
                    }

                    // 4. Tanggal Merah P[pNum]
                    let upahMerah = 0;
                    const merahInp = row.querySelector('.input-merah-p-' + pNum);
                    if (merahInp) {
                        if (merahInp.classList.contains('batch-banyak-merah')) {
                            const banyakMerah = parseFloat(merahInp.value) || 0;
                            upahMerah = banyakMerah * subTar;
                            const subMerahText = row.querySelector('.sub-merah-text-p-' + pNum);
                            if (subMerahText) {
                                const valEl = subMerahText.querySelector('.ac-val');
                                if (valEl) valEl.textContent = Math.round(upahMerah).toLocaleString('id-ID');
                                else subMerahText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahMerah).toLocaleString('id-ID') + '</span>';
                                subMerahText.classList.toggle('hidden', banyakMerah <= 0);
                            }
                        } else {
                            upahMerah = cleanNumber(merahInp.value);
                        }
                    }

                    // 5. Birthday Service P[pNum]
                    const bdayInp = row.querySelector('.input-birthday-p-' + pNum);
                    const banyakBirthday = bdayInp ? (parseFloat(bdayInp.value) || 0) : 0;
                    const upahBirthday = banyakBirthday * 5000;
                    const subBdayText = row.querySelector('.sub-birthday-text-p-' + pNum);
                    if (subBdayText) {
                        const valEl = subBdayText.querySelector('.ac-val');
                        if (valEl) valEl.textContent = Math.round(upahBirthday).toLocaleString('id-ID');
                        else subBdayText.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val">' + Math.round(upahBirthday).toLocaleString('id-ID') + '</span>';
                        subBdayText.classList.toggle('hidden', banyakBirthday <= 0);
                    }

                    // 6. Pengembalian Deposit P[pNum]
                    const depInp = row.querySelector('.input-deposit-kembali-p-' + pNum);
                    const depKembali = depInp ? cleanNumber(depInp.value) : 0;

                    // 7. Bonus Dll P[pNum]
                    const bDllInp = row.querySelector('.input-bonus-dll-p-' + pNum);
                    const bonusDll = bDllInp ? cleanNumber(bDllInp.value) : 0;

                    // Period Bonus Subtotal
                    const subBonus = upahLembur + upahTarget + upahMerah + upahBirthday + depKembali + bonusDll;
                    totalBonus += subBonus;
                    const subBonusEl = row.querySelector('.sub-bonus-p-' + pNum);
                    if (subBonusEl) subBonusEl.textContent = Math.round(subBonus).toLocaleString('id-ID');

                    // 8. Potongan Terlambat P[pNum]
                    const potTerlambatInp = row.querySelector('.potongan-terlambat-raw-p-' + pNum);
                    const potTerlambat = potTerlambatInp ? (parseFloat(potTerlambatInp.value) || 0) : 0;

                    // 9. Potongan Inventaris P[pNum]
                    const potInvInp = row.querySelector('.input-pot-inv-p-' + pNum);
                    const potInv = potInvInp ? cleanNumber(potInvInp.value) : 0;

                    // 10. Potongan Kasbon P[pNum]
                    const potKasbonInp = row.querySelector('.input-pot-kasbon-p-' + pNum);
                    const potKasbon = potKasbonInp ? cleanNumber(potKasbonInp.value) : 0;

                    // 11. Potongan Deposit P[pNum]
                    const potDepInp = row.querySelector('.input-pot-deposit-p-' + pNum);
                    const potDeposit = potDepInp ? cleanNumber(potDepInp.value) : 0;

                    // 12. Potongan Dll P[pNum]
                    const potDllInp = row.querySelector('.input-pot-dll-p-' + pNum);
                    const potDll = potDllInp ? cleanNumber(potDllInp.value) : 0;

                    // Period Deductions Subtotal
                    const subPotongan = potTerlambat + potInv + potKasbon + potDeposit + potDll;
                    totalPotongan += subPotongan;
                    totalKasbon += potKasbon;
                    totalPotNonKasbon += (potTerlambat + potInv + potDeposit + potDll);
                    const subPotEl = row.querySelector('.sub-potongan-p-' + pNum);
                    if (subPotEl) subPotEl.textContent = Math.round(subPotongan).toLocaleString('id-ID');

                    // Period THP Subtotal
                    const subThp = subGp + subBonus - subPotongan;
                    const subThpEl = row.querySelector('.sub-thp-p-' + pNum);
                    if (subThpEl) subThpEl.textContent = Math.round(subThp).toLocaleString('id-ID');
                });

                const displayHkEl = row.querySelector('.display-total-hk');
                if (displayHkEl) displayHkEl.textContent = sumHk;

                totalTakeHomePay = totalGajiPokok + totalBonus - totalPotongan;

                // Update Main / Header cells in this row
                row.setAttribute('data-gaji-pokok', totalGajiPokok);
                const pokokCell = row.querySelector('.row-gaji-pokok-cell');
                if (pokokCell) {
                    const valEl = pokokCell.querySelector('.ac-val');
                    if (valEl) valEl.textContent = Math.round(totalGajiPokok).toLocaleString('id-ID');
                }

                row.setAttribute('data-bonus-total', totalBonus);
                const bonusCell = row.querySelector('.row-total-bonus-cell');
                if (bonusCell) {
                    const valEl = bonusCell.querySelector('.main-bonus-val');
                    if (valEl) valEl.textContent = Math.round(totalBonus).toLocaleString('id-ID');
                }

                row.setAttribute('data-deductions-total', totalPotongan);
                row.setAttribute('data-kasbon-total', totalKasbon);
                row.setAttribute('data-pot-non-kasbon', totalPotNonKasbon);
                const potCell = row.querySelector('.row-total-deductions-cell');
                if (potCell) {
                    const valEl = potCell.querySelector('.main-deductions-val');
                    if (valEl) valEl.textContent = Math.round(totalPotongan).toLocaleString('id-ID');
                }

                row.setAttribute('data-take-home-pay', totalTakeHomePay);
                const thpCell = row.querySelector('.row-take-home-pay-cell');
                if (thpCell) {
                    const valEl = thpCell.querySelector('.main-thp-val') || thpCell.querySelector('.ac-val');
                    if (valEl) valEl.textContent = Math.round(totalTakeHomePay).toLocaleString('id-ID');
                }

            } else {
                // SINGLE PERIOD CALCULATION
                const hkInput = row.querySelector('.batch-hari-kerja');
                const hk = hkInput ? (parseFloat(hkInput.value) || 0) : 0;
                totalGajiPokok = (satuan === 'Bulanan') ? tarif : (hk * tarif);

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

                totalBonus = upahLembur + upahTarget + upahMerah + upahBirthday + depKembali + bonusDll;
                row.setAttribute('data-bonus-total', totalBonus);
                const bonusCell = row.querySelector('.row-total-bonus-cell');
                if (bonusCell) {
                    const valEl = bonusCell.querySelector('.main-bonus-val');
                    if (valEl) {
                        valEl.textContent = Math.round(totalBonus).toLocaleString('id-ID');
                    } else {
                        if (totalBonus > 0) {
                            bonusCell.innerHTML = '<div class="accounting-cell"><span class="ac-curr">Rp</span><span class="ac-val main-bonus-val">' + Math.round(totalBonus).toLocaleString('id-ID') + '</span></div>';
                        } else {
                            bonusCell.innerHTML = '<div class="text-center font-bold text-amber-700/60 text-xs main-bonus-val">-</div>';
                        }
                    }
                }

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

                totalPotongan = potTerlambat + potInv + potKasbon + potDeposit + potDll;
                const potNonKasbon = potTerlambat + potInv + potDeposit + potDll;
                row.setAttribute('data-deductions-total', totalPotongan);
                row.setAttribute('data-kasbon-total', potKasbon);
                row.setAttribute('data-pot-non-kasbon', potNonKasbon);
                const potCell = row.querySelector('.row-total-deductions-cell');
                if (potCell) {
                    const valEl = potCell.querySelector('.main-deductions-val');
                    if (valEl) {
                        valEl.textContent = Math.round(totalPotongan).toLocaleString('id-ID');
                    } else {
                        if (totalPotongan > 0) {
                            potCell.innerHTML = '<div class="accounting-cell"><span class="ac-curr">- Rp</span><span class="ac-val main-deductions-val">' + Math.round(totalPotongan).toLocaleString('id-ID') + '</span></div>';
                        } else {
                            potCell.innerHTML = '<div class="text-center font-bold text-rose-700/60 text-xs main-deductions-val">-</div>';
                        }
                    }
                }

                totalTakeHomePay = totalGajiPokok + totalBonus - totalPotongan;
                row.setAttribute('data-take-home-pay', totalTakeHomePay);
                const thpCell = row.querySelector('.row-take-home-pay-cell');
                if (thpCell) {
                    const valEl = thpCell.querySelector('.main-thp-val') || thpCell.querySelector('.ac-val');
                    if (valEl) {
                        valEl.textContent = Math.round(totalTakeHomePay).toLocaleString('id-ID');
                    } else {
                        thpCell.innerHTML = '<span class="ac-curr">Rp</span><span class="ac-val main-thp-val">' + Math.round(totalTakeHomePay).toLocaleString('id-ID') + '</span>';
                    }
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
            let grandPokok = 0;
            let grandBonus = 0;
            let grandPotNonKasbon = 0;
            let grandKasbon = 0;
            let grandThpTotalKeseluruhan = 0;
            let grandThpAkhirBulan = 0;
            let grandThpPertengahan = 0; // Hanya yang SUDAH DIBAYAR tengah_bulan

            document.querySelectorAll('.payroll-row').forEach(row => {
                const isMultiple = row.getAttribute('data-has-multiple') === '1';
                const rowPokok = parseFloat(row.getAttribute('data-gaji-pokok')) || 0;
                const rowBonus = parseFloat(row.getAttribute('data-bonus-total')) || 0;
                const rowPotNonKasbon = parseFloat(row.getAttribute('data-pot-non-kasbon')) || 0;
                const rowKasbon = parseFloat(row.getAttribute('data-kasbon-total')) || 0;
                const rowNett = parseFloat(row.getAttribute('data-take-home-pay')) || 0;
                const isRowPaid = row.getAttribute('data-is-paid') === '1';
                const paymentType = row.getAttribute('data-tipe-pembayaran') || '';

                grandPokok += rowPokok;
                grandBonus += rowBonus;
                grandPotNonKasbon += rowPotNonKasbon;
                grandKasbon += rowKasbon;
                grandThpTotalKeseluruhan += rowNett;

                if (isMultiple) {
                    const p1El = row.querySelector('.sub-thp-p-1');
                    const p1Val = p1El ? (cleanNumber(p1El.textContent) || 0) : 0;
                    const p1Paid = p1El ? (p1El.getAttribute('data-is-paid') === '1') : false;

                    if (p1Paid) grandThpPertengahan += p1Val;
                } else {
                    if (isRowPaid) {
                        if (paymentType === 'tengah_bulan') {
                            grandThpPertengahan += rowNett;
                        }
                    }
                }
            });

            const grandBeban = grandThpTotalKeseluruhan + grandKasbon;
            // Total THP akhir bulan otomatis terisi dari: total beban gaji - total THP tengah - kasbon
            grandThpAkhirBulan = Math.max(0, grandBeban - grandThpPertengahan - grandKasbon);

            const pokokEl = document.getElementById('headerTotalGajiPokokValue');
            if (pokokEl) pokokEl.textContent = 'Rp ' + Math.round(grandPokok).toLocaleString('id-ID');

            const bonusEl = document.getElementById('headerTotalBonusValue');
            if (bonusEl) bonusEl.textContent = 'Rp ' + Math.round(grandBonus).toLocaleString('id-ID');

            const potNonKasbonEl = document.getElementById('headerTotalPotonganNonKasbonValue');
            if (potNonKasbonEl) potNonKasbonEl.textContent = 'Rp ' + Math.round(grandPotNonKasbon).toLocaleString('id-ID');

            const kasbonEl = document.getElementById('headerTotalKasbonValue');
            if (kasbonEl) kasbonEl.textContent = 'Rp ' + Math.round(grandKasbon).toLocaleString('id-ID');

            const thpAkhirEl = document.getElementById('headerTotalThpAkhirBulanValue');
            if (thpAkhirEl) thpAkhirEl.textContent = 'Rp ' + Math.round(grandThpAkhirBulan).toLocaleString('id-ID');

            const bebanEl = document.getElementById('headerTotalBebanGajiValue');
            if (bebanEl) bebanEl.textContent = 'Rp ' + Math.round(grandBeban).toLocaleString('id-ID');

            const thpMidEl = document.getElementById('headerTotalThpPertengahanValue');
            if (thpMidEl) thpMidEl.textContent = 'Rp ' + Math.round(grandThpPertengahan).toLocaleString('id-ID');
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
                const hasMultiple = (row.getAttribute('data-has-multiple') === '1') || (subInputs.length > 0);

                if (hasMultiple && subInputs.length > 0) {
                    subInputs.forEach((subInput) => {
                        const itemId = subInput.getAttribute('data-item-id');
                        const pNum = subInput.getAttribute('data-periode');
                        const hkVal = parseFloat(subInput.value) || 0;

                        const jamLemburInp = row.querySelector('.input-lembur-p-' + pNum);
                        const jamLembur = jamLemburInp ? (parseFloat(jamLemburInp.value) || 0) : 0;

                        const targetInp = row.querySelector('.input-target-p-' + pNum);
                        let banyakTarget = 0, bonusTarget = 0;
                        if (targetInp) {
                            if (targetInp.classList.contains('batch-banyak-target')) {
                                banyakTarget = parseFloat(targetInp.value) || 0;
                            } else {
                                bonusTarget = cleanNumber(targetInp.value);
                            }
                        }
                        const catTargetInp = row.querySelector('.input-catatan-target-p-' + pNum);
                        const catatanTarget = catTargetInp ? catTargetInp.value : null;

                        const merahInp = row.querySelector('.input-merah-p-' + pNum);
                        let banyakMerah = 0, bonusMerah = 0;
                        if (merahInp) {
                            if (merahInp.classList.contains('batch-banyak-merah')) {
                                banyakMerah = parseFloat(merahInp.value) || 0;
                            } else {
                                bonusMerah = cleanNumber(merahInp.value);
                            }
                        }
                        const catMerahInp = row.querySelector('.input-catatan-merah-p-' + pNum);
                        const catatanMerah = catMerahInp ? catMerahInp.value : null;

                        const bdayInp = row.querySelector('.input-birthday-p-' + pNum);
                        const banyakBirthday = bdayInp ? (parseFloat(bdayInp.value) || 0) : 0;

                        const depInp = row.querySelector('.input-deposit-kembali-p-' + pNum);
                        const depKembali = depInp ? cleanNumber(depInp.value) : 0;

                        const bDllInp = row.querySelector('.input-bonus-dll-p-' + pNum);
                        const bonusDll = bDllInp ? cleanNumber(bDllInp.value) : 0;
                        const catBDllInp = row.querySelector('.input-cat-bonus-dll-p-' + pNum);
                        const catBonusDll = catBDllInp ? catBDllInp.value : '';

                        const potInvInp = row.querySelector('.input-pot-inv-p-' + pNum);
                        const potInv = potInvInp ? cleanNumber(potInvInp.value) : 0;

                        const potKasbonInp = row.querySelector('.input-pot-kasbon-p-' + pNum);
                        const potKasbon = potKasbonInp ? cleanNumber(potKasbonInp.value) : 0;

                        const potDepInp = row.querySelector('.input-pot-deposit-p-' + pNum);
                        const potDeposit = potDepInp ? cleanNumber(potDepInp.value) : 0;

                        const potDllInp = row.querySelector('.input-pot-dll-p-' + pNum);
                        const potDll = potDllInp ? cleanNumber(potDllInp.value) : 0;

                        const catPotDllInp = row.querySelector('.input-cat-pot-dll-p-' + pNum);
                        const catPotDll = catPotDllInp ? catPotDllInp.value : '';

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
                            catatan_bonus_dll: catBonusDll,
                            potongan_inventaris: potInv,
                            potongan_kasbon: potKasbon,
                            potongan_deposit: potDeposit,
                            potongan_dll: potDll,
                            catatan_potongan_dll: catPotDll,
                        });
                    });
                } else {
                    const hkInput = row.querySelector('.batch-hari-kerja');
                    const activeItemId = row.getAttribute('data-active-item-id') || id;
                    const activePeriode = row.getAttribute('data-active-periode') || 1;
                    const hkVal = hkInput ? (parseFloat(hkInput.value) || 0) : 0;

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
                    const catBonusDll = row.querySelector('.batch-catatan-bonus-dll') ? row.querySelector('.batch-catatan-bonus-dll').value : '';

                    const potInv = row.querySelector('.batch-potongan-inventaris') ? cleanNumber(row.querySelector('.batch-potongan-inventaris').value) : 0;
                    const potKasbon = row.querySelector('.batch-potongan-kasbon') ? cleanNumber(row.querySelector('.batch-potongan-kasbon').value) : 0;
                    const potDeposit = row.querySelector('.batch-potongan-deposit') ? cleanNumber(row.querySelector('.batch-potongan-deposit').value) : 0;
                    const potDll = row.querySelector('.batch-potongan-dll') ? cleanNumber(row.querySelector('.batch-potongan-dll').value) : 0;
                    const catPotDll = row.querySelector('.batch-catatan-potongan-dll') ? row.querySelector('.batch-catatan-potongan-dll').value : '';

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
                        catatan_bonus_dll: catBonusDll,
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
    
        // =========================================================================
        // EXPORT EXCEL PAYROLL MODAL FUNCTIONS
        // =========================================================================
        function syncExportModalOrder() {
            // Urutkan baris di modal export sesuai urutan baris di tabel utama formulir hitung gaji
            const mainRows = document.querySelectorAll('#tbodyKaryawan .payroll-row');
            if (!mainRows || mainRows.length === 0) return;

            const modalTbody = document.getElementById('exportModalTbody');
            const orderedIds = [];
            mainRows.forEach(row => {
                const kid = row.getAttribute('data-karyawan-id');
                if (kid && !orderedIds.includes(kid)) {
                    orderedIds.push(kid);
                }
            });

            // Set hidden input agar controller menerima urutan yang pasti sama dengan tabel
            const orderedInput = document.getElementById('exportOrderedKaryawanIds');
            if (orderedInput) {
                orderedInput.value = orderedIds.join(',');
            }

            if (modalTbody) {
                const modalRows = Array.from(modalTbody.querySelectorAll('.export-item-row'));
                modalRows.sort((a, b) => {
                    const kidA = a.getAttribute('data-karyawan-id');
                    const kidB = b.getAttribute('data-karyawan-id');
                    const idxA = orderedIds.indexOf(kidA);
                    const idxB = orderedIds.indexOf(kidB);
                    const orderA = idxA === -1 ? 999999 : idxA;
                    const orderB = idxB === -1 ? 999999 : idxB;
                    if (orderA !== orderB) return orderA - orderB;
                    // Jika karyawan sama (misal multi periode P1 & P2), urutkan berdasarkan periode_num
                    const pA = parseInt(a.getAttribute('data-periode-num') || '1');
                    const pB = parseInt(b.getAttribute('data-periode-num') || '1');
                    return pA - pB;
                });
                modalRows.forEach(row => modalTbody.appendChild(row));
            }
        }

        function openExportPayrollModal() {
            const modal = document.getElementById('modalExportPayrollExcel');
            if (!modal) return;
            // Sinkronkan urutan baris modal dengan urutan tabel utama
            syncExportModalOrder();
            modal.style.display = 'flex';
            // Default preset: P2 & Bulanan (paling sering digunakan saat transfer akhir bulan)
            setExportPreset('p2_monthly');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const formExport = document.getElementById('formExportPayrollExcel');
            if (formExport) {
                formExport.addEventListener('submit', function() {
                    syncExportModalOrder();
                });
            }
        });

        function closeExportPayrollModal() {
            const modal = document.getElementById('modalExportPayrollExcel');
            if (modal) modal.style.display = 'none';
        }

        function setExportPreset(preset) {
            document.querySelectorAll('.btn-export-preset').forEach(b => {
                b.style.borderColor = '#cbd5e1';
                b.style.background = '#ffffff';
                b.style.color = '#1e293b';
            });

            const activeBtn = {
                'all': document.getElementById('btnPresetAll'),
                'p2_monthly': document.getElementById('btnPresetP2Monthly'),
                'p1': document.getElementById('btnPresetP1'),
            }[preset];

            if (activeBtn) {
                activeBtn.style.borderColor = '#059669';
                activeBtn.style.background = '#ecfdf5';
                activeBtn.style.color = '#065f46';
            }

            const filterLabelInput = document.getElementById('exportFilterLabel');
            if (filterLabelInput) {
                filterLabelInput.value = preset === 'p2_monthly' ? 'P2' : (preset === 'p1' ? 'P1' : '');
            }

            const checkboxes = document.querySelectorAll('.check-export-item');
            checkboxes.forEach(cb => {
                const isMulti = cb.getAttribute('data-is-multi') === '1';
                const pNum = parseInt(cb.getAttribute('data-periode-num') || '1');

                if (preset === 'all') {
                    cb.checked = true;
                } else if (preset === 'p2_monthly') {
                    // Jika multi periode: pilih P2 saja. Jika single periode: pilih yang bukan dibayar tengah bulan
                    const tipePembayaran = cb.getAttribute('data-tipe-pembayaran') || '';
                    cb.checked = isMulti ? (pNum === 2) : (tipePembayaran !== 'tengah_bulan');
                } else if (preset === 'p1') {
                    // Jika multi periode: pilih P1 saja. Jika single periode: pilih yang dibayar tengah bulan
                    const tipePembayaran = cb.getAttribute('data-tipe-pembayaran') || '';
                    cb.checked = isMulti ? (pNum === 1) : (tipePembayaran === 'tengah_bulan');
                } else if (preset === 'clear') {
                    cb.checked = false;
                }
            });

            onExportItemChange();
        }

        function toggleAllExportModal(checked) {
            const visibleRows = Array.from(document.querySelectorAll('.export-item-row')).filter(r => r.style.display !== 'none');
            visibleRows.forEach(r => {
                const cb = r.querySelector('.check-export-item');
                if (cb) cb.checked = checked;
            });
            onExportItemChange();
        }

        function filterExportTableList() {
            const q = (document.getElementById('searchExportKaryawan').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.export-item-row');
            rows.forEach(r => {
                const nama = r.getAttribute('data-nama') || '';
                const rek = r.getAttribute('data-rekening') || '';
                if (!q || nama.includes(q) || rek.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        }

        function onExportItemChange() {
            const checkboxes = document.querySelectorAll('.check-export-item');
            let totalNominal = 0;
            let selectedCount = 0;
            let allChecked = checkboxes.length > 0;

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedCount++;
                    const thp = parseFloat(cb.getAttribute('data-thp') || 0);
                    totalNominal += thp;
                } else {
                    allChecked = false;
                }
            });

            const checkAll = document.getElementById('checkAllExportModal');
            if (checkAll) {
                checkAll.checked = allChecked && checkboxes.length > 0;
            }

            const countEl = document.getElementById('exportSelectedCount');
            if (countEl) countEl.textContent = selectedCount;

            const countBadge = document.getElementById('btnExportCountBadge');
            if (countBadge) countBadge.textContent = selectedCount;

            const nomEl = document.getElementById('exportSelectedNominalTotal');
            if (nomEl) nomEl.textContent = 'Rp ' + Math.round(totalNominal).toLocaleString('id-ID');

            const btnSubmit = document.getElementById('btnSubmitExportExcel');
            if (btnSubmit) {
                if (selectedCount === 0) {
                    btnSubmit.disabled = true;
                    btnSubmit.style.opacity = '0.5';
                    btnSubmit.style.cursor = 'not-allowed';
                } else {
                    btnSubmit.disabled = false;
                    btnSubmit.style.opacity = '1';
                    btnSubmit.style.cursor = 'pointer';
                }
            }
        }

</script>
</x-app-layout>
