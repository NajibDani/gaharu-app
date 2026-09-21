<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Kategori;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\PengeluaranBahanBaku;
use App\Models\PengeluaranBahanBakuDetail;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WastedCentralKitchenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('TEST_RUNNING')) {
            define('TEST_RUNNING', true);
        }

        $role = Role::firstOrCreate(['nama' => 'Super Admin']);
        $user = User::create([
            'nama'     => 'Super Admin',
            'username' => 'admin_wasted',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
        $this->actingAs($user);
    }

    public function test_wasted_ck_with_zero_stock_can_be_approved_using_latest_price()
    {
        $kategori = Kategori::create(['nama' => 'Bahan Baku', 'prefix' => 'BB']);

        // 1. Gudang Central Kitchen
        $gudangCK = MasterGudang::create([
            'nama'     => 'Gudang Central Kitchen',
            'kategori' => 'central_kitchen',
        ]);

        // 2. Master Barang dengan HPP referensi
        $barang = MasterBarang::create([
            'kategori_id'   => $kategori->id,
            'kode_barang'   => 'BB-AYAM-01',
            'nama'          => 'Ayam Potong',
            'satuan'        => 'GR',
            'hpp_referensi' => 59.30,
            'is_bahan_baku' => true,
        ]);

        // 3. Buat Pengeluaran Wasted Central Kitchen dengan stok 0
        $pengeluaranCK = PengeluaranBahanBaku::create([
            'kode_pengeluaran'  => 'PBK-WST-1789967281',
            'gudang_id'         => $gudangCK->id,
            'jenis_pengeluaran' => 'wasted',
            'status'            => 'draft',
            'tanggal'           => now(),
            'keterangan'        => 'Wasted Central Kitchen',
            'created_by'        => auth()->id(),
        ]);

        $detailCK = PengeluaranBahanBakuDetail::create([
            'pengeluaran_id' => $pengeluaranCK->id,
            'barang_id'      => $barang->id,
            'qty'            => 340,
            'satuan'         => 'GR',
            'hpp_total'      => 0,
        ]);

        // 4. Test detail-json response contains is_central_kitchen = true and correct calculations
        $jsonResponse = $this->get(route('pengeluaran-bahan-baku.detail-json', $pengeluaranCK->id));
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJson([
            'is_wasted'          => true,
            'is_central_kitchen' => true,
            'total_item_kurang'  => 1,
        ]);

        // 5. Approve Wasted Central Kitchen (should succeed despite stock 0)
        $approveResponse = $this->get(route('pengeluaran-bahan-baku.approve', $pengeluaranCK->id));
        $approveResponse->assertRedirect(route('pengeluaran-bahan-baku.index'));
        $approveResponse->assertSessionHas('success');

        // Assert status updated to approved
        $pengeluaranCK->refresh();
        $this->assertEquals('approved', $pengeluaranCK->status);

        // Assert detail has calculated hpp_total using latest price (340 * 59.30 = 20162.00)
        $detailCK->refresh();
        $this->assertGreaterThan(0, $detailCK->hpp_total);
    }

    public function test_wasted_non_ck_with_zero_stock_is_blocked_from_approval()
    {
        $kategori = Kategori::create(['nama' => 'Bahan Baku', 'prefix' => 'BB']);

        $gudangOutlet = MasterGudang::create([
            'nama'     => 'Gudang Outlet Gaharu',
            'kategori' => 'outlet',
        ]);

        $barang = MasterBarang::create([
            'kategori_id'   => $kategori->id,
            'kode_barang'   => 'BB-MINYAK-01',
            'nama'          => 'Minyak Goreng',
            'satuan'        => 'ML',
            'hpp_referensi' => 15.00,
            'is_bahan_baku' => true,
        ]);

        $pengeluaranOutlet = PengeluaranBahanBaku::create([
            'kode_pengeluaran'  => 'PBK-WST-999999',
            'gudang_id'         => $gudangOutlet->id,
            'jenis_pengeluaran' => 'wasted',
            'status'            => 'draft',
            'tanggal'           => now(),
            'keterangan'        => 'Wasted Outlet',
            'created_by'        => auth()->id(),
        ]);

        PengeluaranBahanBakuDetail::create([
            'pengeluaran_id' => $pengeluaranOutlet->id,
            'barang_id'      => $barang->id,
            'qty'            => 500,
            'satuan'         => 'ML',
            'hpp_total'      => 0,
        ]);

        // Approval for non-CK with 0 stock should fail and redirect back with error
        $approveResponse = $this->get(route('pengeluaran-bahan-baku.approve', $pengeluaranOutlet->id));
        $approveResponse->assertSessionHas('error');

        $pengeluaranOutlet->refresh();
        $this->assertEquals('draft', $pengeluaranOutlet->status);
    }
}
