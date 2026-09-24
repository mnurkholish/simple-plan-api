<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssigneeOptionsRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ClassifyTicketRequest;
use App\Http\Requests\ListTicketRequest;
use App\Http\Requests\RejectTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\AssigneeOptionResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index(ListTicketRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validated();

        return TicketResource::collection(
            $this->service->paginateVisibleTo(
                $request->user(),
                $filters,
                $request->integer('per_page', 15),
            ),
        );
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($this->service->loadDetail($ticket));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->service->create(
            $request->validated(),
            $request->user(),
            $request->file('initial_evidence'),
        );

        return (new TicketResource($ticket))
            ->additional(['message' => 'Tiket berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function classify(ClassifyTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('classify', $ticket);

        $ticket = $this->service->classify(
            $ticket,
            $request->validated(),
            $request->user(),
        );

        return (new TicketResource($this->service->loadSummary($ticket)))
            ->additional(['message' => 'Tiket berhasil diklasifikasi.']);
    }

    public function reject(RejectTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('reject', $ticket);

        $ticket = $this->service->reject(
            $ticket,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return (new TicketResource($this->service->loadSummary($ticket)))
            ->additional(['message' => 'Tiket berhasil ditolak.']);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('assign', $ticket);

        $validated = $request->validated();
        $isReassignment = in_array($ticket->status, [TicketStatus::Ditugaskan, TicketStatus::Diproses], true);
        $ticket = $this->service->assign(
            $ticket,
            $validated,
            $request->user(),
        );

        return (new TicketResource($this->service->loadSummary($ticket)))
            ->additional(['message' => $isReassignment
                ? 'Petugas berhasil ditugaskan ulang.'
                : 'Petugas berhasil ditugaskan.']);
    }

    public function assigneeOptions(
        AssigneeOptionsRequest $request,
        Ticket $ticket,
    ): AnonymousResourceCollection {
        Gate::authorize('assign', $ticket);

        return AssigneeOptionResource::collection(
            $this->service->assigneeOptions(
                $ticket,
                $request->string('search')->toString() ?: null,
            ),
        );
    }
}
