<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketHandlingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            && $this->user()?->can('handle', $ticket) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $timestamp = ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/i'];
        $ticket = $this->route('ticket');
        $allowedStatuses = match ($ticket instanceof Ticket ? $ticket->status : null) {
            TicketStatus::Diproses => [
                TicketStatus::Diproses->value,
                TicketStatus::Terselesaikan->value,
            ],
            default => [
                TicketStatus::Diproses->value,
                TicketStatus::Terselesaikan->value,
            ],
        };

        return [
            'notes' => ['required', 'string'],
            'status' => ['required', Rule::in($allowedStatuses)],
            'started_at' => $timestamp,
            'completed_at' => [
                ...$timestamp,
                'after_or_equal:started_at',
            ],
            'result_photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
