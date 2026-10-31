<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Dapatkan atau buat Role Super Admin
        $role = DB::table('roles')->where('nama', 'Super Admin')->first();
        if (!$role) {
            $roleId = DB::table('roles')->insertGetId([
                'nama' => 'Super Admin',
            ]);
        } else {
            $roleId = $role->id;
        }

        // 2. Buat atau update user 'admin' dengan hak akses Super Admin
        $existingUser = DB::table('users')->where('username', 'admin')->first();
        $passwordHash = Hash::make('admin16072023');

        if ($existingUser) {
            DB::table('users')->where('id', $existingUser->id)->update([
                'nama'       => 'Administrator',
                'name'       => 'Administrator',
                'password'   => $passwordHash,
                'role_id'    => $roleId,
                'gudang_id'  => null,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('users')->insert([
                'nama'       => 'Administrator',
                'name'       => 'Administrator',
                'username'   => 'admin',
                'password'   => $passwordHash,
                'role_id'    => $roleId,
                'gudang_id'  => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('username', 'admin')->delete();
    }
};
