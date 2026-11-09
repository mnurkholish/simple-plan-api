<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListDesignRequestRequest;
use App\Http\Requests\StoreDesignRequestRequest;
use App\Http\Resources\DesignRequestResource;
use App\Models\DesignRequest;
use App\Services\DesignRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DesignRequestController extends Controller
{
    public function __construct(private readonly DesignRequestService $service) {}

    public function index(ListDesignRequestRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        Gate::authorize('viewAny', [DesignRequest::class, $filters['scope'] ?? 'mine']);

        return DesignRequestResource::collection(
            $this->service->paginateVisibleTo(
                $request->user(),
                $filters,
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(StoreDesignRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', DesignRequest::class);

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
}
