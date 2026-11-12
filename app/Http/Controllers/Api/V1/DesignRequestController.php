<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDesignRequestRequest;
use App\Http\Resources\DesignRequestResource;
use App\Services\DesignRequestService;
use Illuminate\Http\JsonResponse;

class DesignRequestController extends Controller
{
    public function __construct(private readonly DesignRequestService $service) {}

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
}
