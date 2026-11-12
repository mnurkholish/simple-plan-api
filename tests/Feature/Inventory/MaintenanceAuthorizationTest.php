<?php

use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'koordinator-sarpras', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'petugas-sarpras', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('Ninda (User) mendapat 403 Forbidden saat menembak endpoint Maintenance', function (): void {
    $ninda = User::factory()->create(['name' => 'Ninda']);
    $ninda->assignRole('user');

    $classification = AssetClassification::factory()->create(['name' => 'Alat IT']);
    $location = AssetLocation::factory()->create(['name' => 'Ruang Server']);
    $item = AssetItem::factory()->create(['asset_classification_id' => $classification->id, 'name' => 'Switch']);
    $code = AssetCode::create([
        'asset_classification_id' => $classification->id,
        'asset_location_id' => $location->id,
        'asset_item_id' => $item->id,
        'sequence_number' => 1,
        'full_code' => "{$classification->id}.{$location->id}.{$item->id}.0001",
    ]);

    $maintenance = AssetMaintenance::create([
        'asset_code_id' => $code->id,
        'assigned_officer_id' => User::factory()->create()->id,
        'creator_id' => User::factory()->create()->id,
        'maintenance_type' => 'Pembersihan Rutin',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    Sanctum::actingAs($ninda);

    // List maintenance
    $this->getJson('/api/v1/asset-maintenances')->assertForbidden();

    // Create maintenance
    $this->postJson('/api/v1/asset-maintenances', [
        'maintenance_type' => 'Pembersihan Rutin',
    ])->assertForbidden();

    // Show maintenance
    $this->getJson("/api/v1/asset-maintenances/{$maintenance->id}")->assertForbidden();

    // Store maintenance log
    $this->postJson("/api/v1/asset-maintenances/{$maintenance->id}/logs", [])->assertForbidden();
});

test('Role berwenang dapat mengakses modul maintenance', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/asset-maintenances')->assertOk();
})->with([
    'super-admin',
    'koordinator-sarpras',
    'petugas-tik',
    'petugas-sarpras',
    'management',
]);
