<?php

use App\Models\BackupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

function actingAsBackupSettingsUser(string $role = 'super-admin', bool $canSchedule = true): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $permission = Permission::firstOrCreate([
        'name' => 'backups-schedule',
        'guard_name' => 'web',
    ]);

    if ($canSchedule) {
        $user->givePermissionTo($permission);
    }

    Sanctum::actingAs($user);

    return $user;
}

test('super admin dapat melihat pengaturan backup otomatis', function (): void {
    actingAsBackupSettingsUser();

    $this->getJson('/api/v1/backup-settings')
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.frequency', null)
        ->assertJsonPath('data.backup_time', null)
        ->assertJsonPath('data.day_of_week', null)
        ->assertJsonPath('data.day_of_month', null);

    $this->assertDatabaseCount('backup_settings', 0);
    Queue::assertNothingPushed();
});

test('super admin dapat memperbarui pengaturan backup harian', function (): void {
    actingAsBackupSettingsUser();
    BackupSetting::query()->forceCreate([
        'id' => 1,
        'is_active' => true,
        'frequency' => 'weekly',
        'backup_time' => '01:00',
        'day_of_week' => 3,
    ]);

    $this->putJson('/api/v1/backup-settings', [
        'is_active' => true,
        'frequency' => 'daily',
        'backup_time' => '02:30',
    ])->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.frequency', 'daily')
        ->assertJsonPath('data.backup_time', '02:30')
        ->assertJsonPath('data.day_of_week', null)
        ->assertJsonPath('data.day_of_month', null);

    $this->assertDatabaseCount('backup_settings', 1);
    $setting = BackupSetting::query()->findOrFail(1);
    expect($setting->is_active)->toBeTrue()
        ->and($setting->frequency)->toBe('daily')
        ->and(substr($setting->backup_time, 0, 5))->toBe('02:30')
        ->and($setting->day_of_week)->toBeNull()
        ->and($setting->day_of_month)->toBeNull();
    Queue::assertNothingPushed();
});

test('super admin dapat memperbarui pengaturan backup mingguan dan bulanan', function (
    string $frequency,
    string $dayField,
    int $day,
): void {
    actingAsBackupSettingsUser();
    BackupSetting::query()->forceCreate([
        'id' => 1,
        'is_active' => false,
        'frequency' => 'daily',
        'backup_time' => '01:00',
    ]);

    $this->putJson('/api/v1/backup-settings', [
        'is_active' => true,
        'frequency' => $frequency,
        'backup_time' => '03:15',
        $dayField => $day,
    ])->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.frequency', $frequency)
        ->assertJsonPath('data.backup_time', '03:15')
        ->assertJsonPath('data.'.$dayField, $day);

    $this->assertDatabaseCount('backup_settings', 1);
    $setting = BackupSetting::query()->findOrFail(1);
    expect($setting->frequency)->toBe($frequency)
        ->and(substr($setting->backup_time, 0, 5))->toBe('03:15')
        ->and($setting->{$dayField})->toBe($day)
        ->and($setting->{$dayField === 'day_of_week' ? 'day_of_month' : 'day_of_week'})->toBeNull();
    Queue::assertNothingPushed();
})->with([
    'mingguan' => ['weekly', 'day_of_week', 3],
    'bulanan' => ['monthly', 'day_of_month', 15],
]);

test('pengaturan backup dengan data tidak valid ditolak', function (): void {
    actingAsBackupSettingsUser();
    BackupSetting::query()->forceCreate([
        'id' => 1,
        'is_active' => false,
        'frequency' => 'daily',
        'backup_time' => '01:00',
    ]);

    $this->putJson('/api/v1/backup-settings', [
        'is_active' => 'aktif',
        'frequency' => 'weekly',
        'backup_time' => '25:00',
        'day_of_week' => 8,
        'day_of_month' => 15,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['is_active', 'backup_time', 'day_of_week', 'day_of_month']);

    $this->assertDatabaseCount('backup_settings', 1);
    $setting = BackupSetting::query()->findOrFail(1);
    expect($setting->is_active)->toBeFalse()
        ->and($setting->frequency)->toBe('daily')
        ->and(substr($setting->backup_time, 0, 5))->toBe('01:00');
    Queue::assertNothingPushed();
});

test('pengguna tanpa hak akses yang sesuai tidak dapat mengubah pengaturan backup', function (
    string $role,
    bool $canSchedule,
): void {
    actingAsBackupSettingsUser($role, $canSchedule);
    BackupSetting::query()->forceCreate([
        'id' => 1,
        'is_active' => false,
        'frequency' => 'daily',
        'backup_time' => '01:00',
    ]);

    $this->putJson('/api/v1/backup-settings', [
        'is_active' => true,
        'frequency' => 'daily',
        'backup_time' => '02:30',
    ])->assertForbidden();

    $this->assertDatabaseCount('backup_settings', 1);
    $setting = BackupSetting::query()->findOrFail(1);
    expect($setting->is_active)->toBeFalse()
        ->and($setting->frequency)->toBe('daily')
        ->and(substr($setting->backup_time, 0, 5))->toBe('01:00');
    Queue::assertNothingPushed();
})->with([
    'super admin tanpa permission' => ['super-admin', false],
    'pengguna biasa dengan permission' => ['user', true],
]);
