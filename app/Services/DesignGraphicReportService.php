<?php

namespace App\Services;

use App\Enums\DesignRequestStatus;
use App\Models\User;
use App\Repositories\DesignRequestRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DesignGraphicReportService
{
    public function __construct(private readonly DesignRequestRepository $designRequests) {}

    /**
     * Shared period scope for the report and a future export.
     *
     * @param  array{periode_awal: string, periode_akhir: string}  $filters
     */
    public function query(array $filters, User $actor): Builder
    {
        abort_unless($actor->hasAnyRole(['super-admin', 'management']), 403);

        return $this->designRequests->reportPeriodQuery([
            'start_date' => $filters['periode_awal'],
            'end_date' => $filters['periode_akhir'],
        ]);
    }

    /** @param array{periode_awal: string, periode_akhir: string} $filters */
    public function report(array $filters, User $actor): array
    {
        $status = [];
        foreach (DesignRequestStatus::cases() as $case) {
            $status[$case->value] = 0;
        }

        $months = [];
        $month = CarbonImmutable::createFromFormat('!Y-m-d', $filters['periode_awal'])->startOfMonth();
        $lastMonth = CarbonImmutable::createFromFormat('!Y-m-d', $filters['periode_akhir'])->startOfMonth();
        while ($month <= $lastMonth) {
            $months[$month->format('Y-m')] = 0;
            $month = $month->addMonth();
        }

        $designTypes = [];
        $durationSeconds = 0;
        $durationCount = 0;

        $this->query($filters, $actor)
            ->select(['id', 'status', 'design_type', 'created_at', 'completed_at'])
            ->chunkById(500, function (Collection $requests) use (&$status, &$months, &$designTypes, &$durationSeconds, &$durationCount): void {
                foreach ($requests as $request) {
                    $status[$request->status->value]++;
                    $months[$request->created_at->format('Y-m')]++;

                    $type = trim(preg_replace('/\s+/u', ' ', $request->design_type) ?? $request->design_type);
                    $key = mb_strtolower($type, 'UTF-8');
                    if (! isset($designTypes[$key])) {
                        $designTypes[$key] = ['jenis_desain' => $type, 'jumlah' => 0];
                    }
                    $designTypes[$key]['jumlah']++;

                    if ($request->status === DesignRequestStatus::Selesai && $request->completed_at !== null) {
                        $durationSeconds += $request->created_at->diffInSeconds($request->completed_at);
                        $durationCount++;
                    }
                }
            });

        $typeChart = array_values($designTypes);
        usort($typeChart, fn (array $a, array $b): int => ($b['jumlah'] <=> $a['jumlah']) ?: strcasecmp($a['jenis_desain'], $b['jenis_desain']));

        $monthChart = [];
        foreach ($months as $name => $count) {
            $monthChart[] = ['bulan' => $name, 'jumlah' => $count];
        }

        return [
            'summary' => [
                'jumlah_request_masuk' => array_sum($status),
                'jumlah_request_selesai' => $status[DesignRequestStatus::Selesai->value],
                'jumlah_request_ditolak' => $status[DesignRequestStatus::Ditolak->value],
                'rata_rata_waktu_penyelesaian' => $durationCount > 0 ? round($durationSeconds / $durationCount / 86400, 2) : 0,
            ],
            'charts' => [
                'request_per_bulan' => $monthChart,
                'status_request' => $status,
                'request_per_jenis_desain' => $typeChart,
            ],
        ];
    }
}
