<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'design-reports-read', 'guard_name' => 'web']);
    $this->requester = User::factory()->for(Unit::factory())->create();
});

function actingAsDesignReportReader(bool $withPermission = true): void
{
    $user = User::factory()->for(Unit::factory())->create();

    if ($withPermission) {
        $user->givePermissionTo('design-reports-read');
    }

    Sanctum::actingAs($user);
}

/** @param array<string, mixed> $attributes */
function createDesignReportRequest(User $requester, int $sequence, string $createdAt, array $attributes = []): DesignRequest
{
    $designRequest = DesignRequest::create([
        'request_number' => sprintf('DGR-2026-%06d', $sequence),
        'title' => "Request desain {$sequence}",
        'design_type' => 'Poster khusus',
        'description' => 'Desain untuk unit.',
        'needed_at' => '2026-02-01',
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru,
        ...$attributes,
    ]);

    $designRequest->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

    return $designRequest;
}

test('pengguna dengan permission laporan dapat melihat rekap dan akses tanpa izin ditolak', function (?bool $withPermission, int $status): void {
    if ($withPermission !== null) {
        actingAsDesignReportReader($withPermission);
    }

    $this->getJson('/api/v1/design-reports')->assertStatus($status);
})->with([
    'belum login' => [null, 401],
    'tanpa permission' => [false, 403],
    'dengan permission' => [true, 200],
]);

test('jumlah request per status dan jenis desain manual sesuai database', function (): void {
    actingAsDesignReportReader();
    createDesignReportRequest($this->requester, 1, '2026-01-10 09:00:00', ['design_type' => 'Infografis khusus']);
    createDesignReportRequest($this->requester, 2, '2026-01-11 09:00:00', ['design_type' => 'Poster khusus']);
    createDesignReportRequest($this->requester, 3, '2026-01-12 09:00:00', ['design_type' => 'Poster khusus', 'status' => DesignRequestStatus::Ditugaskan]);
    createDesignReportRequest($this->requester, 4, '2026-01-13 09:00:00', ['design_type' => 'Infografis khusus', 'status' => DesignRequestStatus::Selesai, 'completed_at' => '2026-01-13 10:00:00']);

    $response = $this->getJson('/api/v1/design-reports')->assertOk()
        ->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.by_status', [
            'baru' => 2,
            'ditugaskan' => 1,
            'menunggu_persetujuan' => 0,
            'perlu_revisi' => 0,
            'selesai' => 1,
            'ditolak' => 0,
        ])
        ->assertJsonCount(4, 'data.requests.data');

    expect($response->json('data.by_design_type'))->toBe([
        ['design_type' => 'Infografis khusus', 'total' => 2],
        ['design_type' => 'Poster khusus', 'total' => 2],
    ]);
    $response->assertJsonPath('data.requests.data.0.unit.name', $this->requester->unit->unit_name)
        ->assertJsonPath('data.requests.data.0.status', 'selesai')
        ->assertJsonPath('data.requests.data.0.completed_at', '2026-01-13T10:00:00.000000Z');
    expect($response->json('data.requests.data.0'))->toHaveKeys(['request_number', 'title', 'created_at']);
});

test('filter periode menghitung request pada batas tanggal awal dan akhir', function (): void {
    actingAsDesignReportReader();
    createDesignReportRequest($this->requester, 11, '2026-01-09 23:59:59');
    $first = createDesignReportRequest($this->requester, 12, '2026-01-10 00:00:00', ['design_type' => 'Poster khusus']);
    $last = createDesignReportRequest($this->requester, 13, '2026-01-12 23:59:59', ['design_type' => 'Banner manual', 'status' => DesignRequestStatus::Ditolak]);
    createDesignReportRequest($this->requester, 14, '2026-01-13 00:00:00');

    $response = $this->getJson('/api/v1/design-reports?start_date=2026-01-10&end_date=2026-01-12')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.by_status.baru', 1)
        ->assertJsonPath('data.by_status.ditolak', 1)
        ->assertJsonPath('data.requests.meta.total', 2);

    expect($response->json('data.requests.data.*.id'))->toEqualCanonicalizing([$first->id, $last->id]);
    expect($response->json('data.by_design_type'))->toEqualCanonicalizing([
        ['design_type' => 'Banner manual', 'total' => 1],
        ['design_type' => 'Poster khusus', 'total' => 1],
    ]);
});

test('pencarian nomor atau judul request hanya menyaring daftar tanpa mengubah rekap', function (string $search): void {
    actingAsDesignReportReader();
    $matching = createDesignReportRequest($this->requester, 301, '2026-01-10 09:00:00', ['title' => 'Poster pilihan']);
    createDesignReportRequest($this->requester, 302, '2026-01-11 09:00:00', ['title' => 'Banner umum', 'design_type' => 'Banner manual']);

    $response = $this->getJson('/api/v1/design-reports?search='.urlencode($search))
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.by_status.baru', 2)
        ->assertJsonCount(2, 'data.by_design_type')
        ->assertJsonPath('data.requests.meta.total', 1);

    expect($response->json('data.requests.data.*.id'))->toBe([$matching->id]);
})->with([
    'nomor request' => '000301',
    'judul request' => 'Poster pilihan',
]);

test('pagination daftar request tidak mengubah jumlah rekap', function (): void {
    actingAsDesignReportReader();
    $requests = [];

    for ($i = 1; $i <= 5; $i++) {
        $requests[] = createDesignReportRequest($this->requester, 400 + $i, sprintf('2026-01-%02d 09:00:00', $i));
    }

    $response = $this->getJson('/api/v1/design-reports?page=2&per_page=2')
        ->assertOk()
        ->assertJsonPath('data.total', 5)
        ->assertJsonPath('data.by_status.baru', 5)
        ->assertJsonPath('data.by_design_type.0.total', 5)
        ->assertJsonCount(2, 'data.requests.data')
        ->assertJsonPath('data.requests.meta.current_page', 2)
        ->assertJsonPath('data.requests.meta.per_page', 2)
        ->assertJsonPath('data.requests.meta.total', 5);

    expect($response->json('data.requests.data.*.id'))->toBe([$requests[2]->id, $requests[1]->id]);
});
