<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Karyawan: ') . $karyawan->nama_karyawan }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow sm:rounded-lg">

                @if ($errors->any())
                <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700">
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form action="{{ route('karyawan.update', $karyawan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nama Karyawan</label>
                        <input type="text" name="nama_karyawan"
                            value="{{ old('nama_karyawan', $karyawan->nama_karyawan) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Jabatan</label>
                        <input type="text" name="jabatan"
                            value="{{ old('jabatan', $karyawan->jabatan) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Jenis Tenaga Kerja</label>
                        <select name="jenis_tenaga_kerja" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(['Karyawan Tetap', 'Karyawan Kontrak', 'Part Time', 'Casual', 'Probation'] as $jenis)
                            <option value="{{ $jenis }}" {{ old('jenis_tenaga_kerja', $karyawan->jenis_tenaga_kerja) == $jenis ? 'selected' : '' }}>
                                {{ $jenis }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Departemen</label>
                        <select name="departemen" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(['Gudang', 'Produksi', 'Manajemen', 'Operasional', 'Kitchen', 'Central Kitchen', 'Cold Kitchen', 'Barista', 'Server', 'Satpam'] as $dept)
                            <option value="{{ $dept }}" {{ old('departemen', $karyawan->departemen) == $dept ? 'selected' : '' }}>
                                {{ $dept }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Outlet <span class="text-red-500">*</span></label>
                        <select name="outlet" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-bold text-amber-900 focus:border-amber-500 focus:ring-amber-500" required>
                            @foreach(['Gaharu', 'Kejingga'] as $outl)
                            <option value="{{ $outl }}" {{ old('outlet', $karyawan->outlet ?? 'Gaharu') == $outl ? 'selected' : '' }}>
                                Outlet {{ $outl }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nomor Rekening</label>
                        <input type="text" name="no_rekening"
                            value="{{ old('no_rekening', $karyawan->no_rekening) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: BCA 1234567890">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Gaji Pokok</label>
                        <input type="number" name="gaji_pokok"
                            value="{{ old('gaji_pokok', $karyawan->gaji_pokok) }}"
                            class="mt-1 block w-full bg-gray-100 text-gray-500 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required min="0" readonly>
                        <p class="text-xs text-gray-500 mt-1">Gaji pokok hanya dapat diubah melalui menu Pengaturan Gaji.</p>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <a href="{{ route('karyawan.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline mr-4">
                            Batal
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Perbarui Karyawan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>