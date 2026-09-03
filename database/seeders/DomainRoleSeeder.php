<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DomainRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $kepalaDepartemenRole = Role::firstOrCreate([
            'name' => 'kepala-departemen',
            'guard_name' => 'web',
        ]);
        $kepalaDepartemenRole->syncPermissions([
            'dashboard-access',
            'units-access-owned',
        ]);

        $perawatRole = Role::firstOrCreate([
            'name' => 'perawat',
            'guard_name' => 'web',
        ]);
        $perawatRole->syncPermissions(['dashboard-access']);
    }
}
