<?php

use App\Enums\AssetStatus;
use App\Enums\DesignRequestStatus;
use App\Enums\MaintenancePeriod;
use App\Enums\MaintenanceStatus;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\AssetClassification;
use App\Models\AssetItem;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceOccurrence;
use App\Models\BackupHistory;
use App\Models\DesignRequest;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'Asia/Jakarta'));
    $this->seed([CorePermissionSeeder::class, DomainPermissionSeeder::class, RoleSeeder::class]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function dashboardActor(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole($role);

    return $user;
}

function dashboardTicket(User $reporter, TicketService $service, TicketStatus $status, ?User $officer = null, ?string $createdAt = null): Ticket
{
    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => $service,
        'status' => $status,
        'assigned_officer_id' => $officer?->id,
    ]);
    if ($createdAt !== null) {
        Ticket::whereKey($ticket->id)->update(['created_at' => $createdAt]);
    }

    return $ticket;
}

function dashboardDesign(User $requester, DesignRequestStatus $status, ?User $officer = null): DesignRequest
{
    static $sequence = 1;

    return DesignRequest::create([
        'request_number' => sprintf('DASH-%05d', $sequence++),
        'title' => 'Desain Dashboard',
        'design_type' => 'Poster',
        'description' => 'Materi unit',
        'needed_at' => now()->addWeek(),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => $status,
        'assigned_officer_id' => $officer?->id,
    ]);
}

function dashboardAsset(string $classification, AssetStatus $status): Asset
{
    $class = AssetClassification::firstOrCreate(['name' => $classification], ['code' => AssetClassification::count() + 1]);
    $item = AssetItem::factory()->create(['asset_classification_id' => $class->id]);

    return Asset::factory()->forInventory($class, AssetLocation::factory()->create(), $item)
        ->create(['status' => $status]);
}

function dashboardOccurrence(Asset $asset, User $officer, string $date, MaintenanceStatus $status = MaintenanceStatus::Terjadwal, bool $active = true): AssetMaintenanceOccurrence
{
    $maintenance = AssetMaintenance::create([
        'asset_code_id' => $asset->asset_code_id,
        'assigned_officer_id' => $officer->id,
        'creator_id' => $officer->id,
        'maintenance_type' => 'Pemeriksaan',
        'period' => MaintenancePeriod::Monthly,
        'next_scheduled_date' => $date,
        'status' => $status,
        'is_active' => $active,
    ]);

    return AssetMaintenanceOccurrence::create([
        'asset_maintenance_id' => $maintenance->id,
        'asset_code_id' => $asset->asset_code_id,
        'period' => MaintenancePeriod::Monthly,
        'scheduled_date' => $date,
        'status' => $status,
        'realized_at' => $status === MaintenanceStatus::Selesai ? $date.' 12:00:00' : null,
    ]);
}

test('guest ditolak dan dashboard kosong tetap konsisten', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();

    Sanctum::actingAs(dashboardActor('koordinator-sarpras'));
    $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($data['summary']['waktu_backup_terakhir'])->toBeNull()
        ->and(array_values(array_diff_key($data['summary'], ['waktu_backup_terakhir' => null])))->each->toBe(0);
    expect(array_values($data['charts']['tren_tiket_per_bulan']))->toHaveCount(6)->each->toBe(0);
});

test('super admin dan management memperoleh statistik seluruh sistem dan grafik enam bulan yang sama', function (): void {
    $reporter = dashboardActor('user');
    $officer = dashboardActor('petugas-tik');
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Baru, createdAt: '2026-06-01 00:00:00');
    dashboardTicket($reporter, TicketService::Sarpras, TicketStatus::Baru, createdAt: '2026-11-17 03:00:00');
    dashboardTicket($reporter, TicketService::Sarpras, TicketStatus::Diproses, createdAt: '2026-09-15 03:00:00');
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Ditutup, createdAt: '2026-05-01 03:00:00');
    dashboardDesign($reporter, DesignRequestStatus::Baru);
    dashboardDesign($reporter, DesignRequestStatus::Selesai);
    $it = dashboardAsset('Alat IT', AssetStatus::Damaged);
    $sarpras = dashboardAsset('Alat Sarpras', AssetStatus::Damaged);
    dashboardAsset('Alat Sarpras', AssetStatus::Borrowed);
    dashboardAsset('Alat IT', AssetStatus::InUse);
    dashboardOccurrence($it, $officer, '2026-11-16');
    dashboardOccurrence($sarpras, $officer, '2026-11-22');
    dashboardOccurrence($sarpras, $officer, '2026-11-23');

    Sanctum::actingAs(dashboardActor('super-admin'));
    $admin = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($admin['summary'])->toBe([
        'jumlah_tiket_baru' => 2,
        'jumlah_tiket_diproses' => 1,
        'jumlah_request_desain_baru' => 1,
        'jumlah_aset_rusak' => 2,
        'jumlah_jadwal_maintenance_minggu_ini' => 2,
    ]);
    expect($admin['charts']['tren_tiket_per_bulan'])->toBe([
        '2026-06' => 1, '2026-07' => 0, '2026-08' => 0,
        '2026-09' => 1, '2026-10' => 0, '2026-11' => 1,
    ]);
    expect($admin['charts']['komposisi_status_aset'])->toBe([
        'Digunakan' => 1, 'Tidak Digunakan' => 0, 'Rusak' => 2, 'Dipinjam' => 1,
    ]);

    Sanctum::actingAs(dashboardActor('management'));
    $management = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($management['summary'])->toBe($admin['summary'])
        ->and($management['charts'])->toBe($admin['charts']);
});

test('koordinator hanya melihat Sarpras dan backup berhasil terbaru', function (): void {
    $reporter = dashboardActor('user');
    $officer = dashboardActor('petugas-sarpras');
    dashboardTicket($reporter, TicketService::Sarpras, TicketStatus::Baru, createdAt: '2026-11-02 03:00:00');
    dashboardTicket($reporter, TicketService::Sarpras, TicketStatus::Diproses, createdAt: '2026-09-02 03:00:00');
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Baru, createdAt: '2026-11-02 03:00:00');
    $sarpras = dashboardAsset('Alat Sarpras', AssetStatus::Damaged);
    $it = dashboardAsset('Alat IT', AssetStatus::Damaged);
    dashboardOccurrence($sarpras, $officer, '2026-11-19');
    dashboardOccurrence($it, $officer, '2026-11-19');
    BackupHistory::create(['type' => 'manual', 'status' => 'success', 'finished_at' => '2026-11-15 02:00:00']);
    BackupHistory::create(['type' => 'manual', 'status' => 'success', 'finished_at' => '2026-11-18 02:00:00']);
    BackupHistory::create(['type' => 'manual', 'status' => 'failed', 'finished_at' => '2026-11-19 02:00:00']);

    Sanctum::actingAs(dashboardActor('koordinator-sarpras'));
    $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($data['summary'])->toBe([
        'jumlah_tiket_sarpras_baru' => 1,
        'jumlah_tiket_sarpras_diproses' => 1,
        'jumlah_aset_sarpras_rusak' => 1,
        'jumlah_jadwal_maintenance_minggu_ini' => 1,
        'waktu_backup_terakhir' => '2026-11-18T09:00:00+07:00',
    ]);
    expect($data['charts']['tren_tiket_per_bulan'])->toBe([
        '2026-06' => 0, '2026-07' => 0, '2026-08' => 0,
        '2026-09' => 1, '2026-10' => 0, '2026-11' => 1,
    ]);
    expect($data['charts']['komposisi_status_aset'])->toBe([
        'Digunakan' => 0, 'Tidak Digunakan' => 0, 'Rusak' => 1, 'Dipinjam' => 0,
    ]);
});

test('petugas hanya melihat tiket desain dan jadwal aktif miliknya sesuai layanan', function (): void {
    $reporter = dashboardActor('user');
    $tik = dashboardActor('petugas-tik');
    $otherTik = dashboardActor('petugas-tik');
    $sarpras = dashboardActor('petugas-sarpras');
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Diproses, $tik);
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Eskalasi, $tik);
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Terselesaikan, $tik);
    dashboardTicket($reporter, TicketService::Tik, TicketStatus::Diproses, $otherTik);
    dashboardTicket($reporter, TicketService::Sarpras, TicketStatus::Diproses, $tik);
    dashboardTicket($reporter, TicketService::Sarpras, TicketStatus::Diproses, $sarpras);
    dashboardDesign($reporter, DesignRequestStatus::Ditugaskan, $tik);
    dashboardDesign($reporter, DesignRequestStatus::PerluRevisi, $tik);
    dashboardDesign($reporter, DesignRequestStatus::Selesai, $tik);
    dashboardDesign($reporter, DesignRequestStatus::Ditugaskan, $otherTik);
    $it = dashboardAsset('Alat IT', AssetStatus::InUse);
    $asset = dashboardAsset('Alat Sarpras', AssetStatus::InUse);
    dashboardOccurrence($it, $tik, '2026-11-18');
    dashboardOccurrence($it, $tik, '2026-12-01');
    dashboardOccurrence($it, $tik, '2026-11-20', MaintenanceStatus::Selesai);
    dashboardOccurrence($it, $otherTik, '2026-11-20');
    dashboardOccurrence($asset, $tik, '2026-11-20');
    dashboardOccurrence($asset, $sarpras, '2026-11-20');
    dashboardOccurrence($asset, $sarpras, '2026-11-17');

    Sanctum::actingAs($tik);
    expect($this->getJson('/api/v1/dashboard')->assertOk()->json('data.summary'))->toBe([
        'jumlah_tiket_ditugaskan' => 2,
        'jumlah_tiket_diproses' => 1,
        'jumlah_tugas_desain_aktif' => 2,
        'jumlah_jadwal_maintenance_mendatang' => 2,
    ]);
    Sanctum::actingAs($sarpras);
    expect($this->getJson('/api/v1/dashboard')->assertOk()->json('data.summary'))->toBe([
        'jumlah_tiket_ditugaskan' => 1,
        'jumlah_tiket_diproses' => 1,
        'jumlah_jadwal_maintenance_mendatang' => 1,
    ]);
});

test('user hanya memperoleh aktivitas sendiri tanpa grafik jadwal atau notifikasi', function (): void {
    $user = dashboardActor('user');
    $other = dashboardActor('user');
    foreach ([TicketStatus::Baru, TicketStatus::Diklasifikasi, TicketStatus::Diproses, TicketStatus::Eskalasi] as $status) {
        dashboardTicket($user, TicketService::Tik, $status);
    }
    dashboardTicket($user, TicketService::Tik, TicketStatus::Terselesaikan);
    dashboardTicket($other, TicketService::Tik, TicketStatus::Baru);
    dashboardTicket($other, TicketService::Tik, TicketStatus::Terselesaikan);
    dashboardDesign($user, DesignRequestStatus::MenungguPersetujuan);
    dashboardDesign($other, DesignRequestStatus::MenungguPersetujuan);

    Sanctum::actingAs($user);
    $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
    expect($data['summary'])->toBe([
        'jumlah_tiket_aktif' => 4,
        'jumlah_tiket_menunggu_verifikasi' => 1,
        'jumlah_request_menunggu_persetujuan' => 1,
    ]);
    expect(array_keys($data))->toBe(['variant', 'summary']);
});
