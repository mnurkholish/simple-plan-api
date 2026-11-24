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
            'asset_type' => $this->assetCode?->classification?->name === 'Alat IT' ? 'TIK' : 'Sarpras',
            'detail_item' => $this->detail_item,
            'serial_number' => $this->serial_number,
            'brand' => $this->brand,
            'model_specification' => $this->model_specification,
            'location_detail' => $this->location_detail,
            'purchase_year' => $this->purchase_year,
            'status' => $this->status?->value,
            'condition' => $this->condition?->value,
            'warranty_until' => $this->warranty_until?->format('Y-m-d'),
            'notes' => $this->notes,
            'calibration_document' => $this->formatDocumentMetadata(
                $this->calibration_object_key,
                $this->calibration_original_name,
                $this->calibration_mime_type,
                $this->calibration_size
            ),
            'invoice_document' => $this->formatDocumentMetadata(
                $this->invoice_object_key,
                $this->invoice_original_name,
                $this->invoice_mime_type,
                $this->invoice_size
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'histories' => AssetHistoryResource::collection($this->whenLoaded('histories')),
            'maintenance_histories' => AssetMaintenanceHistoryResource::collection($this->whenLoaded('maintenanceLogs')),
        ];
    }

    /**
     * Format uploaded asset document metadata consistently.
     *
     * @return array{original_name: string|null, mime_type: string|null, size: int|null}|null
     */
    private function formatDocumentMetadata(
        ?string $objectKey,
        ?string $originalName,
        ?string $mimeType,
        ?int $size
    ): ?array {
        if ($objectKey === null) {
            return null;
        }

        return [
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }
}
