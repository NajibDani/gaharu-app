<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Jurnal Penyesuaian: {{ $jurnal->no_ref }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('adjustment.edit', $jurnal->id) }}" class="bg-yellow-500 text-black px-4 py-2 rounded text-sm shadow-sm hover:bg-yellow-600 transition">
                    Edit
                </a>
                <form action="{{ route('adjustment.destroy', $jurnal->id) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Jurnal Penyesuaian ini? Hapus jurnal akan langsung memperbarui laporan akuntansi.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded text-sm shadow-sm hover:bg-red-700 transition">
                        Hapus
                    </button>
                </form>
                <a href="{{ route('adjustment.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded text-sm shadow-sm hover:bg-gray-600 transition">
                    Kembali
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 border-b pb-6">
                    <div>
                        <p class="text-sm text-gray-500 uppercase font-bold">Tanggal Transaksi</p>
                        <p class="text-lg font-semibold">{{ \Carbon\Carbon::parse($jurnal->tanggal)->translatedFormat('d F Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase font-bold">Nomor Referensi</p>
                        <p class="text-lg font-semibold font-mono text-gray-700">{{ $jurnal->no_ref }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase font-bold">Status</p>
                        <p class="mt-1">
                            @if($jurnal->status === 'approved')
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold uppercase">Posted</span>
                            @else
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold uppercase">Draft</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase font-bold">Deskripsi / Keterangan</p>
                        <p class="text-lg font-semibold">{{ $jurnal->deskripsi }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">
                                <th class="p-4 border-b">Kode Akun / COA</th>
                                <th class="p-4 border-b text-right">Debit</th>
                                <th class="p-4 border-b text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($jurnal->details as $detail)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 text-sm text-gray-700">
                                    <span class="font-mono text-gray-500 mr-2">[{{ $detail->coa->kode ?? $detail->account_id }}]</span>
                                    {{ $detail->coa->nama ?? 'Akun Tidak Ditemukan' }}
                                </td>
                                <td class="p-4 text-sm text-right text-gray-900 font-mono">
                                    {{ $detail->debit > 0 ? 'Rp ' . number_format($detail->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="p-4 text-sm text-right text-gray-900 font-mono">
                                    {{ $detail->kredit > 0 ? 'Rp ' . number_format($detail->kredit, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-bold border-t-2 border-gray-300">
                                <td class="p-4 text-sm text-gray-700 text-right">Total:</td>
                                <td class="p-4 text-right text-indigo-600 font-mono">
                                    Rp {{ number_format($jurnal->details->sum('debit'), 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-right text-indigo-600 font-mono">
                                    Rp {{ number_format($jurnal->details->sum('kredit'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-8 flex justify-end gap-3 border-t pt-6">
                    <a href="{{ route('adjustment.index') }}"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md font-semibold hover:bg-gray-300 transition">
                        Tutup
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>