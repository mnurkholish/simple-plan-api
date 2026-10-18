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

test('authorized user can export an asset label as a rendered PDF', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create();
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
