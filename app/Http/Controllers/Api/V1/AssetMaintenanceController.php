<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetMaintenanceRequest;
use App\Http\Requests\UpdateAssetMaintenanceRequest;
use App\Models\AssetMaintenance;
use App\Notifications\AssetMaintenanceReminderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AssetMaintenance::query()
            ->with([
                'assetCode.assetItem',
                'assetCode.classification',
                'assetCode.location',
                'assignedOfficer',
                'creator',
            ]);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('assetCode', function (Builder $codeQuery) use ($search): void {
                    $codeQuery->where('full_code', 'like', "%{$search}%")
                        ->orWhereHas('assetItem', fn (Builder $itemQuery) => $itemQuery->where('name', 'like', "%{$search}%"));
                })
                ->orWhereHas('assignedOfficer', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhere('maintenance_type', 'like', "%{$search}%");
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('next_scheduled_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('next_scheduled_date', '<=', $dateTo);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return response()->json(
            $query->orderBy('next_scheduled_date')->paginate($perPage)
        );
    }

    public function store(StoreAssetMaintenanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['creator_id'] = $request->user()?->id;
        $validated['status'] = $validated['status'] ?? MaintenanceStatus::Terjadwal->value;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $maintenance = AssetMaintenance::create($validated);
        $maintenance->load([
            'assetCode.assetItem',
            'assetCode.classification',
            'assetCode.location',
            'assignedOfficer',
            'creator',
        ]);

        $this->notifyOfficerIfScheduledToday($maintenance);

        return response()->json([
            'data' => $maintenance,
            'message' => 'Jadwal pemeliharaan aset berhasil dibuat.',
        ], 201);
    }

    public function show(mixed $assetMaintenance): JsonResponse
    {
        $maintenance = $this->resolveMaintenance($assetMaintenance);

        $maintenance->loadMissing([
            'assetCode.assetItem',
            'assetCode.classification',
            'assetCode.location',
            'assignedOfficer',
            'creator',
            'logs' => fn ($query) => $query->latest('id'),
        ]);

        return response()->json([
            'data' => $maintenance,
        ]);
    }

    public function update(UpdateAssetMaintenanceRequest $request, mixed $assetMaintenance): JsonResponse
    {
        $maintenance = $this->resolveMaintenance($assetMaintenance);

        $maintenance->update($request->validated());
        $maintenance->load([
            'assetCode.assetItem',
            'assetCode.classification',
            'assetCode.location',
            'assignedOfficer',
            'creator',
        ]);

        $this->notifyOfficerIfScheduledToday($maintenance);

        return response()->json([
            'data' => $maintenance,
            'message' => 'Jadwal pemeliharaan aset berhasil diperbarui.',
        ]);
    }

    protected function notifyOfficerIfScheduledToday(AssetMaintenance $maintenance): void
    {
        $isTerjadwal = ($maintenance->status === MaintenanceStatus::Terjadwal)
            || (($maintenance->status?->value ?? $maintenance->status) === MaintenanceStatus::Terjadwal->value);

        if (
            $maintenance->is_active &&
            $isTerjadwal &&
            $maintenance->next_scheduled_date?->isToday() &&
            $maintenance->assignedOfficer
        ) {
            $maintenance->assignedOfficer->notify(
                new AssetMaintenanceReminderNotification($maintenance, 'hari-h')
            );
        }
    }

    protected function resolveMaintenance(mixed $assetMaintenance): AssetMaintenance
    {
        if ($assetMaintenance instanceof AssetMaintenance) {
            return $assetMaintenance;
        }

        return AssetMaintenance::findOrFail($assetMaintenance);
    }
}
