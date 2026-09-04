<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CorePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $create = fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        $create('dashboard-access');
        $create('impersonate');

        $create('users-access');
        $create('users-create');
        $create('users-update');
        $create('users-delete');

        $create('roles-access');
        $create('roles-create');
        $create('roles-update');
        $create('roles-delete');
        $create('permissions-access');

        $create('units-access-all');
        $create('units-create-all');
        $create('units-update-all');
        $create('units-delete-all');
        $create('units-access-owned');
        $create('units-update-owned');
        $create('units-delete-owned');

        $create('backups-access');
        $create('backups-create');
        $create('backups-schedule');
        $create('backups-download');
        $create('backups-delete');
    }
}
