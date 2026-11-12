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
            'priority' => $this->priority,
            'assigned_at' => $this->assigned_at,
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
            'assigned_officer' => $this->whenLoaded('assignedOfficer', fn (): ?array => $this->assignedOfficer ? [
                'id' => $this->assignedOfficer->id,
                'name' => $this->assignedOfficer->name,
            ] : null),
            'drafts' => $this->whenLoaded('drafts', fn () => $this->drafts->map(fn ($draft): array => [
                'id' => $draft->id,
                'design_request_id' => $draft->design_request_id,
                'file_path' => $draft->file_path ?? $draft->file_object_key,
                'file_name' => $draft->file_name ?? $draft->file_original_name,
                'file_size' => $draft->file_size,
                'mime_type' => $draft->mime_type ?? $draft->file_mime_type,
                'notes' => $draft->notes,
                'uploaded_by_id' => $draft->uploaded_by_id,
                'created_at' => $draft->created_at,
                'updated_at' => $draft->updated_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
