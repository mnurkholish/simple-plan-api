<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignDraft;
use App\Models\DesignRequest;
use App\Models\DesignRevision;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Permission::firstOrCreate(['name' => 'design-requests-read', 'guard_name' => 'web']);
});

function createDesignHistoryUser(bool $canRead = true, bool $admin = false): User
{
    $user = User::factory()->for(Unit::factory())->create();

    if ($canRead) {
        $user->givePermissionTo('design-requests-read');
    }

    if ($admin) {
        $user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    }

    return $user;
}

function createDesignRequestForHistory(User $requester, ?User $officer = null): DesignRequest
{
    return DesignRequest::create([
        'request_number' => 'DGR-'.now()->format('Y').'-'.str_pad((string) (DesignRequest::count() + 1), 6, '0', STR_PAD_LEFT),
        'title' => 'Poster edukasi pasien',
        'design_type' => 'Poster edukasi',
        'description' => 'Poster untuk ruang tunggu.',
        'needed_at' => today()->addDays(7),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'assigned_officer_id' => $officer?->id,
        'status' => DesignRequestStatus::Ditugaskan,
    ]);
}

function designHistoryUrl(DesignRequest $designRequest): string
{
    return "/api/v1/design-requests/{$designRequest->id}/history";
}

test('pemohon petugas yang ditugaskan dan super admin dapat melihat riwayat request desain', function (string $viewer): void {
    $requester = createDesignHistoryUser();
    $officer = createDesignHistoryUser();
    $admin = createDesignHistoryUser(admin: true);
    $designRequest = createDesignRequestForHistory($requester, $officer);
    Sanctum::actingAs(match ($viewer) {
        'pemohon' => $requester,
        'petugas' => $officer,
        'super admin' => $admin,
    });

    $this->getJson(designHistoryUrl($designRequest))
        ->assertOk()
        ->assertExactJson(['data' => []]);
})->with([
    'pemohon' => 'pemohon',
    'petugas yang ditugaskan' => 'petugas',
    'super admin' => 'super admin',
]);

test('pengguna tanpa hak akses dan guest ditolak melihat riwayat request desain', function (string $viewer, int $status): void {
    $requester = createDesignHistoryUser();
    $designRequest = createDesignRequestForHistory($requester);

    if ($viewer === 'tanpa permission') {
        Sanctum::actingAs(createDesignHistoryUser(canRead: false));
    } elseif ($viewer === 'pengguna lain') {
        Sanctum::actingAs(createDesignHistoryUser());
    }

    $this->getJson(designHistoryUrl($designRequest))->assertStatus($status);
})->with([
    'belum login' => ['guest', 401],
    'tanpa permission' => ['tanpa permission', 403],
    'bukan pemohon atau petugas' => ['pengguna lain', 403],
]);

test('riwayat draft dan revisi terurut serta hanya berasal dari request yang diminta', function (): void {
    $requester = createDesignHistoryUser();
    $officer = createDesignHistoryUser();
    $designRequest = createDesignRequestForHistory($requester, $officer);
    $otherRequest = createDesignRequestForHistory(createDesignHistoryUser());
    Sanctum::actingAs($requester);

    $firstKey = 'design-requests/drafts/awal.pdf';
    $secondKey = 'design-requests/drafts/lanjutan.pdf';
    Storage::disk('local')->put($firstKey, 'draft awal');
    Storage::disk('local')->put($secondKey, 'draft lanjutan');

    $firstDraft = DesignDraft::create([
        'design_request_id' => $designRequest->id,
        'file_object_key' => $firstKey,
        'file_original_name' => 'awal.pdf',
        'file_mime_type' => 'application/pdf',
        'file_size' => 10,
        'notes' => 'Versi awal',
        'uploaded_by_id' => $officer->id,
    ]);
    $secondDraft = DesignDraft::create([
        'design_request_id' => $designRequest->id,
        'file_object_key' => $secondKey,
        'file_original_name' => 'lanjutan.pdf',
        'file_mime_type' => 'application/pdf',
        'file_size' => 14,
        'notes' => 'Setelah revisi',
        'uploaded_by_id' => $officer->id,
    ]);
    $revision = DesignRevision::create([
        'design_request_id' => $designRequest->id,
        'design_draft_id' => $firstDraft->id,
        'revision_note' => 'Perbesar judul',
        'requested_by_id' => $requester->id,
    ]);
    $firstDraft->forceFill(['created_at' => '2026-01-01 08:00:00'])->save();
    $revision->forceFill(['created_at' => '2026-01-02 08:00:00'])->save();
    $secondDraft->forceFill(['created_at' => '2026-01-03 08:00:00'])->save();

    $otherDraft = DesignDraft::create([
        'design_request_id' => $otherRequest->id,
        'file_object_key' => 'design-requests/drafts/lain.pdf',
        'file_original_name' => 'lain.pdf',
        'file_mime_type' => 'application/pdf',
        'file_size' => 8,
        'uploaded_by_id' => $officer->id,
    ]);
    DesignRevision::create([
        'design_request_id' => $otherRequest->id,
        'design_draft_id' => $otherDraft->id,
        'revision_note' => 'Revisi request lain',
        'requested_by_id' => $otherRequest->requester_id,
    ]);

    $response = $this->getJson(designHistoryUrl($designRequest))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.type', 'draft')
        ->assertJsonPath('data.0.id', $firstDraft->id)
        ->assertJsonPath('data.0.created_at', $firstDraft->created_at->toJSON())
        ->assertJsonPath('data.0.file.original_name', 'awal.pdf')
        ->assertJsonPath('data.0.file.mime_type', 'application/pdf')
        ->assertJsonPath('data.0.file.size', 10)
        ->assertJsonPath('data.0.notes', 'Versi awal')
        ->assertJsonPath('data.0.uploaded_by.id', $officer->id)
        ->assertJsonPath('data.0.uploaded_by.name', $officer->name)
        ->assertJsonPath('data.1.type', 'revision')
        ->assertJsonPath('data.1.id', $revision->id)
        ->assertJsonPath('data.1.created_at', $revision->created_at->toJSON())
        ->assertJsonPath('data.1.design_draft_id', $firstDraft->id)
        ->assertJsonPath('data.1.revision_note', 'Perbesar judul')
        ->assertJsonPath('data.1.requested_by.id', $requester->id)
        ->assertJsonPath('data.1.requested_by.name', $requester->name)
        ->assertJsonPath('data.2.type', 'draft')
        ->assertJsonPath('data.2.id', $secondDraft->id)
        ->assertJsonPath('data.2.created_at', $secondDraft->created_at->toJSON())
        ->assertJsonMissingPath('data.0.file.object_key');

    expect($response->json('data.0.file.url'))
        ->toStartWith('http')
        ->toContain('expiration=');
    expect($response->json('data.2.file.url'))
        ->toStartWith('http')
        ->toContain('expiration=');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->not->toContain($otherDraft->id);
});

test('request desain tanpa draft atau revisi menghasilkan riwayat kosong', function (): void {
    $requester = createDesignHistoryUser();
    $designRequest = createDesignRequestForHistory($requester);
    Sanctum::actingAs($requester);

    $this->getJson(designHistoryUrl($designRequest))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('riwayat request desain yang tidak ditemukan menghasilkan 404', function (): void {
    Sanctum::actingAs(createDesignHistoryUser());

    $this->getJson('/api/v1/design-requests/999999/history')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
