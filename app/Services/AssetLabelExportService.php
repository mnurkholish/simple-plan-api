<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AssetRepository;
use Illuminate\Support\Facades\Gate;
use Spatie\Browsershot\Browsershot;

class AssetLabelExportService
{
    private const PAGE_WIDTH_MM = 210;

    private const PAGE_HEIGHT_MM = 297;

    private const MARGIN_MM = 10;

    private const GAP_MM = 2;

    public function __construct(
        private AssetRepository $assets,
        private AssetLabelService $labels,
    ) {}

    /** @param array<int, int> $assetIds */
    public function generatePdf(array $assetIds, float $widthMm, float $heightMm): string
    {
        $columns = (int) floor((self::PAGE_WIDTH_MM - 2 * self::MARGIN_MM + self::GAP_MM) / ($widthMm + self::GAP_MM));
        $rows = (int) floor((self::PAGE_HEIGHT_MM - 2 * self::MARGIN_MM + self::GAP_MM) / ($heightMm + self::GAP_MM));

        $assets = $this->assets->findForLabels($assetIds);
        $labelData = [];

        foreach ($assets as $asset) {
            Gate::authorize('printLabel', $asset);
            $labelData[] = $this->labels->prepareLabel($asset);
        }

        $html = view('inventory.labels.bulk', [
            'pages' => array_chunk($labelData, $columns * $rows),
            'columns' => $columns,
            'widthMm' => $widthMm,
            'heightMm' => $heightMm,
            'gapMm' => self::GAP_MM,
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            ->margins(self::MARGIN_MM, self::MARGIN_MM, self::MARGIN_MM, self::MARGIN_MM)
            ->showBackground()
            ->pdf();
    }
}
