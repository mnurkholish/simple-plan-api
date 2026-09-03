<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeRole = $this->route('role');
        $roleId = $routeRole instanceof Role ? $routeRole->getKey() : $routeRole;

        return [
            'name' => ['required', 'string', Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['nullable', 'array'],
            'selectedPermission' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('permissions') && ! $this->filled('selectedPermission')) {
                    $validator->errors()->add('permissions', 'Permissions wajib diisi.');
                }
            },
        ];
    }
}
