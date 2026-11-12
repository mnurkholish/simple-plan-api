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
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
            'petugas-tik',
            'petugas_tik',
            'petugas-sarpras',
            'petugas_sarpras',
            'manajemen',
            'management',
            'user',
        ]);
    }

    public function view(User $user, Asset $asset): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $asset->loadMissing('assetCode.classification');
        $classificationName = $asset->assetCode?->classification?->name;

        if ($user->hasAnyRole(['manajemen', 'management'])) {
            if ($this->isTikOrSarpras($classificationName)) {
                return false;
            }
        }

        if ($user->hasAnyRole(['super-admin', 'super_admin'])) {
            if ($this->isSarpras($classificationName)) {
                return false;
            }
        }

        if ($user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
            if ($this->isTik($classificationName)) {
                return false;
            }
        }

        return true;
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

    public function update(User $user, Asset $asset): bool
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

    private function isTik(?string $name): bool
    {
        if (! $name) {
            return false;
        }

        return in_array($name, ['Alat IT', 'TIK', 'IT'], true)
            || str_contains(strtolower($name), 'tik')
            || str_contains(strtolower($name), 'alat it');
    }

    private function isSarpras(?string $name): bool
    {
        if (! $name) {
            return false;
        }

        return in_array($name, ['Alat Sarpras', 'Sarpras'], true)
            || str_contains(strtolower($name), 'sarpras')
            || str_contains(strtolower($name), 'alat sarpras');
    }

    private function isTikOrSarpras(?string $name): bool
    {
        return $this->isTik($name) || $this->isSarpras($name);
    }
}
