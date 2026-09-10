<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('new ticket can be verified', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Baru]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/verify");

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Tiket berhasil diverifikasi.')
        ->assertJsonPath('data.status', TicketStatus::Terverifikasi->value);
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => TicketStatus::Terverifikasi->value,
    ]);
});

test('verify returns 409 when ticket is not new', function (TicketStatus $status): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => $status]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/verify");

    $response
        ->assertConflict()
        ->assertJsonPath('message', 'Hanya tiket berstatus baru yang dapat diverifikasi.');
    expect($ticket->refresh()->status)->toBe($status);
})->with([
    'already verified' => TicketStatus::Terverifikasi,
    'rejected' => TicketStatus::Ditolak,
    'in progress' => TicketStatus::Diproses,
    'completed' => TicketStatus::Selesai,
]);

test('new ticket can be rejected with a reason', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Baru]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/reject", [
        'reason' => 'Informasi kerusakan tidak sesuai.',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Tiket berhasil ditolak.')
        ->assertJsonPath('data.status', TicketStatus::Ditolak->value)
        ->assertJsonPath('data.rejection_reason', 'Informasi kerusakan tidak sesuai.');
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => TicketStatus::Ditolak->value,
        'rejection_reason' => 'Informasi kerusakan tidak sesuai.',
    ]);
});

test('reject returns 422 when reason is missing', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Baru]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/reject");

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
    expect($ticket->refresh()->status)->toBe(TicketStatus::Baru)
        ->and($ticket->rejection_reason)->toBeNull();
});

test('reject returns 409 when ticket is not new', function (TicketStatus $status): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => $status]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/reject", [
        'reason' => 'Tidak dapat diproses.',
    ]);

    $response
        ->assertConflict()
        ->assertJsonPath('message', 'Hanya tiket berstatus baru yang dapat ditolak.');
    expect($ticket->refresh()->status)->toBe($status)
        ->and($ticket->rejection_reason)->toBeNull();
})->with([
    'verified' => TicketStatus::Terverifikasi,
    'already rejected' => TicketStatus::Ditolak,
    'in progress' => TicketStatus::Diproses,
    'completed' => TicketStatus::Selesai,
]);

test('verified ticket can be assigned an active officer and priority without changing status', function (TicketPriority $priority): void {
    Sanctum::actingAs(User::factory()->create());
    $officer = User::factory()->create([
        'name' => 'Petugas Terpilih',
        'status' => 'active',
    ]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Terverifikasi]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'priority' => $priority->value,
        'officer_id' => $officer->id,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Prioritas dan petugas berhasil disimpan.')
        ->assertJsonPath('data.priority', $priority->value)
        ->assertJsonPath('data.assigned_officer.id', $officer->id)
        ->assertJsonPath('data.assigned_officer.name', 'Petugas Terpilih')
        ->assertJsonPath('data.status', TicketStatus::Terverifikasi->value);
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'priority' => $priority->value,
        'assigned_officer_id' => $officer->id,
        'status' => TicketStatus::Terverifikasi->value,
    ]);
})->with([
    'critical' => TicketPriority::Critical,
    'high' => TicketPriority::High,
    'medium' => TicketPriority::Medium,
    'low' => TicketPriority::Low,
]);

test('assign returns 422 when priority or officer is missing', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Terverifikasi]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign");

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority', 'officer_id']);
    expect($ticket->refresh()->priority)->toBeNull()
        ->and($ticket->assigned_officer_id)->toBeNull();
});

test('assign returns 422 for an unsupported priority', function (string $priority): void {
    Sanctum::actingAs(User::factory()->create());
    $officer = User::factory()->create(['status' => 'active']);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Terverifikasi]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'priority' => $priority,
        'officer_id' => $officer->id,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('priority');
    expect($ticket->refresh()->priority)->toBeNull()
        ->and($ticket->assigned_officer_id)->toBeNull();
})->with([
    'unknown value' => 'urgent',
    'wrong casing' => 'Critical',
]);

test('assign returns 422 for an unavailable officer', function (int $officerId): void {
    Sanctum::actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Terverifikasi]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'priority' => TicketPriority::High->value,
        'officer_id' => $officerId,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('officer_id');
    expect($ticket->refresh()->priority)->toBeNull()
        ->and($ticket->assigned_officer_id)->toBeNull();
})->with([
    'unknown user' => 999999,
    'inactive user' => fn (): int => User::factory()->create(['status' => 'inactive'])->id,
    'suspended user' => fn (): int => User::factory()->create(['status' => 'suspended'])->id,
]);

test('assign returns 409 when ticket is not verified', function (TicketStatus $status): void {
    Sanctum::actingAs(User::factory()->create());
    $officer = User::factory()->create(['status' => 'active']);
    $ticket = Ticket::factory()->create(['status' => $status]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'priority' => TicketPriority::Low->value,
        'officer_id' => $officer->id,
    ]);

    $response
        ->assertConflict()
        ->assertJsonPath('message', 'Hanya tiket berstatus terverifikasi yang dapat diberi prioritas dan petugas.');
    expect($ticket->refresh()->status)->toBe($status)
        ->and($ticket->priority)->toBeNull()
        ->and($ticket->assigned_officer_id)->toBeNull();
})->with([
    'new' => TicketStatus::Baru,
    'rejected' => TicketStatus::Ditolak,
    'in progress' => TicketStatus::Diproses,
    'completed' => TicketStatus::Selesai,
]);

test('guest cannot use ticket workflow endpoints', function (string $endpoint, array $payload): void {
    $ticket = Ticket::factory()->create();

    $this->postJson("/api/v1/tickets/{$ticket->id}/{$endpoint}", $payload)
        ->assertUnauthorized();
})->with([
    'verify' => ['verify', []],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.']],
    'assign' => ['assign', ['priority' => 'high', 'officer_id' => 1]],
]);

test('ticket workflow endpoint returns 404 when ticket does not exist', function (string $endpoint, array $payload): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/999999/{$endpoint}", $payload)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
})->with([
    'verify' => ['verify', []],
    'reject' => ['reject', ['reason' => 'Tidak dapat diproses.']],
    'assign' => ['assign', ['priority' => 'high', 'officer_id' => 999999]],
]);
