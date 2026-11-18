<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryReportExportRequest;
use App\Http\Requests\InventoryReportRequest;
use App\Services\InventoryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InventoryReportController extends Controller
{
    public function __construct(private readonly InventoryReportService $service) {}

    public function index(InventoryReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->report($request->validated(), $request->user()),
        ]);
    }

    public function export(InventoryReportExportRequest $request): Response
    {
        $validated = $request->validated();

        return $this->service->export($validated, $request->user(), $validated['format']);
    }
}
