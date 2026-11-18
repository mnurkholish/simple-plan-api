<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(InventoryReportService::ALLOWED_ROLES) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'classification_id' => ['nullable', 'integer', 'exists:asset_classifications,id'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'location_id' => ['nullable', 'integer', 'exists:asset_locations,id'],
        ];
    }
}
