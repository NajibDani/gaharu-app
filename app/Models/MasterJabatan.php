<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class MasterJabatan extends Model
{
    protected $table = 'master_jabatan';

    protected $fillable = [
        'nama',
        'departemen',
        'urutan',
    ];

    /**
     * Auto-create table master_jabatan if not exists and seed standard default jabatan
     */
    public static function ensureTableExists(): void
    {
        if (!Schema::hasTable('master_jabatan')) {
            Schema::create('master_jabatan', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->unique();
                $table->string('departemen')->nullable();
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();
            });

            $defaultJabatan = [
                'BARISTA', 'CAPTAIN', 'KASIR', 'FLOOR', 'PROBATION',
                'PART TIME', 'CLEANING SERVICE', 'HEAD KITCHEN', 'COOK',
                'COOK HELPER', 'DISHWASHER', 'ADMIN GUDANG', 'STAFF GUDANG',
                'SECURITY', 'HRD', 'SUPERVISOR', 'MANAGER', 'CEO',
            ];

            $rows = [];
            foreach ($defaultJabatan as $idx => $jabatan) {
                $rows[] = [
                    'nama'       => $jabatan,
                    'departemen' => null,
                    'urutan'     => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('master_jabatan')->insert($rows);
        }
    }
}
