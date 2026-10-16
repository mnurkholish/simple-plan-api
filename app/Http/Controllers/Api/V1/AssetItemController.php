<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAssetItemsRequest;
use App\Http\Requests\StoreAssetItemRequest;
use App\Http\Requests\UpdateAssetItemRequest;
use App\Models\Asset;
use App\Models\AssetCode;
use App\Models\AssetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
        $item = AssetItem::create($request->validated());

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
        if ((int) $item->code !== (int) $request->validated('code')
            && AssetCode::where('asset_item_id', $item->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode barang yang telah dipakai aset tidak dapat diubah.']);
        }
        $item->update($request->validated());

        return response()->json([
            'data' => $item,
            'message' => 'Asset item updated successfully.',
        ]);
    }
}
