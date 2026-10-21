<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEscalationRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\EscalationService;
use App\Services\TicketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketEscalationController extends Controller
{
    public function __construct(
        private readonly EscalationService $escalationService,
        private readonly TicketNotificationService $notifications,
    ) {}

    public function store(StoreEscalationRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('handle', $ticket);

        $validated = $request->validated();

        $ticket = $this->escalationService->escalateTicket(
            $ticket,
            $validated,
            $request->user()->id
        );

        $this->notifications->ticketEscalated($ticket, $request->user());

        return response()->json([
            'message' => 'Tiket berhasil dieskalasikan',
            'data' => new TicketResource($ticket),
        ]);
    }

    public function deEscalate(Ticket $ticket): JsonResponse
    {
        Gate::authorize('handle', $ticket);

        $ticket = $this->escalationService->resumeTicket($ticket, auth()->id());

        return response()->json([
            'message' => 'Tiket berhasil ditarik kembali dan sedang diproses.',
            'data' => $ticket,
        ]);
    }
}
