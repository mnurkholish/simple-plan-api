<?php

use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\User;
use App\Services\AssetLabelService;
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

    $this->classification = AssetClassification::create(['code' => 5, 'name' => 'Alat IT']);
    $this->location = AssetLocation::create(['code' => 10, 'name' => 'Ruang IT']);
    $this->item = AssetItem::create(['code' => 20, 'name' => 'Komputer']);
});

function labelAssets(int $count): array
{
    return Asset::factory()->count($count)->state([
        'purchase_year' => 2021,
    ])->create()->pluck('id')->all();
}

function labelExportPayload(array $ids, float $width = 90, float $height = 50): array
{
    return ['asset_ids' => $ids, 'width_mm' => $width, 'height_mm' => $height];
}

test('asset factory creates unique inventory codes and model-generated UIDs', function (): void {
    $unsaved = Asset::factory()->make();
    expect($unsaved->exists)->toBeFalse()
        ->and(AssetCode::count())->toBe(0);

    $assets = Asset::factory()->count(3)->state(['purchase_year' => 2024])->create();

    expect($assets->pluck('asset_uid')->unique()->count())->toBe(3)
        ->and($assets->pluck('asset_code_id')->unique()->count())->toBe(3)
        ->and($assets->map(fn (Asset $asset): string => $asset->assetCode->full_code)->all())
        ->toBe(['5/10/1/20/2024', '5/10/2/20/2024', '5/10/3/20/2024']);
});

test('A4 export paginates all selected labels without an extra page', function (int $count, int $pages): void {
    $ids = labelAssets($count);

    $response = $this->postJson('/api/v1/asset-label-exports', labelExportPayload($ids))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $pdf = $response->getContent();
    preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $mediaBox);

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe($pages)
        ->and(abs((float) ($mediaBox[1] ?? 0) - 595.28))->toBeLessThan(1)
        ->and(abs((float) ($mediaBox[2] ?? 0) - 841.89))->toBeLessThan(1);
})->with([
    'one label' => [1, 1],
    'five labels' => [5, 1],
    'ten labels' => [10, 1],
    'eleven labels' => [11, 2],
    'twenty labels' => [20, 2],
]);

test('A4 export derives page capacity from label dimensions', function (float $width, float $height, int $pages): void {
    $ids = labelAssets(11);
    $pdf = $this->postJson('/api/v1/asset-label-exports', labelExportPayload($ids, $width, $height))
        ->assertOk()->getContent();

    expect(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe($pages);
})->with([
    'small labels fit on one page' => [70, 40, 1],
    'large labels need six pages' => [150, 100, 6],
]);

test('A4 export rejects invalid assets and dimensions', function (): void {
    $id = labelAssets(1)[0];

    foreach ([
        [[], 'asset_ids'],
        [[$id, $id], 'asset_ids.1'],
        [[$id, 999999], 'asset_ids.1'],
    ] as [$ids, $field]) {
        $this->postJson('/api/v1/asset-label-exports', labelExportPayload($ids))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    foreach ([[69, 50, 'width_mm'], [151, 50, 'width_mm'], [90, 39, 'height_mm'], [90, 101, 'height_mm']] as [$width, $height, $field]) {
        $this->postJson('/api/v1/asset-label-exports', labelExportPayload([$id], $width, $height))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    Asset::findOrFail($id)->delete();
    $this->postJson('/api/v1/asset-label-exports', labelExportPayload([$id]))
        ->assertUnprocessable()->assertJsonValidationErrors('asset_ids.0');
});

test('A4 export requires authentication and permission for every selected asset', function (): void {
    $ids = labelAssets(2);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/asset-label-exports', labelExportPayload($ids))->assertForbidden();

    $manager = User::factory()->create();
    $manager->assignRole('manajemen');
    Sanctum::actingAs($manager);
    $this->postJson('/api/v1/asset-label-exports', labelExportPayload($ids))->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/asset-label-exports', labelExportPayload($ids))->assertUnauthorized();
});

test('each label has its own QR image and the single label HTML remains available', function (): void {
    $ids = labelAssets(20);
    [$firstId, $secondId] = $ids;
    $service = app(AssetLabelService::class);
    $first = $service->prepareLabel(Asset::findOrFail($firstId));
    $second = $service->prepareLabel(Asset::findOrFail($secondId));
    $qrImages = array_map(
        fn (int $id): string => $service->prepareLabel(Asset::findOrFail($id))['qrSvg'],
        $ids,
    );

    expect($first['qrSvg'])->toContain('<svg')
        ->and($second['qrSvg'])->toContain('<svg')
        ->and($first['qrSvg'])->not->toBe($second['qrSvg'])
        ->and(count(array_unique($qrImages)))->toBe(20);

    $this->get("/api/v1/assets/{$firstId}/label")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee($first['inventoryNumber'])
        ->assertSee($first['asset']->model_specification);
});

test('single label PNG and PDF exports still work with requested dimensions', function (): void {
    $id = labelAssets(1)[0];

    $pdf = $this->get("/api/v1/assets/{$id}/label?format=pdf&width_mm=100&height_mm=60")
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    $png = $this->get("/api/v1/assets/{$id}/label?format=png&width_mm=100&height_mm=60")
        ->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();

    expect($pdf)->toStartWith('%PDF-')
        ->and($png)->toStartWith("\x89PNG\r\n\x1a\n");
});
