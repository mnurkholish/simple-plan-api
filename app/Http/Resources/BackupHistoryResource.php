<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'performed_by' => $this->whenLoaded('performedBy', fn (): ?array => $this->performedBy === null ? null : [
                'id' => $this->performedBy->id,
                'name' => $this->performedBy->name,
            ]),
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at,
        ];
    }
}
