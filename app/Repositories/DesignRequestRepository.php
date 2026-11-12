<?php

namespace App\Repositories;

use App\Models\DesignRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DesignRequestRepository
{
    /** @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function reportStatusCounts(array $filters): array
    {
        return $this->reportPeriodQuery($filters)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->status => (int) $row->total])
            ->all();
    }

    /** @param array<string, mixed> $filters
     * @return list<array{design_type: string, total: int}>
     */
    public function reportDesignTypeCounts(array $filters): array
    {
        return $this->reportPeriodQuery($filters)
            ->select('design_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('design_type')
            ->orderByDesc('total')
            ->orderBy('design_type')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'design_type' => $row->design_type,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /** @param array<string, mixed> $filters */
    public function paginateReportRequests(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->reportPeriodQuery($filters)
            ->with('unit:id,unit_name')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('request_number', "%{$search}%")
                        ->orWhereLike('title', "%{$search}%");
                }),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    private function reportPeriodQuery(array $filters): Builder
    {
        return DesignRequest::query()
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date): Builder => $query->where(
                    'created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $date),
                ),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date): Builder => $query->where(
                    'created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $date)->addDay(),
                ),
            );
    }

    /** @param array<string, mixed> $filters */
    public function paginateVisibleTo(User $actor, string $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        return DesignRequest::query()
            ->with(['requester:id,name', 'unit:id,unit_name'])
            ->when(
                $scope === 'mine',
                fn (Builder $query): Builder => $query->where('requester_id', $actor->getKey()),
            )
            ->when(
                $scope === 'assigned',
                fn (Builder $query): Builder => $query->where('assigned_officer_id', $actor->getKey()),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', $status),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('request_number', "%{$search}%")
                        ->orWhereLike('title', "%{$search}%");
                }),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DesignRequest
    {
        return DesignRequest::create($attributes);
    }

    public function loadSummary(DesignRequest $designRequest): DesignRequest
    {
        return $designRequest->load(['requester:id,name', 'unit:id,unit_name']);
    }

    public function loadDetail(DesignRequest $designRequest): DesignRequest
    {
        return $designRequest->load([
            'requester:id,name',
            'unit:id,unit_name',
            'assignedOfficer:id,name',
            'latestDraft.uploadedBy:id,name',
            'latestRevision.requestedBy:id,name',
        ]);
    }

    public function findForUpdate(int $id): DesignRequest
    {
        return DesignRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    public function update(DesignRequest $designRequest, array $attributes): DesignRequest
    {
        $designRequest->update($attributes);

        return $designRequest->refresh();
    }
}
