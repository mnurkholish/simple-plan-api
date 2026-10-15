<?php

namespace App\Services;

use App\Models\Asset;
use BackedEnum;

class AssetLabelService
{
    public function __construct(private AssetQrCodeService $qrCodeService) {}

    public function renderHtml(Asset $asset): string
    {
        $asset->loadMissing([
            'assetCode.item',
            'assetCode.location',
            'assetCode.classification',
        ]);

        $code = $asset->assetCode;

        $inventoryType = $code->classification->name === 'Alat IT'
            ? 'INVENTARIS ASET TIK'
            : 'INVENTARIS ASET SARPRAS';

        $conditionText = $asset->condition instanceof BackedEnum
            ? $asset->condition->value
            : (string) $asset->condition;

        return view('inventory.labels.asset', [
            'asset' => $asset,
            'inventoryNumber' => $code->full_code,
            'inventoryType' => $inventoryType,
            'itemName' => $code->item->name,
            'roomName' => $code->location->name,
            'conditionText' => $conditionText,
            'qrSvg' => $this->qrCodeService->generateSvg($asset),
        ])->render();
    }
}
