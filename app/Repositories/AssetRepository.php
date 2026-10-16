<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssetRepository
{
    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Asset>
     */
    public function findForLabels(array $ids): Collection
    {
        return Asset::query()
            ->with(['assetCode.classification', 'assetCode.location', 'assetCode.item'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Asset $asset): int => array_search($asset->id, $ids, true))
            ->values();
    }

    /**
     * @return Collection<int, Asset>
     */
    public function search(int $locationId, ?string $search): Collection
    {
        return Asset::query()
            ->with(['assetCode.classification', 'assetCode.location', 'assetCode.item'])
            ->whereHas('assetCode', fn (Builder $query): Builder => $query->where('asset_location_id', $locationId))
            ->when(
                filled($search),
                fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereLike('asset_number', "%{$search}%")
                        ->orWhereLike('name', "%{$search}%")
                        ->orWhereHas('assetCode', fn (Builder $code): Builder => $code
                            ->whereLike('full_code', "%{$search}%")
                            ->orWhereHas('item', fn (Builder $item): Builder => $item->whereLike('name', "%{$search}%")));
                }),
            )
            ->orderBy('id')
            ->get();
    }
}
