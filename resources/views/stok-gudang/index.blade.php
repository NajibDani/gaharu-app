<x-app-layout>

    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <h4 class="fw-bold">
                Stok Gudang
            </h4>

        </div>

        <!-- FILTER -->

        <div class="card shadow-sm mb-3">

            <div class="card-body">

                <form method="GET"
                      action="{{ route('stok-gudang.index') }}">

                    <div class="row">

                        <div class="col-md-5">

                            <label class="form-label">
                                Filter Gudang
                            </label>

                            <select
                                name="gudang_id"
                                class="form-control">

                                <option value="">
                                    -- Semua Gudang --
                                </option>

                                @foreach($gudangs as $gudang)

                                    <option
                                        value="{{ $gudang->id }}"
                                        {{ $gudangId == $gudang->id ? 'selected' : '' }}>

                                        {{ $gudang->nama }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                        <div class="col-md-5">

                            <label class="form-label">
                                Filter Barang
                            </label>

                            <select
                                name="barang_id"
                                class="form-control">

                                <option value="">
                                    -- Semua Barang --
                                </option>

                                @foreach($barangs as $barang)

                                    <option
                                        value="{{ $barang->id }}"
                                        {{ $barangId == $barang->id ? 'selected' : '' }}>

                                        {{ $barang->kode_barang }}
                                        -
                                        {{ $barang->nama }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                        <div class="col-md-2 d-flex align-items-end">

                            <button
                                type="submit"
                                class="btn btn-primary me-2">

                                Filter

                            </button>

                            <a href="{{ route('stok-gudang.index') }}"
                               class="btn btn-secondary">

                                Reset

                            </a>

                        </div>

                    </div>

                </form>

            </div>

        </div>

        <!-- TABEL -->

        <div class="card shadow-sm">

            <div class="card-body">

                <table
                    class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th width="200">
                                Gudang
                            </th>

                            <th width="150">
                                Kode Barang
                            </th>

                            <th>
                                Nama Barang
                            </th>

                            <th width="180">
                                Jumlah Stok
                            </th>

                            <th width="150">
                                Status
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($stokGudang as $stok)

                            <tr class="{{ $stok->qty <= 0 ? 'table-danger' : '' }}">

                                <td>
                                    {{ $stok->nama_gudang }}
                                </td>

                                <td>
                                    {{ $stok->kode_barang }}
                                </td>

                                <td>
                                    {{ $stok->nama }}
                                </td>

                                <td>

                                    {{ number_format($stok->qty, 2, ',', '.') }}

                                    {{ $stok->satuan }}

                                </td>

                                <td>

                                    @if($stok->qty <= 0)

                                        <span class="badge bg-danger">

                                            STOK HABIS

                                        </span>

                                    @else

                                        <span class="badge bg-success">

                                            TERSEDIA

                                        </span>

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="5"
                                    class="text-center text-muted">

                                    Belum ada data stok.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

                <div class="mt-3">

                    {{ $stokGudang->links() }}

                </div>

            </div>

        </div>

    </div>

</x-app-layout>