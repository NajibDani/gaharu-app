<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class MasterShift extends Model
{
    protected $table = 'master_shifts';

    protected $fillable = [
        'nama',
        'jam_shift',
        'urutan',
    ];

    /**
     * Auto-create table master_shifts if not exists and seed standard default shifts
     */
    public static function ensureTableExists(): void
    {
        if (!Schema::hasTable('master_shifts')) {
            Schema::create('master_shifts', function (Blueprint $table) {
                $table->id();
                $table->string('nama');
                $table->time('jam_shift');
                $table->integer('urutan')->default(0);
                $table->timestamps();
            });

            DB::table('master_shifts')->insert([
                ['nama' => 'Morning 07.00', 'jam_shift' => '07:00:00', 'urutan' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['nama' => 'Morning 08.00', 'jam_shift' => '08:00:00', 'urutan' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['nama' => 'Middle 10.00',  'jam_shift' => '10:00:00', 'urutan' => 3, 'created_at' => now(), 'updated_at' => now()],
                ['nama' => 'Middle 11.00',  'jam_shift' => '11:00:00', 'urutan' => 4, 'created_at' => now(), 'updated_at' => now()],
                ['nama' => 'Middle 12.00',  'jam_shift' => '12:00:00', 'urutan' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['nama' => 'Evening 15.00', 'jam_shift' => '15:00:00', 'urutan' => 6, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
