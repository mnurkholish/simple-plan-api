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

test('Super Admin BERHASIL membuat jadwal maintenance untuk aset Sarpras dengan petugas Sarpras', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $nextDate = now()->addDays(5)->format('Y-m-d');

    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'maintenance_type' => 'Perawatan Berkala Genset',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.asset_code_id', $this->codeSarpras->id)
        ->assertJsonPath('data.assigned_officer_id', $this->petugasSarpras->id)
        ->assertJsonPath('data.creator_id', $this->superAdmin->id)
        ->assertJsonPath('data.status', 'terjadwal')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('asset_maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->superAdmin->id,
    ]);
});

test('Super Admin BERHASIL memperbarui jadwal maintenance untuk aset Sarpras maupun IT', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $petugasSarprasBaru = createMaintenanceTestUser('petugas-sarpras');
    $petugasTikBaru = createMaintenanceTestUser('petugas-tik');

    $scheduleSarpras = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Servis Genset Awal',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(4)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $scheduleTik = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Servis Server Awal',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(4)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $this->putJson("/api/v1/asset-maintenances/{$scheduleSarpras->id}", [
        'assigned_officer_id' => $petugasSarprasBaru->id,
        'maintenance_type' => 'Servis Genset Lanjutan',
    ])
        ->assertOk()
        ->assertJsonPath('data.assigned_officer_id', $petugasSarprasBaru->id)
        ->assertJsonPath('data.maintenance_type', 'Servis Genset Lanjutan');

    $this->putJson("/api/v1/asset-maintenances/{$scheduleTik->id}", [
        'assigned_officer_id' => $petugasTikBaru->id,
        'maintenance_type' => 'Servis Server Lanjutan',
    ])
        ->assertOk()
        ->assertJsonPath('data.assigned_officer_id', $petugasTikBaru->id)
        ->assertJsonPath('data.maintenance_type', 'Servis Server Lanjutan');
});

test('Super Admin GAGAL membuat atau memperbarui jadwal jika petugas tidak sesuai klasifikasi aset dengan pesan Bahasa Indonesia', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $nextDate = now()->addDays(5)->format('Y-m-d');

    // Create: Aset Sarpras + Petugas TIK -> ditolak 422
    $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Perbaikan Genset',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');

    // Create: Aset IT + Petugas Sarpras -> ditolak 422
    $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang ditugaskan untuk aset TIK harus memiliki role petugas-tik.');

    $scheduleSarpras = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Servis Genset',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => $nextDate,
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $scheduleTik = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Servis Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => $nextDate,
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    // Update: Aset Sarpras + Petugas TIK -> ditolak 422
    $this->putJson("/api/v1/asset-maintenances/{$scheduleSarpras->id}", [
        'assigned_officer_id' => $this->petugasTik->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');

    // Update: Aset IT + Petugas Sarpras -> ditolak 422
    $this->putJson("/api/v1/asset-maintenances/{$scheduleTik->id}", [
        'assigned_officer_id' => $this->petugasSarpras->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang ditugaskan untuk aset TIK harus memiliki role petugas-tik.');
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

test('Koordinator Sarpras BERHASIL memperbarui jadwal maintenance untuk petugas Sarpras', function (): void {
    Sanctum::actingAs($this->koordSarpras);

    $petugasSarprasBaru = createMaintenanceTestUser('petugas-sarpras');

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Pengecekan Oli Genset',
        'period' => MaintenancePeriod::Quarterly,
        'next_scheduled_date' => now()->addWeek()->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $newDate = now()->addDays(10)->format('Y-m-d');

    $response = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'assigned_officer_id' => $petugasSarprasBaru->id,
        'maintenance_type' => 'Penggantian Filter & Oli Genset',
        'period' => MaintenancePeriod::Semiannually->value,
        'next_scheduled_date' => $newDate,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Jadwal pemeliharaan aset berhasil diperbarui.')
        ->assertJsonPath('data.id', $schedule->id)
        ->assertJsonPath('data.assigned_officer_id', $petugasSarprasBaru->id)
        ->assertJsonPath('data.maintenance_type', 'Penggantian Filter & Oli Genset')
        ->assertJsonPath('data.period', MaintenancePeriod::Semiannually->value);

    $this->assertDatabaseHas('asset_maintenances', [
        'id' => $schedule->id,
        'assigned_officer_id' => $petugasSarprasBaru->id,
        'maintenance_type' => 'Penggantian Filter & Oli Genset',
    ]);
});

test('Koordinator Sarpras GAGAL membuat jadwal untuk aset TIK atau petugas yang tidak sesuai dengan pesan Bahasa Indonesia', function (): void {
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
        ->assertJsonValidationErrors(['asset_code_id'])
        ->assertJsonPath('errors.asset_code_id.0', 'Koordinator Sarpras hanya dapat mengatur jadwal pemeliharaan untuk aset non-TIK (Sarpras).');

    $responseWrongOfficer = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pengecekan Genset',
        'period' => MaintenancePeriod::Weekly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $responseWrongOfficer->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');
});

test('Validasi assigned_officer_id tidak valid mengembalikan 422 berbahasa Indonesia pada tambah dan ubah jadwal maintenance', function (): void {
    Sanctum::actingAs($this->koordSarpras);
    $nextDate = now()->addDays(3)->format('Y-m-d');

    $resNotFound = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => 999999,
        'maintenance_type' => 'Pengecekan Genset',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $nextDate,
    ]);

    $resNotFound->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('message', 'Petugas yang dipilih tidak ditemukan atau tidak valid.')
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang dipilih tidak ditemukan atau tidak valid.');

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Pengecekan Genset',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => $nextDate,
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $resUpdateNotFound = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'assigned_officer_id' => 999999,
    ]);

    $resUpdateNotFound->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('message', 'Petugas yang dipilih tidak ditemukan atau tidak valid.')
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang dipilih tidak ditemukan atau tidak valid.');

    $resUpdateWrongRole = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'assigned_officer_id' => $this->petugasTik->id,
    ]);

    $resUpdateWrongRole->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_officer_id'])
        ->assertJsonPath('message', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.')
        ->assertJsonPath('errors.assigned_officer_id.0', 'Petugas yang ditugaskan untuk aset Sarpras harus memiliki role petugas-sarpras.');
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

    // 3. Filter search by nama barang (assetItem.name) oleh Koordinator Sarpras (Domain Isolation)
    Sanctum::actingAs($this->koordSarpras);
    $resSearchItem = $this->getJson('/api/v1/asset-maintenances?search=Genset');
    $resSearchItem->assertOk();
    expect($resSearchItem->json('data'))->toHaveCount(1)
        ->and($resSearchItem->json('data.0.id'))->toBe($schedule2->id);

    // Super Admin dapat melihat jadwal aset Sarpras maupun IT
    Sanctum::actingAs($this->superAdmin);
    $this->getJson('/api/v1/asset-maintenances?search=Genset')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $schedule2->id);

    // 4. Filter search by nama petugas
    $resSearchOfficer = $this->getJson('/api/v1/asset-maintenances?search='.$this->petugasTik->name);
    $resSearchOfficer->assertOk();
    expect($resSearchOfficer->json('data'))->toHaveCount(1)
        ->and($resSearchOfficer->json('data.0.id'))->toBe($schedule1->id);

    // 5. Filter by maintenance_type, assigned_officer_id, dan rentang tanggal (date_from & date_to)
    $resDynamicFilters = $this->getJson('/api/v1/asset-maintenances?'.http_build_query([
        'maintenance_type' => 'Inspeksi Jaringan',
        'assigned_officer_id' => $this->petugasTik->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-05',
    ]));
    $resDynamicFilters->assertOk();
    expect($resDynamicFilters->json('data'))->toHaveCount(1)
        ->and($resDynamicFilters->json('data.0.id'))->toBe($schedule1->id);
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

test('Pembuatan jadwal dadakan jarak <= 3 hari langsung mengirimkan notifikasi instan mendesak ke petugas', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $h2 = now()->addDays(2)->format('Y-m-d');
    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Perbaikan Mendesak Jaringan',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $h2,
    ]);

    $response->assertCreated();

    Notification::assertSentTo(
        $this->petugasTik,
        AssetMaintenanceReminderNotification::class,
        function (AssetMaintenanceReminderNotification $notification): bool {
            return $notification->reminderType === 'h-2' && $notification->isUrgent === true;
        }
    );
});

test('Pembuatan jadwal jarak > 3 hari TIDAK langsung mengirimkan notifikasi instan ke petugas', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $h7 = now()->addDays(7)->format('Y-m-d');
    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pengecekan Rutin Jauh',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $h7,
    ]);

    $response->assertCreated();

    Notification::assertNothingSent();
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

test('Query scope visibleTo mengisolasi data jadwal sesuai role Super Admin, Sarpras, dan Petugas', function (): void {
    $scheduleTik = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Jadwal TIK',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $scheduleSarpras = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Jadwal Sarpras',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    // 1. Super Admin melihat semua jadwal (TIK dan Sarpras) pada list maupun detail
    Sanctum::actingAs($this->superAdmin);
    $resSuperAdmin = $this->getJson('/api/v1/asset-maintenances');
    $idsSuperAdmin = collect($resSuperAdmin->json('data'))->pluck('id');
    expect($idsSuperAdmin)->toContain($scheduleTik->id)
        ->and($idsSuperAdmin)->toContain($scheduleSarpras->id);

    $this->getJson("/api/v1/asset-maintenances/{$scheduleTik->id}")->assertOk();
    $this->getJson("/api/v1/asset-maintenances/{$scheduleSarpras->id}")->assertOk();

    // Super Admin tetap ditolak mengisi log jika bukan assigned_officer_id
    $this->postJson("/api/v1/asset-maintenances/{$scheduleSarpras->id}/logs", [
        'work_description' => 'Eksekusi tanpa penugasan',
        'maintenance_result' => 'Ditolak',
        'post_maintenance_condition' => 'Baik',
        'started_at' => now()->format('Y-m-d 08:00:00'),
        'completed_at' => now()->format('Y-m-d 09:00:00'),
    ])->assertForbidden();

    // 2. Koordinator Sarpras hanya melihat dan mengakses Non-TIK (Sarpras), tidak bocor ke IT
    Sanctum::actingAs($this->koordSarpras);
    $resSarpras = $this->getJson('/api/v1/asset-maintenances');
    $idsSarpras = collect($resSarpras->json('data'))->pluck('id');
    expect($idsSarpras)->toContain($scheduleSarpras->id)
        ->and($idsSarpras)->not->toContain($scheduleTik->id);

    $this->getJson("/api/v1/asset-maintenances/{$scheduleSarpras->id}")->assertOk();
    $this->getJson("/api/v1/asset-maintenances/{$scheduleTik->id}")->assertForbidden();
    $this->putJson("/api/v1/asset-maintenances/{$scheduleTik->id}", [
        'maintenance_type' => 'Mencoba Ubah IT',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['maintenance']);

    // 3. Petugas TIK hanya melihat jadwal yang ditugaskan kepadanya
    Sanctum::actingAs($this->petugasTik);
    $resPetugasTik = $this->getJson('/api/v1/asset-maintenances');
    $idsPetugasTik = collect($resPetugasTik->json('data'))->pluck('id');
    expect($idsPetugasTik)->toContain($scheduleTik->id)
        ->and($idsPetugasTik)->not->toContain($scheduleSarpras->id);

    // 4. Manajemen melihat semua jadwal
    $manajemen = createMaintenanceTestUser('manajemen');
    Sanctum::actingAs($manajemen);
    $resManajemen = $this->getJson('/api/v1/asset-maintenances');
    $idsManajemen = collect($resManajemen->json('data'))->pluck('id');
    expect($idsManajemen)->toContain($scheduleTik->id)
        ->and($idsManajemen)->toContain($scheduleSarpras->id);
});
