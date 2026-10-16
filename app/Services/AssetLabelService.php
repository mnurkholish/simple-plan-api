<?php

namespace App\Services;

use App\Models\Asset;
use BackedEnum;
use Spatie\Browsershot\Browsershot;

class AssetLabelService
{
    public function __construct(private AssetQrCodeService $qrCodeService) {}

    public function renderHtml(Asset $asset, float $widthMm = 90, float $heightMm = 50): string
    {
        return view('inventory.labels.asset', $this->prepareLabel($asset) + [
            'widthMm' => $widthMm,
            'heightMm' => $heightMm,
        ])->render();
    }

    /** @return array<string, mixed> */
    public function prepareLabel(Asset $asset): array
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

        return [
            'asset' => $asset,
            'inventoryNumber' => $code->full_code,
            'inventoryType' => $inventoryType,
            'itemName' => $code->item->name,
            'roomName' => $code->location->name,
            'conditionText' => $conditionText,
            'qrSvg' => $this->qrCodeService->generateSvg($asset),
        ];
    }

    public function generatePng(Asset $asset, float $widthMm = 90, float $heightMm = 50): string
    {
        $html = $this->renderHtml($asset, $widthMm, $heightMm);

        return Browsershot::html($html)
            ->windowSize(800, 600)
            ->deviceScaleFactor(3)
            ->select('.asset-label')
            ->screenshot();
    }

    public function generatePdf(Asset $asset, float $widthMm = 90, float $heightMm = 50): string
    {
        $html = $this->renderHtml($asset, $widthMm, $heightMm);

        return Browsershot::html($html)
            ->paperSize($widthMm, $heightMm, 'mm')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->pdf();
    }
}
