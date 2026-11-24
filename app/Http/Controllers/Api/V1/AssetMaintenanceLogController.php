<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetMaintenanceLogRequest;
use App\Models\AssetMaintenance;
use App\Services\AssetMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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
        $photoPath = null;
        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $photoPath = $file->store('maintenance_photos', $disk);
            if ($photoPath === false) {
                throw new RuntimeException('Maintenance photo could not be stored.');
            }
            $photoData = [
                'proof_photo_object_key' => $photoPath,
                'proof_photo_original_name' => $file->getClientOriginalName(),
                'proof_photo_mime_type' => $file->getMimeType(),
                'proof_photo_size' => $file->getSize(),
            ];
        }

        try {
            $log = $this->maintenanceService->executeMaintenance(
                $maintenance,
                $request->validated(),
                $photoData
            );
        } catch (Throwable $exception) {
            if (is_string($photoPath)) {
                try {
                    if (! Storage::disk($disk)->delete($photoPath)) {
                        report(new RuntimeException('Failed maintenance photo could not be cleaned up.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        return response()->json([
            'data' => $log,
            'message' => 'Log pemeliharaan berhasil dicatat dan jadwal berikutnya telah diperbarui.',
        ], 201);
    }
}
