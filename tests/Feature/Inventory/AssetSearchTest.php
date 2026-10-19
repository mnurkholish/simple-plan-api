<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Repositories\AssetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('asset repository search can find assets by code and item name', function (): void {
    $repository = app(AssetRepository::class);

    $classification = AssetClassification::factory()->create();
    $location1 = AssetLocation::factory()->create(['name' => 'Ruang Server']);
    $location2 = AssetLocation::factory()->create(['name' => 'Ruang IGD']);

    $item1 = AssetItem::factory()->create(['name' => 'Laptop ThinkPad']);
    $item2 = AssetItem::factory()->create(['name' => 'Printer Canon']);

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
