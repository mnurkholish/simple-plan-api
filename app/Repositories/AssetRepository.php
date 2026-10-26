<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AssetRepository
{
    public function findByUidOrFail(string $uid): Asset
    {
        return Asset::query()
            ->with(['assetCode.item', 'assetCode.location'])
            ->where('asset_uid', $uid)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $filters */
    public function filteredQuery(array $filters): Builder
    {
        $query = Asset::query()->with(['assetCode.classification', 'assetCode.location', 'assetCode.item']);
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model_specification', 'like', "%{$search}%")
                    ->orWhere('detail_item', 'like', "%{$search}%")
                    ->orWhere('location_detail', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('assetCode', function (Builder $code) use ($search): void {
                        $code->where('full_code', 'like', "%{$search}%")
                            ->orWhereHas('item', fn (Builder $item): Builder => $item->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('location', fn (Builder $location): Builder => $location->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('classification', fn (Builder $classification): Builder => $classification->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $classificationId = $filters['asset_classification_id'] ?? $filters['classification_id'] ?? null;
        if ($classificationId !== null) {
            $query->whereHas('assetCode', fn (Builder $code): Builder => $code->where('asset_classification_id', $classificationId));
        }

        $locationId = $filters['asset_location_id'] ?? $filters['location_id'] ?? null;
        if ($locationId !== null) {
            $query->whereHas('assetCode', fn (Builder $code): Builder => $code->where('asset_location_id', $locationId));
        }

        if (isset($filters['asset_item_id'])) {
            $query->whereHas('assetCode', fn (Builder $code): Builder => $code->where('asset_item_id', $filters['asset_item_id']));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        $year = $filters['purchase_year'] ?? $filters['year'] ?? null;
        if ($year !== null) {
            $query->where('purchase_year', $year);
        }

        if ($filters['moved_last_3_months'] ?? false) {
            $query->whereHas('histories', fn (Builder $history): Builder => $history
                ->where('is_location_change', true)
                ->where('created_at', '>=', now()->subMonths(3)));
        }

        return $query;
    }

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
                        ->whereHas('assetCode', fn (Builder $code): Builder => $code->where('full_code', 'like', "%{$search}%"))
                        ->orWhereHas('assetCode.item', fn (Builder $item): Builder => $item->where('name', 'like', "%{$search}%"));
                }),
            )
            ->orderBy('id')
            ->get();
    }
}
