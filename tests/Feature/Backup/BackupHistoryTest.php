<?php

use App\Models\BackupHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function actingAsBackupHistoryViewer(): User
{
    $user = User::factory()->create();
    $permission = Permission::firstOrCreate([
        'name' => 'backups-access',
        'guard_name' => 'web',
    ]);
    $user->givePermissionTo($permission);
    Sanctum::actingAs($user);

    return $user;
}

function createBackupHistoryAt(string $type, string $status, string $createdAt, array $attributes = []): BackupHistory
{
    $history = BackupHistory::create([
        'type' => $type,
        'status' => $status,
        ...$attributes,
    ]);
    $history->created_at = CarbonImmutable::parse($createdAt, 'UTC');
    $history->save();

    return $history;
}

test('pengguna yang diizinkan dapat menampilkan semua riwayat backup termasuk yang berhasil dan yang gagal', function (): void {
    $actor = actingAsBackupHistoryViewer();
    $older = createBackupHistoryAt('automatic', 'pending', '2026-10-08 01:00:00');
    $failed = createBackupHistoryAt('manual', 'failed', '2026-10-09 01:00:00', [
        'performed_by_id' => $actor->id,
        'failure_message' => 'Proses backup gagal.',
    ]);
    $latest = createBackupHistoryAt('manual', 'success', '2026-10-10 01:00:00', [
        'performed_by_id' => $actor->id,
        'file_name' => 'backup-terbaru.zip',
        'disk' => 'backup',
        'object_key' => 'simple-plan/backup-terbaru.zip',
        'file_size' => 1024,
    ]);

    $this->getJson('/api/v1/backups?per_page=2&page=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.0.file_available', true)
        ->assertJsonPath('data.1.id', $failed->id)
        ->assertJsonPath('data.1.status', 'failed')
        ->assertJsonPath('data.1.failure_message', 'Proses backup gagal.')
        ->assertJsonPath('data.1.file_available', false)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/backups?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $older->id)
        ->assertJsonPath('meta.current_page', 2);

    expect(BackupHistory::query()->count())->toBe(3);
});

test('riwayat backup dapat difilter berdasarkan tipe status dan tanggal', function (): void {
    actingAsBackupHistoryViewer();
    $matching = createBackupHistoryAt('manual', 'failed', '2026-10-08 17:00:00');
    createBackupHistoryAt('manual', 'failed', '2026-10-09 17:00:00');
    createBackupHistoryAt('automatic', 'failed', '2026-10-09 01:00:00');
    createBackupHistoryAt('manual', 'success', '2026-10-09 02:00:00');

    $response = $this->getJson('/api/v1/backups?type=manual&status=failed&date_from=2026-10-09&date_to=2026-10-09');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('data.0.type', 'manual')
        ->assertJsonPath('data.0.status', 'failed')
        ->assertJsonPath('meta.total', 1);

    expect(BackupHistory::query()->count())->toBe(4);
});

test('filter riwayat backup yang tidak valid mengembalikan 422', function (): void {
    actingAsBackupHistoryViewer();

    $this->getJson('/api/v1/backups?type=lainnya&status=selesai&date_from=2026-10-10&date_to=2026-10-09&page=0&per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'status', 'date_to', 'page', 'per_page']);
});

test('pengguna yang diiainkan dapat melihat detail riwayat backup', function (): void {
    $actor = actingAsBackupHistoryViewer();
    $history = BackupHistory::create([
        'type' => 'manual',
        'performed_by_id' => $actor->id,
        'status' => 'success',
        'file_name' => 'backup-manual.zip',
        'disk' => 'backup',
        'object_key' => 'simple-plan/backup-manual.zip',
        'file_size' => 2048,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $this->getJson("/api/v1/backups/{$history->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $history->id)
        ->assertJsonPath('data.type', 'manual')
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.performed_by.id', $actor->id)
        ->assertJsonPath('data.performed_by.name', $actor->name)
        ->assertJsonPath('data.file_name', 'backup-manual.zip')
        ->assertJsonPath('data.file_size', 2048)
        ->assertJsonPath('data.file_available', true)
        ->assertJsonMissingPath('data.object_key');

    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => 'success',
        'object_key' => 'simple-plan/backup-manual.zip',
    ]);
});

test('detail riwayat backup yang tidak ditemukan mengembalikan 404', function (): void {
    actingAsBackupHistoryViewer();

    $this->getJson('/api/v1/backups/999999')
        ->assertNotFound();
});
