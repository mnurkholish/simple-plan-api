<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
use App\Services\MaintenanceReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(MaintenanceReportService::ALLOWED_ROLES) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $startKey = $this->filled('start_date')
            ? 'start_date'
            : ($this->filled('periode_awal') ? 'periode_awal' : null);

        $endDateRules = ['nullable', 'date_format:Y-m-d'];
        $periodeAkhirRules = ['nullable', 'date_format:Y-m-d'];

        if ($startKey !== null) {
            $endDateRules[] = 'after_or_equal:'.$startKey;
            $periodeAkhirRules[] = 'after_or_equal:'.$startKey;
        }

        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => $endDateRules,
            'periode_awal' => ['nullable', 'date_format:Y-m-d'],
            'periode_akhir' => $periodeAkhirRules,
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
            'classification_id' => ['nullable', 'integer', 'exists:asset_classifications,id'],
            'klasifikasi' => ['nullable', 'integer', 'exists:asset_classifications,id'],
            'location_id' => ['nullable', 'integer', 'exists:asset_locations,id'],
            'lokasi_ruangan' => ['nullable', 'integer', 'exists:asset_locations,id'],
        ];
    }
}
