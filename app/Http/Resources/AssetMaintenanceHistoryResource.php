<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AssetCondition;
use App\Enums\MaintenancePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssetMaintenanceHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $period = $this->maintenance?->period;
        $condition = $this->post_maintenance_condition;

        return [
            'id' => $this->id,
            'asset_maintenance_id' => $this->asset_maintenance_id,
            'period' => $period instanceof MaintenancePeriod ? $period->value : $period,
            'executed_at' => $this->completed_at ?? $this->created_at,
            'executor_name' => $this->maintenance?->assignedOfficer?->name,
            'work_description' => $this->work_description,
            'maintenance_result' => $this->maintenance_result,
            'post_maintenance_condition' => $condition instanceof AssetCondition ? $condition->value : $condition,
            'photo_url' => $this->proof_photo_object_key === null
                ? null
                : Storage::disk('public')->url($this->proof_photo_object_key),
        ];
    }
}
