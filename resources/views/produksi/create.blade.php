<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        .text-warning-dark {
            color: #b45309 !important;
        }
    </style>

    <div class="container py-4 mt-4 mb-5" style="font-family: 'Plus Jakarta Sans', sans-serif;">
        
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="{{ route('produksi.index') }}" class="btn btn-sm btn-outline-secondary rounded-3 px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            
            <div class="card-header text-white py-3 border-0" style="background-color: #4f46e5;">
                <h4 class="mb-0 fw-bold">
                    <i class="bi bi-box-seam me-2"></i> Input Hasil Produksi Fisik
                </h4>
                <small class="text-white-50">
                    Catat penambahan stok produk jadi berdasarkan Work Order aktif yang sedang berjalan.
                </small>
            </div>

            <div class="card-body p-4 bg-white">

                {{-- ALERT SUCCESS --}}
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 p-3 mb-4" role="alert" style="background-color: #ecfdf5;">
                        <i class="bi bi-check-circle-fill me-2 text-success"></i> 
                        <strong class="text-success">Berhasil!</strong> 
                        <span class="small text-secondary d-block mt-1">{{ session('success') }}</span>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                {{-- ALERT ERROR --}}
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 p-3 mb-4" role="alert" style="background-color: #fef2f2;">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> 
                        <strong class="text-danger">Gagal!</strong> 
                        <span class="small text-secondary d-block mt-1">{{ session('error') }}</span>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                {{-- VALIDATION ERROR --}}
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 p-3 mb-4" role="alert" style="background-color: #fef2f2;">
                        <i class="bi bi-x-circle-fill me-2 text-danger"></i>
                        <strong class="text-danger">Periksa Kembali Isian Anda:</strong>
                        <ul class="mb-0 small text-secondary mt-1 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('produksi.store') }}" method="POST">
                    @csrf

                    <div class="p-3 bg-light rounded-3 mb-4 border border-secondary-subtle">
                        <h6 class="fw-bold text-dark mb-3">
                            <span class="badge rounded-circle me-1 text-white" style="background-color: #4f46e5; padding: 5px 9px;">1</span> 
                            Pilih Sumber Dokumen Kerja
                        </h6>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">Tanggal Penginputan</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                                    <input type="date" name="tanggal_produksi" class="form-control border-start-0 ps-0 text-dark fw-medium shadow-none" value="{{ date('Y-m-y') }}" required>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-secondary">Pilih Nomor Work Order (WO Active)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-file-earmark-text"></i></span>
                                    <select name="work_order_id" class="form-select border-start-0 ps-0 text-dark fw-bold shadow-none" onchange="window.location.href='?work_order_id=' + this.value" required>
                                        <option value="">-- Klik untuk memilih nomor WO pabrik --</option>
                                        @foreach($workOrders as $wo)
                                            <option value="{{ $wo->id }}" {{ $selectedWoId == $wo->id ? 'selected' : '' }}>
                                                {{ $wo->kode_wo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-3">
                            <span class="badge rounded-circle me-1 text-white" style="background-color: #4f46e5; padding: 5px 9px;">2</span> 
                            Rekap Hasil Produksi Fisik
                        </h6>

                        @if($selectedWoId)
                            <div class="table-responsive rounded-3 border">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light text-muted small text-uppercase fw-bold">
                                        <tr>
                                            <th class="ps-3">Nama Produk Target</th>
                                            <th class="text-center" width="20%">Sisa Target WO</th>
                                            <th class="text-center" width="25%">Hasil Nyata Hari Ini</th>
                                            <th class="text-center pe-3" width="20%">Status Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($items->isEmpty())
                                            <tr>
                                                <td colspan="4" class="text-center text-success py-5 bg-light-subtle">
                                                    <div class="py-2">
                                                        <i class="bi bi-check2-circle display-5 text-success d-block mb-2"></i>
                                                        <h6 class="fw-bold text-dark mb-1">Semua Item Sudah Terpenuhi</h6>
                                                        <p class="small text-muted mb-0">Seluruh target produk pada Work Order ini sudah selesai dicicil 100%.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @else
                                            @foreach($items as $index => $item)
                                                <tr>
                                                    <td class="ps-3">
                                                        <input type="hidden" name="produk_id[]" value="{{ $item->produk_id }}">
                                                        <strong class="text-dark d-block" style="font-size: 0.95rem;">
                                                            {{ $item->produk?->nama ?? 'Produk Tidak Terdefinisi' }}
                                                        </strong>
                                                        <small class="text-muted" style="font-size: 0.78rem;">
                                                            Target Awal: <span class="fw-semibold">{{ (int) $item->total_target }} Pcs</span> | Sudah Jadi: <span class="fw-semibold text-indigo">{{ (int) $item->sudah_diproduksi }} Pcs</span>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning-subtle text-warning-dark border border-warning-subtle fs-6 px-3">
                                                            Sisa: {{ (int) $item->sisa_target }} Pcs
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="input-group mx-auto shadow-sm rounded-3" style="max-width: 170px;">
                                                            <input type="number" step="any" name="qty_hasil[]" value="{{ (int) $item->sisa_target }}" class="form-control text-center fw-bold qty-hasil border-primary-subtle shadow-none" min="0" data-target="{{ (int) $item->sisa_target }}" data-index="{{ $index }}" required>
                                                            <span class="input-group-text bg-white text-muted small">Pcs</span>
                                                        </div>
                                                    </td>
                                                    <td class="text-center pe-3">
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle selisih-produksi" id="selisih-{{ $index }}">
                                                            Sesuai Sisa Target
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            @if($items->isNotEmpty())
                                <div class="mt-4 text-end">
                                    <button type="submit" class="btn text-white fw-semibold rounded-3 px-4 py-2.5 shadow-sm" style="background-color: #4f46e5;">
                                        <i class="bi bi-cloud-check-fill me-1.5"></i> Simpan Hasil Produksi & Potong FIFO
                                    </button>
                                </div>
                            @endif

                        @else
                            <div class="text-center text-muted py-5 border border-dashed rounded-3 bg-light-subtle">
                                <div class="py-3">
                                    <i class="bi bi-layers-half text-secondary opacity-50 display-5 d-block mb-3"></i>
                                    <h6 class="fw-bold text-dark mb-1">Menunggu Pemilihan Work Order</h6>
                                    <p class="small text-muted mb-0">Silakan tentukan nomor dokumen Work Order pada Langkah 1 untuk memuat daftar item produk.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = document.querySelectorAll('.qty-hasil');

            // Fungsi inti kalkulator selisih berdasarkan sisa target baru
            function hitungSelisih(input) {
                const targetSisa = parseFloat(input.dataset.target);
                const hasilInput = parseFloat(input.value) || 0;
                const index = input.dataset.index;

                const badge = document.getElementById('selisih-' + index);
                if (!badge) return;

                const selisih = hasilInput - targetSisa;

                // Bersihkan semua class bawaan Bootstrap badge agar tidak tumpang tindih
                badge.classList.remove(
                    'bg-success-subtle', 'text-success', 'border-success-subtle',
                    'bg-warning-subtle', 'text-warning-dark', 'border-warning-subtle',
                    'bg-danger-subtle', 'text-danger', 'border-danger-subtle'
                );

                if (selisih === 0) {
                    badge.classList.add('bg-success-subtle', 'text-success', 'border-success-subtle');
                    badge.innerText = 'Sesuai Sisa Target';
                } else if (selisih < 0) {
                    badge.classList.add('bg-warning-subtle', 'text-warning-dark', 'border-warning-subtle');
                    badge.innerText = 'Kurang ' + Math.abs(selisih) + ' Pcs';
                } else {
                    badge.classList.add('bg-danger-subtle', 'text-danger', 'border-danger-subtle');
                    badge.innerText = 'Lebih ' + selisih + ' Pcs';
                }
            }

            // Daftarkan fungsi ke setiap input barang jadi
            inputs.forEach(function (input) {
                // Panggil sekali di awal agar badge langsung kalkulasi (karena default terisi sisa_target)
                hitungSelisih(input);

                // Pantau setiap ketikan angka operator gudang
                input.addEventListener('input', function () {
                    hitungSelisih(this);
                });
            });
        });
    </script>
</x-app-layout>