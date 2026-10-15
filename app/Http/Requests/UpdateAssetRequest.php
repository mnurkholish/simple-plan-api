<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '_method' => ['sometimes', 'string'],
            'asset_classification_id' => ['sometimes', 'required', 'exists:asset_classifications,id'],
            'asset_location_id' => ['sometimes', 'required', 'exists:asset_locations,id'],
            'asset_item_id' => ['sometimes', 'required', 'exists:asset_items,id'],
            'serial_number' => ['sometimes', 'nullable', 'string'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model_specification' => ['sometimes', 'nullable', 'string', 'max:100'],
            'location_detail' => ['sometimes', 'nullable', 'string', 'max:100'],
            'purchase_year' => ['sometimes', 'required', 'integer', 'min:1900', 'max:2100'],
            'status' => ['sometimes', 'required', Rule::enum(AssetStatus::class)],
            'condition' => ['sometimes', 'required', Rule::enum(AssetCondition::class)],
            'warranty_until' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'photo' => ['sometimes', 'nullable', 'file', 'mimes:jpg,png', 'max:5120'],
            'calibration_document' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
