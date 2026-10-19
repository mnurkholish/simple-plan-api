<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetLocationRequest;
use App\Http\Requests\UpdateAssetLocationRequest;
use App\Models\Asset;
use App\Models\AssetLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);
        $query = AssetLocation::query();

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

    public function store(StoreAssetLocationRequest $request): JsonResponse
    {
        $this->authorize('create', Asset::class);
        $location = AssetLocation::create($request->safe()->only(['name']));

        return response()->json([
            'data' => $location,
            'message' => 'Asset location created successfully.',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);
        $location = AssetLocation::findOrFail($id);

        return response()->json([
            'data' => $location,
        ]);
    }

    public function update(UpdateAssetLocationRequest $request, string $id): JsonResponse
    {
        $this->authorize('create', Asset::class);
        $location = AssetLocation::findOrFail($id);
        $location->update($request->safe()->only(['name']));

        return response()->json([
            'data' => $location,
            'message' => 'Asset location updated successfully.',
        ]);
    }
}
