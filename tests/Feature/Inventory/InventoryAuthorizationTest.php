<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'koordinator-sarpras', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'petugas-sarpras', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function createInventoryAuthTestAsset(string $classificationName, string $detailItem): Asset
{
    $classification = AssetClassification::factory()->create(['name' => $classificationName]);
    $location = AssetLocation::factory()->create(['name' => 'Lokasi Test']);
    $item = AssetItem::factory()->create([
        'asset_classification_id' => $classification->id,
        'name' => $detailItem,
    ]);

    $code = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $item->id,
        'sequence_number' => 1,
        'full_code' => "{$classification->id}.{$location->id}.{$item->id}.0001",
    ]);

    return Asset::create([
        'asset_code_id' => $code->id,
        'serial_number' => 'SN-'.uniqid(),
        'detail_item' => $detailItem,
        'purchase_year' => 2026,
        'status' => AssetStatus::InUse,
        'condition' => AssetCondition::Good,
    ]);
}

test('Manajemen, Koordinator Sarpras, Petugas TIK, Petugas Sarpras, dan User mendapat 403 Forbidden saat menembak endpoint Master Data Inventaris', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);

    Sanctum::actingAs($user);

    // Kategori / Klasifikasi
    $this->getJson('/api/v1/asset-classifications')->assertForbidden();
    $this->postJson('/api/v1/asset-classifications', ['name' => 'Kategori Baru'])->assertForbidden();

    // Lokasi
    $this->getJson('/api/v1/asset-locations')->assertForbidden();
    $this->postJson('/api/v1/asset-locations', ['name' => 'Lokasi Baru'])->assertForbidden();

    // Master Barang / Item
    $this->getJson('/api/v1/asset-items')->assertForbidden();
    $this->postJson('/api/v1/asset-items', ['name' => 'Barang Baru', 'asset_classification_id' => 1])->assertForbidden();
})->with([
    'management',
    'koordinator-sarpras',
    'petugas-tik',
    'petugas-sarpras',
    'user',
]);

test('Super Admin BISA mengakses endpoint Master Data Inventaris', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    Sanctum::actingAs($superAdmin);

    $this->getJson('/api/v1/asset-classifications')->assertOk();
    $this->getJson('/api/v1/asset-locations')->assertOk();
    $this->getJson('/api/v1/asset-items')->assertOk();
});

test('response GET /inventories dari token Manajemen tidak mengandung data inventaris milik TIK dan Sarpras', function (): void {
    $management = User::factory()->create();
    $management->assignRole('management');

    $assetTik = createInventoryAuthTestAsset('Alat IT', 'Laptop TIK');
    $assetSarpras = createInventoryAuthTestAsset('Alat Sarpras', 'Genset Sarpras');
    $assetMedis = createInventoryAuthTestAsset('Alat Medis', 'Stetoskop Medis');

    Sanctum::actingAs($management);

    // Test endpoint /inventories
    $response = $this->getJson('/inventories');
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($assetTik->id)
        ->and($ids)->not->toContain($assetSarpras->id)
        ->and($ids)->toContain($assetMedis->id);

    // Test endpoint /api/v1/inventories
    $responseV1 = $this->getJson('/api/v1/inventories');
    $responseV1->assertOk();

    $idsV1 = collect($responseV1->json('data'))->pluck('id');
    expect($idsV1)->not->toContain($assetTik->id)
        ->and($idsV1)->not->toContain($assetSarpras->id)
        ->and($idsV1)->toContain($assetMedis->id);
});

test('response GET /inventories dari token Super Admin tidak mengandung data inventaris milik Sarpras', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $assetTik = createInventoryAuthTestAsset('Alat IT', 'Server TIK');
    $assetSarpras = createInventoryAuthTestAsset('Alat Sarpras', 'AC Sarpras');
    $assetMedis = createInventoryAuthTestAsset('Alat Medis', 'Tensimeter Medis');

    Sanctum::actingAs($superAdmin);

    // Test endpoint /inventories
    $response = $this->getJson('/inventories');
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($assetSarpras->id)
        ->and($ids)->toContain($assetTik->id)
        ->and($ids)->toContain($assetMedis->id);

    // Test endpoint /api/v1/inventories
    $responseV1 = $this->getJson('/api/v1/inventories');
    $responseV1->assertOk();

    $idsV1 = collect($responseV1->json('data'))->pluck('id');
    expect($idsV1)->not->toContain($assetSarpras->id)
        ->and($idsV1)->toContain($assetTik->id)
        ->and($idsV1)->toContain($assetMedis->id);
});
