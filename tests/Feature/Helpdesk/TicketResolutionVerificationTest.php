<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate([
        'name' => 'user',
        'guard_name' => 'web',
    ]);
    $reporter = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $reporter->assignRole($role);
});

test('reporter verification closes a completed ticket directly and records history', function (): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);
    Sanctum::actingAs($reporter);

    try {
        Carbon::setTestNow('2026-09-26 10:30:00');
        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
            'is_approved' => true,
        ]);
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Penyelesaian tiket berhasil diverifikasi dan tiket ditutup.')
        ->assertJsonPath('data.status', TicketStatus::Ditutup->value)
        ->assertJsonPath('data.closed_at', '2026-09-26T10:30:00.000000Z');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Ditutup)
        ->and($ticket->closed_at?->format('Y-m-d H:i:s'))->toBe('2026-09-26 10:30:00');
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Terselesaikan->value,
        'to_status' => TicketStatus::Ditutup->value,
        'changed_by_id' => $reporter->id,
    ]);
    $this->assertDatabaseMissing('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'to_status' => TicketStatus::Terverifikasi->value,
    ]);
});

test('a user other than the reporter cannot verify resolution', function (): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", ['is_approved' => true])
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Terselesaikan)
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('reporter cannot verify a ticket outside completed status', function (TicketStatus $status): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => $status]);
    Sanctum::actingAs($reporter);

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", ['is_approved' => true])
        ->assertConflict()
        ->assertJsonPath(
            'message',
            "Status tiket tidak dapat diubah dari {$status->value} menjadi ditutup.",
        );

    expect($ticket->refresh()->status)->toBe($status)
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->statusHistories()->count())->toBe(0);
})->with([
    'classified' => TicketStatus::Diklasifikasi,
    'in progress' => TicketStatus::Diproses,
    'closed' => TicketStatus::Ditutup,
]);

test('guest cannot verify a completed ticket', function (): void {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Terselesaikan]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", ['is_approved' => true])
        ->assertUnauthorized();
});

test('verification endpoint returns 404 when ticket does not exist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/tickets/999999/verify', ['is_approved' => true])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('reporter can reject a completed ticket with an issue description', function (): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);
    Sanctum::actingAs($reporter);

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => false,
        'keterangan_kendala' => 'Masalah masih terjadi setelah penanganan.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Diproses->value);

    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Terselesaikan->value,
        'to_status' => TicketStatus::Diproses->value,
        'changed_by_id' => $reporter->id,
        'notes' => 'Masalah masih terjadi setelah penanganan.',
    ]);
});

test('verification requires an approval decision', function (): void {
    $reporter = User::factory()->for(Unit::factory())->create();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);
    Sanctum::actingAs($reporter);

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_approved');
});
