<x-app-layout>
    <x-slot name="header">
        Master Karyawan
    </x-slot>

    <style>
        .table-karyawan th {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            font-weight: 800;
        }
        .table-karyawan td {
            font-size: 13px;
            vertical-align: middle;
        }
        .btn-gaharu-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            transition: all 0.15s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .filter-select {
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            background-color: #ffffff;
            outline: none;
            transition: border-color .15s;
        }
        .filter-select:focus {
            border-color: #7A4517;
            box-shadow: 0 0 0 2px rgba(122, 69, 23, 0.15);
        }
        .modal-input {
            width: 100%;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            transition: border-color .15s, box-shadow .15s;
        }
        .modal-input:focus {
            outline: none;
            border-color: #7A4517;
            box-shadow: 0 0 0 3px rgba(122, 69, 23, 0.15);
        }
        .modal-label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .modal-label .req {
            color: #dc2626;
            margin-left: 2px;
        }
        .sortable-ghost {
            opacity: 0.35 !important;
            background-color: #fef3c7 !important;
        }
        .sortable-chosen {
            background-color: #fffbeb !important;
        }
        .sortable-drag {
            background-color: #ffffff !important;
            box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.18), 0 6px 12px -4px rgba(0, 0, 0, 0.1) !important;
        }
        .drag-handle {
            touch-action: none;
            user-select: none;
        }
    </style>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-3">
            <x-outlet-selector :selectedOutlet="$selectedOutlet" />

            @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 shadow-sm">
                <span class="text-emerald-600 text-sm">&#10003;</span> {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 shadow-sm">
                <span class="text-rose-600 text-sm">&#9888;</span> {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
            <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
                {{-- TOP BAR: ADD BUTTON & FILTERS/SEARCH/SORT --}}
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 mb-4">
                    <button type="button" onclick="openCreateKaryawanModal()"
                            style="background-color: #7A4517; color: #ffffff; padding: 7px 16px; border-radius: 9px; font-weight: 800; font-size: 12.5px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 5px rgba(122,69,23,0.3); transition: background .15s; shrink-0;"
                            onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                        <span style="font-size: 15px; line-height: 1;">+</span> Tambah Karyawan ({{ $selectedOutlet }})
                    </button>

                    {{-- FILTER FORM: SORT, DEPARTEMEN, JABATAN, SEARCH --}}
                    <form action="{{ route('karyawan.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">

                        {{-- SORT A-Z / Z-A --}}
                        <select name="sort" class="filter-select" onchange="this.form.submit()">
                            <option value="">Sort: Standar</option>
                            <option value="asc" {{ request('sort') == 'asc' ? 'selected' : '' }}>Sort: Nama (A &rarr; Z)</option>
                            <option value="desc" {{ request('sort') == 'desc' ? 'selected' : '' }}>Sort: Nama (Z &rarr; A)</option>
                        </select>

                        {{-- FILTER DEPARTEMEN --}}
                        <select name="departemen" class="filter-select" onchange="this.form.submit()">
                            <option value="">Semua Departemen</option>
                            @foreach($departemenList as $dept)
                            <option value="{{ $dept }}" {{ request('departemen') == $dept ? 'selected' : '' }}>
                                {{ $dept }}
                            </option>
                            @endforeach
                        </select>

                        {{-- FILTER JABATAN --}}
                        <select name="jabatan" class="filter-select" onchange="this.form.submit()">
                            <option value="">Semua Jabatan</option>
                            @foreach($jabatanList as $jbtn)
                            <option value="{{ $jbtn }}" {{ request('jabatan') == $jbtn ? 'selected' : '' }}>
                                {{ $jbtn }}
                            </option>
                            @endforeach
                        </select>

                        {{-- SEARCH INPUT --}}
                        <div class="relative flex items-center">
                            <input type="text" name="search"
                                   class="filter-select pr-8"
                                   placeholder="Cari nama, NIK, telp..."
                                   value="{{ request('search') }}" style="min-width: 170px;">
                        </div>

                        <button type="submit"
                                style="background-color: #0f172a; color: #ffffff; padding: 7px 14px; border-radius: 9px; font-size: 12.5px; font-weight: 700; border: none; cursor: pointer; transition: background .15s;"
                                onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#0f172a'">
                            Cari
                        </button>

                        @if(request('search') || request('departemen') || request('jabatan') || request('sort'))
                            <a href="{{ route('karyawan.index', ['outlet' => $selectedOutlet]) }}"
                               style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 7px 12px; border-radius: 9px; font-size: 12.5px; font-weight: 700; text-decoration: none;"
                               title="Bersihkan Filter">
                                Reset
                            </a>
                        @endif
                    </form>
                </div>

                {{-- ACTIVE FILTER BADGES SUMMARY --}}
                @if(request('departemen') || request('jabatan') || request('sort') || request('search'))
                <div class="flex items-center gap-2 mb-3 flex-wrap text-xs font-semibold">
                    <span class="text-slate-600">Filter aktif:</span>
                    @if(request('departemen'))
                        <span class="bg-amber-100 text-amber-900 border border-amber-300 px-2 py-0.5 rounded-lg">
                            Dept: {{ request('departemen') }}
                        </span>
                    @endif
                    @if(request('jabatan'))
                        <span class="bg-blue-100 text-blue-900 border border-blue-300 px-2 py-0.5 rounded-lg">
                            Jabatan: {{ request('jabatan') }}
                        </span>
                    @endif
                    @if(request('sort'))
                        <span class="bg-indigo-100 text-indigo-900 border border-indigo-300 px-2 py-0.5 rounded-lg">
                            Urutan: {{ strtoupper(request('sort')) }}
                        </span>
                    @endif
                    @if(request('search'))
                        <span class="bg-slate-100 text-slate-900 border border-slate-300 px-2 py-0.5 rounded-lg">
                            Kata kunci: "{{ request('search') }}"
                        </span>
                    @endif
                </div>
                @endif

                {{-- TABLE DATA KARYAWAN --}}
                <div class="overflow-x-auto rounded-xl border border-slate-200" style="overflow-y: visible;">
                    <table class="w-full text-left table-karyawan">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200">
                                <th class="py-2.5 px-3 text-center w-12 text-slate-600 font-bold" title="Tahan dan geser (drag & drop) untuk mengatur urutan baris">#</th>
                                <th class="py-2.5 px-3 min-w-[240px]">
                                    <a href="{{ route('karyawan.index', array_merge(request()->query(), ['sort' => request('sort') === 'asc' ? 'desc' : 'asc'])) }}"
                                       class="inline-flex items-center gap-1.5 text-slate-800 hover:text-[#7A4517] transition-colors"
                                       title="Klik untuk ubah urutan nama">
                                        <span>Nama Karyawan</span>
                                        @if(request('sort') === 'asc')
                                             <span class="text-emerald-700 font-black">&uarr;</span>
                                        @elseif(request('sort') === 'desc')
                                             <span class="text-emerald-700 font-black">&darr;</span>
                                        @else
                                             <span class="text-slate-400 font-normal">&udarr;</span>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-2.5 px-3">Posisi / Jabatan</th>
                                <th class="py-2.5 px-3">Departemen</th>
                                <th class="py-2.5 px-3 text-center">Outlet</th>
                                <th class="py-2.5 px-3 text-right">Gaji Pokok</th>
                                <th class="py-2.5 px-3 text-right">Tarif Harian</th>
                                <th class="py-2.5 px-3 text-center min-w-[210px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="karyawanTableBody" class="divide-y divide-slate-100 bg-white">
                            @forelse($karyawans as $k)
                            <tr class="karyawan-row hover:bg-slate-50/80 transition-colors" data-id="{{ $k->id }}">
                                <td class="py-2 px-3 text-center drag-handle cursor-grab active:cursor-grabbing text-slate-400 hover:text-[#7A4517]" title="Tahan dan geser (drag & drop) untuk mengatur posisi baris">
                                    <div class="flex items-center justify-center gap-1">
                                        <svg class="w-4 h-4 text-slate-400 hover:text-[#7A4517] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8h16M4 16h16"></path>
                                        </svg>
                                        <span class="row-index text-xs font-black text-slate-600">{{ $loop->iteration }}</span>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 min-w-[240px]">
                                    <div>
                                        <div class="font-extrabold text-slate-900 text-sm leading-tight">{{ $k->nama_karyawan }}</div>
                                        <div class="text-xs text-slate-600 mt-0.5 flex items-center gap-1.5 flex-wrap font-medium">
                                            @if($k->nik)
                                                <span>NIK: <strong class="text-slate-800 font-mono font-bold">{{ $k->nik }}</strong></span>
                                            @endif
                                            @if($k->whatsapp)
                                                <span class="text-emerald-800 font-semibold">&middot; WA: {{ $k->whatsapp }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-800 text-xs">{{ $k->jabatan ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-600 font-medium mt-0.5">{{ $k->jenis_tenaga_kerja ?? 'Karyawan' }}</div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="text-xs font-bold text-slate-800 bg-slate-100 border border-slate-200 px-2.5 py-0.5 rounded-md inline-block">
                                        {{ $k->departemen ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="px-2.5 py-0.5 bg-amber-100 text-amber-900 border border-amber-300 font-extrabold rounded-full text-xs">
                                        {{ $k->outlet ?? 'Gaharu' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right text-slate-800 font-bold">
                                    Rp {{ number_format($k->gaji_pokok, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <div class="font-black text-[#7A4517] text-sm">
                                        Rp {{ number_format($k->tarif_harian_total, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[10px] font-bold mt-0.5">
                                        @if(($k->satuan_gaji ?? 'Harian') === 'Bulanan')
                                            <span class="bg-indigo-100 text-indigo-800 border border-indigo-200 px-1.5 py-0.5 rounded">/bulan</span>
                                        @elseif(($k->satuan_gaji ?? 'Harian') === 'Per Jam')
                                            <span class="bg-cyan-100 text-cyan-800 border border-cyan-200 px-1.5 py-0.5 rounded">/jam</span>
                                        @else
                                            <span class="text-slate-500 font-medium">/hari</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-nowrap">
                                        <button type="button"
                                                onclick="openDetailModal({{ json_encode([
                                                     'id' => $k->id,
                                                     'nama' => $k->nama_karyawan,
                                                     'nik' => $k->nik ?: '-',
                                                     'tempat_lahir' => $k->tempat_lahir ?: '-',
                                                     'tanggal_lahir' => $k->tanggal_lahir ? $k->tanggal_lahir->translatedFormat('d F Y') : '-',
                                                     'ttl' => $k->ttl_formatted,
                                                     'whatsapp' => $k->whatsapp ?: '-',
                                                     'email' => $k->email ?: '-',
                                                     'nomor_darurat' => $k->nomor_darurat ?: '-',
                                                     'jabatan' => $k->jabatan,
                                                     'departemen' => $k->departemen,
                                                     'jenis_tenaga_kerja' => $k->jenis_tenaga_kerja,
                                                     'outlet' => $k->outlet,
                                                     'satuan_gaji' => $k->satuan_gaji ?? 'Harian',
                                                     'no_rekening' => $k->no_rekening,
                                                     'gaji_pokok' => number_format($k->gaji_pokok, 0, ',', '.'),
                                                     'uang_makan' => number_format($k->uang_makan, 0, ',', '.'),
                                                     'uang_transport' => number_format($k->uang_transport, 0, ',', '.'),
                                                     'tarif_total' => number_format($k->tarif_harian_total, 0, ',', '.'),
                                                     'p1_mulai' => $k->tanggal_mulai ? $k->tanggal_mulai->format('d/m/Y') : '-',
                                                     'p1_selesai' => $k->tanggal_selesai ? $k->tanggal_selesai->format('d/m/Y') : '-',
                                                     'has_p2' => ($k->gaji_pokok_2 || $k->tanggal_mulai_2) ? true : false,
                                                     'gaji_pokok_2' => number_format($k->gaji_pokok_2 ?? 0, 0, ',', '.'),
                                                     'uang_makan_2' => number_format($k->uang_makan_2 ?? 0, 0, ',', '.'),
                                                     'uang_transport_2' => number_format($k->uang_transport_2 ?? 0, 0, ',', '.'),
                                                     'tarif_total_2' => number_format($k->tarif_harian_total_2 ?? 0, 0, ',', '.'),
                                                     'p2_mulai' => $k->tanggal_mulai_2 ? $k->tanggal_mulai_2->format('d/m/Y') : '-',
                                                     'p2_selesai' => $k->tanggal_selesai_2 ? $k->tanggal_selesai_2->format('d/m/Y') : '-',
                                                     'edit_url' => route('karyawan.edit', $k->id),
                                                     'show_url' => route('karyawan.show', $k->id),
                                                 ]) }})"
                                                style="background-color: #fffbf5; border: 1.5px solid #fcd34d; color: #78350f; font-weight: 800; font-size: 11.5px; padding: 5px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; transition: all .15s;"
                                                onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fffbf5'"
                                                title="Lihat Detil Profil Lengkap">
                                            Detil
                                        </button>

                                        <button type="button"
                                                onclick="openEditKaryawanModal({{ json_encode([
                                                     'id' => $k->id,
                                                     'nama_karyawan' => $k->nama_karyawan,
                                                     'nik' => $k->nik,
                                                     'tempat_lahir' => $k->tempat_lahir,
                                                     'tanggal_lahir' => $k->tanggal_lahir ? $k->tanggal_lahir->format('Y-m-d') : '',
                                                     'ttl' => $k->ttl,
                                                     'whatsapp' => $k->whatsapp,
                                                     'email' => $k->email,
                                                     'nomor_darurat' => $k->nomor_darurat,
                                                     'departemen' => $k->departemen,
                                                     'jabatan' => $k->jabatan,
                                                     'jenis_tenaga_kerja' => $k->jenis_tenaga_kerja,
                                                     'outlet' => $k->outlet,
                                                     'satuan_gaji' => $k->satuan_gaji ?? 'Harian',
                                                     'no_rekening' => $k->no_rekening,
                                                     'gaji_pokok' => $k->gaji_pokok,
                                                     'uang_makan' => $k->uang_makan,
                                                     'uang_transport' => $k->uang_transport,
                                                 ]) }})"
                                                style="background-color: #eff6ff; border: 1.5px solid #93c5fd; color: #1e40af; font-weight: 800; font-size: 11.5px; padding: 5px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; transition: all .15s;"
                                                onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'"
                                                title="Edit Data Karyawan via Pop-up">
                                            Edit
                                        </button>

                                        <form action="{{ route('karyawan.destroy', $k->id) }}" method="POST" class="inline m-0 p-0"
                                              onsubmit="return confirm('Hapus data karyawan {{ $k->nama_karyawan }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    style="background-color: #fef2f2; border: 1.5px solid #fca5a5; color: #991b1b; font-weight: 800; font-size: 11.5px; padding: 5px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; transition: all .15s;"
                                                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'"
                                                    title="Hapus Karyawan">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-500 font-medium">
                                    <div class="text-3xl mb-2">&#128100;</div>
                                    <div class="font-bold text-slate-700 text-sm">Data karyawan tidak ditemukan.</div>
                                    <div class="text-xs text-slate-500 mt-1">Coba sesuaikan filter atau tambahkan data karyawan baru.</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div class="mt-5">
                    {{ $karyawans->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- POPUP MODAL 1: FORM INPUT & EDIT DATA KARYAWAN --}}
    {{-- ========================================================================= --}}
    <div id="modalFormKaryawan" style="display: none;">
        {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
             onclick="closeFormKaryawanModal()"></div>

        {{-- MODAL CONTAINER CENTERED --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
            <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 680px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden;">
                
                {{-- MODAL HEADER --}}
                <div style="background: #f8fafc; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                    <div>
                        <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;" id="karyawanModalTitle">
                            Tambah Karyawan Baru
                        </h3>
                        <p style="font-size: 11.5px; color: #475569; font-weight: 600; margin: 2px 0 0 0;">
                            Lengkapi profil data identitas, posisi kerja, dan nominal tarif harian.
                        </p>
                    </div>
                    <button type="button" onclick="closeFormKaryawanModal()"
                            style="background: none; border: none; color: #475569; font-size: 24px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0 4px;"
                            onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#475569'">
                        &times;
                    </button>
                </div>

                {{-- MODAL BODY (Scrollable) --}}
                <div style="padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
                    <form action="{{ route('karyawan.store') }}" method="POST" id="formKaryawanModal">
                        @csrf
                        <input type="hidden" name="_method" id="karyawanFormMethod" value="POST">

                        {{-- SECTION 1: DATA IDENTITAS DIRI --}}
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff; margin-bottom: 14px;">
                            <div style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                                <span>&#128100;</span> Data Identitas Karyawan
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div style="grid-column: span 2;">
                                    <label class="modal-label">Nama Lengkap <span class="req">*</span></label>
                                    <input type="text" name="nama_karyawan" id="inputNamaKaryawan" class="modal-input" placeholder="Nama lengkap karyawan..." required>
                                </div>
                                <div style="grid-column: span 2;">
                                    <label class="modal-label">NIK (Nomor Induk Kependudukan)</label>
                                    <input type="text" name="nik" id="inputNik" class="modal-input" placeholder="16 digit NIK...">
                                </div>
                                <div>
                                    <label class="modal-label">Tempat Lahir</label>
                                    <input type="text" name="tempat_lahir" id="inputTempatLahir" class="modal-input" placeholder="Contoh: Semarang">
                                </div>
                                <div>
                                    <label class="modal-label">Tanggal Lahir</label>
                                    <input type="date" name="tanggal_lahir" id="inputTanggalLahir" class="modal-input" style="cursor: pointer;">
                                </div>
                                <div>
                                    <label class="modal-label">WhatsApp / No. Telepon</label>
                                    <input type="text" name="whatsapp" id="inputWhatsapp" class="modal-input" placeholder="0812xxxxxxxx">
                                </div>
                                <div>
                                    <label class="modal-label">Alamat Email</label>
                                    <input type="email" name="email" id="inputEmail" class="modal-input" placeholder="nama@email.com">
                                </div>
                                <div style="grid-column: span 2;">
                                    <label class="modal-label">Nomor Kontak Darurat (Keluarga / Kerabat)</label>
                                    <input type="text" name="nomor_darurat" id="inputNomorDarurat" class="modal-input" placeholder="Contoh: 0813xxxxxxxx (Ibu / Saudara)">
                                </div>
                            </div>
                        </div>

                        {{-- SECTION 2: POSISI, DEPARTEMEN & OUTLET --}}
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff; margin-bottom: 14px;">
                            <div style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                                <span>&#128188;</span> Penempatan &amp; Posisi Kerja
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label class="modal-label">Departemen <span class="req">*</span></label>
                                    <select name="departemen" id="inputDepartemen" class="modal-input" required>
                                        <option value="">-- Pilih Departemen --</option>
                                        @foreach($departemenList as $dept)
                                        <option value="{{ $dept }}">{{ $dept }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="modal-label">Jabatan / Posisi <span class="req">*</span></label>
                                    <select name="jabatan" id="inputJabatan" class="modal-input" required>
                                        <option value="">-- Pilih Jabatan --</option>
                                        @foreach($jabatanList as $jbtn)
                                        <option value="{{ $jbtn }}">{{ $jbtn }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="modal-label">Jenis Tenaga Kerja <span class="req">*</span></label>
                                    <select name="jenis_tenaga_kerja" id="inputJenisTenagaKerja" class="modal-input" required>
                                        <option value="Karyawan Tetap">Karyawan Tetap</option>
                                        <option value="Karyawan Kontrak">Karyawan Kontrak</option>
                                        <option value="Part Time">Part Time</option>
                                        <option value="Casual">Casual</option>
                                        <option value="Probation">Probation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="modal-label">Outlet Penempatan <span class="req">*</span></label>
                                    <select name="outlet" id="inputOutlet" class="modal-input" required>
                                        <option value="Gaharu" {{ $selectedOutlet == 'Gaharu' ? 'selected' : '' }}>Outlet Gaharu</option>
                                        <option value="Kejingga" {{ $selectedOutlet == 'Kejingga' ? 'selected' : '' }}>Outlet Kejingga</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- SECTION 3: REKENING & PENGATURAN GAJI --}}
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #ffffff;">
                            <div style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
                                <span>&#128179;</span> Data Rekening Pembayaran
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                                <div>
                                    <label class="modal-label">Nomor Rekening Bank &amp; Atas Nama</label>
                                    <input type="text" name="no_rekening" id="inputNoRekening" class="modal-input font-mono" placeholder="Contoh: BCA 1234567890 an. Budi">
                                </div>
                                <div style="background: #fffbf5; border: 1.5px dashed #fcd34d; border-radius: 10px; padding: 12px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px;">
                                    <div>
                                        <div style="font-size: 12px; font-weight: 800; color: #78350f;">&#128176; Pengaturan Komponen Gaji Pokok &amp; Tarif</div>
                                        <div style="font-size: 11px; color: #92400e; font-weight: 500; margin-top: 2px;">
                                            Komponen gaji pokok, uang makan, uang transport, dan satuan gaji per periode dikelola terpusat di menu <strong>Pengaturan Gaji</strong>.
                                        </div>
                                    </div>
                                    <div>
                                        <a href="{{ route('pengaturan-gaji.index', ['outlet' => $selectedOutlet]) }}"
                                           style="background-color: #7A4517; color: #ffffff; padding: 6px 14px; border-radius: 8px; font-size: 11.5px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(122,69,23,0.3); transition: background .15s;"
                                           onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                                            <span>&#9881;</span> Buka Menu Pengaturan Gaji &rarr;
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- MODAL FOOTER --}}
                <div style="background: #f8fafc; padding: 14px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-shrink: 0;">
                    <button type="button" onclick="closeFormKaryawanModal()"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                        <span>&times;</span> Batal
                    </button>
                    <button type="submit" form="formKaryawanModal"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border-radius: 8px; font-size: 12px; font-weight: 800; color: #ffffff; background: #7A4517; border: none; cursor: pointer; transition: all .15s; box-shadow: 0 2px 4px rgba(122,69,23,0.25);"
                            onmouseover="this.style.background='#5a3416'" onmouseout="this.style.background='#7A4517'">
                        <span style="font-size: 14px;">&#10003;</span> <span id="btnSubmitKaryawanLabel">Simpan Karyawan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- POPUP MODAL 2: DETAIL PROFIL KARYAWAN --}}
    {{-- ========================================================================= --}}
    <div id="modalDetailKaryawan" style="display: none;">
        {{-- OVERLAY LATAR BELAKANG GELAP (Opacity 50% + Blur) --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 99998;"
             onclick="closeDetailModal()"></div>

        {{-- MODAL CONTAINER CENTERED --}}
        <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: none;">
            <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 620px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #cbd5e1; pointer-events: auto; overflow: hidden;">
                
                {{-- MODAL HEADER --}}
                <div style="background: #f8fafc; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h3 style="font-size: 16px; font-weight: 900; color: #0f172a; margin: 0;" id="mNama">Nama Karyawan</h3>
                            <span style="font-size: 11px; font-weight: 800; background: #fef3c7; color: #78350f; border: 1px solid #fcd34d; border-radius: 20px; padding: 2px 8px;" id="mOutlet">Gaharu</span>
                        </div>
                        <p style="font-size: 11.5px; color: #475569; font-weight: 600; margin: 2px 0 0 0;" id="mSubtitle">Jabatan &middot; Departemen</p>
                    </div>
                    <button type="button" onclick="closeDetailModal()"
                            style="background: none; border: none; color: #475569; font-size: 24px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0 4px;"
                            onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#475569'">
                        &times;
                    </button>
                </div>

                {{-- MODAL BODY --}}
                <div style="padding: 20px; max-height: 75vh; overflow-y: auto; display: flex; flex-direction: column; gap: 14px;">
                    {{-- DATA IDENTITAS PRIBADI --}}
                    <div>
                        <div style="font-size: 11px; font-weight: 800; color: #78350f; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px;">
                            Data Identitas &amp; Kontak Pribadi
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; grid-column: span 2;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">NIK (No. KTP)</div>
                                <div style="font-size: 13px; font-weight: 800; font-family: monospace; color: #0f172a; margin-top: 2px;" id="mNik">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Tempat Lahir</div>
                                <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mTempatLahir">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Tanggal Lahir</div>
                                <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mTanggalLahir">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">WhatsApp / Telp</div>
                                <div style="font-size: 13px; font-weight: 800; color: #047857; margin-top: 2px;" id="mWhatsapp">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Email</div>
                                <div style="font-size: 13px; font-weight: 800; color: #1d4ed8; margin-top: 2px;" id="mEmail">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; grid-column: span 2;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Kontak Darurat</div>
                                <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mNomorDarurat">-</div>
                            </div>
                        </div>
                    </div>

                    {{-- DATA PENEMPATAN & REKENING --}}
                    <div>
                        <div style="font-size: 11px; font-weight: 800; color: #78350f; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px;">
                            Penempatan Kerja &amp; Rekening
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Jabatan</div>
                                <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mJabatan">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Departemen</div>
                                <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mDepartemen">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Jenis Kerja</div>
                                <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mJenisKerja">-</div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; grid-column: span 3;">
                                <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Nomor Rekening Bank</div>
                                <div style="font-size: 13px; font-weight: 800; font-family: monospace; color: #0f172a; margin-top: 2px;" id="mRekening">-</div>
                            </div>
                        </div>
                    </div>

                    {{-- STRUKTUR GAJI MASTER --}}
                    <div>
                        <div style="font-size: 11px; font-weight: 800; color: #78350f; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <span>Komponen Gaji Master</span>
                            <span id="mBadgeSatuanGaji" style="font-size: 10px; font-weight: 800; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; border-radius: 6px; padding: 1px 6px;">Harian</span>
                        </div>
                        <div style="background: linear-gradient(135deg, #fffbf5 0%, #fef3c7 100%); border: 1.5px solid #fcd34d; padding: 12px 16px; border-radius: 12px; display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; text-align: right;">
                            <div>
                                <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase;" id="mLabelGajiPokok">Gaji Pokok</div>
                                <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mGajiPokok">Rp 0</div>
                            </div>
                            <div>
                                <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase;" id="mLabelUangMakan">Uang Makan</div>
                                <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mUangMakan">Rp 0</div>
                            </div>
                            <div>
                                <div style="font-size: 10px; font-weight: 700; color: #78350f; text-transform: uppercase;" id="mLabelUangTransport">Transport</div>
                                <div style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="mUangTransport">Rp 0</div>
                            </div>
                            <div>
                                <div style="font-size: 10px; font-weight: 800; color: #065f46; text-transform: uppercase;" id="mLabelTarifTotal">Total / Hari</div>
                                <div style="font-size: 14px; font-weight: 900; color: #047857; margin-top: 2px;" id="mTarifTotal">Rp 0</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- MODAL FOOTER --}}
                <div style="background: #f8fafc; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <a href="{{ route('pengaturan-gaji.index', ['outlet' => $selectedOutlet]) }}"
                       style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: 11.5px; font-weight: 800; color: #78350f; background: #fffbf5; border: 1.5px solid #fcd34d; text-decoration: none; transition: all .15s;"
                       onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fffbf5'">
                        <span>&#9881;</span> Kelola di Pengaturan Gaji &rarr;
                    </a>
                    <button type="button" onclick="closeDetailModal()"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #334155; background: #ffffff; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                        <span>&times;</span> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openCreateKaryawanModal() {
            document.getElementById('karyawanModalTitle').textContent = 'Tambah Karyawan Baru (Outlet {{ $selectedOutlet }})';
            document.getElementById('btnSubmitKaryawanLabel').textContent = 'Simpan Karyawan';
            document.getElementById('formKaryawanModal').action = "{{ route('karyawan.store') }}";
            document.getElementById('karyawanFormMethod').value = 'POST';

            // Reset form values
            document.getElementById('inputNamaKaryawan').value = '';
            document.getElementById('inputNik').value = '';
            document.getElementById('inputTempatLahir').value = '';
            document.getElementById('inputTanggalLahir').value = '';
            document.getElementById('inputWhatsapp').value = '';
            document.getElementById('inputEmail').value = '';
            document.getElementById('inputNomorDarurat').value = '';
            document.getElementById('inputDepartemen').value = '';
            document.getElementById('inputJabatan').value = '';
            document.getElementById('inputJenisTenagaKerja').value = 'Karyawan Tetap';
            document.getElementById('inputOutlet').value = '{{ $selectedOutlet }}';
            document.getElementById('inputNoRekening').value = '';

            document.getElementById('modalFormKaryawan').style.display = 'block';
        }

        function openEditKaryawanModal(data) {
            document.getElementById('karyawanModalTitle').textContent = 'Ubah Data Karyawan: ' + data.nama_karyawan;
            document.getElementById('btnSubmitKaryawanLabel').textContent = 'Perbarui Karyawan';
            document.getElementById('formKaryawanModal').action = '/karyawan/' + data.id;
            document.getElementById('karyawanFormMethod').value = 'PUT';

            // Populate form values
            document.getElementById('inputNamaKaryawan').value = data.nama_karyawan || '';
            document.getElementById('inputNik').value = data.nik || '';
            document.getElementById('inputTempatLahir').value = data.tempat_lahir || '';
            document.getElementById('inputTanggalLahir').value = data.tanggal_lahir || '';
            document.getElementById('inputWhatsapp').value = data.whatsapp || '';
            document.getElementById('inputEmail').value = data.email || '';
            document.getElementById('inputNomorDarurat').value = data.nomor_darurat || '';
            document.getElementById('inputDepartemen').value = data.departemen || '';
            document.getElementById('inputJabatan').value = data.jabatan || '';
            document.getElementById('inputJenisTenagaKerja').value = data.jenis_tenaga_kerja || 'Karyawan Tetap';
            document.getElementById('inputOutlet').value = data.outlet || '{{ $selectedOutlet }}';
            document.getElementById('inputNoRekening').value = data.no_rekening || '';

            document.getElementById('modalFormKaryawan').style.display = 'block';
        }

        function closeFormKaryawanModal() {
            document.getElementById('modalFormKaryawan').style.display = 'none';
        }

        function openDetailModal(data) {
            document.getElementById('mNama').textContent = data.nama;
            document.getElementById('mOutlet').textContent = data.outlet || 'Gaharu';
            document.getElementById('mSubtitle').textContent = (data.jabatan || '-') + ' \u00B7 ' + (data.departemen || '-');

            document.getElementById('mNik').textContent = data.nik || '-';
            document.getElementById('mTempatLahir').textContent = data.tempat_lahir || '-';
            document.getElementById('mTanggalLahir').textContent = data.tanggal_lahir || '-';
            document.getElementById('mWhatsapp').textContent = data.whatsapp || '-';
            document.getElementById('mEmail').textContent = data.email || '-';
            document.getElementById('mNomorDarurat').textContent = data.nomor_darurat || '-';

            document.getElementById('mJabatan').textContent = data.jabatan || '-';
            document.getElementById('mDepartemen').textContent = data.departemen || '-';
            document.getElementById('mJenisKerja').textContent = data.jenis_tenaga_kerja || 'Karyawan';
            document.getElementById('mRekening').textContent = data.no_rekening || 'Belum diatur';

            const satuan = data.satuan_gaji || 'Harian';
            document.getElementById('mBadgeSatuanGaji').textContent = satuan;
            if (satuan === 'Bulanan') {
                document.getElementById('mLabelGajiPokok').textContent = 'Gaji Pokok';
                document.getElementById('mLabelUangMakan').textContent = 'Tj. Makan';
                document.getElementById('mLabelUangTransport').textContent = 'Tj. Transport';
                document.getElementById('mLabelTarifTotal').textContent = 'Total / Bulan';
            } else if (satuan === 'Per Jam') {
                document.getElementById('mLabelGajiPokok').textContent = 'Upah / Jam';
                document.getElementById('mLabelUangMakan').textContent = 'Makan / Jam';
                document.getElementById('mLabelUangTransport').textContent = 'Transport / Jam';
                document.getElementById('mLabelTarifTotal').textContent = 'Total / Jam';
            } else {
                document.getElementById('mLabelGajiPokok').textContent = 'Gaji Pokok';
                document.getElementById('mLabelUangMakan').textContent = 'Uang Makan';
                document.getElementById('mLabelUangTransport').textContent = 'Transport';
                document.getElementById('mLabelTarifTotal').textContent = 'Total / Hari';
            }

            document.getElementById('mGajiPokok').textContent = 'Rp ' + data.gaji_pokok;
            document.getElementById('mUangMakan').textContent = 'Rp ' + data.uang_makan;
            document.getElementById('mUangTransport').textContent = 'Rp ' + data.uang_transport;
            document.getElementById('mTarifTotal').textContent = 'Rp ' + data.tarif_total;

            document.getElementById('modalDetailKaryawan').style.display = 'block';
        }

        function closeDetailModal() {
            document.getElementById('modalDetailKaryawan').style.display = 'none';
        }
    </script>

    {{-- FLOATING TOAST NOTIFICATION --}}
    <div id="reorderToast" style="display: none; position: fixed; bottom: 28px; right: 28px; z-index: 99999; background: #0f172a; color: #ffffff; padding: 12px 20px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); font-size: 13px; font-weight: 700; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.15); transition: opacity 0.2s ease;">
        <span id="reorderToastIcon" style="font-size: 15px;">⏳</span>
        <span id="reorderToastMsg">Menyimpan urutan...</span>
    </div>

    <!-- SortableJS CDN with Fallback -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tbody = document.getElementById('karyawanTableBody');
            const toast = document.getElementById('reorderToast');
            const toastIcon = document.getElementById('reorderToastIcon');
            const toastMsg = document.getElementById('reorderToastMsg');
            let toastTimeout;

            function showToast(msg, icon = '⏳', isError = false) {
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
                const rows = tbody.querySelectorAll('.karyawan-row');
                rows.forEach((row, idx) => {
                    const idxSpan = row.querySelector('.row-index');
                    if (idxSpan) idxSpan.textContent = idx + 1;
                });
            }

            function saveOrder() {
                const rows = tbody.querySelectorAll('.karyawan-row');
                const ids = Array.from(rows).map(r => r.getAttribute('data-id')).filter(Boolean);

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
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function () {
                        updateRowIndexes();
                        saveOrder();
                    }
                });
            } else if (tbody) {
                // Native HTML5 Drag and Drop fallback
                let draggedRow = null;
                const rows = tbody.querySelectorAll('.karyawan-row');
                rows.forEach(row => {
                    const handle = row.querySelector('.drag-handle') || row;
                    row.setAttribute('draggable', 'true');
                    row.addEventListener('dragstart', (e) => {
                        draggedRow = row;
                        e.dataTransfer.effectAllowed = 'move';
                        row.classList.add('opacity-50');
                    });
                    row.addEventListener('dragend', () => {
                        draggedRow = null;
                        row.classList.remove('opacity-50');
                        updateRowIndexes();
                        saveOrder();
                    });
                    row.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        const targetRow = e.target.closest('.karyawan-row');
                        if (targetRow && targetRow !== draggedRow) {
                            const rect = targetRow.getBoundingClientRect();
                            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                            tbody.insertBefore(draggedRow, next ? targetRow.nextSibling : targetRow);
                        }
                    });
                });
            }
        });
    </script>
</x-app-layout>