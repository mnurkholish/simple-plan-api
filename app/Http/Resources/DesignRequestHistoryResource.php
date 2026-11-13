<?php

namespace App\Http\Resources;

use App\Models\DesignDraft;
use App\Models\DesignRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DesignRequestHistoryResource extends JsonResource
{
    /** @return list<array<string, mixed>> */
    public function toArray(Request $request): array
    {
        $drafts = $this->drafts->map(fn (DesignDraft $draft): array => [
            'type' => 'draft',
            'id' => $draft->id,
            'created_at' => $draft->created_at,
            'file' => [
                'original_name' => $draft->file_original_name,
                'mime_type' => $draft->file_mime_type,
                'size' => $draft->file_size,
                'url' => Storage::temporaryUrl(
                    $draft->file_object_key,
                    now()->addMinutes((int) config('filesystems.temporary_url_expiration_minutes', 5)),
                ),
            ],
            'notes' => $draft->notes,
            'uploaded_by' => [
                'id' => $draft->uploadedBy->id,
                'name' => $draft->uploadedBy->name,
            ],
        ]);

        $revisions = $this->revisions->map(fn (DesignRevision $revision): array => [
            'type' => 'revision',
            'id' => $revision->id,
            'created_at' => $revision->created_at,
            'design_draft_id' => $revision->design_draft_id,
            'revision_note' => $revision->revision_note,
            'requested_by' => [
                'id' => $revision->requestedBy->id,
                'name' => $revision->requestedBy->name,
            ],
        ]);

        return $drafts->concat($revisions)
            ->sortBy(fn (array $activity): string => $activity['created_at']->format('Y-m-d H:i:s.u'))
            ->values()
            ->all();
    }
}
