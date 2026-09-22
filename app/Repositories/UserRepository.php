<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository
{
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
