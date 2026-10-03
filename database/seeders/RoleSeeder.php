<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            'koordinator-sarpras' => [
                'tickets-access',
                'tickets-create',
                'tickets-verify',
                'tickets-reject',
                'tickets-assign',
                'tickets-handle',
            ],
            'petugas-tik' => [
                'tickets-access',
                'tickets-create',
                'tickets-handle',
            ],
            'petugas-sarpras' => [
                'tickets-access',
                'tickets-create',
                'tickets-handle',
            ],
            'user' => [
                'tickets-access',
                'tickets-create',
            ],
            'management' => [
                'tickets-access',
                'tickets-create',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($permissions);
        }

        Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ])->syncPermissions(Permission::all());
    }
}
