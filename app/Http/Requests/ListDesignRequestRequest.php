<?php

namespace App\Http\Requests;

use App\Enums\DesignRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDesignRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(['mine', 'assigned', 'all'])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(DesignRequestStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
