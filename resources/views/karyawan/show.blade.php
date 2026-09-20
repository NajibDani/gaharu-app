<x-app-layout>
    <x-slot name="header">
        Informasi Karyawan
    </x-slot>

    <style>
        .karyawan-card-wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        .gaharu-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 4px 20px -2px rgba(122, 69, 23, 0.07), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .gaharu-header-banner {
            background: linear-gradient(135deg, #7A4517 0%, #a45e22 100%);
            padding: 28px 28px 24px;
            color: #ffffff;
            position: relative;
        }
        .gaharu-avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: #ffffff;
            color: #7A4517;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }
        .gaharu-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .gaharu-section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #7A4517;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .gaharu-info-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.15s ease;
        }
        .gaharu-info-tile:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }
        .gaharu-info-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .gaharu-info-val {
            font-size: 14px;
            color: #0f172a;
            font-weight: 700;
            word-break: break-word;
        }
        .salary-breakdown-card {
            border-radius: 14px;
            padding: 16px;
            border: 1.5px solid #e2e8f0;
            background: #ffffff;
        }
    </style>

    <div class="karyawan-card-wrap">
        {{-- TOP ACTION BUTTONS --}}
        <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
            <a href="{{ route('karyawan.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-xl border border-gray-200 hover:bg-gray-50 shadow-sm transition-all">
                &larr; Kembali ke Daftar Karyawan
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('karyawan.edit', $karyawan->id) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#7A4517] hover:bg-[#5a3416] text-white text-sm font-bold rounded-xl shadow-sm transition-all">
                    &#9999; Edit Data Karyawan
                </a>
            </div>
        </div>

        {{-- MAIN CARD --}}
        <div class="gaharu-card">
            {{-- BANNER & HEADER --}}
            <div class="gaharu-header-banner">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-2xl font-black tracking-tight text-white m-0">
                                {{ $karyawan->nama_karyawan }}
                            </h1>
                            <span class="gaharu-badge bg-amber-400 text-amber-950">
                                {{ $karyawan->outlet ?? 'Gaharu' }}
                            </span>
                        </div>
                        <div class="text-sm text-amber-100 font-medium mt-1 flex items-center gap-2 flex-wrap">
                            <span>{{ $karyawan->jabatan ?? 'Staf' }}</span>
                            @if($karyawan->departemen)
                                <span>&middot;</span>
                                <span>{{ $karyawan->departemen }}</span>
                            @endif
                            @if($karyawan->jenis_tenaga_kerja)
                                <span>&middot;</span>
                                <span class="text-amber-200">{{ $karyawan->jenis_tenaga_kerja }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-amber-200 uppercase tracking-wider font-semibold">Total Tarif Harian</div>
                        <div class="text-2xl font-black text-white mt-0.5">
                            Rp {{ number_format($karyawan->tarif_harian_total, 0, ',', '.') }}
                            <span class="text-xs font-normal text-amber-200">/hari</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BODY CONTENT --}}
            <div class="p-6 md:p-8 space-y-6">
                {{-- SECTION 1: DATA PERSONAL & KONTAK --}}
                <div>
                    <div class="gaharu-section-title">
                        <i class="bi bi-person-lines-fill"></i> Data Identitas &amp; Kontak Pribadi
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Nama Lengkap</div>
                            <div class="gaharu-info-val">{{ $karyawan->nama_karyawan }}</div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">NIK (No. KTP)</div>
                            <div class="gaharu-info-val font-mono">{{ $karyawan->nik ?: '-' }}</div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Tempat, Tanggal Lahir (TTL)</div>
                            <div class="gaharu-info-val">{{ $karyawan->ttl_formatted }}</div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">WhatsApp / Telepon</div>
                            <div class="gaharu-info-val">
                                @if($karyawan->whatsapp)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $karyawan->whatsapp) }}" target="_blank" class="text-emerald-700 hover:underline inline-flex items-center gap-1">
                                        <i class="bi bi-whatsapp"></i> {{ $karyawan->whatsapp }}
                                    </a>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Email</div>
                            <div class="gaharu-info-val">
                                @if($karyawan->email)
                                    <a href="mailto:{{ $karyawan->email }}" class="text-blue-600 hover:underline">
                                        {{ $karyawan->email }}
                                    </a>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Kontak Darurat</div>
                            <div class="gaharu-info-val">{{ $karyawan->nomor_darurat ?: '-' }}</div>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100">

                {{-- SECTION 2: POSISI & REKENING --}}
                <div>
                    <div class="gaharu-section-title">
                        <i class="bi bi-briefcase"></i> Penempatan Kerja &amp; Rekening
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Jabatan</div>
                            <div class="gaharu-info-val">{{ $karyawan->jabatan ?? '-' }}</div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Departemen / Divisi</div>
                            <div class="gaharu-info-val">{{ $karyawan->departemen ?? '-' }}</div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Outlet / Lokasi</div>
                            <div class="gaharu-info-val text-[#7A4517]">{{ $karyawan->outlet ?? 'Gaharu' }}</div>
                        </div>
                        <div class="gaharu-info-tile">
                            <div class="gaharu-info-label">Jenis Tenaga Kerja</div>
                            <div class="gaharu-info-val">{{ $karyawan->jenis_tenaga_kerja ?? 'Karyawan' }}</div>
                        </div>
                        <div class="gaharu-info-tile sm:col-span-2 md:col-span-4">
                            <div class="gaharu-info-label">Nomor Rekening / Pembayaran</div>
                            <div class="gaharu-info-val font-mono text-base">
                                {{ $karyawan->no_rekening ? $karyawan->no_rekening : 'Belum diatur' }}
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100">

                {{-- SECTION 2: STRUKTUR PENGGAJIAN --}}
                <div>
                    <div class="gaharu-section-title">
                        <i class="bi bi-cash-coin"></i> Struktur Komponen Gaji
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {{-- PERIODE 1 / REGULER --}}
                        <div class="salary-breakdown-card border-blue-100 bg-blue-50/20">
                            <div class="flex items-center justify-between pb-3 border-b border-blue-100 mb-3">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wide text-blue-700 bg-blue-100 px-2.5 py-1 rounded-full">
                                        Periode 1 / Utama
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 font-medium">
                                    @if($karyawan->tanggal_mulai || $karyawan->tanggal_selesai)
                                        {{ $karyawan->tanggal_mulai ? $karyawan->tanggal_mulai->format('d/m/Y') : 'Awal' }}
                                        &mdash;
                                        {{ $karyawan->tanggal_selesai ? $karyawan->tanggal_selesai->format('d/m/Y') : 'Seterusnya' }}
                                    @else
                                        Berlaku Standar
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Gaji Pokok Harian:</span>
                                    <span class="font-bold text-gray-900">Rp {{ number_format($karyawan->gaji_pokok ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Uang Makan / Hari:</span>
                                    <span class="font-bold text-gray-900">Rp {{ number_format($karyawan->uang_makan ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Uang Transport / Hari:</span>
                                    <span class="font-bold text-gray-900">Rp {{ number_format($karyawan->uang_transport ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-blue-200/60 font-extrabold text-[#7A4517]">
                                    <span>Total Tarif Harian:</span>
                                    <span class="text-base">Rp {{ number_format($karyawan->tarif_harian_total, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- PERIODE 2 / TRANSISI --}}
                        <div class="salary-breakdown-card border-amber-100 bg-amber-50/20">
                            <div class="flex items-center justify-between pb-3 border-b border-amber-100 mb-3">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wide text-amber-800 bg-amber-100 px-2.5 py-1 rounded-full">
                                        Periode 2 (Jika Ada)
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 font-medium">
                                    @if($karyawan->tanggal_mulai_2 || $karyawan->tanggal_selesai_2)
                                        {{ $karyawan->tanggal_mulai_2 ? $karyawan->tanggal_mulai_2->format('d/m/Y') : 'Awal' }}
                                        &mdash;
                                        {{ $karyawan->tanggal_selesai_2 ? $karyawan->tanggal_selesai_2->format('d/m/Y') : 'Seterusnya' }}
                                    @else
                                        Tidak Diatur
                                    @endif
                                </div>
                            </div>

                            @if(($karyawan->gaji_pok_2 ?? $karyawan->gaji_pokok_2) || $karyawan->tanggal_mulai_2)
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Gaji Pokok Harian (P2):</span>
                                    <span class="font-bold text-gray-900">Rp {{ number_format($karyawan->gaji_pokok_2 ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Uang Makan / Hari (P2):</span>
                                    <span class="font-bold text-gray-900">Rp {{ number_format($karyawan->uang_makan_2 ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-gray-600">Uang Transport / Hari (P2):</span>
                                    <span class="font-bold text-gray-900">Rp {{ number_format($karyawan->uang_transport_2 ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-amber-200/60 font-extrabold text-[#7A4517]">
                                    <span>Total Tarif Harian (P2):</span>
                                    <span class="text-base">Rp {{ number_format($karyawan->tarif_harian_total_2, 0, ',', '.') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-center py-6 text-gray-400 text-xs">
                                Tidak ada penyesuaian tarif periode kedua untuk karyawan ini.
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
