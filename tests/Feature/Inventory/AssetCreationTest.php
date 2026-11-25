<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('create aset tanpa invoice_document berhasil dengan invoice_document bernilai null', function (): void {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $response = $this->postJson('/api/v1/assets', validAssetPayload($classification, $location, $item));

    $response->assertCreated()
        ->assertJsonPath('data.invoice_document', null);

    $this->assertDatabaseHas('assets', [
        'id' => $response->json('data.id'),
        'invoice_object_key' => null,
        'invoice_original_name' => null,
        'invoice_mime_type' => null,
        'invoice_size' => null,
    ]);
});

test('create aset dengan invoice_document PDF valid menyimpan file di storage, DB, dan tampil di response', function (): void {
    Storage::fake();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $invoicePdf = UploadedFile::fake()->create('invoice-pembelian.pdf', 256, 'application/pdf');
    $payload = validAssetPayload($classification, $location, $item);
    $payload['invoice_document'] = $invoicePdf;

    $response = $this->post('/api/v1/assets', $payload, ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.invoice_document.original_name', 'invoice-pembelian.pdf')
        ->assertJsonPath('data.invoice_document.mime_type', 'application/pdf');

    expect($response->json('data.invoice_document.size'))->toBeInt()->toBeGreaterThan(0);

    $asset = Asset::findOrFail($response->json('data.id'));
    expect($asset->invoice_object_key)->toStartWith('assets/invoices/')
        ->and($asset->invoice_original_name)->toBe('invoice-pembelian.pdf')
        ->and($asset->invoice_mime_type)->toBe('application/pdf')
        ->and($asset->invoice_size)->toBeGreaterThan(0);

    Storage::assertExists($asset->invoice_object_key);
});

test('upload file non-PDF pada invoice_document ditolak 422 dengan pesan Bahasa Indonesia', function (): void {
    Storage::fake();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $invalidFile = UploadedFile::fake()->image('invoice.jpg');
    $payload = validAssetPayload($classification, $location, $item);
    $payload['invoice_document'] = $invalidFile;

    $this->post('/api/v1/assets', $payload, ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoice_document')
        ->assertJsonPath('errors.invoice_document.0', 'Dokumen invoice harus berformat PDF.');

    $asset = Asset::factory()->forInventory($classification, $location, $item)->create();
    $invalidTextFile = UploadedFile::fake()->create('invoice.txt', 10, 'text/plain');

    $this->post("/api/v1/assets/{$asset->id}", [
        '_method' => 'PUT',
        'invoice_document' => $invalidTextFile,
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoice_document')
        ->assertJsonPath('errors.invoice_document.0', 'Dokumen invoice harus berformat PDF.');
});

test('update invoice_document via POST multipart _method=PUT mengganti file lama dan mencatat ke asset_histories', function (): void {
    Storage::fake();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create(['name' => 'Admin Inventaris']);
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $oldPdf = UploadedFile::fake()->create('invoice-lama.pdf', 120, 'application/pdf');
    $createPayload = validAssetPayload($classification, $location, $item);
    $createPayload['invoice_document'] = $oldPdf;

    $createResponse = $this->post('/api/v1/assets', $createPayload, ['Accept' => 'application/json'])->assertCreated();
    $asset = Asset::findOrFail($createResponse->json('data.id'));
    $oldObjectKey = $asset->invoice_object_key;
    Storage::assertExists($oldObjectKey);

    $newPdf = UploadedFile::fake()->create('invoice-revisi.pdf', 320, 'application/pdf');
    $updateResponse = $this->post("/api/v1/assets/{$asset->id}", [
        '_method' => 'PUT',
        'invoice_document' => $newPdf,
    ], ['Accept' => 'application/json']);

    $updateResponse->assertOk()
        ->assertJsonPath('data.invoice_document.original_name', 'invoice-revisi.pdf')
        ->assertJsonPath('data.invoice_document.mime_type', 'application/pdf');

    $asset->refresh();
    expect($asset->invoice_object_key)->not->toBe($oldObjectKey)
        ->and($asset->invoice_original_name)->toBe('invoice-revisi.pdf');

    Storage::assertMissing($oldObjectKey);
    Storage::assertExists($asset->invoice_object_key);

    $detailResponse = $this->getJson("/api/v1/assets/{$asset->id}")->assertOk();
    $detailResponse
        ->assertJsonCount(1, 'data.histories')
        ->assertJsonPath('data.histories.0.event_type', 'updated')
        ->assertJsonPath('data.histories.0.old_values.invoice_original_name', 'invoice-lama.pdf')
        ->assertJsonPath('data.histories.0.new_values.invoice_original_name', 'invoice-revisi.pdf')
        ->assertJsonPath('data.histories.0.old_values.invoice_object_key', $oldObjectKey)
        ->assertJsonPath('data.histories.0.new_values.invoice_object_key', $asset->invoice_object_key)
        ->assertJsonPath('data.histories.0.changed_by_name', 'Admin Inventaris');
});

test('update field aset lain tanpa mengirim invoice_document tidak menghapus invoice yang sudah ada', function (): void {
    Storage::fake();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $classification = AssetClassification::factory()->create();
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);

    $invoicePdf = UploadedFile::fake()->create('invoice-tetap.pdf', 180, 'application/pdf');
    $createPayload = validAssetPayload($classification, $location, $item);
    $createPayload['invoice_document'] = $invoicePdf;

    $createResponse = $this->post('/api/v1/assets', $createPayload, ['Accept' => 'application/json'])->assertCreated();
    $asset = Asset::findOrFail($createResponse->json('data.id'));
    $existingKey = $asset->invoice_object_key;
    $existingSize = $asset->invoice_size;

    $updateResponse = $this->putJson("/api/v1/assets/{$asset->id}", [
        'notes' => 'Catatan diperbarui tanpa mengubah invoice',
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.notes', 'Catatan diperbarui tanpa mengubah invoice')
        ->assertJsonPath('data.invoice_document.original_name', 'invoice-tetap.pdf')
        ->assertJsonPath('data.invoice_document.mime_type', 'application/pdf')
        ->assertJsonPath('data.invoice_document.size', $existingSize);

    $asset->refresh();
    expect($asset->invoice_object_key)->toBe($existingKey)
        ->and($asset->invoice_original_name)->toBe('invoice-tetap.pdf');
    Storage::assertExists($existingKey);
});
