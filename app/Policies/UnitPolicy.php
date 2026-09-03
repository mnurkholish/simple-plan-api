<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function update(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('units-update-all')
            || $unit->users()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('units-delete-all')
            || $unit->users()->whereKey($user->id)->exists();
    }
}
