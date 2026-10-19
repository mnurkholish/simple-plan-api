<?php

namespace Database\Seeders;

use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use Illuminate\Database\Seeder;

class InventoryMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMasters(AssetClassification::class, [
            1 => 'Tanah',
            2 => 'Gedung dan Bangunan',
            3 => 'Sarana dan Prasarana',
            4 => 'Alat Medis',
            5 => 'Alat IT',
        ]);

        $this->seedMasters(AssetLocation::class, [
            1 => 'IGD',
            2 => 'Ruang IT',
            3 => 'ICU',
            4 => 'Poli Umum',
            5 => 'Radiologi',
            6 => 'Laboratorium',
            7 => 'Farmasi Rawat Jalan',
            8 => 'Farmasi Rawat Inap',
            9 => 'Gudang Farmasi',
            10 => 'Kamar Operasi',
            11 => 'CSSD',
            12 => 'Gizi',
            13 => 'Laundry',
            14 => 'Hemodialisis',
            15 => 'Perinatologi',
            16 => 'Ruang Bersalin',
            17 => 'Rekam Medis',
            18 => 'Keuangan',
            19 => 'Administrasi',
            20 => 'Gudang Umum',
        ]);

        $this->seedMasters(AssetItem::class, [
            1 => 'Meja',
            2 => 'Kursi',
            3 => 'Lemari',
            4 => 'Komputer Desktop',
            5 => 'Laptop',
            6 => 'Monitor',
            7 => 'Printer',
            8 => 'Scanner',
            9 => 'UPS',
            10 => 'Server',
            11 => 'Switch',
            12 => 'Router',
            13 => 'Access Point',
            14 => 'Telepon IP',
            15 => 'Proyektor',
            16 => 'Televisi',
            17 => 'AC 1 PK',
            18 => 'AC 2 PK',
            19 => 'Kulkas',
            20 => 'Dispenser',
            21 => 'Bed Pasien',
            22 => 'Kursi Roda',
            23 => 'Brankar',
            24 => 'Infusion Pump',
            25 => 'Syringe Pump',
            26 => 'Patient Monitor',
            27 => 'EKG',
            28 => 'Defibrillator',
            29 => 'Tensimeter Digital',
            30 => 'Timbangan',
            31 => 'Lampu Periksa',
            32 => 'Lampu Operasi',
            33 => 'Suction Pump',
            34 => 'Nebulizer',
            35 => 'Autoclave',
            36 => 'Genset',
            37 => 'Pompa Air',
            38 => 'CCTV',
            39 => 'Mesin Absensi',
            40 => 'Handy Talky',
        ]);
    }

    /**
     * @param  class-string<AssetClassification|AssetLocation|AssetItem>  $model
     * @param  array<int, string>  $masters
     */
    private function seedMasters(string $model, array $masters): void
    {
        foreach ($masters as $code => $name) {
            $model::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
