<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Services\InventoryReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(InventoryReportService::ALLOWED_ROLES) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Anda tidak memiliki izin untuk mengakses laporan inventaris.');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'classification_id' => ['nullable', 'integer', 'exists:asset_classifications,id'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'location_id' => ['nullable', 'integer', 'exists:asset_locations,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'classification_id' => 'klasifikasi aset',
            'status' => 'status aset',
            'location_id' => 'lokasi aset',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'classification_id.integer' => 'Klasifikasi aset tidak valid.',
            'classification_id.exists' => 'Klasifikasi aset yang dipilih tidak ditemukan.',
            'status.enum' => 'Status aset yang dipilih tidak valid.',
            'location_id.integer' => 'Lokasi aset tidak valid.',
            'location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
        ];
    }
}
