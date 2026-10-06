<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = storage_path('app/users.csv');
        if (! file_exists($csvFile)) {
            $this->command->error('File users.csv tidak ditemukan di storage/app/users.csv!');

            return;
        }

        $this->command->info('Membaca file CSV users.csv...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $file = fopen($csvFile, 'r');

        // Deteksi delimiter otomatis (koma atau titik koma)
        $firstLine = fgets($file);
        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
        rewind($file);

        fgetcsv($file, 1000, $delimiter); // Lewati header

        $count = 0;

        while (($row = fgetcsv($file, 1000, $delimiter)) !== false) {
            $nama = trim($row[0] ?? '');
            if (empty($nama)) {
                continue;
            }

            $jabatan = trim($row[1] ?? '-');
            $unitName = trim($row[2] ?? '');
            $nip = trim((string) ($row[4] ?? ''));
            $noHp = trim((string) ($row[5] ?? ''));
            $roleRaw = trim($row[6] ?? '');
            $password = trim($row[7] ?? 'rsch123');

            if (empty($unitName)) {
                $unitName = 'Umum';
            }
            $noHp = str_replace(["\n", "\r", ' ', '-', '+'], '', $noHp);
            if (str_starts_with($noHp, '8')) {
                $noHp = '0'.$noHp;
            }

            $roleMap = [
                'User Unit' => 'user',
                'Koordinator Sapras' => 'koordinator-sarpras',
                'Petugas Sapras' => 'petugas-sarpras',
                'Management' => 'management',
                'Petugas TIK' => 'petugas-tik',
                'Superadmin' => 'super-admin',
            ];
            $role = $roleMap[$roleRaw] ?? 'user';

            $unit = Unit::firstOrCreate(
                ['unit_name' => $unitName],
                ['slug' => Str::slug($unitName), 'description' => 'Unit '.$unitName]
            );

            $email = strtolower(Str::slug($nama)).'@rsch.local';
            $emailCounter = 1;
            while (User::where('email', $email)->exists()) {
                $email = strtolower(Str::slug($nama)).$emailCounter.'@rsch.local';
                $emailCounter++;
            }

            $originalNoHp = $noHp;
            $noHpCounter = 1;
            while (User::where('no_hp', $noHp)->exists() || empty($noHp)) {
                $noHp = (empty($originalNoHp) ? '0000' : $originalNoHp).'_'.$noHpCounter;
                $noHpCounter++;
            }

            $user = User::create([
                'name' => ucwords(strtolower($nama)),
                'email' => $email,
                'nip' => $nip,
                'password' => Hash::make($password),
                'unit_id' => $unit->id,
                'jabatan' => ucwords(strtolower($jabatan)),
                'no_hp' => $noHp,
                'status_user' => 'Aktif',
            ]);

            if (method_exists($user, 'assignRole')) {
                try {
                    $user->assignRole($role);
                } catch (\Exception $e) {
                }
            }
            $count++;
        }

        fclose($file);
        $this->command->info("Selesai! {$count} user berhasil diimpor dari file CSV.");
    }
}
