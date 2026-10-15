<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetHistory;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $this->classification = AssetClassification::create(['code' => 5, 'name' => 'Alat IT']);
    $this->room = AssetLocation::create(['code' => 10, 'name' => 'Ruang IT']);
    $this->otherRoom = AssetLocation::create(['code' => 11, 'name' => 'Poli']);
    $this->item = AssetItem::create(['code' => 20, 'name' => 'Komputer']);
});

function inventoryPayload(array $overrides = []): array
{
    return array_merge([
        'asset_classification_id' => AssetClassification::firstOrFail()->id,
        'asset_location_id' => AssetLocation::firstOrFail()->id,
        'asset_item_id' => AssetItem::firstOrFail()->id,
        'purchase_year' => 2021,
        'brand' => 'Dell',
        'model_specification' => 'OptiPlex',
        'location_detail' => 'Lantai 1',
        'status' => 'Digunakan',
        'condition' => 'Baik',
    ], $overrides);
}

test('aset baru mendapat UID tetap dan kode berdasarkan tahun pembelian', function (): void {
    $this->getJson('/api/v1/assets/generate-number?asset_classification_id='.$this->classification->id
        .'&asset_location_id='.$this->room->id.'&asset_item_id='.$this->item->id.'&year=2021')
        ->assertOk()->assertJsonPath('data.full_code', '5/10/1/20/2021');

    $response = $this->postJson('/api/v1/assets', inventoryPayload())
        ->assertCreated()
        ->assertJsonPath('data.asset_code.full_code', '5/10/1/20/2021')
        ->assertJsonPath('data.brand', 'Dell');

    $uid = $response->json('data.asset_uid');
    expect($uid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    $asset = Asset::findOrFail($response->json('data.id'));
    expect($asset->asset_uid)->toBe($uid);
    $asset->update(['asset_uid' => '01AAAAAAAAAAAAAAAAAAAAAAAA']);
    expect($asset->fresh()->asset_uid)->toBe($uid);
    expect(AssetCode::count())->toBe(1);
});

test('satu update mencatat hanya perubahan dan membedakan mutasi ruangan dari detail', function (): void {
    $assetId = $this->postJson('/api/v1/assets', inventoryPayload())->json('data.id');
    $asset = Asset::findOrFail($assetId);
    $codeId = $asset->asset_code_id;

    $this->putJson("/api/v1/assets/{$assetId}", [
        'asset_location_id' => $this->otherRoom->id,
        'location_detail' => 'Lantai 2',
        'status' => 'Dipinjam',
    ])->assertOk()->assertJsonPath('data.asset_code.full_code', '5/11/1/20/2021');

    $history = AssetHistory::sole();
    expect($asset->fresh()->asset_code_id)->toBe($codeId)
        ->and($history->is_location_change)->toBeTrue()
        ->and($history->old_values)->toMatchArray([
            'asset_location_id' => $this->room->id,
            'full_code' => '5/10/1/20/2021',
            'location_detail' => 'Lantai 1',
            'status' => 'Digunakan',
        ])
        ->and($history->new_values)->toMatchArray([
            'asset_location_id' => $this->otherRoom->id,
            'full_code' => '5/11/1/20/2021',
            'location_detail' => 'Lantai 2',
            'status' => 'Dipinjam',
        ]);

    $this->putJson("/api/v1/assets/{$assetId}", ['location_detail' => 'Lantai 3'])
        ->assertOk()->assertJsonPath('data.asset_code.full_code', '5/11/1/20/2021');
    expect(AssetHistory::count())->toBe(2)
        ->and(AssetHistory::latest('id')->first()->is_location_change)->toBeFalse();

    $this->putJson("/api/v1/assets/{$assetId}", ['location_detail' => 'Lantai 3'])->assertOk();
    expect(AssetHistory::count())->toBe(2);
});

test('nomor lama dalam riwayat dan kode aset dihapus tidak diterbitkan ulang', function (): void {
    $firstId = $this->postJson('/api/v1/assets', inventoryPayload())->json('data.id');
    $this->putJson("/api/v1/assets/{$firstId}", ['asset_location_id' => $this->otherRoom->id])->assertOk();

    $second = $this->postJson('/api/v1/assets', inventoryPayload())->assertCreated();
    expect($second->json('data.asset_code.full_code'))->toBe('5/10/2/20/2021');
    $this->deleteJson('/api/v1/assets/'.$second->json('data.id'))->assertOk();
    expect(AssetCode::count())->toBe(2);

    $third = $this->postJson('/api/v1/assets', inventoryPayload())->assertCreated();
    expect($third->json('data.asset_code.full_code'))->toBe('5/10/3/20/2021');
});

test('master barang tidak menerima merek dan merek aset dapat dicari', function (): void {
    $this->postJson('/api/v1/asset-items', ['code' => 21, 'name' => 'Printer', 'brand' => 'Lama'])
        ->assertCreated()->assertJsonMissingPath('data.brand');
    $this->postJson('/api/v1/asset-items', ['code' => 21, 'name' => 'Duplikat'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/asset-locations', ['code' => 10, 'name' => 'Duplikat'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    $this->postJson('/api/v1/assets', inventoryPayload())->assertCreated();
    $this->getJson('/api/v1/assets?search=Dell')->assertOk()->assertJsonCount(1, 'data');
});

test('kode master yang sudah digunakan tidak dapat diubah', function (): void {
    $this->postJson('/api/v1/assets', inventoryPayload())->assertCreated();

    $this->putJson('/api/v1/asset-classifications/'.$this->classification->id, ['code' => 6, 'name' => 'Alat IT'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->putJson('/api/v1/asset-locations/'.$this->room->id, ['code' => 12, 'name' => 'Ruang IT'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->putJson('/api/v1/asset-items/'.$this->item->id, ['code' => 21, 'name' => 'Komputer'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    expect($this->classification->fresh()->code)->toBe(5);
});

test('tahun pembelian mengubah kode yang sama tanpa menandai mutasi lokasi', function (): void {
    $assetId = $this->postJson('/api/v1/assets', inventoryPayload())->json('data.id');
    $codeId = Asset::findOrFail($assetId)->asset_code_id;

    $this->putJson("/api/v1/assets/{$assetId}", ['purchase_year' => 2022, 'condition' => 'Rusak Ringan'])
        ->assertOk()->assertJsonPath('data.asset_code.full_code', '5/10/1/20/2022');

    $history = AssetHistory::sole();
    expect(Asset::findOrFail($assetId)->asset_code_id)->toBe($codeId)
        ->and($history->is_location_change)->toBeFalse()
        ->and($history->old_values['purchase_year'])->toBe(2021)
        ->and($history->new_values['purchase_year'])->toBe(2022)
        ->and($history->old_values['full_code'])->toBe('5/10/1/20/2021');

    $this->postJson('/api/v1/assets', inventoryPayload())
        ->assertCreated()->assertJsonPath('data.asset_code.full_code', '5/10/2/20/2021');
});

test('guest dan role tanpa akses inventaris ditolak', function (): void {
    auth()->forgetGuards();
    $this->postJson('/api/v1/assets', inventoryPayload())->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/assets', inventoryPayload())->assertForbidden();
    $this->getJson('/api/v1/asset-items')->assertForbidden();
    expect(Asset::count())->toBe(0);
});

test('aset Helpdesk lama tanpa kode tetap dapat ditampilkan', function (): void {
    $asset = Asset::create(['name' => 'Komputer lama', 'brand' => 'Dell', 'status' => 'Digunakan', 'condition' => 'Baik']);

    $this->getJson('/api/v1/assets/'.$asset->id)
        ->assertOk()
        ->assertJsonPath('data.id', $asset->id)
        ->assertJsonPath('data.asset_code', null);
    $this->getJson('/api/v1/assets')->assertOk()->assertJsonPath('data.0.asset_code', null);
});

test('penggantian foto mencatat metadata tanpa membuka object key pada API', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $created = $this->post('/api/v1/assets', inventoryPayload([
        'photo' => UploadedFile::fake()->image('lama.jpg'),
    ]), ['Accept' => 'application/json'])->assertCreated();
    $assetId = $created->json('data.id');
    $oldKey = Asset::findOrFail($assetId)->photo_object_key;
    Storage::disk('local')->assertExists($oldKey);

    $this->post('/api/v1/assets/'.$assetId, [
        '_method' => 'PUT',
        'photo' => UploadedFile::fake()->image('baru.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.photo.original_name', 'baru.jpg')
        ->assertJsonMissingPath('data.photo.object_key');

    $newKey = Asset::findOrFail($assetId)->photo_object_key;
    Storage::disk('local')->assertExists($newKey);
    expect($newKey)->not->toBe($oldKey)
        ->and(AssetHistory::sole()->old_values['photo_object_key'])->toBe($oldKey)
        ->and(AssetHistory::sole()->new_values['photo_object_key'])->toBe($newKey);
});
