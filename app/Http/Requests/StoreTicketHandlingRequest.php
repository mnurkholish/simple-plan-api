<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketHandlingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
        return [
            'notes' => ['required', 'string'],
            'status' => [
                'required',
                Rule::enum(TicketStatus::class)
                    ->only([TicketStatus::Diproses, TicketStatus::Selesai]),
            ],
            'started_at' => ['nullable', 'date'],
            'completed_at' => [
                'nullable',
                Rule::requiredIf($this->input('status') === TicketStatus::Selesai->value),
                'date',
                'after_or_equal:started_at',
            ],
            'result_photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
