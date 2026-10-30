<?php

use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\User;
use App\Notifications\AssetMaintenanceOverdueNotification;
use App\Notifications\AssetMaintenanceReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createMaintenanceTestUser(string $role): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->superAdmin = createMaintenanceTestUser('super-admin');
    $this->koordSarpras = createMaintenanceTestUser('koordinator-sarpras');
    $this->petugasTik = createMaintenanceTestUser('petugas-tik');
    $this->petugasSarpras = createMaintenanceTestUser('petugas-sarpras');

    $this->location = AssetLocation::factory()->create(['name' => 'Ruang Server IT', 'code' => 1]);

    // Klasifikasi & Aset TIK (Alat IT)
    $this->classificationTik = AssetClassification::factory()->create(['name' => 'Alat IT', 'code' => 5]);
    $this->itemTik = AssetItem::factory()->create([
        'asset_classification_id' => $this->classificationTik->id,
        'name' => 'Komputer Server',
        'code' => 1,
    ]);
    $this->codeTik = AssetCode::create([
        'asset_classification_id' => $this->classificationTik->id,
        'asset_location_id' => $this->location->id,
        'asset_item_id' => $this->itemTik->id,
        'sequence_number' => 1,
        'full_code' => '5/1/1/1/2026',
    ]);
    $this->assetTik = Asset::factory()->create(['asset_code_id' => $this->codeTik->id]);

    // Klasifikasi & Aset Sarpras (Non-TIK)
    $this->classificationSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras', 'code' => 3]);
    $this->itemSarpras = AssetItem::factory()->create([
        'asset_classification_id' => $this->classificationSarpras->id,
        'name' => 'Genset Utama',
        'code' => 2,
    ]);
    $this->codeSarpras = AssetCode::create([
        'asset_classification_id' => $this->classificationSarpras->id,
        'asset_location_id' => $this->location->id,
        'asset_item_id' => $this->itemSarpras->id,
        'sequence_number' => 1,
        'full_code' => '3/1/1/2/2026',
    ]);
    $this->assetSarpras = Asset::factory()->create(['asset_code_id' => $this->codeSarpras->id]);
});

test('Super Admin BERHASIL membuat jadwal untuk aset TIK dengan petugas TIK', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $nextDate = now()->addDays(5)->format('Y-m-d');

    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pembersihan Rutin Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.asset_code_id', $this->codeTik->id)
        ->assertJsonPath('data.assigned_officer_id', $this->petugasTik->id)
        ->assertJsonPath('data.creator_id', $this->superAdmin->id)
        ->assertJsonPath('data.status', 'terjadwal')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('asset_maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'status' => 'terjadwal',
    ]);
});

test('Super Admin GAGAL membuat jadwal untuk aset Sarpras atau memakai petugas Sarpras', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $nextDate = now()->addDays(5)->format('Y-m-d');

    // Super Admin mencoba mendaftarkan aset Sarpras
    $responseSarprasAsset = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Perbaikan Genset',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $responseSarprasAsset->assertUnprocessable()
        ->assertJsonValidationErrors(['asset_code_id']);

    // Super Admin mencoba menugaskan Petugas Sarpras untuk aset TIK
    $responseSarprasOfficer = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $responseSarprasOfficer->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id']);
});

test('Koordinator Sarpras BERHASIL membuat jadwal untuk aset Sarpras dengan petugas Sarpras', function (): void {
    Sanctum::actingAs($this->koordSarpras);

    $nextDate = now()->addWeek()->format('Y-m-d');

    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'maintenance_type' => 'Pengecekan Oli Genset',
        'period' => MaintenancePeriod::Quarterly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.asset_code_id', $this->codeSarpras->id)
        ->assertJsonPath('data.assigned_officer_id', $this->petugasSarpras->id)
        ->assertJsonPath('data.creator_id', $this->koordSarpras->id)
        ->assertJsonPath('data.status', 'terjadwal');

    $this->assertDatabaseHas('asset_maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
    ]);
});

test('Koordinator Sarpras GAGAL membuat jadwal untuk aset TIK', function (): void {
    Sanctum::actingAs($this->koordSarpras);
    $nextDate = now()->addDays(3)->format('Y-m-d');

    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'maintenance_type' => 'Pengecekan Komputer Server',
        'period' => MaintenancePeriod::Weekly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['asset_code_id']);
});

test('Endpoint Index berhasil memfilter jadwal berdasarkan status dan search', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $schedule1 = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Inspeksi Jaringan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-11-01',
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $schedule2 = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Servis Mesin Genset',
        'period' => MaintenancePeriod::Quarterly,
        'next_scheduled_date' => '2026-11-15',
        'status' => MaintenanceStatus::Selesai,
        'is_active' => true,
    ]);

    // 1. Filter status=terjadwal
    $resStatus = $this->getJson('/api/v1/asset-maintenances?status=terjadwal');
    $resStatus->assertOk();
    expect($resStatus->json('data'))->toHaveCount(1)
        ->and($resStatus->json('data.0.id'))->toBe($schedule1->id);

    // 2. Filter search by nomor inventaris (full_code)
    $resSearchCode = $this->getJson('/api/v1/asset-maintenances?search='.$this->codeTik->full_code);
    $resSearchCode->assertOk();
    expect($resSearchCode->json('data'))->toHaveCount(1)
        ->and($resSearchCode->json('data.0.id'))->toBe($schedule1->id);

    // 3. Filter search by nama barang (assetItem.name)
    $resSearchItem = $this->getJson('/api/v1/asset-maintenances?search=Genset');
    $resSearchItem->assertOk();
    expect($resSearchItem->json('data'))->toHaveCount(1)
        ->and($resSearchItem->json('data.0.id'))->toBe($schedule2->id);

    // 4. Filter search by nama petugas
    $resSearchOfficer = $this->getJson('/api/v1/asset-maintenances?search='.$this->petugasTik->name);
    $resSearchOfficer->assertOk();
    expect($resSearchOfficer->json('data'))->toHaveCount(1)
        ->and($resSearchOfficer->json('data.0.id'))->toBe($schedule1->id);
});

test('Command maintenance:check-status mengubah status menjadi terlambat dan mengirim notifikasi ke pembuat', function (): void {
    Notification::fake();

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->subDays(2)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $this->artisan('maintenance:check-status')
        ->assertSuccessful();

    $schedule->refresh();
    expect($schedule->status)->toBe(MaintenanceStatus::Terlambat);

    Notification::assertSentTo(
        $this->superAdmin,
        AssetMaintenanceOverdueNotification::class,
        function (AssetMaintenanceOverdueNotification $notification) use ($schedule): bool {
            return $notification->schedule->id === $schedule->id;
        }
    );
});

test('Command maintenance:check-status mengirim reminder pada Hari H, H-1, dan H-3 ke petugas', function (): void {
    Notification::fake();

    $scheduleHariH = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Tugas Hari H',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $scheduleH1 = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Tugas H-1',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDay()->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $scheduleH3 = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Tugas H-3',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(3)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $this->artisan('maintenance:check-status')
        ->assertSuccessful();

    Notification::assertSentTo(
        $this->petugasTik,
        AssetMaintenanceReminderNotification::class,
        fn ($n) => $n->reminderType === 'hari-h'
    );

    Notification::assertSentTo(
        $this->petugasSarpras,
        AssetMaintenanceReminderNotification::class,
        fn ($n) => $n->reminderType === 'h-1'
    );

    Notification::assertSentTo(
        $this->petugasTik,
        AssetMaintenanceReminderNotification::class,
        fn ($n) => $n->reminderType === 'h-3'
    );
});

test('Endpoint Update berhasil menonaktifkan jadwal maintenance via PUT', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(10)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $response = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'is_active' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $schedule->id)
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('asset_maintenances', [
        'id' => $schedule->id,
        'is_active' => false,
    ]);
});

test('Endpoint DELETE untuk jadwal maintenance sudah tidak tersedia', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(10)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $response = $this->deleteJson("/api/v1/asset-maintenances/{$schedule->id}");

    $response->assertStatus(405);
});

test('Pembuatan jadwal untuk Hari H langsung mengirimkan notifikasi pengingat instan ke petugas', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $today = now()->format('Y-m-d');

    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pengecekan Darurat Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $today,
    ]);

    $response->assertCreated();

    Notification::assertSentTo(
        $this->petugasTik,
        AssetMaintenanceReminderNotification::class,
        function (AssetMaintenanceReminderNotification $notification): bool {
            return $notification->reminderType === 'hari-h';
        }
    );
});

test('Pembaruan jadwal ke Hari H langsung mengirimkan notifikasi pengingat instan ke petugas', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $response = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'next_scheduled_date' => now()->format('Y-m-d'),
    ]);

    $response->assertOk();

    Notification::assertSentTo(
        $this->petugasTik,
        AssetMaintenanceReminderNotification::class,
        fn ($n) => $n->reminderType === 'hari-h'
    );
});

test('Petugas GAGAL membuat atau memperbarui jadwal pemeliharaan (403 Forbidden)', function (): void {
    Sanctum::actingAs($this->petugasTik);

    // Coba membuat jadwal
    $resStore = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pengecekan Mandiri',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
    ]);
    $resStore->assertForbidden();

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    // Coba memperbarui jadwal
    $resUpdate = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'maintenance_type' => 'Pembaruan Tidak Sah',
    ]);
    $resUpdate->assertForbidden();
});

test('Petugas dan Manajemen BERHASIL melihat list dan detail jadwal pemeliharaan', function (): void {
    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    // Petugas TIK
    Sanctum::actingAs($this->petugasTik);
    $this->getJson('/api/v1/asset-maintenances')->assertOk();
    $this->getJson("/api/v1/asset-maintenances/{$schedule->id}")->assertOk();

    // Manajemen
    $manajemen = createMaintenanceTestUser('manajemen');
    Sanctum::actingAs($manajemen);
    $this->getJson('/api/v1/asset-maintenances')->assertOk();
    $this->getJson("/api/v1/asset-maintenances/{$schedule->id}")->assertOk();
});

test('User biasa GAGAL melihat jadwal pemeliharaan (403 Forbidden)', function (): void {
    $user = createMaintenanceTestUser('user');
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/asset-maintenances')->assertForbidden();
});
