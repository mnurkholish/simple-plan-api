<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function validAssetPayload(AssetClassification $classification, AssetLocation $location, AssetItem $item): array
{
    return [
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $item->id,
        'purchase_year' => 2026,
        'serial_number' => 'SN-TEST-001',
        'brand' => 'Lenovo',
        'model_specification' => 'ThinkPad T14',
        'detail_item' => 'Meja Lab',
        'location_detail' => 'Lantai 2 Meja 3',
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::Good->value,
        'notes' => 'Catatan test aset',
    ];
}

test('super-admin and koordinator-sarpras can create asset', function (string $role): void {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);

    $response = $this->postJson('/api/v1/assets', $payload);

    $response->assertCreated()
        ->assertJsonPath('message', 'Aset berhasil ditambahkan.')
        ->assertJsonPath('data.serial_number', 'SN-TEST-001')
        ->assertJsonPath('data.detail_item', 'Meja Lab');

    $this->assertDatabaseHas('assets', [
        'serial_number' => 'SN-TEST-001',
        'purchase_year' => 2026,
        'detail_item' => 'Meja Lab',
    ]);
})->with([
    'super admin' => 'super-admin',
    'koordinator sarpras' => 'koordinator-sarpras',
]);

test('petugas-tik and petugas-sarpras cannot create asset', function (string $role): void {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);

    $response = $this->postJson('/api/v1/assets', $payload);

    $response->assertForbidden();

    $this->assertDatabaseMissing('assets', [
        'serial_number' => 'SN-TEST-001',
    ]);
})->with([
    'petugas TIK' => 'petugas-tik',
    'petugas Sarpras' => 'petugas-sarpras',
]);

test('regular user or management cannot create asset', function (string $role): void {
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);

    $this->postJson('/api/v1/assets', $payload)->assertForbidden();
})->with([
    'user' => 'user',
    'management' => 'manajemen',
]);

test('guest cannot create asset', function (): void {
    $this->postJson('/api/v1/assets', [])->assertUnauthorized();
});

test('super-admin can update asset detail_item', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $asset = Asset::factory()->forInventory($classification, $location, $item)->create([
        'detail_item' => 'Meja Makan',
    ]);

    $response = $this->putJson("/api/v1/assets/{$asset->id}", [
        'detail_item' => 'Meja Rapat',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.detail_item', 'Meja Rapat');

    $this->assertDatabaseHas('assets', [
        'id' => $asset->id,
        'detail_item' => 'Meja Rapat',
    ]);
});

test('detail_item validation rejects string longer than 255 characters', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);
    $payload['detail_item'] = str_repeat('a', 256);

    $this->postJson('/api/v1/assets', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('detail_item')
        ->assertJsonPath('errors.detail_item.0', 'Detail barang tidak boleh lebih dari 255 karakter.');
});

test('purchase_year > 2100 returns 422 with Indonesian validation message on create and update', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $payload = validAssetPayload($classification, $location, $item);
    $payload['purchase_year'] = 2101;

    $this->postJson('/api/v1/assets', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('purchase_year')
        ->assertJsonPath('message', 'Tahun pembelian tidak boleh lebih dari 2100.')
        ->assertJsonPath('errors.purchase_year.0', 'Tahun pembelian tidak boleh lebih dari 2100.');

    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $this->putJson("/api/v1/assets/{$asset->id}", [
        'purchase_year' => 2105,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('purchase_year')
        ->assertJsonPath('message', 'Tahun pembelian tidak boleh lebih dari 2100.')
        ->assertJsonPath('errors.purchase_year.0', 'Tahun pembelian tidak boleh lebih dari 2100.');
});

test('inventory validation messages are fully localized to Indonesian', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/assets', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'asset_classification_id',
            'asset_location_id',
            'asset_item_id',
            'purchase_year',
            'status',
            'condition',
        ])
        ->assertJsonPath('errors.asset_classification_id.0', 'Klasifikasi aset wajib dipilih.')
        ->assertJsonPath('errors.asset_location_id.0', 'Lokasi aset wajib dipilih.')
        ->assertJsonPath('errors.asset_item_id.0', 'Nama barang wajib dipilih.')
        ->assertJsonPath('errors.purchase_year.0', 'Tahun pembelian wajib diisi.')
        ->assertJsonPath('errors.status.0', 'Status aset wajib dipilih.')
        ->assertJsonPath('errors.condition.0', 'Kondisi aset wajib dipilih.');

    expect($response->json('message'))->toContain('Klasifikasi aset wajib dipilih.');
});
