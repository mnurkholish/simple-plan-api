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
    Permission::firstOrCreate(['name' => 'design-requests-reject', 'guard_name' => 'web']);
    $requester = User::factory()->for(Unit::factory())->create();
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

function actingAsDesignRequestRejector(string $role = 'super-admin', bool $canReject = true): void
{
    $user = User::factory()->for(Unit::factory())->create();
    $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    if ($canReject) {
        $user->givePermissionTo('design-requests-reject');
    }

    Sanctum::actingAs($user);
}

test('super admin dapat menolak request desain baru dan menyimpan catatan penolakan', function (): void {
    actingAsDesignRequestRejector();

    $this->postJson("/api/v1/design-requests/{$this->designRequest->id}/rejections", [
        'rejection_note' => 'Informasi kebutuhan desain belum lengkap.',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Request desain berhasil ditolak.')
        ->assertJsonPath('data.status', DesignRequestStatus::Ditolak->value)
        ->assertJsonPath('data.rejection_note', 'Informasi kebutuhan desain belum lengkap.');

    $this->assertDatabaseHas('design_requests', [
        'id' => $this->designRequest->id,
        'status' => DesignRequestStatus::Ditolak->value,
        'rejection_note' => 'Informasi kebutuhan desain belum lengkap.',
        'assigned_officer_id' => null,
    ]);
});

test('pengguna tanpa hak atau belum login tidak dapat menolak request desain', function (
    ?string $role,
    bool $canReject,
    int $status,
): void {
    $before = $this->designRequest->fresh()->getAttributes();

    if ($role !== null) {
        actingAsDesignRequestRejector($role, $canReject);
    }

    $this->postJson("/api/v1/design-requests/{$this->designRequest->id}/rejections", [
        'rejection_note' => 'Alasan penolakan.',
    ])->assertStatus($status);

    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'belum login' => [null, false, 401],
    'super admin tanpa permission' => ['super-admin', false, 403],
    'pengguna biasa dengan permission' => ['user', true, 403],
]);

test('catatan penolakan yang tidak valid ditolak', function (array $payload): void {
    actingAsDesignRequestRejector();
    $before = $this->designRequest->fresh()->getAttributes();

    $this->postJson("/api/v1/design-requests/{$this->designRequest->id}/rejections", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rejection_note');

    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'catatan kosong' => [[]],
    'catatan bukan teks' => [['rejection_note' => ['tidak valid']]],
]);

test('request desain selain status baru tidak dapat ditolak', function (DesignRequestStatus $status): void {
    actingAsDesignRequestRejector();
    $this->designRequest->update(['status' => $status]);
    $before = $this->designRequest->fresh()->getAttributes();

    $this->postJson("/api/v1/design-requests/{$this->designRequest->id}/rejections", [
        'rejection_note' => 'Alasan baru.',
    ])->assertStatus(409);

    expect($this->designRequest->fresh()->getAttributes())->toBe($before);
})->with([
    'sudah ditugaskan' => DesignRequestStatus::Ditugaskan,
    'sudah ditolak' => DesignRequestStatus::Ditolak,
]);
