<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEscalationRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\EscalationService;
use Illuminate\Http\JsonResponse;

class TicketEscalationController extends Controller
{
    public function __construct(private readonly EscalationService $escalationService) {}

    /**
     * Memproses eskalasi tiket ke pihak ketiga atau unit terkait.
     *
     * @param  StoreEscalationRequest  $request  Request body (target, notes)
     * @param  Ticket  $ticket  Model tiket yang dieskalasi
     * @return JsonResponse JSON yang berisi pesan sukses dan detail tiket
     */
    public function store(StoreEscalationRequest $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validated();

        $ticket = $this->escalationService->escalateTicket(
            $ticket,
            $validated,
            $request->user()->id
        );

        $notifiableUsers = \App\Models\User::role(['super-admin', 'management'])->get();
        $message = 'Tiket #'.$ticket->ticket_number.' telah dieskalasi ke '.$validated['target'];
        foreach ($notifiableUsers as $user) {
            $user->notify(new \App\Notifications\TicketStatusUpdatedNotification($ticket, $message));
        }

        return response()->json([
            'message' => 'Tiket berhasil dieskalasikan',
            'data' => new TicketResource($ticket),
        ]);
    }
}
