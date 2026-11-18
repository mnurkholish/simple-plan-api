<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class HelpdeskReportExportRequest extends HelpdeskReportRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['format' => ['required', Rule::in(['pdf', 'xlsx'])]] + parent::rules();
    }
}
