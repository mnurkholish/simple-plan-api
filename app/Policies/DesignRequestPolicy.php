<?php

namespace App\Policies;

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
}
