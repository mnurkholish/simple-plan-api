<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MaintenanceStatus;
use App\Models\AssetMaintenance;
use App\Notifications\AssetMaintenanceOverdueNotification;
use App\Notifications\AssetMaintenanceReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CheckMaintenanceStatus extends Command
{
    protected $signature = 'maintenance:check-status';

    protected $description = 'Periksa status jadwal pemeliharaan aset (overdue & reminder notifikasi)';

    public function handle(): int
    {
        $today = today()->toDateString();
        $h1 = today()->addDay()->toDateString();
        $h3 = today()->addDays(3)->toDateString();

        $overdueSchedules = AssetMaintenance::query()
            ->where('is_active', true)
            ->where('status', MaintenanceStatus::Terjadwal)
            ->whereDate('next_scheduled_date', '<', $today)
            ->with(['creator', 'assetCode.assetItem'])
            ->get();

        $overdueCount = 0;
        foreach ($overdueSchedules as $schedule) {
            $schedule->update([
                'status' => MaintenanceStatus::Terlambat,
            ]);

            if ($schedule->creator) {
                $schedule->creator->notify(new AssetMaintenanceOverdueNotification($schedule));
            }

            $overdueCount++;
        }

        $reminderSchedules = AssetMaintenance::query()
            ->where('is_active', true)
            ->where('status', MaintenanceStatus::Terjadwal)
            ->where(function (Builder $query) use ($today, $h1, $h3): void {
                $query->whereDate('next_scheduled_date', $today)
                    ->orWhereDate('next_scheduled_date', $h1)
                    ->orWhereDate('next_scheduled_date', $h3);
            })
            ->with(['assignedOfficer', 'assetCode.assetItem'])
            ->get();

        $reminderCount = 0;
        foreach ($reminderSchedules as $schedule) {
            $scheduleDate = $schedule->next_scheduled_date?->format('Y-m-d');
            $reminderType = match ($scheduleDate) {
                $today => 'hari-h',
                $h1 => 'h-1',
                $h3 => 'h-3',
                default => null,
            };

            if ($reminderType !== null && $schedule->assignedOfficer) {
                $schedule->assignedOfficer->notify(
                    new AssetMaintenanceReminderNotification($schedule, $reminderType)
                );
                $reminderCount++;
            }
        }

        $this->info("Pemeriksaan jadwal pemeliharaan selesai: {$overdueCount} jadwal diperbarui menjadi terlambat, {$reminderCount} pengingat terkirim.");

        return self::SUCCESS;
    }
}
