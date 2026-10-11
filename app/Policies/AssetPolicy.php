<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
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

    public function view(User $user, Asset $asset): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'koordinator-sarpras',
            'petugas-tik',
            'petugas-sarpras',
        ]);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'koordinator-sarpras',
            'petugas-tik',
            'petugas-sarpras',
        ]);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }

    public function printLabel(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
