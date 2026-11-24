<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\AssetClassification;
use App\Models\AssetMaintenanceOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

class MaintenanceReportService
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
    public const MONTH_LABELS = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];

    /** @var list<string> */
    public const XLSX_HEADERS = [
        'No. Inventaris',
        'Kode Aset',
        'Nama Aset',
        'Klasifikasi',
        'Lokasi Ruangan',
        'Periode',
        'Tanggal Terjadwal',
        'Tanggal Realisasi',
        'Status',
        'Kondisi Pasca Pemeliharaan',
        'Petugas Pelaksana',
        'Keterangan Keterlambatan',
    ];

    /** @var array<string, string> */
    private const CONDITION_COLORS = [
        'Baik' => '#10B981',
        'Rusak Ringan' => '#00338D',
        'Rusak Berat' => '#EA580C',
    ];

    public function __construct(private readonly ReportExportService $exporter) {}

    /**
     * Shared base query for report aggregates and PDF/XLSX exports without JOIN row duplication.
     *
     * @param  array{
     *     periode_awal?: string|null,
     *     periode_akhir?: string|null,
     *     klasifikasi?: int|string|null
     * }  $filters
     */
    public function query(array $filters, User $actor): Builder
    {
        abort_unless($actor->hasAnyRole(self::ALLOWED_ROLES), 403, 'Anda tidak memiliki izin untuk mengakses laporan pemeliharaan.');

        $query = AssetMaintenanceOccurrence::query();

        if ($this->isSarprasCoordinatorOnly($actor)) {
            $query->whereDoesntHave('assetCode.classification', function (Builder $classification): void {
                $classification->whereRaw('LOWER(TRIM(name)) = ?', ['alat it']);
            });
        }

        $query->scheduledBetween(
            $this->resolveStartDate($filters),
            $this->resolveEndDate($filters),
        );

        $classificationId = $this->resolveClassificationId($filters);
        if ($classificationId !== null) {
            $query->forClassification($classificationId);
        }

        return $query;
    }

    /**
     * @param  array{
     *     periode_awal?: string|null,
     *     periode_akhir?: string|null,
     *     klasifikasi?: int|string|null
     * }  $filters
     * @return array{
     *     summary: array{
     *         jumlah_jadwal: int,
     *         jadwal_selesai: int,
     *         presentase_jadwal_selesai: float|int
     *     },
     *     charts: array{
     *         per_bulan: list<array{bulan: string, jumlah: int}>,
     *         kondisi_pasca_pemeliharaan: array<string, int>,
     *         per_petugas: list<array{petugas: string, jumlah: int}>
     *     }
     * }
     */
    public function report(array $filters, User $actor): array
    {
        $monthCounts = array_fill(1, 12, 0);

        $postConditions = [];
        foreach (AssetCondition::cases() as $case) {
            $postConditions[$case->value] = 0;
        }

        $totalSchedules = 0;
        $completedSchedules = 0;
        $officers = [];

        $this->query($filters, $actor)
            ->select([
                'asset_maintenance_occurrences.id',
                'asset_maintenance_occurrences.asset_maintenance_id',
                'asset_maintenance_occurrences.asset_code_id',
                'asset_maintenance_occurrences.asset_maintenance_log_id',
                'asset_maintenance_occurrences.scheduled_date',
                'asset_maintenance_occurrences.status',
                'asset_maintenance_occurrences.realized_at',
                'asset_maintenance_occurrences.is_late',
            ])
            ->with([
                'log:id,post_maintenance_condition',
                'asset:id,asset_code_id,condition',
                'maintenance' => fn ($relation) => $relation->withTrashed()->select(['id', 'assigned_officer_id']),
                'maintenance.assignedOfficer:id,name',
            ])
            ->chunkById(500, function (Collection $occurrences) use (&$totalSchedules, &$completedSchedules, &$monthCounts, &$postConditions, &$officers): void {
                foreach ($occurrences as $occurrence) {
                    $totalSchedules++;

                    if ($occurrence->scheduled_date !== null) {
                        $monthIndex = (int) $occurrence->scheduled_date->format('n');
                        if (isset($monthCounts[$monthIndex])) {
                            $monthCounts[$monthIndex]++;
                        }
                    }

                    $effectiveStatus = $this->resolveEffectiveStatus($occurrence);
                    if ($effectiveStatus !== MaintenanceStatus::Selesai) {
                        continue;
                    }

                    $completedSchedules++;

                    $condition = $this->resolvePostMaintenanceCondition($occurrence);
                    if ($condition instanceof AssetCondition && array_key_exists($condition->value, $postConditions)) {
                        $postConditions[$condition->value]++;
                    }

                    $officerName = trim((string) ($occurrence->maintenance?->assignedOfficer?->name ?? '')) ?: 'Belum ditentukan';
                    $officers[$officerName] = ($officers[$officerName] ?? 0) + 1;
                }
            }, 'asset_maintenance_occurrences.id', 'id');

        $perBulan = [];
        foreach (self::MONTH_LABELS as $index => $label) {
            $perBulan[] = [
                'bulan' => $label,
                'jumlah' => $monthCounts[$index + 1],
            ];
        }

        $completionPercentage = $totalSchedules > 0
            ? round(($completedSchedules / $totalSchedules) * 100, 2)
            : 0;

        return [
            'summary' => [
                'jumlah_jadwal' => $totalSchedules,
                'jadwal_selesai' => $completedSchedules,
                'presentase_jadwal_selesai' => $completionPercentage,
            ],
            'charts' => [
                'per_bulan' => $perBulan,
                'kondisi_pasca_pemeliharaan' => $postConditions,
                'per_petugas' => $this->sortChartRows($officers, 'petugas'),
            ],
        ];
    }

    /**
     * @param  array{
     *     periode_awal?: string|null,
     *     periode_akhir?: string|null,
     *     klasifikasi?: int|string|null
     * }  $filters
     */
    public function export(array $filters, User $actor, string $format): Response
    {
        if ($format === 'pdf') {
            $report = $this->report($filters, $actor);

            return $this->exporter->pdf(
                $this->buildPdfPayload($filters, $actor, $report),
                'laporan-maintenance',
                [
                    'margins' => ['top' => 14, 'right' => 15, 'bottom' => 18, 'left' => 15],
                    'footer_html' => $this->buildFooterHtml(),
                ],
                'reports.maintenance.pdf',
            );
        }

        $query = $this->query($filters, $actor);

        return $this->exporter->xlsxMultiSheet(
            $this->buildXlsxSheets($query),
            'laporan-maintenance',
        );
    }

    /**
     * @param  array{
     *     periode_awal?: string|null,
     *     periode_akhir?: string|null,
     *     klasifikasi?: int|string|null
     * }  $filters
     * @param  array{
     *     summary: array{
     *         jumlah_jadwal: int,
     *         jadwal_selesai: int,
     *         presentase_jadwal_selesai: float|int
     *     },
     *     charts: array{
     *         per_bulan: list<array{bulan: string, jumlah: int}>,
     *         kondisi_pasca_pemeliharaan: array<string, int>,
     *         per_petugas: list<array{petugas: string, jumlah: int}>
     *     }
     * }  $report
     * @return array<string, mixed>
     */
    private function buildPdfPayload(array $filters, User $actor, array $report): array
    {
        $classificationId = $this->resolveClassificationId($filters);

        $classificationName = $classificationId !== null
            ? AssetClassification::query()->find($classificationId)?->name
            : null;

        $conditionLegend = [];
        $conditionTotal = 0;
        foreach (AssetCondition::cases() as $case) {
            $count = (int) ($report['charts']['kondisi_pasca_pemeliharaan'][$case->value] ?? 0);
            $conditionTotal += $count;
            $conditionLegend[] = [
                'label' => $case->value,
                'count' => $count,
                'color' => self::CONDITION_COLORS[$case->value] ?? '#00338D',
            ];
        }

        $officerItems = array_map(
            fn (array $row): array => ['label' => $row['petugas'], 'count' => $row['jumlah']],
            $report['charts']['per_petugas'],
        );

        return [
            'title' => 'Laporan Maintenance',
            'subtitle' => 'RS Citra Husada Jember',
            'downloaded_at' => $this->formatDownloadedAt($actor),
            'filters' => [
                'Periode' => $this->formatPeriodFilter($filters) ?? 'Semua',
                'Klasifikasi' => $classificationName ?? ($this->isSarprasCoordinatorOnly($actor) ? 'Semua (kecuali Alat IT)' : 'Semua'),
            ],
            'summary' => [
                'jumlah_jadwal' => $report['summary']['jumlah_jadwal'],
                'jadwal_selesai' => $report['summary']['jadwal_selesai'],
                'presentase_jadwal_selesai' => $report['summary']['presentase_jadwal_selesai'],
                'presentase_jadwal_selesai_formatted' => $this->formatPercentage($report['summary']['presentase_jadwal_selesai']),
            ],
            'charts' => [
                'per_bulan_svg' => $this->renderMonthlyColumnChartSvg($report['charts']['per_bulan']),
                'kondisi_donut_svg' => $this->renderDonutChartSvg($conditionLegend, $conditionTotal, 'Alat'),
                'kondisi_legend' => $conditionLegend,
                'per_petugas' => $officerItems,
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

        $occurrences = $query
            ->select([
                'asset_maintenance_occurrences.id',
                'asset_maintenance_occurrences.asset_maintenance_id',
                'asset_maintenance_occurrences.asset_code_id',
                'asset_maintenance_occurrences.asset_maintenance_log_id',
                'asset_maintenance_occurrences.period',
                'asset_maintenance_occurrences.scheduled_date',
                'asset_maintenance_occurrences.status',
                'asset_maintenance_occurrences.realized_at',
                'asset_maintenance_occurrences.is_late',
            ])
            ->with([
                'assetCode:id,asset_classification_id,asset_location_id,asset_item_id,full_code',
                'assetCode.classification:id,name',
                'assetCode.location:id,name',
                'assetCode.item:id,name',
                'asset:id,asset_code_id,detail_item,condition',
                'log:id,post_maintenance_condition',
                'maintenance' => fn ($relation) => $relation->withTrashed()->select(['id', 'assigned_officer_id']),
                'maintenance.assignedOfficer:id,name',
            ])
            ->lazyById(500, 'asset_maintenance_occurrences.id', 'id');

        foreach ($occurrences as $occurrence) {
            $fullCode = (string) ($occurrence->assetCode?->full_code ?? '');
            $assetName = trim(($occurrence->assetCode?->item?->name ?? '').' '.($occurrence->asset?->detail_item ?? ''));
            $classificationName = (string) ($occurrence->assetCode?->classification?->name ?? '');
            $effectiveStatus = $this->resolveEffectiveStatus($occurrence);

            $postCondition = $effectiveStatus === MaintenanceStatus::Selesai
                ? ($this->resolvePostMaintenanceCondition($occurrence)?->value ?? '-')
                : '-';

            $officerName = trim((string) ($occurrence->maintenance?->assignedOfficer?->name ?? '')) ?: '-';

            $row = [
                $fullCode,
                $fullCode,
                $assetName,
                $classificationName,
                (string) ($occurrence->assetCode?->location?->name ?? ''),
                $occurrence->period instanceof MaintenancePeriod ? $occurrence->period->label() : (string) ($occurrence->period ?? ''),
                $occurrence->scheduled_date?->format('Y-m-d') ?? '',
                $occurrence->realized_at?->format('Y-m-d H:i:s') ?? '-',
                $effectiveStatus->label(),
                $postCondition,
                $officerName,
                $this->formatDelayNote($occurrence, $effectiveStatus),
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
     * @param  list<array{bulan: string, jumlah: int}>  $months
     */
    private function renderMonthlyColumnChartSvg(array $months): string
    {
        $width = 640;
        $height = 220;
        $paddingLeft = 16;
        $paddingRight = 16;
        $plotTop = 28;
        $plotBottom = 182;
        $plotHeight = $plotBottom - $plotTop;
        $plotWidth = $width - $paddingLeft - $paddingRight;

        $countItems = max(1, count($months));
        $slotWidth = $plotWidth / $countItems;
        $barWidth = min(28.0, $slotWidth * 0.56);
        $maxValue = max(1, ...array_map(fn (array $row): int => max(0, (int) $row['jumlah']), $months ?: [['jumlah' => 0]]));

        $elements = [];

        // Subtle horizontal gridlines
        for ($step = 0; $step <= 3; $step++) {
            $y = $plotTop + ($plotHeight / 3) * $step;
            $stroke = $step === 3 ? '#CBD5E1' : '#E2E8F0';
            $dash = $step === 3 ? '' : ' stroke-dasharray="3 3"';
            $elements[] = sprintf(
                '<line x1="%d" y1="%.2f" x2="%d" y2="%.2f" stroke="%s" stroke-width="1"%s/>',
                $paddingLeft,
                $y,
                $width - $paddingRight,
                $y,
                $stroke,
                $dash,
            );
        }

        foreach ($months as $index => $item) {
            $count = max(0, (int) $item['jumlah']);
            $centerX = $paddingLeft + ($index * $slotWidth) + ($slotWidth / 2);
            $barX = $centerX - ($barWidth / 2);
            $barHeight = $count > 0 ? max(6.0, ($count / $maxValue) * $plotHeight) : 0.0;
            $barY = $plotBottom - $barHeight;

            if ($barHeight > 0) {
                $r = min(5.0, $barHeight / 2, $barWidth / 2);
                $x1 = $barX;
                $x2 = $barX + $barWidth;
                $path = sprintf(
                    'M %.2f %.2f L %.2f %.2f Q %.2f %.2f %.2f %.2f L %.2f %.2f Q %.2f %.2f %.2f %.2f L %.2f %.2f Z',
                    $x1,
                    $plotBottom,
                    $x1,
                    $barY + $r,
                    $x1,
                    $barY,
                    $x1 + $r,
                    $barY,
                    $x2 - $r,
                    $barY,
                    $x2,
                    $barY,
                    $x2,
                    $barY + $r,
                    $x2,
                    $plotBottom,
                );
                $elements[] = sprintf('<path d="%s" fill="#00338D"/>', $path);
            }

            $valueY = $barHeight > 0 ? $barY - 6 : $plotBottom - 6;
            $valueColor = $count > 0 ? '#0F172A' : '#94A3B8';
            $elements[] = sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="700" fill="%s">%d</text>',
                $centerX,
                $valueY,
                $valueColor,
                $count,
            );

            $escapedLabel = htmlspecialchars($item['bulan'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $elements[] = sprintf(
                '<text x="%.2f" y="%d" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="10" fill="#475569">%s</text>',
                $centerX,
                $plotBottom + 18,
                $escapedLabel,
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

    private function resolveEffectiveStatus(AssetMaintenanceOccurrence $occurrence): MaintenanceStatus
    {
        if ($occurrence->isRealized()) {
            return MaintenanceStatus::Selesai;
        }

        if ($occurrence->isOverdue() || $occurrence->status === MaintenanceStatus::Terlambat) {
            return MaintenanceStatus::Terlambat;
        }

        return MaintenanceStatus::Terjadwal;
    }

    private function resolvePostMaintenanceCondition(AssetMaintenanceOccurrence $occurrence): ?AssetCondition
    {
        $condition = $occurrence->log?->post_maintenance_condition ?? $occurrence->asset?->condition;

        return $condition instanceof AssetCondition ? $condition : null;
    }

    private function formatDelayNote(AssetMaintenanceOccurrence $occurrence, MaintenanceStatus $effectiveStatus): string
    {
        if ($effectiveStatus === MaintenanceStatus::Selesai) {
            if ($occurrence->wasRealizedLate()) {
                $days = $occurrence->overdueDays();

                return $days > 0 ? "Terlambat {$days} hari" : 'Terlambat';
            }

            return 'Tepat Waktu';
        }

        if ($effectiveStatus === MaintenanceStatus::Terlambat) {
            $days = $occurrence->overdueDays();

            return $days > 0 ? "Terlambat {$days} hari" : 'Terlambat';
        }

        return 'Belum Jatuh Tempo';
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

    /** @param array<string, mixed> $filters */
    private function formatPeriodFilter(array $filters): ?string
    {
        $start = $this->resolveStartDate($filters);
        $end = $this->resolveEndDate($filters);

        if ($start !== null && $end !== null) {
            return $this->formatIndonesianDate($start).' - '.$this->formatIndonesianDate($end);
        }

        if ($start !== null) {
            return 'Mulai '.$this->formatIndonesianDate($start);
        }

        if ($end !== null) {
            return 'Sampai '.$this->formatIndonesianDate($end);
        }

        return null;
    }

    private function formatIndonesianDate(string $date): string
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed) {
            return $date;
        }

        $monthLabel = self::MONTH_LABELS[$parsed->month - 1] ?? $parsed->format('M');

        return sprintf('%02d %s %04d', $parsed->day, $monthLabel, $parsed->year);
    }

    private function formatPercentage(float|int $percentage): string
    {
        return rtrim(rtrim(number_format((float) $percentage, 2, '.', ''), '0'), '.').'%';
    }

    private function isSarprasCoordinatorOnly(User $actor): bool
    {
        return ! $actor->hasAnyRole(self::UNRESTRICTED_ROLES)
            && $actor->hasAnyRole(self::SARPRAS_COORDINATOR_ROLES);
    }

    /** @param array<string, mixed> $filters */
    private function resolveStartDate(array $filters): ?string
    {
        $value = $filters['periode_awal'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $filters */
    private function resolveEndDate(array $filters): ?string
    {
        $value = $filters['periode_akhir'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $filters */
    private function resolveClassificationId(array $filters): ?int
    {
        $value = $filters['klasifikasi'] ?? null;

        return $value !== null ? (int) $value : null;
    }
}
