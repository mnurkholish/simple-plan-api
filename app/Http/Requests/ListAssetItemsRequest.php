<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAssetItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_locations,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'location_id' => 'lokasi aset',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'location_id.integer' => 'Lokasi aset tidak valid.',
            'location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
        ];
    }
}
