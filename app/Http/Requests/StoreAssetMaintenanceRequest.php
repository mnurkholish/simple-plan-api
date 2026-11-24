<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePeriod;
use App\Models\AssetCode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'super-admin',
            'super_admin',
            'koordinator-sarpras',
            'koordinator_sarpras',
        ]) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Anda tidak memiliki izin untuk membuat jadwal pemeliharaan aset.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_code_id' => [
                'required',
                'integer',
                Rule::exists(AssetCode::class, 'id'),
            ],
            'assigned_officer_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
            'maintenance_type' => ['required', 'string', 'max:255'],
            'period' => ['required', Rule::enum(MaintenancePeriod::class)],
            'next_scheduled_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'asset_code_id' => 'aset',
            'assigned_officer_id' => 'petugas yang ditugaskan',
            'maintenance_type' => 'jenis pemeliharaan',
            'period' => 'periode pemeliharaan',
            'next_scheduled_date' => 'tanggal jadwal pemeliharaan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_code_id.required' => 'Aset wajib dipilih.',
            'asset_code_id.integer' => 'Aset yang dipilih tidak valid.',
            'asset_code_id.exists' => 'Aset yang dipilih tidak ditemukan.',
            'assigned_officer_id.required' => 'Petugas yang ditugaskan wajib dipilih.',
            'assigned_officer_id.integer' => 'Petugas yang ditugaskan tidak valid.',
            'assigned_officer_id.exists' => 'Petugas yang dipilih tidak ditemukan atau tidak valid.',
            'maintenance_type.required' => 'Jenis pemeliharaan wajib diisi.',
            'maintenance_type.string' => 'Jenis pemeliharaan harus berupa teks.',
            'maintenance_type.max' => 'Jenis pemeliharaan tidak boleh lebih dari :max karakter.',
            'period.required' => 'Periode pemeliharaan wajib dipilih.',
            'period.enum' => 'Periode pemeliharaan yang dipilih tidak valid.',
            'next_scheduled_date.required' => 'Tanggal jadwal pemeliharaan wajib diisi.',
            'next_scheduled_date.date' => 'Tanggal jadwal pemeliharaan tidak valid.',
            'next_scheduled_date.after_or_equal' => 'Tanggal jadwal pemeliharaan tidak boleh kurang dari hari ini.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('asset_code_id') || $validator->errors()->has('assigned_officer_id')) {
                return;
            }

            $user = $this->user();
            if (! $user) {
                return;
            }

            $assetCode = AssetCode::with('classification')->find($this->input('asset_code_id'));
            if (! $assetCode) {
                return;
            }

            $isTik = ($assetCode->classification?->name === 'Alat IT');
            $officer = User::find($this->input('assigned_officer_id'));
            if (! $officer) {
                return;
            }

            if ($user->hasAnyRole(['super-admin', 'super_admin'])) {
                if (! $isTik) {
                    $validator->errors()->add('asset_code_id', 'Superadmin hanya dapat mengatur jadwal pemeliharaan untuk aset TIK (Alat IT).');
                }

                if (! $this->isValidTikOfficer($officer)) {
                    $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset TIK harus memiliki role petugas-tik.');
                }
            } elseif ($user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])) {
                if ($isTik) {
                    $validator->errors()->add('asset_code_id', 'Koordinator Sarpras hanya dapat mengatur jadwal pemeliharaan untuk aset non-TIK (Sarpras).');
                }

                if (! $this->isValidSarprasOfficer($officer)) {
                    $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');
                }
            }
        });
    }

    protected function isValidTikOfficer(User $officer): bool
    {
        return $officer->hasAnyRole(['petugas-tik', 'petugas_tik'])
            || $officer->roles()->withoutGlobalScopes()->whereIn('name', ['petugas-tik', 'petugas_tik'])->exists();
    }

    protected function isValidSarprasOfficer(User $officer): bool
    {
        return $officer->hasAnyRole(['petugas-sarpras', 'petugas_sarpras'])
            || $officer->roles()->withoutGlobalScopes()->whereIn('name', ['petugas-sarpras', 'petugas_sarpras'])->exists();
    }
}
