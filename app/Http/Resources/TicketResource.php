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
            'initial_evidence' => $this->initial_evidence_object_key === null ? null : [
                'object_key' => $this->initial_evidence_object_key,
                'original_name' => $this->initial_evidence_original_name,
                'mime_type' => $this->initial_evidence_mime_type,
                'size' => $this->initial_evidence_size,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
