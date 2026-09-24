<?php

namespace App\Http\Requests;

use App\Enums\TicketService;
use App\Models\ItTag;
use App\Models\QualityCategory;
use App\Models\SarprasCategory;
use App\Models\Unit;
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
            'unit_id' => [
                'required',
                'integer',
                Rule::exists(Unit::class, 'id')->whereNull('deleted_at'),
            ],
            'quality_category_id' => [
                'nullable',
                'integer',
                'required_with:it_tag_id,custom_it_tag_text',
                'prohibited_unless:service,tik',
                Rule::exists(QualityCategory::class, 'id')->where('is_active', true),
            ],
            'it_tag_id' => [
                'nullable',
                'integer',
                'required_with:quality_category_id,custom_it_tag_text',
                'prohibited_unless:service,tik',
                Rule::exists(ItTag::class, 'id')->where('is_active', true),
            ],
            'custom_it_tag_text' => [
                'nullable',
                'string',
                'max:255',
                'prohibited_unless:service,tik',
            ],
            'sarpras_category_id' => [
                'nullable',
                'integer',
                'prohibited_unless:service,sarpras',
                Rule::exists(SarprasCategory::class, 'id')->where('is_active', true),
            ],
            'description' => ['required', 'string'],
            'initial_evidence' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
