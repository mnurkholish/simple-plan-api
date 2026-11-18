<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\ItTag;
use App\Models\QualityCategory;
use App\Models\SarprasCategory;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CorePermissionSeeder::class, DomainPermissionSeeder::class, RoleSeeder::class]);
    $this->reporter = User::factory()->for(Unit::factory())->create();
});

function reportUrl(array $filters = []): string
{
    return '/api/v1/reports/helpdesk?'.http_build_query(array_merge([
        'periode_awal' => '2026-01-01',
        'periode_akhir' => '2026-03-31',
    ], $filters));
}

function actAsHelpdeskReportRole(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::findByName($role, 'web'));
    Sanctum::actingAs($user);

    return $user;
}

/** @param array<string, mixed> $attributes */
function createHelpdeskReportTicket(User $reporter, TicketService $service, TicketStatus $status, string $createdAt, array $attributes = []): Ticket
{
    $ticket = Ticket::factory()->reportedBy($reporter)->create([
        'service' => $service,
        'status' => $status,
        ...$attributes,
    ]);
    $ticket->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

    return $ticket;
}

test('laporan menghitung rekap, waktu penyelesaian, SLA, dan tiket per bulan sesuai periode', function (): void {
    actAsHelpdeskReportRole('super-admin');
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Terselesaikan, '2026-01-15 08:00:00', [
        'completed_at' => '2026-01-15 10:00:00',
        'sla_deadline' => '2026-01-15 10:00:00',
    ]);
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Ditutup, '2026-01-31 23:59:59', [
        'completed_at' => '2026-02-01 03:59:59',
        'sla_deadline' => '2026-02-01 03:00:00',
    ]);
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Terselesaikan, '2026-03-10 09:00:00', [
        'sla_deadline' => '2026-03-10 10:00:00',
    ]);
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Terselesaikan, '2026-03-11 09:00:00', [
        'completed_at' => '2026-03-11 15:00:00',
    ]);
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Ditolak, '2026-03-12 09:00:00');
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-04-01 00:00:00');

    $this->getJson(reportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_tiket_masuk' => 5,
            'jumlah_tiket_selesai' => 4,
            'jumlah_tiket_ditolak' => 1,
            'rata_rata_waktu_penyelesaian' => 4,
            'persentase_sla_tercapai' => 50,
        ])
        ->assertJsonPath('data.charts.tiket_per_bulan', [
            ['bulan' => '2026-01', 'jumlah' => 2],
            ['bulan' => '2026-02', 'jumlah' => 0],
            ['bulan' => '2026-03', 'jumlah' => 3],
        ])
        ->assertJsonPath('data.charts.status_tiket.terselesaikan', 3)
        ->assertJsonPath('data.charts.status_tiket.ditutup', 1)
        ->assertJsonPath('data.charts.status_tiket.ditolak', 1)
        ->assertJsonPath('data.charts.sla', ['tercapai' => 1, 'terlambat' => 1]);
});

test('laporan menghitung IT Tag dan kategori Sarpras tanpa menghitung tiket ganda', function (): void {
    actAsHelpdeskReportRole('management');
    $quality = QualityCategory::create(['name' => 'Kualitas aplikasi']);
    $server = ItTag::create(['name' => 'Server']);
    $electronic = SarprasCategory::create(['name' => 'Elektronik']);

    foreach (range(1, 2) as $_) {
        createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-02-10 10:00:00')
            ->tikDetail()->create(['quality_category_id' => $quality->id, 'it_tag_id' => $server->id]);
    }
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-02-10 10:00:00');
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-02-10 10:00:00')
        ->sarprasDetail()->create(['sarpras_category_id' => $electronic->id]);
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-02-10 10:00:00');

    $response = $this->getJson(reportUrl())->assertOk()
        ->assertJsonPath('data.summary.jumlah_tiket_masuk', 5)
        ->assertJsonPath('data.charts.tiket_per_kategori.tik.0', ['kategori' => 'Server', 'jumlah' => 2]);

    $tik = collect($response->json('data.charts.tiket_per_kategori.tik'))->pluck('jumlah', 'kategori');
    $sarpras = collect($response->json('data.charts.tiket_per_kategori.sarpras'))->pluck('jumlah', 'kategori');
    expect($tik->all())->toBe(['Server' => 2, 'Belum diklasifikasi' => 1]);
    expect($sarpras->all())->toBe(['Elektronik' => 1, 'Belum diklasifikasi' => 1]);
    expect($tik->sum() + $sarpras->sum())->toBe(5);
});

test('filter periode dan layanan membatasi tiket serta menolak input yang tidak valid', function (): void {
    actAsHelpdeskReportRole('super-admin');
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-01-09 23:59:59');
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-01-10 00:00:00');
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-01-12 23:59:59');
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-01-13 00:00:00');

    $filters = ['periode_awal' => '2026-01-10', 'periode_akhir' => '2026-01-12'];
    $this->getJson(reportUrl($filters))->assertOk()->assertJsonPath('data.summary.jumlah_tiket_masuk', 2);
    $this->getJson(reportUrl($filters + ['jenis_layanan' => 'tik']))
        ->assertOk()->assertJsonPath('data.summary.jumlah_tiket_masuk', 1)
        ->assertJsonPath('data.charts.tiket_per_kategori.sarpras', []);
    $this->getJson(reportUrl($filters + ['jenis_layanan' => 'sarpras']))
        ->assertOk()->assertJsonPath('data.summary.jumlah_tiket_masuk', 1)
        ->assertJsonPath('data.charts.tiket_per_kategori.tik', []);

    foreach (['', '?periode_awal=2026-01-10', '?periode_awal=2026-02-01&periode_akhir=2026-01-31', '?periode_awal=2026-01-01&periode_akhir=2026-03-31&jenis_layanan=lain'] as $query) {
        $this->getJson('/api/v1/reports/helpdesk'.$query)->assertUnprocessable();
    }
});

test('super admin dan manajemen dapat melihat laporan semua layanan', function (): void {
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-02-10 10:00:00');
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-02-10 10:00:00');

    foreach (['super-admin', 'management'] as $role) {
        actAsHelpdeskReportRole($role);
        $this->getJson(reportUrl())->assertOk()->assertJsonPath('data.summary.jumlah_tiket_masuk', 2);
    }
});

test('koordinator Sarpras hanya dapat melihat laporan tiket Sarpras', function (): void {
    createHelpdeskReportTicket($this->reporter, TicketService::Tik, TicketStatus::Baru, '2026-02-10 10:00:00');
    createHelpdeskReportTicket($this->reporter, TicketService::Sarpras, TicketStatus::Baru, '2026-02-10 10:00:00');

    actAsHelpdeskReportRole('koordinator-sarpras');
    $this->getJson(reportUrl())->assertOk()->assertJsonPath('data.summary.jumlah_tiket_masuk', 1)
        ->assertJsonPath('data.charts.tiket_per_kategori.tik', []);
    $this->getJson(reportUrl(['jenis_layanan' => 'tik']))
        ->assertOk()->assertJsonPath('data.summary.jumlah_tiket_masuk', 0);
});

test('guest dan role tanpa izin tidak dapat melihat laporan', function (): void {
    $this->getJson(reportUrl())->assertUnauthorized();

    foreach (['user', 'petugas-tik', 'petugas-sarpras'] as $role) {
        $user = actAsHelpdeskReportRole($role);
        $this->getJson(reportUrl())->assertForbidden();
        if ($role === 'user') {
            $user->givePermissionTo('helpdesk-reports-read');
            $this->getJson(reportUrl())->assertForbidden();
        }
    }
});

test('laporan tanpa tiket menampilkan rekap nol dan grafik kosong', function (): void {
    actAsHelpdeskReportRole('management');

    $this->getJson(reportUrl(['periode_awal' => '2026-01-01', 'periode_akhir' => '2026-02-28']))
        ->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_tiket_masuk' => 0,
            'jumlah_tiket_selesai' => 0,
            'jumlah_tiket_ditolak' => 0,
            'rata_rata_waktu_penyelesaian' => 0,
            'persentase_sla_tercapai' => 0,
        ])
        ->assertJsonPath('data.charts.tiket_per_bulan', [
            ['bulan' => '2026-01', 'jumlah' => 0],
            ['bulan' => '2026-02', 'jumlah' => 0],
        ])
        ->assertJsonPath('data.charts.tiket_per_kategori', ['sarpras' => [], 'tik' => []])
        ->assertJsonPath('data.charts.sla', ['tercapai' => 0, 'terlambat' => 0]);
});
