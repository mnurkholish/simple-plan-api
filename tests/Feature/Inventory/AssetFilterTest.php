<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetHistory;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('manajemen', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $this->otherLocation = AssetLocation::create(['code' => 11, 'name' => 'Poli']);
    $this->otherClassification = AssetClassification::create(['code' => 3, 'name' => 'Sarana dan Prasarana']);
});

function filteredAsset(array $attributes = [], ?AssetLocation $location = null, ?AssetClassification $classification = null): Asset
{
    $asset = Asset::factory()->state($attributes)->create();
    $code = $asset->assetCode;

    if ($location !== null || $classification !== null) {
        $location ??= $code->location;
        $classification ??= $code->classification;
        $code->update([
            'asset_location_id' => $location->id,
            'asset_classification_id' => $classification->id,
            'full_code' => "{$classification->code}/{$location->code}/{$code->sequence_number}/{$code->item->code}/{$asset->purchase_year}",
        ]);
    }

    return $asset->fresh();
}

function recordLocationChange(Asset $asset, int $monthsAgo, bool $changed = true): void
{
    AssetHistory::create([
        'asset_id' => $asset->id,
        'event_type' => 'updated',
        'is_location_change' => $changed,
        'created_at' => now()->subMonths($monthsAgo),
    ]);
}

test('inventory filters combine with AND and repeated moves do not duplicate assets', function (): void {
    $match = filteredAsset(['status' => 'Digunakan', 'location_detail' => 'Lantai 1']);
    recordLocationChange($match, 1);
    recordLocationChange($match, 2);

    $oldMove = filteredAsset(['status' => 'Digunakan']);
    recordLocationChange($oldMove, 4);

    $otherRoom = filteredAsset(['status' => 'Digunakan'], $this->otherLocation);
    recordLocationChange($otherRoom, 1);

    $otherStatus = filteredAsset(['status' => 'Dipinjam']);
    recordLocationChange($otherStatus, 1);

    $otherClass = filteredAsset(['status' => 'Digunakan'], null, $this->otherClassification);
    recordLocationChange($otherClass, 1);

    $noMove = filteredAsset(['status' => 'Digunakan']);
    recordLocationChange($noMove, 1, false);

    $location = $match->assetCode->location->id;
    $classification = $match->assetCode->classification->id;

    $this->getJson('/api/v1/assets?'.http_build_query([
        'location_id' => $location,
        'status' => 'Digunakan',
        'classification_id' => $classification,
        'moved_last_3_months' => 'true',
        'per_page' => 1,
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('meta.total', 1);

    $this->getJson('/api/v1/assets?'.http_build_query([
        'asset_location_id' => $location,
        'asset_classification_id' => $classification,
        'moved_last_3_months' => 'false',
    ]))->assertOk()->assertJsonPath('meta.total', 4);

    $this->getJson('/api/v1/assets?location_id='.$location.'&search=Lantai%201')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
});

test('existing item, condition, year, and descending pagination remain available', function (): void {
    $target = filteredAsset(['purchase_year' => 2024, 'condition' => 'Rusak Ringan']);
    $newer = filteredAsset(['purchase_year' => 2021, 'condition' => 'Baik']);

    $this->getJson('/api/v1/assets?'.http_build_query([
        'asset_item_id' => $target->assetCode->asset_item_id,
        'condition' => 'Rusak Ringan',
        'year' => 2024,
    ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);

    $this->getJson('/api/v1/assets?per_page=1')
        ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $newer->id);
});

test('all_filtered exports every matching asset despite the list page size', function (): void {
    $ids = Asset::factory()->count(11)->state(['status' => 'Digunakan'])->create()->pluck('id')->all();
    Asset::factory()->count(11)->state(['status' => 'Dipinjam'])->create();
    $locationId = Asset::findOrFail($ids[0])->assetCode->asset_location_id;
    $classificationId = Asset::findOrFail($ids[0])->assetCode->asset_classification_id;

    $filters = [
        'location_id' => $locationId,
        'classification_id' => $classificationId,
        'status' => 'Digunakan',
        'page' => 2,
        'per_page' => 2,
    ];

    $this->getJson('/api/v1/assets?'.http_build_query($filters))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 11);

    $pdf = $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered',
        'filters' => $filters,
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(2);
});

test('selected mode exports only the requested assets', function (): void {
    $ids = Asset::factory()->count(13)->create()->pluck('id')->all();

    $pdf = $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'selected',
        'asset_ids' => [$ids[0], $ids[12]],
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertOk()->getContent();

    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(1);
});

test('all_filtered uses movement history and returns validation error for no matches', function (): void {
    $recent = filteredAsset();
    recordLocationChange($recent, 1);
    $old = filteredAsset();
    recordLocationChange($old, 4);

    $this->getJson('/api/v1/assets?moved_last_3_months=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $recent->id);

    $pdf = $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered',
        'filters' => ['moved_last_3_months' => true],
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertOk()->getContent();

    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(1);

    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered',
        'filters' => ['status' => 'Rusak'],
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertUnprocessable()->assertJsonValidationErrors('filters');
});

test('selection modes and filter values are validated', function (): void {
    $id = filteredAsset()->id;
    $base = ['width_mm' => 90, 'height_mm' => 50];

    foreach ([
        [$base + ['selection_mode' => 'other', 'asset_ids' => [$id]], 'selection_mode'],
        [$base + ['selection_mode' => 'selected'], 'asset_ids'],
        [$base + ['selection_mode' => 'selected', 'asset_ids' => [$id], 'filters' => []], 'filters'],
        [$base + ['selection_mode' => 'all_filtered', 'filters' => [], 'asset_ids' => [$id]], 'asset_ids'],
        [$base + ['selection_mode' => 'all_filtered'], 'filters'],
        [$base + ['selection_mode' => 'all_filtered', 'filters' => ['location_id' => 999999]], 'filters.location_id'],
        [$base + ['selection_mode' => 'all_filtered', 'filters' => ['status' => 'Unknown']], 'filters.status'],
        [$base + ['selection_mode' => 'all_filtered', 'filters' => ['moved_last_3_months' => 'wrong']], 'filters.moved_last_3_months'],
    ] as [$payload, $field]) {
        $this->postJson('/api/v1/asset-label-exports', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    $this->getJson('/api/v1/assets?location_id=999999')
        ->assertUnprocessable()->assertJsonValidationErrors('location_id');
    $this->getJson('/api/v1/assets?moved_last_3_months=wrong')
        ->assertUnprocessable()->assertJsonValidationErrors('moved_last_3_months');
});

test('legacy assets remain listed but cannot be exported without an inventory code', function (): void {
    $legacy = Asset::create([
        'name' => 'Legacy Helpdesk asset',
        'status' => 'Tidak Digunakan',
        'condition' => 'Baik',
    ]);

    $this->getJson('/api/v1/assets?status=Tidak%20Digunakan')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $legacy->id);

    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'selected', 'asset_ids' => [$legacy->id], 'width_mm' => 90, 'height_mm' => 50,
    ])->assertUnprocessable()->assertJsonValidationErrors('asset_ids.0');

    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered', 'filters' => ['status' => 'Tidak Digunakan'], 'width_mm' => 90, 'height_mm' => 50,
    ])->assertUnprocessable()->assertJsonValidationErrors('filters');
});

test('list and all_filtered retain inventory authorization', function (): void {
    filteredAsset();
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/assets?moved_last_3_months=1')->assertForbidden();
    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered', 'filters' => [], 'width_mm' => 90, 'height_mm' => 50,
    ])->assertForbidden();

    $manager = User::factory()->create();
    $manager->assignRole('manajemen');
    Sanctum::actingAs($manager);
    $this->getJson('/api/v1/assets')->assertOk();
    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered', 'filters' => [], 'width_mm' => 90, 'height_mm' => 50,
    ])->assertForbidden();

    auth()->forgetGuards();
    $this->getJson('/api/v1/assets')->assertUnauthorized();
});
