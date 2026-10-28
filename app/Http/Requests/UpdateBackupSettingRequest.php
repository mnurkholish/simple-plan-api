<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBackupSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'backup_time' => ['required', 'date_format:H:i'],
            'day_of_week' => ['required_if:frequency,weekly', 'prohibited_unless:frequency,weekly', 'integer', 'between:1,7'],
            'day_of_month' => ['required_if:frequency,monthly', 'prohibited_unless:frequency,monthly', 'integer', 'between:1,28'],
        ];
    }
}
