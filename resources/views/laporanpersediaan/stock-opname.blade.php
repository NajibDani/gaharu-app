<x-app-layout>
    <x-slot name="header">Laporan Stock Opname</x-slot>

    <div class="container-fluid">

        {{-- ── FILTER ── --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('laporan.stock-opname') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">DARI TANGGAL</label>
                        <input type="date" name="dari" class="form-control form-control-sm" value="{{ request('dari') }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">SAMPAI TANGGAL</label>
                        <input type="date" name="sampai" class="form-control form-control-sm" value="{{ request('sampai') }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">GUDANG</label>
                        <select name="gudang_id" id="report_gudang_id" class="form-select form-select-sm">
                            <option value="">Semua Gudang</option>
                            @foreach($gudangs as $g)
                                <option value="{{ $g->id }}" {{ request('gudang_id') == $g->id ? 'selected' : '' }}>{{ $g->nama }} ({{ $g->kategori }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2" id="report_divisi_container" style="{{ !request('divisi_id') && !request('gudang_id') ? 'display:none;' : '' }}">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">DIVISI</label>
                        <select name="divisi_id" id="report_divisi_id" class="form-select form-select-sm">
                            <option value="">Semua Divisi</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold text-muted" style="font-size:12px;">STATUS</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            <option value="draft"    {{ request('status') === 'draft'    ? 'selected' : '' }}>Draft</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #d88656; border: none;">
                            <i class="bi bi-search me-1"></i> Tampilkan
                        </button>
                        <a href="{{ route('laporan.stock-opname', array_merge(request()->all(), ['format'=>'excel'])) }}"
                           class="btn btn-sm text-white" style="background-color: #606060; border: none;">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </a>
                        <a href="{{ route('laporan.stock-opname', array_merge(request()->all(), ['format'=>'pdf'])) }}"
                           class="btn btn-sm text-white" style="background-color: #606060; border: none;">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── SUMMARY CARDS ── --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#d88656; color:white;">
                    <div class="card-body">
                        <div style="font-size:11px; opacity:.9; text-transform:uppercase; letter-spacing:1px;">Total Opname</div>
                        <div class="fw-bold mt-1" style="font-size:28px;">{{ $totalOpname }}</div>
                        <div style="font-size:12px; opacity:.9;">dokumen opname</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#d1e7dd;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#0a3622; text-transform:uppercase; letter-spacing:1px;">Sudah Approved</div>
                        <div class="fw-bold mt-1" style="font-size:28px; color:#0a3622;">{{ $totalApproved }}</div>
                        <div style="font-size:12px; color:#0a3622;">dokumen final</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#fff3cd;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#856404; text-transform:uppercase; letter-spacing:1px;">Total Selisih Qty</div>
                        <div class="fw-bold mt-1" style="font-size:28px; color:#856404;">{{ number_format($totalSelisih, 2) }}</div>
                        <div style="font-size:12px; color:#856404;">unit (absolut)</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm" style="background:#f8d7da;">
                    <div class="card-body">
                        <div style="font-size:11px; color:#842029; text-transform:uppercase; letter-spacing:1px;">Nilai Selisih</div>
                        <div class="fw-bold mt-1" style="font-size:20px; color:#842029;">
                            Rp {{ number_format($totalNilaiSelisih, 0, ',', '.') }}
                        </div>
                        <div style="font-size:12px; color:#842029;">estimasi kerugian/keuntungan</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TABEL OPNAME ── --}}
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="px-4 py-3" style="border-bottom:1px solid #eadfd4;">
                    <span class="fw-bold" style="color:#d88656;">Rincian Stock Opname</span>
                    <span class="text-muted ms-2" style="font-size:13px;">{{ $data->count() }} dokumen</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead style="background-color: #d88656; color: white;">
                            <tr>
                                <th style="background-color: #d88656; color: white;" class="px-4">Dokumen</th>
                                <th style="background-color: #d88656; color: white;">Gudang & Divisi</th>
                                <th style="background-color: #d88656; color: white;">Barang</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Sistem</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Fisik</th>
                                <th style="background-color: #d88656; color: white;" class="text-center">Selisih</th>
                                <th style="background-color: #d88656; color: white;" class="text-end">Nilai Selisih</th>
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
                                            </td>
                                            <td class="text-center fw-semibold">
                                                <div>{{ number_format($detail->stok_fisik, 2) }} <span class="text-muted small">{{ $satuan }}</span></div>
                                                @if($hasKonv)
                                                    <div class="text-primary small" style="font-size: 0.75rem;">
                                                        ≈ {{ number_format($detail->stok_fisik / $konv, 2) }} {{ $satuanBeli }}
                                                    </div>
                                                @endif
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
                                            <td class="text-end fw-semibold {{ $detail->nilai_selisih < 0 ? 'text-danger' : ($detail->nilai_selisih > 0 ? 'text-success' : '') }}">
                                                Rp {{ number_format($detail->nilai_selisih, 0, ',', '.') }}
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
                    </table>
                </div>
            </div>
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