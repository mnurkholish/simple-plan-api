<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceReportExportRequest;
use App\Http\Requests\MaintenanceReportRequest;
use App\Services\MaintenanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MaintenanceReportController extends Controller
{
    public function __construct(private readonly MaintenanceReportService $service) {}

    public function index(MaintenanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->report($request->validated(), $request->user()),
        ]);
    }

    public function export(MaintenanceReportExportRequest $request): Response
    {
        $validated = $request->validated();

        return $this->service->export($validated, $request->user(), $validated['format']);
    }
}
