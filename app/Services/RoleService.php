<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($this->permissionNames($data));

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $role->update(['name' => $data['name']]);

            $role->syncPermissions($this->permissionNames($data));

            return $role;
        });
    }

    public function destroy(array $ids): void
    {
        Role::whereIn('id', $ids)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(array $data): array
    {
        $permissions = collect($data['permissions'] ?? $data['selectedPermission'] ?? []);

        $ids = $permissions
            ->map(fn (mixed $permission): mixed => is_array($permission) ? ($permission['id'] ?? null) : $permission)
            ->filter(fn (mixed $permission): bool => is_numeric($permission))
            ->map(fn (mixed $permission): int => (int) $permission);

        $names = $permissions
            ->map(fn (mixed $permission): mixed => is_array($permission) ? ($permission['name'] ?? null) : $permission)
            ->filter(fn (mixed $permission): bool => is_string($permission) && ! is_numeric($permission));

        return $names
            ->merge($this->namesFromIds($ids))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, string>
     */
    private function namesFromIds(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Permission::whereIn('id', $ids->all())->pluck('name');
    }
}
