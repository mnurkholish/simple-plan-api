<?php

namespace App\Services;

use App\Models\BackupHistory;
use App\Models\BackupSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class BackupSettingService
{
    public function __construct(private readonly BackupService $backups) {}

    public function current(): BackupSetting
    {
        return BackupSetting::find(1) ?? new BackupSetting([
            'is_active' => false,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): BackupSetting
    {
        $now = now();

        BackupSetting::upsert([[
            'id' => 1,
            'is_active' => $data['is_active'],
            'frequency' => $data['frequency'],
            'backup_time' => $data['backup_time'],
            'day_of_week' => $data['frequency'] === 'weekly' ? $data['day_of_week'] : null,
            'day_of_month' => $data['frequency'] === 'monthly' ? $data['day_of_month'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], [
            'is_active', 'frequency', 'backup_time', 'day_of_week', 'day_of_month', 'updated_at',
        ]);

        return BackupSetting::findOrFail(1);
    }

    public function enqueueDue(): ?BackupHistory
    {
        $setting = BackupSetting::find(1);
        $now = CarbonImmutable::now('Asia/Jakarta');

        if (! $setting?->is_active || substr((string) $setting->backup_time, 0, 5) !== $now->format('H:i')) {
            return null;
        }

        $due = match ($setting->frequency) {
            'daily' => true,
            'weekly' => $setting->day_of_week === $now->dayOfWeekIso,
            'monthly' => $setting->day_of_month === $now->day,
            default => false,
        };

        if (! $due || ! Cache::add('backup:scheduled:'.$now->format('YmdHi'), true, now()->addDays(2))) {
            return null;
        }

        return $this->backups->createAutomatic();
    }
}
