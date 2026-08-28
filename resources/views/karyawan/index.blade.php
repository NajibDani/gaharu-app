<x-app-layout>
    <x-slot name="header">

        Master Karyawan

    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <x-outlet-selector :selectedOutlet="$selectedOutlet" />

                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <a href="{{ route('karyawan.create', ['outlet' => $selectedOutlet]) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        + Tambah Karyawan ({{ $selectedOutlet }})
                    </a>

                    <form action="{{ route('karyawan.index') }}" method="GET" class="flex gap-2">
                        <input type="hidden" name="outlet" value="{{ $selectedOutlet }}">
                        <input type="text" name="search" class="border rounded px-3 py-1 text-sm" placeholder="Cari nama/jabatan/dept..." value="{{ request('search') }}" style="width: 220px;">
                        <button type="submit" class="bg-gray-800 text-white px-3 py-1 rounded text-sm">Cari</button>
                        @if(request('search'))
                            <a href="{{ route('karyawan.index', ['outlet' => $selectedOutlet]) }}" class="bg-gray-200 text-gray-700 px-3 py-1 rounded text-sm">Reset</a>
                        @endif
                    </form>
                </div>

                <table class="w-full mt-4 border">
                    <thead>
                        <tr class="bg-gray-100 border-b">
                            <th class="p-2 text-left">Nama Karyawan</th>
                            <th class="p-2 text-left">Jabatan</th>
                            <th class="p-2 text-left">Departemen</th>
                            <th class="p-2 text-center">Outlet</th>
                            <th class="p-2 text-right">Gaji Pokok</th>
                            <th class="p-2 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($karyawans as $k)
                        <tr class="border-b">
                            <td class="p-2 font-medium">{{ $k->nama_karyawan }}</td>
                            <td class="p-2">{{ $k->jabatan }}</td>
                            <td class="p-2">{{ $k->departemen }}</td>
                            <td class="p-2 text-center">
                                <span class="px-2 py-1 bg-amber-100 text-amber-800 font-semibold rounded text-xs">
                                    {{ $k->outlet }}
                                </span>
                            </td>
                            <td class="p-2 text-right">Rp {{ number_format($k->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="p-2">
                                <a href="{{ route('karyawan.show', $k->id) }}" class="text-green-600 mr-2">Detil</a>
                                <a href="{{ route('karyawan.edit', $k->id) }}" class="text-blue-600">Edit</a>
                                <form action="{{ route('karyawan.destroy', $k->id) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 ml-2" onclick="return confirm('Hapus?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500">Data karyawan belum ada.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $karyawans->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>