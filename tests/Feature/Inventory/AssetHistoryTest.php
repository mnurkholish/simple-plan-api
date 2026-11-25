<?php

use App\Enums\AssetCondition;
use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetHistory;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('asset detail endpoint returns histories ordered by latest', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create(['name' => 'Dr. Budi']);
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $olderHistory = AssetHistory::create([
        'asset_id' => $asset->id,
        'event_type' => 'updated',
        'old_values' => ['status' => 'Tidak Digunakan'],
        'new_values' => ['status' => 'Digunakan'],
        'is_location_change' => false,
        'changed_by' => $user->id,
        'created_at' => now()->subDay(),
    ]);

    $newerHistory = AssetHistory::create([
        'asset_id' => $asset->id,
        'event_type' => 'updated',
        'old_values' => ['asset_location_id' => 1, 'location_name' => 'Gudang Lama'],
        'new_values' => ['asset_location_id' => 2, 'location_name' => 'Gudang Baru'],
        'is_location_change' => true,
        'changed_by' => $user->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/assets/{$asset->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'asset_uid',
                'histories' => [
                    '*' => [
                        'id',
                        'event_type',
                        'old_values',
                        'new_values',
                        'is_location_change',
                        'changed_by_name',
                        'created_at',
                    ],
                ],
            ],
        ])
        ->assertJsonCount(2, 'data.histories')
        ->assertJsonPath('data.histories.0.id', $newerHistory->id)
        ->assertJsonPath('data.histories.0.is_location_change', true)
        ->assertJsonPath('data.histories.0.changed_by_name', 'Dr. Budi')
        ->assertJsonPath('data.histories.0.old_values.location_name', 'Gudang Lama')
        ->assertJsonPath('data.histories.0.new_values.location_name', 'Gudang Baru')
        ->assertJsonPath('data.histories.1.id', $olderHistory->id)
        ->assertJsonPath('data.histories.1.is_location_change', false);
});

test('asset history correctly records location change when asset is updated', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create(['name' => 'Admin Sarpras']);
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location1 = AssetLocation::factory()->create(['name' => 'Gudang Farmasi']);
    $location2 = AssetLocation::factory()->create(['name' => 'Ruang ICU']);
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $asset = Asset::factory()->forInventory($classification, $location1, $item)->create();

    $this->putJson("/api/v1/assets/{$asset->id}", [
        'asset_location_id' => $location2->id,
    ])->assertOk();

    $response = $this->getJson("/api/v1/assets/{$asset->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data.histories')
        ->assertJsonPath('data.histories.0.is_location_change', true)
        ->assertJsonPath('data.histories.0.changed_by_name', 'Admin Sarpras')
        ->assertJsonPath('data.histories.0.old_values.location_name', 'Gudang Farmasi')
        ->assertJsonPath('data.histories.0.new_values.location_name', 'Ruang ICU')
        ->assertJsonPath('data.histories.0.old_values.asset_location_id', $location1->id)
        ->assertJsonPath('data.histories.0.new_values.asset_location_id', $location2->id);
});

test('asset index does not include histories to keep list lightweight', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    AssetHistory::create([
        'asset_id' => $asset->id,
        'event_type' => 'updated',
        'old_values' => ['notes' => 'Old'],
        'new_values' => ['notes' => 'New'],
        'is_location_change' => false,
        'changed_by' => $user->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/assets');

    $response->assertOk()
        ->assertJsonMissingPath('data.0.histories')
        ->assertJsonMissingPath('data.0.maintenance_histories');
});

test('asset detail endpoint returns maintenance_histories ordered from latest to oldest', function (): void {
    Storage::fake('public');
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('petugas-tik', 'web');

    $admin = User::factory()->create(['name' => 'Super Admin']);
    $admin->assignRole('super-admin');

    $officer = User::factory()->create(['name' => 'Haris Arifin']);
    $officer->assignRole('petugas-tik');

    Sanctum::actingAs($admin);

    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create(['name' => 'Ruang Server']);
    $item = AssetItem::factory()->create([
        'asset_classification_id' => $classification->id,
        'name' => 'Server Utama',
    ]);

    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $asset->asset_code_id,
        'assigned_officer_id' => $officer->id,
        'creator_id' => $admin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-12-01',
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $olderLog = AssetMaintenanceLog::create([
        'asset_maintenance_id' => $schedule->id,
        'scheduled_date' => '2026-10-01',
        'work_description' => 'Pembersihan tahap pertama',
        'maintenance_result' => 'Debu bersih',
        'post_maintenance_condition' => AssetCondition::Good,
        'started_at' => '2026-10-01 08:00:00',
        'completed_at' => '2026-10-01 09:00:00',
        'proof_photo_object_key' => null,
    ]);

    $newerLog = AssetMaintenanceLog::create([
        'asset_maintenance_id' => $schedule->id,
        'scheduled_date' => '2026-11-01',
        'work_description' => 'Pembersihan tahap kedua & cek thermal paste',
        'maintenance_result' => 'Suhu stabil dan perlu pantauan kipas',
        'post_maintenance_condition' => AssetCondition::LightDamage,
        'started_at' => '2026-11-01 10:00:00',
        'completed_at' => '2026-11-01 11:30:00',
        'proof_photo_object_key' => 'maintenance_photos/bukti-november.jpg',
        'proof_photo_original_name' => 'bukti-november.jpg',
        'proof_photo_mime_type' => 'image/jpeg',
        'proof_photo_size' => 1024,
    ]);

    $response = $this->getJson("/api/v1/assets/{$asset->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.maintenance_histories')
        ->assertJsonPath('data.maintenance_histories.0.id', $newerLog->id)
        ->assertJsonPath('data.maintenance_histories.0.asset_maintenance_id', $schedule->id)
        ->assertJsonPath('data.maintenance_histories.0.period', MaintenancePeriod::Monthly->value)
        ->assertJsonPath('data.maintenance_histories.0.executor_name', 'Haris Arifin')
        ->assertJsonPath('data.maintenance_histories.0.work_description', 'Pembersihan tahap kedua & cek thermal paste')
        ->assertJsonPath('data.maintenance_histories.0.maintenance_result', 'Suhu stabil dan perlu pantauan kipas')
        ->assertJsonPath('data.maintenance_histories.0.post_maintenance_condition', AssetCondition::LightDamage->value)
        ->assertJsonPath(
            'data.maintenance_histories.0.photo_url',
            Storage::disk('public')->url('maintenance_photos/bukti-november.jpg')
        )
        ->assertJsonPath('data.maintenance_histories.1.id', $olderLog->id)
        ->assertJsonPath('data.maintenance_histories.1.photo_url', null);

    expect($response->json('data.maintenance_histories.0.executed_at'))->not->toBeNull();

    // Alias /api/v1/inventories/{asset} mengembalikan struktur yang identik
    $this->getJson("/api/v1/inventories/{$asset->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.maintenance_histories')
        ->assertJsonPath('data.maintenance_histories.0.id', $newerLog->id);
});

test('asset without maintenance logs returns empty maintenance_histories array', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);
    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $this->getJson("/api/v1/assets/{$asset->id}")
        ->assertOk()
        ->assertJsonPath('data.maintenance_histories', []);
});
