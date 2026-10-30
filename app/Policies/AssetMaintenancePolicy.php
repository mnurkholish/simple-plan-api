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
            'koordinator-sarpras',
            'petugas-tik',
            'petugas-sarpras',
            'manajemen',
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
            'koordinator-sarpras',
        ]);
    }

    public function update(User $user, AssetMaintenance $maintenance): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'koordinator-sarpras',
        ]);
    }

    public function delete(User $user, ?AssetMaintenance $maintenance = null): bool
    {
        return false;
    }
}
