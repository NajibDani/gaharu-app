<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .card-form { border-radius: 16px; border: 1px solid #eaeaea; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .btn-custom-orange { background-color: #db7946; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px; }
        .btn-custom-orange:hover { background-color: #c06535; color: white; }
    </style>

    <div class="container py-4" style="margin-top: 5.5rem !important; max-width: 900px;">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">Input Hasil Produksi Central Kitchen</h4>
                <p class="text-muted small mb-0">Catat jumlah fisik barang setengah jadi / barang hasil produksi dapur pusat</p>
            </div>
            <a href="{{ route('ck-produksi.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 text-sm mb-4">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('ck-produksi.store-produksi') }}" method="POST">
            @csrf

            <div class="card card-form p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Pilih Work Order CK</h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-sm">Work Order Central Kitchen <span class="text-danger">*</span></label>
                        <select name="work_order_id" id="select-wo" class="form-select text-sm rounded-3" required>
                            <option value="">-- Pilih Work Order CK --</option>
                            @foreach($workOrders as $wo)
                                <option value="{{ $wo->id }}"
                                    data-divisi="{{ $wo->divisi_id ?? '' }}"
                                    {{ $selectedWoId == $wo->id ? 'selected' : '' }}>
                                    {{ $wo->kode_wo }} - {{ date('d/m/Y', strtotime($wo->tanggal_wo)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <input type="hidden" name="divisi_id" id="select-divisi" value="1">

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-sm">Tanggal Selesai Produksi <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_produksi" class="form-control text-sm rounded-3" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
            </div>

            @if($selectedWoId && count($items) > 0)
                <div class="card card-form p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Hasil Fisik Produksi</h6>

                    @if(!empty($hasMissingResep) && !empty($produkTanpaResep))
                        <div class="alert alert-danger border-danger d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                            <i class="bi bi-exclamation-octagon-fill fs-6 text-danger mt-1"></i>
                            <div class="w-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Resep Belum Diisi:</strong>
                                    <a href="{{ route('resep.create') }}" class="btn btn-danger btn-sm py-0 px-2 fw-semibold" style="font-size: 0.75rem;" target="_blank">
                                        <i class="bi bi-journal-plus me-1"></i> Isi Resep Sekarang
                                    </a>
                                </div>
                                <p class="mb-1 text-muted">Approval produksi nantinya tidak dapat dilakukan sebelum menu-menu berikut memiliki resep:</p>
                                <ul class="mb-0 ps-3">
                                    @foreach($produkTanpaResep as $ptr)
                                        <li><strong>{{ $ptr['nama_produk'] }}</strong> (Kode: {{ $ptr['kode_barang'] }})</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if(isset($isBahanSufficient) && !$isBahanSufficient && !empty($defisitBahan))
                        <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                            <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1"></i>
                            <div class="w-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Perhatian Ketersediaan Bahan Baku di Gudang Central Kitchen:</strong>
                                    <form action="{{ route('ck-produksi.kirim-bahan', $selectedWoId) }}" method="POST" class="d-inline" onsubmit="return confirm('Kirim permintaan bahan untuk WO ini ke Gudang?');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning text-dark btn-sm py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Minta Bahan Sekarang
                                        </button>
                                    </form>
                                </div>
                                <ul class="mb-0 ps-3 mt-1">
                                    @foreach($defisitBahan as $def)
                                        <li>{{ $def['nama'] }}: Tersedia <strong>{{ $def['stok'] }} {{ $def['satuan'] }}</strong> / Butuh <strong>{{ $def['butuh'] }} {{ $def['satuan'] }}</strong> (Kurang <span class="text-danger fw-bold">{{ $def['kurang'] }} {{ $def['satuan'] }}</span>)</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr class="text-secondary small">
                                    <th>NAMA BARANG / ITEM</th>
                                    <th class="text-center" style="width: 150px;">TARGET RENCANA</th>
                                    <th style="width: 200px;">QTY HASIL REALISASI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    @php
                                        $itemHasResep = $item->produk && $item->produk->resep_id && $item->produk->resep && $item->produk->resep->count() > 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="hidden" name="produk_id[]" value="{{ $item->produk_id }}">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark">{{ $item->produk->nama ?? 'Produk' }}</span>
                                                @if(!$itemHasResep)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-2" style="font-size: 0.7rem;">
                                                        <i class="bi bi-exclamation-circle me-1"></i>Belum Ada Resep
                                                    </span>
                                                @else
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-2" style="font-size: 0.7rem;">
                                                        <i class="bi bi-check-circle me-1"></i>Resep Siap
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold text-muted">
                                            <div>{{ number_format($item->total_target, 0, ',', '.') }} {{ $item->produk->satuan ?? '' }}</div>
                                            @if($item->produk && !empty($item->produk->satuan_pembelian) && floatval($item->produk->konversi_pembelian) > 1)
                                                @php
                                                    $konvVal = floatval($item->produk->konversi_pembelian);
                                                    $packTarget = $item->total_target / $konvVal;
                                                @endphp
                                                <div class="small text-primary font-monospace" style="font-size: 11px;">
                                                    ({{ number_format($packTarget, ($packTarget == intval($packTarget) ? 0 : 2), ',', '.') }} {{ strtoupper($item->produk->satuan_pembelian) }} @ {{ number_format($konvVal, 0, ',', '.') }} {{ $item->produk->satuan }})
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $hasKonv = $item->produk && !empty($item->produk->satuan_pembelian) && floatval($item->produk->konversi_pembelian) > 1;
                                                $konvVal = $hasKonv ? floatval($item->produk->konversi_pembelian) : 1;
                                                $satuanDasar = $item->produk->satuan ?? 'unit';
                                                $satuanKonv = $hasKonv ? strtoupper($item->produk->satuan_pembelian) : '';
                                            @endphp
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="any" min="0" name="qty_hasil[]" 
                                                    class="form-control text-sm fw-bold text-success text-end input-qty-hasil-create" 
                                                    value="{{ $item->sisa_target }}" 
                                                    data-konversi="{{ $konvVal }}"
                                                    data-satuan-dasar="{{ $satuanDasar }}"
                                                    data-satuan-konv="{{ $satuanKonv }}" required>
                                                @if($hasKonv)
                                                    <select name="satuan_input[]" class="form-select select-unit-create fw-bold" style="max-width: 90px;">
                                                        <option value="dasar">{{ strtoupper($satuanDasar) }}</option>
                                                        <option value="konversi">{{ $satuanKonv }}</option>
                                                    </select>
                                                @else
                                                    <input type="hidden" name="satuan_input[]" value="dasar">
                                                    <span class="input-group-text text-muted">{{ $satuanDasar }}</span>
                                                @endif
                                            </div>
                                            <div class="live-konversi-create small text-end mt-1 font-monospace" style="font-size: 11px; display: none;"></div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('ck-produksi.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                    <button type="submit" class="btn btn-custom-orange shadow-sm px-4">
                        Simpan Draft Hasil Produksi
                    </button>
                </div>
            @elseif($selectedWoId)
                <div class="alert alert-warning text-center rounded-3">Tidak ada item target pada Work Order yang dipilih.</div>
            @endif
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const woSelect = document.getElementById('select-wo');
            const divisiSelect = document.getElementById('select-divisi');

            // Saat WO dipilih, redirect agar item target ter-load + kirim divisi_id juga
            if (woSelect) {
                woSelect.addEventListener('change', function () {
                    const woId = this.value;
                    const divisiId = this.options[this.selectedIndex].getAttribute('data-divisi') || '';
                    const url = new URL(window.location.href);
                    url.searchParams.set('work_order_id', woId);
                    if (divisiId) url.searchParams.set('divisi_id', divisiId);
                    window.location.href = url.toString();
                });
            }

            // Auto-fill divisi berdasarkan query string (hasil redirect WO)
            if (divisiSelect) {
                const params = new URLSearchParams(window.location.search);
                const divisiFromUrl = params.get('divisi_id');
                if (divisiFromUrl) {
                    divisiSelect.value = divisiFromUrl;
                }
            }

            // Live calculation helper untuk input hasil produksi
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('input-qty-hasil-create')) {
                    updateLiveKonversiCreate(e.target);
                }
            });

            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('select-unit-create')) {
                    const row = e.target.closest('td');
                    const inputQty = row.querySelector('.input-qty-hasil-create');
                    if (inputQty) {
                        updateLiveKonversiCreate(inputQty);
                    }
                }
            });

            function updateLiveKonversiCreate(inputEl) {
                const container = inputEl.closest('td');
                if (!container) return;
                const unitSelect = container.querySelector('.select-unit-create');
                const infoBox = container.querySelector('.live-konversi-create');
                if (!infoBox) return;

                const konversi = parseFloat(inputEl.getAttribute('data-konversi') || 1);
                const satuanDasar = inputEl.getAttribute('data-satuan-dasar') || '';
                const satuanKonv = inputEl.getAttribute('data-satuan-konv') || '';
                const qtyVal = parseFloat(inputEl.value || 0);
                const unitVal = unitSelect ? unitSelect.value : 'dasar';

                if (konversi > 1 && satuanKonv && qtyVal > 0) {
                    if (unitVal === 'konversi') {
                        const totalGramasi = qtyVal * konversi;
                        infoBox.innerHTML = `= <strong class="text-primary">${totalGramasi.toLocaleString('id-ID')} ${satuanDasar}</strong> (@ ${konversi.toLocaleString('id-ID')} ${satuanDasar})`;
                        infoBox.style.display = 'block';
                    } else {
                        const totalPack = qtyVal / konversi;
                        const packFmt = (totalPack % 1 === 0) ? totalPack.toFixed(0) : totalPack.toFixed(2);
                        infoBox.innerHTML = `= <strong class="text-success">${packFmt} ${satuanKonv}</strong> (@ ${konversi.toLocaleString('id-ID')} ${satuanDasar})`;
                        infoBox.style.display = 'block';
                    }
                } else {
                    infoBox.style.display = 'none';
                }
            }
        });
    </script>
</x-app-layout>
