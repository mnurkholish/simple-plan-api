<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetItemRequest;
use App\Http\Requests\UpdateAssetItemRequest;
use App\Models\AssetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AssetItem::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
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
        $item = AssetItem::create($request->validated());

        return response()->json([
            'data' => $item,
            'message' => 'Asset item created successfully.',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $item = AssetItem::findOrFail($id);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function update(UpdateAssetItemRequest $request, string $id): JsonResponse
    {
        $item = AssetItem::findOrFail($id);
        $item->update($request->validated());

        return response()->json([
            'data' => $item,
            'message' => 'Asset item updated successfully.',
        ]);
    }
}
