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
}
