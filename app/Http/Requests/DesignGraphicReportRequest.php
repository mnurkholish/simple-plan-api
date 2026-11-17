<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DesignGraphicReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'management']) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periode_awal' => ['required', 'date_format:Y-m-d'],
            'periode_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:periode_awal'],
        ];
    }
}
