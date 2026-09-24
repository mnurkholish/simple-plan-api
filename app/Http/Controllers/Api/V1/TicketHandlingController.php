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

    public function store(StoreTicketHandlingRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('handle', $ticket);

        $ticket = $this->service->addHandling(
            $ticket,
            $request->validated(),
            $request->user(),
            $request->file('result_photo'),
        );

        return (new TicketResource($this->service->loadDetail($ticket)))
            ->additional(['message' => 'Riwayat penanganan berhasil disimpan.']);
    }
}
