<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use App\Repositories\AssetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('asset repository search can find assets by code and item name', function (): void {
    $repository = app(AssetRepository::class);

    $classification = AssetClassification::factory()->create();
    $location1 = AssetLocation::factory()->create(['name' => 'Ruang Server']);
    $location2 = AssetLocation::factory()->create(['name' => 'Ruang IGD']);

    $item1 = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Laptop ThinkPad']);
    $item2 = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Printer Canon']);

    $code1 = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location1->id,
        'asset_item_id' => $item1->id,
        'sequence_number' => 1,
        'full_code' => 'TIK-SRV-001',
    ]);

    $code2 = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location1->id,
        'asset_item_id' => $item2->id,
        'sequence_number' => 2,
        'full_code' => 'TIK-SRV-002',
    ]);

    $code3 = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location2->id,
        'asset_item_id' => $item1->id,
        'sequence_number' => 1,
        'full_code' => 'TIK-IGD-001',
    ]);

    $asset1 = Asset::factory()->create(['asset_code_id' => $code1->id]);
    $asset2 = Asset::factory()->create(['asset_code_id' => $code2->id]);
    $asset3 = Asset::factory()->create(['asset_code_id' => $code3->id]);

    // Search by full_code
    $resultsCode = $repository->search($location1->id, 'TIK-SRV-001');
    expect($resultsCode)->toHaveCount(1)
        ->and($resultsCode->first()->id)->toBe($asset1->id);

    // Search by item name
    $resultsItem = $repository->search($location1->id, 'Printer');
    expect($resultsItem)->toHaveCount(1)
        ->and($resultsItem->first()->id)->toBe($asset2->id);

    // Location isolation check
    $resultsOtherLocation = $repository->search($location2->id, 'Laptop');
    expect($resultsOtherLocation)->toHaveCount(1)
        ->and($resultsOtherLocation->first()->id)->toBe($asset3->id);

    // Search without keyword returns all in location
    $resultsAll = $repository->search($location1->id, null);
    expect($resultsAll)->toHaveCount(2);
});

test('asset repository filteredQuery can find assets by detail_item', function (): void {
    $repository = app(AssetRepository::class);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $asset1 = Asset::factory()->forInventory($classification, $location, $item)->create([
        'detail_item' => 'Meja Makan',
    ]);
    $asset2 = Asset::factory()->forInventory($classification, $location, $item)->create([
        'detail_item' => 'Meja Lab',
    ]);

    $results = $repository->filteredQuery(['search' => 'Makan'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($asset1->id);
});

test('HTTP endpoint search aset memvalidasi otorisasi dan parameter location_id', function (): void {
    // 1. Guest ditolak 401
    $this->getJson('/api/v1/assets/search?location_id=1')
        ->assertUnauthorized();

    // 2. User tanpa permission tickets-create ditolak 403
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/assets/search?location_id=1')
        ->assertForbidden();

    // 3. User berizin tetapi tanpa location_id ditolak 422
    $permission = Permission::firstOrCreate(['name' => 'tickets-create', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);

    $this->getJson('/api/v1/assets/search')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('location_id');
});

test('HTTP endpoint search aset mengembalikan daftar aset berdasarkan lokasi dan keyword pencarian', function (): void {
    $user = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'tickets-create', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $targetLocation = AssetLocation::factory()->create(['name' => 'Laboratorium']);
    $otherLocation = AssetLocation::factory()->create(['name' => 'Radiologi']);

    $item1 = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Mikroskop Olympus']);
    $item2 = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Centrifuge Bio']);

    $code1 = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $targetLocation->id,
        'asset_item_id' => $item1->id,
        'sequence_number' => 1,
        'full_code' => 'LAB-MIC-001',
    ]);

    $code2 = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $targetLocation->id,
        'asset_item_id' => $item2->id,
        'sequence_number' => 2,
        'full_code' => 'LAB-CEN-002',
    ]);

    $codeOther = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $otherLocation->id,
        'asset_item_id' => $item1->id,
        'sequence_number' => 1,
        'full_code' => 'RAD-MIC-001',
    ]);

    $asset1 = Asset::factory()->create(['asset_code_id' => $code1->id]);
    $asset2 = Asset::factory()->create(['asset_code_id' => $code2->id]);
    $assetOther = Asset::factory()->create(['asset_code_id' => $codeOther->id]);

    // Search with keyword 'Mikroskop' in targetLocation
    $response = $this->getJson("/api/v1/assets/search?location_id={$targetLocation->id}&search=Mikroskop")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($asset1->id)
        ->not->toContain($asset2->id, $assetOther->id);

    // Search without keyword in targetLocation returns all assets in that location
    $responseAll = $this->getJson("/api/v1/assets/search?location_id={$targetLocation->id}")
        ->assertOk();

    $allIds = collect($responseAll->json('data'))->pluck('id')->all();

    expect($allIds)->toContain($asset1->id, $asset2->id)
        ->not->toContain($assetOther->id);
});
