<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePeriod;
use App\Models\AssetCode;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssetMaintenanceRequest extends FormRequest
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

            if ($user->hasRole('super-admin')) {
                if (! $isTik) {
                    $validator->errors()->add('asset_code_id', 'Superadmin hanya dapat mengatur jadwal pemeliharaan untuk aset TIK (Alat IT).');
                }

                if (! $officer->hasRole('petugas-tik')) {
                    $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset TIK harus memiliki role petugas-tik.');
                }
            } elseif ($user->hasRole('koordinator-sarpras')) {
                if ($isTik) {
                    $validator->errors()->add('asset_code_id', 'Koordinator Sarpras hanya dapat mengatur jadwal pemeliharaan untuk aset non-TIK (Sarpras).');
                }

                if (! $officer->hasRole('petugas-sarpras')) {
                    $validator->errors()->add('assigned_officer_id', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');
                }
            }
        });
    }
}
