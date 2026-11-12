<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['design-requests-assign', 'design-drafts-upload'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->officer = createDesignAssignmentUser('petugas-tik', canUploadDraft: true);
    $requester = createDesignAssignmentUser();
    $this->designRequest = DesignRequest::create([
        'request_number' => 'DGR-'.now()->format('Y').'-000001',
        'title' => 'Poster edukasi pasien',
        'design_type' => 'Poster edukasi',
        'description' => 'Desain untuk ruang tunggu.',
        'needed_at' => today()->addDays(7),
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru,
    ]);
});

function createDesignAssignmentUser(string $role = 'user', bool $canUploadDraft = false): User
{
    $user = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    if ($canUploadDraft) {
        $user->givePermissionTo('design-drafts-upload');
    }

    return $user;
}

function actingAsDesignRequestAssigner(string $role = 'super-admin', bool $canAssign = true): void
{
    $user = createDesignAssignmentUser($role);

    if ($canAssign) {
        $user->givePermissionTo('design-requests-assign');
    }

    Sanctum::actingAs($user);
}

function designAssignmentUrl(DesignRequest $designRequest): string
{
    return "/api/v1/design-requests/{$designRequest->id}/assignment";
}

/** @return array{priority: string, assigned_officer_id: int} */
function designAssignmentData(User $officer, string $priority = 'sedang'): array
{
    return ['priority' => $priority, 'assigned_officer_id' => $officer->id];
}

test('super admin dapat menugaskan request desain baru dan menyimpan hasil assignment', function (): void {
    actingAsDesignRequestAssigner();
    Carbon::setTestNow('2026-11-10 08:00:00');

    try {
        $this->putJson(designAssignmentUrl($this->designRequest), designAssignmentData($this->officer))
            ->assertOk()
            ->assertJsonPath('message', 'Request desain berhasil ditugaskan.')
            ->assertJsonPath('data.status', DesignRequestStatus::Ditugaskan->value)
            ->assertJsonPath('data.priority', 'sedang')
            ->assertJsonPath('data.assigned_officer.id', $this->officer->id);

        $this->designRequest->refresh();
        expect($this->designRequest->status)->toBe(DesignRequestStatus::Ditugaskan)
            ->and($this->designRequest->priority)->toBe('sedang')
            ->and($this->designRequest->assigned_officer_id)->toBe($this->officer->id)
            ->and($this->designRequest->assigned_at?->format('Y-m-d H:i:s'))->toBe('2026-11-10 08:00:00');
    } finally {
        Carbon::setTestNow();
    }
});

test('pengguna tanpa hak atau belum login ditolak melakukan assignment', function (?string $role, bool $canAssign, int $status): void {
    $before = $this->designRequest->fresh()->getAttributes();

    if ($role !== null) {
        actingAsDesignRequestAssigner($role, $canAssign);
    }

    $this->putJson(designAssignmentUrl($this->designRequest), designAssignmentData($this->officer))
        ->assertStatus($status);
    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'belum login' => [null, false, 401],
    'super admin tanpa permission' => ['super-admin', false, 403],
    'pengguna biasa dengan permission' => ['user', true, 403],
]);

test('prioritas atau id petugas yang tidak valid ditolak', function (array $changes, string $field): void {
    actingAsDesignRequestAssigner();
    $before = $this->designRequest->fresh()->getAttributes();

    $this->putJson(designAssignmentUrl($this->designRequest), [
        ...designAssignmentData($this->officer),
        ...$changes,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'prioritas tidak dikenal' => [['priority' => 'urgent'], 'priority'],
    'id petugas tidak ditemukan' => [['assigned_officer_id' => 999999], 'assigned_officer_id'],
]);

test('petugas yang tidak memenuhi kriteria desain ditolak', function (string $role, bool $canUploadDraft): void {
    actingAsDesignRequestAssigner();
    $candidate = createDesignAssignmentUser($role, $canUploadDraft);
    $before = $this->designRequest->fresh()->getAttributes();

    $this->putJson(designAssignmentUrl($this->designRequest), designAssignmentData($candidate))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_officer_id');

    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'bukan petugas tik' => ['user', true],
    'petugas tik tanpa permission upload draft' => ['petugas-tik', false],
]);

test('request desain selain status baru tidak dapat ditugaskan ulang', function (DesignRequestStatus $status): void {
    actingAsDesignRequestAssigner();
    $this->designRequest->update([
        'status' => $status,
        'priority' => $status === DesignRequestStatus::Ditugaskan ? 'rendah' : null,
        'assigned_officer_id' => $status === DesignRequestStatus::Ditugaskan ? $this->officer->id : null,
        'assigned_at' => $status === DesignRequestStatus::Ditugaskan ? now()->subDay() : null,
    ]);
    $before = $this->designRequest->fresh()->getAttributes();

    $this->putJson(designAssignmentUrl($this->designRequest), designAssignmentData($this->officer, 'tinggi'))
        ->assertStatus(409);
    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'ditugaskan' => DesignRequestStatus::Ditugaskan,
    'selesai' => DesignRequestStatus::Selesai,
]);
