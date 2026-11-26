<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\DesignNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('alur desain memberi tahu admin, pemohon, dan petugas sesuai tahap', function (): void {
    $requester = User::factory()->for(Unit::factory())->create();
    $officer = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $request = DesignRequest::create([
        'request_number' => 'DGR-2026-000123',
        'title' => 'Poster edukasi',
        'design_type' => 'Poster',
        'description' => 'Poster ruang tunggu',
        'needed_at' => now()->addWeek(),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'assigned_officer_id' => $officer->id,
        'status' => DesignRequestStatus::Baru,
    ]);
    $service = app(DesignNotificationService::class);

    $service->created($request);
    $request->update(['status' => DesignRequestStatus::Ditugaskan]);
    $service->assigned($request);
    $request->update(['status' => DesignRequestStatus::MenungguPersetujuan]);
    $service->draftUploaded($request);
    $request->update(['status' => DesignRequestStatus::PerluRevisi]);
    $service->revisionRequested($request);
    $request->update(['status' => DesignRequestStatus::Selesai]);
    $service->approved($request);

    expect($admin->notifications()->firstOrFail()->data['title'])->toBe('Request Desain Baru');
    expect($requester->notifications()->firstOrFail()->data['subtitle'])
        ->toContain('setujui jika sudah sesuai atau ajukan revisi');
    expect($officer->notifications()->get()->pluck('data')->pluck('title')->all())
        ->toContain('Desain Ditugaskan', 'Revisi Desain Diminta', 'Desain Disetujui');

    $data = $officer->notifications()->firstOrFail()->data;
    expect($data['design_request_id'])->toBe($request->id)
        ->and($data['target']['feature'])->toBe('design-requests');

    Sanctum::actingAs($officer);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.design_request_id', $request->id)
        ->assertJsonMissingPath('data.0.ticket_id');
});

test('penolakan desain memberi tahu pemohon', function (): void {
    $requester = User::factory()->for(Unit::factory())->create();
    $request = DesignRequest::create([
        'request_number' => 'DGR-2026-000124',
        'title' => 'Poster edukasi',
        'design_type' => 'Poster',
        'description' => 'Poster ruang tunggu',
        'needed_at' => now()->addWeek(),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Ditolak,
    ]);

    app(DesignNotificationService::class)->rejected($request);

    expect($requester->notifications()->firstOrFail()->data['status'])->toBe('ditolak');
});
