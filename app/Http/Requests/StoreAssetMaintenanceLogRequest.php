<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMaintenanceLogRequest extends FormRequest
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
        return [
            'work_description' => ['required', 'string'],
            'maintenance_result' => ['required', 'string'],
            'post_maintenance_condition' => ['required', Rule::enum(AssetCondition::class)],
            'started_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'completed_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after_or_equal:started_at',
            ],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_description' => 'uraian pekerjaan',
            'maintenance_result' => 'hasil pemeliharaan',
            'post_maintenance_condition' => 'kondisi pasca pemeliharaan',
            'started_at' => 'waktu mulai',
            'completed_at' => 'waktu selesai',
            'photo' => 'foto bukti pemeliharaan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'work_description.required' => 'Uraian pekerjaan wajib diisi.',
            'work_description.string' => 'Uraian pekerjaan harus berupa teks.',
            'maintenance_result.required' => 'Hasil pemeliharaan wajib diisi.',
            'maintenance_result.string' => 'Hasil pemeliharaan harus berupa teks.',
            'post_maintenance_condition.required' => 'Kondisi pasca pemeliharaan wajib dipilih.',
            'post_maintenance_condition.enum' => 'Kondisi pasca pemeliharaan yang dipilih tidak valid.',
            'started_at.required' => 'Waktu mulai pengerjaan wajib diisi.',
            'started_at.date_format' => 'Format waktu mulai harus YYYY-MM-DD HH:MM:SS.',
            'completed_at.required' => 'Waktu selesai pengerjaan wajib diisi.',
            'completed_at.date_format' => 'Format waktu selesai harus YYYY-MM-DD HH:MM:SS.',
            'completed_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai.',
            'photo.image' => 'Foto bukti pemeliharaan harus berupa berkas gambar.',
            'photo.max' => 'Ukuran foto bukti pemeliharaan tidak boleh lebih dari 5 MB.',
            'photo.uploaded' => 'Foto bukti pemeliharaan gagal diunggah.',
        ];
    }
}
