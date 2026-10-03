<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEscalationRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\EscalationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('handle', $ticket);

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

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/de-escalate",
     *     operationId="deEscalateTicket",
     *     tags={"Helpdesk"},
     *     summary="Menarik kembali tiket dari status eskalasi (De-escalate)",
     *     description="Hanya petugas yang ditugaskan dengan permission tickets-handle yang dapat melakukan ini.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="ticket",
     *         in="path",
     *         required=true,
     *         description="ID dari tiket",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Accept",
     *         in="header",
     *         required=true,
     *         description="Wajib diisi application/json agar tidak ter-redirect ke rute login",
     *         @OA\Schema(type="string", default="application/json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil ditarik"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - Token tidak valid atau tidak dikirim"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Missing permission or wrong officer",
     *         @OA\JsonContent(ref="#/components/schemas/TicketReadError")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ticket not found",
     *         @OA\JsonContent(ref="#/components/schemas/TicketReadError")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Ticket state conflict",
     *         @OA\JsonContent(ref="#/components/schemas/TicketReadError")
     *     )
     * )
     */
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
