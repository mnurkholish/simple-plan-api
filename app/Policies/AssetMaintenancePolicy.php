<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetMaintenance;
use App\Models\User;

class AssetMaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
            'petugas-tik',
            'petugas_tik',
            'petugas-sarpras',
            'petugas_sarpras',
            'manajemen',
            'management',
        ]);
    }

    public function view(User $user, AssetMaintenance $maintenance): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
        ]);
    }

    public function update(User $user, AssetMaintenance $maintenance): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
        ]);
    }

    public function delete(User $user, ?AssetMaintenance $maintenance = null): bool
    {
        return false;
    }
}
