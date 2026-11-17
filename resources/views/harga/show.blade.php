<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        .table-success-subtle { background-color: #f0fdf4 !important; }
        .table-warning-subtle { background-color: #fffdec !important; }
        .table-info-subtle { background-color: #f0f9ff !important; }
        .text-gray-800 { color: #1e293b; }
        .text-gray-700 { color: #334155; }
        .btn-primary-theme { background-color: #d88656; color: white; border: none; transition: all 0.2s; }
        .btn-primary-theme:hover { background-color: #c77545; color: white; }
        .btn-outline-primary-theme { border: 1px solid #d88656; color: #d88656; background-color: transparent; transition: all 0.2s; }
        .btn-outline-primary-theme:hover { background-color: #d88656; color: white; }
    </style>

    <div class="container py-4" style="font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; min-height: 100vh;">
        <div class="row">
            {{-- Alert Notifikasi --}}
            <div class="col-md-12 mb-3">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important;">
                        <i class="bi bi-check-circle-fill me-3 fs-5 text-success"></i>
                        <div>
                            <span class="fw-bold text-success d-block">Berhasil</span>
                            <span class="small text-secondary">{{ session('success') }}</span>
                        </div>
                        <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 d-flex align-items-center" role="alert" style="background-color: #fef2f2; border-left: 4px solid #ef4444 !important;">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-5 text-danger"></i>
                        <div>
                            <span class="fw-bold text-danger d-block">Gagal Proses</span>
                            <span class="small text-secondary">{{ $errors->first('error') }}</span>
                        </div>
                        <button type="button" class="btn-close ms-auto shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>

            {{-- Header Section --}}
            <div class="col-md-12 mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <a href="{{ route('harga.index') }}" class="btn btn-sm btn-outline-secondary rounded-3 px-3 fw-bold mb-3 shadow-none">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Barang
                    </a>
                    <h3 class="fw-bold text-gray-800 m-0"><i class="bi bi-tags-fill me-2" style="color: #d88656;"></i>Atur & Histori Harga POS</h3>
                    <p class="text-muted small m-0 mt-1">Detail harga jual serta histori pencatatan periode untuk produk.</p>
                </div>
            </div>

            {{-- KIRI: FORM ENTRI HARGA BARU --}}
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-dark text-white py-3 border-0 rounded-top-4">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>Atur Harga Baru</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <span class="text-muted small d-block">Barang Terpilih:</span>
                            <span class="fw-bold text-gray-800 fs-6 d-block mt-1">{{ $barangTerpilih->nama }}</span>
                            <span class="badge bg-secondary mt-1">Kode: {{ $barangTerpilih->kode_barang }}</span>
                            @php
                                $hpp = $barangTerpilih->dynamic_hpp;
                            @endphp
                            <span class="badge bg-info text-dark mt-1">HPP Ref: Rp {{ number_format($hpp, 0, ',', '.') }}</span>
                        </div>

                        <form action="{{ route('harga.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="barang_id" value="{{ $barangTerpilih->id }}">

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary">Tanggal Mulai Berlaku</label>
                                <input type="date" name="tgl_mulai" class="form-control rounded-3 shadow-none border @error('tgl_mulai') is-invalid @enderror" required value="{{ old('tgl_mulai', date('Y-m-d')) }}">
                                @error('tgl_mulai')
                                    <div class="invalid-feedback fw-bold small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary">Tanggal Selesai Berlaku</label>
                                <input type="date" name="tgl_selesai" class="form-control rounded-3 shadow-none border @error('tgl_selesai') is-invalid @enderror" required value="{{ old('tgl_selesai') }}">
                                @error('tgl_selesai')
                                    <div class="invalid-feedback fw-bold small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary">Harga Jual (POS)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-secondary-subtle border fw-bold text-secondary">Rp</span>
                                    <input type="number" name="harga_pos" class="form-control rounded-end-3 shadow-none border fw-bold text-primary fs-5" placeholder="0" min="0" value="{{ old('harga_pos') }}" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary">Keterangan / Note (Opsional)</label>
                                <textarea name="keterangan" class="form-control rounded-3 shadow-none border" rows="2" placeholder="Contoh: Promo Weekend, Harga Normal, dll">{{ old('keterangan') }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-primary-theme w-100 py-2.5 rounded-3 fw-bold shadow-none">
                                <i class="bi bi-check-circle-fill me-2"></i>Terapkan Harga Baru
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- KANAN: TABEL RIWAYAT & PERIODE HARGA --}}
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-gray-800"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Harga: {{ $barangTerpilih->nama }}</h6>
                        <span class="badge bg-light text-dark border px-2 py-1.5 fw-normal small">
                            Total: <strong class="text-primary">{{ $riwayatHarga->count() }} Data</strong>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        {{-- Legenda Indikator Warna Status --}}
                        <div class="px-4 py-2 bg-light border-bottom d-flex flex-wrap gap-3 align-items-center" style="font-size: 0.75rem;">
                            <span class="fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px;">Indikator:</span>
                            <span><i class="bi bi-circle-fill text-success me-1"></i> Aktif Sekarang</span>
                            <span><i class="bi bi-circle-fill text-warning me-1"></i> Berakhir Hari Ini</span>
                            <span><i class="bi bi-circle-fill text-info me-1"></i> Rencana (Masa Depan)</span>
                            <span><i class="bi bi-circle-fill text-muted me-1"></i> Expired</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle text-nowrap mb-0">
                                <thead class="table-light text-uppercase font-weight-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="ps-4 py-3" width="160">Periode</th>
                                        <th class="py-3 text-end" width="160">Nominal Harga</th>
                                        <th class="text-center py-3" width="140">Status</th>
                                        <th class="text-center py-3 pe-4" width="150">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $today = date('Y-m-d'); @endphp

                                    @forelse($riwayatHarga as $h)
                                        @php
                                            $isAktif = ($today >= $h->tgl_mulai && $today <= $h->tgl_selesai);

                                            if ($isAktif) {
                                                if ($h->tgl_selesai == $today) {
                                                    $rowClass = 'table-warning-subtle border-start border-warning border-3';
                                                    $badgeHtml = '<span class="badge bg-warning text-dark px-2 py-1 text-uppercase rounded-2" style="font-size: 0.7rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Berakhir Hari Ini</span>';
                                                } else {
                                                    $rowClass = 'table-success-subtle border-start border-success border-3';
                                                    $badgeHtml = '<span class="badge bg-success px-2 py-1 text-uppercase rounded-2" style="font-size: 0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Aktif Sekarang</span>';
                                                }
                                            } elseif ($today > $h->tgl_selesai) {
                                                $rowClass = 'text-muted bg-light-subtle';
                                                $badgeHtml = '<span class="badge bg-secondary text-white px-2 py-1 text-uppercase rounded-2" style="font-size: 0.7rem;">Expired</span>';
                                            } else {
                                                $rowClass = 'table-info-subtle border-start border-info border-3';
                                                $badgeHtml = '<span class="badge bg-info text-dark px-2 py-1 text-uppercase rounded-2" style="font-size: 0.7rem;"><i class="bi bi-calendar-event-fill me-1"></i>Rencana</span>';
                                            }
                                        @endphp

                                        <tr class="{{ $rowClass }}">
                                            <td class="ps-4">
                                                <div class="fw-semibold text-dark" style="font-size: 0.85rem;">
                                                    {{ date('d M Y', strtotime($h->tgl_mulai)) }}
                                                </div>
                                                <div class="small text-muted" style="font-size: 0.75rem;">
                                                    s/d {{ date('d M Y', strtotime($h->tgl_selesai)) }}
                                                </div>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="fw-bold fs-6 {{ $isAktif ? ($h->tgl_selesai == $today ? 'text-warning' : 'text-success') : 'text-dark' }}">
                                                    Rp {{ number_format($h->harga_pos, 0, ',', '.') }}
                                                </div>
                                                @if($h->keterangan)
                                                    <div class="small text-muted fst-italic text-wrap" style="max-width: 180px; font-size: 0.75rem;">
                                                        "{{ $h->keterangan }}"
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {!! $badgeHtml !!}
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex justify-content-center align-items-center gap-1 flex-wrap">
                                                    @if($isAktif && $h->tgl_selesai > $today)
                                                        <form action="{{ route('harga.update', $h->id) }}" method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Yakin ingin mengakhiri periode harga ini sekarang?');">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="tgl_selesai" value="{{ date('Y-m-d') }}">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning rounded-2 px-2 py-1 shadow-none fw-semibold" style="font-size: 0.75rem;" title="Akhiri Sekarang">
                                                                <i class="bi bi-stop-circle me-1"></i>Akhiri
                                                            </button>
                                                        </form>
                                                    @elseif($isAktif && $h->tgl_selesai == $today)
                                                        <span class="badge bg-light text-secondary border me-1" style="font-size: 0.7rem;"><i class="bi bi-check-all me-1"></i>Diakhiri Hari Ini</span>
                                                    @endif

                                                    <form action="{{ route('harga.destroy', $h->id) }}" method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus periode harga ini? Data yang dihapus tidak dapat dikembalikan.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-2 px-2 py-1 shadow-none fw-semibold" style="font-size: 0.75rem;" title="Hapus Periode Harga">
                                                            <i class="bi bi-trash"></i> Hapus
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <div class="py-3">
                                                    <i class="bi bi-tags fs-1 text-secondary opacity-25 d-block mb-2"></i>
                                                    <p class="mb-0 fw-semibold">Belum ada riwayat rentang harga untuk item ini.</p>
                                                    <small class="text-muted">Isi form di sebelah kiri untuk mendaftarkan harga baru.</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
