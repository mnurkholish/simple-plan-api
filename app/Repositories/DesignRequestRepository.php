<?php

namespace App\Repositories;

use App\Models\DesignRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DesignRequestRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DesignRequest
    {
        return DesignRequest::create($attributes);
    }

    public function loadSummary(DesignRequest $designRequest): DesignRequest
    {
        return $designRequest->load(['requester:id,name', 'unit:id,unit_name']);
    }

    public function getTasksByAssignee(int|string $userId, int $perPage = 10): LengthAwarePaginator
    {
        return DesignRequest::query()
            ->where('assigned_officer_id', $userId)
            ->with(['requester:id,name', 'unit:id,unit_name', 'drafts'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     period: array{start_date: string, end_date: string},
     *     total: int,
     *     by_status: array<string, int>,
     *     by_design_type: array<string, int>,
     *     requests: Collection<int, DesignRequest>
     * }
     */
    public function getReportSummary(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $baseQuery = DesignRequest::query()
            ->whereBetween('created_at', [$start, $end]);

        $total = (clone $baseQuery)->count();

        $byStatus = (clone $baseQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byDesignType = (clone $baseQuery)
            ->selectRaw('design_type, count(*) as count')
            ->groupBy('design_type')
            ->pluck('count', 'design_type')
            ->toArray();

        $requests = (clone $baseQuery)
            ->with(['requester:id,name', 'unit:id,unit_name', 'assignedOfficer:id,name', 'drafts'])
            ->latest('created_at')
            ->get();

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'total' => $total,
            'by_status' => $byStatus,
            'by_design_type' => $byDesignType,
            'requests' => $requests,
        ];
    }
}
