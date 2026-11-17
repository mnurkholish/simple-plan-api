<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpdeskReportRequest;
use App\Services\HelpdeskReportService;
use Illuminate\Http\JsonResponse;

class HelpdeskReportController extends Controller
{
    public function __construct(private readonly HelpdeskReportService $service) {}

    public function index(HelpdeskReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->report($request->validated(), $request->user())]);
    }
}
