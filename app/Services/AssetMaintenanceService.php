<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceLog;
use App\Models\AssetMaintenanceOccurrence;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssetMaintenanceService
{
    /**
     * Calculate the next scheduled date based on the current date and period.
     */
    public function calculateNextScheduledDate(
        Carbon|string|\DateTimeInterface $currentDate,
        MaintenancePeriod|string $period
    ): Carbon {
        $date = $currentDate instanceof Carbon ? $currentDate->copy() : Carbon::parse($currentDate);
        $periodValue = $period instanceof MaintenancePeriod ? $period->value : $period;

        return match ($periodValue) {
            MaintenancePeriod::Weekly->value, 'weekly' => $date->addWeek(),
            MaintenancePeriod::Monthly->value, 'monthly' => $date->addMonth(),
            MaintenancePeriod::Quarterly->value, 'quarterly' => $date->addMonths(3),
            MaintenancePeriod::Semiannually->value, 'semiannually' => $date->addMonths(6),
            MaintenancePeriod::Yearly->value, 'yearly' => $date->addYear(),
            default => throw new \InvalidArgumentException("Periode pemeliharaan tidak valid: {$periodValue}"),
        };
    }

    /**
     * Create a new maintenance schedule and synchronize its initial occurrence.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSchedule(array $data): AssetMaintenance
    {
        return DB::transaction(function () use ($data): AssetMaintenance {
            $data['status'] = $data['status'] ?? MaintenanceStatus::Terjadwal->value;
            $data['is_active'] = $data['is_active'] ?? true;

            /** @var AssetMaintenance $schedule */
            $schedule = AssetMaintenance::create($data);

            $this->syncScheduleOccurrence($schedule);

            return $schedule;
        });
    }

    /**
     * Update an existing maintenance schedule and synchronize its active occurrence.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSchedule(AssetMaintenance $schedule, array $data): AssetMaintenance
    {
        return DB::transaction(function () use ($schedule, $data): AssetMaintenance {
            $previousScheduledDate = $this->normalizeDateString($schedule->next_scheduled_date);

            $schedule->update($data);
            $schedule->refresh();

            $this->syncScheduleOccurrence($schedule, $previousScheduledDate);

            return $schedule;
        });
    }

    /**
     * Execute a maintenance schedule and record its execution log.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $photoData
     */
    public function executeMaintenance(
        AssetMaintenance $schedule,
        array $data,
        array $photoData = [],
        ?int $actorId = null
    ): AssetMaintenanceLog {
        return DB::transaction(function () use ($schedule, $data, $photoData, $actorId): AssetMaintenanceLog {
            $oldScheduledDate = $this->normalizeDateString($schedule->next_scheduled_date)
                ?? today(config('app.operational_timezone'))->toDateString();
            $postCondition = $this->resolvePostMaintenanceCondition($data['post_maintenance_condition'] ?? null);

            /** @var AssetMaintenanceLog $log */
            $log = $schedule->logs()->create([
                'scheduled_date' => $oldScheduledDate,
                'work_description' => $data['work_description'] ?? null,
                'maintenance_result' => $data['maintenance_result'] ?? null,
                'post_maintenance_condition' => $postCondition?->value,
                'started_at' => isset($data['started_at']) ? CarbonImmutable::parse($data['started_at'])->utc() : null,
                'completed_at' => isset($data['completed_at']) ? CarbonImmutable::parse($data['completed_at'])->utc() : null,
                'proof_photo_object_key' => $photoData['proof_photo_object_key'] ?? $data['proof_photo_object_key'] ?? null,
                'proof_photo_original_name' => $photoData['proof_photo_original_name'] ?? $data['proof_photo_original_name'] ?? null,
                'proof_photo_mime_type' => $photoData['proof_photo_mime_type'] ?? $data['proof_photo_mime_type'] ?? null,
                'proof_photo_size' => $photoData['proof_photo_size'] ?? $data['proof_photo_size'] ?? null,
            ]);

            $this->syncAssetCondition($schedule, $postCondition, $actorId);

            $this->recordOccurrenceRealization($schedule, $log);

            $newScheduledDate = $this->calculateNextScheduledDate($oldScheduledDate, $schedule->period);

            $schedule->update([
                'status' => MaintenanceStatus::Selesai,
                'next_scheduled_date' => $newScheduledDate->toDateString(),
                'last_executed_at' => now(),
            ]);

            $schedule->refresh();
            $this->syncScheduleOccurrence($schedule);

            return $log;
        });
    }

    /**
     * Idempotently synchronize the current cycle occurrence for a maintenance schedule.
     */
    public function syncScheduleOccurrence(
        AssetMaintenance $schedule,
        ?string $previousScheduledDate = null,
        CarbonInterface|string|null $asOf = null
    ): ?AssetMaintenanceOccurrence {
        $scheduledDate = $this->normalizeDateString($schedule->next_scheduled_date);
        if ($scheduledDate === null) {
            return null;
        }

        $referenceDate = $this->resolveReferenceDate($asOf);
        $period = $schedule->period instanceof MaintenancePeriod
            ? $schedule->period
            : MaintenancePeriod::from((string) $schedule->period);

        /** @var AssetMaintenanceOccurrence|null $targetOccurrence */
        $targetOccurrence = $schedule->occurrences()
            ->whereDate('scheduled_date', $scheduledDate)
            ->first();

        /** @var AssetMaintenanceOccurrence|null $previousOccurrence */
        $previousOccurrence = null;
        if ($previousScheduledDate !== null && $previousScheduledDate !== $scheduledDate) {
            $previousOccurrence = $schedule->occurrences()
                ->whereDate('scheduled_date', $previousScheduledDate)
                ->pending()
                ->first();
        }

        if (! $schedule->is_active) {
            if ($targetOccurrence !== null) {
                if (! $targetOccurrence->isRealized()) {
                    $targetOccurrence->update([
                        'asset_code_id' => $schedule->asset_code_id,
                        'period' => $period,
                    ]);
                }

                if ($previousOccurrence !== null && $previousOccurrence->id !== $targetOccurrence->id) {
                    $previousOccurrence->delete();
                }

                return $targetOccurrence->fresh();
            }

            if ($previousOccurrence !== null) {
                $previousOccurrence->update([
                    'asset_code_id' => $schedule->asset_code_id,
                    'period' => $period,
                    'scheduled_date' => $scheduledDate,
                ]);

                return $previousOccurrence->fresh();
            }

            return null;
        }

        $targetStatus = $this->determineOpenOccurrenceStatus($scheduledDate, $referenceDate);

        if ($targetOccurrence !== null) {
            if ($previousOccurrence !== null && $previousOccurrence->id !== $targetOccurrence->id) {
                $previousOccurrence->delete();
            }

            if (! $targetOccurrence->isRealized()) {
                $targetOccurrence->update([
                    'asset_code_id' => $schedule->asset_code_id,
                    'period' => $period,
                    'status' => $targetStatus,
                ]);
            }

            return $targetOccurrence->fresh();
        }

        if ($previousOccurrence !== null) {
            $previousOccurrence->update([
                'asset_code_id' => $schedule->asset_code_id,
                'period' => $period,
                'scheduled_date' => $scheduledDate,
                'status' => $targetStatus,
                'is_late' => false,
            ]);

            return $previousOccurrence->fresh();
        }

        /** @var AssetMaintenanceOccurrence $occurrence */
        $occurrence = AssetMaintenanceOccurrence::query()->create([
            'asset_maintenance_id' => $schedule->id,
            'asset_code_id' => $schedule->asset_code_id,
            'period' => $period,
            'scheduled_date' => $scheduledDate,
            'status' => $targetStatus,
            'is_late' => false,
        ]);

        return $occurrence;
    }

    /**
     * Link a recorded maintenance execution log to its corresponding occurrence idempotently.
     */
    public function recordOccurrenceRealization(
        AssetMaintenance $schedule,
        AssetMaintenanceLog $log
    ): AssetMaintenanceOccurrence {
        $scheduledDate = $this->normalizeDateString($log->scheduled_date)
            ?? $this->normalizeDateString($schedule->next_scheduled_date)
            ?? today(config('app.operational_timezone'))->toDateString();

        $realizedAt = $log->completed_at instanceof CarbonInterface
            ? Carbon::instance($log->completed_at)
            : ($log->completed_at ? Carbon::parse($log->completed_at) : now());

        $isLate = $this->isRealizedLate($scheduledDate, $realizedAt);

        $period = $schedule->period instanceof MaintenancePeriod
            ? $schedule->period
            : MaintenancePeriod::from((string) $schedule->period);

        /** @var AssetMaintenanceOccurrence|null $occurrence */
        $occurrence = $schedule->occurrences()
            ->whereDate('scheduled_date', $scheduledDate)
            ->first();

        if ($occurrence !== null) {
            $occurrence->update([
                'asset_code_id' => $schedule->asset_code_id,
                'asset_maintenance_log_id' => $log->id,
                'period' => $occurrence->period ?? $period,
                'status' => MaintenanceStatus::Selesai,
                'realized_at' => $realizedAt,
                'is_late' => $isLate,
            ]);

            return $occurrence->fresh();
        }

        /** @var AssetMaintenanceOccurrence $created */
        $created = AssetMaintenanceOccurrence::query()->create([
            'asset_maintenance_id' => $schedule->id,
            'asset_code_id' => $schedule->asset_code_id,
            'asset_maintenance_log_id' => $log->id,
            'period' => $period,
            'scheduled_date' => $scheduledDate,
            'status' => MaintenanceStatus::Selesai,
            'realized_at' => $realizedAt,
            'is_late' => $isLate,
        ]);

        return $created;
    }

    /**
     * Transition open occurrences of active schedules past the reference date to overdue.
     */
    public function markOverdueOccurrences(CarbonInterface|string|null $asOf = null): int
    {
        $referenceDate = $this->resolveReferenceDate($asOf)->toDateString();

        return AssetMaintenanceOccurrence::query()
            ->pending()
            ->where('status', MaintenanceStatus::Terjadwal->value)
            ->whereDate('scheduled_date', '<', $referenceDate)
            ->whereHas('maintenance', fn (Builder $query): Builder => $query->where('is_active', true))
            ->update([
                'status' => MaintenanceStatus::Terlambat->value,
            ]);
    }

    /**
     * Idempotently synchronize all maintenance logs, active schedules, and overdue occurrence statuses.
     *
     * @return array{synced_logs: int, synced_schedules: int, overdue_occurrences: int}
     */
    public function syncAllOccurrences(CarbonInterface|string|null $asOf = null): array
    {
        $referenceDate = $this->resolveReferenceDate($asOf);
        $syncedLogs = 0;
        $syncedSchedules = 0;

        AssetMaintenanceLog::query()
            ->whereDoesntHave('occurrence')
            ->with('maintenance')
            ->orderBy('id')
            ->chunkById(200, function (Collection $logs) use (&$syncedLogs): void {
                foreach ($logs as $log) {
                    if (! $log->maintenance) {
                        continue;
                    }

                    $this->recordOccurrenceRealization($log->maintenance, $log);
                    $syncedLogs++;
                }
            });

        AssetMaintenance::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(200, function (Collection $schedules) use ($referenceDate, &$syncedSchedules): void {
                foreach ($schedules as $schedule) {
                    $this->syncScheduleOccurrence($schedule, null, $referenceDate);
                    $syncedSchedules++;
                }
            });

        $overdueOccurrences = $this->markOverdueOccurrences($referenceDate);

        return [
            'synced_logs' => $syncedLogs,
            'synced_schedules' => $syncedSchedules,
            'overdue_occurrences' => $overdueOccurrences,
        ];
    }

    public function determineOpenOccurrenceStatus(
        CarbonInterface|string $scheduledDate,
        CarbonInterface|string|null $asOf = null
    ): MaintenanceStatus {
        $scheduled = $scheduledDate instanceof CarbonInterface
            ? Carbon::instance($scheduledDate)->startOfDay()
            : Carbon::parse($scheduledDate)->startOfDay();

        $referenceDate = $this->resolveReferenceDate($asOf);

        return $scheduled->lt($referenceDate)
            ? MaintenanceStatus::Terlambat
            : MaintenanceStatus::Terjadwal;
    }

    public function isRealizedLate(
        CarbonInterface|string $scheduledDate,
        CarbonInterface|string $realizedAt
    ): bool {
        $scheduled = $scheduledDate instanceof CarbonInterface
            ? Carbon::instance($scheduledDate)->startOfDay()
            : Carbon::parse($scheduledDate)->startOfDay();

        $realized = $realizedAt instanceof CarbonInterface
            ? Carbon::instance($realizedAt)->setTimezone(config('app.operational_timezone'))->startOfDay()
            : Carbon::parse($realizedAt)->setTimezone(config('app.operational_timezone'))->startOfDay();

        return $realized->gt($scheduled);
    }

    protected function resolvePostMaintenanceCondition(AssetCondition|string|null $condition): ?AssetCondition
    {
        if ($condition === null || $condition === '') {
            return null;
        }

        if ($condition instanceof AssetCondition) {
            return $condition;
        }

        return AssetCondition::tryFrom($condition)
            ?? throw new \InvalidArgumentException("Kondisi pasca pemeliharaan tidak valid: {$condition}");
    }

    protected function syncAssetCondition(
        AssetMaintenance $schedule,
        ?AssetCondition $newCondition,
        ?int $actorId = null
    ): void {
        if ($newCondition === null || $schedule->asset_code_id === null) {
            return;
        }

        $asset = Asset::query()
            ->where('asset_code_id', $schedule->asset_code_id)
            ->lockForUpdate()
            ->first();

        if ($asset === null) {
            return;
        }

        $oldCondition = $asset->condition instanceof AssetCondition
            ? $asset->condition->value
            : ($asset->condition !== null ? (string) $asset->condition : null);

        if ($oldCondition === $newCondition->value) {
            return;
        }

        $asset->update([
            'condition' => $newCondition->value,
        ]);

        $asset->histories()->create([
            'event_type' => 'updated',
            'old_values' => ['condition' => $oldCondition],
            'new_values' => ['condition' => $newCondition->value],
            'is_location_change' => false,
            'changed_by' => $actorId ?? auth()->id() ?? $schedule->assigned_officer_id,
            'created_at' => now(),
        ]);
    }

    protected function normalizeDateString(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date) && trim($date) !== '') {
            return Carbon::parse($date)->toDateString();
        }

        return null;
    }

    protected function resolveReferenceDate(CarbonInterface|string|null $asOf = null): Carbon
    {
        if ($asOf instanceof CarbonInterface) {
            return Carbon::instance($asOf)->startOfDay();
        }

        if (is_string($asOf) && trim($asOf) !== '') {
            return Carbon::parse($asOf)->startOfDay();
        }

        return today(config('app.operational_timezone'))->startOfDay();
    }
}
