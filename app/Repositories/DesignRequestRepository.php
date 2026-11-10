<?php

namespace App\Repositories;

use App\Models\DesignRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DesignRequestRepository
{
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
}
