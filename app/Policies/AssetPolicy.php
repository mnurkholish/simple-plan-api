<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'manajemen', 'koor-sarpras', 'petugas-sarpras', 'petugas-tik']);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, int $classificationId = null): bool
    {
        return $user->hasRole(['super-admin', 'petugas-tik', 'petugas-sarpras', 'koor-sarpras']);
    }

    public function update(User $user, Asset $asset): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('petugas-tik') || $user->hasRole('petugas-sarpras') || $user->hasRole('koor-sarpras')) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole('super-admin');
    }

    public function barcode(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
