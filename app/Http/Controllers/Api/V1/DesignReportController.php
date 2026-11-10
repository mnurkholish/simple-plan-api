<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListDesignReportRequest;
use App\Http\Resources\DesignReportRequestResource;
use App\Models\DesignRequest;
use App\Services\DesignRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DesignReportController extends Controller
{
    public function __construct(private readonly DesignRequestService $service) {}

    public function index(ListDesignReportRequest $request): JsonResponse
    {
        Gate::authorize('viewReport', DesignRequest::class);

        $report = $this->service->report($request->validated(), $request->integer('per_page', 15));

        return response()->json(['data' => [
            'total' => $report['total'],
            'by_status' => $report['by_status'],
            'by_design_type' => $report['by_design_type'],
            'requests' => DesignReportRequestResource::collection($report['requests'])->response()->getData(true),
        ]]);
    }
}
