<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DesignRequestDetailResource extends DesignRequestResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($data['reference_file'] !== null) {
            $data['reference_file']['url'] = Storage::temporaryUrl(
                $this->reference_file_object_key,
                now()->addMinutes((int) config('filesystems.temporary_url_expiration_minutes', 5)),
            );
        }

        return [
            ...$data,
            'priority' => $this->priority,
            'assigned_officer' => $this->assignedOfficer === null ? null : [
                'id' => $this->assignedOfficer->id,
                'name' => $this->assignedOfficer->name,
            ],
            'assigned_at' => $this->assigned_at,
            'completed_at' => $this->completed_at,
            'rejection_note' => $this->rejection_note,
            'latest_draft' => $this->latestDraft === null ? null : [
                'id' => $this->latestDraft->id,
                'notes' => $this->latestDraft->notes,
                'file' => [
                    'original_name' => $this->latestDraft->file_original_name,
                    'mime_type' => $this->latestDraft->file_mime_type,
                    'size' => $this->latestDraft->file_size,
                ],
                'uploaded_by' => [
                    'id' => $this->latestDraft->uploadedBy->id,
                    'name' => $this->latestDraft->uploadedBy->name,
                ],
                'created_at' => $this->latestDraft->created_at,
            ],
            'latest_revision' => $this->latestRevision === null ? null : [
                'id' => $this->latestRevision->id,
                'design_draft_id' => $this->latestRevision->design_draft_id,
                'revision_note' => $this->latestRevision->revision_note,
                'requested_by' => [
                    'id' => $this->latestRevision->requestedBy->id,
                    'name' => $this->latestRevision->requestedBy->name,
                ],
                'created_at' => $this->latestRevision->created_at,
            ],
        ];
    }
}
