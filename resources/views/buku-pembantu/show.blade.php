<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Detail Buku Besar Pembantu - {{ $entity->nama }}
        </h2>
    </x-slot>

    <style>
        :root {
            --brand: #e07a5f;
            --brand-soft: #fdf2f0;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .btn-back:hover { background: #f8fafc; }

        .breadcrumb { font-size: 13px; color: #64748b; margin-bottom: 20px; }

        .ledger-container {
            display: grid;
            grid-template-columns: 2.5fr 1fr;
            gap: 20px;
        }

        .ledger-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .ledger-header {
            background: var(--brand);
            color: white;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ledger-header h3 { font-size: 18px; font-weight: 700; margin: 0; }

        table.ledger-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.ledger-table th, table.ledger-table td { padding: 12px 18px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        table.ledger-table th { background: #f8fafc; font-weight: 600; color: #64748b; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }

        .ledger-footer {
            padding: 16px 24px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            border-top: 1px solid #e2e8f0;
            font-size: 15px;
        }

        .info-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            height: fit-content;
        }

        .status-icon-check {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 16px auto;
            font-size: 24px;
        }

        .icon-lunas { background: #dcfce7; color: #16a34a; }
        .icon-pending { background: #fee2e2; color: #dc2626; }
    </style>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis]) }}" class="btn-back">
                ← Kembali ke Daftar Akun
            </a>

            <div class="breadcrumb">
                Home / 
                @if(in_array($jenis, ['utang', 'um-pembelian'])) Pembelian @else Penjualan @endif / 
                Buku Pembantu / 
                {{ $entity->nama }}
            </div>

            <div class="ledger-container">
                <div class="ledger-table-card">
                    <div class="ledger-header">
                        <div>
                            <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase;">Buku Besar Pembantu</div>
                            <h3>{{ $entity->nama }}</h3>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; opacity: 0.85;">Kode Entitas</div>
                            <strong>NO. {{ $entity->id }}</strong>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>No. Ref</th>
                                    <th class="text-right">Debet</th>
                                    <th class="text-right">Kredit</th>
                                    <th class="text-right">Saldo</th>
                                    <th style="text-align: center; width: 130px;">Rincian Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mutasi as $row)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                                        <td>{{ $row->keterangan }}</td>
                                        <td><span class="font-mono text-xs text-gray-600">{{ $row->ref }}</span></td>
                                        <td class="text-right font-mono">{{ $row->debit > 0 ? 'Rp ' . number_format($row->debit, 0, ',', '.') : '-' }}</td>
                                        <td class="text-right font-mono">{{ $row->kredit > 0 ? 'Rp ' . number_format($row->kredit, 0, ',', '.') : '-' }}</td>
                                        <td class="text-right font-mono font-semibold">Rp {{ number_format($row->saldo_akumulasi, 0, ',', '.') }}</td>
                                        <td style="text-align: center;">
                                            @if(isset($row->pembelian_id) && $row->pembelian_id)
                                                <button type="button" class="btn btn-sm" onclick="showPembelianModal({{ $row->pembelian_id }})" style="background-color: var(--brand-soft); color: var(--brand); border: 1px solid var(--brand); border-radius: 6px; font-size: 11px; font-weight: 600; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="bi bi-receipt"></i> Rincian PO
                                                </button>
                                            @elseif(isset($row->pesanan_id) && $row->pesanan_id)
                                                <button type="button" class="btn btn-sm" onclick="showPesananModal({{ $row->pesanan_id }})" style="background-color: #eff6ff; color: #2563eb; border: 1px solid #93c5fd; border-radius: 6px; font-size: 11px; font-weight: 600; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="bi bi-receipt"></i> Rincian SO
                                                </button>
                                            @else
                                                <span class="text-muted" style="font-size: 11px;">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 32px; color: #64748b;">
                                            Belum ada riwayat transaksi jurnal untuk entitas ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="ledger-footer">
                        <span>Saldo Akhir (Sisa Kewajiban/Hak):</span>
                        <span style="color: var(--brand);">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="info-card">
                    <h4 style="text-align: left; color: #334155; font-size: 14px; font-weight: 700;">Status Transaksi</h4>
                    
                    @if($saldoAkhir <= 0)
                        <div class="status-icon-check icon-lunas">✓</div>
                        <strong style="color: #16a34a; font-size: 15px;">Semua Transaksi Lunas</strong>
                        <p style="font-size: 12px; color: #64748b; margin-top: 6px;">
                            Tidak ada kewajiban atau hak gantung yang belum diselesaikan.
                        </p>
                    @else
                        <div class="status-icon-check icon-pending">!</div>
                        <strong style="color: #dc2626; font-size: 15px;">Belum Lunas / Aktif</strong>
                        <p style="font-size: 12px; color: #64748b; margin-top: 6px;">
                            Masih terdapat saldo berjalan sebesar <strong>Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</strong>.
                        </p>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL DETAIL PEMBELIAN (PO) --}}
    <div class="modal fade" id="modalDetailPembelian" tabindex="-1" aria-labelledby="modalDetailPembelianLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background-color: var(--brand);">
                    <h5 class="modal-title fw-bold" id="modalDetailPembelianLabel">
                        <i class="bi bi-file-earmark-text me-2"></i> Detail Transaksi Pembelian: <span id="modalPoKode">-</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Info Ringkas --}}
                    <div class="row g-3 mb-3 p-3 bg-light rounded-3" style="font-size: 13px;">
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Supplier:</span>
                            <strong id="modalPoSupplier" class="text-dark">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Tanggal:</span>
                            <strong id="modalPoTanggal" class="text-dark">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Gudang Tujuan:</span>
                            <strong id="modalPoGudang" class="text-dark">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Metode Bayar:</span>
                            <strong id="modalPoMetode" class="text-dark">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Status Barang:</span>
                            <span id="modalPoStatusBarang">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Status Bayar:</span>
                            <span id="modalPoStatusBayar">-</span>
                        </div>
                        <div class="col-12 col-md-6" id="modalPoCatatanWrapper">
                            <span class="text-muted d-block">Catatan:</span>
                            <span id="modalPoCatatan" class="text-secondary">-</span>
                        </div>
                    </div>

                    {{-- Tabel Detail Item Barang --}}
                    <h6 class="fw-bold mb-2 text-dark" style="font-size: 14px;">Rincian Barang yang Dibeli:</h6>
                    <div class="table-responsive rounded border mb-3">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nama Barang</th>
                                    <th class="text-center">Satuan Beli</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="modalPoItemsBody">
                                {{-- Populated by JS --}}
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Tax / Service:</td>
                                    <td class="text-end fw-bold text-secondary" id="modalPoTax">Rp 0</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Total Pembelian:</td>
                                    <td class="text-end fw-bold" style="color: var(--brand);" id="modalPoTotal">Rp 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL DETAIL PESANAN (SO) --}}
    <div class="modal fade" id="modalDetailPesanan" tabindex="-1" aria-labelledby="modalDetailPesananLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalDetailPesananLabel">
                        <i class="bi bi-receipt me-2"></i> Detail Kontrak Penjualan: <span id="modalSoKode">-</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Info Ringkas --}}
                    <div class="row g-3 mb-3 p-3 bg-light rounded-3" style="font-size: 13px;">
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Customer:</span>
                            <strong id="modalSoCustomer" class="text-dark">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Tanggal:</span>
                            <strong id="modalSoTanggal" class="text-dark">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Status Pesanan:</span>
                            <span id="modalSoStatusPesanan">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block">Status Pembayaran:</span>
                            <span id="modalSoStatusBayar">-</span>
                        </div>
                    </div>

                    {{-- Tabel Detail Produk --}}
                    <h6 class="fw-bold mb-2 text-dark" style="font-size: 14px;">Rincian Produk yang Dipesan:</h6>
                    <div class="table-responsive rounded border mb-3">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Produk</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="modalSoItemsBody">
                                {{-- Populated by JS --}}
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Tax / Service:</td>
                                    <td class="text-end fw-bold text-secondary" id="modalSoTax">Rp 0</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Total Nilai Pesanan:</td>
                                    <td class="text-end fw-bold text-primary" id="modalSoTotal">Rp 0</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Sisa Tagihan:</td>
                                    <td class="text-end fw-bold text-danger" id="modalSoSisa">Rp 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a id="modalSoFullLink" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Pesanan Lengkap
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const pembelianDataMap = @json($pembelianMap ?? []);
        const pesananDataMap = @json($pesananMap ?? []);

        function formatRupiah(num) {
            return 'Rp ' + (new Intl.NumberFormat('id-ID').format(Math.round(num || 0)));
        }

        function showPembelianModal(id) {
            const data = pembelianDataMap[id];
            if (!data) {
                alert('Data detail pembelian tidak ditemukan.');
                return;
            }

            document.getElementById('modalPoKode').innerText = data.kode || ('#' + data.id);
            document.getElementById('modalPoSupplier').innerText = data.supplier_nama || '-';
            document.getElementById('modalPoTanggal').innerText = data.tanggal || '-';
            document.getElementById('modalPoGudang').innerText = data.gudang_nama || '-';
            document.getElementById('modalPoMetode').innerText = data.label || data.metode || '-';
            
            document.getElementById('modalPoStatusBarang').innerHTML = data.is_diterima 
                ? '<span class="badge bg-success">Diterima</span>' 
                : '<span class="badge bg-warning text-dark">Belum Diterima</span>';

            document.getElementById('modalPoStatusBayar').innerHTML = data.is_lunas 
                ? '<span class="badge bg-success">Lunas</span>' 
                : '<span class="badge bg-danger">Belum Lunas</span>';

            document.getElementById('modalPoCatatan').innerText = data.catatan || '-';
            document.getElementById('modalPoTax').innerText = formatRupiah(data.tax_service);
            document.getElementById('modalPoTotal').innerText = formatRupiah(data.total);

            const tbody = document.getElementById('modalPoItemsBody');
            tbody.innerHTML = '';
            if (data.details && data.details.length > 0) {
                data.details.forEach((item, index) => {
                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>
                                <strong>${item.nama}</strong>
                                ${item.kode_barang ? '<br><small class="text-muted">' + item.kode_barang + '</small>' : ''}
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark border">${item.satuan}</span></td>
                            <td class="text-end fw-semibold">${new Intl.NumberFormat('id-ID').format(item.qty)}</td>
                            <td class="text-end">${formatRupiah(item.harga_per_qty)}</td>
                            <td class="text-end fw-bold">${formatRupiah(item.harga)}</td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data rincian barang.</td></tr>';
            }

            const modal = new bootstrap.Modal(document.getElementById('modalDetailPembelian'));
            modal.show();
        }

        function showPesananModal(id) {
            const data = pesananDataMap[id];
            if (!data) {
                alert('Data detail pesanan tidak ditemukan.');
                return;
            }

            document.getElementById('modalSoKode').innerText = data.kode || ('#' + data.id);
            document.getElementById('modalSoCustomer').innerText = data.customer_nama || '-';
            document.getElementById('modalSoTanggal').innerText = data.tanggal || '-';
            
            document.getElementById('modalSoStatusPesanan').innerHTML = `<span class="badge bg-info text-dark">${data.status_pesanan || '-'}</span>`;
            document.getElementById('modalSoStatusBayar').innerHTML = data.status_pembayaran === 'Lunas' 
                ? '<span class="badge bg-success">Lunas</span>' 
                : '<span class="badge bg-danger">' + (data.status_pembayaran || 'Belum') + '</span>';

            document.getElementById('modalSoTax').innerText = formatRupiah(data.tax_service);
            document.getElementById('modalSoTotal').innerText = formatRupiah(data.total_pesanan);
            document.getElementById('modalSoSisa').innerText = formatRupiah(data.sisa_tagihan);
            document.getElementById('modalSoFullLink').href = `/pesanan/${data.id}`;

            const tbody = document.getElementById('modalSoItemsBody');
            tbody.innerHTML = '';
            if (data.details && data.details.length > 0) {
                data.details.forEach((item, index) => {
                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td><strong>${item.nama}</strong></td>
                            <td class="text-end fw-semibold">${new Intl.NumberFormat('id-ID').format(item.qty)} ${item.satuan}</td>
                            <td class="text-end">${formatRupiah(item.harga)}</td>
                            <td class="text-end fw-bold">${formatRupiah(item.subtotal)}</td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada data detail produk.</td></tr>';
            }

            const modal = new bootstrap.Modal(document.getElementById('modalDetailPesanan'));
            modal.show();
        }
    </script>
</x-app-layout>