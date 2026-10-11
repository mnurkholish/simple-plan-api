<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_classification_id' => ['required', 'integer', 'exists:asset_classifications,id'],
            'asset_location_id' => ['required', 'integer', 'exists:asset_locations,id'],
            'asset_item_id' => ['required', 'integer', 'exists:asset_items,id'],
            'purchase_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'serial_number' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'file', 'mimes:jpg,png', 'max:5120'],
            'calibration_document' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
