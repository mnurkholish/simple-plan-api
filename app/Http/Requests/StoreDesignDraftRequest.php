<?php

namespace App\Http\Requests;

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDesignDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = $this->route('id') ?? $this->route('design_request');
            $designRequest = $id instanceof DesignRequest ? $id : DesignRequest::find($id);

            if (! $designRequest) {
                $validator->errors()->add('design_request', 'Request desain tidak ditemukan.');

                return;
            }

            $currentStatus = $designRequest->status instanceof DesignRequestStatus
                ? $designRequest->status
                : DesignRequestStatus::tryFrom((string) $designRequest->status);

            $isValidStatus = in_array($currentStatus, [
                DesignRequestStatus::Ditugaskan,
                DesignRequestStatus::PerluRevisi,
            ], true) || in_array((string) ($designRequest->status?->value ?? $designRequest->status), [
                'ditugaskan',
                'perlu_revisi',
                'Ditugaskan',
                'PerluRevisi',
            ], true);

            if (! $isValidStatus) {
                $validator->errors()->add('status', 'Draft hanya dapat diunggah untuk request desain dengan status Ditugaskan atau PerluRevisi.');
            }

            $user = $this->user();
            if (! $user || empty($designRequest->assigned_officer_id) || (int) $designRequest->assigned_officer_id !== (int) $user->id) {
                $validator->errors()->add('assigned_officer_id', 'Hanya petugas yang ditugaskan yang dapat mengunggah draft desain.');
            }
        });
    }
}
