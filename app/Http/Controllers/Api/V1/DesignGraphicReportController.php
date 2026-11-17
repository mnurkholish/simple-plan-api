<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DesignGraphicReportRequest;
use App\Services\DesignGraphicReportService;
use Illuminate\Http\JsonResponse;

class DesignGraphicReportController extends Controller
{
    public function __construct(private readonly DesignGraphicReportService $service) {}

    public function index(DesignGraphicReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->report($request->validated(), $request->user())]);
    }
}
