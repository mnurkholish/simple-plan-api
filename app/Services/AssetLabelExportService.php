<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Repositories\AssetRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetLabelExportService
{
    public function __construct(
        private AssetRepository $assets,
        private AssetLabelService $labels,
    ) {}

    /** @param array<string, mixed> $selection */
    public function generatePdf(array $selection, float $widthMm, float $heightMm): string
    {
        Gate::authorize('viewAny', Asset::class);

        $allFiltered = ($selection['selection_mode'] ?? 'selected') === 'all_filtered';
        $assets = $allFiltered
            ? $this->assets->filteredQuery($selection['filters'])->whereHas('assetCode')->orderByDesc('assets.id')->get()
            : $this->assets->findForLabels(array_map('intval', $selection['asset_ids']));

        if ($assets->isEmpty()) {
            throw ValidationException::withMessages([
                $allFiltered ? 'filters' : 'asset_ids' => 'No printable assets match this selection.',
            ]);
        }

        $labelData = [];

        foreach ($assets as $asset) {
            Gate::authorize('printLabel', $asset);
            $labelData[] = $this->labels->prepareLabel($asset);
        }

        $html = view('inventory.labels.bulk', [
            'labels' => $labelData,
            'widthMm' => $widthMm,
            'heightMm' => $heightMm,
        ])->render();

        return ConfiguredBrowsershot::html($html)
            ->paperSize($widthMm, $heightMm, 'mm')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->pdf();
    }
}
