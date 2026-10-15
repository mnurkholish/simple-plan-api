<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchAssetsRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Services\AssetCodeService;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function __construct(
        protected AssetService $assetService,
        protected AssetCodeService $assetCodeService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::with(['assetCode.classification', 'assetCode.location', 'assetCode.item']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model_specification', 'like', "%{$search}%")
                    ->orWhere('location_detail', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('assetCode', function ($qCode) use ($search) {
                        $qCode->where('full_code', 'like', "%{$search}%")
                            ->orWhereHas('item', function ($qItem) use ($search) {
                                $qItem->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('location', function ($qLoc) use ($search) {
                                $qLoc->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('classification', function ($qClass) use ($search) {
                                $qClass->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $classificationId = $request->input('asset_classification_id', $request->input('classification_id'));
        if ($classificationId !== null && $classificationId !== '') {
            $query->whereHas('assetCode', function ($q) use ($classificationId) {
                $q->where('asset_classification_id', $classificationId);
            });
        }

        $locationId = $request->input('asset_location_id', $request->input('location_id'));
        if ($locationId !== null && $locationId !== '') {
            $query->whereHas('assetCode', function ($q) use ($locationId) {
                $q->where('asset_location_id', $locationId);
            });
        }

        $itemId = $request->input('asset_item_id', $request->input('item_id'));
        if ($itemId !== null && $itemId !== '') {
            $query->whereHas('assetCode', function ($q) use ($itemId) {
                $q->where('asset_item_id', $itemId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        $year = $request->input('purchase_year', $request->input('year'));
        if ($year !== null && $year !== '') {
            $query->where('purchase_year', $year);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $assets = $query->latest('id')->paginate($perPage);

        return AssetResource::collection($assets);
    }

    public function previewCode(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $request->validate([
            'asset_classification_id' => 'required|integer|exists:asset_classifications,id',
            'asset_location_id' => 'required|integer|exists:asset_locations,id',
            'asset_item_id' => 'required|integer|exists:asset_items,id',
            'year' => 'required|integer|min:1900|max:2100',
        ]);

        $codeData = $this->assetCodeService->generate(
            (int) $request->asset_classification_id,
            (int) $request->asset_location_id,
            (int) $request->asset_item_id,
            (int) $request->year
        );

        return response()->json([
            'data' => [
                'full_code' => $codeData['full_code'],
            ],
        ]);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $asset = $this->assetService->create(
            $request->validated(),
            $request->file('photo'),
            $request->file('calibration_document')
        );
        $asset->load(['assetCode.classification', 'assetCode.location', 'assetCode.item']);

        return response()->json([
            'data' => new AssetResource($asset),
            'message' => 'Asset created successfully.',
        ], 201);
    }

    public function show(Asset $asset): AssetResource
    {
        $this->authorize('view', $asset);
        $asset->load(['assetCode.classification', 'assetCode.location', 'assetCode.item']);

        return new AssetResource($asset);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = $this->assetService->update(
            $asset,
            $request->validated(),
            $request->file('photo'),
            $request->file('calibration_document'),
            $request->user()?->id
        );
        $asset->load(['assetCode.classification', 'assetCode.location', 'assetCode.item']);

        return response()->json([
            'data' => new AssetResource($asset),
            'message' => 'Asset updated successfully.',
        ]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        DB::transaction(function () use ($asset) {
            $asset->delete();
        });

        return response()->json([
            'message' => 'Asset deleted successfully.',
        ]);
    }

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
