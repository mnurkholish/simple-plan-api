<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DomainPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $create = fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        $create('tickets-access');
        $create('tickets-create');
        $create('tickets-verify');
        $create('tickets-reject');
        $create('tickets-assign');
        $create('tickets-handle');

        $create('design-requests-create');
        $create('design-requests-read');
        $create('design-requests-manage');
        $create('design-requests-assign');
        $create('design-requests-reject');
        $create('design-drafts-upload');
        $create('design-requests-revise');
        $create('design-requests-approve');
        $create('design-reports-read');
    }
}
