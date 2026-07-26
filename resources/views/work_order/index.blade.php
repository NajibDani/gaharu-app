<x-app-layout>

<div class="container">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">Work Order Produksi</h3>

        <form action="{{ route('wo.index') }}" method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no WO..." value="{{ request('search') }}" style="width: 200px; border-radius: 6px;">
            <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px;">Cari</button>
            @if(request('search'))
                <a href="{{ route('wo.index') }}" class="btn btn-sm btn-secondary" style="border-radius: 6px;">Reset</a>
            @endif
        </form>
    </div>

    {{-- REFERENSI PESANAN DIBUNGKUS FORM UNTUK WO MASSAL --}}
    <form action="{{ route('wo.review_massal') }}" method="POST">
        @csrf
        <div class="card mb-4 shadow-sm border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Referensi Pesanan B2B (Belum Selesai)</h5>
                
                {{-- TOMBOL BUAT WO MASSAL --}}
                <button type="submit" class="btn btn-light btn-sm fw-bold text-primary shadow-sm" id="btnMassal" disabled>
                    <i class="bi bi-ui-checks"></i> Buat WO
                </button>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">
                                    <input class="form-check-input border-secondary" type="checkbox" id="checkAll">
                                </th>
                                <th>Kode Pesanan</th>
                                <th>Customer</th>
                                <th>Produk</th>
                                <th>Estimasi Kirim</th>
                                <th class="text-center">Sisa Qty</th>
                                <th class="text-center">Status Pesanan</th>
                                <th class="text-center">Status Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pesanan as $item)
                                @foreach($item->details as $detail)
                                @php
                                    // Perbaikan query sum untuk mengecek sisa qty
                                    $qtyWO = \App\Models\WorkOrderDetail::where('pesanan_id', $item->id)
                                                ->where('produk_id', $detail->produk_id) 
                                                ->sum('qty_rencana');

                                    $sisaQty = $detail->qty - $qtyWO;
                                @endphp

                                @if($sisaQty > 0)
                                <tr>
                                    <td class="text-center">
                                        <input class="form-check-input border-secondary checkItem" type="checkbox" name="detail_ids[]" value="{{ $detail->id }}">
                                    </td>

                                    <td class="fw-bold">{{ $item->kode_pesanan }}</td>
                                    <td>{{ $item->customer?->nama ?? $item->customer?->name ?? 'Tanpa Customer' }}</td>
                                    <td>{{ $detail->produk?->nama ?? $detail->produk?->name ?? 'Tanpa Produk' }}</td>
                                    
                                    <td>
                                        @php
                                            // Reset ke startOfDay agar perbandingan murni tanggal (tanpa jam)
                                            $tglKirim = \Carbon\Carbon::parse($item->estimasi_kirim)->startOfDay();
                                            $hariIni = \Carbon\Carbon::now()->startOfDay();
                                            
                                            $selisih = $hariIni->diffInDays($tglKirim, false);
                                            
                                            $textClass = 'text-muted';
                                        @endphp

                                        @if($selisih < 0)
                                            @php $textClass = 'text-danger fw-bold'; @endphp
                                            <span class="badge bg-danger">Terlambat!</span>
                                        @elseif($selisih == 0)
                                            @php $textClass = 'text-danger fw-bold'; @endphp
                                            <span class="badge bg-danger">Hari Ini!</span>
                                        @elseif($selisih > 0 && $selisih <= 2)
                                            @php $textClass = 'text-warning text-dark fw-bold'; @endphp
                                            <span class="badge bg-warning text-dark">Mepet</span>
                                        @endif
                                        <br>
                                        {{-- TAMPILAN TANGGAL TANPA JAM --}}
                                        <small class="{{ $textClass }}">{{ $tglKirim->format('d M Y') }}</small>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-info text-dark fs-6 shadow-sm">
                                            {{ number_format($sisaQty, 0) }} {{ $detail->produk?->satuan ?? '' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge border text-dark">{{ $item->status_pesanan }}</span>
                                    </td>

                                    <td class="text-center">
                                        @if($item->status_pembayaran == 'Belum Bayar')
                                            <span class="badge bg-danger">Menunggu DP</span>
                                        @else
                                            <span class="badge bg-success">{{ $item->status_pembayaran }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
                                        Belum ada pesanan B2B yang butuh diproduksi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 px-3 pb-3">
                    {{ $pesanan->links() }}
                </div>
            </div>
        </div>
    </form>

    {{-- DAFTAR WO --}}
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Daftar Work Order</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Kode WO</th>
                            <th>Tanggal WO</th>
                            <th>Customer</th>
                            <th class="text-center">Jumlah Item</th>
                            <th class="text-center">Total Qty</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wo as $item)
                        @php
                            $relasiDetail = $item->details ?? $item->detail;
                            $jumlahItem = $relasiDetail ? $relasiDetail->count() : 0;
                            $totalQty = $relasiDetail ? $relasiDetail->sum('qty_rencana') : 0;

                            $customerIds = $relasiDetail ? $relasiDetail->pluck('pesanan.customer_id')->unique()->filter() : collect();
                            
                            if ($customerIds->count() > 1) {
                                $customerDisplayName = '<span class="badge rounded-pill bg-info text-dark shadow-sm"><i class="bi bi-people-fill"></i> Multi Customer</span>';
                            } else {
                                $firstDetail = $relasiDetail ? $relasiDetail->first() : null;
                                $name = $firstDetail?->pesanan?->customer?->nama ?? $firstDetail?->pesanan?->customer?->name ?? 'Tanpa Customer';
                                $customerDisplayName = e($name);
                            }
                        @endphp

                        <tr>
                            <td class="fw-bold">{{ $item->kode_wo }}</td>
                            {{-- TANGGAL WO TANPA JAM --}}
                            <td>{{ \Carbon\Carbon::parse($item->tanggal_wo)->format('d M Y') }}</td>
                            
                            <td>{!! $customerDisplayName !!}</td>
                            
                            <td class="text-center">
                                <span class="badge border text-dark">{{ $jumlahItem }} Item</span>
                            </td>
                            <td class="text-center fw-bold">{{ number_format($totalQty, 0) }}</td>

                            <td class="text-center">
                                @switch($item->status_wo)
                                    @case('Draft') <span class="badge bg-secondary">Draft</span> @break
                                    @case('Diproses') <span class="badge bg-primary">Diproses</span> @break
                                    @case('Selesai') <span class="badge bg-success">Selesai</span> @break
                                    @default <span class="badge bg-dark">{{ $item->status_wo }}</span>
                                @endswitch
                            </td>

                            <td class="text-center">
                                <a href="{{ route('wo.show', $item->id) }}" class="btn btn-info btn-sm text-white shadow-sm">
                                    <i class="bi bi-search"></i> Detail
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Belum ada Work Order yang dibuat.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3 px-3 pb-3">
            {{ $wo->links() }}
        </div>
    </div>

</div>

{{-- SCRIPT UNTUK LOGIKA CHECKBOX MASSAL --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkAll = document.getElementById('checkAll');
        const checkItems = document.querySelectorAll('.checkItem');
        const btnMassal = document.getElementById('btnMassal');

        function toggleButton() {
            const checkedCount = document.querySelectorAll('.checkItem:checked').length;
            btnMassal.disabled = checkedCount === 0;
            
            if(checkedCount > 0) {
                btnMassal.classList.add('btn-warning', 'text-dark');
                btnMassal.classList.remove('btn-light', 'text-primary');
            } else {
                btnMassal.classList.remove('btn-warning', 'text-dark');
                btnMassal.classList.add('btn-light', 'text-primary');
            }
        }

        if(checkAll) {
            checkAll.addEventListener('change', function() {
                checkItems.forEach(item => item.checked = this.checked);
                toggleButton();
            });
        }

        checkItems.forEach(item => {
            item.addEventListener('change', function() {
                const allChecked = document.querySelectorAll('.checkItem:checked').length === checkItems.length;
                checkAll.checked = allChecked;
                toggleButton();
            });
        });
    });
</script>

</x-app-layout>