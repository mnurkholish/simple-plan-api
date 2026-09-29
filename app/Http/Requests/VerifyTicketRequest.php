<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class VerifyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            && $this->user()?->can('verifyResolution', $ticket) === true;
    }

    public function rules(): array
    {
        return [
            'is_approved' => ['required', 'boolean'],
            'keterangan_kendala' => ['required_if:is_approved,false', 'nullable', 'string', 'min:5'],
        ];
    }
}
