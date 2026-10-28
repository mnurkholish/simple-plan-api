<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceLog;
use Carbon\Carbon;
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
     * Execute a maintenance schedule and record its execution log.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $photoData
     */
    public function executeMaintenance(
        AssetMaintenance $schedule,
        array $data,
        array $photoData = []
    ): AssetMaintenanceLog {
        return DB::transaction(function () use ($schedule, $data, $photoData): AssetMaintenanceLog {
            $rawDate = $schedule->next_scheduled_date;
            $oldScheduledDate = $rawDate instanceof \DateTimeInterface
                ? $rawDate->format('Y-m-d')
                : (string) $rawDate;

            /** @var AssetMaintenanceLog $log */
            $log = $schedule->logs()->create([
                'scheduled_date' => $oldScheduledDate,
                'work_description' => $data['work_description'] ?? null,
                'maintenance_result' => $data['maintenance_result'] ?? null,
                'post_maintenance_condition' => $data['post_maintenance_condition'] ?? null,
                'started_at' => $data['started_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
                'proof_photo_object_key' => $photoData['proof_photo_object_key'] ?? $data['proof_photo_object_key'] ?? null,
                'proof_photo_original_name' => $photoData['proof_photo_original_name'] ?? $data['proof_photo_original_name'] ?? null,
                'proof_photo_mime_type' => $photoData['proof_photo_mime_type'] ?? $data['proof_photo_mime_type'] ?? null,
                'proof_photo_size' => $photoData['proof_photo_size'] ?? $data['proof_photo_size'] ?? null,
            ]);

            $newScheduledDate = $this->calculateNextScheduledDate($oldScheduledDate, $schedule->period);

            $schedule->update([
                'status' => MaintenanceStatus::Terjadwal,
                'next_scheduled_date' => $newScheduledDate->toDateString(),
            ]);

            return $log;
        });
    }
}
