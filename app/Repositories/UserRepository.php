<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository
{
    /**
     * @return Collection<int, User>
     */
    public function getAssigneeOptions(string $roleName, ?string $search = null): Collection
    {
        return User::query()
            ->select(['id', 'name', 'jabatan'])
            ->where('status', 'active')
            ->where('status_user', 'Aktif')
            ->whereHas('roles', fn (Builder $query): Builder => $query
                ->where('name', $roleName)
                ->where('guard_name', 'web'))
            ->when(
                filled($search),
                fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('jabatan', "%{$search}%");
                }),
            )
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function getPaginatedUsers(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(User::with(['unit', 'roles']))
            ->allowedFilters(...[
                AllowedFilter::exact('status_user'),
                AllowedFilter::callback('search', function (Builder $query, $value): void {
                    $query->where(function (Builder $q) use ($value): void {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('nip', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('unit_id'),
            ])
            ->defaultSort('-created_at')
            ->paginate($perPage);
    }

    public function findById(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function toggleStatus(User $user, array $data): User
    {
        $user->update([
            'status_user' => $data['status_user'],
            'alasan_nonaktif' => $data['alasan_nonaktif'] ?? null,
            'riwayat_status_akun' => $data['riwayat_status_akun'] ?? null,
        ]);

        return $user;
    }
}
