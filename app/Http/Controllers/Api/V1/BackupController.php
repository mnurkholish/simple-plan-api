<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListBackupsRequest;
use App\Http\Resources\BackupHistoryResource;
use App\Models\BackupHistory;
use App\Services\BackupService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $service) {}

    public function index(ListBackupsRequest $request): AnonymousResourceCollection
    {
        return BackupHistoryResource::collection(
            $this->service->histories($request->validated(), $request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $history = $this->service->create((int) $request->user()->getAuthIdentifier());
        } catch (Throwable) {
            return response()->json([
                'message' => 'Gagal memasukkan backup ke antrean.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($history === null) {
            return response()->json([
                'message' => 'Masih ada proses backup yang menunggu atau berjalan.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => 'Backup berhasil dimasukkan ke antrean.',
            'data' => new BackupHistoryResource($history->load('performedBy:id,name')),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(int $id): BackupHistoryResource|JsonResponse
    {
        try {
            return new BackupHistoryResource($this->service->history($id));
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Resource not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), Response::HTTP_FORBIDDEN);

        return $this->streamBackup($this->service->downloadableHistory($id));
    }

    private function streamBackup(BackupHistory $history): StreamedResponse
    {
        return Storage::disk('backup')->download(
            $history->object_key,
            $history->file_name,
            ['Content-Type' => 'application/zip']
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $this->service->delete($data['path']);

        return response()->json([
            'message' => 'File backup berhasil dihapus.',
            'data' => $this->service->index(),
        ]);
    }
}
