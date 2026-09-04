<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Kategori;
use App\Models\MasterBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BahanSetengahJadiSatuanTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define TEST_RUNNING to bypass global scopes filters if needed
        if (!defined('TEST_RUNNING')) {
            define('TEST_RUNNING', true);
        }

        // Create a dummy user and act as super admin or admin to bypass role barriers
        $role = \App\Models\Role::create(['nama' => 'Super Admin']);
        $user = User::create([
            'nama' => 'Test User',
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);
        $this->actingAs($user);
    }

    public function test_cannot_create_bsj_with_invalid_satuan()
    {
        $kategori = Kategori::create(['nama' => 'Bahan Baku', 'prefix' => 'BB']);

        $response = $this->post(route('barang.store'), [
            'kategori_id' => $kategori->id,
            'kode_barang' => 'BB-100',
            'nama' => 'Test BSJ Portions',
            'jenis_utama' => 'BAHAN_SETENGAH_JADI',
            'satuan' => 'PORSI',
            'minimum_stock_ck' => 10,
        ]);

        $response->assertSessionHasErrors('satuan');
    }

    public function test_can_create_bsj_with_valid_satuan_and_it_is_standardized()
    {
        $kategori = Kategori::create(['nama' => 'Bahan Baku', 'prefix' => 'BB']);

        // Test GR/gram
        $response = $this->post(route('barang.store'), [
            'kategori_id' => $kategori->id,
            'kode_barang' => 'BB-101',
            'nama' => 'Test BSJ Gram',
            'jenis_utama' => 'BAHAN_SETENGAH_JADI',
            'satuan' => 'gram',
            'minimum_stock_ck' => 10,
        ]);

        $response->assertRedirect(route('barang.index'));
        $this->assertDatabaseHas('master_barang', [
            'kode_barang' => 'BB-101',
            'satuan' => 'GR',
            'is_bahan_setengah_jadi' => true,
        ]);

        // Test ML/mililiter
        $response2 = $this->post(route('barang.store'), [
            'kategori_id' => $kategori->id,
            'kode_barang' => 'BB-102',
            'nama' => 'Test BSJ Mililiter',
            'jenis_utama' => 'BAHAN_SETENGAH_JADI',
            'satuan' => 'ml',
            'minimum_stock_ck' => 10,
        ]);

        $response2->assertRedirect(route('barang.index'));
        $this->assertDatabaseHas('master_barang', [
            'kode_barang' => 'BB-102',
            'satuan' => 'ML',
            'is_bahan_setengah_jadi' => true,
        ]);
    }
}
