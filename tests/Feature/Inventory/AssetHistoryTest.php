<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetHistory;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'old_values' => ['location_id' => 1],
        'new_values' => ['location_id' => 2],
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
        ->assertJsonPath('data.histories.1.id', $olderHistory->id)
        ->assertJsonPath('data.histories.1.is_location_change', false);
});

test('asset history correctly records location change when asset is updated', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create(['name' => 'Admin Sarpras']);
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location1 = AssetLocation::factory()->create();
    $location2 = AssetLocation::factory()->create();
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
        ->assertJsonMissingPath('data.0.histories');
});
