<x-app-layout>
    <x-slot name="header">
        Tambah Karyawan Baru
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
                @if ($errors->any())
                <div class="mb-6 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-xl text-rose-800 text-sm font-semibold">
                    <div class="font-bold mb-1">Perhatian:</div>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form action="{{ route('karyawan.store') }}" method="POST" class="space-y-6">
                    @csrf

                    {{-- SECTION 1: DATA IDENTITAS DIRI --}}
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-[#7A4517] mb-4 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <i class="bi bi-person-badge"></i> Data Identitas Karyawan
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">
                                    Nama Lengkap <span class="text-rose-600">*</span>
                                </label>
                                <input type="text" name="nama_karyawan" value="{{ old('nama_karyawan') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="Nama lengkap karyawan..." required>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">NIK (Nomor Induk Kependudukan)</label>
                                <input type="text" name="nik" value="{{ old('nik') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="16 digit NIK...">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="Contoh: Semarang">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       style="cursor: pointer;">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">WhatsApp / No. Telepon</label>
                                <input type="text" name="whatsapp" value="{{ old('whatsapp') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="0812xxxxxxxx">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Alamat Email</label>
                                <input type="email" name="email" value="{{ old('email') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="nama@email.com">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Nomor Kontak Darurat (Keluarga / Kerabat)</label>
                                <input type="text" name="nomor_darurat" value="{{ old('nomor_darurat') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="Contoh: 0813xxxxxxxx (Ibu / Saudara)">
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: POSISI, DEPARTEMEN & OUTLET --}}
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-[#7A4517] mb-4 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <i class="bi bi-briefcase"></i> Penempatan &amp; Posisi Kerja
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">
                                    Departemen <span class="text-rose-600">*</span>
                                </label>
                                <select name="departemen" class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20" required>
                                    <option value="">-- Pilih Departemen --</option>
                                    @foreach($departemenList as $dept)
                                    <option value="{{ $dept }}" {{ old('departemen') == $dept ? 'selected' : '' }}>
                                        {{ $dept }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">
                                    Jabatan / Posisi <span class="text-rose-600">*</span>
                                </label>
                                <select name="jabatan" class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20" required>
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach($jabatanList as $jbtn)
                                    <option value="{{ $jbtn }}" {{ old('jabatan') == $jbtn ? 'selected' : '' }}>
                                        {{ $jbtn }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">
                                    Jenis Tenaga Kerja <span class="text-rose-600">*</span>
                                </label>
                                <select name="jenis_tenaga_kerja" class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20" required>
                                    <option value="Karyawan Tetap" {{ old('jenis_tenaga_kerja') == 'Karyawan Tetap' ? 'selected' : '' }}>Karyawan Tetap</option>
                                    <option value="Karyawan Kontrak" {{ old('jenis_tenaga_kerja') == 'Karyawan Kontrak' ? 'selected' : '' }}>Karyawan Kontrak</option>
                                    <option value="Part Time" {{ old('jenis_tenaga_kerja') == 'Part Time' ? 'selected' : '' }}>Part Time</option>
                                    <option value="Casual" {{ old('jenis_tenaga_kerja') == 'Casual' ? 'selected' : '' }}>Casual</option>
                                    <option value="Probation" {{ old('jenis_tenaga_kerja') == 'Probation' ? 'selected' : '' }}>Probation</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">
                                    Outlet Penempatan <span class="text-rose-600">*</span>
                                </label>
                                <select name="outlet" class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-extrabold text-amber-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20" required>
                                    <option value="Gaharu" {{ old('outlet', request('outlet')) == 'Gaharu' ? 'selected' : '' }}>Outlet Gaharu</option>
                                    <option value="Kejingga" {{ old('outlet', request('outlet')) == 'Kejingga' ? 'selected' : '' }}>Outlet Kejingga</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 3: REKENING & PENGGAJIAN --}}
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-[#7A4517] mb-4 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <i class="bi bi-wallet2"></i> Data Pembayaran &amp; Gaji
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Nomor Rekening Bank</label>
                                <input type="text" name="no_rekening" value="{{ old('no_rekening') }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-mono font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20"
                                       placeholder="Contoh: BCA 1234567890 an. Budi">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Gaji Pokok Harian</label>
                                <input type="number" name="gaji_pokok" value="{{ old('gaji_pokok', 0) }}"
                                       class="w-full bg-slate-100 border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-bold text-slate-900"
                                       min="0" required>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Uang Makan / Hari</label>
                                <input type="number" name="uang_makan" value="{{ old('uang_makan', 0) }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-bold text-slate-900 bg-white"
                                       min="0">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-800 mb-1">Uang Transport / Hari</label>
                                <input type="number" name="uang_transport" value="{{ old('uang_transport', 0) }}"
                                       class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-bold text-slate-900 bg-white"
                                       min="0">
                            </div>
                        </div>
                    </div>

                    {{-- SUBMIT BUTTONS --}}
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                        <a href="{{ route('karyawan.index', ['outlet' => request('outlet', 'Gaharu')]) }}"
                           style="padding: 10px 20px; border-radius: 10px; border: 1.5px solid #cbd5e1; color: #334155; background: #ffffff; font-weight: 700; font-size: 13px; text-decoration: none; transition: background .15s;"
                           onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                            Batal
                        </a>
                        <button type="submit"
                                style="padding: 10px 24px; border-radius: 10px; background-color: #7A4517; color: #ffffff; font-weight: 800; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(122,69,23,0.3); transition: background .15s;"
                                onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                            Simpan Data Karyawan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>