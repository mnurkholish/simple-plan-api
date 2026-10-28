<?php

use App\Enums\AssetCondition;
use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetCode;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createMaintenanceLogTestUser(string $role): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->superAdmin = createMaintenanceLogTestUser('super-admin');
    $this->petugasTik = createMaintenanceLogTestUser('petugas-tik');
    $this->petugasLain = createMaintenanceLogTestUser('petugas-tik');

    $this->location = AssetLocation::factory()->create(['name' => 'Ruang IT', 'code' => 1]);
    $this->classification = AssetClassification::factory()->create(['name' => 'Alat IT', 'code' => 5]);
    $this->item = AssetItem::factory()->create([
        'asset_classification_id' => $this->classification->id,
        'name' => 'Laptop Petugas',
        'code' => 1,
    ]);

    $this->code = AssetCode::create([
        'asset_classification_id' => $this->classification->id,
        'asset_location_id' => $this->location->id,
        'asset_item_id' => $this->item->id,
        'sequence_number' => 1,
        'full_code' => '5/1/1/1/2026',
    ]);
    $this->asset = Asset::factory()->create(['asset_code_id' => $this->code->id]);
});

test('Petugas yang DITUNJUK berhasil submit log beserta upload foto bukti', function (): void {
    Storage::fake('public');
    Sanctum::actingAs($this->petugasTik);

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->code->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Debu dan Pasta',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-11-01',
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $photo = UploadedFile::fake()->image('bukti_maintenance.jpg', 640, 480);

    $response = $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Pembersihan debu kipas dan pergantian thermal paste',
        'maintenance_result' => 'Suhu laptop kembali dingin dan kipas berputar hening',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => '2026-11-01 09:00:00',
        'completed_at' => '2026-11-01 10:15:00',
        'photo' => $photo,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.work_description', 'Pembersihan debu kipas dan pergantian thermal paste')
        ->assertJsonPath('data.scheduled_date', '2026-11-01')
        ->assertJsonPath('data.post_maintenance_condition', AssetCondition::Good->value);

    $log = AssetMaintenanceLog::where('asset_maintenance_id', $schedule->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->proof_photo_object_key)->not->toBeNull()
        ->and($log->proof_photo_original_name)->toBe('bukti_maintenance.jpg');

    $this->assertDatabaseHas('asset_maintenance_logs', [
        'asset_maintenance_id' => $schedule->id,
        'proof_photo_original_name' => 'bukti_maintenance.jpg',
    ]);

    Storage::disk('public')->assertExists($log->proof_photo_object_key);
});

test('Petugas LAIN gagal submit log (403 Forbidden)', function (): void {
    Sanctum::actingAs($this->petugasLain);

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->code->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-11-01',
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $response = $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Mencoba mengisi pekerjaan orang lain',
        'maintenance_result' => 'Tidak diizinkan',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => '2026-11-01 09:00:00',
        'completed_at' => '2026-11-01 10:00:00',
    ]);

    $response->assertForbidden();
    expect(AssetMaintenanceLog::count())->toBe(0);
});

test('Setelah log disubmit, status Jadwal Induk kembali menjadi terjadwal dan next_scheduled_date bertambah sesuai periode', function (): void {
    Sanctum::actingAs($this->petugasTik);

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->code->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pengecekan Bulanan',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-11-01',
        'status' => MaintenanceStatus::Terlambat,
        'is_active' => true,
    ]);

    $response = $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Pemeriksaan rutin kelayakan hardware',
        'maintenance_result' => 'Semua komponen berfungsi optimal',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => '2026-11-05 08:30:00',
        'completed_at' => '2026-11-05 09:30:00',
    ]);

    $response->assertCreated();

    $schedule->refresh();

    // Pastikan status kembali menjadi 'terjadwal'
    expect($schedule->status)->toBe(MaintenanceStatus::Terjadwal);

    // Pastikan tanggal berikutnya dihitung dari tanggal jadwal lama (2026-11-01 + 1 bulan = 2026-12-01)
    expect($schedule->next_scheduled_date->format('Y-m-d'))->toBe('2026-12-01');

    // Pastikan log menyimpan scheduled_date snapshot dari jadwal lama
    $this->assertDatabaseHas('asset_maintenance_logs', [
        'asset_maintenance_id' => $schedule->id,
        'scheduled_date' => '2026-11-01',
        'work_description' => 'Pemeriksaan rutin kelayakan hardware',
    ]);
});
