<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class InventoryReportExportRequest extends InventoryReportRequest
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
