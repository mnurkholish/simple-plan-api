<?php

use App\Jobs\RunBackup;
use App\Models\BackupHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('backup');
});

function actingAsBackupCreator(bool $canCreate = true): User
{
    $user = User::factory()->create();
    $permission = Permission::firstOrCreate([
        'name' => 'backups-create',
        'guard_name' => 'web',
    ]);

    if ($canCreate) {
        $user->givePermissionTo($permission);
    }

    Sanctum::actingAs($user);

    return $user;
}

test('pengguna yang diizinkan dapat membuat backup manual', function (): void {
    $actor = actingAsBackupCreator();

    $response = $this->postJson('/api/v1/backups');

    $response->assertAccepted()
        ->assertJsonPath('message', 'Backup berhasil dimasukkan ke antrean.')
        ->assertJsonPath('data.type', 'manual')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.performed_by.id', $actor->id)
        ->assertJsonPath('data.file_available', false);

    $this->assertDatabaseCount('backup_histories', 1);
    $history = BackupHistory::query()->firstOrFail();
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'type' => 'manual',
        'status' => 'pending',
        'performed_by_id' => $actor->id,
        'object_key' => null,
    ]);
    expect($response->json('data.id'))->toBe($history->id);

    Queue::assertPushed(RunBackup::class, function (RunBackup $job) use ($history): bool {
        return $job->historyId === $history->id
            && $job->connection === 'backup'
            && $job->queue === 'backups';
    });
    Queue::assertPushedOnce(RunBackup::class);
});

test('backup baru ditolak ketika backup lain masih menunggu atau berjalan', function (string $status): void {
    actingAsBackupCreator();
    $existing = BackupHistory::create([
        'type' => 'automatic',
        'status' => $status,
    ]);

    $this->postJson('/api/v1/backups')
        ->assertConflict()
        ->assertJsonPath('message', 'Masih ada proses backup yang menunggu atau berjalan.');

    $this->assertDatabaseCount('backup_histories', 1);
    expect($existing->refresh()->status)->toBe($status);
    Queue::assertNothingPushed();
})->with([
    'menunggu' => 'pending',
    'berjalan' => 'processing',
]);

test('pengguna tanpa izin tidak dapat membuat backup manual', function (): void {
    actingAsBackupCreator(false);

    $this->postJson('/api/v1/backups')->assertForbidden();

    $this->assertDatabaseCount('backup_histories', 0);
    Queue::assertNothingPushed();
});
