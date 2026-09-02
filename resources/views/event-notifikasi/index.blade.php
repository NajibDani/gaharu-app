<x-app-layout>
    <x-slot name="header">
        <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-bell-fill me-2 text-warning"></i> Manajemen Event & Notifikasi</h4>
    </x-slot>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold">Daftar Event &amp; Notifikasi Khusus</h5>
                <small class="text-muted">Kelola popup pengingat SweetAlert untuk Purchasing / High Season / Promo</small>
            </div>

            <div class="d-flex align-items-center gap-2">
                <form action="{{ route('event-notifikasi.index') }}" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul / pesan..." value="{{ request('search') }}" style="width: 220px; border-radius: 6px;">
                    <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('event-notifikasi.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
                    @endif
                </form>

                <button type="button" class="btn btn-sm text-white px-3" style="background-color: #d88656; border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#modalTambahEvent">
                    <i class="bi bi-plus-circle me-1"></i> Buat Event Baru
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background-color: #5a3416; color: white;">
                        <tr>
                            <th class="ps-3" width="60">No</th>
                            <th>Judul Event</th>
                            <th>Isi Pesan</th>
                            <th width="140">Menu Target</th>
                            <th width="180">Periode Tayang</th>
                            <th class="text-center" width="100">Status</th>
                            <th class="text-center" width="160">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr>
                                <td class="ps-3 text-muted">{{ $loop->iteration + ($events->currentPage() - 1) * $events->perPage() }}</td>
                                <td class="fw-bold text-dark">
                                    <div class="d-flex align-items-center gap-2">
                                        @php
                                            $iconColor = match($event->tipe_icon) {
                                                'warning' => 'text-warning bi-exclamation-triangle-fill',
                                                'info'    => 'text-info bi-info-circle-fill',
                                                'success' => 'text-success bi-check-circle-fill',
                                                'error'   => 'text-danger bi-x-circle-fill',
                                                default   => 'text-primary bi-bell-fill'
                                            };
                                        @endphp
                                        <i class="bi {{ $iconColor }} fs-5"></i>
                                        <span>{{ $event->judul }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 320px;" title="{{ $event->pesan }}">
                                        {{ $event->pesan }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 text-uppercase">
                                        {{ $event->menu_target }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($event->tanggal_mulai)->format('d M Y') }}</span>
                                        <div class="text-muted" style="font-size: 11px;">s.d. {{ \Carbon\Carbon::parse($event->tanggal_selesai)->format('d M Y') }}</div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('event-notifikasi.toggle', $event->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        @if($event->is_active)
                                            <button type="submit" class="badge bg-success border-0 px-3 py-1 cursor-pointer" title="Klik untuk nonaktifkan">Aktif</button>
                                        @else
                                            <button type="submit" class="badge bg-danger border-0 px-3 py-1 cursor-pointer" title="Klik untuk aktifkan">Nonaktif</button>
                                        @endif
                                    </form>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" 
                                                class="btn btn-sm btn-info text-white" 
                                                style="font-size: 11px; padding: 2px 8px; border-radius: 5px;"
                                                onclick="previewSweetAlert('{{ addslashes($event->judul) }}', '{{ addslashes($event->pesan) }}', '{{ $event->tipe_icon }}', '{{ $event->id }}')"
                                                title="Preview Pop-up SweetAlert">
                                            <i class="bi bi-eye"></i> Test
                                        </button>

                                        <button type="button" 
                                                class="btn btn-sm btn-warning text-dark" 
                                                style="font-size: 11px; padding: 2px 8px; border-radius: 5px;"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditEvent{{ $event->id }}"
                                                title="Edit Event">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <form action="{{ route('event-notifikasi.destroy', $event->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus event {{ $event->judul }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" style="font-size: 11px; padding: 2px 8px; border-radius: 5px;" title="Hapus Event">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            {{-- Modal Edit Event --}}
                            <div class="modal fade" id="modalEditEvent{{ $event->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow">
                                        <form action="{{ route('event-notifikasi.update', $event->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header bg-warning-subtle text-dark">
                                                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Event & Notifikasi</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Judul Event / Notifikasi <span class="text-danger">*</span></label>
                                                    <input type="text" name="judul" class="form-control form-control-sm" value="{{ $event->judul }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Isi Pesan Notifikasi <span class="text-danger">*</span></label>
                                                    <textarea name="pesan" rows="3" class="form-control form-control-sm" required>{{ $event->pesan }}</textarea>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Menu Target <span class="text-danger">*</span></label>
                                                        <select name="menu_target" class="form-select form-select-sm" required>
                                                            <option value="pembelian" {{ $event->menu_target === 'pembelian' ? 'selected' : '' }}>Pembelian Bahan Baku</option>
                                                            <option value="permintaan" {{ $event->menu_target === 'permintaan' ? 'selected' : '' }}>Permintaan / Transfer Bahan</option>
                                                            <option value="produksi" {{ $event->menu_target === 'produksi' ? 'selected' : '' }}>Produksi Central Kitchen</option>
                                                            <option value="penjualan_pos" {{ $event->menu_target === 'penjualan_pos' ? 'selected' : '' }}>Penjualan POS</option>
                                                            <option value="semua" {{ $event->menu_target === 'semua' ? 'selected' : '' }}>Semua Menu</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Tipe Icon <span class="text-danger">*</span></label>
                                                        <select name="tipe_icon" class="form-select form-select-sm" required>
                                                            <option value="warning" {{ $event->tipe_icon === 'warning' ? 'selected' : '' }}>Peringatan (Warning)</option>
                                                            <option value="info" {{ $event->tipe_icon === 'info' ? 'selected' : '' }}>Informasi (Info)</option>
                                                            <option value="success" {{ $event->tipe_icon === 'success' ? 'selected' : '' }}>Sukses (Success)</option>
                                                            <option value="question" {{ $event->tipe_icon === 'question' ? 'selected' : '' }}>Pertanyaan (Question)</option>
                                                            <option value="error" {{ $event->tipe_icon === 'error' ? 'selected' : '' }}>Bahaya (Error)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Tanggal Mulai <span class="text-danger">*</span></label>
                                                        <input type="date" name="tanggal_mulai" class="form-control form-control-sm" value="{{ $event->tanggal_mulai->format('Y-m-d') }}" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-bold">Tanggal Selesai <span class="text-danger">*</span></label>
                                                        <input type="date" name="tanggal_selesai" class="form-control form-control-sm" value="{{ $event->tanggal_selesai->format('Y-m-d') }}" required>
                                                    </div>
                                                </div>
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active{{ $event->id }}" value="1" {{ $event->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label small fw-bold" for="edit_is_active{{ $event->id }}">Status Aktif</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light py-2">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sm btn-primary px-3">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada event notifikasi. Silakan tambahkan event baru.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p-3">
            {{ $events->links() }}
        </div>
    </div>

    {{-- Modal Tambah Event Baru --}}
    <div class="modal fade" id="modalTambahEvent" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('event-notifikasi.store') }}" method="POST">
                    @csrf
                    <div class="modal-header text-white" style="background-color: #5a3416;">
                        <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Buat Event & Notifikasi Baru</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Judul Event / Notifikasi <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control form-control-sm" placeholder="Contoh: Menjelang High Season Idul Fitri" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Isi Pesan Notifikasi <span class="text-danger">*</span></label>
                            <textarea name="pesan" rows="3" class="form-control form-control-sm" placeholder="Contoh: Perhatian! Menjelang High Season, harap lakukan double purchase pada bahan baku utama agar tidak kehabisan stok." required></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Menu Target <span class="text-danger">*</span></label>
                                <select name="menu_target" class="form-select form-select-sm" required>
                                    <option value="pembelian">Pembelian Bahan Baku</option>
                                    <option value="permintaan">Permintaan / Transfer Bahan</option>
                                    <option value="produksi">Produksi Central Kitchen</option>
                                    <option value="penjualan_pos">Penjualan POS</option>
                                    <option value="semua">Semua Menu</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Tipe Icon <span class="text-danger">*</span></label>
                                <select name="tipe_icon" class="form-select form-select-sm" required>
                                    <option value="warning">Peringatan (Warning)</option>
                                    <option value="info">Informasi (Info)</option>
                                    <option value="success">Sukses (Success)</option>
                                    <option value="question">Pertanyaan (Question)</option>
                                    <option value="error">Bahaya (Error)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Tanggal Mulai <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_mulai" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Tanggal Selesai <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_selesai" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+7 days')) }}" required>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" value="1" checked>
                            <label class="form-check-label small fw-bold" for="add_is_active">Aktifkan Sekarang</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #d88656;">Simpan Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function previewSweetAlert(judul, pesan, icon, id) {
            Swal.fire({
                title: judul,
                html: '<p style="font-size: 15px; margin-bottom: 12px; color: #374151; line-height: 1.6;">' + pesan + '</p>',
                icon: icon,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check2"></i> Mengerti',
                cancelButtonText: '<i class="bi bi-eye-slash"></i> Jangan tampilkan lagi',
                confirmButtonColor: '#7A4517',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusConfirm: true,
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    Swal.fire({
                        title: 'Pengaturan Disimpan',
                        text: 'Notifikasi ini tidak akan dimunculkan lagi pada perangkat Anda.',
                        icon: 'info',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
    </script>
    @endpush
</x-app-layout>
