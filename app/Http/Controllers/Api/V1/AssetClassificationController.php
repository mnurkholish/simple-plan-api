<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetClassificationRequest;
use App\Http\Requests\UpdateAssetClassificationRequest;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssetClassificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);
        $query = AssetClassification::query();

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

    public function store(StoreAssetClassificationRequest $request): JsonResponse
    {
        $this->authorize('create', Asset::class);
        $classification = AssetClassification::create($request->validated());

        return response()->json([
            'data' => $classification,
            'message' => 'Asset classification created successfully.',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);
        $classification = AssetClassification::findOrFail($id);

        return response()->json([
            'data' => $classification,
        ]);
    }

    public function update(UpdateAssetClassificationRequest $request, string $id): JsonResponse
    {
        $this->authorize('create', Asset::class);
        $classification = AssetClassification::findOrFail($id);
        if ((int) $classification->code !== (int) $request->validated('code')
            && AssetCode::where('asset_classification_id', $classification->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode klasifikasi yang telah dipakai aset tidak dapat diubah.']);
        }
        $classification->update($request->validated());

        return response()->json([
            'data' => $classification,
            'message' => 'Asset classification updated successfully.',
        ]);
    }
}
