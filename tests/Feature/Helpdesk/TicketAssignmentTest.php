<?php

use App\Enums\TicketPriority;
use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    createTicketAssignmentUser('user');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function createTicketAssignmentUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);
    $user = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
        ...$attributes,
    ]);
    $user->assignRole($role);

    return $user;
}

function actingAsTicketAssignmentUser(string $roleName, bool $withPermission = true): User
{
    $user = createTicketAssignmentUser($roleName);

    if ($withPermission) {
        $permission = Permission::firstOrCreate([
            'name' => 'tickets-assign',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);
    }

    Sanctum::actingAs($user);

    return $user;
}

test('role yang sesuai service dapat menjadi assignee', function (
    TicketService $service,
    string $assignerRole,
    string $assigneeRole,
): void {
    $assigner = actingAsTicketAssignmentUser($assignerRole);
    $assignee = createTicketAssignmentUser($assigneeRole);
    $ticket = Ticket::factory()->create([
        'service' => $service,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    Carbon::setTestNow('2026-10-05 08:00:00');

    try {
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assigned_officer_id' => $assignee->id,
            'priority' => TicketPriority::High->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::Diproses->value)
            ->assertJsonPath('data.priority', TicketPriority::High->value)
            ->assertJsonPath('data.assigned_officer.id', $assignee->id);
    } finally {
        Carbon::setTestNow();
    }

    $ticket->refresh();

    expect($ticket->assigned_officer_id)->toBe($assignee->id)
        ->and($ticket->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->assigned_at?->format('Y-m-d H:i:s'))->toBe('2026-10-05 08:00:00')
        ->and($ticket->sla_started_at?->format('Y-m-d H:i:s'))->toBe('2026-10-05 08:00:00')
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-10-05 12:00:00');

    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Diklasifikasi->value,
        'to_status' => TicketStatus::Diproses->value,
        'changed_by_id' => $assigner->id,
    ]);
})->with([
    'TIK kepada Super Admin' => [TicketService::Tik, 'super-admin', 'super-admin'],
    'TIK kepada Petugas TIK' => [TicketService::Tik, 'super-admin', 'petugas-tik'],
    'Sarpras kepada Koordinator Sarpras' => [TicketService::Sarpras, 'koordinator-sarpras', 'koordinator-sarpras'],
    'Sarpras kepada Petugas Sarpras' => [TicketService::Sarpras, 'koordinator-sarpras', 'petugas-sarpras'],
]);

test('assignee dengan role yang tidak sesuai service ditolak', function (
    TicketService $service,
    string $assignerRole,
    string $invalidAssigneeRole,
): void {
    actingAsTicketAssignmentUser($assignerRole);
    $assignee = createTicketAssignmentUser($invalidAssigneeRole);
    $ticket = Ticket::factory()->create([
        'service' => $service,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $assignee->id,
        'priority' => TicketPriority::Medium->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_officer_id');

    expect($ticket->refresh()->assigned_officer_id)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Diklasifikasi);
})->with([
    'TIK menolak Petugas Sarpras' => [TicketService::Tik, 'super-admin', 'petugas-sarpras'],
    'Sarpras menolak Petugas TIK' => [TicketService::Sarpras, 'koordinator-sarpras', 'petugas-tik'],
    'Sarpras menolak Super Admin sebagai assignee' => [TicketService::Sarpras, 'koordinator-sarpras', 'super-admin'],
]);

test('daftar kandidat hanya memuat role aktif yang sesuai service', function (
    TicketService $service,
    string $assignerRole,
    array $allowedRoles,
    string $invalidRole,
): void {
    $assigner = actingAsTicketAssignmentUser($assignerRole);
    $firstCandidate = createTicketAssignmentUser($allowedRoles[0]);
    $secondCandidate = createTicketAssignmentUser($allowedRoles[1]);
    $invalidCandidate = createTicketAssignmentUser($invalidRole);
    $inactiveCandidate = createTicketAssignmentUser($allowedRoles[1], [
        'status' => 'inactive',
        'status_user' => 'Nonaktif',
    ]);
    $ticket = Ticket::factory()->create([
        'service' => $service,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $response = $this->getJson("/api/v1/tickets/{$ticket->id}/assignee-options")
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $candidateIds = collect($response->json('data'))->pluck('id')->all();

    expect($candidateIds)->toContain($assigner->id, $firstCandidate->id, $secondCandidate->id)
        ->not->toContain($invalidCandidate->id, $inactiveCandidate->id);
})->with([
    'TIK' => [TicketService::Tik, 'super-admin', ['super-admin', 'petugas-tik'], 'petugas-sarpras'],
    'Sarpras' => [TicketService::Sarpras, 'koordinator-sarpras', ['koordinator-sarpras', 'petugas-sarpras'], 'petugas-tik'],
]);

test('assignment pertama menyertakan priority', function (): void {
    actingAsTicketAssignmentUser('super-admin');
    $assignee = createTicketAssignmentUser('petugas-tik');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $assignee->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('priority');

    expect($ticket->refresh()->assigned_officer_id)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Diklasifikasi);
});

test('role yang tidak berwenang tidak dapat melakukan assignment', function (): void {
    actingAsTicketAssignmentUser('petugas-tik');
    $assignee = createTicketAssignmentUser('petugas-tik');
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $assignee->id,
        'priority' => TicketPriority::Medium->value,
    ])->assertForbidden();
});

test('guest tidak dapat melakukan assignment atau melihat kandidat', function (): void {
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/assign")
        ->assertUnauthorized();
    $this->getJson("/api/v1/tickets/{$ticket->id}/assignee-options")
        ->assertUnauthorized();
});

test('penugasan ulang (reassignment) memperbarui petugas dan waktu penugasan tanpa mengubah SLA', function (): void {
    actingAsTicketAssignmentUser('super-admin');

    $originalOfficer = createTicketAssignmentUser('petugas-tik', ['name' => 'Petugas Pertama']);
    $newOfficer = createTicketAssignmentUser('petugas-tik', ['name' => 'Petugas Kedua']);

    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $originalOfficer->id,
        'priority' => TicketPriority::High,
        'assigned_at' => '2026-10-01 08:00:00',
        'sla_started_at' => '2026-10-01 08:00:00',
        'sla_deadline' => '2026-10-01 12:00:00',
    ]);

    // Priority dilarang saat reassignment (tiket berstatus diproses)
    $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assigned_officer_id' => $newOfficer->id,
        'priority' => TicketPriority::Critical->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('priority');

    // Reassignment yang valid tanpa field priority
    Carbon::setTestNow('2026-10-01 09:30:00');

    try {
        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assigned_officer_id' => $newOfficer->id,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Petugas berhasil ditugaskan ulang.')
        ->assertJsonPath('data.status', TicketStatus::Diproses->value)
        ->assertJsonPath('data.assigned_officer.id', $newOfficer->id)
        ->assertJsonPath('data.priority', TicketPriority::High->value);

    $ticket->refresh();

    expect($ticket->assigned_officer_id)->toBe($newOfficer->id)
        ->and($ticket->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->assigned_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:30:00')
        ->and($ticket->sla_started_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 08:00:00')
        ->and($ticket->sla_deadline?->format('Y-m-d H:i:s'))->toBe('2026-10-01 12:00:00');

    // Transisi Diproses -> Diproses tidak membuat status history duplikat
    expect($ticket->statusHistories()->count())->toBe(0);
});

test('pencarian kandidat petugas melalui parameter search mencocokkan nama atau jabatan', function (): void {
    actingAsTicketAssignmentUser('super-admin');

    $candidate1 = createTicketAssignmentUser('petugas-tik', ['name' => 'Ahmad Teknisi', 'jabatan' => 'Staff IT']);
    $candidate2 = createTicketAssignmentUser('petugas-tik', ['name' => 'Budi Jaringan', 'jabatan' => 'Network Admin']);

    $ticket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diklasifikasi,
    ]);

    // Search by name
    $response = $this->getJson("/api/v1/tickets/{$ticket->id}/assignee-options?search=Ahmad")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($candidate1->id)->not->toContain($candidate2->id);

    // Search by jabatan
    $responseJabatan = $this->getJson("/api/v1/tickets/{$ticket->id}/assignee-options?search=Network")
        ->assertOk();

    $idsJabatan = collect($responseJabatan->json('data'))->pluck('id')->all();
    expect($idsJabatan)->toContain($candidate2->id)->not->toContain($candidate1->id);
});
