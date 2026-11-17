<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DomainPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([DomainPermissionSeeder::class, RoleSeeder::class]);
    $this->requester = User::factory()->for(Unit::factory())->create();
});

function designGraphicReportUrl(array $filters = []): string
{
    return '/api/v1/reports/design-graphic?'.http_build_query(array_merge([
        'periode_awal' => '2026-01-01',
        'periode_akhir' => '2026-03-31',
    ], $filters));
}

function actAsDesignGraphicReportRole(string $role): User
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::findByName($role, 'web'));
    Sanctum::actingAs($user);

    return $user;
}

/** @param array<string, mixed> $attributes */
function createGraphicReportRequest(User $requester, int $sequence, string $createdAt, array $attributes = []): DesignRequest
{
    $request = DesignRequest::create([
        'request_number' => sprintf('DGR-2026-%06d', $sequence),
        'title' => "Request desain {$sequence}",
        'design_type' => 'Poster Edukasi',
        'description' => 'Desain untuk unit.',
        'needed_at' => '2026-04-01',
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru,
        ...$attributes,
    ]);
    $request->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

    return $request;
}

test('laporan menghitung rekap, waktu penyelesaian, dan request per bulan sesuai periode', function (): void {
    actAsDesignGraphicReportRole('super-admin');
    $first = createGraphicReportRequest($this->requester, 1, '2026-01-15 08:00:00', [
        'status' => DesignRequestStatus::Selesai,
        'completed_at' => '2026-01-16 20:00:00',
    ]);
    $first->forceFill(['updated_at' => '2026-03-01 00:00:00'])->save();
    createGraphicReportRequest($this->requester, 2, '2026-01-31 23:59:59', [
        'status' => DesignRequestStatus::Selesai,
    ]);
    createGraphicReportRequest($this->requester, 3, '2026-03-10 09:00:00', [
        'status' => DesignRequestStatus::Selesai,
        'completed_at' => '2026-03-10 15:00:00',
    ]);
    createGraphicReportRequest($this->requester, 4, '2026-03-11 09:00:00', [
        'status' => DesignRequestStatus::Ditolak,
    ]);
    createGraphicReportRequest($this->requester, 5, '2026-04-01 00:00:00');

    $this->getJson(designGraphicReportUrl())->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_request_masuk' => 4,
            'jumlah_request_selesai' => 3,
            'jumlah_request_ditolak' => 1,
            'rata_rata_waktu_penyelesaian' => 0.88,
        ])
        ->assertJsonPath('data.charts.request_per_bulan', [
            ['bulan' => '2026-01', 'jumlah' => 2],
            ['bulan' => '2026-02', 'jumlah' => 0],
            ['bulan' => '2026-03', 'jumlah' => 2],
        ])
        ->assertJsonPath('data.charts.status_request', [
            'baru' => 0,
            'ditugaskan' => 0,
            'menunggu_persetujuan' => 0,
            'perlu_revisi' => 0,
            'selesai' => 3,
            'ditolak' => 1,
        ]);
});

test('laporan mengelompokkan jenis desain tanpa membedakan kapital dan spasi', function (): void {
    actAsDesignGraphicReportRole('management');
    $first = createGraphicReportRequest($this->requester, 11, '2026-02-01 09:00:00', ['design_type' => 'Poster  Edukasi']);
    createGraphicReportRequest($this->requester, 12, '2026-02-02 09:00:00', ['design_type' => 'poster edukasi']);
    createGraphicReportRequest($this->requester, 13, '2026-02-03 09:00:00', ['design_type' => 'POSTER   EDUKASI']);
    createGraphicReportRequest($this->requester, 14, '2026-02-04 09:00:00', ['design_type' => 'Banner']);

    $this->getJson(designGraphicReportUrl())->assertOk()
        ->assertJsonPath('data.summary.jumlah_request_masuk', 4)
        ->assertJsonPath('data.charts.request_per_jenis_desain', [
            ['jenis_desain' => 'Poster Edukasi', 'jumlah' => 3],
            ['jenis_desain' => 'Banner', 'jumlah' => 1],
        ]);
    expect($first->fresh()->design_type)->toBe('Poster  Edukasi');
});

test('filter periode mencakup tanggal akhir dan menolak periode tidak valid', function (): void {
    actAsDesignGraphicReportRole('super-admin');
    createGraphicReportRequest($this->requester, 21, '2026-01-09 23:59:59');
    createGraphicReportRequest($this->requester, 22, '2026-01-10 00:00:00');
    createGraphicReportRequest($this->requester, 23, '2026-01-12 23:59:59');
    createGraphicReportRequest($this->requester, 24, '2026-01-13 00:00:00');

    $this->getJson(designGraphicReportUrl(['periode_awal' => '2026-01-10', 'periode_akhir' => '2026-01-12']))
        ->assertOk()->assertJsonPath('data.summary.jumlah_request_masuk', 2);

    foreach (['', '?periode_awal=2026-01-10', '?periode_awal=2026-02-01&periode_akhir=2026-01-31', '?periode_awal=tidak-valid&periode_akhir=2026-01-31'] as $query) {
        $this->getJson('/api/v1/reports/design-graphic'.$query)->assertUnprocessable();
    }
});

test('super admin dan manajemen dapat melihat seluruh request desain', function (): void {
    createGraphicReportRequest($this->requester, 31, '2026-02-01 09:00:00');
    createGraphicReportRequest($this->requester, 32, '2026-02-02 09:00:00');

    foreach (['super-admin', 'management'] as $role) {
        actAsDesignGraphicReportRole($role);
        $this->getJson(designGraphicReportUrl())->assertOk()
            ->assertJsonPath('data.summary.jumlah_request_masuk', 2);
    }
});

test('guest dan role lain tidak dapat melihat laporan desain grafis', function (): void {
    $this->getJson(designGraphicReportUrl())->assertUnauthorized();

    foreach (['koordinator-sarpras', 'petugas-tik', 'user'] as $role) {
        $user = actAsDesignGraphicReportRole($role);
        $this->getJson(designGraphicReportUrl())->assertForbidden();
        if ($role === 'user') {
            $user->givePermissionTo('design-reports-read');
            $this->getJson(designGraphicReportUrl())->assertForbidden();
        }
    }
});

test('laporan tanpa request menampilkan rekap nol dan grafik kosong', function (): void {
    actAsDesignGraphicReportRole('management');

    $this->getJson(designGraphicReportUrl(['periode_awal' => '2026-01-01', 'periode_akhir' => '2026-02-28']))
        ->assertOk()
        ->assertJsonPath('data.summary', [
            'jumlah_request_masuk' => 0,
            'jumlah_request_selesai' => 0,
            'jumlah_request_ditolak' => 0,
            'rata_rata_waktu_penyelesaian' => 0,
        ])
        ->assertJsonPath('data.charts.request_per_bulan', [
            ['bulan' => '2026-01', 'jumlah' => 0],
            ['bulan' => '2026-02', 'jumlah' => 0],
        ])
        ->assertJsonPath('data.charts.request_per_jenis_desain', []);
});
