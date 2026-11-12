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

function authenticateAssetQrUser(): void
{
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);
}

test('super admin dapat membuat kode QR SVG untuk aset', function (): void {
    authenticateAssetQrUser();
    $asset = Asset::factory()->create();

    $response = $this->get("/api/v1/assets/{$asset->id}/qr");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->getContent())
        ->toContain('<svg', '<path')
        ->toContain('</svg>');
});

test('request QR Code untuk aset yang tidak ditemukan mengembalikan 404', function (): void {
    authenticateAssetQrUser();

    $this->getJson('/api/v1/assets/999999/qr')->assertNotFound();
});

test('super admin dapat mencari aset berdasarkan UID', function (): void {
    authenticateAssetQrUser();
    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);
    $asset = Asset::factory()->forInventory($classification, $location, $item)->create([
        'detail_item' => 'Komputer meja perawat',
        'brand' => 'Lenovo',
    ]);
    $asset->refresh()->load('assetCode');

    $this->getJson("/api/v1/assets/uid/{$asset->asset_uid}")
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'uid', 'classification', 'asset_code', 'location',
            'detail_location', 'name', 'detail_name', 'brand', 'status',
        ]])
        ->assertJsonPath('data.id', $asset->id)
        ->assertJsonPath('data.uid', $asset->asset_uid)
        ->assertJsonPath('data.classification', 'TIK')
        ->assertJsonPath('data.asset_code', $asset->assetCode->full_code)
        ->assertJsonPath('data.location', $location->name)
        ->assertJsonPath('data.name', $item->name)
        ->assertJsonPath('data.detail_name', 'Komputer meja perawat')
        ->assertJsonPath('data.brand', 'Lenovo');
});

test('pencarian UID yang tidak ditemukan mengembalikan 404', function (): void {
    authenticateAssetQrUser();

    $this->getJson('/api/v1/assets/uid/01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->assertNotFound();
});

test('guest tidak dapat membuat kode QR aset', function (): void {
    $asset = Asset::factory()->create();

    $this->getJson("/api/v1/assets/{$asset->id}/qr")
        ->assertUnauthorized();
});

test('kode QR dan UID aset tetap sama setelah nomor inventaris berubah', function (): void {
    authenticateAssetQrUser();
    $classification = AssetClassification::factory()->create();
    $originalLocation = AssetLocation::factory()->create();
    $newLocation = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);
    $asset = Asset::factory()->forInventory($classification, $originalLocation, $item)->create();
    $asset->refresh()->load('assetCode');
    $originalUid = $asset->asset_uid;
    $originalNumber = $asset->assetCode->full_code;

    $originalQr = $this->get("/api/v1/assets/{$asset->id}/qr")
        ->assertOk()
        ->getContent();

    $this->putJson("/api/v1/assets/{$asset->id}", [
        'asset_location_id' => $newLocation->id,
    ])->assertOk()
        ->assertJsonPath('data.asset_uid', $originalUid);

    $asset->refresh()->load('assetCode');
    expect($asset->assetCode->full_code)->not->toBe($originalNumber);
    expect($asset->asset_uid)->toBe($originalUid);

    $updatedQr = $this->get("/api/v1/assets/{$asset->id}/qr")
        ->assertOk()
        ->getContent();

    expect($updatedQr)->toBe($originalQr);
    $this->getJson("/api/v1/assets/uid/{$originalUid}")
        ->assertOk()
        ->assertJsonPath('data.id', $asset->id)
        ->assertJsonPath('data.asset_code', $asset->assetCode->full_code);
});
