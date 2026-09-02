<x-app-layout>
    <div class="container py-4">
        <h3 class="mb-4 fw-bold">Edit Penjualan POS</h3>

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <strong>Gagal!</strong> {{ session('error') }}
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('penjualan_pos.update', $penjualan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal</label>
                            <input type="datetime-local" 
                                   name="tanggal" 
                                   id="input-tanggal"
                                   class="form-control @error('tanggal') is-invalid @enderror" 
                                   value="{{ \Carbon\Carbon::parse($penjualan->tanggal)->format('Y-m-d\TH:i') }}" 
                                   required>
                            @error('tanggal')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Gudang</label>
                            <select name="gudang_id" class="form-select @error('gudang_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($gudang as $g)
                                    <option value="{{ $g->id }}" {{ $penjualan->gudang_id == $g->id ? 'selected' : '' }}>
                                        {{ $g->nama }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gudang_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle" id="table-item">
                            <thead class="table-light">
                                <tr>
                                    <th>Produk</th>
                                    <th width="130">Qty</th>
                                    <th width="180">Harga Jual</th>
                                    @if($isSuperAdmin ?? false)
                                        <th width="180" class="text-primary">
                                            HPP Satuan <small class="text-muted d-block" style="font-size:10px;">(Opsional Koreksi)</small>
                                        </th>
                                    @endif
                                    <th width="180">Subtotal</th>
                                    <th width="50" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($penjualan->details as $detail)
                                <tr>
                                    <td>
                                        <select name="produk_id[]" class="form-select select-produk" required>
                                            <option value="">-- Pilih Produk --</option>
                                            @foreach($produk as $p)
                                                <option value="{{ $p->id }}" {{ $detail->produk_id == $p->id ? 'selected' : '' }}>
                                                    {{ $p->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               step="0.01" 
                                               min="0.01"
                                               name="qty[]" 
                                               class="form-control qty" 
                                               value="{{ $detail->qty }}" 
                                               required>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               step="0.01" 
                                               name="harga[]" 
                                               class="form-control harga" 
                                               value="{{ $detail->harga }}" 
                                               readonly>
                                    </td>
                                    @if($isSuperAdmin ?? false)
                                    <td>
                                        <input type="number" 
                                               step="0.01" 
                                               name="hpp_satuan[]" 
                                               class="form-control hpp-satuan border-primary" 
                                               value="{{ $detail->hpp_satuan > 0 ? $detail->hpp_satuan : '' }}" 
                                               placeholder="Otomatis FIFO">
                                    </td>
                                    @endif
                                    <td>
                                        <input type="number" 
                                               class="form-control subtotal" 
                                               value="{{ $detail->subtotal }}" 
                                               readonly>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm btn-remove">
                                            X
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-success mb-4" id="btn-add">
                        + Tambah Item
                    </button>

                    <div class="d-flex justify-content-end align-items-center mb-4">
                        <h4 class="mb-0 fw-bold">
                            Total: Rp <span id="grand-total">{{ number_format($penjualan->total, 0, ',', '.') }}</span>
                        </h4>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('penjualan_pos.index') }}" class="btn btn-secondary">
                            Kembali
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // 1. TAMBAH BARIS BARU (CLONE ROW)
            document.getElementById('btn-add').addEventListener('click', function() {
                let tableBody = document.querySelector('#table-item tbody');
                let firstRow = tableBody.querySelector('tr');
                
                if (!firstRow) return; 

                let row = firstRow.cloneNode(true);

                // Reset nilai input baris baru
                row.querySelectorAll('input').forEach(input => {
                    input.value = '';
                });
                row.querySelector('.subtotal').value = 0;
                
                // Reset dropdown select baris baru
                row.querySelectorAll('select').forEach(select => {
                    select.selectedIndex = 0;
                });

                tableBody.appendChild(row);
            });

            // 2. FUNGSI PANGGIL API HARGA (Sinkron dengan Logika Periode)
            function fetchHarga(selectElement) {
                const row = selectElement.closest('tr');
                const inputHarga = row.querySelector('.harga'); 
                
                const produkId = selectElement.value;
                const tanggalInput = document.getElementById('input-tanggal').value; 

                if (produkId) {
                    inputHarga.value = '...'; 

                    // Memanggil endpoint get-harga milikmu
                    const urlHarga = '/penjualan_pos/get-harga/' + produkId + '?tanggal=' + tanggalInput;

                    fetch(urlHarga)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Gagal mengambil data harga server.');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data && data.has_resep === false) {
                                alert('Gagal! Produk "' + data.nama + '" belum memiliki resep bahan baku. Silakan atur resep terlebih dahulu.');
                                selectElement.selectedIndex = 0;
                                inputHarga.value = 0;
                                hitungSubtotal(row);
                                return;
                            }
                            if (!data || parseFloat(data.harga_pos) <= 0) {
                                alert('Gagal! Produk "' + (data ? data.nama : '') + '" belum memiliki harga jual POS yang aktif. Silakan atur harga jual terlebih dahulu.');
                                selectElement.selectedIndex = 0;
                                inputHarga.value = 0;
                                hitungSubtotal(row);
                                return;
                            }
                            inputHarga.value = data.harga_pos;
                            hitungSubtotal(row);
                        })
                        .catch(error => {
                            console.error('Error fetching price:', error);
                            alert('Gagal mengambil harga aktif: ' + error.message);
                            selectElement.selectedIndex = 0;
                            inputHarga.value = 0;
                            hitungSubtotal(row);
                        });
                } else {
                    inputHarga.value = 0;
                    hitungSubtotal(row);
                }
            }

            // 3. EVENT DELEGATION (UNTUK OPERASI TABEL DINAMIS)
            const table = document.querySelector('#table-item');

            // Deteksi perubahan produk
            table.addEventListener('change', function(e) {
                if (e.target.classList.contains('select-produk')) {
                    fetchHarga(e.target);
                }
            });

            // Deteksi input nilai Qty / Harga
            table.addEventListener('input', function(e) {
                if (e.target.classList.contains('qty') || e.target.classList.contains('harga')) {
                    let row = e.target.closest('tr');
                    hitungSubtotal(row);
                }
            });

            // Hapus baris item
            table.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-remove')) {
                    let rows = document.querySelectorAll('#table-item tbody tr');
                    
                    if (rows.length > 1) {
                        e.target.closest('tr').remove();
                        hitungTotal();
                    } else {
                        alert('Minimal harus ada satu item produk di dalam transaksi!');
                    }
                }
            });

            // 4. DETEKSI PERUBAHAN TANGGAL (Update ulang semua harga di tabel)
            document.getElementById('input-tanggal').addEventListener('change', function() {
                document.querySelectorAll('.select-produk').forEach(function(selectElement) {
                    if (selectElement.value !== "") {
                        fetchHarga(selectElement);
                    }
                });
            });

            // 5. FUNGSI HITUNG MATEMATIKA SUB-TOTAL
            function hitungSubtotal(row) {
                let qty = parseFloat(row.querySelector('.qty').value) || 0;
                let harga = parseFloat(row.querySelector('.harga').value) || 0;
                let subtotal = qty * harga;

                row.querySelector('.subtotal').value = subtotal;
                hitungTotal();
            }

            // 6. FUNGSI HITUNG MATEMATIKA GRAND TOTAL
            function hitungTotal() {
                let total = 0;
                document.querySelectorAll('.subtotal').forEach(function(item) {
                    total += parseFloat(item.value) || 0;
                });

                document.getElementById('grand-total').innerText = total.toLocaleString('id-ID');
            }
        });
    </script>
</x-app-layout>