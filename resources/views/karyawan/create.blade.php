<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Karyawan Baru</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <form action="{{ route('karyawan.store') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nama Karyawan</label>
                        <input type="text" name="nama_karyawan" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Jabatan</label>
                        <input type="text" name="jabatan" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Jenis Tenaga Kerja</label>
                        <select name="jenis_tenaga_kerja" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="Karyawan Tetap">Karyawan Tetap</option>
                            <option value="Karyawan Kontrak">Karyawan Kontrak</option>
                            <option value="Part Time">Part Time</option>
                            <option value="Casual">Casual</option>
                            <option value="Probation">Probation</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Departemen</label>
                        <select name="departemen" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="Gudang">Gudang</option>
                            <option value="Produksi">Produksi</option>
                            <option value="Manajemen">Manajemen</option>
                            <option value="Operasional">Operasional</option>
                            <option value="Kitchen">Kitchen</option>
                            <option value="Central Kitchen">Central Kitchen</option>
                            <option value="Cold Kitchen">Cold Kitchen</option>
                            <option value="Barista">Barista</option>
                            <option value="Server">Server</option>
                            <option value="Satpam">Satpam</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Outlet <span class="text-red-500">*</span></label>
                        <select name="outlet" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-bold text-amber-900" required>
                            <option value="Gaharu" {{ request('outlet') == 'Gaharu' ? 'selected' : '' }}>Outlet Gaharu</option>
                            <option value="Kejingga" {{ request('outlet') == 'Kejingga' ? 'selected' : '' }}>Outlet Kejingga</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nomor Rekening</label>
                        <input type="text" name="no_rekening" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Contoh: BCA 1234567890">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Gaji Pokok</label>
                        <input type="number" name="gaji_pokok" class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm text-gray-500" required min="0" value="0" readonly>
                        <p class="text-xs text-gray-500 mt-1">Gaji pokok akan diatur melalui menu Pengaturan Gaji.</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Simpan Karyawan
                        </button>
                        <a href="{{ route('karyawan.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>