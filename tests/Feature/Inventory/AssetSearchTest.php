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
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('asset index returns asset type based on classification', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $location = AssetLocation::factory()->create();

    foreach (['Alat IT' => 'TIK', 'Peralatan Medis' => 'Sarpras'] as $name => $expectedType) {
        $classification = AssetClassification::factory()->create(['name' => $name]);
        $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);
        $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

        $response = $this->getJson('/api/v1/assets');
        $assetData = collect($response->json('data'))->firstWhere('id', $asset->id);

        $response->assertOk();
        expect($assetData['asset_type'])->toBe($expectedType);

        $filtered = $this->getJson('/api/v1/assets?asset_type='.$expectedType);
        $filtered->assertOk();
        expect(collect($filtered->json('data'))->pluck('asset_type')->unique()->all())->toBe([$expectedType]);
    }

    $this->getJson('/api/v1/assets?asset_type=invalid')->assertUnprocessable();
});

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
