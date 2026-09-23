<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:255', 'unique:users,nip'],
            'password' => ['required', 'string', 'min:8'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'jabatan' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'role' => ['required', 'string', Rule::in(['super admin', 'koordinator-sarpras', 'petugas-tik', 'petugas-sarpras', 'user', 'management'])],
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
