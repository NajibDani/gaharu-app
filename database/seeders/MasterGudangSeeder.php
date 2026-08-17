<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterGudang;
use App\Models\GudangDivisi;

class MasterGudangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'nama' => 'Gudang Utama',
                'kategori' => 'Utama',
                'divisi' => [],
            ],
            [
                'nama' => 'Gudang Gaharu',
                'kategori' => 'Operasional',
                'divisi' => ['Kitchen', 'Barista', 'Server'],
            ],
            [
                'nama' => 'Gudang B2B',
                'kategori' => 'Produksi',
                'divisi' => [],
            ],
            [
                'nama' => 'Gudang KeJingga',
                'kategori' => 'Operasional',
                'divisi' => ['Kitchen', 'Barista', 'Server'],
            ],
        ];

        foreach ($data as $item) {
            $gudang = MasterGudang::updateOrCreate(
                ['nama' => $item['nama']],
                [
                    'nama' => $item['nama'],
                    'kategori' => $item['kategori'],
                ]
            );

            if (!empty($item['divisi'])) {
                foreach ($item['divisi'] as $divName) {
                    GudangDivisi::updateOrCreate(
                        [
                            'gudang_id' => $gudang->id,
                            'nama'      => $divName,
                        ],
                        [
                            'keterangan' => 'Divisi ' . $divName . ' untuk ' . $gudang->nama,
                        ]
                    );
                }
            }
        }
    }
}