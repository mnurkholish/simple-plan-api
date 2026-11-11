<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use App\Services\AssetLabelService;
use App\Services\AssetQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function authenticateAssetLabelExportUser(string $role = 'super-admin'): void
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);
}

function recordPreparedAssetLabels(): AssetLabelService
{
    $labels = new class(app(AssetQrCodeService::class)) extends AssetLabelService
    {
        public array $preparedAssetIds = [];

        public function prepareLabel(Asset $asset): array
        {
            $this->preparedAssetIds[] = $asset->id;

            return parent::prepareLabel($asset);
        }
    };

    app()->instance(AssetLabelService::class, $labels);

    return $labels;
}

test('pengguna yang diizinkan dapat mengekspor label untuk aset terpilih', function (): void {
    authenticateAssetLabelExportUser();
    $firstAsset = Asset::factory()->create();
    $secondAsset = Asset::factory()->create();
    $labels = recordPreparedAssetLabels();

    $response = $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'selected',
        'asset_ids' => [$secondAsset->id, $firstAsset->id],
        'width_mm' => 90,
        'height_mm' => 50,
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="asset-labels.pdf"');

    expect($labels->preparedAssetIds)->toBe([$secondAsset->id, $firstAsset->id]);
    $pdf = $response->getContent();
    expect($pdf)->toMatch('/^%PDF-\d\.\d/')
        ->toContain('startxref', '%%EOF');
    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(2);
    expect(preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $mediaBox))->toBe(1);
    expect(abs((float) $mediaBox[1] - 90 / 25.4 * 72))->toBeLessThan(1.42);
    expect(abs((float) $mediaBox[2] - 50 / 25.4 * 72))->toBeLessThan(1.42);
    expect(abs((float) $mediaBox[1] / (float) $mediaBox[2] - 90 / 50))->toBeLessThan(0.01);
});

test('pengguna yang diizinkan dapat mengekspor semua label sesuai filter tanpa paginasi', function (): void {
    authenticateAssetLabelExportUser();
    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create();
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id]);
    $firstAsset = Asset::factory()->forInventory($classification, $location, $item)->create();
    $secondAsset = Asset::factory()->forInventory($classification, $location, $item)->create();

    $otherClassification = AssetClassification::factory()->create(['name' => 'Peralatan Medis']);
    $otherItem = AssetItem::factory()->create(['asset_classification_id' => $otherClassification->id]);
    Asset::factory()->forInventory($otherClassification, $location, $otherItem)->create();
    $labels = recordPreparedAssetLabels();

    $response = $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered',
        'filters' => [
            'classification_id' => $classification->id,
            'page' => 1,
            'per_page' => 1,
        ],
        'width_mm' => 50,
        'height_mm' => 60,
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="asset-labels.pdf"');

    expect($labels->preparedAssetIds)->toBe([$secondAsset->id, $firstAsset->id]);
    $pdf = $response->getContent();
    expect($pdf)->toMatch('/^%PDF-\d\.\d/')
        ->toContain('startxref', '%%EOF');
    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(2);
    expect(preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $mediaBox))->toBe(1);
    expect(abs((float) $mediaBox[1] - 50 / 25.4 * 72))->toBeLessThan(1.42);
    expect(abs((float) $mediaBox[2] - 60 / 25.4 * 72))->toBeLessThan(1.42);
    expect(abs((float) $mediaBox[1] / (float) $mediaBox[2] - 50 / 60))->toBeLessThan(0.01);
});

test('request ekspor label dengan input tidak valid mengembalikan 422', function (): void {
    authenticateAssetLabelExportUser();
    $asset = Asset::factory()->create();

    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'selected',
        'asset_ids' => [$asset->id, 'invalid'],
        'width_mm' => 40,
        'height_mm' => 101,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['asset_ids.1', 'width_mm', 'height_mm']);
});

test('pengguna tanpa izin cetak tidak dapat mengekspor label aset', function (): void {
    authenticateAssetLabelExportUser('user');
    $asset = Asset::factory()->create();

    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'selected',
        'asset_ids' => [$asset->id],
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertForbidden();
});

test('ekspor semua label mengembalikan 422 jika filter tidak menemukan aset', function (): void {
    authenticateAssetLabelExportUser();
    Asset::factory()->create(['detail_item' => 'Komputer ruang server']);
    $emptyClassification = AssetClassification::factory()->create();

    $this->postJson('/api/v1/asset-label-exports', [
        'selection_mode' => 'all_filtered',
        'filters' => ['classification_id' => $emptyClassification->id],
        'width_mm' => 90,
        'height_mm' => 50,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('filters');
});
