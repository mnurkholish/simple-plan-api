<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index(ListTicketRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Ticket::query()
            ->with([
                'reporter:id,name',
                'unit:id,unit_name',
            ])
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
                        foreach (['ticket_number', 'category', 'description'] as $column) {
                            $query->orWhereLike($column, "%{$search}%");
                        }
                    });
                },
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return TicketResource::collection(
            $query->paginate($request->integer('per_page', 15))->withQueryString(),
        );
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($ticket->load([
            'reporter:id,name',
            'unit:id,unit_name',
        ]));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->service->create(
            $request->validated(),
            $request->user(),
            $request->file('initial_evidence'),
        );

        return (new TicketResource($ticket->load([
            'reporter:id,name',
            'unit:id,unit_name',
        ])))
            ->additional(['message' => 'Tiket berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }
}
