<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            && $this->user()?->can('assign', $ticket) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $allowedRoles = $ticket instanceof Ticket && $ticket->service === TicketService::Sarpras
            ? ['koordinator-sarpras', 'petugas-sarpras']
            : ['super-admin', 'petugas-tik'];
        $priorityRules = match ($ticket instanceof Ticket ? $ticket->status : null) {
            TicketStatus::Diklasifikasi => ['required', Rule::enum(TicketPriority::class)],
            TicketStatus::Diproses => ['prohibited'],
            default => ['sometimes', Rule::enum(TicketPriority::class)],
        };

        return [
            'assigned_officer_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists(User::class, 'id')
                    ->where('status', 'active')
                    ->where('status_user', 'Aktif'),
                function (string $attribute, mixed $value, \Closure $fail) use ($allowedRoles): void {
                    $officer = User::query()->find($value);

                    if ($officer !== null && ! $officer->hasAnyRole($allowedRoles)) {
                        $fail('Petugas yang dipilih harus memiliki salah satu role: '.implode(', ', $allowedRoles).'.');
                    }
                },
            ],
            'priority' => $priorityRules,
        ];
    }
}
