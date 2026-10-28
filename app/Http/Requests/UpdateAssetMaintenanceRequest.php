<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePeriod;
use App\Models\AssetMaintenance;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'koordinator-sarpras']) ?? false;
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

            if ($user->hasRole('super-admin')) {
                if (! $isTik) {
                    $validator->errors()->add('maintenance', 'Superadmin hanya dapat mengelola jadwal pemeliharaan untuk aset TIK (Alat IT).');
                }
            } elseif ($user->hasRole('koordinator-sarpras')) {
                if ($isTik) {
                    $validator->errors()->add('maintenance', 'Koordinator Sarpras hanya dapat mengelola jadwal pemeliharaan untuk aset non-TIK (Sarpras).');
                }
            }

            if ($this->has('assigned_officer_id') && ! $validator->errors()->has('assigned_officer_id')) {
                $officer = User::find($this->input('assigned_officer_id'));
                if ($officer) {
                    if ($isTik && ! $officer->hasRole('petugas-tik')) {
                        $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset TIK harus memiliki role petugas-tik.');
                    } elseif (! $isTik && ! $officer->hasRole('petugas-sarpras')) {
                        $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');
                    }
                }
            }
        });
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
