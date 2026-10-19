<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_uid' => $this->asset_uid,
            'asset_code' => $this->whenLoaded('assetCode', fn () => $this->assetCode === null ? null : [
                'id' => $this->assetCode->id,
                'full_code' => $this->assetCode->full_code,
                'sequence_number' => $this->assetCode->sequence_number,
                'classification' => $this->assetCode->relationLoaded('classification') ? $this->assetCode->classification : null,
                'location' => $this->assetCode->relationLoaded('location') ? $this->assetCode->location : null,
                'item' => $this->assetCode->relationLoaded('item') ? $this->assetCode->item : null,
            ]),
            'serial_number' => $this->serial_number,
            'brand' => $this->brand,
            'model_specification' => $this->model_specification,
            'location_detail' => $this->location_detail,
            'purchase_year' => $this->purchase_year,
            'status' => $this->status->value,
            'condition' => $this->condition->value,
            'warranty_until' => $this->warranty_until?->format('Y-m-d'),
            'notes' => $this->notes,
            'photo' => $this->photo_object_key === null ? null : [
                'original_name' => $this->photo_original_name,
                'mime_type' => $this->photo_mime_type,
                'size' => $this->photo_size,
            ],
            'calibration_document' => $this->calibration_object_key === null ? null : [
                'original_name' => $this->calibration_original_name,
                'mime_type' => $this->calibration_mime_type,
                'size' => $this->calibration_size,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
