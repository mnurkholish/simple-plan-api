<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::updateOrCreate(
            ['email' => 'juniyasyos@gmail.com'],
            [
                'name' => 'Ahmad Ilyas',
                'nip' => '0000.00000',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $superAdminRole = Role::where('name', 'super-admin')->first();

        if ($superAdminRole) {
            $admin->syncRoles([$superAdminRole->name]);
        }

        $admin->syncPermissions(Permission::all());

        $kepala = User::updateOrCreate(
            ['email' => 'kepala@gmail.com'],
            [
                'name' => 'Dr. Kepala Departemen',
                'nip' => '2000.11111',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $kepalaRole = Role::where('name', 'kepala-departemen')->first();

        if ($kepalaRole) {
            $kepala->syncRoles([$kepalaRole->name]);
        }

        $perawat = User::updateOrCreate(
            ['email' => 'perawat@gmail.com'],
            [
                'name' => 'Suster Perawat',
                'nip' => '3000.22222',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $perawatRole = Role::where('name', 'perawat')->first();

        if ($perawatRole) {
            $perawat->syncRoles([$perawatRole->name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
