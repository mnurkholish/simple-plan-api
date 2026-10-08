<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchAssetsRequest;
use App\Http\Resources\AssetResource;
use App\Services\AssetService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

use App\Http\Requests\StoreAssetRequest;
use App\Models\Asset;
use App\Repositories\Contracts\AssetRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Http\Requests\UpdateAssetRequest;
use App\Models\AssetMutation;

class AssetController extends Controller
{
    public function __construct(
        protected AssetService $assetService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::with(['classification', 'location', 'item']);

        if ($request->filled('location_id')) {
            $query->where('asset_location_id', $request->location_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('classification_id')) {
            $query->where('asset_classification_id', $request->classification_id);
        }

        if ($request->boolean('mutated_last_3_months')) {
            $query->whereHas('mutations', function ($q) {
                $q->where('mutation_date', '>=', now()->subMonths(3));
            });
        }

        return AssetResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $asset->load([
            'classification',
            'location',
            'item',
            'mutations' => function ($query) {
                $query->with(['oldLocation', 'newLocation'])->orderBy('mutation_date', 'desc');
            }
        ]);

        return response()->json(new AssetResource($asset));
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

    public function store(StoreAssetRequest $request, AssetRepositoryInterface $repository): JsonResponse
    {
        $this->authorize('create', [Asset::class, $request->asset_classification_id]);

        $validated = $request->validated();

        $codeData = $this->assetService->generateInventoryNumber(
            $validated['asset_classification_id'],
            $validated['asset_location_id'],
            $validated['asset_item_id'],
            $validated['purchase_year']
        );

        $validated['inventory_number'] = $codeData['inventory_number'];
        $validated['sequence_number'] = $codeData['sequence_number'];

        if ($request->hasFile('photo_path')) {
            $validated['photo_path'] = $request->file('photo_path')->store('assets/photos', 'public');
        }

        if ($request->hasFile('calibration_document_path')) {
            $validated['calibration_document_path'] = $request->file('calibration_document_path')->store('assets/documents', 'public');
        }

        $asset = $repository->create($validated);

        return response()->json(new AssetResource($asset), 201);
    }

    public function update(UpdateAssetRequest $request, Asset $asset, AssetRepositoryInterface $repository): JsonResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validated();
        $isLocationChanged = $asset->asset_location_id !== (int) $validated['asset_location_id'];

        if ($isLocationChanged) {
            $codeData = $this->assetService->generateInventoryNumber(
                $asset->asset_classification_id,
                (int) $validated['asset_location_id'],
                $asset->asset_item_id,
                (int) $asset->purchase_year
            );

            AssetMutation::create([
                'asset_id' => $asset->id,
                'old_location_id' => $asset->asset_location_id,
                'new_location_id' => (int) $validated['asset_location_id'],
                'mutation_date' => now(),
            ]);

            $validated['inventory_number'] = $codeData['inventory_number'];
            $validated['sequence_number'] = $codeData['sequence_number'];
        }

        if ($request->hasFile('photo_path')) {
            $validated['photo_path'] = $request->file('photo_path')->store('assets/photos', 'public');
        }

        if ($request->hasFile('calibration_document_path')) {
            $validated['calibration_document_path'] = $request->file('calibration_document_path')->store('assets/documents', 'public');
        }

        $repository->update($asset, $validated);

        return response()->json(new AssetResource($asset->fresh([
            'classification',
            'location',
            'item',
            'mutations' => function ($query) {
                $query->with(['oldLocation', 'newLocation'])->orderBy('mutation_date', 'desc');
            }
        ])));
    }
}

