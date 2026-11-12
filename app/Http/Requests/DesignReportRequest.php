<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DesignReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Tanggal mulai (start_date) wajib diisi.',
            'start_date.date' => 'Format tanggal mulai (start_date) tidak valid.',
            'end_date.required' => 'Tanggal selesai (end_date) wajib diisi.',
            'end_date.date' => 'Format tanggal selesai (end_date) tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai (end_date) harus sama atau setelah tanggal mulai (start_date).',
        ];
    }
}
