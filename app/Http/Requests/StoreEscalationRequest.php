<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class StoreEscalationRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'in:Manajemen,Vendor,Tim Terkait'],
            'notes' => ['required', 'string', 'min:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'target.required' => 'Tujuan eskalasi (target) wajib dipilih.',
            'target.in' => 'Pilihan tujuan eskalasi tidak valid.',
            'notes.required' => 'Catatan eskalasi (notes) wajib diisi.',
            'notes.min' => 'Catatan eskalasi minimal 5 karakter.',
        ];
    }
}
