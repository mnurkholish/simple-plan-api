<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->service->forUser($request->user());

        return response()->json([
            ...$notifications,
            'activity' => $this->service->activitySummary(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $this->service->markAsRead($request->user(), $id);

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            ...$this->service->forUser($request->user()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->service->markAllAsRead($request->user());

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            ...$this->service->forUser($request->user()),
        ]);
    }
}
