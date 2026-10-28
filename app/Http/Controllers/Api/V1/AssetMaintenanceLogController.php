<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetMaintenanceLogRequest;
use App\Models\AssetMaintenance;
use App\Services\AssetMaintenanceService;
use Illuminate\Http\JsonResponse;

class AssetMaintenanceLogController extends Controller
{
    public function __construct(
        protected AssetMaintenanceService $maintenanceService
    ) {}

    public function store(
        StoreAssetMaintenanceLogRequest $request,
        AssetMaintenance $maintenance
    ): JsonResponse {
        if ($request->user()?->id !== $maintenance->assigned_officer_id) {
            abort(403, 'Hanya petugas yang ditugaskan yang dapat mengisi log pemeliharaan.');
        }

        $photoData = [];
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $photoData = [
                'proof_photo_object_key' => $file->store('maintenance_photos', 'public'),
                'proof_photo_original_name' => $file->getClientOriginalName(),
                'proof_photo_mime_type' => $file->getClientMimeType(),
                'proof_photo_size' => $file->getSize(),
            ];
        }

        $log = $this->maintenanceService->executeMaintenance(
            $maintenance,
            $request->validated(),
            $photoData
        );

        return response()->json([
            'data' => $log,
            'message' => 'Log pemeliharaan berhasil dicatat dan jadwal berikutnya telah diperbarui.',
        ], 201);
    }
}
