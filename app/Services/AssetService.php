<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCode;
use App\Models\AssetHistory;
use App\Models\AssetLocation;
use App\Repositories\AssetRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssetService
{
    public function __construct(
        protected AssetRepository $assetRepository,
        protected AssetCodeService $assetCodeService
    ) {}

    /**
     * @return Collection<int, Asset>
     */
    public function search(int $locationId, ?string $search): Collection
    {
        return $this->assetRepository->search($locationId, $search);
    }

    public function create(array $data, ?UploadedFile $calibration = null): Asset
    {
        $storedKeys = [];
        try {
            return DB::transaction(function () use ($data, $calibration, &$storedKeys): Asset {
                $codeData = $this->assetCodeService->generate(
                    (int) $data['asset_classification_id'],
                    (int) $data['asset_location_id'],
                    (int) $data['asset_item_id'],
                    (int) $data['purchase_year'],
                    true
                );
                $assetCode = AssetCode::create([
                    'asset_classification_id' => $data['asset_classification_id'],
                    'asset_location_id' => $data['asset_location_id'],
                    'asset_item_id' => $data['asset_item_id'],
                    'sequence_number' => $codeData['sequence_number'],
                    'full_code' => $codeData['full_code'],
                ]);

                $assetData = array_intersect_key($data, array_flip([
                    'serial_number', 'purchase_year', 'brand', 'model_specification', 'detail_item', 'location_detail',
                    'status', 'condition', 'warranty_until', 'notes',
                ]));
                $assetData['asset_code_id'] = $assetCode->id;
                $assetData = array_merge($assetData, $this->storeFile($calibration, 'assets/calibrations', 'calibration', $storedKeys));

                return Asset::create($assetData);
            });
        } catch (\Throwable $exception) {
            foreach ($storedKeys as $key) {
                Storage::delete($key);
            }
            throw $exception;
        }
    }

    public function update(Asset $asset, array $data, ?UploadedFile $calibration = null, ?int $actorId = null): Asset
    {
        $storedKeys = [];
        try {
            return DB::transaction(function () use ($asset, $data, $calibration, $actorId, &$storedKeys): Asset {
                $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                $assetCode = $asset->asset_code_id === null ? null
                    : AssetCode::query()->whereKey($asset->asset_code_id)->lockForUpdate()->firstOrFail();
                $oldValues = [];
                $newValues = [];
                $isLocationChange = false;

                if ($assetCode) {
                    $newClassId = (int) ($data['asset_classification_id'] ?? $assetCode->asset_classification_id);
                    $newLocId = (int) ($data['asset_location_id'] ?? $assetCode->asset_location_id);
                    $newItemId = (int) ($data['asset_item_id'] ?? $assetCode->asset_item_id);
                    $newYear = (int) ($data['purchase_year'] ?? $asset->purchase_year);
                    $isLocationChange = $newLocId !== (int) $assetCode->asset_location_id;

                    if ($isLocationChange) {
                        $oldLocationName = $assetCode->location?->name ?? AssetLocation::find($assetCode->asset_location_id)?->name;
                        $newLocationName = AssetLocation::find($newLocId)?->name;

                        if ($oldLocationName !== null && $newLocationName !== null && $oldLocationName !== $newLocationName) {
                            $oldValues['location_name'] = $oldLocationName;
                            $newValues['location_name'] = $newLocationName;
                        }
                    }

                    if ($newClassId !== (int) $assetCode->asset_classification_id
                        || $isLocationChange
                        || $newItemId !== (int) $assetCode->asset_item_id
                        || $newYear !== (int) $asset->purchase_year) {
                        $codeData = $this->assetCodeService->generate(
                            $newClassId, $newLocId, $newItemId, $newYear, true
                        );
                        foreach (['asset_classification_id' => $newClassId, 'asset_location_id' => $newLocId,
                            'asset_item_id' => $newItemId] as $field => $value) {
                            if ((int) $assetCode->{$field} !== $value) {
                                $oldValues[$field] = $assetCode->{$field};
                                $newValues[$field] = $value;
                            }
                        }
                        $oldValues['full_code'] = $assetCode->full_code;
                        $newValues['full_code'] = $codeData['full_code'];
                        $assetCode->update([
                            'asset_classification_id' => $newClassId,
                            'asset_location_id' => $newLocId,
                            'asset_item_id' => $newItemId,
                            'sequence_number' => $codeData['sequence_number'],
                            'full_code' => $codeData['full_code'],
                        ]);
                    }
                }

                $assetData = array_intersect_key($data, array_flip([
                    'serial_number', 'purchase_year', 'brand', 'model_specification', 'detail_item', 'location_detail',
                    'status', 'condition', 'warranty_until', 'notes',
                ]));
                if ($calibration !== null) {
                    if ($asset->calibration_object_key) {
                        $oldKey = $asset->calibration_object_key;
                        DB::afterCommit(fn () => Storage::delete($oldKey));
                    }
                    $assetData = array_merge($assetData, $this->storeFile($calibration, 'assets/calibrations', 'calibration', $storedKeys));
                }

                $asset->fill($assetData);
                foreach ($asset->getDirty() as $field => $value) {
                    $oldValues[$field] = $asset->getRawOriginal($field);
                    $newValues[$field] = $value;
                }
                if ($oldValues !== []) {
                    $asset->save();
                    $asset->histories()->create([
                        'event_type' => 'updated',
                        'old_values' => $oldValues,
                        'new_values' => $newValues,
                        'is_location_change' => $isLocationChange,
                        'changed_by' => $actorId ?? auth()->id(),
                        'created_at' => now(),
                    ]);
                }

                return $asset;
            });
        } catch (\Throwable $exception) {
            foreach ($storedKeys as $key) {
                Storage::delete($key);
            }
            throw $exception;
        }
    }

    private function storeFile(?UploadedFile $file, string $directory, string $prefix, array &$storedKeys): array
    {
        if ($file === null) {
            return [];
        }
        $key = $file->store($directory);
        if ($key === false) {
            throw new \RuntimeException('Asset file could not be stored.');
        }
        $storedKeys[] = $key;

        return [
            "{$prefix}_object_key" => $key,
            "{$prefix}_original_name" => $file->getClientOriginalName(),
            "{$prefix}_mime_type" => $file->getMimeType(),
            "{$prefix}_size" => $file->getSize(),
        ];
    }
}
