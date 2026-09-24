<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ListTicketRequest;
use App\Http\Requests\RejectTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index(ListTicketRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validated();

        $query = Ticket::query()
            ->visibleTo($request->user())
            ->with([
                'assignedOfficer:id,name',
                'reporter:id,name',
                'sarprasDetail.sarprasCategory:id,name',
                'statusHistories:id,ticket_id,to_status,notes,created_at',
                'tikDetail.itTag:id,name',
                'tikDetail.qualityCategory:id,name',
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
            ->orderByDesc('id');

        return TicketResource::collection(
            $query->paginate($request->integer('per_page', 15))->withQueryString(),
        );
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load([
            'assignedOfficer:id,name',
            'handlings.handledBy:id,name',
            'handlings.ticket:id,status',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
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
            'assignedOfficer:id,name',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ])))
            ->additional(['message' => 'Tiket berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function verify(Request $request, Ticket $ticket): TicketResource
    {
        $ticket = $this->service->verify($ticket, $request->user());

        return (new TicketResource($ticket->load([
            'assignedOfficer:id,name',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ])))->additional(['message' => 'Tiket berhasil diklasifikasi.']);
    }

    public function reject(RejectTicketRequest $request, Ticket $ticket): TicketResource
    {
        $ticket = $this->service->reject(
            $ticket,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return (new TicketResource($ticket->load([
            'assignedOfficer:id,name',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ])))->additional(['message' => 'Tiket berhasil ditolak.']);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): TicketResource
    {
        $validated = $request->validated();
        $ticket = $this->service->assign(
            $ticket,
            TicketPriority::from($validated['priority']),
            $validated['officer_id'],
            $request->user(),
        );

        return (new TicketResource($ticket->load([
            'assignedOfficer:id,name',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ])))->additional(['message' => 'Prioritas dan petugas berhasil disimpan.']);
    }
}
