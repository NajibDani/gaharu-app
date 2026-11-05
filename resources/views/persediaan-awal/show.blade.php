<x-app-layout>
    <x-slot name="header">
        Detail Persediaan Awal
    </x-slot>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('persediaan-awal.index') }}" class="btn btn-sm btn-outline-secondary rounded-2 px-2">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    @php
                        $isApproved = ($persediaanAwal->status === 'approved' || $persediaanAwal->status === 'posted');
                        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
                        $canEdit = !$isApproved || $isSuperAdmin;
                        $canApprove = !$isApproved && $isSuperAdmin;
                    @endphp
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            {{ $persediaanAwal->kode_transaksi }}
                            @if($isApproved)
                                <span class="badge bg-success-subtle text-success fs-6 ms-2">
                                    <i class="bi bi-check-circle-fill me-1"></i> Approved
                                </span>
                            @else
                                <span class="badge bg-warning-subtle text-warning fs-6 ms-2">
                                    <i class="bi bi-clock-fill me-1"></i> Draft (Menunggu Approval)
                                </span>
                            @endif
                        </h5>
                        <small class="text-muted">Transaksi input saldo awal persediaan master barang</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-2 px-3" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Cetak / Print
                    </button>
                    @if($canApprove)
                        <button type="button" class="btn btn-sm btn-success rounded-2 px-3" data-bs-toggle="modal" data-bs-target="#modalApproveShow">
                            <i class="bi bi-check-lg me-1"></i> Setujui / Approve
                        </button>
                    @endif
                    @if($canEdit)
                        <a href="{{ route('persediaan-awal.edit', $persediaanAwal->id) }}" class="btn btn-sm btn-warning text-white rounded-2 px-3">
                            <i class="bi bi-pencil-square me-1"></i> Edit Transaksi {{ $isApproved ? '(Super Admin)' : '' }}
                        </a>
                    @endif
                    <a href="{{ route('persediaan-awal.create') }}" class="btn btn-sm text-white rounded-2 px-3" style="background-color: #d88656; border: none;">
                        <i class="bi bi-plus-lg me-1"></i> Input Baru
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            @if(session('import_result'))
                @php $res = session('import_result'); @endphp
                <div class="alert alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 p-3" 
                     style="background-color: #ffffff; border-left: 5px solid {{ $res['gagal'] > 0 ? ($res['berhasil'] > 0 ? '#DE8958' : '#dc3545') : '#198754' }} !important;" 
                     role="alert">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                             style="width: 42px; height: 42px; background-color: {{ $res['gagal'] > 0 ? ($res['berhasil'] > 0 ? '#DE8958' : '#dc3545') : '#198754' }};">
                            <i class="bi {{ $res['gagal'] > 0 ? ($res['berhasil'] > 0 ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill') : 'bi-check-circle-fill' }} fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold text-dark">Hasil Import Persediaan Awal</h6>
                            <div class="fs-6 fw-bold text-dark my-1">
                                {{ $res['pesan'] }}
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1.5 rounded-2">
                                    <i class="bi bi-check2 me-1"></i> <strong>{{ $res['berhasil'] }}</strong> persediaan awal berhasil masuk
                                </span>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 rounded-2">
                                    <i class="bi bi-plus-circle me-1"></i> <strong>{{ $res['item_baru'] }}</strong> item baru
                                </span>
                                <span class="badge {{ $res['gagal'] > 0 ? 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25' : 'bg-secondary bg-opacity-10 text-muted' }} px-2.5 py-1.5 rounded-2">
                                    <i class="bi {{ $res['gagal'] > 0 ? 'bi-exclamation-circle' : 'bi-dash-circle' }} me-1"></i> <strong>{{ $res['gagal'] }}</strong> gagal
                                </span>
                            </div>

                            @if(!empty($res['new_items']) && count($res['new_items']) > 0)
                                <div class="small text-muted mt-2 pt-1">
                                    <i class="bi bi-box-seam me-1 text-primary"></i> <strong>Item baru didaftarkan ke Master Barang:</strong> {{ implode(', ', array_slice($res['new_items'], 0, 10)) }}{{ count($res['new_items']) > 10 ? ' dan ' . (count($res['new_items']) - 10) . ' lainnya' : '' }}
                                </div>
                            @endif

                            @if(!empty($res['failed_rows']) && count($res['failed_rows']) > 0)
                                <div class="mt-2 pt-2 border-top">
                                    <a class="text-danger small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1" data-bs-toggle="collapse" href="#collapseFailedRowsShow" role="button" aria-expanded="false" aria-controls="collapseFailedRowsShow">
                                        <i class="bi bi-chevron-down"></i> Lihat rincian {{ count($res['failed_rows']) }} baris yang gagal
                                    </a>
                                    <div class="collapse mt-2" id="collapseFailedRowsShow">
                                        <div class="table-responsive bg-light rounded border" style="max-height: 220px; overflow-y: auto;">
                                            <table class="table table-sm table-striped mb-0 small">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th style="width: 90px;">Baris Excel</th>
                                                        <th>Nama / Kode Barang</th>
                                                        <th>Alasan Gagal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($res['failed_rows'] as $fr)
                                                        <tr>
                                                            <td class="text-muted fw-bold">Baris {{ $fr['baris'] ?? '-' }}</td>
                                                            <td>{{ $fr['item'] ?? '-' }}</td>
                                                            <td class="text-danger">{{ $fr['alasan'] ?? 'Format data tidak valid' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4 d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Info Header -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Gudang / Lokasi</span>
                        <span class="fs-6 fw-bold text-dark d-block">{{ $persediaanAwal->gudang->nama ?? '-' }}</span>
                        @if($persediaanAwal->divisi)
                            <span class="badge bg-primary-subtle text-primary mt-1">Divisi: {{ $persediaanAwal->divisi->nama }}</span>
                        @else
                            <span class="text-muted small">Tanpa Divisi (Umum)</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Tanggal Saldo Awal</span>
                        <span class="fs-6 fw-bold text-dark d-block">{{ $persediaanAwal->tanggal->format('d F Y') }}</span>
                        <span class="text-muted small">Dicatat oleh: {{ $persediaanAwal->user->nama_karyawan ?? $persediaanAwal->user->name ?? '-' }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Item & Kuantitas</span>
                        <span class="fs-6 fw-bold text-dark d-block">{{ number_format($persediaanAwal->total_item) }} Barang</span>
                        <span class="text-muted small">Total Qty: <strong>{{ number_format($persediaanAwal->total_qty, 2, ',', '.') }}</strong></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Nilai Persediaan Awal</span>
                        <span class="fs-5 fw-bold text-success d-block">Rp {{ number_format($persediaanAwal->total_nilai, 0, ',', '.') }}</span>
                        <span class="text-muted small">{{ $persediaanAwal->keterangan ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- TABEL DAFTAR BARANG -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-boxes me-1 text-primary"></i> Rincian Barang & Batch FIFO Terbentuk
                    </h6>
                    <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5 rounded-pill" id="persediaanItemCount">
                        {{ $persediaanAwal->details->count() }} item barang
                    </span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2 w-100 w-md-auto">
                    <!-- Search Input -->
                    <div style="min-width: 220px; max-width: 270px;" class="flex-grow-1 flex-md-grow-0">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="persediaanSearchInput" class="form-control border shadow-none" placeholder="Cari nama atau kode barang..." oninput="applyPersediaanFilterSort()">
                            <button class="btn btn-outline-secondary border btn-sm" type="button" onclick="document.getElementById('persediaanSearchInput').value=''; applyPersediaanFilterSort();" title="Reset">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Filter Kategori -->
                    <div style="min-width: 170px;">
                        <select id="persediaanKategoriFilter" class="form-select form-select-sm" onchange="applyPersediaanFilterSort()" title="Filter berdasarkan kategori">
                            <option value="all">Semua Kategori</option>
                            @php
                                $kategoriList = $persediaanAwal->details->map(fn($d) => $d->barang->kategori->nama ?? null)->filter()->unique()->sort();
                            @endphp
                            @foreach($kategoriList as $kat)
                                <option value="{{ strtolower($kat) }}">{{ $kat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Quick Sort Dropdown -->
                    <div style="min-width: 230px;">
                        <select id="persediaanQuickSort" class="form-select form-select-sm" onchange="handlePersediaanQuickSortChange(this.value)" title="Urutkan dari besar ke terkecil atau sebaliknya">
                            <option value="">Urutkan Kolom (Besar &rarr; Kecil)...</option>
                            <option value="qty_desc">Qty Masuk: Besar &rarr; Kecil</option>
                            <option value="qty_asc">Qty Masuk: Kecil &rarr; Besar</option>
                            <option value="hpp_desc">HPP Satuan: Besar &rarr; Kecil</option>
                            <option value="hpp_asc">HPP Satuan: Kecil &rarr; Besar</option>
                            <option value="nilai_desc">Total Nilai: Besar &rarr; Kecil</option>
                            <option value="nilai_asc">Total Nilai: Kecil &rarr; Besar</option>
                            <option value="nama_asc">Nama Barang: A &rarr; Z</option>
                            <option value="nama_desc">Nama Barang: Z &rarr; A</option>
                            <option value="kode_asc">Kode Barang: A &rarr; Z</option>
                            <option value="kode_desc">Kode Barang: Z &rarr; A</option>
                            <option value="kategori_asc">Kategori: A &rarr; Z</option>
                            <option value="batch_asc">Nomor Batch: A &rarr; Z</option>
                            <option value="default">Reset ke Urutan Awal</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive border rounded-3 mb-4">
                <table class="table table-hover align-middle mb-0 text-center" id="persediaanTable">
                    <thead style="background-color: #715745; color: white;">
                        <tr style="background-color: #715745; color: white;">
                            <th class="text-white text-center persediaan-sortable-th" data-col="0" data-type="num" onclick="sortPersediaanTable(0, 'num')" style="width: 50px; cursor: pointer; user-select: none;" title="Klik untuk mengurutkan No">
                                <span>No</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white text-start persediaan-sortable-th" data-col="1" data-type="str" onclick="sortPersediaanTable(1, 'str')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Kode Barang (A-Z / Z-A)">
                                <span>Kode Barang</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white text-start persediaan-sortable-th" data-col="2" data-type="str" onclick="sortPersediaanTable(2, 'str')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Nama Barang (A-Z / Z-A)">
                                <span>Nama Barang</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white persediaan-sortable-th" data-col="3" data-type="str" onclick="sortPersediaanTable(3, 'str')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Kategori (A-Z / Z-A)">
                                <span>Kategori</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white persediaan-sortable-th" data-col="4" data-type="num" onclick="sortPersediaanTable(4, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Input Pembelian (Besar ke Kecil / Kecil ke Besar)">
                                <span>Input Pembelian</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white persediaan-sortable-th" data-col="5" data-type="num" onclick="sortPersediaanTable(5, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Satuan & Konversi">
                                <span>Satuan & Konversi</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white text-center persediaan-sortable-th" data-col="6" data-type="num" onclick="sortPersediaanTable(6, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Qty Masuk Stok (Besar ke Kecil / Kecil ke Besar)">
                                <span>Qty Masuk Stok</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white text-end persediaan-sortable-th" data-col="7" data-type="num" onclick="sortPersediaanTable(7, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan HPP Satuan Stok (Besar ke Kecil / Kecil ke Besar)">
                                <span>HPP Satuan Stok (Rp)</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white text-end persediaan-sortable-th" data-col="8" data-type="num" onclick="sortPersediaanTable(8, 'num')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Total Nilai (Besar ke Kecil / Kecil ke Besar)">
                                <span>Total Nilai (Rp)</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                            <th class="text-white text-start persediaan-sortable-th" data-col="9" data-type="str" onclick="sortPersediaanTable(9, 'str')" style="cursor: pointer; user-select: none;" title="Klik untuk mengurutkan Nomor Batch FIFO (A-Z / Z-A)">
                                <span>Nomor Batch FIFO</span> <i class="bi bi-arrow-down-up text-white-50 ms-1 sort-icon" style="font-size:0.75rem;"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="persediaanTableBody">
                        @forelse($persediaanAwal->details as $index => $detail)
                            @php
                                $hasKonv = $detail->satuan_pembelian && (float)$detail->konversi_pembelian > 1 && $detail->satuan_pembelian !== $detail->satuan;
                                $konv = (float)$detail->konversi_pembelian ?: 1;
                                $displayQtyBeli = $detail->qty_pembelian;
                                $displayHargaBeli = $detail->harga_pembelian;
                                if ($hasKonv) {
                                    if ($displayQtyBeli === null || abs(((float)$displayQtyBeli * $konv) - (float)$detail->qty) > 0.05) {
                                        $displayQtyBeli = $konv > 0 ? ((float)$detail->qty / $konv) : (float)$detail->qty;
                                        $displayHargaBeli = (float)$detail->harga_satuan * $konv;
                                    }
                                } else {
                                    $displayQtyBeli = 0;
                                    $displayHargaBeli = 0;
                                }
                            @endphp
                            <tr class="persediaan-row"
                                data-name="{{ strtolower($detail->barang->nama ?? '') }}"
                                data-code="{{ strtolower($detail->barang->kode_barang ?? '') }}"
                                data-kategori="{{ strtolower($detail->barang->kategori->nama ?? '') }}"
                                data-batch="{{ strtolower($detail->batch_number ?? '') }}"
                                data-qty-beli="{{ (float)$displayQtyBeli }}"
                                data-konversi="{{ (float)($detail->konversi_pembelian ?: 1) }}"
                                data-qty="{{ (float)$detail->qty }}"
                                data-hpp="{{ (float)$detail->harga_satuan }}"
                                data-nilai="{{ (float)$detail->total_nilai }}"
                                data-index="{{ $loop->iteration }}">
                                <td class="text-muted row-index text-center" data-val="{{ $loop->iteration }}">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="text-start font-monospace fw-bold" data-val="{{ strtolower($detail->barang->kode_barang ?? '') }}">
                                    {{ $detail->barang->kode_barang ?? '-' }}
                                </td>
                                <td class="text-start" data-val="{{ strtolower($detail->barang->nama ?? '') }}">
                                    <span class="fw-semibold text-dark">{{ $detail->barang->nama ?? '-' }}</span>
                                </td>
                                <td data-val="{{ strtolower($detail->barang->kategori->nama ?? '') }}">
                                    <span class="badge bg-light text-dark border">{{ $detail->barang->kategori->nama ?? '-' }}</span>
                                </td>
                                <td data-val="{{ (float)$displayQtyBeli }}">
                                    @if($hasKonv)
                                        <span class="fw-bold text-dark">{{ number_format($displayQtyBeli, 2, ',', '.') }}</span> {{ $detail->satuan_pembelian }}
                                        <div class="text-muted small" style="font-size: 11px;">@ Rp {{ number_format($displayHargaBeli, 0, ',', '.') }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-val="{{ (float)($detail->konversi_pembelian ?: 1) }}">
                                    @if($hasKonv)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            1 {{ $detail->satuan_pembelian }} = {{ number_format($detail->konversi_pembelian, 0, ',', '.') }} {{ $detail->satuan }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark border">{{ $detail->satuan }}</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-primary" data-val="{{ (float)$detail->qty }}">
                                    {{ number_format($detail->qty, 2, ',', '.') }} <span class="small fw-normal text-muted">{{ $detail->satuan }}</span>
                                </td>
                                <td class="text-end" data-val="{{ (float)$detail->harga_satuan }}">
                                    Rp {{ number_format($detail->harga_satuan, 2, ',', '.') }} <span class="small text-muted">/ {{ $detail->satuan }}</span>
                                </td>
                                <td class="text-end fw-bold text-success" data-val="{{ (float)$detail->total_nilai }}">
                                    Rp {{ number_format($detail->total_nilai, 0, ',', '.') }}
                                </td>
                                <td class="text-start font-monospace small" data-val="{{ strtolower($detail->batch_number ?? '') }}">
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        {{ $detail->batch_number }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">Tidak ada rincian item barang.</td>
                            </tr>
                        @endforelse
                        <tr id="persediaanNoResults" style="display: none;">
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-search fs-4 d-block mb-1 text-muted"></i>
                                Tidak ada barang yang cocok dengan filter / pencarian.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Total Masuk Stok:</td>
                            <td class="text-center text-primary" id="footerTotalQty">{{ number_format($persediaanAwal->total_qty, 2, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end text-success" id="footerTotalNilai">Rp {{ number_format($persediaanAwal->total_nilai, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if(!$isApproved)
                <div class="alert alert-warning border-0 shadow-sm rounded-3 p-3 mb-0 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center bg-warning text-white flex-shrink-0" style="width: 42px; height: 42px;">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">Stok Belum Masuk ke Persediaan (Status: Draft)</h6>
                        <span class="text-secondary small">
                            Transaksi ini masih berstatus <strong>Draft</strong>. Kuantitas fisik, kartu stok, batch FIFO, dan jurnal saldo awal <strong>belum dimasukkan</strong> ke sistem persediaan. 
                            @if($canApprove)
                                Silakan periksa rincian di atas dan klik tombol <strong>Setujui / Approve</strong> di atas untuk memposting stok ke persediaan.
                            @else
                                Stok baru akan masuk ke persediaan setelah disetujui (Approved) oleh <strong>Super Admin</strong>.
                            @endif
                        </span>
                    </div>
                </div>
            @elseif($jurnal)
                <div class="card border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-journal-text me-1 text-success"></i> Jurnal Penyesuaian Terkait (Otomatis)
                            </h6>
                            <small class="text-muted">No. Ref: <strong>{{ $jurnal->no_ref }}</strong> | Tanggal: {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}</small>
                        </div>
                        <span class="badge bg-success px-3 py-2">Approved</span>
                    </div>

                    <div class="table-responsive bg-white rounded-2 border">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light text-center small">
                                <tr>
                                    <th class="text-start">Kode Akun</th>
                                    <th class="text-start">Nama Akun</th>
                                    <th class="text-end" style="width: 180px;">Debit (Rp)</th>
                                    <th class="text-end" style="width: 180px;">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                @php $totalDebit = 0; $totalKredit = 0; @endphp
                                @foreach($jurnal->details as $jd)
                                    @php
                                        $totalDebit += (float) $jd->debit;
                                        $totalKredit += (float) $jd->kredit;
                                    @endphp
                                    <tr>
                                        <td class="font-monospace fw-bold">{{ $jd->account->kode ?? '-' }}</td>
                                        <td>
                                            @if($jd->kredit > 0)
                                                <span class="ms-4 text-secondary">{{ $jd->account->nama ?? '-' }}</span>
                                            @else
                                                <span class="fw-semibold text-dark">{{ $jd->account->nama ?? '-' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ $jd->debit > 0 ? number_format($jd->debit, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ $jd->kredit > 0 ? number_format($jd->kredit, 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold small">
                                <tr>
                                    <td colspan="2" class="text-end">Total Jurnal:</td>
                                    <td class="text-end text-success">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td>
                                    <td class="text-end text-success">Rp {{ number_format($totalKredit, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($canApprove)
        <!-- Modal Konfirmasi Approve Show -->
        <div class="modal fade" id="modalApproveShow" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-start border-0 shadow">
                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                        <h5 class="modal-title fw-bold text-success">
                            <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Persetujuan
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('persediaan-awal.approve', $persediaanAwal->id) }}" method="POST">
                        @csrf
                        <div class="modal-body px-4 py-3">
                            <p class="mb-0 text-secondary">
                                Apakah Anda yakin ingin menyetujui (Approve) transaksi persediaan awal <strong>{{ $persediaanAwal->kode_transaksi }}</strong> untuk <strong>{{ $persediaanAwal->gudang->nama ?? '-' }} {{ $persediaanAwal->divisi ? '('.$persediaanAwal->divisi->nama.')' : '' }}</strong>?
                                <br><br>
                                <span class="text-success small fw-semibold">
                                    <i class="bi bi-check-circle me-1"></i> Setelah disetujui, seluruh kuantitas stok barang, batch FIFO, dan jurnal penyesuaian akan resmi masuk dan diposting ke persediaan.
                                </span>
                            </p>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 pt-0">
                            <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success px-3">Ya, Setujui (Approve)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <style>
        .persediaan-sortable-th {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.15s ease-in-out;
        }
        .persediaan-sortable-th:hover {
            background-color: #5c3e2e !important;
        }
    </style>

    <script>
    let persediaanSortState = {
        col: 0,
        dir: 'asc'
    };

    const totalPersediaanItemCount = {{ $persediaanAwal->details->count() }};

    function sortPersediaanTable(colIndex, type) {
        if (persediaanSortState.col === colIndex) {
            persediaanSortState.dir = (persediaanSortState.dir === 'desc') ? 'asc' : 'desc';
        } else {
            persediaanSortState.col = colIndex;
            // Kolom numerik default dari BESAR KE KECIL ('desc'), kecuali No
            persediaanSortState.dir = (type === 'num' && colIndex !== 0) ? 'desc' : 'asc';
        }

        syncPersediaanQuickSortSelect();
        applyPersediaanFilterSort();
    }

    function handlePersediaanQuickSortChange(val) {
        switch (val) {
            case 'qty_desc':
                persediaanSortState.col = 6;
                persediaanSortState.dir = 'desc';
                break;
            case 'qty_asc':
                persediaanSortState.col = 6;
                persediaanSortState.dir = 'asc';
                break;
            case 'hpp_desc':
                persediaanSortState.col = 7;
                persediaanSortState.dir = 'desc';
                break;
            case 'hpp_asc':
                persediaanSortState.col = 7;
                persediaanSortState.dir = 'asc';
                break;
            case 'nilai_desc':
                persediaanSortState.col = 8;
                persediaanSortState.dir = 'desc';
                break;
            case 'nilai_asc':
                persediaanSortState.col = 8;
                persediaanSortState.dir = 'asc';
                break;
            case 'nama_asc':
                persediaanSortState.col = 2;
                persediaanSortState.dir = 'asc';
                break;
            case 'nama_desc':
                persediaanSortState.col = 2;
                persediaanSortState.dir = 'desc';
                break;
            case 'kode_asc':
                persediaanSortState.col = 1;
                persediaanSortState.dir = 'asc';
                break;
            case 'kode_desc':
                persediaanSortState.col = 1;
                persediaanSortState.dir = 'desc';
                break;
            case 'kategori_asc':
                persediaanSortState.col = 3;
                persediaanSortState.dir = 'asc';
                break;
            case 'batch_asc':
                persediaanSortState.col = 9;
                persediaanSortState.dir = 'asc';
                break;
            case 'default':
                persediaanSortState.col = 0;
                persediaanSortState.dir = 'asc';
                break;
            default:
                return;
        }

        applyPersediaanFilterSort();
    }

    function syncPersediaanQuickSortSelect() {
        const sel = document.getElementById('persediaanQuickSort');
        if (!sel) return;

        const col = persediaanSortState.col;
        const dir = persediaanSortState.dir;

        if (col === 6 && dir === 'desc') sel.value = 'qty_desc';
        else if (col === 6 && dir === 'asc') sel.value = 'qty_asc';
        else if (col === 7 && dir === 'desc') sel.value = 'hpp_desc';
        else if (col === 7 && dir === 'asc') sel.value = 'hpp_asc';
        else if (col === 8 && dir === 'desc') sel.value = 'nilai_desc';
        else if (col === 8 && dir === 'asc') sel.value = 'nilai_asc';
        else if (col === 2 && dir === 'asc') sel.value = 'nama_asc';
        else if (col === 2 && dir === 'desc') sel.value = 'nama_desc';
        else if (col === 1 && dir === 'asc') sel.value = 'kode_asc';
        else if (col === 1 && dir === 'desc') sel.value = 'kode_desc';
        else if (col === 3 && dir === 'asc') sel.value = 'kategori_asc';
        else if (col === 9 && dir === 'asc') sel.value = 'batch_asc';
        else if (col === 0 && dir === 'asc') sel.value = 'default';
        else sel.value = '';
    }

    function applyPersediaanFilterSort() {
        const searchInput = document.getElementById('persediaanSearchInput');
        const kategoriFilter = document.getElementById('persediaanKategoriFilter');
        const tbody = document.getElementById('persediaanTableBody');
        if (!tbody) return;

        const q = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
        const kFilter = kategoriFilter ? (kategoriFilter.value || 'all').toLowerCase() : 'all';

        const colIndex = persediaanSortState.col;
        const isAsc = (persediaanSortState.dir === 'asc');

        // 1. Sort semua baris jika kolom sort aktif
        let rows = Array.from(tbody.querySelectorAll('.persediaan-row'));
        if (colIndex >= 0) {
            rows.sort((rowA, rowB) => {
                const cellA = rowA.children[colIndex];
                const cellB = rowB.children[colIndex];

                const valA = cellA ? (cellA.getAttribute('data-val') ?? cellA.textContent.trim()) : '';
                const valB = cellB ? (cellB.getAttribute('data-val') ?? cellB.textContent.trim()) : '';

                const numA = parseFloat(valA);
                const numB = parseFloat(valB);

                let cmp = 0;
                if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                    cmp = numA - numB;
                } else {
                    cmp = String(valA).localeCompare(String(valB), 'id', { numeric: true, sensitivity: 'base' });
                }

                return isAsc ? cmp : -cmp;
            });

            // Masukkan kembali ke tbody dalam urutan baru
            const noResults = document.getElementById('persediaanNoResults');
            rows.forEach(r => tbody.appendChild(r));
            if (noResults) tbody.appendChild(noResults);
        }

        // 2. Filter baris berdasarkan pencarian & kategori
        let visibleCount = 0;
        let totalQtySum = 0;
        let totalNilaiSum = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const code = row.getAttribute('data-code') || '';
            const kat = row.getAttribute('data-kategori') || '';
            const batch = row.getAttribute('data-batch') || '';
            const qty = parseFloat(row.getAttribute('data-qty') || 0);
            const nilai = parseFloat(row.getAttribute('data-nilai') || 0);

            let matchSearch = (!q || name.includes(q) || code.includes(q) || batch.includes(q));
            let matchKat = (kFilter === 'all' || kat === kFilter);

            if (matchSearch && matchKat) {
                row.style.display = '';
                visibleCount++;
                const idxCell = row.querySelector('.row-index');
                if (idxCell) idxCell.innerText = visibleCount;

                totalQtySum += qty;
                totalNilaiSum += nilai;
            } else {
                row.style.display = 'none';
            }
        });

        // 3. Update counter & empty message
        const noResults = document.getElementById('persediaanNoResults');
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? '' : 'none';
        }

        const countBadge = document.getElementById('persediaanItemCount');
        if (countBadge) {
            if (q || kFilter !== 'all') {
                countBadge.textContent = `${visibleCount} dari ${totalPersediaanItemCount} item barang`;
            } else {
                countBadge.textContent = `${totalPersediaanItemCount} item barang`;
            }
        }

        // 4. Update footer display
        const footerQty = document.getElementById('footerTotalQty');
        if (footerQty) {
            footerQty.textContent = totalQtySum.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const footerNilai = document.getElementById('footerTotalNilai');
        if (footerNilai) {
            footerNilai.textContent = 'Rp ' + Math.round(totalNilaiSum).toLocaleString('id-ID');
        }

        // 5. Update icon dan highlight pada header
        const headers = document.querySelectorAll('#persediaanTable .persediaan-sortable-th');
        headers.forEach(h => {
            const col = parseInt(h.getAttribute('data-col'));
            const icon = h.querySelector('.sort-icon');
            const badgeOld = h.querySelector('.sort-badge');
            if (badgeOld) badgeOld.remove();

            if (col === colIndex) {
                h.style.backgroundColor = '#5c3e2e';
                const type = h.getAttribute('data-type');
                if (icon) {
                    icon.className = isAsc
                        ? 'bi bi-sort-up text-warning ms-1 sort-icon'
                        : 'bi bi-sort-down-alt text-warning ms-1 sort-icon';
                }
                const badge = document.createElement('span');
                badge.className = 'badge bg-warning text-dark py-0 px-1 ms-1 sort-badge';
                badge.style.fontSize = '0.62rem';
                if (type === 'num' && col !== 0) {
                    badge.innerText = isAsc ? 'Kecil→Besar' : 'Besar→Kecil';
                } else if (type === 'str') {
                    badge.innerText = isAsc ? 'A→Z' : 'Z→A';
                } else {
                    badge.innerText = isAsc ? '1→N' : 'N→1';
                }
                h.appendChild(badge);
            } else {
                h.style.backgroundColor = '';
                if (icon) {
                    icon.className = 'bi bi-arrow-down-up text-white-50 ms-1 sort-icon';
                }
            }
        });
    }
    </script>
</x-app-layout>
