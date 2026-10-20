<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function validAssetPayload(AssetClassification $classification, AssetLocation $location, AssetItem $item): array
{
    return [
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $item->id,
        'purchase_year' => 2026,
        'serial_number' => 'SN-TEST-001',
        'brand' => 'Lenovo',
        'model_specification' => 'ThinkPad T14',
        'location_detail' => 'Lantai 2 Meja 3',
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::Good->value,
        'notes' => 'Catatan test aset',
    ];
}

test('super-admin and koordinator-sarpras can create asset', function (string $role): void {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);

    $response = $this->postJson('/api/v1/assets', $payload);

    $response->assertCreated()
        ->assertJsonPath('message', 'Asset created successfully.')
        ->assertJsonPath('data.serial_number', 'SN-TEST-001');

    $this->assertDatabaseHas('assets', [
        'serial_number' => 'SN-TEST-001',
        'purchase_year' => 2026,
    ]);
})->with([
    'super admin' => 'super-admin',
    'koordinator sarpras' => 'koordinator-sarpras',
]);

test('petugas-tik and petugas-sarpras cannot create asset', function (string $role): void {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);

    $response = $this->postJson('/api/v1/assets', $payload);

    $response->assertForbidden();

    $this->assertDatabaseMissing('assets', [
        'serial_number' => 'SN-TEST-001',
    ]);
})->with([
    'petugas TIK' => 'petugas-tik',
    'petugas Sarpras' => 'petugas-sarpras',
]);

test('regular user or management cannot create asset', function (string $role): void {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);

    $this->postJson('/api/v1/assets', $payload)->assertForbidden();
})->with([
    'user' => 'user',
    'management' => 'manajemen',
]);

test('guest cannot create asset', function (): void {
    $this->postJson('/api/v1/assets', [])->assertUnauthorized();
});
