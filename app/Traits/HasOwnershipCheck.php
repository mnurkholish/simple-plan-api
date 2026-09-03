<?php

namespace App\Traits;

use Closure;

trait HasOwnershipCheck
{
    public function authorizeOwnership(string $allPermission, Closure $ownerCheck): void
    {
        $user = auth()->user();

        if (! $user || (! $user->hasPermissionTo($allPermission) && ! $ownerCheck($user))) {
            abort(403, 'Unauthorized.');
        }
    }
}
