<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDesignRequest;
use App\Http\Requests\DesignReportRequest;
use App\Http\Requests\StoreDesignRequestRequest;
use App\Http\Resources\DesignRequestResource;
use App\Models\DesignRequest;
use App\Services\DesignRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DesignRequestController extends Controller
{
    public function __construct(private readonly DesignRequestService $service) {}

    /**
     * Menampilkan daftar request desain dengan paginasi dan filter hak akses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = (int) ($request->get('limit') ?? $request->get('per_page') ?? 10);

        $designRequests = DesignRequest::query()
            ->with(['requester', 'unit'])
            ->visibleTo($request->user())
            ->latest()
            ->paginate($limit)
            ->withQueryString();

        return DesignRequestResource::collection($designRequests);
    }

    /**
     * Menampilkan detail request desain beserta riwayat draft-nya.
     */
    public function show(int|string $id): DesignRequestResource
    {
        $designRequest = DesignRequest::with(['drafts', 'requester', 'unit', 'assignedOfficer'])->findOrFail($id);

        $this->authorize('view', $designRequest);

        return new DesignRequestResource($designRequest);
    }

    /**
     * Membuat request desain baru.
     */
    public function store(StoreDesignRequestRequest $request): JsonResponse
    {
        $designRequest = $this->service->create(
            $request->validated(),
            $request->user(),
            $request->file('reference_file'),
        );

        return (new DesignRequestResource($designRequest))
            ->additional(['message' => 'Request desain berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Menugaskan request desain ke Petugas TIK (khusus Super Admin).
     */
    public function assign(AssignDesignRequest $request, int|string $id): JsonResponse
    {
        $designRequest = DesignRequest::findOrFail($id);

        $this->authorize('assign', $designRequest);

        $assignedRequest = $this->service->assignTask($designRequest, $request->validated());

        return (new DesignRequestResource($assignedRequest))
            ->additional(['message' => 'Tugas desain berhasil ditugaskan ke petugas TIK.'])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Menampilkan daftar tugas desain untuk Petugas TIK yang sedang login.
     */
    public function tasks(Request $request): AnonymousResourceCollection
    {
        $limit = (int) ($request->get('limit') ?? $request->get('per_page') ?? 10);

        $tasks = $this->service->getTasksByAssignee($request->user()->getKey(), $limit);

        return DesignRequestResource::collection($tasks);
    }

    /**
     * Rekapitulasi laporan request desain berdasarkan periode tanggal (khusus Manajemen / Super Admin).
     */
    public function reports(DesignReportRequest $request): JsonResponse
    {
        $summary = $this->service->getReportSummary(
            (string) $request->validated('start_date'),
            (string) $request->validated('end_date'),
        );

        return response()->json([
            'message' => 'Laporan rekapitulasi request desain berhasil diambil.',
            'data' => [
                'period' => [
                    'start_date' => $summary['start_date'],
                    'end_date' => $summary['end_date'],
                ],
                'total' => $summary['total'],
                'by_status' => $summary['by_status'],
                'by_design_type' => $summary['by_design_type'],
                'requests' => DesignRequestResource::collection($summary['requests']),
            ],
        ]);
    }
}
