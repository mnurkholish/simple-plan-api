<?php

use App\Services\BackupSettingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(BackupSettingService::class)->enqueueDue())
    ->everyMinute()
    ->timezone('Asia/Jakarta')
    ->name('backup:automatic');

Schedule::command('maintenance:check-status')
    ->daily()
    ->timezone('Asia/Jakarta');
