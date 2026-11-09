<?php

namespace App\Repositories;

use App\Models\DesignRequest;

class DesignRequestRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DesignRequest
    {
        return DesignRequest::create($attributes);
    }

    public function loadSummary(DesignRequest $designRequest): DesignRequest
    {
        return $designRequest->load(['requester:id,name', 'unit:id,unit_name']);
    }
}
