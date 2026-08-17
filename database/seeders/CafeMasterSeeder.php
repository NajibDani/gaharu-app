<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CafeMasterSeeder extends Seeder
{
    public function run(): void
    {
        // Matikan foreign key constraints sementara
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate tabel-tabel master yang lama agar seed bersih
        DB::table('resep_bahanbaku')->truncate();
        DB::table('resep_btkl_bop')->truncate();
        DB::table('master_barang')->truncate();
        DB::table('kategori')->truncate();
        DB::table('customers')->truncate();
        DB::table('suppliers')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. SEED KATEGORI (Tanpa Timestamps)
        $kategori = [
            ['id' => 1, 'nama' => 'Kopi & Minuman'],
            ['id' => 2, 'nama' => 'Makanan & Pastry'],
            ['id' => 3, 'nama' => 'Bahan Baku'],
            ['id' => 4, 'nama' => 'Packaging'],
        ];
        DB::table('kategori')->insert($kategori);

        // 2. SEED CUSTOMERS (Outlet Internal & B2B Clients)
        $customers = [
            ['id' => 1, 'nama' => 'Outlet Gaharu', 'jenis' => 'Outlet Internal', 'no_hp' => '081234567890', 'alamat' => 'Gudang Gaharu, Jakarta'],
            ['id' => 2, 'nama' => 'Outlet KeJingga', 'jenis' => 'Outlet Internal', 'no_hp' => '081298765432', 'alamat' => 'Gudang KeJingga, Jakarta'],
            ['id' => 3, 'nama' => 'Gaharu Cafe Partner', 'jenis' => 'B2B', 'no_hp' => '081234567891', 'alamat' => 'Jl. Sudirman No. 12, Jakarta'],
            ['id' => 4, 'nama' => 'Kejingga Resto Partner', 'jenis' => 'B2B', 'no_hp' => '081298765433', 'alamat' => 'Jl. Thamrin No. 45, Jakarta'],
            ['id' => 5, 'nama' => 'Hotel Grand Horizon', 'jenis' => 'Hotel', 'no_hp' => '0215551234', 'alamat' => 'Jl. Gatot Subroto No. 99, Jakarta'],
        ];
        DB::table('customers')->insert($customers);

        // 3. SEED SUPPLIERS (Tanpa Timestamps, pakai kolom 'no_hp')
        $suppliers = [
            ['id' => 1, 'nama' => 'Gayo Coffee Bean Supplier', 'no_hp' => '085211223344', 'alamat' => 'Takengon, Aceh Tengah'],
            ['id' => 2, 'nama' => 'IndoMilk Distributor', 'no_hp' => '0218887766', 'alamat' => 'Kawasan Industri Pulogadung, Jakarta'],
            ['id' => 3, 'nama' => 'Torani Syrup Official', 'no_hp' => '0811998877', 'alamat' => 'Kembangan, Jakarta Barat'],
            ['id' => 4, 'nama' => 'EcoPack Packaging', 'no_hp' => '0216664422', 'alamat' => 'Kawasan Industri Tangerang'],
        ];
        DB::table('suppliers')->insert($suppliers);

        // 4. SEED MASTER BARANG (Bahan Baku - Ada Timestamps)
        $bahanBaku = [
            [
                'id' => 1,
                'kategori_id' => 3,
                'resep_id' => null,
                'kode_barang' => 'BB-001',
                'nama' => 'Coffee Beans Espresso Blend',
                'satuan' => 'gram',
                'is_bahan_baku' => true,
                'is_barang_jadi' => false,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 150.00, // Rp 150 per gram (Rp 150.000 / kg)
                'minimum_stock' => 5000, // 5 kg
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'kategori_id' => 3,
                'resep_id' => null,
                'kode_barang' => 'BB-002',
                'nama' => 'Fresh Milk Pasteurized',
                'satuan' => 'ml',
                'is_bahan_baku' => true,
                'is_barang_jadi' => false,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 20.00, // Rp 20 per ml (Rp 20.000 / liter)
                'minimum_stock' => 10000, // 10 liter
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'kategori_id' => 3,
                'resep_id' => null,
                'kode_barang' => 'BB-003',
                'nama' => 'Sirup Caramel Torani',
                'satuan' => 'ml',
                'is_bahan_baku' => true,
                'is_barang_jadi' => false,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 100.00, // Rp 100 per ml (Rp 75.000 / botol 750ml)
                'minimum_stock' => 2000, // 2 liter
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'kategori_id' => 4,
                'resep_id' => null,
                'kode_barang' => 'BB-004',
                'nama' => 'Paper Cup 8oz + Lid',
                'satuan' => 'pcs',
                'is_bahan_baku' => true,
                'is_barang_jadi' => false,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 800.00,
                'minimum_stock' => 500,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'kategori_id' => 4,
                'resep_id' => null,
                'kode_barang' => 'BB-005',
                'nama' => 'Plastic Cup 16oz + Lid',
                'satuan' => 'pcs',
                'is_bahan_baku' => true,
                'is_barang_jadi' => false,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 600.00,
                'minimum_stock' => 500,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('master_barang')->insert($bahanBaku);

        // 5. SEED MASTER BARANG (Barang Jadi - Menu Cafe)
        $barangJadi = [
            [
                'id' => 6,
                'kategori_id' => 1,
                'resep_id' => null,
                'kode_barang' => 'BJ-001',
                'nama' => 'Hot Caramel Latte',
                'satuan' => 'cup',
                'is_bahan_baku' => false,
                'is_barang_jadi' => true,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 24000.00,
                'harga_jual_pos' => 28000.00,
                'hpp_referensi' => 11000.00, // Di-update sesuai total resep nanti
                'minimum_stock' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'kategori_id' => 1,
                'resep_id' => null,
                'kode_barang' => 'BJ-002',
                'nama' => 'Ice Caffe Latte',
                'satuan' => 'cup',
                'is_bahan_baku' => false,
                'is_barang_jadi' => true,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 21000.00,
                'harga_jual_pos' => 25000.00,
                'hpp_referensi' => 9300.00,
                'minimum_stock' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'kategori_id' => 1,
                'resep_id' => null,
                'kode_barang' => 'BJ-003',
                'nama' => 'Ice Caramel Latte',
                'satuan' => 'cup',
                'is_bahan_baku' => false,
                'is_barang_jadi' => true,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 23000.00,
                'harga_jual_pos' => 27000.00,
                'hpp_referensi' => 10800.00,
                'minimum_stock' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('master_barang')->insert($barangJadi);

        // 5b. SEED BAHAN SETENGAH JADI (BSJ) Central Kitchen
        $bahanSetengahJadi = [
            [
                'id' => 9,
                'kategori_id' => 3,
                'resep_id' => null,
                'kode_barang' => 'BSJ-001',
                'nama' => 'Espresso Base Batch 1L',
                'satuan' => 'liter',
                'is_bahan_baku' => false,
                'is_barang_jadi' => false,
                'is_bahan_setengah_jadi' => true,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 45000.00,
                'minimum_stock' => 5,
                'minimum_stock_gaharu' => 5,
                'minimum_stock_kejingga' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 10,
                'kategori_id' => 3,
                'resep_id' => null,
                'kode_barang' => 'BSJ-002',
                'nama' => 'Caramel Syrup Infusion 1L',
                'satuan' => 'liter',
                'is_bahan_baku' => false,
                'is_barang_jadi' => false,
                'is_bahan_setengah_jadi' => true,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 35000.00,
                'minimum_stock' => 3,
                'minimum_stock_gaharu' => 4,
                'minimum_stock_kejingga' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'kategori_id' => 3,
                'resep_id' => null,
                'kode_barang' => 'BSJ-003',
                'nama' => 'Premix Powder Blend 1kg',
                'satuan' => 'kg',
                'is_bahan_baku' => false,
                'is_barang_jadi' => false,
                'is_bahan_setengah_jadi' => true,
                'is_operational' => false,
                'is_direct_consumption' => false,
                'harga_jual_b2b' => 0,
                'harga_jual_pos' => 0,
                'hpp_referensi' => 25000.00,
                'minimum_stock' => 4,
                'minimum_stock_gaharu' => 6,
                'minimum_stock_kejingga' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('master_barang')->insert($bahanSetengahJadi);

        // 6. SEED RESEP BTKL & BOP (Header Resep - Ada Timestamps)
        $reseps = [
            [
                'id' => 1,
                'produk_id' => 6, // Hot Caramel Latte
                'output_qty' => 1.00,
                'satuan_output' => 'cup',
                'btkl_per_batch' => 2000.00, // Upah barista per cup
                'bop_per_batch' => 1000.00,  // Listrik & air per cup
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'produk_id' => 7, // Ice Caffe Latte
                'output_qty' => 1.00,
                'satuan_output' => 'cup',
                'btkl_per_batch' => 2000.00,
                'bop_per_batch' => 1000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'produk_id' => 8, // Ice Caramel Latte
                'output_qty' => 1.00,
                'satuan_output' => 'cup',
                'btkl_per_batch' => 2000.00,
                'bop_per_batch' => 1000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('resep_btkl_bop')->insert($reseps);

        // 7. SEED DETAIL RESEP (Bahan Baku per Resep - Tanpa Timestamps)
        $resepBahanBaku = [
            // Hot Caramel Latte (Resep ID 1)
            [
                'resep_id' => 1,
                'bahan_id' => 1, // Coffee Beans (18g)
                'qty_bahan' => 18.00,
                'satuan' => 'gram',
            ],
            [
                'resep_id' => 1,
                'bahan_id' => 2, // Fresh Milk (150ml)
                'qty_bahan' => 150.00,
                'satuan' => 'ml',
            ],
            [
                'resep_id' => 1,
                'bahan_id' => 3, // Caramel Syrup (15ml)
                'qty_bahan' => 15.00,
                'satuan' => 'ml',
            ],
            [
                'resep_id' => 1,
                'bahan_id' => 4, // Paper Cup (1 pcs)
                'qty_bahan' => 1.00,
                'satuan' => 'pcs',
            ],

            // Ice Caffe Latte (Resep ID 2)
            [
                'resep_id' => 2,
                'bahan_id' => 1, // Coffee Beans (18g)
                'qty_bahan' => 18.00,
                'satuan' => 'gram',
            ],
            [
                'resep_id' => 2,
                'bahan_id' => 2, // Fresh Milk (150ml)
                'qty_bahan' => 150.00,
                'satuan' => 'ml',
            ],
            [
                'resep_id' => 2,
                'bahan_id' => 5, // Plastic Cup (1 pcs)
                'qty_bahan' => 1.00,
                'satuan' => 'pcs',
            ],

            // Ice Caramel Latte (Resep ID 3)
            [
                'resep_id' => 3,
                'bahan_id' => 1, // Coffee Beans (18g)
                'qty_bahan' => 18.00,
                'satuan' => 'gram',
            ],
            [
                'resep_id' => 3,
                'bahan_id' => 2, // Fresh Milk (150ml)
                'qty_bahan' => 150.00,
                'satuan' => 'ml',
            ],
            [
                'resep_id' => 3,
                'bahan_id' => 3, // Caramel Syrup (15ml)
                'qty_bahan' => 15.00,
                'satuan' => 'ml',
            ],
            [
                'resep_id' => 3,
                'bahan_id' => 5, // Plastic Cup (1 pcs)
                'qty_bahan' => 1.00,
                'satuan' => 'pcs',
            ],
        ];
        DB::table('resep_bahanbaku')->insert($resepBahanBaku);

        // 8. HUBUNGKAN KEMBALI RESEP_ID KE MASTER BARANG JADI
        DB::table('master_barang')->where('id', 6)->update(['resep_id' => 1]);
        DB::table('master_barang')->where('id', 7)->update(['resep_id' => 2]);
        DB::table('master_barang')->where('id', 8)->update(['resep_id' => 3]);
    }
}
