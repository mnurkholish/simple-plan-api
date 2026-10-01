<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchAssetsRequest;
use App\Http\Resources\AssetResource;
use App\Services\AssetService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetController extends Controller
{
    public function __construct(
        protected AssetService $assetService
    ) {}

    public function search(SearchAssetsRequest $request): AnonymousResourceCollection
    {
        return AssetResource::collection(
            $this->assetService->search(
                $request->integer('unit'),
                $request->string('search')->toString() ?: null,
            )
        );
    }
}
