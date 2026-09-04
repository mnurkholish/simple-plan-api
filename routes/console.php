<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$scheduleFile = config('backup.schedule_file', storage_path('app/backup_schedule.json'));

if (file_exists($scheduleFile)) {
    $config = json_decode((string) file_get_contents($scheduleFile), true);

    if (is_array($config) && ($config['frequency'] ?? 'none') !== 'none') {
        $event = Schedule::command('backup:run --only-db --disable-notifications');
        $time = $config['time'] ?? '00:00';

        match ($config['frequency']) {
            'daily' => $event->dailyAt($time),
            'weekly' => $event->weeklyOn(1, $time),
            'monthly' => $event->monthlyOn(1, $time),
            default => null,
        };
    }
}
