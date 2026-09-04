<?php

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function backupToken(): string
{
    test()->seed();

    return test()->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');
}

test('it returns backup list and schedule config', function (): void {
    Storage::fake('local');
    config()->set('backup.backup.name', 'Laravel');
    config()->set('backup.backup.destination.disks', ['local']);

    Storage::disk('local')->put('Laravel/test-backup.zip', 'backup-content');

    $response = $this->withToken(backupToken())->getJson('/api/v1/backups');

    $response
        ->assertOk()
        ->assertJsonPath('data.backups.0.name', 'test-backup.zip')
        ->assertJsonPath('data.schedule_config.frequency', 'none')
        ->assertJsonStructure([
            'data' => [
                'backups' => [
                    '*' => ['name', 'path', 'size', 'size_bytes', 'date', 'created_at'],
                ],
                'schedule_config' => ['frequency', 'time'],
            ],
        ]);
});

test('it saves backup schedule config', function (): void {
    $scheduleFile = storage_path('framework/testing/backup_schedule.json');
    config()->set('backup.schedule_file', $scheduleFile);

    if (file_exists($scheduleFile)) {
        unlink($scheduleFile);
    }

    $response = $this->withToken(backupToken())->postJson('/api/v1/backups/schedule', [
        'frequency' => 'daily',
        'time' => '01:30',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.frequency', 'daily')
        ->assertJsonPath('data.time', '01:30');

    expect(json_decode((string) file_get_contents($scheduleFile), true))
        ->toMatchArray(['frequency' => 'daily', 'time' => '01:30']);

    unlink($scheduleFile);
});

test('it deletes an existing backup file', function (): void {
    Storage::fake('local');
    config()->set('backup.backup.name', 'Laravel');
    config()->set('backup.backup.destination.disks', ['local']);

    Storage::disk('local')->put('Laravel/delete-me.zip', 'backup-content');

    $response = $this->withToken(backupToken())->deleteJson('/api/v1/backups', [
        'path' => 'Laravel/delete-me.zip',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'File backup berhasil dihapus.');

    Storage::disk('local')->assertMissing('Laravel/delete-me.zip');
});

test('it rejects unsafe backup paths', function (): void {
    $response = $this->withToken(backupToken())->deleteJson('/api/v1/backups', [
        'path' => '../database.sqlite',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('path');
});

test('it delegates backup creation to backup service', function (): void {
    $this->mock(BackupService::class, function ($mock): void {
        $mock->shouldReceive('create')
            ->once()
            ->andReturn(['message' => 'Backup database berhasil dibuat.', 'output' => 'Backup completed.']);
        $mock->shouldReceive('index')
            ->once()
            ->andReturn(['backups' => [], 'schedule_config' => ['frequency' => 'none', 'time' => '00:00']]);
    });

    $response = $this->withToken(backupToken())->postJson('/api/v1/backups');

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Backup database berhasil dibuat.');
});
