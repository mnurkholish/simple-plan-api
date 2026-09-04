<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use App\Support\DashboardResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        $user->loadMissing(['roles.permissions', 'units']);
        $variant = DashboardResolver::resolveFor($user);

        return [
            'variant' => $variant,
            'summary' => [
                'total_users' => User::count(),
                'total_roles' => Role::count(),
                'total_units' => Unit::count(),
                'total_permissions' => Permission::count(),
            ],
            'current_user' => [
                'roles' => $user->roles->pluck('name')->values(),
                'units' => $user->units->pluck('unit_name')->values(),
                'permissions_count' => count($user->getPermissions()),
            ],
            'role_distribution' => $this->roleDistribution(),
            'recent_users' => $this->recentUsers(),
            'owned_units' => $this->ownedUnitsData($user),
            'today' => [
                'date' => now()->toDateString(),
                'timezone' => config('app.timezone'),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getLiveStats(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'online_users' => $this->onlineUsers(),
            'logins_today' => (int) Cache::get('login_today_'.$today, 0),
            'logouts_today' => (int) Cache::get('logout_today_'.$today, 0),
        ];
    }

    public function recordLogin(): void
    {
        $this->incrementDailyCounter('login_today_'.now()->format('Y-m-d'));
    }

    public function recordLogout(): void
    {
        $this->incrementDailyCounter('logout_today_'.now()->format('Y-m-d'));
    }

    /**
     * @return array<int, array{name: string, users_count: int}>
     */
    private function roleDistribution(): array
    {
        return DB::table('roles')
            ->leftJoin('model_has_roles', function ($join): void {
                $join
                    ->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->select('roles.name')
            ->selectRaw('COUNT(DISTINCT model_has_roles.model_id) as users_count')
            ->groupBy('roles.id', 'roles.name')
            ->orderBy('name')
            ->get()
            ->map(fn (object $role): array => [
                'name' => $role->name,
                'users_count' => (int) $role->users_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentUsers(): array
    {
        return User::query()
            ->with('roles:id,name')
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'email', 'avatar', 'nip', 'status', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'nip' => $user->nip,
                'status' => $user->status,
                'roles' => $user->roles->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values(),
                'created_at' => $user->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ownedUnitsData(User $user): array
    {
        $units = $user->units()->withCount('users')->orderBy('unit_name')->get();
        $unitIds = $units->pluck('id');

        return [
            'total_units' => $units->count(),
            'unit_names' => $units->pluck('unit_name')->values(),
            'total_members' => $unitIds->isEmpty()
                ? 0
                : User::whereHas('units', fn ($query) => $query->whereIn('units.id', $unitIds))->count(),
            'units' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'unit_name' => $unit->unit_name,
                'users_count' => $unit->users_count,
            ])->values(),
            'active_shifts' => 0,
            'present_today' => 0,
        ];
    }

    private function onlineUsers(): int
    {
        return DB::table('personal_access_tokens')
            ->where(function ($query): void {
                $query
                    ->where('last_used_at', '>=', now()->subMinutes(5))
                    ->orWhere('created_at', '>=', now()->subMinutes(5));
            })
            ->distinct('tokenable_id')
            ->count('tokenable_id');
    }

    private function incrementDailyCounter(string $key): void
    {
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }
}
