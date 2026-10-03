<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketHandlingResource extends JsonResource
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
            'notes' => $this->notes,
            'status' => $this->whenLoaded('ticket', fn (): string => $this->ticket->status->value),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'handled_by' => $this->whenLoaded('handledBy', fn (): array => [
                'id' => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ]),
            'result_photo' => $this->result_photo_object_key === null ? null : [
                'original_name' => $this->result_photo_original_name,
                'mime_type' => $this->result_photo_mime_type,
                'size' => $this->result_photo_size,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
