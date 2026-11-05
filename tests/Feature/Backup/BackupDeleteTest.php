<?php

use App\Models\BackupHistory;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('backup');
});

function actingAsBackupDeleter(bool $canDelete = true): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate([
        'name' => 'super-admin',
        'guard_name' => 'web',
    ]));

    $permission = Permission::firstOrCreate([
        'name' => 'backups-delete',
        'guard_name' => 'web',
    ]);

    if ($canDelete) {
        $user->givePermissionTo($permission);
    }

    Sanctum::actingAs($user);

    return $user;
}

test('super admin dapat menghapus file backup tanpa menghapus riwayat', function (): void {
    $actor = actingAsBackupDeleter();
    $objectKey = 'simple-plan/backup-sukses.zip';
    $contents = 'arsip-backup';
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

    $this->deleteJson("/api/v1/backups/{$history->id}")
        ->assertNoContent();

    Storage::disk('backup')->assertMissing($objectKey);
    $this->assertDatabaseCount('backup_histories', 1);
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'type' => 'manual',
        'performed_by_id' => $actor->id,
        'status' => 'success',
        'file_name' => 'backup-sukses.zip',
        'file_size' => strlen($contents),
        'disk' => null,
        'object_key' => null,
    ]);
});

test('backup yang belum berhasil tidak dapat dihapus', function (string $status): void {
    actingAsBackupDeleter();
    $objectKey = 'simple-plan/backup-belum-selesai.zip';
    Storage::disk('backup')->put($objectKey, 'arsip-backup');
    $history = BackupHistory::create([
        'type' => 'automatic',
        'status' => $status,
        'file_name' => 'backup-belum-selesai.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => 12,
    ]);

    $this->deleteJson("/api/v1/backups/{$history->id}")
        ->assertConflict();

    Storage::disk('backup')->assertExists($objectKey);
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => $status,
        'disk' => 'backup',
        'object_key' => $objectKey,
    ]);
})->with([
    'menunggu' => 'pending',
    'berjalan' => 'processing',
    'gagal' => 'failed',
]);

test('pengguna tanpa izin hapus tidak dapat menghapus backup', function (): void {
    actingAsBackupDeleter(false);
    $objectKey = 'simple-plan/backup-terlindungi.zip';
    Storage::disk('backup')->put($objectKey, 'arsip-backup');
    $history = BackupHistory::create([
        'type' => 'automatic',
        'status' => 'success',
        'file_name' => 'backup-terlindungi.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => 12,
    ]);

    $this->deleteJson("/api/v1/backups/{$history->id}")
        ->assertForbidden();

    Storage::disk('backup')->assertExists($objectKey);
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => 'success',
        'disk' => 'backup',
        'object_key' => $objectKey,
    ]);
});

test('kegagalan menghapus file backup di storage mengembalikan 500', function (): void {
    actingAsBackupDeleter();
    $objectKey = 'simple-plan/backup-gagal-dihapus.zip';
    $fakeDisk = Storage::disk('backup');
    $fakeDisk->put($objectKey, 'arsip-backup');
    $history = BackupHistory::create([
        'type' => 'automatic',
        'status' => 'success',
        'file_name' => 'backup-gagal-dihapus.zip',
        'disk' => 'backup',
        'object_key' => $objectKey,
        'file_size' => 12,
    ]);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('delete')->once()->with($objectKey)->andReturn(false);
    Storage::shouldReceive('disk')->once()->with('backup')->andReturn($disk);

    $this->deleteJson("/api/v1/backups/{$history->id}")
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Gagal menghapus file backup.');

    expect($fakeDisk->exists($objectKey))->toBeTrue();
    $this->assertDatabaseHas('backup_histories', [
        'id' => $history->id,
        'status' => 'success',
        'file_name' => 'backup-gagal-dihapus.zip',
        'file_size' => 12,
        'disk' => 'backup',
        'object_key' => $objectKey,
    ]);
});
