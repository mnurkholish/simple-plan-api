<?php

namespace App\Policies;

use App\Models\DesignRequest;
use App\Models\User;

class DesignRequestPolicy
{
    public function viewAny(User $user, string $scope = 'mine'): bool
    {
        return $user->can('design-requests-read')
            && ($scope !== 'all' || $user->hasRole('super-admin'));
    }

    public function create(User $user): bool
    {
        return $user->can('design-requests-create');
    }

    public function view(User $user, DesignRequest $designRequest): bool
    {
        return $user->can('design-requests-read')
            && ($user->hasRole('super-admin')
                || (int) $designRequest->requester_id === (int) $user->getKey()
                || (int) $designRequest->assigned_officer_id === (int) $user->getKey());
    }

    public function assign(User $user, DesignRequest $designRequest): bool
    {
        return $user->hasRole('super-admin')
            && $user->can('design-requests-assign');
    }

    public function reject(User $user, DesignRequest $designRequest): bool
    {
        return $user->hasRole('super-admin')
            && $user->can('design-requests-reject');
    }

    public function viewReport(User $user): bool
    {
        return $user->can('design-reports-read');
    }

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
