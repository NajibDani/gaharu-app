@php
    // Ambil nama role user yang sedang login
    $role = auth()->user()->role->nama;

    $isSuperAdmin = in_array($role, ['Super Admin', 'Administrator']);
    $canRole = fn (array $allowed) => $isSuperAdmin || in_array($role, $allowed);

    $masterActive = request()->routeIs([
        'kategori.*', 'barang.*', 'suppliers.*', 'customer.*',
        'gudangs.*', 'karyawan.*', 'resep.*', 'harga.*', 'coa.*',
    ]);

    $operationsActive = request()->routeIs([
        'pembelian.*', 'pengeluaran-bahan-baku.*', 'stok-gudang.*', 'stock-opname.*',
        'penjualan_pos.*', 'penjualanpos-detail.*', 'pesanan.*', 'pesanan-detail.*',
        'wo.*', 'produksi.*', 'pengiriman.*', 'penggajian.*',
        'ck-orders.*', 'ck-produksi.*',
    ]);

    $financeActive = request()->routeIs([
        'jurnal.*', 'jurnal-penjualanb2b.*', 'jurnal-penjualanpos.*', 'jurnal-pembelian.*',
        'adjustment.*', 'closing.*',
        'laporan.laba-rugi.*', 'laporan.neraca.*', 'laporan.arus-kas.*',
        'laporan.neraca-saldo.*', 'laporan.buku-besar.*',
    ]);

    $reportsActive = request()->routeIs([
        'laporan.pembelian', 'laporan.stok-gudang', 'laporan.pengeluaran-bahan-baku',
        'laporan.stock-opname', 'laporan-penjualan-pos', 'laporan.penjualan',
        'laporan.hpp', 'laporan.rekapitulasi',
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
                        <span>DASHBOARD</span>
                    </div>
                </a>
            </div>

            @if($canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
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
            
            @if($canRole(['Bagian Produksi']))
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
            @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Kepala Gudang', 'HRD', 'Direktur Keuangan']))
            <div class="menu-group {{ $masterActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-folder2-open me-3 fs-5"></i>
                        <span>MASTER DATA</span>
                    </div>
                    <i class="bi {{ $masterActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">

                    {{-- PRODUK & RESEP --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Kepala Gudang']))
                    <div class="submenu-divider">PRODUK & RESEP</div>
                        @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Kepala Gudang']))
                            <a href="{{ route('kategori.index') }}" class="{{ request()->routeIs('kategori.*') ? 'active' : '' }}">
                                <i class="bi bi-tags me-2" style="font-size:12px;"></i>Kategori Barang
                            </a>
                            <a href="{{ route('barang.index') }}" class="{{ request()->routeIs('barang.*') ? 'active' : '' }}">
                                <i class="bi bi-box-seam me-2" style="font-size:12px;"></i>Data Barang / Items
                            </a>
                        @endif
                        @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga']))
                            <a href="{{ route('resep.index') }}" class="{{ request()->routeIs('resep.*') ? 'active' : '' }}">
                                <i class="bi bi-journal-text me-2" style="font-size:12px;"></i>Resep Produksi
                            </a>
                            <a href="{{ route('harga.index') }}" class="{{ request()->routeIs('harga.*') ? 'active' : '' }}">
                                <i class="bi bi-currency-dollar me-2" style="font-size:12px;"></i>Harga POS
                            </a>
                        @endif
                    @endif

                    {{-- MITRA & DISTRIBUSI --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Gudang']))
                    <div class="submenu-divider">MITRA & DISTRIBUSI</div>
                        @if($canRole(['Kepala Outlet Gaharu', 'Kepala Gudang']))
                            <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                                <i class="bi bi-truck me-2" style="font-size:12px;"></i>Supplier
                            </a>
                        @endif
                        @if($canRole(['Kepala Outlet Gaharu']))
                            <a href="{{ route('customer.index') }}" class="{{ request()->routeIs('customer.*') ? 'active' : '' }}">
                                <i class="bi bi-people me-2" style="font-size:12px;"></i>Customer B2B
                            </a>
                        @endif
                        @if($canRole(['Kepala Gudang', 'Kepala Outlet Gaharu']))
                            <a href="{{ route('gudangs.index') }}" class="{{ request()->routeIs('gudangs.*') ? 'active' : '' }}">
                                <i class="bi bi-geo-alt me-2" style="font-size:12px;"></i>Gudang / Warehouse
                            </a>
                        @endif
                    @endif

                    {{-- SDM & KEUANGAN --}}
                    @if($canRole(['HRD', 'Kepala Outlet Gaharu', 'Direktur Keuangan']))
                    <div class="submenu-divider">SDM & KEUANGAN</div>
                        @if($canRole(['HRD']))
                            <a href="{{ route('karyawan.index') }}" class="{{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
                                <i class="bi bi-person-badge me-2" style="font-size:12px;"></i>Data Karyawan
                            </a>
                        @endif
                        @if($canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
                            <a href="{{ route('coa.index') }}" class="{{ request()->routeIs('coa.*') ? 'active' : '' }}">
                                <i class="bi bi-diagram-3 me-2" style="font-size:12px;"></i>Chart of Accounts
                            </a>
                        @endif
                    @endif

                </div>
            </div>
            @endif


            {{-- ========================================================================= --}}
            {{-- OPERATIONS --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Kepala Gudang', 'Bagian Produksi', 'HRD', 'Direktur Keuangan']))
            <div class="menu-group {{ $operationsActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-gear me-3 fs-5"></i>
                        <span>OPERATIONS</span>
                    </div>
                    <i class="bi {{ $operationsActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">

                    {{-- ── B2B FLOW ─────────────────────── --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Bagian Produksi']))
                    <div class="submenu-divider">B2B — ORDER TO DELIVERY</div>

                    {{-- Step 1: Order --}}
                    @if($canRole(['Kepala Outlet Gaharu']))
                    <a href="{{ route('pesanan.index') }}"
                       class="submenu-step {{ request()->routeIs('pesanan.*') ? 'active' : '' }}">
                        <span class="step-badge">1</span>
                        <i class="bi bi-briefcase me-2" style="font-size:12px;"></i>B2B Orders
                    </a>
                    @endif

                    {{-- Step 2: B2B Produksi (Unified WO & Produksi) --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Bagian Produksi']))
                    <a href="{{ route('produksi.index') }}"
                       class="submenu-step {{ (request()->routeIs('produksi.*') && !request()->routeIs('laporan.produksi.*')) || request()->routeIs('wo.*') ? 'active' : '' }}">
                        <span class="step-badge">2</span>
                        <i class="bi bi-hammer me-2" style="font-size:12px;"></i>B2B Produksi
                    </a>
                    @endif

                    @endif

                    {{-- ── POS SALES ────────────────────── --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga']))
                    <div class="submenu-divider">POS — RESTORAN & OUTLET</div>
                        <a href="{{ route('penjualan_pos.index') }}" class="{{ request()->routeIs('penjualan_pos.*') ? 'active' : '' }}">
                            <i class="bi bi-cart me-2" style="font-size:12px;"></i>Rekap Penjualan POS
                        </a>
                    @endif

                    {{-- ── CENTRAL KITCHEN FLOW ─────────── --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Bagian Produksi', 'Kepala Gudang']))
                    <div class="submenu-divider">CENTRAL KITCHEN</div>

                    {{-- Step 1: CK Order --}}
                    <a href="{{ route('ck-orders.index') }}"
                       class="submenu-step {{ request()->routeIs('ck-orders.*') ? 'active' : '' }}">
                        <span class="step-badge">1</span>
                        <i class="bi bi-shop me-2" style="font-size:12px;"></i>CK Orders
                    </a>

                    {{-- Step 2: CK Production --}}
                    <a href="{{ route('ck-produksi.index') }}"
                       class="submenu-step {{ request()->routeIs('ck-produksi.*') ? 'active' : '' }}">
                        <span class="step-badge">2</span>
                        <i class="bi bi-gear-wide-connected me-2" style="font-size:12px;"></i>CK Produksi
                    </a>
                    @endif

                    {{-- ── PENGIRIMAN & LOGISTIK (B2B & CENTRAL KITCHEN) ──── --}}
                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Bagian Produksi', 'Kepala Gudang']))
                    <div class="submenu-divider">PENGIRIMAN & LOGISTIK</div>
                    <a href="{{ route('pengiriman.index') }}"
                       class="submenu-step {{ request()->routeIs('pengiriman.*') ? 'active' : '' }}">
                        <i class="bi bi-truck me-2" style="font-size:12px;"></i>Pengiriman / Delivery
                    </a>
                    @endif

                    {{-- ── INVENTORY & GUDANG ───────────── --}}
                    @if($canRole(['Kepala Gudang', 'Kepala Outlet Gaharu', 'Kepala Outlet Kejingga']))
                    <div class="submenu-divider">INVENTORY & GUDANG</div>
                        @if($canRole(['Kepala Gudang', 'Kepala Outlet Gaharu']))
                            <a href="{{ route('pembelian.index') }}" class="{{ request()->routeIs('pembelian.*') ? 'active' : '' }}">
                                <i class="bi bi-bag-plus me-2" style="font-size:12px;"></i>Purchase / Pembelian
                            </a>
                            <a href="{{ route('pengeluaran-bahan-baku.index') }}" class="{{ request()->routeIs('pengeluaran-bahan-baku.*') ? 'active' : '' }}">
                                <i class="bi bi-arrow-right-circle me-2" style="font-size:12px;"></i>Pengeluaran Bahan Baku
                            </a>
                        @endif
                        <a href="{{ route('stok-gudang.index') }}" class="{{ request()->routeIs('stok-gudang.*') && !request()->routeIs('stok-gudang.buku-pembantu.*') ? 'active' : '' }}">
                            <i class="bi bi-boxes me-2" style="font-size:12px;"></i>Stok Gudang
                        </a>
                        <a href="{{ route('stock-opname.index') }}" class="{{ request()->routeIs('stock-opname.*') ? 'active' : '' }}">
                            <i class="bi bi-clipboard-check me-2" style="font-size:12px;"></i>Stock Opname
                        </a>
                        <a href="{{ route('stok-gudang.buku-pembantu.index') }}" class="{{ request()->routeIs('stok-gudang.buku-pembantu.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-bookmark-fill me-2" style="font-size:12px;"></i>Buku Pembantu Persediaan
                        </a>
                    @endif

                    {{-- ── HR ──────────────────────────── --}}
                    @if($canRole(['HRD', 'Direktur Keuangan']))
                    <div class="submenu-divider">SDM / HR</div>
                        <a href="{{ route('penggajian.index') }}" class="{{ request()->routeIs('penggajian.*') ? 'active' : '' }}">
                            <i class="bi bi-cash-stack me-2" style="font-size:12px;"></i>Payroll / Penggajian
                        </a>
                    @endif

                </div>
            </div>
            @endif


            {{-- ========================================================================= --}}
            {{-- FINANCE (grup sendiri, hanya Kepala Outlet Gaharu) --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Direktur Keuangan']))
            <div class="menu-group {{ $financeActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-wallet2 me-3 fs-5"></i>
                        <span>FINANCE</span>
                    </div>
                    <i class="bi {{ $financeActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">
                    @if($canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
                        <div class="submenu-divider">JOURNALS</div>
                        <a href="{{ route('jurnal.index') }}" class="{{ request()->routeIs('jurnal.index') ? 'active' : '' }}">
                            <i class="bi bi-journal-check me-2" style="font-size:12px;"></i>General Journal
                        </a>
                        <a href="{{ route('jurnal-pembelian.index') }}" class="{{ request()->routeIs('jurnal-pembelian.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-plus me-2" style="font-size:12px;"></i>Purchase Journal
                        </a>
                        <a href="{{ route('jurnal-penjualanb2b.index') }}" class="{{ request()->routeIs('jurnal-penjualanb2b.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-plus me-2" style="font-size:12px;"></i>B2B Sales Journal
                        </a>
                    @endif

                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Direktur Keuangan']))
                        @if(!$canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
                            <div class="submenu-divider">JOURNALS</div>
                        @endif
                        <a href="{{ route('jurnal-penjualanpos.index') }}" class="{{ request()->routeIs('jurnal-penjualanpos.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-minus me-2" style="font-size:12px;"></i>Rekap POS Sales Journal
                        </a>
                    @endif

                    @if($canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
                        <a href="{{ route('adjustment.index') }}" class="{{ request()->routeIs('adjustment.*') ? 'active' : '' }}">
                            <i class="bi bi-sliders me-2" style="font-size:12px;"></i>Adjustment Journal
                        </a>
                        <a href="{{ route('closing.index') }}" class="{{ request()->routeIs('closing.*') ? 'active' : '' }}">
                            <i class="bi bi-lock me-2" style="font-size:12px;"></i>Closing Journal
                        </a>

                        <div class="submenu-divider">REPORTS</div>
                        <a href="{{ route('laporan.laba-rugi.index') }}" class="{{ request()->routeIs('laporan.laba-rugi.*') ? 'active' : '' }}">
                            <i class="bi bi-graph-up me-2" style="font-size:12px;"></i>Profit &amp; Loss
                        </a>
                        <a href="{{ route('laporan.neraca.index') }}" class="{{ request()->routeIs('laporan.neraca.*') ? 'active' : '' }}">
                            <i class="bi bi-clipboard-data me-2" style="font-size:12px;"></i>Balance Sheet
                        </a>
                        <a href="{{ route('laporan.arus-kas.index') }}" class="{{ request()->routeIs('laporan.arus-kas.*') ? 'active' : '' }}">
                            <i class="bi bi-cash-coin me-2" style="font-size:12px;"></i>Cash Flow
                        </a>
                        <a href="{{ route('laporan.perubahan-ekuitas.index') }}" class="{{ request()->routeIs('perubahan-ekuitas.*') ? 'active' : '' }}">
                            <i class="bi bi-book-half me-2" style="font-size:12px;"></i>Equity Statement 
                        </a>

                        <div class="submenu-divider">OTHERS</div>
                        <a href="{{ route('laporan.neraca-saldo.index') }}" class="{{ request()->routeIs('laporan.neraca-saldo.*') ? 'active' : '' }}">
                            <i class="bi bi-list-check me-2" style="font-size:12px;"></i>Trial Balance
                        </a>
                        <a href="{{ route('laporan.buku-besar.index') }}" class="{{ request()->routeIs('laporan.buku-besar.*') ? 'active' : '' }}">
                            <i class="bi bi-folder-fill me-2" style="font-size:12px;"></i>General Ledger
                        </a>
                        <a href="{{ route('buku-pembantu.index') }}" class="{{ request()->routeIs('buku-pembantu.*') ? 'active' : '' }}">
                            <i class="bi bi-book-half me-2" style="font-size:12px;"></i>Subsidiary Ledger
                        </a>
                    @endif
                </div>
            </div>
            @endif


            {{-- ========================================================================= --}}
            {{-- REPORTS --}}
            {{-- ========================================================================= --}}
            @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Bagian Produksi', 'Kepala Gudang', 'Direktur Keuangan']))
            <div class="menu-group {{ $reportsActive ? 'open' : '' }}">
                <div class="menu-parent d-flex align-items-center justify-content-between toggle-accordion">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bar-chart-line me-3 fs-5"></i>
                        <span>REPORTS</span>
                    </div>
                    <i class="bi {{ $reportsActive ? 'bi-chevron-down' : 'bi-chevron-right' }} chevron-icon"></i>
                </div>

                <div class="submenu-content">
                    @if($canRole(['Kepala Gudang', 'Kepala Outlet Gaharu', 'Direktur Keuangan', 'Kepala Outlet Kejingga']))
                        <div class="submenu-divider">INVENTORY</div>
                        @if($canRole(['Kepala Gudang', 'Kepala Outlet Gaharu', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.pembelian') }}" class="{{ request()->routeIs('laporan.pembelian') ? 'active' : '' }}">
                                <i class="bi bi-cart-check me-2" style="font-size:12px;"></i>Purchase
                            </a>
                        @endif
                        <a href="{{ route('laporan.stok-gudang') }}" class="{{ request()->routeIs('laporan.stok-gudang') ? 'active' : '' }}">
                            <i class="bi bi-boxes me-2" style="font-size:12px;"></i>Stock Position
                        </a>
                        <a href="{{ route('laporan.pengeluaran-bahan-baku') }}" class="{{ request()->routeIs('laporan.pengeluaran-bahan-baku') ? 'active' : '' }}">
                            <i class="bi bi-box-arrow-up me-2" style="font-size:12px;"></i>Raw Material Output
                        </a>
                        <a href="{{ route('laporan.stock-opname') }}" class="{{ request()->routeIs('laporan.stock-opname') ? 'active' : '' }}">
                            <i class="bi bi-clipboard-check me-2" style="font-size:12px;"></i>Stock Opname
                        </a>
                    @endif

                    @if($canRole(['Kepala Outlet Gaharu', 'Kepala Outlet Kejingga', 'Direktur Keuangan']))
                        <div class="submenu-divider">SALES</div>

                        @if($canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.penjualan') }}" class="{{ request()->routeIs('laporan.penjualan') ? 'active' : '' }}">
                                <i class="bi bi-building me-2" style="font-size:12px;"></i>B2B Sales Report
                            </a>
                        @endif

                        <a href="{{ route('penjualan_pos.laporan') }}" class="{{ request()->routeIs('penjualan_pos.laporan') ? 'active' : '' }}">
                            <i class="bi bi-receipt me-2" style="font-size:12px;"></i>Rekap POS Sales Report
                        </a>

                        @if($canRole(['Kepala Outlet Gaharu', 'Direktur Keuangan']))
                            <a href="{{ route('laporan.hpp') }}" class="{{ request()->routeIs('laporan.hpp') ? 'active' : '' }}">
                                <i class="bi bi-cpu me-2" style="font-size:12px;"></i>HPP Report
                            </a>
                        @endif
                    @endif

                    @if($canRole(['Bagian Produksi', 'Direktur Keuangan']))
                        <div class="submenu-divider">PRODUCTION</div>
                        <a href="{{ route('laporan.rekapitulasi') }}" class="{{ request()->routeIs('laporan.rekapitulasi') ? 'active' : '' }}">
                            <i class="bi bi-gear-wide-connected me-2" style="font-size:12px;"></i>Production Report
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
                Logout
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