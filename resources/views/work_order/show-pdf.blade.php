<!DOCTYPE html>
<html>
<head>
    <title>Work Order - {{ $wo->kode_wo }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; color: #1e3a8a; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #666; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; font-size: 11px; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .main-table th { background-color: #1e3a8a; color: #ffffff; text-align: left; padding: 8px; font-weight: bold; font-size: 11px; }
        .main-table td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 10px; }
        .main-table tr:nth-child(even) { background-color: #fcfcfc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .resep-title { margin-top: 20px; font-size: 12px; font-weight: bold; color: #1e3a8a; border-bottom: 1px solid #1e3a8a; padding-bottom: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>WORK ORDER (SURAT PERINTAH KERJA)</h2>
        <p>CV Gaharu App</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Kode WO</strong></td>
            <td width="3%">:</td>
            <td width="32%">{{ $wo->kode_wo }}</td>
            <td width="15%"><strong>Tanggal WO</strong></td>
            <td width="3%">:</td>
            <td width="32%">{{ \Carbon\Carbon::parse($wo->tanggal_wo)->format('d-M-Y H:i') }}</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>:</td>
            <td>{{ strtoupper($wo->status_wo) }}</td>
            <td><strong>Pembuat</strong></td>
            <td>:</td>
            <td>{{ $wo->pembuat->nama ?? '-' }}</td>
        </tr>
        @if($wo->catatan)
        <tr>
            <td><strong>Catatan</strong></td>
            <td>:</td>
            <td colspan="4">{{ $wo->catatan }}</td>
        </tr>
        @endif
    </table>

    <h3 class="resep-title">Daftar Item Produksi</h3>
    <table class="main-table">
        <thead>
            <tr>
                <th width="30" class="text-center">No</th>
                <th>Customer</th>
                <th>Kode Pesanan</th>
                <th>Nama Produk</th>
                <th class="text-center" width="100">Qty Rencana</th>
            </tr>
        </thead>
        <tbody>
            @foreach($wo->details as $index => $detail)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $detail->pesanan->customer->nama ?? 'Customer' }}</td>
                <td class="fw-bold">{{ $detail->pesanan->kode_pesanan ?? '-' }}</td>
                <td class="fw-bold text-primary">{{ $detail->produk->nama ?? 'Produk' }}</td>
                <td class="text-center fw-bold">{{ number_format($detail->qty_rencana, 0) }} {{ $detail->produk->satuan ?? 'Unit' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3 class="resep-title">Kebutuhan Bahan Baku (Kalkulasi Resep)</h3>
    @php
        // Agregasi kebutuhan bahan baku untuk seluruh produk di WO ini
        $kebutuhanBahan = [];
        foreach($wo->details as $detail) {
            if($detail->produk && $detail->produk->resep) {
                foreach($detail->produk->resep as $resep) {
                    $bahanId = $resep->bahan_id;
                    $namaBahan = $resep->bahan->nama ?? 'Bahan';
                    $satuan = $resep->satuan;
                    $qtyDibutuhkan = $resep->qty_bahan * $detail->qty_rencana;
                    
                    if(isset($kebutuhanBahan[$bahanId])) {
                        $kebutuhanBahan[$bahanId]['qty'] += $qtyDibutuhkan;
                    } else {
                        $kebutuhanBahan[$bahanId] = [
                            'nama' => $namaBahan,
                            'satuan' => $satuan,
                            'qty' => $qtyDibutuhkan
                        ];
                    }
                }
            }
        }
    @endphp

    <table class="main-table">
        <thead>
            <tr>
                <th width="30" class="text-center">No</th>
                <th>Nama Bahan Baku</th>
                <th class="text-center" width="150">Total Kebutuhan</th>
                <th class="text-center" width="100">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($kebutuhanBahan as $bahanId => $bahan)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td class="fw-bold">{{ $bahan['nama'] }}</td>
                <td class="text-center fw-bold">{{ number_format($bahan['qty'], 2, ',', '.') }}</td>
                <td class="text-center">{{ $bahan['satuan'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center text-muted">Tidak ada kebutuhan bahan baku atau resep belum diatur.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
