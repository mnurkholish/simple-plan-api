<?php

use App\Enums\TicketService;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createTicketReadUser(string $roleName = 'user'): User
{
    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);
    $user = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $user->assignRole($role);

    return $user;
}

function actingAsTicketReadUser(User $user, bool $withPermission = true): void
{
    if ($withPermission) {
        $permission = Permission::firstOrCreate([
            'name' => 'tickets-access',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);
    }

    Sanctum::actingAs($user);
}

function createVisibleTicket(
    User $reporter,
    TicketService $service = TicketService::Tik,
    ?User $assignedOfficer = null,
): Ticket {
    return Ticket::factory()->reportedBy($reporter)->create([
        'service' => $service,
        'assigned_officer_id' => $assignedOfficer?->id,
    ]);
}

test('guest tidak dapat melihat daftar maupun detail tiket', function (): void {
    $reporter = createTicketReadUser();
    $ticket = createVisibleTicket($reporter);

    $this->getJson('/api/v1/tickets')->assertUnauthorized();
    $this->getJson("/api/v1/tickets/{$ticket->id}")->assertUnauthorized();
});

test('user tanpa permission tickets-access tidak dapat melihat daftar maupun detail tiket', function (): void {
    Permission::firstOrCreate([
        'name' => 'tickets-access',
        'guard_name' => 'web',
    ]);
    $user = createTicketReadUser();
    $ticket = createVisibleTicket($user);
    actingAsTicketReadUser($user, withPermission: false);

    $this->getJson('/api/v1/tickets')->assertForbidden();
    $this->getJson("/api/v1/tickets/{$ticket->id}")->assertForbidden();
});

test('semua role dapat melihat tiket yang dibuat sendiri', function (): void {
    foreach ([
        'super-admin',
        'koordinator-sarpras',
        'petugas-tik',
        'petugas-sarpras',
        'user',
        'management',
    ] as $roleName) {
        $reader = createTicketReadUser($roleName);
        $ownTicket = createVisibleTicket($reader, TicketService::Tik);
        actingAsTicketReadUser($reader);

        $response = $this->getJson('/api/v1/tickets')->assertOk();
        expect($response->json('data.*.id'))->toContain($ownTicket->id);
        $this->getJson("/api/v1/tickets/{$ownTicket->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownTicket->id);
    }
});

test('super admin dapat melihat semua tiket', function (): void {
    $reporter = createTicketReadUser();
    $tikTicket = createVisibleTicket($reporter, TicketService::Tik);
    $sarprasTicket = createVisibleTicket($reporter, TicketService::Sarpras);
    $superAdmin = createTicketReadUser('super-admin');
    actingAsTicketReadUser($superAdmin);

    $response = $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
    expect($response->json('data.*.id'))
        ->toEqualCanonicalizing([$tikTicket->id, $sarprasTicket->id]);
    $this->getJson("/api/v1/tickets/{$tikTicket->id}")->assertOk();
    $this->getJson("/api/v1/tickets/{$sarprasTicket->id}")->assertOk();
});

test('manajemen dapat melihat semua tiket', function (): void {
    $reporter = createTicketReadUser();
    $tikTicket = createVisibleTicket($reporter, TicketService::Tik);
    $sarprasTicket = createVisibleTicket($reporter, TicketService::Sarpras);
    $management = createTicketReadUser('management');
    actingAsTicketReadUser($management);

    $response = $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
    expect($response->json('data.*.id'))
        ->toEqualCanonicalizing([$tikTicket->id, $sarprasTicket->id]);
    $this->getJson("/api/v1/tickets/{$tikTicket->id}")->assertOk();
    $this->getJson("/api/v1/tickets/{$sarprasTicket->id}")->assertOk();
});

test('koordinator Sarpras dapat melihat seluruh tiket Sarpras dan tiket TIK miliknya sendiri', function (): void {
    $coordinator = createTicketReadUser('koordinator-sarpras');
    $otherReporter = createTicketReadUser();
    $ownTikTicket = createVisibleTicket($coordinator, TicketService::Tik);
    $sarprasTicket = createVisibleTicket($otherReporter, TicketService::Sarpras);
    $otherTikTicket = createVisibleTicket($otherReporter, TicketService::Tik);
    actingAsTicketReadUser($coordinator);

    $response = $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
    expect($response->json('data.*.id'))
        ->toEqualCanonicalizing([$ownTikTicket->id, $sarprasTicket->id]);
    $this->getJson("/api/v1/tickets/{$ownTikTicket->id}")->assertOk();
    $this->getJson("/api/v1/tickets/{$sarprasTicket->id}")->assertOk();
    $this->getJson("/api/v1/tickets/{$otherTikTicket->id}")->assertForbidden();
});

test('petugas TIK dan Sarpras hanya dapat melihat tiket assigned sesuai layanan dan tiket miliknya sendiri', function (): void {
    foreach ([
        [TicketService::Tik, TicketService::Sarpras, 'petugas-tik'],
        [TicketService::Sarpras, TicketService::Tik, 'petugas-sarpras'],
    ] as [$ownService, $otherService, $roleName]) {
        $officer = createTicketReadUser($roleName);
        $reporter = createTicketReadUser();
        $ownTicket = createVisibleTicket($officer, $otherService);
        $assignedTicket = createVisibleTicket($reporter, $ownService, $officer);
        $unrelatedTicket = createVisibleTicket($reporter, $ownService);
        $wrongServiceTicket = createVisibleTicket($reporter, $otherService, $officer);
        actingAsTicketReadUser($officer);

        $response = $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        expect($response->json('data.*.id'))
            ->toEqualCanonicalizing([$ownTicket->id, $assignedTicket->id]);
        $this->getJson("/api/v1/tickets/{$ownTicket->id}")->assertOk();
        $this->getJson("/api/v1/tickets/{$assignedTicket->id}")->assertOk();
        $this->getJson("/api/v1/tickets/{$unrelatedTicket->id}")->assertForbidden();
        $this->getJson("/api/v1/tickets/{$wrongServiceTicket->id}")->assertForbidden();
    }
});

test('user biasa hanya dapat melihat tiket miliknya sendiri', function (): void {
    $user = createTicketReadUser();
    $otherReporter = createTicketReadUser();
    $ownTicket = createVisibleTicket($user);
    $otherTicket = createVisibleTicket($otherReporter);
    actingAsTicketReadUser($user);

    $response = $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
    expect($response->json('data.*.id'))->toBe([$ownTicket->id]);
    $this->getJson("/api/v1/tickets/{$ownTicket->id}")->assertOk();
    $this->getJson("/api/v1/tickets/{$otherTicket->id}")->assertForbidden();
});

test('endpoint detail tiket mengembalikan 404 ketika tiket tidak ditemukan', function (): void {
    $user = createTicketReadUser();
    actingAsTicketReadUser($user);

    $this->getJson('/api/v1/tickets/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
