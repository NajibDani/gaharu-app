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

    $masterActive = request()->routeIs([
        'kategori.*', 'barang.*', 'persediaan-awal.*', 'suppliers.*', 'customer.*',
        'gudangs.*', 'karyawan.*', 'pengaturan-gaji.*', 'resep.*', 'harga.*', 'coa.*', 'event-notifikasi.*',
    ]);

    $operationsActive = request()->routeIs([
        'pembelian.*', 'pengeluaran-bahan-baku.*', 'persediaan-awal.*', 'stok-gudang.index', 'stok-gudang.detail', 'stok-gudang-batch.*', 'stock-opname.*',
        'penjualan_pos.*', 'penjualanpos-detail.*', 'pesanan.*', 'pesanan-detail.*',
        'wo.*', 'produksi.*', 'pengiriman.*', 'penggajian.*', 'keterlambatan.*',
        'ck-orders.*', 'ck-produksi.*',
    ]);

    $financeActive = request()->routeIs([
        'jurnal.*', 'jurnal-penjualanb2b.*', 'jurnal-penjualanpos.*', 'jurnal-pembelian.*',
        'adjustment.*', 'closing.*',
    ]);

    $reportsActive = request()->routeIs([
        'laporan.*', 'penjualan_pos.laporan', 'stok-gudang.buku-pembantu.*', 'buku-pembantu.*',
    ]);
@endphp

<div class="sidebar d-flex flex-column justify-content-between">
    <div>
        <div class="sidebar-logo">
            <a href="{{ route('dashboard') }}">
                <x-application-logo class="mx-auto" style="height:60px; width:auto;" />
            </a>
        </div>

        <div class="sidebar-menu">

            {{-- ========================================================================= --}}
            {{-- DASHBOARD (semua role) --}}
            {{-- ========================================================================= --}}
            <div class="menu-group">
                <a href="{{ route('dashboard') }}"
                    class="menu-parent text-decoration-none d-flex align-items-center justify-content-start {{ request()->routeIs('dashboard') ? 'active-menu-root' : '' }}"
                    style="color: {{ request()->routeIs('dashboard') ? '#d88656' : '#ffffff' }};">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-house-door me-3 fs-5"></i>
                        <span>DASHBOARD UTAMA</span>
                    </div>
                </a>
            </div>

            @if($canRole(['Management', 'Direktur Keuangan']))
            <div class="menu-group">
                <a href="{{ route('dashboard.keuangan') }}"
                    class="menu-parent text-decoration-none d-flex align-items-center justify-content-start {{ request()->routeIs('dashboard.keuangan') ? 'active-menu-root' : '' }}"
                    style="color: {{ request()->routeIs('dashboard.keuangan') ? '#d88656' : '#ffffff' }};">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-wallet2 me-3 fs-5"></i>
                        <span>DASHBOARD KEUANGAN</span>
                    </div>
                </a>
            </div>
            @endif
            
            @if($canRole(['Central Kitchen', 'Cold Kitchen', 'Bagian Produksi']))
            <div class="menu-group">
                <a href="{{ route('laporan.produksi.dashboard') }}"
                    class="menu-parent text-decoration-none d-flex align-items-center justify-content-start {{ request()->routeIs('laporan.produksi.dashboard') ? 'active-menu-root' : '' }}"
                    style="color: {{ request()->routeIs('laporan.produksi.dashboard') ? '#d88656' : '#ffffff' }};">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-speedometer2 me-3 fs-5"></i>
                        <span>DASHBOARD PRODUKSI</span>
                    </div>
                </a>
            </div>
            @endif


            {{-- ========================================================================= --}}
            {{-- MASTER DATA --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Kepala Gudang', 'HRD', 'Management', 'Direktur Keuangan']))
            <div class="menu-group {{ $masterActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-folder2-open me-3 fs-5"></i>
                        <span>DATA MASTER</span>
                    </div>
                    <i class="bi {{ $masterActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">

                    {{-- PRODUK & RESEP --}}
                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Kepala Gudang']))
                    <div class="submenu-divider">PRODUK &amp; RESEP</div>
                        <a href="{{ route('kategori.index') }}" class="{{ request()->routeIs('kategori.*') ? 'active' : '' }}">
                            <i class="bi bi-tags me-2" style="font-size:12px;"></i>Kategori Barang
                        </a>
                        <a href="{{ route('barang.index') }}" class="{{ request()->routeIs('barang.*') ? 'active' : '' }}">
                            <i class="bi bi-box-seam me-2" style="font-size:12px;"></i>Daftar Barang &amp; Bahan
                        </a>
                        <a href="{{ route('persediaan-awal.index') }}" class="{{ request()->routeIs('persediaan-awal.*') ? 'active' : '' }}">
                            <i class="bi bi-inbox-fill me-2" style="font-size:12px;"></i>Persediaan Awal
                        </a>
                        @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga']))
                            <a href="{{ route('resep.index') }}" class="{{ request()->routeIs('resep.*') ? 'active' : '' }}">
                                <i class="bi bi-journal-text me-2" style="font-size:12px;"></i>Resep Produk
                            </a>
                            <a href="{{ route('harga.index') }}" class="{{ request()->routeIs('harga.*') ? 'active' : '' }}">
                                <i class="bi bi-currency-dollar me-2" style="font-size:12px;"></i>Harga Jual POS
                            </a>
                        @endif
                        <a href="{{ route('event-notifikasi.index') }}" class="{{ request()->routeIs('event-notifikasi.*') ? 'active' : '' }}">
                            <i class="bi bi-bell me-2" style="font-size:12px;"></i>Event &amp; Notifikasi
                        </a>
                    @endif

                    {{-- MITRA & DISTRIBUSI --}}
                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Kepala Gudang']))
                    <div class="submenu-divider">MITRA &amp; GUDANG</div>
                        <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                            <i class="bi bi-truck me-2" style="font-size:12px;"></i>Daftar Supplier
                        </a>
                        @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu']))
                            <a href="{{ route('customer.index') }}" class="{{ request()->routeIs('customer.*') ? 'active' : '' }}">
                                <i class="bi bi-people me-2" style="font-size:12px;"></i>Pelanggan B2B
                            </a>
                        @endif
                        <a href="{{ route('gudangs.index') }}" class="{{ request()->routeIs('gudangs.*') ? 'active' : '' }}">
                            <i class="bi bi-geo-alt me-2" style="font-size:12px;"></i>Daftar Gudang / Outlet
                        </a>
                    @endif

                    {{-- SDM & KEUANGAN --}}
                    @if($canRole(['HRD', 'Management', 'Direktur Keuangan']))
                    <div class="submenu-divider">SDM &amp; AKUN</div>
                        @if($canRole(['HRD', 'Management', 'Direktur Keuangan']))
                            <a href="{{ route('karyawan.index') }}" class="{{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
                                <i class="bi bi-person-badge me-2" style="font-size:12px;"></i>Data Karyawan
                            </a>
                        @endif
                        @if($canRole(['HRD']))
                            <a href="{{ route('pengaturan-gaji.index') }}" class="{{ request()->routeIs('pengaturan-gaji.*') ? 'active' : '' }}">
                                <i class="bi bi-sliders me-2" style="font-size:12px;"></i>Pengaturan Gaji
                            </a>
                        @endif
                        @if($canRole(['Management', 'Direktur Keuangan']))
                            <a href="{{ route('coa.index') }}" class="{{ request()->routeIs('coa.*') ? 'active' : '' }}">
                                <i class="bi bi-diagram-3 me-2" style="font-size:12px;"></i>Bagan Akun (COA)
                            </a>
                        @endif
                    @endif

                </div>
            </div>
            @endif


            {{-- ========================================================================= --}}
            {{-- OPERASIONAL --}}
            {{-- ========================================================================= --}}
            <div class="menu-group {{ $operationsActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-gear me-3 fs-5"></i>
                        <span>OPERASIONAL</span>
                    </div>
                    <i class="bi {{ $operationsActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">

                    {{-- ── PESANAN & PRODUKSI B2B ─────────────────────── --}}
                    <div class="submenu-divider">PESANAN &amp; PRODUKSI B2B</div>
                    <a href="{{ route('pesanan.index') }}"
                       class="submenu-step {{ request()->routeIs('pesanan.*') ? 'active' : '' }}">
                        <span class="step-badge">1</span>
                        <i class="bi bi-briefcase me-2" style="font-size:12px;"></i>Pesanan B2B
                    </a>
                    <a href="{{ route('produksi.index') }}"
                       class="submenu-step {{ (request()->routeIs('produksi.*') && !request()->routeIs('laporan.produksi.*')) || request()->routeIs('wo.*') ? 'active' : '' }}">
                        <span class="step-badge">2</span>
                        <i class="bi bi-hammer me-2" style="font-size:12px;"></i>Produksi B2B
                    </a>

                    {{-- ── PENJUALAN KASIR / POS ────────────────────── --}}
                    <div class="submenu-divider">PENJUALAN KASIR (POS)</div>
                    <a href="{{ route('penjualan_pos.index') }}" class="{{ request()->routeIs('penjualan_pos.*') ? 'active' : '' }}">
                        <i class="bi bi-cart me-2" style="font-size:12px;"></i>Rekap Penjualan POS
                    </a>

                    {{-- ── CENTRAL KITCHEN ─────────── --}}
                    <div class="submenu-divider">DAPUR PUSAT (CENTRAL KITCHEN)</div>
                    <a href="{{ route('ck-orders.index') }}"
                       class="submenu-step {{ request()->routeIs('ck-orders.*') ? 'active' : '' }}">
                        <span class="step-badge">1</span>
                        <i class="bi bi-shop me-2" style="font-size:12px;"></i>Permintaan Dapur Pusat
                    </a>
                    <a href="{{ route('ck-produksi.index') }}"
                       class="submenu-step {{ request()->routeIs('ck-produksi.*') ? 'active' : '' }}">
                        <span class="step-badge">2</span>
                        <i class="bi bi-gear-wide-connected me-2" style="font-size:12px;"></i>Produksi Dapur Pusat
                    </a>

                    {{-- ── PENGIRIMAN ──── --}}
                    <div class="submenu-divider">PENGIRIMAN &amp; LOGISTIK</div>
                    <a href="{{ route('pengiriman.index') }}"
                       class="submenu-step {{ request()->routeIs('pengiriman.*') ? 'active' : '' }}">
                        <i class="bi bi-truck me-2" style="font-size:12px;"></i>Pengiriman Barang
                    </a>

                    {{-- ── INVENTORY & GUDANG ───────────── --}}
                    <div class="submenu-divider">PENGELOLAAN STOK</div>
                    <a href="{{ route('pembelian.index') }}" class="{{ request()->routeIs('pembelian.*') ? 'active' : '' }}">
                        <i class="bi bi-bag-plus me-2" style="font-size:12px;"></i>Pembelian Bahan Baku
                    </a>
                    <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="{{ request()->routeIs('pengeluaran-bahan-baku.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-right-circle me-2" style="font-size:12px;"></i>Permintaan / Transfer Bahan
                    </a>
                    <a href="{{ route('persediaan-awal.index') }}" class="{{ request()->routeIs('persediaan-awal.*') ? 'active' : '' }}">
                        <i class="bi bi-inbox-fill me-2" style="font-size:12px;"></i>Persediaan Awal
                    </a>
                    <a href="{{ route('stok-gudang.index') }}" class="{{ request()->routeIs('stok-gudang.index') || request()->routeIs('stok-gudang.detail') || request()->routeIs('stok-gudang-batch.*') ? 'active' : '' }}">
                        <i class="bi bi-boxes me-2" style="font-size:12px;"></i>Stok Gudang
                    </a>
                    <a href="{{ route('stock-opname.index') }}" class="{{ request()->routeIs('stock-opname.*') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-check me-2" style="font-size:12px;"></i>Stock Opname (Cek Fisik)
                    </a>

                    {{-- ── HR ──────────────────────────── --}}
                    @if($canRole(['HRD']))
                    <div class="submenu-divider">PENGGAJIAN (HR)</div>
                        <a href="{{ route('penggajian.index') }}" class="{{ request()->routeIs('penggajian.*') ? 'active' : '' }}">
                            <i class="bi bi-cash-stack me-2" style="font-size:12px;"></i>Penggajian Karyawan
                        </a>
                        <a href="{{ route('keterlambatan.index') }}" class="{{ request()->routeIs('keterlambatan.*') ? 'active' : '' }}">
                            <i class="bi bi-clock-history me-2" style="font-size:12px;"></i>Data Keterlambatan
                        </a>
                    @endif

                </div>
            </div>


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
                        <i class="bi bi-journal-plus me-2" style="font-size:12px;"></i>Jurnal Penjualan B2B
                    </a>
                    <a href="{{ route('jurnal-penjualanpos.index') }}" class="{{ request()->routeIs('jurnal-penjualanpos.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-minus me-2" style="font-size:12px;"></i>Jurnal Penjualan POS
                    </a>

                    <div class="submenu-divider">PENYESUAIAN &amp; TUTUP BUKU</div>
                    <a href="{{ route('adjustment.index') }}" class="{{ request()->routeIs('adjustment.*') ? 'active' : '' }}">
                        <i class="bi bi-sliders me-2" style="font-size:12px;"></i>Jurnal Penyesuaian
                    </a>
                    <a href="{{ route('closing.index') }}" class="{{ request()->routeIs('closing.*') ? 'active' : '' }}">
                        <i class="bi bi-lock me-2" style="font-size:12px;"></i>Tutup Buku Bulanan
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
                    {{-- ── LAPORAN PERSEDIAAN & STOK ──────────────── --}}
                    @if($canRole(['Kepala Gudang', 'Operasional Gaharu', 'Kepala Outlet Gaharu', 'Management', 'Direktur Keuangan', 'Operasional Kejingga', 'Kepala Outlet Kejingga']))
                        <div class="submenu-divider">PERSEDIAAN &amp; STOK</div>
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

                    {{-- ── LAPORAN KEUANGAN UTAMA ───────────── --}}
                    @if($canRole(['Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">LAPORAN KEUANGAN</div>
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
                    @endif

                    {{-- ── BUKU BESAR & PEMBANTU ────── --}}
                    @if($canRole(['Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">BUKU BESAR &amp; PEMBANTU</div>
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

                    {{-- ── PENJUALAN & HPP ──────────────────── --}}
                    @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Operasional Kejingga', 'Kepala Outlet Kejingga', 'Management', 'Direktur Keuangan']))
                        <div class="submenu-divider">PENJUALAN &amp; HPP</div>

                        @if($canRole(['Operasional Gaharu', 'Kepala Outlet Gaharu', 'Management', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.penjualan') }}" class="{{ request()->routeIs('laporan.penjualan') ? 'active' : '' }}">
                                <i class="bi bi-building me-2" style="font-size:12px;"></i>Laporan Penjualan B2B
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

                    {{-- ── LAPORAN PRODUKSI ─────────────── --}}
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

    <div style="padding:24px 0; border-top:1px solid #4a4a4a;">
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