@php
    // Ambil nama role user yang sedang login
    $role = auth()->user()->role->nama ?? '';

    $roleMap = [
        'Superadmin'             => 'Super Admin',
        'Administrator'          => 'Super Admin',
        'Bagian Produksi'        => 'Central Kitchen',
        'Kepala Outlet Gaharu'   => 'Operasional Gaharu',
        'Kepala Outlet Kejingga' => 'Operasional Kejingga',
        'Direktur Keuangan'      => 'Management',
    ];

    $normalizedRole = $roleMap[$role] ?? $role;
    $isSuperAdmin = in_array($normalizedRole, ['Super Admin', 'Superadmin']);

    $canRole = function (array $allowed) use ($isSuperAdmin, $role, $normalizedRole, $roleMap) {
        if ($isSuperAdmin) return true;
        foreach ($allowed as $a) {
            $normA = $roleMap[$a] ?? $a;
            if ($role === $a || $normalizedRole === $normA || $role === $normA || $normalizedRole === $a) {
                return true;
            }
        }
        return false;
    };

    $dashboardActive = request()->routeIs(['dashboard', 'dashboard.keuangan', 'laporan.produksi.dashboard']);

    $penjualanActive = request()->routeIs(['penjualan_pos.*', 'pesanan.*', 'customer.*']);

    $produksiActive = request()->routeIs(['produksi.*', 'wo.*', 'ck-orders.*', 'ck-produksi.*']);

    $stokActive = request()->routeIs([
        'stok-gudang.index', 'stok-gudang.detail', 'stok-gudang-batch.*',
        'pembelian.*', 'pembelian-kejingga.*', 'pengeluaran-bahan-baku.*',
        'stock-opname.*', 'pengiriman.*', 'persediaan-awal.*',
    ]);

    $financeActive = request()->routeIs([
        'jurnal.*', 'jurnal-penjualanb2b.*', 'jurnal-penjualanpos.*', 'jurnal-pembelian.*',
        'adjustment.*', 'closing.*', 'coa.*',
    ]);

    $reportsActive = request()->routeIs([
        'laporan.*', 'penjualan_pos.laporan', 'stok-gudang.buku-pembantu.*', 'buku-pembantu.*',
    ]);

    $masterActive = request()->routeIs([
        'kategori.*', 'barang.*', 'suppliers.*', 'gudangs.*',
        'karyawan.*', 'pengaturan-gaji.*', 'harga.*', 'event-notifikasi.*', 'resep.*',
    ]);
@endphp

<div class="sidebar d-flex flex-column justify-content-between">
    <div>
        <div class="sidebar-logo position-relative">
            <button type="button" class="btn-close btn-close-white d-lg-none position-absolute top-50 end-0 translate-middle-y me-3" id="sidebarCloseMobile" aria-label="Tutup Menu"></button>
            <a href="{{ route('dashboard') }}">
                <x-application-logo class="mx-auto" style="height:55px; width:auto;" />
            </a>
        </div>

        <div class="sidebar-menu">

            {{-- ========================================================================= --}}
            {{-- DASHBOARD --}}
            {{-- ========================================================================= --}}
            <div class="menu-group {{ $dashboardActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion {{ request()->routeIs('dashboard') && !request()->routeIs('dashboard.keuangan') && !request()->routeIs('laporan.produksi.dashboard') ? 'active-menu-root' : '' }}">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-house-door me-3 fs-5"></i>
                        <span>DASHBOARD</span>
                    </div>
                    <i class="bi {{ $dashboardActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>
                <div class="submenu-content">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') && !request()->routeIs('dashboard.keuangan') && !request()->routeIs('laporan.produksi.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer me-2" style="font-size:12px;"></i>Dashboard Utama
                    </a>
                    @if($canRole(['Management', 'Direktur Keuangan']))
                        <a href="{{ route('dashboard.keuangan') }}" class="{{ request()->routeIs('dashboard.keuangan') ? 'active' : '' }}">
                            <i class="bi bi-wallet2 me-2" style="font-size:12px;"></i>Dashboard Keuangan
                        </a>
                    @endif
                    @if($canRole(['Central Kitchen', 'Cold Kitchen', 'Bagian Produksi']))
                        <a href="{{ route('laporan.produksi.dashboard') }}" class="{{ request()->routeIs('laporan.produksi.dashboard') ? 'active' : '' }}">
                            <i class="bi bi-gear-wide me-2" style="font-size:12px;"></i>Dashboard Produksi
                        </a>
                    @endif
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- DATA MASTER & SDM (Nomor 2) --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Kepala Gudang', 'HRD', 'Management', 'Direktur Keuangan']))
            <div class="menu-group {{ $masterActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-database me-3 fs-5"></i>
                        <span>DATA MASTER &amp; SDM</span>
                    </div>
                    <i class="bi {{ $masterActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">
                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Kepala Gudang']))
                        <div class="submenu-divider">BARANG &amp; KATEGORI</div>
                        <a href="{{ route('kategori.index') }}" class="{{ request()->routeIs('kategori.*') ? 'active' : '' }}">
                            <i class="bi bi-tags me-2" style="font-size:12px;"></i>Kategori Barang
                        </a>
                        <a href="{{ route('barang.index') }}" class="{{ request()->routeIs('barang.*') ? 'active' : '' }}">
                            <i class="bi bi-box-seam me-2" style="font-size:12px;"></i>Daftar Barang &amp; Bahan
                        </a>
                        <a href="{{ route('resep.index') }}" class="{{ request()->routeIs('resep.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-text me-2" style="font-size:12px;"></i>Resep Produk
                        </a>
                        @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga']))
                            <a href="{{ route('harga.index') }}" class="{{ request()->routeIs('harga.*') ? 'active' : '' }}">
                                <i class="bi bi-currency-dollar me-2" style="font-size:12px;"></i>Harga Jual POS
                            </a>
                        @endif
                        <a href="{{ route('event-notifikasi.index') }}" class="{{ request()->routeIs('event-notifikasi.*') ? 'active' : '' }}">
                            <i class="bi bi-bell me-2" style="font-size:12px;"></i>Event &amp; Notifikasi
                        </a>
                    @endif

                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Kepala Gudang']))
                        <div class="submenu-divider">SUPPLIER &amp; GUDANG</div>
                        <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                            <i class="bi bi-truck me-2" style="font-size:12px;"></i>Daftar Supplier
                        </a>
                        <a href="{{ route('gudangs.index') }}" class="{{ request()->routeIs('gudangs.*') ? 'active' : '' }}">
                            <i class="bi bi-geo-alt me-2" style="font-size:12px;"></i>Daftar Gudang / Outlet
                        </a>
                    @endif

                    @if($canRole(['HRD', 'Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">SDM &amp; PENGGAJIAN</div>
                        <a href="{{ route('karyawan.index') }}" class="{{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
                            <i class="bi bi-person-badge me-2" style="font-size:12px;"></i>Data Karyawan
                        </a>
                        @if($canRole(['HRD']))
                            <a href="{{ route('pengaturan-gaji.index') }}" class="{{ request()->routeIs('pengaturan-gaji.*') ? 'active' : '' }}">
                                <i class="bi bi-sliders me-2" style="font-size:12px;"></i>Pengaturan Gaji
                            </a>
                            <a href="{{ route('penggajian.index') }}" class="{{ request()->routeIs('penggajian.*') ? 'active' : '' }}">
                                <i class="bi bi-cash-stack me-2" style="font-size:12px;"></i>Penggajian Karyawan
                            </a>
                            <a href="{{ route('keterlambatan.index') }}" class="{{ request()->routeIs('keterlambatan.*') ? 'active' : '' }}">
                                <i class="bi bi-clock-history me-2" style="font-size:12px;"></i>Data Keterlambatan
                            </a>
                        @endif
                    @endif
                </div>
            </div>
            @endif

            {{-- ========================================================================= --}}
            {{-- OPERASIONAL (Nomor 3) --}}
            {{-- ========================================================================= --}}
            <div class="menu-group {{ $stokActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-gear me-3 fs-5"></i>
                        <span>OPERASIONAL</span>
                    </div>
                    <i class="bi {{ $stokActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>
                <div class="submenu-content">
                    <a href="{{ route('stok-gudang.index') }}" class="{{ request()->routeIs('stok-gudang.index') || request()->routeIs('stok-gudang.detail') || request()->routeIs('stok-gudang-batch.*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam me-2" style="font-size:12px;"></i>Stok Gudang
                    </a>
                    <a href="{{ route('pembelian.index') }}" class="{{ request()->routeIs('pembelian.*') && !request()->routeIs('pembelian-kejingga.*') ? 'active' : '' }}">
                        <i class="bi bi-bag-plus me-2" style="font-size:12px;"></i>Pembelian Bahan Baku
                    </a>
                    @if($canRole(['Super Admin', 'Superadmin', 'Kepala Outlet Kejingga', 'Operasional Kejingga']))
                        <a href="{{ route('pembelian-kejingga.index') }}" class="{{ request()->routeIs('pembelian-kejingga.*') ? 'active' : '' }}">
                            <i class="bi bi-cart-plus me-2" style="font-size:12px;"></i>Pembelian Kejingga (Luar)
                        </a>
                    @endif
                    <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="{{ request()->routeIs('pengeluaran-bahan-baku.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-right-circle me-2" style="font-size:12px;"></i>Permintaan / Transfer Bahan
                    </a>
                    <a href="{{ route('stock-opname.index') }}" class="{{ request()->routeIs('stock-opname.*') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-check me-2" style="font-size:12px;"></i>Stock Opname (Cek Fisik)
                    </a>
                    <a href="{{ route('pengiriman.index') }}" class="{{ request()->routeIs('pengiriman.*') ? 'active' : '' }}">
                        <i class="bi bi-truck me-2" style="font-size:12px;"></i>Pengiriman &amp; Logistik
                    </a>
                    <a href="{{ route('persediaan-awal.index') }}" class="{{ request()->routeIs('persediaan-awal.*') ? 'active' : '' }}">
                        <i class="bi bi-inbox-fill me-2" style="font-size:12px;"></i>Persediaan Awal
                    </a>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- PENJUALAN POS (Nomor 4) --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Management', 'Direktur Keuangan']))
            <div class="menu-group {{ $penjualanActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-cart3 me-3 fs-5"></i>
                        <span>PENJUALAN POS</span>
                    </div>
                    <i class="bi {{ $penjualanActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>
                <div class="submenu-content">
                    <a href="{{ route('penjualan_pos.index') }}" class="{{ request()->routeIs('penjualan_pos.index') ? 'active' : '' }}">
                        <i class="bi bi-receipt me-2" style="font-size:12px;"></i>Rekap Penjualan POS
                    </a>
                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu']))
                        <a href="{{ route('customer.index') }}" class="{{ request()->routeIs('customer.*') ? 'active' : '' }}">
                            <i class="bi bi-people me-2" style="font-size:12px;"></i>Pelanggan Cold Kitchen
                        </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- ========================================================================= --}}
            {{-- CENTRAL KITCHEN & COLD KITCHEN (Nomor 5) --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Central Kitchen', 'Cold Kitchen', 'Bagian Produksi', 'Management', 'Direktur Keuangan']))
            <div class="menu-group {{ $produksiActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-gear-wide-connected me-3 fs-5"></i>
                        <span>CENTRAL KITCHEN &amp; COLD KITCHEN</span>
                    </div>
                    <i class="bi {{ $produksiActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>
                <div class="submenu-content">
                    <a href="{{ route('pesanan.index') }}" class="{{ request()->routeIs('pesanan.*') ? 'active' : '' }}">
                        <i class="bi bi-briefcase me-2" style="font-size:12px;"></i>Permintaan Cold Kitchen
                    </a>
                    <a href="{{ route('produksi.index') }}" class="{{ (request()->routeIs('produksi.*') && !request()->routeIs('laporan.produksi.*')) || request()->routeIs('wo.*') ? 'active' : '' }}">
                        <i class="bi bi-hammer me-2" style="font-size:12px;"></i>Produksi Cold Kitchen
                    </a>
                    <a href="{{ route('ck-orders.index') }}" class="{{ request()->routeIs('ck-orders.*') ? 'active' : '' }}">
                        <i class="bi bi-shop me-2" style="font-size:12px;"></i>Permintaan Central Kitchen
                    </a>
                    <a href="{{ route('ck-produksi.index') }}" class="{{ request()->routeIs('ck-produksi.*') ? 'active' : '' }}">
                        <i class="bi bi-cpu me-2" style="font-size:12px;"></i>Produksi Central Kitchen
                    </a>
                </div>
            </div>
            @endif

            {{-- ========================================================================= --}}
            {{-- KEUANGAN --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Management', 'Direktur Keuangan']))
            <div class="menu-group {{ $financeActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-wallet2 me-3 fs-5"></i>
                        <span>KEUANGAN</span>
                    </div>
                    <i class="bi {{ $financeActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">
                    <div class="submenu-divider">JURNAL TRANSAKSI</div>
                    <a href="{{ route('jurnal.index') }}" class="{{ request()->routeIs('jurnal.index') ? 'active' : '' }}">
                        <i class="bi bi-journal-check me-2" style="font-size:12px;"></i>Jurnal Umum
                    </a>
                    <a href="{{ route('jurnal-pembelian.index') }}" class="{{ request()->routeIs('jurnal-pembelian.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-plus me-2" style="font-size:12px;"></i>Jurnal Pembelian
                    </a>
                    <a href="{{ route('jurnal-penjualanb2b.index') }}" class="{{ request()->routeIs('jurnal-penjualanb2b.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-plus me-2" style="font-size:12px;"></i>Jurnal Penjualan Cold Kitchen
                    </a>
                    <a href="{{ route('jurnal-penjualanpos.index') }}" class="{{ request()->routeIs('jurnal-penjualanpos.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-minus me-2" style="font-size:12px;"></i>Jurnal Penjualan POS
                    </a>

                    <div class="submenu-divider">PENYESUAIAN &amp; AKUN</div>
                    <a href="{{ route('adjustment.index') }}" class="{{ request()->routeIs('adjustment.*') ? 'active' : '' }}">
                        <i class="bi bi-sliders me-2" style="font-size:12px;"></i>Jurnal Penyesuaian
                    </a>
                    <a href="{{ route('closing.index') }}" class="{{ request()->routeIs('closing.*') ? 'active' : '' }}">
                        <i class="bi bi-lock me-2" style="font-size:12px;"></i>Tutup Buku Bulanan
                    </a>
                    <a href="{{ route('coa.index') }}" class="{{ request()->routeIs('coa.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3 me-2" style="font-size:12px;"></i>Bagan Akun (COA)
                    </a>
                </div>
            </div>
            @endif

            {{-- ========================================================================= --}}
            {{-- LAPORAN --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Central Kitchen', 'Cold Kitchen', 'Bagian Produksi', 'Kepala Gudang', 'Management', 'Direktur Keuangan']))
            <div class="menu-group {{ $reportsActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bar-chart-line me-3 fs-5"></i>
                        <span>LAPORAN</span>
                    </div>
                    <i class="bi {{ $reportsActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">
                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">PENJUALAN</div>
                        @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Management', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.penjualan') }}" class="{{ request()->routeIs('laporan.penjualan') ? 'active' : '' }}">
                                <i class="bi bi-building me-2" style="font-size:12px;"></i>Laporan Penjualan Cold Kitchen
                            </a>
                        @endif
                        <a href="{{ route('penjualan_pos.laporan') }}" class="{{ request()->routeIs('penjualan_pos.laporan') ? 'active' : '' }}">
                            <i class="bi bi-receipt me-2" style="font-size:12px;"></i>Laporan Penjualan POS
                        </a>
                        @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Management', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.hpp') }}" class="{{ request()->routeIs('laporan.hpp') ? 'active' : '' }}">
                                <i class="bi bi-cpu me-2" style="font-size:12px;"></i>Laporan HPP Produk
                            </a>
                        @endif
                    @endif

                    @if($canRole(['Kepala Gudang', 'Operasional Gaharu', 'Kepala Outlet Gaharu', 'Management', 'Direktur Keuangan', 'Operasional Kejingga', 'Kepala Outlet Kejingga']))
                        <div class="submenu-divider">STOK &amp; PERSEDIAAN</div>
                        @if($canRole(['Kepala Gudang', 'Operasional Gaharu', 'Kepala Outlet Gaharu', 'Management', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.pembelian') }}" class="{{ request()->routeIs('laporan.pembelian') ? 'active' : '' }}">
                                <i class="bi bi-cart-check me-2" style="font-size:12px;"></i>Laporan Pembelian
                            </a>
                        @endif
                        <a href="{{ route('laporan.stok-gudang') }}" class="{{ request()->routeIs('laporan.stok-gudang') ? 'active' : '' }}">
                            <i class="bi bi-boxes me-2" style="font-size:12px;"></i>Posisi Stok Gudang
                        </a>
                        <a href="{{ route('laporan.pengeluaran-bahan-baku') }}" class="{{ request()->routeIs('laporan.pengeluaran-bahan-baku') ? 'active' : '' }}">
                            <i class="bi bi-box-arrow-up me-2" style="font-size:12px;"></i>Laporan Pengeluaran Bahan
                        </a>
                        <a href="{{ route('laporan.stock-opname') }}" class="{{ request()->routeIs('laporan.stock-opname') ? 'active' : '' }}">
                            <i class="bi bi-clipboard-check me-2" style="font-size:12px;"></i>Laporan Stock Opname
                        </a>
                    @endif

                    @if($canRole(['Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">KEUANGAN</div>
                        <a href="{{ route('laporan.laba-rugi.index') }}" class="{{ request()->routeIs('laporan.laba-rugi.*') ? 'active' : '' }}">
                            <i class="bi bi-graph-up me-2" style="font-size:12px;"></i>Laba Rugi (Profit &amp; Loss)
                        </a>
                        <a href="{{ route('laporan.neraca.index') }}" class="{{ request()->routeIs('laporan.neraca.*') ? 'active' : '' }}">
                            <i class="bi bi-clipboard-data me-2" style="font-size:12px;"></i>Neraca (Balance Sheet)
                        </a>
                        <a href="{{ route('laporan.arus-kas.index') }}" class="{{ request()->routeIs('laporan.arus-kas.*') ? 'active' : '' }}">
                            <i class="bi bi-cash-coin me-2" style="font-size:12px;"></i>Arus Kas (Cash Flow)
                        </a>
                        <a href="{{ route('laporan.perubahan-ekuitas.index') }}" class="{{ request()->routeIs('perubahan-ekuitas.*') ? 'active' : '' }}">
                            <i class="bi bi-book-half me-2" style="font-size:12px;"></i>Perubahan Modal (Ekuitas)
                        </a>

                        <div class="submenu-divider">BUKU BESAR</div>
                        <a href="{{ route('laporan.neraca-saldo.index') }}" class="{{ request()->routeIs('laporan.neraca-saldo.*') ? 'active' : '' }}">
                            <i class="bi bi-list-check me-2" style="font-size:12px;"></i>Neraca Saldo (Trial Balance)
                        </a>
                        <a href="{{ route('laporan.buku-besar.index') }}" class="{{ request()->routeIs('laporan.buku-besar.*') ? 'active' : '' }}">
                            <i class="bi bi-folder-fill me-2" style="font-size:12px;"></i>Buku Besar (General Ledger)
                        </a>
                        <a href="{{ route('buku-pembantu.index') }}" class="{{ request()->routeIs('buku-pembantu.*') ? 'active' : '' }}">
                            <i class="bi bi-book-half me-2" style="font-size:12px;"></i>Buku Pembantu Piutang/Utang
                        </a>
                        <a href="{{ route('stok-gudang.buku-pembantu.index') }}" class="{{ request()->routeIs('stok-gudang.buku-pembantu.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-bookmark-fill me-2" style="font-size:12px;"></i>Buku Pembantu Persediaan
                        </a>
                    @endif

                    @if($canRole(['Central Kitchen', 'Cold Kitchen', 'Bagian Produksi', 'Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">PRODUKSI</div>
                        <a href="{{ route('laporan.rekapitulasi') }}" class="{{ request()->routeIs('laporan.rekapitulasi') ? 'active' : '' }}">
                            <i class="bi bi-gear-wide-connected me-2" style="font-size:12px;"></i>Rekapitulasi Produksi
                        </a>
                    @endif
                </div>
            </div>
            @endif

        </div>
    </div>

    <div style="padding:20px 0; border-top:1px solid rgba(255, 255, 255, 0.08);">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn d-flex align-items-center justify-content-center">
                <i class="bi bi-box-arrow-left me-2"></i>
                Keluar (Logout)
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toggle-accordion').forEach(button => {
            button.addEventListener('click', () => {
                const group = button.parentElement;
                const chevron = button.querySelector('.chevron-icon');
                group.classList.toggle('open');
                chevron.classList.toggle('bi-chevron-right', !group.classList.contains('open'));
                chevron.classList.toggle('bi-chevron-down', group.classList.contains('open'));
            });
        });
    });
</script>