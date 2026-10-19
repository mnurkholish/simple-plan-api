<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('moved_last_3_months');

        if ($value === 'true' || $value === 'false') {
            $this->merge(['moved_last_3_months' => $value === 'true']);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::filterRules();
    }

    /** @return array<string, mixed> */
    public static function filterRules(string $prefix = ''): array
    {
        return [
            $prefix.'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            $prefix.'location_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_locations,id'],
            $prefix.'asset_location_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_locations,id'],
            $prefix.'classification_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_classifications,id'],
            $prefix.'asset_classification_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_classifications,id'],
            $prefix.'asset_item_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_items,id'],
            $prefix.'status' => ['sometimes', 'nullable', Rule::enum(AssetStatus::class)],
            $prefix.'condition' => ['sometimes', 'nullable', Rule::enum(AssetCondition::class)],
            $prefix.'purchase_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            $prefix.'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            $prefix.'moved_last_3_months' => ['sometimes', 'boolean'],
        ];
    }
}
