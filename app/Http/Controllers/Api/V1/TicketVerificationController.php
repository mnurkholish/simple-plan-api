<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Notifications\TicketStatusUpdatedNotification;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;

class TicketVerificationController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    /**
     * Memproses verifikasi pelapor setelah tiket diselesaikan oleh teknisi.
     *
     * @param  VerifyTicketRequest  $request  Request body (is_approved, keterangan_kendala)
     * @param  Ticket  $ticket  Model tiket yang diverifikasi
     * @return JsonResponse JSON yang berisi pesan hasil verifikasi dan detail tiket
     */
    public function verify(VerifyTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validated();

        $technician = $ticket->assignedOfficer;

        if ($validated['is_approved']) {
            $ticket = $this->ticketService->verifyResolution($ticket, $request->user());
            $message = 'Penyelesaian tiket berhasil diverifikasi dan tiket ditutup.';

            if ($technician) {
                $notifMsg = 'Penyelesaian tiket #'.$ticket->ticket_number.' telah disetujui (Ditutup).';
                $technician->notify(new TicketStatusUpdatedNotification($ticket, $notifMsg));
            }
        } else {
            $ticket = $this->ticketService->transitionStatus(
                $ticket,
                TicketStatus::Diproses,
                $request->user(),
                $validated['keterangan_kendala']
            );
            $message = 'Verifikasi ditolak, tiket dikembalikan ke status Diproses.';

            if ($technician) {
                $notifMsg = 'Verifikasi tiket #'.$ticket->ticket_number.' ditolak/dikembalikan dengan kendala: '.$validated['keterangan_kendala'];
                $technician->notify(new TicketStatusUpdatedNotification($ticket, $notifMsg));
            }
        }

        return (new TicketResource($this->ticketService->loadSummary($ticket)))
            ->additional(['message' => $message])
            ->response();
    }
}
