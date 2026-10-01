<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitService
{
    /**
     * @return Collection<int, Unit>
     */
    public function search(?string $search): Collection
    {
        return Unit::query()
            ->select(['id', 'unit_name'])
            ->when(
                filled($search),
                fn (Builder $query): Builder => $query->whereLike('unit_name', "%{$search}%"),
            )
            ->orderBy('unit_name')
            ->get();
    }

    public function store(array $data): Unit
    {
        return Unit::create($this->withSlug($data));
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($this->withSlug($data));

        return $unit;
    }

    public function syncUsers(Unit $unit, array $userIds): void
    {
        DB::transaction(function () use ($unit, $userIds): void {
            $unit->users()
                ->whereKeyNot($userIds)
                ->update(['unit_id' => null]);

            User::query()
                ->whereKey($userIds)
                ->update(['unit_id' => $unit->getKey()]);
        });
    }

    public function destroy(array $ids): void
    {
        Unit::whereIn('id', $ids)->delete();
    }

    private function withSlug(array $data): array
    {
        return [
            ...$data,
            'slug' => $data['slug'] ?? Str::slug($data['unit_name']),
        ];
    }
}
