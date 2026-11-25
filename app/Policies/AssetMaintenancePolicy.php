<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetMaintenance;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssetMaintenancePolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canView($user)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk melihat daftar jadwal pemeliharaan.');
    }

    public function view(User $user, AssetMaintenance $maintenance): Response
    {
        if (! $this->canView($user)) {
            return Response::deny('Anda tidak memiliki izin untuk melihat detail jadwal pemeliharaan ini.');
        }

        if ($user->hasAnyRole(['super-admin', 'super_admin', 'manajemen', 'management'])) {
            return Response::allow();
        }

        if ($user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
            $maintenance->loadMissing('assetCode.classification');

            if ($maintenance->assetCode?->classification?->name === 'Alat IT') {
                return Response::deny('Anda tidak memiliki izin untuk melihat detail jadwal pemeliharaan ini.');
            }

            return Response::allow();
        }

        if ($user->hasAnyRole(['petugas-tik', 'petugas_tik', 'petugas-sarpras', 'petugas_sarpras'])) {
            return (int) $maintenance->assigned_officer_id === (int) $user->id
                ? Response::allow()
                : Response::deny('Anda tidak memiliki izin untuk melihat detail jadwal pemeliharaan ini.');
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->canManage($user)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin untuk membuat jadwal pemeliharaan aset.');
    }

    public function update(User $user, AssetMaintenance $maintenance): Response
    {
        if (! $this->canManage($user)) {
            return Response::deny('Anda tidak memiliki izin untuk memperbarui jadwal pemeliharaan aset.');
        }

        if (! $user->hasAnyRole(['super-admin', 'super_admin'])
            && $user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
            $maintenance->loadMissing('assetCode.classification');

            if ($maintenance->assetCode?->classification?->name === 'Alat IT') {
                return Response::deny('Koordinator Sarpras hanya dapat mengelola jadwal pemeliharaan untuk aset non-TIK (Sarpras).');
            }
        }

        return Response::allow();
    }

    public function delete(User $user, ?AssetMaintenance $maintenance = null): Response
    {
        return Response::deny('Jadwal pemeliharaan tidak dapat dihapus. Nonaktifkan jadwal jika sudah tidak digunakan.');
    }

    private function canView(User $user): bool
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

    private function canManage(User $user): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
        ]);
    }
}
