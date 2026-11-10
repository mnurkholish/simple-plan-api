<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DesignRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDesignDraftRequest;
use App\Http\Requests\StoreDesignRevisionRequest;
use App\Models\DesignRequest;
use App\Services\DesignWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DesignWorkflowController extends Controller
{
    public function __construct(
        private readonly DesignWorkflowService $service,
    ) {}

    /**
     * Unggah draft desain baru untuk request terkait.
     */
    public function storeDraft(StoreDesignDraftRequest $request, int|string $id): JsonResponse
    {
        $designRequest = DesignRequest::findOrFail($id);

        $draft = $this->service->storeDraft(
            $designRequest,
            $request->file('file'),
            $request->input('notes'),
        );

        return response()->json([
            'message' => 'Draft desain berhasil diunggah.',
            'data' => $draft,
        ], Response::HTTP_CREATED);
    }

    /**
     * Ajukan catatan revisi untuk draft desain terakhir.
     */
    public function storeRevision(StoreDesignRevisionRequest $request, int|string $id): JsonResponse
    {
        $designRequest = DesignRequest::findOrFail($id);

        $revision = $this->service->storeRevision(
            $designRequest,
            (string) $request->input('revision_note'),
        );

        return response()->json([
            'message' => 'Revisi desain berhasil diajukan.',
            'data' => $revision,
        ], Response::HTTP_CREATED);
    }

    /**
     * Setujui hasil desain oleh pemohon request.
     */
    public function storeApproval(Request $request, int|string $id): JsonResponse
    {
        $designRequest = DesignRequest::findOrFail($id);

        $isWaitingApproval = ($designRequest->status === DesignRequestStatus::MenungguPersetujuan)
            || in_array((string) ($designRequest->status?->value ?? $designRequest->status), [
                'menunggu_persetujuan',
                'MenungguPersetujuan',
            ], true);

        if (! $isWaitingApproval || (int) $request->user()?->id !== (int) $designRequest->requester_id) {
            return response()->json([
                'message' => 'Hanya pemohon yang dapat menyetujui request desain berstatus MenungguPersetujuan.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->service->approve($designRequest);

        return response()->json([
            'message' => 'Request desain berhasil disetujui.',
        ], Response::HTTP_OK);
    }

    /**
     * Dapatkan URL unduhan file final desain yang telah disetujui.
     */
    public function downloadFinal(Request $request, int|string $id): JsonResponse
    {
        $designRequest = DesignRequest::findOrFail($id);

        $isCompleted = ($designRequest->status === DesignRequestStatus::Selesai)
            || in_array((string) ($designRequest->status?->value ?? $designRequest->status), [
                'selesai',
                'Selesai',
            ], true);

        if (! $isCompleted) {
            return response()->json([
                'message' => 'File final hanya dapat diunduh untuk request desain yang sudah selesai.',
            ], Response::HTTP_FORBIDDEN);
        }

        $url = $this->service->getFinalFileUrl($designRequest);

        return response()->json([
            'final_file_url' => $url,
        ], Response::HTTP_OK);
    }
}
