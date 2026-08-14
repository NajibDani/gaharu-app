<x-app-layout>
    <div class="container">
        <h3 class="mb-4">Review & Sesuaikan Qty Work Order</h3>

        <form action="{{ route('wo.store_massal') }}" method="POST">
            @csrf
            
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Daftar Item Terpilih</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kode Pesanan</th>
                                <th>Customer</th>
                                <th>Produk</th>
                                <th class="text-center">Sisa Kebutuhan</th>
                                <th class="text-center" width="200">Qty Produksi (Edit)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasEligibleItems = false; @endphp
                            @foreach($details as $detail)
                                @if($detail->sisa_qty > 0)
                                @php $hasEligibleItems = true; @endphp
                                <tr>
                                    <td class="fw-bold">{{ $detail->pesanan->kode_pesanan }}</td>
                                    <td>{{ $detail->pesanan->customer?->nama ?? $detail->pesanan->customer?->name ?? '-' }}</td>
                                    <td>{{ $detail->produk?->nama ?? $detail->produk?->name ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-info fs-6">{{ $detail->sisa_qty }} {{ $detail->produk?->satuan }}</span>
                                    </td>
                                    <td>
                                        {{-- Input Hidden untuk dilempar ke Controller --}}
                                        <input type="hidden" name="pesanan_id[]" value="{{ $detail->pesanan_id }}">
                                        <input type="hidden" name="produk_id[]" value="{{ $detail->produk_id }}">
                                        
                                        {{-- Input Qty yang bisa diedit oleh User --}}
                                        <input type="number" name="qty_rencana[]" class="form-control text-center" 
                                               value="{{ $detail->sisa_qty }}" 
                                               min="1" max="{{ $detail->sisa_qty }}" required>
                                    </td>
                                </tr>
                                @endif
                            @endforeach

                            @if(!$hasEligibleItems)
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Semua item sudah dibuatkan Work Order. Tidak ada sisa kebutuhan produksi.
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <a href="{{ route('produksi.index', ['tab' => 'wo']) }}" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-success" @if(!$hasEligibleItems) disabled @endif>
                        <i class="bi bi-save"></i> Simpan Work Order
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>