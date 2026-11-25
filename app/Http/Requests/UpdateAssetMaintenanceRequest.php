<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePeriod;
use App\Models\AssetMaintenance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAssetMaintenanceRequest extends FormRequest
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
        throw new AuthorizationException('Anda tidak memiliki izin untuk memperbarui jadwal pemeliharaan aset.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'maintenance_type' => ['sometimes', 'required', 'string', 'max:255'],
            'period' => ['sometimes', 'required', Rule::enum(MaintenancePeriod::class)],
            'next_scheduled_date' => ['sometimes', 'required', 'date'],
            'assigned_officer_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'maintenance_type' => 'jenis pemeliharaan',
            'period' => 'periode pemeliharaan',
            'next_scheduled_date' => 'tanggal jadwal pemeliharaan',
            'assigned_officer_id' => 'petugas yang ditugaskan',
            'is_active' => 'status aktif jadwal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maintenance_type.required' => 'Jenis pemeliharaan wajib diisi.',
            'maintenance_type.string' => 'Jenis pemeliharaan harus berupa teks.',
            'maintenance_type.max' => 'Jenis pemeliharaan tidak boleh lebih dari :max karakter.',
            'period.required' => 'Periode pemeliharaan wajib dipilih.',
            'period.enum' => 'Periode pemeliharaan yang dipilih tidak valid.',
            'next_scheduled_date.required' => 'Tanggal jadwal pemeliharaan wajib diisi.',
            'next_scheduled_date.date' => 'Tanggal jadwal pemeliharaan tidak valid.',
            'assigned_officer_id.required' => 'Petugas yang ditugaskan wajib dipilih.',
            'assigned_officer_id.integer' => 'Petugas yang ditugaskan tidak valid.',
            'assigned_officer_id.exists' => 'Petugas yang dipilih tidak ditemukan atau tidak valid.',
            'is_active.required' => 'Status aktif jadwal wajib diisi.',
            'is_active.boolean' => 'Status aktif jadwal harus berupa nilai benar atau salah.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if (! $user) {
                return;
            }

            $maintenance = $this->getMaintenance();
            if (! $maintenance) {
                return;
            }

            $isTik = ($maintenance->assetCode?->classification?->name === 'Alat IT');

            if (! $user->hasAnyRole(['super-admin', 'super_admin'])
                && $user->hasAnyRole(['koordinator-sarpras', 'koordinator_sarpras'])
                && $isTik) {
                $validator->errors()->add('maintenance', 'Koordinator Sarpras hanya dapat mengelola jadwal pemeliharaan untuk aset non-TIK (Sarpras).');
            }

            if ($this->has('assigned_officer_id') && ! $validator->errors()->has('assigned_officer_id')) {
                $officer = User::find($this->input('assigned_officer_id'));
                if ($officer) {
                    if ($isTik && ! $this->isValidTikOfficer($officer)) {
                        $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset TIK harus memiliki role petugas-tik.');
                    } elseif (! $isTik && ! $this->isValidSarprasOfficer($officer)) {
                        $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');
                    }
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

    protected function getMaintenance(): ?AssetMaintenance
    {
        $maintenance = $this->route('asset_maintenance')
            ?? $this->route('maintenance')
            ?? $this->route('id');

        if ($maintenance instanceof AssetMaintenance) {
            $maintenance->loadMissing('assetCode.classification');

            return $maintenance;
        }

        if (is_numeric($maintenance)) {
            return AssetMaintenance::with('assetCode.classification')->find($maintenance);
        }

        return null;
    }
}
