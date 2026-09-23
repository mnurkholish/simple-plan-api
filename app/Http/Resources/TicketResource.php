<?php

namespace App\Http\Resources;

use App\Enums\TicketService;
use App\Enums\TicketStatus;
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
            'category' => $this->categoryLabel(),
            'tik_detail' => $this->whenLoaded(
                'tikDetail',
                fn (): ?array => $this->tikDetail === null ? null : [
                    'quality_category' => $this->tikDetail->qualityCategory === null ? null : [
                        'id' => $this->tikDetail->qualityCategory->id,
                        'name' => $this->tikDetail->qualityCategory->name,
                    ],
                    'it_tag' => $this->tikDetail->itTag === null ? null : [
                        'id' => $this->tikDetail->itTag->id,
                        'name' => $this->tikDetail->itTag->name,
                    ],
                    'custom_it_tag_text' => $this->tikDetail->custom_it_tag_text,
                ],
            ),
            'sarpras_detail' => $this->whenLoaded(
                'sarprasDetail',
                fn (): ?array => $this->sarprasDetail === null ? null : [
                    'sarpras_category' => $this->sarprasDetail->sarprasCategory === null ? null : [
                        'id' => $this->sarprasDetail->sarprasCategory->id,
                        'name' => $this->sarprasDetail->sarprasCategory->name,
                    ],
                ],
            ),
            'description' => $this->description,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejectionReason(),
            'priority' => $this->priority?->value,
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'name' => $this->unit->unit_name,
            ]),
            'assigned_officer' => $this->whenLoaded(
                'assignedOfficer',
                fn (): ?array => $this->assignedOfficer === null ? null : [
                    'id' => $this->assignedOfficer->id,
                    'name' => $this->assignedOfficer->name,
                ],
            ),
            'initial_evidence' => $this->initial_evidence_object_key === null ? null : [
                'object_key' => $this->initial_evidence_object_key,
                'original_name' => $this->initial_evidence_original_name,
                'mime_type' => $this->initial_evidence_mime_type,
                'size' => $this->initial_evidence_size,
            ],
            'completed_at' => $this->completed_at,
            'handlings' => TicketHandlingResource::collection($this->whenLoaded('handlings')),
            'created_at' => $this->created_at,
        ];
    }

    private function categoryLabel(): ?string
    {
        if ($this->service === TicketService::Tik && $this->resource->relationLoaded('tikDetail')) {
            return $this->tikDetail?->custom_it_tag_text
                ?: $this->tikDetail?->itTag?->name;
        }

        if ($this->service === TicketService::Sarpras && $this->resource->relationLoaded('sarprasDetail')) {
            return $this->sarprasDetail?->sarprasCategory?->name;
        }

        return null;
    }

    private function rejectionReason(): ?string
    {
        if (! $this->resource->relationLoaded('statusHistories')) {
            return null;
        }

        return $this->statusHistories
            ->firstWhere('to_status', TicketStatus::Ditolak->value)
            ?->notes;
    }
}
