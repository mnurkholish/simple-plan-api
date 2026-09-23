<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitService
{
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
