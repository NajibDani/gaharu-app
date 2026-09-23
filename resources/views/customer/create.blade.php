<x-app-layout>
    <x-slot name="header">
        <h4 class="fw-bold text-dark mb-0">Tambah Konsumen / Pelanggan Baru</h4>
    </x-slot>

    <div class="container-fluid px-2 px-md-4 py-3">
        <div class="card border-0 shadow-sm rounded-4" style="max-width: 700px;">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-plus-fill text-warning me-2"></i> Form Input Konsumen / Pelanggan</h5>
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

                <form action="{{ route('customer.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Nama Konsumen / Outlet <span class="text-danger">*</span></label>
                        <input type="text" name="nama" value="{{ old('nama') }}" class="form-control rounded-3" placeholder="Contoh: Konsumen Cold Kitchen / Cafe ABC / Outlet XYZ" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Jenis Pelanggan <span class="text-danger">*</span></label>
                        <select name="jenis" class="form-select rounded-3" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Konsumen Cold Kitchen" {{ old('jenis') == 'Konsumen Cold Kitchen' ? 'selected' : '' }}>Konsumen Cold Kitchen (Luar)</option>
                            <option value="Konsumen Luar" {{ old('jenis') == 'Konsumen Luar' ? 'selected' : '' }}>Konsumen Luar / Mitra Bisnis</option>
                            <option value="Outlet Internal" {{ old('jenis') == 'Outlet Internal' ? 'selected' : '' }}>Outlet Internal</option>
                            <option value="Reseller" {{ old('jenis') == 'Reseller' ? 'selected' : '' }}>Reseller</option>
                            <option value="Horeca" {{ old('jenis') == 'Horeca' ? 'selected' : '' }}>Horeca (Hotel / Resto / Cafe)</option>
                            <option value="Corporate" {{ old('jenis') == 'Corporate' ? 'selected' : '' }}>Corporate</option>
                            <option value="Umum" {{ old('jenis') == 'Umum' ? 'selected' : '' }}>Umum</option>
                        </select>
                        <small class="text-muted" style="font-size: 11px;">Pilih <strong>Konsumen Cold Kitchen</strong> untuk pelanggan di luar gudang Gaharu/KeJingga/CK.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">No HP / Kontak</label>
                        <input type="text" name="no_hp" value="{{ old('no_hp') }}" class="form-control rounded-3" placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-secondary">Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control rounded-3" rows="3" placeholder="Alamat pengiriman / lokasi pelanggan...">{{ old('alamat') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-custom-orange px-4 fw-bold">Simpan Konsumen</button>
                        <a href="{{ route('customer.index') }}" class="btn btn-outline-secondary px-4">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>