<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignDraft;
use App\Models\DesignRequest;
use App\Models\DesignRevision;
use App\Models\Unit;
use App\Models\User;
use App\Services\DesignWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
});

function createServiceTestDesignRequest(array $attributes = []): DesignRequest
{
    $requesterId = $attributes['requester_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $officerId = $attributes['assigned_officer_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $unitId = $attributes['unit_id'] ?? User::find($requesterId)?->unit_id ?? Unit::factory()->create()->id;

    return DesignRequest::create(array_merge([
        'request_number' => 'DGR-'.uniqid(),
        'title' => 'Test Poster Flyer',
        'design_type' => 'Flyer Promosi',
        'description' => 'Deskripsi flyer promosi',
        'needed_at' => now()->addDays(3),
        'requester_id' => $requesterId,
        'assigned_officer_id' => $officerId,
        'unit_id' => $unitId,
        'status' => DesignRequestStatus::Ditugaskan,
    ], $attributes));
}

test('storeDraft menyimpan file, membuat draft, dan memperbarui status menjadi MenungguPersetujuan', function (): void {
    $service = app(DesignWorkflowService::class);
    $designRequest = createServiceTestDesignRequest();

    $officer = User::find($designRequest->assigned_officer_id);
    Sanctum::actingAs($officer);

    $file = UploadedFile::fake()->create('draft_preview.png', 1024, 'image/png');
    $notes = 'Draft versi awal silakan direview';

    $draft = $service->storeDraft($designRequest, $file, $notes);

    expect($draft)->toBeInstanceOf(DesignDraft::class);
    expect($draft->notes)->toBe($notes);
    expect($draft->file_original_name)->toBe('draft_preview.png');
    expect($draft->file_name)->toBe('draft_preview.png');
    expect($draft->file_size)->toBe($file->getSize());
    expect($draft->uploaded_by_id)->toBe($officer->id);
    expect($draft->file_path)->not->toBeEmpty();
    expect($draft->file_object_key)->not->toBeEmpty();

    Storage::disk('local')->assertExists($draft->file_path);

    $designRequest->refresh();
    expect($designRequest->status)->toBe(DesignRequestStatus::MenungguPersetujuan);

    $this->assertDatabaseHas('design_drafts', [
        'id' => $draft->id,
        'design_request_id' => $designRequest->id,
        'notes' => $notes,
        'file_original_name' => 'draft_preview.png',
        'uploaded_by_id' => $officer->id,
    ]);
});

test('storeRevision mengaitkan draft terakhir, membuat revisi, dan memperbarui status menjadi PerluRevisi', function (): void {
    $service = app(DesignWorkflowService::class);
    $designRequest = createServiceTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
    ]);

    $requester = User::find($designRequest->requester_id);
    Sanctum::actingAs($requester);

    $file1 = UploadedFile::fake()->create('draft_v1.png', 500);
    $draft1 = $service->storeDraft($designRequest, $file1, 'Versi 1');

    $file2 = UploadedFile::fake()->create('draft_v2.png', 600);
    $draft2 = $service->storeDraft($designRequest, $file2, 'Versi 2');

    $revisionNote = 'Warna latar belakang perlu diganti menjadi biru rumah sakit.';
    $revision = $service->storeRevision($designRequest, $revisionNote);

    expect($revision)->toBeInstanceOf(DesignRevision::class);
    expect($revision->design_request_id)->toBe($designRequest->id);
    expect($revision->design_draft_id)->toBe($draft2->id);
    expect($revision->revision_note)->toBe($revisionNote);
    expect($revision->requested_by_id)->toBe($requester->id);

    $designRequest->refresh();
    expect($designRequest->status)->toBe(DesignRequestStatus::PerluRevisi);

    $this->assertDatabaseHas('design_revisions', [
        'id' => $revision->id,
        'design_request_id' => $designRequest->id,
        'design_draft_id' => $draft2->id,
        'revision_note' => $revisionNote,
        'requested_by_id' => $requester->id,
    ]);
});

test('approve memperbarui status request menjadi Selesai dan mengisi completed_at', function (): void {
    $service = app(DesignWorkflowService::class);
    $designRequest = createServiceTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
    ]);

    $result = $service->approve($designRequest);

    expect($result)->toBeTrue();

    $designRequest->refresh();
    expect($designRequest->status)->toBe(DesignRequestStatus::Selesai);
    expect($designRequest->completed_at)->not->toBeNull();
});

test('getFinalFileUrl mengembalikan url file draft terakhir', function (): void {
    $service = app(DesignWorkflowService::class);
    $designRequest = createServiceTestDesignRequest();

    $file = UploadedFile::fake()->create('final_design.png', 800);
    $draft = $service->storeDraft($designRequest, $file, 'Draft final');

    $url = $service->getFinalFileUrl($designRequest);

    expect($url)->toBeString();
    expect($url)->not->toBeEmpty();
    expect($url)->toContain($draft->file_path);
});
