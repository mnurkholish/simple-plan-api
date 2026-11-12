<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'design_type' => $this->design_type,
            'description' => $this->description,
            'needed_at' => $this->needed_at,
            'status' => $this->status->value,
            'requester' => $this->whenLoaded('requester', fn (): array => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'name' => $this->unit->unit_name,
            ]),
            'reference_file' => $this->reference_file_object_key === null ? null : [
                'original_name' => $this->reference_file_original_name,
                'mime_type' => $this->reference_file_mime_type,
                'size' => $this->reference_file_size,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
