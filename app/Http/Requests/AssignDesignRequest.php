<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->has('prioritas') && $this->has('priority')) {
            $merge['prioritas'] = $this->input('priority');
        }

        if (! $this->has('petugas_desain_id') && $this->has('assigned_officer_id')) {
            $merge['petugas_desain_id'] = $this->input('assigned_officer_id');
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prioritas' => [
                'required',
                'string',
                Rule::in(['Rendah', 'Sedang', 'Tinggi', 'rendah', 'sedang', 'tinggi']),
            ],
            'petugas_desain_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $officer = User::query()->find($value);

                    if ($officer === null || ! $officer->hasAnyRole(['petugas-tik', 'petugas_tik'])) {
                        $fail('Petugas yang dipilih harus memiliki role petugas_tik.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prioritas.required' => 'Prioritas wajib diisi.',
            'prioritas.in' => 'Prioritas harus salah satu dari: Rendah, Sedang, Tinggi.',
            'petugas_desain_id.required' => 'Petugas desain wajib dipilih.',
            'petugas_desain_id.integer' => 'ID petugas desain harus berupa angka.',
            'petugas_desain_id.exists' => 'Petugas desain yang dipilih tidak ditemukan.',
        ];
    }
}
