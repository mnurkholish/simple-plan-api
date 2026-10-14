<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchAssetsRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetCode;
use App\Services\AssetCodeService;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('assetCode', function ($qCode) use ($search) {
                        $qCode->where('full_code', 'like', "%{$search}%")
                            ->orWhereHas('item', function ($qItem) use ($search) {
                                $qItem->where('name', 'like', "%{$search}%")
                                    ->orWhere('brand', 'like', "%{$search}%");
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
        $request->validate([
            'asset_classification_id' => 'required|integer|exists:asset_classifications,id',
            'asset_location_id' => 'required|integer|exists:asset_locations,id',
            'asset_item_id' => 'required|integer|exists:asset_items,id',
            'year' => 'required|integer',
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

        $asset = DB::transaction(function () use ($request) {
            $codeData = $this->assetCodeService->generate(
                (int) $request->asset_classification_id,
                (int) $request->asset_location_id,
                (int) $request->asset_item_id,
                (int) $request->purchase_year
            );

            $assetCode = AssetCode::create([
                'asset_classification_id' => $request->asset_classification_id,
                'asset_location_id' => $request->asset_location_id,
                'asset_item_id' => $request->asset_item_id,
                'sequence_number' => $codeData['sequence_number'],
                'year' => $request->purchase_year,
                'full_code' => $codeData['full_code'],
                'is_assigned' => true,
            ]);

            $assetData = [
                'asset_code_id' => $assetCode->id,
                'serial_number' => $request->serial_number,
                'purchase_year' => $request->purchase_year,
                'status' => $request->status,
                'condition' => $request->condition,
                'warranty_until' => $request->warranty_until,
                'notes' => $request->notes,
            ];

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $assetData['photo_object_key'] = $file->store('assets/photos');
                $assetData['photo_original_name'] = $file->getClientOriginalName();
                $assetData['photo_mime_type'] = $file->getMimeType();
                $assetData['photo_size'] = $file->getSize();
            }

            if ($request->hasFile('calibration_document')) {
                $file = $request->file('calibration_document');
                $assetData['calibration_object_key'] = $file->store('assets/calibrations');
                $assetData['calibration_original_name'] = $file->getClientOriginalName();
                $assetData['calibration_mime_type'] = $file->getMimeType();
                $assetData['calibration_size'] = $file->getSize();
            }

            return Asset::create($assetData);
        });

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

        $asset = DB::transaction(function () use ($request, $asset) {
            $validated = $request->validated();
            $assetCode = $asset->assetCode;

            if ($assetCode) {
                $newClassId = isset($validated['asset_classification_id'])
                    ? (int) $validated['asset_classification_id']
                    : (int) $assetCode->asset_classification_id;
                $newLocId = isset($validated['asset_location_id'])
                    ? (int) $validated['asset_location_id']
                    : (int) $assetCode->asset_location_id;
                $newItemId = isset($validated['asset_item_id'])
                    ? (int) $validated['asset_item_id']
                    : (int) $assetCode->asset_item_id;
                $newYear = isset($validated['purchase_year'])
                    ? (int) $validated['purchase_year']
                    : (int) $assetCode->year;

                if (
                    $newClassId !== (int) $assetCode->asset_classification_id ||
                    $newLocId !== (int) $assetCode->asset_location_id ||
                    $newItemId !== (int) $assetCode->asset_item_id ||
                    $newYear !== (int) $assetCode->year
                ) {
                    $codeData = $this->assetCodeService->generate(
                        $newClassId,
                        $newLocId,
                        $newItemId,
                        $newYear,
                        $assetCode->id
                    );

                    $assetCode->update([
                        'asset_classification_id' => $newClassId,
                        'asset_location_id' => $newLocId,
                        'asset_item_id' => $newItemId,
                        'year' => $newYear,
                        'sequence_number' => $codeData['sequence_number'],
                        'full_code' => $codeData['full_code'],
                    ]);
                }
            }

            $assetData = $validated;
            unset(
                $assetData['_method'],
                $assetData['asset_classification_id'],
                $assetData['asset_location_id'],
                $assetData['asset_item_id'],
                $assetData['photo'],
                $assetData['calibration_document']
            );

            if ($request->hasFile('photo')) {
                if ($asset->photo_object_key) {
                    Storage::delete($asset->photo_object_key);
                }
                $file = $request->file('photo');
                $assetData['photo_object_key'] = $file->store('assets/photos');
                $assetData['photo_original_name'] = $file->getClientOriginalName();
                $assetData['photo_mime_type'] = $file->getMimeType();
                $assetData['photo_size'] = $file->getSize();
            }

            if ($request->hasFile('calibration_document')) {
                if ($asset->calibration_object_key) {
                    Storage::delete($asset->calibration_object_key);
                }
                $file = $request->file('calibration_document');
                $assetData['calibration_object_key'] = $file->store('assets/calibrations');
                $assetData['calibration_original_name'] = $file->getClientOriginalName();
                $assetData['calibration_mime_type'] = $file->getMimeType();
                $assetData['calibration_size'] = $file->getSize();
            }

            $asset->update($assetData);

            return $asset;
        });

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
            $asset->assetCode?->delete();
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
