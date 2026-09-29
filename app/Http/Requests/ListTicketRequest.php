<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dateToRules = ['nullable', 'date_format:Y-m-d'];

        if ($this->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return [
            'service' => ['nullable', Rule::enum(TicketService::class)],
            'status' => ['nullable', Rule::in([
                TicketStatus::Baru->value,
                TicketStatus::Diklasifikasi->value,
                TicketStatus::Diproses->value,
                TicketStatus::Terselesaikan->value,
                TicketStatus::Ditutup->value,
                TicketStatus::Ditolak->value,
            ])],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'status_sla' => ['nullable', 'string', Rule::in(['melewati_batas', 'mendekati_batas', 'tepat_waktu'])],
        ];
    }
}
