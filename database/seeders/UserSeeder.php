<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::updateOrCreate(
            ['email' => 'citrahusada@gmail.com'],
            [
                'name' => 'Citra Husada',
                'nip' => '0000.00000',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $superAdminRole = Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $admin->syncRoles([$superAdminRole]);
        $admin->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
