<?php

namespace App\Http\Requests;

use App\Services\MaintenanceReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class MaintenanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(MaintenanceReportService::ALLOWED_ROLES) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Anda tidak memiliki izin untuk mengakses laporan pemeliharaan.');
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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'periode_awal' => 'periode awal',
            'periode_akhir' => 'periode akhir',
            'klasifikasi' => 'klasifikasi aset',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'periode_awal.date' => 'Periode awal harus berupa tanggal yang valid.',
            'periode_awal.date_format' => 'Format periode awal harus YYYY-MM-DD.',
            'periode_akhir.date' => 'Periode akhir harus berupa tanggal yang valid.',
            'periode_akhir.date_format' => 'Format periode akhir harus YYYY-MM-DD.',
            'periode_akhir.after_or_equal' => 'Periode akhir tidak boleh lebih awal dari periode awal.',
            'klasifikasi.integer' => 'Klasifikasi aset tidak valid.',
            'klasifikasi.exists' => 'Klasifikasi aset yang dipilih tidak ditemukan.',
        ];
    }
}
