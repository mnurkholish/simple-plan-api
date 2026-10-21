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
            2 => 'Bangunan',
            3 => 'Alat Sarpras',
            4 => 'Alat Medis',
            5 => 'Alat Elektronik',
            6 => 'Alat IT',
            7 => 'Kendaraan',
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

        $items = [
            // Sarana dan Prasarana (Classification 3)
            ['classification_id' => 3, 'code' => 1, 'name' => 'Meja'],
            ['classification_id' => 3, 'code' => 2, 'name' => 'Kursi'],
            ['classification_id' => 3, 'code' => 3, 'name' => 'Lemari'],
            ['classification_id' => 3, 'code' => 4, 'name' => 'AC 1 PK'],
            ['classification_id' => 3, 'code' => 5, 'name' => 'AC 2 PK'],
            ['classification_id' => 3, 'code' => 6, 'name' => 'Kulkas'],
            ['classification_id' => 3, 'code' => 7, 'name' => 'Dispenser'],
            ['classification_id' => 3, 'code' => 8, 'name' => 'Genset'],
            ['classification_id' => 3, 'code' => 9, 'name' => 'Pompa Air'],

            // Alat IT (Classification 5)
            ['classification_id' => 5, 'code' => 1, 'name' => 'Komputer Desktop'],
            ['classification_id' => 5, 'code' => 2, 'name' => 'Laptop'],
            ['classification_id' => 5, 'code' => 3, 'name' => 'Monitor'],
            ['classification_id' => 5, 'code' => 4, 'name' => 'Printer'],
            ['classification_id' => 5, 'code' => 5, 'name' => 'Scanner'],
            ['classification_id' => 5, 'code' => 6, 'name' => 'UPS'],
            ['classification_id' => 5, 'code' => 7, 'name' => 'Server'],
            ['classification_id' => 5, 'code' => 8, 'name' => 'Switch'],
            ['classification_id' => 5, 'code' => 9, 'name' => 'Router'],
            ['classification_id' => 5, 'code' => 10, 'name' => 'Access Point'],
            ['classification_id' => 5, 'code' => 11, 'name' => 'Telepon IP'],
            ['classification_id' => 5, 'code' => 12, 'name' => 'Proyektor'],
            ['classification_id' => 5, 'code' => 13, 'name' => 'Televisi'],
            ['classification_id' => 5, 'code' => 14, 'name' => 'CCTV'],
            ['classification_id' => 5, 'code' => 15, 'name' => 'Mesin Absensi'],
            ['classification_id' => 5, 'code' => 16, 'name' => 'Handy Talky'],

            // Alat Medis (Classification 4)
            ['classification_id' => 4, 'code' => 1, 'name' => 'Bed Pasien'],
            ['classification_id' => 4, 'code' => 2, 'name' => 'Kursi Roda'],
            ['classification_id' => 4, 'code' => 3, 'name' => 'Brankar'],
            ['classification_id' => 4, 'code' => 4, 'name' => 'Infusion Pump'],
            ['classification_id' => 4, 'code' => 5, 'name' => 'Syringe Pump'],
            ['classification_id' => 4, 'code' => 6, 'name' => 'Patient Monitor'],
            ['classification_id' => 4, 'code' => 7, 'name' => 'EKG'],
            ['classification_id' => 4, 'code' => 8, 'name' => 'Defibrillator'],
            ['classification_id' => 4, 'code' => 9, 'name' => 'Tensimeter Digital'],
            ['classification_id' => 4, 'code' => 10, 'name' => 'Timbangan'],
            ['classification_id' => 4, 'code' => 11, 'name' => 'Lampu Periksa'],
            ['classification_id' => 4, 'code' => 12, 'name' => 'Lampu Operasi'],
            ['classification_id' => 4, 'code' => 13, 'name' => 'Suction Pump'],
            ['classification_id' => 4, 'code' => 14, 'name' => 'Nebulizer'],
            ['classification_id' => 4, 'code' => 15, 'name' => 'Autoclave'],
        ];

        foreach ($items as $item) {
            AssetItem::query()->updateOrCreate(
                [
                    'asset_classification_id' => $item['classification_id'],
                    'code' => $item['code'],
                ],
                ['name' => $item['name']]
            );
        }
    }

    /**
     * @param  class-string<AssetClassification|AssetLocation>  $model
     * @param  array<int, string>  $masters
     */
    private function seedMasters(string $model, array $masters): void
    {
        foreach ($masters as $code => $name) {
            $model::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
