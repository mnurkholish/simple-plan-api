<?php

namespace App\Services;

use App\Enums\DesignRequestStatus;
use App\Models\DesignDraft;
use App\Models\DesignRequest;
use App\Models\DesignRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DesignWorkflowService
{
    /**
     * Upload dan simpan draft desain baru.
     */
    public function storeDraft(DesignRequest $request, UploadedFile $file, ?string $notes = null): DesignDraft
    {
        $filePath = Storage::putFile('design_drafts/'.$request->id, $file);

        if ($filePath === false) {
            throw new RuntimeException('Gagal mengunggah file draft desain.');
        }

        try {
            return DB::transaction(function () use ($request, $file, $notes, $filePath): DesignDraft {
                $draft = $request->drafts()->create([
                    'file_object_key' => $filePath,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'notes' => $notes,
                    'uploaded_by_id' => auth()->id() ?? $request->assigned_officer_id ?? $request->requester_id,
                ]);

                $request->status = DesignRequestStatus::MenungguPersetujuan;
                $request->save();

                return $draft;
            });
        } catch (Throwable $exception) {
            Storage::delete($filePath);

            throw $exception;
        }
    }

    /**
     * Ajukan revisi untuk draft desain terakhir.
     */
    public function storeRevision(DesignRequest $request, string $note): DesignRevision
    {
        return DB::transaction(function () use ($request, $note): DesignRevision {
            $draft = $request->drafts()->latest()->firstOrFail();

            $revision = $request->revisions()->create([
                'design_draft_id' => $draft->id,
                'revision_note' => $note,
                'requested_by_id' => auth()->id() ?? $request->requester_id,
            ]);

            $request->status = DesignRequestStatus::PerluRevisi;
            $request->save();

            return $revision;
        });
    }

    /**
     * Setujui request desain (approval).
     */
    public function approve(DesignRequest $request): bool
    {
        return DB::transaction(function () use ($request): bool {
            $request->status = DesignRequestStatus::Selesai;
            $request->completed_at = now();

            return $request->save();
        });
    }

    /**
     * Dapatkan URL file draft terakhir sebagai file final.
     */
    public function getFinalFileUrl(DesignRequest $request): string
    {
        $draft = $request->drafts()->latest()->firstOrFail();

        try {
            return Storage::temporaryUrl($draft->file_path, now()->addMinutes(30));
        } catch (Throwable) {
            return Storage::url($draft->file_path);
        }
    }
}
