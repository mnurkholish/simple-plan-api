<?php

namespace App\Http\Requests;

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDesignRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'revision_note' => ['required', 'string'],
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

            $isValidStatus = ($currentStatus === DesignRequestStatus::MenungguPersetujuan)
                || in_array((string) ($designRequest->status?->value ?? $designRequest->status), [
                    'menunggu_persetujuan',
                    'MenungguPersetujuan',
                ], true);

            if (! $isValidStatus) {
                $validator->errors()->add('status', 'Revisi hanya dapat diajukan untuk request desain dengan status MenungguPersetujuan.');
            }

            $user = $this->user();
            if (! $user || empty($designRequest->requester_id) || (int) $designRequest->requester_id !== (int) $user->id) {
                $validator->errors()->add('requester_id', 'Hanya pemohon yang dapat mengajukan revisi desain.');
            }
        });
    }
}
