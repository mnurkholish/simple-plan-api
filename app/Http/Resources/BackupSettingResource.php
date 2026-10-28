<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'is_active' => $this->is_active,
            'frequency' => $this->frequency,
            'backup_time' => $this->backup_time ? substr($this->backup_time, 0, 5) : null,
            'day_of_week' => $this->day_of_week,
            'day_of_month' => $this->day_of_month,
        ];
    }
}
