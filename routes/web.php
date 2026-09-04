<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\ResepBtklBopController;
use App\Http\Controllers\ResepBahanBakuController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\GudangController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\CoaController;
use App\Http\Controllers\PenggajianController;
use App\Http\Controllers\JurnalController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\StokGudangController;
use App\Http\Controllers\PesananController;
use App\Http\Controllers\PesananDetailController;
use App\Http\Controllers\PenjualanPosController;
use App\Http\Controllers\PenjualanPosDetailController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\PengeluaranBahanBakuController;
use App\Http\Controllers\ProduksiController;
use App\Http\Controllers\HargaBarangPosController;
use App\Http\Controllers\StokGudangBatchController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LaporanPenjualanController;
use App\Http\Controllers\LaporanPenjualanPosController;
use App\Http\Controllers\LaporanProduksiController;
use App\Http\Controllers\LaporanPersediaanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportInventoryController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\CentralKitchenOrderController;
use App\Http\Controllers\CentralKitchenProductionController;
use App\Http\Controllers\PersediaanAwalController;
use App\Http\Controllers\PengaturanGajiController;
use App\Http\Controllers\KeterlambatanController;
use App\Http\Controllers\EventNotifikasiController;

Route::get('/proposal-penawaran', function () {
    return file_get_contents(public_path('proposal/index.html'));
})->name('proposal.penawaran');

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {

    // =========================================================================
    // AKSES UMUM (Bisa diakses oleh semua user yang sudah login)
    // =========================================================================
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard-keuangan', [DashboardController::class, 'keuangan'])->name('dashboard.keuangan')->middleware('role:Management,Direktur Keuangan');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/gudangs/{id}/divisi', [GudangController::class, 'getDivisi'])->name('gudangs.divisi');


    // =========================================================================
    // 1. GROUP GAHARU, KEJINGGA & GUDANG
    // Hak Akses: Master Data Kategori, Barang, Persediaan Awal
    // =========================================================================
    Route::middleware(['role:Kepala Outlet Gaharu,Kepala Outlet Kejingga,Kepala Gudang'])->group(function () {
        Route::resource('kategori', KategoriController::class)->names('kategori');
        Route::resource('barang', BarangController::class)->names('barang');
        Route::patch('barang/{barang}/toggle', [BarangController::class, 'toggle'])->name('barang.toggle');
        Route::get('/barang/generate-kode/{kategori}', [BarangController::class, 'generateKode'])->name('barang.generate-kode');
        Route::get('/barang/check-nama', [BarangController::class, 'checkNama'])->name('barang.check-nama');
        Route::get('/barang/import/template', [BarangController::class, 'importTemplate'])->name('barang.import.template');
        Route::post('/barang/import', [BarangController::class, 'import'])->name('barang.import');

        // Event & Notifikasi Khusus (High Season, Promo, dll)
        Route::patch('event-notifikasi/{id}/toggle', [EventNotifikasiController::class, 'toggleActive'])->name('event-notifikasi.toggle');
        Route::resource('event-notifikasi', EventNotifikasiController::class)->names('event-notifikasi');
    });

    // API Event Notifikasi Aktif untuk Frontend/SweetAlert (Semua User Login)
    Route::get('/api/event-notifikasi-aktif', [EventNotifikasiController::class, 'getActiveEvents'])->name('api.event-notifikasi.aktif');

    // Persediaan Awal (Akses Operasional - Semua Role)
    Route::get('/persediaan-awal/template', [PersediaanAwalController::class, 'importTemplate'])->name('persediaan-awal.template');
    Route::post('/persediaan-awal/import', [PersediaanAwalController::class, 'importExcel'])->name('persediaan-awal.import');
    Route::post('/persediaan-awal/load-barang', [PersediaanAwalController::class, 'loadBarang'])->name('persediaan-awal.load-barang');
    Route::resource('persediaan-awal', PersediaanAwalController::class)->names('persediaan-awal');

    // Pembelian Mandiri Kejingga (Khusus Super Admin)
    Route::middleware(['role:Super Admin,Superadmin,Administrator'])->group(function () {
        Route::post('pembelian-kejingga/{pembelian}/catat-pembayaran', [\App\Http\Controllers\PembelianKejinggaController::class, 'catatPembayaran'])->name('pembelian-kejingga.catat-pembayaran');
        Route::post('pembelian-kejingga/{pembelian}/lunasi', [\App\Http\Controllers\PembelianKejinggaController::class, 'lunasi'])->name('pembelian-kejingga.lunasi');
        Route::post('pembelian-kejingga/{pembelian}/terima', [\App\Http\Controllers\PembelianKejinggaController::class, 'terima'])->name('pembelian-kejingga.terima');
        Route::resource('pembelian-kejingga', \App\Http\Controllers\PembelianKejinggaController::class)->names('pembelian-kejingga');
    });


    // =========================================================================
    // 2. GROUP GAHARU & KEJINGGA
    // Hak Akses: Resep, Harga POS, Transaksi POS
    // =========================================================================
    Route::middleware(['role:Kepala Outlet Gaharu,Kepala Outlet Kejingga'])->group(function () {
        Route::resource('resep', ResepBtklBopController::class);

        Route::get('/resep-bahan/{id}', [ResepBahanBakuController::class, 'show'])->name('resep.bahan.show');
        Route::post('/resep-bahan/{id}', [ResepBahanBakuController::class, 'store'])->name('resep.bahan.store');
        Route::delete('/resep-bahan/{id}', [ResepBahanBakuController::class, 'destroy'])->name('resep.bahan.destroy');
Route::get('/resep/import/template', [ResepBtklBopController::class, 'importTemplate'])->name('resep.import.template');
    Route::post('/resep/import', [ResepBtklBopController::class, 'import'])->name('resep.import');
        // Harga Barang POS
        Route::get('/harga-barang-pos', [HargaBarangPosController::class, 'index'])->name('harga.index');
        Route::get('/harga-barang-pos/{id}', [HargaBarangPosController::class, 'show'])->name('harga.show');
        Route::post('/harga-barang-pos/store', [HargaBarangPosController::class, 'store'])->name('harga.store');
        Route::put('/harga-barang-pos/{id}', [HargaBarangPosController::class, 'update'])->name('harga.update');
        Route::delete('/harga-barang-pos/{id}', [HargaBarangPosController::class, 'destroy'])->name('harga.destroy');

    });

    // Transaksi POS (Akses Operasional - Semua Role)
    Route::post('penjualan-pos/import-moka', [PenjualanPosController::class, 'importMokaExcel'])->name('penjualan_pos.import-moka');
    Route::post('penjualan-pos/{id}/approve', [PenjualanPosController::class, 'approve'])->name('penjualan_pos.approve');
    Route::get('/penjualan_pos/get-harga/{produk_id}', [PenjualanPosController::class, 'getHargaAktif'])->name('penjualan_pos.get-harga');
    Route::get('/penjualan_pos/{id}/cetak-pdf', [PenjualanPosController::class, 'cetakNotaPdf'])->name('penjualan_pos.cetak-pdf');
    Route::resource('penjualan_pos', PenjualanPosController::class);
    Route::resource('penjualanpos-detail', PenjualanPosDetailController::class);

    // Laporan B2B Sales (diakses oleh Gaharu, Kejingga, dan Direktur Keuangan)
    Route::middleware(['role:Kepala Outlet Gaharu,Kepala Outlet Kejingga,Direktur Keuangan'])->group(function () {
        // Laporan Sales & HPP
        Route::get('/laporan-penjualan-pos', [LaporanPenjualanPosController::class, 'index'])->name('penjualan_pos.laporan');
        Route::get('/laporan-produksi/hpp', [LaporanProduksiController::class, 'hpp'])->name('laporan.hpp');
        Route::get('/laporan-produksi/hpp-recipe-detail', [LaporanProduksiController::class, 'hppRecipeDetail'])->name('laporan.hpp-recipe-detail');
        Route::get('/laporan/detail-hpp', [LaporanPenjualanController::class, 'detailHpp'])->name('laporan.detail-hpp');
        Route::get('/laporan/detail-harga-jual', [LaporanPenjualanController::class, 'detailHargaJual']) ->name('laporan.detail-harga-jual'); //baru
    });


    // =========================================================================
    // 3. GROUP GAHARU EKSKLUSIF (MANAJEMEN & B2B)
    // Hak Akses: Pelanggan, B2B
    // =========================================================================
    Route::middleware(['role:Kepala Outlet Gaharu'])->group(function () {
        Route::resource('customer', CustomerController::class)->names('customer');
    });

    // Pesanan B2B (Akses Operasional - Semua Role)
    Route::get('/pesanan/{id}/cetak-pdf', [PesananController::class, 'cetakSoPdf'])->name('pesanan.cetak-pdf');
    Route::resource('pesanan', PesananController::class)->names('pesanan');
    Route::resource('pesanan-detail', PesananDetailController::class);
    Route::post('/pesanan/{id}/pembayaran', [PesananController::class, 'simpanPembayaran'])->name('pesanan.bayar');
    Route::get('/pesanan/{id}/kwitansi', [PesananController::class, 'kwitansi'])->name('pesanan.kwitansi');
    Route::post('/pesanan/{id}/batal', [PesananController::class, 'batal'])->name('pesanan.batal');

    Route::middleware(['role:Kepala Outlet Gaharu,Kepala Gudang'])->group(function () {
        Route::resource('suppliers', SupplierController::class)->names('suppliers');
    });

    // Pengadaan & Pengeluaran Bahan (Akses Operasional - Semua Role)
    Route::get('pengeluaran-bahan-baku/suggestions', [PengeluaranBahanBakuController::class, 'suggestions'])->name('pengeluaran-bahan-baku.suggestions');
    Route::get('pengeluaran-bahan-baku/{id}/detail-json', [PengeluaranBahanBakuController::class, 'detailJson'])->name('pengeluaran-bahan-baku.detail-json');
    Route::get('pengeluaran-bahan-baku/{id}/cetak-pdf', [PengeluaranBahanBakuController::class, 'cetakPdf'])->name('pengeluaran-bahan-baku.cetak-pdf');
    Route::get('pengeluaran-bahan-baku/{id}/approve', [PengeluaranBahanBakuController::class, 'approve'])->name('pengeluaran-bahan-baku.approve');
    Route::resource('pengeluaran-bahan-baku', PengeluaranBahanBakuController::class);

    Route::get('pembelian/suggestions', [PembelianController::class, 'suggestions'])->name('pembelian.suggestions');
    Route::get('pembelian/{id}/cetak-pdf', [PembelianController::class, 'cetakPoPdf'])->name('pembelian.cetak-pdf');
    Route::post('pembelian/{pembelian}/terima', [PembelianController::class, 'terima'])->name('pembelian.terima');
    Route::post('pembelian/{pembelian}/lunasi', [PembelianController::class, 'lunasi'])->name('pembelian.lunasi');
    Route::post('pembelian/{pembelian}/catat-pembayaran', [PembelianController::class, 'catatPembayaran'])->name('pembelian.catat-pembayaran');
    Route::resource('pembelian', PembelianController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);


    // =========================================================================
    // 3C. GROUP FINANCE & REPORTS (GAHARU & DIREKTUR KEUANGAN)
    // =========================================================================
    Route::middleware(['role:Management,Direktur Keuangan'])->group(function () {
        Route::resource('coa', CoaController::class)->names('coa');

        // Laporan Penjualan B2B
        Route::get('/laporan-penjualan', [LaporanPenjualanController::class, 'index'])->name('laporan.penjualan');

        // Jurnal / Finance
        Route::get('/coa/get-name/{id}', [JurnalController::class, 'getCoaName'])->name('coa.getName');
        Route::resource('jurnal', JurnalController::class);

        Route::post('/jurnal/approve-batch', [JurnalController::class, 'approveBatch'])->name('jurnal.approve_batch');

        // Closing & Adjustment
        Route::get('closing', [JurnalController::class, 'closingPage'])->name('closing.index');
        Route::post('/jurnal-closing', [JurnalController::class, 'closePeriod'])->name('closing.store');
        Route::get('adjustment', [JurnalController::class, 'adjustmentIndex'])->name('adjustment.index');
        Route::get('adjustment/create', [JurnalController::class, 'adjustmentPage'])->name('adjustment.create');
        Route::post('adjustment', [JurnalController::class, 'adjustmentStore'])->name('adjustment.store');
        Route::get('/adjustment/{id}', [JurnalController::class, 'adjustmentShow'])->name('adjustment.show');
        Route::get('/adjustment/{id}/edit', [JurnalController::class, 'adjustmentEdit'])->name('adjustment.edit');
        Route::put('/adjustment/{id}', [JurnalController::class, 'adjustmentUpdate'])->name('adjustment.update');
        Route::put('adjustment/{id}/approve', [JurnalController::class, 'adjustmentApprove'])->name('adjustment.approve');
        Route::post('adjustment/approve-batch', [JurnalController::class, 'adjustmentApproveBatch'])->name('adjustment.approve_batch');
        Route::delete('adjustment/{id}', [JurnalController::class, 'adjustmentDestroy'])->name('adjustment.destroy');

        // Jurnal Pembelian
        Route::get('/jurnal-pembelian', [JurnalController::class, 'pembelianIndex'])->name('jurnal-pembelian.index');
        Route::get('/jurnal-pembelian/create/{id}', [JurnalController::class, 'pembelianCreate'])->name('jurnal-pembelian.create');
        Route::post('/jurnal-pembelian/store/{id}', [JurnalController::class, 'prosesJurnalPembelian'])->name('jurnal-pembelian.store');
        Route::post('/jurnal-pembelian/post-auto/{id}', [JurnalController::class, 'pembelianPostAuto'])->name('jurnal-pembelian.post-auto');
        Route::get('/jurnal-pembelian/show/{id}', [JurnalController::class, 'pembelianShow'])->name('jurnal-pembelian.show');

        // Jurnal Penggajian
        Route::get('/jurnal-penggajian', [JurnalController::class, 'penggajianIndex'])->name('jurnal-penggajian.index');
        Route::get('/jurnal-penggajian/create/{id}', [JurnalController::class, 'penggajianCreate'])->name('jurnal-penggajian.create');
        Route::post('/jurnal-penggajian/store/{id}', [JurnalController::class, 'penggajianStore'])->name('jurnal-penggajian.store');
        Route::get('/jurnal-penggajian/show/{id}', [JurnalController::class, 'penggajianShow'])->name('jurnal-penggajian.show');

        // Jurnal Produksi
        Route::get('/jurnal-produksi', [JurnalController::class, 'produksiIndex'])->name('jurnal-produksi.index');
        Route::get('/jurnal-produksi/create/{id}', [JurnalController::class, 'produksiCreate'])->name('jurnal-produksi.create');
        Route::post('/jurnal-produksi/store/{id}', [JurnalController::class, 'produksiStore'])->name('jurnal-produksi.store');
        Route::get('/jurnal-produksi/show/{id}', [JurnalController::class, 'produksiShow'])->name('jurnal-produksi.show');

        // Jurnal Penjualan B2B
        Route::get('/jurnal-penjualanb2b', [JurnalController::class, 'penjualanb2bIndex'])->name('jurnal-penjualanb2b.index');
        Route::get('/jurnal-penjualanb2b/create/{id}', [JurnalController::class, 'penjualanb2bCreate'])->name('jurnal-penjualanb2b.create');
        Route::post('/jurnal-penjualanb2b/store/{id}', [JurnalController::class, 'penjualanB2BStore'])->name('jurnal-penjualanb2b.store');
        Route::post('/jurnal-penjualanb2b/post-auto/{id}', [JurnalController::class, 'penjualanb2bPostAuto'])->name('jurnal-penjualanb2b.post-auto');
        Route::get('/jurnal-penjualanb2b/show/{id}', [JurnalController::class, 'penjualanB2BShow'])->name('jurnal-penjualanb2b.show');
        Route::get('/buku-pembantu', [JurnalController::class, 'bukuPembantuIndex'])->name('buku-pembantu.index');
        Route::get('/buku-pembantu/{jenis}/{id}', [JurnalController::class, 'bukuPembantuShow'])->name('buku-pembantu.show');        // Laporan Keuangan
        Route::prefix('laporan')->name('laporan.')->group(function () {
            Route::get('/', [LaporanController::class, 'labaRugiIndex'])->name('index');
            Route::get('/laba-rugi', [LaporanController::class, 'labaRugiIndex'])->name('laba-rugi.index');
            Route::get('/laba-rugi/show', [LaporanController::class, 'labaRugiShow'])->name('laba-rugi.show');
            Route::get('/neraca', [LaporanController::class, 'neracaIndex'])->name('neraca.index');
            Route::get('/neraca/show', [LaporanController::class, 'neracaShow'])->name('neraca.show');
            Route::get('/arus-kas', [LaporanController::class, 'arusKasIndex'])->name('arus-kas.index');
            Route::get('/arus-kas/show', [LaporanController::class, 'arusKasShow'])->name('arus-kas.show');
            Route::get('/buku-besar', [LaporanController::class, 'bukuBesar'])->name('buku-besar.index');
            Route::get('/neraca-saldo', [LaporanController::class, 'neracaSaldo'])->name('neraca-saldo.index');
            Route::get('/perubahan-ekuitas', [LaporanController::class, 'perubahanEkuitas'])->name('perubahan-ekuitas.index');
        });
    });

    // Jurnal Penjualan POS (Gaharu, Kejingga, Direktur Keuangan)
    Route::middleware(['role:Management,Direktur Keuangan'])->group(function () {
        Route::get('/jurnal-penjualanpos', [JurnalController::class, 'penjualanposIndex'])->name('jurnal-penjualanpos.index');
        Route::get('/jurnal-penjualanpos/create/{id}', [JurnalController::class, 'penjualanposCreate'])->name('jurnal-penjualanpos.create');
        Route::post('/jurnal-penjualanpos/store/{id}', [JurnalController::class, 'penjualanposStore'])->name('jurnal-penjualanpos.store');
        Route::post('/jurnal-penjualanpos/post-auto/{id}', [JurnalController::class, 'penjualanposPostAuto'])->name('jurnal-penjualanpos.post-auto');
        Route::get('/jurnal-penjualanpos/show/{id}', [JurnalController::class, 'penjualanposShow'])->name('jurnal-penjualanpos.show');
    });


    // =========================================================================
    // 4. GROUP PRODUKSI (Akses Operasional - Semua Role)
    // =========================================================================
    Route::prefix('work-order')->name('wo.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('produksi.index');
        })->name('index');
        Route::get('/create/{id}', [WorkOrderController::class, 'create'])->name('create');
        Route::post('/store', [WorkOrderController::class, 'store'])->name('store');
        Route::get('/show/{id}', [WorkOrderController::class, 'show'])->name('show');
        Route::get('/{id}/cetak-pdf', [WorkOrderController::class, 'cetakPdf'])->name('cetak-pdf');
        Route::post('/massal/review', [WorkOrderController::class, 'reviewMassal'])->name('review_massal');
        Route::post('/massal/store', [WorkOrderController::class, 'storeMassal'])->name('store_massal');
        Route::get('/massal/review', function () {
            return redirect()->route('produksi.index', ['tab' => 'wo'])->with('error', 'Sesi tidak valid, silakan ulangi.');
        });
        Route::post('/{id}/kirim-produksi', [WorkOrderController::class, 'kirimKeProduksi'])->name('kirim_produksi');
    });

    Route::get('/produksi', [ProduksiController::class, 'index'])->name('produksi.index');
    Route::post('/produksi/wo', [ProduksiController::class, 'storeWo'])->name('produksi.store-wo');
    Route::post('/produksi/store-and-approve', [ProduksiController::class, 'storeAndApprove'])->name('produksi.store-and-approve');
    Route::get('/produksi/create', [ProduksiController::class, 'create'])->name('produksi.create');
    Route::post('/produksi', [ProduksiController::class, 'store'])->name('produksi.store');
    Route::get('/produksi/get-wo-detail/{id}', [ProduksiController::class, 'getWoDetail'])->name('produksi.getWoDetail');
    Route::get('/produksi/{id}/cetak-pdf', [ProduksiController::class, 'cetakPdf'])->name('produksi.cetak-pdf');
    Route::resource('produksi', ProduksiController::class);
    Route::post('/produksi/{id}/approve', [ProduksiController::class, 'approve'])->name('produksi.approve');

    // Pengiriman / Delivery (Akses Operasional - Semua Role)
    Route::get('/pengiriman', [PengirimanController::class, 'index'])->name('pengiriman.index');
    Route::get('/pengiriman/create', [PengirimanController::class, 'create'])->name('pengiriman.create');
    Route::post('/pengiriman/store', [PengirimanController::class, 'store'])->name('pengiriman.store');
    Route::get('/pengiriman/pesanan-detail/{id}', [PengirimanController::class, 'getPesananDetail'])->name('pengiriman.pesanan-detail');
    Route::resource('pengiriman', PengirimanController::class);
    Route::post('pengiriman/{id}/approve', [PengirimanController::class, 'approve'])->name('pengiriman.approve');

    // Laporan Produksi Dashboard & Rekapitulasi (Akses Laporan - Semua Role)
    Route::get('/laporan-produksi/dashboard', [LaporanProduksiController::class, 'dashboard'])->name('laporan.produksi.dashboard');
    Route::get('/laporan-produksi/rekapitulasi', [LaporanProduksiController::class, 'rekapitulasi'])->name('laporan.rekapitulasi');

    // =========================================================================
    // 4B. GROUP CENTRAL KITCHEN (Akses Operasional - Semua Role)
    // =========================================================================
    // CK Orders
    Route::get('/central-kitchen/orders/suggestions', [CentralKitchenOrderController::class, 'suggestions'])->name('ck-orders.suggestions');
    Route::get('/central-kitchen/orders/{id}/cetak-pdf', [CentralKitchenOrderController::class, 'cetakPdf'])->name('ck-orders.cetak-pdf');
    Route::resource('central-kitchen/orders', CentralKitchenOrderController::class)->names('ck-orders');

    // CK Production
    Route::get('/central-kitchen/produksi', [CentralKitchenProductionController::class, 'index'])->name('ck-produksi.index');
    Route::post('/central-kitchen/produksi/wo', [CentralKitchenProductionController::class, 'storeWo'])->name('ck-produksi.store-wo');
    Route::post('/central-kitchen/produksi/kirim-bahan/{id}', [CentralKitchenProductionController::class, 'kirimBahanBaku'])->name('ck-produksi.kirim-bahan');
    Route::get('/central-kitchen/produksi/create-produksi', [CentralKitchenProductionController::class, 'createProduksi'])->name('ck-produksi.create-produksi');
    Route::post('/central-kitchen/produksi/store-produksi', [CentralKitchenProductionController::class, 'storeProduksi'])->name('ck-produksi.store-produksi');
    Route::post('/central-kitchen/produksi/store-and-approve', [CentralKitchenProductionController::class, 'storeAndApprove'])->name('ck-produksi.store-and-approve');
    Route::post('/central-kitchen/produksi/{id}/approve', [CentralKitchenProductionController::class, 'approveProduksi'])->name('ck-produksi.approve');
    Route::get('/central-kitchen/produksi/stok-internal/create', [CentralKitchenProductionController::class, 'createStokInternal'])->name('ck-produksi.create-stok-internal');
    Route::post('/central-kitchen/produksi/stok-internal/store', [CentralKitchenProductionController::class, 'storeStokInternal'])->name('ck-produksi.store-stok-internal');

    // =========================================================================
    // 5. GROUP KEPALA GUDANG & KEPALA OUTLET GAHARU
    // Hak Akses: Master Gudang, Pembelian, Stok Gudang, Stock Opname
    // =========================================================================
    Route::middleware(['role:Kepala Gudang,Kepala Outlet Gaharu,Kepala Outlet Kejingga'])->group(function () {
        Route::resource('gudangs', GudangController::class)->names('gudangs');
    });

    // Pengelolaan Stok (Akses Operasional - Semua Role)
    Route::get('/stok-gudang', [StokGudangController::class, 'index'])->name('stok-gudang.index');
    Route::get('stok-gudang/{id}/detail', [StokGudangController::class, 'detail'])->name('stok-gudang.detail');
    Route::resource('stok-gudang-batch', StokGudangBatchController::class);
    Route::get('/stok-gudang-batch', [StokGudangBatchController::class, 'index'])->name('stok-gudang-batch.index');

    // Stock Opname
    Route::post('/stock-opname/hitung-fifo', [StockOpnameController::class, 'hitungFIFORealtime'])->name('stock-opname.hitung-fifo');
    Route::post('stock-opname/load-barang', [StockOpnameController::class, 'loadBarang'])->name('stock-opname.load-barang');
    Route::resource('stock-opname', StockOpnameController::class);
    Route::get('stock-opname/{id}/approve', [StockOpnameController::class, 'approve'])->name('stock-opname.approve');
    Route::get('stock-opname/{id}/detail-json', [StockOpnameController::class, 'detailJson'])->name('stock-opname.detail-json');

    // Laporan Persediaan / Inventory (Akses Laporan - Semua Role)
    Route::get('/reports/inventory', [ReportInventoryController::class, 'index'])->name('reports.inventory');
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/pembelian', [LaporanPersediaanController::class, 'pembelian'])->name('pembelian');
    });

    Route::get('/stok-gudang/buku-pembantu', [StokGudangController::class, 'bukuPembantuIndex'])->name('stok-gudang.buku-pembantu.index');
    Route::get('/stok-gudang/buku-pembantu/mutasi', [StokGudangController::class, 'bukuPembantuMutasi'])->name('stok-gudang.buku-pembantu.mutasi');

    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/stok-gudang', [LaporanPersediaanController::class, 'stokGudang'])->name('stok-gudang');
        Route::get('/pengeluaran-bahan-baku', [LaporanPersediaanController::class, 'pengeluaranBahanBaku'])->name('pengeluaran-bahan-baku');
        Route::get('/stock-opname', [LaporanPersediaanController::class, 'stockOpname'])->name('stock-opname');
    });


    // =========================================================================
    // 6. GROUP HRD & PAYROLL
    // Hak Akses: Master Data Karyawan (HRD & Management), User, Role, Penggajian (Khusus HRD)
    // =========================================================================
    Route::middleware(['role:HRD,Management,Direktur Keuangan'])->group(function () {
        Route::resource('karyawan', KaryawanController::class)->names('karyawan');
    });

    Route::middleware(['role:HRD'])->group(function () {
        Route::resource('roles', RoleController::class);
        Route::resource('users', UserController::class);

        // Payroll & Pengaturan Gaji
        Route::get('/penggajian/create', [PenggajianController::class, 'create'])->name('penggajian.create');
        Route::get('/penggajian/periode', [PenggajianController::class, 'periodeDetail'])->name('penggajian.show-periode');
        Route::post('/penggajian/auto-fill', [PenggajianController::class, 'autoFill'])->name('penggajian.auto-fill');
        Route::post('/penggajian/ajukan-approval', [PenggajianController::class, 'ajukanApproval'])->name('penggajian.ajukanApproval');
        Route::post('/penggajian/approve', [PenggajianController::class, 'approve'])->name('penggajian.approve');
        Route::post('/penggajian/kirim-jurnal', [PenggajianController::class, 'kirimJurnalUmum'])->name('penggajian.kirimJurnalUmum');
        Route::get('/penggajian/periode/{periode}', [PenggajianController::class, 'showPeriode'])->name('penggajian.periode');
        Route::get('/penggajian/{id}/pdf', [PenggajianController::class, 'cetakPdf'])->name('penggajian.pdf');
        Route::post('/penggajian/store', [PenggajianController::class, 'store'])->name('penggajian.store');
        Route::post('/penggajian/periode/{periode}/submit', [PenggajianController::class, 'submitToDirector'])->name('penggajian.submit');
        Route::post('/penggajian/periode/{periode}/approve', [PenggajianController::class, 'approveByDirector'])->name('penggajian.approve');
        Route::post('/penggajian/periode/{periode}/journal', [PenggajianController::class, 'sendToJournal'])->name('penggajian.journal');
        Route::post('/penggajian/{id}/bayar', [PenggajianController::class, 'bayarKaryawan'])->name('penggajian.bayar');
        Route::post('/penggajian/periode/{periode}/bayar-semua', [PenggajianController::class, 'bayarSemuaPeriode'])->name('penggajian.bayar-semua');
        Route::delete('/penggajian/{penggajian}', [PenggajianController::class, 'destroy'])->name('penggajian.destroy');
        Route::get('/pengaturan-gaji', [PengaturanGajiController::class, 'index'])->name('pengaturan-gaji.index');
        Route::put('/pengaturan-gaji/{id}', [PengaturanGajiController::class, 'update'])->name('pengaturan-gaji.update');

        // Data Keterlambatan Karyawan
        Route::get('/keterlambatan/hitung-ajax', [KeterlambatanController::class, 'hitungAjax'])->name('keterlambatan.hitung-ajax');
        Route::resource('keterlambatan', KeterlambatanController::class)->names('keterlambatan');

        Route::resource('penggajian', PenggajianController::class);
    });
    });


require __DIR__.'/auth.php';