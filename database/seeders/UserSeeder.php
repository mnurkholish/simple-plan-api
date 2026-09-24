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
            ['email' => 'rsch@gmail.com'],
            [
                'name' => 'Citra Husada',
                'nip' => '0000.00000',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $superAdminRole = Role::where('name', 'super-admin')->first();

        if ($superAdminRole) {
            $admin->syncRoles([$superAdminRole->name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
