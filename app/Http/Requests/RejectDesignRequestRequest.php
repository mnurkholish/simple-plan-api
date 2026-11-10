<?php

namespace App\Http\Requests;

use App\Models\DesignRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectDesignRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $designRequest = $this->route('designRequest');

        return $designRequest instanceof DesignRequest
            && $this->user()?->can('reject', $designRequest) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['rejection_note' => ['required', 'string']];
    }
}
