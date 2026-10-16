<?php

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
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $this->classification = AssetClassification::create(['code' => 5, 'name' => 'Alat IT']);
    $this->firstLocation = AssetLocation::create(['code' => 10, 'name' => 'Ruang IT']);
    $this->secondLocation = AssetLocation::create(['code' => 11, 'name' => 'Poli']);
    $this->emptyLocation = AssetLocation::create(['code' => 12, 'name' => 'Gudang Kosong']);
    $this->computer = AssetItem::create(['code' => 20, 'name' => 'Komputer']);
    $this->printer = AssetItem::create(['code' => 21, 'name' => 'Printer']);
    $this->unusedItem = AssetItem::create(['code' => 22, 'name' => 'Barang Kosong']);

    $this->createAsset = function (AssetLocation $location, AssetItem $item, int $sequence): Asset {
        $code = AssetCode::create([
            'asset_classification_id' => $this->classification->id,
            'asset_location_id' => $location->id,
            'asset_item_id' => $item->id,
            'sequence_number' => $sequence,
            'full_code' => "5/{$location->code}/{$sequence}/{$item->code}/2026",
        ]);

        return Asset::create([
            'asset_code_id' => $code->id,
            'purchase_year' => 2026,
            'status' => 'Digunakan',
            'condition' => 'Baik',
        ]);
    };
});

test('cascading search filters items and assets using the selected location', function (): void {
    $computer = ($this->createAsset)($this->firstLocation, $this->computer, 1);
    ($this->createAsset)($this->firstLocation, $this->printer, 2);
    ($this->createAsset)($this->secondLocation, $this->printer, 3);

    $this->getJson('/api/v1/asset-locations')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $this->firstLocation->id)
        ->assertJsonPath('data.1.id', $this->secondLocation->id)
        ->assertJsonPath('data.2.id', $this->emptyLocation->id);

    $this->getJson('/api/v1/asset-items?location_id='.$this->firstLocation->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->computer->id)
        ->assertJsonPath('data.1.id', $this->printer->id);

    $this->getJson('/api/v1/asset-items?location_id='.$this->secondLocation->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->printer->id);

    $this->getJson('/api/v1/assets?'.http_build_query([
        'location_id' => $this->firstLocation->id,
        'asset_item_id' => $this->computer->id,
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $computer->id);

    $this->getJson('/api/v1/assets/'.$computer->id)
        ->assertOk()
        ->assertJsonPath('data.id', $computer->id);
});

test('item options ignore soft deleted assets while master lists stay compatible', function (): void {
    $asset = ($this->createAsset)($this->firstLocation, $this->computer, 1);
    $asset->delete();

    $this->getJson('/api/v1/asset-items?location_id='.$this->firstLocation->id)
        ->assertOk()->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/asset-locations')
        ->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/asset-items')
        ->assertOk()->assertJsonCount(3, 'data');
});

test('cascading search parameters are validated', function (): void {
    $this->getJson('/api/v1/asset-items?location_id=999999')
        ->assertUnprocessable()->assertJsonValidationErrors('location_id');
});
