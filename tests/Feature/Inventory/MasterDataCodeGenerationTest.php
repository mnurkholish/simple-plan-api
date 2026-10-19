<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('master data models auto-generate pure sequential integer codes', function (): void {
    $class1 = AssetClassification::create(['name' => 'Klasifikasi Pertama']);
    $class2 = AssetClassification::create(['name' => 'Klasifikasi Kedua']);
    $class3 = AssetClassification::create(['name' => 'Klasifikasi Ketiga']);

    expect($class1->code)->toBe(1)
        ->and($class2->code)->toBe(2)
        ->and($class3->code)->toBe(3);

    $loc1 = AssetLocation::create(['name' => 'Ruang 1']);
    $loc2 = AssetLocation::create(['name' => 'Ruang 2']);

    expect($loc1->code)->toBe(1)
        ->and($loc2->code)->toBe(2);

    $item1 = AssetItem::create(['name' => 'Barang 1']);
    $item2 = AssetItem::create(['name' => 'Barang 2']);

    expect($item1->code)->toBe(1)
        ->and($item2->code)->toBe(2);
});

test('store master data via API auto-generates code and update ignores code payload', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    // 1. Classification
    $responseClass = $this->postJson('/api/v1/asset-classifications', [
        'name' => 'Alat Medis',
    ])->assertCreated()
        ->assertJsonPath('data.code', 1)
        ->assertJsonPath('data.name', 'Alat Medis');

    $classId = $responseClass->json('data.id');

    // Attempting to change code via PUT should be ignored
    $this->putJson("/api/v1/asset-classifications/{$classId}", [
        'code' => 99,
        'name' => 'Alat Medis Updated',
    ])->assertOk()
        ->assertJsonPath('data.code', 1)
        ->assertJsonPath('data.name', 'Alat Medis Updated');

    // 2. Location
    $responseLoc = $this->postJson('/api/v1/asset-locations', [
        'name' => 'Ruang Operasi',
    ])->assertCreated()
        ->assertJsonPath('data.code', 1)
        ->assertJsonPath('data.name', 'Ruang Operasi');

    $locId = $responseLoc->json('data.id');

    $this->putJson("/api/v1/asset-locations/{$locId}", [
        'code' => 99,
        'name' => 'Ruang Operasi 1',
    ])->assertOk()
        ->assertJsonPath('data.code', 1)
        ->assertJsonPath('data.name', 'Ruang Operasi 1');

    // 3. Item
    $responseItem = $this->postJson('/api/v1/asset-items', [
        'name' => 'Stetoskop',
    ])->assertCreated()
        ->assertJsonPath('data.code', 1)
        ->assertJsonPath('data.name', 'Stetoskop');

    $itemId = $responseItem->json('data.id');

    $this->putJson("/api/v1/asset-items/{$itemId}", [
        'code' => 99,
        'name' => 'Stetoskop Digital',
    ])->assertOk()
        ->assertJsonPath('data.code', 1)
        ->assertJsonPath('data.name', 'Stetoskop Digital');
});

test('asset resource does not expose photo attributes', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create();
    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $response = $this->getJson("/api/v1/assets/{$asset->id}");

    $response->assertOk()
        ->assertJsonMissingPath('data.photo')
        ->assertJsonMissingPath('data.photo_object_key');
});
