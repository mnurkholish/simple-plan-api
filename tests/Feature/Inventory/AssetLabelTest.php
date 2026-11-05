<?php

use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function authenticateAssetLabelUser(string $role = 'super-admin'): void
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);
}

test('pengguna yang diizinkan dapat membuat label HTML untuk aset', function (): void {
    authenticateAssetLabelUser();
    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create(['name' => 'Ruang Server']);
    $item = AssetItem::factory()->create([
        'name' => 'Server',
        'asset_classification_id' => $classification->id,
    ]);
    $asset = Asset::factory()->forInventory($classification, $location, $item)->create([
        'detail_item' => 'Lenovo ThinkSystem',
        'condition' => AssetCondition::Good,
    ]);
    $asset->refresh()->load('assetCode');

    $response = $this->get("/api/v1/assets/{$asset->id}/label?format=html");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'inline');

    expect($response->getContent())
        ->toContain('KARTU INVENTARIS', 'RS CITRA HUSADA')
        ->toContain($asset->assetCode->full_code)
        ->toContain('Server Lenovo ThinkSystem', 'Ruang Server', 'Baik')
        ->toContain('class="qr-code"', '<svg');
});

test('pengguna yang diizinkan dapat membuat label PNG untuk aset', function (): void {
    authenticateAssetLabelUser();
    $asset = Asset::factory()->create();

    $response = $this->get("/api/v1/assets/{$asset->id}/label?format=png&width_mm=70&height_mm=40");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="asset-label-'.$asset->asset_uid.'.png"')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $image = getimagesizefromstring($response->getContent());

    expect($response->getContent())->toStartWith("\x89PNG\r\n\x1a\n");
    expect($image)->not->toBeFalse();
    expect($image['mime'])->toBe('image/png');
    expect($image[0])->toBeGreaterThan(0);
    expect($image[1])->toBeGreaterThan(0);
});

test('pengguna yang diizinkan dapat membuat label PDF untuk aset', function (): void {
    authenticateAssetLabelUser();
    $asset = Asset::factory()->create();

    $response = $this->get("/api/v1/assets/{$asset->id}/label?format=pdf&width_mm=70&height_mm=40");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="asset-label-'.$asset->asset_uid.'.pdf"')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $pdf = $response->getContent();

    expect($pdf)->toMatch('/^%PDF-\d\.\d/')
        ->toContain('startxref', '%%EOF');
});

test('request label dengan format tidak valid mengembalikan 422', function (): void {
    authenticateAssetLabelUser();
    $asset = Asset::factory()->create();

    $this->getJson("/api/v1/assets/{$asset->id}/label?format=docx")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});

test('pengguna tanpa izin cetak tidak dapat membuat label PDF untuk aset', function (): void {
    authenticateAssetLabelUser('user');
    $asset = Asset::factory()->create();

    $this->getJson("/api/v1/assets/{$asset->id}/label?format=pdf")
        ->assertForbidden();
});
