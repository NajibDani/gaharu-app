<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Menggunakan 'nama' sesuai validasi di RoleController Anda
        Role::updateOrCreate(['nama' => 'Super Admin']);
        Role::updateOrCreate(['nama' => 'HRD']);
        Role::updateOrCreate(['nama' => 'Kepala Gudang']);
        Role::updateOrCreate(['nama' => 'Central Kitchen']);
        Role::updateOrCreate(['nama' => 'Cold Kitchen']);
        Role::updateOrCreate(['nama' => 'Operasional Gaharu']);
        Role::updateOrCreate(['nama' => 'Operasional Kejingga']);
        Role::updateOrCreate(['nama' => 'Management']);

        // Legacy / Alias roles
        Role::updateOrCreate(['nama' => 'Kepala Outlet Gaharu']);
        Role::updateOrCreate(['nama' => 'Kepala Outlet Kejingga']);
        Role::updateOrCreate(['nama' => 'Bagian Produksi']);
        Role::updateOrCreate(['nama' => 'Direktur Keuangan']);

    }
}