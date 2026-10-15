<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('asset_item');

        return [
            'code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('asset_items', 'code')->ignore($id),
            ],
            'name' => ['required', 'string'],
        ];
    }
}
