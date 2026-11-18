<?php

namespace App\Http\Requests;

use App\Services\MaintenanceReportService;
use Illuminate\Foundation\Http\FormRequest;

class MaintenanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(MaintenanceReportService::ALLOWED_ROLES) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $periodeAkhirRules = ['nullable', 'date', 'date_format:Y-m-d'];

        if ($this->filled('periode_awal')) {
            $periodeAkhirRules[] = 'after_or_equal:periode_awal';
        }

        return [
            'periode_awal' => ['nullable', 'date', 'date_format:Y-m-d'],
            'periode_akhir' => $periodeAkhirRules,
            'klasifikasi' => ['nullable', 'integer', 'exists:asset_classifications,id'],
        ];
    }
}
