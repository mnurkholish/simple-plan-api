<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_description' => ['required', 'string'],
            'maintenance_result' => ['required', 'string'],
            'post_maintenance_condition' => ['required', Rule::enum(AssetCondition::class)],
            'started_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'completed_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after_or_equal:started_at',
            ],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
