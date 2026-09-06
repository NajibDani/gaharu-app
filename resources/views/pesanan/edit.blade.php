<x-app-layout>

<!DOCTYPE html>
<html>
<head>
    <title>Form Edit Pesanan</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background-color: #f8fafc;">

<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Edit Pesanan
            </h2>

            <small class="text-muted">
                Form edit data pesanan customer
            </small>

        </div>

        <a href="{{ route('pesanan.index') }}"
           class="btn btn-secondary rounded-3">

            Kembali

        </a>

    </div>

    <div class="card border-0 shadow-sm rounded-4">

        <div class="card-body p-4">

            @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Terjadi Kesalahan Input:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <form action="{{ route('pesanan.update', $pesanan->id) }}"
                  method="POST">

                @csrf
                @method('PUT')

                <div class="mb-3">

                    <label class="form-label">
                        Kode Pesanan
                    </label>

                    <input type="text"
                           class="form-control"
                           value="{{ $pesanan->kode_pesanan }}"
                           readonly>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Customer
                    </label>

                    <select name="customer_id"
                            class="form-select select2">

                        @foreach($customers as $customer)

                        <option value="{{ $customer->id }}"
                            {{ $pesanan->customer_id == $customer->id ? 'selected' : '' }}>

                            {{ $customer->nama }}

                        </option>

                        @endforeach

                    </select>

                </div>

                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label class="form-label fw-semibold text-secondary">Tanggal Permintaan</label>
                        <input type="datetime-local" name="tanggal" class="form-control" value="{{ date('Y-m-d\TH:i', strtotime($pesanan->tanggal)) }}" required>
                    </div>

                    <div class="col-12 col-md-6 mb-3">
                        <label class="form-label fw-semibold text-secondary">Estimasi Kirim (Opsional)</label>
                        <input type="datetime-local" name="estimasi_kirim" class="form-control" value="{{ $pesanan->estimasi_kirim ? date('Y-m-d\TH:i', strtotime($pesanan->estimasi_kirim)) : '' }}">
                    </div>
                </div>

                {{-- REVISI: Bagian input dropdown "Status Pesanan" telah dihapus sepenuhnya di sini --}}

                <hr class="mb-4">

                <h5 class="fw-semibold mb-3">
                    Detail Pesanan
                </h5>

                <div class="table-responsive">

                    <table class="table align-middle table-bordered">

                        <thead class="table-light">

                            <tr>
                                <th>Produk</th>
                                <th width="150">Qty</th>
                                <th width="200">Harga</th>
                                <th width="200">Subtotal</th>
                            </tr>

                        </thead>

                        <tbody>

                            @foreach($pesanan->details as $detail)

                            <tr>

                                <td>

                                    <select name="produk_id[]"
                                            class="form-select produk select2">

                                        @foreach($produk as $p)

                                        <option value="{{ $p->id }}"
                                                data-harga="{{ $p->harga_jual_b2b }}"
                                                {{ $detail->produk_id == $p->id ? 'selected' : '' }}>

                                            {{ $p->nama }}

                                        </option>

                                        @endforeach

                                    </select>

                                </td>

                                <td>

                                    <input type="number"
                                           name="qty[]"
                                           class="form-control qty"
                                           value="{{ $detail->qty }}">

                                </td>

                                <td>

                                    <input type="number"
                                           name="harga[]"
                                           class="form-control harga"
                                           value="{{ $detail->harga }}">

                                </td>

                                <td>

                                    <input type="number"
                                           name="subtotal[]"
                                           class="form-control subtotal"
                                           value="{{ $detail->subtotal }}"
                                           readonly>

                                </td>

                            </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

                <div class="row justify-content-end mb-2 mt-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold text-secondary">Subtotal (DPP)</label>
                        <input type="number" id="subtotal_pesanan" class="form-control fw-semibold" readonly value="0">
                    </div>
                </div>

                <div class="row justify-content-end mb-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Tax/Service (%)</label>
                        <input type="number" name="tax_percentage" id="tax_percentage" class="form-control" value="{{ $pesanan->tax_percentage ?? 0 }}" min="0" max="100" step="0.01">
                    </div>
                </div>

                <div class="row justify-content-end mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Total Pesanan (Nett)</label>
                        <input type="number" name="total_pesanan" id="total_pesanan" class="form-control fw-bold text-primary" value="{{ $pesanan->total_pesanan }}" readonly>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button type="submit" class="btn btn-primary rounded-3">
                        Update Pesanan
                    </button>
                </div>

            </form>

        </div>

    </div>

</div>

@push('scripts')
<script>
    function hitungSubtotal(row)
    {
        let qty = parseFloat(row.querySelector('.qty').value) || 0;
        let harga = parseFloat(row.querySelector('.harga').value) || 0;
        let subtotal = qty * harga;

        row.querySelector('.subtotal').value = subtotal;

        hitungTotal();
    }

    function hitungTotal()
    {
        let subtotal = 0;

        document.querySelectorAll('.subtotal')
        .forEach(function(item) {
            subtotal += parseFloat(item.value) || 0;
        });

        document.getElementById('subtotal_pesanan').value = subtotal;

        let taxPercentage = parseFloat(document.getElementById('tax_percentage').value) || 0;
        let taxAmount = subtotal * (taxPercentage / 100);
        let total = subtotal + taxAmount;

        document.getElementById('total_pesanan').value = total.toFixed(2);
    }

    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        document.getElementById('tax_percentage').addEventListener('input', hitungTotal);

        $(document).on('input', '.qty, .harga', function() {
            let row = this.closest('tr');
            hitungSubtotal(row);
        });

        $(document).on('change', '.produk', function() {
            let row = this.closest('tr');
            let selectedOption = $(this).find(':selected');
            let harga = selectedOption.length ? parseFloat(selectedOption.data('harga')) || 0 : 0;

            $(row).find('.harga').val(harga);
            hitungSubtotal(row);
        });

        // LOAD AWAL
        document.querySelectorAll('tbody tr').forEach(function(row) {
            hitungSubtotal(row);
        });
    });
</script>
@endpush
</x-app-layout>