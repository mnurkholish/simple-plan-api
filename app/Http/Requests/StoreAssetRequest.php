<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_classification_id' => ['required', 'integer', 'exists:asset_classifications,id'],
            'asset_location_id' => ['required', 'integer', 'exists:asset_locations,id'],
            'asset_item_id' => ['required', 'integer', 'exists:asset_items,id'],
            'purchase_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'serial_number' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model_specification' => ['nullable', 'string', 'max:100'],
            'detail_item' => ['nullable', 'string', 'max:255'],
            'location_detail' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'calibration_document' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'asset_classification_id' => 'klasifikasi aset',
            'asset_location_id' => 'lokasi aset',
            'asset_item_id' => 'nama barang',
            'purchase_year' => 'tahun pembelian',
            'serial_number' => 'nomor seri',
            'brand' => 'merek',
            'model_specification' => 'model / spesifikasi',
            'detail_item' => 'detail barang',
            'location_detail' => 'detail lokasi',
            'status' => 'status aset',
            'condition' => 'kondisi aset',
            'warranty_until' => 'masa berlaku garansi',
            'notes' => 'catatan',
            'calibration_document' => 'dokumen kalibrasi',
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
            'asset_location_id.required' => 'Lokasi aset wajib dipilih.',
            'asset_location_id.integer' => 'Lokasi aset tidak valid.',
            'asset_location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
            'asset_item_id.required' => 'Nama barang wajib dipilih.',
            'asset_item_id.integer' => 'Nama barang tidak valid.',
            'asset_item_id.exists' => 'Nama barang yang dipilih tidak ditemukan.',
            'purchase_year.required' => 'Tahun pembelian wajib diisi.',
            'purchase_year.integer' => 'Tahun pembelian harus berupa angka tahun yang valid.',
            'purchase_year.min' => 'Tahun pembelian tidak boleh kurang dari :min.',
            'purchase_year.max' => 'Tahun pembelian tidak boleh lebih dari :max.',
            'serial_number.string' => 'Nomor seri harus berupa teks.',
            'brand.string' => 'Merek harus berupa teks.',
            'brand.max' => 'Merek tidak boleh lebih dari :max karakter.',
            'model_specification.string' => 'Model / spesifikasi harus berupa teks.',
            'model_specification.max' => 'Model / spesifikasi tidak boleh lebih dari :max karakter.',
            'detail_item.string' => 'Detail barang harus berupa teks.',
            'detail_item.max' => 'Detail barang tidak boleh lebih dari :max karakter.',
            'location_detail.string' => 'Detail lokasi harus berupa teks.',
            'location_detail.max' => 'Detail lokasi tidak boleh lebih dari :max karakter.',
            'status.required' => 'Status aset wajib dipilih.',
            'status.enum' => 'Status aset yang dipilih tidak valid.',
            'condition.required' => 'Kondisi aset wajib dipilih.',
            'condition.enum' => 'Kondisi aset yang dipilih tidak valid.',
            'warranty_until.date' => 'Masa berlaku garansi harus berupa tanggal yang valid.',
            'notes.string' => 'Catatan harus berupa teks.',
            'calibration_document.file' => 'Dokumen kalibrasi harus berupa berkas yang valid.',
            'calibration_document.mimes' => 'Dokumen kalibrasi harus berformat PDF.',
            'calibration_document.max' => 'Ukuran dokumen kalibrasi tidak boleh lebih dari 5 MB.',
            'calibration_document.uploaded' => 'Dokumen kalibrasi gagal diunggah.',
        ];
    }
}
