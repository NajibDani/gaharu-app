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
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-sm">Work Order Central Kitchen <span class="text-danger">*</span></label>
                        <select name="work_order_id" class="form-select text-sm rounded-3" onchange="window.location.href='{{ route('ck-produksi.create-produksi') }}?work_order_id=' + this.value" required>
                            <option value="">-- Pilih Work Order CK --</option>
                            @foreach($workOrders as $wo)
                                <option value="{{ $wo->id }}" {{ $selectedWoId == $wo->id ? 'selected' : '' }}>
                                    {{ $wo->kode_wo }} - {{ date('d/m/Y', strtotime($wo->tanggal_wo)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-sm">Tanggal Selesai Produksi <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_produksi" class="form-control text-sm rounded-3" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
            </div>

            @if($selectedWoId && count($items) > 0)
                <div class="card card-form p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Hasil Fisik Produksi</h6>

                    @if(isset($isBahanSufficient) && !$isBahanSufficient && !empty($defisitBahan))
                        <div class="alert alert-warning border-warning d-flex align-items-start gap-2 p-2 rounded-3 mb-3 small">
                            <i class="bi bi-exclamation-triangle-fill fs-6 text-warning mt-1"></i>
                            <div>
                                <strong>Perhatian Ketersediaan Bahan Baku di Gudang Central Kitchen:</strong>
                                <ul class="mb-0 ps-3">
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
                                    <tr>
                                        <td>
                                            <input type="hidden" name="produk_id[]" value="{{ $item->produk_id }}">
                                            <span class="fw-bold text-dark">{{ $item->produk->nama ?? 'Produk' }}</span>
                                        </td>
                                        <td class="text-center fw-semibold text-muted">
                                            {{ number_format($item->total_target, 2) }} {{ $item->produk->satuan ?? '' }}
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.01" min="0.01" name="qty_hasil[]" class="form-control text-sm fw-bold text-success" value="{{ $item->sisa_target }}" required>
                                                <span class="input-group-text text-muted">{{ $item->produk->satuan ?? 'unit' }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('ck-produksi.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                    <button type="submit" class="btn btn-custom-orange shadow-sm px-4" {{ (isset($isBahanSufficient) && !$isBahanSufficient) ? 'disabled' : '' }} title="{{ (isset($isBahanSufficient) && !$isBahanSufficient) ? 'Stok bahan baku belum mencukupi' : '' }}">
                        Simpan Draft Hasil Produksi
                    </button>
                </div>
            @elseif($selectedWoId)
                <div class="alert alert-warning text-center rounded-3">Tidak ada item target pada Work Order yang dipilih.</div>
            @endif
        </form>
    </div>
</x-app-layout>
