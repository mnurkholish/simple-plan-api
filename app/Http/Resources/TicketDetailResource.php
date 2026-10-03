<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketDetailResource extends TicketResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($data['initial_evidence'] !== null) {
            $data['initial_evidence']['url'] = Storage::temporaryUrl(
                $this->initial_evidence_object_key,
                now()->addMinutes((int) config('filesystems.temporary_url_expiration_minutes', 5)),
            );
        }

        if ($this->resource->relationLoaded('handlings')) {
            $data['handlings'] = TicketHandlingDetailResource::collection($this->handlings);
        }

        return $data;
    }
}
