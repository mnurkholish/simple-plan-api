<?php

namespace App\Services;

use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;

class AssetCodeService
{
    public function generate(int $classificationId, int $locationId, int $itemId, int $year, ?int $excludeId = null): array
    {
        $classification = AssetClassification::findOrFail($classificationId);
        $location = AssetLocation::findOrFail($locationId);
        $item = AssetItem::findOrFail($itemId);

        $query = AssetCode::withTrashed()
            ->where('asset_location_id', $locationId)
            ->where('asset_item_id', $itemId)
            ->where('year', $year);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $maxSequence = $query->max('sequence_number');

        $sequence = $maxSequence ? $maxSequence + 1 : 1;

        $fullCode = sprintf(
            '%s/%s/%s/%s/%s',
            $classification->code,
            $location->code,
            $sequence,
            $item->code,
            $year
        );

        return [
            'sequence_number' => $sequence,
            'full_code' => $fullCode,
        ];
    }
}
