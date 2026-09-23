<x-app-layout>
    <style>
        .pg-form-wrap { max-width: 780px; margin: 0 auto; padding: 24px 16px 40px; }
        .pg-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .pg-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: #f9fafb;
            border-bottom: 1px solid #f3f4f6;
        }
        .pg-section-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .pg-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 0.2px;
        }
        .pg-section-body { padding: 20px; }
        .pg-field { margin-bottom: 16px; }
        .pg-field:last-child { margin-bottom: 0; }
        .pg-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .pg-input {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 9px 13px;
            font-size: 14px;
            color: #111827;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            box-sizing: border-box;
        }
        .pg-input:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
        .pg-input-rupiah { text-align: right; font-weight: 700; color: #0f172a; }
        .pg-sub-calc { font-size: 11px; color: #6b7280; text-align: right; margin-top: 4px; }
        .pg-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media(max-width:600px) { .pg-grid-2 { grid-template-columns: 1fr; } }
        .pg-summary {
            background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%);
            border: 1.5px solid #fcd34d;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .pg-summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; }
        @media(max-width:600px) { .pg-summary-grid { grid-template-columns: 1fr; } }
        .pg-summary-item { text-align: center; padding: 8px 12px; }
        .pg-summary-item + .pg-summary-item { border-left: 1px solid #fcd34d; }
        @media(max-width:600px) { .pg-summary-item + .pg-summary-item { border-left: none; border-top: 1px solid #fcd34d; } }
        .pg-summary-label { font-size: 11px; color: #92400e; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; }
        .pg-summary-value { font-size: 20px; font-weight: 800; margin-top: 4px; color: #b45309; }
        .pg-btn-save {
            width: 100%;
            background: #7A4517;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s;
        }
        .pg-btn-save:hover { background: #5a3416; }
    </style>

    <div class="pg-form-wrap">
        {{-- Header Outlet & Periode --}}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px;flex-wrap:wrap;border-bottom:1px solid #f1f5f9;padding-bottom:14px;">
            <div>
                <a href="{{ route('penggajian.bonus.periode', ['periode' => $targetPeriode, 'outlet' => $selectedOutlet]) }}"
                   style="font-size:11px;font-weight:600;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:4px;" class="hover:text-[#7A4517]">
                    <span>&larr;</span> Kembali ke Daftar Bonus Periode
                </a>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h1 style="font-size:18px;font-weight:800;color:#1e293b;margin:0;">
                        Edit Bonus &amp; Lembur Karyawan
                    </h1>
                    <span style="display:inline-flex;align-items:center;gap:5px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;">
                        <span>&#127978;</span> Outlet {{ $selectedOutlet }}
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;">
                        <span>&#128197;</span> {{ \App\Models\Penggajian::formatPeriode($targetPeriode) }}
                    </span>
                </div>
            </div>
        </div>

        @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:13px;color:#b91c1c;">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- INFO KARYAWAN CARD --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-section-icon" style="background:#eff6ff;">&#128100;</div>
                <span class="pg-section-title">Karyawan &amp; Tarif Master</span>
            </div>
            <div class="pg-section-body">
                <div class="pg-grid-2" style="align-items:center;">
                    <div>
                        <div style="font-size:15px;font-weight:800;color:#1e293b;">{{ $payroll->karyawan->nama_karyawan }}</div>
                        <div style="font-size:12px;color:#7A4517;font-weight:600;margin-top:2px;">{{ $payroll->karyawan->jabatan ?? '-' }} &middot; {{ $payroll->karyawan->departemen ?? '-' }}</div>
                        <div style="font-size:11px;color:#64748b;margin-top:4px;">Hari Kerja: <strong>{{ $payroll->hari_kerja }} Hari</strong></div>
                    </div>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:right;">
                        <div style="font-size:10px;text-transform:uppercase;color:#64748b;font-weight:700;">Tarif Harian Terhitung</div>
                        <div style="font-size:16px;font-weight:800;color:#059669;margin-top:2px;">Rp {{ number_format($tarifHarian, 0, ',', '.') }}/hari</div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('penggajian.bonus.update', $payroll->id) }}" method="POST" id="formBonus">
            @csrf
            @method('PUT')

            {{-- SECTION KOMPONEN BONUS --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#fefce8;">&#11088;</div>
                    <span class="pg-section-title">Komponen Variabel Bonus &amp; Lembur</span>
                </div>
                <div class="pg-section-body">
                    <div class="pg-grid-2">
                        <div class="pg-field">
                            <label class="pg-label">Jam Lembur <span style="font-size:10px;font-weight:400;text-transform:none;">(Rp 10.000 / jam)</span></label>
                            <input type="number" name="jam_lembur" id="inputJamLembur" min="0" step="0.5"
                                   value="{{ $payroll->jam_lembur ?? 0 }}"
                                   class="pg-input" oninput="hitungBonus()">
                            <div class="pg-sub-calc" id="subLembur">Upah: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Banyak Target <span style="font-size:10px;font-weight:400;text-transform:none;">(Target &times; Tarif Harian)</span></label>
                            <input type="number" name="banyak_target" id="inputBanyakTarget" min="0" step="1"
                                   value="{{ $payroll->banyak_target ?? 0 }}"
                                   class="pg-input" oninput="hitungBonus()">
                            <div class="pg-sub-calc" id="subTarget">Bonus: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Hadir Tanggal Merah <span style="font-size:10px;font-weight:400;text-transform:none;">(Merah &times; Tarif Harian)</span></label>
                            <input type="number" name="banyak_tanggal_merah" id="inputBanyakTanggalMerah" min="0" step="1"
                                   value="{{ $payroll->banyak_tanggal_merah ?? 0 }}"
                                   class="pg-input" oninput="hitungBonus()">
                            <div class="pg-sub-calc" id="subMerah">Bonus: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Birthday Service <span style="font-size:10px;font-weight:400;text-transform:none;">(&times; Rp 5.000)</span></label>
                            <input type="number" name="banyak_birthday_service" id="inputBanyakBirthday" min="0" step="1"
                                   value="{{ $payroll->banyak_birthday_service ?? 0 }}"
                                   class="pg-input" oninput="hitungBonus()">
                            <div class="pg-sub-calc" id="subBirthday">Bonus: Rp 0</div>
                        </div>
                        <div class="pg-field">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <label class="pg-label" style="color: #047857; margin-bottom: 0;">Pengembalian Deposit (Rp)</label>
                                @if(($saldoDeposit ?? 0) > 0)
                                <button type="button" onclick="document.getElementById('inputPengembalianDeposit').value = Math.round({{ $saldoDeposit }}).toLocaleString('id-ID'); hitungBonus();"
                                        style="font-size: 10px; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 4px; padding: 2px 7px; cursor: pointer;"
                                        title="Isi otomatis dengan total saldo deposit yang pernah dipotong">
                                    &#8629; Isi Saldo (Rp {{ number_format($saldoDeposit, 0, ',', '.') }})
                                </button>
                                @endif
                            </div>
                            <input type="text" name="pengembalian_deposit" id="inputPengembalianDeposit"
                                   value="{{ number_format($payroll->pengembalian_deposit ?? 0, 0, ',', '.') }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungBonus()"
                                   style="border-color: #a7f3d0; background: #f0fdf4;">
                        </div>
                    </div>
                    <div class="pg-field" style="margin-bottom:0;margin-top:4px;">
                        <label class="pg-label">Bonus Lain-lain (Rp)</label>
                        <input type="text" name="bonus_dll" id="inputBonusDll"
                               value="{{ number_format($payroll->bonus_dll ?? 0, 0, ',', '.') }}"
                               class="pg-input pg-input-rupiah input-rupiah" oninput="hitungBonus()">
                    </div>
                </div>
            </div>

            {{-- SUMMARY BONUS --}}
            <div class="pg-summary">
                <div class="pg-summary-grid">
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Upah Lembur</div>
                        <div class="pg-summary-value" id="sumUpahLembur">Rp 0</div>
                    </div>
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Bonus Kinerja &amp; Khusus</div>
                        <div class="pg-summary-value" id="sumBonusKinerja">Rp 0</div>
                    </div>
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Total Bonus &amp; Lembur</div>
                        <div class="pg-summary-value" style="color:#7A4517;" id="sumTotalBonus">Rp 0</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="pg-btn-save">
                &#10003; Simpan Data Bonus &amp; Lembur
            </button>
        </form>
    </div>

    @push('scripts')
    <script>
        const tarifHarian = {{ $tarifHarian }};

        function formatRupiah(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        function parseRupiahInput(elId) {
            let el = document.getElementById(elId);
            if (!el) return 0;
            let val = el.value.replace(/[^0-9]/g, '');
            return parseFloat(val) || 0;
        }

        function hitungBonus() {
            let jamLembur = parseFloat(document.getElementById('inputJamLembur').value) || 0;
            let upahLembur = jamLembur * 10000;
            document.getElementById('subLembur').innerText = 'Upah: ' + formatRupiah(upahLembur);

            let banyakTarget = parseInt(document.getElementById('inputBanyakTarget').value) || 0;
            let bonusTarget = banyakTarget * tarifHarian;
            document.getElementById('subTarget').innerText = 'Bonus: ' + formatRupiah(bonusTarget);

            let banyakMerah = parseInt(document.getElementById('inputBanyakTanggalMerah').value) || 0;
            let bonusMerah = banyakMerah * tarifHarian;
            document.getElementById('subMerah').innerText = 'Bonus: ' + formatRupiah(bonusMerah);

            let banyakBirthday = parseInt(document.getElementById('inputBanyakBirthday').value) || 0;
            let bonusBirthday = banyakBirthday * 5000;
            document.getElementById('subBirthday').innerText = 'Bonus: ' + formatRupiah(bonusBirthday);

            let pengembalianDeposit = parseRupiahInput('inputPengembalianDeposit');
            let bonusDll = parseRupiahInput('inputBonusDll');

            let bonusKinerja = bonusTarget + bonusMerah + bonusBirthday + pengembalianDeposit + bonusDll;
            let totalBonus = upahLembur + bonusKinerja;

            document.getElementById('sumUpahLembur').innerText = formatRupiah(upahLembur);
            document.getElementById('sumBonusKinerja').innerText = formatRupiah(bonusKinerja);
            document.getElementById('sumTotalBonus').innerText = formatRupiah(totalBonus);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const rupiahInputs = document.querySelectorAll('.input-rupiah');
            rupiahInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let raw = this.value.replace(/[^0-9]/g, '');
                    if (raw) {
                        this.value = Math.round(parseFloat(raw)).toLocaleString('id-ID');
                    } else {
                        this.value = '0';
                    }
                    hitungBonus();
                });
            });

            document.getElementById('formBonus').addEventListener('submit', function() {
                rupiahInputs.forEach(input => {
                    input.value = input.value.replace(/[^0-9]/g, '') || '0';
                });
            });

            hitungBonus();
        });
    </script>
    @endpush
</x-app-layout>
