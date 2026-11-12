<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignDraft;
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

function createReadTestDesignRequest(array $attributes = []): DesignRequest
{
    $requesterId = $attributes['requester_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $officerId = $attributes['assigned_officer_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
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
        'status' => DesignRequestStatus::Ditugaskan,
    ], $attributes));
}

test('index mengembalikan daftar tiket dengan paginasi default 10', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Sanctum::actingAs($superAdmin);

    for ($i = 0; $i < 15; $i++) {
        createReadTestDesignRequest();
    }

    $response = $this->getJson('/api/v1/design-requests');

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
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 15);
});

test('index menerima parameter limit untuk mengatur jumlah item per halaman', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Sanctum::actingAs($superAdmin);

    for ($i = 0; $i < 5; $i++) {
        createReadTestDesignRequest();
    }

    $response = $this->getJson('/api/v1/design-requests?limit=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);
});

test('index untuk super-admin menampilkan seluruh request', function (): void {
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Sanctum::actingAs($superAdmin);

    createReadTestDesignRequest(['title' => 'Req 1']);
    createReadTestDesignRequest(['title' => 'Req 2']);

    $response = $this->getJson('/api/v1/design-requests');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('index untuk pemohon hanya menampilkan request miliknya', function (): void {
    $requester = User::factory()->create(['unit_id' => Unit::factory()]);
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);

    $myRequest = createReadTestDesignRequest(['requester_id' => $requester->id, 'title' => 'Request Saya']);
    createReadTestDesignRequest(['requester_id' => $otherUser->id, 'title' => 'Request Orang Lain']);

    Sanctum::actingAs($requester);

    $response = $this->getJson('/api/v1/design-requests');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $myRequest->id)
        ->assertJsonPath('data.0.title', 'Request Saya');
});

test('index untuk petugas-tik hanya menampilkan request yang ditugaskan kepadanya', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $otherOfficer = User::factory()->create(['unit_id' => Unit::factory()]);
    $otherOfficer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $assignedRequest = createReadTestDesignRequest([
        'assigned_officer_id' => $officer->id,
        'title' => 'Request Ditugaskan ke Saya',
    ]);
    createReadTestDesignRequest([
        'assigned_officer_id' => $otherOfficer->id,
        'title' => 'Request Ditugaskan ke Petugas Lain',
    ]);

    Sanctum::actingAs($officer);

    $response = $this->getJson('/api/v1/design-requests');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assignedRequest->id)
        ->assertJsonPath('data.0.title', 'Request Ditugaskan ke Saya');
});

test('show menampilkan detail request desain beserta riwayat draft yang di-eager-load', function (): void {
    $designRequest = createReadTestDesignRequest();
    $requester = User::find($designRequest->requester_id);

    DesignDraft::create([
        'design_request_id' => $designRequest->id,
        'file_object_key' => 'design_drafts/1/draft_test.png',
        'file_original_name' => 'draft_test.png',
        'file_mime_type' => 'image/png',
        'file_size' => 1024,
        'notes' => 'Catatan draft awal',
        'uploaded_by_id' => $designRequest->assigned_officer_id,
    ]);

    Sanctum::actingAs($requester);

    $response = $this->getJson("/api/v1/design-requests/{$designRequest->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $designRequest->id)
        ->assertJsonPath('data.title', $designRequest->title)
        ->assertJsonStructure([
            'data' => [
                'id',
                'request_number',
                'title',
                'design_type',
                'description',
                'needed_at',
                'status',
                'requester',
                'unit',
                'drafts' => [
                    '*' => [
                        'id',
                        'design_request_id',
                        'file_path',
                        'file_name',
                        'file_size',
                        'mime_type',
                        'notes',
                        'uploaded_by_id',
                    ],
                ],
            ],
        ])
        ->assertJsonCount(1, 'data.drafts')
        ->assertJsonPath('data.drafts.0.file_name', 'draft_test.png');
});

test('show mengizinkan super-admin dan petugas yang ditugaskan', function (): void {
    $designRequest = createReadTestDesignRequest();

    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $officer = User::find($designRequest->assigned_officer_id);

    Sanctum::actingAs($superAdmin);
    $this->getJson("/api/v1/design-requests/{$designRequest->id}")->assertOk();

    Sanctum::actingAs($officer);
    $this->getJson("/api/v1/design-requests/{$designRequest->id}")->assertOk();
});

test('show menolak user lain yang tidak berhak dengan status 403', function (): void {
    $designRequest = createReadTestDesignRequest();
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);

    Sanctum::actingAs($otherUser);

    $this->getJson("/api/v1/design-requests/{$designRequest->id}")->assertForbidden();
});

test('show mengembalikan 404 jika id request tidak ditemukan', function (): void {
    $user = User::factory()->create(['unit_id' => Unit::factory()]);
    $user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/design-requests/999999')->assertNotFound();
});
