<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getDashboardData($request->user()),
        ]);
    }

    public function liveStats(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getLiveStats(),
        ]);
    }
}
