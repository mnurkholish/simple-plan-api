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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
                $lockedRequest = DesignRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

                if (! in_array($lockedRequest->status, [DesignRequestStatus::Ditugaskan, DesignRequestStatus::PerluRevisi], true)) {
                    throw new ConflictHttpException('Draft hanya dapat diunggah saat request berstatus ditugaskan atau perlu revisi.');
                }

                $draft = $lockedRequest->drafts()->create([
                    'file_object_key' => $filePath,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'notes' => $notes,
                    'uploaded_by_id' => auth()->id() ?? $lockedRequest->assigned_officer_id ?? $lockedRequest->requester_id,
                ]);

                $lockedRequest->status = DesignRequestStatus::MenungguPersetujuan;
                $lockedRequest->save();

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
            $lockedRequest = DesignRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== DesignRequestStatus::MenungguPersetujuan) {
                throw new ConflictHttpException('Revisi hanya dapat diajukan saat request menunggu persetujuan.');
            }

            $draft = $lockedRequest->drafts()->latest()->firstOrFail();

            $revision = $lockedRequest->revisions()->create([
                'design_draft_id' => $draft->id,
                'revision_note' => $note,
                'requested_by_id' => auth()->id() ?? $lockedRequest->requester_id,
            ]);

            $lockedRequest->status = DesignRequestStatus::PerluRevisi;
            $lockedRequest->save();

            return $revision;
        });
    }

    /**
     * Setujui request desain (approval).
     */
    public function approve(DesignRequest $request): bool
    {
        return DB::transaction(function () use ($request): bool {
            $lockedRequest = DesignRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== DesignRequestStatus::MenungguPersetujuan) {
                throw new ConflictHttpException('Request hanya dapat disetujui saat menunggu persetujuan.');
            }

            $lockedRequest->status = DesignRequestStatus::Selesai;
            $lockedRequest->completed_at = now();

            return $lockedRequest->save();
        });
    }

    /**
     * Dapatkan URL file draft terakhir sebagai file final.
     */
    public function getFinalFileUrl(DesignRequest $request): string
    {
        $draft = $request->drafts()->latest()->firstOrFail();

        return Storage::temporaryUrl(
            $draft->file_object_key,
            now()->addMinutes((int) config('filesystems.temporary_url_expiration_minutes', 5)),
        );
    }
}
