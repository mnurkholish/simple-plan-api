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
}
