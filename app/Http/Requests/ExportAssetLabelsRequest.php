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

    protected function prepareForValidation(): void
    {
        $filters = $this->input('filters');

        if (is_array($filters) && in_array($filters['moved_last_3_months'] ?? null, ['true', 'false'], true)) {
            $filters['moved_last_3_months'] = $filters['moved_last_3_months'] === 'true';
            $this->merge(['filters' => $filters]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $selected = $this->input('selection_mode', 'selected') !== 'all_filtered';

        return [
            'selection_mode' => ['sometimes', 'required', Rule::in(['selected', 'all_filtered'])],
            'asset_ids' => [$selected ? 'required' : 'missing', 'array', 'min:1'],
            'asset_ids.*' => ['required', 'integer', 'distinct', Rule::exists('assets', 'id')->whereNull('deleted_at')->whereNotNull('asset_code_id')],
            'filters' => [$selected ? 'missing' : 'present', 'array:search,location_id,asset_location_id,classification_id,asset_classification_id,asset_item_id,status,condition,purchase_year,year,moved_last_3_months,page,per_page'],
            ...ListAssetsRequest::filterRules('filters.'),
            'filters.page' => ['sometimes', 'integer', 'min:1'],
            'filters.per_page' => ['sometimes', 'integer', 'min:1'],
            'width_mm' => ['required', 'numeric', 'min:50', 'max:150'],
            'height_mm' => ['required', 'numeric', 'min:25', 'max:100'],
        ];
    }
}
