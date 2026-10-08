<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\AssetClassification;
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
            'purchase_year' => ['required', 'date_format:Y'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'photo_path' => ['nullable', 'image', 'max:2048'],
            'calibration_document_path' => ['nullable', 'mimes:pdf', 'max:5120'],
            'specification_model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:50'],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $classId = $this->input('asset_classification_id');
            if ($classId) {
                $classification = AssetClassification::find($classId);
                if ($classification && strtolower(trim($classification->name)) === 'alat medis') {
                    if (!$this->hasFile('calibration_document_path')) {
                        $validator->errors()->add('calibration_document_path', 'Dokumen kalibrasi wajib diunggah untuk klasifikasi Alat Medis.');
                    }
                }
            }
        });
    }
}
