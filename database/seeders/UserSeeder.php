<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\MasterGudang; // Menggunakan MasterGudang sesuai seeder Anda
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil ID dari Role
        $idSuperAdmin = Role::where('nama', 'Super Admin')->first()?->id;
        $idHrd        = Role::where('nama', 'HRD')->first()?->id;
        $idGaharu     = Role::whereIn('nama', ['Operasional Gaharu', 'Kepala Outlet Gaharu'])->first()?->id;
        $idKejingga   = Role::whereIn('nama', ['Operasional Kejingga', 'Kepala Outlet Kejingga'])->first()?->id;
        $idProduksi   = Role::whereIn('nama', ['Central Kitchen', 'Bagian Produksi'])->first()?->id;
        $idColdKitchen = Role::where('nama', 'Cold Kitchen')->first()?->id;
        $idGudang     = Role::where('nama', 'Kepala Gudang')->first()?->id;
        $idManagement = Role::whereIn('nama', ['Management', 'Direktur Keuangan'])->first()?->id;

        // 2. Ambil ID dari MasterGudang
        $idGudangUtama    = MasterGudang::where('nama', 'Gudang Utama')->first()?->id;
        $idGudangGaharu   = MasterGudang::where('nama', 'Gudang Gaharu')->first()?->id;
        $idGudangKejingga = MasterGudang::where('nama', 'Gudang KeJingga')->first()?->id;

        $passwordPrivacy = Hash::make('admin16072023');

        // 1. Superadmin
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'nama'      => 'Super Admin Utama',
                'password'  => $passwordPrivacy,
                'role_id'   => $idSuperAdmin,
                'gudang_id' => null,
            ]
        );

        // 2. HRD
        User::updateOrCreate(
            ['username' => 'hrd'],
            [
                'nama'      => 'HRD Manager',
                'password'  => $passwordPrivacy,
                'role_id'   => $idHrd,
                'gudang_id' => null,
            ]
        );

        // 3. Management
        User::updateOrCreate(
            ['username' => 'management'],
            [
                'nama'      => 'Management Utama',
                'password'  => $passwordPrivacy,
                'role_id'   => $idManagement,
                'gudang_id' => null,
            ]
        );
        User::updateOrCreate(
            ['username' => 'direktur'],
            [
                'nama'      => 'Direktur Keuangan',
                'password'  => $passwordPrivacy,
                'role_id'   => $idManagement,
                'gudang_id' => null,
            ]
        );

        // 4. Kepala Gudang
        User::updateOrCreate(
            ['username' => 'gudang'],
            [
                'nama'      => 'Kepala Gudang Logistik',
                'password'  => Hash::make('gudang123'),
                'role_id'   => $idGudang,
                'gudang_id' => $idGudangUtama,
            ]
        );

        // 5. Central Kitchen
        User::updateOrCreate(
            ['username' => 'centralkitchen'],
            [
                'nama'      => 'Central Kitchen Production',
                'password'  => Hash::make('ck123'),
                'role_id'   => $idProduksi,
                'gudang_id' => null,
            ]
        );
        User::updateOrCreate(
            ['username' => 'produksi'],
            [
                'nama'      => 'Eko Produksi',
                'password'  => Hash::make('ck123'),
                'role_id'   => $idProduksi,
                'gudang_id' => null,
            ]
        );

        // 5b. Cold Kitchen
        User::updateOrCreate(
            ['username' => 'coldkitchen'],
            [
                'nama'      => 'Cold Kitchen Production',
                'password'  => Hash::make('ck123'),
                'role_id'   => $idColdKitchen,
                'gudang_id' => null,
            ]
        );

        // 6. Operasional Gaharu
        User::updateOrCreate(
            ['username' => 'gaharu'],
            [
                'nama'      => 'Operasional Gaharu',
                'password'  => Hash::make('gaharu123'),
                'role_id'   => $idGaharu,
                'gudang_id' => $idGudangGaharu,
            ]
        );

        // 7. Operasional Kejingga
        User::updateOrCreate(
            ['username' => 'kejingga'],
            [
                'nama'      => 'Operasional Kejingga',
                'password'  => Hash::make('kejingga123'),
                'role_id'   => $idKejingga,
                'gudang_id' => $idGudangKejingga,
            ]
        );
    }

}