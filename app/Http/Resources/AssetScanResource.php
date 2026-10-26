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
            'code' => $code?->full_code,
            'name' => trim(implode(' ', array_filter([$code?->item?->name, $this->detail_item], fn ($value) => filled($value)))),
            'brand' => trim(implode(' ', array_filter([$this->brand, $this->model_specification], fn ($value) => filled($value)))),
            'location' => trim(implode(' ', array_filter([$code?->location?->name, $this->location_detail], fn ($value) => filled($value)))),
        ];
    }
}
