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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $ticketsCreate = Permission::firstOrCreate(['name' => 'tickets-create', 'guard_name' => 'web']);

    $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $koordSarpras = Role::firstOrCreate(['name' => 'koordinator-sarpras', 'guard_name' => 'web']);
    $superAdmin->givePermissionTo($ticketsCreate);
    $koordSarpras->givePermissionTo($ticketsCreate);

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

test('role user mendapat 403 Forbidden saat mengakses endpoint Master Data Inventaris', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

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
});

test('seluruh role internal (Super Admin, Manajemen, Koordinator Sarpras, Petugas TIK, Petugas Sarpras) BISA mengakses GET Master Data Inventaris', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/asset-classifications')->assertOk();
    $this->getJson('/api/v1/asset-locations')->assertOk();
    $this->getJson('/api/v1/asset-items')->assertOk();
})->with([
    'super-admin',
    'management',
    'koordinator-sarpras',
    'petugas-tik',
    'petugas-sarpras',
]);

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

test('response GET /inventories dan /api/v1/assets dari token Super Admin mencakup seluruh aset lintas domain (IT, Sarpras, dan lainnya)', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $assetTik = createInventoryAuthTestAsset('Alat IT', 'Server TIK');
    $assetSarpras = createInventoryAuthTestAsset('Alat Sarpras', 'AC Sarpras');
    $assetMedis = createInventoryAuthTestAsset('Alat Medis', 'Tensimeter Medis');

    Sanctum::actingAs($superAdmin);

    foreach (['/inventories', '/api/v1/inventories', '/api/v1/assets'] as $endpoint) {
        $response = $this->getJson($endpoint);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($assetSarpras->id)
            ->and($ids)->toContain($assetTik->id)
            ->and($ids)->toContain($assetMedis->id);
    }
});

test('super-admin dapat melihat detail, search, generate number, QR, label, export label, serta mengelola CRUD aset Sarpras maupun IT', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    Sanctum::actingAs($superAdmin);

    $location = AssetLocation::factory()->create(['name' => 'Gedung Utama', 'code' => 1]);
    $classificationIt = AssetClassification::factory()->create(['name' => 'Alat IT', 'code' => 1]);
    $classificationSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras', 'code' => 2]);

    $itemIt = AssetItem::factory()->create(['asset_classification_id' => $classificationIt->id, 'name' => 'Router IT', 'code' => 1]);
    $itemSarpras = AssetItem::factory()->create(['asset_classification_id' => $classificationSarpras->id, 'name' => 'Pompa Air Sarpras', 'code' => 2]);

    $this->getJson('/api/v1/assets/generate-number?'.http_build_query([
        'asset_classification_id' => $classificationSarpras->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $itemSarpras->id,
        'year' => 2026,
    ]))->assertOk()->assertJsonStructure(['data' => ['full_code']]);

    $createIt = $this->postJson('/api/v1/assets', [
        'asset_classification_id' => $classificationIt->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $itemIt->id,
        'purchase_year' => 2026,
        'detail_item' => 'Router Core',
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::Good->value,
    ])->assertCreated();

    $createSarpras = $this->postJson('/api/v1/assets', [
        'asset_classification_id' => $classificationSarpras->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $itemSarpras->id,
        'purchase_year' => 2026,
        'detail_item' => 'Pompa Booster',
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::Good->value,
    ])->assertCreated();

    $assetIt = Asset::findOrFail($createIt->json('data.id'));
    $assetSarpras = Asset::findOrFail($createSarpras->json('data.id'));

    // Search mencakup IT dan Sarpras
    $searchIds = collect($this->getJson("/api/v1/assets/search?location_id={$location->id}")->assertOk()->json('data'))
        ->pluck('id');
    expect($searchIds)->toContain($assetIt->id)->and($searchIds)->toContain($assetSarpras->id);

    // Show, Show by UID, QR, Label HTML & PDF untuk aset Sarpras maupun IT
    foreach ([$assetIt, $assetSarpras] as $asset) {
        $this->getJson("/api/v1/assets/{$asset->id}")->assertOk()->assertJsonPath('data.id', $asset->id);
        $this->getJson("/api/v1/assets/uid/{$asset->asset_uid}")->assertOk()->assertJsonPath('data.id', $asset->id);
        $this->get("/api/v1/assets/{$asset->id}/qr")->assertOk();
        $this->get("/api/v1/assets/{$asset->id}/label?format=html")->assertOk();
        $this->get("/api/v1/assets/{$asset->id}/label?format=pdf&width_mm=70&height_mm=40")->assertOk();
    }

    // Export label gabungan IT + Sarpras
    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'selected',
        'asset_ids' => [$assetIt->id, $assetSarpras->id],
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertOk();

    // Update & Delete aset Sarpras maupun IT
    $this->putJson("/api/v1/assets/{$assetSarpras->id}", ['detail_item' => 'Pompa Booster Baru'])
        ->assertOk()
        ->assertJsonPath('data.detail_item', 'Pompa Booster Baru');

    $this->putJson("/api/v1/assets/{$assetIt->id}", ['detail_item' => 'Router Core Baru'])
        ->assertOk()
        ->assertJsonPath('data.detail_item', 'Router Core Baru');

    $this->deleteJson("/api/v1/assets/{$assetSarpras->id}")->assertOk();
    $this->deleteJson("/api/v1/assets/{$assetIt->id}")->assertOk();
});

test('koordinator-sarpras tetap hanya bisa mengakses dan mengelola aset Sarpras tanpa bocor ke aset IT', function (): void {
    $koordSarpras = User::factory()->create();
    $koordSarpras->assignRole('koordinator-sarpras');

    $location = AssetLocation::factory()->create(['name' => 'Ruang Bersama', 'code' => 1]);
    $classTik = AssetClassification::factory()->create(['name' => 'Alat IT', 'code' => 1]);
    $classSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras', 'code' => 2]);

    $itemTik = AssetItem::factory()->create(['asset_classification_id' => $classTik->id, 'name' => 'Laptop IT', 'code' => 1]);
    $itemSarpras = AssetItem::factory()->create(['asset_classification_id' => $classSarpras->id, 'name' => 'AC Sarpras', 'code' => 2]);

    $assetTik = Asset::factory()->forInventory($classTik, $location, $itemTik)->create();
    $assetSarpras = Asset::factory()->forInventory($classSarpras, $location, $itemSarpras)->create();

    Sanctum::actingAs($koordSarpras);

    $indexIds = collect($this->getJson('/api/v1/assets')->assertOk()->json('data'))->pluck('id');
    expect($indexIds)->toContain($assetSarpras->id)->and($indexIds)->not->toContain($assetTik->id);

    $searchIds = collect($this->getJson("/api/v1/assets/search?location_id={$location->id}")->assertOk()->json('data'))->pluck('id');
    expect($searchIds)->toContain($assetSarpras->id)->and($searchIds)->not->toContain($assetTik->id);

    // Sarpras diizinkan
    $this->getJson("/api/v1/assets/{$assetSarpras->id}")->assertOk();
    $this->get("/api/v1/assets/{$assetSarpras->id}/qr")->assertOk();
    $this->putJson("/api/v1/assets/{$assetSarpras->id}", ['detail_item' => 'AC Ruang Rapat'])->assertOk();

    // IT diblokir 403
    $this->getJson("/api/v1/assets/{$assetTik->id}")->assertForbidden();
    $this->getJson("/api/v1/assets/uid/{$assetTik->asset_uid}")->assertForbidden();
    $this->get("/api/v1/assets/{$assetTik->id}/qr")->assertForbidden();
    $this->get("/api/v1/assets/{$assetTik->id}/label?format=html")->assertForbidden();
    $this->putJson("/api/v1/assets/{$assetTik->id}", ['detail_item' => 'Ubah IT'])->assertForbidden();
    $this->deleteJson("/api/v1/assets/{$assetTik->id}")->assertForbidden();
});
