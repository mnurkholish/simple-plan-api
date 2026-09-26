<?php

namespace App\Http\Requests;

use App\Enums\TicketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassificationOptionsRequest extends FormRequest
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
            'service' => ['required', Rule::enum(TicketService::class)],
        ];
    }
}
