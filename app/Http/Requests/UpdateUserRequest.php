<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'nip' => ['sometimes', 'required', 'string', 'max:255', 'unique:users,nip,'.$userId],
            'password' => ['nullable', 'string', 'min:8'],
            'unit_id' => ['sometimes', 'required', 'integer', 'exists:units,id'],
            'jabatan' => ['sometimes', 'required', 'string', 'max:255'],
            'no_hp' => ['sometimes', 'required', 'string', 'max:20', 'unique:users,no_hp,'.$userId],
            'role' => ['sometimes', 'string', Rule::in(['super admin', 'koordinator-sarpras', 'petugas-tik', 'petugas-sarpras', 'user', 'management'])],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Form wajib diisi',
            'nip.unique' => 'NIP sudah terdaftar di sistem.',
            'no_hp.unique' => 'Nomor HP sudah terdaftar di sistem.',
            '*' => 'Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan',
        ];
    }
}
