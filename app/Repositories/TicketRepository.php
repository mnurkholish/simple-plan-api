<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TicketService;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TicketRepository
{
    /**
     * @param  array{service?: string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null}  $filters
     */
    public function paginateVisibleTo(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        return Ticket::query()
            ->visibleTo($actor)
            ->with($this->summaryRelations())
            ->when(
                $filters['service'] ?? null,
                fn (Builder $query, string $service): Builder => $query->where('service', $service),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', $status),
            )
            ->when(
                $filters['date_from'] ?? null,
                fn (Builder $query, string $dateFrom): Builder => $query->where(
                    'created_at',
                    '>=',
                    CarbonImmutable::createFromFormat('!Y-m-d', $dateFrom),
                ),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn (Builder $query, string $dateTo): Builder => $query->where(
                    'created_at',
                    '<',
                    CarbonImmutable::createFromFormat('!Y-m-d', $dateTo)->addDay(),
                ),
            )
            ->when(
                $filters['search'] ?? null,
                function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $query) use ($search): void {
                        foreach (['ticket_number', 'description'] as $column) {
                            $query->orWhereLike($column, "%{$search}%");
                        }

                        $query
                            ->orWhereHas('tikDetail', function (Builder $detailQuery) use ($search): void {
                                $detailQuery
                                    ->whereLike('custom_it_tag_text', "%{$search}%")
                                    ->orWhereHas(
                                        'qualityCategory',
                                        fn (Builder $categoryQuery): Builder => $categoryQuery->whereLike('name', "%{$search}%"),
                                    )
                                    ->orWhereHas(
                                        'itTag',
                                        fn (Builder $tagQuery): Builder => $tagQuery->whereLike('name', "%{$search}%"),
                                    );
                            })
                            ->orWhereHas(
                                'sarprasDetail.sarprasCategory',
                                fn (Builder $categoryQuery): Builder => $categoryQuery->whereLike('name', "%{$search}%"),
                            );
                    });
                },
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Ticket
    {
        return Ticket::create($attributes);
    }

    public function latestTicketNumber(TicketService $service, string $numberPrefix): ?string
    {
        return Ticket::query()
            ->where('service', $service->value)
            ->whereLike('ticket_number', $numberPrefix.'%')
            ->lockForUpdate()
            ->orderByDesc('ticket_number')
            ->value('ticket_number');
    }

    public function loadSummary(Ticket $ticket): Ticket
    {
        return $ticket->load($this->summaryRelations());
    }

    public function loadDetail(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'asset:id,asset_number,name,brand,location,status',
            'assignedOfficer:id,name',
            'classifiedBy:id,name',
            'handlings.handledBy:id,name',
            'handlings.ticket:id,status',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,from_status,to_status,changed_by_id,notes,created_at',
            'statusHistories.changedBy:id,name',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ]);
    }

    /**
     * @return list<string>
     */
    private function summaryRelations(): array
    {
        return [
            'asset:id,asset_number,name,brand,location,status',
            'assignedOfficer:id,name',
            'classifiedBy:id,name',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ];
    }
}
