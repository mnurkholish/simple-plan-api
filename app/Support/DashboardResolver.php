<?php

namespace App\Support;

use App\Models\User;

class DashboardResolver
{
    public static function resolveFor(User $user): string
    {
        return match (true) {
            $user->hasAnyRole(['super-admin', 'super_admin']) => 'super-admin',
            $user->hasAnyRole(['management', 'manajemen']) => 'management',
            $user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras']) => 'koordinator-sarpras',
            $user->hasAnyRole(['petugas-tik', 'petugas_tik']) => 'petugas-tik',
            $user->hasAnyRole(['petugas-sarpras', 'petugas_sarpras']) => 'petugas-sarpras',
            default => 'user',
        };
    }
}
