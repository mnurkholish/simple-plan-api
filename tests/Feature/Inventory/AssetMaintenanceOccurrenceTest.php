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
use App\Models\AssetMaintenanceOccurrence;
use App\Models\User;
use App\Services\AssetMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createOccurrenceTestUser(string $role): User
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
    $this->superAdmin = createOccurrenceTestUser('super-admin');
    $this->koordSarpras = createOccurrenceTestUser('koordinator-sarpras');
    $this->petugasTik = createOccurrenceTestUser('petugas-tik');
    $this->petugasSarpras = createOccurrenceTestUser('petugas-sarpras');
    $this->manajemen = createOccurrenceTestUser('manajemen');

    $this->locationServer = AssetLocation::factory()->create(['name' => 'Ruang Server', 'code' => 1]);
    $this->locationGenset = AssetLocation::factory()->create(['name' => 'Ruang Genset', 'code' => 2]);

    $this->classificationTik = AssetClassification::factory()->create(['name' => 'Alat IT', 'code' => 5]);
    $this->itemTik = AssetItem::factory()->create([
        'asset_classification_id' => $this->classificationTik->id,
        'name' => 'Server Utama',
        'code' => 1,
    ]);
    $this->codeTik = AssetCode::create([
        'asset_classification_id' => $this->classificationTik->id,
        'asset_location_id' => $this->locationServer->id,
        'asset_item_id' => $this->itemTik->id,
        'sequence_number' => 1,
        'full_code' => '5/1/1/1/2026',
    ]);
    $this->assetTik = Asset::factory()->create(['asset_code_id' => $this->codeTik->id]);

    $this->classificationSarpras = AssetClassification::factory()->create(['name' => 'Alat Sarpras', 'code' => 3]);
    $this->itemSarpras = AssetItem::factory()->create([
        'asset_classification_id' => $this->classificationSarpras->id,
        'name' => 'Genset Cadangan',
        'code' => 2,
    ]);
    $this->codeSarpras = AssetCode::create([
        'asset_classification_id' => $this->classificationSarpras->id,
        'asset_location_id' => $this->locationGenset->id,
        'asset_item_id' => $this->itemSarpras->id,
        'sequence_number' => 1,
        'full_code' => '3/2/2/1/2026',
    ]);
    $this->assetSarpras = Asset::factory()->create(['asset_code_id' => $this->codeSarpras->id]);
});

test('Pembuatan jadwal maintenance otomatis membuat occurrence awal secara idempoten', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $scheduledDate = today(config('app.operational_timezone'))->addDays(10)->toDateString();

    $response = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pembersihan Rutin Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $scheduledDate,
    ]);

    $response->assertCreated();
    $scheduleId = $response->json('data.id');

    expect(AssetMaintenanceOccurrence::where('asset_maintenance_id', $scheduleId)->count())->toBe(1);

    $this->assertDatabaseHas('asset_maintenance_occurrences', [
        'asset_maintenance_id' => $scheduleId,
        'asset_code_id' => $this->codeTik->id,
        'asset_maintenance_log_id' => null,
        'period' => MaintenancePeriod::Monthly->value,
        'scheduled_date' => $scheduledDate,
        'status' => MaintenanceStatus::Terjadwal->value,
        'is_late' => false,
    ]);

    $schedule = AssetMaintenance::findOrFail($scheduleId);
    app(AssetMaintenanceService::class)->syncScheduleOccurrence($schedule);
    app(AssetMaintenanceService::class)->syncScheduleOccurrence($schedule);

    expect($schedule->occurrences()->count())->toBe(1);
});

test('Pembaruan jadwal menyinkronkan occurrence terbuka tanpa duplikasi dan menjaga histori selesai', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $initialDate = today(config('app.operational_timezone'))->addDays(7)->toDateString();
    $rescheduledDate = today(config('app.operational_timezone'))->addDays(14)->toDateString();

    $createResponse = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $initialDate,
    ]);

    $createResponse->assertCreated();
    $schedule = AssetMaintenance::findOrFail($createResponse->json('data.id'));

    // Reschedule tanggal dan periode
    $updateResponse = $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'next_scheduled_date' => $rescheduledDate,
        'period' => MaintenancePeriod::Quarterly->value,
    ]);

    $updateResponse->assertOk();

    expect($schedule->occurrences()->count())->toBe(1);

    $occurrence = $schedule->occurrences()->first();
    expect($occurrence->scheduled_date->format('Y-m-d'))->toBe($rescheduledDate)
        ->and($occurrence->period)->toBe(MaintenancePeriod::Quarterly)
        ->and($occurrence->status)->toBe(MaintenanceStatus::Terjadwal);

    // Panggilan update ulang dengan nilai yang sama tetap idempoten
    $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'next_scheduled_date' => $rescheduledDate,
        'period' => MaintenancePeriod::Quarterly->value,
    ])->assertOk();

    expect($schedule->occurrences()->count())->toBe(1);
});

test('Realisasi tepat waktu memperbarui occurrence menjadi selesai, menautkan log, dan membuat siklus berikutnya', function (): void {
    Sanctum::actingAs($this->superAdmin);

    $firstCycleDate = today(config('app.operational_timezone'))->toDateString();
    $createResponse = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pengecekan Bulanan Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $firstCycleDate,
    ]);

    $createResponse->assertCreated();
    $schedule = AssetMaintenance::findOrFail($createResponse->json('data.id'));

    Sanctum::actingAs($this->petugasTik);

    $logResponse = $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Membersihkan rack server dan cek UPS',
        'maintenance_result' => 'Seluruh unit berfungsi normal',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => "{$firstCycleDate}T08:00:00+07:00",
        'completed_at' => "{$firstCycleDate}T09:30:00+07:00",
    ]);

    $logResponse->assertCreated();
    $log = AssetMaintenanceLog::findOrFail($logResponse->json('data.id'));

    $schedule->refresh();
    $nextCycleDate = $schedule->next_scheduled_date->format('Y-m-d');

    expect($schedule->occurrences()->count())->toBe(2);

    $completedOccurrence = $schedule->occurrences()->whereDate('scheduled_date', $firstCycleDate)->first();
    expect($completedOccurrence)->not->toBeNull()
        ->and($completedOccurrence->status)->toBe(MaintenanceStatus::Selesai)
        ->and($completedOccurrence->asset_maintenance_log_id)->toBe($log->id)
        ->and($completedOccurrence->realized_at->format('Y-m-d H:i:s'))->toBe(CarbonImmutable::parse("{$firstCycleDate}T09:30:00+07:00")->utc()->format('Y-m-d H:i:s'))
        ->and($completedOccurrence->is_late)->toBeFalse()
        ->and($completedOccurrence->wasRealizedLate())->toBeFalse()
        ->and($completedOccurrence->isOverdue())->toBeFalse()
        ->and($completedOccurrence->overdueDays())->toBe(0);

    // Relasi balik
    expect($log->occurrence?->id)->toBe($completedOccurrence->id)
        ->and($completedOccurrence->log?->id)->toBe($log->id)
        ->and($completedOccurrence->maintenance?->id)->toBe($schedule->id)
        ->and($completedOccurrence->assetCode?->id)->toBe($this->codeTik->id)
        ->and($completedOccurrence->asset?->id)->toBe($this->assetTik->id)
        ->and($this->codeTik->maintenanceOccurrences()->count())->toBe(2)
        ->and($this->assetTik->maintenanceOccurrences()->count())->toBe(2);

    $nextOccurrence = $schedule->occurrences()->whereDate('scheduled_date', $nextCycleDate)->first();
    expect($nextOccurrence)->not->toBeNull()
        ->and($nextOccurrence->status)->toBe(MaintenanceStatus::Terjadwal)
        ->and($nextOccurrence->asset_maintenance_log_id)->toBeNull()
        ->and($nextOccurrence->realized_at)->toBeNull()
        ->and($nextOccurrence->is_late)->toBeFalse();
});

test('Realisasi terlambat menandai occurrence selesai dengan is_late true dan menghitung keterlambatan secara akurat', function (): void {
    $scheduledDate = today(config('app.operational_timezone'))->subDays(5)->toDateString();
    $realizedDate = today(config('app.operational_timezone'))->subDays(1)->toDateString();

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pengecekan Mingguan',
        'period' => MaintenancePeriod::Weekly,
        'next_scheduled_date' => $scheduledDate,
        'status' => MaintenanceStatus::Terlambat,
        'is_active' => true,
    ]);

    app(AssetMaintenanceService::class)->syncScheduleOccurrence($schedule);

    $openOccurrence = $schedule->occurrences()->first();
    expect($openOccurrence->status)->toBe(MaintenanceStatus::Terlambat)
        ->and($openOccurrence->isOverdue())->toBeTrue()
        ->and($openOccurrence->overdueDays())->toBe(5);

    Sanctum::actingAs($this->petugasTik);

    $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Pemeriksaan dilakukan terlambat 4 hari',
        'maintenance_result' => 'Kondisi perangkat baik',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => "{$realizedDate}T10:00:00+07:00",
        'completed_at' => "{$realizedDate}T11:00:00+07:00",
    ])->assertCreated();

    $openOccurrence->refresh();
    expect($openOccurrence->status)->toBe(MaintenanceStatus::Selesai)
        ->and($openOccurrence->asset_maintenance_log_id)->not->toBeNull()
        ->and($openOccurrence->realized_at->format('Y-m-d H:i:s'))->toBe(CarbonImmutable::parse("{$realizedDate}T11:00:00+07:00")->utc()->format('Y-m-d H:i:s'))
        ->and($openOccurrence->is_late)->toBeTrue()
        ->and($openOccurrence->wasRealizedLate())->toBeTrue()
        ->and($openOccurrence->isOverdue())->toBeFalse()
        ->and($openOccurrence->resolveEffectiveStatus())->toBe(MaintenanceStatus::Selesai)
        ->and($openOccurrence->overdueDays())->toBe(4);

    // Siklus berikutnya (scheduledDate + 7 hari = today + 2 hari) harus berstatus terjadwal
    $nextDate = today(config('app.operational_timezone'))->addDays(2)->toDateString();
    $nextOccurrence = $schedule->occurrences()->whereDate('scheduled_date', $nextDate)->first();
    expect($nextOccurrence)->not->toBeNull()
        ->and($nextOccurrence->status)->toBe(MaintenanceStatus::Terjadwal)
        ->and($nextOccurrence->isOverdue())->toBeFalse();
});

test('Realisasi jadwal yang sangat tertinggal tetap mendeteksi siklus berikutnya sebagai terlambat bila masih lewat hari ini', function (): void {
    $veryOldScheduledDate = today(config('app.operational_timezone'))->subDays(20)->toDateString();
    $realizedDate = today(config('app.operational_timezone'))->toDateString();

    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Pengecekan Mingguan Tertinggal',
        'period' => MaintenancePeriod::Weekly,
        'next_scheduled_date' => $veryOldScheduledDate,
        'status' => MaintenanceStatus::Terlambat,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->petugasTik);

    $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Eksekusi susulan',
        'maintenance_result' => 'Baik',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => "{$realizedDate}T08:00:00+07:00",
        'completed_at' => "{$realizedDate}T09:00:00+07:00",
    ])->assertCreated();

    $nextDate = today(config('app.operational_timezone'))->subDays(13)->toDateString();
    $nextOccurrence = $schedule->occurrences()->whereDate('scheduled_date', $nextDate)->first();
    expect($nextOccurrence)->not->toBeNull()
        ->and($nextOccurrence->status)->toBe(MaintenanceStatus::Terlambat)
        ->and($nextOccurrence->isOverdue())->toBeTrue();
});

test('Reschedule ke tanggal yang sudah memiliki occurrence selesai tidak menimpa histori dan menghapus occurrence lama yang terbuka', function (): void {
    Notification::fake();
    Sanctum::actingAs($this->superAdmin);

    $firstDate = today(config('app.operational_timezone'))->toDateString();
    $createResponse = $this->postJson('/api/v1/asset-maintenances', [
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'maintenance_type' => 'Pembersihan Server',
        'period' => MaintenancePeriod::Monthly->value,
        'next_scheduled_date' => $firstDate,
    ]);
    $schedule = AssetMaintenance::findOrFail($createResponse->json('data.id'));

    Sanctum::actingAs($this->petugasTik);
    $logResponse = $this->postJson("/api/v1/asset-maintenances/{$schedule->id}/logs", [
        'work_description' => 'Pembersihan',
        'maintenance_result' => 'Bersih',
        'post_maintenance_condition' => AssetCondition::Good->value,
        'started_at' => "{$firstDate}T08:00:00+07:00",
        'completed_at' => "{$firstDate}T08:45:00+07:00",
    ])->assertCreated();

    $logId = $logResponse->json('data.id');

    Sanctum::actingAs($this->superAdmin);
    $this->putJson("/api/v1/asset-maintenances/{$schedule->id}", [
        'next_scheduled_date' => $firstDate,
    ])->assertOk();

    expect($schedule->occurrences()->count())->toBe(1);

    $occurrence = $schedule->occurrences()->first();
    expect($occurrence->status)->toBe(MaintenanceStatus::Selesai)
        ->and($occurrence->asset_maintenance_log_id)->toBe($logId);
});

test('Command maintenance:check-status menyinkronkan occurrence, menandai overdue, backfill log, dan berjalan idempoten', function (): void {
    Notification::fake();

    $pastDate = today(config('app.operational_timezone'))->subDays(3)->toDateString();
    $futureDate = today(config('app.operational_timezone'))->addDays(5)->toDateString();
    $inactivePastDate = today(config('app.operational_timezone'))->subDays(4)->toDateString();

    $overdueSchedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Maintenance Terlambat',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => $pastDate,
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    // Pre-existing open occurrence still marked terjadwal
    AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $overdueSchedule->id,
        'asset_code_id' => $this->codeTik->id,
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => $pastDate,
        'status' => MaintenanceStatus::Terjadwal,
    ]);

    // Legacy log without an occurrence row
    $legacyDate = today(config('app.operational_timezone'))->subMonth()->toDateString();
    $legacyLog = AssetMaintenanceLog::create([
        'asset_maintenance_id' => $overdueSchedule->id,
        'scheduled_date' => $legacyDate,
        'work_description' => 'Log lama sebelum fitur occurrence',
        'maintenance_result' => 'Normal',
        'post_maintenance_condition' => AssetCondition::Good,
        'started_at' => "{$legacyDate}T09:00:00+07:00",
        'completed_at' => "{$legacyDate}T10:00:00+07:00",
    ]);

    $futureSchedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Maintenance Mendatang',
        'period' => MaintenancePeriod::Quarterly,
        'next_scheduled_date' => $futureDate,
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $inactiveSchedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Maintenance Nonaktif',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => $inactivePastDate,
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => false,
    ]);

    $this->artisan('maintenance:check-status')->assertSuccessful();
    $this->artisan('maintenance:check-status')->assertSuccessful();

    // Legacy log berhasil di-backfill ke occurrence selesai tanpa duplikat
    $legacyOccurrence = AssetMaintenanceOccurrence::where('asset_maintenance_log_id', $legacyLog->id)->first();
    expect($legacyOccurrence)->not->toBeNull()
        ->and($legacyOccurrence->status)->toBe(MaintenanceStatus::Selesai)
        ->and($legacyOccurrence->is_late)->toBeFalse();

    // Occurrence overdue berubah menjadi terlambat
    $overdueOccurrence = $overdueSchedule->occurrences()->whereDate('scheduled_date', $pastDate)->first();
    expect($overdueOccurrence)->not->toBeNull()
        ->and($overdueOccurrence->status)->toBe(MaintenanceStatus::Terlambat);

    // Active future schedule disinkronkan sebagai terjadwal
    $futureOccurrence = $futureSchedule->occurrences()->whereDate('scheduled_date', $futureDate)->first();
    expect($futureOccurrence)->not->toBeNull()
        ->and($futureOccurrence->status)->toBe(MaintenanceStatus::Terjadwal);

    // Jadwal nonaktif tidak dibuatkan occurrence baru
    expect($inactiveSchedule->occurrences()->count())->toBe(0);

    // Total occurrence tepat 3 (1 legacy log + 1 overdue + 1 future), tidak ada duplikasi pada eksekusi ke-2
    expect(AssetMaintenanceOccurrence::count())->toBe(3);
});

test('Constraint unik database mencegah duplikasi occurrence untuk jadwal dan scheduled_date yang sama', function (): void {
    $schedule = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Inspeksi Unik',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-12-01',
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $schedule->id,
        'asset_code_id' => $this->codeTik->id,
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => '2026-12-01',
        'status' => MaintenanceStatus::Terjadwal,
    ]);

    expect(fn () => AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $schedule->id,
        'asset_code_id' => $this->codeTik->id,
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => '2026-12-01',
        'status' => MaintenanceStatus::Terjadwal,
    ]))->toThrow(QueryException::class);
});

test('Query scope AssetMaintenanceOccurrence memfilter rentang tanggal, status, aset, klasifikasi, lokasi, dan visibilitas role', function (): void {
    $scheduleTik = AssetMaintenance::create([
        'asset_code_id' => $this->codeTik->id,
        'assigned_officer_id' => $this->petugasTik->id,
        'creator_id' => $this->superAdmin->id,
        'maintenance_type' => 'Maintenance Server',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => '2026-11-25',
        'status' => MaintenanceStatus::Terjadwal,
        'is_active' => true,
    ]);

    $scheduleSarpras = AssetMaintenance::create([
        'asset_code_id' => $this->codeSarpras->id,
        'assigned_officer_id' => $this->petugasSarpras->id,
        'creator_id' => $this->koordSarpras->id,
        'maintenance_type' => 'Maintenance Genset',
        'period' => MaintenancePeriod::Quarterly,
        'next_scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Terlambat,
        'is_active' => true,
    ]);

    $occScheduled = AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $scheduleTik->id,
        'asset_code_id' => $this->codeTik->id,
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => '2026-11-25',
        'status' => MaintenanceStatus::Terjadwal,
        'is_late' => false,
    ]);

    $occOverdue = AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $scheduleSarpras->id,
        'asset_code_id' => $this->codeSarpras->id,
        'period' => MaintenancePeriod::Quarterly,
        'scheduled_date' => '2026-11-10',
        'status' => MaintenanceStatus::Terlambat,
        'is_late' => false,
    ]);

    $logTik = AssetMaintenanceLog::create([
        'asset_maintenance_id' => $scheduleTik->id,
        'scheduled_date' => '2026-10-25',
        'work_description' => 'Selesai terlambat',
        'maintenance_result' => 'Normal',
        'post_maintenance_condition' => AssetCondition::Good,
        'started_at' => '2026-10-28 08:00:00',
        'completed_at' => '2026-10-28 09:00:00',
    ]);

    $occCompletedLate = AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $scheduleTik->id,
        'asset_code_id' => $this->codeTik->id,
        'asset_maintenance_log_id' => $logTik->id,
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => '2026-10-25',
        'status' => MaintenanceStatus::Selesai,
        'realized_at' => '2026-10-28 09:00:00',
        'is_late' => true,
    ]);

    expect(AssetMaintenanceOccurrence::scheduledBetween('2026-11-01', '2026-11-30')->pluck('id')->all())
        ->toEqualCanonicalizing([$occScheduled->id, $occOverdue->id]);

    expect(AssetMaintenanceOccurrence::realizedBetween('2026-10-20', '2026-10-31')->pluck('id')->all())
        ->toBe([$occCompletedLate->id]);

    expect(AssetMaintenanceOccurrence::scheduled()->pluck('id')->all())->toBe([$occScheduled->id])
        ->and(AssetMaintenanceOccurrence::completed()->pluck('id')->all())->toBe([$occCompletedLate->id])
        ->and(AssetMaintenanceOccurrence::overdue('2026-11-18')->pluck('id')->all())->toBe([$occOverdue->id])
        ->and(AssetMaintenanceOccurrence::realizedLate()->pluck('id')->all())->toBe([$occCompletedLate->id])
        ->and(AssetMaintenanceOccurrence::forAsset($this->assetTik)->pluck('id')->all())
        ->toEqualCanonicalizing([$occScheduled->id, $occCompletedLate->id])
        ->and(AssetMaintenanceOccurrence::forAsset($this->assetSarpras->id)->pluck('id')->all())
        ->toBe([$occOverdue->id])
        ->and(AssetMaintenanceOccurrence::forClassification($this->classificationTik)->pluck('id')->all())
        ->toEqualCanonicalizing([$occScheduled->id, $occCompletedLate->id])
        ->and(AssetMaintenanceOccurrence::forLocation($this->locationGenset)->pluck('id')->all())
        ->toBe([$occOverdue->id])
        ->and(AssetMaintenanceOccurrence::forOfficer($this->petugasSarpras)->pluck('id')->all())
        ->toBe([$occOverdue->id]);

    // Role visibility isolation
    expect(AssetMaintenanceOccurrence::visibleTo($this->superAdmin)->pluck('id')->all())
        ->toEqualCanonicalizing([$occScheduled->id, $occOverdue->id, $occCompletedLate->id])
        ->and(AssetMaintenanceOccurrence::visibleTo($this->koordSarpras)->pluck('id')->all())
        ->toBe([$occOverdue->id])
        ->and(AssetMaintenanceOccurrence::visibleTo($this->petugasTik)->pluck('id')->all())
        ->toEqualCanonicalizing([$occScheduled->id, $occCompletedLate->id])
        ->and(AssetMaintenanceOccurrence::visibleTo($this->manajemen)->pluck('id')->all())
        ->toEqualCanonicalizing([$occScheduled->id, $occOverdue->id, $occCompletedLate->id]);
});
