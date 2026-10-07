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

function createTicketResolutionReporter(): User
{
    $role = Role::firstOrCreate([
        'name' => 'user',
        'guard_name' => 'web',
    ]);
    $reporter = User::factory()->for(Unit::factory())->create([
        'status' => 'active',
        'status_user' => 'Aktif',
    ]);
    $reporter->assignRole($role);

    return $reporter;
}

test('guest tidak dapat memverifikasi penyelesaian tiket', function (): void {
    $reporter = createTicketResolutionReporter();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => true,
    ])->assertUnauthorized();
});

test('reporter dapat memverifikasi penyelesaian tiket', function (): void {
    $reporter = createTicketResolutionReporter();
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
});

test('user selain reporter tidak dapat memverifikasi penyelesaian tiket', function (): void {
    $reporter = createTicketResolutionReporter();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => true,
    ])->assertForbidden();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Terselesaikan)
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->statusHistories()->count())->toBe(0);
});

test('reporter dapat menolak penyelesaian dan wajib mengisi keterangan kendala', function (): void {
    $reporter = createTicketResolutionReporter();
    $ticket = Ticket::factory()
        ->reportedBy($reporter)
        ->create(['status' => TicketStatus::Terselesaikan]);
    Sanctum::actingAs($reporter);

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => false,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('keterangan_kendala');

    $this->postJson("/api/v1/tickets/{$ticket->id}/verify", [
        'is_approved' => false,
        'keterangan_kendala' => 'Masalah masih terjadi setelah penanganan.',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Verifikasi ditolak, tiket dikembalikan ke status Diproses.')
        ->assertJsonPath('data.status', TicketStatus::Diproses->value);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Diproses)
        ->and($ticket->closed_at)->toBeNull();
    $this->assertDatabaseHas('ticket_status_histories', [
        'ticket_id' => $ticket->id,
        'from_status' => TicketStatus::Terselesaikan->value,
        'to_status' => TicketStatus::Diproses->value,
        'changed_by_id' => $reporter->id,
        'notes' => 'Masalah masih terjadi setelah penanganan.',
    ]);
});

test('endpoint verifikasi penyelesaian mengembalikan 404 ketika tiket tidak ditemukan', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/tickets/999999/verify', [
        'is_approved' => true,
    ])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
