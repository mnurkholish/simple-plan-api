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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'asset_classification_id' => 'klasifikasi aset',
            'name' => 'nama barang',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_classification_id.required' => 'Klasifikasi aset wajib dipilih.',
            'asset_classification_id.integer' => 'Klasifikasi aset tidak valid.',
            'asset_classification_id.exists' => 'Klasifikasi aset yang dipilih tidak ditemukan.',
            'name.required' => 'Nama barang wajib diisi.',
            'name.string' => 'Nama barang harus berupa teks.',
            'name.unique' => 'Nama barang sudah terdaftar pada klasifikasi aset ini.',
        ];
    }
}
