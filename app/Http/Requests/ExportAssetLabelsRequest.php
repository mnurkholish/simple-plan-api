<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportAssetLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['required', 'integer', 'distinct', Rule::exists('assets', 'id')->whereNull('deleted_at')],
            'width_mm' => ['required', 'numeric', 'min:70', 'max:150'],
            'height_mm' => ['required', 'numeric', 'min:40', 'max:100'],
        ];
    }
}
