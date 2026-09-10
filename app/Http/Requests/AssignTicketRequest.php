<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
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
        return [
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'officer_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where('status', 'active'),
            ],
        ];
    }
}
