<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAssetItemsRequest;
use App\Http\Requests\StoreAssetItemRequest;
use App\Http\Requests\UpdateAssetItemRequest;
use App\Models\Asset;
use App\Models\AssetItem;
use Illuminate\Http\JsonResponse;

class AssetItemController extends Controller
{
    public function index(ListAssetItemsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);
        $query = AssetItem::query();

        if (($locationId = $request->validated('location_id')) !== null) {
            $query->whereHas('assetCodes', fn ($assetCodes) => $assetCodes
                ->where('asset_location_id', $locationId)
                ->whereHas('asset'));
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('per_page') || $request->has('page')) {
            $perPage = min(max($request->integer('per_page', 15), 1), 100);

            return response()->json($query->paginate($perPage));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(StoreAssetItemRequest $request): JsonResponse
    {
        $this->authorize('create', Asset::class);
        $item = AssetItem::create($request->safe()->only(['name']));

        return response()->json([
            'data' => $item,
            'message' => 'Asset item created successfully.',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);
        $item = AssetItem::findOrFail($id);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function update(UpdateAssetItemRequest $request, string $id): JsonResponse
    {
        $this->authorize('create', Asset::class);
        $item = AssetItem::findOrFail($id);
        $item->update($request->safe()->only(['name']));

        return response()->json([
            'data' => $item,
            'message' => 'Asset item updated successfully.',
        ]);
    }
}
