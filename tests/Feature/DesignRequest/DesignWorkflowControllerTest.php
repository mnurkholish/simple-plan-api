<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\DesignWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
});

function createWorkflowControllerTestDesignRequest(array $attributes = []): DesignRequest
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

test('storeDraft pada controller mengembalikan response 201 dan data draft', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest();
    $officer = User::find($designRequest->assigned_officer_id);

    $permission = Permission::firstOrCreate(['name' => 'design-drafts-upload', 'guard_name' => 'web']);
    $officer->givePermissionTo($permission);
    Sanctum::actingAs($officer);

    $file = UploadedFile::fake()->create('draft_1.png', 500, 'image/png');

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/drafts", [
        'file' => $file,
        'notes' => 'Catatan draft awal',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Draft desain berhasil diunggah.')
        ->assertJsonPath('data.design_request_id', $designRequest->id)
        ->assertJsonPath('data.notes', 'Catatan draft awal');

    $designRequest->refresh();
    expect($designRequest->status)->toBe(DesignRequestStatus::MenungguPersetujuan);
});

test('storeRevision pada controller mengembalikan response 201 dan data revisi', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest([
        'status' => DesignRequestStatus::Ditugaskan,
    ]);

    $service = app(DesignWorkflowService::class);
    $service->storeDraft($designRequest, UploadedFile::fake()->create('draft.png', 300));

    $requester = User::find($designRequest->requester_id);
    $permission = Permission::firstOrCreate(['name' => 'design-requests-revise', 'guard_name' => 'web']);
    $requester->givePermissionTo($permission);
    Sanctum::actingAs($requester);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/revisions", [
        'revision_note' => 'Perbaiki tata letak teks logo',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Revisi desain berhasil diajukan.')
        ->assertJsonPath('data.design_request_id', $designRequest->id)
        ->assertJsonPath('data.revision_note', 'Perbaiki tata letak teks logo');

    $designRequest->refresh();
    expect($designRequest->status)->toBe(DesignRequestStatus::PerluRevisi);
});

test('storeApproval pada controller berhasil menyetujui request jika user adalah pemohon dan status menunggu persetujuan', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
    ]);

    $requester = User::find($designRequest->requester_id);
    $permission = Permission::firstOrCreate(['name' => 'design-requests-approve', 'guard_name' => 'web']);
    $requester->givePermissionTo($permission);
    Sanctum::actingAs($requester);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/approvals");

    $response->assertOk()
        ->assertJsonPath('message', 'Request desain berhasil disetujui.');

    $designRequest->refresh();
    expect($designRequest->status)->toBe(DesignRequestStatus::Selesai);
    expect($designRequest->completed_at)->not->toBeNull();
});

test('storeApproval pada controller mengembalikan 403 jika user bukan pemohon', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
    ]);

    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);
    $permission = Permission::firstOrCreate(['name' => 'design-requests-approve', 'guard_name' => 'web']);
    $otherUser->givePermissionTo($permission);
    Sanctum::actingAs($otherUser);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/approvals");

    $response->assertForbidden();
});

test('storeApproval pada controller mengembalikan 403 jika status bukan MenungguPersetujuan', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest([
        'status' => DesignRequestStatus::Ditugaskan,
    ]);

    $requester = User::find($designRequest->requester_id);
    $permission = Permission::firstOrCreate(['name' => 'design-requests-approve', 'guard_name' => 'web']);
    $requester->givePermissionTo($permission);
    Sanctum::actingAs($requester);

    $response = $this->postJson("/api/v1/design-requests/{$designRequest->id}/approvals");

    $response->assertForbidden();
});

test('downloadFinal mengembalikan url file final jika status selesai', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest();

    $service = app(DesignWorkflowService::class);
    $draft = $service->storeDraft($designRequest, UploadedFile::fake()->create('final.png', 400));
    $service->approve($designRequest);

    $requester = User::find($designRequest->requester_id);
    Sanctum::actingAs($requester);

    $response = $this->getJson("/api/v1/design-requests/{$designRequest->id}/final-file");

    $response->assertOk()
        ->assertJsonStructure(['final_file_url']);

    expect($response->json('final_file_url'))->toContain($draft->file_path);
});

test('downloadFinal mengembalikan 403 jika request belum berstatus selesai', function (): void {
    $designRequest = createWorkflowControllerTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
    ]);

    $requester = User::find($designRequest->requester_id);
    Sanctum::actingAs($requester);

    $response = $this->getJson("/api/v1/design-requests/{$designRequest->id}/final-file");

    $response->assertForbidden();
});
