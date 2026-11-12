<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use App\Policies\DesignRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createPolicyTestDesignRequest(array $attributes = []): DesignRequest
{
    $requesterId = $attributes['requester_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $officerId = $attributes['assigned_officer_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $unitId = $attributes['unit_id'] ?? User::find($requesterId)?->unit_id ?? Unit::factory()->create()->id;

    return DesignRequest::create(array_merge([
        'request_number' => 'DGR-'.uniqid(),
        'title' => 'Design Poster',
        'design_type' => 'Poster',
        'description' => 'Deskripsi poster',
        'needed_at' => now()->addDays(2),
        'requester_id' => $requesterId,
        'assigned_officer_id' => $officerId,
        'unit_id' => $unitId,
        'status' => DesignRequestStatus::Ditugaskan,
    ], $attributes));
}

test('uploadDraft hanya mengizinkan petugas-tik yang secara spesifik ditugaskan pada request', function (): void {
    $designRequest = createPolicyTestDesignRequest();
    $policy = new DesignRequestPolicy;

    $assignedOfficer = User::find($designRequest->assigned_officer_id);
    $assignedOfficer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $otherTikOfficer = User::factory()->create(['unit_id' => Unit::factory()]);
    $otherTikOfficer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    $assignedWithoutTikRole = User::factory()->create(['unit_id' => Unit::factory()]);
    $requestForNonTik = createPolicyTestDesignRequest(['assigned_officer_id' => $assignedWithoutTikRole->id]);

    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $requester = User::find($designRequest->requester_id);

    // Petugas TIK yang ditugaskan -> HARUS true
    expect($policy->uploadDraft($assignedOfficer, $designRequest))->toBeTrue();

    // Petugas TIK lain yang tidak ditugaskan -> HARUS false
    expect($policy->uploadDraft($otherTikOfficer, $designRequest))->toBeFalse();

    // Petugas yang ditugaskan tetapi bukan role petugas-tik -> HARUS false
    expect($policy->uploadDraft($assignedWithoutTikRole, $requestForNonTik))->toBeFalse();

    // Super Admin bukan eksekutor -> HARUS false
    expect($policy->uploadDraft($superAdmin, $designRequest))->toBeFalse();

    // Pemohon bukan eksekutor -> HARUS false
    expect($policy->uploadDraft($requester, $designRequest))->toBeFalse();
});

test('revise hanya mengizinkan pemohon request desain', function (): void {
    $designRequest = createPolicyTestDesignRequest();
    $policy = new DesignRequestPolicy;

    $requester = User::find($designRequest->requester_id);
    $officer = User::find($designRequest->assigned_officer_id);
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);

    expect($policy->revise($requester, $designRequest))->toBeTrue();
    expect($policy->revise($officer, $designRequest))->toBeFalse();
    expect($policy->revise($superAdmin, $designRequest))->toBeFalse();
    expect($policy->revise($otherUser, $designRequest))->toBeFalse();
});

test('approve hanya mengizinkan pemohon request desain', function (): void {
    $designRequest = createPolicyTestDesignRequest();
    $policy = new DesignRequestPolicy;

    $requester = User::find($designRequest->requester_id);
    $officer = User::find($designRequest->assigned_officer_id);
    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);

    expect($policy->approve($requester, $designRequest))->toBeTrue();
    expect($policy->approve($officer, $designRequest))->toBeFalse();
    expect($policy->approve($superAdmin, $designRequest))->toBeFalse();
    expect($policy->approve($otherUser, $designRequest))->toBeFalse();
});

test('downloadFinal mengizinkan super-admin, pemohon, dan petugas yang ditugaskan, tetapi menolak user lain', function (): void {
    $designRequest = createPolicyTestDesignRequest();
    $policy = new DesignRequestPolicy;

    $superAdmin = User::factory()->create(['unit_id' => Unit::factory()]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $requester = User::find($designRequest->requester_id);
    $officer = User::find($designRequest->assigned_officer_id);
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);

    expect($policy->downloadFinal($superAdmin, $designRequest))->toBeTrue();
    expect($policy->downloadFinal($requester, $designRequest))->toBeTrue();
    expect($policy->downloadFinal($officer, $designRequest))->toBeTrue();
    expect($policy->downloadFinal($otherUser, $designRequest))->toBeFalse();
});

test('Gate::authorize bekerja secara otomatis melalui auto-discovery Laravel Policy', function (): void {
    $designRequest = createPolicyTestDesignRequest();
    $officer = User::find($designRequest->assigned_officer_id);
    $officer->assignRole(Role::firstOrCreate(['name' => 'petugas-tik', 'guard_name' => 'web']));

    expect(Gate::forUser($officer)->allows('uploadDraft', $designRequest))->toBeTrue();
    expect(Gate::forUser($officer)->denies('revise', $designRequest))->toBeTrue();
});
