<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use app\Models\Karyawan;

class KaryawanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $karyawan = [
            ['nama_karyawan' => 'Anindhia Prameswari', 'jabatan' => 'Captain Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Desya Sulistyaningtyas', 'jabatan' => 'Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Brigita Anggun Putri Swary', 'jabatan' => 'Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Anisa Dwi Wulandari', 'jabatan' => 'Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Ivana Navi Wijaya', 'jabatan' => 'Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Destiana', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Bilqis Dinda Farkhana', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Aryati Ambarwati', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            
            ['nama_karyawan' => 'Arif Wibowo', 'jabatan' => 'Captain Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Nadya Rahma Assyta', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Senia Wanadya Riskillah', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Aisyah Nindi Kurnia', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Nailla Arzam', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Rifqi', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Niken', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Niva', 'jabatan' => 'Server', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            
            ['nama_karyawan' => 'Ahmad Bagas Setiawan', 'jabatan' => 'Cleaning Service', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Gudang', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Farid', 'jabatan' => 'Cleaning Service', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Gudang', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Irfan', 'jabatan' => 'Cleaning Service', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Gudang', 'gaji_pokok' => 1000000],

            ['nama_karyawan' => 'Nino Fernandito', 'jabatan' => 'Head Cook', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Muhamad Riski Febrian', 'jabatan' => 'Cook', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Khaq Muhammad Murzzudin', 'jabatan' => 'Cook', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Widodo', 'jabatan' => 'Training Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Nabila Fitri Firnanda', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Nila Zahrotul Mustafidah', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Muhammad Ezra Danendra', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Genta Kumara Putra', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Yoga', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Muhammad Rizwan Hakim', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Evan Rain', 'jabatan' => 'Steward', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Amar', 'jabatan' => 'Steward', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Faisal', 'jabatan' => 'Steward', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],

            ['nama_karyawan' => 'Anita Putri Wulandari', 'jabatan' => 'Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Rizqi Maulida Maftuchah', 'jabatan' => 'Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Meilina Ella Larasati', 'jabatan' => 'Training Kasir', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Ferdio Ardi Pratama', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Aven Ardias', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Charisma Bayu Permatasari', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Laura Nova Divandra', 'jabatan' => 'Barista', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Riana Natasya', 'jabatan' => 'Probation', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Operasional', 'gaji_pokok' => 1000000],
            
            ['nama_karyawan' => 'Wenny Noviana', 'jabatan' => 'Head Cook', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Melodia Immanuel Nathalie', 'jabatan' => 'Cook', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Bagus Ramdhani', 'jabatan' => 'Junior Cook', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Rizal Wahyu Romadhon', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Elvina Putri Cahyaningtyas', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Ardia Galih Pramesthi', 'jabatan' => 'Cook Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Rizky Agustia Rahayuningsih', 'jabatan' => 'Trainee Helper', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Rio Septi Anggo', 'jabatan' => 'Steward', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
            ['nama_karyawan' => 'Ardian Bintang Firmansyah', 'jabatan' => 'Steward', 'jenis_tenaga_kerja' => 'Karyawan Kontrak', 'departemen' => 'Produksi', 'gaji_pokok' => 1000000],
        ];

        DB::table('karyawan')->insert($karyawan);
    }
}
