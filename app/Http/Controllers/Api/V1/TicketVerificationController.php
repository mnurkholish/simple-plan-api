<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketNotificationService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;

class TicketVerificationController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketNotificationService $notifications,
    ) {}

    public function verify(VerifyTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['is_approved']) {
            $ticket = $this->ticketService->verifyResolution($ticket, $request->user());
            $message = 'Penyelesaian tiket berhasil diverifikasi dan tiket ditutup.';

        } else {
            $ticket = $this->ticketService->transitionStatus(
                $ticket,
                TicketStatus::Diproses,
                $request->user(),
                $validated['keterangan_kendala']
            );
            $message = 'Verifikasi ditolak, tiket dikembalikan ke status Diproses.';

        }

        $this->notifications->ticketVerified($ticket, $validated['is_approved']);

        return (new TicketResource($this->ticketService->loadSummary($ticket)))
            ->additional(['message' => $message])
            ->response();
    }
}
