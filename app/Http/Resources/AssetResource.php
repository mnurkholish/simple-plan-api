<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_uuid' => $this->item_uuid,
            'inventory_number' => $this->inventory_number,
            'sequence_number' => $this->sequence_number,
            'specification_model' => $this->specification_model,
            'serial_number' => $this->serial_number,
            'purchase_year' => $this->purchase_year,
            'status' => $this->status,
            'condition' => $this->condition,
            'warranty_until' => $this->warranty_until,
            'notes' => $this->notes,
            'photo_path' => $this->photo_path,
            'calibration_document_path' => $this->calibration_document_path,
            'classification' => $this->whenLoaded('classification'),
            'location' => $this->whenLoaded('location'),
            'item' => $this->whenLoaded('item'),
            'mutations' => $this->whenLoaded('mutations'),
        ];
    }
}
