<?php

use App\Models\Asset;
use App\Enums\AssetCondition;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('asset label preview shows the hospital header and the requested inventory fields', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create(['name' => 'Server']);
    $item = AssetItem::factory()->create([
        'name' => 'server',
        'asset_classification_id' => $classification->id,
    ]);
    $asset = Asset::factory()->forInventory($classification, $location, $item)->create([
        'detail_item' => 'Lenovo ThinkSystem SR650 V3',
        'model_specification' => 'Unrelated model',
        'condition' => AssetCondition::Good,
    ]);

    $response = $this->get('/api/v1/assets/'.$asset->id.'/label');

    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $html = $response->getContent();

    expect($html)
        ->toContain('size: 50mm 60mm', 'width: 50mm', 'height: 60mm')
        ->toContain('KARTU INVENTARIS', 'RS CITRA HUSADA', 'Jl. Teratai No. 22 Jember 0331-486200')
        ->toContain('Nomor', $asset->assetCode->full_code)
        ->toContain('Nama', 'server Lenovo ThinkSystem SR650 V3')
        ->toContain('Lokasi', 'Server', 'Kondisi', 'Baik')
        ->toContain('class="hospital-logo" src="data:image/png;base64,', 'class="qr-code"')
        ->toContain('Pindai QR untuk mengakses detail data inventaris')
        ->not->toContain('Unrelated model');
});

test('authorized user can export an asset label as a rendered PDF', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);
    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $response = $this->postJson('/api/v1/asset-label-exports', [
        'asset_ids' => [$asset->id],
        'width_mm' => 90,
        'height_mm' => 50,
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="asset-labels-a4.pdf"');

    expect($response->getContent())->toStartWith('%PDF-');
});
