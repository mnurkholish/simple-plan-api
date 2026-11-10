<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'design-requests-read', 'guard_name' => 'web']);
});

function actingAsDesignRequestReader(bool $canRead = true, bool $admin = false): User
{
    $user = User::factory()->for(Unit::factory())->create();

    if ($canRead) {
        $user->givePermissionTo('design-requests-read');
    }

    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    Sanctum::actingAs($user);

    return $user;
}

/** @param array<string, mixed> $attributes */
function createDesignRequestForList(User $requester, int $sequence, array $attributes = []): DesignRequest
{
    return DesignRequest::create([
        'request_number' => sprintf('DGR-%s-%06d', now()->format('Y'), $sequence),
        'title' => "Poster {$sequence}",
        'design_type' => 'Poster edukasi',
        'description' => 'Desain untuk kebutuhan unit.',
        'needed_at' => today()->addDays(7),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru,
        ...$attributes,
    ]);
}

test('pengguna yang diizinkan dapat melihat request desain sesuai scope', function (
    ?string $scope,
    bool $admin,
    array $expected,
): void {
    $actor = actingAsDesignRequestReader(admin: $admin);
    $other = User::factory()->for(Unit::factory())->create();
    $requests = [
        'milik' => createDesignRequestForList($actor, 1),
        'ditugaskan' => createDesignRequestForList($other, 2, ['assigned_officer_id' => $actor->id]),
        'lain' => createDesignRequestForList($other, 3),
    ];
    $url = '/api/v1/design-requests'.($scope ? '?scope='.$scope : '');
    $response = $this->getJson($url)->assertOk();
    $expectedIds = array_map(fn (string $key): int => $requests[$key]->id, $expected);

    expect($response->json('data.*.id'))->toEqualCanonicalizing($expectedIds);
    $response
        ->assertJsonCount(count($expectedIds), 'data')
        ->assertJsonPath('meta.total', count($expectedIds));
    expect($response->json('data.0'))->toHaveKeys([
        'request_number', 'title', 'design_type', 'unit', 'needed_at', 'status', 'requester',
    ]);
})->with([
    'default mine' => [null, false, ['milik']],
    'mine' => ['mine', false, ['milik']],
    'assigned' => ['assigned', false, ['ditugaskan']],
    'all hanya super admin' => ['all', true, ['milik', 'ditugaskan', 'lain']],
]);

test('akses daftar request desain tanpa izin ditolak', function (?bool $canRead, string $scope, int $status): void {
    if ($canRead !== null) {
        actingAsDesignRequestReader(canRead: $canRead);
    }

    $this->getJson('/api/v1/design-requests?scope='.$scope)->assertStatus($status);
})->with([
    'guest' => [null, 'mine', 401],
    'tanpa permission' => [false, 'mine', 403],
    'scope all bukan super admin' => [true, 'all', 403],
]);

test('parameter daftar request desain yang tidak valid ditolak', function (array $query, string $field): void {
    actingAsDesignRequestReader();

    $this->getJson('/api/v1/design-requests?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'scope tidak dikenal' => [['scope' => 'other'], 'scope'],
    'status tidak dikenal' => [['status' => 'invalid'], 'status'],
    'halaman kurang dari satu' => [['page' => 0], 'page'],
    'jumlah per halaman melebihi batas' => [['per_page' => 101], 'per_page'],
]);

test('pencarian nomor atau judul request desain hanya menampilkan milik pengguna', function (string $search): void {
    $actor = actingAsDesignRequestReader();
    $other = User::factory()->for(Unit::factory())->create();
    $matching = createDesignRequestForList($actor, 101, ['title' => 'Poster gizi anak']);
    createDesignRequestForList($actor, 102, ['title' => 'Banner ruang tunggu']);
    createDesignRequestForList($other, 103, ['title' => 'Poster gizi anak milik orang lain']);
    $response = $this->getJson('/api/v1/design-requests?search='.urlencode($search))->assertOk();

    expect($response->json('data.*.id'))->toBe([$matching->id]);
})->with([
    'nomor request' => '000101',
    'judul request' => 'Poster gizi',
]);

test('filter status dan kombinasi parameter request desain menampilkan data yang cocok', function (): void {
    $actor = actingAsDesignRequestReader();
    $other = User::factory()->for(Unit::factory())->create();
    $matching = createDesignRequestForList($other, 201, [
        'title' => 'Poster keselamatan pasien',
        'status' => DesignRequestStatus::Ditugaskan,
        'assigned_officer_id' => $actor->id,
    ]);
    createDesignRequestForList($other, 202, ['title' => 'Poster keselamatan staf', 'assigned_officer_id' => $actor->id]);
    createDesignRequestForList($other, 203, ['title' => 'Banner pasien', 'status' => DesignRequestStatus::Ditugaskan, 'assigned_officer_id' => $actor->id]);
    createDesignRequestForList($other, 204, ['title' => 'Poster keselamatan lain', 'status' => DesignRequestStatus::Ditugaskan]);
    $response = $this->getJson('/api/v1/design-requests?'.http_build_query([
        'scope' => 'assigned',
        'search' => 'Poster keselamatan',
        'status' => DesignRequestStatus::Ditugaskan->value,
        'page' => 1,
        'per_page' => 2,
    ]))->assertOk();

    expect($response->json('data.*.id'))->toBe([$matching->id]);
    $response->assertJsonPath('meta.total', 1);
});

test('pagination daftar request desain mengembalikan halaman dan metadata yang benar', function (): void {
    $actor = actingAsDesignRequestReader();
    $requests = [];

    for ($i = 1; $i <= 5; $i++) {
        $requests[] = createDesignRequestForList($actor, 300 + $i);
    }

    $response = $this->getJson('/api/v1/design-requests?page=2&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.total', 5);

    expect($response->json('data.*.id'))->toBe([$requests[2]->id, $requests[1]->id]);
    expect($response->json('links.next'))->not->toBeNull();
});

test('daftar request desain kosong tetap memiliki pagination yang valid', function (): void {
    actingAsDesignRequestReader();

    $this->getJson('/api/v1/design-requests')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 0)
        ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta']);
});
