<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketHandlingRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;

class TicketHandlingController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function store(StoreTicketHandlingRequest $request, Ticket $ticket): TicketResource
    {
        $ticket = $this->service->addHandling(
            $ticket,
            $request->validated(),
            $request->user(),
            $request->file('result_photo'),
        );

        return (new TicketResource($ticket->load([
            'assignedOfficer:id,name',
            'handlings.handledBy:id,name',
            'handlings.ticket:id,status',
            'reporter:id,name',
            'sarprasDetail.sarprasCategory:id,name',
            'statusHistories:id,ticket_id,to_status,notes,created_at',
            'tikDetail.itTag:id,name',
            'tikDetail.qualityCategory:id,name',
            'unit:id,unit_name',
        ])))->additional(['message' => 'Riwayat penanganan berhasil disimpan.']);
    }
}
