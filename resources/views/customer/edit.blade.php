<x-app-layout>
    <x-slot name="header">
        <h4 class="fw-bold text-dark mb-0">Edit Data Konsumen / Pelanggan</h4>
    </x-slot>

    <div class="container-fluid px-2 px-md-4 py-3">
        <div class="card border-0 shadow-sm rounded-4" style="max-width: 700px;">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square text-warning me-2"></i> Form Edit Konsumen / Pelanggan</h5>
            </div>

            <div class="card-body p-4">
                @if ($errors->any())
                    <div class="alert alert-danger rounded-3 small">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('customer.update', $data->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Nama Konsumen / Outlet <span class="text-danger">*</span></label>
                        <input type="text" name="nama" value="{{ old('nama', $data->nama) }}" class="form-control rounded-3" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Jenis Pelanggan <span class="text-danger">*</span></label>
                        @php $currentJenis = old('jenis', $data->jenis); @endphp
                        <select name="jenis" class="form-select rounded-3" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Konsumen Cold Kitchen" {{ $currentJenis == 'Konsumen Cold Kitchen' ? 'selected' : '' }}>Konsumen Cold Kitchen (Luar)</option>
                            <option value="Konsumen Luar" {{ $currentJenis == 'Konsumen Luar' ? 'selected' : '' }}>Konsumen Luar / Mitra Bisnis</option>
                            <option value="Outlet Internal" {{ $currentJenis == 'Outlet Internal' ? 'selected' : '' }}>Outlet Internal</option>
                            <option value="Reseller" {{ $currentJenis == 'Reseller' ? 'selected' : '' }}>Reseller</option>
                            <option value="Horeca" {{ $currentJenis == 'Horeca' ? 'selected' : '' }}>Horeca (Hotel / Resto / Cafe)</option>
                            <option value="Corporate" {{ $currentJenis == 'Corporate' ? 'selected' : '' }}>Corporate</option>
                            <option value="Umum" {{ $currentJenis == 'Umum' ? 'selected' : '' }}>Umum</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">No HP / Kontak</label>
                        <input type="text" name="no_hp" value="{{ old('no_hp', $data->no_hp) }}" class="form-control rounded-3">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-secondary">Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control rounded-3" rows="3">{{ old('alamat', $data->alamat) }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-custom-orange px-4 fw-bold">Update Konsumen</button>
                        <a href="{{ route('customer.index') }}" class="btn btn-outline-secondary px-4">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>