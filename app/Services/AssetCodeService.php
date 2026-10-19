<?php

namespace App\Services;

use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetHistory;
use App\Models\AssetItem;
use App\Models\AssetLocation;

class AssetCodeService
{
    /** The caller holds a transaction when requesting a locked allocation. */
    public function generate(int $classificationId, int $locationId, int $itemId, int $year, bool $lock = false): array
    {
        $classificationQuery = AssetClassification::query();
        if ($lock) {
            $classificationQuery->lockForUpdate();
        }
        $classification = $classificationQuery->findOrFail($classificationId);
        $location = AssetLocation::findOrFail($locationId);
        $item = AssetItem::findOrFail($itemId);

        $prefix = "{$classification->code}/{$location->code}/";
        $suffix = "/{$item->code}/{$year}";
        $sequence = (int) (AssetCode::query()
            ->where('asset_classification_id', $classificationId)
            ->where('asset_location_id', $locationId)
            ->where('asset_item_id', $itemId)
            ->max('sequence_number') ?? 0) + 1;

        do {
            $fullCode = $prefix.$sequence.$suffix;
            $used = AssetCode::query()->where('full_code', $fullCode)->exists()
                || AssetHistory::query()->where('old_values->full_code', $fullCode)->exists()
                || AssetHistory::query()->where('new_values->full_code', $fullCode)->exists();
            if ($used) {
                $sequence++;
            }
        } while ($used);

        return [
            'sequence_number' => $sequence,
            'full_code' => $fullCode,
        ];
    }
}
