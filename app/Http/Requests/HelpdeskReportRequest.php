<?php

namespace App\Http\Requests;

use App\Enums\TicketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HelpdeskReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'management', 'koordinator-sarpras']) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periode_awal' => ['required', 'date_format:Y-m-d'],
            'periode_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:periode_awal'],
            'jenis_layanan' => ['nullable', Rule::in(['semua', ...array_map(
                fn (TicketService $service): string => $service->value,
                TicketService::cases(),
            )])],
        ];
    }
}
