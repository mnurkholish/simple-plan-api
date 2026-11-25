<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class MaintenanceReportExportRequest extends MaintenanceReportRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'format' => 'format ekspor',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'format.required' => 'Format ekspor wajib dipilih.',
            'format.in' => 'Format ekspor harus berupa pdf atau xlsx.',
        ];
    }
}
