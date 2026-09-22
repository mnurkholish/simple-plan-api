<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            'Kasir', 'Security', 'Kebersihan', 'TPP', 'Pemasaran', 'Kebersihan OK Kamar Operasi',
            'Gizi', 'Informasi dan Komplain', 'Driver', 'Laundry', 'CSSD', 'Akupuntur', 'Umum RT',
            'IPSRS', 'Sanitarian', 'Kepegawaian dan Diklat', 'IT', 'Radiologi', 'Anturium', 'ICU',
            'Lotus', 'Konsultan', 'Dokter IGD', 'Poli Spesialis', 'Parkir', 'Psikologi', 'Direksi',
            'Pelayanan Medik', 'Penunjang Medik', 'Farmasi', 'Farmasi Rawat Jalan', 'Farmasi Rawat Inap',
            'Gudang Farmasi', 'Tulip', 'Bersalin', 'Perinatologi', 'Rekam Medis', 'Tim Komite', 'OK',
            'Perawat IGD', 'Teratai', 'Hemodialisis', 'Keuangan', 'Akutansi', 'Sekretariat', 'Rosalina',
            'Alamanda', 'Poli', 'Laboratorium', 'Ka. Bidang Keperawatan', 'Ka.Seksi Asuhan Keperawatan',
            'SPI', 'Pajak', 'HRD AJK', 'Dokter Internship',
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['unit_name' => $unit],
                ['slug' => Str::slug($unit)]
            );
        }
    }
}
