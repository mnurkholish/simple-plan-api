<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('asset_classification');

        return [
            'code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('asset_classifications', 'code')->ignore($id),
            ],
            'name' => ['required', 'string'],
        ];
    }
}
