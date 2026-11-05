<?php

namespace App\Services;

use App\Models\Asset;
use BackedEnum;
use Illuminate\Support\Facades\File;

class AssetLabelService
{
    private ?string $logoDataUri = null;

    public function __construct(private AssetQrCodeService $qrCodeService) {}

    public function renderHtml(Asset $asset, float $widthMm = 50, float $heightMm = 60): string
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
        ]);

        $code = $asset->assetCode;

        $conditionText = $asset->condition instanceof BackedEnum
            ? $asset->condition->value
            : (string) $asset->condition;

        return [
            'asset' => $asset,
            'inventoryNumber' => $code->full_code,
            'itemName' => trim($code->item->name.' '.($asset->detail_item ?? '')),
            'roomName' => $code->location->name,
            'conditionText' => $conditionText,
            'logoDataUri' => $this->logoDataUri ??= 'data:image/png;base64,'.base64_encode(
                File::get(resource_path('images/logo-cithus.png'))
            ),
            'qrSvg' => $this->qrCodeService->generateSvg($asset),
        ];
    }

    public function generatePng(Asset $asset, float $widthMm = 50, float $heightMm = 60): string
    {
        $html = $this->renderHtml($asset, $widthMm, $heightMm);

        return ConfiguredBrowsershot::html($html)
            ->windowSize(800, 600)
            ->deviceScaleFactor(3)
            ->select('.asset-label')
            ->screenshot();
    }

    public function generatePdf(Asset $asset, float $widthMm = 50, float $heightMm = 60): string
    {
        $html = $this->renderHtml($asset, $widthMm, $heightMm);

        return ConfiguredBrowsershot::html($html)
            ->paperSize($widthMm, $heightMm, 'mm')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->pdf();
    }
}
