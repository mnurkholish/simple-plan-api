<?php

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $reporter = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $reporter->assignRole($role);
});

function actingAsTicketEscalationUser(
    ?Ticket $ticket = null,
    ?string $roleName = null,
    bool $withPermission = true,
): User {
    $user = User::factory()->create();

    if ($roleName !== null) {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $user->assignRole($role);
    }

    if ($withPermission) {
        $permission = Permission::firstOrCreate([
            'name' => 'tickets-handle',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);
    }

    $ticket?->update(['assigned_officer_id' => $user->id]);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return array{target: string, notes: string}
 */
function validTicketEscalationPayload(): array
{
    return [
        'target' => 'Vendor',
        'notes' => 'Membutuhkan pemeriksaan dari vendor.',
    ];
}

function prepareEscalationNotifications(): void
{
    Notification::fake();
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']);
}

test('guest tidak dapat mengeskalasi atau melanjutkan tiket', function (): void {
    $processedTicket = Ticket::factory()->create(['status' => TicketStatus::Diproses]);
    $escalatedTicket = Ticket::factory()->create(['status' => TicketStatus::Eskalasi]);

    $this->postJson(
        "/api/v1/tickets/{$processedTicket->id}/escalate",
        validTicketEscalationPayload(),
    )->assertUnauthorized();
    $this->postJson("/api/v1/tickets/{$escalatedTicket->id}/de-escalate")
        ->assertUnauthorized();
});

test('user tanpa permission tickets-handle tidak dapat mengeskalasi atau melanjutkan tiket', function (): void {
    Permission::firstOrCreate([
        'name' => 'tickets-handle',
        'guard_name' => 'web',
    ]);
    $processedTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
    ]);
    $escalatedTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Eskalasi,
    ]);
    $handler = actingAsTicketEscalationUser(
        $processedTicket,
        'petugas-tik',
        withPermission: false,
    );
    $escalatedTicket->update(['assigned_officer_id' => $handler->id]);

    $this->postJson(
        "/api/v1/tickets/{$processedTicket->id}/escalate",
        validTicketEscalationPayload(),
    )->assertForbidden();
    $this->postJson("/api/v1/tickets/{$escalatedTicket->id}/de-escalate")
        ->assertForbidden();
});

test('super admin dapat mengeskalasi dan melanjutkan tiket TIK dan Sarpras tanpa harus ditugaskan', function (): void {
    prepareEscalationNotifications();
    $assignedOfficer = User::factory()->create();
    $superAdmin = actingAsTicketEscalationUser(roleName: 'super-admin');

    foreach ([TicketService::Tik, TicketService::Sarpras] as $service) {
        $ticket = Ticket::factory()->create([
            'service' => $service,
            'status' => TicketStatus::Diproses,
            'assigned_officer_id' => $assignedOfficer->id,
        ]);

        $this->postJson(
            "/api/v1/tickets/{$ticket->id}/escalate",
            validTicketEscalationPayload(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::Eskalasi->value);
        $this->postJson("/api/v1/tickets/{$ticket->id}/de-escalate")
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::Diproses->value);

        $this->assertDatabaseHas('ticket_escalations', [
            'ticket_id' => $ticket->id,
            'escalated_by_id' => $superAdmin->id,
        ]);
    }
});

test('koordinator Sarpras dapat mengeskalasi dan melanjutkan tiket Sarpras tanpa harus ditugaskan', function (): void {
    prepareEscalationNotifications();
    $assignedOfficer = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'service' => TicketService::Sarpras,
        'status' => TicketStatus::Diproses,
        'assigned_officer_id' => $assignedOfficer->id,
    ]);
    $coordinator = actingAsTicketEscalationUser(roleName: 'koordinator-sarpras');

    $this->postJson(
        "/api/v1/tickets/{$ticket->id}/escalate",
        validTicketEscalationPayload(),
    )->assertOk();
    $this->postJson("/api/v1/tickets/{$ticket->id}/de-escalate")
        ->assertOk();

    $this->assertDatabaseHas('ticket_escalations', [
        'ticket_id' => $ticket->id,
        'escalated_by_id' => $coordinator->id,
    ]);
});

test('koordinator Sarpras tidak dapat mengeskalasi atau melanjutkan tiket TIK', function (): void {
    $processedTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Diproses,
    ]);
    $escalatedTicket = Ticket::factory()->create([
        'service' => TicketService::Tik,
        'status' => TicketStatus::Eskalasi,
    ]);
    actingAsTicketEscalationUser(roleName: 'koordinator-sarpras');

    $this->postJson(
        "/api/v1/tickets/{$processedTicket->id}/escalate",
        validTicketEscalationPayload(),
    )->assertForbidden();
    $this->postJson("/api/v1/tickets/{$escalatedTicket->id}/de-escalate")
        ->assertForbidden();
});

test('petugas TIK dan Sarpras hanya dapat mengeskalasi tiket yang ditugaskan sesuai layanan masing-masing', function (): void {
    prepareEscalationNotifications();

    foreach ([
        [TicketService::Tik, TicketService::Sarpras, 'petugas-tik'],
        [TicketService::Sarpras, TicketService::Tik, 'petugas-sarpras'],
    ] as [$ownService, $otherService, $roleName]) {
        $assignedTicket = Ticket::factory()->create([
            'service' => $ownService,
            'status' => TicketStatus::Diproses,
        ]);
        $otherServiceTicket = Ticket::factory()->create([
            'service' => $otherService,
            'status' => TicketStatus::Diproses,
        ]);
        $unassignedTicket = Ticket::factory()->create([
            'service' => $ownService,
            'status' => TicketStatus::Diproses,
            'assigned_officer_id' => User::factory(),
        ]);
        $handler = actingAsTicketEscalationUser($assignedTicket, $roleName);
        $otherServiceTicket->update(['assigned_officer_id' => $handler->id]);

        $this->postJson(
            "/api/v1/tickets/{$assignedTicket->id}/escalate",
            validTicketEscalationPayload(),
        )->assertOk();
        $this->postJson("/api/v1/tickets/{$assignedTicket->id}/de-escalate")
            ->assertOk();
        $this->postJson(
            "/api/v1/tickets/{$otherServiceTicket->id}/escalate",
            validTicketEscalationPayload(),
        )->assertForbidden();
        $this->postJson(
            "/api/v1/tickets/{$unassignedTicket->id}/escalate",
            validTicketEscalationPayload(),
        )->assertForbidden();
    }
});

test('endpoint eskalasi dan de-eskalasi mengembalikan 404 ketika tiket tidak ditemukan', function (): void {
    actingAsTicketEscalationUser();

    $this->postJson(
        '/api/v1/tickets/999999/escalate',
        validTicketEscalationPayload(),
    )
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);

    $this->postJson(
        '/api/v1/tickets/999999/de-escalate',
        validTicketEscalationPayload(),
    )
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
