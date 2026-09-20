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
        .pg-input:focus { outline: none; border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.15); }
        .pg-input-rupiah { text-align: right; font-weight: 700; color: #0f172a; }
        .pg-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media(max-width:600px) { .pg-grid-2 { grid-template-columns: 1fr; } }
        .pg-summary {
            background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%);
            border: 1.5px solid #fca5a5;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .pg-summary-grid { display: grid; grid-template-columns: 1fr; gap: 0; }
        .pg-summary-item { text-align: center; padding: 8px 12px; }
        .pg-summary-label { font-size: 11px; color: #991b1b; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; }
        .pg-summary-value { font-size: 22px; font-weight: 800; margin-top: 4px; color: #dc2626; }
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
                <a href="{{ route('penggajian.potongan.periode', ['periode' => $targetPeriode, 'outlet' => $selectedOutlet]) }}"
                   style="font-size:11px;font-weight:600;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:4px;" class="hover:text-[#7A4517]">
                    <span>&larr;</span> Kembali ke Daftar Potongan Periode
                </a>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h1 style="font-size:18px;font-weight:800;color:#1e293b;margin:0;">
                        Edit Potongan &amp; Pengurangan Karyawan
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
                <span class="pg-section-title">Karyawan Terpilih</span>
            </div>
            <div class="pg-section-body">
                <div class="pg-grid-2" style="align-items:center;">
                    <div>
                        <div style="font-size:15px;font-weight:800;color:#1e293b;">{{ $payroll->karyawan->nama_karyawan }}</div>
                        <div style="font-size:12px;color:#7A4517;font-weight:600;margin-top:2px;">{{ $payroll->karyawan->jabatan ?? '-' }} &middot; {{ $payroll->karyawan->departemen ?? '-' }}</div>
                        <div style="font-size:11px;color:#64748b;margin-top:4px;">Hari Kerja: <strong>{{ $payroll->hari_kerja }} Hari</strong></div>
                    </div>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:right;">
                        <div style="font-size:10px;text-transform:uppercase;color:#64748b;font-weight:700;">Rekap Keterlambatan Bulan Ini</div>
                        <div style="font-size:14px;font-weight:800;color:#dc2626;margin-top:2px;">Rp {{ number_format($terlambatSum, 0, ',', '.') }}</div>
                        <a href="{{ route('keterlambatan.index', ['periode' => $targetPeriode]) }}" target="_blank"
                           style="font-size:10px;color:#3b82f6;text-decoration:none;font-weight:600;display:inline-block;margin-top:3px;">
                            Lihat Detail Absensi &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('penggajian.potongan.update', $payroll->id) }}" method="POST" id="formPotongan">
            @csrf
            @method('PUT')

            {{-- SECTION KOMPONEN POTONGAN --}}
            <div class="pg-section">
                <div class="pg-section-header">
                    <div class="pg-section-icon" style="background:#fff1f2;">&#9986;</div>
                    <span class="pg-section-title">Komponen Potongan &amp; Pengurangan</span>
                </div>
                <div class="pg-section-body">
                    <div class="pg-grid-2">
                        <div class="pg-field">
                            <label class="pg-label">
                                Denda Keterlambatan (Rp) <span style="font-size:10px; color:#64748b; font-weight:normal;">(Otomatis)</span>
                                <a href="{{ route('keterlambatan.index', ['periode' => $targetPeriode]) }}" target="_blank"
                                   style="font-size:10px;color:#3b82f6;font-weight:600;text-transform:none;text-decoration:none;margin-left:4px;">
                                    &#9889; Menu Keterlambatan &rarr;
                                </a>
                            </label>
                            <input type="text" name="potongan_terlambat" id="inputPotTerlambat" readonly
                                   value="{{ number_format($payroll->potongan_terlambat ?? 0, 0, ',', '.') }}"
                                   class="pg-input pg-input-rupiah input-rupiah" style="background:#f1f5f9; cursor:not-allowed;"
                                   title="Denda keterlambatan terisi otomatis dari menu Keterlambatan">
                            <div style="font-size:10px; color:#64748b; margin-top:2px;">
                                * Dihitung otomatis dari menu Rekap Keterlambatan
                            </div>
                        </div>
                        <div class="pg-field">
                            <label class="pg-label">Kerusakan Inventaris (Rp)</label>
                            <input type="text" name="potongan_inventaris" id="inputPotInventaris"
                                   value="{{ number_format($payroll->potongan_inventaris ?? 0, 0, ',', '.') }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungPotongan()">
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Potongan Kasbon (Rp)</label>
                            <input type="text" name="potongan_kasbon" id="inputPotKasbon"
                                   value="{{ number_format($payroll->potongan_kasbon ?? 0, 0, ',', '.') }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungPotongan()">
                        </div>
                        <div class="pg-field" style="margin-bottom:0;">
                            <label class="pg-label">Potongan Lain-lain (Rp)</label>
                            <input type="text" name="potongan_dll" id="inputPotDll"
                                   value="{{ number_format($payroll->potongan_dll ?? 0, 0, ',', '.') }}"
                                   class="pg-input pg-input-rupiah input-rupiah" oninput="hitungPotongan()">
                        </div>
                    </div>
                </div>
            </div>

            {{-- SUMMARY POTONGAN --}}
            <div class="pg-summary">
                <div class="pg-summary-grid">
                    <div class="pg-summary-item">
                        <div class="pg-summary-label">Total Potongan &amp; Pengurangan</div>
                        <div class="pg-summary-value" id="sumTotalPotongan">Rp 0</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="pg-btn-save">
                &#10003; Simpan Data Potongan
            </button>
        </form>
    </div>

    @push('scripts')
    <script>
        function formatRupiah(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        function parseRupiahInput(elId) {
            let el = document.getElementById(elId);
            if (!el) return 0;
            let val = el.value.replace(/[^0-9]/g, '');
            return parseFloat(val) || 0;
        }

        function hitungPotongan() {
            let potTerlambat  = parseRupiahInput('inputPotTerlambat');
            let potInventaris = parseRupiahInput('inputPotInventaris');
            let potKasbon     = parseRupiahInput('inputPotKasbon');
            let potDll        = parseRupiahInput('inputPotDll');

            let totalDeductions = potTerlambat + potInventaris + potKasbon + potDll;
            document.getElementById('sumTotalPotongan').innerText = '- ' + formatRupiah(totalDeductions);
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
                    hitungPotongan();
                });
            });

            document.getElementById('formPotongan').addEventListener('submit', function() {
                rupiahInputs.forEach(input => {
                    input.value = input.value.replace(/[^0-9]/g, '') || '0';
                });
            });

            hitungPotongan();
        });
    </script>
    @endpush
</x-app-layout>
