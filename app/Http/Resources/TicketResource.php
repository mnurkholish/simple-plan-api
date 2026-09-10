<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'service' => $this->service->value,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status->value,
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'name' => $this->unit->unit_name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
