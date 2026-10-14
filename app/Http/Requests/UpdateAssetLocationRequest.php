<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('asset_location');

        return [
            'code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('asset_locations', 'code')->ignore($id),
            ],
            'name' => ['required', 'string'],
        ];
    }
}
