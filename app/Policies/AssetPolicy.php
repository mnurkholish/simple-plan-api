<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssetPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canViewAny($user)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk melihat daftar inventaris.');
    }

    public function view(User $user, Asset $asset): Response
    {
        if (! $this->canViewAny($user)) {
            return Response::deny('Anda tidak memiliki izin untuk melihat detail aset ini.');
        }

        $asset->loadMissing('assetCode.classification');
        $classificationName = $asset->assetCode?->classification?->name;

        if ($user->hasAnyRole(['manajemen', 'management'])) {
            if ($this->isTikOrSarpras($classificationName)) {
                return Response::deny('Anda tidak memiliki izin untuk melihat detail aset ini.');
            }
        }

        if ($user->hasAnyRole(['super-admin', 'super_admin'])) {
            if ($this->isSarpras($classificationName)) {
                return Response::deny('Anda tidak memiliki izin untuk melihat detail aset ini.');
            }
        }

        if ($user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
            if ($this->isTik($classificationName)) {
                return Response::deny('Anda tidak memiliki izin untuk melihat detail aset ini.');
            }
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $user->hasAnyRole([
            'super-admin',
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
        ])
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk menambahkan data inventaris.');
    }

    public function update(User $user, Asset $asset): Response
    {
        return $this->canManageAsset($user)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk memperbarui data inventaris.');
    }

    public function delete(User $user, Asset $asset): Response
    {
        return $this->canManageAsset($user)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk menghapus data inventaris.');
    }

    public function printLabel(User $user, Asset $asset): Response
    {
        return $this->canManageAsset($user)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk mencetak label aset.');
    }

    private function canViewAny(User $user): bool
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

    private function canManageAsset(User $user): bool
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
