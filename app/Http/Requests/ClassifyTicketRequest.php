<?php

namespace App\Http\Requests;

use App\Enums\TicketService;
use App\Models\ItTag;
use App\Models\QualityCategory;
use App\Models\SarprasCategory;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassifyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            && $this->user()?->can('classify', $ticket) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $rules = [];

        if (! $ticket instanceof Ticket) {
            return $rules;
        }

        if ($ticket->service === TicketService::Tik) {
            $isOtherTag = ItTag::query()
                ->whereKey($this->input('it_tag_id'))
                ->where('is_active', true)
                ->where('name', 'Lain-lain')
                ->exists();

            return [
                ...$rules,
                'quality_category_id' => [
                    'required',
                    'integer',
                    Rule::exists(QualityCategory::class, 'id')->where('is_active', true),
                ],
                'it_tag_id' => [
                    'required',
                    'integer',
                    Rule::exists(ItTag::class, 'id')->where('is_active', true),
                ],
                'custom_it_tag_text' => [
                    Rule::requiredIf($isOtherTag),
                    Rule::prohibitedIf(! $isOtherTag),
                    'nullable',
                    'string',
                    'max:255',
                ],
                'sarpras_category_id' => ['prohibited'],
            ];
        }

        return [
            ...$rules,
            'quality_category_id' => ['prohibited'],
            'it_tag_id' => ['prohibited'],
            'custom_it_tag_text' => ['prohibited'],
            'sarpras_category_id' => [
                'required',
                'integer',
                Rule::exists(SarprasCategory::class, 'id')
                    ->where('is_active', true)
                    ->whereIn('name', ['Sarpras', 'Elektronik', 'Alkes']),
            ],
        ];
    }
}
