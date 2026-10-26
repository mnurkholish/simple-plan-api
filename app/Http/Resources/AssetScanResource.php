<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $code = $this->assetCode;

        return [
            'id' => $this->id,
            'uid' => $this->asset_uid,
            'classification' => $code?->classification?->name === 'Alat IT' ? 'TIK' : 'SARPRAS',
            'asset_code' => $code?->full_code,
            'location' => $code?->location?->name,
            'detail_location' => $this->location_detail,
            'name' => $code?->item?->name,
            'detail_name' => $this->detail_item,
            'status' => $this->status->value,
        ];
    }
}
