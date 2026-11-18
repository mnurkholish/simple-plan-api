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

        $designRequestPermissions = [
            'design-requests-create',
            'design-requests-read',
            'design-requests-revise',
            'design-requests-approve',
        ];

        $reportPermissions = [];
        foreach (['helpdesk', 'inventory', 'maintenance'] as $report) {
            $reportPermissions[] = $report.'-reports-read';
            $reportPermissions[] = $report.'-reports-export';
        }

        $rolePermissions = [
            'koordinator-sarpras' => [
                'users-access',
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

            $role->givePermissionTo(array_merge(
                $permissions,
                $designRequestPermissions,
                in_array($roleName, ['management', 'koordinator-sarpras'], true) ? $reportPermissions : [],
                match ($roleName) {
                    'petugas-tik' => ['design-drafts-upload'],
                    'management' => ['design-reports-read', 'design-reports-export'],
                    default => [],
                },
            ));
        }

        Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ])->givePermissionTo(Permission::all());
    }
}
