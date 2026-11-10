<?php

namespace App\Http\Requests;

use App\Models\DesignRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDesignRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $designRequest = $this->route('designRequest');

        return $designRequest instanceof DesignRequest
            && $this->user()?->can('assign', $designRequest) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::in(['rendah', 'sedang', 'tinggi'])],
            'assigned_officer_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists(User::class, 'id')
                    ->where('status', 'active')
                    ->where('status_user', 'Aktif'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $officer = User::query()->find($value);

                    if ($officer !== null && (! $officer->hasRole('petugas-tik') || ! $officer->can('design-drafts-upload'))) {
                        $fail('Petugas yang dipilih harus merupakan Petugas TIK bagian desain.');
                    }
                },
            ],
        ];
    }
}
