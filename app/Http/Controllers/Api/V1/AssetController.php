<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAssetsRequest;
use App\Http\Requests\SearchAssetsRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Http\Resources\AssetScanResource;
use App\Models\Asset;
use App\Repositories\AssetRepository;
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

    public function index(ListAssetsRequest $request, AssetRepository $assets): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return AssetResource::collection(
            $assets->filteredQuery($request->validated())->orderByDesc('assets.id')->paginate($perPage)
        );
    }

    public function previewCode(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $request->validate([
            'asset_classification_id' => 'required|integer|exists:asset_classifications,id',
            'asset_location_id' => 'required|integer|exists:asset_locations,id',
            'asset_item_id' => 'required|integer|exists:asset_items,id',
            'year' => 'required|integer|min:1900|max:2100',
        ], [
            'asset_classification_id.required' => 'Klasifikasi aset wajib dipilih.',
            'asset_classification_id.integer' => 'Klasifikasi aset tidak valid.',
            'asset_classification_id.exists' => 'Klasifikasi aset yang dipilih tidak ditemukan.',
            'asset_location_id.required' => 'Lokasi aset wajib dipilih.',
            'asset_location_id.integer' => 'Lokasi aset tidak valid.',
            'asset_location_id.exists' => 'Lokasi aset yang dipilih tidak ditemukan.',
            'asset_item_id.required' => 'Nama barang wajib dipilih.',
            'asset_item_id.integer' => 'Nama barang tidak valid.',
            'asset_item_id.exists' => 'Nama barang yang dipilih tidak ditemukan.',
            'year.required' => 'Tahun pembelian wajib diisi.',
            'year.integer' => 'Tahun pembelian harus berupa angka tahun yang valid.',
            'year.min' => 'Tahun pembelian tidak boleh kurang dari :min.',
            'year.max' => 'Tahun pembelian tidak boleh lebih dari :max.',
        ], [
            'asset_classification_id' => 'klasifikasi aset',
            'asset_location_id' => 'lokasi aset',
            'asset_item_id' => 'nama barang',
            'year' => 'tahun pembelian',
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
            $request->file('calibration_document')
        );
        $asset->load(['assetCode.classification', 'assetCode.location', 'assetCode.item']);

        return response()->json([
            'data' => new AssetResource($asset),
            'message' => 'Aset berhasil ditambahkan.',
        ], 201);
    }

    public function show(Asset $asset): AssetResource
    {
        $this->authorize('view', $asset);
        $asset->load([
            'assetCode.classification',
            'assetCode.location',
            'assetCode.item',
            'histories' => fn ($query) => $query->latest('id')->with(['changedBy', 'changer']),
        ]);

        return new AssetResource($asset);
    }

    public function showByUid(string $uid, AssetRepository $assets): AssetScanResource
    {
        $asset = $assets->findByUidOrFail($uid);
        $this->authorize('view', $asset);

        return new AssetScanResource($asset);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = $this->assetService->update(
            $asset,
            $request->validated(),
            $request->file('calibration_document'),
            $request->user()?->id
        );
        $asset->load(['assetCode.classification', 'assetCode.location', 'assetCode.item']);

        return response()->json([
            'data' => new AssetResource($asset),
            'message' => 'Aset berhasil diperbarui.',
        ]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        DB::transaction(function () use ($asset) {
            $asset->delete();
        });

        return response()->json([
            'message' => 'Aset berhasil dihapus.',
        ]);
    }

    public function search(SearchAssetsRequest $request): AnonymousResourceCollection
    {

        return AssetResource::collection(
            $this->assetService->search(
                $request->integer('location_id'),
                $request->string('search')->toString() ?: null,
            )
        );
    }
}
