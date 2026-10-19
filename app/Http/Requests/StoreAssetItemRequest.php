<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_classification_id' => ['required', 'integer', 'exists:asset_classifications,id'],
            'name' => [
                'required',
                'string',
                Rule::unique('asset_items')->where(fn ($query) => $query->where(
                    'asset_classification_id',
                    $this->input('asset_classification_id')
                )),
            ],
        ];
    }
}
