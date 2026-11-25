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
            'filters' => [$selected ? 'missing' : 'present', 'array:search,location_id,asset_location_id,classification_id,asset_classification_id,asset_type,asset_item_id,status,condition,purchase_year,year,moved_last_3_months,page,per_page'],
            ...ListAssetsRequest::filterRules('filters.'),
            'filters.page' => ['sometimes', 'integer', 'min:1'],
            'filters.per_page' => ['sometimes', 'integer', 'min:1'],
            'width_mm' => ['required', 'numeric', 'min:50', 'max:150'],
            'height_mm' => ['required', 'numeric', 'min:25', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'selection_mode' => 'mode pemilihan aset',
            'asset_ids' => 'daftar aset',
            'asset_ids.*' => 'aset',
            'filters' => 'filter aset',
            ...ListAssetsRequest::filterAttributes('filters.'),
            'filters.page' => 'halaman',
            'filters.per_page' => 'jumlah data per halaman',
            'width_mm' => 'lebar label (mm)',
            'height_mm' => 'tinggi label (mm)',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'selection_mode.required' => 'Mode pemilihan aset wajib diisi.',
            'selection_mode.in' => 'Mode pemilihan aset tidak valid.',
            'asset_ids.required' => 'Daftar aset yang akan dicetak wajib dipilih.',
            'asset_ids.missing' => 'Daftar aset tidak boleh dikirim saat memilih semua aset sesuai filter.',
            'asset_ids.array' => 'Daftar aset harus berupa daftar pilihan yang valid.',
            'asset_ids.min' => 'Minimal harus memilih :min aset untuk dicetak.',
            'asset_ids.*.required' => 'ID aset wajib diisi.',
            'asset_ids.*.integer' => 'ID aset harus berupa angka yang valid.',
            'asset_ids.*.distinct' => 'ID aset tidak boleh duplikat.',
            'asset_ids.*.exists' => 'Aset yang dipilih tidak ditemukan atau tidak valid.',
            'filters.present' => 'Filter aset wajib disertakan saat memilih semua aset sesuai filter.',
            'filters.missing' => 'Filter aset tidak boleh dikirim saat memilih aset secara manual.',
            'filters.array' => 'Format filter aset tidak valid.',
            ...ListAssetsRequest::filterMessages('filters.'),
            'filters.page.integer' => 'Nomor halaman harus berupa angka.',
            'filters.page.min' => 'Nomor halaman minimal :min.',
            'filters.per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'filters.per_page.min' => 'Jumlah data per halaman minimal :min.',
            'width_mm.required' => 'Lebar label (mm) wajib diisi.',
            'width_mm.numeric' => 'Lebar label (mm) harus berupa angka.',
            'width_mm.min' => 'Lebar label tidak boleh kurang dari :min mm.',
            'width_mm.max' => 'Lebar label tidak boleh lebih dari :max mm.',
            'height_mm.required' => 'Tinggi label (mm) wajib diisi.',
            'height_mm.numeric' => 'Tinggi label (mm) harus berupa angka.',
            'height_mm.min' => 'Tinggi label tidak boleh kurang dari :min mm.',
            'height_mm.max' => 'Tinggi label tidak boleh lebih dari :max mm.',
        ];
    }
}
