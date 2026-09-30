<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssetRepository
{
    /**
     * @return Collection<int, Asset>
     */
    public function search(int $unitId, ?string $search): Collection
    {
        return Asset::query()
            ->select(['id', 'asset_number', 'name', 'brand', 'location', 'status'])
            ->where('unit_id', $unitId)
            ->when(
                filled($search),
                fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereLike('asset_number', "%{$search}%")
                        ->orWhereLike('name', "%{$search}%");
                }),
            )
            ->orderBy('name')
            ->get();
    }
}
