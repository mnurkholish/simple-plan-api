<?php

use App\Models\BackupHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('backup');
});

function actingAsBackupDownloader(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate([
        'name' => 'super-admin',
        'guard_name' => 'web',
    ]));

    $permission = Permission::firstOrCreate([
        'name' => 'backups-download',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    Sanctum::actingAs($user);

    return $user;
}

test('super admin dapat mengunduh file backup yang berhasil', function (): void {
    $actor = actingAsBackupDownloader();
    $objectKey = 'simple-plan/backup-sukses.zip';
    $contents = "PK\x03\x04arsip-backup";
    Storage::disk('backup')->put($objectKey, $contents);
    $history = BackupHistory::create([
        'type' => 'manual',
        'performed_by_id' => $actor->id,
        'status' => 'success',
        'file_name' => 'backup-sukses.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => strlen($contents),
    ]);

    $response = $this->get("/api/v1/backups/{$history->id}/download");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/zip')
        ->assertDownload('backup-sukses.zip');
    expect($response->streamedContent())->toBe($contents);

    Storage::disk('backup')->assertExists($objectKey);
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => 'success',
        'disk' => 'backup',
        'object_key' => $objectKey,
    ]);
});

test('file backup yang tidak ditemukan mengembalikan 404', function (): void {
    actingAsBackupDownloader();
    $objectKey = 'simple-plan/backup-hilang.zip';
    $history = BackupHistory::create([
        'type' => 'automatic',
        'status' => 'success',
        'file_name' => 'backup-hilang.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => 1024,
    ]);

    $this->getJson("/api/v1/backups/{$history->id}/download")
        ->assertNotFound();

    Storage::disk('backup')->assertMissing($objectKey);
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => 'success',
        'object_key' => $objectKey,
    ]);
});

test('backup yang belum berhasil tidak dapat diunduh', function (string $status): void {
    actingAsBackupDownloader();
    $objectKey = 'simple-plan/backup-belum-selesai.zip';
    Storage::disk('backup')->put($objectKey, 'arsip-backup');
    $history = BackupHistory::create([
        'type' => 'manual',
        'status' => $status,
        'file_name' => 'backup-belum-selesai.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => 12,
    ]);

    $this->getJson("/api/v1/backups/{$history->id}/download")
        ->assertConflict();

    Storage::disk('backup')->assertExists($objectKey);
    expect($history->refresh()->status)->toBe($status);
})->with([
    'menunggu' => 'pending',
    'berjalan' => 'processing',
    'gagal' => 'failed',
]);

test('guest tidak dapat mengunduh backup', function (): void {
    $objectKey = 'simple-plan/backup-rahasia.zip';
    Storage::disk('backup')->put($objectKey, 'arsip-backup');
    $history = BackupHistory::create([
        'type' => 'automatic',
        'status' => 'success',
        'file_name' => 'backup-rahasia.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => 12,
    ]);

    $this->getJson("/api/v1/backups/{$history->id}/download")
        ->assertUnauthorized();

    Storage::disk('backup')->assertExists($objectKey);
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => 'success',
        'object_key' => $objectKey,
    ]);
});
