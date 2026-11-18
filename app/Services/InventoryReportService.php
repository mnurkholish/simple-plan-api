<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetLocation;
use App\Models\User;
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

    /** @var list<string> */
    private const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** @var list<string> */
    private const CLASSIFICATION_COLORS = [
        '#00338D',
        '#10B981',
        '#EA580C',
        '#0284C7',
        '#7C3AED',
        '#D97706',
        '#0D9488',
        '#DB2777',
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
                'per_lokasi' => $this->sortChartRows($locations, 'lokasi'),
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
                [
                    'margins' => ['top' => 14, 'right' => 15, 'bottom' => 18, 'left' => 15],
                    'footer_html' => $this->buildFooterHtml(),
                ],
                'reports.inventory.pdf',
            );
        }

        $query = $this->query($filters, $actor);

        return $this->exporter->xlsxMultiSheet(
            $this->buildXlsxSheets($query),
            'laporan-inventaris',
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
     * @return array<string, mixed>
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

        $classificationLegend = [];
        foreach ($report['charts']['per_klasifikasi'] as $index => $row) {
            $classificationLegend[] = [
                'label' => $row['klasifikasi'],
                'count' => $row['jumlah'],
                'color' => self::CLASSIFICATION_COLORS[$index % count(self::CLASSIFICATION_COLORS)],
            ];
        }

        $conditionItems = [];
        foreach ($report['charts']['per_kondisi'] as $label => $count) {
            $conditionItems[] = ['label' => $label, 'count' => $count];
        }

        $locationItems = array_map(
            fn (array $row): array => ['label' => $row['lokasi'], 'count' => $row['jumlah']],
            $report['charts']['per_lokasi'],
        );

        return [
            'title' => 'Laporan Inventaris',
            'subtitle' => 'RS Citra Husada Jember',
            'downloaded_at' => $this->formatDownloadedAt($actor),
            'filters' => [
                'Klasifikasi' => $classificationName ?? ($this->isSarprasCoordinatorOnly($actor) ? 'Semua (kecuali Alat IT)' : 'Semua'),
                'Status' => $filters['status'] ?? 'Semua',
                'Lokasi ruangan' => $locationName ?? 'Semua',
            ],
            'summary' => $report['summary'],
            'charts' => [
                'per_klasifikasi_donut_svg' => $this->renderDonutChartSvg(
                    $classificationLegend,
                    $report['summary']['total_aset'],
                    'Aset',
                ),
                'per_klasifikasi_legend' => $classificationLegend,
                'per_kondisi' => $conditionItems,
                'per_lokasi' => $locationItems,
            ],
        ];
    }

    /**
     * @return list<array{title: string, headers: list<string>, rows: list<array<int, string>>}>
     */
    private function buildXlsxSheets(Builder $query): array
    {
        $allRows = [];
        $rowsByClassification = [];

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
            $classificationName = (string) ($asset->assetCode?->classification?->name ?? '');

            $row = [
                $fullCode,
                $fullCode,
                $itemName,
                $classificationName,
                (string) ($asset->assetCode?->location?->name ?? ''),
                $asset->status instanceof AssetStatus ? $asset->status->value : (string) ($asset->status ?? ''),
                $asset->condition instanceof AssetCondition ? $asset->condition->value : (string) ($asset->condition ?? ''),
            ];

            $allRows[] = $row;
            $sheetKey = $classificationName !== '' ? $classificationName : 'Belum diklasifikasi';
            $rowsByClassification[$sheetKey][] = $row;
        }

        ksort($rowsByClassification, SORT_NATURAL | SORT_FLAG_CASE);

        $sheets = [
            [
                'title' => 'Semua Data',
                'headers' => self::XLSX_HEADERS,
                'rows' => $allRows,
            ],
        ];

        foreach ($rowsByClassification as $classificationName => $rows) {
            $sheets[] = [
                'title' => $classificationName,
                'headers' => self::XLSX_HEADERS,
                'rows' => $rows,
            ];
        }

        return $sheets;
    }

    /**
     * @param  list<array{label: string, count: int, color: string}>  $slices
     */
    private function renderDonutChartSvg(array $slices, int $total, string $subtext): string
    {
        $size = 180;
        $cx = 90;
        $cy = 90;
        $radius = 64;
        $strokeWidth = 24;
        $circumference = 2 * M_PI * $radius;

        $segments = [
            sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="#E2E8F0" stroke-width="%d"/>',
                $cx,
                $cy,
                $radius,
                $strokeWidth,
            ),
        ];

        if ($total > 0) {
            $accumulated = 0.0;
            foreach ($slices as $slice) {
                $count = max(0, (int) $slice['count']);
                if ($count === 0) {
                    continue;
                }

                $dashLength = ($count / $total) * $circumference;
                $color = htmlspecialchars($slice['color'], ENT_QUOTES | ENT_XML1, 'UTF-8');

                $segments[] = sprintf(
                    '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s" stroke-width="%d" stroke-dasharray="%.4f %.4f" stroke-dashoffset="%.4f" transform="rotate(-90 %d %d)"/>',
                    $cx,
                    $cy,
                    $radius,
                    $color,
                    $strokeWidth,
                    $dashLength,
                    $circumference,
                    -$accumulated,
                    $cx,
                    $cy,
                );

                $accumulated += $dashLength;
            }
        }

        $formattedTotal = htmlspecialchars(number_format($total, 0, ',', '.'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $escapedSubtext = htmlspecialchars($subtext, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $segments[] = sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="700" fill="#0F172A">%s</text>',
            $cx,
            $cy - 2,
            $formattedTotal,
        );
        $segments[] = sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="11" fill="#64748B">%s</text>',
            $cx,
            $cy + 16,
            $escapedSubtext,
        );

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img">%s</svg>',
            $size,
            $size,
            $size,
            $size,
            implode('', $segments),
        );
    }

    private function buildFooterHtml(): string
    {
        return '<div style="width:100%;font-family:Arial,Helvetica,sans-serif;font-size:9px;color:#64748B;padding:0 15mm;box-sizing:border-box;">'
            .'<div style="border-top:1px solid #CBD5E1;padding-top:6px;text-align:right;">'
            .'Halaman <span class="pageNumber"></span> dari <span class="totalPages"></span>'
            .'</div>'
            .'</div>';
    }

    private function formatDownloadedAt(User $actor): string
    {
        $now = now();
        $monthLabel = self::MONTH_LABELS[$now->month - 1] ?? $now->format('M');
        $dateText = sprintf('%02d %s %04d', $now->day, $monthLabel, $now->year);

        $roleName = $actor->getRoleNames()->first();
        $roleLabel = is_string($roleName) && $roleName !== ''
            ? match ($roleName) {
                'super-admin', 'super_admin' => 'Super Admin',
                'management', 'manajemen' => 'Manajemen',
                'koordinator-sarpras', 'koordinator_sarpras' => 'Koordinator Sarpras',
                default => $roleName,
            }
        : null;

        $name = trim((string) ($actor->name ?? ''));
        if ($name !== '' && $roleLabel !== null) {
            return "{$dateText}, {$name} ({$roleLabel})";
        }

        return $dateText.', '.($name !== '' ? $name : ($roleLabel ?? 'Pengguna'));
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
