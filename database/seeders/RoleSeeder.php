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

        // Delete old roles if possible
        $oldRoles = ['super-admin', 'kepala-departemen', 'perawat'];
        Role::whereIn('name', $oldRoles)->delete();

        // Create new roles
        $roles = [
            'super admin',
            'koordinator-sarpras',
            'petugas-tik',
            'petugas-sarpras',
            'user',
            'management',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        // If super admin needs all permissions, sync them (optional, but good practice if migrating from old super-admin)
        $superAdmin = Role::where('name', 'super admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }
    }
}
