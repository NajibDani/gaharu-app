<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\Preparation;
use App\Models\Role;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\Supplier;
use App\Models\TransaksiStok;
use App\Models\User;
use App\Services\PreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreparationBahanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected MasterGudang $gudang;
    protected Supplier $supplier;
    protected MasterBarang $bahanBaku1;
    protected MasterBarang $bahanBaku2;
    protected MasterBarang $bahanSetengahJadi;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('TEST_RUNNING')) {
            define('TEST_RUNNING', true);
        }

        $role = Role::create(['nama' => 'Super Admin']);
        $this->user = User::create([
            'nama'     => 'Admin Test',
            'username' => 'admintest',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
        $this->actingAs($this->user);

        $this->gudang = MasterGudang::create([
            'nama'     => 'Central Kitchen',
            'kategori' => 'Gudang',
        ]);

        $this->supplier = Supplier::create([
            'nama'   => 'Supplier Utama',
            'no_hp'  => '08123456789',
            'alamat' => 'Jl. Test No. 1',
        ]);

        $kategori = Kategori::create([
            'nama'   => 'Bahan Baku',
            'prefix' => 'BB',
        ]);

        // Bahan Baku 1: Cabai Rawit (base unit: GR)
        $this->bahanBaku1 = MasterBarang::create([
            'kategori_id'            => $kategori->id,
            'kode_barang'            => 'BB-CABAI',
            'nama'                   => 'Cabai Rawit',
            'satuan'                 => 'gr',
            'is_bahan_baku'          => true,
            'is_barang_jadi'         => false,
            'is_bahan_setengah_jadi' => false,
            'hpp_referensi'          => 10,
        ]);

        // Bahan Baku 2: Bawang Merah (base unit: GR)
        $this->bahanBaku2 = MasterBarang::create([
            'kategori_id'            => $kategori->id,
            'kode_barang'            => 'BB-BAWANG',
            'nama'                   => 'Bawang Merah',
            'satuan'                 => 'gr',
            'is_bahan_baku'          => true,
            'is_barang_jadi'         => false,
            'is_bahan_setengah_jadi' => false,
            'hpp_referensi'          => 20,
        ]);

        // BSJ: Sambal Matah (base unit: GR)
        $this->bahanSetengahJadi = MasterBarang::create([
            'kategori_id'            => $kategori->id,
            'kode_barang'            => 'BSJ-SAMBAL',
            'nama'                   => 'Sambal Matah',
            'satuan'                 => 'gr',
            'is_bahan_baku'          => false,
            'is_barang_jadi'         => false,
            'is_bahan_setengah_jadi' => true,
            'hpp_referensi'          => 0,
        ]);
    }

    /**
     * Helper to seed stock in gudang & batch
     */
    protected function seedStock(MasterBarang $barang, float $qty, float $hargaPerQty, string $batchNo = 'BATCH-01'): StokGudangBatch
    {
        $batch = StokGudangBatch::create([
            'gudang_id'     => $this->gudang->id,
            'supplier_id'   => $this->supplier->id,
            'barang_id'     => $barang->id,
            'batch_number'  => $batchNo,
            'qty_masuk'     => $qty,
            'qty_keluar'    => 0,
            'qty_sisa'      => $qty,
            'harga_per_qty' => $hargaPerQty,
            'is_habis'      => false,
        ]);

        $stokGudang = StokGudang::firstOrCreate([
            'gudang_id' => $this->gudang->id,
            'barang_id' => $barang->id,
        ], ['jumlah' => 0]);
        $stokGudang->increment('jumlah', $qty);

        return $batch;
    }

    public function test_user_can_view_preparation_index_page()
    {
        $response = $this->get(route('preparation-bahan.index'));
        $response->assertStatus(200);
        $response->assertSee('Preparation Bahan');
        $response->assertSee('Buat Preparation');
    }

    public function test_user_can_create_preparation_draft_with_status_menunggu()
    {
        // Seed stock
        $this->seedStock($this->bahanBaku1, 1000, 10);
        $this->seedStock($this->bahanBaku2, 1000, 20);

        $payload = [
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'keterangan' => 'Proses sambal matah pagi',
            'inputs'    => [
                [
                    'barang_id' => $this->bahanBaku1->id,
                    'qty'       => 500,
                    'satuan'    => 'gr',
                ],
                [
                    'barang_id' => $this->bahanBaku2->id,
                    'qty'       => 800,
                    'satuan'    => 'gr',
                ],
            ],
            'outputs'   => [
                [
                    'barang_id' => $this->bahanSetengahJadi->id,
                    'qty'       => 1200,
                    'satuan'    => 'gr',
                ],
            ],
        ];

        $response = $this->post(route('preparation-bahan.store'), $payload);
        $response->assertRedirect(route('preparation-bahan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('preparations', [
            'status'    => 'menunggu',
            'gudang_id' => $this->gudang->id,
        ]);

        $prep = Preparation::first();
        $this->assertEquals('2026-10-16', $prep->tanggal->format('Y-m-d'));
        $this->assertStringStartsWith('PRP-', $prep->kode);
        $this->assertCount(2, $prep->inputs);
        $this->assertCount(1, $prep->outputs);

        // Verify stock has NOT changed during draft (still 1000)
        $this->assertEquals(1000, StokGudang::where('barang_id', $this->bahanBaku1->id)->value('jumlah'));
    }

    public function test_user_can_create_draft_when_insufficient_stock_but_cannot_approve_until_stock_sufficient()
    {
        // Only 200 available
        $this->seedStock($this->bahanBaku1, 200, 10);

        $payload = [
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                [
                    'barang_id' => $this->bahanBaku1->id,
                    'qty'       => 500, // needs 500, available only 200
                    'satuan'    => 'gr',
                ],
            ],
            'outputs'   => [
                [
                    'barang_id' => $this->bahanSetengahJadi->id,
                    'qty'       => 500,
                    'satuan'    => 'gr',
                ],
            ],
        ];

        // 1. Saving draft must succeed even with insufficient stock
        $response = $this->post(route('preparation-bahan.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('preparation-bahan.index'));
        $response->assertSessionHas('warning'); // Warning given informing user approval is pending stock

        $this->assertEquals(1, Preparation::count());
        $prep = Preparation::first();
        $this->assertEquals('menunggu', $prep->status);
        $this->assertFalse($prep->hasSufficientStock());

        // 2. Test AJAX stock check returns insufficient details
        $checkRes = $this->get(route('preparation-bahan.stock-check', $prep->id));
        $checkRes->assertOk();
        $checkData = $checkRes->json();
        $this->assertFalse($checkData['stock_check']['is_sufficient']);
        $this->assertEquals(1, $checkData['stock_check']['insufficient_count']);
        $this->assertEquals(300, (float)$checkData['stock_check']['insufficient_items'][0]['kekurangan']);

        // 3. Approval must be blocked when stock is insufficient
        $approveRes = $this->post(route('preparation-bahan.approve', $prep->id));
        $approveRes->assertSessionHas('error');
        $prep->refresh();
        $this->assertEquals('menunggu', $prep->status); // Still waiting

        // 4. Replenish stock by adding 400 more (200 + 400 = 600 >= 500)
        $this->seedStock($this->bahanBaku1, 400, 12);

        // Check real-time stock sufficiency
        $this->assertTrue($prep->hasSufficientStock());
        $checkRes2 = $this->get(route('preparation-bahan.stock-check', $prep->id));
        $this->assertTrue($checkRes2->json()['stock_check']['is_sufficient']);

        // 5. Approval now succeeds
        $approveRes2 = $this->post(route('preparation-bahan.approve', $prep->id));
        $approveRes2->assertSessionHas('success');
        $prep->refresh();
        $this->assertEquals('disetujui', $prep->status);
        $this->assertEquals(100, (float)StokGudang::where('barang_id', $this->bahanBaku1->id)->value('jumlah'));
    }

    public function test_user_can_edit_menunggu_draft()
    {
        $this->seedStock($this->bahanBaku1, 1000, 10);
        $this->seedStock($this->bahanBaku2, 1000, 20);

        /** @var PreparationService $service */
        $service = app(PreparationService::class);
        $prep = $service->createDraft([
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 300, 'satuan' => 'gr'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 300, 'satuan' => 'gr'],
            ],
        ], $this->user->id);

        $updatePayload = [
            'tanggal'   => '2026-10-17',
            'gudang_id' => $this->gudang->id,
            'keterangan' => 'Revisi qty',
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 450, 'satuan' => 'gr'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 400, 'satuan' => 'gr'],
            ],
        ];

        $response = $this->put(route('preparation-bahan.update', $prep->id), $updatePayload);
        $response->assertRedirect(route('preparation-bahan.index'));

        $prep->refresh();
        $this->assertEquals('2026-10-17', $prep->tanggal->format('Y-m-d'));
        $this->assertEquals('Revisi qty', $prep->keterangan);
        $this->assertEquals(450, $prep->inputs->first()->qty);
    }

    public function test_approval_consumes_fifo_and_creates_bsj_batch_and_hpp()
    {
        // Setup 2 FIFO layers:
        // Layer 1: 500 gr @ Rp 10 = Rp 5,000
        // Layer 2: 500 gr @ Rp 20 = Rp 10,000
        $b1 = $this->seedStock($this->bahanBaku1, 500, 10, 'CABAI-01');
        $b2 = $this->seedStock($this->bahanBaku1, 500, 20, 'CABAI-02');

        /** @var PreparationService $service */
        $service = app(PreparationService::class);
        $prep = $service->createDraft([
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 800, 'satuan' => 'gr'], // Consumes 500 from B1 + 300 from B2
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 200, 'satuan' => 'gr'],
            ],
        ], $this->user->id);

        // Expected Cost: (500 * 10) + (300 * 20) = 5,000 + 6,000 = Rp 11,000
        // Expected BSJ HPP: 11,000 / 200 = Rp 55 / gr
        $response = $this->post(route('preparation-bahan.approve', $prep->id));
        $response->assertRedirect(route('preparation-bahan.index'));
        $response->assertSessionHas('success');

        $prep->refresh();
        $this->assertEquals('disetujui', $prep->status);
        $this->assertEquals(11000, (float)$prep->total_biaya);
        $this->assertEquals($this->user->id, $prep->approved_by);
        $this->assertNotNull($prep->approved_at);

        // Check Batch 1 is depleted
        $b1->refresh();
        $this->assertEquals(0, $b1->qty_sisa);
        $this->assertTrue((bool)$b1->is_habis);

        // Check Batch 2 has 200 remaining
        $b2->refresh();
        $this->assertEquals(200, $b2->qty_sisa);
        $this->assertFalse((bool)$b2->is_habis);

        // Check Summary Stock in StokGudang
        $stokRaw = StokGudang::where('barang_id', $this->bahanBaku1->id)->value('jumlah');
        $this->assertEquals(200, (float)$stokRaw); // 1000 - 800 = 200

        // Check BSJ Batch created
        $bsjBatch = StokGudangBatch::where('barang_id', $this->bahanSetengahJadi->id)->first();
        $this->assertNotNull($bsjBatch);
        $this->assertEquals(200, (float)$bsjBatch->qty_masuk);
        $this->assertEquals(200, (float)$bsjBatch->qty_sisa);
        $this->assertEquals(55, (float)$bsjBatch->harga_per_qty);

        // Check BSJ StokGudang
        $stokBsj = StokGudang::where('barang_id', $this->bahanSetengahJadi->id)->value('jumlah');
        $this->assertEquals(200, (float)$stokBsj);

        // Check MasterBarang HPP reference updated
        $this->bahanSetengahJadi->refresh();
        $this->assertEquals(55, (float)$this->bahanSetengahJadi->hpp_referensi);

        // Check Buku Pembantu Persediaan (transaksi_stok)
        $txKeluar = TransaksiStok::where('source_type', 'preparation_bahan')
            ->where('source_id', $prep->id)
            ->where('tipe', 'keluar')
            ->first();
        $this->assertNotNull($txKeluar);
        $this->assertEquals(800, (float)$txKeluar->qty);
        $this->assertEquals(11000, (float)$txKeluar->total_harga);

        $txMasuk = TransaksiStok::where('source_type', 'preparation_bahan')
            ->where('source_id', $prep->id)
            ->where('tipe', 'masuk')
            ->first();
        $this->assertNotNull($txMasuk);
        $this->assertEquals(200, (float)$txMasuk->qty);
        $this->assertEquals(11000, (float)$txMasuk->total_harga);
    }

    public function test_approved_preparation_cannot_be_edited_or_deleted()
    {
        $this->seedStock($this->bahanBaku1, 500, 10);

        /** @var PreparationService $service */
        $service = app(PreparationService::class);
        $prep = $service->createDraft([
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 100, 'satuan' => 'gr'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 100, 'satuan' => 'gr'],
            ],
        ], $this->user->id);

        $service->approve($prep, $this->user->id);

        // Try update
        $updateResp = $this->put(route('preparation-bahan.update', $prep->id), [
            'tanggal'   => '2026-10-18',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 50, 'satuan' => 'gr'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 50, 'satuan' => 'gr'],
            ],
        ]);
        $updateResp->assertSessionHasErrors();

        // Try delete
        $delResp = $this->delete(route('preparation-bahan.destroy', $prep->id));
        $delResp->assertSessionHas('error');
        $this->assertDatabaseHas('preparations', ['id' => $prep->id]);
    }

    public function test_double_approval_prevention()
    {
        $this->seedStock($this->bahanBaku1, 500, 10);

        /** @var PreparationService $service */
        $service = app(PreparationService::class);
        $prep = $service->createDraft([
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 100, 'satuan' => 'gr'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 100, 'satuan' => 'gr'],
            ],
        ], $this->user->id);

        // First approval
        $service->approve($prep, $this->user->id);

        // Second approval via route
        $response = $this->post(route('preparation-bahan.approve', $prep->id));
        $response->assertSessionHas('error');
    }

    public function test_user_can_reject_preparation_with_reason()
    {
        $this->seedStock($this->bahanBaku1, 500, 10);

        /** @var PreparationService $service */
        $service = app(PreparationService::class);
        $prep = $service->createDraft([
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 100, 'satuan' => 'gr'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 100, 'satuan' => 'gr'],
            ],
        ], $this->user->id);

        $response = $this->post(route('preparation-bahan.reject', $prep->id), [
            'alasan_tolak' => 'Stok fisik belum dicek',
        ]);
        $response->assertRedirect(route('preparation-bahan.index'));
        $response->assertSessionHas('success');

        $prep->refresh();
        $this->assertEquals('ditolak', $prep->status);
        $this->assertEquals('Stok fisik belum dicek', $prep->alasan_tolak);

        // Stock must remain unchanged
        $this->assertEquals(500, StokGudang::where('barang_id', $this->bahanBaku1->id)->value('jumlah'));

        // Can delete when rejected
        $delResp = $this->delete(route('preparation-bahan.destroy', $prep->id));
        $delResp->assertSessionHas('success');
        $this->assertDatabaseMissing('preparations', ['id' => $prep->id]);
    }

    public function test_unit_conversion_during_preparation()
    {
        // 2,000 gr in stock @ Rp 10/gr
        $this->seedStock($this->bahanBaku1, 2000, 10);

        /** @var PreparationService $service */
        $service = app(PreparationService::class);

        // User enters 0.5 kg (which is 500 gr)
        $prep = $service->createDraft([
            'tanggal'   => '2026-10-16',
            'gudang_id' => $this->gudang->id,
            'inputs'    => [
                ['barang_id' => $this->bahanBaku1->id, 'qty' => 0.5, 'satuan' => 'kg'],
            ],
            'outputs'   => [
                ['barang_id' => $this->bahanSetengahJadi->id, 'qty' => 500, 'satuan' => 'gr'],
            ],
        ], $this->user->id);

        $input = $prep->inputs->first();
        $this->assertEquals(0.5, (float)$input->qty);
        $this->assertEquals(1000, (float)$input->konversi);
        $this->assertEquals(500, (float)$input->qty_base);

        // Approve
        $service->approve($prep, $this->user->id);

        // Stock in base unit deducted by 500 gr: 2000 - 500 = 1500
        $this->assertEquals(1500, (float)StokGudang::where('barang_id', $this->bahanBaku1->id)->value('jumlah'));
    }
}
