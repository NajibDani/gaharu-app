<x-app-layout>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-success text-white p-3">
                <h5 class="mb-0 font-weight-bold"><i class="fas fa-calculator mr-2"></i> Laporan Harga Pokok Produksi / HPP (Akuntansi)</h5>
            </div>
            <div class="card-body bg-white text-dark">
                <form action="{{ route('laporan.hpp') }}" method="GET" class="row align-items-end g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label font-weight-bold text-secondary">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label font-weight-bold text-secondary">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-6 d-flex gap-2">
                        <button type="submit" class="btn btn-success shadow-sm px-4">
                            <i class="fas fa-search-dollar mr-1"></i> Filter Keuangan
                        </button>
                        <a href="{{ route('laporan.hpp', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success shadow-sm px-4">
                            📊 Export Excel
                        </a>
                        <a href="{{ route('laporan.hpp', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-danger shadow-sm px-4">
                            📕 Export PDF
                        </a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle text-center mb-0" style="color: #212529;">
                        <thead class="bg-light text-dark font-weight-bold">
                            <tr style="background-color: #f8f9fa;">
                                <th style="width: 12%;">Kode Barang</th>
                                <th class="text-left">Nama Produk Jadi</th>
                                <th style="width: 10%;">Tipe</th>
                                <th style="width: 18%;">Total Qty</th>
                                <th style="width: 22%;">Total Nilai HPP (BBB + BTKL + BOP)</th>
                                <th style="width: 22%;">Rata-rata HPP / Satuan</th>
                                <th style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $grandTotalHpp = 0; @endphp
                            @forelse($laporanHpp as $row)
                            @php 
                                $hppPerSatuan = $row->total_qty > 0 ? ($row->total_hpp / $row->total_qty) : 0;
                                $grandTotalHpp += $row->total_hpp;
                            @endphp
                            <tr>
                                <td class="align-middle font-weight-bold text-secondary">{{ $row->kode_barang }}</td>
                                <td class="align-middle text-left text-dark font-weight-bold">{{ $row->nama_produk }}</td>
                                <td class="align-middle">
                                    @if(strtoupper($row->tipe ?? 'B2B') === 'POS')
                                        <span class="badge bg-primary text-white">POS</span>
                                    @else
                                        <span class="badge bg-success text-white">B2B</span>
                                    @endif
                                </td>
                                <td class="align-middle text-dark font-weight-bold">
                                    {{ number_format($row->total_qty, 0, ',', '.') }} {{ $row->satuan ?? 'Pcs' }}
                                </td>
                                <td class="align-middle text-right text-danger font-weight-bold" style="font-size: 1.05em;">
                                    Rp {{ number_format($row->total_hpp, 2, ',', '.') }}
                                </td>
                                <td class="align-middle text-right text-info font-weight-bold" style="font-size: 1.05em;">
                                    Rp {{ number_format($hppPerSatuan, 2, ',', '.') }} / {{ $row->satuan ?? 'Pcs' }}
                                </td>
                                <td class="align-middle">
                                    <button class="btn btn-sm btn-info text-white shadow-sm btn-detail-hpp" data-id="{{ $row->produk_id }}" title="Lihat Detail Komponen HPP">
                                        <i class="fas fa-info-circle"></i> Detail
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Tidak ada perputaran HPP produksi pada periode ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($laporanHpp->count() > 0)
                        <tfoot>
                            <tr style="background-color: #f8f9fa;">
                                <th colspan="4" class="text-right text-dark font-weight-bold align-middle">GRAND TOTAL BIAYA HPP:</th>
                                <th class="text-right text-danger font-weight-bold align-middle" style="font-size: 1.15em;">
                                    Rp {{ number_format($grandTotalHpp, 2, ',', '.') }}
                                </th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Resep HPP -->
    <div class="modal fade" id="hppDetailModal" tabindex="-1" aria-labelledby="hppDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold" id="hppDetailModalLabel">
                        <i class="fas fa-calculator mr-2"></i> Detail Resep HPP
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-dark" id="hppDetailModalBody">
                    <!-- Dynamic content will be injected here -->
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4 shadow-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const detailButtons = document.querySelectorAll('.btn-detail-hpp');
            const modal = new bootstrap.Modal(document.getElementById('hppDetailModal'));
            const modalTitle = document.getElementById('hppDetailModalLabel');
            const modalBody = document.getElementById('hppDetailModalBody');

            detailButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const produkId = this.getAttribute('data-id');
                    modalBody.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Mengambil detail resep...</p>
                        </div>
                    `;
                    modal.show();

                    fetch(`/laporan-produksi/hpp-recipe-detail?produk_id=${produkId}`)
                        .then(res => {
                            if (!res.ok) {
                                return res.json().then(err => { throw new Error(err.error || 'Gagal memuat resep.'); });
                            }
                            return res.json();
                        })
                        .then(data => {
                            modalTitle.innerHTML = `<i class="fas fa-calculator mr-2"></i> Detail Resep HPP: ${data.nama_produk} (${data.kode_barang})`;
                            
                            let ingredientsHtml = '';
                            if (data.ingredients.length === 0) {
                                ingredientsHtml = `
                                    <tr>
                                        <td colspan="6" class="text-muted text-center py-3">Tidak ada bahan baku dalam resep ini.</td>
                                    </tr>
                                `;
                            } else {
                                data.ingredients.forEach((ing, index) => {
                                    ingredientsHtml += `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td class="text-left font-weight-bold">${ing.nama_bahan} <span class="text-muted small">(${ing.kode_bahan})</span></td>
                                            <td>${numberFormat(ing.qty_resep, 2)}</td>
                                            <td>${ing.satuan}</td>
                                            <td class="text-right">Rp ${numberFormat(ing.harga_satuan, 2)}</td>
                                            <td class="text-right font-weight-bold text-success">Rp ${numberFormat(ing.total_harga, 2)}</td>
                                        </tr>
                                    `;
                                });
                            }

                            modalBody.innerHTML = `
                                <div class="mb-3 p-3 bg-light rounded d-flex justify-content-between align-items-center" style="border-left: 5px solid #28a745;">
                                    <div>
                                        <span class="text-secondary font-weight-bold">Target Output Resep:</span>
                                        <span class="badge bg-success fs-6 ml-2 px-3 py-2">${numberFormat(data.output_qty, 2)} ${data.satuan_output}</span>
                                    </div>
                                </div>

                                <h6 class="font-weight-bold mb-2 text-success"><i class="fas fa-seedling mr-1"></i> Rincian Bahan Baku (BBB)</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-bordered align-middle text-center mb-0">
                                        <thead class="bg-light text-dark font-weight-bold">
                                            <tr>
                                                <th style="width: 5%;">No</th>
                                                <th class="text-left">Bahan Baku</th>
                                                <th style="width: 15%;">Qty Resep</th>
                                                <th style="width: 15%;">Satuan</th>
                                                <th style="width: 20%;" class="text-right">Harga Satuan</th>
                                                <th style="width: 25%;" class="text-right">Subtotal Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${ingredientsHtml}
                                        </tbody>
                                    </table>
                                </div>

                                <h6 class="font-weight-bold mb-2 text-success"><i class="fas fa-calculator mr-1"></i> Alokasi Biaya & HPP</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle text-center mb-0">
                                        <thead class="bg-light font-weight-bold text-dark">
                                            <tr>
                                                <th class="text-left">Komponen Biaya</th>
                                                <th style="width: 45%;" class="text-right">Biaya Per Batch (${numberFormat(data.output_qty, 2)} ${data.satuan_output})</th>
                                                <th style="width: 45%;" class="text-right">Biaya Per Unit (1 ${data.satuan_output})</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-left font-weight-bold">1. Bahan Baku (BBB) <span class="text-muted small">(100%)</span></td>
                                                <td class="text-right font-weight-bold">Rp ${numberFormat(data.summary.bbb, 2)}</td>
                                                <td class="text-right font-weight-bold">Rp ${numberFormat(data.summary.bbb_per_unit, 2)}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-left font-weight-bold">2. Tenaga Kerja (BTKL) <span class="text-muted small">(20% dari BBB)</span></td>
                                                <td class="text-right text-info font-weight-bold">Rp ${numberFormat(data.summary.btkl, 2)}</td>
                                                <td class="text-right text-info font-weight-bold">Rp ${numberFormat(data.summary.btkl_per_unit, 2)}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-left font-weight-bold">3. Overhead (BOP) <span class="text-muted small">(10% dari BBB)</span></td>
                                                <td class="text-right text-warning font-weight-bold">Rp ${numberFormat(data.summary.bop, 2)}</td>
                                                <td class="text-right text-warning font-weight-bold">Rp ${numberFormat(data.summary.bop_per_unit, 2)}</td>
                                            </tr>
                                            <tr class="table-success font-weight-bold" style="background-color: #d4edda;">
                                                <td class="text-left text-success font-weight-bold">TOTAL HPP (BBB + BTKL + BOP)</td>
                                                <td class="text-right text-danger font-weight-bold" style="font-size: 1.15em;">Rp ${numberFormat(data.summary.total_hpp, 2)}</td>
                                                <td class="text-right text-danger font-weight-bold" style="font-size: 1.15em;">Rp ${numberFormat(data.summary.total_hpp_per_unit, 2)}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            `;
                        })
                        .catch(err => {
                            modalBody.innerHTML = `
                                <div class="alert alert-danger mb-0">
                                    <i class="fas fa-exclamation-triangle mr-2"></i> ${err.message}
                                </div>
                            `;
                        });
                });
            });

            function numberFormat(val, decimals = 2) {
                let number = parseFloat(val);
                if (isNaN(number)) return '0';
                return number.toLocaleString('id-ID', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }
        });
    </script>
</x-app-layout>