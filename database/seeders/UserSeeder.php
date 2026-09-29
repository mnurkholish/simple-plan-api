<?php

namespace Database\Seeders;

use App\Models\Unit;
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
            ['email' => 'rsch@gmail.com'],
            [
                'name' => 'Citra Husada',
                'nip' => '0000.00000',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $superAdminRole = Role::where('name', 'super-admin')->first();
        $itUnit = Unit::where('unit_name', 'IT')->first();

        if ($superAdminRole) {
            $admin->syncRoles([$superAdminRole->name]);
        }
        if ($itUnit) {
            $admin->update(['unit_id' => $itUnit->id]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
