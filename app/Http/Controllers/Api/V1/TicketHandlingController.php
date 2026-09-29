<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketHandlingRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Support\Facades\Gate;

class TicketHandlingController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tickets/{ticket}/handlings",
     *     operationId="storeHandling",
     *     tags={"Helpdesk"},
     *     summary="Menginput penanganan tiket oleh teknisi",
     *     description="Digunakan oleh petugas untuk melaporkan hasil pengerjaan tiket. Status tiket harus 'diproses' dan akan berubah menjadi 'terselesaikan'.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="ticket",
     *         in="path",
     *         required=true,
     *         description="ID dari tiket",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"notes", "status", "started_at", "completed_at"},
     *                 @OA\Property(property="notes", type="string", description="Catatan penanganan dari teknisi"),
     *                 @OA\Property(property="status", type="string", enum={"terselesaikan"}, description="Status penyelesaian"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", description="Waktu mulai pengerjaan (Y-m-d H:i:s)"),
     *                 @OA\Property(property="completed_at", type="string", format="date-time", description="Waktu selesai pengerjaan (Y-m-d H:i:s)"),
     *                 @OA\Property(property="result_photo", type="string", format="binary", description="Foto bukti penyelesaian (opsional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Penanganan berhasil disimpan",
     *     )
     * )
     */
    public function store(StoreTicketHandlingRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('handle', $ticket);

        $ticket = $this->service->addHandling(
            $ticket,
            $request->validated(),
            $request->user(),
            $request->file('result_photo'),
        );

        if ($ticket->status === \App\Enums\TicketStatus::Terselesaikan) {
            $ticket->reporter->notify(new \App\Notifications\TicketStatusUpdatedNotification(
                $ticket,
                'Tiket Anda telah diselesaikan oleh teknisi dan menunggu verifikasi.'
            ));
        }

        return (new TicketResource($this->service->loadDetail($ticket)))
            ->additional(['message' => 'Riwayat penanganan berhasil disimpan.']);
    }
}
