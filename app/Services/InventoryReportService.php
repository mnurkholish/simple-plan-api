<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetLocation;
use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

class InventoryReportService
{
    /** @var list<string> */
    public const ALLOWED_ROLES = [
        'super-admin',
        'super_admin',
        'management',
        'manajemen',
        'koordinator-sarpras',
        'koordinator_sarpras',
    ];

    /** @var list<string> */
    public const UNRESTRICTED_ROLES = [
        'super-admin',
        'super_admin',
        'management',
        'manajemen',
    ];

    /** @var list<string> */
    public const SARPRAS_COORDINATOR_ROLES = [
        'koordinator-sarpras',
        'koordinator_sarpras',
    ];

    /** @var list<string> */
    public const XLSX_HEADERS = [
        'No. Inventaris',
        'Kode Aset',
        'Nama Aset',
        'Klasifikasi',
        'Lokasi Ruangan',
        'Status',
        'Kondisi',
    ];

    public function __construct(private readonly ReportExportService $exporter) {}

    /**
     * Shared scope for report aggregates and PDF/XLSX exports without JOIN row duplication.
     *
     * @param  array{
     *     classification_id?: int|string|null,
     *     klasifikasi?: int|string|null,
     *     status?: string|null,
     *     location_id?: int|string|null,
     *     lokasi_ruangan?: int|string|null
     * }  $filters
     */
    public function query(array $filters, User $actor): Builder
    {
        abort_unless($actor->hasAnyRole(self::ALLOWED_ROLES), 403);

        $query = Asset::query();

        if ($this->isSarprasCoordinatorOnly($actor)) {
            $query->whereDoesntHave('assetCode.classification', function (Builder $classification): void {
                $classification->whereRaw('LOWER(TRIM(name)) = ?', ['alat it']);
            });
        }

        $classificationId = $this->resolveClassificationId($filters);
        if ($classificationId !== null) {
            $query->whereHas('assetCode', fn (Builder $code): Builder => $code->where('asset_classification_id', $classificationId));
        }

        $locationId = $this->resolveLocationId($filters);
        if ($locationId !== null) {
            $query->whereHas('assetCode', fn (Builder $code): Builder => $code->where('asset_location_id', $locationId));
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('assets.status', $filters['status']);
        }

        return $query;
    }

    /**
     * @param  array{
     *     classification_id?: int|string|null,
     *     klasifikasi?: int|string|null,
     *     status?: string|null,
     *     location_id?: int|string|null,
     *     lokasi_ruangan?: int|string|null
     * }  $filters
     * @return array{
     *     summary: array{total_aset: int, total_rusak: int, total_dipinjam: int},
     *     charts: array{
     *         per_klasifikasi: list<array{klasifikasi: string, jumlah: int}>,
     *         per_kondisi: array<string, int>,
     *         per_lokasi: list<array{lokasi: string, jumlah: int}>
     *     }
     * }
     */
    public function report(array $filters, User $actor): array
    {
        $conditions = [];
        foreach (AssetCondition::cases() as $case) {
            $conditions[$case->value] = 0;
        }

        $total = 0;
        $damaged = 0;
        $borrowed = 0;
        $classifications = [];
        $locations = [];

        $this->query($filters, $actor)
            ->select(['assets.id', 'assets.asset_code_id', 'assets.status', 'assets.condition'])
            ->with([
                'assetCode:id,asset_classification_id,asset_location_id',
                'assetCode.classification:id,name',
                'assetCode.location:id,name',
            ])
            ->chunkById(500, function (Collection $assets) use (&$total, &$damaged, &$borrowed, &$conditions, &$classifications, &$locations): void {
                foreach ($assets as $asset) {
                    $total++;

                    if ($asset->status === AssetStatus::Damaged) {
                        $damaged++;
                    }

                    if ($asset->status === AssetStatus::Borrowed) {
                        $borrowed++;
                    }

                    if ($asset->condition instanceof AssetCondition && array_key_exists($asset->condition->value, $conditions)) {
                        $conditions[$asset->condition->value]++;
                    }

                    $classificationName = $asset->assetCode?->classification?->name ?? 'Belum diklasifikasi';
                    $classifications[$classificationName] = ($classifications[$classificationName] ?? 0) + 1;

                    $locationName = $asset->assetCode?->location?->name ?? 'Belum ditentukan';
                    $locations[$locationName] = ($locations[$locationName] ?? 0) + 1;
                }
            }, 'assets.id', 'id');

        return [
            'summary' => [
                'total_aset' => $total,
                'total_rusak' => $damaged,
                'total_dipinjam' => $borrowed,
            ],
            'charts' => [
                'per_klasifikasi' => $this->sortChartRows($classifications, 'klasifikasi'),
                'per_kondisi' => $conditions,
                'per_lokasi' => array_slice($this->sortChartRows($locations, 'lokasi'), 0, 10),
            ],
        ];
    }

    /**
     * @param  array{
     *     classification_id?: int|string|null,
     *     klasifikasi?: int|string|null,
     *     status?: string|null,
     *     location_id?: int|string|null,
     *     lokasi_ruangan?: int|string|null
     * }  $filters
     */
    public function export(array $filters, User $actor, string $format): Response
    {
        if ($format === 'pdf') {
            $report = $this->report($filters, $actor);

            return $this->exporter->pdf(
                $this->buildPdfPayload($filters, $actor, $report),
                'laporan-inventaris',
            );
        }

        $query = $this->query($filters, $actor);

        return $this->exporter->xlsx(
            self::XLSX_HEADERS,
            $this->exportRows($query),
            'laporan-inventaris',
            'Laporan Inventaris',
        );
    }

    /**
     * @param  array{
     *     classification_id?: int|string|null,
     *     klasifikasi?: int|string|null,
     *     status?: string|null,
     *     location_id?: int|string|null,
     *     lokasi_ruangan?: int|string|null
     * }  $filters
     * @param  array{
     *     summary: array{total_aset: int, total_rusak: int, total_dipinjam: int},
     *     charts: array{
     *         per_klasifikasi: list<array{klasifikasi: string, jumlah: int}>,
     *         per_kondisi: array<string, int>,
     *         per_lokasi: list<array{lokasi: string, jumlah: int}>
     *     }
     * }  $report
     * @return array{
     *     title: string,
     *     filters: array<string, scalar|null>,
     *     summary: array<string, scalar|null>,
     *     charts: list<array{title: string, svg: string}>
     * }
     */
    private function buildPdfPayload(array $filters, User $actor, array $report): array
    {
        $classificationId = $this->resolveClassificationId($filters);
        $locationId = $this->resolveLocationId($filters);

        $classificationName = $classificationId !== null
            ? AssetClassification::query()->find($classificationId)?->name
            : null;
        $locationName = $locationId !== null
            ? AssetLocation::query()->find($locationId)?->name
            : null;

        $conditionItems = [];
        foreach ($report['charts']['per_kondisi'] as $label => $count) {
            $conditionItems[] = ['label' => $label, 'count' => $count];
        }

        return [
            'title' => 'Laporan Inventaris',
            'filters' => [
                'Klasifikasi' => $classificationName ?? ($this->isSarprasCoordinatorOnly($actor) ? 'Semua (kecuali Alat IT)' : null),
                'Status' => $filters['status'] ?? null,
                'Lokasi Ruangan' => $locationName,
            ],
            'summary' => [
                'Total Aset' => $report['summary']['total_aset'],
                'Total Rusak' => $report['summary']['total_rusak'],
                'Total Dipinjam' => $report['summary']['total_dipinjam'],
            ],
            'charts' => [
                [
                    'title' => 'Aset per Klasifikasi',
                    'svg' => $this->renderBarChartSvg(
                        array_map(
                            fn (array $row): array => ['label' => $row['klasifikasi'], 'count' => $row['jumlah']],
                            $report['charts']['per_klasifikasi'],
                        ),
                        '#1f4e78',
                    ),
                ],
                [
                    'title' => 'Aset per Kondisi',
                    'svg' => $this->renderBarChartSvg($conditionItems, '#2b6cb0'),
                ],
                [
                    'title' => 'Top 10 Lokasi dengan Aset Terbanyak',
                    'svg' => $this->renderBarChartSvg(
                        array_map(
                            fn (array $row): array => ['label' => $row['lokasi'], 'count' => $row['jumlah']],
                            $report['charts']['per_lokasi'],
                        ),
                        '#2c7a7b',
                    ),
                ],
            ],
        ];
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    private function exportRows(Builder $query): Generator
    {
        $assets = $query
            ->select(['assets.id', 'assets.asset_code_id', 'assets.detail_item', 'assets.status', 'assets.condition'])
            ->with([
                'assetCode:id,asset_classification_id,asset_location_id,asset_item_id,full_code',
                'assetCode.classification:id,name',
                'assetCode.location:id,name',
                'assetCode.item:id,name',
            ])
            ->lazyById(500, 'assets.id', 'id');

        foreach ($assets as $asset) {
            $fullCode = (string) ($asset->assetCode?->full_code ?? '');
            $itemName = trim(($asset->assetCode?->item?->name ?? '').' '.($asset->detail_item ?? ''));

            yield [
                $fullCode,
                $fullCode,
                $itemName,
                (string) ($asset->assetCode?->classification?->name ?? ''),
                (string) ($asset->assetCode?->location?->name ?? ''),
                $asset->status instanceof AssetStatus ? $asset->status->value : (string) ($asset->status ?? ''),
                $asset->condition instanceof AssetCondition ? $asset->condition->value : (string) ($asset->condition ?? ''),
            ];
        }
    }

    /**
     * @param  list<array{label: string, count: int}>  $items
     */
    private function renderBarChartSvg(array $items, string $barColor): string
    {
        $width = 640;

        if ($items === []) {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 60" width="640" height="60" role="img">'
                .'<rect x="0" y="0" width="640" height="60" fill="#f8fafc" stroke="#dce4ea" rx="4"/>'
                .'<text x="320" y="34" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#64748b">Tidak ada data</text>'
                .'</svg>';
        }

        $rowHeight = 28;
        $paddingY = 12;
        $labelWidth = 200;
        $barX = 212;
        $maxBarWidth = 360;
        $barHeight = 16;
        $height = ($paddingY * 2) + (count($items) * $rowHeight);
        $maxValue = max(1, ...array_map(fn (array $item): int => max(0, (int) $item['count']), $items));

        $elements = [
            sprintf('<rect x="0" y="0" width="%d" height="%d" fill="#ffffff" stroke="#dce4ea" rx="4"/>', $width, $height),
        ];

        foreach ($items as $index => $item) {
            $y = $paddingY + ($index * $rowHeight);
            $count = max(0, (int) $item['count']);
            $barWidth = $count > 0 ? max(2, (int) round(($count / $maxValue) * $maxBarWidth)) : 0;
            $labelText = mb_strimwidth($item['label'], 0, 28, '...', 'UTF-8');
            $escapedLabel = htmlspecialchars($labelText, ENT_QUOTES | ENT_XML1, 'UTF-8');

            $elements[] = sprintf(
                '<text x="%d" y="%d" text-anchor="end" font-family="Arial, sans-serif" font-size="11" fill="#243447">%s</text>',
                $labelWidth,
                $y + 12,
                $escapedLabel,
            );
            $elements[] = sprintf(
                '<rect x="%d" y="%d" width="%d" height="%d" fill="#edf3f7" rx="3"/>',
                $barX,
                $y,
                $maxBarWidth,
                $barHeight,
            );
            if ($barWidth > 0) {
                $elements[] = sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="%s" rx="3"/>',
                    $barX,
                    $y,
                    $barWidth,
                    $barHeight,
                    $barColor,
                );
            }
            $elements[] = sprintf(
                '<text x="%d" y="%d" font-family="Arial, sans-serif" font-size="11" font-weight="bold" fill="#173b5e">%d</text>',
                $barX + $maxBarWidth + 10,
                $y + 12,
                $count,
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img">%s</svg>',
            $width,
            $height,
            $width,
            $height,
            implode('', $elements),
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, int|string>>
     */
    private function sortChartRows(array $counts, string $labelKey): array
    {
        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = [$labelKey => $label, 'jumlah' => $count];
        }

        usort(
            $rows,
            fn (array $a, array $b): int => ($b['jumlah'] <=> $a['jumlah']) ?: strcasecmp((string) $a[$labelKey], (string) $b[$labelKey]),
        );

        return $rows;
    }

    private function isSarprasCoordinatorOnly(User $actor): bool
    {
        return ! $actor->hasAnyRole(self::UNRESTRICTED_ROLES)
            && $actor->hasAnyRole(self::SARPRAS_COORDINATOR_ROLES);
    }

    /** @param array<string, mixed> $filters */
    private function resolveClassificationId(array $filters): ?int
    {
        $value = $filters['classification_id'] ?? $filters['klasifikasi'] ?? null;

        return $value !== null ? (int) $value : null;
    }

    /** @param array<string, mixed> $filters */
    private function resolveLocationId(array $filters): ?int
    {
        $value = $filters['location_id'] ?? $filters['lokasi_ruangan'] ?? null;

        return $value !== null ? (int) $value : null;
    }
}
