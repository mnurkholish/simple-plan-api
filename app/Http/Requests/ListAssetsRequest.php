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
            $prefix.'asset_type' => ['sometimes', 'nullable', Rule::in(['TIK', 'Sarpras'])],
            $prefix.'asset_item_id' => ['sometimes', 'nullable', 'integer', 'exists:asset_items,id'],
            $prefix.'status' => ['sometimes', 'nullable', Rule::enum(AssetStatus::class)],
            $prefix.'condition' => ['sometimes', 'nullable', Rule::enum(AssetCondition::class)],
            $prefix.'purchase_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            $prefix.'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            $prefix.'moved_last_3_months' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function filterAttributes(string $prefix = ''): array
    {
        return [
            $prefix.'search' => 'kata kunci pencarian',
            $prefix.'location_id' => 'lokasi aset',
            $prefix.'asset_location_id' => 'lokasi aset',
            $prefix.'classification_id' => 'klasifikasi aset',
            $prefix.'asset_classification_id' => 'klasifikasi aset',
            $prefix.'asset_type' => 'tipe aset',
            $prefix.'asset_item_id' => 'nama barang',
            $prefix.'status' => 'status aset',
            $prefix.'condition' => 'kondisi aset',
            $prefix.'purchase_year' => 'tahun pembelian',
            $prefix.'year' => 'tahun pembelian',
            $prefix.'moved_last_3_months' => 'filter perpindahan 3 bulan terakhir',
        ];
    }

    /** @return array<string, string> */
    public static function filterMessages(string $prefix = ''): array
    {
        return [
            $prefix.'search.string' => 'Kata kunci pencarian harus berupa teks.',
            $prefix.'search.max' => 'Kata kunci pencarian tidak boleh lebih dari :max karakter.',
            $prefix.'location_id.integer' => 'Lokasi aset tidak valid.',
            $prefix.'location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
            $prefix.'asset_location_id.integer' => 'Lokasi aset tidak valid.',
            $prefix.'asset_location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
            $prefix.'classification_id.integer' => 'Klasifikasi aset tidak valid.',
            $prefix.'classification_id.exists' => 'Klasifikasi aset yang dipilih tidak ditemukan.',
            $prefix.'asset_classification_id.integer' => 'Klasifikasi aset tidak valid.',
            $prefix.'asset_classification_id.exists' => 'Klasifikasi aset yang dipilih tidak ditemukan.',
            $prefix.'asset_type.in' => 'Tipe aset yang dipilih tidak valid.',
            $prefix.'asset_item_id.integer' => 'Nama barang tidak valid.',
            $prefix.'asset_item_id.exists' => 'Nama barang yang dipilih tidak ditemukan.',
            $prefix.'status.enum' => 'Status aset yang dipilih tidak valid.',
            $prefix.'condition.enum' => 'Kondisi aset yang dipilih tidak valid.',
            $prefix.'purchase_year.integer' => 'Tahun pembelian harus berupa angka tahun yang valid.',
            $prefix.'purchase_year.min' => 'Tahun pembelian tidak boleh kurang dari :min.',
            $prefix.'purchase_year.max' => 'Tahun pembelian tidak boleh lebih dari :max.',
            $prefix.'year.integer' => 'Tahun pembelian harus berupa angka tahun yang valid.',
            $prefix.'year.min' => 'Tahun pembelian tidak boleh kurang dari :min.',
            $prefix.'year.max' => 'Tahun pembelian tidak boleh lebih dari :max.',
            $prefix.'moved_last_3_months.boolean' => 'Filter perpindahan 3 bulan terakhir harus bernilai benar atau salah.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return self::filterAttributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::filterMessages();
    }
}
