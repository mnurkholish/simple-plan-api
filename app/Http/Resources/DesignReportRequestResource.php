<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignReportRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'unit' => [
                'id' => $this->unit->id,
                'name' => $this->unit->unit_name,
            ],
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
