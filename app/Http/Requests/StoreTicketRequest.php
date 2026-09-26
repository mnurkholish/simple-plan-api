<?php

namespace App\Http\Requests;

use App\Enums\TicketService;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'service' => ['required', Rule::enum(TicketService::class)],
            'asset_id' => [
                'nullable',
                'integer',
                Rule::exists(Asset::class, 'id')->whereNull('deleted_at'),
            ],
            'description' => ['required', 'string'],
            'initial_evidence' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
