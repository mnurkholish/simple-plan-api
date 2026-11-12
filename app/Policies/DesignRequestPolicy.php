<?php

namespace App\Policies;

use App\Models\DesignRequest;
use App\Models\User;

class DesignRequestPolicy
{
    /**
     * Tentukan apakah user dapat mengunggah draft desain.
     * Hanya Petugas TIK yang secara spesifik ditugaskan yang diizinkan.
     */
    public function uploadDraft(User $user, DesignRequest $request): bool
    {
        return $user->hasRole('petugas-tik')
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
        return $user->hasRole('super-admin')
            || $this->isRequester($user, $request)
            || $this->isAssignedOfficer($user, $request);
    }

    private function isRequester(User $user, DesignRequest $request): bool
    {
        return (int) $user->getKey() === (int) $request->requester_id;
    }

    private function isAssignedOfficer(User $user, DesignRequest $request): bool
    {
        return $request->assigned_officer_id !== null
            && (int) $user->getKey() === (int) $request->assigned_officer_id;
    }
}
