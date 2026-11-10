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

function createDesignDetailUser(bool $canRead = true, bool $admin = false): User
{
    $user = User::factory()->for(Unit::factory())->create();

    if ($canRead) {
        $user->givePermissionTo('design-requests-read');
    }

    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    return $user;
}

/** @param array<string, mixed> $attributes */
function createDesignRequestForDetail(User $requester, ?User $officer = null, array $attributes = []): DesignRequest
{
    return DesignRequest::create([
        'request_number' => 'DGR-'.now()->format('Y').'-000001',
        'title' => 'Poster edukasi pasien',
        'design_type' => 'Poster edukasi',
        'description' => 'Poster untuk ruang tunggu.',
        'needed_at' => today()->addDays(7),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru,
        'assigned_officer_id' => $officer?->id,
        ...$attributes,
    ]);
}

test('pemohon petugas yang ditugaskan dan super admin dapat melihat detail request desain', function (string $viewer): void {
    $requester = createDesignDetailUser();
    $officer = createDesignDetailUser();
    $admin = createDesignDetailUser(admin: true);
    $designRequest = createDesignRequestForDetail($requester, $officer);
    Sanctum::actingAs(match ($viewer) {
        'pemohon' => $requester,
        'petugas' => $officer,
        'super admin' => $admin,
    });

    $this->getJson("/api/v1/design-requests/{$designRequest->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $designRequest->id)
        ->assertJsonPath('data.requester.id', $requester->id)
        ->assertJsonPath('data.assigned_officer.id', $officer->id);
})->with([
    'pemohon' => 'pemohon',
    'petugas yang ditugaskan' => 'petugas',
    'super admin' => 'super admin',
]);

test('pengguna tanpa hak akses dan guest ditolak melihat detail request desain', function (
    string $viewer,
    int $status,
): void {
    $requester = createDesignDetailUser();
    $designRequest = createDesignRequestForDetail($requester);

    if ($viewer === 'tanpa permission') {
        Sanctum::actingAs(createDesignDetailUser(canRead: false));
    } elseif ($viewer === 'pengguna lain') {
        Sanctum::actingAs(createDesignDetailUser());
    }

    $this->getJson("/api/v1/design-requests/{$designRequest->id}")->assertStatus($status);
})->with([
    'belum login' => ['guest', 401],
    'tanpa permission' => ['tanpa permission', 403],
    'bukan pemohon atau petugas' => ['pengguna lain', 403],
]);

test('detail request desain menampilkan file referensi serta draft dan revisi terbaru', function (): void {
    $requester = createDesignDetailUser();
    $officer = createDesignDetailUser();
    Sanctum::actingAs($requester);

    $objectKey = 'design-requests/references/referensi.pdf';
    Storage::disk('local')->put($objectKey, 'contoh file');
    $designRequest = createDesignRequestForDetail($requester, $officer, [
        'status' => DesignRequestStatus::Selesai,
        'priority' => 'high',
        'assigned_at' => now()->subDays(2),
        'completed_at' => now(),
        'rejection_note' => 'Catatan keputusan',
        'reference_file_object_key' => $objectKey,
        'reference_file_original_name' => 'referensi.pdf',
        'reference_file_mime_type' => 'application/pdf',
        'reference_file_size' => 11,
    ]);

    $oldDraft = DesignDraft::create([
        'design_request_id' => $designRequest->id,
        'file_object_key' => 'design-requests/drafts/lama.pdf',
        'file_original_name' => 'lama.pdf',
        'file_mime_type' => 'application/pdf',
        'file_size' => 100,
        'notes' => 'Draft awal',
        'uploaded_by_id' => $officer->id,
    ]);
    $latestDraft = DesignDraft::create([
        'design_request_id' => $designRequest->id,
        'file_object_key' => 'design-requests/drafts/terbaru.pdf',
        'file_original_name' => 'terbaru.pdf',
        'file_mime_type' => 'application/pdf',
        'file_size' => 200,
        'notes' => 'Draft revisi',
        'uploaded_by_id' => $officer->id,
    ]);
    DesignRevision::create([
        'design_request_id' => $designRequest->id,
        'design_draft_id' => $oldDraft->id,
        'revision_note' => 'Perbaiki warna',
        'requested_by_id' => $requester->id,
    ]);
    $latestRevision = DesignRevision::create([
        'design_request_id' => $designRequest->id,
        'design_draft_id' => $latestDraft->id,
        'revision_note' => 'Perbesar judul',
        'requested_by_id' => $requester->id,
    ]);

    $response = $this->getJson("/api/v1/design-requests/{$designRequest->id}")
        ->assertOk()
        ->assertJsonPath('data.request_number', $designRequest->request_number)
        ->assertJsonPath('data.title', 'Poster edukasi pasien')
        ->assertJsonPath('data.design_type', 'Poster edukasi')
        ->assertJsonPath('data.description', 'Poster untuk ruang tunggu.')
        ->assertJsonPath('data.status', DesignRequestStatus::Selesai->value)
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.requester.id', $requester->id)
        ->assertJsonPath('data.unit.id', $requester->unit_id)
        ->assertJsonPath('data.assigned_officer.id', $officer->id)
        ->assertJsonPath('data.rejection_note', 'Catatan keputusan')
        ->assertJsonPath('data.reference_file.original_name', 'referensi.pdf')
        ->assertJsonPath('data.reference_file.mime_type', 'application/pdf')
        ->assertJsonPath('data.reference_file.size', 11)
        ->assertJsonPath('data.latest_draft.id', $latestDraft->id)
        ->assertJsonPath('data.latest_draft.file.original_name', 'terbaru.pdf')
        ->assertJsonPath('data.latest_draft.uploaded_by.id', $officer->id)
        ->assertJsonPath('data.latest_revision.id', $latestRevision->id)
        ->assertJsonPath('data.latest_revision.design_draft_id', $latestDraft->id)
        ->assertJsonPath('data.latest_revision.revision_note', 'Perbesar judul')
        ->assertJsonPath('data.latest_revision.requested_by.id', $requester->id)
        ->assertJsonMissingPath('data.reference_file.object_key')
        ->assertJsonMissingPath('data.latest_draft.file.object_key');

    expect($response->json('data.needed_at'))->not->toBeNull();
    expect($response->json('data.assigned_at'))->not->toBeNull();
    expect($response->json('data.completed_at'))->not->toBeNull();
    expect($response->json('data.reference_file.url'))
        ->toStartWith('http')
        ->toContain('expiration=');
});

test('detail request desain tanpa file draft atau revisi tetap valid', function (): void {
    $requester = createDesignDetailUser();
    $designRequest = createDesignRequestForDetail($requester);
    Sanctum::actingAs($requester);

    $this->getJson("/api/v1/design-requests/{$designRequest->id}")
        ->assertOk()
        ->assertJsonPath('data.reference_file', null)
        ->assertJsonPath('data.latest_draft', null)
        ->assertJsonPath('data.latest_revision', null)
        ->assertJsonPath('data.assigned_officer', null)
        ->assertJsonPath('data.priority', null)
        ->assertJsonPath('data.rejection_note', null);
});

test('detail request desain yang tidak ditemukan menghasilkan 404', function (): void {
    Sanctum::actingAs(createDesignDetailUser());

    $this->getJson('/api/v1/design-requests/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
