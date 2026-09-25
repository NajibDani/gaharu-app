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
                                <div class="flex justify-between items-center mb-1">
                                    <label class="block text-xs font-bold uppercase text-slate-800 mb-0">
                                        Jabatan / Posisi <span class="text-rose-600">*</span>
                                    </label>
                                    <button type="button" onclick="bukaModalKelolaJabatan()" class="text-xs font-bold text-[#7A4517] hover:underline inline-flex items-center gap-1 cursor-pointer" style="background: none; border: none; padding: 0;">
                                        <span>&#9881;</span> Atur Pilihan Jabatan
                                    </button>
                                </div>
                                <select name="jabatan" id="inputJabatan" onchange="onJabatanSelectChange(this)" class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white focus:border-[#7A4517] focus:ring focus:ring-[#7A4517]/20" required>
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach($jabatanList as $jbtn)
                                    <option value="{{ $jbtn }}" {{ old('jabatan') == $jbtn ? 'selected' : '' }}>
                                        {{ $jbtn }}
                                    </option>
                                    @endforeach
                                    <option value="__add_new__" class="text-[#7A4517] font-bold">+ Tambah / Edit Pilihan Jabatan...</option>
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

    {{-- MODAL KELOLA JABATAN --}}
    <div id="modalKelolaJabatan" style="display: none;">
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 100000;"
             onclick="tutupModalKelolaJabatan()"></div>

        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 100001; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
            <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 650px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden;">
                <div style="background: #7A4517; padding: 16px 20px; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="font-size: 16px; font-weight: 900; margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>&#9881;</span> Kelola Pilihan Jabatan / Posisi
                        </h3>
                        <p style="font-size: 11.5px; opacity: 0.85; margin: 2px 0 0 0;">
                            Tambah, edit nama posisi, atau hapus opsi jabatan pada dropdown form karyawan.
                        </p>
                    </div>
                    <button type="button" onclick="tutupModalKelolaJabatan()"
                            style="background: none; border: none; color: #ffffff; font-size: 24px; font-weight: 700; cursor: pointer; padding: 0 4px; line-height: 1;">
                        &times;
                    </button>
                </div>

                <div style="padding: 18px 20px; max-height: 75vh; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 14px;">
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #16a34a; font-size: 14px;">+</span> Tambah Pilihan Jabatan Baru
                        </div>
                        <form id="formTambahJabatan" onsubmit="handleTambahJabatan(event)" style="display: flex; gap: 8px;">
                            <input type="text" id="tambahNamaJabatan" class="w-full border-1.5 border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-900 bg-white" placeholder="Contoh: BARISTA / HEAD BARISTA..." style="flex: 1; text-transform: uppercase;" required>
                            <button type="submit" id="btnSubmitTambahJabatan"
                                    style="padding: 8px 16px; background-color: #16a34a; color: #ffffff; border-radius: 8px; font-size: 12px; font-weight: 800; border: none; cursor: pointer; white-space: nowrap; transition: background .15s;"
                                    onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                                + Tambah
                            </button>
                        </form>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column;">
                        <div style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #ffffff; flex-shrink: 0;">
                            <span style="font-size: 12px; font-weight: 800; color: #334155;">Daftar Pilihan Jabatan di Dropdown</span>
                            <span id="badgeTotalJabatan" style="font-size: 11px; font-weight: 800; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 20px; border: 1px solid #cbd5e1;">0 Jabatan</span>
                        </div>
                        <div style="overflow-x: auto; overflow-y: auto; max-height: 280px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                                <thead style="position: sticky; top: 0; z-index: 2;">
                                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 800;">
                                        <th style="padding: 8px 12px; text-align: center; width: 40px; background: #f8fafc;">#</th>
                                        <th style="padding: 8px 12px; text-align: left; background: #f8fafc;">Nama Jabatan / Posisi</th>
                                        <th style="padding: 8px 12px; text-align: center; width: 130px; background: #f8fafc;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDaftarJabatan">
                                    <tr>
                                        <td colspan="3" style="text-align: center; padding: 18px; color: #94a3b8;">Memuat data jabatan...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11.5px; color: #64748b; font-weight: 500;">
                        <span>&#8505;</span> Perubahan akan otomatis terpasang pada pilihan dropdown form.
                    </span>
                    <button type="button" onclick="tutupModalKelolaJabatan()"
                            style="padding: 7px 18px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let globalJabatanList = [];

        function loadMasterJabatan(selectedToSelect = null) {
            fetch("{{ route('karyawan.jabatan.index') }}")
                .then(res => res.json())
                .then(data => {
                    globalJabatanList = data;
                    renderJabatanDropdown(data, selectedToSelect);
                    renderJabatanTable(data);
                })
                .catch(err => {
                    console.error("Gagal memuat master jabatan:", err);
                });
        }

        function renderJabatanDropdown(jabatans, selectedVal = null) {
            const selectEl = document.getElementById('inputJabatan');
            if (!selectEl) return;

            const currentVal = selectedVal !== null ? selectedVal : selectEl.value;
            let html = '<option value="">-- Pilih Jabatan --</option>';

            jabatans.forEach(j => {
                const isSelected = (currentVal && currentVal.toUpperCase() === j.nama.toUpperCase()) ? 'selected' : '';
                html += `<option value="${escapeHtml(j.nama)}" ${isSelected}>${escapeHtml(j.nama)}</option>`;
            });

            if (currentVal && currentVal !== '__add_new__' && !jabatans.some(j => j.nama.toUpperCase() === currentVal.toUpperCase())) {
                html += `<option value="${escapeHtml(currentVal)}" selected>${escapeHtml(currentVal)}</option>`;
            }

            html += `<option value="__add_new__" class="text-[#7A4517] font-bold">+ Tambah / Edit Pilihan Jabatan...</option>`;
            selectEl.innerHTML = html;
        }

        function renderJabatanTable(jabatans) {
            const tbody = document.getElementById('tbodyDaftarJabatan');
            const badge = document.getElementById('badgeTotalJabatan');
            if (badge) badge.textContent = (jabatans ? jabatans.length : 0) + ' Jabatan';
            if (!tbody) return;

            if (!jabatans || jabatans.length === 0) {
                tbody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 18px; color: #94a3b8;">Belum ada data jabatan.</td></tr>`;
                return;
            }

            let html = '';
            jabatans.forEach((j, idx) => {
                html += `
                    <tr id="jabatan-row-${j.id}" style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 12px; text-align: center; color: #64748b; font-family: monospace; font-size: 11px;">${idx + 1}</td>
                        <td style="padding: 10px 12px;">
                            <span class="jabatan-view-nama" style="font-weight: 800; color: #0f172a;">${escapeHtml(j.nama)}</span>
                            <input type="text" class="w-full border-1.5 border-slate-300 rounded-xl px-2 py-1 text-xs font-semibold text-slate-900 bg-white jabatan-edit-nama" value="${escapeHtml(j.nama)}" style="display: none; text-transform: uppercase;">
                        </td>
                        <td style="padding: 10px 12px; text-align: center; white-space: nowrap;">
                            <div class="jabatan-view-actions" style="display: flex; justify-content: center; gap: 4px;">
                                <button type="button" onclick="mulaiEditJabatanRow(${j.id})"
                                        style="padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; cursor: pointer;"
                                        title="Edit Jabatan">
                                    &#9998; Edit
                                </button>
                                <button type="button" onclick="hapusJabatanItem(${j.id}, '${escapeHtml(j.nama)}')"
                                        style="padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; cursor: pointer;"
                                        title="Hapus Jabatan">
                                    &#128465; Hapus
                                </button>
                            </div>
                            <div class="jabatan-edit-actions" style="display: none; justify-content: center; gap: 4px;">
                                <button type="button" onclick="simpanEditJabatanRow(${j.id})"
                                        style="padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; cursor: pointer;"
                                        title="Simpan">
                                    &#10003; Simpan
                                </button>
                                <button type="button" onclick="batalEditJabatanRow(${j.id})"
                                        style="padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; cursor: pointer;"
                                        title="Batal">
                                    &times; Batal
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function onJabatanSelectChange(selectEl) {
            if (selectEl.value === '__add_new__') {
                selectEl.value = '';
                bukaModalKelolaJabatan();
            }
        }

        function bukaModalKelolaJabatan() {
            loadMasterJabatan();
            document.getElementById('modalKelolaJabatan').style.display = 'block';
        }

        function tutupModalKelolaJabatan() {
            document.getElementById('modalKelolaJabatan').style.display = 'none';
        }

        function handleTambahJabatan(e) {
            e.preventDefault();
            const input = document.getElementById('tambahNamaJabatan');
            const submitBtn = document.getElementById('btnSubmitTambahJabatan');
            const nama = input.value.trim();
            if (!nama) return;

            const origBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Menambah...';

            fetch("{{ route('karyawan.jabatan.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ nama: nama })
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = origBtnText;

                if (data.success) {
                    input.value = '';
                    globalJabatanList = data.jabatans;
                    renderJabatanTable(data.jabatans);
                    renderJabatanDropdown(data.jabatans, data.jabatan.nama);
                } else {
                    alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.textContent = origBtnText;
                alert('Kesalahan jaringan: ' + err.message);
            });
        }

        function mulaiEditJabatanRow(id) {
            const row = document.getElementById(`jabatan-row-${id}`);
            if (!row) return;

            row.querySelector('.jabatan-view-nama').style.display = 'none';
            row.querySelector('.jabatan-edit-nama').style.display = 'block';

            row.querySelector('.jabatan-view-actions').style.display = 'none';
            row.querySelector('.jabatan-edit-actions').style.display = 'flex';
        }

        function batalEditJabatanRow(id) {
            const row = document.getElementById(`jabatan-row-${id}`);
            if (!row) return;

            row.querySelector('.jabatan-view-nama').style.display = 'inline';
            row.querySelector('.jabatan-edit-nama').style.display = 'none';

            row.querySelector('.jabatan-view-actions').style.display = 'flex';
            row.querySelector('.jabatan-edit-actions').style.display = 'none';
        }

        function simpanEditJabatanRow(id) {
            const row = document.getElementById(`jabatan-row-${id}`);
            if (!row) return;

            const nama = row.querySelector('.jabatan-edit-nama').value.trim();
            if (!nama) {
                alert('Nama jabatan tidak boleh kosong.');
                return;
            }

            fetch(`/karyawan/jabatan/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ nama: nama })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    globalJabatanList = data.jabatans;
                    renderJabatanTable(data.jabatans);
                    renderJabatanDropdown(data.jabatans, data.jabatan.nama);
                } else {
                    alert('Gagal memperbarui jabatan: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(err => {
                alert('Kesalahan jaringan: ' + err.message);
            });
        }

        function hapusJabatanItem(id, nama) {
            if (!confirm(`Apakah Anda yakin ingin menghapus pilihan jabatan "${nama}" dari dropdown?`)) return;

            fetch(`/karyawan/jabatan/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    globalJabatanList = data.jabatans;
                    renderJabatanTable(data.jabatans);
                    renderJabatanDropdown(data.jabatans);
                } else {
                    alert('Gagal menghapus jabatan: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(err => {
                alert('Kesalahan jaringan: ' + err.message);
            });
        }
    </script>
</x-app-layout>