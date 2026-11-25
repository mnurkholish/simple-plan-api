<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchAssetsRequest extends FormRequest
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
            'location_id' => ['required', 'integer', 'exists:asset_locations,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'location_id' => 'lokasi aset',
            'search' => 'kata kunci pencarian',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Lokasi aset wajib dipilih.',
            'location_id.integer' => 'Lokasi aset tidak valid.',
            'location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
            'search.string' => 'Kata kunci pencarian harus berupa teks.',
            'search.max' => 'Kata kunci pencarian tidak boleh lebih dari :max karakter.',
        ];
    }
}
