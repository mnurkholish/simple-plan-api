<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DesignRequest;
use App\Models\User;

class DesignRequestPolicy
{
    /**
     * Tentukan apakah user dapat melihat daftar request desain.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Tentukan apakah user dapat melihat detail request desain.
     */
    public function view(User $user, DesignRequest $request): bool
    {
        return $user->hasAnyRole(['super-admin', 'super_admin'])
            || $this->isRequester($user, $request)
            || $this->isAssignedOfficer($user, $request);
    }

    /**
     * Tentukan apakah user dapat membuat request desain baru.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Tentukan apakah user dapat menugaskan petugas pada request desain.
     */
    public function assign(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'super_admin']);
    }

    /**
     * Tentukan apakah user dapat mengunggah draft desain.
     * Hanya Petugas TIK yang secara spesifik ditugaskan yang diizinkan.
     */
    public function uploadDraft(User $user, DesignRequest $request): bool
    {
        return $user->hasAnyRole(['petugas-tik', 'petugas_tik'])
            && $this->isAssignedOfficer($user, $request);
    }

    /**
     * Tentukan apakah user dapat mengajukan revisi pada request desain.
     * Hanya pembuat request yang diizinkan.
     */
    public function revise(User $user, DesignRequest $request): bool
    {
        return $this->isRequester($user, $request);
    }

    /**
     * Tentukan apakah user dapat menyetujui request desain.
     * Hanya pembuat request yang diizinkan.
     */
    public function approve(User $user, DesignRequest $request): bool
    {
        return $this->isRequester($user, $request);
    }

    /**
     * Tentukan apakah user dapat mengunduh file final request desain.
     */
    public function downloadFinal(User $user, DesignRequest $request): bool
    {
        return $user->hasAnyRole(['super-admin', 'super_admin'])
            || $this->isRequester($user, $request)
            || $this->isAssignedOfficer($user, $request);
    }

    private function isRequester(User $user, DesignRequest $request): bool
    {
        $requesterId = $request->requester_id ?? $request->user_id;

        return $requesterId !== null && (int) $user->getKey() === (int) $requesterId;
    }

    private function isAssignedOfficer(User $user, DesignRequest $request): bool
    {
        $officerId = $request->assigned_officer_id ?? $request->petugas_id;

        return $officerId !== null && (int) $user->getKey() === (int) $officerId;
    }
}
