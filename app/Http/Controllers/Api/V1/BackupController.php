<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->index(),
        ]);
    }

    public function store(): JsonResponse
    {
        try {
            $result = $this->service->create();
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Gagal membuat backup: '.$exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => $result['message'],
            'output' => $result['output'],
            'data' => $this->service->index(),
        ], Response::HTTP_CREATED);
    }

    public function saveSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frequency' => ['required', 'in:none,daily,weekly,monthly'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        return response()->json([
            'message' => 'Jadwal backup otomatis berhasil diperbarui.',
            'data' => $this->service->saveSchedule($data),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = $this->service->downloadPath($data['path']);

        return Storage::disk($this->service->diskName())->download($path, basename($path));
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
