<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center py-0.5">
            <div>
                <h4 class="fw-bold m-0" style="color:#9c4f18; font-size: 1.25rem;">Dashboard Operasional</h4>
                <small class="text-muted" style="font-size: 0.7rem;">Ringkasan aktivitas ERP Gaharu</small>
            </div>
            @if(in_array(auth()->user()->role->nama ?? '', ['Kepala Outlet Gaharu', 'Direktur Keuangan', 'Super Admin', 'Administrator']))
                <a href="{{ route('dashboard.keuangan') }}" class="btn text-white fw-bold d-flex align-items-center gap-1.5 transition" style="background-color: #9c4f18; border-radius: 6px; font-size: 0.75rem; padding: 6px 12px;">
                    <i class="bi bi-wallet2"></i> Buka Dashboard Keuangan
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $activeCardsCount = 2;
        if ($hasPurchaseAccess) $activeCardsCount++;
        if ($hasB2bAccess) $activeCardsCount++;

        $cardColClass = match($activeCardsCount) {
            4 => 'col-md-3',
            3 => 'col-md-4',
            2 => 'col-md-6',
            default => 'col-md-12'
        };

        $row1ChartColClass = ($hasPosAccess && $hasB2bAccess) ? 'col-md-6' : 'col-md-12';
        $row2ChartColClass = ($hasPurchaseAccess && $hasProductionAccess) ? 'col-md-6' : 'col-md-12';
    @endphp

    <div class="container-fluid px-3 py-2">

        {{-- FILTER TANGGAL PERIODE --}}
        <div class="card border-0 shadow-sm mb-3" style="background: #fff; border-radius: 8px;">
            <div class="card-body p-2 px-3">
                <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-center">
                    <div class="col-auto d-flex align-items-center gap-1 text-secondary fw-bold" style="font-size: 0.75rem;">
                        <i class="bi bi-funnel-fill text-warning"></i> Filter Periode Dashboard:
                    </div>
                    <div class="col-auto d-flex align-items-center gap-1">
                        <label for="tgl_mulai" class="form-label m-0 text-muted" style="font-size: 0.7rem;">Dari:</label>
                        <input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control form-control-sm" value="{{ $startDate }}" style="font-size: 0.75rem; width: 140px;">
                    </div>
                    <div class="col-auto d-flex align-items-center gap-1">
                        <label for="tgl_selesai" class="form-label m-0 text-muted" style="font-size: 0.7rem;">Sampai:</label>
                        <input type="date" id="tgl_selesai" name="tgl_selesai" class="form-control form-control-sm" value="{{ $endDate }}" style="font-size: 0.75rem; width: 140px;">
                    </div>
                    <div class="col-auto d-flex gap-1">
                        <button type="submit" class="btn btn-sm text-white fw-bold" style="background:#9c4f18; font-size: 0.75rem; padding: 4px 12px;">
                            <i class="bi bi-search me-1"></i> Terapkan
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 0.75rem; padding: 4px 10px;" title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- BARIS 1: DYNAMIC MINI SUMMARY CARDS --}}
        <div class="row gx-3 mb-3">
            <div class="{{ $cardColClass }} mb-2 mb-md-0">
                <div class="card border-0 shadow-sm" style="border-left: 3px solid #9c4f18 !important; background: #fff; height: 100%;">
                    <div class="card-body p-2 px-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Nilai Inventory Saat Ini</div>
                        <h4 class="fw-bold m-0" style="color:#9c4f18; font-size: 1.2rem;">Rp {{ number_format($inventoryValue, 0, ',', '.') }}</h4>
                        <small class="text-muted opacity-75" style="font-size: 0.65rem;">Berdasarkan FIFO Gudang</small>
                    </div>
                </div>
            </div>

            @if($hasPurchaseAccess)
            <div class="{{ $cardColClass }} mb-2 mb-md-0">
                <div class="card border-0 shadow-sm" style="border-left: 3px solid #d88656 !important; background: #fff; height: 100%;">
                    <div class="card-body p-2 px-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Pembelian Bulan Ini</div>
                        <h4 class="fw-bold m-0" style="color:#d88656; font-size: 1.2rem;">Rp {{ number_format($pembelianBulanIni, 0, ',', '.') }}</h4>
                        <small class="text-muted" style="font-size: 0.65rem;">Total transaksi bulan berjalan</small>
                    </div>
                </div>
            </div>
            @endif

            @if($hasB2bAccess)
            <div class="{{ $cardColClass }} mb-2 mb-md-0">
                <div class="card border-0 shadow-sm" style="border-left: 3px solid #28a745 !important; background: #fff; height: 100%;">
                    <div class="card-body p-2 px-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Total Pesanan B2B</div>
                        <h4 class="fw-bold m-0" style="color:#28a745; font-size: 1.2rem;">{{ number_format($totalPesanan, 0) }} Pesanan</h4>
                        <small class="text-muted" style="font-size: 0.65rem;">Pesanan terdaftar di sistem</small>
                    </div>
                </div>
            </div>
            @endif

            <div class="{{ $cardColClass }}">
                <div class="card border-0 shadow-sm" style="border-left: 3px solid #17a2b8 !important; background: #fff; height: 100%;">
                    <div class="card-body p-2 px-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Total Varian Barang</div>
                        <h4 class="fw-bold m-0" style="color:#17a2b8; font-size: 1.2rem;">{{ number_format($totalProduk, 0) }} Item</h4>
                        <small class="text-muted" style="font-size: 0.65rem;">Katalog barang aktif</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- BARIS 2: DATA CHARTS & STATS --}}
        <div class="row gx-3">
            
            {{-- SEKTOR KIRI: CHART GRID --}}
            <div class="col-lg-8 d-flex flex-column gap-3">
                
                {{-- ROW CHARTS 1: POS & B2B --}}
                <div class="row gx-3">
                    @if($hasPosAccess)
                    <div class="{{ $row1ChartColClass }} mb-3 mb-md-0">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-1" style="color:#9c4f18; font-size: 0.8rem;">📈 Tren Penjualan POS (7 Hari Terakhir)</h6>
                                <div style="position: relative; height: 160px; width:100%;">
                                    <canvas id="grafikPos"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($hasB2bAccess)
                    <div class="{{ $row1ChartColClass }}">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-1" style="color:#28a745; font-size: 0.8rem;">💼 Tren Penjualan B2B (7 Hari Terakhir)</h6>
                                <div style="position: relative; height: 160px; width:100%;">
                                    <canvas id="grafikB2b"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- ROW CHARTS 2: PEMBELIAN & PRODUKSI --}}
                @if($hasPurchaseAccess || $hasProductionAccess)
                <div class="row gx-3">
                    @if($hasPurchaseAccess)
                    <div class="{{ $row2ChartColClass }} mb-3 mb-md-0">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-1" style="color:#d88656; font-size: 0.8rem;">🛒 Tren Pembelian Bahan (7 Hari Terakhir)</h6>
                                <div style="position: relative; height: 160px; width:100%;">
                                    <canvas id="grafikPembelian"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($hasProductionAccess)
                    <div class="{{ $row2ChartColClass }}">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-1" style="color:#17a2b8; font-size: 0.8rem;">⚙ Status Produksi (Work Order)</h6>
                                <div style="position: relative; height: 160px; width:100%;" class="d-flex align-items-center justify-content-center">
                                    <canvas id="grafikProduksi" style="max-height: 150px; max-width: 150px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- STATS SUMMARY (BAHAN & SUPPLIERS) --}}
                @if($hasPosAccess || $hasPurchaseAccess)
                <div class="row gx-3">
                    @if($hasPosAccess)
                    <div class="{{ ($hasPosAccess && $hasPurchaseAccess) ? 'col-sm-6' : 'col-12' }} mb-3 mb-sm-0">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-2 text-dark" style="font-size: 0.8rem;">📦 Kuantitas Bahan Terbanyak (Top 3)</h6>
                                <div class="table-responsive rounded border">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.72rem;">
                                        <thead style="background-color: #f8f9fa !important; border-bottom: 1px solid #dee2e6;">
                                            <tr>
                                                <th class="ps-2 py-1.5 fw-bold text-secondary" style="background: none; border: none;">Nama Bahan</th>
                                                <th class="pe-2 py-1.5 fw-bold text-secondary text-end" style="background: none; border: none;">Volume</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($bahanSeringDibeli as $bahan)
                                                <tr>
                                                    <td class="ps-2 py-1 fw-medium text-dark text-truncate" style="max-width: 130px;">{{ $bahan->nama }}</td>
                                                    <td class="pe-2 py-1 text-end fw-bold text-dark">
                                                        {{ number_format($bahan->total_qty, 0, ',', '.') }} <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ $bahan->satuan }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="text-center py-2 text-muted" style="font-size: 0.7rem;">Tidak ada data.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($hasPurchaseAccess)
                    <div class="{{ ($hasPosAccess && $hasPurchaseAccess) ? 'col-sm-6' : 'col-12' }}">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <h6 class="fw-bold mb-2 text-dark" style="font-size: 0.8rem;">🏆 Supplier Teratas (Top 3)</h6>
                                <div class="table-responsive rounded border">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.72rem;">
                                        <thead style="background-color: #f8f9fa !important; border-bottom: 1px solid #dee2e6;">
                                            <tr>
                                                <th class="ps-2 py-1.5 fw-bold text-secondary" style="background: none; border: none;">Supplier</th>
                                                <th class="pe-2 py-1.5 fw-bold text-secondary text-end" style="background: none; border: none;">Nominal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($supplierTeratas as $supplier)
                                                <tr>
                                                    <td class="ps-2 py-1 fw-medium text-dark text-truncate" style="max-width: 120px;">{{ $supplier->nama }}</td>
                                                    <td class="pe-2 py-1 text-end fw-bold text-success">
                                                        Rp {{ number_format($supplier->total_nominal, 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="text-center py-2 text-muted" style="font-size: 0.7rem;">Tidak ada data.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- SEKTOR KANAN: BARANG HAMPIR HABIS --}}
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="card border-0 shadow-sm h-100 d-flex flex-column justify-content-between">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold m-0" style="color:#dc3545; font-size: 0.8rem;">
                                ⚠ Stok Kritis (Maksimal 5)
                            </h6>
                            <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">Penting</span>
                        </div>

                        <div class="list-group list-group-flush border rounded border-bottom-0">
                            @forelse($barangHampirHabis as $item)
                                <div class="d-flex justify-content-between align-items-center p-2 border-bottom bg-white">
                                    <span class="text-dark fw-medium text-truncate small" style="max-width: 170px;" title="{{ $item->nama }}">
                                        {{ $item->nama }}
                                    </span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 font-monospace fw-bold" style="font-size: 0.7rem;">
                                        {{ number_format($item->jumlah, 0) }} {{ $item->satuan }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-success fw-medium small bg-white border-bottom">
                                    ✨ Semua stok barang aman
                                </div>
                            @endforelse
                        </div>
                    </div>
                    
                    @if($hasPurchaseAccess)
                    <div class="card-footer bg-transparent border-0 p-3 pt-0">
                        <a href="{{ url('/pembelian') }}" class="btn w-100 text-white fw-semibold py-1.5 transition shadow-sm d-flex align-items-center justify-content-center gap-1" style="background-color: #9c4f18; border-radius: 6px; font-size: 0.75rem;">
                            <i class="bi bi-cart-plus"></i> Buat Pesanan Pembelian (Restock)
                        </a>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- SCRIPTS GRAPH --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart Penjualan POS
        @if($hasPosAccess)
        const ctxPos = document.getElementById('grafikPos');
        new Chart(ctxPos, {
            type: 'line',
            data: {
                labels: @json($labelsPos),
                datasets: [{
                    data: @json($dataPos),
                    borderColor: '#9c4f18',
                    backgroundColor: 'rgba(156,79,24,0.05)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointBackgroundColor: '#9c4f18'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 9.5 } },
                        grid: { color: 'rgba(0, 0, 0, 0.03)' }
                    },
                    x: {
                        ticks: { font: { size: 9.5 } },
                        grid: { display: false }
                    }
                }
            }
        });
        @endif

        // Chart Penjualan B2B
        @if($hasB2bAccess)
        const ctxB2b = document.getElementById('grafikB2b');
        new Chart(ctxB2b, {
            type: 'bar',
            data: {
                labels: @json($labelsB2b),
                datasets: [{
                    data: @json($dataB2b),
                    backgroundColor: '#28a745',
                    borderRadius: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 9.5 } },
                        grid: { color: 'rgba(0, 0, 0, 0.03)' }
                    },
                    x: {
                        ticks: { font: { size: 9.5 } },
                        grid: { display: false }
                    }
                }
            }
        });
        @endif

        // Chart Pembelian
        @if($hasPurchaseAccess)
        const ctxPembelian = document.getElementById('grafikPembelian');
        new Chart(ctxPembelian, {
            type: 'line',
            data: {
                labels: @json($labelsPembelian),
                datasets: [{
                    data: @json($dataPembelian),
                    borderColor: '#d88656',
                    backgroundColor: 'rgba(216,134,86,0.05)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointBackgroundColor: '#d88656'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 9.5 } },
                        grid: { color: 'rgba(0, 0, 0, 0.03)' }
                    },
                    x: {
                        ticks: { font: { size: 9.5 } },
                        grid: { display: false }
                    }
                }
            }
        });
        @endif

        // Chart Produksi (Pie/Doughnut)
        @if($hasProductionAccess)
        const ctxProduksi = document.getElementById('grafikProduksi');
        new Chart(ctxProduksi, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($productionStatus)) !!},
                datasets: [{
                    data: {!! json_encode(array_values($productionStatus)) !!},
                    backgroundColor: ['#6c757d', '#ffc107', '#28a745', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 8.5 } }
                    }
                }
            }
        });
        @endif
    </script>
</x-app-layout>