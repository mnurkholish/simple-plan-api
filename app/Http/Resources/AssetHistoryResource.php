<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetHistoryResource extends JsonResource
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
            'event_type' => $this->event_type,
            'old_values' => is_array($this->old_values) ? $this->old_values : ($this->old_values ? json_decode((string) $this->old_values, true) : null),
            'new_values' => is_array($this->new_values) ? $this->new_values : ($this->new_values ? json_decode((string) $this->new_values, true) : null),
            'is_location_change' => (bool) $this->is_location_change,
            'changed_by_name' => $this->changer?->name ?? $this->changedBy?->name,
            'created_at' => $this->created_at,
        ];
    }
}
