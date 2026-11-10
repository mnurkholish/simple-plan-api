<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesignRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'design_type' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'needed_at' => ['required', 'date', 'after_or_equal:today'],
            'reference_file' => ['nullable', 'file', 'max:5120'],
        ];
    }
}
