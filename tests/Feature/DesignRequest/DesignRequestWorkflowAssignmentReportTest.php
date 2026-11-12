<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
});

function createAssignmentReportTestDesignRequest(array $attributes = []): DesignRequest
{
    $requesterId = $attributes['requester_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $officerId = $attributes['assigned_officer_id'] ?? null;
    $unitId = $attributes['unit_id'] ?? User::find($requesterId)?->unit_id ?? Unit::factory()->create()->id;

    return DesignRequest::create(array_merge([
        'request_number' => 'DGR-'.uniqid(),
        'title' => 'Design Banner IGD',
        'design_type' => 'Roll Banner',
        'description' => 'Banner informasi alur IGD',
        'needed_at' => now()->addDays(2),
        'requester_id' => $requesterId,
        'assigned_officer_id' => $officerId,
        'unit_id' => $unitId,
        'status' => DesignRequestStatus::Baru,
    ], $attributes));
}

// -------------------------------------------------------------
// Tests for POST /api/v1/design-requests/{id}/assignments
// -------------------------------------------------------------

test('super admin dapat menugaskan request desain berstatus Baru ke Petugas TIK', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $designRequest = createAssignmentReportTestDesignRequest(['status' => DesignRequestStatus::Baru]);

    Sanctum::actingAs($superAdmin);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/assignments", [
        'prioritas' => 'Tinggi',
        'petugas_desain_id' => $officer->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Tugas desain berhasil ditugaskan ke petugas TIK.')
        ->assertJsonPath('data.id', $designRequest->id)
        ->assertJsonPath('data.status', 'ditugaskan')
        ->assertJsonPath('data.priority', 'Tinggi')
        ->assertJsonPath('data.assigned_officer.id', $officer->id);

    $this->assertDatabaseHas('design_requests', [
        'id' => $designRequest->id,
        'status' => 'ditugaskan',
        'priority' => 'Tinggi',
        'assigned_officer_id' => $officer->id,
    ]);
});

test('super admin dapat menugaskan request menggunakan alias priority dan assigned_officer_id', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $designRequest = createAssignmentReportTestDesignRequest(['status' => DesignRequestStatus::Baru]);

    Sanctum::actingAs($superAdmin);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/assignments", [
        'priority' => 'sedang',
        'assigned_officer_id' => $officer->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'ditugaskan')
        ->assertJsonPath('data.priority', 'Sedang')
        ->assertJsonPath('data.assigned_officer.id', $officer->id);
});

test('non super admin dilarang menugaskan request desain (403 Forbidden)', function (): void {
    $user = User::factory()->create(['unit_id' => Unit::factory()]);
    $user->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));

    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $designRequest = createAssignmentReportTestDesignRequest();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/assignments", [
        'prioritas' => 'Sedang',
        'petugas_desain_id' => $officer->id,
    ]);

    $response->assertForbidden();
});

test('penugasan gagal jika status request bukan Baru (422)', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $designRequest = createAssignmentReportTestDesignRequest([
        'status' => DesignRequestStatus::Ditugaskan,
        'assigned_officer_id' => $officer->id,
    ]);

    Sanctum::actingAs($superAdmin);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/assignments", [
        'prioritas' => 'Tinggi',
        'petugas_desain_id' => $officer->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('penugasan gagal jika petugas yang dipilih bukan role petugas-tik (422)', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $regularUser = User::factory()->create(['unit_id' => Unit::factory()]);
    $regularUser->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));

    $designRequest = createAssignmentReportTestDesignRequest(['status' => DesignRequestStatus::Baru]);

    Sanctum::actingAs($superAdmin);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/assignments", [
        'prioritas' => 'Tinggi',
        'petugas_desain_id' => $regularUser->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['petugas_desain_id']);
});

test('penugasan gagal jika prioritas tidak valid (422)', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $designRequest = createAssignmentReportTestDesignRequest(['status' => DesignRequestStatus::Baru]);

    Sanctum::actingAs($superAdmin);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/assignments", [
        'prioritas' => 'MendesakSekali',
        'petugas_desain_id' => $officer->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['prioritas']);
});

// -------------------------------------------------------------
// Tests for GET /api/v1/design-tasks
// -------------------------------------------------------------

test('petugas-tik dapat melihat daftar tugas desain miliknya secara paginasi', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $otherOfficer = User::factory()->create(['unit_id' => Unit::factory()]);
    $otherOfficer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    // 12 tugas untuk $officer
    for ($i = 0; $i < 12; $i++) {
        createAssignmentReportTestDesignRequest([
            'assigned_officer_id' => $officer->id,
            'status' => DesignRequestStatus::Ditugaskan,
            'title' => "Tugas Saya {$i}",
        ]);
    }

    // 3 tugas untuk $otherOfficer
    for ($j = 0; $j < 3; $j++) {
        createAssignmentReportTestDesignRequest([
            'assigned_officer_id' => $otherOfficer->id,
            'status' => DesignRequestStatus::Ditugaskan,
            'title' => "Tugas Petugas Lain {$j}",
        ]);
    }

    Sanctum::actingAs($officer);

    $response = $this->getJson('/api/v1/design-tasks?limit=5');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 12);
});

test('non petugas-tik dilarang mengakses GET /api/v1/design-tasks (403)', function (): void {
    $regularUser = User::factory()->create(['unit_id' => Unit::factory()]);
    $regularUser->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));

    Sanctum::actingAs($regularUser);

    $response = $this->getJson('/api/v1/design-tasks');

    $response->assertForbidden();
});

// -------------------------------------------------------------
// Tests for GET /api/v1/design-requests/reports
// -------------------------------------------------------------

test('manajemen dapat melihat rekapitulasi laporan request desain berdasarkan tanggal', function (): void {
    $management = User::factory()->create(['unit_id' => Unit::factory()]);
    $management->assignRole(Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']));

    // Request dalam rentang waktu
    $req1 = createAssignmentReportTestDesignRequest([
        'title' => 'Banner Dies Natalis',
        'design_type' => 'Banner',
        'status' => DesignRequestStatus::Baru,
    ]);
    $req1->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

    $req2 = createAssignmentReportTestDesignRequest([
        'title' => 'Poster Vaksinasi',
        'design_type' => 'Poster',
        'status' => DesignRequestStatus::Selesai,
    ]);
    $req2->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

    // Request di luar rentang waktu (10 hari lalu)
    $req3 = createAssignmentReportTestDesignRequest([
        'title' => 'Brosur Lama',
        'design_type' => 'Brosur',
        'status' => DesignRequestStatus::Selesai,
    ]);
    $req3->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    Sanctum::actingAs($management);

    $startDate = now()->subDays(6)->toDateString();
    $endDate = now()->toDateString();

    $response = $this->getJson("/api/v1/design-requests/reports?start_date={$startDate}&end_date={$endDate}");

    $response->assertOk()
        ->assertJsonPath('message', 'Laporan rekapitulasi request desain berhasil diambil.')
        ->assertJsonStructure([
            'data' => [
                'period' => ['start_date', 'end_date'],
                'total',
                'by_status',
                'by_design_type',
                'requests',
            ],
        ])
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.period.start_date', $startDate)
        ->assertJsonPath('data.period.end_date', $endDate)
        ->assertJsonPath('data.by_status.baru', 1)
        ->assertJsonPath('data.by_status.selesai', 1)
        ->assertJsonPath('data.by_design_type.Banner', 1)
        ->assertJsonPath('data.by_design_type.Poster', 1);
});

test('super admin juga dapat mengakses rekapitulasi laporan', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    Sanctum::actingAs($superAdmin);

    $startDate = now()->subDays(2)->toDateString();
    $endDate = now()->toDateString();

    $response = $this->getJson("/api/v1/design-requests/reports?start_date={$startDate}&end_date={$endDate}");

    $response->assertOk();
});

test('laporan gagal validasi jika tanggal tidak valid atau end_date sebelum start_date (422)', function (): void {
    $management = User::factory()->create(['unit_id' => Unit::factory()]);
    $management->assignRole(Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']));

    Sanctum::actingAs($management);

    // Missing parameters
    $this->getJson('/api/v1/design-requests/reports')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);

    // end_date before start_date
    $this->getJson('/api/v1/design-requests/reports?start_date=2026-02-10&end_date=2026-02-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

test('non manajemen dan non super admin dilarang mengakses laporan (403)', function (): void {
    $regularUser = User::factory()->create(['unit_id' => Unit::factory()]);
    $regularUser->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));

    Sanctum::actingAs($regularUser);

    $response = $this->getJson('/api/v1/design-requests/reports?start_date=2026-01-01&end_date=2026-01-31');

    $response->assertForbidden();
});
