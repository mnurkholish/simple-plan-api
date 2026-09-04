<?php

namespace App\Support;

use App\Models\User;

class DashboardResolver
{
    public static function resolveFor(User $user): string
    {
        if ($user->canAny(['users-access', 'roles-access'])) {
            return 'super-admin';
        }

        if ($user->can('units-access-owned')) {
            return 'kepala-ruangan';
        }

        if ($user->can('dashboard-access')) {
            return 'perawat';
        }

        return 'default';
    }
}
