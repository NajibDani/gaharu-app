<x-app-layout>
    <x-slot name="header">Laporan Stock Opname</x-slot>

    <div class="container-fluid">

        {{-- ── FILTER ── --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.stock-opname') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">DARI TANGGAL</label>
                        <input type="date" name="dari" class="form-control form-control-sm" value="{{ request('dari') }}">
                    </div>
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">SAMPAI TANGGAL</label>
                        <input type="date" name="sampai" class="form-control form-control-sm" value="{{ request('sampai') }}">
                    </div>
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">GUDANG</label>
                        <select name="gudang_id" id="report_gudang_id" class="form-select form-select-sm">
                            <option value="">Semua Gudang</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>{{ $g->nama }} ({{ $g->kategori }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-2" id="report_divisi_container" style="{{ !request('divisi_id') && !request('gudang_id') ? 'display:none;' : '' }}">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">DIVISI</label>
                        <select name="divisi_id" id="report_divisi_id" class="form-select form-select-sm">
                            <option value="">Semua Divisi</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">STATUS</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            <option value="draft"    {{ request('status') === 'draft'    ? 'selected' : '' }}>Draft</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">FILTER BARANG</label>
                        <select name="hanya_selisih" class="form-select form-select-sm">
                            <option value="" {{ !request('hanya_selisih') ? 'selected' : '' }}>Semua Barang</option>
                            <option value="1" {{ request('hanya_selisih') == '1' ? 'selected' : '' }}>Hanya Berselisih (≠ 0)</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end mt-2">
                        <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #d88656; border: none;">
                            <i class="bi bi-search me-1"></i> Tampilkan
                        </button>
                        @if(request()->hasAny(['dari', 'sampai', 'gudang_id', 'divisi_id', 'status', 'hanya_selisih']))
                            <a href="{{ route('laporan.stock-opname') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset
                            </a>
                        @endif
                        <a href="{{ route('laporan.stock-opname', array_merge(request()->all(), ['format'=>'excel'])) }}"
                           class="btn btn-sm text-white" style="background-color: #606060; border: none;" title="Export Excel">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
                        </a>
                        <a href="{{ route('laporan.stock-opname', array_merge(request()->all(), ['format'=>'pdf'])) }}"
                           class="btn btn-sm text-white" style="background-color: #606060; border: none;" title="Export PDF">
                            <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── SUMMARY CARDS ── --}}
        {{-- Row 1: Dokumen & Selisih Qty --}}
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm" style="background:#d88656; color:white;">
                    <div class="card-body">
                        <div style="font-size:11px; opacity:.9; text-transform:uppercase; letter-spacing:1px;"><i class="bi bi-file-earmark-text me-1"></i>Total Opname</div>
                        <div class="fw-bold mt-1" style="font-size:26px;">{{ $totalOpname }}</div>
                        <div style="font-size:12px; opacity:.9;">dokumen opname</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm" style="background:#d1e7dd;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#0a3622; text-transform:uppercase; letter-spacing:1px;"><i class="bi bi-check-circle me-1"></i>Sudah Approved</div>
                        <div class="fw-bold mt-1" style="font-size:26px; color:#0a3622;">{{ $totalApproved }}</div>
                        <div style="font-size:12px; color:#0a3622;">dokumen final</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm" style="background:#fff3cd;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#856404; text-transform:uppercase; letter-spacing:1px;"><i class="bi bi-arrow-left-right me-1"></i>Total Selisih Qty</div>
                        <div class="fw-bold mt-1" style="font-size:26px; color:#856404;">{{ number_format($totalSelisih, 2) }}</div>
                        <div style="font-size:12px; color:#856404;">unit (absolut)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Nilai Finansial Inventory (Sistem, Riil, Selisih) --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm" style="background:#e0f2fe;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#0369a1; text-transform:uppercase; letter-spacing:1px;">
                            <i class="bi bi-cpu me-1"></i>Total Nominal Inventory Sistem
                        </div>
                        <div class="fw-bold mt-1" style="font-size:22px; color:#0369a1;">
                            Rp {{ number_format($totalNominalSistem, 0, ',', '.') }}
                        </div>
                        <div style="font-size:12px; color:#0369a1; opacity:0.9;">nilai persediaan stok sistem</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 border-0 shadow-sm" style="background:#dcfce7;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#15803d; text-transform:uppercase; letter-spacing:1px;">
                            <i class="bi bi-box-seam me-1"></i>Total Nominal Inventory Riil
                        </div>
                        <div class="fw-bold mt-1" style="font-size:22px; color:#15803d;">
                            Rp {{ number_format($totalNominalRiil, 0, ',', '.') }}
                        </div>
                        <div style="font-size:12px; color:#15803d; opacity:0.9;">nilai persediaan stok fisik riil</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                @php
                    $isSurplus  = $totalNominalSelisih > 0;
                    $isShortage = $totalNominalSelisih < 0;
                    $cardBg     = $isSurplus ? '#d1e7dd' : ($isShortage ? '#f8d7da' : '#f1f5f9');
                    $cardText   = $isSurplus ? '#0f5132' : ($isShortage ? '#842029' : '#475569');
                    $prefixSign = $isSurplus ? '+' : ($isShortage ? '-' : '');
                @endphp
                <div class="card h-100 border-0 shadow-sm" style="background:{{ $cardBg }};">
                    <div class="card-body">
                        <div style="font-size:11px; color:{{ $cardText }}; text-transform:uppercase; letter-spacing:1px;">
                            <i class="bi bi-cash-stack me-1"></i>Nominal Selisih
                        </div>
                        <div class="fw-bold mt-1" style="font-size:22px; color:{{ $cardText }};">
                            {{ $prefixSign }}Rp {{ number_format(abs($totalNominalSelisih), 0, ',', '.') }}
                        </div>
                        <div style="font-size:12px; color:{{ $cardText }};">
                            @if($isSurplus)
                                surplus nilai inventory (+)
                            @elseif($isShortage)
                                shortage / penyusutan nilai inventory (-)
                            @else
                                cocok / balance (selisih Rp 0)
                            @endif
                            @if($totalNilaiSelisih > 0 && abs($totalNominalSelisih) != $totalNilaiSelisih)
                                <span class="d-block small mt-1 opacity-75" style="font-size:11px;">
                                    (Total selisih absolut: Rp {{ number_format($totalNilaiSelisih, 0, ',', '.') }})
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TABEL OPNAME ── --}}
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="px-4 py-3 d-flex justify-content-between align-items-center" style="border-bottom:1px solid #eadfd4;">
                    <div>
                        <span class="fw-bold" style="color:#d88656;">Rincian Stock Opname</span>
                        <span class="text-muted ms-2" style="font-size:13px;">
                            @if($data->total() > 0)
                                Menampilkan {{ $data->firstItem() }} - {{ $data->lastItem() }} dari {{ $data->total() }} dokumen
                            @else
                                0 dokumen
                            @endif
                        </span>
                    </div>
                    @if(request('hanya_selisih') == '1')
                        <span class="badge bg-warning text-dark"><i class="bi bi-filter me-1"></i>Hanya Menampilkan Barang Berselisih (≠ 0)</span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Dokumen</th>
                                <th style="background-color: #d88656; color: white;">Gudang & Divisi</th>
                                <th style="background-color: #d88656; color: white;">Barang</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Sistem (Qty & Nilai)</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Fisik / Riil (Qty & Nilai)</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Selisih Qty</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Nominal Selisih</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $opname)
                                    @foreach($opname->details as $detail)
                                        @php
                                            $konv = (float) ($detail->barang->konversi_pembelian ?? 1);
                                            $hasKonv = !empty($detail->barang->satuan_pembelian) && $konv > 1;
                                            $satuan = $detail->barang->satuan ?? 'pcs';
                                            $satuanBeli = $detail->barang->satuan_pembelian ?? '';
                                            $nomSistem = $detail->nominal_sistem ?? 0;
                                            $nomFisik  = $detail->nominal_fisik ?? 0;
                                            $nomSel    = $detail->nominal_selisih ?? 0;
                                        @endphp
                                        <tr>
                                            <td class="px-4">
                                                <div class="fw-semibold" style="color:#d88656; font-size:12px;">{{ $opname->kode_opname }}</div>
                                                <div class="text-muted" style="font-size:11px;">{{ \Carbon\Carbon::parse($opname->tanggal)->format('d M Y') }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark">{{ $opname->gudang->nama ?? '-' }}</div>
                                                @if($opname->divisi)
                                                    <span class="badge bg-light text-primary border border-primary-subtle" style="font-size: 0.72rem;">
                                                        <i class="bi bi-diagram-3 me-1"></i>{{ $opname->divisi->nama }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $detail->barang->nama ?? '-' }}</div>
                                                @if($hasKonv)
                                                    <small class="text-primary font-monospace" style="font-size: 0.72rem;">
                                                        1 {{ $satuanBeli }} = {{ number_format($konv, 0, ',', '.') }} {{ $satuan }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div>{{ number_format($detail->stok_sistem, 2) }} <span class="text-muted small">{{ $satuan }}</span></div>
                                                @if($hasKonv)
                                                    <div class="text-primary small" style="font-size: 0.75rem;">
                                                        ≈ {{ number_format($detail->stok_sistem / $konv, 2) }} {{ $satuanBeli }}
                                                    </div>
                                                @endif
                                                <div class="badge bg-light text-primary border border-primary-subtle mt-1" style="font-size: 0.72rem;" title="Nominal Inventory Sistem">
                                                    Rp {{ number_format($nomSistem, 0, ',', '.') }}
                                                </div>
                                            </td>
                                            <td class="text-center fw-semibold">
                                                <div>{{ number_format($detail->stok_fisik, 2) }} <span class="text-muted small">{{ $satuan }}</span></div>
                                                @if($hasKonv)
                                                    <div class="text-primary small" style="font-size: 0.75rem;">
                                                        ≈ {{ number_format($detail->stok_fisik / $konv, 2) }} {{ $satuanBeli }}
                                                    </div>
                                                @endif
                                                <div class="badge bg-light text-success border border-success-subtle mt-1" style="font-size: 0.72rem;" title="Nominal Inventory Riil / Fisik">
                                                    Rp {{ number_format($nomFisik, 0, ',', '.') }}
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                @if($detail->selisih < 0)
                                                    <span class="text-danger fw-bold">{{ number_format($detail->selisih, 2) }} {{ $satuan }}</span>
                                                    @if($hasKonv)
                                                        <div class="text-danger small font-monospace" style="font-size: 0.75rem;">
                                                            ({{ number_format($detail->selisih / $konv, 2) }} {{ $satuanBeli }})
                                                        </div>
                                                    @endif
                                                @elseif($detail->selisih > 0)
                                                    <span class="text-success fw-bold">+{{ number_format($detail->selisih, 2) }} {{ $satuan }}</span>
                                                    @if($hasKonv)
                                                        <div class="text-success small font-monospace" style="font-size: 0.75rem;">
                                                            (+{{ number_format($detail->selisih / $konv, 2) }} {{ $satuanBeli }})
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">0 {{ $satuan }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold {{ $nomSel < 0 ? 'text-danger' : ($nomSel > 0 ? 'text-success' : 'text-muted') }}">
                                                    {{ $nomSel > 0 ? '+' : ($nomSel < 0 ? '-' : '') }}Rp {{ number_format(abs($nomSel), 0, ',', '.') }}
                                                </div>
                                                @if(!empty($detail->unit_price) && $detail->unit_price > 0)
                                                    <div class="text-muted small" style="font-size: 0.7rem;" title="Harga Satuan Dasar">
                                                        @ Rp {{ number_format($detail->unit_price, 2, ',', '.') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($opname->status === 'approved')
                                                    <span class="badge bg-success">Approved</span>
                                                @else
                                                    <span class="badge bg-secondary">Draft</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        Tidak ada data stock opname.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($data->count() > 0)
                            <tfoot style="background-color: #fdfaf6; border-top: 2px solid #d88656;">
                                <tr class="fw-bold align-middle">
                                    <td colspan="3" class="text-end px-4 py-3" style="color: #8c4a1e;">
                                        TOTAL KESELURUHAN (SESUAI FILTER):
                                    </td>
                                    <td class="text-center py-3">
                                        <div class="small text-muted" style="font-size: 11px;">Nominal Sistem</div>
                                        <div class="text-primary fw-bold" style="font-size: 13px;">Rp {{ number_format($totalNominalSistem, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="text-center py-3">
                                        <div class="small text-muted" style="font-size: 11px;">Nominal Riil</div>
                                        <div class="text-success fw-bold" style="font-size: 13px;">Rp {{ number_format($totalNominalRiil, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="text-center py-3">
                                        <div class="small text-muted" style="font-size: 11px;">Selisih Qty</div>
                                        <div class="text-dark fw-bold" style="font-size: 13px;">{{ number_format($totalSelisih, 2) }}</div>
                                    </td>
                                    <td class="text-end py-3">
                                        <div class="small text-muted" style="font-size: 11px;">Nominal Selisih</div>
                                        <div class="fw-bold {{ $totalNominalSelisih > 0 ? 'text-success' : ($totalNominalSelisih < 0 ? 'text-danger' : 'text-muted') }}" style="font-size: 13px;">
                                            {{ $totalNominalSelisih > 0 ? '+' : ($totalNominalSelisih < 0 ? '-' : '') }}Rp {{ number_format(abs($totalNominalSelisih), 0, ',', '.') }}
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @if($data->hasPages())
                <div class="card-footer bg-white py-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="text-muted small">
                        Halaman {{ $data->currentPage() }} dari {{ $data->lastPage() }} (Total {{ $data->total() }} Dokumen)
                    </div>
                    <div>
                        {{ $data->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gudangSelect = document.getElementById('report_gudang_id');
        const divisiContainer = document.getElementById('report_divisi_container');
        const divisiSelect = document.getElementById('report_divisi_id');
        const currentDivisiId = "{{ request('divisi_id') }}";

        function loadDivisi(gudangId, selectedId = null) {
            if (!gudangId) {
                divisiContainer.style.display = 'none';
                divisiSelect.innerHTML = '<option value="">Semua Divisi</option>';
                return;
            }

            fetch('/gudangs/' + gudangId + '/divisi')
                .then(res => res.json())
                .then(data => {
                    if (data.is_operasional && data.divisi && data.divisi.length > 0) {
                        divisiContainer.style.display = 'block';
                        let opts = '<option value="">Semua Divisi</option>';
                        data.divisi.forEach(d => {
                            let isSel = (selectedId && selectedId == d.id) ? 'selected' : '';
                            opts += `<option value="${d.id}" ${isSel}>${d.nama}</option>`;
                        });
                        divisiSelect.innerHTML = opts;
                    } else {
                        divisiContainer.style.display = 'none';
                        divisiSelect.innerHTML = '<option value="">Semua Divisi</option>';
                    }
                })
                .catch(() => {
                    divisiContainer.style.display = 'none';
                });
        }

        if (gudangSelect) {
            gudangSelect.addEventListener('change', function() {
                loadDivisi(this.value);
            });

            if (gudangSelect.value) {
                loadDivisi(gudangSelect.value, currentDivisiId);
            }
        }
    });
    </script>
</x-app-layout>