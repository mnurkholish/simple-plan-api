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
use Illuminate\Support\Carbon;

class AssetMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetMaintenance::class);

        $query = AssetMaintenance::query()
            ->visibleTo($request->user())
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
        $this->authorize('create', AssetMaintenance::class);

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

        $this->notifyOfficerIfUrgent($maintenance);

        return response()->json([
            'data' => $maintenance,
            'message' => 'Jadwal pemeliharaan aset berhasil dibuat.',
        ], 201);
    }

    public function show(mixed $assetMaintenance): JsonResponse
    {
        $maintenance = $this->resolveMaintenance($assetMaintenance);
        $this->authorize('view', $maintenance);

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
        $this->authorize('update', $maintenance);

        $maintenance->update($request->validated());
        $maintenance->load([
            'assetCode.assetItem',
            'assetCode.classification',
            'assetCode.location',
            'assignedOfficer',
            'creator',
        ]);

        $this->notifyOfficerIfUrgent($maintenance);

        return response()->json([
            'data' => $maintenance,
            'message' => 'Jadwal pemeliharaan aset berhasil diperbarui.',
        ]);
    }

    protected function notifyOfficerIfUrgent(AssetMaintenance $maintenance): void
    {
        $isTerjadwal = ($maintenance->status === MaintenanceStatus::Terjadwal)
            || (($maintenance->status?->value ?? $maintenance->status) === MaintenanceStatus::Terjadwal->value);

        if (! $maintenance->is_active || ! $isTerjadwal || ! $maintenance->assignedOfficer || ! $maintenance->next_scheduled_date) {
            return;
        }

        $today = today();
        $scheduledDate = Carbon::parse($maintenance->next_scheduled_date)->startOfDay();
        $diffDays = (int) $today->diffInDays($scheduledDate, false);

        if ($diffDays >= 0 && $diffDays <= 3) {
            $reminderType = match ($diffDays) {
                0 => 'hari-h',
                1 => 'h-1',
                2 => 'h-2',
                3 => 'h-3',
                default => 'hari-h',
            };

            $maintenance->assignedOfficer->notify(
                new AssetMaintenanceReminderNotification($maintenance, $reminderType, isUrgent: true)
            );
        }
    }

    protected function notifyOfficerIfScheduledToday(AssetMaintenance $maintenance): void
    {
        $this->notifyOfficerIfUrgent($maintenance);
    }

    protected function resolveMaintenance(mixed $assetMaintenance): AssetMaintenance
    {
        if ($assetMaintenance instanceof AssetMaintenance) {
            return $assetMaintenance;
        }

        return AssetMaintenance::findOrFail($assetMaintenance);
    }
}
